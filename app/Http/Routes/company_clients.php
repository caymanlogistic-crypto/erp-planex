<?php

$router->get('/company/clients', function () use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);
    $pageTitle = 'Клиенты';
    $pageContext = 'Клиенты › Компания';

    $companyId = (int)(getSessionCompanyId() ?? 0);

    if ($companyId <= 0) {
        $company = null;
        $clients = [];
        $dbError = null;

        ob_start();
        require base_path('app/View/pages/company_clients.php');
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
            $clients = [];
            $dbError = null;

            ob_start();
            require base_path('app/View/pages/company_clients.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $pageContext = 'Клиенты › Компания: ' . $company['name'];

        if ($company['status'] !== 'active') {
            $clients = [];
            $dbError = null;

            ob_start();
            require base_path('app/View/pages/company_clients.php');
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
            $localPdo->query("SELECT 1 FROM clients LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $migrationSql = file_get_contents(base_path('database/migrations-local/002_create_company_clients.sql'));
            $localPdo->exec($migrationSql);
        }

        try {
            $localPdo->query("SELECT created_by_user_id FROM clients LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $localPdo->exec("ALTER TABLE clients ADD COLUMN created_by_user_id INT UNSIGNED DEFAULT NULL, ADD COLUMN created_by_role VARCHAR(20) DEFAULT NULL");
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
            $clientStmt = $localPdo->prepare(
                "SELECT * FROM clients WHERE (created_by_user_id = ? OR id IN (SELECT entity_id FROM entity_access_grants WHERE entity_type = 'client' AND granted_to_user_id = ? AND access_level = 'view')) ORDER BY created_at DESC"
            );
            $clientStmt->execute([$userId, $userId]);
            $clients = $clientStmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $clientStmt = $localPdo->query("SELECT * FROM clients ORDER BY created_at DESC");
            $clients = $clientStmt->fetchAll(PDO::FETCH_ASSOC);
        }
        $dbError = null;
    } catch (\Exception $e) {
        $company = $company ?? null;
        $clients = [];
        $dbError = 'Не удалось подключиться к базе данных компании.';
    }

    ob_start();
    require base_path('app/View/pages/company_clients.php');
    $content = ob_get_clean();
    require base_path('app/View/layouts/main.php');
});

$router->get('/company/clients/create', function () use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);
    $pageTitle = 'Создать клиента';
    $pageContext = 'Клиенты › Компания';
    $isModalRequest = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest';

    $companyId = (int)(getSessionCompanyId() ?? 0);

    if ($companyId <= 0) {
        $company = null;
        $success = false;
        $errors = [];
        $old = [];
        $formError = null;
        $createdClient = null;

        ob_start();
        require base_path('app/View/pages/company_clients_create.php');
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
            $createdClient = null;

            ob_start();
            require base_path('app/View/pages/company_clients_create.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $pageContext = 'Клиенты › Компания: ' . $company['name'];

        $success = false;
        $errors = [];
        $old = [];
        $formError = null;
        $createdClient = null;
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

                $docTypes = $localPdo->query("SELECT id, name, code, entity_type FROM document_types WHERE entity_type = 'client' OR entity_type IS NULL ORDER BY sort_order, name")->fetchAll(PDO::FETCH_ASSOC);
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
        $createdClient = null;
    }

    if ($isModalRequest) {
        header('Content-Type: text/html; charset=utf-8');
        $leEntityType = 'client';
        $leFormAction = '/company/clients/create';
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
    require base_path('app/View/pages/company_clients_create.php');
    $content = ob_get_clean();
    require base_path('app/View/layouts/main.php');
});

$router->post('/company/clients/create', function () use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);
    $pageTitle = 'Создать клиента';
    $pageContext = 'Клиенты › Компания';
    $isModalRequest = !empty($_POST['is_modal']);

    $companyId = (int)(getSessionCompanyId() ?? 0);
    $errors = [];
    $old = $_POST;
    $formError = null;
    $success = false;
    $createdClient = null;
    $docErrors = [];
    $uploadedDocs = [];

    if ($companyId <= 0) {
        $company = null;
        $formError = 'Компания не найдена';

        ob_start();
        require base_path('app/View/pages/company_clients_create.php');
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
            require base_path('app/View/pages/company_clients_create.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $pageContext = 'Клиенты › Компания: ' . $company['name'];

        if ($company['status'] !== 'active') {
            $formError = 'Создание клиентов недоступно';

            ob_start();
            require base_path('app/View/pages/company_clients_create.php');
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
            $localPdo->query("SELECT 1 FROM clients LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $migrationSql = file_get_contents(base_path('database/migrations-local/002_create_company_clients.sql'));
            $localPdo->exec($migrationSql);
        }

        if (isPostTruncated()) {
            $formError = 'Общий размер отправки превышает серверный лимит. Для ERP требуется настройка post_max_size не менее 100M. Уменьшите количество файлов или обратитесь к администратору.';
            ob_start();
            require base_path('app/View/pages/company_clients_create.php');
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

        $contactPayload = ClientContactService::normalizeSubmittedContacts($_POST['contacts'] ?? []);
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
            $checkStmt = $localPdo->prepare('SELECT COUNT(*) FROM clients WHERE inn = ?');
            $checkStmt->execute([$inn]);
            if ($checkStmt->fetchColumn() > 0) {
                $errors['inn'] = 'ИНН уже используется в этой компании';
            }
        }

        if (!empty($errors)) {
            ob_start();
            require base_path('app/View/pages/company_clients_create.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        // Check total upload size before INSERT
        $totalSizeError = validateTotalUploadSize();
        if ($totalSizeError !== '') {
            $formError = $totalSizeError;
            ob_start();
            require base_path('app/View/pages/company_clients_create.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $legalEntityFiles = $_FILES;
        $customDocumentTitleError = validateLegalEntityCustomDocumentTitles($_POST, $legalEntityFiles);
        if ($customDocumentTitleError !== null) {
            $formError = $customDocumentTitleError;
            ob_start();
            require base_path('app/View/pages/company_clients_create.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }
        $_FILES['custom_doc_file']['name'] = [];

        // Pre-validate custom document titles (before INSERT)
        if (!empty($_FILES['custom_doc_file']['name']) && is_array($_FILES['custom_doc_file']['name'])) {
            foreach ($_FILES['custom_doc_file']['name'] as $idx => $origName) {
                $fe = $_FILES['custom_doc_file']['error'][$idx] ?? UPLOAD_ERR_NO_FILE;
                if ($fe !== UPLOAD_ERR_OK || trim((string)$origName) === '') {
                    continue;
                }
                $customTypeNew = trim($_POST['custom_doc_type_new'][$idx] ?? '');
                $customTypeSelect = trim($_POST['custom_doc_type'][$idx] ?? '');
                if ($customTypeNew === '' && $customTypeSelect === '') {
                    $formError = 'Введите название документа';
                    ob_start();
                    require base_path('app/View/pages/company_clients_create.php');
                    $content = ob_get_clean();
                    require base_path('app/View/layouts/main.php');
                    return;
                }
            }
        }

        $insert = $localPdo->prepare(
            'INSERT INTO clients (name, inn, kpp, ogrn, entity_type, legal_address, physical_address,
             bank_account, bank_name, bank_bik, bank_corr_account,
             director_full_name, director_position,
             status, comments, created_by_user_id, created_by_role,
             updated_by_user_id, updated_by_role)
             VALUES (:name, :inn, :kpp, :ogrn, :entity_type, :legal_address, :physical_address,
             :bank_account, :bank_name, :bank_bik, :bank_corr_account,
             :director_full_name, :director_position,
             :status, :comments, :created_by_user_id, :created_by_role,
             :updated_by_user_id, :updated_by_role)'
        );
        $insert->execute([
            ':name'               => $name,
            ':inn'                => $inn,
            ':kpp'                => $kpp !== '' ? $kpp : null,
            ':ogrn'               => $ogrn !== '' ? $ogrn : null,
            ':entity_type'        => ($_POST['entity_type'] ?? '') !== '' ? $_POST['entity_type'] : null,
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
            ':updated_by_user_id' => (int)$_SESSION['user_id'],
            ':updated_by_role'    => $_SESSION['role_code'],
        ]);

        $newClientId = (int)$localPdo->lastInsertId();
        ClientContactService::replaceForClient(
            $localPdo,
            $newClientId,
            $submittedContacts,
            (int) $_SESSION['user_id'],
            (string) ($_SESSION['role_code'] ?? '')
        );

        $createdClient = [
            'id'          => $newClientId,
            'name'        => $name,
            'inn'         => $inn,
            'entity_type' => ($_POST['entity_type'] ?? '') !== '' ? $_POST['entity_type'] : null,
        ];

        $documentUploadResult = processLegalEntityCreateDocuments(
            $localPdo,
            $companyId,
            'client',
            $newClientId,
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
        $entityType = 'client';
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
                    $storedName = uniqid('doc_', true) . '.' . $ext; $relativeDir = 'companies/' . $companyId . '/documents/client/' . $newClientId; $absoluteDir = storage_path($relativeDir);
                    if (!is_dir($absoluteDir)) mkdir($absoluteDir, 0755, true);
                    if (!move_uploaded_file($_FILES['predef_doc']['tmp_name'][$code], $absoluteDir . DIRECTORY_SEPARATOR . $storedName)) { $docErrors[] = 'Предопределённый документ «' . ($_POST['predef_doc_type'][$code] ?? $code) . '»: не удалось сохранить'; continue; }
                    $docTypeName = $_POST['predef_doc_type'][$code] ?? ''; $mime = $_FILES['predef_doc']['type'][$code]; $dtId = null;
                    if ($docTypeName !== '') { $dts = $localPdo->prepare("SELECT id FROM document_types WHERE name = ? AND entity_type = ? LIMIT 1"); $dts->execute([$docTypeName, $entityType]); $dtId = $dts->fetchColumn() ?: null; }
                    $ins = $localPdo->prepare('INSERT INTO documents (entity_type, entity_id, document_type, document_type_id, original_name, stored_name, relative_path, mime_type, file_size, status, uploaded_by_user_id, uploaded_by_role, created_by_user_id, created_by_role) VALUES (:et, :eid, :dtype, :dtid, :oname, :sname, :rpath, :mime, :fsize, :status, :uid, :role, :cuid, :crole)');
                    $ins->execute([':et' => $entityType, ':eid' => $newClientId, ':dtype' => $docTypeName ?: null, ':dtid' => $dtId, ':oname' => $origName, ':sname' => $storedName, ':rpath' => $relativeDir . '/' . $storedName, ':mime' => $mime, ':fsize' => $fs, ':status' => 'uploaded', ':uid' => (int)$_SESSION['user_id'], ':role' => $_SESSION['role_code'], ':cuid' => (int)$_SESSION['user_id'], ':crole' => $_SESSION['role_code']]);
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
                if ($docTypeName === '') { $docErrors[] = 'Произвольный документ #' . ($idx + 1) . ': введите название документа'; continue; }
                if ($customTypeNew !== '') { try { $idts = $localPdo->prepare("INSERT IGNORE INTO document_types (name, entity_type, category, created_by_user_id, created_by_role) VALUES (:name, :et, 'custom', :uid, :role)"); $idts->execute([':name' => $customTypeNew, ':et' => $entityType, ':uid' => (int)$_SESSION['user_id'], ':role' => $_SESSION['role_code']]); $dtId = $localPdo->lastInsertId(); if (!$dtId) { $g = $localPdo->prepare("SELECT id FROM document_types WHERE name = ? AND entity_type = ? LIMIT 1"); $g->execute([$customTypeNew, $entityType]); $dtId = $g->fetchColumn() ?: null; } } catch (\Exception $ex) { $docErrors[] = 'Произвольный документ #' . ($idx + 1) . ': ошибка создания типа документа'; } }
                elseif ($customTypeSelect !== '') { $g = $localPdo->prepare("SELECT id FROM document_types WHERE name = ? AND entity_type = ? LIMIT 1"); $g->execute([$customTypeSelect, $entityType]); $dtId = $g->fetchColumn() ?: null; }
                try {
                    $storedName = uniqid('doc_', true) . '.' . $ext; $relativeDir = 'companies/' . $companyId . '/documents/client/' . $newClientId; $absoluteDir = storage_path($relativeDir);
                    if (!is_dir($absoluteDir)) mkdir($absoluteDir, 0755, true);
                    if (!move_uploaded_file($_FILES['custom_doc_file']['tmp_name'][$idx], $absoluteDir . DIRECTORY_SEPARATOR . $storedName)) { $docErrors[] = 'Произвольный документ #' . ($idx + 1) . ': не удалось сохранить'; continue; }
                    $mime = $_FILES['custom_doc_file']['type'][$idx];
                    $ins = $localPdo->prepare('INSERT INTO documents (entity_type, entity_id, document_type, document_type_id, original_name, stored_name, relative_path, mime_type, file_size, status, uploaded_by_user_id, uploaded_by_role, created_by_user_id, created_by_role) VALUES (:et, :eid, :dtype, :dtid, :oname, :sname, :rpath, :mime, :fsize, :status, :uid, :role, :cuid, :crole)');
                    $ins->execute([':et' => $entityType, ':eid' => $newClientId, ':dtype' => $docTypeName ?: null, ':dtid' => $dtId, ':oname' => $origName, ':sname' => $storedName, ':rpath' => $relativeDir . '/' . $storedName, ':mime' => $mime, ':fsize' => $fs, ':status' => 'uploaded', ':uid' => (int)$_SESSION['user_id'], ':role' => $_SESSION['role_code'], ':cuid' => (int)$_SESSION['user_id'], ':crole' => $_SESSION['role_code']]);
                    $uploadedDocs[] = $docTypeName . ' (' . $origName . ')';
                } catch (\Exception $ex) { $docErrors[] = 'Произвольный документ #' . ($idx + 1) . ': ошибка сохранения'; }
            }
        }

        $success = true;
    } catch (\Exception $e) {
        $company = $company ?? null;
        $formError = 'Ошибка создания клиента: ' . $e->getMessage();
    }

    if ($isModalRequest) {
        header('Content-Type: text/html; charset=utf-8');
        if ($success) {
            echo '<div data-le-create-success="1"></div>';
        } else {
            if (empty($docTypes) && !empty($localPdo)) {
                try { $docTypes = $localPdo->query("SELECT id, name, code, entity_type FROM document_types WHERE entity_type = 'client' OR entity_type IS NULL ORDER BY sort_order, name")->fetchAll(PDO::FETCH_ASSOC); } catch (\Exception $e) { $docTypes = []; }
            }
            $leEntityType = 'client';
            $leFormAction = '/company/clients/create';
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
    require base_path('app/View/pages/company_clients_create.php');
    $content = ob_get_clean();
    require base_path('app/View/layouts/main.php');
});

$router->get('/company/clients/{id}', function ($id) use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);

    $companyId = (int)(getSessionCompanyId() ?? 0);
    $grants = [];
    $logists = [];

    if ($companyId <= 0) {
        $company = null;
        $client = null;
        $dbError = null;

        ob_start();
        require base_path('app/View/pages/company_client_view.php');
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
            $client = null;
            $dbError = null;

            ob_start();
            require base_path('app/View/pages/company_client_view.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $pageTitle = 'Клиент';
        $pageContext = 'Клиенты › Компания: ' . $company['name'];

        if ($company['status'] !== 'active') {
            $client = null;
            $dbError = null;

            ob_start();
            require base_path('app/View/pages/company_client_view.php');
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
            $localPdo->query("SELECT 1 FROM clients LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $migrationSql = file_get_contents(base_path('database/migrations-local/002_create_company_clients.sql'));
            $localPdo->exec($migrationSql);
        }

        try {
            $localPdo->query("SELECT created_by_user_id FROM clients LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $localPdo->exec("ALTER TABLE clients ADD COLUMN created_by_user_id INT UNSIGNED DEFAULT NULL, ADD COLUMN created_by_role VARCHAR(20) DEFAULT NULL");
        }

        try {
            $localPdo->query("SELECT 1 FROM entity_access_grants LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $migrationSql = file_get_contents(base_path('database/migrations-local/009_create_entity_access_grants.sql'));
            $localPdo->exec($migrationSql);
        }

        $clientStmt = $localPdo->prepare('SELECT * FROM clients WHERE id = ?');
        $clientStmt->execute([(int) $id]);
        $client = $clientStmt->fetch(PDO::FETCH_ASSOC) ?: null;

        $contacts = [];
        if ($client) {
            try {
                $contacts = ClientContactService::loadByClientId($localPdo, (int) $client['id']);
            } catch (\Exception $e) {
                $contacts = [];
            }
        }

        $pageTitle = $client ? 'Клиент: ' . $client['name'] : 'Клиент';

        $grants = [];
        $logists = [];
        if (($_SESSION['role_code'] ?? '') === 'company_owner') {
            $grantsStmt = $localPdo->prepare(
                "SELECT g.*, u.full_name AS logist_name
                 FROM entity_access_grants g
                 LEFT JOIN users u ON g.granted_to_user_id = u.id
                 WHERE g.entity_type = ? AND g.entity_id = ?"
            );
            $grantsStmt->execute(['client', (int)$id]);
            $grants = $grantsStmt->fetchAll(PDO::FETCH_ASSOC);

            $logists = $localPdo->query("SELECT id, full_name, login FROM users WHERE role_code IN ('logist', 'senior_logist') AND status='active' ORDER BY full_name")->fetchAll(PDO::FETCH_ASSOC);
        }

        $dbError = null;

        ob_start();
        require base_path('app/View/pages/company_client_view.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
    } catch (\Exception $e) {
        $company = $company ?? null;
        $client = null;
        $grants = [];
        $logists = [];
        $dbError = 'Не удалось загрузить данные: ' . $e->getMessage();

        ob_start();
        require base_path('app/View/pages/company_client_view.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
    }
});

$router->get('/company/clients/{id}/edit', function ($id) use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);

    $companyId = (int)(getSessionCompanyId() ?? 0);

    if ($companyId <= 0) {
        $company = null;
        $client = null;
        $contacts = [];
        $errors = [];
        $old = ['contacts' => clientFormDefaultContacts()];
        $formError = null;

        ob_start();
        require base_path('app/View/pages/company_client_edit.php');
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
            $client = null;
            $contacts = [];
            $errors = [];
            $old = ['contacts' => clientFormDefaultContacts()];
            $formError = null;

            ob_start();
            require base_path('app/View/pages/company_client_edit.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $pageTitle = 'Редактировать клиента';
        $pageContext = 'Клиенты › Компания: ' . $company['name'];

        if ($company['status'] !== 'active') {
            $client = null;
            $contacts = [];
            $errors = [];
            $old = ['contacts' => clientFormDefaultContacts()];
            $formError = null;

            ob_start();
            require base_path('app/View/pages/company_client_edit.php');
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
            $localPdo->query("SELECT 1 FROM clients LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $migrationSql = file_get_contents(base_path('database/migrations-local/002_create_company_clients.sql'));
            $localPdo->exec($migrationSql);
        }

        $clientStmt = $localPdo->prepare('SELECT * FROM clients WHERE id = ?');
        $clientStmt->execute([(int) $id]);
        $client = $clientStmt->fetch(PDO::FETCH_ASSOC) ?: null;

        $contacts = [];
        if ($client) {
            try {
                $contacts = ClientContactService::loadByClientId($localPdo, (int) $client['id']);
            } catch (\Exception $e) {
                $contacts = [];
            }
        }

        $errors = [];
        $old = $client ?: [];
        $old['contacts'] = $contacts !== [] ? $contacts : clientFormDefaultContacts();
        $formError = null;

        ob_start();
        require base_path('app/View/pages/company_client_edit.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
    } catch (\Exception $e) {
        $company = $company ?? null;
        $client = null;
        $contacts = [];
        $errors = [];
        $old = ['contacts' => clientFormDefaultContacts()];
        $formError = 'Не удалось загрузить данные: ' . $e->getMessage();

        ob_start();
        require base_path('app/View/pages/company_client_edit.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
    }
});

$router->post('/company/clients/{id}/edit', function ($id) use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);

    $companyId = (int)(getSessionCompanyId() ?? 0);

    if ($companyId <= 0) {
        $company = null;
        $client = null;
        $contacts = [];
        $errors = [];
        $old = $_POST;
        $old['contacts'] = $_POST['contacts'] ?? clientFormDefaultContacts();
        $formError = 'Компания не найдена';

        ob_start();
        require base_path('app/View/pages/company_client_edit.php');
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
            $client = null;
            $contacts = [];
            $errors = [];
            $old = $_POST;
            $old['contacts'] = $_POST['contacts'] ?? clientFormDefaultContacts();
            $formError = 'Компания не найдена';

            ob_start();
            require base_path('app/View/pages/company_client_edit.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $pageTitle = 'Редактировать клиента';
        $pageContext = 'Клиенты › Компания: ' . $company['name'];

        if ($company['status'] !== 'active') {
            $client = null;
            $contacts = [];
            $errors = [];
            $old = $_POST;
            $old['contacts'] = $_POST['contacts'] ?? clientFormDefaultContacts();
            $formError = 'Редактирование клиентов недоступно';

            ob_start();
            require base_path('app/View/pages/company_client_edit.php');
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
            $localPdo->query("SELECT 1 FROM clients LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $migrationSql = file_get_contents(base_path('database/migrations-local/002_create_company_clients.sql'));
            $localPdo->exec($migrationSql);
        }

        $clientStmt = $localPdo->prepare('SELECT * FROM clients WHERE id = ?');
        $clientStmt->execute([(int) $id]);
        $client = $clientStmt->fetch(PDO::FETCH_ASSOC) ?: null;

        if (!$client) {
            $contacts = [];
            $errors = [];
            $old = $_POST;
            $old['contacts'] = $_POST['contacts'] ?? clientFormDefaultContacts();
            $formError = null;

            ob_start();
            require base_path('app/View/pages/company_client_edit.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
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
            if ($dupStmt->fetchColumn() > 0) {
                $errors['inn'] = 'ИНН уже используется в этой компании';
            }
        }

        if (!empty($errors)) {
            ob_start();
            require base_path('app/View/pages/company_client_edit.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $kpp = trim($_POST['kpp'] ?? '');
        $ogrn = trim($_POST['ogrn'] ?? '');
        $legalAddress = trim($_POST['legal_address'] ?? '');
        $physicalAddress = trim($_POST['physical_address'] ?? '');
        $comments = trim($_POST['comments'] ?? '');

        $update = $localPdo->prepare(
            'UPDATE clients SET
                name = :name,
                inn = :inn,
                kpp = :kpp,
                ogrn = :ogrn,
                legal_address = :legal_address,
                physical_address = :physical_address,
                status = :status,
                comments = :comments,
                updated_by_user_id = :updated_by_user_id,
                updated_by_role = :updated_by_role
             WHERE id = :id'
        );

        $update->execute([
            ':name'             => $name,
            ':inn'              => $inn,
            ':kpp'              => $kpp !== '' ? $kpp : null,
            ':ogrn'             => $ogrn !== '' ? $ogrn : null,
            ':legal_address'    => $legalAddress !== '' ? $legalAddress : null,
            ':physical_address' => $physicalAddress !== '' ? $physicalAddress : null,
            ':status'           => $_POST['status'] ?? $client['status'],
            ':comments'         => $comments !== '' ? $comments : null,
            ':updated_by_user_id' => (int)$_SESSION['user_id'],
            ':updated_by_role'   => $_SESSION['role_code'],
            ':id'               => (int) $id,
        ]);

        ClientContactService::replaceForClient(
            $localPdo,
            (int) $id,
            $submittedContacts,
            (int) $_SESSION['user_id'],
            (string) ($_SESSION['role_code'] ?? '')
        );

        header('Location: /company/clients/' . $id);
        exit;
    } catch (\Exception $e) {
        $company = $company ?? null;
        $client = $client ?? null;
        $contacts = $contacts ?? [];
        $errors = [];
        $old = $_POST;
        $formError = 'Ошибка сохранения: ' . $e->getMessage();

        ob_start();
        require base_path('app/View/pages/company_client_edit.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
    }
});

$router->post('/company/clients/{id}/archive', function ($id) use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);

    $companyId = (int)(getSessionCompanyId() ?? 0);

    if ($companyId <= 0) {
        header('Location: /company/clients');
        exit;
    }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company || $company['status'] !== 'active') {
            header('Location: /company/clients');
            exit;
        }

        $dbIdentifier = $company['db_identifier'];
        $localDbConfig = $config['database'];
        $localDbConfig['database'] = $dbIdentifier;
        $localDb = new \App\Core\Database($localDbConfig);
        $localPdo = $localDb->connection();
                applyLocalMigrations($localPdo);

        try {
            $localPdo->query("SELECT 1 FROM clients LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $migrationSql = file_get_contents(base_path('database/migrations-local/002_create_company_clients.sql'));
            $localPdo->exec($migrationSql);
        }

        $update = $localPdo->prepare("UPDATE clients SET status = 'archived' WHERE id = ?");
        $update->execute([(int) $id]);

        header('Location: /company/clients');
        exit;
    } catch (\Exception $e) {
        header('Location: /company/clients');
        exit;
    }
});

$router->get('/company/clients/{id}/modal-view', function ($id) use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);

    $renderMessage = static function (string $message): void {
        echo '<div class="modal-body"><div class="notice warn" style="margin:16px">' . e($message) . '</div></div>';
        echo '<div class="modal-foot is-spaced"><div class="modal-foot-actions"><button type="button" class="btn btn-ghost" data-client-view-close-btn>Закрыть</button></div></div>';
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
            $localPdo->query("SELECT 1 FROM clients LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $localPdo->exec(file_get_contents(base_path('database/migrations-local/002_create_company_clients.sql')));
        }
        try {
            $localPdo->query("SELECT created_by_user_id FROM clients LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $localPdo->exec("ALTER TABLE clients ADD COLUMN created_by_user_id INT UNSIGNED DEFAULT NULL, ADD COLUMN created_by_role VARCHAR(20) DEFAULT NULL");
        }
        try {
            $localPdo->query("SELECT 1 FROM entity_access_grants LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $localPdo->exec(file_get_contents(base_path('database/migrations-local/009_create_entity_access_grants.sql')));
        }

        $clientStmt = $localPdo->prepare('SELECT * FROM clients WHERE id = ?');
        $clientStmt->execute([(int) $id]);
        $client = $clientStmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if (!$client) {
            $renderMessage('Клиент не найден.');
            return;
        }

        $roleCode = (string) ($_SESSION['role_code'] ?? '');
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $grantLevel = null;
        if ($roleCode === 'logist') {
            $grantStmt = $localPdo->prepare("SELECT access_level FROM entity_access_grants WHERE entity_type = 'client' AND entity_id = ? AND granted_to_user_id = ? AND access_level IN ('view','edit') AND revoked_at IS NULL LIMIT 1");
            $grantStmt->execute([(int) $id, $userId]);
            $grantLevel = $grantStmt->fetchColumn() ?: null;
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

        $canEdit = $roleCode !== 'logist' || (int) ($client['created_by_user_id'] ?? 0) === $userId || $grantLevel === 'edit';
        $canArchive = $roleCode === 'company_owner' || $roleCode === 'senior_logist' || ($roleCode === 'logist' && (int) ($client['created_by_user_id'] ?? 0) === $userId);
        $archiveBlockedMessage = '';

        require base_path('app/View/partials/company_client_modal_view.php');
    } catch (\Exception $e) {
        $renderMessage('Не удалось загрузить клиента: ' . $e->getMessage());
    }
});

$router->get('/company/clients/{id}/modal-edit', function ($id) use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);

    $renderMessage = static function (string $message): void {
        echo '<div class="modal-body"><div class="notice warn" style="margin:16px">' . e($message) . '</div></div>';
        echo '<div class="modal-foot is-spaced"><div class="modal-foot-actions"><button type="button" class="btn btn-ghost" data-client-cancel-edit-btn>Закрыть</button></div></div>';
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
            $localPdo->query("SELECT 1 FROM clients LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $localPdo->exec(file_get_contents(base_path('database/migrations-local/002_create_company_clients.sql')));
        }
        try {
            $localPdo->query("SELECT created_by_user_id FROM clients LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $localPdo->exec("ALTER TABLE clients ADD COLUMN created_by_user_id INT UNSIGNED DEFAULT NULL, ADD COLUMN created_by_role VARCHAR(20) DEFAULT NULL");
        }
        try {
            $localPdo->query("SELECT 1 FROM entity_access_grants LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $localPdo->exec(file_get_contents(base_path('database/migrations-local/009_create_entity_access_grants.sql')));
        }

        $clientStmt = $localPdo->prepare('SELECT * FROM clients WHERE id = ?');
        $clientStmt->execute([(int) $id]);
        $client = $clientStmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if (!$client) {
            $renderMessage('Клиент не найден.');
            return;
        }

        $roleCode = (string) ($_SESSION['role_code'] ?? '');
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        if ($roleCode === 'logist') {
            $grantStmt = $localPdo->prepare("SELECT access_level FROM entity_access_grants WHERE entity_type = 'client' AND entity_id = ? AND granted_to_user_id = ? AND access_level = 'edit' AND revoked_at IS NULL LIMIT 1");
            $grantStmt->execute([(int) $id, $userId]);
            $canEditGrant = $grantStmt->fetchColumn() ?: null;
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
});

$router->post('/company/clients/{id}/modal-edit', function ($id) use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);

    $renderMessage = static function (string $message): void {
        echo '<div class="modal-body"><div class="notice warn" style="margin:16px">' . e($message) . '</div></div>';
        echo '<div class="modal-foot is-spaced"><div class="modal-foot-actions"><button type="button" class="btn btn-ghost" data-client-view-close-btn>Закрыть</button></div></div>';
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
            $localPdo->query("SELECT 1 FROM clients LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $localPdo->exec(file_get_contents(base_path('database/migrations-local/002_create_company_clients.sql')));
        }
        try {
            $localPdo->query("SELECT created_by_user_id FROM clients LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $localPdo->exec("ALTER TABLE clients ADD COLUMN created_by_user_id INT UNSIGNED DEFAULT NULL, ADD COLUMN created_by_role VARCHAR(20) DEFAULT NULL");
        }
        try {
            $localPdo->query("SELECT 1 FROM entity_access_grants LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $localPdo->exec(file_get_contents(base_path('database/migrations-local/009_create_entity_access_grants.sql')));
        }

        $clientStmt = $localPdo->prepare('SELECT * FROM clients WHERE id = ?');
        $clientStmt->execute([(int) $id]);
        $client = $clientStmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if (!$client) {
            $renderMessage('Клиент не найден.');
            return;
        }

        $roleCode = (string) ($_SESSION['role_code'] ?? '');
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        if ($roleCode === 'logist') {
            $grantStmt = $localPdo->prepare("SELECT access_level FROM entity_access_grants WHERE entity_type = 'client' AND entity_id = ? AND granted_to_user_id = ? AND access_level = 'edit' AND revoked_at IS NULL LIMIT 1");
            $grantStmt->execute([(int) $id, $userId]);
            $canEditGrant = $grantStmt->fetchColumn() ?: null;
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

        $update = $localPdo->prepare(
            'UPDATE clients SET
                name = :name,
                inn = :inn,
                kpp = :kpp,
                ogrn = :ogrn,
                legal_address = :legal_address,
                physical_address = :physical_address,
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
            ':legal_address' => ($legalAddress = trim($_POST['legal_address'] ?? '')) !== '' ? $legalAddress : null,
            ':physical_address' => ($physicalAddress = trim($_POST['physical_address'] ?? '')) !== '' ? $physicalAddress : null,
            ':status' => $_POST['status'] ?? ($client['status'] ?? 'active'),
            ':comments' => ($comments = trim($_POST['comments'] ?? '')) !== '' ? $comments : null,
            ':updated_by_user_id' => $userId,
            ':updated_by_role' => $roleCode,
            ':id' => (int) $id,
        ]);

        ClientContactService::replaceForClient($localPdo, (int) $id, $submittedContacts, $userId, $roleCode);

        $clientStmt->execute([(int) $id]);
        $client = $clientStmt->fetch(PDO::FETCH_ASSOC) ?: $client;
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
});

$router->post('/company/clients/{id}/modal-archive', function ($id) use ($config, $db) {
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
            $localPdo->query("SELECT 1 FROM clients LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $localPdo->exec(file_get_contents(base_path('database/migrations-local/002_create_company_clients.sql')));
        }
        try {
            $localPdo->query("SELECT created_by_user_id FROM clients LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $localPdo->exec("ALTER TABLE clients ADD COLUMN created_by_user_id INT UNSIGNED DEFAULT NULL, ADD COLUMN created_by_role VARCHAR(20) DEFAULT NULL");
        }

        $clientStmt = $localPdo->prepare('SELECT * FROM clients WHERE id = ?');
        $clientStmt->execute([(int) $id]);
        $client = $clientStmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if (!$client) {
            throw new \RuntimeException('Клиент не найден.');
        }

        $roleCode = (string) ($_SESSION['role_code'] ?? '');
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        if ($roleCode === 'logist' && (int) ($client['created_by_user_id'] ?? 0) !== $userId) {
            throw new \RuntimeException('Логист может архивировать только записи, созданные им самим.');
        }

        $update = $localPdo->prepare("UPDATE clients SET status = 'archived' WHERE id = ?");
        $update->execute([(int) $id]);

        echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
    } catch (\Throwable $e) {
        http_response_code(200);
        echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
});
