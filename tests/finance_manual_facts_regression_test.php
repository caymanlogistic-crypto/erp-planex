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
assert_contains($client, "'operation_type' => 'INCOME'", 'Legacy client cash receipt writer must remain a real income operation');
assert_contains($client, "'linear_route_payment_id'", 'Legacy client cash receipt writer must preserve historical route-payment compatibility');
assert_contains($client, "finance_operation_allocations", 'Legacy client cash receipt writer must preserve manual allocation history');
assert_contains($client, "cascadeAfterAllocationCreate", 'Legacy client cash receipt writer must recalculate settlement');
assert_contains($client, "finance_cash_route_receipts", 'Legacy client cash receipt writer must keep historical route traceability');
assert_contains($client, "outflow_finance_operation_id", 'Legacy client cash receipt writer must explicitly use resolution without fake outflow');
assert_contains($client, "NULL, NULL", 'Legacy client cash resolution must not create outflow/employee movement');
assert_not_contains($client, "createCashMovement", 'Legacy client cash receipt writer must not create employee cash movement');

// Legacy 073 fact writer is preserved for historical compatibility only. The live entry point moved to Employee Payments.
$personal = method_body($service, 'createEmployeePersonalExpense');
assert_contains($personal, 'finance_employee_personal_expenses', 'Legacy personal-funded fact storage must remain readable');
assert_contains($personal, 'FinanceStructureService::assertAllowedPair', 'Legacy personal-funded fact must validate CFU/DDS pairing');
assert_not_contains($personal, 'FinanceCashService::createCashOperation', 'Legacy fact writer must not silently become a money writer');

assert_contains($migration, 'finance_cash_route_receipts', 'Migration must create cash-route receipt trace table');
assert_contains($migration, 'finance_employee_personal_expenses', 'Migration must create employee personal expense fact table');
assert_contains($migration, 'outflow_finance_operation_id` INT UNSIGNED DEFAULT NULL', 'Cash resolution must support business assignment without outflow');
assert_contains($migration, 'cash_flow_center_name_snapshot', 'Migration must preserve CFU snapshot');
assert_contains($migration, 'dds_category_name_snapshot', 'Migration must preserve DDS snapshot');

// The primary UI is invoice-centric now. Keep the legacy writer only for historical compatibility; do not expose route-first entry.
assert_contains($form, 'CLIENT_CASH_INVOICE', 'Cash UI must expose invoice-centric client cash receipt scenario');
assert_not_contains($form, '<option value="CLIENT_CASH_RECEIPT">', 'Cash UI must not expose the legacy route-first client cash scenario');
assert_not_contains($form, '<option value="EMPLOYEE_PERSONAL_EXPENSE">', 'Cash UI must not expose employee personal-funded expense entry point');
assert_not_contains($form, 'data-scenario-block="EMPLOYEE_PERSONAL_EXPENSE"', 'Cash UI must not keep a competing personal-expense form block');
assert_contains($page, 'initManualFinanceForm', 'Cash page must initialize fetched manual finance form');
assert_contains($page, 'Расходы сотрудников из личных средств', 'Cash page must keep historical/linked personal expense visibility');
assert_contains($submit, 'CLIENT_CASH_INVOICE', 'Cash submit must route client cash through invoice-centric settlement');
assert_contains($submit, 'оформляется в разделе «Выплаты сотрудникам»', 'Stale cash personal-expense submissions must fail closed and point to Employee Payments');
assert_contains($ledger, 'CASH_RESOLUTION_CLIENT_ROUTE', 'Cash ledger must preserve legacy route-assigned client cash visibility');
assert_contains($deletion, 'finance_employee_personal_expenses', 'CFU/DDS deletion must protect recorded personal expenses');

fwrite(STDOUT, "OK: manual finance facts regression passed\n");