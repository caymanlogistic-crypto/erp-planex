<?php
require_once base_path('app/Service/FinanceOperationInvoiceSettlementService.php');
require_once base_path('app/Service/FinanceCashInvoiceEventService.php');
require_once base_path('app/Service/FinanceEmployeeInvoicePaymentEventService.php');
require_once base_path('app/Service/FinanceEmployeePersonalExpenseEventService.php');
require_once base_path('app/Service/FinanceEmployeeMoneyAccountService.php');
require_once base_path('app/Service/FinanceEmployeeDirectTransferService.php');
require_once base_path('app/Http/Controllers/Company/FinanceEmployeePaymentsController.php');
require_once base_path('app/Http/Controllers/Company/FinanceEmployeeInvoicePaymentController.php');
require_once base_path('app/Http/Controllers/Company/FinanceEmployeePersonalExpenseController.php');

$controller = new \App\Http\Controllers\Company\FinanceEmployeePaymentsController($config, $db);
$invoicePaymentController = new \App\Http\Controllers\Company\FinanceEmployeeInvoicePaymentController($config, $db);
$personalExpenseController = new \App\Http\Controllers\Company\FinanceEmployeePersonalExpenseController($config, $db);

$router->get('/company/finance/employee-payments', [$controller, 'index']);
$router->get('/company/finance/employee-payments/create', [$controller, 'createForm']);
$router->post('/company/finance/employee-payments/create', [$controller, 'createSubmit']);

// New employee handoffs bypass the historical technical CASH account.
$router->post('/company/finance/employee-payments/transfer', static function () use ($config, $db): void {
    require base_path('app/Http/Controllers/Company/FinanceEmployeeDirectActions/transfer_submit.php');
});

// Historical employee transfers keep their existing edit/delete path so no old
// finance_operations or finance_cash_resolutions need to be rewritten.
$router->post('/company/finance/employee-payments/transfer/update', [$controller, 'transferUpdateSubmit']);
$router->post('/company/finance/employee-payments/transfer/delete', [$controller, 'transferDeleteSubmit']);
$router->get('/company/finance/employee-payments/employee/{type}/{id}', [$controller, 'employeeDetail']);
$router->post('/company/finance/employee-payments/movements/{id}/reassign', [$controller, 'reassignMovement']);

// Bank transactions are again attached directly to the responsible employee.
$router->post('/company/finance/employee-payments/bank-link', static function () use ($config, $db): void {
    require base_path('app/Http/Controllers/Company/FinanceEmployeeDirectActions/bank_link.php');
});
$router->post('/company/finance/employee-payments/bank-unlink', [$controller, 'bankUnlink']);

$router->post('/company/finance/employee-payments/invoice-payment/create', [$invoicePaymentController, 'createSubmit']);
$router->post('/company/finance/employee-payments/invoice-payment/update', [$invoicePaymentController, 'updateSubmit']);
$router->post('/company/finance/employee-payments/invoice-payment/cancel', [$invoicePaymentController, 'cancelSubmit']);

$router->get('/company/finance/employee-payments/personal-expenses', [$personalExpenseController, 'listForEmployee']);
$router->get('/company/finance/employee-payments/personal-expense/create', [$personalExpenseController, 'createForm']);
$router->post('/company/finance/employee-payments/personal-expense/create', [$personalExpenseController, 'createSubmit']);
$router->get('/company/finance/employee-payments/personal-expense/{id}/edit', [$personalExpenseController, 'editForm']);
$router->post('/company/finance/employee-payments/personal-expense/{id}/update', [$personalExpenseController, 'updateSubmit']);
$router->post('/company/finance/employee-payments/personal-expense/{id}/cancel', [$personalExpenseController, 'cancelSubmit']);
