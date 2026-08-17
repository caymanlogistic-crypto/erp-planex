<?php

require_once __DIR__ . '/../app/Support/helpers.php';
require_once __DIR__ . '/../app/Support/environment.php';
loadEnvFileNonOverwriting(dirname(__DIR__) . '/.env');
$config = require __DIR__ . '/../bootstrap/app.php';
require_once base_path('app/Support/entrypoint_dependencies.php');

$migrationName = '073_finance_manual_cash_and_personal_expense_facts.sql';
$migrationPath = base_path('database/migrations-local/' . $migrationName);
$sql = file_get_contents($migrationPath);
if ($sql === false || trim($sql) === '') {
    throw new RuntimeException('P97 migration SQL is missing or empty: ' . $migrationName);
}
$checksum = hash('sha256', $sql);

$db = new \App\Core\Database($config['database']);
$pdo = $db->connection();
$companies = $pdo->query("SELECT * FROM companies WHERE status = 'active' ORDER BY id")
    ->fetchAll(PDO::FETCH_ASSOC);

$companyCount = 0;
$appliedCount = 0;
$existingCount = 0;

foreach ($companies as $company) {
    $localDb = new \App\Core\Database(companyDatabaseConfig($config, $company));
    $local = $localDb->connection();
    $local->exec(
        "CREATE TABLE IF NOT EXISTS `schema_migrations` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `migration` VARCHAR(255) NOT NULL,
            `checksum` VARCHAR(64) NOT NULL,
            `executed_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uk_local_migration` (`migration`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $databaseName = (string) $local->query('SELECT DATABASE()')->fetchColumn();
    if ($databaseName === '') {
        throw new RuntimeException('P97 cannot resolve tenant database for company ' . (int) $company['id']);
    }

    $lockName = 'planex_p97_' . substr(hash('sha256', $databaseName), 0, 32);
    $lock = $local->prepare('SELECT GET_LOCK(?, 10)');
    $lock->execute([$lockName]);
    if ((int) $lock->fetchColumn() !== 1) {
        throw new RuntimeException('Cannot acquire P97 migration lock for company ' . (int) $company['id']);
    }

    try {
        $stmt = $local->prepare('SELECT checksum FROM schema_migrations WHERE migration = ? LIMIT 1');
        $stmt->execute([$migrationName]);
        $storedChecksum = $stmt->fetchColumn();

        if ($storedChecksum !== false) {
            if (!hash_equals((string) $storedChecksum, $checksum)) {
                throw new RuntimeException(
                    'P97 migration checksum mismatch in company ' . (int) $company['id'] . ': ' . $migrationName
                );
            }
            $existingCount++;
        } else {
            try {
                $local->exec($sql);
            } catch (Throwable $e) {
                throw new RuntimeException(
                    'P97 migration failed in company ' . (int) $company['id'] . ': ' . $e->getMessage(),
                    0,
                    $e
                );
            }

            $insert = $local->prepare('INSERT INTO schema_migrations (migration, checksum) VALUES (?, ?)');
            $insert->execute([$migrationName, $checksum]);
            $appliedCount++;
        }

        $tableCheck = $local->prepare(
            'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
        );
        foreach (['finance_cash_route_receipts', 'finance_employee_personal_expenses'] as $tableName) {
            $tableCheck->execute([$tableName]);
            if ((int) $tableCheck->fetchColumn() !== 1) {
                throw new RuntimeException(
                    'P97 schema verification failed in company ' . (int) $company['id'] . ': missing table ' . $tableName
                );
            }
        }

        $columnCheck = $local->prepare(
            'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
        );
        foreach ([
            ['finance_employee_personal_expenses', 'employee_identity_type'],
            ['finance_employee_personal_expenses', 'cash_flow_center_id'],
            ['finance_employee_personal_expenses', 'dds_category_id'],
            ['finance_employee_personal_expenses', 'linear_route_id'],
            ['finance_cash_route_receipts', 'finance_operation_id'],
            ['finance_cash_route_receipts', 'finance_allocation_id'],
            ['finance_cash_route_receipts', 'linear_route_payment_id'],
        ] as [$tableName, $columnName]) {
            $columnCheck->execute([$tableName, $columnName]);
            if ((int) $columnCheck->fetchColumn() !== 1) {
                throw new RuntimeException(
                    'P97 schema verification failed in company ' . (int) $company['id'] . ': ' . $tableName . '.' . $columnName
                );
            }
        }

        $nullableCheck = $local->query(
            "SELECT IS_NULLABLE FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = 'finance_cash_resolutions'
                AND COLUMN_NAME = 'outflow_finance_operation_id'
              LIMIT 1"
        )->fetchColumn();
        if ($nullableCheck !== 'YES') {
            throw new RuntimeException(
                'P97 schema verification failed in company ' . (int) $company['id'] . ': finance_cash_resolutions.outflow_finance_operation_id must be nullable'
            );
        }
    } finally {
        $release = $local->prepare('SELECT RELEASE_LOCK(?)');
        $release->execute([$lockName]);
    }

    $companyCount++;
}

printf(
    "P97_FINANCE_MANUAL_FACTS_MIGRATION_OK companies=%d applied=%d existing=%d migration=%s\n",
    $companyCount,
    $appliedCount,
    $existingCount,
    $migrationName
);
