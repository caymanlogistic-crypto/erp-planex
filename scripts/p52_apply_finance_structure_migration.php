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

$migrationName = '064_finance_structure_links.sql';
$migrationPath = base_path('database/migrations-local/' . $migrationName);
$sql = file_get_contents($migrationPath);
if ($sql === false || trim($sql) === '') throw new RuntimeException('P52 migration SQL is missing or empty.');
$checksum = hash('sha256', $sql);

$db = new \App\Core\Database($config['database']);
$central = $db->connection();
$companies = $central->query("SELECT * FROM companies WHERE status='active' ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
$count = $appliedCount = $existingCount = 0;

foreach ($companies as $company) {
    $local = (new \App\Core\Database(companyDatabaseConfig($config, $company)))->connection();
    $local->exec("CREATE TABLE IF NOT EXISTS `schema_migrations` (`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,`migration` VARCHAR(255) NOT NULL,`checksum` VARCHAR(64) NOT NULL,`executed_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY (`id`),UNIQUE KEY `uk_local_migration` (`migration`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $databaseName = (string)$local->query('SELECT DATABASE()')->fetchColumn();
    $lockName = 'planex_p52_' . substr(hash('sha256', $databaseName), 0, 32);
    $lock = $local->prepare('SELECT GET_LOCK(?,10)');
    $lock->execute([$lockName]);
    if ((int)$lock->fetchColumn() !== 1) throw new RuntimeException('Cannot acquire P52 migration lock for company ' . (int)$company['id']);

    try {
        $stmt = $local->prepare('SELECT checksum FROM schema_migrations WHERE migration=? LIMIT 1');
        $stmt->execute([$migrationName]);
        $stored = $stmt->fetchColumn();
        if ($stored !== false) {
            if (!hash_equals((string)$stored, $checksum)) throw new RuntimeException('P52 migration checksum mismatch in company ' . (int)$company['id']);
            $existingCount++;
        } else {
            $local->exec($sql);
            $tableCheck = $local->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='finance_cash_flow_center_dds_categories'");
            if ((int)$tableCheck->fetchColumn() !== 1) throw new RuntimeException('Finance structure table missing after migration in company ' . (int)$company['id']);
            $insert = $local->prepare('INSERT INTO schema_migrations (migration,checksum) VALUES (?,?)');
            $insert->execute([$migrationName, $checksum]);
            $appliedCount++;
        }

        $tableCheck = $local->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='finance_cash_flow_center_dds_categories'");
        if ((int)$tableCheck->fetchColumn() !== 1) throw new RuntimeException('Finance structure table missing in company ' . (int)$company['id']);
        $links = (int)$local->query('SELECT COUNT(*) FROM finance_cash_flow_center_dds_categories WHERE is_active=1')->fetchColumn();
        printf("P52_COMPANY_OK id=%d db=%s active_links=%d\n", (int)$company['id'], $databaseName, $links);
    } finally {
        $release = $local->prepare('SELECT RELEASE_LOCK(?)');
        $release->execute([$lockName]);
    }
    $count++;
}

printf("P52_MIGRATION_OK companies=%d applied=%d existing=%d checksum=%s\n", $count, $appliedCount, $existingCount, $checksum);
