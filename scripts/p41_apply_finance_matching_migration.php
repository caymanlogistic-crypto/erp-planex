<?php

require_once __DIR__ . '/../app/Support/helpers.php';
require_once __DIR__ . '/../app/Support/environment.php';
loadEnvFileNonOverwriting(dirname(__DIR__) . '/.env');
$config = require __DIR__ . '/../bootstrap/app.php';
require_once base_path('app/Support/entrypoint_dependencies.php');

$migrationNames = [
    '063_finance_matching_classification.sql',
    '066_finance_employee_identity.sql',
];
$migrations = [];
foreach ($migrationNames as $migrationName) {
    $sql = file_get_contents(base_path('database/migrations-local/' . $migrationName));
    if ($sql === false || trim($sql) === '') {
        throw new RuntimeException('P41 migration SQL is missing or empty: ' . $migrationName);
    }
    $migrations[$migrationName] = ['sql' => $sql, 'checksum' => hash('sha256', $sql)];
}

$db = new \App\Core\Database($config['database']);
$pdo = $db->connection();
$companies = $pdo->query("SELECT * FROM companies WHERE status = 'active' ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
$count = 0;
$appliedCount = 0;
$existingCount = 0;

foreach ($companies as $company) {
    $localDb = new \App\Core\Database(companyDatabaseConfig($config, $company));
    $local = $localDb->connection();
    $local->exec("CREATE TABLE IF NOT EXISTS `schema_migrations` (`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,`migration` VARCHAR(255) NOT NULL,`checksum` VARCHAR(64) NOT NULL,`executed_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY (`id`),UNIQUE KEY `uk_local_migration` (`migration`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $databaseName = (string)$local->query('SELECT DATABASE()')->fetchColumn();
    $lockName = 'planex_p41_' . substr(hash('sha256', $databaseName), 0, 32);
    $lock = $local->prepare('SELECT GET_LOCK(?, 10)');
    $lock->execute([$lockName]);
    if ((int)$lock->fetchColumn() !== 1) throw new RuntimeException('Cannot acquire P41 migration lock.');

    try {
        foreach ($migrations as $migrationName => $migration) {
            $stmt = $local->prepare('SELECT checksum FROM schema_migrations WHERE migration = ? LIMIT 1');
            $stmt->execute([$migrationName]);
            $stored = $stmt->fetchColumn();
            if ($stored !== false) {
                if (!hash_equals((string)$stored, $migration['checksum'])) {
                    throw new RuntimeException('P41 migration checksum mismatch in company ' . (int)$company['id'] . ': ' . $migrationName);
                }
                $existingCount++;
            } else {
                $local->exec($migration['sql']);
                $insert = $local->prepare('INSERT INTO schema_migrations (migration, checksum) VALUES (?, ?)');
                $insert->execute([$migrationName, $migration['checksum']]);
                $appliedCount++;
            }
        }

        $columns = [
            ['bank_transactions', 'classification_status'],
            ['finance_operations', 'classification_status'],
            ['finance_employee_movements', 'employee_identity_type'],
            ['finance_employee_movements', 'employee_identity_id'],
            ['finance_employee_movements', 'employee_name_snapshot'],
            ['finance_employee_movements', 'employee_role_snapshot'],
        ];
        $columnCheck = $local->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
        foreach ($columns as $column) {
            $columnCheck->execute([$column[0], $column[1]]);
            if ((int)$columnCheck->fetchColumn() !== 1) throw new RuntimeException('Schema verification failed: ' . $column[0] . '.' . $column[1]);
        }
    } finally {
        $release = $local->prepare('SELECT RELEASE_LOCK(?)');
        $release->execute([$lockName]);
    }
    $count++;
}

printf("P41_FINANCE_MIGRATION_OK companies=%d applied=%d existing=%d migrations=%s\n",$count,$appliedCount,$existingCount,implode(',',array_keys($migrations)));
