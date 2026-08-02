<?php
/** @var ClientService $service */
use App\Service\ClientContactService;

requireRole(['company_owner', 'senior_logist', 'logist']);

$renderMessage = static function (string $message): void {
    echo '<div class="modal-body"><div class="notice warn modal-notice">' . e($message) . '</div></div>';
    echo '<div class="modal-foot is-spaced"><div class="modal-foot-actions"><button type="button" class="btn btn-ghost" data-client-view-close-btn>Закрыть</button></div></div>';
};

$renderEditForm = static function (array $client, array $old, array $errors, ?string $formError, array $leExistingDocs, array $leDocTypes, array $leContactValues, array $leContactErrors): void {
    $leEntityType = 'client';
    $leFormAction = app_url('/company/clients/' . ((int) ($client['id'] ?? 0)) . '/modal-edit');
    $leFormId = 'client-edit-form';
    $leIsModal = true;
    $leOld = $old;
    $leErrors = $errors;
    $leFormError = $formError;
    $leSubmitLabel = 'Сохранить';
    $leShowContacts = true;
    $leShowBankDetails = true;
    $leShowDocuments = true;
    $leShowStatus = false;
    $leShowInlineActions = false;
    $leInnLookupUrl = app_url('/company/requisites/lookup-by-inn');
    $leHiddenFields = ['status' => $leOld['status'] ?? 'active'];
    require base_path('app/View/partials/company_client_modal_edit.php');
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
    $old = array_merge($client, $_POST);
    $old['contacts'] = $_POST['contacts'] ?? ($contacts !== [] ? $contacts : clientFormDefaultContacts());
    $formError = null;

    $contactPayload = ClientContactService::normalizeSubmittedContacts($_POST['contacts'] ?? []);
    $submittedContacts = $contactPayload['contacts'];
    $errors = $service->validateClient($_POST, $localPdo, (int) $id);
    if (!empty($contactPayload['errors'])) {
        $errors['contacts'] = $contactPayload['errors'];
    }

    $leDocTypes = [];
    $leExistingDocs = [];
    try {
        $service->ensureDocumentTables($localPdo);
        $leDocTypes = $service->getDocTypes($localPdo);
    } catch (\Exception $e) {
        $leDocTypes = [];
    }

    require_once base_path('app/Support/legal_entity_document_upload.php');
    try {
        $leExistingDocs = loadEntityDocuments($localPdo, (int) $id, 'client');
    } catch (\Exception $e) {
        $leExistingDocs = [];
    }

    if (!empty($errors)) {
        $leContactValues = $old['contacts'] ?? [];
        $leContactErrors = $errors['contacts'] ?? [];
        $renderEditForm($client, $old, $errors, $formError, $leExistingDocs, $leDocTypes, $leContactValues, $leContactErrors);
        return;
    }

    $totalSizeError = '';
    if (function_exists('validateTotalUploadSize')) {
        $totalSizeError = validateTotalUploadSize();
    }
    if ($totalSizeError !== '') {
        $formError = $totalSizeError;
        $leContactValues = $old['contacts'] ?? [];
        $leContactErrors = $errors['contacts'] ?? [];
        $renderEditForm($client, $old, $errors, $formError, $leExistingDocs, $leDocTypes, $leContactValues, $leContactErrors);
        return;
    }

    $customDocumentTitleError = null;
    if (function_exists('validateLegalEntityCustomDocumentTitles')) {
        $customDocumentTitleError = validateLegalEntityCustomDocumentTitles($_POST, $_FILES);
    }
    if ($customDocumentTitleError !== null) {
        $formError = $customDocumentTitleError;
        $leContactValues = $old['contacts'] ?? [];
        $leContactErrors = $errors['contacts'] ?? [];
        $renderEditForm($client, $old, $errors, $formError, $leExistingDocs, $leDocTypes, $leContactValues, $leContactErrors);
        return;
    }

    $docErrors = [];

    $deleteExistingDocs = $_POST['delete_existing_doc'] ?? [];
    foreach ($deleteExistingDocs as $docId => $val) {
        if ($val !== '1') continue;
        try {
            if (function_exists('softDeleteEntityDocument')) {
                softDeleteEntityDocument($localPdo, (int)$docId, 'client', (int)$id, $userId, 'Archived via client modal edit');
            }
        } catch (\Exception $e) {
            $docErrors[] = 'Не удалось удалить документ: ' . $e->getMessage();
        }
    }

    $existingFiles = $_FILES['existing_doc_file'] ?? [];
    if (!empty($existingFiles['name']) && is_array($existingFiles['name'])) {
        foreach ($existingFiles['name'] as $docId => $origName) {
            $uploadError = $existingFiles['error'][$docId] ?? UPLOAD_ERR_NO_FILE;
            if ($uploadError !== UPLOAD_ERR_OK || trim((string) $origName) === '') continue;
            try {
                if (function_exists('replaceEntityDocument')) {
                    replaceEntityDocument(
                        $localPdo,
                        (int)$docId,
                        (int)$id,
                        'client',
                        $companyId,
                        [
                            'name' => (string) $origName,
                            'tmp_name' => (string) ($existingFiles['tmp_name'][$docId] ?? ''),
                            'type' => (string) ($existingFiles['type'][$docId] ?? ''),
                            'size' => (int) ($existingFiles['size'][$docId] ?? 0),
                        ],
                        $userId,
                        $roleCode
                    );
                }
            } catch (\Exception $e) {
                $docErrors[] = 'Не удалось заменить документ: ' . $e->getMessage();
            }
        }
    }

    if (function_exists('processLegalEntityCreateDocuments')) {
        $docResult = processLegalEntityCreateDocuments(
            $localPdo,
            $companyId,
            'client',
            (int) $id,
            $_POST,
            $_FILES,
            $userId,
            $roleCode
        );
        if (!empty($docResult['docErrors'])) {
            $docErrors = array_merge($docErrors, $docResult['docErrors']);
        }
    }

    if (!empty($docErrors)) {
        $formError = 'Ошибка при обработке документов:<br>' . implode('<br>', array_map('htmlspecialchars', $docErrors));
        try {
            $service->ensureDocumentTables($localPdo);
            $leDocTypes = $service->getDocTypes($localPdo);
        } catch (\Exception $e) {
            $leDocTypes = [];
        }
        try {
            $leExistingDocs = loadEntityDocuments($localPdo, (int) $id, 'client');
        } catch (\Exception $e) {
            $leExistingDocs = [];
        }
        $leContactValues = $old['contacts'] ?? [];
        $leContactErrors = $errors['contacts'] ?? [];
        $renderEditForm($client, $old, $errors, $formError, $leExistingDocs, $leDocTypes, $leContactValues, $leContactErrors);
        return;
    }

    $service->updateClient($localPdo, (int) $id, $_POST, $userId, $roleCode);

    ClientContactService::replaceForClient($localPdo, (int) $id, $submittedContacts, $userId, $roleCode);

    $client = $service->getClientById($localPdo, (int) $id);
    $contacts = ClientContactService::loadByClientId($localPdo, (int) $id);
    $canEdit = $roleCode !== 'logist' || (int) ($client['created_by_user_id'] ?? 0) === $userId;
    $canArchive = $roleCode === 'company_owner' || $roleCode === 'senior_logist' || ($roleCode === 'logist' && (int) ($client['created_by_user_id'] ?? 0) === $userId);
    $archiveBlockedMessage = '';
    $clientDocuments = [];
    try {
        require_once base_path('app/Support/legal_entity_document_upload.php');
        if (function_exists('loadEntityDocuments')) {
            $clientDocuments = loadEntityDocuments($localPdo, (int) $client['id'], 'client');
        }
    } catch (\Exception $e) {
        $clientDocuments = [];
    }
    require base_path('app/View/partials/company_client_modal_view.php');
} catch (\Exception $e) {
    $errors = [];
    $old = $_POST;
    $old['contacts'] = $_POST['contacts'] ?? clientFormDefaultContacts();
    $contacts = $old['contacts'];
    $client = $client ?? ['id' => (int) $id, 'status' => 'active'];
    $formError = 'Ошибка сохранения: ' . $e->getMessage();

    $leDocTypes = [];
    $leExistingDocs = [];
    try {
        $service->ensureDocumentTables($localPdo ?? null);
        $leDocTypes = $service->getDocTypes($localPdo ?? null);
    } catch (\Exception $ex) {
        $leDocTypes = [];
    }
    try {
        if (function_exists('loadEntityDocuments') && isset($localPdo)) {
            $leExistingDocs = loadEntityDocuments($localPdo, (int) $id, 'client');
        }
    } catch (\Exception $ex) {
        $leExistingDocs = [];
    }

    $leContactValues = $old['contacts'] ?? [];
    $leContactErrors = $errors['contacts'] ?? [];
    $renderEditForm($client, $old, $errors, $formError, $leExistingDocs, $leDocTypes, $leContactValues, $leContactErrors);
}
