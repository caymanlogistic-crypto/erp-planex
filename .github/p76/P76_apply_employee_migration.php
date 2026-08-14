<?php

$root = rtrim((string)($argv[1] ?? ''), '/');
if ($root === '' || !is_dir($root)) {
    throw new RuntimeException('Live ERP root is missing.');
}
chdir($root);
require_once $root . '/app/Support/helpers.php';
require_once $root . '/app/Support/environment.php';
loadEnvFileNonOverwriting($root . '/.env');
$config = require $root . '/bootstrap/app.php';
require_once $root . '/app/Support/entrypoint_dependencies.php';

$migrationName = '065_finance_employee_movements.sql';
$migrationPath = $root . '/database/migrations-local/' . $migrationName;
$sql = file_get_contents($migrationPath);
if ($sql === false || trim($sql) === '') {
    throw new RuntimeException('Migration 065 is missing or empty in deployed ERP.');
}
$checksum = hash('sha256', $sql);
$central = (new \App\Core\Database($config['database']))->connection();
$companies = $central->query("SELECT * FROM companies WHERE status='active' ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
$applied = 0;
$existing = 0;
$verified = 0;

foreach ($companies as $company) {
    $local = (new \App\Core\Database(companyDatabaseConfig($config, $company)))->connection();
    $local->exec("CREATE TABLE IF NOT EXISTS `schema_migrations` (`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,`migration` VARCHAR(255) NOT NULL,`checksum` VARCHAR(64) NOT NULL,`executed_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY (`id`),UNIQUE KEY `uk_local_migration` (`migration`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $dbName = (string)$local->query('SELECT DATABASE()')->fetchColumn();
    $lockName = 'planex_p76_' . substr(hash('sha256', $dbName), 0, 32);
    $lock = $local->prepare('SELECT GET_LOCK(?,10)');
    $lock->execute([$lockName]);
    if ((int)$lock->fetchColumn() !== 1) {
        throw new RuntimeException('Cannot acquire migration lock for company ' . (int)$company['id']);
    }
    try {
        $stmt = $local->prepare('SELECT checksum FROM schema_migrations WHERE migration=? LIMIT 1');
        $stmt->execute([$migrationName]);
        $stored = $stmt->fetchColumn();
        if ($stored !== false) {
            if (!hash_equals((string)$stored, $checksum)) {
                throw new RuntimeException('Migration 065 checksum mismatch for company ' . (int)$company['id']);
            }
            $existing++;
        } else {
            $local->exec($sql);
            $insert = $local->prepare('INSERT INTO schema_migrations (migration,checksum) VALUES (?,?)');
            $insert->execute([$migrationName,$checksum]);
            $applied++;
        }

        $table = $local->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='finance_employee_movements'");
        if ((int)$table->fetchColumn() !== 1) throw new RuntimeException('finance_employee_movements missing for company ' . (int)$company['id']);
        $columns = ['employee_user_id','movement_type','source_type','finance_operation_id','bank_transaction_id'];
        $columnCheck = $local->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='finance_employee_movements' AND COLUMN_NAME=?");
        foreach ($columns as $column) {
            $columnCheck->execute([$column]);
            if ((int)$columnCheck->fetchColumn() !== 1) throw new RuntimeException('Missing '.$column.' for company '.(int)$company['id']);
        }
        $indexCheck = $local->prepare("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='finance_employee_movements' AND INDEX_NAME=?");
        foreach (['uk_fem_finance_operation','uk_fem_bank_transaction'] as $indexName) {
            $indexCheck->execute([$indexName]);
            if ((int)$indexCheck->fetchColumn() < 1) throw new RuntimeException('Missing '.$indexName.' for company '.(int)$company['id']);
        }
        $rows = (int)$local->query('SELECT COUNT(*) FROM finance_employee_movements')->fetchColumn();
        if ($rows !== 0) throw new RuntimeException('Unexpected pre-existing employee movement rows for company ' . (int)$company['id']);
        $verified++;
    } finally {
        $release = $local->prepare('SELECT RELEASE_LOCK(?)');
        $release->execute([$lockName]);
    }
}

printf("P76_EMPLOYEE_MIGRATION_OK companies=%d applied=%d existing=%d verified=%d checksum=%s\n", count($companies), $applied, $existing, $verified, $checksum);
