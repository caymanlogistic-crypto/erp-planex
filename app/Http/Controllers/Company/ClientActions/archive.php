<?php
/** @var ClientService $service */
requireRole(['company_owner', 'senior_logist', 'logist']);

$companyId = $service->getCompanyId();

if ($companyId <= 0) {
    header('Location: /company/clients');
    exit;
}

try {
    $company = $service->loadCompany($companyId);

    if (!$company || $company['status'] !== 'active') {
        header('Location: /company/clients');
        exit;
    }

    $localPdo = $service->getLocalPdo($company);

    $client = $service->getClientById($localPdo, (int) $id);
    if ($client) {
        $service->archiveClient($localPdo, (int) $id);
        $displayName = $client['name'] ?? $client['full_name'] ?? '#' . $id;
        $snapshot = json_encode($client, JSON_UNESCAPED_UNICODE);
        $userId = (int)($_SESSION['user_id'] ?? 0);
        $role = (string)($_SESSION['role_code'] ?? '');
        $userName = $_SESSION['user_name'] ?? '';
        try {
            $centralPdo = $db->connection();
            \App\Service\AuditService::recordDeletion($centralPdo, $company, 'client', (int)$id, 'clients', $displayName, $userId, $role, $userName, null, $snapshot);
        } catch (\Exception $auditEx) {
            error_log('Audit record failed for client ' . $id . ': ' . $auditEx->getMessage());
        }
    }

    header('Location: /company/clients');
    exit;
} catch (\Exception $e) {
    header('Location: /company/clients');
    exit;
}
