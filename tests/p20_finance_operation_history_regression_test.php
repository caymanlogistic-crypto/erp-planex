<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$fail = static function (string $message): never {
    fwrite(STDERR, "P20 regression failure: {$message}\n");
    exit(1);
};
$ok = static function (bool $condition, string $message) use ($fail): void {
    if (!$condition) $fail($message);
};

$manifest = file_get_contents($root . '/app/Support/entrypoint_dependencies.php');
$historyAction = file_get_contents($root . '/app/Http/Controllers/Company/FinanceOperationActions/history.php');
$ok(is_string($manifest) && str_contains($manifest, "require_once base_path('app/Service/FinanceOperationActionService.php');"), 'FinanceOperationActionService is not loaded by entrypoint manifest');
$ok(is_string($historyAction) && str_contains($historyAction, "SELECT id FROM finance_operations WHERE id = ? LIMIT 1"), 'history action does not verify operation existence in tenant DB');
$ok(str_contains($historyAction, 'http_response_code(404);'), 'history action does not preserve not-found contract');
$ok(!str_contains($historyAction, 'json_encode('), 'history endpoint contract unexpectedly changed from HTML to JSON');

require_once $root . '/app/Service/FinanceOperationActionService.php';

if (extension_loaded('pdo_sqlite')) {
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('CREATE TABLE finance_operation_allocations (id INTEGER PRIMARY KEY, operation_id INTEGER NOT NULL)');
    $pdo->exec('CREATE TABLE finance_audit_log (id INTEGER PRIMARY KEY, entity_type TEXT NOT NULL, entity_id INTEGER NOT NULL, action TEXT NOT NULL, old_values TEXT NULL, new_values TEXT NULL, created_by_user_id INTEGER NULL, created_by_role TEXT NULL, created_at TEXT NOT NULL)');
    $pdo->exec("INSERT INTO finance_operation_allocations(id, operation_id) VALUES (7, 45)");
    $pdo->exec("INSERT INTO finance_audit_log(id,entity_type,entity_id,action,old_values,new_values,created_by_user_id,created_by_role,created_at) VALUES
      (1,'finance_operation',45,'cancel','{\"status\":\"POSTED\"}','{\"status\":\"CANCELLED\"}',12,'company_owner','2026-08-07 10:00:00'),
      (2,'finance_allocation',7,'allocation_create',NULL,'{\"amount\":\"100.00\"}',12,'company_owner','2026-08-07 10:01:00')");
    $rows = App\Service\FinanceOperationActionService::fetchOperationHistory($pdo, 45);
    $ok(count($rows) === 2, 'service did not return operation and allocation history entries');
    $ok(($rows[0]['action'] ?? '') === 'allocation_create', 'history ordering is not newest-first');
    $empty = App\Service\FinanceOperationActionService::fetchOperationHistory($pdo, 999999);
    $ok($empty === [], 'service empty-history contract changed');
} else {
    fwrite(STDOUT, "P20_SQLITE_SERVICE_FIXTURE=SKIPPED_EXTENSION_UNAVAILABLE\n");
    $rows = [[
        'action' => 'cancel',
        'old_values' => '{"status":"POSTED"}',
        'new_values' => '{"status":"CANCELLED"}',
        'created_by_user_id' => 12,
        'created_by_role' => 'company_owner',
        'created_at' => '2026-08-07 10:00:00',
    ]];
}

if (!function_exists('e')) {
    function e(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
}
$error = null;
$logs = [];
ob_start();
require $root . '/app/View/partials/company_finance_history_view.php';
$emptyHtml = ob_get_clean();
$ok(str_contains($emptyHtml, 'История изменений пуста'), 'empty history HTML state missing');

$error = null;
$logs = $rows;
ob_start();
require $root . '/app/View/partials/company_finance_history_view.php';
$historyHtml = ob_get_clean();
$ok(str_contains($historyHtml, 'history-timeline'), 'history timeline HTML missing');
$ok(str_contains($historyHtml, 'company_owner'), 'history actor/role missing from HTML');
$ok(str_contains($historyHtml, 'Стало:'), 'history old/new state rendering missing');

fwrite(STDOUT, "P20_FINANCE_HISTORY_REGRESSION=PASS\n");
