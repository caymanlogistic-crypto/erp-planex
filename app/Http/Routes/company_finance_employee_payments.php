<?php
require_once base_path('app/Service/FinanceEmployeePersonalExpenseEventService.php');
require_once base_path('app/Http/Controllers/Company/FinanceEmployeePaymentsController.php');
require_once base_path('app/Http/Controllers/Company/FinanceEmployeePersonalExpenseController.php');

$controller = new \App\Http\Controllers\Company\FinanceEmployeePaymentsController($config, $db);
$personalExpenseController = new \App\Http\Controllers\Company\FinanceEmployeePersonalExpenseController($config, $db);

$router->get('/company/finance/employee-payments', [$controller, 'index']);
$router->get('/company/finance/employee-payments/create', [$controller, 'createForm']);
$router->post('/company/finance/employee-payments/create', [$controller, 'createSubmit']);
$router->post('/company/finance/employee-payments/transfer', [$controller, 'transferSubmit']);
$router->post('/company/finance/employee-payments/transfer/update', [$controller, 'transferUpdateSubmit']);
$router->post('/company/finance/employee-payments/transfer/delete', [$controller, 'transferDeleteSubmit']);
$router->get('/company/finance/employee-payments/employee/{type}/{id}', [$controller, 'employeeDetail']);
$router->post('/company/finance/employee-payments/movements/{id}/reassign', [$controller, 'reassignMovement']);
$router->post('/company/finance/employee-payments/bank-link', [$controller, 'bankLink']);
$router->post('/company/finance/employee-payments/bank-unlink', [$controller, 'bankUnlink']);

$router->get('/company/finance/employee-payments/personal-expenses', [$personalExpenseController, 'listForEmployee']);
$router->get('/company/finance/employee-payments/personal-expense/create', [$personalExpenseController, 'createForm']);
$router->post('/company/finance/employee-payments/personal-expense/create', [$personalExpenseController, 'createSubmit']);
$router->get('/company/finance/employee-payments/personal-expense/{id}/edit', [$personalExpenseController, 'editForm']);
$router->post('/company/finance/employee-payments/personal-expense/{id}/update', [$personalExpenseController, 'updateSubmit']);
$router->post('/company/finance/employee-payments/personal-expense/{id}/cancel', [$personalExpenseController, 'cancelSubmit']);
