<?php
require_once base_path('app/Service/FinanceOperationInvoiceSettlementService.php');
require_once base_path('app/Service/FinanceCashInvoiceEventService.php');
require_once base_path('app/Service/FinanceEmployeeInvoicePaymentEventService.php');
require_once base_path('app/Service/FinanceEmployeePersonalExpenseEventService.php');
require_once base_path('app/Service/FinanceEmployeeMoneyAccountService.php');
require_once base_path('app/Service/FinanceEmployeeBankSettlementService.php');
require_once base_path('app/Service/FinanceEmployeeDirectTransferService.php');
require_once base_path('app/Service/FinanceEmployeeDirectExpenseService.php');
require_once base_path('app/Service/FinanceEmployeeDirectInvoicePaymentService.php');
require_once base_path('app/Http/Controllers/Company/FinanceEmployeePaymentsController.php');
require_once base_path('app/Http/Controllers/Company/FinanceEmployeeInvoicePaymentController.php');
require_once base_path('app/Http/Controllers/Company/FinanceEmployeePersonalExpenseController.php');

$controller = new \App\Http\Controllers\Company\FinanceEmployeePaymentsController($config, $db);
$invoicePaymentController = new \App\Http\Controllers\Company\FinanceEmployeeInvoicePaymentController($config, $db);
$personalExpenseController = new \App\Http\Controllers\Company\FinanceEmployeePersonalExpenseController($config, $db);

$router->get('/company/finance/employee-payments', [$controller, 'index']);

$router->get('/company/finance/employee-payments/create', static function (): void {
    requireRole(['company_owner']);
    $_SESSION['employee_payments_success'] = 'Выберите прямую операцию сотрудника: передача, личный расход или оплата счёта.';
    redirect_to('/company/finance/employee-payments');
});
$router->post('/company/finance/employee-payments/create', static function (): void {
    requireRole(['company_owner']);
    verifyCsrfRequest();
    $_SESSION['employee_payments_error'] = 'Старая кассовая операция сотрудника отключена. Используйте прямые операции.';
    redirect_to('/company/finance/employee-payments');
});

$router->post('/company/finance/employee-payments/transfer', static function () use ($config, $db): void {
    require base_path('app/Http/Controllers/Company/FinanceEmployeeDirectActions/transfer_submit.php');
});
$router->post('/company/finance/employee-payments/transfer/update', static function (): void {
    requireRole(['company_owner']);
    verifyCsrfRequest();
    $_SESSION['employee_payments_error'] = 'Исторические кассовые передачи защищены от изменения. Для исправления создайте новую прямую операцию.';
    redirect_to('/company/finance/employee-payments');
});
$router->post('/company/finance/employee-payments/transfer/delete', static function (): void {
    requireRole(['company_owner']);
    verifyCsrfRequest();
    $_SESSION['employee_payments_error'] = 'Исторические кассовые передачи защищены от удаления.';
    redirect_to('/company/finance/employee-payments');
});

$router->get('/company/finance/employee-payments/employee/{type}/{id}', [$controller, 'employeeDetail']);
$router->post('/company/finance/employee-payments/movements/{id}/reassign', [$controller, 'reassignMovement']);

$router->post('/company/finance/employee-payments/bank-link', static function () use ($config, $db): void {
    require base_path('app/Http/Controllers/Company/FinanceEmployeeDirectActions/bank_link.php');
});
$router->post('/company/finance/employee-payments/bank-unlink', static function (): void {
    requireRole(['company_owner']);
    verifyCsrfRequest();
    $_SESSION['bank_finance_error'] = 'Прямой перевод банк ↔ сотрудник нельзя частично отвязать. Для корректировки используйте компенсирующую операцию.';
    redirect_to('/company/finance/bank-accounts');
});

$router->post('/company/finance/employee-payments/invoice-payment/create', static function () use ($config, $db): void {
    require base_path('app/Http/Controllers/Company/FinanceEmployeeDirectActions/invoice_payment_create.php');
});
$router->post('/company/finance/employee-payments/invoice-payment/update', [$invoicePaymentController, 'updateSubmit']);
$router->post('/company/finance/employee-payments/invoice-payment/cancel', [$invoicePaymentController, 'cancelSubmit']);

$router->get('/company/finance/employee-payments/personal-expenses', [$personalExpenseController, 'listForEmployee']);
$router->get('/company/finance/employee-payments/personal-expense/create', [$personalExpenseController, 'createForm']);
$router->post('/company/finance/employee-payments/personal-expense/create', static function () use ($config, $db): void {
    require base_path('app/Http/Controllers/Company/FinanceEmployeeDirectActions/personal_expense_create.php');
});
$router->get('/company/finance/employee-payments/personal-expense/{id}/edit', [$personalExpenseController, 'editForm']);
$router->post('/company/finance/employee-payments/personal-expense/{id}/update', [$personalExpenseController, 'updateSubmit']);
$router->post('/company/finance/employee-payments/personal-expense/{id}/cancel', [$personalExpenseController, 'cancelSubmit']);
