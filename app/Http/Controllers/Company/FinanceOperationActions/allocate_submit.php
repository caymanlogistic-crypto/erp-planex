<?php

use App\Core\Database;
use App\Service\FinanceAllocationService;

requireRole(['company_owner']);
verifyCsrfRequest();

$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest';
$success = false;
$error = null;
$allocationId = null;

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
    $user = $_SESSION['user'] ?? [];

    $data = [
        'amount' => $_POST['amount'] ?? '',
        'invoice_id' => $_POST['invoice_id'] ?? null,
        'linear_route_id' => $_POST['linear_route_id'] ?? null,
        'linear_route_payment_id' => $_POST['linear_route_payment_id'] ?? null,
        'allocation_date' => $_POST['allocation_date'] ?? date('Y-m-d'),
        'comment' => $_POST['comment'] ?? null,
    ];

    $allocationId = FinanceAllocationService::createAllocation($localPdo, $operationId, $data, $user);
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
    $_SESSION['finance_success'] = 'Распределение создано.';
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
