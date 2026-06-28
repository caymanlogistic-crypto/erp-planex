<?php
/** @var ContractorService $service */
requireRole(['company_owner', 'senior_logist', 'logist']);
$contractor_id = (int)$contractorId;
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

    $cntStmt = $localPdo->prepare('SELECT COUNT(*) FROM contractor_contacts WHERE contractor_id = ?');
    $cntStmt->execute([$contractor_id]);
    $isFirst = ($cntStmt->fetchColumn() == 0);

    $isPrimary = $isFirst ? 1 : 0;
    $isDocEmail = ($isFirst && !empty($email)) ? 1 : 0;

    $insert = $localPdo->prepare(
        'INSERT INTO contractor_contacts (contractor_id, contact_person, phone, email, is_primary, is_document_email, comment, created_by_user_id, created_by_role)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $insert->execute([$contractor_id, $contactPerson ?: null, $phone ?: null, $email ?: null, $isPrimary, $isDocEmail, $comment ?: null, (int)$_SESSION['user_id'], $_SESSION['role_code'] ?? null]);
} catch (\Exception $e) {}

header('Location: ' . $redirect);
exit;
