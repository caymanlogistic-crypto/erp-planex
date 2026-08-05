from __future__ import annotations

from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[2]


def read(rel: str) -> str:
    return (ROOT / rel).read_text(encoding="utf-8")


def write(rel: str, content: str) -> None:
    if content.startswith("\ufeff"):
        raise RuntimeError(f"BOM forbidden: {rel}")
    (ROOT / rel).write_text(content, encoding="utf-8", newline="\n")


def replace_once(content: str, old: str, new: str, label: str) -> str:
    count = content.count(old)
    if count != 1:
        raise RuntimeError(f"{label}: expected one anchor, found {count}")
    return content.replace(old, new, 1)


# 1. Register the protected mutation error service.
manifest_path = "app/Support/entrypoint_dependencies.php"
manifest = read(manifest_path)
manifest = replace_once(
    manifest,
    "require_once base_path('app/Service/AuditService.php');\n",
    "require_once base_path('app/Service/AuditService.php');\n"
    "require_once base_path('app/Service/MutationErrorService.php');\n",
    "manifest service registration",
)
write(manifest_path, manifest)

# 2. Establish one canonical modern -> legacy due-type mapping.
route_service_path = "app/Service/LinearRouteService.php"
route_service = read(route_service_path)
anchor = """    public static function parsePaymentMethodFromLegacyType(string $paymentType): string
    {
"""
method = """    public static function legacyPaymentDueTypeFromConditionType(string $conditionType): string
    {
        return match ($conditionType) {
            DateCalculationService::CONDITION_PREPAYMENT,
            DateCalculationService::CONDITION_START_DAY => 'Предоплата на загрузке',
            DateCalculationService::CONDITION_AFTER_START => 'После загрузки',
            DateCalculationService::CONDITION_END_DAY => 'До выгрузки',
            DateCalculationService::CONDITION_AFTER_END,
            DateCalculationService::CONDITION_AFTER_DOCUMENTS => 'После выгрузки',
            DateCalculationService::CONDITION_SPECIFIC_DATE => 'После загрузки',
            default => 'После загрузки',
        };
    }

"""
route_service = replace_once(route_service, anchor, method + anchor, "canonical due mapping")
write(route_service_path, route_service)

# 3. Make edit-save delegate to the canonical mapping.
edit_service_path = "app/Service/LinearTripEditSaveService.php"
edit_service = read(edit_service_path)
legacy_pattern = re.compile(
    r"    private static function legacyDueType\(string \$conditionType\): string\n"
    r"    \{\n"
    r"        return match \(\$conditionType\) \{.*?\n"
    r"        \};\n"
    r"    \}\n",
    re.DOTALL,
)
replacement = """    private static function legacyDueType(string $conditionType): string
    {
        return LinearRouteService::legacyPaymentDueTypeFromConditionType($conditionType);
    }
"""
edit_service, count = legacy_pattern.subn(replacement, edit_service, count=1)
if count != 1:
    raise RuntimeError(f"edit legacy mapping: expected one method, found {count}")
write(edit_service_path, edit_service)

# 4. Fix create-path payment payload and sanitize unexpected errors.
create_path = "app/Http/Controllers/Company/LinearTripActions/create_submit.php"
create = read(create_path)
create = replace_once(
    create,
    "use App\\Service\\LocalMigrationService;\n",
    "use App\\Service\\LocalMigrationService;\nuse App\\Service\\MutationErrorService;\n",
    "create mutation service import",
)
create = replace_once(
    create,
    """            'specific_due_date' => $specificDueDate,
            'condition_comment' => $conditionComment !== '' ? $conditionComment : null,
        ];
""",
    """            'specific_due_date' => $specificDueDate,
            'condition_comment' => $conditionComment !== '' ? $conditionComment : null,
            // Keep the legacy columns populated until the compatibility schema is retired.
            'payment_due_type' => LinearRouteService::legacyPaymentDueTypeFromConditionType($conditionType),
            'payment_due_days' => $daysCount,
            'payment_due_days_kind' => $daysKind,
        ];
""",
    "create legacy payment payload",
)
old_catch = """} catch (\\Throwable $e) {
    if (isset($localPdo) && $localPdo instanceof PDO && $localPdo->inTransaction()) {
        $localPdo->rollBack();
    }

    $_SESSION['linear_trip_form_error'] = 'Не удалось создать рейс: ' . $e->getMessage();
    $_SESSION['linear_trip_validation_errors'] = $errors;
    $_SESSION['linear_trip_old'] = $old;
    header('Location: ' . $redirect);
    exit;
}
"""
new_catch = """} catch (\\Throwable $e) {
    if (isset($localPdo) && $localPdo instanceof PDO && $localPdo->inTransaction()) {
        $localPdo->rollBack();
    }

    $errorId = MutationErrorService::report($e, 'linear_trip_create', [
        'company_id' => $companyId,
        'user_id' => (int) ($_SESSION['user_id'] ?? 0),
        'role_code' => (string) ($_SESSION['role_code'] ?? ''),
        'route_type' => $routeType,
    ]);
    $_SESSION['linear_trip_form_error'] = MutationErrorService::userMessage('создать рейс', $errorId);
    $_SESSION['linear_trip_validation_errors'] = $errors;
    $_SESSION['linear_trip_old'] = $old;
    header('Location: ' . $redirect);
    exit;
}
"""
create = replace_once(create, old_catch, new_catch, "safe create exception flow")
write(create_path, create)

# 5. Reuse the same protected flow for edit-save unexpected errors.
edit_controller_path = "app/Http/Controllers/Company/LinearTripActions/modal_edit_submit_p13.php"
edit_controller = read(edit_controller_path)
edit_controller = replace_once(
    edit_controller,
    "use App\\Service\\LinearTripEditTokenService;\n",
    "use App\\Service\\LinearTripEditTokenService;\nuse App\\Service\\MutationErrorService;\n",
    "edit mutation service import",
)
edit_catch_pattern = re.compile(r"\} catch \(Throwable \$e\) \{.*?\n\}\n\Z", re.DOTALL)
edit_catch = """} catch (Throwable $e) {
    $errorId = MutationErrorService::report($e, 'linear_trip_update', [
        'company_id' => $companyId,
        'route_id' => $routeId,
        'user_id' => $sessionUser['user_id'],
        'role_code' => $sessionUser['role_code'],
    ]);

    $respond([
        'success' => false,
        'message' => MutationErrorService::userMessage('сохранить рейс', $errorId),
        'error_code' => 'internal_save_error',
        'error_id' => $errorId,
        'retry_token' => LinearTripEditTokenService::issue($routeId),
    ], 500);
}
"""
edit_controller, count = edit_catch_pattern.subn(edit_catch, edit_controller, count=1)
if count != 1:
    raise RuntimeError(f"safe edit exception flow: expected one catch, found {count}")
write(edit_controller_path, edit_controller)

# 6. Add regression tests that fail on the P14 implementation.
test_path = ROOT / "tests/p15_trip_create_payment_terms_regression_test.php"
test_path.write_text("""<?php

declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/app/Service/DateCalculationService.php';
require_once $root . '/app/Service/LinearRouteService.php';

use App\\Service\\DateCalculationService;
use App\\Service\\LinearRouteService;

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
$check(is_string($create) && str_contains($create, "'payment_due_days' => \\$daysCount"), 'create payload persists payment_due_days');
$check(is_string($create) && str_contains($create, "'payment_due_days_kind' => \\$daysKind"), 'create payload persists payment_due_days_kind');
$check(is_string($create) && !str_contains($create, "'Не удалось создать рейс: ' . \\$e->getMessage()"), 'create UI does not expose exception message');
$check(is_string($edit) && !str_contains($edit, "'message' => \\$e->getMessage()"), 'edit UI does not expose unexpected exception message');
$check(is_string($manifest) && str_contains($manifest, 'MutationErrorService.php'), 'runtime manifest registers error service');

echo 'FAILED=' . $failed . PHP_EOL;
exit($failed === 0 ? 0 : 1);
""", encoding="utf-8", newline="\n")

security_test = ROOT / "tests/p15_mutation_error_service_test.php"
security_test.write_text("""<?php

declare(strict_types=1);

$source = file_get_contents(dirname(__DIR__) . '/app/Service/MutationErrorService.php');
$failed = 0;
$check = static function (bool $condition, string $label) use (&$failed): void {
    echo ($condition ? 'PASS' : 'FAIL') . ' - ' . $label . PHP_EOL;
    if (!$condition) $failed++;
};
$check(is_string($source) && str_contains($source, 'mutation_errors.log'), 'technical details use protected log');
$check(is_string($source) && str_contains($source, "'[REDACTED]'"), 'secret-like context is redacted');
$check(is_string($source) && !str_contains($source, "userMessage(string \\$action, string \\$errorId): string\\n    {\\n        return \\$error->getMessage"), 'public message cannot return exception text');
$check(is_string($source) && str_contains($source, 'Код ошибки:'), 'public message contains correlation ID');
echo 'FAILED=' . $failed . PHP_EOL;
exit($failed === 0 ? 0 : 1);
""", encoding="utf-8", newline="\n")

print("P15_PATCH=PASS\n")
