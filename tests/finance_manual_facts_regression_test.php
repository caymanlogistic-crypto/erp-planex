<?php

declare(strict_types=1);

$root = dirname(__DIR__);

function fail_test(string $message): never
{
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
}

function assert_contains(string $haystack, string $needle, string $message): void
{
    if (!str_contains($haystack, $needle)) fail_test($message . " [missing: {$needle}]");
}

function assert_not_contains(string $haystack, string $needle, string $message): void
{
    if (str_contains($haystack, $needle)) fail_test($message . " [unexpected: {$needle}]");
}

function method_body(string $php, string $methodName): string
{
    $marker = 'function ' . $methodName . '(';
    $start = strpos($php, $marker);
    if ($start === false) fail_test("Method {$methodName} not found");
    $open = strpos($php, '{', $start);
    if ($open === false) fail_test("Method {$methodName} body not found");
    $depth = 0;
    $len = strlen($php);
    for ($i = $open; $i < $len; $i++) {
        if ($php[$i] === '{') $depth++;
        if ($php[$i] === '}') {
            $depth--;
            if ($depth === 0) return substr($php, $open, $i - $open + 1);
        }
    }
    fail_test("Method {$methodName} is not balanced");
}

$service = file_get_contents($root . '/app/Service/FinanceManualFactService.php');
$migration = file_get_contents($root . '/database/migrations-local/073_finance_manual_cash_and_personal_expense_facts.sql');
$form = file_get_contents($root . '/app/View/partials/company_cash_operation_form.php');
$page = file_get_contents($root . '/app/View/pages/company_finance_cash.php');
$submit = file_get_contents($root . '/app/Http/Controllers/Company/FinanceCashActions/operation_create_submit.php');
$ledger = file_get_contents($root . '/app/Service/FinanceCashLedgerService.php');
$deletion = file_get_contents($root . '/app/Service/FinanceStructureDeletionService.php');

foreach (compact('service','migration','form','page','submit','ledger','deletion') as $name => $value) {
    if ($value === false) fail_test("Cannot read {$name}");
}

$client = method_body($service, 'createClientCashReceipt');
assert_contains($client, "'operation_type' => 'INCOME'", 'Client cash receipt must create real income');
assert_contains($client, "'linear_route_payment_id'", 'Client cash receipt must target an explicit route payment');
assert_contains($client, "finance_operation_allocations", 'Client cash receipt must create a manual allocation');
assert_contains($client, "cascadeAfterAllocationCreate", 'Client cash receipt must recalculate settlement');
assert_contains($client, "finance_cash_route_receipts", 'Client cash receipt must keep route traceability');
assert_contains($client, "outflow_finance_operation_id", 'Client cash receipt must explicitly use resolution without fake outflow');
assert_contains($client, "NULL, NULL", 'Client cash resolution must not create outflow/employee movement');
assert_not_contains($client, "createCashMovement", 'Client cash receipt must not create employee cash movement');

$personal = method_body($service, 'createEmployeePersonalExpense');
assert_contains($personal, 'finance_employee_personal_expenses', 'Personal-funded expense must be stored as its own economic fact');
assert_contains($personal, 'FinanceStructureService::assertAllowedPair', 'Personal-funded expense must validate CFU/DDS pairing');
assert_contains($personal, 'cash_flow_center_name_snapshot', 'Personal-funded expense must snapshot CFU name');
assert_contains($personal, 'dds_category_name_snapshot', 'Personal-funded expense must snapshot DDS name');
assert_not_contains($personal, 'FinanceCashService::createCashOperation', 'Personal-funded expense must not change cash balance');
assert_not_contains($personal, 'FinanceOperationService::', 'Personal-funded expense must not create corporate money operation');
assert_not_contains($personal, 'finance_employee_movements', 'Personal-funded expense must not create employee debt/settlement movement');

assert_contains($migration, 'finance_cash_route_receipts', 'Migration must create cash-route receipt trace table');
assert_contains($migration, 'finance_employee_personal_expenses', 'Migration must create employee personal expense fact table');
assert_contains($migration, 'outflow_finance_operation_id` INT UNSIGNED DEFAULT NULL', 'Cash resolution must support business assignment without outflow');
assert_contains($migration, 'cash_flow_center_name_snapshot', 'Migration must preserve CFU snapshot');
assert_contains($migration, 'dds_category_name_snapshot', 'Migration must preserve DDS snapshot');

assert_contains($form, 'CLIENT_CASH_RECEIPT', 'UI must expose client cash receipt scenario');
assert_contains($form, 'EMPLOYEE_PERSONAL_EXPENSE', 'UI must expose employee personal-funded expense scenario');
assert_contains($form, 'data-allowed-expense-dds-map', 'UI must carry allowed manual CFU/DDS map');
assert_not_contains($form, '<script>', 'Fetched modal partial must not rely on non-executing embedded script');
assert_contains($page, 'initManualFinanceForm', 'Cash page must initialize fetched manual finance form');
assert_contains($page, 'Расходы сотрудников из личных средств', 'Cash page must display recorded personal-funded expenses');
assert_contains($submit, "client_dds_category_id", 'Submit must normalize client DDS server-side');
assert_contains($submit, "personal_dds_category_id", 'Submit must normalize personal expense DDS server-side');
assert_contains($submit, "personal_linear_route_id", 'Submit must normalize optional personal expense route server-side');
assert_contains($ledger, 'CASH_RESOLUTION_CLIENT_ROUTE', 'Cash ledger must distinguish route-assigned client cash from employee handoff');
assert_contains($deletion, 'finance_employee_personal_expenses', 'CFU/DDS deletion must protect recorded personal expenses');

fwrite(STDOUT, "OK: manual finance facts regression passed\n");
