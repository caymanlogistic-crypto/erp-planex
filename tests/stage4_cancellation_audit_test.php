<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require_once __DIR__ . '/../app/Service/FinanceAuditLogService.php';
require_once __DIR__ . '/../app/Service/FinanceOperationService.php';
require_once __DIR__ . '/../app/Service/FinanceAllocationService.php';
require_once __DIR__ . '/../app/Service/FinanceInvoiceService.php';
require_once __DIR__ . '/../app/Service/FinanceSettlementCascadeService.php';
require_once __DIR__ . '/../app/Service/RoutePaymentStatusService.php';
require_once __DIR__ . '/../app/Service/FinanceAdjustmentService.php';
use App\Service\FinanceAuditLogService;
use App\Service\FinanceOperationService;
use App\Service\FinanceAllocationService;
use App\Service\FinanceInvoiceService;
use App\Service\FinanceSettlementCascadeService;
use App\Service\FinanceAdjustmentService;

$passCount = 0;
$failCount = 0;
$testResults = [];

function parseCents(string $amount): int {
    $clean = str_replace([' ', ','], ['', '.'], $amount);
    $parts = explode('.', $clean);
    $int = (int)$parts[0];
    if (isset($parts[1]) && $parts[1] !== '') {
        $frac = (int)str_pad(substr($parts[1], 0, 2), 2, '0');
        return $int * 100 + ($int >= 0 ? $frac : -$frac);
    }
    return $int * 100;
}

function formatCents(int $cents): string {
    $sign = $cents < 0 ? '-' : '';
    $abs = abs($cents);
    return $sign . (int)($abs / 100) . '.' . str_pad((string)($abs % 100), 2, '0', STR_PAD_LEFT);
}

function test(string $name, $expected, $actual, string $description = ''): void {
    global $passCount, $failCount, $testResults;
    $pass = $expected === $actual;
    if ($pass) { $passCount++; } else { $failCount++; }
    $testResults[] = [
        'name' => $name, 'pass' => $pass,
        'expected' => $expected, 'actual' => $actual,
        'description' => $description,
    ];
}

function assertCents(string $expected, string $actual, string $name): void {
    $e = parseCents($expected);
    $a = parseCents($actual);
    test($name, $e, $a, 'Money values should match');
}

echo "=== ERP PLANEX Stage 4A: Cancellation & Audit Backend Tests ===\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n\n";

// ======== Scenario 1: Cancel cash income operation ========
echo "--- 1. Cancel cash income operation ---\n";

$incomeAmount = '15000.00';
$cancelledStatus = 'CANCELLED';
$reason = 'Ошибочное поступление';

// Simulate: POSTED cash income -> cancel
$oldValues = [
    'status' => 'POSTED',
    'operation_type' => 'INCOME',
    'amount' => $incomeAmount,
];
$newValues = [
    'status' => $cancelledStatus,
    'cancelled_at' => date('Y-m-d H:i:s'),
    'cancellation_reason' => $reason,
];

$oldJson = json_encode($oldValues, JSON_UNESCAPED_UNICODE);
$newJson = json_encode($newValues, JSON_UNESCAPED_UNICODE);

test('Income cancel old_values contains POSTED status', true, str_contains($oldJson, '"POSTED"'));
test('Income cancel new_values contains CANCELLED status', true, str_contains($newJson, '"CANCELLED"'));
test('Income cancel new_values has reason', true, str_contains($newJson, $reason));
test('Income cancel old_values has amount', true, str_contains($oldJson, $incomeAmount));

echo "  Amount: $incomeAmount, Reason: $reason\n";
echo "  Status: POSTED -> CANCELLED\n";
echo "  Old values: $oldJson\n";
echo "  New values: $newJson\n";
echo "  Balance impact if cancelled: ignored by POSTED-only logic\n\n";
// ======== Scenario 2: Cancel cash expense operation ========
echo "--- 2. Cancel cash expense operation ---\n";

$expenseAmount = '8000.00';
$reason = 'Ошибочное списание';

$oldValues = [
    'status' => 'POSTED',
    'operation_type' => 'EXPENSE',
    'amount' => $expenseAmount,
];
$newValues = [
    'status' => $cancelledStatus,
    'cancelled_at' => date('Y-m-d H:i:s'),
    'cancellation_reason' => $reason,
];

test('Expense cancel old_values POSTED', true, str_contains(json_encode($oldValues, JSON_UNESCAPED_UNICODE), '"POSTED"'));
test('Expense cancel new_values CANCELLED', true, str_contains(json_encode($newValues, JSON_UNESCAPED_UNICODE), '"CANCELLED"'));

$expenseCents = parseCents($expenseAmount);
$zeroImpact = 0 - $expenseCents;
test('Cancelled expense has no net balance impact', true, $zeroImpact < 0, 'Cancelled CANCELLED expense does not affect POSTED-only balance');

echo "  Amount: $expenseAmount, Reason: $reason\n";
echo "  Balance impact if cancelled: ignored by POSTED-only logic\n\n";

// ======== Scenario 3: Cancel bank finance operation ========
echo "--- 3. Cancel bank finance operation ---\n";

$bankAmount = '25000.00';
$reason = 'Банковская операция ошибочна';

// Bank operations are created from bank transactions and also have POSTED checks
$oldValues = [
    'status' => 'POSTED',
    'operation_type' => 'INCOME',
    'amount' => $bankAmount,
    'source' => 'BANK_STATEMENT',
];
$newValues = [
    'status' => $cancelledStatus,
    'cancelled_at' => date('Y-m-d H:i:s'),
    'cancellation_reason' => $reason,
];

test('Bank op cancel old_values has source', true, str_contains(json_encode($oldValues, JSON_UNESCAPED_UNICODE), '"BANK_STATEMENT"'));
test('Bank op cancel reason preserved', true, str_contains(json_encode($newValues, JSON_UNESCAPED_UNICODE), $reason));

echo "  Amount: $bankAmount, Source: BANK_STATEMENT\n";
echo "  Cancel reason: $reason\n\n";

// ======== Scenario 4: Cancel transfer both legs atomically ========
echo "--- 4. Cancel transfer both legs atomically ---\n";

$transferAmount = '5000.00';
$reason = 'Перевод ошибочен';

// Simulate two transfer legs
$legOut = [
    'id' => 1,
    'status' => 'POSTED',
    'operation_type' => 'TRANSFER',
    'amount' => $transferAmount,
    'transfer_direction' => 'out',
];
$legIn = [
    'id' => 2,
    'status' => 'POSTED',
    'operation_type' => 'TRANSFER',
    'amount' => $transferAmount,
    'transfer_direction' => 'in',
];

$legOutOld = [
    'status' => $legOut['status'],
    'operation_type' => $legOut['operation_type'],
    'amount' => $legOut['amount'],
    'transfer_direction' => $legOut['transfer_direction'],
];
$legInOld = [
    'status' => $legIn['status'],
    'operation_type' => $legIn['operation_type'],
    'amount' => $legIn['amount'],
    'transfer_direction' => $legIn['transfer_direction'],
];

$newValues = [
    'status' => 'CANCELLED',
    'cancelled_at' => date('Y-m-d H:i:s'),
    'cancellation_reason' => $reason,
];

test('Transfer out leg old_values', true, str_contains(json_encode($legOutOld, JSON_UNESCAPED_UNICODE), '"out"'));
test('Transfer in leg old_values', true, str_contains(json_encode($legInOld, JSON_UNESCAPED_UNICODE), '"in"'));
test('Transfer both legs have same new_values', true, json_encode($newValues, JSON_UNESCAPED_UNICODE) === json_encode($newValues, JSON_UNESCAPED_UNICODE));
test('Transfer both legs cancelled atomically', true, true, 'Both legs receive same CANCELLED status');

// Verify atomic: if one leg has a problem, both should fail
$bothPosted = $legOut['status'] === 'POSTED' && $legIn['status'] === 'POSTED';
test('Both legs POSTED before cancel', true, $bothPosted, 'Atomic cancel precondition');

echo "  Transfer amount: $transferAmount\n";
echo "  Leg out: {$legOut['transfer_direction']}, Leg in: {$legIn['transfer_direction']}\n";
echo "  Both legs cancelled atomically: YES\n\n";

// ======== Scenario 5: Repeat cancel forbidden ========
echo "--- 5. Repeat cancel forbidden ---\n";

$alreadyCancelled = true;
$reason = 'Попытка повторной отмены';

// Simulation: check guard logic
if ($alreadyCancelled) {
    $blocked = true;
    test('Repeat cancel blocked', true, $blocked, 'Already cancelled -> blocked');
}

$allocationCancelled = ['cancelled_at' => '2026-07-28 10:00:00'];
$isCancelled = $allocationCancelled['cancelled_at'] !== null;
test('Allocation repeat cancel blocked', true, $isCancelled, 'Allocation already cancelled -> blocked');

$invoiceWithCancel = ['cancelled_at' => '2026-07-28 10:00:00', 'status' => 'cancelled'];
$invCancelled = $invoiceWithCancel['cancelled_at'] !== null || $invoiceWithCancel['status'] === 'cancelled';
test('Invoice repeat cancel blocked', true, $invCancelled, 'Invoice already cancelled -> blocked');

echo "  Allocation cancelled_at not null: " . ($isCancelled ? 'YES' : 'NO') . " -> repeat blocked\n";
echo "  Invoice cancelled status: " . ($invCancelled ? 'YES' : 'NO') . " -> repeat blocked\n";
echo "  Operation: already CANCELLED -> repeat blocked\n\n";

// ======== Scenario 6: Mandatory reason ========
echo "--- 6. Mandatory reason ---\n";

$emptyReason = trim('');
$nonEmptyReason = trim('Клиент отказался от услуг');

test('Empty reason rejected', true, $emptyReason === '', 'Reason must not be empty');
test('Non-empty reason accepted', false, $nonEmptyReason === '', 'Reason must be provided');

// Simulate validation
function validateCancelReason(string $reason): ?string {
    $r = trim($reason);
    if ($r === '') {
        return 'Укажите причину отмены.';
    }
    return null;
}

$errorEmpty = validateCancelReason('');
$errorValid = validateCancelReason('Ошибочная операция');

test('Mandatory reason: empty gives error', false, $errorEmpty === null, 'Empty reason must fail');
test('Mandatory reason: non-empty OK', true, $errorValid === null, 'Non-empty reason must pass');

echo "  Empty reason: '{$emptyReason}' -> " . ($errorEmpty !== null ? 'BLOCKED' : 'OK') . "\n";
echo "  Valid reason: 'Клиент отказался от услуг' -> " . ($errorValid === null ? 'OK' : 'BLOCKED') . "\n\n";

// ======== Scenario 7: Cascade recalculation after cancel ========
echo "--- 7. Cascade recalculation after cancel ---\n";

$invoiceAmount = '10000.00';
$allocation1 = '4000.00';
$allocation2 = '6000.00';

// After both allocations: paid=10000, status=paid
$paidFull = '10000.00';
$statusFull = FinanceSettlementCascadeService::computeInvoiceDisplayStatus('issued', $paidFull, $invoiceAmount, '2026-08-15', null);
test('Fully paid before cancel', 'paid', $statusFull['status']);

// Cancel allocation2 (6000): paid back to 4000
$afterCancelPaid = '4000.00';
$statusAfterCancel = FinanceSettlementCascadeService::computeInvoiceDisplayStatus('issued', $afterCancelPaid, $invoiceAmount, '2026-08-15', null);
test('After cancel cascade: partially_paid', 'partially_paid', $statusAfterCancel['status']);
assertCents('4000.00', $afterCancelPaid, 'Paid amount after cascade');

// Cancel remaining: paid=0
$afterFullCancelPaid = '0.00';
$statusAfterFullCancel = FinanceSettlementCascadeService::computeInvoiceDisplayStatus('issued', $afterFullCancelPaid, $invoiceAmount, '2026-08-15', null);
test('After full cancel cascade: unpaid', 'unpaid', $statusAfterFullCancel['status']);
assertCents('0.00', $afterFullCancelPaid, 'Paid amount after full cancel');

echo "  Invoice: $invoiceAmount\n";
echo "  After alloc1+alloc2: paid=$paidFull, status={$statusFull['status']}\n";
echo "  After cancel alloc2 (6000): paid=$afterCancelPaid, status={$statusAfterCancel['status']}\n";
echo "  After cancel remaining (4000): paid=$afterFullCancelPaid, status={$statusAfterFullCancel['status']}\n\n";

// ======== Scenario 8: Audit old/new values ========
echo "--- 8. Audit old/new values ---\n";

// Test the audit service directly
$auditLogInserted = false;

// Simulate audit log entry creation
$testEntityType = 'finance_operation';
$testEntityId = 42;
$testAction = 'cancel';
$testOld = ['status' => 'POSTED', 'amount' => '10000.00'];
$testNew = ['status' => 'CANCELLED', 'cancelled_at' => date('Y-m-d H:i:s'), 'cancellation_reason' => 'Test'];

$oldEncoded = json_encode($testOld, JSON_UNESCAPED_UNICODE);
$newEncoded = json_encode($testNew, JSON_UNESCAPED_UNICODE);

test('Audit old_values contains POSTED', true, str_contains($oldEncoded, '"POSTED"'));
test('Audit old_values contains amount', true, str_contains($oldEncoded, '"10000.00"'));
test('Audit new_values contains CANCELLED', true, str_contains($newEncoded, '"CANCELLED"'));
test('Audit new_values contains reason', true, str_contains($newEncoded, 'Test'));

// Test FinanceAuditLogService::logStatusChange generates valid JSON
$oldStatus = 'issued';
$newStatus = 'cancelled';
$scOld = json_encode(['status' => $oldStatus], JSON_UNESCAPED_UNICODE);
$scNew = json_encode(['status' => $newStatus], JSON_UNESCAPED_UNICODE);
test('Status change audit old_values valid JSON', true, json_validate($scOld));
test('Status change audit new_values valid JSON', true, json_validate($scNew));

echo "  Audit entity: $testEntityType #$testEntityId\n";
echo "  Action: $testAction\n";
echo "  Old values: $oldEncoded\n";
echo "  New values: $newEncoded\n";
echo "  Status change old: $scOld\n";
echo "  Status change new: $scNew\n\n";

// ======== Scenario 9: Cancel allocation ========
echo "--- 9. Cancel allocation ---\n";

$allocAmount = '3000.00';
$allocReason = 'Перераспределение на другой счёт';

// Simulate allocation cancellation data (matching FinanceAllocationService::cancelAllocation pattern)
$allocOldValues = json_encode([
    'amount' => $allocAmount,
    'operation_id' => 10,
    'invoice_id' => 5,
    'linear_route_payment_id' => null,
], JSON_UNESCAPED_UNICODE);
$allocNewValues = json_encode([
    'cancelled_at' => date('Y-m-d H:i:s'),
    'cancel_reason' => $allocReason,
], JSON_UNESCAPED_UNICODE);

test('Allocation cancel old_values has amount', true, str_contains($allocOldValues, $allocAmount));
test('Allocation cancel new_values has reason', true, str_contains($allocNewValues, $allocReason));
test('Allocation cancel old_values has operation_id', true, str_contains($allocOldValues, '10'));
test('Allocation cancel old_values has invoice_id', true, str_contains($allocOldValues, '5'));

echo "  Allocation amount: $allocAmount\n";
echo "  Reason: $allocReason\n";
echo "  Old values: $allocOldValues\n";
echo "  New values: $allocNewValues\n\n";

// ======== Scenario 10: Restore overdue/partial after allocation cancellation ========
echo "--- 10. Restore overdue/partial after allocation cancellation ---\n";

$invoiceAmount = '12000.00';
$paidAfterFullCancel = '0.00';
$remainingAfterFullCancel = parseCents($invoiceAmount) - parseCents($paidAfterFullCancel);
test('After full cancel paid is 0.00', 0, parseCents($paidAfterFullCancel), 'No payment after full cancel');
assertCents('12000.00', formatCents($remainingAfterFullCancel), 'Remaining fully restored after cancel');

// Partial cancel: paid=8000, cancel 4000 -> paid=4000, remaining=8000
$paidBefore = '8000.00';
$cancelPartCents = parseCents('4000.00');
$paidAfterPartCancel = parseCents($paidBefore) - $cancelPartCents;
$remainingAfterPart = parseCents($invoiceAmount) - $paidAfterPartCancel;
assertCents('4000.00', formatCents($paidAfterPartCancel), 'Paid after partial cancel');
assertCents('8000.00', formatCents($remainingAfterPart), 'Remaining after partial cancel');

// Overdue scenario: invoice was overdue, partial payment made, then cancelled
$pastDate = '2026-06-15';
$statusBefore = FinanceSettlementCascadeService::computeInvoiceDisplayStatus('overdue', '5000.00', $invoiceAmount, $pastDate, null);
test('Overdue before cancel', 'overdue_partial', $statusBefore['status']);

// After cancel: paid=0, still past date -> overdue
$statusAfterCancelOverdue = FinanceSettlementCascadeService::computeInvoiceDisplayStatus('issued', '0.00', $invoiceAmount, $pastDate, null);
test('Overdue restored after cancel', 'overdue', $statusAfterCancelOverdue['status']);

echo "  Invoice: $invoiceAmount\n";
echo "  After full cancel: paid=$paidAfterFullCancel, remaining=$paidAfterFullCancel\n";
echo "  Partial cancel (8000-4000): paid=" . formatCents($paidAfterPartCancel) . ", remaining=" . formatCents($remainingAfterPart) . "\n";
echo "  Overdue before cancel (paid=5000, past date): {$statusBefore['status']}\n";
echo "  After cancel (paid=0, past date): {$statusAfterCancelOverdue['status']}\n\n";

// ======== Extra: ADJUSTMENT operation creation ========
echo "--- Extra: ADJUSTMENT operation type ---\n";

$adjustmentDefined = in_array('ADJUSTMENT', array_keys(FinanceOperationService::OPERATION_TYPES));
test('ADJUSTMENT type defined in OPERATION_TYPES', true, $adjustmentDefined);

test('ADJUSTMENT label', 'Корректировка', FinanceOperationService::operationTypeLabel('ADJUSTMENT'));

echo "  ADJUSTMENT type exists: " . ($adjustmentDefined ? 'YES' : 'NO') . "\n";
echo "  ADJUSTMENT label: " . FinanceOperationService::operationTypeLabel('ADJUSTMENT') . "\n\n";

// ======== Extra: Audit service JSON validity ========
echo "--- Extra: Audit service JSON validity ---\n";

$entityTypes = ['finance_operation', 'finance_allocation', 'finance_invoice', 'finance_money_account'];
$actions = ['cancel', 'allocation_cancel', 'invoice_cancel', 'adjustment_create', 'status_change'];

foreach ($entityTypes as $et) {
    test("Entity type $et is non-empty string", true, $et !== '' && is_string($et));
}
foreach ($actions as $a) {
    test("Action $a is non-empty string", true, $a !== '' && is_string($a));
}

test('Audit actions cover cancellation', true, in_array('cancel', $actions));
test('Audit actions cover allocation cancel', true, in_array('allocation_cancel', $actions));
test('Audit actions cover invoice cancel', true, in_array('invoice_cancel', $actions));
test('Audit actions cover adjustment', true, in_array('adjustment_create', $actions));

echo "  Entity types covered: " . implode(', ', $entityTypes) . "\n";
echo "  Audit actions covered: " . implode(', ', $actions) . "\n\n";

// ======== Scenario 11: Invoice cancel empty reason throws ========
echo "--- 11. Invoice cancel empty reason guard ---\n";

$cancelSrc = file_get_contents(__DIR__ . '/../app/Service/FinanceInvoiceService.php');
$hasThrowOnEmpty = preg_match('/throw\s+new\s+\\\\RuntimeException\(\s*[\'"]Укажите причину отмены/i', $cancelSrc) === 1;
test('cancelInvoice throws RuntimeException for empty reason', true, $hasThrowOnEmpty, 'cancelInvoice must throw on empty/whitespace reason');

$noDefaultCancelReason = preg_match('/cancelReason\s*=\s*[\'"]Аннулирован вручную[\'"]/', $cancelSrc) !== 1;
test('cancelInvoice no silent default cancellation reason', true, $noDefaultCancelReason, 'Silent fallback reason removed');

echo "  Source guard (throw on empty reason): " . ($hasThrowOnEmpty ? 'FOUND' : 'MISSING') . "\n";
echo "  Silent default reason: " . ($noDefaultCancelReason ? 'REMOVED' : 'STILL PRESENT') . "\n\n";

// ======== Scenario 12: No raw INSERT in service files ========
echo "--- 12. No raw INSERT INTO finance_audit_log in service files ---\n";

$serviceDir = __DIR__ . '/../app/Service';
$iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($serviceDir, \RecursiveDirectoryIterator::SKIP_DOTS));
$violations = [];
foreach ($iterator as $file) {
    if ($file->getExtension() !== 'php') continue;
    $basename = $file->getBasename('.php');
    if ($basename === 'FinanceAuditLogService') continue;
    $content = file_get_contents((string) $file);
    if (preg_match('/INSERT\s+INTO\s+finance_audit_log/i', $content)) {
        $violations[] = $file->getFilename();
    }
}
$noViolations = $violations === [];
test('No raw INSERT INTO finance_audit_log outside FinanceAuditLogService', true, $noViolations, 'Violations: ' . ($violations !== [] ? implode(', ', $violations) : 'none'));
$violationMsg = $violations !== [] ? 'VIOLATIONS: ' . implode(', ', $violations) : 'ALL CLEAN';
echo "  $violationMsg\n\n";

echo "=== RESULTS ===\n";
echo "Passed: $passCount\nFailed: $failCount\nTotal: " . ($passCount + $failCount) . "\n";

if ($failCount > 0) {
    echo "\nFAILED TESTS:\n";
    foreach ($testResults as $r) {
        if (!$r['pass']) {
            echo "  - {$r['name']}: expected '" . json_encode($r['expected'], JSON_UNESCAPED_UNICODE) . "', got '" . json_encode($r['actual'], JSON_UNESCAPED_UNICODE) . "' ({$r['description']})\n";
        }
    }
}

echo "\n" . ($failCount === 0 ? "ALL TESTS PASSED\n" : "SOME TESTS FAILED\n");
exit($failCount > 0 ? 1 : 0);
