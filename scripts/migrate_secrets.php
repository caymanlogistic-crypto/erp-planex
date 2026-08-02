<?php

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must be run from CLI.\n");
    exit(1);
}

define('BASE_PATH', dirname(__DIR__));
define('STORAGE_PATH', BASE_PATH . DIRECTORY_SEPARATOR . 'storage');

require_once BASE_PATH . '/app/Support/helpers.php';

$envFile = BASE_PATH . '/.env';
if (is_file($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        $parts = explode('=', $line, 2);
        if (count($parts) === 2) {
            putenv(trim($parts[0]) . '=' . trim($parts[1]));
        }
    }
}

require_once BASE_PATH . '/app/Core/Database.php';
require_once BASE_PATH . '/app/Support/crypto_helper.php';
require_once BASE_PATH . '/app/Support/company_database.php';

$dbConfig = require BASE_PATH . '/config/database.php';

try {
    if (!env('APP_ENCRYPTION_KEY')) {
        throw new RuntimeException('APP_ENCRYPTION_KEY is required.');
    }

    $central = new \App\Core\Database($dbConfig);
    $centralPdo = $central->connection();
    $companies = $centralPdo->query(
        "SELECT id, name, db_identifier, db_host, db_port, db_username, db_password
           FROM companies
          WHERE db_identifier IS NOT NULL AND db_identifier <> ''
          ORDER BY id"
    )->fetchAll(PDO::FETCH_ASSOC);

    $dbPasswordsUpdated = 0;
    $imapPasswordsUpdated = 0;

    foreach ($companies as $company) {
        $connectionConfig = companyDatabaseConfig(['database' => $dbConfig], $company);

        $storedDbPassword = (string) ($company['db_password'] ?? '');
        if ($storedDbPassword !== '' && !str_starts_with($storedDbPassword, 'v2:')) {
            $encrypted = companyDbStorePassword($storedDbPassword);
            $stmt = $centralPdo->prepare('UPDATE companies SET db_password = ? WHERE id = ? AND db_password = ?');
            $stmt->execute([$encrypted, (int) $company['id'], $storedDbPassword]);
            $dbPasswordsUpdated += $stmt->rowCount();
        }

        try {
            $local = new \App\Core\Database($connectionConfig);
            $localPdo = $local->connection();
            $settings = $localPdo->query(
                "SELECT id, password_encrypted FROM bank_statement_settings WHERE password_encrypted <> ''"
            )->fetchAll(PDO::FETCH_ASSOC);

            foreach ($settings as $setting) {
                $stored = (string) $setting['password_encrypted'];
                if (str_starts_with($stored, 'v2:')) {
                    continue;
                }
                $plaintext = cryptoDecrypt($stored);
                $encrypted = cryptoEncrypt($plaintext);
                $stmt = $localPdo->prepare(
                    'UPDATE bank_statement_settings SET password_encrypted = ? WHERE id = ? AND password_encrypted = ?'
                );
                $stmt->execute([$encrypted, (int) $setting['id'], $stored]);
                $imapPasswordsUpdated += $stmt->rowCount();
            }
        } catch (Throwable $e) {
            fwrite(STDERR, 'Company #' . (int) $company['id'] . ': local secret migration skipped: ' . $e->getMessage() . "\n");
        }
    }

    echo "Database passwords updated: {$dbPasswordsUpdated}\n";
    echo "IMAP passwords updated: {$imapPasswordsUpdated}\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'Secret migration failed: ' . $e->getMessage() . "\n");
    exit(1);
}
