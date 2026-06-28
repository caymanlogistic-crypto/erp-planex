<?php
/** @var ClientService $service */
requireRole(['company_owner', 'senior_logist', 'logist']);
$pageTitle = 'Клиенты';
$pageContext = 'Клиенты › Компания';

$companyId = $service->getCompanyId();

if ($companyId <= 0) {
    $company = null;
    $clients = [];
    $dbError = null;

    ob_start();
    require base_path('app/View/pages/company_clients.php');
    $content = ob_get_clean();
    require base_path('app/View/layouts/main.php');
    return;
}

try {
    $company = $service->loadCompany($companyId);

    if (!$company) {
        $company = null;
        $clients = [];
        $dbError = null;

        ob_start();
        require base_path('app/View/pages/company_clients.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
        return;
    }

    $pageContext = 'Клиенты › Компания: ' . $company['name'];

    if ($company['status'] !== 'active') {
        $clients = [];
        $dbError = null;

        ob_start();
        require base_path('app/View/pages/company_clients.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
        return;
    }

    $localPdo = $service->getLocalPdo($company);

    $isLogist = ($_SESSION['role_code'] ?? '') === 'logist';
    $clients = $service->listClients($localPdo, $isLogist, (int)$_SESSION['user_id']);
    $dbError = null;
} catch (\Exception $e) {
    $company = $company ?? null;
    $clients = [];
    $dbError = 'Не удалось подключиться к базе данных компании.';
}

ob_start();
require base_path('app/View/pages/company_clients.php');
$content = ob_get_clean();
require base_path('app/View/layouts/main.php');
