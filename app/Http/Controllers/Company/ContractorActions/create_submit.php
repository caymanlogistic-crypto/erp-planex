<?php
/** @var ContractorService $service */
use App\Service\ContractorContactService;

requireRole(['company_owner', 'senior_logist', 'logist']);
$pageTitle = 'Создать перевозчика';
$pageContext = 'Перевозчики › Компания';
$isModalRequest = !empty($_POST['is_modal']);

$companyId = $service->getCompanyId();
$errors = [];
$old = $_POST;
$formError = null;
$success = false;
$createdContractor = null;
$docErrors = [];
$uploadedDocs = [];

$renderContractorCreateResponse = static function () use (
    &$isModalRequest,
    &$success,
    &$docTypes,
    &$localPdo,
    &$service,
    &$old,
    &$errors,
    &$formError,
    &$submittedContacts,
    &$company,
    &$content
): void {
    if ($isModalRequest) {
        header('Content-Type: text/html; charset=utf-8');
        if ($success) {
            echo '<div data-le-create-success="1"></div>';
            return;
        }

        if (empty($docTypes) && !empty($localPdo)) {
            $docTypes = $service->getDocTypes($localPdo, 'contractor');
        }
        $leEntityType = 'contractor';
        $leFormAction = '/company/contractors/create';
        $leFormId = 'le-create-form';
        $leOld = $old;
        $leErrors = $errors;
        $leFormError = $formError;
        $leDocTypes = $docTypes ?? [];
        $leContactValues = $submittedContacts ?? ($old['contacts'] ?? []);
        $leContactErrors = $errors['contacts'] ?? [];
        ob_start();
        require base_path('app/View/partials/legal_entity_create_form.php');
        echo ob_get_clean();
        return;
    }

    ob_start();
    require base_path('app/View/pages/company_contractors_create.php');
    $content = ob_get_clean();
    require base_path('app/View/layouts/main.php');
};

if ($companyId <= 0) {
    $company = null;
    $formError = 'Компания не найдена';
    $renderContractorCreateResponse();
    return;
}

try {
    $company = $service->loadCompany($companyId);

    if (!$company) {
        $company = null;
        $formError = 'Компания не найдена';
        $renderContractorCreateResponse();
        return;
    }

    $pageContext = 'Перевозчики › Компания: ' . $company['name'];

    if ($company['status'] !== 'active') {
        $formError = 'Создание перевозчиков недоступно';
        $renderContractorCreateResponse();
        return;
    }

    $localPdo = $service->getLocalPdo($company);

    if (isPostTruncated()) {
        $formError = 'Общий размер отправки превышает серверный лимит. Для ERP требуется настройка post_max_size не менее 100M. Уменьшите количество файлов или обратитесь к администратору.';
        $renderContractorCreateResponse();
        return;
    }

    $name = trim($_POST['name'] ?? '');
    $inn = trim($_POST['inn'] ?? '');
    $kpp = trim($_POST['kpp'] ?? '');
    $ogrn = trim($_POST['ogrn'] ?? '');
    $legalAddress = trim($_POST['legal_address'] ?? '');
    $physicalAddress = trim($_POST['physical_address'] ?? '');
    $comments = trim($_POST['comments'] ?? '');
    $directorFullName = trim($_POST['director_full_name'] ?? '');
    $directorPosition = trim($_POST['director_position'] ?? '');

    $contactPayload = ContractorContactService::normalizeSubmittedContacts($_POST['contacts'] ?? []);
    $submittedContacts = $contactPayload['contacts'];
    if (!empty($contactPayload['errors'])) {
        $errors['contacts'] = $contactPayload['errors'];
    }

    $errors = $service->validateContractor($_POST, $localPdo);

    if (!empty($errors)) {
        $renderContractorCreateResponse();
        return;
    }

    $totalSizeError = validateTotalUploadSize();
    if ($totalSizeError !== '') {
        $formError = $totalSizeError;
        $renderContractorCreateResponse();
        return;
    }

    $legalEntityFiles = $_FILES;
    $customDocumentTitleError = validateLegalEntityCustomDocumentTitles($_POST, $legalEntityFiles);
    if ($customDocumentTitleError !== null) {
        $formError = $customDocumentTitleError;
        $renderContractorCreateResponse();
        return;
    }

    $documentFileError = validateLegalEntityCreateDocumentFiles($legalEntityFiles);
    if ($documentFileError !== null) {
        $formError = $documentFileError;
        $renderContractorCreateResponse();
        return;
    }

    $newContractorId = $service->createContractor($localPdo, $_POST, (int)$_SESSION['user_id'], $_SESSION['role_code']);

    ContractorContactService::replaceForContractor(
        $localPdo,
        $newContractorId,
        $submittedContacts,
        (int) $_SESSION['user_id'],
        (string) ($_SESSION['role_code'] ?? '')
    );

    $createdContractor = [
        'id'              => $newContractorId,
        'name'            => $name,
        'inn'             => $inn,
        'contractor_type' => ($_POST['contractor_type'] ?? '') !== '' ? $_POST['contractor_type'] : null,
    ];

    $documentUploadResult = processLegalEntityCreateDocuments(
        $localPdo,
        $companyId,
        'contractor',
        $newContractorId,
        $_POST,
        $legalEntityFiles,
        (int) $_SESSION['user_id'],
        (string) ($_SESSION['role_code'] ?? '')
    );
    $docErrors = $documentUploadResult['docErrors'];
    $uploadedDocs = $documentUploadResult['uploadedDocs'];
    $_FILES['predef_doc']['name'] = [];
    $_FILES['custom_doc_file']['name'] = [];

    $entityType = 'contractor';
    $service->ensureDocumentTables($localPdo);
    $allowedExt = ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'doc', 'docx', 'xls', 'xlsx']; $maxSize = 20 * 1024 * 1024;
    if (!empty($_FILES['predef_doc']['name']) && is_array($_FILES['predef_doc']['name'])) {
        foreach ($_FILES['predef_doc']['name'] as $code => $origName) {
            $fe = $_FILES['predef_doc']['error'][$code] ?? UPLOAD_ERR_NO_FILE; if ($fe !== UPLOAD_ERR_OK || trim((string)$origName) === '') continue;
            $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION)); $fs = $_FILES['predef_doc']['size'][$code];
            if (!in_array($ext, $allowedExt, true)) { $docErrors[] = 'Предопределённый документ «' . ($_POST['predef_doc_type'][$code] ?? $code) . '»: недопустимый формат'; continue; }
            if ($fs > $maxSize) { $docErrors[] = 'Предопределённый документ «' . ($_POST['predef_doc_type'][$code] ?? $code) . '»: размер > 20 МБ'; continue; }
            if (strpos($origName, '../') !== false || strpos($origName, '..\\') !== false || strpos($origName, '/') !== false || strpos($origName, '\\') !== false) { $docErrors[] = 'Предопределённый документ «' . ($_POST['predef_doc_type'][$code] ?? $code) . '»: недопустимое имя'; continue; }
            try {
                $storedName = uniqid('doc_', true) . '.' . $ext; $relativeDir = 'companies/' . $companyId . '/documents/' . $entityType . '/' . $newContractorId; $absoluteDir = storage_path($relativeDir);
                if (!is_dir($absoluteDir)) mkdir($absoluteDir, 0755, true);
                if (!move_uploaded_file($_FILES['predef_doc']['tmp_name'][$code], $absoluteDir . DIRECTORY_SEPARATOR . $storedName)) { $docErrors[] = 'Предопределённый документ «' . ($_POST['predef_doc_type'][$code] ?? $code) . '»: не удалось сохранить'; continue; }
                $docTypeName = $_POST['predef_doc_type'][$code] ?? ''; $mime = $_FILES['predef_doc']['type'][$code]; $dtId = null;
                if ($docTypeName !== '') { $dts = $localPdo->prepare("SELECT id FROM document_types WHERE name = ? AND entity_type = ? LIMIT 1"); $dts->execute([$docTypeName, $entityType]); $dtId = $dts->fetchColumn() ?: null; }
                $ins = $localPdo->prepare('INSERT INTO documents (entity_type, entity_id, document_type, document_type_id, original_name, stored_name, relative_path, mime_type, file_size, status, uploaded_by_user_id, uploaded_by_role, created_by_user_id, created_by_role) VALUES (:et, :eid, :dtype, :dtid, :oname, :sname, :rpath, :mime, :fsize, :status, :uid, :role, :cuid, :crole)');
                $ins->execute([':et' => $entityType, ':eid' => $newContractorId, ':dtype' => $docTypeName ?: null, ':dtid' => $dtId, ':oname' => $origName, ':sname' => $storedName, ':rpath' => $relativeDir . '/' . $storedName, ':mime' => $mime, ':fsize' => $fs, ':status' => 'uploaded', ':uid' => (int)$_SESSION['user_id'], ':role' => $_SESSION['role_code'], ':cuid' => (int)$_SESSION['user_id'], ':crole' => $_SESSION['role_code']]);
                $uploadedDocs[] = $docTypeName . ' (' . $origName . ')';
            } catch (\Exception $ex) { $docErrors[] = 'Предопределённый документ «' . ($_POST['predef_doc_type'][$code] ?? $code) . '»: ошибка сохранения'; }
        }
    }
    if (!empty($_FILES['custom_doc_file']['name']) && is_array($_FILES['custom_doc_file']['name'])) {
        foreach ($_FILES['custom_doc_file']['name'] as $idx => $origName) {
            $fe = $_FILES['custom_doc_file']['error'][$idx] ?? UPLOAD_ERR_NO_FILE; if ($fe !== UPLOAD_ERR_OK || trim((string)$origName) === '') continue;
            $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION)); $fs = $_FILES['custom_doc_file']['size'][$idx];
            if (!in_array($ext, $allowedExt, true)) { $docErrors[] = 'Произвольный документ #' . ($idx + 1) . ': недопустимый формат'; continue; }
            if ($fs > $maxSize) { $docErrors[] = 'Произвольный документ #' . ($idx + 1) . ': размер > 20 МБ'; continue; }
            if (strpos($origName, '../') !== false || strpos($origName, '..\\') !== false || strpos($origName, '/') !== false || strpos($origName, '\\') !== false) { $docErrors[] = 'Произвольный документ #' . ($idx + 1) . ': недопустимое имя'; continue; }
            $customTypeNew = trim($_POST['custom_doc_type_new'][$idx] ?? ''); $customTypeSelect = trim($_POST['custom_doc_type'][$idx] ?? ''); $docTypeName = $customTypeNew !== '' ? $customTypeNew : $customTypeSelect; $dtId = null;
            if ($customTypeNew !== '') { try { $idts = $localPdo->prepare("INSERT IGNORE INTO document_types (name, entity_type, category, created_by_user_id, created_by_role) VALUES (:name, :et, 'custom', :uid, :role)"); $idts->execute([':name' => $customTypeNew, ':et' => $entityType, ':uid' => (int)$_SESSION['user_id'], ':role' => $_SESSION['role_code']]); $dtId = $localPdo->lastInsertId(); if (!$dtId) { $g = $localPdo->prepare("SELECT id FROM document_types WHERE name = ? AND entity_type = ? LIMIT 1"); $g->execute([$customTypeNew, $entityType]); $dtId = $g->fetchColumn() ?: null; } } catch (\Exception $ex) { $docErrors[] = 'Произвольный документ #' . ($idx + 1) . ': ошибка создания типа документа'; } }
            elseif ($customTypeSelect !== '') { $g = $localPdo->prepare("SELECT id FROM document_types WHERE name = ? AND entity_type = ? LIMIT 1"); $g->execute([$customTypeSelect, $entityType]); $dtId = $g->fetchColumn() ?: null; }
            try {
                $storedName = uniqid('doc_', true) . '.' . $ext; $relativeDir = 'companies/' . $companyId . '/documents/' . $entityType . '/' . $newContractorId; $absoluteDir = storage_path($relativeDir);
                if (!is_dir($absoluteDir)) mkdir($absoluteDir, 0755, true);
                if (!move_uploaded_file($_FILES['custom_doc_file']['tmp_name'][$idx], $absoluteDir . DIRECTORY_SEPARATOR . $storedName)) { $docErrors[] = 'Произвольный документ #' . ($idx + 1) . ': не удалось сохранить'; continue; }
                $mime = $_FILES['custom_doc_file']['type'][$idx];
                $ins = $localPdo->prepare('INSERT INTO documents (entity_type, entity_id, document_type, document_type_id, original_name, stored_name, relative_path, mime_type, file_size, status, uploaded_by_user_id, uploaded_by_role, created_by_user_id, created_by_role) VALUES (:et, :eid, :dtype, :dtid, :oname, :sname, :rpath, :mime, :fsize, :status, :uid, :role, :cuid, :crole)');
                $ins->execute([':et' => $entityType, ':eid' => $newContractorId, ':dtype' => $docTypeName ?: null, ':dtid' => $dtId, ':oname' => $origName, ':sname' => $storedName, ':rpath' => $relativeDir . '/' . $storedName, ':mime' => $mime, ':fsize' => $fs, ':status' => 'uploaded', ':uid' => (int)$_SESSION['user_id'], ':role' => $_SESSION['role_code'], ':cuid' => (int)$_SESSION['user_id'], ':crole' => $_SESSION['role_code']]);
                $uploadedDocs[] = $docTypeName . ' (' . $origName . ')';
            } catch (\Exception $ex) { $docErrors[] = 'Произвольный документ #' . ($idx + 1) . ': ошибка сохранения'; }
        }
    }

    $success = true;
} catch (\Exception $e) {
    $company = $company ?? null;
    $formError = 'Ошибка создания перевозчика: ' . $e->getMessage();
}

$renderContractorCreateResponse();
