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

    $contactStmt = $localPdo->prepare('SELECT is_primary FROM contractor_contacts WHERE id = ? AND contractor_id = ?');
    $contactStmt->execute([$contact_id, $contractor_id]);
    $contact = $contactStmt->fetch(PDO::FETCH_ASSOC);

    $delStmt = $localPdo->prepare('DELETE FROM contractor_contacts WHERE id = ? AND contractor_id = ?');
    $delStmt->execute([$contact_id, $contractor_id]);

    if ($contact && $contact['is_primary']) {
        $first = $localPdo->prepare('SELECT id FROM contractor_contacts WHERE contractor_id = ? ORDER BY id ASC LIMIT 1');
        $first->execute([$contractor_id]);
        $firstRow = $first->fetch(PDO::FETCH_ASSOC);
        if ($firstRow) {
            $localPdo->prepare('UPDATE contractor_contacts SET is_primary = 1 WHERE id = ?')->execute([$firstRow['id']]);
        }
    }
} catch (\Exception $e) {}

header('Location: ' . $redirect);
exit;
