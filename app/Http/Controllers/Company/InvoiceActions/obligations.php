<?php

use App\Core\Database;
use App\Service\FinanceObligationService;

requireRole(['company_owner']);
header('Content-Type: application/json; charset=utf-8');

try {
    $companyId = (int)(getSessionCompanyId() ?? 0);
    $company = $db->fetch('SELECT * FROM companies WHERE id = ?', [$companyId]);
    if (!$company || $company['status'] !== 'active') {
        echo json_encode([]); exit;
    }
    $localPdo = (new Database(companyDatabaseConfig($config, $company)))->connection();
    applyLocalMigrations($localPdo);

    $direction = strtoupper(trim((string)($_GET['direction'] ?? 'OUTGOING')));
    $counterparty = trim((string)($_GET['counterparty'] ?? ''));
    $invoiceId = (int)($_GET['invoice_id'] ?? 0);
    $type = null; $id = null;
    if ($counterparty !== '') {
        [$type, $rawId] = array_pad(explode(':', $counterparty, 2), 2, null);
        $id = (int)$rawId;
        if (!in_array($type, ['client','contractor'], true) || $id <= 0) {
            echo json_encode([]); exit;
        }
    }
    $rows = FinanceObligationService::fetchCompatibleForInvoice($localPdo, $direction, $type, $id, $invoiceId > 0 ? $invoiceId : null);
    $result = array_map(static function(array $row): array {
        $due = $row['due_date'] ?? $row['forecast_due_date'] ?? null;
        return [
            'id' => (int)$row['id'],
            'route_id' => (int)$row['source_parent_id'],
            'counterparty' => (string)$row['counterparty_name'],
            'condition' => (string)$row['condition_label'],
            'due_date' => $due,
            'amount' => (string)$row['amount'],
            'available' => (string)$row['available_to_invoice'],
            'current_amount' => (string)$row['current_invoice_link_amount'],
            'status' => (string)$row['status'],
        ];
    }, $rows);
    echo json_encode($result, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(422);
    echo json_encode(['error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
exit;
