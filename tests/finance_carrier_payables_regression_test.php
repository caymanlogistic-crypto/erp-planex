<?php

declare(strict_types=1);

$root = dirname(__DIR__);
function fcp_fail(string $message): never { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); }
function fcp_read(string $path): string { global $root; $v=file_get_contents($root.'/'.$path); if($v===false) fcp_fail('Cannot read '.$path); return $v; }
function fcp_has(string $text,string $needle,string $message): void { if(!str_contains($text,$needle)) fcp_fail($message.' [missing: '.$needle.']'); }
function fcp_not(string $text,string $needle,string $message): void { if(str_contains($text,$needle)) fcp_fail($message.' [unexpected: '.$needle.']'); }

$routes=fcp_read('app/Http/Routes/company_finance_invoices.php');
$controller=fcp_read('app/Http/Controllers/Company/FinanceInvoiceController.php');
$action=fcp_read('app/Http/Controllers/Company/InvoiceActions/payables.php');
$cashAction=fcp_read('app/Http/Controllers/Company/InvoiceActions/pay_cash_submit.php');
$dateAction=fcp_read('app/Http/Controllers/Company/InvoiceActions/settlement_date_submit.php');
$report=fcp_read('app/Service/FinancePayablesReportService.php');
$history=fcp_read('app/Service/FinanceInvoiceSettlementHistoryService.php');
$dateService=fcp_read('app/Service/FinanceInvoiceSettlementDateService.php');
$view=fcp_read('app/View/pages/company_finance_payables.php');
$modal=fcp_read('app/View/partials/company_invoice_modal_view.php');
$generic=fcp_read('app/Service/FinanceOperationInvoiceSettlementService.php');
$bank=fcp_read('app/Service/FinanceBankInvoiceSettlementService.php');
$cash=fcp_read('app/Service/FinanceCashInvoiceEventService.php');
$employee=fcp_read('app/Service/FinanceEmployeeInvoicePaymentEventService.php');

// This application intentionally uses explicit runtime require manifests (no
// PSR autoloader). Any new invoice-route service must therefore be required by
// the route before controller dispatch. This catches the production failure
// where the PHP file existed on disk but the class had never been loaded.
foreach ([
    'FinanceOperationInvoiceSettlementService.php',
    'FinanceCashInvoiceEventService.php',
    'FinanceInvoiceSettlementHistoryService.php',
    'FinanceInvoiceSettlementDateService.php',
    'FinancePayablesReportService.php',
] as $dependency) {
    fcp_has($routes, "app/Service/{$dependency}", 'Invoice route must load runtime dependency '.$dependency);
}

// Dedicated, owner-only AP entry point.
fcp_has($routes,"/company/finance/payables",'Payables route must exist');
fcp_has($controller,'function payables()', 'Controller must expose payables');
fcp_has($action,"requireRole(['company_owner'])",'Payables must remain owner-only');
fcp_has($report,"o.direction='PAYABLE'",'AP register must be driven by PAYABLE obligations');
fcp_has($report,"fo.status='POSTED'",'AP paid facts must only use posted money operations');
fcp_has($report,'a.cancelled_at IS NULL','Cancelled allocations must not count as paid');
fcp_has($report,'finance_invoice_links','AP must preserve invoice-obligation links');

// Production UI contract.
foreach (['Кредиторская задолженность','Всего к оплате','Просрочено','Перевозчик','Рейс / событие','Входящие счета','Оплачено','Остаток','Источник оплаты'] as $needle) {
    fcp_has($view,$needle,'Payables UI must expose '.$needle);
}
foreach (['Расчётный счёт','Касса','Сотрудник'] as $channel) {
    fcp_has($view,$channel,'Payables UI must explain '.$channel.' settlement');
}
foreach (['Расчётный счёт','Основная касса','Сотрудник'] as $channel) {
    fcp_has($modal,$channel,'Invoice modal must expose '.$channel.' settlement');
}
fcp_has($view,'data-payables-search','AP table must have client-side search');
fcp_has($view,'data-payables-status','AP table must have settlement filters');

// One shared settlement core for every source.
fcp_has($generic,': FinanceInvoiceService::DIRECTION_INCOMING','EXPENSE must settle INCOMING invoices');
fcp_has($bank,"'client' : 'contractor'",'Bank expense must resolve a contractor');
fcp_has($bank,'count($entities) !== 1','Bank fallback must fail closed on ambiguous contractor INN');
fcp_has($cash,'payCarrierInvoiceFromMainCash','Cash carrier payment service must exist');
fcp_has($cash,'FinanceOperationInvoiceSettlementService::allocate','Cash carrier payment must use generic settlement');
fcp_has($employee,'FinanceOperationInvoiceSettlementService::allocate','Employee carrier payment must use generic settlement');
fcp_has($employee,"'movement_type'=>'RETURN'",'Employee carrier payment must reduce employee-held balance');

// Modal cash action must delegate to the established audited service; no raw money writes.
fcp_has($routes,'/{id}/pay-cash','Carrier invoice cash action must be routed');
fcp_has($cashAction,'verifyCsrfRequest()','Cash payment action must require CSRF');
fcp_has($cashAction,'FinanceCashInvoiceEventService::payCarrierInvoiceFromMainCash','Cash action must delegate to established service');
fcp_not($cashAction,'INSERT INTO finance_operations','Controller must not write money rows directly');
fcp_not($cashAction,'DELETE FROM','Carrier payment must never hard-delete finance history');

// Settlement history must identify real payment channel from posted allocations.
fcp_has($history,"fo.status='POSTED'",'Settlement history must ignore cancelled/unposted operations');
fcp_has($history,'fo.bank_transaction_id','History must identify bank settlements');
fcp_has($history,'money_account_type','History must project money account type for cash identification');
fcp_has($history,"=== 'CASH'",'History must identify cash settlements');
fcp_has($history,'finance_employee_invoice_payments','History must identify employee-funded settlements');

// Actual payment date must be editable for manual settlements without creating
// another money operation. Bank dates stay sourced from imported statements.
fcp_has($routes,'/{id}/settlements/{operationId}/date','Settlement date update route must exist');
fcp_has($controller,'function settlementDateSubmit(', 'Controller must expose settlement date update');
fcp_has($dateAction,"requireRole(['company_owner'])",'Settlement date edit must remain owner-only');
fcp_has($dateAction,'verifyCsrfRequest()','Settlement date edit must require CSRF');
fcp_has($dateAction,'FinanceInvoiceSettlementDateService::updateDate','Date action must delegate to the date service');
fcp_has($dateService,'bank_transaction_id','Date service must distinguish bank-sourced dates');
fcp_has($dateService,'Дата банковского платежа берётся из банковской выписки','Bank dates must be immutable from invoice settlement UI');
fcp_has($dateService,'finance_employee_invoice_payments','Employee-funded invoice event date must be updated');
fcp_has($dateService,'receipt_finance_operation_id','Employee receipt leg date must remain synchronized');
fcp_has($dateService,'expense_finance_operation_id','Employee expense leg date must remain synchronized');
fcp_has($dateService,'SET allocation_date=?','Allocation date must follow edited actual payment date');
fcp_has($dateService,'first_paid_at','Invoice first-paid date must be synchronized');
fcp_has($dateService,'fully_paid_at','Invoice fully-paid date must be synchronized');
fcp_has($dateService,'linear_route_payments SET paid_at=?','Route payment paid date must be synchronized');
fcp_not($dateService,'INSERT INTO finance_operations','Editing payment date must not create a second finance operation');
fcp_not($dateService,'DELETE FROM','Editing payment date must not delete finance history');
fcp_has($modal,'data-settlement-date-edit','Manual settlement must expose an edit-date control');
fcp_has($modal,'data-settlement-date-form','Manual settlement must expose an inline date form');
fcp_has($modal,'Дата из банковской выписки','Bank settlement UI must explain why its date is not editable here');
fcp_has($modal,'window.location.reload()','Successful date edit must refresh invoice plan/fact data');

fwrite(STDOUT,"OK: carrier payables regression contract passed\n");
