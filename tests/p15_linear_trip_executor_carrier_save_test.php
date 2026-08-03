<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$checks = [];
$failed = 0;

$check = static function (bool $condition, string $label) use (&$checks, &$failed): void {
    $checks[] = [$condition, $label];
    if (!$condition) {
        $failed++;
    }
};

$read = static function (string $path) use ($root): string {
    $content = file_get_contents($root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path));
    if ($content === false) {
        throw new RuntimeException('Unable to read ' . $path);
    }
    return $content;
};

$normalizer = $read('app/Service/LinearTripRequestNormalizer.php');
$controller = $read('app/Http/Controllers/Company/LinearTripController.php');
$editForm = $read('app/Http/Controllers/Company/LinearTripActions/modal_edit_form.php');
$saveService = $read('app/Service/LinearTripEditSaveService.php');
$tripPage = $read('app/View/pages/company_linear_trips.php');
$carrierJs = $read('public/assets/js/linear-trip-executor-carrier.js');
$manifest = $read('app/Support/entrypoint_dependencies.php');

$check(str_contains($normalizer, 'deriveCarrierFromExecutor'), 'server derives carrier from route executor');
$check(str_contains($normalizer, "['contractor_id']"), 'derived carrier uses executor contractor_id');
$check(str_contains($controller, 'normalizeSubmittedExecutorCarrier();'), 'controller normalizes create/edit submissions');
$check(substr_count($controller, 'normalizeSubmittedExecutorCarrier();') >= 2, 'carrier normalization covers create and edit');
$check(str_contains($editForm, 'normalizeRoutePaymentConditions'), 'edit form normalizes legacy payment conditions');
$check(str_contains($normalizer, "'Предоплата на загрузке' => DateCalculationService::CONDITION_START_DAY"), 'legacy loading prepayment maps to route start day');
$check(str_contains($saveService, "'payment_due_type' => self::legacyDueType(\$conditionType)"), 'save payload preserves legacy payment_due_type');
$check(str_contains($saveService, "'payment_due_days' => \$daysCount"), 'save payload preserves legacy payment_due_days');
$check(str_contains($saveService, "'payment_due_days_kind' => \$daysKind"), 'save payload preserves legacy payment_due_days_kind');
$check(str_contains($tripPage, 'linear-trip-executor-carrier.js'), 'linear trip page loads executor-carrier behavior');
$check(str_contains($carrierJs, "field.classList.add('is-hidden')"), 'redundant carrier field is hidden');
$check(str_contains($carrierJs, "target.name !== 'route_executor_id'"), 'carrier sync reacts to executor changes');
$check(str_contains($carrierJs, "carrier.value = matchedValue"), 'client fallback synchronizes carrier value');
$check(str_contains($manifest, 'LinearTripRequestNormalizer.php'), 'runtime manifest registers request normalizer');

foreach ($checks as [$condition, $label]) {
    echo ($condition ? 'PASS' : 'FAIL') . ' - ' . $label . PHP_EOL;
}

echo 'TOTAL=' . count($checks) . '; FAILED=' . $failed . PHP_EOL;
exit($failed === 0 ? 0 : 1);
