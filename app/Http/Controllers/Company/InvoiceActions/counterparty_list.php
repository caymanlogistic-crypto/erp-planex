<?php

use App\Core\Database;
use App\Service\FinanceInvoiceService;

requireRole(['company_owner']);

header('Content-Type: application/json; charset=utf-8');

try {
    $companyId = (int)(getSessionCompanyId() ?? 0);
    $company = $db->fetch('SELECT * FROM companies WHERE id = ?', [$companyId]);

    if (!$company || $company['status'] !== 'active') {
        echo json_encode([]);
        exit;
    }

    $localConfig = companyDatabaseConfig($config, $company);
    $localDb = new Database($localConfig);
    $localPdo = $localDb->connection();
    applyLocalMigrations($localPdo);

    $type = $_GET['type'] ?? '';

    if ($type === 'client') {
        $items = FinanceInvoiceService::fetchClientsForSelect($localPdo);
    } elseif ($type === 'contractor') {
        $items = FinanceInvoiceService::fetchContractorsForSelect($localPdo);
    } else {
        $items = [];
    }

    $result = array_map(static function (array $item): array {
        return [
            'id' => (int) $item['id'],
            'name' => $item['name'],
            'inn' => $item['inn'] ?? '',
        ];
    }, $items);

    echo json_encode($result, JSON_UNESCAPED_UNICODE);
} catch (\Throwable $e) {
    echo json_encode([]);
}
exit;
