<?php
/** @var ContractorService $service */
use App\Service\ContractorContactService;

requireRole(['company_owner', 'senior_logist', 'logist']);

$renderMessage = static function (string $message): void {
    echo '<div class="modal-body"><div class="notice warn" style="margin:16px">' . e($message) . '</div></div>';
    echo '<div class="modal-foot is-spaced"><div class="modal-foot-actions"><button type="button" class="btn btn-ghost" data-contractor-cancel-edit-btn>Закрыть</button></div></div>';
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

    $contractor = $service->getContractorById($localPdo, (int) $id);
    if (!$contractor) {
        $renderMessage('Перевозчик не найден.');
        return;
    }

    $roleCode = (string) ($_SESSION['role_code'] ?? '');
    $userId = (int) ($_SESSION['user_id'] ?? 0);
    if ($roleCode === 'logist') {
        $grantStmt = $localPdo->prepare("SELECT access_level FROM entity_access_grants WHERE entity_type = 'contractor' AND entity_id = ? AND granted_to_user_id = ? AND access_level = 'edit' AND revoked_at IS NULL LIMIT 1");
        $grantStmt->execute([(int) $id, $userId]);
        $canEditGrant = $grantStmt->fetchColumn() ?: null;
        if ((int) ($contractor['created_by_user_id'] ?? 0) !== $userId && !$canEditGrant) {
            $renderMessage('У вас нет прав на редактирование этой записи.');
            return;
        }
    }

    $contacts = ContractorContactService::loadByContractorId($localPdo, (int) $id);
    $errors = [];
    $old = $contractor;
    $old['contacts'] = $contacts !== [] ? $contacts : contractorFormDefaultContacts();
    $formError = null;

    require base_path('app/View/partials/company_contractor_modal_edit.php');
} catch (\Exception $e) {
    $renderMessage('Не удалось загрузить форму: ' . $e->getMessage());
}
