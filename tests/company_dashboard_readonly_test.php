<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$routeFile = __DIR__ . '/../app/Http/Routes/company_dashboard.php';
$source = file_get_contents($routeFile);
if ($source === false) {
    fwrite(STDERR, "Cannot read company dashboard route\n");
    exit(1);
}

$getStart = strpos($source, "\$router->get('/company/dashboard'");
$nextPost = strpos($source, "\$router->post('/company/access-grants/grant'");
$getHandler = ($getStart !== false && $nextPost !== false && $nextPost > $getStart)
    ? substr($source, $getStart, $nextPost - $getStart)
    : '';

$pass = 0;
$fail = 0;
$check = static function (string $name, bool $ok) use (&$pass, &$fail): void {
    if ($ok) {
        $pass++;
        echo "[PASS] {$name}\n";
        return;
    }
    $fail++;
    echo "[FAIL] {$name}\n";
};

$check('dashboard GET handler extracted', $getHandler !== '');
$check('dashboard GET is migration-free', !str_contains($getHandler, 'applyLocalMigrations('));
$check('central company lookup remains read-only', str_contains($getHandler, 'SELECT id, name, db_identifier, status, db_host, db_port, db_username, db_password'));
$check('company lookup remains tenant-scoped', str_contains($getHandler, 'FROM companies WHERE id = ?'));
$check('company database resolution remains configured', str_contains($getHandler, 'companyDatabaseConfig($config, $company)'));
$check('company database connection remains enabled', str_contains($getHandler, '$localPdo = $localDb->connection()'));
$check('owner metrics remain SELECT-only', str_contains($getHandler, 'SELECT COUNT(*) as total'));
$check('logist metrics remain SELECT-only', str_contains($getHandler, 'SELECT COUNT(*) FROM `{$table}` WHERE created_by_user_id = ?'));
$check('dashboard view remains unchanged', str_contains($getHandler, "app/View/pages/company_dashboard.php"));

printf("COMPANY_DASHBOARD_READONLY: %d passed, %d failed\n", $pass, $fail);
exit($fail === 0 ? 0 : 1);
