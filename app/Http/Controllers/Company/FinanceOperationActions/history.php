<?php

use App\Core\Database;
use App\Service\FinanceOperationActionService;

requireRole(['company_owner']);

$logs = [];
$error = null;

try {
    $companyId = (int) (getSessionCompanyId() ?? 0);
    $company = $db->fetch('SELECT * FROM companies WHERE id = ?', [$companyId]);
    if (!$company || $company['status'] !== 'active') {
        throw new \RuntimeException('Компания недоступна.');
    }
    $localDb = new Database(companyDatabaseConfig($config, $company));
    $localPdo = $localDb->connection();
    $operationId = (int) $id;

    $operationStmt = $localPdo->prepare('SELECT id FROM finance_operations WHERE id = ? LIMIT 1');
    $operationStmt->execute([$operationId]);
    if ($operationStmt->fetchColumn() === false) {
        http_response_code(404);
        throw new \RuntimeException('Операция не найдена.');
    }

    $logs = FinanceOperationActionService::fetchOperationHistory($localPdo, $operationId);
} catch (\Throwable $e) {
    if (http_response_code() < 400) {
        http_response_code(500);
    }
    $error = $e instanceof \RuntimeException && !($e instanceof \PDOException)
        ? $e->getMessage()
        : 'Не удалось загрузить историю.';
}

require base_path('app/View/partials/company_finance_history_view.php');
