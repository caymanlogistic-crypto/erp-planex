<?php

require_once __DIR__ . '/../app/Support/helpers.php';
require_once __DIR__ . '/../app/Support/environment.php';
loadEnvFileNonOverwriting(dirname(__DIR__) . '/.env');
$config = require __DIR__ . '/../bootstrap/app.php';
require_once base_path('app/Support/entrypoint_dependencies.php');

$renames = [
    '057_create_crew_drivers.sql' => '057_create_crew_drivers.sql',
    '058_create_linear_route_points.sql' => '058_create_linear_route_points.sql',
];

$db = new \App\Core\Database($config['database']);
$central = $db->connection();
$companies = $central->query("SELECT * FROM companies WHERE status = 'active' ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);

foreach ($companies as $company) {
    $local = (new \App\Core\Database(companyDatabaseConfig($config, $company)))->connection();
    $dbName = (string) $local->query('SELECT DATABASE()')->fetchColumn();
    $lockName = 'planex_p40_migration_names_' . substr(hash('sha256', $dbName), 0, 24);
    $lock = $local->prepare('SELECT GET_LOCK(?, 10)');
    $lock->execute([$lockName]);
    if ((int) $lock->fetchColumn() !== 1) {
        throw new RuntimeException('Cannot acquire P40 migration-name lock for ' . $dbName);
    }

    try {
        $hasJournal = (int) $local->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='schema_migrations'")->fetchColumn();
        if ($hasJournal !== 1) {
            printf("P40_RENAME_SKIP company=%d db=%s reason=no_journal\n", (int) $company['id'], $dbName);
            continue;
        }

        foreach ($renames as $oldName => $newName) {
            $file = base_path('database/migrations-local/' . $newName);
            $sql = file_get_contents($file);
            if ($sql === false) {
                throw new RuntimeException('Renumbered migration missing: ' . $newName);
            }
            $expectedChecksum = hash('sha256', $sql);

            $stmt = $local->prepare('SELECT id, checksum FROM schema_migrations WHERE migration = ? LIMIT 1');
            $stmt->execute([$oldName]);
            $old = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
            $stmt->execute([$newName]);
            $new = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

            if ($new !== null && !hash_equals((string) $new['checksum'], $expectedChecksum)) {
                throw new RuntimeException('New migration journal checksum mismatch: ' . $newName . ' in ' . $dbName);
            }
            if ($old !== null && !hash_equals((string) $old['checksum'], $expectedChecksum)) {
                throw new RuntimeException('Old migration journal checksum mismatch before rename: ' . $oldName . ' in ' . $dbName);
            }

            if ($old !== null && $new === null) {
                $update = $local->prepare('UPDATE schema_migrations SET migration = ? WHERE id = ?');
                $update->execute([$newName, (int) $old['id']]);
                printf("P40_RENAMED company=%d %s=>%s\n", (int) $company['id'], $oldName, $newName);
            } elseif ($old !== null && $new !== null) {
                throw new RuntimeException('Both old and new migration journal names exist: ' . $oldName . ' / ' . $newName . ' in ' . $dbName);
            } else {
                printf("P40_RENAME_NOOP company=%d new=%s present=%s\n", (int) $company['id'], $newName, $new !== null ? 'yes' : 'no');
            }
        }
    } finally {
        $release = $local->prepare('SELECT RELEASE_LOCK(?)');
        $release->execute([$lockName]);
    }
}

echo "P40_MIGRATION_NAME_RECONCILIATION_OK\n";
