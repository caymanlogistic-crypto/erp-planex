<?php

use App\Core\Database;
use App\Service\FinanceOperationService;
use App\Service\FinanceAllocationService;

requireRole(['company_owner']);
verifyCsrfRequest();

$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest';
$error = null;

try {
    $companyId = (int)(getSessionCompanyId() ?? 0);
    $company = $db->fetch('SELECT * FROM companies WHERE id = ?', [$companyId]);

    if (!$company || $company['status'] !== 'active') {
        throw new \RuntimeException('Компания недоступна.');
    }

    $localConfig = companyDatabaseConfig($config, $company);
    $localDb = new Database($localConfig);
    $localPdo = $localDb->connection();
    applyLocalMigrations($localPdo);

    $operationId = (int) $id;
    $reason = (string) ($_POST['reason'] ?? '');
    $skipAllocations = !empty($_POST['skip_allocations']);

    $trimmedReason = trim($reason);
    if ($trimmedReason === '') {
        throw new \RuntimeException('Укажите причину отмены.');
    }

    $user = $_SESSION['user'] ?? [];
    $user['user_id'] = $_SESSION['user_id'] ?? 0;
    $user['role_code'] = $_SESSION['role_code'] ?? '';

    FinanceOperationService::cancelOperation($localPdo, $operationId, $user, $trimmedReason, $skipAllocations);
} catch (\Throwable $e) {
    $error = $e->getMessage();
}

if ($isAjax) {
    if ($error) {
        header('Content-Type: text/html; charset=utf-8');
        echo '<div class="form-alert alert-error">' . e($error) . '</div>';
        exit;
    }
    $operation = FinanceAllocationService::fetchOperationCard($localPdo, $operationId);
    $allocations = FinanceAllocationService::fetchOperationAllocations($localPdo, $operationId);
    $canAllocate = in_array($_SESSION['role_code'] ?? '', ['company_owner'], true);
    require base_path('app/View/partials/company_finance_operation_modal_view.php');
    exit;
}

if ($error) {
    $_SESSION['finance_error'] = 'Ошибка: ' . $error;
} else {
    $_SESSION['finance_success'] = 'Операция #' . $operationId . ' отменена.';
}

$redirectUrl = app_url('/company/finance/operations');
header('Location: ' . $redirectUrl);
exit;
