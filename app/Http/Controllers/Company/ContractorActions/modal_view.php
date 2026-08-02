<?php
/** @var ContractorService $service */
use App\Service\ContractorContactService;

requireRole(['company_owner', 'senior_logist', 'logist']);

$renderMessage = static function (string $message): void {
    echo '<div class="modal-body"><div class="notice warn modal-notice">' . e($message) . '</div></div>';
    echo '<div class="modal-foot is-spaced"><div class="modal-foot-actions"><button type="button" class="btn btn-ghost" data-contractor-view-close-btn>Закрыть</button></div></div>';
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
    $grantLevel = null;
    if ($roleCode === 'logist') {
        $grantStmt = $localPdo->prepare("SELECT access_level FROM entity_access_grants WHERE entity_type = 'contractor' AND entity_id = ? AND granted_to_user_id = ? AND access_level IN ('view','edit') AND revoked_at IS NULL LIMIT 1");
        $grantStmt->execute([(int) $id, $userId]);
        $grantLevel = $grantStmt->fetchColumn() ?: null;
        if ((int) ($contractor['created_by_user_id'] ?? 0) !== $userId && !$grantLevel) {
            $renderMessage('У вас нет доступа к этой записи.');
            return;
        }
    }

    $contacts = ContractorContactService::loadByContractorId($localPdo, (int) $id);
    $canEdit = $roleCode !== 'logist' || (int) ($contractor['created_by_user_id'] ?? 0) === $userId || $grantLevel === 'edit';
    $canArchive = $roleCode === 'company_owner' || $roleCode === 'senior_logist' || ($roleCode === 'logist' && (int) ($contractor['created_by_user_id'] ?? 0) === $userId);
    $archiveBlockedMessage = '';
    try {
        $localPdo->query("SELECT 1 FROM crews LIMIT 1")->fetch();
        $crewCheck = $localPdo->prepare("SELECT COUNT(*) FROM crews WHERE contractor_id = ? AND status != 'archived'");
        $crewCheck->execute([(int) $id]);
        if ((int) $crewCheck->fetchColumn() > 0) {
            $archiveBlockedMessage = 'Перевозчик участвует в экипажах. Сначала удалите экипажи.';
            $canArchive = false;
        }
    } catch (\Exception $e) {
    }

    require base_path('app/View/partials/company_contractor_modal_view.php');
} catch (\Exception $e) {
    $renderMessage('Не удалось загрузить перевозчика: ' . $e->getMessage());
}
