<?php
/** @var ClientService $service */
use App\Service\ClientContactService;

requireRole(['company_owner', 'senior_logist', 'logist']);

$renderMessage = static function (string $message): void {
    echo '<div class="modal-body"><div class="notice warn modal-notice">' . e($message) . '</div></div>';
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
    $grantLevel = null;
    if ($roleCode === 'logist') {
        $grantLevel = $service->checkLogistAccess($localPdo, (int) $id, $userId);
        if ((int) ($client['created_by_user_id'] ?? 0) !== $userId && !$grantLevel) {
            $renderMessage('У вас нет доступа к этой записи.');
            return;
        }
    }

    $contacts = [];
    try {
        $contacts = ClientContactService::loadByClientId($localPdo, (int) $client['id']);
    } catch (\Exception $e) {
        $contacts = [];
    }

    $clientDocuments = [];
    try {
        require_once base_path('app/Support/legal_entity_document_upload.php');
        if (function_exists('loadEntityDocuments')) {
            $clientDocuments = loadEntityDocuments($localPdo, (int) $client['id'], 'client');
        }
    } catch (\Exception $e) {
        $clientDocuments = [];
    }

    $canEdit = $roleCode !== 'logist' || (int) ($client['created_by_user_id'] ?? 0) === $userId || $grantLevel === 'edit';
    $canArchive = $roleCode === 'company_owner' || $roleCode === 'senior_logist' || ($roleCode === 'logist' && (int) ($client['created_by_user_id'] ?? 0) === $userId);
    $archiveBlockedMessage = '';

    require base_path('app/View/partials/company_client_modal_view.php');
} catch (\Exception $e) {
    $renderMessage('Не удалось загрузить клиента: ' . $e->getMessage());
}
