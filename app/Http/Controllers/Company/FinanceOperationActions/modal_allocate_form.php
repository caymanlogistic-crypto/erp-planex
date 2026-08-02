<?php

use App\Core\Database;
use App\Service\FinanceAllocationService;

requireRole(['company_owner']);

$operation = null;
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
        throw new \RuntimeException('Операция не найдена.');
    }

    if ($operation['status'] !== 'POSTED') {
        throw new \RuntimeException('Распределять можно только проведённые операции.');
    }

    $user = $_SESSION['user'] ?? [];
    $invoices = FinanceAllocationService::fetchInvoicesForAllocation(
        $localPdo,
        $operation['operation_type'],
        $operation['counterparty_inn'] ?? null
    );
    $routes = FinanceAllocationService::fetchRoutesForAllocation($localPdo, $user);
    $routePayments = [];
} catch (\Throwable $e) {
    $error = $e->getMessage();
}

require base_path('app/View/partials/company_finance_operation_allocate_form.php');
