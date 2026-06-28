<?php
/** @var ClientService $service */
use App\Service\ClientContactService;

requireRole(['company_owner', 'senior_logist', 'logist']);

$companyId = $service->getCompanyId();
$grants = [];
$logists = [];

if ($companyId <= 0) {
    $company = null;
    $client = null;
    $dbError = null;

    ob_start();
    require base_path('app/View/pages/company_client_view.php');
    $content = ob_get_clean();
    require base_path('app/View/layouts/main.php');
    return;
}

try {
    $company = $service->loadCompany($companyId);

    if (!$company) {
        $company = null;
        $client = null;
        $dbError = null;

        ob_start();
        require base_path('app/View/pages/company_client_view.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
        return;
    }

    $pageTitle = 'Клиент';
    $pageContext = 'Клиенты › Компания: ' . $company['name'];

    if ($company['status'] !== 'active') {
        $client = null;
        $dbError = null;

        ob_start();
        require base_path('app/View/pages/company_client_view.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
        return;
    }

    $localPdo = $service->getLocalPdo($company);

    $client = $service->getClientById($localPdo, (int) $id);

    $contacts = [];
    if ($client) {
        try {
            $contacts = ClientContactService::loadByClientId($localPdo, (int) $client['id']);
        } catch (\Exception $e) {
            $contacts = [];
        }
    }

    $pageTitle = $client ? 'Клиент: ' . $client['name'] : 'Клиент';

    $grants = [];
    $logists = [];
    if (($_SESSION['role_code'] ?? '') === 'company_owner') {
        $grants = $service->getGrants($localPdo, (int)$id);
        $logists = $service->getLogists($localPdo);
    }

    $dbError = null;

    ob_start();
    require base_path('app/View/pages/company_client_view.php');
    $content = ob_get_clean();
    require base_path('app/View/layouts/main.php');
} catch (\Exception $e) {
    $company = $company ?? null;
    $client = null;
    $grants = [];
    $logists = [];
    $dbError = 'Не удалось загрузить данные: ' . $e->getMessage();

    ob_start();
    require base_path('app/View/pages/company_client_view.php');
    $content = ob_get_clean();
    require base_path('app/View/layouts/main.php');
}
