<?php

use App\Core\Database;
use App\Service\FinanceAllocationService;

requireRole(['company_owner']);

$operation = null;
$allocations = [];
$error = null;

try {
    $companyId = $_SESSION['company_id'] ?? 0;
    $company = $db->fetch('SELECT * FROM companies WHERE id = ?', [$companyId]);

    if (!$company || $company['status'] !== 'active') {
        throw new \RuntimeException('Компания недоступна.');
    }

    $localConfig = companyDatabaseConfig($config, $company);
    $localDb = new Database($localConfig);
    $localPdo = $localDb->connection();
    applyLocalMigrations($localPdo);

    $operationId = (int) $id;
    $operation = FinanceAllocationService::fetchOperationCard($localPdo, $operationId);

    if (!$operation) {
        http_response_code(404);
        throw new \RuntimeException('Операция не найдена.');
    }

    $allocations = FinanceAllocationService::fetchOperationAllocations($localPdo, $operationId);

    $user = $_SESSION['user'] ?? [];
    $roleCode = (string) ($user['role_code'] ?? '');
    $canAllocate = $roleCode === 'company_owner';
} catch (\Throwable $e) {
    $error = $e->getMessage();
}

require base_path('app/View/partials/company_finance_operation_modal_view.php');
