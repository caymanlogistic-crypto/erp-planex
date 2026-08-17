<?php

declare(strict_types=1);

$root = dirname(__DIR__);

function fice_fail(string $message): never { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); }
function fice_has(string $text, string $needle, string $message): void { if (!str_contains($text, $needle)) fice_fail($message . " [missing: {$needle}]"); }
function fice_not(string $text, string $needle, string $message): void { if (str_contains($text, $needle)) fice_fail($message . " [unexpected: {$needle}]"); }
function fice_method(string $php, string $name): string {
    $start = strpos($php, 'function ' . $name . '(');
    if ($start === false) fice_fail("Method {$name} not found");
    $open = strpos($php, '{', $start);
    if ($open === false) fice_fail("Method {$name} body not found");
    $depth = 0; $len = strlen($php);
    for ($i=$open; $i<$len; $i++) {
        if ($php[$i] === '{') $depth++;
        elseif ($php[$i] === '}' && --$depth === 0) return substr($php, $open, $i-$open+1);
    }
    fice_fail("Method {$name} is not balanced");
}

$files = [
    'generic' => 'app/Service/FinanceOperationInvoiceSettlementService.php',
    'cash' => 'app/Service/FinanceCashInvoiceEventService.php',
    'employee' => 'app/Service/FinanceEmployeeInvoicePaymentEventService.php',
    'migration' => 'database/migrations-local/075_employee_invoice_payment_events.sql',
    'cashForm' => 'app/View/partials/company_cash_operation_form.php',
    'cashSubmit' => 'app/Http/Controllers/Company/FinanceCashActions/operation_create_submit.php',
    'cashIndex' => 'app/Http/Controllers/Company/FinanceCashActions/index.php',
    'employeeTools' => 'app/View/partials/company_finance_employee_invoice_tools.php',
    'employeeController' => 'app/Http/Controllers/Company/FinanceEmployeePaymentsController.php',
    'employeeRoutes' => 'app/Http/Routes/company_finance_employee_payments.php',
];
$data = [];
foreach ($files as $name => $path) {
    $value = file_get_contents($root . '/' . $path);
    if ($value === false) fice_fail("Cannot read {$path}");
    $data[$name] = $value;
}

$genericAllocate = fice_method($data['generic'], 'allocate');
$genericInsert = fice_method($data['generic'], 'insertAllocations');
$genericCancel = fice_method($data['generic'], 'cancelOperationInvoiceAllocations');
$clientCash = fice_method($data['cash'], 'createClientCashReceipt');
$carrierCash = fice_method($data['cash'], 'payCarrierInvoiceFromMainCash');
$cashToEmployee = fice_method($data['cash'], 'transferMainCashToEmployee');
$employeeCreate = fice_method($data['employee'], 'create');
$employeeUpdate = fice_method($data['employee'], 'update');
$employeeCancel = fice_method($data['employee'], 'cancel');

// One settlement model for every real-money source.
fice_has($data['generic'], "? FinanceInvoiceService::DIRECTION_OUTGOING", 'INCOME must settle outgoing invoices');
fice_has($data['generic'], ": FinanceInvoiceService::DIRECTION_INCOMING", 'EXPENSE must settle incoming invoices');
fice_has($genericAllocate, 'FinanceAllocationService::getOperationRemainingAmount', 'Generic settlement must respect operation remainder');
fice_has($genericAllocate, 'FinanceSettlementCascadeService::getInvoiceRemainingAmount', 'Generic settlement must respect invoice remainder');
fice_has($genericInsert, 'finance_invoice_links', 'Generic settlement must honor invoice obligation links');
fice_has($genericInsert, 'obligation_id', 'Generic settlement must allocate through obligation ids');
fice_has($genericAllocate, 'FinanceSettlementCascadeService::cascadeAfterAllocationCreate', 'Allocation must cascade settlement');
fice_has($genericCancel, 'cancelled_at=NOW()', 'Allocation cancellation must preserve history');
fice_has($genericCancel, 'cascadeAfterAllocationCancel', 'Allocation cancellation must reopen settlement state');
fice_not($genericCancel, 'DELETE FROM finance_operation_allocations', 'Real allocation history must never be deleted');

// Client cash: physical receipt may be larger than invoice settlement.
fice_has($clientCash, "'operation_type'=>'INCOME'", 'Client cash must physically increase Main Cash');
fice_has($clientCash, "['invoice_amount']", 'Client cash must support a separate invoice allocation amount');
fice_has($clientCash, 'FinanceOperationInvoiceSettlementService::allocate', 'Client cash must settle the selected invoice');
fice_has($clientCash, "'unallocated_amount'", 'Client cash audit must preserve unallocated remainder semantics');
fice_not($clientCash, 'linear_route_payment_id', 'New client cash flow must not bypass invoice settlement via route payment id');

// Main Cash can pay an invoice or hand fungible cash to an employee.
fice_has($carrierCash, "'operation_type'=>'EXPENSE'", 'Main Cash carrier payment must be a real expense');
fice_has($carrierCash, 'FinanceOperationInvoiceSettlementService::allocate', 'Main Cash carrier payment must settle invoice');
fice_has($cashToEmployee, "'movement_type'=>'PAYMENT'", 'Main Cash handoff must increase employee-held balance');
fice_has($cashToEmployee, "operation_type='TRANSFER'", 'Main Cash handoff must be technical transfer, not economic expense');
fice_has($cashToEmployee, "transfer_direction='out'", 'Main Cash handoff must decrease Main Cash');
fice_not($cashToEmployee, 'sourceOperationIds', 'New employee handoff must not depend on a particular source receipt');

// Employee pays incoming invoice: RETURN -> TRANSFER IN -> EXPENSE -> invoice allocation.
fice_has($employeeCreate, "'movement_type'=>'RETURN'", 'Employee invoice payment must reduce employee-held balance');
fice_has($employeeCreate, "operation_type='TRANSFER'", 'Employee return into Main Cash must be technical transfer');
fice_has($employeeCreate, "transfer_direction='in'", 'Technical employee return must enter Main Cash');
fice_has($employeeCreate, "'operation_type'=>'EXPENSE'", 'Carrier payment must leave Main Cash as a real expense');
fice_has($employeeCreate, 'FinanceOperationInvoiceSettlementService::allocate', 'Employee payment must settle incoming invoice');
fice_has($employeeCreate, "'main_cash_net_effect'=>'0.00'", 'Employee invoice payment must audit zero Main Cash net effect');
fice_not($employeeCreate, 'cash_flow_center_id', 'Invoice payment mode must not ask for CFU');
fice_not($employeeCreate, 'dds_category_id', 'Invoice payment mode must not ask for DDS article');

fice_has($employeeUpdate, 'cancelOperationInvoiceAllocations', 'Edit must first cancel old invoice allocations');
fice_has($employeeUpdate, 'FinanceOperationInvoiceSettlementService::allocate', 'Edit must recreate settlement using updated amount/invoice');
fice_has($employeeCancel, 'cancelOperationInvoiceAllocations', 'Cancel must reopen invoice/obligations');
fice_has($employeeCancel, "status='CANCELLED'", 'Cancel must state-cancel linked money operations');
fice_not($employeeCancel, 'DELETE FROM finance_operations', 'Cancel must preserve money history');
fice_not($employeeCancel, 'DELETE FROM finance_employee_movements', 'Cancel must preserve employee history');

foreach (['event_group_id','invoice_id','employee_movement_id','receipt_finance_operation_id','expense_finance_operation_id','status','cancellation_reason'] as $column) {
    fice_has($data['migration'], $column, "Migration 075 must store {$column}");
}
fice_has($data['migration'], 'ON DELETE RESTRICT', 'Migration 075 must protect linked finance history');

// UX contracts.
foreach (['CLIENT_CASH_INVOICE','MAIN_CASH_INVOICE_PAYMENT','MAIN_CASH_EMPLOYEE_TRANSFER'] as $scenario) {
    fice_has($data['cashForm'], $scenario, "Cash form must expose {$scenario}");
    fice_has($data['cashSubmit'], $scenario, "Cash submit must handle {$scenario}");
}
fice_not($data['cashForm'], 'CLIENT_CASH_RECEIPT">Оплата клиента наличными за рейс', 'Old route-first client cash UI must be removed');
fice_has($data['cashIndex'], '.cash-batch-bar', 'Legacy source-specific dispatch bar must be hidden from primary UI');
fice_has($data['employeeTools'], 'Оплатил счёт', 'Employee page must expose invoice-payment entry');
fice_has($data['employeeTools'], 'Прочий расход', 'Employee page must keep misc-expense entry distinct');
fice_has($data['employeeTools'], 'ЦФУ и статью ДДС выбирать не нужно', 'Invoice payment UI must explain no manual CFU/DDS');
fice_has($data['employeeController'], 'FinanceEmployeeInvoicePaymentEventService::fetchForEmployee', 'Employee page must load invoice payment events');
fice_has($data['employeeRoutes'], '/invoice-payment/create', 'Employee invoice create route must exist');
fice_has($data['employeeRoutes'], '/invoice-payment/update', 'Employee invoice update route must exist');
fice_has($data['employeeRoutes'], '/invoice-payment/cancel', 'Employee invoice cancel route must exist');

fwrite(STDOUT, "OK: invoice-centric cash and employee finance regression passed\n");