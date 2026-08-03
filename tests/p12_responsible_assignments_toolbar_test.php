<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$css = file_get_contents(__DIR__ . '/../public/assets/css/erp-ui.css');
$view = file_get_contents(__DIR__ . '/../app/View/pages/company_responsible_assignments.php');
$checks = [
    'Mass toolbar form exists in view' => str_contains($view, 'id="massReassignToolbarForm"'),
    'Mass submit button exists' => str_contains($view, 'Переназначить выбранные'),
    'Scoped P12 CSS marker exists' => str_contains($css, 'P12 responsible assignments mass toolbar'),
    'Mass toolbar remains one row on Full HD' => preg_match('/#massReassignToolbarForm\s*\{[^}]*flex-flow:\s*row nowrap;/s', $css) === 1,
    'Mass toolbar vertically aligns controls' => preg_match('/#massReassignToolbarForm\s*\{[^}]*align-items:\s*center;/s', $css) === 1,
    'Select can shrink and grow' => preg_match('/#massReassignToolbarForm \.field-select\s*\{[^}]*flex:\s*1 1 320px;/s', $css) === 1,
    'Submit button remains visible' => preg_match('/#massReassignToolbarForm \.btn\s*\{[^}]*flex:\s*0 0 auto;/s', $css) === 1,
    'Narrow desktop fallback expands toolbar' => str_contains($css, '.table-toolbar:has(#massReassignToolbarForm)'),
];

$failed = 0;
echo "=== P12 Responsible Assignments Toolbar ===\n";
foreach ($checks as $name => $ok) {
    echo ($ok ? 'PASS' : 'FAIL') . " - {$name}\n";
    if (!$ok) $failed++;
}
echo 'TOTAL=' . count($checks) . '; FAILED=' . $failed . "\n";
exit($failed === 0 ? 0 : 1);
