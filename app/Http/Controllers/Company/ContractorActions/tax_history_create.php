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

    $taxSystem = trim($_POST['tax_system'] ?? '');
    $vatMode = trim($_POST['vat_mode'] ?? '');
    $effectiveFrom = trim($_POST['effective_from'] ?? '');
    $comment = trim($_POST['comment'] ?? '');

    $insert = $localPdo->prepare(
        'INSERT INTO contractor_tax_history (contractor_id, tax_system, vat_mode, effective_from, comment, created_by_user_id, created_by_role)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $insert->execute([$contractor_id, $taxSystem ?: null, $vatMode ?: null, $effectiveFrom ?: null, $comment ?: null, (int)$_SESSION['user_id'], $_SESSION['role_code'] ?? null]);
} catch (\Exception $e) {}

header('Location: ' . $redirect);
exit;
