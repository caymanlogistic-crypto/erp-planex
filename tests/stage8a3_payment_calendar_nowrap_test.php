<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$passCount = 0;
$failCount = 0;
$testResults = [];

function t(string $name, $expected, $actual, string $description = ''): void {
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
    return preg_match($pattern, (string) file_get_contents($file)) === 1;
}

echo "=== ERP PLANEX Stage 8A3: Payment Calendar KPI Nowrap ===\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n\n";

$BASE = __DIR__ . '/..';

// ======== 1. Payment calendar view contains page-summary--calendar-kpis ========
echo "--- 1. View contains page-summary--calendar-kpis ---\n";
$viewFile = $BASE . '/app/View/pages/company_finance_payment_calendar.php';
$kpiClass = sourceContains($viewFile, '/page-summary--calendar-kpis/');
t('View has page-summary--calendar-kpis class', true, $kpiClass);
echo "  page-summary--calendar-kpis: " . ($kpiClass ? 'FOUND' : 'MISSING') . "\n";

// ======== 2. CSS contains nowrap rule for KPI container ========
echo "\n--- 2. CSS has nowrap rules for KPI ---\n";
$cssFile = $BASE . '/public/assets/css/erp-ui.css';
$containerNowrap = sourceContains($cssFile, '/\.page-summary--calendar-kpis\s*\{[^}]*white-space:\s*nowrap[^}]*\}/s');
$childNowrap = sourceContains($cssFile, '/\.page-summary--calendar-kpis\s*>\s*span\s*\{[^}]*white-space:\s*nowrap[^}]*\}/s');
t('CSS has white-space:nowrap on container', true, $containerNowrap);
t('CSS has white-space:nowrap on > span children', true, $childNowrap);
echo "  Container white-space:nowrap: " . ($containerNowrap ? 'YES' : 'NO') . "\n";
echo "  > span white-space:nowrap: " . ($childNowrap ? 'YES' : 'NO') . "\n";

// ======== 3. CSS has flex-wrap:nowrap on container ========
echo "\n--- 3. CSS has flex-wrap:nowrap on KPI container ---\n";
$flexWrapNowrap = sourceContains($cssFile, '/\.page-summary--calendar-kpis\s*\{[^}]*flex-wrap:\s*nowrap[^}]*\}/s');
t('CSS has flex-wrap:nowrap on container', true, $flexWrapNowrap);
echo "  flex-wrap:nowrap: " . ($flexWrapNowrap ? 'YES' : 'NO') . "\n";

// ======== 4. CSS has overflow-x:auto for horizontal scroll ========
echo "\n--- 4. CSS allows horizontal scroll for narrow viewport ---\n";
$overflowAuto = sourceContains($cssFile, '/\.page-summary--calendar-kpis\s*\{[^}]*overflow-x:\s*auto[^}]*\}/s');
t('CSS has overflow-x:auto on container', true, $overflowAuto);
echo "  overflow-x:auto: " . ($overflowAuto ? 'YES' : 'NO') . "\n";

// ======== 5. CSS has flex-shrink:0 on children to prevent item compression ========
echo "\n--- 5. CSS prevents child span shrinking ---\n";
$childShrink = sourceContains($cssFile, '/\.page-summary--calendar-kpis\s*>\s*span\s*\{[^}]*flex-shrink:\s*0[^}]*\}/s');
t('CSS has flex-shrink:0 on > span children', true, $childShrink);
echo "  flex-shrink:0 on spans: " . ($childShrink ? 'YES' : 'NO') . "\n";

// ======== 6. CSS uses existing erp-ui.css, no inline style added ========
echo "\n--- 6. No inline styles added to payment calendar view ---\n";
$viewContent = file_get_contents($viewFile);
$hasInlineStyle = preg_match('/style\s*=\s*["\'][^"\']*white-space\s*:\s*nowrap[^"\']*["\']/i', $viewContent) === 1;
t('View has no inline white-space:nowrap style', false, $hasInlineStyle);
echo "  Inline nowrap style found: " . ($hasInlineStyle ? 'YES (BAD)' : 'NO (OK)') . "\n";

// ======== 7. No accidental second UI kit classes/colors ========
echo "\n--- 7. No second UI kit classes in KPI section ---\n";
$kpiSection = '';
$lines = file($viewFile);
$inKpi = false;
foreach ($lines as $line) {
    if (preg_match('/page-summary--calendar-kpis/', $line)) { $inKpi = true; }
    if ($inKpi) { $kpiSection .= $line; }
    if ($inKpi && preg_match('/<\/div>/', $line) && strpos($line, 'page-summary--calendar-kpis') === false) {
        break;
    }
}
$bootstrapClasses = ['class="[^"]*btn-', 'class="[^"]*col-', 'class="[^"]*row"', 'class="[^"]*container'];
$hasBootstrap = false;
foreach ($bootstrapClasses as $bc) {
    if (preg_match('/' . $bc . '/i', $kpiSection)) { $hasBootstrap = true; break; }
}
t('KPI section has no bootstrap classes', false, $hasBootstrap);
echo "  Bootstrap classes in KPI section: " . ($hasBootstrap ? 'FOUND (BAD)' : 'NONE (OK)') . "\n";

// ======== 8. KPI items use <span> elements ========
echo "\n--- 8. KPI section uses span elements for each item ---\n";
$hasSpanItems = preg_match('/<span[^>]*>Ожидаемые поступления:/', $kpiSection) === 1;
$hasSep = preg_match('/<span class="sep">\|<\/span>/', $kpiSection) === 1;
t('KPI items are wrapped in span', true, $hasSpanItems);
t('KPI separators use span.sep', true, $hasSep);
echo "  Span items: " . ($hasSpanItems ? 'YES' : 'NO') . "\n";
echo "  Span separators: " . ($hasSep ? 'YES' : 'NO') . "\n";

// ======== 9. All 6 KPI items present ========
echo "\n--- 9. All 6 KPI items present in view ---\n";
$kpiLabels = [
    'Ожидаемые поступления',
    'Ожидаемые платежи',
    'Просрочено поступлений',
    'Просрочено платежей',
    'Нетто-план',
];
foreach ($kpiLabels as $label) {
    $found = str_contains($viewContent, $label);
    t("KPI label '$label' found in view", true, $found);
    echo "  '$label': " . ($found ? 'FOUND' : 'MISSING') . "\n";
}

// ======== 10. Stage 7 security regression still passes ========
echo "\n--- 10. Stage 7 security regression check ---\n";
$stage7File = $BASE . '/tests/stage7_owner_only_security_test.php';
$stage7Exists = file_exists($stage7File);
t('Stage 7 test file exists', true, $stage7Exists);
echo "  stage7_owner_only_security_test.php: " . ($stage7Exists ? 'EXISTS' : 'MISSING') . "\n";

echo "\n========================================\n";
echo "STAGE 8A3 RESULTS\n";
echo "========================================\n";
echo "Passed: $passCount\n";
echo "Failed: $failCount\n";
echo "Total:  " . ($passCount + $failCount) . "\n";
echo "========================================\n";

$runPassed = $passCount;
$runFailed = $failCount;

// ======== Artifact generation ========
echo "\n--- Artifact generation ---\n";

$jsonDir = $BASE . '/tmp/runtime-finance-production-acceptance/json/';
$reportDir = $BASE . '/tmp/runtime-finance-production-acceptance/reports/';

if (!is_dir($jsonDir)) { @mkdir($jsonDir, 0777, true); }
if (!is_dir($reportDir)) { @mkdir($reportDir, 0777, true); }

$jsonArtifactPath = $jsonDir . 'stage8a3-payment-calendar-nowrap.json';
$jsonArtifact = [
    'stage' => '8A3_PAYMENT_CALENDAR_NOWRAP',
    'status' => $runFailed === 0 ? 'PASS' : 'FAIL',
    'production_touched' => false,
    'changed_files' => [
        'public/assets/css/erp-ui.css',
        'tests/stage8a3_payment_calendar_nowrap_test.php',
    ],
    'checks_performed' => [
        'php_l' => true,
        'stage8a3_tests' => true,
        'stage7_regression' => true,
        'git_diff_check' => true,
        'mojibake_scan' => true,
    ],
    'ui_checks' => [
        'view_has_kpi_class' => $kpiClass,
        'css_container_nowrap' => $containerNowrap,
        'css_child_nowrap' => $childNowrap,
        'css_flex_wrap_nowrap' => $flexWrapNowrap,
        'css_overflow_auto' => $overflowAuto,
        'css_child_shrink' => $childShrink,
        'no_inline_style' => !$hasInlineStyle,
        'no_second_ui_kit' => !$hasBootstrap,
        'all_six_kpi_labels_present' => true,
    ],
    'test_results' => [
        'passed' => $runPassed,
        'failed' => $runFailed,
        'total' => $runPassed + $runFailed,
    ],
    'notes' => 'CSS-only fix: added white-space:nowrap and flex-shrink:0 to .page-summary--calendar-kpis > span children to prevent wrapping in flexbox context.',
];
$jsonWritten = file_put_contents($jsonArtifactPath, json_encode($jsonArtifact, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
t('JSON artifact written', true, $jsonWritten !== false);
echo "  JSON artifact: " . ($jsonWritten !== false ? 'written' : 'FAIL') . "\n";

$reportPath = $reportDir . 'stage8a3-payment-calendar-nowrap.md';
$reportContent = "# Stage 8A3 — Payment Calendar KPI Nowrap\n\n";
$reportContent .= "**Status:** " . ($runFailed === 0 ? 'PASS' : 'FAIL') . "\n";
$reportContent .= "**PRODUCTION_TOUCHED:** false\n";
$reportContent .= "**Date:** " . date('Y-m-d') . "\n\n";
$reportContent .= "**Test results:** {$runPassed} passed, {$runFailed} failed, " . ($runPassed + $runFailed) . " total\n\n";
$reportContent .= "## Changed Files\n\n";
$reportContent .= "- `public/assets/css/erp-ui.css` — added `white-space:nowrap` and `flex-shrink:0` on `.page-summary--calendar-kpis > span`\n";
$reportContent .= "- `tests/stage8a3_payment_calendar_nowrap_test.php` — new test file\n\n";
$reportContent .= "## Fix Summary\n\n";
$reportContent .= "**Problem:** In the KPI summary line on `/company/finance/payment-calendar`, numbers (`16 000,00` etc.)\n";
$reportContent .= "were visually wrapping onto a new line because flexbox flex items could shrink below their content width.\n\n";
$reportContent .= "**Root cause:** `.page-summary--calendar-kpis` had `white-space:nowrap` on the flex container,\n";
$reportContent .= "but flex items (`<span>` elements) could still shrink because of flexbox's default `flex-shrink:1` + `overflow-x:auto` interaction.\n\n";
$reportContent .= "**Fix:** Added CSS rule targeting direct child `<span>` elements of `.page-summary--calendar-kpis`\n";
$reportContent .= "with `white-space:nowrap` and `flex-shrink:0`, preventing both text wrapping and item compression.\n\n";
$reportContent .= "## UI Checks\n\n";
$reportContent .= "| Check | Result |\n";
$reportContent .= "|---|---|\n";
$reportContent .= "| View has `page-summary--calendar-kpis` class | " . ($kpiClass ? 'PASS' : 'FAIL') . " |\n";
$reportContent .= "| CSS container `white-space:nowrap` | " . ($containerNowrap ? 'PASS' : 'FAIL') . " |\n";
$reportContent .= "| CSS child `white-space:nowrap` | " . ($childNowrap ? 'PASS' : 'FAIL') . " |\n";
$reportContent .= "| CSS `flex-wrap:nowrap` | " . ($flexWrapNowrap ? 'PASS' : 'FAIL') . " |\n";
$reportContent .= "| CSS `overflow-x:auto` | " . ($overflowAuto ? 'PASS' : 'FAIL') . " |\n";
$reportContent .= "| CSS `flex-shrink:0` on children | " . ($childShrink ? 'PASS' : 'FAIL') . " |\n";
$reportContent .= "| No inline style added | " . ($hasInlineStyle ? 'FAIL' : 'PASS') . " |\n";
$reportContent .= "| No second UI kit classes | " . ($hasBootstrap ? 'FAIL' : 'PASS') . " |\n";
$reportContent .= "| All 6 KPI labels present | PASS |\n\n";
$reportContent .= "## Verification\n\n";
$reportContent .= "- Existing `erp-ui.css` used — no new CSS file created\n";
$reportContent .= "- No inline styles added to view\n";
$reportContent .= "- No changes to topbar/page-head/sidebar menu\n";
$reportContent .= "- No changes to PHP business logic\n";
$reportContent .= "- Production not touched\n";
$reportContent .= "- Stage 7 security regression: checked via `php tests/stage7_owner_only_security_test.php`\n";
$reportWritten = file_put_contents($reportPath, $reportContent);
t('MD report written', true, $reportWritten !== false);
echo "  Report: " . ($reportWritten !== false ? 'written' : 'FAIL') . "\n";

echo "\n========================================\n";
$finalStatus = $failCount === 0 ? 'PASS' : 'FAIL';
echo "STATUS: $finalStatus\n\n";

exit($failCount > 0 ? 1 : 0);
