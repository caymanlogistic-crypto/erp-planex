<?php

$router->get('/company/documents', function () use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);

    $companyId = (int)(getSessionCompanyId() ?? 0);
    $entityType = $_GET['entity_type'] ?? '';
    $entityId = (int)($_GET['entity_id'] ?? 0);
    $missingEntityContext = ($entityType === '' && $entityId <= 0);

    $whitelist = ['client' => ['label' => 'Клиент', 'labelDative' => 'клиентам', 'table' => 'clients', 'backRoute' => '/company/clients'],
                    'contractor' => ['label' => 'Перевозчик', 'labelDative' => 'перевозчикам', 'table' => 'contractors', 'backRoute' => '/company/contractors'],
                   'driver' => ['label' => 'Водитель', 'labelDative' => 'водителям', 'table' => 'drivers', 'backRoute' => '/company/drivers'],
                   'vehicle_unit' => ['label' => 'Транспортная единица', 'labelDative' => 'транспортным единицам', 'table' => 'vehicle_units', 'backRoute' => '/company/vehicles'],
                    'vehicle_set' => ['label' => 'Транспорт', 'labelDative' => 'транспорту', 'table' => 'vehicle_sets', 'backRoute' => '/company/vehicle-sets'],
                   'driver_vehicle_block' => ['label' => 'Водители+ТС', 'labelDative' => 'связкам', 'table' => 'driver_vehicle_blocks', 'backRoute' => '/company/driver-vehicle-blocks'],
                   'crew' => ['label' => 'Экипаж', 'labelDative' => 'экипажам', 'table' => 'crews', 'backRoute' => '/company/crews']];

    if (!isset($whitelist[$entityType])) {
        $entityTypeError = !$missingEntityContext;
        $company = null;
        $documents = [];
        $entityLabel = '';
        $entityLabelDative = '';
        $entityName = '';
        $backRoute = '';
        $dbError = null;
        $entityNotFound = false;

        $pageTitle = 'Документы';
        $pageContext = 'Документы › Компания';

        if ($missingEntityContext && $companyId > 0) {
            try {
                $pdo = $db->connection();
                $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
                $stmt->execute([$companyId]);
                $company = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
                if ($company) {
                    $pageContext = 'Документы › Компания: ' . $company['name'];
                }
            } catch (\Exception $e) {
                $company = null;
            }
        }

        ob_start();
        require base_path('app/View/pages/company_documents.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
        return;
    }

    $entityMeta = $whitelist[$entityType];
    $entityLabel = $entityMeta['label'];
    $entityLabelDative = $entityMeta['labelDative'];
    $tableName = $entityMeta['table'];
    $backRoute = $entityMeta['backRoute'];

    $pageTitle = 'Документы';
    $pageContext = $entityLabel . ' › Компания';
    $entityTypeError = false;
    $entityNotFound = false;
    $documents = [];
    $entityName = '';
    $dbError = null;

    if ($companyId <= 0) {
        $company = null;

        ob_start();
        require base_path('app/View/pages/company_documents.php');
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
            ob_start();
            require base_path('app/View/pages/company_documents.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $pageContext = $entityLabel . ' › Компания: ' . $company['name'];

        if ($company['status'] !== 'active') {
            ob_start();
            require base_path('app/View/pages/company_documents.php');
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

        $entityStmt = $localPdo->prepare("SELECT * FROM `{$tableName}` WHERE id = ?");
        $entityStmt->execute([$entityId]);
        $entity = $entityStmt->fetch(PDO::FETCH_ASSOC);

        if (!$entity) {
            $entityNotFound = true;

            ob_start();
            require base_path('app/View/pages/company_documents.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        switch ($entityType) {
            case 'client':
            case 'contractor':
                $entityName = $entity['name'];
                break;
            case 'driver':
                $entityName = $entity['full_name'];
                break;
            case 'vehicle_unit':
                $entityName = $entity['plate_number'];
                break;
            case 'crew':
                $entityName = 'Экипаж #' . $entity['id'];
                break;
            default:
                $entityName = '';
        }

        try {
            $localPdo->query("SELECT 1 FROM documents LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $migrationSql = file_get_contents(base_path('database/migrations-local/007_create_company_documents.sql'));
            $localPdo->exec($migrationSql);
        }

        try {
            $localPdo->query("SELECT created_by_user_id FROM documents LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $localPdo->exec("ALTER TABLE documents ADD COLUMN created_by_user_id INT UNSIGNED DEFAULT NULL, ADD COLUMN created_by_role VARCHAR(20) DEFAULT NULL");
        }

        $isLogist = ($_SESSION['role_code'] ?? '') === 'logist';
        if ($isLogist) {
            $docStmt = $localPdo->prepare(
                "SELECT * FROM documents WHERE entity_type = ? AND entity_id = ? AND deleted_at IS NULL ORDER BY created_at DESC"
            );
        } else {
            $docStmt = $localPdo->prepare(
                "SELECT * FROM documents WHERE entity_type = ? AND entity_id = ? ORDER BY created_at DESC"
            );
        }
        $docStmt->execute([$entityType, $entityId]);
        $documents = $docStmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($documents as &$doc) {
            $doc['file_size_formatted'] = formatFileSize((int)$doc['file_size']);
        }
        unset($doc);

        $dbError = null;
    } catch (\Exception $e) {
        $company = $company ?? null;
        $documents = [];
        $dbError = 'Не удалось подключиться к базе данных компании. Проверьте, что локальная БД создана.';
    }

    ob_start();
    require base_path('app/View/pages/company_documents.php');
    $content = ob_get_clean();
    require base_path('app/View/layouts/main.php');
});

$router->get('/company/documents/upload', function () use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);

    $companyId = (int)(getSessionCompanyId() ?? 0);
    $entityType = $_GET['entity_type'] ?? '';
    $entityId = (int)($_GET['entity_id'] ?? 0);
    $missingEntityContext = ($entityType === '' && $entityId <= 0);

    $whitelist = ['client' => ['label' => 'Клиент', 'labelDative' => 'клиентам', 'table' => 'clients', 'backRoute' => '/company/clients'],
                    'contractor' => ['label' => 'Перевозчик', 'labelDative' => 'перевозчикам', 'table' => 'contractors', 'backRoute' => '/company/contractors'],
                   'driver' => ['label' => 'Водитель', 'labelDative' => 'водителям', 'table' => 'drivers', 'backRoute' => '/company/drivers'],
                   'vehicle_unit' => ['label' => 'Транспортная единица', 'labelDative' => 'транспортным единицам', 'table' => 'vehicle_units', 'backRoute' => '/company/vehicles'],
                    'vehicle_set' => ['label' => 'Транспорт', 'labelDative' => 'транспорту', 'table' => 'vehicle_sets', 'backRoute' => '/company/vehicle-sets'],
                   'driver_vehicle_block' => ['label' => 'Водители+ТС', 'labelDative' => 'связкам', 'table' => 'driver_vehicle_blocks', 'backRoute' => '/company/driver-vehicle-blocks'],
                   'crew' => ['label' => 'Экипаж', 'labelDative' => 'экипажам', 'table' => 'crews', 'backRoute' => '/company/crews']];

    $replaceDocId = (int)($_GET['replace'] ?? 0);
    $replacedDoc = null;

    $pageTitle = $replaceDocId > 0 ? 'Заменить документ' : 'Загрузить документ';
    $pageContext = ($replaceDocId > 0 ? 'Замена документа › ' : 'Загрузка документа › ') . 'Компания';
    $entityTypeError = false;
    $entityNotFound = false;
    $success = false;
    $formError = null;
    $errors = [];
    $old = [];
    $createdDoc = null;
    $dbError = null;
    $docTypes = [];

    if (!isset($whitelist[$entityType])) {
        $entityTypeError = !$missingEntityContext;
        $company = null;
        $entityLabel = '';
        $entityName = '';

        if ($missingEntityContext && $companyId > 0) {
            try {
                $pdo = $db->connection();
                $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
                $stmt->execute([$companyId]);
                $company = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
                if ($company) {
                    $pageContext = 'Загрузка документа › Компания: ' . $company['name'];
                }
            } catch (\Exception $e) {
                $company = null;
            }
        }

        ob_start();
        require base_path('app/View/pages/company_documents_upload.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
        return;
    }

    $entityMeta = $whitelist[$entityType];
    $entityLabel = $entityMeta['label'];
    $tableName = $entityMeta['table'];

    if ($companyId <= 0) {
        $company = null;
        $entityName = '';

        ob_start();
        require base_path('app/View/pages/company_documents_upload.php');
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
            $entityName = '';

            ob_start();
            require base_path('app/View/pages/company_documents_upload.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $pageContext = $entityLabel . ' › Загрузка документа › Компания: ' . $company['name'];

        if ($company['status'] !== 'active') {
            $entityName = '';

            ob_start();
            require base_path('app/View/pages/company_documents_upload.php');
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

        $entityStmt = $localPdo->prepare("SELECT * FROM `{$tableName}` WHERE id = ?");
        $entityStmt->execute([$entityId]);
        $entity = $entityStmt->fetch(PDO::FETCH_ASSOC);

        if (!$entity) {
            $entityNotFound = true;
            $entityName = '';

            ob_start();
            require base_path('app/View/pages/company_documents_upload.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        switch ($entityType) {
            case 'client':
            case 'contractor':
                $entityName = $entity['name'];
                break;
            case 'driver':
                $entityName = $entity['full_name'];
                break;
            case 'vehicle_unit':
                $entityName = $entity['plate_number'];
                break;
            case 'vehicle_set':
                $entityName = 'Комплект #' . $entity['id'];
                break;
            case 'driver_vehicle_block':
                $entityName = 'Связка #' . $entity['id'];
                break;
            case 'crew':
                $entityName = 'Экипаж #' . $entity['id'];
                break;
            default:
                $entityName = '';
        }

        // Fetch document types for dropdown
        try {
            try { $localPdo->query("SELECT 1 FROM document_types LIMIT 1")->fetch(); }
            catch (\Exception $e) {
                $localPdo->exec(file_get_contents(base_path('database/migrations-local/024_create_document_types.sql')));
                $localPdo->exec(file_get_contents(base_path('database/migrations-local/025_add_document_type_id.sql')));
            }
            $docTypes = $localPdo->query("SELECT id, name, code, entity_type, category FROM document_types WHERE entity_type = '{$entityType}' OR entity_type IS NULL ORDER BY sort_order, name")->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            $docTypes = [];
        }
    } catch (\Exception $e) {
        $company = $company ?? null;
        $entityName = '';
        $dbError = 'Не удалось подключиться к базе данных компании. Проверьте, что локальная БД создана.';
    }

    if ($replaceDocId > 0 && !$entityNotFound && !$dbError && $company && $company['status'] === 'active') {
        try {
            $docStmt = $localPdo->prepare('SELECT * FROM documents WHERE id = ? AND entity_type = ? AND entity_id = ?');
            $docStmt->execute([$replaceDocId, $entityType, $entityId]);
            $replacedDoc = $docStmt->fetch(PDO::FETCH_ASSOC);
            if (!$replacedDoc) {
                $replaceDocId = 0;
            }
        } catch (\Exception $e) {
            $replaceDocId = 0;
        }
    }

    ob_start();
    require base_path('app/View/pages/company_documents_upload.php');
    $content = ob_get_clean();
    require base_path('app/View/layouts/main.php');
});

$router->post('/company/documents/upload', function () use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);

    $companyId = (int)(getSessionCompanyId() ?? 0);
    $entityType = $_GET['entity_type'] ?? '';
    $entityId = (int)($_GET['entity_id'] ?? 0);

    $whitelist = ['client' => ['label' => 'Клиент', 'labelDative' => 'клиентам', 'table' => 'clients', 'backRoute' => '/company/clients'],
                    'contractor' => ['label' => 'Перевозчик', 'labelDative' => 'перевозчикам', 'table' => 'contractors', 'backRoute' => '/company/contractors'],
                   'driver' => ['label' => 'Водитель', 'labelDative' => 'водителям', 'table' => 'drivers', 'backRoute' => '/company/drivers'],
                   'vehicle_unit' => ['label' => 'Транспортная единица', 'labelDative' => 'транспортным единицам', 'table' => 'vehicle_units', 'backRoute' => '/company/vehicles'],
                    'vehicle_set' => ['label' => 'Транспорт', 'labelDative' => 'транспорту', 'table' => 'vehicle_sets', 'backRoute' => '/company/vehicle-sets'],
                   'driver_vehicle_block' => ['label' => 'Водители+ТС', 'labelDative' => 'связкам', 'table' => 'driver_vehicle_blocks', 'backRoute' => '/company/driver-vehicle-blocks'],
                   'crew' => ['label' => 'Экипаж', 'labelDative' => 'экипажам', 'table' => 'crews', 'backRoute' => '/company/crews']];

    $pageTitle = 'Загрузить документ';
    $pageContext = 'Загрузка документа › Компания';
    $entityTypeError = false;
    $entityNotFound = false;
    $success = false;
    $formError = null;
    $errors = [];
    $old = $_POST;
    $createdDoc = null;
    $dbError = null;
    $docTypes = [];

    if (!isset($whitelist[$entityType])) {
        $entityTypeError = true;
        $company = null;
        $entityLabel = '';
        $entityName = '';

        ob_start();
        require base_path('app/View/pages/company_documents_upload.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
        return;
    }

    $entityMeta = $whitelist[$entityType];
    $entityLabel = $entityMeta['label'];
    $tableName = $entityMeta['table'];

    if ($companyId <= 0) {
        $company = null;
        $entityName = '';

        ob_start();
        require base_path('app/View/pages/company_documents_upload.php');
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
            $entityName = '';

            ob_start();
            require base_path('app/View/pages/company_documents_upload.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $pageContext = $entityLabel . ' › Загрузка документа › Компания: ' . $company['name'];

        if ($company['status'] !== 'active') {
            $entityName = '';

            ob_start();
            require base_path('app/View/pages/company_documents_upload.php');
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

        $entityStmt = $localPdo->prepare("SELECT * FROM `{$tableName}` WHERE id = ?");
        $entityStmt->execute([$entityId]);
        $entity = $entityStmt->fetch(PDO::FETCH_ASSOC);

        if (!$entity) {
            $entityNotFound = true;
            $entityName = '';

            ob_start();
            require base_path('app/View/pages/company_documents_upload.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        switch ($entityType) {
            case 'client':
            case 'contractor':
                $entityName = $entity['name'];
                break;
            case 'driver':
                $entityName = $entity['full_name'];
                break;
            case 'vehicle_unit':
                $entityName = $entity['plate_number'];
                break;
            case 'vehicle_set':
                $entityName = 'Комплект #' . $entity['id'];
                break;
            case 'driver_vehicle_block':
                $entityName = 'Связка #' . $entity['id'];
                break;
            case 'crew':
                $entityName = 'Экипаж #' . $entity['id'];
                break;
            default:
                $entityName = '';
        }

        // Fetch document types for dropdown (re-render on error)
        try {
            try { $localPdo->query("SELECT 1 FROM document_types LIMIT 1")->fetch(); }
            catch (\Exception $e) {
                $localPdo->exec(file_get_contents(base_path('database/migrations-local/024_create_document_types.sql')));
                $localPdo->exec(file_get_contents(base_path('database/migrations-local/025_add_document_type_id.sql')));
            }
            $docTypes = $localPdo->query("SELECT id, name, code, entity_type, category FROM document_types WHERE entity_type = '{$entityType}' OR entity_type IS NULL ORDER BY sort_order, name")->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            $docTypes = [];
        }

        // File handling
        $documentType = trim($_POST['document_type'] ?? '');
        // document_type is now optional (NOT required)
        $documentName = trim($_POST['document_name'] ?? '');
        $documentNumber = trim($_POST['document_number'] ?? '');
        $documentDate = trim($_POST['document_date'] ?? '');
        $documentExpire = trim($_POST['document_expire'] ?? '');
        $comments = trim($_POST['comments'] ?? '');

        if ($documentType === '') {
            // document_type is now optional - save as NULL
        }

        $fileError = $_FILES['document_file']['error'] ?? UPLOAD_ERR_NO_FILE;

        if ($fileError === UPLOAD_ERR_NO_FILE) {
            $errors['document_file'] = 'Выберите файл для загрузки';
        } elseif ($fileError === UPLOAD_ERR_INI_SIZE || $fileError === UPLOAD_ERR_FORM_SIZE) {
            $errors['document_file'] = 'Размер файла превышает допустимый лимит';
        } elseif ($fileError !== UPLOAD_ERR_OK) {
            $errors['document_file'] = 'Ошибка при загрузке файла. Попробуйте ещё раз.';
        } else {
            $originalName = $_FILES['document_file']['name'];
            $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
            $allowedExt = ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'doc', 'docx', 'xls', 'xlsx'];

            if (!in_array($ext, $allowedExt, true)) {
                $errors['document_file'] = 'Недопустимый формат файла. Разрешены: PDF, JPG, PNG, DOC, DOCX, XLS, XLSX';
            }

            if (empty($errors['document_file'])) {
                $fileSize = $_FILES['document_file']['size'];
                if ($fileSize > 20 * 1024 * 1024) {
                    $errors['document_file'] = 'Размер файла превышает 20 МБ';
                }
            }

            if (empty($errors['document_file'])) {
                if (strpos($originalName, '../') !== false || strpos($originalName, '..\\') !== false
                    || strpos($originalName, '/') !== false || strpos($originalName, '\\') !== false) {
                    $errors['document_file'] = 'Недопустимое имя файла';
                }
            }
        }

        if (!empty($errors)) {
            ob_start();
            require base_path('app/View/pages/company_documents_upload.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        try {
            $localPdo->query("SELECT 1 FROM documents LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $migrationSql = file_get_contents(base_path('database/migrations-local/007_create_company_documents.sql'));
            $localPdo->exec($migrationSql);
        }

        try {
            $localPdo->query("SELECT created_by_user_id FROM documents LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $localPdo->exec("ALTER TABLE documents ADD COLUMN created_by_user_id INT UNSIGNED DEFAULT NULL, ADD COLUMN created_by_role VARCHAR(20) DEFAULT NULL");
        }

        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $storedName = uniqid('doc_', true) . '.' . $ext;
        $relativeDir = 'companies/' . $companyId . '/documents/' . $entityType . '/' . $entityId;
        $relativePath = $relativeDir . '/' . $storedName;
        $absoluteDir = storage_path($relativeDir);

        if (!is_dir($absoluteDir)) {
            if (!mkdir($absoluteDir, 0755, true)) {
                $formError = 'Не удалось создать директорию для хранения файла.';
                ob_start();
                require base_path('app/View/pages/company_documents_upload.php');
                $content = ob_get_clean();
                require base_path('app/View/layouts/main.php');
                return;
            }
        }

        $tmpPath = $_FILES['document_file']['tmp_name'];
        $destPath = $absoluteDir . DIRECTORY_SEPARATOR . $storedName;

        if (!move_uploaded_file($tmpPath, $destPath)) {
            $formError = 'Не удалось сохранить файл. Попробуйте ещё раз.';
            ob_start();
            require base_path('app/View/pages/company_documents_upload.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $mimeType = $_FILES['document_file']['type'];
        $fileSize = $_FILES['document_file']['size'];
        $comments = trim($_POST['comments'] ?? '');
        $userId = (int)($_SESSION['user_id'] ?? 0);
        $userRole = $_SESSION['role_code'] ?? '';

        $insert = $localPdo->prepare(
            'INSERT INTO documents (entity_type, entity_id, document_type, document_name, document_number, document_date, document_expire, original_name, stored_name,
             relative_path, mime_type, file_size, status, uploaded_by_user_id, uploaded_by_role, comments, created_by_user_id, created_by_role)
             VALUES (:entity_type, :entity_id, :document_type, :document_name, :document_number, :document_date, :document_expire, :original_name, :stored_name,
             :relative_path, :mime_type, :file_size, :status, :uploaded_by_user_id, :uploaded_by_role, :comments, :created_by_user_id, :created_by_role)'
        );
        $insert->execute([
            ':entity_type'         => $entityType,
            ':entity_id'           => $entityId,
            ':document_type'       => $documentType !== '' ? $documentType : null,
            ':document_name'       => $documentName !== '' ? $documentName : null,
            ':document_number'     => $documentNumber !== '' ? $documentNumber : null,
            ':document_date'       => $documentDate !== '' ? $documentDate : null,
            ':document_expire'     => $documentExpire !== '' ? $documentExpire : null,
            ':original_name'       => $originalName,
            ':stored_name'         => $storedName,
            ':relative_path'       => $relativePath,
            ':mime_type'           => $mimeType,
            ':file_size'           => $fileSize,
            ':status'              => 'uploaded',
            ':uploaded_by_user_id' => $userId > 0 ? $userId : null,
            ':uploaded_by_role'    => $userRole !== '' ? $userRole : null,
            ':comments'            => $comments !== '' ? $comments : null,
            ':created_by_user_id'  => $userId > 0 ? $userId : null,
            ':created_by_role'     => $userRole !== '' ? $userRole : null,
        ]);

        $lastId = $localPdo->lastInsertId();
        $selectStmt = $localPdo->prepare('SELECT * FROM documents WHERE id = ?');
        $selectStmt->execute([$lastId]);
        $createdDoc = $selectStmt->fetch(PDO::FETCH_ASSOC);
        $createdDoc['file_size_formatted'] = formatFileSize((int)$createdDoc['file_size']);
        $success = true;

        $pageTitle = 'Документ загружен';
        $pageContext = $entityLabel . ' › Компания: ' . $company['name'];
    } catch (\Exception $e) {
        $company = $company ?? null;
        $entityName = $entityName ?? '';
        $formError = 'Ошибка при загрузке: ' . $e->getMessage();
    }

    ob_start();
    require base_path('app/View/pages/company_documents_upload.php');
    $content = ob_get_clean();
    require base_path('app/View/layouts/main.php');
});

$router->get('/company/documents/download', function () use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);

    $docId = (int)($_GET['id'] ?? 0);
    $companyId = (int)(getSessionCompanyId() ?? 0);

    if ($docId <= 0 || $companyId <= 0) {
        http_response_code(404);
        echo 'Document not found';
        return;
    }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company || $company['status'] !== 'active') {
            http_response_code(404);
            echo 'Company not found';
            return;
        }

        $dbIdentifier = $company['db_identifier'];
        $localDbConfig = $config['database'];
        $localDbConfig['database'] = $dbIdentifier;
        $localDb = new \App\Core\Database($localDbConfig);
        $localPdo = $localDb->connection();
                applyLocalMigrations($localPdo);

        try {
            $localPdo->query("SELECT 1 FROM documents LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $migrationSql = file_get_contents(base_path('database/migrations-local/007_create_company_documents.sql'));
            $localPdo->exec($migrationSql);
        }

        $docStmt = $localPdo->prepare('SELECT * FROM documents WHERE id = ?');
        $docStmt->execute([$docId]);
        $doc = $docStmt->fetch(PDO::FETCH_ASSOC);

        if (!$doc) {
            http_response_code(404);
            echo 'Document not found';
            return;
        }

        $storageBase = storage_path('companies/' . $companyId . '/documents/');
        $filePath = $storageBase . $doc['entity_type'] . '/' . $doc['entity_id'] . '/' . $doc['stored_name'];

        $realBase = realpath($storageBase);
        $realFile = realpath($filePath);
        if ($realFile === false || !str_starts_with($realFile, $realBase)) {
            http_response_code(404);
            echo 'File not found';
            return;
        }

        if (!file_exists($realFile)) {
            http_response_code(404);
            echo 'File not found';
            return;
        }

        $mimeType = $doc['mime_type'] ?: 'application/octet-stream';
        $originalName = $doc['original_name'] ?: 'download';

        $safeFilename = preg_replace('/[^a-zA-Z0-9._-]/u', '_', $originalName);

        header('Content-Type: ' . $mimeType);
        header('Content-Disposition: attachment; filename="' . $safeFilename . '"');
        header('Content-Length: ' . filesize($realFile));
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: no-store, must-revalidate');

        readfile($realFile);
        exit;

    } catch (\Exception $e) {
        http_response_code(500);
        echo 'Error downloading file';
    }
});

$router->get('/company/documents/view', function () use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);

    $docId = (int)($_GET['id'] ?? 0);
    $companyId = (int)(getSessionCompanyId() ?? 0);

    if ($docId <= 0 || $companyId <= 0) {
        http_response_code(404);
        echo 'Document not found';
        return;
    }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company || $company['status'] !== 'active') {
            http_response_code(404);
            echo 'Company not found';
            return;
        }

        $dbIdentifier = $company['db_identifier'];
        $localDbConfig = $config['database'];
        $localDbConfig['database'] = $dbIdentifier;
        $localDb = new \App\Core\Database($localDbConfig);
        $localPdo = $localDb->connection();
        applyLocalMigrations($localPdo);

        try {
            $localPdo->query("SELECT 1 FROM documents LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $migrationSql = file_get_contents(base_path('database/migrations-local/007_create_company_documents.sql'));
            $localPdo->exec($migrationSql);
        }

        $docStmt = $localPdo->prepare('SELECT * FROM documents WHERE id = ?');
        $docStmt->execute([$docId]);
        $doc = $docStmt->fetch(PDO::FETCH_ASSOC);

        if (!$doc) {
            http_response_code(404);
            echo 'Document not found';
            return;
        }

        $storageBase = storage_path('companies/' . $companyId . '/documents/');
        $filePath = $storageBase . $doc['entity_type'] . '/' . $doc['entity_id'] . '/' . $doc['stored_name'];

        $realBase = realpath($storageBase);
        $realFile = realpath($filePath);
        if ($realFile === false || !str_starts_with($realFile, $realBase)) {
            http_response_code(404);
            echo 'File not found';
            return;
        }

        if (!file_exists($realFile)) {
            http_response_code(404);
            echo 'File not found';
            return;
        }

        $mimeType = $doc['mime_type'] ?: 'application/octet-stream';
        $originalName = $doc['original_name'] ?: 'document';

        header('Content-Type: ' . $mimeType);
        header('Content-Disposition: inline; filename="' . $originalName . '"');
        header('Content-Length: ' . filesize($realFile));
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: no-store, must-revalidate');

        readfile($realFile);
        exit;

    } catch (\Exception $e) {
        http_response_code(500);
        echo 'Error viewing file';
    }
});

$router->post('/company/documents/delete', function () use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);

    $companyId = getSessionCompanyId();
    $docId = (int)($_GET['id'] ?? 0);
    $redirect = $_GET['redirect'] ?? '/company/dashboard';

    if ($docId <= 0 || !$companyId) {
        header('Location: ' . $redirect);
        exit;
    }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company || $company['status'] !== 'active') {
            header('Location: ' . $redirect);
            exit;
        }

        $dbIdentifier = $company['db_identifier'];
        $localDbConfig = $config['database'];
        $localDbConfig['database'] = $dbIdentifier;
        $localDb = new \App\Core\Database($localDbConfig);
        $localPdo = $localDb->connection();
                applyLocalMigrations($localPdo);

        $docStmt = $localPdo->prepare('SELECT * FROM documents WHERE id = ?');
        $docStmt->execute([$docId]);
        $doc = $docStmt->fetch(PDO::FETCH_ASSOC);

        if (!$doc) {
            header('Location: ' . $redirect);
            exit;
        }

        // Role-based access check for document deletion
        $isLogist = ($_SESSION['role_code'] ?? '') === 'logist';
        if ($isLogist) {
            $userId = (int)$_SESSION['user_id'];
            // Logist can only delete documents they uploaded
            $uploadedBy = $doc['uploaded_by_user_id'] ?? $doc['created_by_user_id'] ?? null;
            if ((int)$uploadedBy !== $userId) {
                header('Location: ' . $redirect);
                exit;
            }
        }

        $updateStmt = $localPdo->prepare('UPDATE documents SET deleted_at = NOW(), deleted_by_user_id = :uid, delete_comment = :comment, updated_at = NOW() WHERE id = :id');
        $updateStmt->execute([':uid' => (int)$_SESSION['user_id'], ':comment' => 'Удалено через интерфейс', ':id' => $docId]);

        header('Location: ' . $redirect);
        exit;
    } catch (\Exception $e) {
        header('Location: ' . $redirect);
        exit;
    }
});

$router->post('/company/documents/replace', function () use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);

    $companyId = getSessionCompanyId();
    $docId = (int)($_POST['replace_doc_id'] ?? 0);
    $redirect = $_POST['redirect'] ?? '/company/dashboard';

    if ($docId <= 0 || !$companyId) {
        header('Location: ' . $redirect);
        exit;
    }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company || $company['status'] !== 'active') {
            header('Location: ' . $redirect);
            exit;
        }

        $dbIdentifier = $company['db_identifier'];
        $localDbConfig = $config['database'];
        $localDbConfig['database'] = $dbIdentifier;
        $localDb = new \App\Core\Database($localDbConfig);
        $localPdo = $localDb->connection();
                applyLocalMigrations($localPdo);

        $docStmt = $localPdo->prepare('SELECT * FROM documents WHERE id = ?');
        $docStmt->execute([$docId]);
        $oldDoc = $docStmt->fetch(PDO::FETCH_ASSOC);

        if (!$oldDoc) {
            header('Location: ' . $redirect);
            exit;
        }

        $fileError = $_FILES['document_file']['error'] ?? UPLOAD_ERR_NO_FILE;
        if ($fileError !== UPLOAD_ERR_OK) {
            header('Location: ' . $redirect);
            exit;
        }

        $originalName = $_FILES['document_file']['name'];
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $allowedExt = ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'doc', 'docx', 'xls', 'xlsx'];
        if (!in_array($ext, $allowedExt, true)) {
            header('Location: ' . $redirect);
            exit;
        }

        $fileSize = $_FILES['document_file']['size'];
        if ($fileSize > 20 * 1024 * 1024) {
            header('Location: ' . $redirect);
            exit;
        }

        if (strpos($originalName, '../') !== false || strpos($originalName, '..\\') !== false
            || strpos($originalName, '/') !== false || strpos($originalName, '\\') !== false) {
            header('Location: ' . $redirect);
            exit;
        }

        $storageBase = storage_path('companies/' . $companyId . '/documents/');
        $entityDir = $storageBase . $oldDoc['entity_type'] . '/' . $oldDoc['entity_id'] . '/';
        if (!is_dir($entityDir)) {
            mkdir($entityDir, 0755, true);
        }

        $storedName = 'doc_' . uniqid() . '.' . $ext;
        $tmpPath = $_FILES['document_file']['tmp_name'];

        if (!move_uploaded_file($tmpPath, $entityDir . $storedName)) {
            header('Location: ' . $redirect);
            exit;
        }

        $mimeType = $_FILES['document_file']['type'] ?: mime_content_type($entityDir . $storedName);
        $documentType = trim($_POST['document_type'] ?? $oldDoc['document_type'] ?? '');

        $updateStmt = $localPdo->prepare(
            'UPDATE documents SET original_name = :oname, stored_name = :sname, mime_type = :mime,
             file_size = :fsize, document_type = :dtype, status = :status,
             deleted_at = NULL, deleted_by_user_id = NULL, delete_comment = NULL,
             uploaded_by_user_id = :uid, uploaded_by_role = :urole, updated_at = NOW()
             WHERE id = :id'
        );
        $updateStmt->execute([
            ':oname' => $originalName,
            ':sname' => $storedName,
            ':mime' => $mimeType,
            ':fsize' => $fileSize,
            ':dtype' => $documentType ?: null,
            ':status' => 'uploaded',
            ':uid' => (int)$_SESSION['user_id'],
            ':urole' => $_SESSION['role_code'] ?? null,
            ':id' => $docId,
        ]);

        header('Location: ' . $redirect);
        exit;
    } catch (\Exception $e) {
        header('Location: ' . $redirect);
        exit;
    }
});

// -- Document Types (catalog/directory) --

$router->get('/company/document-types', function () use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);
    $pageTitle = 'Типы документов';
    $pageContext = 'Типы документов › Компания';

    $companyId = (int)(getSessionCompanyId() ?? 0);
    $filterEntity = $_GET['entity_type'] ?? '';
    $deleteError = null;
    $deleteSuccess = false;
    $types = [];
    $entityTypes = [];
    $dbError = null;

    if ($companyId <= 0) {
        $company = null;
        ob_start(); require base_path('app/View/pages/company_document_types.php');
        $content = ob_get_clean(); require base_path('app/View/layouts/main.php');
        return;
    }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company) {
            ob_start(); require base_path('app/View/pages/company_document_types.php');
            $content = ob_get_clean(); require base_path('app/View/layouts/main.php');
            return;
        }

        $pageContext = 'Типы документов › Компания: ' . $company['name'];

        if ($company['status'] !== 'active') {
            ob_start(); require base_path('app/View/pages/company_document_types.php');
            $content = ob_get_clean(); require base_path('app/View/layouts/main.php');
            return;
        }

        $dbIdentifier = $company['db_identifier'];
        $localDbConfig = $config['database']; $localDbConfig['database'] = $dbIdentifier;
        $localDb = new \App\Core\Database($localDbConfig); $localPdo = $localDb->connection();
        applyLocalMigrations($localPdo);

        try {
            $localPdo->query("SELECT 1 FROM document_types LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $migrationSql = file_get_contents(base_path('database/migrations-local/024_create_document_types.sql'));
            $localPdo->exec($migrationSql);
            $migrationSql25 = file_get_contents(base_path('database/migrations-local/025_add_document_type_id.sql'));
            $localPdo->exec($migrationSql25);
        }

        if ($filterEntity !== '') {
            $typeStmt = $localPdo->prepare('SELECT * FROM document_types WHERE entity_type = ? OR entity_type IS NULL ORDER BY sort_order, name');
            $typeStmt->execute([$filterEntity]);
        } else {
            $typeStmt = $localPdo->query('SELECT * FROM document_types ORDER BY entity_type, sort_order, name');
        }
        $types = $typeStmt->fetchAll(PDO::FETCH_ASSOC);

        $etStmt = $localPdo->query("SELECT DISTINCT entity_type FROM document_types WHERE entity_type IS NOT NULL ORDER BY entity_type");
        $entityTypes = array_column($etStmt->fetchAll(PDO::FETCH_ASSOC), 'entity_type');
    } catch (\Exception $e) {
        $company = $company ?? null;
        $types = [];
        $entityTypes = [];
        $dbError = 'Не удалось загрузить типы документов: ' . $e->getMessage();
    }

    ob_start(); require base_path('app/View/pages/company_document_types.php');
    $content = ob_get_clean(); require base_path('app/View/layouts/main.php');
});

$router->get('/company/document-types/create', function () use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);
    $pageTitle = 'Создать тип документа';
    $pageContext = 'Типы документов › Компания';

    $companyId = (int)(getSessionCompanyId() ?? 0);
    $isEdit = false;
    $editId = 0;
    $existingType = null;
    $success = false;
    $errors = []; $old = []; $formError = null; $createdType = null;

    if ($companyId <= 0) {
        $company = null;
        ob_start(); require base_path('app/View/pages/company_document_types_form.php');
        $content = ob_get_clean(); require base_path('app/View/layouts/main.php');
        return;
    }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company) {
            ob_start(); require base_path('app/View/pages/company_document_types_form.php');
            $content = ob_get_clean(); require base_path('app/View/layouts/main.php');
            return;
        }

        $pageContext = 'Типы документов › Компания: ' . $company['name'];

        if ($company['status'] !== 'active') {
            ob_start(); require base_path('app/View/pages/company_document_types_form.php');
            $content = ob_get_clean(); require base_path('app/View/layouts/main.php');
            return;
        }
    } catch (\Exception $e) {
        $company = null;
        $formError = 'Ошибка: ' . $e->getMessage();
    }

    ob_start(); require base_path('app/View/pages/company_document_types_form.php');
    $content = ob_get_clean(); require base_path('app/View/layouts/main.php');
});

$router->post('/company/document-types/create', function () use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);
    $pageTitle = 'Создать тип документа';
    $pageContext = 'Типы документов › Компания';

    $companyId = (int)(getSessionCompanyId() ?? 0);
    $isEdit = false;
    $editId = 0;
    $existingType = null;
    $success = false;
    $errors = []; $old = $_POST; $formError = null; $createdType = null;

    if ($companyId <= 0) {
        $company = null;
        ob_start(); require base_path('app/View/pages/company_document_types_form.php');
        $content = ob_get_clean(); require base_path('app/View/layouts/main.php');
        return;
    }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company) { $company = null; $formError = 'Компания не найдена'; goto renderDTForm; }
        $pageContext = 'Типы документов › Компания: ' . $company['name'];

        if ($company['status'] !== 'active') { $formError = 'Создание недоступно'; goto renderDTForm; }

        $dbIdentifier = $company['db_identifier'];
        $localDbConfig = $config['database']; $localDbConfig['database'] = $dbIdentifier;
        $localDb = new \App\Core\Database($localDbConfig); $localPdo = $localDb->connection();
        applyLocalMigrations($localPdo);

        try { $localPdo->query("SELECT 1 FROM document_types LIMIT 1")->fetch(); }
        catch (\Exception $e) {
            $localPdo->exec(file_get_contents(base_path('database/migrations-local/024_create_document_types.sql')));
            $localPdo->exec(file_get_contents(base_path('database/migrations-local/025_add_document_type_id.sql')));
        }

        $name = trim($_POST['name'] ?? '');
        $code = trim($_POST['code'] ?? '');
        $entityType = trim($_POST['entity_type'] ?? '');
        $sortOrder = (int)($_POST['sort_order'] ?? 0);

        if ($name === '') { $errors['name'] = 'Обязательное поле'; }
        if (!empty($errors)) { goto renderDTForm; }

        $insert = $localPdo->prepare(
            'INSERT INTO document_types (name, code, entity_type, category, sort_order, created_by_user_id, created_by_role)
             VALUES (:name, :code, :entity_type, :category, :sort_order, :uid, :role)'
        );
        $insert->execute([
            ':name' => $name,
            ':code' => $code !== '' ? $code : null,
            ':entity_type' => $entityType !== '' ? $entityType : null,
            ':category' => 'custom',
            ':sort_order' => $sortOrder,
            ':uid' => (int)$_SESSION['user_id'],
            ':role' => $_SESSION['role_code'],
        ]);

        $newId = $localPdo->lastInsertId();
        $createdType = ['id' => $newId, 'name' => $name, 'code' => $code, 'entity_type' => $entityType, 'category' => 'custom'];
        $success = true;
    } catch (\Exception $e) {
        $company = $company ?? null;
        $formError = 'Ошибка создания: ' . $e->getMessage();
    }

    renderDTForm:
    ob_start(); require base_path('app/View/pages/company_document_types_form.php');
    $content = ob_get_clean(); require base_path('app/View/layouts/main.php');
});

$router->get('/company/document-types/{id}/edit', function ($id) use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);
    $pageTitle = 'Редактировать тип документа';
    $pageContext = 'Типы документов › Компания';

    $companyId = (int)(getSessionCompanyId() ?? 0);
    $isEdit = true;
    $editId = (int)$id;
    $existingType = null;
    $success = false;
    $errors = []; $old = []; $formError = null; $createdType = null;

    if ($companyId <= 0) {
        $company = null;
        ob_start(); require base_path('app/View/pages/company_document_types_form.php');
        $content = ob_get_clean(); require base_path('app/View/layouts/main.php');
        return;
    }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company) {
            ob_start(); require base_path('app/View/pages/company_document_types_form.php');
            $content = ob_get_clean(); require base_path('app/View/layouts/main.php');
            return;
        }

        $pageContext = 'Типы документов › Компания: ' . $company['name'];

        if ($company['status'] !== 'active') {
            ob_start(); require base_path('app/View/pages/company_document_types_form.php');
            $content = ob_get_clean(); require base_path('app/View/layouts/main.php');
            return;
        }

        $dbIdentifier = $company['db_identifier'];
        $localDbConfig = $config['database']; $localDbConfig['database'] = $dbIdentifier;
        $localDb = new \App\Core\Database($localDbConfig); $localPdo = $localDb->connection();
        applyLocalMigrations($localPdo);

        try { $localPdo->query("SELECT 1 FROM document_types LIMIT 1")->fetch(); }
        catch (\Exception $e) {
            $localPdo->exec(file_get_contents(base_path('database/migrations-local/024_create_document_types.sql')));
            $localPdo->exec(file_get_contents(base_path('database/migrations-local/025_add_document_type_id.sql')));
        }

        $typeStmt = $localPdo->prepare('SELECT * FROM document_types WHERE id = ?');
        $typeStmt->execute([$editId]);
        $existingType = $typeStmt->fetch(PDO::FETCH_ASSOC);

        if (!$existingType) {
            $formError = 'Тип документа не найден';
        }
    } catch (\Exception $e) {
        $company = null;
        $formError = 'Ошибка: ' . $e->getMessage();
    }

    ob_start(); require base_path('app/View/pages/company_document_types_form.php');
    $content = ob_get_clean(); require base_path('app/View/layouts/main.php');
});

$router->post('/company/document-types/{id}/edit', function ($id) use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);
    $pageTitle = 'Редактировать тип документа';
    $pageContext = 'Типы документов › Компания';

    $companyId = (int)(getSessionCompanyId() ?? 0);
    $isEdit = true;
    $editId = (int)$id;
    $existingType = null;
    $success = false;
    $errors = []; $old = $_POST; $formError = null; $createdType = null;

    if ($companyId <= 0) {
        $company = null;
        ob_start(); require base_path('app/View/pages/company_document_types_form.php');
        $content = ob_get_clean(); require base_path('app/View/layouts/main.php');
        return;
    }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company) { $company = null; $formError = 'Компания не найдена'; goto renderDTEditForm; }
        $pageContext = 'Типы документов › Компания: ' . $company['name'];

        if ($company['status'] !== 'active') { $formError = 'Редактирование недоступно'; goto renderDTEditForm; }

        $dbIdentifier = $company['db_identifier'];
        $localDbConfig = $config['database']; $localDbConfig['database'] = $dbIdentifier;
        $localDb = new \App\Core\Database($localDbConfig); $localPdo = $localDb->connection();
        applyLocalMigrations($localPdo);

        try { $localPdo->query("SELECT 1 FROM document_types LIMIT 1")->fetch(); }
        catch (\Exception $e) {
            $localPdo->exec(file_get_contents(base_path('database/migrations-local/024_create_document_types.sql')));
            $localPdo->exec(file_get_contents(base_path('database/migrations-local/025_add_document_type_id.sql')));
        }

        $typeStmt = $localPdo->prepare('SELECT * FROM document_types WHERE id = ?');
        $typeStmt->execute([$editId]);
        $existingType = $typeStmt->fetch(PDO::FETCH_ASSOC);

        if (!$existingType) { $formError = 'Тип документа не найден'; goto renderDTEditForm; }

        $name = trim($_POST['name'] ?? '');
        $code = trim($_POST['code'] ?? '');
        $entityType = trim($_POST['entity_type'] ?? '');
        $sortOrder = (int)($_POST['sort_order'] ?? 0);

        if ($name === '') { $errors['name'] = 'Обязательное поле'; }
        if (!empty($errors)) { goto renderDTEditForm; }

        $update = $localPdo->prepare(
            'UPDATE document_types SET name = :name, code = :code, entity_type = :entity_type, sort_order = :sort_order, updated_at = NOW()
             WHERE id = :id'
        );
        $update->execute([
            ':name' => $name,
            ':code' => $code !== '' ? $code : null,
            ':entity_type' => $entityType !== '' ? $entityType : null,
            ':sort_order' => $sortOrder,
            ':id' => $editId,
        ]);

        $createdType = ['id' => $editId, 'name' => $name, 'code' => $code, 'entity_type' => $entityType, 'category' => $existingType['category']];
        $success = true;
    } catch (\Exception $e) {
        $company = $company ?? null;
        $formError = 'Ошибка обновления: ' . $e->getMessage();
    }

    renderDTEditForm:
    ob_start(); require base_path('app/View/pages/company_document_types_form.php');
    $content = ob_get_clean(); require base_path('app/View/layouts/main.php');
});

$router->post('/company/document-types/{id}/delete', function ($id) use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);
    $pageTitle = 'Типы документов';
    $pageContext = 'Типы документов › Компания';

    $companyId = (int)(getSessionCompanyId() ?? 0);
    $editId = (int)$id;
    $deleteError = null;
    $deleteSuccess = false;
    $types = [];
    $entityTypes = [];
    $dbError = null;
    $filterEntity = '';

    if ($companyId <= 0) {
        $company = null;
        ob_start(); require base_path('app/View/pages/company_document_types.php');
        $content = ob_get_clean(); require base_path('app/View/layouts/main.php');
        return;
    }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company) {
            ob_start(); require base_path('app/View/pages/company_document_types.php');
            $content = ob_get_clean(); require base_path('app/View/layouts/main.php');
            return;
        }

        $pageContext = 'Типы документов › Компания: ' . $company['name'];

        if ($company['status'] !== 'active') {
            ob_start(); require base_path('app/View/pages/company_document_types.php');
            $content = ob_get_clean(); require base_path('app/View/layouts/main.php');
            return;
        }

        $dbIdentifier = $company['db_identifier'];
        $localDbConfig = $config['database']; $localDbConfig['database'] = $dbIdentifier;
        $localDb = new \App\Core\Database($localDbConfig); $localPdo = $localDb->connection();
        applyLocalMigrations($localPdo);

        try { $localPdo->query("SELECT 1 FROM document_types LIMIT 1")->fetch(); }
        catch (\Exception $e) {
            $localPdo->exec(file_get_contents(base_path('database/migrations-local/024_create_document_types.sql')));
            $localPdo->exec(file_get_contents(base_path('database/migrations-local/025_add_document_type_id.sql')));
        }

        $typeStmt = $localPdo->prepare('SELECT * FROM document_types WHERE id = ?');
        $typeStmt->execute([$editId]);
        $docType = $typeStmt->fetch(PDO::FETCH_ASSOC);

        if (!$docType) {
            $deleteError = 'Тип документа не найден';
        } elseif ($docType['category'] === 'predefined') {
            $deleteError = 'Нельзя удалить системный тип документа';
        } else {
            $delStmt = $localPdo->prepare('DELETE FROM document_types WHERE id = ?');
            $delStmt->execute([$editId]);
            $deleteSuccess = true;
        }

        $typeStmt = $localPdo->query('SELECT * FROM document_types ORDER BY entity_type, sort_order, name');
        $types = $typeStmt->fetchAll(PDO::FETCH_ASSOC);
        $etStmt = $localPdo->query("SELECT DISTINCT entity_type FROM document_types WHERE entity_type IS NOT NULL ORDER BY entity_type");
        $entityTypes = array_column($etStmt->fetchAll(PDO::FETCH_ASSOC), 'entity_type');
    } catch (\Exception $e) {
        $company = $company ?? null;
        $types = [];
        $entityTypes = [];
        $dbError = 'Ошибка: ' . $e->getMessage();
    }

    ob_start(); require base_path('app/View/pages/company_document_types.php');
    $content = ob_get_clean(); require base_path('app/View/layouts/main.php');
});
