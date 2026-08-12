<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap/app.php';
require_once base_path('app/Support/entrypoint_dependencies.php');

$renames = [
    '029_create_crew_drivers.sql' => '057_create_crew_drivers.sql',
    '052_create_linear_route_points.sql' => '058_create_linear_route_points.sql',
];

$db = new \App\Core\Database($config['database']);
$central = $db->pdo();
$companyRows = $central->query("SELECT id, db_name FROM companies WHERE status <> 'deleted' ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);

foreach ($companyRows as $company) {
    $companyId = (int)$company['id'];
    $dbName = trim((string)($company['db_name'] ?? ''));
    if ($dbName === '') {
        $dbName = 'erp_company_' . $companyId;
    }
    if (!preg_match('/^[A-Za-z0-9_]+$/', $dbName)) {
        throw new RuntimeException('Unsafe tenant database name for company ' . $companyId);
    }

    $tenant = new PDO(
        sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $config['database']['host'], (int)$config['database']['port'], $dbName),
        $config['database']['username'],
        $config['database']['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );

    $exists = (bool)$tenant->query("SHOW TABLES LIKE 'schema_migrations'")->fetchColumn();
    if (!$exists) {
        echo "company={$companyId} journal=missing\n";
        continue;
    }

    foreach ($renames as $old => $new) {
        $oldStmt = $tenant->prepare('SELECT * FROM schema_migrations WHERE migration = ? LIMIT 1');
        $oldStmt->execute([$old]);
        $oldRow = $oldStmt->fetch();
        $newStmt = $tenant->prepare('SELECT * FROM schema_migrations WHERE migration = ? LIMIT 1');
        $newStmt->execute([$new]);
        $newRow = $newStmt->fetch();

        if ($oldRow && !$newRow) {
            $update = $tenant->prepare('UPDATE schema_migrations SET migration = ? WHERE migration = ?');
            $update->execute([$new, $old]);
            echo "company={$companyId} renamed={$old}=>{$new}\n";
        } elseif ($oldRow && $newRow) {
            throw new RuntimeException("Both old and new migration journal names exist for company {$companyId}: {$old} / {$new}");
        } else {
            echo "company={$companyId} migration={$new} state=" . ($newRow ? 'already-normalized' : 'not-recorded') . "\n";
        }
    }
}
