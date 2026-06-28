<?php
/** @var ClientService $service */
use App\Service\ClientContactService;

requireRole(['company_owner', 'senior_logist', 'logist']);

$companyId = $service->getCompanyId();

if ($companyId <= 0) {
    $company = null;
    $client = null;
    $contacts = [];
    $errors = [];
    $old = ['contacts' => clientFormDefaultContacts()];
    $formError = null;

    ob_start();
    require base_path('app/View/pages/company_client_edit.php');
    $content = ob_get_clean();
    require base_path('app/View/layouts/main.php');
    return;
}

try {
    $company = $service->loadCompany($companyId);

    if (!$company) {
        $company = null;
        $client = null;
        $contacts = [];
        $errors = [];
        $old = ['contacts' => clientFormDefaultContacts()];
        $formError = null;

        ob_start();
        require base_path('app/View/pages/company_client_edit.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
        return;
    }

    $pageTitle = 'Редактировать клиента';
    $pageContext = 'Клиенты › Компания: ' . $company['name'];

    if ($company['status'] !== 'active') {
        $client = null;
        $contacts = [];
        $errors = [];
        $old = ['contacts' => clientFormDefaultContacts()];
        $formError = null;

        ob_start();
        require base_path('app/View/pages/company_client_edit.php');
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

    $errors = [];
    $old = $client ?: [];
    $old['contacts'] = $contacts !== [] ? $contacts : clientFormDefaultContacts();
    $formError = null;

    ob_start();
    require base_path('app/View/pages/company_client_edit.php');
    $content = ob_get_clean();
    require base_path('app/View/layouts/main.php');
} catch (\Exception $e) {
    $company = $company ?? null;
    $client = null;
    $contacts = [];
    $errors = [];
    $old = ['contacts' => clientFormDefaultContacts()];
    $formError = 'Не удалось загрузить данные: ' . $e->getMessage();

    ob_start();
    require base_path('app/View/pages/company_client_edit.php');
    $content = ob_get_clean();
    require base_path('app/View/layouts/main.php');
}
