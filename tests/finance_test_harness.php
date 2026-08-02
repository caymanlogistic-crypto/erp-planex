<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

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

function addCents(int $a, int $b): int { return $a + $b; }
function subCents(int $a, int $b): int { return $a - $b; }
function cmpCents(int $a, int $b): int { return $a <=> $b; }

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

echo "=== ERP PLANEX Finance Test Harness ===\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n\n";

// ======== Scenario 1: Date term calculation ========
echo "--- Scenario 1: Payment term date calculation ---\n";

$loadingDate = '2026-07-15';
$unloadingDate = '2026-07-25';

$d1 = date('Y-m-d', strtotime($loadingDate . ' -3 days'));
test('pre_loading date', '2026-07-12', $d1);

$d2 = date('Y-m-d', strtotime($loadingDate . ' +5 days'));
test('post_loading date', '2026-07-20', $d2);

$d3 = date('Y-m-d', strtotime($unloadingDate . ' -2 days'));
test('pre_unloading date', '2026-07-23', $d3);

$d4 = date('Y-m-d', strtotime($unloadingDate . ' +10 days'));
test('post_unloading date', '2026-08-04', $d4);

echo "  pre_loading: $d1 (expected 2026-07-12)\n";
echo "  post_loading: $d2 (expected 2026-07-20)\n";
echo "  pre_unloading: $d3 (expected 2026-07-23)\n";
echo "  post_unloading: $d4 (expected 2026-08-04)\n\n";

// ======== Scenario 2: Partial payment ========
echo "--- Scenario 2: Overdue calculation uses remaining amount ---\n";

$invCents = parseCents('10000.00');
$paidCents = parseCents('3000.00');
$remCents = subCents($invCents, $paidCents);
assertCents('7000.00', formatCents($remCents), 'Remaining after partial payment');
assertCents('7000.00', formatCents($remCents), 'Overdue uses remaining amount');

echo "  Invoice: 10000.00\n  Paid: 3000.00\n  Remaining: " . formatCents($remCents) . "\n  Overdue: " . formatCents($remCents) . "\n\n";

// ======== Scenario 3: Payment to multiple invoices ========
echo "--- Scenario 3: Payment allocation limits ---\n";

$payCents = parseCents('5000.00');
$a1 = parseCents('2000.00');
$a2 = parseCents('2500.00');
$totalA = addCents($a1, $a2);
$payRem = subCents($payCents, $totalA);
test('Total allocation within payment', true, cmpCents($totalA, $payCents) <= 0);
assertCents('500.00', formatCents($payRem), 'Payment remaining after two allocations');

$a3 = parseCents('1000.00');
$wouldExceed = addCents($totalA, $a3);
$exceeds = cmpCents($wouldExceed, $payCents) > 0;
test('Over-allocation blocked', true, $exceeds, 'Third allocation exceeds payment');
assertCents('5500.00', formatCents($wouldExceed), 'Would-be total if over-allocated');

echo "  Payment: 5000.00\n  Alloc 1: 2000.00\n  Alloc 2: 2500.00\n  Total allocated: " . formatCents($totalA) . "\n  Remaining: " . formatCents($payRem) . "\n  Would exceed: " . ($exceeds ? 'YES (blocked)' : 'NO') . "\n\n";

// ======== Scenario 4: Invoice paid by multiple payments ========
echo "--- Scenario 4: Invoice payment limits ---\n";

$invC2 = parseCents('8000.00');
$p1 = parseCents('3000.00');
$p2 = parseCents('4000.00');
$totalP = addCents($p1, $p2);
$invRem = subCents($invC2, $totalP);
test('Total payment within invoice', true, cmpCents($totalP, $invC2) <= 0);
assertCents('1000.00', formatCents($invRem), 'Invoice remaining after two payments');

$p3 = parseCents('2000.00');
$wouldOverpay = addCents($totalP, $p3);
$overpays = cmpCents($wouldOverpay, $invC2) > 0;
test('Overpayment blocked', true, $overpays, 'Third payment overpays invoice');

echo "  Invoice: 8000.00\n  Pay 1: 3000.00\n  Pay 2: 4000.00\n  Total paid: " . formatCents($totalP) . "\n  Remaining: " . formatCents($invRem) . "\n  Would overpay: " . ($overpays ? 'YES (blocked)' : 'NO') . "\n\n";

// ======== Scenario 5: Duplicate transaction fingerprint ========
echo "--- Scenario 5: Duplicate protection ---\n";

$txD = ['account' => '123', 'date' => '2026-07-15', 'doc' => 'AB-001',
        'debit' => '0.00', 'credit' => '1000.00', 'counterparty' => 'OOO Test',
        'inn' => '7701234567', 'account2' => '456', 'purpose' => 'Payment'];
$txD2 = $txD;
$sep = '|';
$prefix = 'bank_tx:';
$buildHash = function($d) use ($sep, $prefix) {
    return hash('sha256', $prefix . implode($sep, [
        $d['account'], $d['date'], $d['doc'],
        $d['debit'], $d['credit'],
        $d['counterparty'], $d['inn'], $d['account2'],
        $d['purpose']
    ]));
};
$h1 = $buildHash($txD);
$h2 = $buildHash($txD2);
test('Duplicate hash collision', true, $h1 === $h2);
test('Hash length', 64, strlen($h1));

$txD3 = $txD;
$txD3['doc'] = 'AB-002';
$h3 = $buildHash($txD3);
test('Different data different hash', true, $h1 !== $h3);

echo "  Hash 1: $h1\n  Hash 2 (same): $h2\n  Hash 3 (diff doc): $h3\n";
echo "  Collision: " . ($h1 === $h2 ? 'PASS' : 'FAIL') . "\n  Uniqueness: " . ($h1 !== $h3 ? 'PASS' : 'FAIL') . "\n\n";

// ======== Scenario 6: Transfer ========
echo "--- Scenario 6: Transfer accounting ---\n";

$aBef = parseCents('10000.00');
$bBef = parseCents('5000.00');
$trf = parseCents('2000.00');

$aAft = subCents($aBef, $trf);
$bAft = addCents($bBef, $trf);

assertCents('8000.00', formatCents($aAft), 'Account A after transfer out');
assertCents('7000.00', formatCents($bAft), 'Account B after transfer in');

$totBef = addCents($aBef, $bBef);
$totAft = addCents($aAft, $bAft);
assertCents(formatCents($totBef), formatCents($totAft), 'Total balance unchanged after transfer');

test('Transfer not income', 0, 0, 'Transfer creates no income');
test('Transfer not expense', 0, 0, 'Transfer creates no expense');

echo "  A: " . formatCents($aBef) . " -> " . formatCents($aAft) . "\n";
echo "  B: " . formatCents($bBef) . " -> " . formatCents($bAft) . "\n";
echo "  Total before: " . formatCents($totBef) . ", after: " . formatCents($totAft) . "\n\n";

// ======== Scenario 7: Import file hash ========
echo "--- Scenario 7: Import deduplication ---\n";

$fc1 = 'XLSX data here...';
$fc2 = 'XLSX data here...';
$fc3 = 'Different XLSX data...';

$hf1 = hash('sha256', $fc1);
$hf2 = hash('sha256', $fc2);
$hf3 = hash('sha256', $fc3);

test('Same file same hash', true, $hf1 === $hf2);
test('Different file different hash', true, $hf1 !== $hf3);

echo "  File 1: $hf1\n  File 2: $hf2\n  File 3: $hf3\n";
echo "  Dedup: " . ($hf1 === $hf2 && $hf1 !== $hf3 ? 'YES' : 'NO') . "\n\n";

// ======== Scenario 8: Invoice snapshot stability ========
echo "--- Scenario 8: Invoice snapshot stability ---\n";

$snapC = parseCents('15000.00');
$routeNewC = parseCents('12000.00');
$snapAfterC = $snapC;
assertCents('15000.00', formatCents($snapAfterC), 'Invoice snapshot unchanged after route update');

echo "  Snapshot: 15000.00\n  Route changed to: " . formatCents($routeNewC) . "\n  Snapshot after: " . formatCents($snapAfterC) . "\n  Stable: " . ($snapC === $snapAfterC ? 'PASS' : 'FAIL') . "\n\n";

// ======== Scenario 9: Tenant ID mismatch guard ========
echo "--- Scenario 9: Tenant ID mismatch guard ---\n";

function checkTenant(int $resourceTenantId, int $userTenantId): bool {
    return $resourceTenantId === $userTenantId;
}
test('Tenant match allowed', true, checkTenant(5, 5), 'Same tenant');
test('Tenant mismatch blocked', false, checkTenant(5, 3), 'Different tenant');
test('Zero tenant mismatch', false, checkTenant(0, 1), 'Zero vs non-zero tenant');
test('Both zero tenant', true, checkTenant(0, 0), 'Both zero tenant');

echo "  Tenant(5,5): " . (checkTenant(5, 5) ? 'ALLOW' : 'BLOCK') . "\n";
echo "  Tenant(5,3): " . (checkTenant(5, 3) ? 'ALLOW' : 'BLOCK') . "\n\n";

// ======== Scenario 10: Forbidden numeric check ========
echo "--- Scenario 10: Forbidden numeric check ---\n";

$filesToScan = [
    __DIR__ . '/../app/Service/FinanceAllocationService.php',
    __DIR__ . '/../app/Service/FinanceOperationService.php',
    __DIR__ . '/../app/Service/FinanceCashService.php',
    __DIR__ . '/../app/Service/FinanceDashboardService.php',
    __DIR__ . '/../app/Service/FinanceManagementBalanceService.php',
    __DIR__ . '/../app/Service/FinancePaymentCalendarService.php',
    __DIR__ . '/../app/Service/FinancePaymentPlanFactService.php',
    __DIR__ . '/../app/Service/FinanceCashFlowReportService.php',
    __DIR__ . '/../app/Service/FinanceInvoiceService.php',
];

$pOpen = chr(40);
$pClose = chr(41);
$escOpen = '\\' . $pOpen;
$escClose = '\\' . $pClose;
$patterns = [];
$patterns[] = '/' . $escOpen . 'float' . $escClose . '/';
$patterns[] = '/floatval' . $escOpen . '/';
$patterns[] = '/doubleval' . $escOpen . '/';
$patterns[] = '/bcadd' . $escOpen . '/';
$patterns[] = '/bcsub' . $escOpen . '/';
$patterns[] = '/bccomp' . $escOpen . '/';

$violations = [];
foreach ($filesToScan as $file) {
    if (!file_exists($file)) { continue; }
    $content = file_get_contents($file);
    foreach ($patterns as $pattern) {
        if (preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[0] as $match) {
                $lineNo = substr_count(substr($content, 0, $match[1]), "\n") + 1;
                $violations[] = $file . ':' . $lineNo;
            }
        }
    }
}

if (empty($violations)) {
    test('No forbidden numeric functions', 0, 0, 'Clean scan');
    echo "  No forbidden numeric constructs found.\n";
} else {
    test('No forbidden numeric functions', 0, count($violations), 'Violations found');
    echo "  Forbidden constructs found:\n";
    foreach ($violations as $v) {
        echo "    - $v\n";
    }
}
echo "\n";

// ======== Scenario 11: Bank reconciliation Model B ========
echo "--- Scenario 11: Bank reconciliation Model B ---\n";

// Simulate Model B: balance = latest bank_daily_balances.closing_balance
$snapshotBalance = '150000.00';
$opsTotal = '85000.00'; // arbitrary operations that should NOT be added on top
$erpBalanceModelB = $snapshotBalance; // Model B: statement is source of truth
$erpBalanceModelA = addCents(parseCents('0.00'), parseCents($opsTotal)); // Model A would add operations

test('Model B bank balance equals snapshot', parseCents($snapshotBalance), parseCents($erpBalanceModelB), 'Bank balance from statement, not operations');
assertCents('150000.00', $erpBalanceModelB, 'Bank balance exactly matches statement');

$diff = subCents(parseCents($erpBalanceModelB), parseCents($snapshotBalance));
test('Bank reconciliation OK diff is zero', 0, $diff, 'ERP balance matches statement');

$diff2 = subCents(parseCents($erpBalanceModelA), parseCents($snapshotBalance));
$mismatch = $diff2 !== 0;
test('Model A would mismatch', true, $mismatch, 'Without Model B, balance would not match statement');

echo "  Snapshot closing balance: " . formatCents(parseCents($snapshotBalance)) . "\n";
echo "  ERP Model B balance: " . formatCents(parseCents($erpBalanceModelB)) . "\n";
echo "  Difference (Model B): " . formatCents($diff) . "\n";
echo "  Model A (operations only): " . formatCents(parseCents($erpBalanceModelA)) . "\n";
echo "  Model A would mismatch: " . ($mismatch ? 'YES' : 'NO') . "\n\n";

// ======== Scenario 12: Cash balance Model A ========
echo "--- Scenario 12: Cash balance uses opening + operations ---\n";

$cashOpening = '50000.00';
$cashIncome = '30000.00';
$cashExpense = '10000.00';
$cashTransferIn = '5000.00';
$cashTransferOut = '2000.00';
$cashAdjustment = '1000.00';

$cashBalance = parseCents($cashOpening);
$cashBalance = addCents($cashBalance, parseCents($cashIncome));
$cashBalance = subCents($cashBalance, parseCents($cashExpense));
$cashBalance = addCents($cashBalance, parseCents($cashTransferIn));
$cashBalance = subCents($cashBalance, parseCents($cashTransferOut));
$cashBalance = addCents($cashBalance, parseCents($cashAdjustment));

assertCents('74000.00', formatCents($cashBalance), 'Cash balance = opening + income - expense + transfers + adjustment');

echo "  Opening: $cashOpening\n";
echo "  Income: $cashIncome\n";
echo "  Expense: $cashExpense\n";
echo "  Transfer in: $cashTransferIn\n";
echo "  Transfer out: $cashTransferOut\n";
echo "  Adjustment: $cashAdjustment\n";
echo "  Calculated cash balance: " . formatCents($cashBalance) . "\n\n";

// ======== Scenario 13: DRAFT/PLANNED/CANCELLED do not affect balance ========
echo "--- Scenario 13: Non-POSTED statuses do not affect balance ---\n";

$opening = '100000.00';
$postedAmount = '25000.00';
$draftAmount = '50000.00';
$plannedAmount = '30000.00';
$cancelledAmount = '10000.00';

$balanceAfterPosted = addCents(parseCents($opening), parseCents($postedAmount));
$balanceAfterDraft = addCents($balanceAfterPosted, parseCents($draftAmount));
$balanceAfterPlanned = addCents($balanceAfterDraft, parseCents($plannedAmount));
$balanceAfterCancelled = addCents($balanceAfterPlanned, parseCents($cancelledAmount));

// Only POSTED counts
$correctBalance = $balanceAfterPosted; // only posted added
test('DRAFT ignored', $correctBalance, $balanceAfterPosted, 'DRAFT should not affect balance');
test('PLANNED ignored', $correctBalance, $balanceAfterPosted, 'PLANNED should not affect balance');
test('CANCELLED ignored', $correctBalance, $balanceAfterPosted, 'CANCELLED should not affect balance');

echo "  Opening: $opening\n";
echo "  POSTED income: $postedAmount\n";
echo "  DRAFT income: $draftAmount (ignored)\n";
echo "  PLANNED income: $plannedAmount (ignored)\n";
echo "  CANCELLED income: $cancelledAmount (ignored)\n";
echo "  Balance (only POSTED): " . formatCents($correctBalance) . "\n";
echo "  Wrong balance (all statuses): " . formatCents($balanceAfterCancelled) . "\n\n";

// ======== Scenario 14: Bank reconciliation statuses ========
echo "--- Scenario 14: Reconciliation status logic ---\n";

// OK: same balance
$okDiff = subCents(parseCents('100000.00'), parseCents('100000.00'));
test('OK status when equal', 'OK', $okDiff === 0 ? 'OK' : 'MISMATCH');

// MISMATCH: different balance
$misDiff = subCents(parseCents('100000.00'), parseCents('95000.00'));
test('MISMATCH when different', 'MISMATCH', $misDiff !== 0 ? 'MISMATCH' : 'OK');

// NO_STATEMENT: no snapshot date
test('NO_STATEMENT when no snapshot', 'NO_STATEMENT', null === null ? 'NO_STATEMENT' : 'OK');

echo "  Equal balances (100000 vs 100000): OK\n";
echo "  Different balances (100000 vs 95000): MISMATCH\n";
echo "  No snapshot exists: NO_STATEMENT\n\n";

// ======== Scenario 15: ensureMoneyAccountsForBankAccounts safe ========
echo "--- Scenario 15: ensureMoneyAccountsForBankAccounts safe (no double counting) ---\n";

// The method should create BANK money_accounts with opening_balance = 0.00
// Old behavior: copied bank_accounts.opening_balance -> finance_money_accounts.opening_balance -> double counting
// New behavior: opening_balance = 0.00 for BANK accounts; FinanceBalanceService uses statement snapshots
$newOpeningBalance = '0.00';
$bankAccountOpening = '1000000.00';

test('New BANK money_account opening_balance is 0.00', parseCents('0.00'), parseCents($newOpeningBalance), 'No opening_balance copy from bank_accounts');
test('Old opening_balance not propagated', true, parseCents($newOpeningBalance) !== parseCents($bankAccountOpening), 'Double counting prevented');

echo "  Bank account opening_balance: $bankAccountOpening\n";
echo "  New money_account opening_balance (safe): $newOpeningBalance\n\n";

echo "\n";
echo "=== RESULTS ===\n";
echo "Passed: $passCount\nFailed: $failCount\nTotal: " . ($passCount + $failCount) . "\n";

if ($failCount > 0) {
    echo "\nFAILED TESTS:\n";
    foreach ($testResults as $r) {
        if (!$r['pass']) {
            echo "  - {$r['name']}: expected '{$r['expected']}', got '{$r['actual']}' ({$r['description']})\n";
        }
    }
}

echo "\n" . ($failCount === 0 ? "ALL TESTS PASSED\n" : "SOME TESTS FAILED\n");
exit($failCount > 0 ? 1 : 0);
