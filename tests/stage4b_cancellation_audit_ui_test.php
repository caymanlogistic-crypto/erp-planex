<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require_once __DIR__ . '/../app/Service/FinanceOperationService.php';
use App\Service\FinanceOperationService;

$passCount = 0;
$failCount = 0;
$testResults = [];

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

function sourceContains(string $file, string $pattern): bool {
    if (!file_exists($file)) return false;
    $content = file_get_contents($file);
    return preg_match($pattern, $content) === 1;
}

echo "=== ERP PLANEX Stage 4B: Cancellation & Audit UI Tests ===\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n\n";

// ======== 1. Owner-only security ========
echo "--- 1. Owner-only security ---\n";

$cancelEndpoints = [
    __DIR__ . '/../app/Http/Controllers/Company/FinanceOperationActions/cancel.php',
    __DIR__ . '/../app/Http/Controllers/Company/FinanceOperationActions/allocation_cancel.php',
    __DIR__ . '/../app/Http/Controllers/Company/InvoiceActions/modal_delete.php',
];

$historyEndpoints = [
    __DIR__ . '/../app/Http/Controllers/Company/FinanceOperationActions/history.php',
    __DIR__ . '/../app/Http/Controllers/Company/InvoiceActions/history.php',
];

$modalViewEndpoints = [
    __DIR__ . '/../app/Http/Controllers/Company/FinanceOperationActions/modal_view.php',
    __DIR__ . '/../app/Http/Controllers/Company/InvoiceActions/modal_view.php',
];

$indexActions = [
    __DIR__ . '/../app/Http/Controllers/Company/FinanceOperationActions/index.php',
    __DIR__ . '/../app/Http/Controllers/Company/InvoiceActions/index.php',
];

$allEndpoints = array_merge($cancelEndpoints, $historyEndpoints, $modalViewEndpoints, $indexActions);

foreach ($allEndpoints as $ep) {
    $file = basename(dirname(dirname($ep))) . '/' . basename(dirname($ep)) . '/' . basename($ep);
    $hasOwnerGuard = sourceContains($ep, '/requireRole\\(\\[?[\'"]company_owner[\'"]\\]?\\)/');
    test("Owner guard present: $file", true, $hasOwnerGuard, "Endpoint $file must require company_owner role");
    echo "  $file: " . ($hasOwnerGuard ? 'OWNER-ONLY' : 'MISSING GUARD') . "\n";
}

echo "\n";

// ======== 2. Cancel with reason required ========
echo "--- 2. Cancel with reason required ---\n";

$operationCancel = file_get_contents(__DIR__ . '/../app/Http/Controllers/Company/FinanceOperationActions/cancel.php');
test('Operation cancel rejects empty reason', true, str_contains($operationCancel, 'trim($reason)') || str_contains($operationCancel, 'trimmedReason'), 'Cancel reason must be trimmed and checked');
test('Operation cancel has RuntimeException for empty reason', true, str_contains($operationCancel, 'RuntimeException'), 'Empty reason must throw');

$invoiceDelete = file_get_contents(__DIR__ . '/../app/Http/Controllers/Company/InvoiceActions/modal_delete.php');
test('Invoice cancel reads reason from POST', true, str_contains($invoiceDelete, '$_POST[\'reason\']'), 'Invoice cancel must read reason from POST');
test('Invoice cancel trims reason', true, str_contains($invoiceDelete, 'trim('), 'Reason must be trimmed');
test('Invoice cancel has RuntimeException for empty reason', true, str_contains($invoiceDelete, 'RuntimeException'), 'Empty reason must throw');

echo "  Operation cancel.php has trim check: " . (str_contains($operationCancel, 'trim(') ? 'YES' : 'NO') . "\n";
echo "  Invoice modal_delete.php has POST reason: " . (str_contains($invoiceDelete, '$_POST[\'reason\']') ? 'YES' : 'NO') . "\n\n";

// ======== 3. Repeat cancel blocked ========
echo "--- 3. Repeat cancel blocked ---\n";

$cancelService = file_get_contents(__DIR__ . '/../app/Service/FinanceOperationService.php');
$hasRepeatCheck = str_contains($cancelService, 'CANCELLED') && preg_match('/status.*CANCELLED|cancelled_at/', $cancelService) === 1;
test('OperationService has repeat cancel check', true, $hasRepeatCheck, 'Service must prevent cancel of already cancelled operation');

$allocationService = file_get_contents(__DIR__ . '/../app/Service/FinanceAllocationService.php');
$hasAllocRepeatCheck = str_contains($allocationService, 'cancelled_at') || preg_match('/cancel.*already|cancelled.*throw/', $allocationService) === 1;
test('AllocationService has repeat cancel check', true, $hasAllocRepeatCheck, 'Service must prevent cancel of already cancelled allocation');

$invoiceService = file_get_contents(__DIR__ . '/../app/Service/FinanceInvoiceService.php');
$hasInvoiceRepeatCheck = preg_match('/cancelled.*throw|cancelled_at.*null|status.*cancelled/', $invoiceService) === 1;
test('InvoiceService has repeat cancel check', true, $hasInvoiceRepeatCheck, 'Service must prevent cancel of already cancelled invoice');

echo "  OperationService repeat check: " . ($hasRepeatCheck ? 'YES' : 'NO') . "\n";
echo "  AllocationService repeat check: " . ($hasAllocRepeatCheck ? 'YES' : 'NO') . "\n";
echo "  InvoiceService repeat check: " . ($hasInvoiceRepeatCheck ? 'YES' : 'NO') . "\n\n";

// ======== 4. History endpoints ========
echo "--- 4. History endpoints exist ---\n";

test('Operation history endpoint exists', true, file_exists(__DIR__ . '/../app/Http/Controllers/Company/FinanceOperationActions/history.php'));
test('Invoice history endpoint exists', true, file_exists(__DIR__ . '/../app/Http/Controllers/Company/InvoiceActions/history.php'));
test('History view partial exists', true, file_exists(__DIR__ . '/../app/View/partials/company_finance_history_view.php'));

$operationHistory = file_get_contents(__DIR__ . '/../app/Http/Controllers/Company/FinanceOperationActions/history.php');
test('Operation history fetches audit log', true, str_contains($operationHistory, 'fetchAuditLog'), 'History endpoint must fetch from audit log');

$invoiceHistory = file_get_contents(__DIR__ . '/../app/Http/Controllers/Company/InvoiceActions/history.php');
test('Invoice history fetches audit log', true, str_contains($invoiceHistory, 'fetchAuditLog'), 'History endpoint must fetch from audit log');

echo "  Operation history: " . (file_exists(__DIR__ . '/../app/Http/Controllers/Company/FinanceOperationActions/history.php') ? 'EXISTS' : 'MISSING') . "\n";
echo "  Invoice history: " . (file_exists(__DIR__ . '/../app/Http/Controllers/Company/InvoiceActions/history.php') ? 'EXISTS' : 'MISSING') . "\n";
echo "  History view: " . (file_exists(__DIR__ . '/../app/View/partials/company_finance_history_view.php') ? 'EXISTS' : 'MISSING') . "\n\n";

// ======== 5. Cancel modal partial ========
echo "--- 5. Cancel modal partial ---\n";

$cancelModalExists = file_exists(__DIR__ . '/../app/View/partials/company_finance_cancel_modal.php');
test('Cancel modal partial exists', true, $cancelModalExists, 'company_finance_cancel_modal.php must exist');

if ($cancelModalExists) {
    $cancelModalContent = file_get_contents(__DIR__ . '/../app/View/partials/company_finance_cancel_modal.php');
    test('Cancel modal has reason textarea', true, str_contains($cancelModalContent, 'textarea'), 'Cancel modal must have reason input');
    test('Cancel modal has CSRF', true, str_contains($cancelModalContent, '_csrf_token') || str_contains($cancelModalContent, 'csrfField'), 'Cancel modal must have CSRF protection');
    test('Cancel modal has required attribute on reason', true, str_contains($cancelModalContent, 'required'), 'Cancel reason must be required in HTML');
    echo "  Cancel modal partial: " . ($cancelModalExists ? 'EXISTS' : 'MISSING') . "\n";
    echo "  Has reason textarea: " . (str_contains($cancelModalContent, 'textarea') ? 'YES' : 'NO') . "\n";
    echo "  Has CSRF: " . ((str_contains($cancelModalContent, '_csrf_token') || str_contains($cancelModalContent, 'csrfField')) ? 'YES' : 'NO') . "\n";
    echo "  Required attribute: " . (str_contains($cancelModalContent, 'required') ? 'YES' : 'NO') . "\n";
} else {
    echo "  Cancel modal partial: MISSING\n";
}
echo "\n";

// ======== 6. Cancel button in operation modal view ========
echo "--- 6. Cancel button in operation modal view ---\n";

$opModalView = file_get_contents(__DIR__ . '/../app/View/partials/company_finance_operation_modal_view.php');
test('Operation modal has cancel button', true, str_contains($opModalView, 'data-operation-cancel-btn'), 'Operation modal view must have cancel button');
test('Operation modal has history button', true, str_contains($opModalView, 'data-operation-history-btn'), 'Operation modal view must have history button');
test('Operation modal loads history on click', true, str_contains($opModalView, '/company/finance/operations/') && str_contains($opModalView, '/history'), 'Operation modal must load history via fetch');

echo "  Cancel button: " . (str_contains($opModalView, 'data-operation-cancel-btn') ? 'PRESENT' : 'MISSING') . "\n";
echo "  History button: " . (str_contains($opModalView, 'data-operation-history-btn') ? 'PRESENT' : 'MISSING') . "\n";
echo "  History fetch: " . ((str_contains($opModalView, '/company/finance/operations/') && str_contains($opModalView, '/history')) ? 'YES' : 'NO') . "\n\n";

// ======== 7. Cancel and history in invoice modal view ========
echo "--- 7. Cancel and history in invoice modal view ---\n";

$invModalView = file_get_contents(__DIR__ . '/../app/View/partials/company_invoice_modal_view.php');
test('Invoice modal has cancel button', true, str_contains($invModalView, 'data-invoice-cancel-btn'), 'Invoice modal view must have cancel button');
test('Invoice modal has history button', true, str_contains($invModalView, 'data-invoice-history-btn'), 'Invoice modal view must have history button');
test('Invoice modal loads history on click', true, str_contains($invModalView, '/company/finance/invoices/') && str_contains($invModalView, '/history'), 'Invoice modal must load history via fetch');

echo "  Cancel button: " . (str_contains($invModalView, 'data-invoice-cancel-btn') ? 'PRESENT' : 'MISSING') . "\n";
echo "  History button: " . (str_contains($invModalView, 'data-invoice-history-btn') ? 'PRESENT' : 'MISSING') . "\n";
echo "  History fetch: " . ((str_contains($invModalView, '/company/finance/invoices/') && str_contains($invModalView, '/history')) ? 'YES' : 'NO') . "\n\n";

// ======== 8. Cancel modal overlays in page views ========
echo "--- 8. Cancel modal overlays in page views ---\n";

$opsPage = file_get_contents(__DIR__ . '/../app/View/pages/company_finance_operations.php');
test('Operations page has cancel modal', true, str_contains($opsPage, 'operation-cancel-modal'), 'Operations page must include cancel modal overlay');
test('Operations page includes cancel modal partial', true, str_contains($opsPage, 'company_finance_cancel_modal'), 'Must require cancel modal partial');

$invPage = file_get_contents(__DIR__ . '/../app/View/pages/company_finance_invoices.php');
test('Invoices page has cancel modal', true, str_contains($invPage, 'invoice-cancel-modal'), 'Invoices page must include cancel modal overlay');
test('Invoices page includes cancel modal partial', true, str_contains($invPage, 'company_finance_cancel_modal'), 'Must require cancel modal partial');

echo "  Operations page cancel modal: " . (str_contains($opsPage, 'operation-cancel-modal') ? 'PRESENT' : 'MISSING') . "\n";
echo "  Invoices page cancel modal: " . (str_contains($invPage, 'invoice-cancel-modal') ? 'PRESENT' : 'MISSING') . "\n\n";

// ======== 9. AJAX support in cancel handlers ========
echo "--- 9. AJAX support in cancel handlers ---\n";

test('Operation cancel supports AJAX', true, str_contains($operationCancel, 'XMLHttpRequest'), 'Operation cancel must support AJAX');
test('Operation cancel has AJAX response', true, str_contains($operationCancel, '$isAjax'), 'Operation cancel must check AJAX flag');

test('Invoice cancel supports AJAX', true, str_contains($invoiceDelete, 'XMLHttpRequest'), 'Invoice cancel must support AJAX');
test('Invoice cancel has AJAX response', true, str_contains($invoiceDelete, '$isAjax'), 'Invoice cancel must check AJAX flag');

echo "  Operation cancel AJAX: " . (str_contains($operationCancel, 'XMLHttpRequest') ? 'SUPPORTED' : 'MISSING') . "\n";
echo "  Invoice cancel AJAX: " . (str_contains($invoiceDelete, 'XMLHttpRequest') ? 'SUPPORTED' : 'MISSING') . "\n\n";

// ======== 10. Routes have cancel/history endpoints ========
echo "--- 10. Routes for cancel and history ---\n";

$opsRoutes = file_get_contents(__DIR__ . '/../app/Http/Routes/company_finance_operations.php');
test('Operations route has cancel', true, str_contains($opsRoutes, '/cancel'), 'Operation cancel route must exist');
test('Operations route has history', true, str_contains($opsRoutes, '/history'), 'Operation history route must exist');

$invRoutes = file_get_contents(__DIR__ . '/../app/Http/Routes/company_finance_invoices.php');
test('Invoices route has history', true, str_contains($invRoutes, '/history'), 'Invoice history route must exist');

echo "  Operations cancel route: " . (str_contains($opsRoutes, '/cancel') ? 'EXISTS' : 'MISSING') . "\n";
echo "  Operations history route: " . (str_contains($opsRoutes, '/history') ? 'EXISTS' : 'MISSING') . "\n";
echo "  Invoices history route: " . (str_contains($invRoutes, '/history') ? 'EXISTS' : 'MISSING') . "\n\n";

// ======== 11. Audit service only has raw INSERT (static guard) ========
echo "--- 11. No raw INSERT INTO finance_audit_log in service files ---\n";

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

// ======== 12. Controller action files contain no raw audit inserts ========
echo "--- 12. Controller action files contain no raw audit inserts ---\n";

$controllerDirs = [
    __DIR__ . '/../app/Http/Controllers/Company/FinanceOperationActions',
    __DIR__ . '/../app/Http/Controllers/Company/InvoiceActions',
];
$ctrlViolations = [];
foreach ($controllerDirs as $dir) {
    if (!is_dir($dir)) continue;
    $files = new \FilesystemIterator($dir);
    foreach ($files as $file) {
        if ($file->getExtension() !== 'php') continue;
        $content = file_get_contents((string) $file);
        if (preg_match('/INSERT\s+INTO\s+finance_audit_log/i', $content)) {
            $ctrlViolations[] = $file->getFilename();
        }
    }
}
$noCtrlViolations = $ctrlViolations === [];
test('No raw audit inserts in controller actions', true, $noCtrlViolations, 'Violations: ' . ($ctrlViolations !== [] ? implode(', ', $ctrlViolations) : 'none'));
echo "  " . ($ctrlViolations !== [] ? 'VIOLATIONS: ' . implode(', ', $ctrlViolations) : 'ALL CLEAN') . "\n\n";

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
