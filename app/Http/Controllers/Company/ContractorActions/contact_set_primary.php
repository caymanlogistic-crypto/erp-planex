<?php
/** @var ContractorService $service */
requireRole(['company_owner', 'senior_logist', 'logist']);
$contractor_id = (int)$contractorId; $contact_id = (int)$contactId;
$companyId = $service->getCompanyId();
$redirect = '/company/contractors/' . $contractor_id;

if ($companyId <= 0) { header('Location: ' . $redirect); exit; }

try {
    $company = $service->loadCompany($companyId);
    if (!$company || $company['status'] !== 'active') { header('Location: ' . $redirect); exit; }

    $localPdo = $service->getLocalPdo($company);

    $cStmt = $localPdo->prepare('SELECT * FROM contractors WHERE id = ?');
    $cStmt->execute([$contractor_id]);
    $contractor = $cStmt->fetch(PDO::FETCH_ASSOC);
    if (!$contractor) { header('Location: /company/contractors'); exit; }

    $isLogist = ($_SESSION['role_code'] ?? '') === 'logist';
    if ($isLogist) {
        $userId = (int)$_SESSION['user_id'];
        $hasGrant = $service->checkLogistCanEdit($localPdo, $contractor_id, $userId, (int)$contractor['created_by_user_id']);
        if (!$hasGrant) { header('Location: ' . $redirect); exit; }
    }

    $localPdo->prepare('UPDATE contractor_contacts SET is_primary = 0 WHERE contractor_id = ?')->execute([$contractor_id]);
    $localPdo->prepare('UPDATE contractor_contacts SET is_primary = 1 WHERE id = ? AND contractor_id = ?')->execute([$contact_id, $contractor_id]);
} catch (\Exception $e) {}

header('Location: ' . $redirect);
exit;
