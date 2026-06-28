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
    $old = $_POST;
    $old['contacts'] = $_POST['contacts'] ?? clientFormDefaultContacts();
    $formError = 'Компания не найдена';

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
        $old = $_POST;
        $old['contacts'] = $_POST['contacts'] ?? clientFormDefaultContacts();
        $formError = 'Компания не найдена';

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
        $old = $_POST;
        $old['contacts'] = $_POST['contacts'] ?? clientFormDefaultContacts();
        $formError = 'Редактирование клиентов недоступно';

        ob_start();
        require base_path('app/View/pages/company_client_edit.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
        return;
    }

    $localPdo = $service->getLocalPdo($company);

    $client = $service->getClientById($localPdo, (int) $id);

    if (!$client) {
        $contacts = [];
        $errors = [];
        $old = $_POST;
        $old['contacts'] = $_POST['contacts'] ?? clientFormDefaultContacts();
        $formError = null;

        ob_start();
        require base_path('app/View/pages/company_client_edit.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
        return;
    }

    $contacts = ClientContactService::loadByClientId($localPdo, (int) $id);
    $errors = [];
    $old = $_POST;
    $old['contacts'] = $_POST['contacts'] ?? ($contacts !== [] ? $contacts : clientFormDefaultContacts());
    $formError = null;

    $contactPayload = ClientContactService::normalizeSubmittedContacts($_POST['contacts'] ?? []);
    $submittedContacts = $contactPayload['contacts'];
    if (!empty($contactPayload['errors'])) {
        $errors['contacts'] = $contactPayload['errors'];
    }

    $errors = $service->validateClient($_POST, $localPdo, (int) $id);

    if (!empty($errors)) {
        ob_start();
        require base_path('app/View/pages/company_client_edit.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
        return;
    }

    $service->updateClient($localPdo, (int) $id, $_POST, (int)$_SESSION['user_id'], $_SESSION['role_code']);

    ClientContactService::replaceForClient(
        $localPdo,
        (int) $id,
        $submittedContacts,
        (int) $_SESSION['user_id'],
        (string) ($_SESSION['role_code'] ?? '')
    );

    header('Location: /company/clients/' . $id);
    exit;
} catch (\Exception $e) {
    $company = $company ?? null;
    $client = $client ?? null;
    $contacts = $contacts ?? [];
    $errors = [];
    $old = $_POST;
    $formError = 'Ошибка сохранения: ' . $e->getMessage();

    ob_start();
    require base_path('app/View/pages/company_client_edit.php');
    $content = ob_get_clean();
    require base_path('app/View/layouts/main.php');
}
