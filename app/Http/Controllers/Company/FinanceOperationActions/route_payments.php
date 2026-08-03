<?php

use App\Core\Database;
use App\Service\FinanceAllocationService;

requireRole(['company_owner']);
header('Content-Type: application/json; charset=utf-8');

try {
    $companyId = (int) (getSessionCompanyId() ?? 0);
    $company = $db->fetch('SELECT * FROM companies WHERE id = ?', [$companyId]);
    if (!$company || $company['status'] !== 'active') {
        throw new \RuntimeException('Компания недоступна.');
    }

    $routeId = (int) ($_GET['route_id'] ?? 0);
    if ($routeId <= 0) {
        echo '[]';
        exit;
    }

    $localDb = new Database(companyDatabaseConfig($config, $company));
    $payments = FinanceAllocationService::fetchRoutePaymentsForAllocation(
        $localDb->connection(),
        $routeId,
        (string) ($_GET['operation_type'] ?? 'EXPENSE')
    );

    $result = array_map(static function (array $p): array {
        $partyLabel = match ($p['party_role'] ?? '') {
            'customer' => 'Заказчик',
            'carrier' => 'Перевозчик',
            'principal' => 'Принципал',
            default => (string) ($p['party_role'] ?? ''),
        };
        return [
            'id' => (int) $p['id'],
            'label' => $partyLabel . ' · ' . FinanceAllocationService::formatAmount($p['amount'] ?? null),
        ];
    }, $payments);
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Не удалось загрузить платёжные строки.'], JSON_UNESCAPED_UNICODE);
}
