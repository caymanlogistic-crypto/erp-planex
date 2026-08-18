<?php

declare(strict_types=1);

$directServicePath = dirname(__DIR__) . '/app/Service/FinanceEmployeeDirectTransferService.php';
$legacyServicePath = dirname(__DIR__) . '/app/Service/FinanceEmployeeTransferService.php';
$routePath = dirname(__DIR__) . '/app/Http/Routes/company_finance_employee_payments.php';
$viewPath = dirname(__DIR__) . '/app/View/pages/company_finance_employee_payments.php';

foreach ([$directServicePath, $legacyServicePath, $routePath, $viewPath] as $path) {
    if (!is_file($path)) {
        fwrite(STDERR, "Missing file: {$path}\n");
        exit(1);
    }
}

$direct = file_get_contents($directServicePath);
$legacy = file_get_contents($legacyServicePath);
$route = file_get_contents($routePath);
$view = file_get_contents($viewPath);

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
};

$assert(str_contains($direct, "'RETURN'"), 'sender must be recorded as RETURN');
$assert(str_contains($direct, "'PAYMENT'"), 'recipient must be recorded as PAYMENT');
$assert(str_contains($direct, 'FinanceEmployeeMoneyAccountService::accountId'), 'each transfer side must use an employee money account');
$assert(str_contains($direct, "'TRANSFER','POSTED','TRANSFER'"), 'employee handoff must be a paired internal transfer');
$assert(str_contains($direct, "'EMPLOYEE'"), 'employee movement provenance must be EMPLOYEE');
$assert(str_contains($direct, "'cash_account_used' => false"), 'direct transfer must record retired-account bypass');
$assert(!str_contains($direct, 'INSERT INTO finance_cash_resolutions'), 'direct transfer must never create historical resolution');
$assert(!str_contains($direct, 'FinanceCashResolutionService::MAIN_CASH_NAME'), 'direct transfer must not depend on retired main account');
$assert(str_contains($direct, 'beginTransaction()'), 'employee handoff must be atomic');
$assert(str_contains($direct, 'rollBack()'), 'employee handoff must roll back both legs on failure');
$assert(!str_contains($direct, 'Недостаточно средств у сотрудника'), 'sender balance may become negative');

$assert(str_contains($legacy, 'public static function decorateLedger'), 'historical ledger decoration remains available');
$assert(str_contains($legacy, 'finance_cash_resolutions'), 'historical transfer implementation remains preserved for read/audit compatibility');

$assert(str_contains($route, "FinanceEmployeeDirectActions/transfer_submit.php"), 'new transfer route must use direct service action');
$assert(str_contains($route, 'Исторические передачи защищены от изменения'), 'historical transfer edit endpoint must be frozen');
$assert(str_contains($route, 'Исторические передачи защищены от удаления'), 'historical transfer delete endpoint must be frozen');
$assert(!str_contains($route, "[$controller, 'transferSubmit']"), 'active create route must not call legacy transfer controller');
$assert(!str_contains($route, "[$controller, 'transferUpdateSubmit']"), 'active update route must not call legacy transfer mutation');
$assert(!str_contains($route, "[$controller, 'transferDeleteSubmit']"), 'active delete route must not call legacy transfer mutation');

$assert(str_contains($view, 'id="employee-transfer-open"'), 'page header must expose transfer button');
$assert(str_contains($view, 'btn btn-primary btn--toolbar'), 'transfer button must use standard primary toolbar style');
$assert(str_contains($view, 'Сальдо отправителя после перевода может стать отрицательным.'), 'UI explicitly allows negative sender balance');
$assert(str_contains($view, 'Поступление: <strong>'), 'all-time incoming label uses Поступление');
$assert(str_contains($view, 'Расход: <strong>'), 'all-time outgoing label uses Расход');
$assert(str_contains($view, '>Поступление</th>'), 'incoming table heading uses Поступление');
$assert(str_contains($view, '>Расход</th>'), 'outgoing table heading uses Расход');

fwrite(STDOUT, "Employee direct transfer architecture: OK\n");
