<?php
declare(strict_types=1);

$file = dirname(__DIR__) . '/app/View/partials/company_linear_trip_create_form.php';
$source = file_get_contents($file);
if ($source === false) {
    fwrite(STDERR, "FAIL: partial unreadable\n");
    exit(1);
}
if (!str_contains($source, '\\App\\Service\\DateCalculationService::CONDITION_LABELS')) {
    fwrite(STDERR, "FAIL: DateCalculationService must be fully qualified in the included partial\n");
    exit(1);
}
if (preg_match('/(?<![A-Za-z0-9_\\\\])DateCalculationService::CONDITION_LABELS/', $source) === 1) {
    fwrite(STDERR, "FAIL: unqualified DateCalculationService reference remains\n");
    exit(1);
}
echo "PASS: linear trip partial resolves DateCalculationService explicitly\n";
