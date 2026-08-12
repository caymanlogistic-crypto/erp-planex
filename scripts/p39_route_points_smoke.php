<?php

$_SERVER['REQUEST_METHOD'] = 'CLI';
require_once __DIR__ . '/../app/Support/helpers.php';
require_once __DIR__ . '/../app/Support/environment.php';
loadEnvFileNonOverwriting(dirname(__DIR__) . '/.env');
$config = require __DIR__ . '/../bootstrap/app.php';
require_once base_path('app/Support/entrypoint_dependencies.php');

$db = new \App\Core\Database($config['database']);
$pdo = $db->connection();
$company = $pdo->query("SELECT * FROM companies WHERE status = 'active' ORDER BY id LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if (!$company) {
    throw new RuntimeException('No active company for P39 smoke.');
}
$localDb = new \App\Core\Database(companyDatabaseConfig($config, $company));
$local = $localDb->connection();
$routeId = (int) $local->query("SELECT id FROM linear_routes WHERE deleted_at IS NULL ORDER BY id LIMIT 1")->fetchColumn();
if ($routeId <= 0) {
    echo "P39_SMOKE_SKIPPED no_routes\n";
    exit(0);
}
$before = \App\Service\LinearRoutePointService::fetch($local, $routeId);
$local->beginTransaction();
try {
    \App\Service\LinearRoutePointService::store($local, $routeId, [
        'loading' => ['P39 TEST LOADING 1', 'P39 TEST LOADING 2'],
        'unloading' => ['P39 TEST UNLOADING 1'],
    ], 0, 'system');
    $after = \App\Service\LinearRoutePointService::fetch($local, $routeId);
    if (count($after['loading'] ?? []) !== 2 || count($after['unloading'] ?? []) !== 1) {
        throw new RuntimeException('Route point store/fetch mismatch.');
    }
    $local->rollBack();
} catch (Throwable $e) {
    if ($local->inTransaction()) $local->rollBack();
    throw $e;
}
$restored = \App\Service\LinearRoutePointService::fetch($local, $routeId);
if ($restored !== $before) {
    throw new RuntimeException('Rollback did not restore original route points.');
}
printf("P39_SMOKE_OK route=%d loading=%d unloading=%d\n", $routeId, count($before['loading'] ?? []), count($before['unloading'] ?? []));
