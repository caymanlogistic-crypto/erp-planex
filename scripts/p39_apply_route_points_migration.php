<?php

$_SERVER['REQUEST_METHOD'] = 'POST';
require_once __DIR__ . '/../app/Support/helpers.php';
require_once __DIR__ . '/../app/Support/environment.php';
loadEnvFileNonOverwriting(dirname(__DIR__) . '/.env');
$config = require __DIR__ . '/../bootstrap/app.php';
require_once base_path('app/Support/entrypoint_dependencies.php');

$db = new \App\Core\Database($config['database']);
$pdo = $db->connection();
$companies = $pdo->query("SELECT * FROM companies WHERE status = 'active' ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
$count = 0;
foreach ($companies as $company) {
    $localDb = new \App\Core\Database(companyDatabaseConfig($config, $company));
    \App\Service\LocalMigrationService::apply($localDb->connection());
    $count++;
}
printf("P39_MIGRATION_OK companies=%d\n", $count);
