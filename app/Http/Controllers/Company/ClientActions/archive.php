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

    $service->archiveClient($localPdo, (int) $id);

    header('Location: /company/clients');
    exit;
} catch (\Exception $e) {
    header('Location: /company/clients');
    exit;
}
