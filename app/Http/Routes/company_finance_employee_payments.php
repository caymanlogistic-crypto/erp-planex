<?php
require_once base_path('app/Http/Controllers/Company/FinanceEmployeePaymentsController.php');
$controller = new \App\Http\Controllers\Company\FinanceEmployeePaymentsController($config, $db);
$router->get('/company/finance/employee-payments', [$controller, 'index']);
$router->get('/company/finance/employee-payments/create', [$controller, 'createForm']);
$router->post('/company/finance/employee-payments/create', [$controller, 'createSubmit']);
$router->post('/company/finance/employee-payments/transfer', [$controller, 'transferSubmit']);
$router->get('/company/finance/employee-payments/employee/{type}/{id}', [$controller, 'employeeDetail']);
$router->post('/company/finance/employee-payments/movements/{id}/reassign', [$controller, 'reassignMovement']);
$router->post('/company/finance/employee-payments/bank-link', [$controller, 'bankLink']);
$router->post('/company/finance/employee-payments/bank-unlink', [$controller, 'bankUnlink']);
