<?php
/** @var ClientService $service */
requireRole(['company_owner', 'senior_logist', 'logist']);
header('Content-Type: application/json; charset=UTF-8');

try {
    $companyId = $service->getCompanyId();
    if ($companyId <= 0) {
        throw new \RuntimeException('Компания не найдена.');
    }

    $company = $service->loadCompany($companyId);
    if (!$company || ($company['status'] ?? '') !== 'active') {
        throw new \RuntimeException('Компания недоступна.');
    }

    $localPdo = $service->getLocalPdo($company);

    $client = $service->getClientById($localPdo, (int) $id);
    if (!$client) {
        throw new \RuntimeException('Клиент не найден.');
    }

    $roleCode = (string) ($_SESSION['role_code'] ?? '');
    $userId = (int) ($_SESSION['user_id'] ?? 0);
    if ($roleCode === 'logist' && (int) ($client['created_by_user_id'] ?? 0) !== $userId) {
        throw new \RuntimeException('Логист может архивировать только записи, созданные им самим.');
    }

    $service->archiveClient($localPdo, (int) $id);

    $displayName = $client['name'] ?? $client['full_name'] ?? '#' . $id;
    $snapshot = json_encode($client, JSON_UNESCAPED_UNICODE);
    $userName = $_SESSION['user_name'] ?? '';
    try {
        $centralPdo = $db->connection();
        \App\Service\AuditService::recordDeletion($centralPdo, $company, 'client', (int)$id, 'clients', $displayName, $userId, $roleCode, $userName, null, $snapshot);
    } catch (\Exception $auditEx) {
        error_log('Audit record failed for client ' . $id . ': ' . $auditEx->getMessage());
    }

    echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
} catch (\Throwable $e) {
    http_response_code(200);
    echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
