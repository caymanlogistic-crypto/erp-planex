<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$read = static fn(string $path): string => file_get_contents($root . '/' . $path) ?: '';
$assert = static function (bool $ok, string $message): void {
    if (!$ok) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
};

$routes = $read('app/Http/Routes/company_finance_employee_payments.php');
$ui = $read('app/View/partials/company_finance_employee_ledger_editor.php');
$polish = $read('app/View/partials/company_finance_employee_ledger_editor_polish.php');
$client = $read('app/Service/FinanceEmployeeClientReceiptEditService.php');
$directTransfer = $read('app/Service/FinanceEmployeeDirectTransferEditService.php');

foreach ([
    '/client-receipt/update',
    '/client-receipt/cancel',
    '/transfer/update',
    '/transfer/delete',
    '/invoice-payment/update',
    '/invoice-payment/cancel',
    '/personal-expense/{id}/update',
    '/personal-expense/{id}/cancel',
] as $needle) {
    $assert(str_contains($routes, $needle), "missing edit lifecycle route {$needle}");
}

$assert(str_contains($ui, "addEventListener('dblclick'"), 'ledger rows must open editor on double click');
$assert(substr_count($ui, '>Удалить</button>') >= 2, 'client and transfer popups must expose delete action');
$assert(str_contains($ui, 'tr.is-muted{display:none'), 'cancelled employee payments must disappear from working journal');
$assert(str_contains($ui, "kind==='invoice'"), 'invoice payments must use the standard double-click editor');
$assert(str_contains($ui, "kind==='personal'"), 'personal expenses must use the standard double-click editor');
$assert(str_contains($ui, "kind==='client'"), 'client receipts must use the standard double-click editor');
$assert(str_contains($ui, "kind==='transfer'"), 'employee transfers must use the standard double-click editor');
$assert(str_contains($polish, "del.textContent='Удалить'"), 'invoice edit popup must expose delete action');
$assert(str_contains($polish, "del.textContent='Удалить';"), 'personal expense edit popup must expose delete action');

$assert(str_contains($client, 'cancelOperationInvoiceAllocations'), 'client receipt edit/delete must unwind invoice allocations');
$assert(str_contains($client, 'FinanceAuditLogService::log'), 'client receipt edit/delete must be audited');
$assert(!str_contains($client, 'finance_cash_resolutions'), 'client receipt correction must not recreate retired account chain');
$assert(!str_contains($directTransfer, 'finance_cash_resolutions'), 'direct transfer correction must not recreate retired account chain');
$assert(str_contains($directTransfer, "status='CANCELLED'"), 'direct transfer delete must be soft cancellation');
$assert(str_contains($directTransfer, 'FinanceAuditLogService::log'), 'direct transfer correction must be audited');

fwrite(STDOUT, "FINANCE_EMPLOYEE_LEDGER_EDITING_OK\n");
