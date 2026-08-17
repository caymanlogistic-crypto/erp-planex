<?php

require_once __DIR__ . '/../app/Support/helpers.php';
require_once __DIR__ . '/../app/Support/environment.php';
loadEnvFileNonOverwriting(dirname(__DIR__) . '/.env');
$config = require __DIR__ . '/../bootstrap/app.php';
require_once base_path('app/Support/entrypoint_dependencies.php');

$migrationName = '075_employee_invoice_payment_events.sql';
$migrationPath = base_path('database/migrations-local/' . $migrationName);
$sql = file_get_contents($migrationPath);
if ($sql === false || trim($sql) === '') throw new RuntimeException('P99 migration SQL is missing or empty: ' . $migrationName);
$checksum = hash('sha256', $sql);

$central = (new \App\Core\Database($config['database']))->connection();
$companies = $central->query("SELECT * FROM companies WHERE status='active' ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
$companyCount = 0;
$appliedCount = 0;
$existingCount = 0;

foreach ($companies as $company) {
    $local = (new \App\Core\Database(companyDatabaseConfig($config, $company)))->connection();
    $local->exec("CREATE TABLE IF NOT EXISTS schema_migrations (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        migration VARCHAR(255) NOT NULL,
        checksum VARCHAR(64) NOT NULL,
        executed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY(id), UNIQUE KEY uk_local_migration(migration)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $databaseName = (string)$local->query('SELECT DATABASE()')->fetchColumn();
    if ($databaseName === '') throw new RuntimeException('P99 cannot resolve tenant database for company ' . (int)$company['id']);
    $lockName = 'planex_p99_' . substr(hash('sha256', $databaseName), 0, 32);
    $lock = $local->prepare('SELECT GET_LOCK(?,10)');
    $lock->execute([$lockName]);
    if ((int)$lock->fetchColumn() !== 1) throw new RuntimeException('Cannot acquire P99 migration lock for company ' . (int)$company['id']);

    try {
        $stmt = $local->prepare('SELECT checksum FROM schema_migrations WHERE migration=? LIMIT 1');
        $stmt->execute([$migrationName]);
        $stored = $stmt->fetchColumn();
        if ($stored !== false) {
            if (!hash_equals((string)$stored, $checksum)) throw new RuntimeException('P99 migration checksum mismatch in company ' . (int)$company['id']);
            $existingCount++;
        } else {
            try {
                $local->exec($sql);
            } catch (Throwable $e) {
                throw new RuntimeException('P99 migration failed in company ' . (int)$company['id'] . ': ' . $e->getMessage(), 0, $e);
            }
            $insert = $local->prepare('INSERT INTO schema_migrations (migration,checksum) VALUES (?,?)');
            $insert->execute([$migrationName, $checksum]);
            $appliedCount++;
        }

        $tableCheck = $local->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='finance_employee_invoice_payments'");
        if ((int)$tableCheck->fetchColumn() !== 1) throw new RuntimeException('P99 schema verification failed in company ' . (int)$company['id'] . ': missing finance_employee_invoice_payments');

        $columnCheck = $local->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='finance_employee_invoice_payments' AND COLUMN_NAME=?");
        foreach (['event_group_id','invoice_id','employee_movement_id','receipt_finance_operation_id','expense_finance_operation_id','status','cancellation_reason'] as $column) {
            $columnCheck->execute([$column]);
            if ((int)$columnCheck->fetchColumn() !== 1) throw new RuntimeException('P99 schema verification failed in company ' . (int)$company['id'] . ': missing ' . $column);
        }

        $indexCheck = $local->prepare("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='finance_employee_invoice_payments' AND INDEX_NAME=?");
        foreach (['uk_feip_event_group','uk_feip_employee_movement','uk_feip_receipt_operation','uk_feip_expense_operation','idx_feip_invoice'] as $index) {
            $indexCheck->execute([$index]);
            if ((int)$indexCheck->fetchColumn() < 1) throw new RuntimeException('P99 schema verification failed in company ' . (int)$company['id'] . ': missing index ' . $index);
        }
    } finally {
        $release = $local->prepare('SELECT RELEASE_LOCK(?)');
        $release->execute([$lockName]);
    }
    $companyCount++;
}

printf("P99_EMPLOYEE_INVOICE_PAYMENT_MIGRATION_OK companies=%d applied=%d existing=%d migration=%s\n",
    $companyCount, $appliedCount, $existingCount, $migrationName);
