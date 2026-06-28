<?php
/** @var ContractorService $service */
use App\Service\ContractorContactService;

requireRole(['company_owner', 'senior_logist', 'logist']);

$renderMessage = static function (string $message): void {
    echo '<div class="modal-body"><div class="notice warn" style="margin:16px">' . e($message) . '</div></div>';
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
    $old = $_POST;
    $old['contacts'] = $_POST['contacts'] ?? ($contacts !== [] ? $contacts : contractorFormDefaultContacts());
    $formError = null;

    $contactPayload = ContractorContactService::normalizeSubmittedContacts($_POST['contacts'] ?? []);
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
        $dupStmt = $localPdo->prepare('SELECT COUNT(*) FROM contractors WHERE inn = ? AND id != ?');
        $dupStmt->execute([$inn, (int) $id]);
        if ((int) $dupStmt->fetchColumn() > 0) {
            $errors['inn'] = 'ИНН уже используется';
        }
    }

    if (!empty($errors)) {
        require base_path('app/View/partials/company_contractor_modal_edit.php');
        return;
    }

    $update = $localPdo->prepare(
        'UPDATE contractors SET
            name = :name,
            inn = :inn,
            kpp = :kpp,
            ogrn = :ogrn,
            contractor_type = :contractor_type,
            legal_address = :legal_address,
            physical_address = :physical_address,
            bank_account = :bank_account,
            bank_name = :bank_name,
            bank_bik = :bank_bik,
            bank_corr_account = :bank_corr_account,
            status = :status,
            comments = :comments,
            updated_by_user_id = :updated_by_user_id,
            updated_by_role = :updated_by_role
         WHERE id = :id'
    );
    $update->execute([
        ':name' => $name,
        ':inn' => $inn,
        ':kpp' => ($kpp = trim($_POST['kpp'] ?? '')) !== '' ? $kpp : null,
        ':ogrn' => ($ogrn = trim($_POST['ogrn'] ?? '')) !== '' ? $ogrn : null,
        ':contractor_type' => ($_POST['contractor_type'] ?? '') !== '' ? $_POST['contractor_type'] : null,
        ':legal_address' => ($legalAddress = trim($_POST['legal_address'] ?? '')) !== '' ? $legalAddress : null,
        ':physical_address' => ($physicalAddress = trim($_POST['physical_address'] ?? '')) !== '' ? $physicalAddress : null,
        ':bank_account' => ($bankAccount = trim($_POST['bank_account'] ?? '')) !== '' ? $bankAccount : null,
        ':bank_name' => ($bankName = trim($_POST['bank_name'] ?? '')) !== '' ? $bankName : null,
        ':bank_bik' => ($bankBik = trim($_POST['bank_bik'] ?? '')) !== '' ? $bankBik : null,
        ':bank_corr_account' => ($bankCorr = trim($_POST['bank_corr_account'] ?? '')) !== '' ? $bankCorr : null,
        ':status' => $_POST['status'] ?? ($contractor['status'] ?? 'active'),
        ':comments' => ($comments = trim($_POST['comments'] ?? '')) !== '' ? $comments : null,
        ':updated_by_user_id' => $userId,
        ':updated_by_role' => $roleCode,
        ':id' => (int) $id,
    ]);

    ContractorContactService::replaceForContractor($localPdo, (int) $id, $submittedContacts, $userId, $roleCode);

    $contractor = $service->getContractorById($localPdo, (int) $id) ?: $contractor;
    $contacts = ContractorContactService::loadByContractorId($localPdo, (int) $id);
    $canEdit = $roleCode !== 'logist' || (int) ($contractor['created_by_user_id'] ?? 0) === $userId;
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
    $errors = [];
    $old = $_POST;
    $old['contacts'] = $_POST['contacts'] ?? contractorFormDefaultContacts();
    $contacts = $old['contacts'];
    $contractor = $contractor ?? ['id' => (int) $id, 'status' => 'active'];
    $formError = 'Ошибка сохранения: ' . $e->getMessage();
    require base_path('app/View/partials/company_contractor_modal_edit.php');
}
