<?php

if (php_sapi_name() !== 'cli') {
    echo "This script must be run from CLI.\n";
    exit(1);
}

define('BASE_PATH', dirname(__DIR__));
define('STORAGE_PATH', BASE_PATH . DIRECTORY_SEPARATOR . 'storage');
require_once BASE_PATH . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Support' . DIRECTORY_SEPARATOR . 'helpers.php';

$envFile = BASE_PATH . DIRECTORY_SEPARATOR . '.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        $parts = explode('=', $line, 2);
        if (count($parts) === 2) {
            $name  = trim($parts[0]);
            $value = trim($parts[1]);
            putenv("$name=$value");
            $_ENV[$name] = $value;
        }
    }
}

$dbConfig = require BASE_PATH . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'database.php';

require_once BASE_PATH . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Core' . DIRECTORY_SEPARATOR . 'Database.php';

use App\Core\Database;

$db = new Database($dbConfig);

try {
    $pdo = $db->connection();
} catch (RuntimeException $e) {
    echo "Database connection failed. Check your .env configuration.\n";
    exit(1);
}

$pdo->exec("CREATE TABLE IF NOT EXISTS `schema_migrations` (
    `id` INT UNSIGNED AUTO_INCREMENT,
    `migration` VARCHAR(255) NOT NULL,
    `checksum` VARCHAR(64) NOT NULL,
    `executed_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_migration` (`migration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$migrationsDir = BASE_PATH . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'migrations';
$files = glob($migrationsDir . DIRECTORY_SEPARATOR . '*.sql');

if ($files === false || count($files) === 0) {
    echo "=== ERP PLANEX Migration Runner ===\n";
    echo "No migration files found in database/migrations/\n";
    exit(0);
}

sort($files, SORT_STRING);

$migrationNames = array_map('basename', $files);

$stmt = $pdo->query("SELECT migration, checksum FROM schema_migrations ORDER BY id");
$applied = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

$pending = [];
$skipped = 0;

foreach ($migrationNames as $name) {
    if (array_key_exists($name, $applied)) {
        $skipped++;
    } else {
        $pending[] = $name;
    }
}

$total = count($migrationNames);

echo "=== ERP PLANEX Migration Runner ===\n";
echo sprintf("Found %d migration(s), %d already applied, %d pending.\n\n", $total, $skipped, count($pending));

if (count($pending) === 0) {
    foreach ($migrationNames as $name) {
        echo sprintf("[SKIP] %s - already applied\n", $name);
    }
    echo sprintf("\nDone: 0 applied, %d skipped, 0 failed.\n", $total);
    exit(0);
}

$appliedCount = 0;
$failed = false;

foreach ($pending as $name) {
    $filePath = $migrationsDir . DIRECTORY_SEPARATOR . $name;
    $sql = file_get_contents($filePath);

    if ($sql === false) {
        echo sprintf("[FAIL] %s - unable to read file\n", $name);
        $failed = true;
        break;
    }

    $checksum = hash('sha256', $sql);

    try {
        $pdo->exec($sql);
    } catch (PDOException $e) {
        $errorMsg = $e->getMessage();
        echo sprintf("[FAIL] %s - %s\n", $name, $errorMsg);
        $failed = true;
        break;
    }

    $insert = $pdo->prepare("INSERT INTO schema_migrations (migration, checksum) VALUES (:migration, :checksum)");
    $insert->execute([':migration' => $name, ':checksum' => $checksum]);

    echo sprintf("[OK] %s\n", $name);
    $appliedCount++;
}

if ($failed) {
    echo sprintf("\nDone: %d applied, %d skipped, 1 failed.\n", $appliedCount, $skipped);
    exit(1);
}

foreach ($migrationNames as $name) {
    if (!in_array($name, $pending, true)) {
        echo sprintf("[SKIP] %s - already applied\n", $name);
    }
}

$failCount = 0;
echo sprintf("\nDone: %d applied, %d skipped, %d failed.\n", $appliedCount, $skipped, $failCount);
exit(0);
