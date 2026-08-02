<?php

if (PHP_SAPI !== 'cli') {
    echo "This script must be run from CLI.\n";
    exit(1);
}

define('BASE_PATH', dirname(__DIR__));
define('STORAGE_PATH', BASE_PATH . DIRECTORY_SEPARATOR . 'storage');

require_once BASE_PATH . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Support' . DIRECTORY_SEPARATOR . 'helpers.php';

$envFile = BASE_PATH . DIRECTORY_SEPARATOR . '.env';
if (is_file($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }
        [$name, $value] = array_map('trim', explode('=', $line, 2));
        putenv($name . '=' . $value);
        $_ENV[$name] = $value;
    }
}

require_once BASE_PATH . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Core' . DIRECTORY_SEPARATOR . 'Database.php';

$dbConfig = require BASE_PATH . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'database.php';
$pdo = (new App\Core\Database($dbConfig))->connection();

$pdo->exec("CREATE TABLE IF NOT EXISTS `schema_migrations` (
    `id` INT UNSIGNED AUTO_INCREMENT,
    `migration` VARCHAR(255) NOT NULL,
    `checksum` VARCHAR(64) NOT NULL,
    `executed_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_migration` (`migration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$migrationsDir = BASE_PATH . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'migrations';
$files = glob($migrationsDir . DIRECTORY_SEPARATOR . '*.sql') ?: [];
sort($files, SORT_STRING);

$insert = $pdo->prepare(
    'INSERT INTO schema_migrations (migration, checksum)
     VALUES (:migration, :checksum)
     ON DUPLICATE KEY UPDATE checksum = VALUES(checksum)'
);

$count = 0;
foreach ($files as $file) {
    $sql = file_get_contents($file);
    if ($sql === false) {
        fwrite(STDERR, 'Cannot read migration: ' . basename($file) . PHP_EOL);
        exit(1);
    }

    $insert->execute([
        ':migration' => basename($file),
        ':checksum' => hash('sha256', $sql),
    ]);
    $count++;
}

echo "Baseline recorded for {$count} migration(s)." . PHP_EOL;
