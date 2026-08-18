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
function fceil_lacks(string $text, string $needle, string $message): void {
    if (str_contains($text, $needle)) fceil_fail($message . ' [unexpected: ' . $needle . ']');
}
function fceil_count_at_least(string $text, string $needle, int $minimum, string $message): void {
    if (substr_count($text, $needle) < $minimum) fceil_fail($message . ' [needle: ' . $needle . ']');
}

$ledger = fceil_read('app/Service/FinanceCashLedgerService.php');
$resolution = fceil_read('app/Service/FinanceCashResolutionService.php');
$view = fceil_read('app/View/pages/company_finance_cash.php');
$controller = fceil_read('app/Http/Controllers/Company/FinanceCashActions/index.php');
$invoiceEvent = fceil_read('app/Service/FinanceEmployeeInvoicePaymentEventService.php');
$personalEvent = fceil_read('app/Service/FinanceEmployeePersonalExpenseEventService.php');
$p48 = fceil_read('.github/p48/P48_cash_ledger_runtime.js');

// Both employee-funded scenarios are stored as two real linked cash legs.
fceil_has($invoiceEvent, 'receipt_finance_operation_id', 'Employee invoice event must retain the technical receipt operation');
fceil_has($invoiceEvent, 'expense_finance_operation_id', 'Employee invoice event must retain the carrier expense operation');
fceil_has($invoiceEvent, "'main_cash_net_effect'=>'0.00'", 'Employee invoice payment must keep Main Cash net zero');
fceil_has($personalEvent, 'receipt_finance_operation_id', 'Personal expense event must retain the employee receipt operation');
fceil_has($personalEvent, 'expense_finance_operation_id', 'Personal expense event must retain the economic cash expense operation');
fceil_has($personalEvent, 'FinanceCashService::createCashOperation', 'Personal expense must create a real economic cash expense');
fceil_has($personalEvent, "'cash_net_effect' => '0.00'", 'Personal expense must keep Main Cash net zero');

// Cash journal folds the receipt + expense pair into one human lifecycle row.
fceil_has($ledger, 'finance_employee_invoice_payments employee_invoice_receipt', 'Cash ledger must identify the employee invoice receipt leg');
fceil_has($ledger, 'finance_employee_invoice_payments employee_invoice_expense', 'Cash ledger must identify the employee invoice expense leg');
fceil_count_at_least($ledger, 'AND employee_invoice_expense.id IS NULL', 2, 'Cash ledger count and page query must both hide the duplicate invoice expense leg');
fceil_has($ledger, 'finance_employee_personal_expenses employee_personal_receipt', 'Cash ledger must identify the personal-expense receipt leg');
fceil_has($ledger, 'finance_employee_personal_expenses employee_personal_expense', 'Cash ledger must identify the personal-expense expense leg');
fceil_count_at_least($ledger, 'AND employee_personal_expense.id IS NULL', 2, 'Cash ledger count and page query must both hide the duplicate personal-expense leg');
fceil_has($ledger, 'hasEmployeePersonalExpenseEvents', 'Cash ledger must tolerate tenants before the linked personal-expense schema');
fceil_has($ledger, "return 'Получено → списано';", 'Both employee-funded flows must render as received then spent');
fceil_has($ledger, 'isExpandableEmployeeLifecycle', 'Cash ledger must expose one unified expandable employee lifecycle classifier');
fceil_has($ledger, "if (self::isExpandableEmployeeLifecycle(\$row)) return false;", 'Combined employee receipt must never be selectable as unresolved cash');
fceil_has($ledger, "return 'EMPLOYEE_INVOICE';", 'Cash ledger must distinguish employee invoice lifecycle details');
fceil_has($ledger, "return 'EMPLOYEE_PERSONAL_EXPENSE';", 'Cash ledger must distinguish personal-expense lifecycle details');
fceil_has($ledger, 'employee_personal_cfu', 'Personal expense lifecycle must expose CFU in its detail projection');
fceil_has($ledger, 'employee_personal_dds', 'Personal expense lifecycle must expose DDS category in its detail projection');

// Unresolved cash/dispatch remains fail-closed for already consumed invoice receipts.
fceil_has($resolution, 'hasEmployeeInvoicePaymentEvents', 'Cash projection must tolerate tenants where migration 075 is not yet present');
fceil_has($resolution, 'finance_employee_invoice_payments employee_invoice_payment', 'Cash unresolved source of truth must know employee invoice events');
fceil_has($resolution, 'AND employee_invoice_payment.id IS NULL', 'Employee invoice receipt must not count as unresolved cash');
fceil_has($resolution, 'employee_invoice_payment.id AS employee_invoice_payment_id', 'Dispatch lock query must identify invoice-consumed receipt rows');
fceil_has($resolution, "!empty(\$source['employee_invoice_payment_id'])", 'Dispatch must reject an employee invoice receipt as already resolved');

// Primary cash UI keeps its columns, adds a disclosure control, and removes the duplicate lower table.
fceil_has($view, '<th>Дата</th><th>Касса</th><th>Движение</th><th>Получено от</th>', 'Cash ledger primary column format must remain intact');
fceil_has($view, 'data-cash-lifecycle-toggle', 'Expandable lifecycle disclosure control missing');
fceil_has($view, 'cash-lifecycle-detail-row', 'Expandable lifecycle detail row missing');
fceil_has($view, 'Поступление', 'Expanded lifecycle must name the receipt leg');
fceil_has($view, 'Списание', 'Expanded lifecycle must name the expense leg');
fceil_has($view, 'ЦФУ:', 'Personal expense detail must show CFU');
fceil_has($view, 'Статья ДДС:', 'Personal expense detail must show DDS category');
fceil_lacks($view, '<div class="section-title mt-section">Расходы сотрудников из личных средств</div>', 'Duplicate personal-expense table must be removed from Cash');
fceil_lacks($controller, 'fetchRecentPersonalExpenses', 'Cash controller must not run the duplicate personal-expense history query');

// Permanent browser acceptance validates the exact expandable business semantics.
fceil_has($p48, 'duplicate personal-funded expense history section must not exist below the cash ledger', 'P48 must reject the old duplicate personal-expense block');
fceil_has($p48, 'employee-funded cash lifecycle must be expandable', 'P48 must require combined employee rows to be expandable');
fceil_has($p48, 'expanded lifecycle missing receipt leg', 'P48 must verify the receipt leg after expansion');
fceil_has($p48, 'expanded lifecycle missing expense leg', 'P48 must verify the expense leg after expansion');
fceil_has($p48, 'expanded lifecycle missing Main Cash routing', 'P48 must verify employee -> Main Cash -> recipient routing');
fceil_has($p48, 'P48_EMPLOYEE_FUNDED_CASH_EXPAND_OK', 'P48 must emit the employee-funded expandable cash acceptance marker');

fwrite(STDOUT, "OK: employee-funded cash lifecycles are single expandable rows with two real linked legs\n");
