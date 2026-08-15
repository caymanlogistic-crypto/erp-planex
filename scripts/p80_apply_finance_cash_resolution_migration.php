<?php

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only.\n");
    exit(1);
}

require_once __DIR__ . '/../app/Support/helpers.php';
require_once __DIR__ . '/../app/Support/environment.php';
loadEnvFileNonOverwriting(dirname(__DIR__) . '/.env');
$config = require __DIR__ . '/../bootstrap/app.php';
require_once base_path('app/Support/entrypoint_dependencies.php');

$migrationName = '067_finance_cash_resolutions.sql';
$migrationPath = base_path('database/migrations-local/' . $migrationName);
$sql = file_get_contents($migrationPath);
if ($sql === false || trim($sql) === '') {
    throw new RuntimeException('P80 migration SQL is missing or empty.');
}
$checksum = hash('sha256', $sql);

$db = new \App\Core\Database($config['database']);
$central = $db->connection();
$companies = $central->query("SELECT * FROM companies WHERE status='active' ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
$count = 0;
$appliedCount = 0;
$existingCount = 0;

foreach ($companies as $company) {
    $companyId = (int)($company['id'] ?? 0);
    if ($companyId <= 0) {
        throw new RuntimeException('P80 encountered an active company without a valid id.');
    }

    $local = (new \App\Core\Database(companyDatabaseConfig($config, $company)))->connection();
    $databaseName = (string)$local->query('SELECT DATABASE()')->fetchColumn();
    if ($databaseName === '') {
        throw new RuntimeException('P80 cannot resolve tenant database for company ' . $companyId);
    }

    $local->exec("CREATE TABLE IF NOT EXISTS `schema_migrations` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `migration` VARCHAR(255) NOT NULL,
        `checksum` VARCHAR(64) NOT NULL,
        `executed_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uk_local_migration` (`migration`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $lockName = 'planex_p80_' . substr(hash('sha256', $databaseName), 0, 32);
    $lock = $local->prepare('SELECT GET_LOCK(?,10)');
    $lock->execute([$lockName]);
    if ((int)$lock->fetchColumn() !== 1) {
        throw new RuntimeException('Cannot acquire P80 migration lock for company ' . $companyId);
    }

    try {
        foreach (['finance_operations', 'finance_employee_movements'] as $requiredTable) {
            $tableCheck = $local->prepare(
                "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?"
            );
            $tableCheck->execute([$requiredTable]);
            if ((int)$tableCheck->fetchColumn() !== 1) {
                throw new RuntimeException('P80 prerequisite table ' . $requiredTable . ' is missing for company ' . $companyId);
            }
        }

        $stmt = $local->prepare('SELECT checksum FROM schema_migrations WHERE migration=? LIMIT 1');
        $stmt->execute([$migrationName]);
        $stored = $stmt->fetchColumn();

        if ($stored !== false) {
            if (!hash_equals((string)$stored, $checksum)) {
                throw new RuntimeException('P80 migration checksum mismatch in company ' . $companyId);
            }
            $existingCount++;
        } else {
            $local->exec($sql);
            $insert = $local->prepare('INSERT INTO schema_migrations (migration,checksum) VALUES (?,?)');
            $insert->execute([$migrationName, $checksum]);
            $appliedCount++;
        }

        $tableCheck = $local->query(
            "SELECT COUNT(*) FROM information_schema.TABLES
              WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='finance_cash_resolutions'"
        );
        if ((int)$tableCheck->fetchColumn() !== 1) {
            throw new RuntimeException('finance_cash_resolutions is missing after P80 migration in company ' . $companyId);
        }

        $requiredColumns = [
            'source_finance_operation_id',
            'resolution_type',
            'target_identity_type',
            'target_identity_id',
            'outflow_finance_operation_id',
            'employee_movement_id',
        ];
        $placeholders = implode(',', array_fill(0, count($requiredColumns), '?'));
        $columnCheck = $local->prepare(
            "SELECT COUNT(DISTINCT COLUMN_NAME)
               FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA=DATABASE()
                AND TABLE_NAME='finance_cash_resolutions'
                AND COLUMN_NAME IN ($placeholders)"
        );
        $columnCheck->execute($requiredColumns);
        if ((int)$columnCheck->fetchColumn() !== count($requiredColumns)) {
            throw new RuntimeException('finance_cash_resolutions columns are incomplete in company ' . $companyId);
        }

        $requiredUniqueIndexes = ['uk_fcr_source_operation', 'uk_fcr_outflow_operation', 'uk_fcr_employee_movement'];
        $placeholders = implode(',', array_fill(0, count($requiredUniqueIndexes), '?'));
        $indexCheck = $local->prepare(
            "SELECT COUNT(DISTINCT INDEX_NAME)
               FROM information_schema.STATISTICS
              WHERE TABLE_SCHEMA=DATABASE()
                AND TABLE_NAME='finance_cash_resolutions'
                AND NON_UNIQUE=0
                AND INDEX_NAME IN ($placeholders)"
        );
        $indexCheck->execute($requiredUniqueIndexes);
        if ((int)$indexCheck->fetchColumn() !== count($requiredUniqueIndexes)) {
            throw new RuntimeException('finance_cash_resolutions unique indexes are incomplete in company ' . $companyId);
        }

        printf("P80_COMPANY_OK id=%d db=%s migration=%s\n", $companyId, $databaseName, $stored === false ? 'applied' : 'existing');
    } finally {
        $release = $local->prepare('SELECT RELEASE_LOCK(?)');
        $release->execute([$lockName]);
    }

    $count++;
}

printf(
    "P80_MIGRATION_OK companies=%d applied=%d existing=%d checksum=%s\n",
    $count,
    $appliedCount,
    $existingCount,
    $checksum
);
