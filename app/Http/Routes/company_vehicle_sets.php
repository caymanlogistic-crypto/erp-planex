<?php

$router->get('/company/vehicle-sets', function () use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);
    $pageTitle = 'Транспорт';
    $pageContext = 'Транспорт › Компания';

    $companyId = (int)(getSessionCompanyId() ?? 0);

    if ($companyId <= 0) {
        $company = null; $vehicleSets = []; $dbError = null;
        ob_start(); require base_path('app/View/pages/company_vehicle_sets.php');
        $content = ob_get_clean(); require base_path('app/View/layouts/main.php');
        return;
    }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$company) {
            $company = null; $vehicleSets = []; $dbError = null;
            ob_start(); require base_path('app/View/pages/company_vehicle_sets.php');
            $content = ob_get_clean(); require base_path('app/View/layouts/main.php');
            return;
        }
        $pageContext = 'Транспорт › Компания: ' . $company['name'];
        $topbarCrumbs = [
            ['label' => mb_strtoupper($company['name']), 'url' => '/company/dashboard'],
            ['label' => 'Подрядчики', 'url' => null],
            ['label' => 'Список транспорта', 'url' => null],
        ];
        if ($company['status'] !== 'active') {
            $vehicleSets = []; $dbError = null;
            ob_start(); require base_path('app/View/pages/company_vehicle_sets.php');
            $content = ob_get_clean(); require base_path('app/View/layouts/main.php');
            return;
        }

        $dbIdentifier = $company['db_identifier'];
        $localDbConfig = $config['database']; $localDbConfig['database'] = $dbIdentifier;
        $localDb = new \App\Core\Database($localDbConfig); $localPdo = $localDb->connection();
        applyLocalMigrations($localPdo);

        try { $localPdo->query("SELECT 1 FROM vehicle_sets LIMIT 1")->fetch(); }
        catch (\Exception $e) {
            $migrationSql = file_get_contents(base_path('database/migrations-local/017_create_vehicle_sets.sql'));
            $localPdo->exec($migrationSql);
        }

        $isLogist = ($_SESSION['role_code'] ?? '') === 'logist';
        if ($isLogist) {
            $userId = (int)$_SESSION['user_id'];
            $stmt = $localPdo->prepare(
                "SELECT vs.*,
                 vu1.plate_number AS primary_plate,
                 vu1.brand AS primary_brand,
                 vu1.model AS primary_model,
                 vu1.volume_m3 AS primary_volume_m3,
                 vu1.capacity_tons AS primary_capacity_tons,
                 vu1.diagnostic_card_number AS primary_diagnostic_card_number,
                 vu2.plate_number AS secondary_plate,
                 vu2.brand AS secondary_brand,
                 vu2.model AS secondary_model,
                 vu2.volume_m3 AS secondary_volume_m3,
                 vu2.capacity_tons AS secondary_capacity_tons,
                 vu2.diagnostic_card_number AS secondary_diagnostic_card_number,
                 EXISTS (SELECT 1 FROM documents d INNER JOIN document_types dt ON dt.id = d.document_type_id WHERE d.entity_type = 'vehicle_unit' AND d.entity_id = vu1.id AND d.deleted_at IS NULL AND dt.code = 'sts') AS has_sts,
                 EXISTS (SELECT 1 FROM documents d INNER JOIN document_types dt ON dt.id = d.document_type_id WHERE d.entity_type = 'vehicle_unit' AND d.entity_id = vu1.id AND d.deleted_at IS NULL AND dt.code = 'diagnostic_card') AS has_diagnostic_card_doc,
                 EXISTS (SELECT 1 FROM documents d WHERE d.entity_type = 'vehicle_unit' AND d.entity_id = vu1.id AND d.deleted_at IS NULL AND d.mime_type LIKE 'image/%') AS has_photo,
                 EXISTS (SELECT 1 FROM documents d INNER JOIN document_types dt ON dt.id = d.document_type_id WHERE d.entity_type = 'vehicle_unit' AND d.entity_id = vu2.id AND d.deleted_at IS NULL AND dt.code = 'sts') AS has_sts_sec,
                 EXISTS (SELECT 1 FROM documents d INNER JOIN document_types dt ON dt.id = d.document_type_id WHERE d.entity_type = 'vehicle_unit' AND d.entity_id = vu2.id AND d.deleted_at IS NULL AND dt.code = 'diagnostic_card') AS has_diagnostic_card_doc_sec,
                 EXISTS (SELECT 1 FROM documents d WHERE d.entity_type = 'vehicle_unit' AND d.entity_id = vu2.id AND d.deleted_at IS NULL AND d.mime_type LIKE 'image/%') AS has_photo_sec
                 FROM vehicle_sets vs
                 LEFT JOIN vehicle_units vu1 ON vs.primary_vehicle_unit_id = vu1.id
                 LEFT JOIN vehicle_units vu2 ON vs.secondary_vehicle_unit_id = vu2.id
                 WHERE (vs.created_by_user_id = ? OR vs.id IN (SELECT entity_id FROM entity_access_grants WHERE entity_type = 'vehicle_set' AND granted_to_user_id = ? AND access_level IN ('view','edit') AND revoked_at IS NULL))
                 ORDER BY vs.created_at DESC"
            );
            $stmt->execute([$userId, $userId]);
            $vehicleSets = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $stmt = $localPdo->query(
                "SELECT vs.*,
                 vu1.plate_number AS primary_plate,
                 vu1.brand AS primary_brand,
                 vu1.model AS primary_model,
                 vu1.volume_m3 AS primary_volume_m3,
                 vu1.capacity_tons AS primary_capacity_tons,
                 vu1.diagnostic_card_number AS primary_diagnostic_card_number,
                 vu2.plate_number AS secondary_plate,
                 vu2.brand AS secondary_brand,
                 vu2.model AS secondary_model,
                 vu2.volume_m3 AS secondary_volume_m3,
                 vu2.capacity_tons AS secondary_capacity_tons,
                 vu2.diagnostic_card_number AS secondary_diagnostic_card_number,
                 EXISTS (SELECT 1 FROM documents d INNER JOIN document_types dt ON dt.id = d.document_type_id WHERE d.entity_type = 'vehicle_unit' AND d.entity_id = vu1.id AND d.deleted_at IS NULL AND dt.code = 'sts') AS has_sts,
                 EXISTS (SELECT 1 FROM documents d INNER JOIN document_types dt ON dt.id = d.document_type_id WHERE d.entity_type = 'vehicle_unit' AND d.entity_id = vu1.id AND d.deleted_at IS NULL AND dt.code = 'diagnostic_card') AS has_diagnostic_card_doc,
                 EXISTS (SELECT 1 FROM documents d WHERE d.entity_type = 'vehicle_unit' AND d.entity_id = vu1.id AND d.deleted_at IS NULL AND d.mime_type LIKE 'image/%') AS has_photo,
                 EXISTS (SELECT 1 FROM documents d INNER JOIN document_types dt ON dt.id = d.document_type_id WHERE d.entity_type = 'vehicle_unit' AND d.entity_id = vu2.id AND d.deleted_at IS NULL AND dt.code = 'sts') AS has_sts_sec,
                 EXISTS (SELECT 1 FROM documents d INNER JOIN document_types dt ON dt.id = d.document_type_id WHERE d.entity_type = 'vehicle_unit' AND d.entity_id = vu2.id AND d.deleted_at IS NULL AND dt.code = 'diagnostic_card') AS has_diagnostic_card_doc_sec,
                 EXISTS (SELECT 1 FROM documents d WHERE d.entity_type = 'vehicle_unit' AND d.entity_id = vu2.id AND d.deleted_at IS NULL AND d.mime_type LIKE 'image/%') AS has_photo_sec
                 FROM vehicle_sets vs
                 LEFT JOIN vehicle_units vu1 ON vs.primary_vehicle_unit_id = vu1.id
                 LEFT JOIN vehicle_units vu2 ON vs.secondary_vehicle_unit_id = vu2.id
                 ORDER BY vs.created_at DESC"
            );
            $vehicleSets = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        $dbError = null;
    } catch (\Exception $e) {
        $company = $company ?? null; $vehicleSets = [];
        $dbError = 'Не удалось подключиться к базе данных компании.';
    }

    ob_start(); require base_path('app/View/pages/company_vehicle_sets.php');
    $content = ob_get_clean(); require base_path('app/View/layouts/main.php');
});

$router->get('/company/vehicle-sets/create', function () use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);
    $pageTitle = 'Создать транспорт';
    $pageContext = 'Транспорт > Компания';

    $companyId = (int) (getSessionCompanyId() ?? 0);
    $company = null;
    $success = false;
    $errors = [];
    $old = [];
    $formError = null;
    $createdVehicleSet = null;
    $docErrors = [];
    $uploadedDocs = [];
    $docTypes = [];

    if ($companyId <= 0) {
        ob_start(); require base_path('app/View/pages/company_vehicle_sets_create.php');
        $content = ob_get_clean(); require base_path('app/View/layouts/main.php');
        return;
    }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$company) {
            $company = null;
            goto renderCreateVehicleSet;
        }

        $pageContext = 'Транспорт > Компания: ' . $company['name'];

        $localDbConfig = $config['database'];
        $localDbConfig['database'] = $company['db_identifier'];
        $localDb = new \App\Core\Database($localDbConfig);
        $localPdo = $localDb->connection();
        applyLocalMigrations($localPdo);

        try {
            $localPdo->query('SELECT 1 FROM document_types LIMIT 1')->fetch();
        } catch (\Exception $e) {
            $localPdo->exec(file_get_contents(base_path('database/migrations-local/024_create_document_types.sql')));
            $localPdo->exec(file_get_contents(base_path('database/migrations-local/025_add_document_type_id.sql')));
        }

        $docTypes = $localPdo->query("SELECT id, name, code, entity_type FROM document_types WHERE entity_type = 'vehicle_unit' OR entity_type IS NULL ORDER BY sort_order, name")->fetchAll(PDO::FETCH_ASSOC);
    } catch (\Exception $e) {
        $company = null;
        $formError = 'Ошибка: ' . $e->getMessage();
    }

    renderCreateVehicleSet:
    ob_start(); require base_path('app/View/pages/company_vehicle_sets_create.php');
    $content = ob_get_clean(); require base_path('app/View/layouts/main.php');
});

$router->post('/company/vehicle-sets/create', function () use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);
    $pageTitle = 'Создать транспорт';
    $pageContext = 'Транспорт > Компания';

    $companyId = (int) (getSessionCompanyId() ?? 0);
    $errors = [];
    $old = $_POST;
    $formError = null;
    $success = false;
    $createdVehicleSet = null;
    $docErrors = [];
    $uploadedDocs = [];
    $docTypes = [];
    $company = null;

    if ($companyId <= 0) {
        $formError = 'Компания не найдена';
        ob_start(); require base_path('app/View/pages/company_vehicle_sets_create.php');
        $content = ob_get_clean(); require base_path('app/View/layouts/main.php');
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
            goto renderCreateVehicleSetPost;
        }

        $pageContext = 'Транспорт > Компания: ' . $company['name'];
        if (($company['status'] ?? '') !== 'active') {
            $formError = 'Компания недоступна';
            goto renderCreateVehicleSetPost;
        }

        $localDbConfig = $config['database'];
        $localDbConfig['database'] = $company['db_identifier'];
        $localDb = new \App\Core\Database($localDbConfig);
        $localPdo = $localDb->connection();
        applyLocalMigrations($localPdo);

        try {
            $localPdo->query('SELECT 1 FROM document_types LIMIT 1')->fetch();
        } catch (\Exception $e) {
            $localPdo->exec(file_get_contents(base_path('database/migrations-local/024_create_document_types.sql')));
            $localPdo->exec(file_get_contents(base_path('database/migrations-local/025_add_document_type_id.sql')));
        }
        $docTypes = $localPdo->query("SELECT id, name, code, entity_type FROM document_types WHERE entity_type = 'vehicle_unit' OR entity_type IS NULL ORDER BY sort_order, name")->fetchAll(PDO::FETCH_ASSOC);

        if (isPostTruncated()) {
            $formError = 'Общий размер отправки превышает серверный лимит. Для ERP требуется настройка post_max_size не менее 100M. Уменьшите количество файлов или обратитесь к администратору.';
            goto renderCreateVehicleSetPost;
        }

        $rules = vehicleSetTypeRules();
        $docSections = vehicleSetPredefinedDocumentSections();
        $setType = trim((string) ($_POST['set_type'] ?? ''));
        $status = trim((string) ($_POST['status'] ?? 'active'));
        $comments = trim((string) ($_POST['comments'] ?? ''));
        $unitsInput = isset($_POST['units']) && is_array($_POST['units']) ? $_POST['units'] : [];
        $activeRoles = vehicleSetVisibleUnitRoles($setType);
        $unitPayload = [];
        $createdUnits = [];
        $createdUnitIds = [];
        $storedPaths = [];

        if ($setType === '' || !isset($rules[$setType])) {
            $errors['set_type'] = 'Выберите тип комплекта';
        }
        if (!in_array($status, ['active', 'inactive'], true)) {
            $status = 'active';
        }

        foreach ($activeRoles as $role) {
            $unitRule = $rules[$setType]['units'][$role] ?? [];
            $source = isset($unitsInput[$role]) && is_array($unitsInput[$role]) ? $unitsInput[$role] : [];
            $label = (string) ($unitRule['label'] ?? 'Транспортная единица');
            $showCapacity = !empty($unitRule['show_capacity']);
            $showVolume = !empty($unitRule['show_volume']);
            $diagnosticCardDate = trim((string) ($source['diagnostic_card_date'] ?? ''));
            $payload = [
                'label' => $label,
                'unit_type' => (string) ($unitRule['unit_type'] ?? ''),
                'plate_number' => trim((string) ($source['plate_number'] ?? '')),
                'brand' => trim((string) ($source['brand'] ?? '')),
                'model' => trim((string) ($source['model'] ?? '')),
                'vin' => trim((string) ($source['vin'] ?? '')),
                'capacity_tons' => trim((string) ($source['capacity_tons'] ?? '')),
                'volume_m3' => trim((string) ($source['volume_m3'] ?? '')),
                'diagnostic_card_number' => trim((string) ($source['diagnostic_card_number'] ?? '')),
                'diagnostic_card_date' => $diagnosticCardDate,
                'show_capacity' => $showCapacity,
                'show_volume' => $showVolume,
            ];
            $unitPayload[$role] = $payload;

            if ($payload['plate_number'] === '') {
                $errors['units'][$role]['plate_number'] = 'Укажите госномер';
            }
            if ($payload['vin'] !== '' && !preg_match('/^[A-HJ-NPR-Z0-9]{17}$/', strtoupper($payload['vin']))) {
                $errors['units'][$role]['vin'] = 'Формат VIN: 17 символов';
            }
            if ($payload['diagnostic_card_number'] !== '' && !ctype_digit($payload['diagnostic_card_number'])) {
                $errors['units'][$role]['diagnostic_card_number'] = 'Только цифры';
            }
            if ($diagnosticCardDate !== '') {
                $dateCheck = \DateTime::createFromFormat('Y-m-d', $diagnosticCardDate);
                if (!$dateCheck || $dateCheck->format('Y-m-d') !== $diagnosticCardDate) {
                    $errors['units'][$role]['diagnostic_card_date'] = 'Некорректная дата';
                }
            }
            if ($showCapacity) {
                if ($payload['capacity_tons'] !== '' && (!is_numeric($payload['capacity_tons']) || (float) $payload['capacity_tons'] <= 0)) {
                    $errors['units'][$role]['capacity_tons'] = 'Введите число';
                }
            } else {
                $payload['capacity_tons'] = '';
                $unitPayload[$role]['capacity_tons'] = '';
            }
            if ($showVolume) {
                if ($payload['volume_m3'] !== '' && (!is_numeric($payload['volume_m3']) || (float) $payload['volume_m3'] <= 0)) {
                    $errors['units'][$role]['volume_m3'] = 'Введите число';
                }
            } else {
                $payload['volume_m3'] = '';
                $unitPayload[$role]['volume_m3'] = '';
            }
        }

        $allowedExt = ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'doc', 'docx', 'rtf', 'odt', 'xls', 'xlsx', 'csv', 'ods', 'gif', 'bmp', 'tif', 'tiff', 'heic', 'heif'];
        $maxSize = 20 * 1024 * 1024;

        $normalizeNamedFiles = static function (array $fileBag, string $role, string $docCode): array {
            $names = $fileBag['name'][$role][$docCode] ?? [];
            $tmpNames = $fileBag['tmp_name'][$role][$docCode] ?? [];
            $errors = $fileBag['error'][$role][$docCode] ?? [];
            $sizes = $fileBag['size'][$role][$docCode] ?? [];
            $types = $fileBag['type'][$role][$docCode] ?? [];
            if (!is_array($names)) {
                $names = [$names];
                $tmpNames = [$tmpNames];
                $errors = [$errors];
                $sizes = [$sizes];
                $types = [$types];
            }
            $items = [];
            foreach ($names as $idx => $origName) {
                $items[] = [
                    'name' => trim((string) $origName),
                    'tmp_name' => (string) ($tmpNames[$idx] ?? ''),
                    'error' => (int) ($errors[$idx] ?? UPLOAD_ERR_NO_FILE),
                    'size' => (int) ($sizes[$idx] ?? 0),
                    'type' => (string) ($types[$idx] ?? ''),
                ];
            }
            return $items;
        };

        $normalizeRoleFiles = static function (array $fileBag, string $role): array {
            $names = $fileBag['name'][$role] ?? [];
            $tmpNames = $fileBag['tmp_name'][$role] ?? [];
            $errors = $fileBag['error'][$role] ?? [];
            $sizes = $fileBag['size'][$role] ?? [];
            $types = $fileBag['type'][$role] ?? [];
            if (!is_array($names)) {
                $names = [$names];
                $tmpNames = [$tmpNames];
                $errors = [$errors];
                $sizes = [$sizes];
                $types = [$types];
            }
            $items = [];
            foreach ($names as $idx => $origName) {
                $items[] = [
                    'name' => trim((string) $origName),
                    'tmp_name' => (string) ($tmpNames[$idx] ?? ''),
                    'error' => (int) ($errors[$idx] ?? UPLOAD_ERR_NO_FILE),
                    'size' => (int) ($sizes[$idx] ?? 0),
                    'type' => (string) ($types[$idx] ?? ''),
                ];
            }
            return $items;
        };

        foreach ($activeRoles as $role) {
            $docs = $docSections[$role] ?? [];
            $unitLabel = $unitPayload[$role]['label'] ?? 'Транспортная единица';
            foreach ($docs as $docMeta) {
                $files = $normalizeNamedFiles($_FILES['predef_doc'] ?? [], $role, $docMeta['doc_code']);
                foreach ($files as $file) {
                    if ($file['error'] === UPLOAD_ERR_NO_FILE || $file['name'] === '') {
                        continue;
                    }
                    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                    if (!in_array($ext, $allowedExt, true)) {
                        $docErrors[] = $unitLabel . ': Документ <' . $docMeta['name'] . '> имеет недопустимый формат';
                    }
                    if ($file['size'] > $maxSize) {
                        $docErrors[] = $unitLabel . ': Документ <' . $docMeta['name'] . '> превышает 20 МБ';
                    }
                    if (strpos($file['name'], '../') !== false || strpos($file['name'], '..\\') !== false || strpos($file['name'], '/') !== false || strpos($file['name'], '\\') !== false) {
                        $docErrors[] = $unitLabel . ': Документ <' . $docMeta['name'] . '> имеет недопустимое имя';
                    }
                }
            }

            $customSelected = $_POST['custom_doc_type'][$role] ?? [];
            $customNew = $_POST['custom_doc_type_new'][$role] ?? [];
            $customFiles = $normalizeRoleFiles($_FILES['custom_doc_file'] ?? [], $role);
            $rowCount = max(count($customFiles), is_array($customSelected) ? count($customSelected) : 0, is_array($customNew) ? count($customNew) : 0);
            for ($idx = 0; $idx < $rowCount; $idx++) {
                $file = $customFiles[$idx] ?? ['name' => '', 'error' => UPLOAD_ERR_NO_FILE, 'size' => 0, 'tmp_name' => '', 'type' => ''];
                $selectedType = trim((string) ((is_array($customSelected) ? ($customSelected[$idx] ?? '') : '')));
                $newType = trim((string) ((is_array($customNew) ? ($customNew[$idx] ?? '') : '')));
                $hasFile = $file['error'] !== UPLOAD_ERR_NO_FILE && $file['name'] !== '';
                $hasType = $selectedType !== '' || $newType !== '';
                if ($hasFile && !$hasType) {
                    $docErrors[] = $unitLabel . ': Укажите название произвольного документа #' . ($idx + 1);
                }
                if ($hasFile) {
                    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                    if (!in_array($ext, $allowedExt, true)) {
                        $docErrors[] = $unitLabel . ': Произвольный документ #' . ($idx + 1) . ' имеет недопустимый формат';
                    }
                    if ($file['size'] > $maxSize) {
                        $docErrors[] = $unitLabel . ': Произвольный документ #' . ($idx + 1) . ' превышает 20 МБ';
                    }
                    if (strpos($file['name'], '../') !== false || strpos($file['name'], '..\\') !== false || strpos($file['name'], '/') !== false || strpos($file['name'], '\\') !== false) {
                        $docErrors[] = $unitLabel . ': Произвольный документ #' . ($idx + 1) . ' имеет недопустимое имя';
                    }
                }
            }
        }

        if (!empty($errors) || !empty($docErrors)) {
            goto renderCreateVehicleSetPost;
        }

        // Check total upload size before transaction
        $totalSizeError = validateTotalUploadSize();
        if ($totalSizeError !== '') {
            $formError = $totalSizeError;
            goto renderCreateVehicleSetPost;
        }

        $storeUpload = static function (PDO $localPdo, int $companyId, string $entityType, int $entityId, string $docTypeName, string $docCode, array $file, string $category, array &$storedPaths) {
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $storedName = uniqid('doc_', true) . '.' . $ext;
            $relativeDir = 'companies/' . $companyId . '/documents/' . $entityType . '/' . $entityId;
            $absoluteDir = storage_path($relativeDir);
            if (!is_dir($absoluteDir) && !mkdir($absoluteDir, 0755, true)) {
                throw new RuntimeException('mkdir_failed');
            }
            $absolutePath = $absoluteDir . DIRECTORY_SEPARATOR . $storedName;
            if (!move_uploaded_file($file['tmp_name'], $absolutePath)) {
                throw new RuntimeException('move_failed');
            }
            $storedPaths[] = $absolutePath;
            $documentTypeId = ensureDocumentTypeRecord($localPdo, $docTypeName, $docCode, $entityType, $category);
            $insertDocument = $localPdo->prepare(
                'INSERT INTO documents (entity_type, entity_id, document_type, document_type_id, original_name, stored_name, relative_path, mime_type, file_size, status, uploaded_by_user_id, uploaded_by_role, created_by_user_id, created_by_role)
                 VALUES (:et, :eid, :dtype, :dtid, :oname, :sname, :rpath, :mime, :fsize, :status, :uid, :role, :cuid, :crole)'
            );
            $insertDocument->execute([
                ':et' => $entityType,
                ':eid' => $entityId,
                ':dtype' => $docTypeName,
                ':dtid' => $documentTypeId,
                ':oname' => $file['name'],
                ':sname' => $storedName,
                ':rpath' => $relativeDir . '/' . $storedName,
                ':mime' => $file['type'],
                ':fsize' => $file['size'],
                ':status' => 'uploaded',
                ':uid' => (int) $_SESSION['user_id'],
                ':role' => $_SESSION['role_code'],
                ':cuid' => (int) $_SESSION['user_id'],
                ':crole' => $_SESSION['role_code'],
            ]);
        };

        $localPdo->beginTransaction();
        try {
            $insertUnit = $localPdo->prepare(
                'INSERT INTO vehicle_units (plate_number, brand, model, unit_type, vin, capacity_tons, volume_m3, diagnostic_card_number, diagnostic_card_date, status, comments, created_by_user_id, created_by_role)
                 VALUES (:plate_number, :brand, :model, :unit_type, :vin, :capacity_tons, :volume_m3, :diagnostic_card_number, :diagnostic_card_date, :status, :comments, :created_by_user_id, :created_by_role)'
            );

            foreach ($activeRoles as $role) {
                $payload = $unitPayload[$role];
                $insertUnit->execute([
                    ':plate_number' => $payload['plate_number'],
                    ':brand' => $payload['brand'],
                    ':model' => $payload['model'],
                    ':unit_type' => $payload['unit_type'],
                    ':vin' => $payload['vin'],
                    ':capacity_tons' => ($payload['show_capacity'] && $payload['capacity_tons'] !== '') ? (float) $payload['capacity_tons'] : null,
                    ':volume_m3' => ($payload['show_volume'] && $payload['volume_m3'] !== '') ? (float) $payload['volume_m3'] : null,
                    ':diagnostic_card_number' => $payload['diagnostic_card_number'] !== '' ? $payload['diagnostic_card_number'] : null,
                    ':diagnostic_card_date' => $payload['diagnostic_card_date'] !== '' ? $payload['diagnostic_card_date'] : null,
                    ':status' => $status,
                    ':comments' => null,
                    ':created_by_user_id' => (int) $_SESSION['user_id'],
                    ':created_by_role' => $_SESSION['role_code'],
                ]);
                $unitId = (int) $localPdo->lastInsertId();
                $createdUnitIds[$role] = $unitId;
                $createdUnits[$role] = $payload + ['id' => $unitId];
            }

            $insertSet = $localPdo->prepare(
                'INSERT INTO vehicle_sets (set_type, primary_vehicle_unit_id, secondary_vehicle_unit_id, status, comments, created_by_user_id, created_by_role)
                 VALUES (:set_type, :primary_id, :secondary_id, :status, :comments, :uid, :role)'
            );
            $insertSet->execute([
                ':set_type' => $setType,
                ':primary_id' => (int) ($createdUnitIds['primary'] ?? 0),
                ':secondary_id' => isset($createdUnitIds['secondary']) ? (int) $createdUnitIds['secondary'] : null,
                ':status' => $status,
                ':comments' => $comments !== '' ? $comments : null,
                ':uid' => (int) $_SESSION['user_id'],
                ':role' => $_SESSION['role_code'],
            ]);
            $newSetId = (int) $localPdo->lastInsertId();

            foreach ($activeRoles as $role) {
                $docs = $docSections[$role] ?? [];
                foreach ($docs as $docMeta) {
                    $files = $normalizeNamedFiles($_FILES['predef_doc'] ?? [], $role, $docMeta['doc_code']);
                    foreach ($files as $file) {
                        if ($file['error'] === UPLOAD_ERR_NO_FILE || $file['name'] === '') {
                            continue;
                        }
                        $storeUpload($localPdo, $companyId, 'vehicle_unit', (int) $createdUnitIds[$role], $docMeta['name'], $docMeta['doc_code'], $file, 'predefined', $storedPaths);
                        $uploadedDocs[] = ($unitPayload[$role]['label'] ?? 'Транспортная единица') . ': ' . $docMeta['name'] . ' (' . $file['name'] . ')';
                    }
                }

                $customSelected = $_POST['custom_doc_type'][$role] ?? [];
                $customNew = $_POST['custom_doc_type_new'][$role] ?? [];
                $customFiles = $normalizeRoleFiles($_FILES['custom_doc_file'] ?? [], $role);
                $rowCount = max(count($customFiles), is_array($customSelected) ? count($customSelected) : 0, is_array($customNew) ? count($customNew) : 0);
                for ($idx = 0; $idx < $rowCount; $idx++) {
                    $file = $customFiles[$idx] ?? ['name' => '', 'error' => UPLOAD_ERR_NO_FILE, 'size' => 0, 'tmp_name' => '', 'type' => ''];
                    if ($file['error'] === UPLOAD_ERR_NO_FILE || $file['name'] === '') {
                        continue;
                    }
                    $selectedType = trim((string) ((is_array($customSelected) ? ($customSelected[$idx] ?? '') : '')));
                    $newType = trim((string) ((is_array($customNew) ? ($customNew[$idx] ?? '') : '')));
                    $docTypeName = $newType !== '' ? $newType : $selectedType;
                    $storeUpload($localPdo, $companyId, 'vehicle_unit', (int) $createdUnitIds[$role], $docTypeName, '', $file, 'custom', $storedPaths);
                    $uploadedDocs[] = ($unitPayload[$role]['label'] ?? 'Транспортная единица') . ': ' . $docTypeName . ' (' . $file['name'] . ')';
                }
            }

            $localPdo->commit();

            $createdVehicleSet = [
                'id' => $newSetId,
                'set_type' => $setType,
                'status' => $status,
                'comments' => $comments,
                'units' => $createdUnits,
                'primary_plate' => $createdUnits['primary']['plate_number'] ?? '',
                'secondary_plate' => $createdUnits['secondary']['plate_number'] ?? null,
                'primary_vehicle_unit_id' => $createdUnitIds['primary'] ?? null,
                'secondary_vehicle_unit_id' => $createdUnitIds['secondary'] ?? null,
            ];
            $success = true;
        } catch (\Throwable $e) {
            if ($localPdo->inTransaction()) {
                $localPdo->rollBack();
            }
            foreach ($storedPaths as $storedPath) {
                if (is_string($storedPath) && $storedPath !== '' && is_file($storedPath)) {
                    @unlink($storedPath);
                }
            }
            throw $e;
        }
    } catch (\Exception $e) {
        $company = $company ?? null;
        $formError = 'Ошибка: ' . $e->getMessage();
    }

    renderCreateVehicleSetPost:
    ob_start(); require base_path('app/View/pages/company_vehicle_sets_create.php');
    $content = ob_get_clean(); require base_path('app/View/layouts/main.php');
});

// --- Vehicle Set Modal View ---

$router->get('/company/vehicle-sets/{id}/modal-view', function ($id) use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);

    $companyId = (int) (getSessionCompanyId() ?? 0);
    if ($companyId <= 0) {
        http_response_code(404);
        echo '<div class="notice warn">Компания не найдена.</div>';
        exit;
    }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company || ($company['status'] ?? '') !== 'active') {
            http_response_code(404);
            echo '<div class="notice warn">Компания не найдена или неактивна.</div>';
            exit;
        }

        $localDbConfig = $config['database'];
        $localDbConfig['database'] = $company['db_identifier'];
        $localDb = new \App\Core\Database($localDbConfig);
        $localPdo = $localDb->connection();
        applyLocalMigrations($localPdo);

        $vehicleSetStmt = $localPdo->prepare('SELECT * FROM vehicle_sets WHERE id = ?');
        $vehicleSetStmt->execute([(int) $id]);
        $vehicleSet = $vehicleSetStmt->fetch(PDO::FETCH_ASSOC) ?: null;

        if (!$vehicleSet) {
            http_response_code(404);
            echo '<div class="notice warn">Транспорт не найден.</div>';
            exit;
        }

        $grantAccessLevel = null;
        $roleCode = $_SESSION['role_code'] ?? '';
        if ($roleCode === 'logist') {
            $userId = (int) ($_SESSION['user_id'] ?? 0);
            $grantCheck = $localPdo->prepare("SELECT access_level FROM entity_access_grants WHERE entity_type = 'vehicle_set' AND entity_id = ? AND granted_to_user_id = ? AND revoked_at IS NULL LIMIT 1");
            $grantCheck->execute([(int) $id, $userId]);
            $grantRow = $grantCheck->fetch(PDO::FETCH_ASSOC);
            $grantAccessLevel = $grantRow['access_level'] ?? null;
            $hasGrant = in_array($grantAccessLevel, ['view', 'edit'], true);
            if ((int) ($vehicleSet['created_by_user_id'] ?? 0) !== $userId && !$hasGrant) {
                http_response_code(403);
                echo '<div class="notice warn">У вас нет доступа к этой записи.</div>';
                exit;
            }
        }

        $unitIds = array_values(array_filter([
            (int) ($vehicleSet['primary_vehicle_unit_id'] ?? 0),
            (int) ($vehicleSet['secondary_vehicle_unit_id'] ?? 0),
        ]));

        $unitsByRole = ['primary' => [], 'secondary' => []];
        if (!empty($unitIds)) {
            $placeholders = implode(',', array_fill(0, count($unitIds), '?'));
            $unitsStmt = $localPdo->prepare("SELECT * FROM vehicle_units WHERE id IN ($placeholders)");
            $unitsStmt->execute($unitIds);
            foreach ($unitsStmt->fetchAll(PDO::FETCH_ASSOC) as $unit) {
                $unitId = (int) ($unit['id'] ?? 0);
                if ($unitId === (int) ($vehicleSet['primary_vehicle_unit_id'] ?? 0)) {
                    $unitsByRole['primary'] = $unit;
                } elseif ($unitId === (int) ($vehicleSet['secondary_vehicle_unit_id'] ?? 0)) {
                    $unitsByRole['secondary'] = $unit;
                }
            }
        }

        $docsByRole = ['primary' => [], 'secondary' => []];
        if (!empty($unitIds)) {
            $placeholders = implode(',', array_fill(0, count($unitIds), '?'));
            $docsStmt = $localPdo->prepare(
                "SELECT id, entity_id, document_type, original_name, mime_type, stored_name, file_size
                   FROM documents
                  WHERE entity_type = 'vehicle_unit'
                    AND entity_id IN ($placeholders)
                    AND deleted_at IS NULL
                  ORDER BY id"
            );
            $docsStmt->execute($unitIds);
            foreach ($docsStmt->fetchAll(PDO::FETCH_ASSOC) as $doc) {
                $entityId = (int) ($doc['entity_id'] ?? 0);
                if ($entityId === (int) ($vehicleSet['primary_vehicle_unit_id'] ?? 0)) {
                    $docsByRole['primary'][] = $doc;
                } elseif ($entityId === (int) ($vehicleSet['secondary_vehicle_unit_id'] ?? 0)) {
                    $docsByRole['secondary'][] = $doc;
                }
            }
        }

        $rules = vehicleSetTypeRules();
        $unitTitles = [
            'primary' => $rules[$vehicleSet['set_type'] ?? '']['units']['primary']['label'] ?? 'Основная единица',
            'secondary' => $rules[$vehicleSet['set_type'] ?? '']['units']['secondary']['label'] ?? 'Доп. единица',
        ];

        $canEdit = false;
        $canDelete = false;
        if ($roleCode === 'company_owner' || $roleCode === 'senior_logist') {
            $canEdit = true;
            $canDelete = true;
        } elseif ($roleCode === 'logist') {
            $userId = (int) ($_SESSION['user_id'] ?? 0);
            if ((int) ($vehicleSet['created_by_user_id'] ?? 0) === $userId) {
                $canEdit = true;
                $canDelete = true;
            } elseif ($grantAccessLevel === 'edit') {
                $canEdit = true;
            }
        }

        header('Content-Type: text/html; charset=utf-8');
        require base_path('app/View/partials/company_vehicle_set_modal_view.php');
        exit;
    } catch (\Throwable $e) {
        http_response_code(500);
        echo '<div class="notice warn">Ошибка загрузки: ' . e($e->getMessage()) . '</div>';
        exit;
    }
});

// --- Vehicle Set Modal Edit (GET) ---

$router->get('/company/vehicle-sets/{id}/modal-edit', function ($id) use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);

    $companyId = (int) (getSessionCompanyId() ?? 0);
    if ($companyId <= 0) {
        http_response_code(404);
        echo '<div class="notice warn">Компания не найдена.</div>';
        exit;
    }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company || ($company['status'] ?? '') !== 'active') {
            http_response_code(404);
            echo '<div class="notice warn">Компания не найдена или неактивна.</div>';
            exit;
        }

        $localDbConfig = $config['database'];
        $localDbConfig['database'] = $company['db_identifier'];
        $localDb = new \App\Core\Database($localDbConfig);
        $localPdo = $localDb->connection();
        applyLocalMigrations($localPdo);

        $vehicleSetStmt = $localPdo->prepare('SELECT * FROM vehicle_sets WHERE id = ?');
        $vehicleSetStmt->execute([(int) $id]);
        $vehicleSet = $vehicleSetStmt->fetch(PDO::FETCH_ASSOC) ?: null;

        if (!$vehicleSet) {
            http_response_code(404);
            echo '<div class="notice warn">Транспорт не найден.</div>';
            exit;
        }

        $roleCode = $_SESSION['role_code'] ?? '';
        $grantAccessLevel = null;
        $canEdit = false;
        if ($roleCode === 'company_owner' || $roleCode === 'senior_logist') {
            $canEdit = true;
        } elseif ($roleCode === 'logist') {
            $userId = (int) ($_SESSION['user_id'] ?? 0);
            if ((int) ($vehicleSet['created_by_user_id'] ?? 0) === $userId) {
                $canEdit = true;
            } else {
                $grantCheck = $localPdo->prepare("SELECT access_level FROM entity_access_grants WHERE entity_type = 'vehicle_set' AND entity_id = ? AND granted_to_user_id = ? AND revoked_at IS NULL LIMIT 1");
                $grantCheck->execute([(int) $id, $userId]);
                $grantRow = $grantCheck->fetch(PDO::FETCH_ASSOC);
                $grantAccessLevel = $grantRow['access_level'] ?? null;
                $canEdit = $grantAccessLevel === 'edit';
            }
        }

        if (!$canEdit) {
            http_response_code(403);
            echo '<div class="notice warn">У вас нет права редактировать эту запись.</div>';
            exit;
        }

        $unitIds = array_values(array_filter([
            (int) ($vehicleSet['primary_vehicle_unit_id'] ?? 0),
            (int) ($vehicleSet['secondary_vehicle_unit_id'] ?? 0),
        ]));

        $unitsByRole = ['primary' => [], 'secondary' => []];
        if (!empty($unitIds)) {
            $placeholders = implode(',', array_fill(0, count($unitIds), '?'));
            $unitsStmt = $localPdo->prepare("SELECT * FROM vehicle_units WHERE id IN ($placeholders)");
            $unitsStmt->execute($unitIds);
            foreach ($unitsStmt->fetchAll(PDO::FETCH_ASSOC) as $unit) {
                $unitId = (int) ($unit['id'] ?? 0);
                if ($unitId === (int) ($vehicleSet['primary_vehicle_unit_id'] ?? 0)) {
                    $unitsByRole['primary'] = $unit;
                } elseif ($unitId === (int) ($vehicleSet['secondary_vehicle_unit_id'] ?? 0)) {
                    $unitsByRole['secondary'] = $unit;
                }
            }
        }

        $docsByRole = ['primary' => [], 'secondary' => []];
        if (!empty($unitIds)) {
            $placeholders = implode(',', array_fill(0, count($unitIds), '?'));
            $docsStmt = $localPdo->prepare(
                "SELECT id, entity_id, document_type, original_name, mime_type, stored_name, file_size
                   FROM documents
                  WHERE entity_type = 'vehicle_unit'
                    AND entity_id IN ($placeholders)
                    AND deleted_at IS NULL
                  ORDER BY id"
            );
            $docsStmt->execute($unitIds);
            foreach ($docsStmt->fetchAll(PDO::FETCH_ASSOC) as $doc) {
                $entityId = (int) ($doc['entity_id'] ?? 0);
                if ($entityId === (int) ($vehicleSet['primary_vehicle_unit_id'] ?? 0)) {
                    $docsByRole['primary'][] = $doc;
                } elseif ($entityId === (int) ($vehicleSet['secondary_vehicle_unit_id'] ?? 0)) {
                    $docsByRole['secondary'][] = $doc;
                }
            }
        }

        $old = [
            'set_type' => $vehicleSet['set_type'] ?? 'single',
            'status' => $vehicleSet['status'] ?? 'active',
            'comments' => $vehicleSet['comments'] ?? '',
            'units' => [
                'primary' => $unitsByRole['primary'] ?? [],
                'secondary' => $unitsByRole['secondary'] ?? [],
            ],
        ];
        $errors = [];
        $formError = null;

        header('Content-Type: text/html; charset=utf-8');
        require base_path('app/View/partials/company_vehicle_set_modal_edit.php');
        exit;
    } catch (\Throwable $e) {
        http_response_code(500);
        echo '<div class="notice warn">Ошибка загрузки: ' . e($e->getMessage()) . '</div>';
        exit;
    }
});

// --- Vehicle Set Modal Edit (POST) ---

$router->post('/company/vehicle-sets/{id}/modal-edit', function ($id) use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);

    $companyId = (int) (getSessionCompanyId() ?? 0);
    if ($companyId <= 0) {
        http_response_code(404);
        echo '<div class="notice warn">Компания не найдена.</div>';
        exit;
    }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company || ($company['status'] ?? '') !== 'active') {
            http_response_code(404);
            echo '<div class="notice warn">Компания не найдена или неактивна.</div>';
            exit;
        }

        $localDbConfig = $config['database'];
        $localDbConfig['database'] = $company['db_identifier'];
        $localDb = new \App\Core\Database($localDbConfig);
        $localPdo = $localDb->connection();
        applyLocalMigrations($localPdo);

        $vehicleSetStmt = $localPdo->prepare('SELECT * FROM vehicle_sets WHERE id = ?');
        $vehicleSetStmt->execute([(int) $id]);
        $vehicleSet = $vehicleSetStmt->fetch(PDO::FETCH_ASSOC) ?: null;

        if (!$vehicleSet) {
            http_response_code(404);
            echo '<div class="notice warn">Транспорт не найден.</div>';
            exit;
        }

        $roleCode = $_SESSION['role_code'] ?? '';
        $canEdit = false;
        if ($roleCode === 'company_owner' || $roleCode === 'senior_logist') {
            $canEdit = true;
        } elseif ($roleCode === 'logist') {
            $userId = (int) ($_SESSION['user_id'] ?? 0);
            if ((int) ($vehicleSet['created_by_user_id'] ?? 0) === $userId) {
                $canEdit = true;
            } else {
                $grantCheck = $localPdo->prepare("SELECT access_level FROM entity_access_grants WHERE entity_type = 'vehicle_set' AND entity_id = ? AND granted_to_user_id = ? AND revoked_at IS NULL LIMIT 1");
                $grantCheck->execute([(int) $id, $userId]);
                $grantRow = $grantCheck->fetch(PDO::FETCH_ASSOC);
                $canEdit = ($grantRow['access_level'] ?? null) === 'edit';
            }
        }

        if (!$canEdit) {
            http_response_code(403);
            echo '<div class="notice warn">У вас нет права редактировать эту запись.</div>';
            exit;
        }

        $currentUnitIds = [
            'primary' => (int) ($vehicleSet['primary_vehicle_unit_id'] ?? 0),
            'secondary' => (int) ($vehicleSet['secondary_vehicle_unit_id'] ?? 0),
        ];

        $unitsByRole = ['primary' => [], 'secondary' => []];
        $loadedUnitIds = array_values(array_filter($currentUnitIds));
        if (!empty($loadedUnitIds)) {
            $placeholders = implode(',', array_fill(0, count($loadedUnitIds), '?'));
            $unitsStmt = $localPdo->prepare("SELECT * FROM vehicle_units WHERE id IN ($placeholders)");
            $unitsStmt->execute($loadedUnitIds);
            foreach ($unitsStmt->fetchAll(PDO::FETCH_ASSOC) as $unit) {
                $unitId = (int) ($unit['id'] ?? 0);
                if ($unitId === $currentUnitIds['primary']) {
                    $unitsByRole['primary'] = $unit;
                } elseif ($unitId === $currentUnitIds['secondary']) {
                    $unitsByRole['secondary'] = $unit;
                }
            }
        }

        $docsByRole = ['primary' => [], 'secondary' => []];
        if (!empty($loadedUnitIds)) {
            $placeholders = implode(',', array_fill(0, count($loadedUnitIds), '?'));
            $docsStmt = $localPdo->prepare(
                "SELECT id, entity_id, document_type, original_name, mime_type, stored_name, file_size
                   FROM documents
                  WHERE entity_type = 'vehicle_unit'
                    AND entity_id IN ($placeholders)
                    AND deleted_at IS NULL
                  ORDER BY id"
            );
            $docsStmt->execute($loadedUnitIds);
            foreach ($docsStmt->fetchAll(PDO::FETCH_ASSOC) as $doc) {
                $entityId = (int) ($doc['entity_id'] ?? 0);
                if ($entityId === $currentUnitIds['primary']) {
                    $docsByRole['primary'][] = $doc;
                } elseif ($entityId === $currentUnitIds['secondary']) {
                    $docsByRole['secondary'][] = $doc;
                }
            }
        }

        $rules = vehicleSetTypeRules();
        $allowedExt = ['pdf', 'doc', 'docx', 'rtf', 'odt', 'xls', 'xlsx', 'csv', 'ods', 'jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp', 'tif', 'tiff', 'heic', 'heif'];
        $maxFileSize = 20 * 1024 * 1024;

        $normalizeDate = static function (?string $value): ?string {
            $value = trim((string) $value);
            if ($value === '') {
                return '';
            }
            $value = preg_replace('/[^0-9.\-]/u', '', $value);
            if (preg_match('/^(\d{2})\.(\d{2})\.(\d{4})$/', $value, $m)) {
                return $m[3] . '-' . $m[2] . '-' . $m[1];
            }
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                return $value;
            }
            return null;
        };

        $normalizeRoleFiles = static function (array $fileBag, string $role): array {
            $names = $fileBag['name'][$role] ?? [];
            $tmpNames = $fileBag['tmp_name'][$role] ?? [];
            $errors = $fileBag['error'][$role] ?? [];
            $sizes = $fileBag['size'][$role] ?? [];
            $types = $fileBag['type'][$role] ?? [];
            if (!is_array($names)) {
                $names = [$names];
                $tmpNames = [$tmpNames];
                $errors = [$errors];
                $sizes = [$sizes];
                $types = [$types];
            }
            $items = [];
            foreach ($names as $idx => $origName) {
                $items[] = [
                    'name' => trim((string) $origName),
                    'tmp_name' => (string) ($tmpNames[$idx] ?? ''),
                    'error' => (int) ($errors[$idx] ?? UPLOAD_ERR_NO_FILE),
                    'size' => (int) ($sizes[$idx] ?? 0),
                    'type' => (string) ($types[$idx] ?? ''),
                ];
            }
            return $items;
        };

        $lockedSetType = (string) ($vehicleSet['set_type'] ?? 'single');
        $old = [
            'set_type' => $lockedSetType,
            'status' => trim((string) ($_POST['status'] ?? ($vehicleSet['status'] ?? 'active'))),
            'comments' => trim((string) ($_POST['comments'] ?? ($vehicleSet['comments'] ?? ''))),
            'units' => [],
            'custom_doc_type' => $_POST['custom_doc_type'] ?? [],
        ];
        $errors = [];
        $formError = null;

        $setType = isset($rules[$lockedSetType]) ? $lockedSetType : 'single';
        $old['set_type'] = $setType;

        if (!in_array($old['status'], ['active', 'inactive'], true)) {
            $old['status'] = 'active';
        }

        $activeRoles = isset($rules[$setType]['units']) && is_array($rules[$setType]['units'])
            ? array_keys($rules[$setType]['units'])
            : ['primary'];
        $updatedUnitPayload = [];

        foreach ($activeRoles as $role) {
            $roleRule = $rules[$setType]['units'][$role] ?? [];
            $posted = isset($_POST['units'][$role]) && is_array($_POST['units'][$role]) ? $_POST['units'][$role] : [];
            $plate = mb_strtoupper(trim((string) ($posted['plate_number'] ?? '')));
            $brand = trim((string) ($posted['brand'] ?? ''));
            $model = trim((string) ($posted['model'] ?? ''));
            $vin = mb_strtoupper(trim((string) ($posted['vin'] ?? '')));
            $capacity = trim((string) ($posted['capacity_tons'] ?? ''));
            $volume = trim((string) ($posted['volume_m3'] ?? ''));
            $diagNumber = trim((string) ($posted['diagnostic_card_number'] ?? ''));
            $diagDateRaw = trim((string) ($posted['diagnostic_card_date'] ?? ''));
            $diagDate = $normalizeDate($diagDateRaw);

            $old['units'][$role] = [
                'plate_number' => $plate,
                'brand' => $brand,
                'model' => $model,
                'vin' => $vin,
                'capacity_tons' => $capacity,
                'volume_m3' => $volume,
                'diagnostic_card_number' => $diagNumber,
                'diagnostic_card_date' => $diagDateRaw,
            ];

            if ($plate === '') {
                $errors['units'][$role]['plate_number'] = 'Обязательное поле';
            }
            if ($vin !== '' && !preg_match('/^[A-HJ-NPR-Z0-9]{17}$/', $vin)) {
                $errors['units'][$role]['vin'] = 'Формат VIN: 17 символов';
            }
            if ($diagNumber !== '' && !ctype_digit($diagNumber)) {
                $errors['units'][$role]['diagnostic_card_number'] = 'Только цифры';
            }
            if ($diagDateRaw !== '' && $diagDate === null) {
                $errors['units'][$role]['diagnostic_card_date'] = 'Некорректная дата';
            }
            if (!empty($roleRule['show_capacity']) && $capacity !== '' && (!is_numeric($capacity) || (float) $capacity <= 0)) {
                $errors['units'][$role]['capacity_tons'] = 'Введите число';
            }
            if (!empty($roleRule['show_volume']) && $volume !== '' && (!is_numeric($volume) || (float) $volume <= 0)) {
                $errors['units'][$role]['volume_m3'] = 'Введите число';
            }

            $updatedUnitPayload[$role] = [
                'plate_number' => $plate,
                'brand' => $brand,
                'model' => $model,
                'unit_type' => (string) ($roleRule['unit_type'] ?? ''),
                'vin' => $vin,
                'capacity_tons' => !empty($roleRule['show_capacity']) && $capacity !== '' ? (float) $capacity : null,
                'volume_m3' => !empty($roleRule['show_volume']) && $volume !== '' ? (float) $volume : null,
                'diagnostic_card_number' => $diagNumber !== '' ? $diagNumber : null,
                'diagnostic_card_date' => $diagDate !== '' ? $diagDate : null,
            ];
        }

        if (!empty($_FILES['existing_doc_file']['name']) && is_array($_FILES['existing_doc_file']['name'])) {
            foreach ($_FILES['existing_doc_file']['name'] as $docId => $origName) {
                $uploadError = (int) ($_FILES['existing_doc_file']['error'][$docId] ?? UPLOAD_ERR_NO_FILE);
                if ($uploadError !== UPLOAD_ERR_OK || trim((string) $origName) === '') {
                    continue;
                }
                $size = (int) ($_FILES['existing_doc_file']['size'][$docId] ?? 0);
                if ($size > $maxFileSize) {
                    $formError = 'Один из файлов превышает 20 МБ.';
                }
                $ext = strtolower(pathinfo((string) $origName, PATHINFO_EXTENSION));
                if (!in_array($ext, $allowedExt, true)) {
                    $formError = 'Один из файлов имеет недопустимый формат.';
                }
            }
        }

        foreach ($activeRoles as $role) {
            $customFiles = $normalizeRoleFiles($_FILES['custom_doc_file'] ?? [], $role);
            $customTitles = $_POST['custom_doc_type'][$role] ?? [];
            $rowCount = max(count($customFiles), is_array($customTitles) ? count($customTitles) : 0);
            for ($i = 0; $i < $rowCount; $i++) {
                $file = $customFiles[$i] ?? ['name' => '', 'error' => UPLOAD_ERR_NO_FILE, 'size' => 0];
                $title = trim((string) ((is_array($customTitles) ? ($customTitles[$i] ?? '') : '')));
                $hasFile = ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK && ($file['name'] ?? '') !== '';
                if ($hasFile && $title === '') {
                    $formError = 'Введите название для добавляемого документа.';
                }
                if ($hasFile) {
                    $ext = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
                    if (!in_array($ext, $allowedExt, true)) {
                        $formError = 'Один из файлов имеет недопустимый формат.';
                    }
                    if ((int) ($file['size'] ?? 0) > $maxFileSize) {
                        $formError = 'Один из файлов превышает 20 МБ.';
                    }
                }
            }
        }

        if (isPostTruncated()) {
            $formError = 'Общий размер отправки превышает серверный лимит.';
        }
        $totalSizeError = validateTotalUploadSize();
        if ($totalSizeError !== '') {
            $formError = $totalSizeError;
        }

        if (!empty($errors) || $formError !== null) {
            header('Content-Type: text/html; charset=utf-8');
            require base_path('app/View/partials/company_vehicle_set_modal_edit.php');
            exit;
        }

        $storedPaths = [];
        $filePathsToDelete = [];
        $dirPathsToClean = [];
        $companyIdForPath = $companyId;
        $storeUpload = static function (PDO $localPdo, int $companyId, string $entityType, int $entityId, string $docTypeName, string $docCode, array $file, string $category, array &$storedPaths) {
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $storedName = uniqid('doc_', true) . '.' . $ext;
            $relativeDir = 'companies/' . $companyId . '/documents/' . $entityType . '/' . $entityId;
            $absoluteDir = storage_path($relativeDir);
            if (!is_dir($absoluteDir) && !mkdir($absoluteDir, 0755, true)) {
                throw new RuntimeException('mkdir_failed');
            }
            $absolutePath = $absoluteDir . DIRECTORY_SEPARATOR . $storedName;
            if (!move_uploaded_file($file['tmp_name'], $absolutePath)) {
                throw new RuntimeException('move_failed');
            }
            $storedPaths[] = $absolutePath;
            $documentTypeId = ensureDocumentTypeRecord($localPdo, $docTypeName, $docCode, $entityType, $category);
            $insertDocument = $localPdo->prepare(
                'INSERT INTO documents (entity_type, entity_id, document_type, document_type_id, original_name, stored_name, relative_path, mime_type, file_size, status, uploaded_by_user_id, uploaded_by_role, created_by_user_id, created_by_role)
                 VALUES (:et, :eid, :dtype, :dtid, :oname, :sname, :rpath, :mime, :fsize, :status, :uid, :role, :cuid, :crole)'
            );
            $insertDocument->execute([
                ':et' => $entityType,
                ':eid' => $entityId,
                ':dtype' => $docTypeName,
                ':dtid' => $documentTypeId,
                ':oname' => $file['name'],
                ':sname' => $storedName,
                ':rpath' => $relativeDir . '/' . $storedName,
                ':mime' => $file['type'] ?: 'application/octet-stream',
                ':fsize' => $file['size'],
                ':status' => 'uploaded',
                ':uid' => (int) ($_SESSION['user_id'] ?? 0),
                ':role' => $_SESSION['role_code'] ?? null,
                ':cuid' => (int) ($_SESSION['user_id'] ?? 0),
                ':crole' => $_SESSION['role_code'] ?? null,
            ]);
        };

        $collectDocFilesForDocId = static function (PDO $localPdo, int $docId, array &$filePaths): void {
            $docStmt = $localPdo->prepare("SELECT relative_path FROM documents WHERE id = ?");
            $docStmt->execute([$docId]);
            $relative = trim((string) ($docStmt->fetchColumn() ?: ''));
            if ($relative !== '') {
                $absolute = storage_path($relative);
                if (is_file($absolute)) {
                    $filePaths[] = $absolute;
                }
            }
        };

        $updatedUnitIds = $currentUnitIds;

        $localPdo->beginTransaction();
        try {
            $insertUnit = $localPdo->prepare(
                'INSERT INTO vehicle_units (plate_number, brand, model, unit_type, vin, capacity_tons, volume_m3, diagnostic_card_number, diagnostic_card_date, status, comments, created_by_user_id, created_by_role)
                 VALUES (:plate_number, :brand, :model, :unit_type, :vin, :capacity_tons, :volume_m3, :diagnostic_card_number, :diagnostic_card_date, :status, :comments, :created_by_user_id, :created_by_role)'
            );
            $updateUnit = $localPdo->prepare(
                'UPDATE vehicle_units
                    SET plate_number = :plate_number,
                        brand = :brand,
                        model = :model,
                        unit_type = :unit_type,
                        vin = :vin,
                        capacity_tons = :capacity_tons,
                        volume_m3 = :volume_m3,
                        diagnostic_card_number = :diagnostic_card_number,
                        diagnostic_card_date = :diagnostic_card_date,
                        status = :status,
                        updated_by_user_id = :updated_by_user_id,
                        updated_by_role = :updated_by_role
                  WHERE id = :id'
            );

            foreach ($activeRoles as $role) {
                $payload = $updatedUnitPayload[$role];
                if (!empty($updatedUnitIds[$role])) {
                    $updateUnit->execute([
                        ':plate_number' => $payload['plate_number'],
                        ':brand' => $payload['brand'],
                        ':model' => $payload['model'],
                        ':unit_type' => $payload['unit_type'],
                        ':vin' => $payload['vin'],
                        ':capacity_tons' => $payload['capacity_tons'],
                        ':volume_m3' => $payload['volume_m3'],
                        ':diagnostic_card_number' => $payload['diagnostic_card_number'],
                        ':diagnostic_card_date' => $payload['diagnostic_card_date'],
                        ':status' => $old['status'],
                        ':updated_by_user_id' => (int) ($_SESSION['user_id'] ?? 0),
                        ':updated_by_role' => $_SESSION['role_code'] ?? null,
                        ':id' => (int) $updatedUnitIds[$role],
                    ]);
                } else {
                    $insertUnit->execute([
                        ':plate_number' => $payload['plate_number'],
                        ':brand' => $payload['brand'],
                        ':model' => $payload['model'],
                        ':unit_type' => $payload['unit_type'],
                        ':vin' => $payload['vin'],
                        ':capacity_tons' => $payload['capacity_tons'],
                        ':volume_m3' => $payload['volume_m3'],
                        ':diagnostic_card_number' => $payload['diagnostic_card_number'],
                        ':diagnostic_card_date' => $payload['diagnostic_card_date'],
                        ':status' => $old['status'],
                        ':comments' => null,
                        ':created_by_user_id' => (int) ($_SESSION['user_id'] ?? 0),
                        ':created_by_role' => $_SESSION['role_code'] ?? null,
                    ]);
                    $updatedUnitIds[$role] = (int) $localPdo->lastInsertId();
                }
            }

            foreach (['primary', 'secondary'] as $role) {
                if (in_array($role, $activeRoles, true)) {
                    continue;
                }
                $removeId = (int) ($currentUnitIds[$role] ?? 0);
                if ($removeId <= 0) {
                    continue;
                }

                $docsStmt = $localPdo->prepare("SELECT relative_path FROM documents WHERE entity_type = 'vehicle_unit' AND entity_id = ?");
                $docsStmt->execute([$removeId]);
                foreach ($docsStmt->fetchAll(PDO::FETCH_ASSOC) as $doc) {
                    $relative = trim((string) ($doc['relative_path'] ?? ''));
                    if ($relative !== '') {
                        $absolute = storage_path($relative);
                        if (is_file($absolute)) {
                            $filePathsToDelete[] = $absolute;
                        }
                    }
                }
                $dirPathsToClean[] = storage_path('companies/' . $companyIdForPath . '/documents/vehicle_unit/' . $removeId);

                $localPdo->prepare("DELETE FROM entity_access_grants WHERE entity_type = 'vehicle_unit' AND entity_id = ?")->execute([$removeId]);
                $localPdo->prepare("DELETE FROM documents WHERE entity_type = 'vehicle_unit' AND entity_id = ?")->execute([$removeId]);
                $localPdo->prepare("DELETE FROM vehicle_units WHERE id = ?")->execute([$removeId]);
                $updatedUnitIds[$role] = 0;
            }

            $updateSet = $localPdo->prepare(
                'UPDATE vehicle_sets
                    SET set_type = :set_type,
                        primary_vehicle_unit_id = :primary_id,
                        secondary_vehicle_unit_id = :secondary_id,
                        status = :status,
                        comments = :comments,
                        updated_by_user_id = :updated_by_user_id,
                        updated_by_role = :updated_by_role
                  WHERE id = :id'
            );
            $updateSet->execute([
                ':set_type' => $setType,
                ':primary_id' => (int) ($updatedUnitIds['primary'] ?? 0),
                ':secondary_id' => !empty($updatedUnitIds['secondary']) ? (int) $updatedUnitIds['secondary'] : null,
                ':status' => $old['status'],
                ':comments' => $old['comments'] !== '' ? $old['comments'] : null,
                ':updated_by_user_id' => (int) ($_SESSION['user_id'] ?? 0),
                ':updated_by_role' => $_SESSION['role_code'] ?? null,
                ':id' => (int) $id,
            ]);

            $deleteExistingDocs = $_POST['delete_existing_doc'] ?? [];
            $replaceDocIds = [];
            if (!empty($_FILES['existing_doc_file']['name']) && is_array($_FILES['existing_doc_file']['name'])) {
                foreach ($_FILES['existing_doc_file']['name'] as $docId => $origName) {
                    $uploadError = (int) ($_FILES['existing_doc_file']['error'][$docId] ?? UPLOAD_ERR_NO_FILE);
                    if ($uploadError === UPLOAD_ERR_OK && trim((string) $origName) !== '') {
                        $replaceDocIds[] = (int) $docId;
                    }
                }
            }

            foreach ($deleteExistingDocs as $docId => $flag) {
                $docId = (int) $docId;
                if ($flag !== '1' || in_array($docId, $replaceDocIds, true)) {
                    continue;
                }
                $collectDocFilesForDocId($localPdo, $docId, $filePathsToDelete);
                $localPdo->prepare("DELETE FROM documents WHERE id = ? AND entity_type = 'vehicle_unit'")->execute([$docId]);
            }

            foreach ($replaceDocIds as $docId) {
                $docStmt = $localPdo->prepare("SELECT entity_id, document_type FROM documents WHERE id = ? AND entity_type = 'vehicle_unit' LIMIT 1");
                $docStmt->execute([$docId]);
                $existingDoc = $docStmt->fetch(PDO::FETCH_ASSOC);
                if (!$existingDoc) {
                    continue;
                }

                $collectDocFilesForDocId($localPdo, $docId, $filePathsToDelete);
                $localPdo->prepare("DELETE FROM documents WHERE id = ? AND entity_type = 'vehicle_unit'")->execute([$docId]);

                $file = [
                    'name' => (string) ($_FILES['existing_doc_file']['name'][$docId] ?? ''),
                    'tmp_name' => (string) ($_FILES['existing_doc_file']['tmp_name'][$docId] ?? ''),
                    'size' => (int) ($_FILES['existing_doc_file']['size'][$docId] ?? 0),
                    'type' => (string) ($_FILES['existing_doc_file']['type'][$docId] ?? 'application/octet-stream'),
                ];
                $storeUpload(
                    $localPdo,
                    $companyIdForPath,
                    'vehicle_unit',
                    (int) ($existingDoc['entity_id'] ?? 0),
                    (string) ($existingDoc['document_type'] ?? 'Документ'),
                    '',
                    $file,
                    'custom',
                    $storedPaths
                );
            }

            foreach ($activeRoles as $role) {
                $unitId = (int) ($updatedUnitIds[$role] ?? 0);
                if ($unitId <= 0) {
                    continue;
                }
                $customTitles = $_POST['custom_doc_type'][$role] ?? [];
                $customFiles = $normalizeRoleFiles($_FILES['custom_doc_file'] ?? [], $role);
                $rowCount = max(count($customFiles), is_array($customTitles) ? count($customTitles) : 0);
                for ($i = 0; $i < $rowCount; $i++) {
                    $file = $customFiles[$i] ?? ['name' => '', 'error' => UPLOAD_ERR_NO_FILE, 'size' => 0, 'tmp_name' => '', 'type' => ''];
                    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || ($file['name'] ?? '') === '') {
                        continue;
                    }
                    $docTypeName = trim((string) ((is_array($customTitles) ? ($customTitles[$i] ?? '') : '')));
                    if ($docTypeName === '') {
                        continue;
                    }
                    $storeUpload($localPdo, $companyIdForPath, 'vehicle_unit', $unitId, $docTypeName, '', $file, 'custom', $storedPaths);
                }
            }

            $localPdo->commit();
        } catch (\Throwable $e) {
            if ($localPdo->inTransaction()) {
                $localPdo->rollBack();
            }
            foreach ($storedPaths as $storedPath) {
                if (is_string($storedPath) && $storedPath !== '' && is_file($storedPath)) {
                    @unlink($storedPath);
                }
            }
            throw $e;
        }

        foreach (array_unique($filePathsToDelete) as $filePath) {
            if (is_string($filePath) && $filePath !== '' && is_file($filePath)) {
                @unlink($filePath);
            }
        }
        foreach (array_unique($dirPathsToClean) as $dirPath) {
            if (!is_dir($dirPath)) {
                continue;
            }
            $items = @scandir($dirPath) ?: [];
            foreach ($items as $item) {
                if ($item === '.' || $item === '..') {
                    continue;
                }
                $path = $dirPath . DIRECTORY_SEPARATOR . $item;
                if (is_file($path)) {
                    @unlink($path);
                }
            }
            @rmdir($dirPath);
        }

        $vehicleSetStmt = $localPdo->prepare(
            'SELECT vs.*, vu1.plate_number AS primary_plate, vu2.plate_number AS secondary_plate
               FROM vehicle_sets vs
               LEFT JOIN vehicle_units vu1 ON vs.primary_vehicle_unit_id = vu1.id
               LEFT JOIN vehicle_units vu2 ON vs.secondary_vehicle_unit_id = vu2.id
              WHERE vs.id = ?'
        );
        $vehicleSetStmt->execute([(int) $id]);
        $vehicleSet = $vehicleSetStmt->fetch(PDO::FETCH_ASSOC) ?: $vehicleSet;

        $currentUnitIds = [
            'primary' => (int) ($vehicleSet['primary_vehicle_unit_id'] ?? 0),
            'secondary' => (int) ($vehicleSet['secondary_vehicle_unit_id'] ?? 0),
        ];
        $unitIds = array_values(array_filter($currentUnitIds));
        $unitsByRole = ['primary' => [], 'secondary' => []];
        if (!empty($unitIds)) {
            $placeholders = implode(',', array_fill(0, count($unitIds), '?'));
            $unitsStmt = $localPdo->prepare("SELECT * FROM vehicle_units WHERE id IN ($placeholders)");
            $unitsStmt->execute($unitIds);
            foreach ($unitsStmt->fetchAll(PDO::FETCH_ASSOC) as $unit) {
                $unitId = (int) ($unit['id'] ?? 0);
                if ($unitId === $currentUnitIds['primary']) {
                    $unitsByRole['primary'] = $unit;
                } elseif ($unitId === $currentUnitIds['secondary']) {
                    $unitsByRole['secondary'] = $unit;
                }
            }
        }

        $docsByRole = ['primary' => [], 'secondary' => []];
        if (!empty($unitIds)) {
            $placeholders = implode(',', array_fill(0, count($unitIds), '?'));
            $docsStmt = $localPdo->prepare(
                "SELECT id, entity_id, document_type, original_name, mime_type, stored_name, file_size
                   FROM documents
                  WHERE entity_type = 'vehicle_unit'
                    AND entity_id IN ($placeholders)
                    AND deleted_at IS NULL
                  ORDER BY id"
            );
            $docsStmt->execute($unitIds);
            foreach ($docsStmt->fetchAll(PDO::FETCH_ASSOC) as $doc) {
                $entityId = (int) ($doc['entity_id'] ?? 0);
                if ($entityId === $currentUnitIds['primary']) {
                    $docsByRole['primary'][] = $doc;
                } elseif ($entityId === $currentUnitIds['secondary']) {
                    $docsByRole['secondary'][] = $doc;
                }
            }
        }

        $unitTitles = [
            'primary' => $rules[$vehicleSet['set_type'] ?? '']['units']['primary']['label'] ?? 'Основная единица',
            'secondary' => $rules[$vehicleSet['set_type'] ?? '']['units']['secondary']['label'] ?? 'Доп. единица',
        ];
        $canDelete = $roleCode === 'company_owner' || $roleCode === 'senior_logist' || (($roleCode === 'logist') && (int) ($vehicleSet['created_by_user_id'] ?? 0) === (int) ($_SESSION['user_id'] ?? 0));
        $canEdit = true;

        header('Content-Type: text/html; charset=utf-8');
        require base_path('app/View/partials/company_vehicle_set_modal_view.php');
        exit;
    } catch (\Throwable $e) {
        http_response_code(500);
        echo '<div class="notice warn">Ошибка сохранения: ' . e($e->getMessage()) . '</div>';
        exit;
    }
});

// --- Vehicle Set Modal Delete ---

$router->post('/company/vehicle-sets/{id}/modal-delete', function ($id) use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);

    header('Content-Type: application/json; charset=utf-8');

    $companyId = (int) (getSessionCompanyId() ?? 0);
    if ($companyId <= 0) {
        echo json_encode(['success' => false, 'error' => 'Компания не найдена.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company || ($company['status'] ?? '') !== 'active') {
            echo json_encode(['success' => false, 'error' => 'Компания не найдена или неактивна.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }

        $localDbConfig = $config['database'];
        $localDbConfig['database'] = $company['db_identifier'];
        $localDb = new \App\Core\Database($localDbConfig);
        $localPdo = $localDb->connection();
        applyLocalMigrations($localPdo);

        $vehicleSetStmt = $localPdo->prepare('SELECT * FROM vehicle_sets WHERE id = ?');
        $vehicleSetStmt->execute([(int) $id]);
        $vehicleSet = $vehicleSetStmt->fetch(PDO::FETCH_ASSOC) ?: null;

        if (!$vehicleSet) {
            echo json_encode(['success' => false, 'error' => 'Транспорт не найден.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }

        $roleCode = $_SESSION['role_code'] ?? '';
        if ($roleCode === 'logist' && (int) ($vehicleSet['created_by_user_id'] ?? 0) !== (int) ($_SESSION['user_id'] ?? 0)) {
            echo json_encode(['success' => false, 'error' => 'У вас нет права удалять эту запись.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }

        try {
            $localPdo->query("SELECT 1 FROM crews LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $localPdo->exec(file_get_contents(base_path('database/migrations-local/006_create_company_crews.sql')));
        }

        try {
            $localPdo->query("SELECT 1 FROM driver_vehicle_blocks LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $localPdo->exec(file_get_contents(base_path('database/migrations-local/018_create_driver_vehicle_blocks.sql')));
        }

        $crewCheck = $localPdo->prepare(
            'SELECT COUNT(*)
               FROM crews c
               JOIN driver_vehicle_blocks dvb ON dvb.id = c.driver_vehicle_block_id
              WHERE dvb.vehicle_set_id = ?'
        );
        $crewCheck->execute([(int) $id]);
        if ((int) $crewCheck->fetchColumn() > 0) {
            echo json_encode(['success' => false, 'error' => 'Транспорт участвует в экипажах. Сначала удалите экипажи.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }

        $setId = (int) $id;
        $unitIds = array_values(array_filter([
            (int) ($vehicleSet['primary_vehicle_unit_id'] ?? 0),
            (int) ($vehicleSet['secondary_vehicle_unit_id'] ?? 0),
        ]));

        $filePathsToDelete = [];
        $dirPathsToDelete = [];

        foreach ($unitIds as $unitId) {
            $docsStmt = $localPdo->prepare("SELECT relative_path FROM documents WHERE entity_type = 'vehicle_unit' AND entity_id = ?");
            $docsStmt->execute([$unitId]);
            foreach ($docsStmt->fetchAll(PDO::FETCH_ASSOC) as $doc) {
                $relative = trim((string) ($doc['relative_path'] ?? ''));
                if ($relative !== '') {
                    $absolute = storage_path($relative);
                    if (is_file($absolute)) {
                        $filePathsToDelete[] = $absolute;
                    }
                }
            }
            $dirPathsToDelete[] = storage_path('companies/' . $companyId . '/documents/vehicle_unit/' . $unitId);
        }

        $blockIdsStmt = $localPdo->prepare("SELECT id FROM driver_vehicle_blocks WHERE vehicle_set_id = ?");
        $blockIdsStmt->execute([$setId]);
        $blockIds = array_map('intval', $blockIdsStmt->fetchAll(PDO::FETCH_COLUMN));

        $localPdo->beginTransaction();
        $localPdo->prepare("DELETE FROM entity_access_grants WHERE entity_type = 'vehicle_set' AND entity_id = ?")->execute([$setId]);
        if (!empty($unitIds)) {
            $placeholders = implode(',', array_fill(0, count($unitIds), '?'));
            $localPdo->prepare("DELETE FROM entity_access_grants WHERE entity_type = 'vehicle_unit' AND entity_id IN ($placeholders)")->execute($unitIds);
            $localPdo->prepare("DELETE FROM documents WHERE entity_type = 'vehicle_unit' AND entity_id IN ($placeholders)")->execute($unitIds);
            $localPdo->prepare("DELETE FROM vehicle_units WHERE id IN ($placeholders)")->execute($unitIds);
        }
        if (!empty($blockIds)) {
            $placeholders = implode(',', array_fill(0, count($blockIds), '?'));
            $localPdo->prepare("DELETE FROM entity_access_grants WHERE entity_type = 'driver_vehicle_block' AND entity_id IN ($placeholders)")->execute($blockIds);
            $localPdo->prepare("DELETE FROM driver_vehicle_blocks WHERE id IN ($placeholders)")->execute($blockIds);
        }
        $localPdo->prepare("DELETE FROM vehicle_sets WHERE id = ?")->execute([$setId]);
        $localPdo->commit();

        foreach (array_unique($filePathsToDelete) as $filePath) {
            if (is_string($filePath) && $filePath !== '' && is_file($filePath)) {
                @unlink($filePath);
            }
        }
        foreach (array_unique($dirPathsToDelete) as $dirPath) {
            if (!is_dir($dirPath)) {
                continue;
            }
            $items = @scandir($dirPath) ?: [];
            foreach ($items as $item) {
                if ($item === '.' || $item === '..') {
                    continue;
                }
                $path = $dirPath . DIRECTORY_SEPARATOR . $item;
                if (is_file($path)) {
                    @unlink($path);
                }
            }
            @rmdir($dirPath);
        }

        echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    } catch (\Throwable $e) {
        echo json_encode(['success' => false, 'error' => 'Ошибка: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
});

$router->get('/company/vehicle-sets/{id}', function ($id) use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);
    $pageTitle = 'Транспорт';
    $pageContext = 'Транспорт › Компания';

    $companyId = (int)(getSessionCompanyId() ?? 0);
    $grants = []; $logists = [];

    if ($companyId <= 0) {
        $company = null; $vehicleSet = null; $dbError = null;
        ob_start(); require base_path('app/View/pages/company_vehicle_set_view.php');
        $content = ob_get_clean(); require base_path('app/View/layouts/main.php');
        return;
    }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]); $company = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$company) { $company = null; $vehicleSet = null; $dbError = null; goto renderViewVS; }
        $pageContext = 'Транспорт › Компания: ' . $company['name'];
        if ($company['status'] !== 'active') { $vehicleSet = null; $dbError = null; goto renderViewVS; }

        $dbIdentifier = $company['db_identifier'];
        $localDbConfig = $config['database']; $localDbConfig['database'] = $dbIdentifier;
        $localDb = new \App\Core\Database($localDbConfig); $localPdo = $localDb->connection();
        applyLocalMigrations($localPdo);

        try { $localPdo->query("SELECT 1 FROM vehicle_sets LIMIT 1")->fetch(); }
        catch (\Exception $e) { $localPdo->exec(file_get_contents(base_path('database/migrations-local/017_create_vehicle_sets.sql'))); }

        $stmt = $localPdo->prepare(
            "SELECT vs.*, vu1.plate_number AS primary_plate, vu1.brand AS primary_brand, vu1.model AS primary_model, vu1.vin AS primary_vin,
             vu2.plate_number AS secondary_plate, vu2.brand AS secondary_brand, vu2.model AS secondary_model, vu2.vin AS secondary_vin
             FROM vehicle_sets vs
             LEFT JOIN vehicle_units vu1 ON vs.primary_vehicle_unit_id = vu1.id
             LEFT JOIN vehicle_units vu2 ON vs.secondary_vehicle_unit_id = vu2.id
             WHERE vs.id = ?"
        );
        $stmt->execute([(int)$id]); $vehicleSet = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        $accessDenied = null;
        if ($vehicleSet) {
            $isLogist = ($_SESSION['role_code'] ?? '') === 'logist';
            if ($isLogist) {
                $userId = (int)$_SESSION['user_id'];
                $hasGrant = false;
                $gc = $localPdo->prepare("SELECT access_level FROM entity_access_grants WHERE entity_type = 'vehicle_set' AND entity_id = ? AND granted_to_user_id = ? AND revoked_at IS NULL LIMIT 1");
                $gc->execute([(int)$id, $userId]);
                $gr = $gc->fetch(PDO::FETCH_ASSOC);
                $hasGrant = ($gr && in_array($gr['access_level'], ['view', 'edit']));
                if ((int)$vehicleSet['created_by_user_id'] !== $userId && !$hasGrant) {
                    $accessDenied = 'У вас нет доступа к этой записи.';
                }
            }
        }

        if ($vehicleSet && !$accessDenied) { $pageTitle = 'Транспорт #' . $vehicleSet['id']; }

        if (($_SESSION['role_code'] ?? '') === 'company_owner') {
            $grantsStmt = $localPdo->prepare("SELECT g.*, u.full_name AS logist_name FROM entity_access_grants g LEFT JOIN users u ON g.granted_to_user_id = u.id WHERE g.entity_type = ? AND g.entity_id = ?");
            $grantsStmt->execute(['vehicle_set', (int)$id]);
            $grants = $grantsStmt->fetchAll(PDO::FETCH_ASSOC);
            $logists = $localPdo->query("SELECT id, full_name, login FROM users WHERE role_code IN ('logist', 'senior_logist') AND status='active' ORDER BY full_name")->fetchAll(PDO::FETCH_ASSOC);
        }

        // Load driver_vehicle_blocks for this vehicle set
        $vehicleSetBlocks = [];
        if ($vehicleSet) {
            try {
                $vsbStmt = $localPdo->prepare(
                    "SELECT dvb.id AS block_id, dvb.status AS block_status,
                     d.full_name AS driver_name, d.id AS driver_id
                     FROM driver_vehicle_blocks dvb
                     JOIN drivers d ON dvb.driver_id = d.id
                     WHERE dvb.vehicle_set_id = ? AND dvb.status != 'archived'
                     ORDER BY dvb.id DESC"
                );
                $vsbStmt->execute([(int)$id]);
                $vehicleSetBlocks = $vsbStmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (\Exception $e) {
                $vehicleSetBlocks = [];
            }
        }

        $dbError = null;
    } catch (\Exception $e) {
        $company = $company ?? null; $vehicleSet = null; $grants = []; $logists = []; $vehicleSetBlocks = [];
        $dbError = 'Не удалось загрузить: ' . $e->getMessage();
    }

    renderViewVS:
    ob_start(); require base_path('app/View/pages/company_vehicle_set_view.php');
    $content = ob_get_clean(); require base_path('app/View/layouts/main.php');
});

$router->get('/company/vehicle-sets/{id}/edit', function ($id) use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);
    $pageTitle = 'Редактировать транспорт';
    $pageContext = 'Транспорт › Компания';

    $companyId = (int)(getSessionCompanyId() ?? 0);
    $entityNotFound = false; $success = false; $vehicleUnits = [];

    if ($companyId <= 0) {
        $company = null; $vehicleSet = null; $errors = []; $old = []; $formError = null;
        ob_start(); require base_path('app/View/pages/company_vehicle_set_edit.php');
        $content = ob_get_clean(); require base_path('app/View/layouts/main.php');
        return;
    }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]); $company = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$company) { $company = null; $vehicleSet = null; $errors = []; $old = []; $formError = null; goto renderEditVS; }
        $pageContext = 'Транспорт › Компания: ' . $company['name'];
        if ($company['status'] !== 'active') { $vehicleSet = null; $errors = []; $old = []; $formError = null; goto renderEditVS; }

        $dbIdentifier = $company['db_identifier'];
        $localDbConfig = $config['database']; $localDbConfig['database'] = $dbIdentifier;
        $localDb = new \App\Core\Database($localDbConfig); $localPdo = $localDb->connection();
        applyLocalMigrations($localPdo);

        $stmt = $localPdo->prepare('SELECT * FROM vehicle_sets WHERE id = ?');
        $stmt->execute([(int)$id]); $vehicleSet = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        if (!$vehicleSet) { $entityNotFound = true; $errors = []; $old = []; $formError = null; goto renderEditVS; }

        $vehicleUnits = $localPdo->query("SELECT * FROM vehicle_units WHERE status = 'active' ORDER BY plate_number")->fetchAll(PDO::FETCH_ASSOC);
        $errors = []; $old = $vehicleSet; $formError = null;
    } catch (\Exception $e) {
        $company = $company ?? null; $vehicleSet = null; $errors = []; $old = []; $formError = 'Ошибка: ' . $e->getMessage();
    }

    renderEditVS:
    ob_start(); require base_path('app/View/pages/company_vehicle_set_edit.php');
    $content = ob_get_clean(); require base_path('app/View/layouts/main.php');
});

$router->post('/company/vehicle-sets/{id}/edit', function ($id) use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);
    $pageTitle = 'Редактировать транспорт';
    $pageContext = 'Транспорт › Компания';

    $companyId = (int)(getSessionCompanyId() ?? 0);
    $entityNotFound = false; $success = false; $vehicleUnits = [];

    if ($companyId <= 0) {
        $company = null; $vehicleSet = null; $errors = []; $old = $_POST; $formError = 'Компания не найдена';
        ob_start(); require base_path('app/View/pages/company_vehicle_set_edit.php');
        $content = ob_get_clean(); require base_path('app/View/layouts/main.php');
        return;
    }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]); $company = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$company) { $company = null; $vehicleSet = null; $errors = []; $old = $_POST; $formError = 'Компания не найдена'; goto renderEditVSPost; }
        if ($company['status'] !== 'active') { $vehicleSet = null; $errors = []; $old = $_POST; $formError = 'Редактирование недоступно'; goto renderEditVSPost; }

        $dbIdentifier = $company['db_identifier'];
        $localDbConfig = $config['database']; $localDbConfig['database'] = $dbIdentifier;
        $localDb = new \App\Core\Database($localDbConfig); $localPdo = $localDb->connection();
        applyLocalMigrations($localPdo);

        $stmt = $localPdo->prepare('SELECT * FROM vehicle_sets WHERE id = ?');
        $stmt->execute([(int)$id]); $vehicleSet = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if (!$vehicleSet) { $entityNotFound = true; $errors = []; $old = $_POST; $formError = null; goto renderEditVSPost; }

        $vehicleUnits = $localPdo->query("SELECT * FROM vehicle_units WHERE status = 'active' ORDER BY plate_number")->fetchAll(PDO::FETCH_ASSOC);
        $errors = []; $old = $_POST; $formError = null;

        $setType = trim($_POST['set_type'] ?? '');
        $primaryId = trim($_POST['primary_vehicle_unit_id'] ?? '');
        $secondaryId = trim($_POST['secondary_vehicle_unit_id'] ?? '');

        if ($primaryId === '') { $errors['primary_vehicle_unit_id'] = 'Обязательное поле'; }
        if (($setType === 'coupling' || $setType === 'road_train') && $secondaryId === '') {
            $errors['secondary_vehicle_unit_id'] = 'Обязательно для сцепки и автопоезда';
        }
        if ($setType === 'single') { $secondaryId = ''; }

        if (!empty($errors)) { goto renderEditVSPost; }

        $update = $localPdo->prepare(
            'UPDATE vehicle_sets SET set_type = :set_type, primary_vehicle_unit_id = :primary_id, secondary_vehicle_unit_id = :secondary_id, status = :status, comments = :comments, updated_by_user_id = :uid, updated_by_role = :role WHERE id = :id'
        );
        $update->execute([
            ':set_type' => $setType !== '' ? $setType : null,
            ':primary_id' => (int)$primaryId,
            ':secondary_id' => $secondaryId !== '' ? (int)$secondaryId : null,
            ':status' => $_POST['status'] ?? $vehicleSet['status'],
            ':comments' => $_POST['comments'] ?? null,
            ':uid' => (int)$_SESSION['user_id'],
            ':role' => $_SESSION['role_code'],
            ':id' => (int)$id,
        ]);

        $vehicleSet = $localPdo->prepare('SELECT * FROM vehicle_sets WHERE id = ?');
        $vehicleSet->execute([(int)$id]); $vehicleSet = $vehicleSet->fetch(PDO::FETCH_ASSOC);
        $success = true;
    } catch (\Exception $e) {
        $company = $company ?? null; $vehicleSet = $vehicleSet ?? null;
        $errors = []; $old = $_POST; $formError = 'Ошибка: ' . $e->getMessage();
    }

    renderEditVSPost:
    ob_start(); require base_path('app/View/pages/company_vehicle_set_edit.php');
    $content = ob_get_clean(); require base_path('app/View/layouts/main.php');
});

$router->post('/company/vehicle-sets/{id}/archive', function ($id) use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);
    $companyId = (int)(getSessionCompanyId() ?? 0);
    if ($companyId <= 0) { header('Location: /company/vehicle-sets'); exit; }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]); $company = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$company || $company['status'] !== 'active') { header('Location: /company/vehicle-sets'); exit; }

        $dbIdentifier = $company['db_identifier'];
        $localDbConfig = $config['database']; $localDbConfig['database'] = $dbIdentifier;
        $localDb = new \App\Core\Database($localDbConfig); $localPdo = $localDb->connection();
        applyLocalMigrations($localPdo);

        try { $localPdo->query("SELECT 1 FROM driver_vehicle_blocks LIMIT 1")->fetch(); }
        catch (\Exception $e) { $localPdo->exec(file_get_contents(base_path('database/migrations-local/018_create_driver_vehicle_blocks.sql'))); }

        $blockCheck = $localPdo->prepare('SELECT COUNT(*) FROM driver_vehicle_blocks WHERE vehicle_set_id = ? AND status = ?');
        $blockCheck->execute([(int)$id, 'active']);
        if ($blockCheck->fetchColumn() > 0) {
            $_SESSION['flash'] = 'Транспорт используется в активных связках «Водители+ТС». Сначала архивируйте связки.';
            header('Location: /company/vehicle-sets/' . $id); exit;
        }

        $update = $localPdo->prepare("UPDATE vehicle_sets SET status = 'archived' WHERE id = ?");
        $update->execute([(int)$id]);
        header('Location: /company/vehicle-sets'); exit;
    } catch (\Exception $e) {
        header('Location: /company/vehicle-sets'); exit;
    }
});

// ---- DRIVER VEHICLE BLOCKS (Водители+ТС) ----
