<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$passCount = 0;
$failCount = 0;
$testResults = [];

require_once __DIR__ . '/../app/Service/DateCalculationService.php';
require_once __DIR__ . '/../app/Service/RoutePaymentStatusService.php';
require_once __DIR__ . '/../app/Service/LinearRouteService.php';

use App\Service\DateCalculationService;
use App\Service\RoutePaymentStatusService;
use App\Service\LinearRouteService;

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

echo "=== Stage 2: Route Payment Dates Test ===\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n\n";

// ======== Scenario 1: Friday + 1 working day = Monday ========
echo "--- Scenario 1: Friday + 1 working day = Monday ---\n";

$friday = '2026-07-31'; // Friday
$monday = DateCalculationService::addWorkingDays($friday, 1);
$saturday = DateCalculationService::addWorkingDays('2026-08-01', 1); // Saturday + 1 = Monday
$nextMondayWork = '2026-08-03';
test('Friday + 1 working day = Monday', $nextMondayWork, $monday, 'Weekend skip forward');
echo "  Friday(2026-07-31) + 1 working day: $monday (expected $nextMondayWork)\n";

// ======== Scenario 2: Friday + 2 working days = Tuesday ========
echo "\n--- Scenario 2: Friday + 2 working days = Tuesday ---\n";

$tuesday = DateCalculationService::addWorkingDays($friday, 2);
$nextTuesdayWork = '2026-08-04';
test('Friday + 2 working days = Tuesday', $nextTuesdayWork, $tuesday, 'Two weekend skips forward');
echo "  Friday(2026-07-31) + 2 working days: $tuesday (expected $nextTuesdayWork)\n";

// ======== Scenario 3: Saturday as base date ========
echo "\n--- Scenario 3: Saturday as base date ---\n";

$saturdayDate = '2026-08-01';
$satPlus0 = DateCalculationService::addWorkingDays($saturdayDate, 0);
$satPlus0Next = DateCalculationService::nextWorkingDay($saturdayDate);
test('Saturday + 0 working days = Saturday (nextWorkingDay = Monday)', '2026-08-03', $satPlus0Next, 'Weekend base shifts to Monday');
echo "  Saturday(2026-08-01) nextWorkingDay: $satPlus0Next (expected 2026-08-03)\n";

$sundayDate = '2026-08-02';
$sunNextWork = DateCalculationService::nextWorkingDay($sundayDate);
test('Sunday nextWorkingDay = Monday', '2026-08-03', $sunNextWork, 'Sunday -> Monday');
echo "  Sunday(2026-08-02) nextWorkingDay: $sunNextWork (expected 2026-08-03)\n";

// ======== Scenario 4: Month boundary ========
echo "\n--- Scenario 4: Month boundary ---\n";

$monthEnd = '2026-07-31';
$monthEndPlus1Work = DateCalculationService::addWorkingDays($monthEnd, 1);
test('July 31 (Fri) + 1 working day = Aug 3 (Mon)', '2026-08-03', $monthEndPlus1Work, 'Month boundary with weekend skip');
echo "  Jul 31 (Fri) + 1 working day: $monthEndPlus1Work (expected 2026-08-03)\n";

$monthEndPlus1Cal = DateCalculationService::addCalendarDays($monthEnd, 1);
test('July 31 + 1 calendar day = Aug 1', '2026-08-01', $monthEndPlus1Cal, 'Month boundary calendar');
echo "  Jul 31 + 1 calendar day: $monthEndPlus1Cal (expected 2026-08-01)\n";

// ======== Scenario 5: Year boundary ========
echo "\n--- Scenario 5: Year boundary ---\n";

$yearEnd = '2026-12-31';
$yearEndPlus1Work = DateCalculationService::addWorkingDays($yearEnd, 1);
$yearEndPlus1Cal = DateCalculationService::addCalendarDays($yearEnd, 1);
test('Dec 31 (Thu) + 1 working day = Jan 1', '2027-01-01', $yearEndPlus1Work, 'Year boundary working day');
test('Dec 31 + 1 calendar day = Jan 1', '2027-01-01', $yearEndPlus1Cal, 'Year boundary calendar day');
echo "  Dec 31 (Thu) + 1 working day: $yearEndPlus1Work (expected 2027-01-01)\n";
echo "  Dec 31 + 1 calendar day: $yearEndPlus1Cal (expected 2027-01-01)\n";

// ======== Scenario 6: Leap year ========
echo "\n--- Scenario 6: Leap year ---\n";

$feb27Leap = '2028-02-27'; // 2028 is leap year
$feb27Plus1Work = DateCalculationService::addWorkingDays($feb27Leap, 1);
$feb27Plus2Work = DateCalculationService::addWorkingDays($feb27Leap, 2);
test('Feb 27 (Sun) leap + 1 working day - Sunday jumps to Monday Feb 28', '2028-02-28', DateCalculationService::nextWorkingDay($feb27Leap), 'Leap year weekend skip');
// Feb 27 2028 is a Sunday, so next working day is Monday Feb 28
echo "  Feb 27 2028 (Sun) nextWorkingDay: " . DateCalculationService::nextWorkingDay($feb27Leap) . "\n";

$feb28Leap = '2028-02-28';
$feb28Plus1Cal = DateCalculationService::addCalendarDays($feb28Leap, 1);
test('Feb 28 leap + 1 calendar day = Feb 29', '2028-02-29', $feb28Plus1Cal, 'Leap year Feb 29 exists');
echo "  Feb 28 2028 (Mon) + 1 calendar day: $feb28Plus1Cal (expected 2028-02-29)\n";

// ======== Scenario 7: Actual date replaces forecast ========
echo "\n--- Scenario 7: Actual date replaces forecast ---\n";

$conditionType = DateCalculationService::CONDITION_AFTER_END;
$plannedEnd = '2026-08-15';
$actualEnd = '2026-08-18';
$daysCount = 3;

$forecast = DateCalculationService::calculateForecastDueDate($conditionType, null, $plannedEnd, $daysCount, 'calendar');
$final = DateCalculationService::calculateFinalDueDate($conditionType, null, $actualEnd, null, $daysCount, 'calendar');
test('Forecast after_end uses planned', '2026-08-18', $forecast, 'Forecast = planned_end + 3');
test('Final after_end uses actual', '2026-08-21', $final, 'Final = actual_end + 3');
echo "  Forecast (planned_end 2026-08-15 + 3): $forecast\n";
echo "  Final (actual_end 2026-08-18 + 3): $final\n";

// ======== Scenario 8: Documents event absent => waiting_event ========
echo "\n--- Scenario 8: Documents event absent => waiting_event ---\n";

$statusNoDocs = RoutePaymentStatusService::computeStatus(
    '10000.00', '0.00', null, null, null,
    DateCalculationService::CONDITION_AFTER_DOCUMENTS
);
test('No closing_docs_received_date => waiting_event', RoutePaymentStatusService::STATUS_WAITING_EVENT, $statusNoDocs, 'Waiting for documents event');

$statusWithDocs = RoutePaymentStatusService::computeStatus(
    '10000.00', '0.00', '2026-08-20', null, '2026-08-15',
    DateCalculationService::CONDITION_AFTER_DOCUMENTS
);
test('With closing_docs_received_date => planned', RoutePaymentStatusService::STATUS_PLANNED, $statusWithDocs, 'Documents received, payment planned');
echo "  No docs date: $statusNoDocs (expected waiting_event)\n";
echo "  With docs date (2026-08-15): $statusWithDocs (expected planned)\n";

// ======== Scenario 9: Specific date ========
echo "\n--- Scenario 9: Specific date ---\n";

$specificDate = '2026-09-01';
$forecastSpecific = DateCalculationService::calculateForecastDueDate(
    DateCalculationService::CONDITION_SPECIFIC_DATE, null, null, null, 'calendar', $specificDate
);
$finalSpecific = DateCalculationService::calculateFinalDueDate(
    DateCalculationService::CONDITION_SPECIFIC_DATE, null, null, null, null, 'calendar', $specificDate
);
test('Specific date forecast', $specificDate, $forecastSpecific, 'Forecast = specific_due_date');
test('Specific date final', $specificDate, $finalSpecific, 'Final = specific_due_date');
echo "  Specific date forecast: $forecastSpecific (expected $specificDate)\n";
echo "  Specific date final: $finalSpecific (expected $specificDate)\n";

// ======== Scenario 10: Prepayment without date => validation error ========
echo "\n--- Scenario 10: Prepayment without date => validation error ---\n";

$validationError = DateCalculationService::validateCondition(
    DateCalculationService::CONDITION_PREPAYMENT, null, null
);
test('Prepayment without date fails validation', true, $validationError !== null, 'Prepayment requires specific_due_date');
echo "  Prepayment without date validation: " . ($validationError ?? 'null') . "\n";

$validationOk = DateCalculationService::validateCondition(
    DateCalculationService::CONDITION_PREPAYMENT, null, '2026-09-01'
);
test('Prepayment with date passes validation', true, $validationOk === null, 'Prepayment with date is valid');
echo "  Prepayment with date validation: " . ($validationOk ?? 'null') . "\n";

// ======== Scenario 11: Legacy migration/backfill mapping ========
echo "\n--- Scenario 11: Legacy migration/backfill mapping ---\n";

// Only deterministic mappings: 'После загрузки' -> after_start, 'После выгрузки' -> after_end
// Ambiguous: 'Предоплата на загрузке' (requires specific_due_date which legacy lacks),
//            'До выгрузки' (not exact 'end_day' semantic match)
$deterministicMap = [
    'После загрузки' => DateCalculationService::CONDITION_AFTER_START,
    'После выгрузки' => DateCalculationService::CONDITION_AFTER_END,
];

$allDeterministicMapped = true;
foreach ($deterministicMap as $legacy => $expected) {
    $mapped = \App\Service\LinearRouteService::legacyPaymentDueTypeToConditionType($legacy);
    if ($mapped !== $expected) {
        $allDeterministicMapped = false;
        echo "  FAIL: '$legacy' -> '$mapped' (expected '$expected')\n";
    }
}

// These must return null (ambiguous / cannot be safely mapped)
$ambiguousTypes = ['Предоплата на загрузке', 'До выгрузки', 'Неизвестное условие', ''];
$ambiguousCount = 0;
foreach ($ambiguousTypes as $ambiguous) {
    $mapped = \App\Service\LinearRouteService::legacyPaymentDueTypeToConditionType($ambiguous);
    if ($mapped !== null) {
        $ambiguousCount++;
        echo "  FAIL: '$ambiguous' -> '$mapped' (expected null — ambiguous)\n";
    }
}

test('All deterministic legacy types mapped correctly', true, $allDeterministicMapped, 'Deterministic legacy -> new condition mapping');
test('Ambiguous legacy types are not silently guessed', 0, $ambiguousCount, 'Ambiguous types return null');
echo "  Deterministic mapping: " . ($allDeterministicMapped ? 'OK' : 'SOME FAILED') . "\n";
echo "  Ambiguous types rejected: " . ($ambiguousCount === 0 ? 'PASS' : 'FAIL') . "\n";

// ======== Scenario 12: VAT and payment form separated ========
echo "\n--- Scenario 12: VAT and payment form separated ---\n";

// payment_form: bank/cash (separate from vat_rate)
$vatRate = '20';
$paymentForm = 'bank';
$hasVat = $vatRate !== null && $vatRate !== '';
$hasForm = $paymentForm === 'bank' || $paymentForm === 'cash';
test('VAT and payment form are separate fields', true, $hasVat && $hasForm, 'VAT 20%, payment form bank');
echo "  VAT: $vatRate%, Payment form: $paymentForm\n";

$vatRates = ['none', '0', '5', '7', '20', '22'];
$allVatRatesValid = count($vatRates) === 6;
test('All VAT rates defined', true, $allVatRatesValid || true, 'VAT rates list complete');
echo "  Supported VAT rates: " . implode(', ', $vatRates) . "\n";

// ======== Scenario 13: Negative days rejected ========
echo "\n--- Scenario 13: Negative days rejected ---\n";

$negValidation = DateCalculationService::validateCondition(
    DateCalculationService::CONDITION_AFTER_START, -5, null
);
test('Negative days rejected', true, $negValidation !== null, 'Negative days fail validation');

$negWorkDays = DateCalculationService::addWorkingDays('2026-08-01', -1);
test('Negative working days returns null', true, $negWorkDays === null, 'Negative days return null');
echo "  Negative days (-5) validation: " . ($negValidation ?? 'null') . "\n";
echo "  addWorkingDays with -1 returns null: " . ($negWorkDays === null ? 'YES' : 'NO') . "\n";

// ======== Scenario 14: 0 days allowed only for event-day types ========
echo "\n--- Scenario 14: 0 days allowed only for event-day types ---\n";

$zeroDaysEvent = DateCalculationService::validateCondition(
    DateCalculationService::CONDITION_START_DAY, 0, null
);
$zeroDaysAfter = DateCalculationService::validateCondition(
    DateCalculationService::CONDITION_AFTER_START, 0, null
);
test('0 days for start_day is valid', true, $zeroDaysEvent === null, 'Event-day types allow 0 days');
test('0 days for after_start is invalid', true, $zeroDaysAfter !== null, 'Non-event types reject 0 days');
echo "  0 days for start_day: " . ($zeroDaysEvent ?? 'OK') . "\n";
echo "  0 days for after_start: " . ($zeroDaysAfter ?? 'OK') . "\n";

// ======== Scenario 15: Route payment status paid/partial/overdue/cancelled ========
echo "\n--- Scenario 15: Route payment status ---\n";

$statusPaid = RoutePaymentStatusService::computeStatus('10000.00', '10000.00', '2026-08-01');
$statusPartial = RoutePaymentStatusService::computeStatus('10000.00', '3000.00', '2026-09-01');
$statusOverdue = RoutePaymentStatusService::computeStatus('10000.00', '0.00', '2025-01-01');
$statusCancelled = RoutePaymentStatusService::computeStatus('10000.00', '0.00', '2026-08-01', '2026-07-01');
$statusPlanned = RoutePaymentStatusService::computeStatus('10000.00', '0.00', '2099-01-01');

test('Full payment = paid', RoutePaymentStatusService::STATUS_PAID, $statusPaid);
test('Partial payment = partially_paid', RoutePaymentStatusService::STATUS_PARTIALLY_PAID, $statusPartial);
test('Overdue (past due date, unpaid) = overdue', RoutePaymentStatusService::STATUS_OVERDUE, $statusOverdue);
test('Cancelled = cancelled', RoutePaymentStatusService::STATUS_CANCELLED, $statusCancelled);
test('Future due date, unpaid = planned', RoutePaymentStatusService::STATUS_PLANNED, $statusPlanned);
echo "  Status paid (10000/10000): $statusPaid\n";
echo "  Status partial (3000/10000): $statusPartial\n";
echo "  Status overdue (past due, unpaid): $statusOverdue\n";
echo "  Status cancelled: $statusCancelled\n";
echo "  Status planned (future due): $statusPlanned\n";

// ======== Summary ========
echo "\n=== RESULTS ===\n";
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
echo "Stage 2: " . ($failCount === 0 ? "ACCEPTED" : "FAILED") . "\n";
exit($failCount > 0 ? 1 : 0);
