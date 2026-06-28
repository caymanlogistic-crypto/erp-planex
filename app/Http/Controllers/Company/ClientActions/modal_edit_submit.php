<?php
/** @var ClientService $service */
use App\Service\ClientContactService;

requireRole(['company_owner', 'senior_logist', 'logist']);

$renderMessage = static function (string $message): void {
    echo '<div class="modal-body"><div class="notice warn" style="margin:16px">' . e($message) . '</div></div>';
    echo '<div class="modal-foot is-spaced"><div class="modal-foot-actions"><button type="button" class="btn btn-ghost" data-client-view-close-btn>Закрыть</button></div></div>';
};

$companyId = $service->getCompanyId();
if ($companyId <= 0) {
    $renderMessage('Компания не найдена.');
    return;
}

try {
    $company = $service->loadCompany($companyId);
    if (!$company || ($company['status'] ?? '') !== 'active') {
        $renderMessage('Компания недоступна.');
        return;
    }

    $localPdo = $service->getLocalPdo($company);

    $client = $service->getClientById($localPdo, (int) $id);
    if (!$client) {
        $renderMessage('Клиент не найден.');
        return;
    }

    $roleCode = (string) ($_SESSION['role_code'] ?? '');
    $userId = (int) ($_SESSION['user_id'] ?? 0);
    if ($roleCode === 'logist') {
        $canEditGrant = $service->checkLogistAccess($localPdo, (int) $id, $userId, 'edit');
        if ((int) ($client['created_by_user_id'] ?? 0) !== $userId && !$canEditGrant) {
            $renderMessage('У вас нет прав на редактирование этой записи.');
            return;
        }
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

    $name = trim($_POST['name'] ?? '');
    $inn = trim($_POST['inn'] ?? '');
    if ($name === '') {
        $errors['name'] = 'Обязательное поле';
    }
    if ($inn === '') {
        $errors['inn'] = 'Обязательное поле';
    } else {
        $dupStmt = $localPdo->prepare('SELECT COUNT(*) FROM clients WHERE inn = ? AND id != ?');
        $dupStmt->execute([$inn, (int) $id]);
        if ((int) $dupStmt->fetchColumn() > 0) {
            $errors['inn'] = 'ИНН уже используется в этой компании';
        }
    }

    if (!empty($errors)) {
        require base_path('app/View/partials/company_client_modal_edit.php');
        return;
    }

    $service->updateClient($localPdo, (int) $id, $_POST, $userId, $roleCode);

    ClientContactService::replaceForClient($localPdo, (int) $id, $submittedContacts, $userId, $roleCode);

    $client = $service->getClientById($localPdo, (int) $id);
    $contacts = ClientContactService::loadByClientId($localPdo, (int) $id);
    $canEdit = $roleCode !== 'logist' || (int) ($client['created_by_user_id'] ?? 0) === $userId;
    $canArchive = $roleCode === 'company_owner' || $roleCode === 'senior_logist' || ($roleCode === 'logist' && (int) ($client['created_by_user_id'] ?? 0) === $userId);
    $archiveBlockedMessage = '';
    require base_path('app/View/partials/company_client_modal_view.php');
} catch (\Exception $e) {
    $errors = [];
    $old = $_POST;
    $old['contacts'] = $_POST['contacts'] ?? clientFormDefaultContacts();
    $contacts = $old['contacts'];
    $client = $client ?? ['id' => (int) $id, 'status' => 'active'];
    $formError = 'Ошибка сохранения: ' . $e->getMessage();
    require base_path('app/View/partials/company_client_modal_edit.php');
}
