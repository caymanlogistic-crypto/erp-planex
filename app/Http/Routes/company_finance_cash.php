<?php

require_once base_path('app/Http/Controllers/Company/FinanceCashController.php');

$controller = new \App\Http\Controllers\Company\FinanceCashController($config, $db);

$router->get('/company/finance/cash', [$controller, 'index']);
$router->get('/company/finance/cash/account-create', [$controller, 'accountCreateForm']);
$router->post('/company/finance/cash/account-create', [$controller, 'accountCreateSubmit']);
$router->get('/company/finance/cash/operation-create', [$controller, 'operationCreateForm']);
$router->post('/company/finance/cash/operation-create', [$controller, 'operationCreateSubmit']);
$router->get('/company/finance/cash/transfer-create', [$controller, 'transferCreateForm']);
$router->post('/company/finance/cash/transfer-create', [$controller, 'transferCreateSubmit']);

// Keep this new endpoint out of FinanceCashController's method surface. During
// atomic deploy long-lived PHP-FPM workers may still have the previous class
// bytecode cached; the closure itself remains a valid callable in that window.
$router->post('/company/finance/cash/dispatch-employee', static function () use ($config, $db): void {
    require base_path('app/Http/Controllers/Company/FinanceCashActions/dispatch_employee_submit.php');
});