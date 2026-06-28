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

    $contactPerson = trim($_POST['contact_person'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $comment = trim($_POST['comment'] ?? '');

    $update = $localPdo->prepare(
        'UPDATE contractor_contacts SET contact_person = ?, phone = ?, email = ?, comment = ?, updated_by_user_id = ?, updated_by_role = ? WHERE id = ? AND contractor_id = ?'
    );
    $update->execute([$contactPerson ?: null, $phone ?: null, $email ?: null, $comment ?: null, (int)$_SESSION['user_id'], $_SESSION['role_code'] ?? null, $contact_id, $contractor_id]);
} catch (\Exception $e) {}

header('Location: ' . $redirect);
exit;
