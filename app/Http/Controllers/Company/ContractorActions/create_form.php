<?php
/** @var ContractorService $service */
requireRole(['company_owner', 'senior_logist', 'logist']);
$pageTitle = 'Создать перевозчика';
$pageContext = 'Перевозчики › Компания';
$isModalRequest = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest';

$companyId = $service->getCompanyId();

if ($companyId <= 0) {
    $company = null;
    $success = false;
    $errors = [];
    $old = [];
    $formError = null;
    $createdContractor = null;

    ob_start();
    require base_path('app/View/pages/company_contractors_create.php');
    $content = ob_get_clean();
    require base_path('app/View/layouts/main.php');
    return;
}

try {
    $company = $service->loadCompany($companyId);

    if (!$company) {
        $company = null;
        $success = false;
        $errors = [];
        $old = [];
        $formError = null;
        $createdContractor = null;

        ob_start();
        require base_path('app/View/pages/company_contractors_create.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
        return;
    }

    $pageContext = 'Перевозчики › Компания: ' . $company['name'];

    $success = false;
    $errors = [];
    $old = [];
    $formError = null;
    $createdContractor = null;
    $docTypes = [];

    if ($company['status'] === 'active') {
        try {
            $localPdo = $service->getLocalPdo($company);
            $docTypes = $service->getDocTypes($localPdo, 'contractor');
        } catch (\Exception $e) {
            $docTypes = [];
        }
    }
} catch (\Exception $e) {
    $company = null;
    $success = false;
    $errors = [];
    $old = [];
    $formError = 'Ошибка загрузки данных: ' . $e->getMessage();
    $createdContractor = null;
}

if ($isModalRequest) {
    header('Content-Type: text/html; charset=utf-8');
    $leEntityType = 'contractor';
    $leFormAction = '/company/contractors/create';
    $leFormId = 'le-create-form';
    $leOld = $old ?? [];
    $leErrors = $errors ?? [];
    $leFormError = $formError;
    $leDocTypes = $docTypes ?? [];
    $leContactValues = $contactValues ?? [];
    $leContactErrors = [];
    ob_start();
    require base_path('app/View/partials/legal_entity_create_form.php');
    echo ob_get_clean();
    exit;
}

ob_start();
require base_path('app/View/pages/company_contractors_create.php');
$content = ob_get_clean();
require base_path('app/View/layouts/main.php');
