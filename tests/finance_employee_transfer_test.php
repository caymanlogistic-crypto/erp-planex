<?php

declare(strict_types=1);

$servicePath = dirname(__DIR__) . '/app/Service/FinanceEmployeeTransferService.php';
$controllerPath = dirname(__DIR__) . '/app/Http/Controllers/Company/FinanceEmployeePaymentsController.php';
$routePath = dirname(__DIR__) . '/app/Http/Routes/company_finance_employee_payments.php';
$viewPath = dirname(__DIR__) . '/app/View/pages/company_finance_employee_payments.php';
$entrypointDependenciesPath = dirname(__DIR__) . '/app/Support/entrypoint_dependencies.php';

foreach ([$servicePath, $controllerPath, $routePath, $viewPath, $entrypointDependenciesPath] as $path) {
    if (!is_file($path)) {
        fwrite(STDERR, "Missing file: {$path}\n");
        exit(1);
    }
}

$service = file_get_contents($servicePath);
$controller = file_get_contents($controllerPath);
$route = file_get_contents($routePath);
$view = file_get_contents($viewPath);
$entrypointDependencies = file_get_contents($entrypointDependenciesPath);

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
};

$assert(str_contains($service, "'movement_type' => 'RETURN'"), 'sender must be recorded as RETURN');
$assert(str_contains($service, "'movement_type' => 'PAYMENT'"), 'recipient must be recorded as PAYMENT');
$assert(str_contains($service, "FinanceCashResolutionService::MAIN_CASH_NAME"), 'transfer must use the technical main cash');
$assert(str_contains($service, "operation_type = 'TRANSFER'"), 'employee handoff cash legs must be technical transfers');
$assert(str_contains($service, "transfer_direction = ?"), 'technical transfer direction must be explicit');
$assert(str_contains($service, "INSERT INTO finance_cash_resolutions"), 'employee handoff must create a cash resolution');
$assert(str_contains($service, "beginTransaction()"), 'employee handoff must be atomic');
$assert(str_contains($service, "rollBack()"), 'employee handoff must roll back both legs on failure');
$assert(!str_contains($service, 'Недостаточно средств у сотрудника'), 'sender balance must not limit transfer amount');
$assert(!str_contains($service, 'lockAndReadEmployeeBalance'), 'sender balance gate must not exist');
$assert(str_contains($service, 'public static function decorateLedger'), 'employee ledger must expose editable transfer metadata');
$assert(str_contains($service, 'public static function updateTransfer'), 'employee transfer must support atomic editing');
$assert(str_contains($service, 'public static function deleteTransfer'), 'employee transfer must support deletion');
$assert(str_contains($service, "'employee_transfer_edit'"), 'employee transfer edits must be audited');
$assert(str_contains($service, "'employee_transfer_delete'"), 'employee transfer deletions must be audited');
$assert(str_contains($service, "status = 'CANCELLED'"), 'delete must preserve audit by cancelling transfer legs instead of physical deletion');

$assert(str_contains($entrypointDependencies, "require_once base_path('app/Service/FinanceEmployeeTransferService.php');"), 'public runtime must load employee transfer service');
$assert(str_contains($route, '/company/finance/employee-payments/transfer'), 'transfer route must be registered');
$assert(str_contains($route, '/company/finance/employee-payments/transfer/update'), 'transfer update route must be registered');
$assert(str_contains($route, '/company/finance/employee-payments/transfer/delete'), 'transfer delete route must be registered');
$assert(str_contains($controller, 'FinanceEmployeeTransferService::transfer'), 'controller must delegate create to transfer service');
$assert(str_contains($controller, 'FinanceEmployeeTransferService::updateTransfer'), 'controller must delegate edits to transfer service');
$assert(str_contains($controller, 'FinanceEmployeeTransferService::deleteTransfer'), 'controller must delegate deletion to transfer service');
$assert(str_contains($controller, 'FinanceEmployeeTransferService::decorateLedger'), 'main employee report must decorate transfer rows');
$assert(str_contains($view, 'id="employee-transfer-open"'), 'page header must expose the transfer button');
$assert(str_contains($view, 'btn btn-primary btn--toolbar'), 'transfer button must use the standard primary toolbar style');
$assert(!str_contains($view, 'Перевод проводится как внутреннее движение'), 'transfer modal must not show the removed technical hint block');
$assert(!str_contains($view, 'employee-transfer-note'), 'removed transfer hint wrapper must not remain in the view');
$assert(str_contains($view, 'Сальдо отправителя после перевода может стать отрицательным.'), 'UI must explicitly allow negative sender balance');
$assert(str_contains($view, 'Передача денежных средств:'), 'transfer basis must recognize the generated employee handoff label');
$assert(str_contains($view, "implode(' ',\$initials)"), 'transfer participant names must render with spaced initials');
$assert(str_contains($view, 'Поступление: <strong>'), 'all-time incoming label must use Поступление');
$assert(str_contains($view, 'Расход: <strong>'), 'all-time outgoing label must use Расход');
$assert(str_contains($view, 'За месяц: Поступление'), 'monthly incoming label must use Поступление');
$assert(str_contains($view, '· Расход <strong>'), 'monthly outgoing label must use Расход');
$assert(str_contains($view, '>Поступление</th>'), 'incoming table heading must use Поступление');
$assert(str_contains($view, '>Расход</th>'), 'outgoing table heading must use Расход');
$assert(!str_contains($view, 'Получено от компании:'), 'old all-time incoming wording must be removed');
$assert(!str_contains($view, 'Возвращено компании:'), 'old all-time outgoing wording must be removed');
$assert(str_contains($view, 'employee-transfer-row'), 'editable transfer rows must be identifiable');
$assert(str_contains($view, "addEventListener('dblclick'"), 'double click must open transfer editing');
$assert(str_contains($view, 'id="employee-transfer-edit-modal"'), 'standard edit popup must exist');
$assert(str_contains($view, 'Редактирование передачи денег'), 'edit popup must use an explicit transfer title');
$assert(str_contains($view, 'class="section-title"'), 'edit popup must follow the standard system section-title pattern');
$assert(str_contains($view, 'fs-delete-action'), 'edit popup must use the standard destructive action style');
$assert(str_contains($view, 'Удалить передачу'), 'edit popup must expose deletion');
$assert(str_contains($view, '>Сохранить</button>'), 'edit popup must expose saving');
$assert(str_contains($view, 'name="source_employee_ref"'), 'edit popup must expose sender');
$assert(str_contains($view, 'name="target_employee_ref"'), 'edit popup must expose recipient');
$assert(str_contains($view, 'name="operation_date"'), 'edit popup must expose date');
$assert(str_contains($view, 'name="amount"'), 'edit popup must expose amount');
$assert(str_contains($view, 'name="purpose"'), 'edit popup must expose basis');
$assert(str_contains($view, 'name="comment"'), 'edit popup must expose comment');

fwrite(STDOUT, "Employee transfer architecture: OK\n");
