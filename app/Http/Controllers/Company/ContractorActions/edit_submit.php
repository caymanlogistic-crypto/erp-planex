<?php
/** @var ContractorService $service */
use App\Service\ContractorContactService;

requireRole(['company_owner', 'senior_logist', 'logist']);

$companyId = $service->getCompanyId();

if ($companyId <= 0) {
    $company = null;
    $contractor = null;
    $errors = [];
    $old = $_POST;
    $formError = 'Компания не найдена';

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
        $errors = [];
        $old = $_POST;
        $formError = 'Компания не найдена';

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
        $errors = [];
        $old = $_POST;
        $formError = 'Редактирование перевозчиков недоступно';

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
        $errors = [];
        $old = $_POST;
        $formError = 'Перевозчик не найден';

        ob_start();
        require base_path('app/View/pages/company_contractor_edit.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
        return;
    }

    $isLogist = ($_SESSION['role_code'] ?? '') === 'logist';
    if ($isLogist) {
        $userId = (int)$_SESSION['user_id'];
        $hasGrant = $service->checkLogistCanEdit($localPdo, (int) $id, $userId, (int)$contractor['created_by_user_id']);
        if (!$hasGrant) {
            $errors = []; $old = $contractor;
            $formError = (int)$contractor['created_by_user_id'] !== $userId ? 'У вас есть доступ на просмотр, но нет права редактировать эту запись.' : 'У вас нет доступа к этой записи.';
            ob_start();
            require base_path('app/View/pages/company_contractor_edit.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }
    }

    $errors = [];

    $contactPayload = ContractorContactService::normalizeSubmittedContacts($_POST['contacts'] ?? []);
    $submittedContacts = $contactPayload['contacts'];
    if (!empty($contactPayload['errors'])) {
        $errors['contacts'] = $contactPayload['errors'];
    }

    $validationErrors = $service->validateContractor($_POST, $localPdo, (int) $id);
    $errors = array_merge($errors, $validationErrors);

    if (!empty($errors)) {
        $old = $_POST;
        ob_start();
        require base_path('app/View/pages/company_contractor_edit.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
        return;
    }

    $service->updateContractor($localPdo, (int) $id, $_POST, (int)$_SESSION['user_id'], $_SESSION['role_code']);

    ContractorContactService::replaceForContractor(
        $localPdo,
        (int) $id,
        $submittedContacts,
        (int) $_SESSION['user_id'],
        (string) ($_SESSION['role_code'] ?? '')
    );

    header('Location: /company/contractors/' . $id);
    exit;
} catch (\Exception $e) {
    $company = $company ?? null;
    $contractor = $contractor ?? null;
    $errors = [];
    $old = $_POST;
    $formError = 'Ошибка сохранения: ' . $e->getMessage();

    ob_start();
    require base_path('app/View/pages/company_contractor_edit.php');
    $content = ob_get_clean();
    require base_path('app/View/layouts/main.php');
}
