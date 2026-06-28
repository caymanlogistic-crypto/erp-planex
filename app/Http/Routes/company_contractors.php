<?php

use App\Service\ContractorContactService;
use App\Service\CompanyInnLookupService;

$router->get('/company/contractors', function () use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);
    $pageTitle = 'Перевозчики';
    $pageContext = 'Перевозчики › Компания';

    $companyId = (int)(getSessionCompanyId() ?? 0);

    if ($companyId <= 0) {
        $company = null;
        $contractors = [];
        $dbError = null;

        ob_start();
        require base_path('app/View/pages/company_contractors.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
        return;
    }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company) {
            $company = null;
            $contractors = [];
            $dbError = null;

            ob_start();
            require base_path('app/View/pages/company_contractors.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $pageContext = 'Перевозчики › Компания: ' . $company['name'];
        $topbarCrumbs = [
            ['label' => mb_strtoupper($company['name']), 'url' => '/company/dashboard'],
            ['label' => 'Подрядчики', 'url' => null],
            ['label' => 'Список перевозчиков', 'url' => null],
        ];

        if ($company['status'] !== 'active') {
            $contractors = [];
            $dbError = null;

            ob_start();
            require base_path('app/View/pages/company_contractors.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $dbIdentifier = $company['db_identifier'];
        $localDbConfig = $config['database'];
        $localDbConfig['database'] = $dbIdentifier;
        $localDb = new \App\Core\Database($localDbConfig);
        $localPdo = $localDb->connection();
                applyLocalMigrations($localPdo);

        try {
            $localPdo->query("SELECT 1 FROM contractors LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $migrationSql = file_get_contents(base_path('database/migrations-local/003_create_company_contractors.sql'));
            $localPdo->exec($migrationSql);
        }

        try {
            $localPdo->query("SELECT created_by_user_id FROM contractors LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $localPdo->exec("ALTER TABLE contractors ADD COLUMN created_by_user_id INT UNSIGNED DEFAULT NULL, ADD COLUMN created_by_role VARCHAR(20) DEFAULT NULL");
        }

        try {
            $localPdo->query("SELECT 1 FROM entity_access_grants LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $migrationSql = file_get_contents(base_path('database/migrations-local/009_create_entity_access_grants.sql'));
            $localPdo->exec($migrationSql);
        }

        $isLogist = ($_SESSION['role_code'] ?? '') === 'logist';
        if ($isLogist) {
            $userId = (int)$_SESSION['user_id'];
            $contractorStmt = $localPdo->prepare(
                "SELECT c.*,
                        " . ContractorContactService::buildPrimaryContactSubquery('contact_person') . " AS primary_contact_person,
                        " . ContractorContactService::buildPrimaryContactSubquery('phone') . " AS primary_contact_phone,
                        " . ContractorContactService::buildDocumentEmailSubquery() . " AS doc_email
                 FROM contractors c
                 WHERE (c.created_by_user_id = ? OR c.id IN (SELECT entity_id FROM entity_access_grants WHERE entity_type = 'contractor' AND granted_to_user_id = ? AND access_level = 'view'))
                 ORDER BY c.created_at DESC"
            );
            $contractorStmt->execute([$userId, $userId]);
            $contractors = $contractorStmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $contractorStmt = $localPdo->query(
                "SELECT c.*,
                        " . ContractorContactService::buildPrimaryContactSubquery('contact_person') . " AS primary_contact_person,
                        " . ContractorContactService::buildPrimaryContactSubquery('phone') . " AS primary_contact_phone,
                        " . ContractorContactService::buildDocumentEmailSubquery() . " AS doc_email
                 FROM contractors c
                 ORDER BY c.created_at DESC"
            );
            $contractors = $contractorStmt->fetchAll(PDO::FETCH_ASSOC);
        }
        $dbError = null;
    } catch (\Exception $e) {
        $company = $company ?? null;
        $contractors = [];
        $dbError = 'Не удалось подключиться к базе данных компании.';
    }

    ob_start();
    require base_path('app/View/pages/company_contractors.php');
    $content = ob_get_clean();
    require base_path('app/View/layouts/main.php');
});

$router->get('/company/contractors/create', function () use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);
    $pageTitle = 'Создать перевозчика';
    $pageContext = 'Перевозчики › Компания';
    $isModalRequest = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest';

    $companyId = (int)(getSessionCompanyId() ?? 0);

    if ($companyId <= 0) {
        $company = null;
        $success = false;
        $errors = [];
        $old = [];
        $formError = null;
        $createdContractor = null;

        ob_start();
        require base_path('app/View/pages/company_contractors_create.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
        return;
    }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company) {
            $company = null;
            $success = false;
            $errors = [];
            $old = [];
            $formError = null;
            $createdContractor = null;

            ob_start();
            require base_path('app/View/pages/company_contractors_create.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $pageContext = 'Перевозчики › Компания: ' . $company['name'];

        $success = false;
        $errors = [];
        $old = [];
        $formError = null;
        $createdContractor = null;
        $docTypes = [];

        if ($company['status'] === 'active') {
            try {
                $dbIdentifier = $company['db_identifier'];
                $localDbConfig = $config['database']; $localDbConfig['database'] = $dbIdentifier;
                $localDb = new \App\Core\Database($localDbConfig); $localPdo = $localDb->connection();
                applyLocalMigrations($localPdo);

                try { $localPdo->query("SELECT 1 FROM document_types LIMIT 1")->fetch(); }
                catch (\Exception $e) {
                    $localPdo->exec(file_get_contents(base_path('database/migrations-local/024_create_document_types.sql')));
                    $localPdo->exec(file_get_contents(base_path('database/migrations-local/025_add_document_type_id.sql')));
                }

                $docTypes = $localPdo->query("SELECT id, name, code, entity_type FROM document_types WHERE entity_type = 'contractor' OR entity_type IS NULL ORDER BY sort_order, name")->fetchAll(PDO::FETCH_ASSOC);
            } catch (\Exception $e) {
                $docTypes = [];
            }
        }
    } catch (\Exception $e) {
        $company = null;
        $success = false;
        $errors = [];
        $old = [];
        $formError = 'Ошибка загрузки данных: ' . $e->getMessage();
        $createdContractor = null;
    }

    if ($isModalRequest) {
        header('Content-Type: text/html; charset=utf-8');
        $leEntityType = 'contractor';
        $leFormAction = '/company/contractors/create';
        $leFormId = 'le-create-form';
        $leOld = $old ?? [];
        $leErrors = $errors ?? [];
        $leFormError = $formError;
        $leDocTypes = $docTypes ?? [];
        $leContactValues = $contactValues ?? [];
        $leContactErrors = [];
        ob_start();
        require base_path('app/View/partials/legal_entity_create_form.php');
        echo ob_get_clean();
        exit;
    }

    ob_start();
    require base_path('app/View/pages/company_contractors_create.php');
    $content = ob_get_clean();
    require base_path('app/View/layouts/main.php');
});

$router->post('/company/requisites/lookup-by-inn', function () use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);

    $companyId = (int)(getSessionCompanyId() ?? 0);
    if ($companyId <= 0) {
        jsonResponse([
            'ok' => false,
            'message' => 'Не удалось получить данные. Заполните реквизиты вручную.',
        ], 200);
        return;
    }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company || ($company['status'] ?? '') !== 'active') {
            jsonResponse([
                'ok' => false,
                'message' => 'Не удалось получить данные. Заполните реквизиты вручную.',
            ], 200);
            return;
        }

        $payload = requestJsonBody();
        $inn = (string)($payload['inn'] ?? $_POST['inn'] ?? '');

        $lookupService = new CompanyInnLookupService(
            (string)env('DADATA_API_KEY', ''),
            (string)env('DADATA_API_URL', 'https://suggestions.dadata.ru/suggestions/api/4_1/rs/findById/party'),
            (int)env('DADATA_API_TIMEOUT', 5),
            (string)env('DADATA_CAINFO', '')
        );

        $result = $lookupService->lookup($inn);
        jsonResponse($result, 200);
    } catch (\Throwable $e) {
        jsonResponse([
            'ok' => false,
            'message' => 'Не удалось получить данные. Заполните реквизиты вручную.',
        ], 200);
    }
});

$router->get('/company/contractors/create-full', function () use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);
    $pageTitle = 'Создать перевозчика + Водителя + Транспорт';
    $pageContext = 'Перевозчики › Компания';

    $companyId = (int)(getSessionCompanyId() ?? 0);
    $contractors = [];
    $drivers = [];
    $vehicleSets = [];

    if ($companyId <= 0) {
        $company = null;
        $success = false;
        $errors = [];
        $old = [];
        $formError = null;

        ob_start();
        require base_path('app/View/pages/company_contractors_create_full.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
        return;
    }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company) {
            $company = null;
            $success = false;
            $errors = [];
            $old = [];
            $formError = null;

            ob_start();
            require base_path('app/View/pages/company_contractors_create_full.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $pageContext = 'Перевозчики › Компания: ' . $company['name'];

        // Load existing entities from local database
        $dbIdentifier = $company['db_identifier'];
        $localDbConfig = $config['database'];
        $localDbConfig['database'] = $dbIdentifier;
        $localDb = new \App\Core\Database($localDbConfig);
        $localPdo = $localDb->connection();
        applyLocalMigrations($localPdo);

        // Load contractors
        try {
            $contractorsStmt = $localPdo->query(
                'SELECT id, name, inn FROM contractors WHERE status IN (\'active\', \'archived\') ORDER BY name'
            );
            $contractors = $contractorsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Exception $e) {
            $contractors = [];
        }

        // Load drivers
        try {
            $driversStmt = $localPdo->query(
                'SELECT id, full_name, phone FROM drivers WHERE status IN (\'active\', \'archived\') ORDER BY full_name'
            );
            $drivers = $driversStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Exception $e) {
            $drivers = [];
        }

        // Load vehicle sets with plate numbers
        try {
            $vehicleSetsStmt = $localPdo->query(
                'SELECT vs.id, vs.set_type,
                        vu1.plate_number AS primary_plate,
                        vu2.plate_number AS secondary_plate
                   FROM vehicle_sets vs
                   JOIN vehicle_units vu1 ON vs.primary_vehicle_unit_id = vu1.id
                   LEFT JOIN vehicle_units vu2 ON vs.secondary_vehicle_unit_id = vu2.id
                  WHERE vs.status IN (\'active\', \'archived\')
                  ORDER BY vs.id DESC'
            );
            $vehicleSets = $vehicleSetsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Exception $e) {
            $vehicleSets = [];
        }

        $success = false;
        $errors = [];
        $old = [];
        $formError = null;
    } catch (\Exception $e) {
        $company = null;
        $success = false;
        $errors = [];
        $old = [];
        $formError = 'Ошибка загрузки данных: ' . $e->getMessage();
    }

    ob_start();
    require base_path('app/View/pages/company_contractors_create_full.php');
    $content = ob_get_clean();
    require base_path('app/View/layouts/main.php');
});

$router->post('/company/contractors/create', function () use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);
    $pageTitle = 'Создать перевозчика';
    $pageContext = 'Перевозчики › Компания';
    $isModalRequest = !empty($_POST['is_modal']);

    $companyId = (int)(getSessionCompanyId() ?? 0);
    $errors = [];
    $old = $_POST;
    $formError = null;
    $success = false;
    $createdContractor = null;
    $docErrors = [];
    $uploadedDocs = [];

    if ($companyId <= 0) {
        $company = null;
        $formError = 'Компания не найдена';

        ob_start();
        require base_path('app/View/pages/company_contractors_create.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
        return;
    }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company) {
            $company = null;
            $formError = 'Компания не найдена';

            ob_start();
            require base_path('app/View/pages/company_contractors_create.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $pageContext = 'Перевозчики › Компания: ' . $company['name'];

        if ($company['status'] !== 'active') {
            $formError = 'Создание перевозчиков недоступно';

            ob_start();
            require base_path('app/View/pages/company_contractors_create.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $dbIdentifier = $company['db_identifier'];
        $localDbConfig = $config['database'];
        $localDbConfig['database'] = $dbIdentifier;
        $localDb = new \App\Core\Database($localDbConfig);
        $localPdo = $localDb->connection();
                applyLocalMigrations($localPdo);

        try {
            $localPdo->query("SELECT 1 FROM contractors LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $migrationSql = file_get_contents(base_path('database/migrations-local/003_create_company_contractors.sql'));
            $localPdo->exec($migrationSql);
        }

        if (isPostTruncated()) {
            $formError = 'Общий размер отправки превышает серверный лимит. Для ERP требуется настройка post_max_size не менее 100M. Уменьшите количество файлов или обратитесь к администратору.';
            ob_start();
            require base_path('app/View/pages/company_contractors_create.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
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

        if ($name === '') {
            $errors['name'] = 'Обязательное поле';
        }

        // Валидация ИНН
        if ($inn === '') {
            $errors['inn'] = 'Укажите ИНН';
        } elseif (!preg_match('/^\d+$/', $inn) || (strlen($inn) !== 10 && strlen($inn) !== 12)) {
            $errors['inn'] = 'ИНН должен содержать 10 или 12 цифр';
        }

        // Валидация КПП (если заполнен)
        if ($kpp !== '' && !preg_match('/^\d{9}$/', $kpp)) {
            $errors['kpp'] = 'КПП должен содержать 9 цифр';
        }

        // Валидация ОГРН (если заполнен)
        if ($ogrn !== '' && (!preg_match('/^\d+$/', $ogrn) || (strlen($ogrn) !== 13 && strlen($ogrn) !== 15))) {
            $errors['ogrn'] = 'ОГРН/ОГРНИП должен содержать 13 или 15 цифр';
        }

        // Валидация банковских полей (если заполнены)
        if (($_POST['bank_account'] ?? '') !== '' && !preg_match('/^\d{20}$/', $_POST['bank_account'])) {
            $errors['bank_account'] = 'Расчётный счёт должен содержать 20 цифр';
        }
        if (($_POST['bank_bik'] ?? '') !== '' && !preg_match('/^\d{9}$/', $_POST['bank_bik'])) {
            $errors['bank_bik'] = 'БИК должен содержать 9 цифр';
        }
        if (($_POST['bank_corr_account'] ?? '') !== '' && !preg_match('/^\d{20}$/', $_POST['bank_corr_account'])) {
            $errors['bank_corr_account'] = 'Корр. счёт должен содержать 20 цифр';
        }

        if ($inn !== '') {
            $checkStmt = $localPdo->prepare('SELECT COUNT(*) FROM contractors WHERE inn = ?');
            $checkStmt->execute([$inn]);
            if ($checkStmt->fetchColumn() > 0) {
                $errors['inn'] = 'ИНН уже используется в этой компании';
            }
        }

        if (!empty($errors)) {
            ob_start();
            require base_path('app/View/pages/company_contractors_create.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        // Check total upload size before INSERT
        $totalSizeError = validateTotalUploadSize();
        if ($totalSizeError !== '') {
            $formError = $totalSizeError;
            ob_start();
            require base_path('app/View/pages/company_contractors_create.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $legalEntityFiles = $_FILES;
        $customDocumentTitleError = validateLegalEntityCustomDocumentTitles($_POST, $legalEntityFiles);
        if ($customDocumentTitleError !== null) {
            $formError = $customDocumentTitleError;
            ob_start();
            require base_path('app/View/pages/company_contractors_create.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $insert = $localPdo->prepare(
            'INSERT INTO contractors (name, inn, kpp, ogrn, contractor_type, legal_address, physical_address,
             bank_account, bank_name, bank_bik, bank_corr_account,
             director_full_name, director_position,
             status, comments, created_by_user_id, created_by_role)
             VALUES (:name, :inn, :kpp, :ogrn, :contractor_type, :legal_address, :physical_address,
             :bank_account, :bank_name, :bank_bik, :bank_corr_account,
             :director_full_name, :director_position,
             :status, :comments, :created_by_user_id, :created_by_role)'
        );
        $insert->execute([
            ':name'               => $name,
            ':inn'                => $inn,
            ':kpp'                => $kpp !== '' ? $kpp : null,
            ':ogrn'               => $ogrn !== '' ? $ogrn : null,
            ':contractor_type'    => ($_POST['contractor_type'] ?? '') !== '' ? $_POST['contractor_type'] : null,
            ':legal_address'      => $legalAddress !== '' ? $legalAddress : null,
            ':physical_address'   => $physicalAddress !== '' ? $physicalAddress : null,
            ':bank_account'       => ($_POST['bank_account'] ?? '') !== '' ? $_POST['bank_account'] : null,
            ':bank_name'          => ($_POST['bank_name'] ?? '') !== '' ? $_POST['bank_name'] : null,
            ':bank_bik'           => ($_POST['bank_bik'] ?? '') !== '' ? $_POST['bank_bik'] : null,
            ':bank_corr_account'  => ($_POST['bank_corr_account'] ?? '') !== '' ? $_POST['bank_corr_account'] : null,
            ':director_full_name' => $directorFullName !== '' ? $directorFullName : null,
            ':director_position'  => $directorPosition !== '' ? $directorPosition : null,
            ':status'             => 'active',
            ':comments'           => $comments !== '' ? $comments : null,
            ':created_by_user_id' => (int)$_SESSION['user_id'],
            ':created_by_role'    => $_SESSION['role_code'],
        ]);

        $newContractorId = (int)$localPdo->lastInsertId();
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

        // -- Process document uploads during creation --
        $entityType = 'contractor';
        try { $localPdo->query("SELECT 1 FROM documents LIMIT 1")->fetch(); } catch (\Exception $e) { $localPdo->exec(file_get_contents(base_path('database/migrations-local/007_create_company_documents.sql'))); }
        try { $localPdo->query("SELECT 1 FROM document_types LIMIT 1")->fetch(); } catch (\Exception $e) { $localPdo->exec(file_get_contents(base_path('database/migrations-local/024_create_document_types.sql'))); $localPdo->exec(file_get_contents(base_path('database/migrations-local/025_add_document_type_id.sql'))); }
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

    if ($isModalRequest) {
        header('Content-Type: text/html; charset=utf-8');
        if ($success) {
            echo '<div data-le-create-success="1"></div>';
        } else {
            if (empty($docTypes) && !empty($localPdo)) {
                try { $docTypes = $localPdo->query("SELECT id, name, code, entity_type FROM document_types WHERE entity_type = 'contractor' OR entity_type IS NULL ORDER BY sort_order, name")->fetchAll(PDO::FETCH_ASSOC); } catch (\Exception $e) { $docTypes = []; }
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
        }
        exit;
    }

    ob_start();
    require base_path('app/View/pages/company_contractors_create.php');
    $content = ob_get_clean();
    require base_path('app/View/layouts/main.php');
});

$router->post('/company/contractors/create-full', function () use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);
    $pageTitle = 'Создать перевозчика + Водителя + Транспорт';
    $pageContext = 'Перевозчики › Компания';

    $companyId = (int)(getSessionCompanyId() ?? 0);
    $errors = [];
    $old = $_POST;
    $formError = null;
    $success = false;
    $createdContractor = null;
    $createdDriver = null;
    $createdVehicleSet = null;
    $createdBlock = null;
    $createdCrew = null;
    $contractors = [];
    $drivers = [];
    $vehicleSets = [];

    if ($companyId <= 0) {
        $company = null;
        $formError = 'Компания не найдена';

        ob_start();
        require base_path('app/View/pages/company_contractors_create_full.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
        return;
    }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company) {
            $company = null;
            $formError = 'Компания не найдена';

            ob_start();
            require base_path('app/View/pages/company_contractors_create_full.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $pageContext = 'Перевозчики › Компания: ' . $company['name'];

        if ($company['status'] !== 'active') {
            $formError = 'Создание недоступно';

            ob_start();
            require base_path('app/View/pages/company_contractors_create_full.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $dbIdentifier = $company['db_identifier'];
        $localDbConfig = $config['database'];
        $localDbConfig['database'] = $dbIdentifier;
        $localDb = new \App\Core\Database($localDbConfig);
        $localPdo = $localDb->connection();
        applyLocalMigrations($localPdo);

        // Ensure all required tables exist
        $requiredTables = [
            'contractors' => 'database/migrations-local/003_create_company_contractors.sql',
            'drivers'     => 'database/migrations-local/004_create_company_drivers.sql',
            'vehicle_units' => 'database/migrations-local/005_create_company_vehicles.sql',
            'vehicle_sets' => 'database/migrations-local/017_create_vehicle_sets.sql',
            'driver_vehicle_blocks' => 'database/migrations-local/018_create_driver_vehicle_blocks.sql',
            'crews'       => 'database/migrations-local/006_create_company_crews.sql',
        ];

        foreach ($requiredTables as $table => $migrationFile) {
            try {
                $localPdo->query("SELECT 1 FROM `$table` LIMIT 1")->fetch();
            } catch (\Exception $e) {
                $migrationSql = file_get_contents(base_path($migrationFile));
                if ($migrationSql !== false && trim($migrationSql) !== '') {
                    $localPdo->exec($migrationSql);
                }
            }
        }

        // Handle vehicles -> vehicle_units rename if needed
        try {
            $localPdo->query("SELECT 1 FROM vehicle_units LIMIT 1")->fetch();
        } catch (\Exception $e) {
            try {
                $localPdo->exec("RENAME TABLE vehicles TO vehicle_units");
            } catch (\Exception $renameEx) {
                // Table may already be vehicle_units or vehicles may not exist
            }
        }

        // --- Determine modes ---
        $contractorMode = trim($_POST['contractor_mode'] ?? 'existing');
        $driverMode = trim($_POST['driver_mode'] ?? 'existing');
        $vehicleMode = trim($_POST['vehicle_mode'] ?? 'existing');

        // --- Load existing entities for view (dropdowns + success display) ---
        $contractors = [];
        $drivers = [];
        $vehicleSets = [];
        try {
            $contractorsStmt = $localPdo->query(
                'SELECT id, name, inn FROM contractors WHERE status IN (\'active\', \'archived\') ORDER BY name'
            );
            $contractors = $contractorsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Exception $e) {}
        try {
            $driversStmt = $localPdo->query(
                'SELECT id, full_name, phone FROM drivers WHERE status IN (\'active\', \'archived\') ORDER BY full_name'
            );
            $drivers = $driversStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Exception $e) {}
        try {
            $vehicleSetsStmt = $localPdo->query(
                'SELECT vs.id, vs.set_type,
                        vu1.plate_number AS primary_plate,
                        vu2.plate_number AS secondary_plate
                   FROM vehicle_sets vs
                   JOIN vehicle_units vu1 ON vs.primary_vehicle_unit_id = vu1.id
                   LEFT JOIN vehicle_units vu2 ON vs.secondary_vehicle_unit_id = vu2.id
                  WHERE vs.status IN (\'active\', \'archived\')
                  ORDER BY vs.id DESC'
            );
            $vehicleSets = $vehicleSetsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Exception $e) {}

        // --- Validation ---
        $name = trim($_POST['name'] ?? '');
        $inn = trim($_POST['inn'] ?? '');
        $kpp = trim($_POST['kpp'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $contractorIdExisting = trim($_POST['contractor_id'] ?? '');

        $driverFullName = trim($_POST['driver_full_name'] ?? '');
        $driverPhone = trim($_POST['driver_phone'] ?? '');
        $driverIdExisting = trim($_POST['driver_id'] ?? '');

        $plateNumber = trim($_POST['plate_number'] ?? '');
        $brand = trim($_POST['brand'] ?? '');
        $model = trim($_POST['model'] ?? '');
        $setType = trim($_POST['set_type'] ?? 'single');
        $secondaryPlateNumber = trim($_POST['secondary_plate_number'] ?? '');
        $vehicleSetIdExisting = trim($_POST['vehicle_set_id'] ?? '');

        // Contractor validation
        if ($contractorMode === 'existing') {
            if ($contractorIdExisting === '') {
                $errors['contractor_id'] = 'Выберите перевозчика';
            } else {
                // Verify contractor exists
                $checkStmt = $localPdo->prepare('SELECT id, name, inn FROM contractors WHERE id = ?');
                $checkStmt->execute([(int)$contractorIdExisting]);
                $existingContractorRow = $checkStmt->fetch(PDO::FETCH_ASSOC);
                if (!$existingContractorRow) {
                    $errors['contractor_id'] = 'Перевозчик не найден';
                }
            }
        } else {
            if ($name === '') {
                $errors['name'] = 'Обязательное поле';
            }
            if ($inn === '') {
                $errors['inn'] = 'Обязательное поле';
            } else {
                $checkStmt = $localPdo->prepare('SELECT COUNT(*) FROM contractors WHERE inn = ?');
                $checkStmt->execute([$inn]);
                if ($checkStmt->fetchColumn() > 0) {
                    $errors['inn'] = 'ИНН уже используется в этой компании';
                }
            }
        }

        // Driver validation
        if ($driverMode === 'existing') {
            if ($driverIdExisting === '') {
                $errors['driver_id'] = 'Выберите водителя';
            } else {
                $checkStmt = $localPdo->prepare('SELECT id, full_name FROM drivers WHERE id = ?');
                $checkStmt->execute([(int)$driverIdExisting]);
                if (!$checkStmt->fetch(PDO::FETCH_ASSOC)) {
                    $errors['driver_id'] = 'Водитель не найден';
                }
            }
        } else {
            if ($driverFullName === '') {
                $errors['driver_full_name'] = 'Обязательное поле';
            }
        }

        // Vehicle validation
        if ($vehicleMode === 'existing') {
            if ($vehicleSetIdExisting === '') {
                $errors['vehicle_set_id'] = 'Выберите транспорт';
            } else {
                $checkStmt = $localPdo->prepare('SELECT id FROM vehicle_sets WHERE id = ?');
                $checkStmt->execute([(int)$vehicleSetIdExisting]);
                if (!$checkStmt->fetch(PDO::FETCH_ASSOC)) {
                    $errors['vehicle_set_id'] = 'Транспорт не найден';
                }
            }
        } else {
            if ($plateNumber === '') {
                $errors['plate_number'] = 'Обязательное поле';
            } else {
                $checkStmt = $localPdo->prepare('SELECT COUNT(*) FROM vehicle_units WHERE plate_number = ?');
                $checkStmt->execute([$plateNumber]);
                if ($checkStmt->fetchColumn() > 0) {
                    $errors['plate_number'] = 'Госномер уже используется в этой компании';
                }
            }
            if (($setType === 'coupling' || $setType === 'road_train') && $secondaryPlateNumber === '') {
                $errors['secondary_plate_number'] = 'Обязательно для сцепки и автопоезда';
            }
            if ($secondaryPlateNumber !== '' && empty($errors['plate_number'])) {
                $checkStmt = $localPdo->prepare('SELECT COUNT(*) FROM vehicle_units WHERE plate_number = ?');
                $checkStmt->execute([$secondaryPlateNumber]);
                if ($checkStmt->fetchColumn() > 0) {
                    $errors['secondary_plate_number'] = 'Госномер уже используется в этой компании';
                }
            }
        }

        if (!empty($errors)) {
            ob_start();
            require base_path('app/View/pages/company_contractors_create_full.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        // --- Transactional creation ---
        $localPdo->beginTransaction();
        try {
            $uid = (int)$_SESSION['user_id'];
            $role = $_SESSION['role_code'];

            // 1. Contractor — use existing or create new
            $contractorId = null;
            $contractorNameForSuccess = '';
            $contractorInnForSuccess = '';
            if ($contractorMode === 'existing') {
                $contractorId = (int)$contractorIdExisting;
                $contractorNameForSuccess = $existingContractorRow['name'] ?? '';
                $contractorInnForSuccess = $existingContractorRow['inn'] ?? '';
            } else {
                $insertContractor = $localPdo->prepare(
                    'INSERT INTO contractors (name, inn, kpp, status, created_by_user_id, created_by_role)
                     VALUES (:name, :inn, :kpp, :status, :uid, :role)'
                );
                $insertContractor->execute([
                    ':name'   => $name,
                    ':inn'    => $inn,
                    ':kpp'    => $kpp !== '' ? $kpp : null,
                    ':status' => 'active',
                    ':uid'    => $uid,
                    ':role'   => $role,
                ]);
                $contractorId = (int)$localPdo->lastInsertId();
                ContractorContactService::replaceForContractor(
                    $localPdo,
                    $contractorId,
                    $phone !== '' ? [[
                        'contact_person'      => null,
                        'phone'               => $phone,
                        'email'               => null,
                        'comment'             => null,
                        'is_primary'          => 1,
                        'is_document_email'   => 0,
                    ]] : [],
                    $uid,
                    (string) ($role ?? '')
                );
                $contractorNameForSuccess = $name;
                $contractorInnForSuccess = $inn;
            }

            // 2. Driver — use existing or create new
            $driverId = null;
            $driverNameForSuccess = '';
            if ($driverMode === 'existing') {
                $driverId = (int)$driverIdExisting;
                // Fetch name for success display
                $drvStmt = $localPdo->prepare('SELECT full_name FROM drivers WHERE id = ?');
                $drvStmt->execute([$driverId]);
                $drvRow = $drvStmt->fetch(PDO::FETCH_ASSOC);
                $driverNameForSuccess = $drvRow['full_name'] ?? '';
            } else {
                $insertDriver = $localPdo->prepare(
                    'INSERT INTO drivers (full_name, phone, status, created_by_user_id, created_by_role)
                     VALUES (:full_name, :phone, :status, :uid, :role)'
                );
                $insertDriver->execute([
                    ':full_name' => $driverFullName,
                    ':phone'     => $driverPhone !== '' ? $driverPhone : '',
                    ':status'    => 'active',
                    ':uid'       => $uid,
                    ':role'      => $role,
                ]);
                $driverId = (int)$localPdo->lastInsertId();
                $driverNameForSuccess = $driverFullName;
            }

            // 3. Vehicle — use existing or create new
            $vehicleSetId = null;
            $vehiclePrimaryPlateForSuccess = '';
            $vehicleSecondaryPlateForSuccess = null;
            if ($vehicleMode === 'existing') {
                $vehicleSetId = (int)$vehicleSetIdExisting;
                // Fetch plate numbers for success display
                $vsStmt = $localPdo->prepare(
                    'SELECT vu1.plate_number AS primary_plate, vu2.plate_number AS secondary_plate
                       FROM vehicle_sets vs
                       JOIN vehicle_units vu1 ON vs.primary_vehicle_unit_id = vu1.id
                       LEFT JOIN vehicle_units vu2 ON vs.secondary_vehicle_unit_id = vu2.id
                      WHERE vs.id = ?'
                );
                $vsStmt->execute([$vehicleSetId]);
                $vsRow = $vsStmt->fetch(PDO::FETCH_ASSOC);
                $vehiclePrimaryPlateForSuccess = $vsRow['primary_plate'] ?? '';
                $vehicleSecondaryPlateForSuccess = !empty($vsRow['secondary_plate']) ? $vsRow['secondary_plate'] : null;
            } else {
                // Create primary vehicle_unit
                $insertPrimaryUnit = $localPdo->prepare(
                    'INSERT INTO vehicle_units (plate_number, brand, model, unit_type, status, created_by_user_id, created_by_role)
                     VALUES (:plate_number, :brand, :model, :unit_type, :status, :uid, :role)'
                );
                $insertPrimaryUnit->execute([
                    ':plate_number' => $plateNumber,
                    ':brand'        => $brand !== '' ? $brand : null,
                    ':model'        => $model !== '' ? $model : null,
                    ':unit_type'    => 'tractor',
                    ':status'       => 'active',
                    ':uid'          => $uid,
                    ':role'         => $role,
                ]);
                $primaryUnitId = (int)$localPdo->lastInsertId();

                // Create secondary vehicle_unit if needed
                $secondaryUnitId = null;
                if ($secondaryPlateNumber !== '') {
                    $insertSecondaryUnit = $localPdo->prepare(
                        'INSERT INTO vehicle_units (plate_number, unit_type, status, created_by_user_id, created_by_role)
                         VALUES (:plate_number, :unit_type, :status, :uid, :role)'
                    );
                    $insertSecondaryUnit->execute([
                        ':plate_number' => $secondaryPlateNumber,
                        ':unit_type'    => 'trailer',
                        ':status'       => 'active',
                        ':uid'          => $uid,
                        ':role'         => $role,
                    ]);
                    $secondaryUnitId = (int)$localPdo->lastInsertId();
                }

                // Create vehicle_set
                $insertVehicleSet = $localPdo->prepare(
                    'INSERT INTO vehicle_sets (set_type, primary_vehicle_unit_id, secondary_vehicle_unit_id, status, created_by_user_id, created_by_role)
                     VALUES (:set_type, :primary_id, :secondary_id, :status, :uid, :role)'
                );
                $insertVehicleSet->execute([
                    ':set_type'     => $setType,
                    ':primary_id'   => $primaryUnitId,
                    ':secondary_id' => $secondaryUnitId,
                    ':status'       => 'active',
                    ':uid'          => $uid,
                    ':role'         => $role,
                ]);
                $vehicleSetId = (int)$localPdo->lastInsertId();
                $vehiclePrimaryPlateForSuccess = $plateNumber;
                $vehicleSecondaryPlateForSuccess = $secondaryPlateNumber !== '' ? $secondaryPlateNumber : null;
            }

            // 4. Check for existing driver_vehicle_block
            $existingBlock = $localPdo->prepare('SELECT id FROM driver_vehicle_blocks WHERE driver_id = ? AND vehicle_set_id = ?');
            $existingBlock->execute([$driverId, $vehicleSetId]);
            $blockRow = $existingBlock->fetch(PDO::FETCH_ASSOC);
            if ($blockRow) {
                $blockId = (int)$blockRow['id'];
            } else {
                $insertBlock = $localPdo->prepare(
                    'INSERT INTO driver_vehicle_blocks (driver_id, vehicle_set_id, status, created_by_user_id, created_by_role)
                     VALUES (:driver_id, :vehicle_set_id, :status, :uid, :role)'
                );
                $insertBlock->execute([
                    ':driver_id'       => $driverId,
                    ':vehicle_set_id'  => $vehicleSetId,
                    ':status'          => 'active',
                    ':uid'             => $uid,
                    ':role'            => $role,
                ]);
                $blockId = (int)$localPdo->lastInsertId();
            }

            // 5. Create crew (with duplicate check)
            $existingCrew = $localPdo->prepare('SELECT id FROM crews WHERE contractor_id = ? AND driver_vehicle_block_id = ?');
            $existingCrew->execute([$contractorId, $blockId]);
            if (!$existingCrew->fetch()) {
                $insertCrew = $localPdo->prepare(
                    'INSERT INTO crews (contractor_id, driver_vehicle_block_id, status, created_by_user_id, created_by_role)
                     VALUES (:contractor_id, :driver_vehicle_block_id, :status, :uid, :role)'
                );
                $insertCrew->execute([
                    ':contractor_id'           => $contractorId,
                    ':driver_vehicle_block_id' => $blockId,
                    ':status'                  => 'active',
                    ':uid'                     => $uid,
                    ':role'                    => $role,
                ]);
            }

            // Capture crew ID for success display
            $crewFetch = $localPdo->prepare('SELECT id FROM crews WHERE contractor_id = ? AND driver_vehicle_block_id = ?');
            $crewFetch->execute([$contractorId, $blockId]);
            $crewRow = $crewFetch->fetch(PDO::FETCH_ASSOC);
            $crewId = $crewRow ? (int)$crewRow['id'] : 0;

            $localPdo->commit();

            $createdContractor = ['id' => $contractorId, 'name' => $contractorNameForSuccess, 'inn' => $contractorInnForSuccess];
            $createdDriver = ['id' => $driverId, 'full_name' => $driverNameForSuccess];
            $createdVehicleSet = [
                'id'              => $vehicleSetId,
                'primary_plate'   => $vehiclePrimaryPlateForSuccess,
                'secondary_plate' => $vehicleSecondaryPlateForSuccess,
            ];
            $createdBlock = ['id' => $blockId];
            $createdCrew = ['id' => $crewId];
            $success = true;
        } catch (\Exception $e) {
            $localPdo->rollBack();
            throw $e;
        }
    } catch (\Exception $e) {
        $company = $company ?? null;
        $formError = 'Ошибка создания: ' . $e->getMessage();
    }

    // Re-load entity lists for view in case of error (if not already loaded)
    if (!isset($contractors) || !is_array($contractors)) { $contractors = []; }
    if (!isset($drivers) || !is_array($drivers)) { $drivers = []; }
    if (!isset($vehicleSets) || !is_array($vehicleSets)) { $vehicleSets = []; }

    ob_start();
    require base_path('app/View/pages/company_contractors_create_full.php');
    $content = ob_get_clean();
    require base_path('app/View/layouts/main.php');
});

// ====================================================================
// MASTER-FLOW: Add crew to existing contractor
// ====================================================================
$router->get('/company/contractors/{id}/add-crew', function ($id) use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);
    $pageTitle = 'Добавить экипаж перевозчику';
    $pageContext = 'Перевозчики › Компания';

    $companyId = (int)(getSessionCompanyId() ?? 0);
    $contractorId = (int)$id;
    $drivers = [];
    $vehicleSets = [];
    $driverVehicleBlocks = [];

    if ($companyId <= 0) {
        $company = null;
        $contractor = null;
        $success = false;
        $errors = [];
        $old = [];
        $formError = null;
        $dbError = null;

        ob_start();
        require base_path('app/View/pages/company_contractor_add_crew.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
        return;
    }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company) {
            $company = null;
            $contractor = null;
            $success = false;
            $errors = [];
            $old = [];
            $formError = null;
            $dbError = null;

            ob_start();
            require base_path('app/View/pages/company_contractor_add_crew.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $pageContext = 'Перевозчики › Компания: ' . $company['name'];

        if ($company['status'] !== 'active') {
            $contractor = null;
            $success = false;
            $errors = [];
            $old = [];
            $formError = null;
            $dbError = null;

            ob_start();
            require base_path('app/View/pages/company_contractor_add_crew.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $dbIdentifier = $company['db_identifier'];
        $localDbConfig = $config['database'];
        $localDbConfig['database'] = $dbIdentifier;
        $localDb = new \App\Core\Database($localDbConfig);
        $localPdo = $localDb->connection();
        applyLocalMigrations($localPdo);

        // Ensure required tables
        try { $localPdo->query("SELECT 1 FROM contractors LIMIT 1")->fetch(); } catch (\Exception $e) { $localPdo->exec(file_get_contents(base_path('database/migrations-local/003_create_company_contractors.sql'))); }
        try { $localPdo->query("SELECT 1 FROM drivers LIMIT 1")->fetch(); } catch (\Exception $e) { $localPdo->exec(file_get_contents(base_path('database/migrations-local/004_create_company_drivers.sql'))); }
        try { $localPdo->query("SELECT 1 FROM vehicle_sets LIMIT 1")->fetch(); } catch (\Exception $e) { $localPdo->exec(file_get_contents(base_path('database/migrations-local/017_create_vehicle_sets.sql'))); }
        try { $localPdo->query("SELECT 1 FROM driver_vehicle_blocks LIMIT 1")->fetch(); } catch (\Exception $e) { $localPdo->exec(file_get_contents(base_path('database/migrations-local/018_create_driver_vehicle_blocks.sql'))); }
        try { $localPdo->query("SELECT 1 FROM crews LIMIT 1")->fetch(); } catch (\Exception $e) { $localPdo->exec(file_get_contents(base_path('database/migrations-local/006_create_company_crews.sql'))); }

        // Load contractor
        $contractorStmt = $localPdo->prepare('SELECT * FROM contractors WHERE id = ?');
        $contractorStmt->execute([$contractorId]);
        $contractor = $contractorStmt->fetch(PDO::FETCH_ASSOC) ?: null;

        if (!$contractor) {
            $dbError = null;
            $success = false;
            $errors = [];
            $old = [];
            $formError = null;

            ob_start();
            require base_path('app/View/pages/company_contractor_add_crew.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        // Load drivers
        $isLogist = ($_SESSION['role_code'] ?? '') === 'logist';
        $userId = (int)$_SESSION['user_id'];

        try {
            if ($isLogist) {
                $driversStmt = $localPdo->prepare(
                    "SELECT id, full_name, phone FROM drivers WHERE status IN ('active', 'archived') AND (created_by_user_id = ? OR id IN (SELECT entity_id FROM entity_access_grants WHERE entity_type = 'driver' AND granted_to_user_id = ? AND access_level IN ('view','edit') AND revoked_at IS NULL)) ORDER BY full_name"
                );
                $driversStmt->execute([$userId, $userId]);
                $drivers = $driversStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            } else {
                $driversStmt = $localPdo->query(
                    "SELECT id, full_name, phone FROM drivers WHERE status IN ('active', 'archived') ORDER BY full_name"
                );
                $drivers = $driversStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            }
        } catch (\Exception $e) {
            $drivers = [];
        }

        // Load vehicle sets
        try {
            if ($isLogist) {
                $vehicleSetsStmt = $localPdo->prepare(
                    "SELECT vs.id, vs.set_type,
                            vu1.plate_number AS primary_plate,
                            vu2.plate_number AS secondary_plate
                       FROM vehicle_sets vs
                       JOIN vehicle_units vu1 ON vs.primary_vehicle_unit_id = vu1.id
                       LEFT JOIN vehicle_units vu2 ON vs.secondary_vehicle_unit_id = vu2.id
                      WHERE vs.status IN ('active', 'archived')
                        AND (vs.created_by_user_id = ? OR vs.id IN (SELECT entity_id FROM entity_access_grants WHERE entity_type = 'vehicle_set' AND granted_to_user_id = ? AND access_level IN ('view','edit') AND revoked_at IS NULL))
                      ORDER BY vs.id DESC"
                );
                $vehicleSetsStmt->execute([$userId, $userId]);
                $vehicleSets = $vehicleSetsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            } else {
                $vehicleSetsStmt = $localPdo->query(
                    "SELECT vs.id, vs.set_type,
                            vu1.plate_number AS primary_plate,
                            vu2.plate_number AS secondary_plate
                       FROM vehicle_sets vs
                       JOIN vehicle_units vu1 ON vs.primary_vehicle_unit_id = vu1.id
                       LEFT JOIN vehicle_units vu2 ON vs.secondary_vehicle_unit_id = vu2.id
                      WHERE vs.status IN ('active', 'archived')
                      ORDER BY vs.id DESC"
                );
                $vehicleSets = $vehicleSetsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            }
        } catch (\Exception $e) {
            $vehicleSets = [];
        }

        // Load driver-vehicle blocks not already crewed with this contractor
        try {
            if ($isLogist) {
                $dvbStmt = $localPdo->prepare(
                    "SELECT dvb.id, d.full_name AS driver_name, d.id AS driver_id,
                            vs.id AS vehicle_set_id, vs.set_type,
                            vu1.plate_number AS primary_plate,
                            vu2.plate_number AS secondary_plate
                       FROM driver_vehicle_blocks dvb
                       JOIN drivers d ON dvb.driver_id = d.id
                       JOIN vehicle_sets vs ON dvb.vehicle_set_id = vs.id
                       JOIN vehicle_units vu1 ON vs.primary_vehicle_unit_id = vu1.id
                       LEFT JOIN vehicle_units vu2 ON vs.secondary_vehicle_unit_id = vu2.id
                      WHERE dvb.status = 'active'
                        AND dvb.id NOT IN (
                            SELECT driver_vehicle_block_id FROM crews
                             WHERE contractor_id = ? AND status != 'archived'
                        )
                        AND (dvb.created_by_user_id = ? OR dvb.id IN (SELECT entity_id FROM entity_access_grants WHERE entity_type = 'driver_vehicle_block' AND granted_to_user_id = ? AND access_level IN ('view','edit') AND revoked_at IS NULL))
                      ORDER BY d.full_name"
                );
                $dvbStmt->execute([$contractorId, $userId, $userId]);
                $driverVehicleBlocks = $dvbStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            } else {
                $dvbStmt = $localPdo->prepare(
                    "SELECT dvb.id, d.full_name AS driver_name, d.id AS driver_id,
                            vs.id AS vehicle_set_id, vs.set_type,
                            vu1.plate_number AS primary_plate,
                            vu2.plate_number AS secondary_plate
                       FROM driver_vehicle_blocks dvb
                       JOIN drivers d ON dvb.driver_id = d.id
                       JOIN vehicle_sets vs ON dvb.vehicle_set_id = vs.id
                       JOIN vehicle_units vu1 ON vs.primary_vehicle_unit_id = vu1.id
                       LEFT JOIN vehicle_units vu2 ON vs.secondary_vehicle_unit_id = vu2.id
                      WHERE dvb.status = 'active'
                        AND dvb.id NOT IN (
                            SELECT driver_vehicle_block_id FROM crews
                             WHERE contractor_id = ? AND status != 'archived'
                        )
                      ORDER BY d.full_name"
                );
                $dvbStmt->execute([$contractorId]);
                $driverVehicleBlocks = $dvbStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            }
        } catch (\Exception $e) {
            $driverVehicleBlocks = [];
        }

        $success = false;
        $errors = [];
        $old = [];
        $formError = null;
        $dbError = null;
    } catch (\Exception $e) {
        $company = null;
        $contractor = null;
        $success = false;
        $errors = [];
        $old = [];
        $formError = 'Ошибка загрузки данных: ' . $e->getMessage();
        $dbError = null;
    }

    ob_start();
    require base_path('app/View/pages/company_contractor_add_crew.php');
    $content = ob_get_clean();
    require base_path('app/View/layouts/main.php');
});

$router->post('/company/contractors/{id}/add-crew', function ($id) use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);
    $pageTitle = 'Добавить экипаж перевозчику';
    $pageContext = 'Перевозчики › Компания';

    $companyId = (int)(getSessionCompanyId() ?? 0);
    $contractorId = (int)$id;
    $errors = [];
    $old = $_POST;
    $formError = null;
    $success = false;
    $createdDriver = null;
    $createdVehicleSet = null;
    $createdBlock = null;
    $createdCrew = null;
    $drivers = [];
    $vehicleSets = [];
    $driverVehicleBlocks = [];

    if ($companyId <= 0) {
        $company = null;
        $contractor = null;
        $formError = 'Компания не найдена';
        $dbError = null;

        ob_start();
        require base_path('app/View/pages/company_contractor_add_crew.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
        return;
    }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company) {
            $company = null;
            $contractor = null;
            $formError = 'Компания не найдена';
            $dbError = null;

            ob_start();
            require base_path('app/View/pages/company_contractor_add_crew.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $pageContext = 'Перевозчики › Компания: ' . $company['name'];

        if ($company['status'] !== 'active') {
            $contractor = null;
            $formError = 'Создание недоступно';
            $dbError = null;

            ob_start();
            require base_path('app/View/pages/company_contractor_add_crew.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $dbIdentifier = $company['db_identifier'];
        $localDbConfig = $config['database'];
        $localDbConfig['database'] = $dbIdentifier;
        $localDb = new \App\Core\Database($localDbConfig);
        $localPdo = $localDb->connection();
        applyLocalMigrations($localPdo);

        // Ensure required tables
        $requiredTables = [
            'contractors' => 'database/migrations-local/003_create_company_contractors.sql',
            'drivers'     => 'database/migrations-local/004_create_company_drivers.sql',
            'vehicle_units' => 'database/migrations-local/005_create_company_vehicles.sql',
            'vehicle_sets' => 'database/migrations-local/017_create_vehicle_sets.sql',
            'driver_vehicle_blocks' => 'database/migrations-local/018_create_driver_vehicle_blocks.sql',
            'crews'       => 'database/migrations-local/006_create_company_crews.sql',
        ];
        foreach ($requiredTables as $table => $migrationFile) {
            try {
                $localPdo->query("SELECT 1 FROM `$table` LIMIT 1")->fetch();
            } catch (\Exception $e) {
                $migrationSql = file_get_contents(base_path($migrationFile));
                if ($migrationSql !== false && trim($migrationSql) !== '') {
                    $localPdo->exec($migrationSql);
                }
            }
        }

        // Handle vehicles -> vehicle_units rename if needed
        try {
            $localPdo->query("SELECT 1 FROM vehicle_units LIMIT 1")->fetch();
        } catch (\Exception $e) {
            try { $localPdo->exec("RENAME TABLE vehicles TO vehicle_units"); } catch (\Exception $renameEx) {}
        }

        // Load contractor
        $contractorStmt = $localPdo->prepare('SELECT * FROM contractors WHERE id = ?');
        $contractorStmt->execute([$contractorId]);
        $contractor = $contractorStmt->fetch(PDO::FETCH_ASSOC) ?: null;

        if (!$contractor) {
            $dbError = null;
            $formError = 'Перевозчик не найден';

            ob_start();
            require base_path('app/View/pages/company_contractor_add_crew.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        // Load entity lists for dropdowns and error re-display
        $isLogist = ($_SESSION['role_code'] ?? '') === 'logist';
        $userId = (int)$_SESSION['user_id'];

        try {
            if ($isLogist) {
                $driversStmt = $localPdo->prepare(
                    "SELECT id, full_name, phone FROM drivers WHERE status IN ('active', 'archived') AND (created_by_user_id = ? OR id IN (SELECT entity_id FROM entity_access_grants WHERE entity_type = 'driver' AND granted_to_user_id = ? AND access_level IN ('view','edit') AND revoked_at IS NULL)) ORDER BY full_name"
                );
                $driversStmt->execute([$userId, $userId]);
                $drivers = $driversStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            } else {
                $driversStmt = $localPdo->query("SELECT id, full_name, phone FROM drivers WHERE status IN ('active', 'archived') ORDER BY full_name");
                $drivers = $driversStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            }
        } catch (\Exception $e) {}
        try {
            if ($isLogist) {
                $vehicleSetsStmt = $localPdo->prepare(
                    "SELECT vs.id, vs.set_type, vu1.plate_number AS primary_plate, vu2.plate_number AS secondary_plate FROM vehicle_sets vs JOIN vehicle_units vu1 ON vs.primary_vehicle_unit_id = vu1.id LEFT JOIN vehicle_units vu2 ON vs.secondary_vehicle_unit_id = vu2.id WHERE vs.status IN ('active', 'archived') AND (vs.created_by_user_id = ? OR vs.id IN (SELECT entity_id FROM entity_access_grants WHERE entity_type = 'vehicle_set' AND granted_to_user_id = ? AND access_level IN ('view','edit') AND revoked_at IS NULL)) ORDER BY vs.id DESC"
                );
                $vehicleSetsStmt->execute([$userId, $userId]);
                $vehicleSets = $vehicleSetsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            } else {
                $vehicleSetsStmt = $localPdo->query("SELECT vs.id, vs.set_type, vu1.plate_number AS primary_plate, vu2.plate_number AS secondary_plate FROM vehicle_sets vs JOIN vehicle_units vu1 ON vs.primary_vehicle_unit_id = vu1.id LEFT JOIN vehicle_units vu2 ON vs.secondary_vehicle_unit_id = vu2.id WHERE vs.status IN ('active', 'archived') ORDER BY vs.id DESC");
                $vehicleSets = $vehicleSetsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            }
        } catch (\Exception $e) {}

        // --- Determine modes ---
        $blockMode = trim($_POST['block_mode'] ?? 'existing');

        if ($blockMode === 'new') {
            $driverMode = trim($_POST['driver_mode'] ?? 'existing');
            $vehicleMode = trim($_POST['vehicle_mode'] ?? 'existing');
        } else {
            $driverMode = '';
            $vehicleMode = '';
        }

        // --- Validation ---
        if ($blockMode === 'existing') {
            // Validate existing driver_vehicle_block
            $blockIdExisting = trim($_POST['block_id'] ?? '');
            if ($blockIdExisting === '') {
                $errors['block_id'] = 'Выберите связку Водитель+ТС';
            } else {
                $checkStmt = $localPdo->prepare(
                    'SELECT dvb.id, d.full_name AS driver_name, d.id AS driver_id,
                            vs.id AS vehicle_set_id,
                            vu1.plate_number AS primary_plate,
                            vu2.plate_number AS secondary_plate
                       FROM driver_vehicle_blocks dvb
                       JOIN drivers d ON dvb.driver_id = d.id
                       JOIN vehicle_sets vs ON dvb.vehicle_set_id = vs.id
                       JOIN vehicle_units vu1 ON vs.primary_vehicle_unit_id = vu1.id
                       LEFT JOIN vehicle_units vu2 ON vs.secondary_vehicle_unit_id = vu2.id
                      WHERE dvb.id = ? AND dvb.status = \'active\''
                );
                $checkStmt->execute([(int)$blockIdExisting]);
                $existingBlockRow = $checkStmt->fetch(PDO::FETCH_ASSOC);
                if (!$existingBlockRow) {
                    $errors['block_id'] = 'Связка Водитель+ТС не найдена или неактивна';
                } elseif ($isLogist) {
                    // Backend validation: logist can only use their own blocks
                    $bOwnerCheck = $localPdo->prepare("SELECT created_by_user_id FROM driver_vehicle_blocks WHERE id = ?");
                    $bOwnerCheck->execute([(int)$blockIdExisting]);
                    $bOwner = $bOwnerCheck->fetchColumn();
                    if ($bOwner !== false && (int)$bOwner !== $userId) {
                        $bGrantCheck = $localPdo->prepare("SELECT COUNT(*) FROM entity_access_grants WHERE entity_type = 'driver_vehicle_block' AND entity_id = ? AND granted_to_user_id = ? AND access_level IN ('view','edit') AND revoked_at IS NULL");
                        $bGrantCheck->execute([(int)$blockIdExisting, $userId]);
                        if ($bGrantCheck->fetchColumn() == 0) {
                            $errors['block_id'] = 'Связка недоступна.';
                        }
                    }
                }
            }
        } else {
            $driverFullName = trim($_POST['driver_full_name'] ?? '');
            $driverPhone = trim($_POST['driver_phone'] ?? '');
            $driverIdExisting = trim($_POST['driver_id'] ?? '');

            $plateNumber = trim($_POST['plate_number'] ?? '');
            $brand = trim($_POST['brand'] ?? '');
            $model = trim($_POST['model'] ?? '');
            $setType = trim($_POST['set_type'] ?? 'single');
            $secondaryPlateNumber = trim($_POST['secondary_plate_number'] ?? '');
            $vehicleSetIdExisting = trim($_POST['vehicle_set_id'] ?? '');

            // Driver validation
            if ($driverMode === 'existing') {
                if ($driverIdExisting === '') {
                    $errors['driver_id'] = 'Выберите водителя';
                } else {
                    $checkStmt = $localPdo->prepare('SELECT id, full_name FROM drivers WHERE id = ?');
                    $checkStmt->execute([(int)$driverIdExisting]);
                    if (!$checkStmt->fetch(PDO::FETCH_ASSOC)) {
                        $errors['driver_id'] = 'Водитель не найден';
                    }
                }
            } else {
                if ($driverFullName === '') {
                    $errors['driver_full_name'] = 'Обязательное поле';
                }
            }

            // Vehicle validation
            if ($vehicleMode === 'existing') {
                if ($vehicleSetIdExisting === '') {
                    $errors['vehicle_set_id'] = 'Выберите транспорт';
                } else {
                    $checkStmt = $localPdo->prepare('SELECT id FROM vehicle_sets WHERE id = ?');
                    $checkStmt->execute([(int)$vehicleSetIdExisting]);
                    if (!$checkStmt->fetch(PDO::FETCH_ASSOC)) {
                        $errors['vehicle_set_id'] = 'Транспорт не найден';
                    }
                }
            } else {
                if ($plateNumber === '') {
                    $errors['plate_number'] = 'Обязательное поле';
                } else {
                    $checkStmt = $localPdo->prepare('SELECT COUNT(*) FROM vehicle_units WHERE plate_number = ?');
                    $checkStmt->execute([$plateNumber]);
                    if ($checkStmt->fetchColumn() > 0) {
                        $errors['plate_number'] = 'Госномер уже используется в этой компании';
                    }
                }
                if (($setType === 'coupling' || $setType === 'road_train') && $secondaryPlateNumber === '') {
                    $errors['secondary_plate_number'] = 'Обязательно для сцепки и автопоезда';
                }
                if ($secondaryPlateNumber !== '' && empty($errors['plate_number'])) {
                    $checkStmt = $localPdo->prepare('SELECT COUNT(*) FROM vehicle_units WHERE plate_number = ?');
                    $checkStmt->execute([$secondaryPlateNumber]);
                    if ($checkStmt->fetchColumn() > 0) {
                        $errors['secondary_plate_number'] = 'Госномер уже используется в этой компании';
                    }
                }
            }
        }

        if (!empty($errors)) {
            // Re-load entity lists for error re-display
            try {
                $dvbStmt = $localPdo->prepare(
                    "SELECT dvb.id, d.full_name AS driver_name, d.id AS driver_id,
                            vs.id AS vehicle_set_id, vs.set_type,
                            vu1.plate_number AS primary_plate,
                            vu2.plate_number AS secondary_plate
                       FROM driver_vehicle_blocks dvb
                       JOIN drivers d ON dvb.driver_id = d.id
                       JOIN vehicle_sets vs ON dvb.vehicle_set_id = vs.id
                       JOIN vehicle_units vu1 ON vs.primary_vehicle_unit_id = vu1.id
                       LEFT JOIN vehicle_units vu2 ON vs.secondary_vehicle_unit_id = vu2.id
                      WHERE dvb.status = 'active'
                        AND dvb.id NOT IN (
                            SELECT driver_vehicle_block_id FROM crews
                             WHERE contractor_id = ? AND status != 'archived'
                        )
                      ORDER BY d.full_name"
                );
                $dvbStmt->execute([$contractorId]);
                $driverVehicleBlocks = $dvbStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            } catch (\Exception $e) {}
            $dbError = null;
            ob_start();
            require base_path('app/View/pages/company_contractor_add_crew.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        // --- Transactional creation ---
        $localPdo->beginTransaction();
        try {
            $uid = (int)$_SESSION['user_id'];
            $role = $_SESSION['role_code'];

            if ($blockMode === 'existing') {
                // Use existing driver_vehicle_block — just create crew
                $blockId = (int)$blockIdExisting;
                $driverNameForSuccess = $existingBlockRow['driver_name'] ?? '';
                $driverId = (int)($existingBlockRow['driver_id'] ?? 0);
                $vehicleSetId = (int)($existingBlockRow['vehicle_set_id'] ?? 0);
                $vehiclePrimaryPlateForSuccess = $existingBlockRow['primary_plate'] ?? '';
                $vehicleSecondaryPlateForSuccess = (!empty($existingBlockRow['secondary_plate']) ? $existingBlockRow['secondary_plate'] : null);
            } else {
                // 1. Driver — use existing or create new
                $driverId = null;
                $driverNameForSuccess = '';
                if ($driverMode === 'existing') {
                    $driverId = (int)$driverIdExisting;
                    $drvStmt = $localPdo->prepare('SELECT full_name FROM drivers WHERE id = ?');
                    $drvStmt->execute([$driverId]);
                    $drvRow = $drvStmt->fetch(PDO::FETCH_ASSOC);
                    $driverNameForSuccess = $drvRow['full_name'] ?? '';
                } else {
                    $insertDriver = $localPdo->prepare(
                        'INSERT INTO drivers (full_name, phone, status, created_by_user_id, created_by_role)
                         VALUES (:full_name, :phone, :status, :uid, :role)'
                    );
                    $insertDriver->execute([
                        ':full_name' => $driverFullName,
                        ':phone'     => $driverPhone !== '' ? $driverPhone : '',
                        ':status'    => 'active',
                        ':uid'       => $uid,
                        ':role'      => $role,
                    ]);
                    $driverId = (int)$localPdo->lastInsertId();
                    $driverNameForSuccess = $driverFullName;
                }

                // 2. Vehicle — use existing or create new
                $vehicleSetId = null;
                $vehiclePrimaryPlateForSuccess = '';
                $vehicleSecondaryPlateForSuccess = null;
                if ($vehicleMode === 'existing') {
                    $vehicleSetId = (int)$vehicleSetIdExisting;
                    $vsStmt = $localPdo->prepare(
                        'SELECT vu1.plate_number AS primary_plate, vu2.plate_number AS secondary_plate
                           FROM vehicle_sets vs
                           JOIN vehicle_units vu1 ON vs.primary_vehicle_unit_id = vu1.id
                           LEFT JOIN vehicle_units vu2 ON vs.secondary_vehicle_unit_id = vu2.id
                          WHERE vs.id = ?'
                    );
                    $vsStmt->execute([$vehicleSetId]);
                    $vsRow = $vsStmt->fetch(PDO::FETCH_ASSOC);
                    $vehiclePrimaryPlateForSuccess = $vsRow['primary_plate'] ?? '';
                    $vehicleSecondaryPlateForSuccess = !empty($vsRow['secondary_plate']) ? $vsRow['secondary_plate'] : null;
                } else {
                    $insertPrimaryUnit = $localPdo->prepare(
                        'INSERT INTO vehicle_units (plate_number, brand, model, unit_type, status, created_by_user_id, created_by_role)
                         VALUES (:plate_number, :brand, :model, :unit_type, :status, :uid, :role)'
                    );
                    $insertPrimaryUnit->execute([
                        ':plate_number' => $plateNumber,
                        ':brand'        => $brand !== '' ? $brand : null,
                        ':model'        => $model !== '' ? $model : null,
                        ':unit_type'    => 'tractor',
                        ':status'       => 'active',
                        ':uid'          => $uid,
                        ':role'         => $role,
                    ]);
                    $primaryUnitId = (int)$localPdo->lastInsertId();

                    $secondaryUnitId = null;
                    if ($secondaryPlateNumber !== '') {
                        $insertSecondaryUnit = $localPdo->prepare(
                            'INSERT INTO vehicle_units (plate_number, unit_type, status, created_by_user_id, created_by_role)
                             VALUES (:plate_number, :unit_type, :status, :uid, :role)'
                        );
                        $insertSecondaryUnit->execute([
                            ':plate_number' => $secondaryPlateNumber,
                            ':unit_type'    => 'trailer',
                            ':status'       => 'active',
                            ':uid'          => $uid,
                            ':role'         => $role,
                        ]);
                        $secondaryUnitId = (int)$localPdo->lastInsertId();
                    }

                    $insertVehicleSet = $localPdo->prepare(
                        'INSERT INTO vehicle_sets (set_type, primary_vehicle_unit_id, secondary_vehicle_unit_id, status, created_by_user_id, created_by_role)
                         VALUES (:set_type, :primary_id, :secondary_id, :status, :uid, :role)'
                    );
                    $insertVehicleSet->execute([
                        ':set_type'     => $setType,
                        ':primary_id'   => $primaryUnitId,
                        ':secondary_id' => $secondaryUnitId,
                        ':status'       => 'active',
                        ':uid'          => $uid,
                        ':role'         => $role,
                    ]);
                    $vehicleSetId = (int)$localPdo->lastInsertId();
                    $vehiclePrimaryPlateForSuccess = $plateNumber;
                    $vehicleSecondaryPlateForSuccess = $secondaryPlateNumber !== '' ? $secondaryPlateNumber : null;
                }

                // 3. Find or create driver_vehicle_block
                $existingBlock = $localPdo->prepare('SELECT id FROM driver_vehicle_blocks WHERE driver_id = ? AND vehicle_set_id = ?');
                $existingBlock->execute([$driverId, $vehicleSetId]);
                $blockRow = $existingBlock->fetch(PDO::FETCH_ASSOC);
                if ($blockRow) {
                    $blockId = (int)$blockRow['id'];
                } else {
                    $insertBlock = $localPdo->prepare(
                        'INSERT INTO driver_vehicle_blocks (driver_id, vehicle_set_id, status, created_by_user_id, created_by_role)
                         VALUES (:driver_id, :vehicle_set_id, :status, :uid, :role)'
                    );
                    $insertBlock->execute([
                        ':driver_id'       => $driverId,
                        ':vehicle_set_id'  => $vehicleSetId,
                        ':status'          => 'active',
                        ':uid'             => $uid,
                        ':role'            => $role,
                    ]);
                    $blockId = (int)$localPdo->lastInsertId();
                }
            }

            // 4. Create crew (with duplicate check)
            $existingCrew = $localPdo->prepare('SELECT id FROM crews WHERE contractor_id = ? AND driver_vehicle_block_id = ?');
            $existingCrew->execute([$contractorId, $blockId]);
            $crewRow = $existingCrew->fetch(PDO::FETCH_ASSOC);
            if ($crewRow) {
                $crewId = (int)$crewRow['id'];
            } else {
                $insertCrew = $localPdo->prepare(
                    'INSERT INTO crews (contractor_id, driver_vehicle_block_id, status, created_by_user_id, created_by_role)
                     VALUES (:contractor_id, :driver_vehicle_block_id, :status, :uid, :role)'
                );
                $insertCrew->execute([
                    ':contractor_id'           => $contractorId,
                    ':driver_vehicle_block_id' => $blockId,
                    ':status'                  => 'active',
                    ':uid'                     => $uid,
                    ':role'                    => $role,
                ]);
                $crewId = (int)$localPdo->lastInsertId();
            }

            $localPdo->commit();

            $createdDriver = ['id' => $driverId, 'full_name' => $driverNameForSuccess];
            $createdVehicleSet = [
                'id'              => $vehicleSetId,
                'primary_plate'   => $vehiclePrimaryPlateForSuccess,
                'secondary_plate' => $vehicleSecondaryPlateForSuccess,
            ];
            $createdBlock = ['id' => $blockId];
            $createdCrew = ['id' => $crewId];
            $success = true;
            $dbError = null;
        } catch (\Exception $e) {
            $localPdo->rollBack();
            throw $e;
        }
    } catch (\Exception $e) {
        $company = $company ?? null;
        $contractor = $contractor ?? null;
        $formError = 'Ошибка создания: ' . $e->getMessage();
        $dbError = null;
    }

    // Re-load entity lists for view in case of error
    if (!isset($drivers) || !is_array($drivers)) { $drivers = []; }
    if (!isset($vehicleSets) || !is_array($vehicleSets)) { $vehicleSets = []; }

    ob_start();
    require base_path('app/View/pages/company_contractor_add_crew.php');
    $content = ob_get_clean();
    require base_path('app/View/layouts/main.php');
});

$router->get('/company/contractors/{id}', function ($id) use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);
    $pageTitle = 'Перевозчик';
    $pageContext = 'Перевозчики › Компания';

    $companyId = (int)(getSessionCompanyId() ?? 0);
    $archiveError = null;
    $grants = [];
    $logists = [];
    $crewBlocks = [];

    if ($companyId <= 0) {
        $company = null;
        $contractor = null;
        $dbError = null;

        ob_start();
        require base_path('app/View/pages/company_contractor_view.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
        return;
    }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company) {
            $company = null;
            $contractor = null;
            $dbError = null;

            ob_start();
            require base_path('app/View/pages/company_contractor_view.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $pageContext = 'Перевозчики › Компания: ' . $company['name'];

        if ($company['status'] !== 'active') {
            $contractor = null;
            $dbError = null;

            ob_start();
            require base_path('app/View/pages/company_contractor_view.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $dbIdentifier = $company['db_identifier'];
        $localDbConfig = $config['database'];
        $localDbConfig['database'] = $dbIdentifier;
        $localDb = new \App\Core\Database($localDbConfig);
        $localPdo = $localDb->connection();
                applyLocalMigrations($localPdo);

        try {
            $localPdo->query("SELECT 1 FROM contractors LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $migrationSql = file_get_contents(base_path('database/migrations-local/003_create_company_contractors.sql'));
            $localPdo->exec($migrationSql);
        }

        try {
            $localPdo->query("SELECT created_by_user_id FROM contractors LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $localPdo->exec("ALTER TABLE contractors ADD COLUMN created_by_user_id INT UNSIGNED DEFAULT NULL, ADD COLUMN created_by_role VARCHAR(20) DEFAULT NULL");
        }

        try {
            $localPdo->query("SELECT 1 FROM entity_access_grants LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $migrationSql = file_get_contents(base_path('database/migrations-local/009_create_entity_access_grants.sql'));
            $localPdo->exec($migrationSql);
        }

        $contractorStmt = $localPdo->prepare('SELECT * FROM contractors WHERE id = ?');
        $contractorStmt->execute([(int) $id]);
        $contractor = $contractorStmt->fetch(PDO::FETCH_ASSOC) ?: null;

        // Role-based access check
        $accessDenied = null;
        $createdByUser = null;
        $updatedByUser = null;
        if ($contractor) {
            $isLogist = ($_SESSION['role_code'] ?? '') === 'logist';
            if ($isLogist) {
                $userId = (int)$_SESSION['user_id'];
                $hasGrant = false;
                $grantCheck = $localPdo->prepare("SELECT access_level FROM entity_access_grants WHERE entity_type = 'contractor' AND entity_id = ? AND granted_to_user_id = ? AND revoked_at IS NULL LIMIT 1");
                $grantCheck->execute([(int)$id, $userId]);
                $grantRow = $grantCheck->fetch(PDO::FETCH_ASSOC);
                $hasGrant = ($grantRow && in_array($grantRow['access_level'], ['view', 'edit']));
                if ((int)$contractor['created_by_user_id'] !== $userId && !$hasGrant) {
                    $accessDenied = 'У вас нет доступа к этой записи.';
                }
            }
            // Load created/updated by user names
            $createdByUser = $localPdo->prepare("SELECT full_name FROM users WHERE id = ?");
            $createdByUser->execute([(int)$contractor['created_by_user_id']]);
            $createdByUser = $createdByUser->fetchColumn() ?: null;
            if (!empty($contractor['updated_by_user_id'])) {
                $updatedByUser = $localPdo->prepare("SELECT full_name FROM users WHERE id = ?");
                $updatedByUser->execute([(int)$contractor['updated_by_user_id']]);
                $updatedByUser = $updatedByUser->fetchColumn() ?: null;
            }
        }

        if ($contractor && !$accessDenied) {
            $pageTitle = 'Перевозчик: ' . $contractor['name'];
        }

        $contacts = [];
        if ($contractor && !$accessDenied) {
            $contacts = ContractorContactService::loadByContractorId($localPdo, (int) $id);
        }

        // Load tax history
        $taxHistory = [];
        if ($contractor && !$accessDenied) {
            $taxHistory = $localPdo->prepare("SELECT * FROM contractor_tax_history WHERE contractor_id = ? ORDER BY effective_from DESC, id DESC");
            $taxHistory->execute([(int)$id]);
            $taxHistory = $taxHistory->fetchAll(PDO::FETCH_ASSOC);
        }

        // Load crew blocks (Водители+ТС)
        if ($contractor && !$accessDenied) {
            try {
                $crewBlockStmt = $localPdo->prepare(
                    "SELECT c.id AS crew_id, dvb.id AS block_id, dvb.status AS block_status,
                            d.full_name AS driver_name, d.id AS driver_id,
                            vs.id AS vehicle_set_id,
                            vs.primary_vehicle_unit_id, vs.secondary_vehicle_unit_id
                     FROM crews c
                     JOIN driver_vehicle_blocks dvb ON c.driver_vehicle_block_id = dvb.id
                     JOIN drivers d ON dvb.driver_id = d.id
                     JOIN vehicle_sets vs ON dvb.vehicle_set_id = vs.id
                     WHERE c.contractor_id = ? AND c.status != 'archived'
                     ORDER BY d.full_name"
                );
                $crewBlockStmt->execute([(int)$id]);
                $crewBlocks = $crewBlockStmt->fetchAll(PDO::FETCH_ASSOC);

                // Load vehicle plates
                if (!empty($crewBlocks)) {
                    $vehicleUnitIds = [];
                    foreach ($crewBlocks as $cb) {
                        if (!empty($cb['primary_vehicle_unit_id'])) {
                            $vehicleUnitIds[] = (int)$cb['primary_vehicle_unit_id'];
                        }
                        if (!empty($cb['secondary_vehicle_unit_id'])) {
                            $vehicleUnitIds[] = (int)$cb['secondary_vehicle_unit_id'];
                        }
                    }
                    $vehicleUnitIds = array_unique($vehicleUnitIds);
                    $vehicleUnitIds = array_values($vehicleUnitIds);

                    $plateMap = [];
                    if (!empty($vehicleUnitIds)) {
                        $placeholders = implode(',', array_fill(0, count($vehicleUnitIds), '?'));
                        $plateStmt = $localPdo->prepare("SELECT id, plate_number FROM vehicle_units WHERE id IN ($placeholders)");
                        $plateStmt->execute($vehicleUnitIds);
                        while ($row = $plateStmt->fetch(PDO::FETCH_ASSOC)) {
                            $plateMap[$row['id']] = $row['plate_number'];
                        }
                    }

                    // Enrich crewBlocks with plate info
                    foreach ($crewBlocks as &$cb) {
                        $primaryPlate = $plateMap[$cb['primary_vehicle_unit_id']] ?? null;
                        $secondaryPlate = !empty($cb['secondary_vehicle_unit_id'])
                            ? ($plateMap[$cb['secondary_vehicle_unit_id']] ?? null)
                            : null;

                        $cb['vehicle_plate'] = $primaryPlate ?: 'ТС #' . $cb['vehicle_set_id'];
                        $cb['secondary_plate'] = $secondaryPlate;
                    }
                    unset($cb);
                }
            } catch (\Exception $e) {
                $crewBlocks = [];
            }
        }

        $grants = [];
        $logists = [];
        $dbError = null;
    } catch (\Exception $e) {
        $company = $company ?? null;
        $contractor = null;
        $grants = [];
        $logists = [];
        $crewBlocks = [];
        $dbError = 'Не удалось загрузить перевозчика: ' . $e->getMessage();
    }

    ob_start();
    require base_path('app/View/pages/company_contractor_view.php');
    $content = ob_get_clean();
    require base_path('app/View/layouts/main.php');
});

$router->get('/company/contractors/{id}/edit', function ($id) use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);
    $pageTitle = 'Редактировать перевозчика';
    $pageContext = 'Перевозчики › Компания';

    $companyId = (int)(getSessionCompanyId() ?? 0);

    if ($companyId <= 0) {
        $company = null;
        $contractor = null;
        $contacts = [];
        $errors = [];
        $old = ['contacts' => contractorFormDefaultContacts()];
        $formError = null;

        ob_start();
        require base_path('app/View/pages/company_contractor_edit.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
        return;
    }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company) {
            $company = null;
            $contractor = null;
            $contacts = [];
            $errors = [];
            $old = ['contacts' => contractorFormDefaultContacts()];
            $formError = null;

            ob_start();
            require base_path('app/View/pages/company_contractor_edit.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $pageContext = 'Перевозчики › Компания: ' . $company['name'];

        if ($company['status'] !== 'active') {
            $contractor = null;
            $contacts = [];
            $errors = [];
            $old = ['contacts' => contractorFormDefaultContacts()];
            $formError = null;

            ob_start();
            require base_path('app/View/pages/company_contractor_edit.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $dbIdentifier = $company['db_identifier'];
        $localDbConfig = $config['database'];
        $localDbConfig['database'] = $dbIdentifier;
        $localDb = new \App\Core\Database($localDbConfig);
        $localPdo = $localDb->connection();
                applyLocalMigrations($localPdo);

        try {
            $localPdo->query("SELECT 1 FROM contractors LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $migrationSql = file_get_contents(base_path('database/migrations-local/003_create_company_contractors.sql'));
            $localPdo->exec($migrationSql);
        }

        $contractorStmt = $localPdo->prepare('SELECT * FROM contractors WHERE id = ?');
        $contractorStmt->execute([(int) $id]);
        $contractor = $contractorStmt->fetch(PDO::FETCH_ASSOC) ?: null;

        if (!$contractor) {
            $contractor = null;
            $contacts = [];
            $errors = [];
            $old = ['contacts' => contractorFormDefaultContacts()];
            $formError = null;

            ob_start();
            require base_path('app/View/pages/company_contractor_edit.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $contacts = ContractorContactService::loadByContractorId($localPdo, (int) $id);
        $errors = [];
        $old = $contractor;
        $old['contacts'] = $contacts !== [] ? $contacts : contractorFormDefaultContacts();
        $formError = null;

        ob_start();
        require base_path('app/View/pages/company_contractor_edit.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
    } catch (\Exception $e) {
        $company = $company ?? null;
        $contractor = null;
        $errors = [];
        $old = [];
        $formError = 'Не удалось загрузить перевозчика: ' . $e->getMessage();

        ob_start();
        require base_path('app/View/pages/company_contractor_edit.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
    }
});

$router->post('/company/contractors/{id}/edit', function ($id) use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);
    $pageTitle = 'Редактировать перевозчика';
    $pageContext = 'Перевозчики › Компания';

    $companyId = (int)(getSessionCompanyId() ?? 0);

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
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

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

        $dbIdentifier = $company['db_identifier'];
        $localDbConfig = $config['database'];
        $localDbConfig['database'] = $dbIdentifier;
        $localDb = new \App\Core\Database($localDbConfig);
        $localPdo = $localDb->connection();
                applyLocalMigrations($localPdo);

        try {
            $localPdo->query("SELECT 1 FROM contractors LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $migrationSql = file_get_contents(base_path('database/migrations-local/003_create_company_contractors.sql'));
            $localPdo->exec($migrationSql);
        }

        $contractorStmt = $localPdo->prepare('SELECT * FROM contractors WHERE id = ?');
        $contractorStmt->execute([(int) $id]);
        $contractor = $contractorStmt->fetch(PDO::FETCH_ASSOC) ?: null;

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

        // Access check for logist
        $isLogist = ($_SESSION['role_code'] ?? '') === 'logist';
        if ($isLogist) {
            $userId = (int)$_SESSION['user_id'];
            $hasGrantEdit = false;
            $gc = $localPdo->prepare("SELECT access_level FROM entity_access_grants WHERE entity_type = 'contractor' AND entity_id = ? AND granted_to_user_id = ? AND revoked_at IS NULL LIMIT 1");
            $gc->execute([(int)$id, $userId]);
            $gr = $gc->fetch(PDO::FETCH_ASSOC);
            $hasGrantEdit = ($gr && $gr['access_level'] === 'edit');
            if ((int)$contractor['created_by_user_id'] !== $userId && !$hasGrantEdit) {
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
        $old = $_POST;
        $formError = null;

        $contactPayload = ContractorContactService::normalizeSubmittedContacts($_POST['contacts'] ?? []);
        $submittedContacts = $contactPayload['contacts'];
        if (!empty($contactPayload['errors'])) {
            $errors['contacts'] = $contactPayload['errors'];
        }

        $name = trim($_POST['name'] ?? '');
        $inn  = trim($_POST['inn'] ?? '');

        if ($name === '') {
            $errors['name'] = 'Обязательное поле';
        }

        if ($inn === '') {
            $errors['inn'] = 'Обязательное поле';
        } else {
            $dupStmt = $localPdo->prepare('SELECT COUNT(*) FROM contractors WHERE inn = ? AND id != ?');
            $dupStmt->execute([$inn, (int) $id]);
            if ($dupStmt->fetchColumn() > 0) {
                $errors['inn'] = 'ИНН уже используется';
            }
        }

        if (!empty($errors)) {
            ob_start();
            require base_path('app/View/pages/company_contractor_edit.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
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
            ':name'             => $name,
            ':inn'              => $inn,
            ':kpp'              => $_POST['kpp'] ?? null,
            ':ogrn'             => $_POST['ogrn'] ?? null,
            ':contractor_type'  => $_POST['contractor_type'] ?? null,
            ':legal_address'    => $_POST['legal_address'] ?? null,
            ':physical_address' => $_POST['physical_address'] ?? null,
            ':bank_account'     => $_POST['bank_account'] ?? null,
            ':bank_name'        => $_POST['bank_name'] ?? null,
            ':bank_bik'         => $_POST['bank_bik'] ?? null,
            ':bank_corr_account'=> $_POST['bank_corr_account'] ?? null,
            ':status'           => $_POST['status'] ?? $contractor['status'],
            ':comments'         => $_POST['comments'] ?? null,
            ':updated_by_user_id' => (int)$_SESSION['user_id'],
            ':updated_by_role'  => $_SESSION['role_code'] ?? null,
            ':id'               => (int) $id,
        ]);

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
});

$router->post('/company/contractors/{id}/archive', function ($id) use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);
    $pageTitle = 'Перевозчик';
    $pageContext = 'Перевозчики › Компания';

    $companyId = (int)(getSessionCompanyId() ?? 0);
    $archiveError = null;

    if ($companyId <= 0) {
        $company = null;
        $contractor = null;
        $dbError = null;

        ob_start();
        require base_path('app/View/pages/company_contractor_view.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
        return;
    }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company) {
            $company = null;
            $contractor = null;
            $dbError = null;

            ob_start();
            require base_path('app/View/pages/company_contractor_view.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $pageContext = 'Перевозчики › Компания: ' . $company['name'];

        if ($company['status'] !== 'active') {
            $contractor = null;
            $dbError = null;

            ob_start();
            require base_path('app/View/pages/company_contractor_view.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $dbIdentifier = $company['db_identifier'];
        $localDbConfig = $config['database'];
        $localDbConfig['database'] = $dbIdentifier;
        $localDb = new \App\Core\Database($localDbConfig);
        $localPdo = $localDb->connection();
                applyLocalMigrations($localPdo);

        try {
            $localPdo->query("SELECT 1 FROM contractors LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $migrationSql = file_get_contents(base_path('database/migrations-local/003_create_company_contractors.sql'));
            $localPdo->exec($migrationSql);
        }

        $contractorStmt = $localPdo->prepare('SELECT * FROM contractors WHERE id = ?');
        $contractorStmt->execute([(int) $id]);
        $contractor = $contractorStmt->fetch(PDO::FETCH_ASSOC) ?: null;

        if (!$contractor) {
            $contractor = null;
            $dbError = null;

            ob_start();
            require base_path('app/View/pages/company_contractor_view.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        // Archive access check: logist can only archive own records
        $isLogist = ($_SESSION['role_code'] ?? '') === 'logist';
        if ($isLogist) {
            $userId = (int)$_SESSION['user_id'];
            if ((int)$contractor['created_by_user_id'] !== $userId) {
                $archiveError = 'Логист может архивировать только записи, созданные им самим.';
                $dbError = null;
                ob_start();
                require base_path('app/View/pages/company_contractor_view.php');
                $content = ob_get_clean();
                require base_path('app/View/layouts/main.php');
                return;
            }
        }

        $pageTitle = 'Перевозчик: ' . $contractor['name']; // archive handler

        try {
            $localPdo->query("SELECT 1 FROM crews LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $migrationSql = file_get_contents(base_path('database/migrations-local/006_create_company_crews.sql'));
            $localPdo->exec($migrationSql);
        }

        $crewCheck = $localPdo->prepare('SELECT COUNT(*) FROM crews WHERE contractor_id = ?');
        $crewCheck->execute([(int) $id]);
        if ($crewCheck->fetchColumn() > 0) {
            $archiveError = 'Перевозчик участвует в экипажах. Сначала удалите экипажи.';
            $dbError = null;

            ob_start();
            require base_path('app/View/pages/company_contractor_view.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $update = $localPdo->prepare("UPDATE contractors SET status = 'archived' WHERE id = ?");
        $update->execute([(int) $id]);

        header('Location: /company/contractors');
        exit;
    } catch (\Exception $e) {
        $company = $company ?? null;
        $contractor = null;
        $dbError = 'Ошибка архивирования: ' . $e->getMessage();

        ob_start();
        require base_path('app/View/pages/company_contractor_view.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
    }
});

$router->get('/company/contractors/{id}/modal-view', function ($id) use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);

    $renderMessage = static function (string $message): void {
        echo '<div class="modal-body"><div class="notice warn" style="margin:16px">' . e($message) . '</div></div>';
        echo '<div class="modal-foot is-spaced"><div class="modal-foot-actions"><button type="button" class="btn btn-ghost" data-contractor-view-close-btn>Закрыть</button></div></div>';
    };

    $companyId = (int) (getSessionCompanyId() ?? 0);
    if ($companyId <= 0) {
        $renderMessage('Компания не найдена.');
        return;
    }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$company || ($company['status'] ?? '') !== 'active') {
            $renderMessage('Компания недоступна.');
            return;
        }

        $localDbConfig = $config['database'];
        $localDbConfig['database'] = $company['db_identifier'];
        $localPdo = (new \App\Core\Database($localDbConfig))->connection();
        applyLocalMigrations($localPdo);
        try {
            $localPdo->query("SELECT 1 FROM contractors LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $localPdo->exec(file_get_contents(base_path('database/migrations-local/003_create_company_contractors.sql')));
        }
        try {
            $localPdo->query("SELECT created_by_user_id FROM contractors LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $localPdo->exec("ALTER TABLE contractors ADD COLUMN created_by_user_id INT UNSIGNED DEFAULT NULL, ADD COLUMN created_by_role VARCHAR(20) DEFAULT NULL");
        }
        try {
            $localPdo->query("SELECT 1 FROM entity_access_grants LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $localPdo->exec(file_get_contents(base_path('database/migrations-local/009_create_entity_access_grants.sql')));
        }

        $contractorStmt = $localPdo->prepare('SELECT * FROM contractors WHERE id = ?');
        $contractorStmt->execute([(int) $id]);
        $contractor = $contractorStmt->fetch(PDO::FETCH_ASSOC) ?: null;
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
});

$router->get('/company/contractors/{id}/modal-edit', function ($id) use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);

    $renderMessage = static function (string $message): void {
        echo '<div class="modal-body"><div class="notice warn" style="margin:16px">' . e($message) . '</div></div>';
        echo '<div class="modal-foot is-spaced"><div class="modal-foot-actions"><button type="button" class="btn btn-ghost" data-contractor-cancel-edit-btn>Закрыть</button></div></div>';
    };

    $companyId = (int) (getSessionCompanyId() ?? 0);
    if ($companyId <= 0) {
        $renderMessage('Компания не найдена.');
        return;
    }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$company || ($company['status'] ?? '') !== 'active') {
            $renderMessage('Компания недоступна.');
            return;
        }

        $localDbConfig = $config['database'];
        $localDbConfig['database'] = $company['db_identifier'];
        $localPdo = (new \App\Core\Database($localDbConfig))->connection();
        applyLocalMigrations($localPdo);
        try {
            $localPdo->query("SELECT 1 FROM contractors LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $localPdo->exec(file_get_contents(base_path('database/migrations-local/003_create_company_contractors.sql')));
        }
        try {
            $localPdo->query("SELECT created_by_user_id FROM contractors LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $localPdo->exec("ALTER TABLE contractors ADD COLUMN created_by_user_id INT UNSIGNED DEFAULT NULL, ADD COLUMN created_by_role VARCHAR(20) DEFAULT NULL");
        }
        try {
            $localPdo->query("SELECT 1 FROM entity_access_grants LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $localPdo->exec(file_get_contents(base_path('database/migrations-local/009_create_entity_access_grants.sql')));
        }

        $contractorStmt = $localPdo->prepare('SELECT * FROM contractors WHERE id = ?');
        $contractorStmt->execute([(int) $id]);
        $contractor = $contractorStmt->fetch(PDO::FETCH_ASSOC) ?: null;
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
});

$router->post('/company/contractors/{id}/modal-edit', function ($id) use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);

    $renderMessage = static function (string $message): void {
        echo '<div class="modal-body"><div class="notice warn" style="margin:16px">' . e($message) . '</div></div>';
        echo '<div class="modal-foot is-spaced"><div class="modal-foot-actions"><button type="button" class="btn btn-ghost" data-contractor-view-close-btn>Закрыть</button></div></div>';
    };

    $companyId = (int) (getSessionCompanyId() ?? 0);
    if ($companyId <= 0) {
        $renderMessage('Компания не найдена.');
        return;
    }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$company || ($company['status'] ?? '') !== 'active') {
            $renderMessage('Компания недоступна.');
            return;
        }

        $localDbConfig = $config['database'];
        $localDbConfig['database'] = $company['db_identifier'];
        $localPdo = (new \App\Core\Database($localDbConfig))->connection();
        applyLocalMigrations($localPdo);
        try {
            $localPdo->query("SELECT 1 FROM contractors LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $localPdo->exec(file_get_contents(base_path('database/migrations-local/003_create_company_contractors.sql')));
        }
        try {
            $localPdo->query("SELECT created_by_user_id FROM contractors LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $localPdo->exec("ALTER TABLE contractors ADD COLUMN created_by_user_id INT UNSIGNED DEFAULT NULL, ADD COLUMN created_by_role VARCHAR(20) DEFAULT NULL");
        }
        try {
            $localPdo->query("SELECT 1 FROM entity_access_grants LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $localPdo->exec(file_get_contents(base_path('database/migrations-local/009_create_entity_access_grants.sql')));
        }

        $contractorStmt = $localPdo->prepare('SELECT * FROM contractors WHERE id = ?');
        $contractorStmt->execute([(int) $id]);
        $contractor = $contractorStmt->fetch(PDO::FETCH_ASSOC) ?: null;
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

        $contractorStmt->execute([(int) $id]);
        $contractor = $contractorStmt->fetch(PDO::FETCH_ASSOC) ?: $contractor;
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
});

$router->post('/company/contractors/{id}/modal-archive', function ($id) use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);
    header('Content-Type: application/json; charset=UTF-8');

    try {
        $companyId = (int) (getSessionCompanyId() ?? 0);
        if ($companyId <= 0) {
            throw new \RuntimeException('Компания не найдена.');
        }

        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$company || ($company['status'] ?? '') !== 'active') {
            throw new \RuntimeException('Компания недоступна.');
        }

        $localDbConfig = $config['database'];
        $localDbConfig['database'] = $company['db_identifier'];
        $localPdo = (new \App\Core\Database($localDbConfig))->connection();
        applyLocalMigrations($localPdo);
        try {
            $localPdo->query("SELECT 1 FROM contractors LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $localPdo->exec(file_get_contents(base_path('database/migrations-local/003_create_company_contractors.sql')));
        }
        try {
            $localPdo->query("SELECT created_by_user_id FROM contractors LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $localPdo->exec("ALTER TABLE contractors ADD COLUMN created_by_user_id INT UNSIGNED DEFAULT NULL, ADD COLUMN created_by_role VARCHAR(20) DEFAULT NULL");
        }
        try {
            $localPdo->query("SELECT 1 FROM crews LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $localPdo->exec(file_get_contents(base_path('database/migrations-local/006_create_company_crews.sql')));
        }

        $contractorStmt = $localPdo->prepare('SELECT * FROM contractors WHERE id = ?');
        $contractorStmt->execute([(int) $id]);
        $contractor = $contractorStmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if (!$contractor) {
            throw new \RuntimeException('Перевозчик не найден.');
        }

        $roleCode = (string) ($_SESSION['role_code'] ?? '');
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        if ($roleCode === 'logist' && (int) ($contractor['created_by_user_id'] ?? 0) !== $userId) {
            throw new \RuntimeException('Логист может архивировать только записи, созданные им самим.');
        }

        $crewCheck = $localPdo->prepare("SELECT COUNT(*) FROM crews WHERE contractor_id = ? AND status != 'archived'");
        $crewCheck->execute([(int) $id]);
        if ((int) $crewCheck->fetchColumn() > 0) {
            throw new \RuntimeException('Перевозчик участвует в экипажах. Сначала удалите экипажи.');
        }

        $update = $localPdo->prepare("UPDATE contractors SET status = 'archived' WHERE id = ?");
        $update->execute([(int) $id]);

        echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
    } catch (\Throwable $e) {
        http_response_code(200);
        echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
});

// --- Contractor Contacts CRUD ---

$router->post('/company/contractors/{contractor_id}/contacts/create', function ($contractor_id) use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);
    $contractor_id = (int)$contractor_id;
    $companyId = (int)(getSessionCompanyId() ?? 0);
    $redirect = '/company/contractors/' . $contractor_id;

    if ($companyId <= 0) { header('Location: ' . $redirect); exit; }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$company || $company['status'] !== 'active') { header('Location: ' . $redirect); exit; }

        $dbIdentifier = $company['db_identifier'];
        $localDbConfig = $config['database']; $localDbConfig['database'] = $dbIdentifier;
        $localDb = new \App\Core\Database($localDbConfig); $localPdo = $localDb->connection();
        applyLocalMigrations($localPdo);

        $cStmt = $localPdo->prepare('SELECT * FROM contractors WHERE id = ?');
        $cStmt->execute([$contractor_id]);
        $contractor = $cStmt->fetch(PDO::FETCH_ASSOC);
        if (!$contractor) { header('Location: /company/contractors'); exit; }

        // Access check
        $isLogist = ($_SESSION['role_code'] ?? '') === 'logist';
        if ($isLogist) {
            $userId = (int)$_SESSION['user_id'];
            $hasGrant = false;
            $gc = $localPdo->prepare("SELECT access_level FROM entity_access_grants WHERE entity_type = 'contractor' AND entity_id = ? AND granted_to_user_id = ? AND revoked_at IS NULL LIMIT 1");
            $gc->execute([$contractor_id, $userId]);
            $gr = $gc->fetch(PDO::FETCH_ASSOC);
            $hasGrant = ($gr && $gr['access_level'] === 'edit');
            if ((int)$contractor['created_by_user_id'] !== $userId && !$hasGrant) { header('Location: ' . $redirect); exit; }
        }

        $contactPerson = trim($_POST['contact_person'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $comment = trim($_POST['comment'] ?? '');

        // Check if this is the first contact
        $cntStmt = $localPdo->prepare('SELECT COUNT(*) FROM contractor_contacts WHERE contractor_id = ?');
        $cntStmt->execute([$contractor_id]);
        $isFirst = ($cntStmt->fetchColumn() == 0);

        $isPrimary = $isFirst ? 1 : 0;
        $isDocEmail = ($isFirst && !empty($email)) ? 1 : 0;

        $insert = $localPdo->prepare(
            'INSERT INTO contractor_contacts (contractor_id, contact_person, phone, email, is_primary, is_document_email, comment, created_by_user_id, created_by_role)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $insert->execute([$contractor_id, $contactPerson ?: null, $phone ?: null, $email ?: null, $isPrimary, $isDocEmail, $comment ?: null, (int)$_SESSION['user_id'], $_SESSION['role_code'] ?? null]);
    } catch (\Exception $e) {}

    header('Location: ' . $redirect);
    exit;
});

$router->post('/company/contractors/{contractor_id}/contacts/{contact_id}/edit', function ($contractor_id, $contact_id) use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);
    $contractor_id = (int)$contractor_id; $contact_id = (int)$contact_id;
    $companyId = (int)(getSessionCompanyId() ?? 0);
    $redirect = '/company/contractors/' . $contractor_id;

    if ($companyId <= 0) { header('Location: ' . $redirect); exit; }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$company || $company['status'] !== 'active') { header('Location: ' . $redirect); exit; }

        $dbIdentifier = $company['db_identifier'];
        $localDbConfig = $config['database']; $localDbConfig['database'] = $dbIdentifier;
        $localDb = new \App\Core\Database($localDbConfig); $localPdo = $localDb->connection();
        applyLocalMigrations($localPdo);

        $cStmt = $localPdo->prepare('SELECT * FROM contractors WHERE id = ?');
        $cStmt->execute([$contractor_id]);
        $contractor = $cStmt->fetch(PDO::FETCH_ASSOC);
        if (!$contractor) { header('Location: /company/contractors'); exit; }

        // Access check
        $isLogist = ($_SESSION['role_code'] ?? '') === 'logist';
        if ($isLogist) {
            $userId = (int)$_SESSION['user_id'];
            $hasGrant = false;
            $gc = $localPdo->prepare("SELECT access_level FROM entity_access_grants WHERE entity_type = 'contractor' AND entity_id = ? AND granted_to_user_id = ? AND revoked_at IS NULL LIMIT 1");
            $gc->execute([$contractor_id, $userId]);
            $gr = $gc->fetch(PDO::FETCH_ASSOC);
            $hasGrant = ($gr && $gr['access_level'] === 'edit');
            if ((int)$contractor['created_by_user_id'] !== $userId && !$hasGrant) { header('Location: ' . $redirect); exit; }
        }

        $contactPerson = trim($_POST['contact_person'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $comment = trim($_POST['comment'] ?? '');

        $update = $localPdo->prepare(
            'UPDATE contractor_contacts SET contact_person = ?, phone = ?, email = ?, comment = ?, updated_by_user_id = ?, updated_by_role = ? WHERE id = ? AND contractor_id = ?'
        );
        $update->execute([$contactPerson ?: null, $phone ?: null, $email ?: null, $comment ?: null, (int)$_SESSION['user_id'], $_SESSION['role_code'] ?? null, $contact_id, $contractor_id]);
    } catch (\Exception $e) {}

    header('Location: ' . $redirect);
    exit;
});

$router->post('/company/contractors/{contractor_id}/contacts/{contact_id}/delete', function ($contractor_id, $contact_id) use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);
    $contractor_id = (int)$contractor_id; $contact_id = (int)$contact_id;
    $companyId = (int)(getSessionCompanyId() ?? 0);
    $redirect = '/company/contractors/' . $contractor_id;

    if ($companyId <= 0) { header('Location: ' . $redirect); exit; }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$company || $company['status'] !== 'active') { header('Location: ' . $redirect); exit; }

        $dbIdentifier = $company['db_identifier'];
        $localDbConfig = $config['database']; $localDbConfig['database'] = $dbIdentifier;
        $localDb = new \App\Core\Database($localDbConfig); $localPdo = $localDb->connection();
        applyLocalMigrations($localPdo);

        $cStmt = $localPdo->prepare('SELECT * FROM contractors WHERE id = ?');
        $cStmt->execute([$contractor_id]);
        $contractor = $cStmt->fetch(PDO::FETCH_ASSOC);
        if (!$contractor) { header('Location: /company/contractors'); exit; }

        // Access check: logist view — deny, logist edit — allow if can edit, company_owner — allow
        $isLogist = ($_SESSION['role_code'] ?? '') === 'logist';
        if ($isLogist) {
            $userId = (int)$_SESSION['user_id'];
            $hasGrant = false;
            $gc = $localPdo->prepare("SELECT access_level FROM entity_access_grants WHERE entity_type = 'contractor' AND entity_id = ? AND granted_to_user_id = ? AND revoked_at IS NULL LIMIT 1");
            $gc->execute([$contractor_id, $userId]);
            $gr = $gc->fetch(PDO::FETCH_ASSOC);
            $hasGrant = ($gr && $gr['access_level'] === 'edit');
            if ((int)$contractor['created_by_user_id'] !== $userId && !$hasGrant) { header('Location: ' . $redirect); exit; }
        }

        // Check if deleting primary contact
        $contactStmt = $localPdo->prepare('SELECT is_primary FROM contractor_contacts WHERE id = ? AND contractor_id = ?');
        $contactStmt->execute([$contact_id, $contractor_id]);
        $contact = $contactStmt->fetch(PDO::FETCH_ASSOC);

        // Delete
        $delStmt = $localPdo->prepare('DELETE FROM contractor_contacts WHERE id = ? AND contractor_id = ?');
        $delStmt->execute([$contact_id, $contractor_id]);

        // If deleted primary, set first remaining as primary
        if ($contact && $contact['is_primary']) {
            $first = $localPdo->prepare('SELECT id FROM contractor_contacts WHERE contractor_id = ? ORDER BY id ASC LIMIT 1');
            $first->execute([$contractor_id]);
            $firstRow = $first->fetch(PDO::FETCH_ASSOC);
            if ($firstRow) {
                $localPdo->prepare('UPDATE contractor_contacts SET is_primary = 1 WHERE id = ?')->execute([$firstRow['id']]);
            }
        }
    } catch (\Exception $e) {}

    header('Location: ' . $redirect);
    exit;
});

$router->post('/company/contractors/{contractor_id}/contacts/{contact_id}/set-primary', function ($contractor_id, $contact_id) use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);
    $contractor_id = (int)$contractor_id; $contact_id = (int)$contact_id;
    $companyId = (int)(getSessionCompanyId() ?? 0);
    $redirect = '/company/contractors/' . $contractor_id;

    if ($companyId <= 0) { header('Location: ' . $redirect); exit; }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$company || $company['status'] !== 'active') { header('Location: ' . $redirect); exit; }

        $dbIdentifier = $company['db_identifier'];
        $localDbConfig = $config['database']; $localDbConfig['database'] = $dbIdentifier;
        $localDb = new \App\Core\Database($localDbConfig); $localPdo = $localDb->connection();
        applyLocalMigrations($localPdo);

        $cStmt = $localPdo->prepare('SELECT * FROM contractors WHERE id = ?');
        $cStmt->execute([$contractor_id]);
        $contractor = $cStmt->fetch(PDO::FETCH_ASSOC);
        if (!$contractor) { header('Location: /company/contractors'); exit; }

        // Access check
        $isLogist = ($_SESSION['role_code'] ?? '') === 'logist';
        if ($isLogist) {
            $userId = (int)$_SESSION['user_id'];
            $hasGrant = false;
            $gc = $localPdo->prepare("SELECT access_level FROM entity_access_grants WHERE entity_type = 'contractor' AND entity_id = ? AND granted_to_user_id = ? AND revoked_at IS NULL LIMIT 1");
            $gc->execute([$contractor_id, $userId]);
            $gr = $gc->fetch(PDO::FETCH_ASSOC);
            $hasGrant = ($gr && $gr['access_level'] === 'edit');
            if ((int)$contractor['created_by_user_id'] !== $userId && !$hasGrant) { header('Location: ' . $redirect); exit; }
        }

        $localPdo->prepare('UPDATE contractor_contacts SET is_primary = 0 WHERE contractor_id = ?')->execute([$contractor_id]);
        $localPdo->prepare('UPDATE contractor_contacts SET is_primary = 1 WHERE id = ? AND contractor_id = ?')->execute([$contact_id, $contractor_id]);
    } catch (\Exception $e) {}

    header('Location: ' . $redirect);
    exit;
});

$router->post('/company/contractors/{contractor_id}/contacts/{contact_id}/set-document-email', function ($contractor_id, $contact_id) use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);
    $contractor_id = (int)$contractor_id; $contact_id = (int)$contact_id;
    $companyId = (int)(getSessionCompanyId() ?? 0);
    $redirect = '/company/contractors/' . $contractor_id;

    if ($companyId <= 0) { header('Location: ' . $redirect); exit; }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$company || $company['status'] !== 'active') { header('Location: ' . $redirect); exit; }

        $dbIdentifier = $company['db_identifier'];
        $localDbConfig = $config['database']; $localDbConfig['database'] = $dbIdentifier;
        $localDb = new \App\Core\Database($localDbConfig); $localPdo = $localDb->connection();
        applyLocalMigrations($localPdo);

        $cStmt = $localPdo->prepare('SELECT * FROM contractors WHERE id = ?');
        $cStmt->execute([$contractor_id]);
        $contractor = $cStmt->fetch(PDO::FETCH_ASSOC);
        if (!$contractor) { header('Location: /company/contractors'); exit; }

        // Access check
        $isLogist = ($_SESSION['role_code'] ?? '') === 'logist';
        if ($isLogist) {
            $userId = (int)$_SESSION['user_id'];
            $hasGrant = false;
            $gc = $localPdo->prepare("SELECT access_level FROM entity_access_grants WHERE entity_type = 'contractor' AND entity_id = ? AND granted_to_user_id = ? AND revoked_at IS NULL LIMIT 1");
            $gc->execute([$contractor_id, $userId]);
            $gr = $gc->fetch(PDO::FETCH_ASSOC);
            $hasGrant = ($gr && $gr['access_level'] === 'edit');
            if ((int)$contractor['created_by_user_id'] !== $userId && !$hasGrant) { header('Location: ' . $redirect); exit; }
        }

        $localPdo->prepare('UPDATE contractor_contacts SET is_document_email = 1 WHERE id = ? AND contractor_id = ?')->execute([$contact_id, $contractor_id]);
    } catch (\Exception $e) {}

    header('Location: ' . $redirect);
    exit;
});

// --- Contractor Tax History ---

$router->post('/company/contractors/{contractor_id}/tax-history/create', function ($contractor_id) use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);
    $contractor_id = (int)$contractor_id;
    $companyId = (int)(getSessionCompanyId() ?? 0);
    $redirect = '/company/contractors/' . $contractor_id;

    if ($companyId <= 0) { header('Location: ' . $redirect); exit; }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$company || $company['status'] !== 'active') { header('Location: ' . $redirect); exit; }

        $dbIdentifier = $company['db_identifier'];
        $localDbConfig = $config['database']; $localDbConfig['database'] = $dbIdentifier;
        $localDb = new \App\Core\Database($localDbConfig); $localPdo = $localDb->connection();
        applyLocalMigrations($localPdo);

        $cStmt = $localPdo->prepare('SELECT * FROM contractors WHERE id = ?');
        $cStmt->execute([$contractor_id]);
        $contractor = $cStmt->fetch(PDO::FETCH_ASSOC);
        if (!$contractor) { header('Location: /company/contractors'); exit; }

        // Access check
        $isLogist = ($_SESSION['role_code'] ?? '') === 'logist';
        if ($isLogist) {
            $userId = (int)$_SESSION['user_id'];
            $hasGrant = false;
            $gc = $localPdo->prepare("SELECT access_level FROM entity_access_grants WHERE entity_type = 'contractor' AND entity_id = ? AND granted_to_user_id = ? AND revoked_at IS NULL LIMIT 1");
            $gc->execute([$contractor_id, $userId]);
            $gr = $gc->fetch(PDO::FETCH_ASSOC);
            $hasGrant = ($gr && $gr['access_level'] === 'edit');
            if ((int)$contractor['created_by_user_id'] !== $userId && !$hasGrant) { header('Location: ' . $redirect); exit; }
        }

        $taxSystem = trim($_POST['tax_system'] ?? '');
        $vatMode = trim($_POST['vat_mode'] ?? '');
        $effectiveFrom = trim($_POST['effective_from'] ?? '');
        $comment = trim($_POST['comment'] ?? '');

        $insert = $localPdo->prepare(
            'INSERT INTO contractor_tax_history (contractor_id, tax_system, vat_mode, effective_from, comment, created_by_user_id, created_by_role)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $insert->execute([$contractor_id, $taxSystem ?: null, $vatMode ?: null, $effectiveFrom ?: null, $comment ?: null, (int)$_SESSION['user_id'], $_SESSION['role_code'] ?? null]);
    } catch (\Exception $e) {}

    header('Location: ' . $redirect);
    exit;
});
