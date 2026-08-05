<?php

declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/app/Service/DateCalculationService.php';
require_once $root . '/app/Service/LinearRouteService.php';

use App\Service\DateCalculationService;
use App\Service\LinearRouteService;

$failed = 0;
$check = static function (bool $condition, string $label) use (&$failed): void {
    echo ($condition ? 'PASS' : 'FAIL') . ' - ' . $label . PHP_EOL;
    if (!$condition) $failed++;
};

$expected = [
    DateCalculationService::CONDITION_PREPAYMENT => 'Предоплата на загрузке',
    DateCalculationService::CONDITION_START_DAY => 'Предоплата на загрузке',
    DateCalculationService::CONDITION_AFTER_START => 'После загрузки',
    DateCalculationService::CONDITION_END_DAY => 'До выгрузки',
    DateCalculationService::CONDITION_AFTER_END => 'После выгрузки',
    DateCalculationService::CONDITION_AFTER_DOCUMENTS => 'После выгрузки',
    DateCalculationService::CONDITION_SPECIFIC_DATE => 'После загрузки',
];
foreach ($expected as $condition => $legacy) {
    $check(LinearRouteService::legacyPaymentDueTypeFromConditionType($condition) === $legacy, 'mapping ' . $condition);
}

$create = file_get_contents($root . '/app/Http/Controllers/Company/LinearTripActions/create_submit.php');
$edit = file_get_contents($root . '/app/Http/Controllers/Company/LinearTripActions/modal_edit_submit_p13.php');
$manifest = file_get_contents($root . '/app/Support/entrypoint_dependencies.php');
$check(is_string($create) && str_contains($create, "'payment_due_type' => LinearRouteService::legacyPaymentDueTypeFromConditionType"), 'create payload persists payment_due_type');
$check(is_string($create) && str_contains($create, "'payment_due_days' => \$daysCount"), 'create payload persists payment_due_days');
$check(is_string($create) && str_contains($create, "'payment_due_days_kind' => \$daysKind"), 'create payload persists payment_due_days_kind');
$check(is_string($create) && !str_contains($create, "'Не удалось создать рейс: ' . \$e->getMessage()"), 'create UI does not expose exception message');
$unexpectedCatch = is_string($edit) ? substr($edit, (int) strrpos($edit, '} catch (Throwable $e) {')) : '';
$check(!str_contains($unexpectedCatch, "'message' => \$e->getMessage()"), 'edit unexpected-error UI does not expose exception message');
$check(is_string($manifest) && str_contains($manifest, 'MutationErrorService.php'), 'runtime manifest registers error service');

echo 'FAILED=' . $failed . PHP_EOL;
exit($failed === 0 ? 0 : 1);
