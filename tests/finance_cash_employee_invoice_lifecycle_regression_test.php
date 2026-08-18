<?php

declare(strict_types=1);

$root = dirname(__DIR__);

function fceil_fail(string $message): never { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); }
function fceil_read(string $path): string {
    global $root;
    $value = file_get_contents($root . '/' . $path);
    if ($value === false) fceil_fail('Cannot read ' . $path);
    return $value;
}
function fceil_has(string $text, string $needle, string $message): void {
    if (!str_contains($text, $needle)) fceil_fail($message . ' [missing: ' . $needle . ']');
}
function fceil_count_at_least(string $text, string $needle, int $minimum, string $message): void {
    if (substr_count($text, $needle) < $minimum) fceil_fail($message . ' [needle: ' . $needle . ']');
}

$ledger = fceil_read('app/Service/FinanceCashLedgerService.php');
$resolution = fceil_read('app/Service/FinanceCashResolutionService.php');
$view = fceil_read('app/View/pages/company_finance_cash.php');
$event = fceil_read('app/Service/FinanceEmployeeInvoicePaymentEventService.php');

// The economic model remains two immutable POSTED operations linked by one event.
fceil_has($event, 'receipt_finance_operation_id', 'Employee invoice event must retain the technical receipt operation');
fceil_has($event, 'expense_finance_operation_id', 'Employee invoice event must retain the carrier expense operation');
fceil_has($event, "'main_cash_net_effect'=>'0.00'", 'Employee invoice payment must keep Main Cash net zero');

// Cash journal must fold those two technical rows into one human lifecycle row.
fceil_has($ledger, 'finance_employee_invoice_payments employee_invoice_receipt', 'Cash ledger must identify the employee invoice receipt leg');
fceil_has($ledger, 'finance_employee_invoice_payments employee_invoice_expense', 'Cash ledger must identify the employee invoice expense leg');
fceil_count_at_least($ledger, 'AND employee_invoice_expense.id IS NULL', 2, 'Cash ledger count and page query must both hide the duplicate expense leg');
fceil_has($ledger, 'isEmployeeInvoiceLifecycle', 'Cash ledger must have an explicit employee invoice lifecycle classifier');
fceil_has($ledger, "return 'Получено → списано';", 'Cash ledger must present receipt and expense as one movement');
fceil_has($ledger, 'employee_invoice_counterparty', 'Combined row must expose the carrier as recipient');
fceil_has($ledger, 'employee_invoice_expense_purpose', 'Combined row must use the real carrier expense purpose');
fceil_has($ledger, "if (self::isEmployeeInvoiceLifecycle(\$row)) return false;", 'Combined employee invoice receipt must never be selectable as unresolved cash');

// Unresolved cash counters and dispatch must fail closed for an already consumed receipt.
fceil_has($resolution, 'finance_employee_invoice_payments employee_invoice_payment', 'Cash unresolved source of truth must know employee invoice events');
fceil_has($resolution, 'AND employee_invoice_payment.id IS NULL', 'Employee invoice receipt must not count as unresolved cash');
fceil_has($resolution, 'employee_invoice_payment.id AS employee_invoice_payment_id', 'Dispatch lock query must identify invoice-consumed receipt rows');
fceil_has($resolution, "!empty(\$source['employee_invoice_payment_id'])", 'Dispatch must reject an employee invoice receipt as already resolved');

// Existing table projection is reused; no duplicate UI table or fake transaction is introduced.
fceil_has($view, 'FinanceCashLedgerService::movementLabel($op)', 'Cash page must render the ledger projection movement label');
fceil_has($view, "!empty(\$op['is_resolved_cash_lifecycle'])", 'Combined lifecycle must render as resolved in the existing table');

fwrite(STDOUT, "OK: employee invoice cash lifecycle is projected as one resolved journal row\n");
