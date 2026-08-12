<?php

require_once __DIR__ . '/../app/Support/helpers.php';
require_once __DIR__ . '/../app/Support/environment.php';
loadEnvFileNonOverwriting(dirname(__DIR__) . '/.env');
$config = require __DIR__ . '/../bootstrap/app.php';
require_once base_path('app/Support/entrypoint_dependencies.php');

$migrationName = '052_create_linear_route_points.sql';
$migrationPath = base_path('database/migrations-local/' . $migrationName);
$sql = file_get_contents($migrationPath);
if ($sql === false || trim($sql) === '') throw new RuntimeException('P39 migration SQL is missing or empty.');
$checksum = hash('sha256', $sql);
$db = new \App\Core\Database($config['database']);
$pdo = $db->connection();
$companies = $pdo->query("SELECT * FROM companies WHERE status = 'active' ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
$count = $appliedCount = $existingCount = 0;
foreach ($companies as $company) {
    $local = (new \App\Core\Database(companyDatabaseConfig($config, $company)))->connection();
    $local->exec("CREATE TABLE IF NOT EXISTS `schema_migrations` (`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,`migration` VARCHAR(255) NOT NULL,`checksum` VARCHAR(64) NOT NULL,`executed_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY (`id`),UNIQUE KEY `uk_local_migration` (`migration`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $lockName = 'planex_p39_' . substr(hash('sha256', (string)$local->query('SELECT DATABASE()')->fetchColumn()), 0, 32);
    $lock = $local->prepare('SELECT GET_LOCK(?, 10)'); $lock->execute([$lockName]);
    if ((int)$lock->fetchColumn() !== 1) throw new RuntimeException('Cannot acquire P39 migration lock.');
    try {
        $stmt=$local->prepare('SELECT checksum FROM schema_migrations WHERE migration=? LIMIT 1'); $stmt->execute([$migrationName]); $stored=$stmt->fetchColumn();
        if ($stored !== false) {
            if (!hash_equals((string)$stored,$checksum)) throw new RuntimeException('P39 migration checksum mismatch in company '.(int)($company['id']??0));
            $existingCount++;
        } else {
            // MySQL DDL implicitly commits; do not wrap CREATE TABLE/ALTER in a PDO transaction.
            // Migration 052 is idempotent. Journal only after successful SQL execution and table verification.
            $local->exec($sql);
            $tableCheck=$local->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='linear_route_points'");
            if ((int)$tableCheck->fetchColumn() !== 1) throw new RuntimeException('linear_route_points table missing after P39 migration.');
            $insert=$local->prepare('INSERT INTO schema_migrations (migration,checksum) VALUES (?,?)'); $insert->execute([$migrationName,$checksum]);
            $appliedCount++;
        }
        $tableCheck=$local->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='linear_route_points'");
        if ((int)$tableCheck->fetchColumn() !== 1) throw new RuntimeException('linear_route_points table missing after P39 migration.');
    } finally { $release=$local->prepare('SELECT RELEASE_LOCK(?)'); $release->execute([$lockName]); }
    $count++;
}
printf("P39_MIGRATION_OK companies=%d applied=%d existing=%d checksum=%s\n",$count,$appliedCount,$existingCount,$checksum);
