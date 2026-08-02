<?php

use App\Core\Database;
use App\Service\FinanceAllocationService;

requireRole(['company_owner']);

header('Content-Type: application/json; charset=utf-8');

try {
    $companyId = $_SESSION['company_id'] ?? 0;
    $company = $db->fetch('SELECT * FROM companies WHERE id = ?', [$companyId]);

    if (!$company || $company['status'] !== 'active') {
        echo json_encode([]);
        exit;
    }

    $localConfig = companyDatabaseConfig($config, $company);
    $localDb = new Database($localConfig);
    $localPdo = $localDb->connection();
    applyLocalMigrations($localPdo);

    $routeId = (int) ($_GET['route_id'] ?? 0);
    $operationType = (string) ($_GET['operation_type'] ?? 'EXPENSE');
    if ($routeId <= 0) {
        echo json_encode([]);
        exit;
    }

    $payments = FinanceAllocationService::fetchRoutePaymentsForAllocation($localPdo, $routeId, $operationType);

    $result = array_map(static function (array $p): array {
        $partyLabel = match ($p['party_role'] ?? '') {
            'customer' => 'Заказчик',
            'carrier' => 'Перевозчик',
            'principal' => 'Принципал',
            default => $p['party_role'] ?? '',
        };
        return [
            'id' => (int) $p['id'],
            'label' => $partyLabel . ' · ' . FinanceAllocationService::formatAmount($p['amount']),
        ];
    }, $payments);

    echo json_encode($result, JSON_UNESCAPED_UNICODE);
} catch (\Throwable $e) {
    echo json_encode([]);
}
exit;
