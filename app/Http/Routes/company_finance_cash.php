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
$router->post('/company/finance/cash/dispatch-employee', [$financeCashController, 'dispatchEmployeeSubmit']);