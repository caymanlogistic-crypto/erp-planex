<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only.\n");
    exit(1);
}

$root = rtrim((string)(getenv('PLANEX_ROOT') ?: ($argv[1] ?? '')), '/');
if ($root === '' || !is_file($root . '/bootstrap/app.php')) {
    throw new RuntimeException('PLANEX_ROOT is invalid.');
}

require_once $root . '/app/Support/helpers.php';
require_once $root . '/app/Support/environment.php';
loadEnvFileNonOverwriting($root . '/.env');
$config = require $root . '/bootstrap/app.php';
require_once $root . '/app/Support/entrypoint_dependencies.php';

$migrationName = '076_finance_employee_money_accounts.sql';
$migrationPath = $root . '/database/migrations-local/' . $migrationName;
$sql = file_get_contents($migrationPath);
if ($sql === false || trim($sql) === '') {
    throw new RuntimeException('Migration 076 is missing or empty.');
}
$checksum = hash('sha256', $sql);

$central = (new \App\Core\Database($config['database']))->connection();
$companies = $central->query("SELECT * FROM companies WHERE status='active' ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
if (!$companies) {
    throw new RuntimeException('No active companies found.');
}

$applied = 0;
$existing = 0;
foreach ($companies as $company) {
    $companyId = (int)($company['id'] ?? 0);
    if ($companyId <= 0) throw new RuntimeException('Invalid active company id.');
    $pdo = (new \App\Core\Database(companyDatabaseConfig($config, $company)))->connection();
    $dbName = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
    if ($dbName === '') throw new RuntimeException('Cannot resolve tenant database for company ' . $companyId);

    $pdo->exec("CREATE TABLE IF NOT EXISTS schema_migrations (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        migration VARCHAR(255) NOT NULL,
        checksum VARCHAR(64) NOT NULL,
        executed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uk_local_migration (migration)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $lockName = 'planex_finance_direct_076_' . substr(hash('sha256', $dbName), 0, 24);
    $lock = $pdo->prepare('SELECT GET_LOCK(?,10)');
    $lock->execute([$lockName]);
    if ((int)$lock->fetchColumn() !== 1) {
        throw new RuntimeException('Cannot acquire migration lock for company ' . $companyId);
    }

    try {
        $stmt = $pdo->prepare('SELECT checksum FROM schema_migrations WHERE migration=? LIMIT 1');
        $stmt->execute([$migrationName]);
        $stored = $stmt->fetchColumn();
        if ($stored !== false) {
            if (!hash_equals((string)$stored, $checksum)) {
                throw new RuntimeException('Migration 076 checksum mismatch for company ' . $companyId);
            }
            $existing++;
        } else {
            // Fail closed if a newer migration is already recorded while 076 is absent.
            $newer = $pdo->prepare("SELECT COUNT(*) FROM schema_migrations WHERE migration REGEXP '^[0-9]{3}_' AND CAST(LEFT(migration,3) AS UNSIGNED)>76");
            $newer->execute();
            if ((int)$newer->fetchColumn() !== 0) {
                throw new RuntimeException('Newer tenant migrations exist while 076 is absent for company ' . $companyId);
            }
            $pdo->exec($sql);
            $insert = $pdo->prepare('INSERT INTO schema_migrations (migration,checksum) VALUES (?,?)');
            $insert->execute([$migrationName, $checksum]);
            $applied++;
        }

        $table = $pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='finance_employee_money_accounts'");
        if ((int)$table->fetchColumn() !== 1) {
            throw new RuntimeException('Employee money account table missing after migration for company ' . $companyId);
        }
        $columns = $pdo->query("SELECT COUNT(DISTINCT COLUMN_NAME) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='finance_employee_money_accounts' AND COLUMN_NAME IN ('money_account_id','employee_identity_type','employee_identity_id','employee_name_snapshot','is_active')");
        if ((int)$columns->fetchColumn() !== 5) {
            throw new RuntimeException('Employee money account table shape invalid for company ' . $companyId);
        }
        printf("FINANCE_DIRECT_076_COMPANY_OK id=%d db=%s state=%s\n", $companyId, $dbName, $stored === false ? 'applied' : 'existing');
    } finally {
        $release = $pdo->prepare('SELECT RELEASE_LOCK(?)');
        $release->execute([$lockName]);
    }
}

printf("FINANCE_DIRECT_076_OK applied=%d existing=%d companies=%d checksum=%s\n", $applied, $existing, count($companies), $checksum);
