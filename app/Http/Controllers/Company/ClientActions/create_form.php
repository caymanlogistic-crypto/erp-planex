<?php
/** @var ClientService $service */
requireRole(['company_owner', 'senior_logist', 'logist']);
$pageTitle = 'Создать клиента';
$pageContext = 'Клиенты › Компания';
$isModalRequest = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest';

$companyId = $service->getCompanyId();

if ($companyId <= 0) {
    $company = null;
    $success = false;
    $errors = [];
    $old = [];
    $formError = null;
    $createdClient = null;

    ob_start();
    require base_path('app/View/pages/company_clients_create.php');
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
        $createdClient = null;

        ob_start();
        require base_path('app/View/pages/company_clients_create.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
        return;
    }

    $pageContext = 'Клиенты › Компания: ' . $company['name'];

    $success = false;
    $errors = [];
    $old = [];
    $formError = null;
    $createdClient = null;
    $docTypes = [];

    if ($company['status'] === 'active') {
        try {
            $localPdo = $service->getLocalPdo($company);
            $docTypes = $service->getDocTypes($localPdo);
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
    $createdClient = null;
}

if ($isModalRequest) {
    header('Content-Type: text/html; charset=utf-8');
    $leEntityType = 'client';
    $leFormAction = '/company/clients/create';
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
require base_path('app/View/pages/company_clients_create.php');
$content = ob_get_clean();
require base_path('app/View/layouts/main.php');
