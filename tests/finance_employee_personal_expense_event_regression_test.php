<?php

declare(strict_types=1);

$root = dirname(__DIR__);

function fepe_fail(string $message): never { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); }
function fepe_has(string $text, string $needle, string $message): void { if (!str_contains($text, $needle)) fepe_fail($message . " [missing: {$needle}]"); }
function fepe_not(string $text, string $needle, string $message): void { if (str_contains($text, $needle)) fepe_fail($message . " [unexpected: {$needle}]"); }
function fepe_method(string $php, string $name): string {
    $start = strpos($php, 'function ' . $name . '(');
    if ($start === false) fepe_fail("Method {$name} not found");
    $open = strpos($php, '{', $start); if ($open === false) fepe_fail("Method {$name} body not found");
    $depth = 0; $len = strlen($php);
    for ($i=$open; $i<$len; $i++) { if ($php[$i]==='{') $depth++; elseif ($php[$i]==='}' && --$depth===0) return substr($php,$open,$i-$open+1); }
    fepe_fail("Method {$name} is not balanced");
}

$service = file_get_contents($root . '/app/Service/FinanceEmployeePersonalExpenseEventService.php');
$migration = file_get_contents($root . '/database/migrations-local/074_employee_personal_expense_linked_event.sql');
$routes = file_get_contents($root . '/app/Http/Routes/company_finance_employee_payments.php');
$form = file_get_contents($root . '/app/View/partials/company_finance_employee_personal_expense_form.php');
$list = file_get_contents($root . '/app/View/partials/company_finance_employee_personal_expense_list.php');
$js = file_get_contents($root . '/public/assets/js/finance-employee-personal-expense.js');
$cashForm = file_get_contents($root . '/app/View/partials/company_cash_operation_form.php');
$cashSubmit = file_get_contents($root . '/app/Http/Controllers/Company/FinanceCashActions/operation_create_submit.php');
foreach (compact('service','migration','routes','form','list','js','cashForm','cashSubmit') as $name=>$value) if ($value===false) fepe_fail("Cannot read {$name}");

$create = fepe_method($service, 'create');
$update = fepe_method($service, 'update');
$cancel = fepe_method($service, 'cancel');
$receipt = fepe_method($service, 'insertTechnicalReceipt');
$employeeReturn = fepe_method($service, 'insertEmployeeReturn');

fepe_has($receipt, "'TRANSFER','POSTED','TRANSFER'", 'Employee funding must enter Main Cash as a technical transfer, not economic income');
fepe_has($receipt, "'in'", 'Technical employee funding must be transfer-in');
fepe_has($employeeReturn, "'RETURN','CASH'", 'Employee ledger row must be negative RETURN');
fepe_has($create, "'operation_type' => 'EXPENSE'", 'Company expense must be a real cash expense');
fepe_has($create, 'FinanceCashResolutionService::findMainCashAccount', 'Linked event must use Main Cash');
fepe_has($create, 'finance_cash_resolutions', 'Technical receipt must be resolved and not left pending');
fepe_has($create, 'cash_net_effect', 'Audit must record the zero-net cash invariant');
fepe_has($update, 'receipt_finance_operation_id', 'Update must touch technical receipt');
fepe_has($update, 'expense_finance_operation_id', 'Update must touch economic expense');
fepe_has($update, 'finance_employee_personal_expenses', 'Update must keep master event synchronized');
fepe_has($cancel, "status='CANCELLED'", 'Cancellation must state-cancel linked cash operations');
fepe_has($cancel, 'receipt_finance_operation_id', 'Cancellation must cancel receipt leg');
fepe_has($cancel, 'expense_finance_operation_id', 'Cancellation must cancel expense leg');
fepe_not($cancel, 'DELETE FROM finance_operations', 'Cancellation must preserve finance operation history');
fepe_not($cancel, 'DELETE FROM finance_employee_movements', 'Cancellation must preserve employee ledger history');

foreach (['event_group_id','employee_movement_id','receipt_finance_operation_id','expense_finance_operation_id','cash_resolution_id'] as $column) {
    fepe_has($migration, $column, "Migration 074 must add {$column}");
}
fepe_has($routes, '/personal-expense/create', 'Employee Payments must own personal-expense create route');
fepe_has($routes, '/personal-expense/{id}/update', 'Employee Payments must own personal-expense update route');
fepe_has($routes, '/personal-expense/{id}/cancel', 'Employee Payments must own personal-expense cancel route');
fepe_has($form, 'Сотрудник оплатил расход компании', 'Form must explain employee-funded expense workflow');
fepe_has($form, 'Основную кассу', 'Form must explain automatic Main Cash postings');
fepe_has($list, 'Расходы, оплаченные сотрудником', 'Employee Payments page must expose linked-event management list');
fepe_has($js, 'Оплачено сотрудником', 'Employee Payments must expose the new entry button');
fepe_has($js, 'data-personal-expense-edit', 'Linked events must be editable from Employee Payments');
fepe_has($js, 'data-personal-expense-cancel-event', 'Linked events must be cancellable from Employee Payments');
fepe_not($cashForm, '<option value="EMPLOYEE_PERSONAL_EXPENSE">', 'Cash must not expose competing employee-funded expense entry');
fepe_has($cashSubmit, 'оформляется в разделе «Выплаты сотрудникам»', 'Stale Cash submissions must fail closed');

fwrite(STDOUT, "OK: linked employee personal expense event regression passed\n");
