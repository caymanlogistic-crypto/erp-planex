<?php

use App\Http\Controllers\Company\FinanceCashController;

$financeCashController = new FinanceCashController($config, $db);

$router->get('/company/finance/cash', [$financeCashController, 'index']);
$router->get('/company/finance/cash/account-create', [$financeCashController, 'accountCreateForm']);
$router->post('/company/finance/cash/account-create', [$financeCashController, 'accountCreateSubmit']);
$router->get('/company/finance/cash/operation-create', [$financeCashController, 'operationCreateForm']);
$router->post('/company/finance/cash/operation-create', [$financeCashController, 'operationCreateSubmit']);
$router->get('/company/finance/cash/transfer-create', [$financeCashController, 'transferCreateForm']);
$router->post('/company/finance/cash/transfer-create', [$financeCashController, 'transferCreateSubmit']);

// Keep this endpoint out of FinanceCashController: long-lived PHP-FPM workers may
// still have the previous controller class cached during an atomic deploy. A
// closure stays callable even while that old class is resident in OPcache.
$router->post('/company/finance/cash/dispatch-employee', static function () use ($config, $db): void {
    require base_path('app/Http/Controllers/Company/FinanceCashActions/dispatch_employee_submit.php');
});