<?php

use App\Core\Database;
use App\Service\FinanceAllocationService;

requireRole(['company_owner']);
verifyCsrfRequest();

$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest';
$success = false;
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

    $allocationId = (int) $id;
    $user = $_SESSION['user'] ?? [];
    $reason = $_POST['reason'] ?? 'Отменено вручную';

    $operationId = FinanceAllocationService::cancelAllocation($localPdo, $allocationId, $user, $reason);
    $success = true;
} catch (\Throwable $e) {
    $error = $e->getMessage();
}

if ($success) {
    if ($isAjax) {
        $operation = FinanceAllocationService::fetchOperationCard($localPdo, $operationId);
        $allocations = FinanceAllocationService::fetchOperationAllocations($localPdo, $operationId);
        $canAllocate = in_array($_SESSION['role_code'] ?? '', ['company_owner'], true);
        require base_path('app/View/partials/company_finance_operation_modal_view.php');
        exit;
    }
    $_SESSION['finance_success'] = 'Распределение отменено.';
    redirect_to(app_url('/company/finance/operations'));
} else {
    if ($isAjax) {
        header('Content-Type: text/html; charset=utf-8');
        echo '<div class="form-alert alert-error">' . e($error ?? 'Неизвестная ошибка.') . '</div>';
        exit;
    }
    $_SESSION['finance_error'] = $error ?? 'Неизвестная ошибка.';
    redirect_to(app_url('/company/finance/operations'));
}
