<?php

require_once base_path('app/Service/FinanceOperationInvoiceSettlementService.php');
require_once base_path('app/Service/FinanceCashInvoiceEventService.php');
require_once base_path('app/Http/Controllers/Company/FinanceCashController.php');

$controller = new \App\Http\Controllers\Company\FinanceCashController($config, $db);

$router->get('/company/finance/cash', [$controller, 'index']);
$router->get('/company/finance/cash/account-create', [$controller, 'accountCreateForm']);
$router->post('/company/finance/cash/account-create', [$controller, 'accountCreateSubmit']);
$router->get('/company/finance/cash/operation-create', [$controller, 'operationCreateForm']);
$router->post('/company/finance/cash/operation-create', [$controller, 'operationCreateSubmit']);
$router->get('/company/finance/cash/transfer-create', [$controller, 'transferCreateForm']);
$router->post('/company/finance/cash/transfer-create', [$controller, 'transferCreateSubmit']);

// Legacy source-specific handoff endpoint remains for backward compatibility
// with already created cash-resolution chains. New handoffs are created from
// «Новая операция» and use the fungible Main Cash balance.
$router->post('/company/finance/cash/dispatch-employee', static function () use ($config, $db): void {
    require base_path('app/Http/Controllers/Company/FinanceCashActions/dispatch_employee_submit.php');
});
