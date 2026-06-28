<?php
/** @var ClientService $service */
use App\Service\ClientContactService;

requireRole(['company_owner', 'senior_logist', 'logist']);

$renderMessage = static function (string $message): void {
    echo '<div class="modal-body"><div class="notice warn" style="margin:16px">' . e($message) . '</div></div>';
    echo '<div class="modal-foot is-spaced"><div class="modal-foot-actions"><button type="button" class="btn btn-ghost" data-client-cancel-edit-btn>Закрыть</button></div></div>';
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
    $old = $client;
    $old['contacts'] = $contacts !== [] ? $contacts : clientFormDefaultContacts();
    $formError = null;

    require base_path('app/View/partials/company_client_modal_edit.php');
} catch (\Exception $e) {
    $renderMessage('Не удалось загрузить форму: ' . $e->getMessage());
}
