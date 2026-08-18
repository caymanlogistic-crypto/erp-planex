<?php

require_once base_path('app/Service/FinanceOperationInvoiceSettlementService.php');
require_once base_path('app/Service/FinanceCashInvoiceEventService.php');
require_once base_path('app/Service/FinanceInvoiceSettlementHistoryService.php');
require_once base_path('app/Service/FinanceInvoiceSettlementDateService.php');
require_once base_path('app/Service/FinancePayablesReportService.php');
require_once base_path('app/Http/Controllers/Company/FinanceInvoiceController.php');

$controller = new \App\Http\Controllers\Company\FinanceInvoiceController($config, $db);

$router->get('/company/finance/invoices', [$controller, 'index']);
$router->get('/company/finance/receivables', [$controller, 'receivables']);
$router->get('/company/finance/payables', [$controller, 'payables']);
$router->post('/company/finance/invoices/create', [$controller, 'createSubmit']);
$router->get('/company/finance/invoices/{id}/modal-view', [$controller, 'modalView']);
$router->get('/company/finance/invoices/{id}/modal-edit', [$controller, 'modalEditForm']);
$router->post('/company/finance/invoices/{id}/modal-edit', [$controller, 'modalEditSubmit']);
$router->post('/company/finance/invoices/{id}/modal-delete', [$controller, 'modalDelete']);

// Retired direct payment channel is fail-closed. Carrier invoices are paid from
// the bank statement or through the approved employee action «Оплатил счёт».
$router->post('/company/finance/invoices/{id}/pay-cash', static function (): void {
    requireRole(['company_owner']);
    verifyCsrfRequest();
    $_SESSION['invoice_error'] = 'Этот способ оплаты отключён. Используйте банковскую выписку или действие сотрудника «Оплатил счёт».';
    redirect_to('/company/finance/invoices');
});

$router->post('/company/finance/invoices/{id}/settlements/{operationId}/date', [$controller, 'settlementDateSubmit']);
$router->get('/company/finance/invoices/obligations', [$controller, 'obligations']);
$router->get('/company/finance/invoices/{id}/history', [$controller, 'history']);
$router->get('/company/finance/invoices/route-payments', [$controller, 'routePayments']);
