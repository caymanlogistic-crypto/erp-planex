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

$historicalService = file_get_contents($root . '/app/Service/FinanceEmployeePersonalExpenseEventService.php');
$directService = file_get_contents($root . '/app/Service/FinanceEmployeeDirectExpenseService.php');
$migration = file_get_contents($root . '/database/migrations-local/074_employee_personal_expense_linked_event.sql');
$routes = file_get_contents($root . '/app/Http/Routes/company_finance_employee_payments.php');
$form = file_get_contents($root . '/app/View/partials/company_finance_employee_personal_expense_form.php');
$page = file_get_contents($root . '/app/View/pages/company_finance_employee_payments.php');
$js = file_get_contents($root . '/public/assets/js/finance-employee-personal-expense.js');
$cashForm = file_get_contents($root . '/app/View/partials/company_cash_operation_form.php');
$cashSubmit = file_get_contents($root . '/app/Http/Controllers/Company/FinanceCashActions/operation_create_submit.php');
foreach (compact('historicalService','directService','migration','routes','form','page','js','cashForm','cashSubmit') as $name=>$value) if ($value===false) fepe_fail("Cannot read {$name}");

// Historical linked events stay readable/cancellable without deleting audit history.
$update = fepe_method($historicalService, 'update');
$cancel = fepe_method($historicalService, 'cancel');
fepe_has($update, 'finance_employee_personal_expenses', 'Historical event update must keep master event synchronized');
fepe_has($cancel, "status='CANCELLED'", 'Historical cancellation must state-cancel linked operations');
fepe_not($cancel, 'DELETE FROM finance_operations', 'Cancellation must preserve finance operation history');
fepe_not($cancel, 'DELETE FROM finance_employee_movements', 'Cancellation must preserve employee ledger history');

// New entries use one direct economic operation on the employee account.
fepe_has($directService, 'FinanceEmployeeMoneyAccountService::accountId', 'New personal expense must use employee money account');
fepe_has($directService, "'EXPENSE','POSTED','EMPLOYEE'", 'New personal expense must be a direct employee expense');
fepe_has($directService, "'RETURN','EMPLOYEE'", 'Employee ledger must decrease for the company expense');
fepe_has($directService, "'cash_account_used' => false", 'Direct expense audit must record no retired account usage');
fepe_not($directService, 'finance_cash_resolutions', 'New personal expense must not create historical resolution rows');

foreach (['event_group_id','employee_movement_id','receipt_finance_operation_id','expense_finance_operation_id','cash_resolution_id'] as $column) {
    fepe_has($migration, $column, "Migration 074 must preserve historical {$column}");
}
fepe_has($routes, 'FinanceEmployeeDirectActions/personal_expense_create.php', 'Employee Payments must route new personal expense to direct action');
fepe_has($routes, '/personal-expense/{id}/update', 'Historical/update route must remain available');
fepe_has($routes, '/personal-expense/{id}/cancel', 'Cancellation route must remain available');
fepe_has($form, 'Сотрудник оплатил расход компании', 'Form must explain the approved business event');
fepe_has($form, 'в его взаиморасчётах и в расходах компании', 'Form must explain direct accounting result');
fepe_not($form, 'Основную кассу', 'Form must not expose retired internal model');
fepe_not($form, 'Основной кассы', 'Form must not expose retired internal model');
fepe_has($page, '$personalByMovement', 'Personal expenses must decorate their existing employee movement in the journal');
fepe_has($page, "['Прочий расход', 'Сотрудник'", 'Journal must label personal company expense semantically');
fepe_not($js, 'employee-personal-expense-list-host', 'Employee Payments must not render a second personal-expense history block');
fepe_has($js, 'Оплачено сотрудником', 'Employee Payments must retain the personal-expense entry fallback button');
fepe_has($js, 'data-personal-expense-cancel-event', 'Linked historical events must remain cancellable');
fepe_not($cashForm, '<option value="EMPLOYEE_PERSONAL_EXPENSE">', 'Retired generic form must not expose competing employee expense entry');
fepe_has($cashSubmit, 'оформляется в разделе «Выплаты сотрудникам»', 'Stale submissions must fail closed');

fwrite(STDOUT, "OK: linked/direct employee personal expense regression passed\n");
