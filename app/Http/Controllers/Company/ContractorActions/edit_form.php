<?php
/** @var ContractorService $service */
use App\Service\ContractorContactService;

requireRole(['company_owner', 'senior_logist', 'logist']);

$companyId = $service->getCompanyId();

if ($companyId <= 0) {
    $company = null;
    $contractor = null;
    $contacts = [];
    $errors = [];
    $old = ['contacts' => contractorFormDefaultContacts()];
    $formError = null;

    ob_start();
    require base_path('app/View/pages/company_contractor_edit.php');
    $content = ob_get_clean();
    require base_path('app/View/layouts/main.php');
    return;
}

try {
    $company = $service->loadCompany($companyId);

    if (!$company) {
        $company = null;
        $contractor = null;
        $contacts = [];
        $errors = [];
        $old = ['contacts' => contractorFormDefaultContacts()];
        $formError = null;

        ob_start();
        require base_path('app/View/pages/company_contractor_edit.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
        return;
    }

    $pageTitle = 'Редактировать перевозчика';
    $pageContext = 'Перевозчики › Компания: ' . $company['name'];

    if ($company['status'] !== 'active') {
        $contractor = null;
        $contacts = [];
        $errors = [];
        $old = ['contacts' => contractorFormDefaultContacts()];
        $formError = null;

        ob_start();
        require base_path('app/View/pages/company_contractor_edit.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
        return;
    }

    $localPdo = $service->getLocalPdo($company);

    $contractor = $service->getContractorById($localPdo, (int) $id);

    if (!$contractor) {
        $contractor = null;
        $contacts = [];
        $errors = [];
        $old = ['contacts' => contractorFormDefaultContacts()];
        $formError = null;

        ob_start();
        require base_path('app/View/pages/company_contractor_edit.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
        return;
    }

    if (($_SESSION['role_code'] ?? '') === 'logist' && !$service->checkLogistCanEdit(
        $localPdo,
        (int) $id,
        (int) ($_SESSION['user_id'] ?? 0),
        (int) ($contractor['created_by_user_id'] ?? 0)
    )) {
        denyEntityAccess();
    }

    $contacts = ContractorContactService::loadByContractorId($localPdo, (int) $id);
    $errors = [];
    $old = $contractor;
    $old['contacts'] = $contacts !== [] ? $contacts : contractorFormDefaultContacts();
    $formError = null;

    ob_start();
    require base_path('app/View/pages/company_contractor_edit.php');
    $content = ob_get_clean();
    require base_path('app/View/layouts/main.php');
} catch (\Exception $e) {
    $company = $company ?? null;
    $contractor = null;
    $errors = [];
    $old = [];
    $formError = 'Не удалось загрузить перевозчика: ' . $e->getMessage();

    ob_start();
    require base_path('app/View/pages/company_contractor_edit.php');
    $content = ob_get_clean();
    require base_path('app/View/layouts/main.php');
}
