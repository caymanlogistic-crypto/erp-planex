<?php

require_once base_path('app/Http/Controllers/Company/FinanceOperationController.php');

$controller = new \App\Http\Controllers\Company\FinanceOperationController($config, $db);

$router->get('/company/finance/operations', [$controller, 'index']);
$router->get('/company/finance/operations/{id}/modal-view', [$controller, 'modalView']);
$router->get('/company/finance/operations/{id}/allocate', [$controller, 'modalAllocateForm']);
$router->post('/company/finance/operations/{id}/allocate', [$controller, 'allocateSubmit']);
$router->post('/company/finance/operations/allocations/{id}/cancel', [$controller, 'allocationCancel']);
$router->post('/company/finance/operations/{id}/cancel', [$controller, 'cancel']);
$router->get('/company/finance/operations/{id}/history', [$controller, 'history']);
$router->get('/company/finance/operations/route-payments', [$controller, 'routePayments']);
