<?php

$router->get('/company/drivers', function () use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);
    $pageTitle = 'Водители';
    $pageContext = 'Водители › Компания';

    $companyId = (int)(getSessionCompanyId() ?? 0);

    if ($companyId <= 0) {
        $company = null;
        $drivers = [];
        $dbError = null;

        ob_start();
        require base_path('app/View/pages/company_drivers.php');
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
            $drivers = [];
            $dbError = null;

            ob_start();
            require base_path('app/View/pages/company_drivers.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $pageContext = 'Водители › Компания: ' . $company['name'];
        $topbarCrumbs = [
            ['label' => mb_strtoupper($company['name']), 'url' => '/company/dashboard'],
            ['label' => 'Подрядчики', 'url' => null],
            ['label' => 'Список водителей', 'url' => null],
        ];

        if ($company['status'] !== 'active') {
            $drivers = [];
            $dbError = null;

            ob_start();
            require base_path('app/View/pages/company_drivers.php');
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
            $localPdo->query("SELECT 1 FROM drivers LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $migrationSql = file_get_contents(base_path('database/migrations-local/004_create_company_drivers.sql'));
            $localPdo->exec($migrationSql);
        }

        try {
            $localPdo->query("SELECT created_by_user_id FROM drivers LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $localPdo->exec("ALTER TABLE drivers ADD COLUMN created_by_user_id INT UNSIGNED DEFAULT NULL, ADD COLUMN created_by_role VARCHAR(20) DEFAULT NULL");
        }

        try {
            $localPdo->query("SELECT 1 FROM entity_access_grants LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $migrationSql = file_get_contents(base_path('database/migrations-local/009_create_entity_access_grants.sql'));
            $localPdo->exec($migrationSql);
        }

        try {
            $docTypes = $localPdo->query("SELECT id, name, code, entity_type FROM document_types WHERE entity_type = 'driver' OR entity_type IS NULL ORDER BY sort_order, name")->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            $docTypes = [];
        }

        $isLogist = ($_SESSION['role_code'] ?? '') === 'logist';
        if ($isLogist) {
            $userId = (int)$_SESSION['user_id'];
            $driverStmt = $localPdo->prepare(
                "SELECT d.*, COALESCE(dp.phone, d.phone) AS main_phone,
                        (SELECT COUNT(*) FROM driver_phones WHERE driver_id = d.id AND is_main = 0) AS extra_phones_count,
                        (SELECT COUNT(*) FROM documents doc WHERE doc.entity_type = 'driver' AND doc.entity_id = d.id AND doc.deleted_at IS NULL) AS files_count
                 FROM drivers d
                 LEFT JOIN driver_phones dp ON d.id = dp.driver_id AND dp.is_main = 1
                 WHERE (d.created_by_user_id = ? OR d.id IN (SELECT entity_id FROM entity_access_grants WHERE entity_type = 'driver' AND granted_to_user_id = ? AND access_level = 'view'))
                 ORDER BY d.created_at DESC"
            );
            $driverStmt->execute([$userId, $userId]);
            $drivers = $driverStmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $driverStmt = $localPdo->query(
                "SELECT d.*, COALESCE(dp.phone, d.phone) AS main_phone,
                        (SELECT COUNT(*) FROM driver_phones WHERE driver_id = d.id AND is_main = 0) AS extra_phones_count,
                        (SELECT COUNT(*) FROM documents doc WHERE doc.entity_type = 'driver' AND doc.entity_id = d.id AND doc.deleted_at IS NULL) AS files_count
                 FROM drivers d
                 LEFT JOIN driver_phones dp ON d.id = dp.driver_id AND dp.is_main = 1
                 ORDER BY d.created_at DESC"
            );
            $drivers = $driverStmt->fetchAll(PDO::FETCH_ASSOC);
        }

        $dbError = null;
    } catch (\Exception $e) {
        $company = $company ?? null;
        $drivers = [];
        $dbError = 'Не удалось подключиться к базе данных компании.';
    }

    ob_start();
    require base_path('app/View/pages/company_drivers.php');
    $content = ob_get_clean();
    require base_path('app/View/layouts/main.php');
});

$router->get('/company/drivers/create', function () use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);
    $pageTitle = 'Создать водителя';
    $pageContext = 'Водители › Компания';

    $companyId = (int)(getSessionCompanyId() ?? 0);

    if ($companyId <= 0) {
        $company = null;
        $success = false;
        $errors = [];
        $old = [];
        $formError = null;
        $createdDriver = null;

        ob_start();
        require base_path('app/View/pages/company_drivers_create.php');
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
            $createdDriver = null;

            ob_start();
            require base_path('app/View/pages/company_drivers_create.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $topbarCrumbs = [
            ['label' => mb_strtoupper($company['name']), 'url' => '/company/dashboard'],
            ['label' => 'Подрядчики', 'url' => null],
            ['label' => 'Водители', 'url' => '/company/drivers'],
            ['label' => 'Создать водителя', 'url' => null],
        ];

        $success = false;
        $errors = [];
        $old = [];
        $formError = null;
        $createdDriver = null;
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

                $docTypes = $localPdo->query("SELECT id, name, code, entity_type FROM document_types WHERE entity_type = 'driver' OR entity_type IS NULL ORDER BY sort_order, name")->fetchAll(PDO::FETCH_ASSOC);
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
        $createdDriver = null;
    }

    ob_start();
    require base_path('app/View/pages/company_drivers_create.php');
    $content = ob_get_clean();
    require base_path('app/View/layouts/main.php');
});

$router->post('/company/drivers/create', function () use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);
    require_once base_path('app/Support/driver_create_handler.php');
    handleCompanyDriverCreate($config, $db, 'page');
    return;
    $pageTitle = 'Создать водителя';
    $pageContext = 'Водители › Компания';

    $companyId = (int)(getSessionCompanyId() ?? 0);
    $errors = [];
    $old = $_POST;
    $formError = null;
    $success = false;
    $createdDriver = null;
    $docErrors = [];
    $uploadedDocs = [];

    if ($companyId <= 0) {
        $company = null;
        $formError = 'Компания не найдена';

        ob_start();
        require base_path('app/View/pages/company_drivers_create.php');
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
            require base_path('app/View/pages/company_drivers_create.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $topbarCrumbs = [
            ['label' => mb_strtoupper($company['name']), 'url' => '/company/dashboard'],
            ['label' => 'Подрядчики', 'url' => null],
            ['label' => 'Водители', 'url' => '/company/drivers'],
            ['label' => 'Создать водителя', 'url' => null],
        ];

        if ($company['status'] !== 'active') {
            $formError = 'Создание водителей недоступно';

            ob_start();
            require base_path('app/View/pages/company_drivers_create.php');
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
            $localPdo->query("SELECT 1 FROM drivers LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $migrationSql = file_get_contents(base_path('database/migrations-local/004_create_company_drivers.sql'));
            $localPdo->exec($migrationSql);
            applyLocalMigrations($localPdo);
        }

        $normalizeSpaces = static function ($value): string {
            return preg_replace('/\s+/u', ' ', trim((string)$value)) ?? '';
        };
        $normalizeDateInput = static function ($value) use ($normalizeSpaces): ?string {
            $value = $normalizeSpaces($value);
            if ($value === '') {
                return null;
            }

            $value = preg_replace('/\s*(г(?:\.|ода?|од)?)\s*$/ui', '', $value) ?? $value;
            $value = $normalizeSpaces($value);

            if (preg_match('/^(\d{8})$/', $value, $m)) {
                $day = (int)substr($m[1], 0, 2);
                $month = (int)substr($m[1], 2, 2);
                $year = (int)substr($m[1], 4, 4);
            } elseif (preg_match('/^(\d{4})[.\/-](\d{1,2})[.\/-](\d{1,2})$/', $value, $m)) {
                $year = (int)$m[1];
                $month = (int)$m[2];
                $day = (int)$m[3];
            } elseif (preg_match('/^(\d{1,2})[\s.\/-](\d{1,2})[\s.\/-](\d{2}|\d{4})$/', $value, $m)) {
                $day = (int)$m[1];
                $month = (int)$m[2];
                $year = (int)$m[3];
                if ($year < 100) {
                    $year = $year <= 49 ? 2000 + $year : 1900 + $year;
                }
            } else {
                return false;
            }

            if (!checkdate($month, $day, $year)) {
                return false;
            }

            return sprintf('%04d-%02d-%02d', $year, $month, $day);
        };
        $normalizePhone = static function ($value): ?string {
            $digits = preg_replace('/\D+/', '', (string)$value) ?? '';
            if (strlen($digits) === 11 && $digits[0] === '8') {
                $digits = '7' . substr($digits, 1);
            } elseif (strlen($digits) === 10) {
                $digits = '7' . $digits;
            }
            if (strlen($digits) !== 11 || $digits[0] !== '7') {
                return null;
            }

            return sprintf(
                '+7 %s %s-%s-%s',
                substr($digits, 1, 3),
                substr($digits, 4, 3),
                substr($digits, 7, 2),
                substr($digits, 9, 2)
            );
        };
        $normalizeTenDigits = static function ($value): ?string {
            $digits = preg_replace('/\D+/', '', (string)$value) ?? '';
            if (strlen($digits) !== 10) {
                return null;
            }

            return substr($digits, 0, 4) . ' ' . substr($digits, 4, 6);
        };
        $normalizeDepartmentCode = static function ($value): ?string {
            $digits = preg_replace('/\D+/', '', (string)$value) ?? '';
            if (strlen($digits) !== 6) {
                return null;
            }

            return substr($digits, 0, 3) . '-' . substr($digits, 3, 3);
        };
        $normalizeSnils = static function ($value): ?string {
            $digits = preg_replace('/\D+/', '', (string)$value) ?? '';
            if (strlen($digits) !== 11) {
                return null;
            }

            return substr($digits, 0, 3) . '-' . substr($digits, 3, 3) . '-' . substr($digits, 6, 3) . ' ' . substr($digits, 9, 2);
        };
        $normalizeFullName = static function ($value) use ($normalizeSpaces): ?string {
            $value = $normalizeSpaces($value);
            if ($value === '') {
                return null;
            }
            $parts = preg_split('/\s+/u', $value) ?: [];
            if (count($parts) !== 3) {
                return false;
            }

            $parts = array_map(static function ($part) {
                $part = mb_strtolower($part, 'UTF-8');
                return mb_strtoupper(mb_substr($part, 0, 1, 'UTF-8'), 'UTF-8') . mb_substr($part, 1, null, 'UTF-8');
            }, $parts);

            return implode(' ', $parts);
        };

        if (isPostTruncated()) {
            $formError = 'Общий размер отправки превышает серверный лимит. Для ERP требуется настройка post_max_size не менее 100M. Уменьшите количество файлов или обратитесь к администратору.';
            ob_start();
            require base_path('app/View/pages/company_drivers_create.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $fullName = $normalizeFullName($_POST['full_name'] ?? '');
        $phoneRaw = trim((string)($_POST['phone'] ?? ''));
        $phone = $normalizePhone($phoneRaw);
        $licenseNumberRaw = trim((string)($_POST['license_number'] ?? ''));
        $licenseNumber = $licenseNumberRaw !== '' ? $normalizeTenDigits($licenseNumberRaw) : null;
        $licenseCategory = trim((string)($_POST['license_category'] ?? ''));
        $licenseIssueDateRaw = trim((string)($_POST['license_issue_date'] ?? ''));
        $licenseIssueDate = $licenseIssueDateRaw !== '' ? $normalizeDateInput($licenseIssueDateRaw) : null;
        $licenseExpireDate = trim((string)($_POST['license_expire_date'] ?? ''));
        $comments = $normalizeSpaces($_POST['comments'] ?? '');
        $passportNumberRaw = trim((string)($_POST['passport_number'] ?? ''));
        $passportNumber = $passportNumberRaw !== '' ? $normalizeTenDigits($passportNumberRaw) : null;
        $passportIssuedBy = $normalizeSpaces($_POST['passport_issued_by'] ?? '');
        $passportDepartmentCodeRaw = trim((string)($_POST['passport_department_code'] ?? ''));
        $passportDepartmentCode = $passportDepartmentCodeRaw !== '' ? $normalizeDepartmentCode($passportDepartmentCodeRaw) : null;
        $passportIssueDateRaw = trim((string)($_POST['passport_issue_date'] ?? ''));
        $passportIssueDate = $passportIssueDateRaw !== '' ? $normalizeDateInput($passportIssueDateRaw) : null;
        $snilsRaw = trim((string)($_POST['snils'] ?? ''));
        $snils = $snilsRaw !== '' ? $normalizeSnils($snilsRaw) : null;
        $email = strtolower(trim((string)($_POST['email'] ?? '')));

        $old['full_name'] = is_string($fullName) ? $fullName : trim((string)($_POST['full_name'] ?? ''));
        $old['phone'] = $phone ?? $phoneRaw;
        $old['license_number'] = $licenseNumber ?? $licenseNumberRaw;
        $old['license_issue_date'] = is_string($licenseIssueDate) ? date('d.m.Y', strtotime($licenseIssueDate)) : $licenseIssueDateRaw;
        $old['comments'] = $comments;
        $old['passport_number'] = $passportNumber ?? $passportNumberRaw;
        $old['passport_issued_by'] = $passportIssuedBy;
        $old['passport_department_code'] = $passportDepartmentCode ?? $passportDepartmentCodeRaw;
        $old['passport_issue_date'] = is_string($passportIssueDate) ? date('d.m.Y', strtotime($passportIssueDate)) : $passportIssueDateRaw;
        $old['snils'] = $snils ?? $snilsRaw;
        $old['email'] = $email;

        if ($fullName === null) {
            $errors['full_name'] = 'ФИО: 3 слова';
        } elseif ($fullName === false) {
            $errors['full_name'] = 'ФИО: 3 слова';
        }

        if ($phoneRaw !== '' && $phone === null) {
            $errors['phone'] = 'Неверный формат';
        }

        if ($licenseNumberRaw !== '' && $licenseNumber === null) {
            $errors['license_number'] = 'Нужно 10 цифр';
        }

        if ($licenseIssueDateRaw !== '' && $licenseIssueDate === false) {
            $errors['license_issue_date'] = 'Неверная дата';
        }

        if ($passportNumberRaw !== '' && $passportNumber === null) {
            $errors['passport_number'] = 'Нужно 10 цифр';
        }

        if ($passportDepartmentCodeRaw !== '' && $passportDepartmentCode === null) {
            $errors['passport_department_code'] = 'Формат 000-000';
        }

        if ($passportIssueDateRaw !== '' && $passportIssueDate === false) {
            $errors['passport_issue_date'] = 'Неверная дата';
        }

        if ($snilsRaw !== '' && $snils === null) {
            $errors['snils'] = 'Нужно 11 цифр';
        }

        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Неверный email';
        }

        if (!empty($errors)) {
            ob_start();
            require base_path('app/View/pages/company_drivers_create.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        // Check total upload size before INSERT
        $totalSizeError = validateTotalUploadSize();
        if ($totalSizeError !== '') {
            $formError = $totalSizeError;
            ob_start();
            require base_path('app/View/pages/company_drivers_create.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $insert = $localPdo->prepare(
            'INSERT INTO drivers (full_name, phone, email, license_number, license_category,
             license_issue_date, license_expire_date, passport_number, passport_issued_by,
             passport_department_code, passport_issue_date, snils, status, comments, created_by_user_id, created_by_role)
             VALUES (:full_name, :phone, :email, :license_number, :license_category,
             :license_issue_date, :license_expire_date, :passport_number, :passport_issued_by,
             :passport_department_code, :passport_issue_date, :snils, :status, :comments, :created_by_user_id, :created_by_role)'
        );
        $insert->execute([
            ':full_name'              => $fullName,
            ':phone'                  => $phone ?? '',
            ':email'                  => $email !== '' ? $email : null,
            ':license_number'         => $licenseNumber,
            ':license_category'       => $licenseCategory !== '' ? $licenseCategory : null,
            ':license_issue_date'     => $licenseIssueDate ?: null,
            ':license_expire_date'    => $licenseExpireDate !== '' ? $licenseExpireDate : null,
            ':passport_number'        => $passportNumber,
            ':passport_issued_by'     => $passportIssuedBy !== '' ? $passportIssuedBy : null,
            ':passport_department_code' => $passportDepartmentCode,
            ':passport_issue_date'    => $passportIssueDate ?: null,
            ':snils'                  => $snils,
            ':status'                 => 'active',
            ':comments'               => $comments !== '' ? $comments : null,
            ':created_by_user_id'     => (int)$_SESSION['user_id'],
            ':created_by_role'        => $_SESSION['role_code'],
        ]);

        $createdDriver = [
            'id'                     => $localPdo->lastInsertId(),
            'full_name'              => $fullName,
            'phone'                  => $phone,
            'email'                  => $email !== '' ? $email : null,
            'passport_number'        => $passportNumber,
            'passport_issued_by'     => $passportIssuedBy !== '' ? $passportIssuedBy : null,
            'passport_issue_date'    => $passportIssueDate ? date('d.m.Y', strtotime($passportIssueDate)) : null,
            'snils'                  => $snils,
            'license_number'         => $licenseNumber,
            'license_category'       => $licenseCategory !== '' ? $licenseCategory : null,
        ];
        $newDriverId = (int)$localPdo->lastInsertId();
        $entityType = 'driver';

        // -- Process extra phones during creation --
        $extraPhones = $_POST['extra_phones'] ?? [];
        if (is_array($extraPhones)) {
            try { $localPdo->query("SELECT 1 FROM driver_phones LIMIT 1")->fetch(); }
            catch (\Exception $e) { $localPdo->exec(file_get_contents(base_path('database/migrations-local/015_create_driver_phones.sql'))); }
            $phoneComments = $_POST['extra_phone_comments'] ?? [];
            $phoneIns = $localPdo->prepare('INSERT INTO driver_phones (driver_id, phone, is_main, comment, created_by_user_id, created_by_role) VALUES (:did, :phone, 0, :comment, :uid, :role)');
            foreach ($extraPhones as $pIdx => $extraPhone) {
                $extraPhone = $normalizePhone($extraPhone ?? '');
                if ($extraPhone === '') continue;
                $phoneIns->execute([
                    ':did'     => $newDriverId,
                    ':phone'   => $extraPhone,
                    ':comment' => isset($phoneComments[$pIdx]) ? $normalizeSpaces($phoneComments[$pIdx]) : null,
                    ':uid'     => (int)$_SESSION['user_id'],
                    ':role'    => $_SESSION['role_code'],
                ]);
            }
            // Load back for success display
            $extraPhonesSaved = $localPdo->prepare("SELECT phone, comment FROM driver_phones WHERE driver_id = ? AND is_main = 0 ORDER BY id ASC");
            $extraPhonesSaved->execute([$newDriverId]);
            $createdDriver['extra_phones'] = $extraPhonesSaved->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $createdDriver['extra_phones'] = [];
        }

        // -- Process document uploads during creation --
        $docErrors = [];
        $uploadedDocs = [];

        try { $localPdo->query("SELECT 1 FROM documents LIMIT 1")->fetch(); }
        catch (\Exception $e) { $localPdo->exec(file_get_contents(base_path('database/migrations-local/007_create_company_documents.sql'))); }
        try { $localPdo->query("SELECT 1 FROM document_types LIMIT 1")->fetch(); }
        catch (\Exception $e) {
            $localPdo->exec(file_get_contents(base_path('database/migrations-local/024_create_document_types.sql')));
            $localPdo->exec(file_get_contents(base_path('database/migrations-local/025_add_document_type_id.sql')));
        }
        try { $localPdo->query("SELECT created_by_user_id FROM documents LIMIT 1")->fetch(); }
        catch (\Exception $e) { $localPdo->exec("ALTER TABLE documents ADD COLUMN created_by_user_id INT UNSIGNED DEFAULT NULL, ADD COLUMN created_by_role VARCHAR(20) DEFAULT NULL"); }

        $allowedExt = ['pdf', 'doc', 'docx', 'rtf', 'odt', 'xls', 'xlsx', 'csv', 'ods', 'jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp', 'tif', 'tiff', 'heic', 'heif', 'txt'];
        $maxSize = 20 * 1024 * 1024;

        // Predefined docs (supports multiple files per type via multiple attribute)
        if (!empty($_FILES['predef_doc']['name']) && is_array($_FILES['predef_doc']['name'])) {
            foreach ($_FILES['predef_doc']['name'] as $code => $nameValue) {
                $docTypeName = $_POST['predef_doc_type'][$code] ?? '';
                // Normalize to array of files (single or multiple)
                if (is_array($nameValue)) {
                    $names = $nameValue;
                    $tmpNames = $_FILES['predef_doc']['tmp_name'][$code] ?? [];
                    $errors = $_FILES['predef_doc']['error'][$code] ?? [];
                    $sizes = $_FILES['predef_doc']['size'][$code] ?? [];
                    $types = $_FILES['predef_doc']['type'][$code] ?? [];
                } else {
                    $names = [$nameValue];
                    $tmpNames = [$_FILES['predef_doc']['tmp_name'][$code] ?? ''];
                    $errors = [$_FILES['predef_doc']['error'][$code] ?? UPLOAD_ERR_NO_FILE];
                    $sizes = [$_FILES['predef_doc']['size'][$code] ?? 0];
                    $types = [$_FILES['predef_doc']['type'][$code] ?? ''];
                }
                foreach ($names as $fileIdx => $origName) {
                    $fe = $errors[$fileIdx] ?? UPLOAD_ERR_NO_FILE;
                    if ($fe !== UPLOAD_ERR_OK || trim((string)$origName) === '') continue;
                    $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
                    $fs = $sizes[$fileIdx] ?? 0;
                    if (!in_array($ext, $allowedExt, true)) { $docErrors[] = 'Предопределённый документ «' . $docTypeName . '»: недопустимый формат'; continue; }
                    if ($fs > $maxSize) { $docErrors[] = 'Предопределённый документ «' . $docTypeName . '»: размер > 20 МБ'; continue; }
                    if (strpos($origName, '../') !== false || strpos($origName, '..\\') !== false || strpos($origName, '/') !== false || strpos($origName, '\\') !== false) { $docErrors[] = 'Предопределённый документ «' . $docTypeName . '»: недопустимое имя'; continue; }
                    try {
                        $storedName = uniqid('doc_', true) . '.' . $ext;
                        $relativeDir = 'companies/' . $companyId . '/documents/' . $entityType . '/' . $newDriverId;
                        $absoluteDir = storage_path($relativeDir);
                        if (!is_dir($absoluteDir) && !mkdir($absoluteDir, 0755, true)) { $docErrors[] = 'Предопределённый документ «' . $docTypeName . '»: не удалось создать директорию'; continue; }
                        $destPath = $absoluteDir . DIRECTORY_SEPARATOR . $storedName;
                        $tmpSrc = $tmpNames[$fileIdx] ?? '';
                        if ($tmpSrc === '' || !move_uploaded_file($tmpSrc, $destPath)) { $docErrors[] = 'Предопределённый документ «' . $docTypeName . '»: не удалось сохранить'; continue; }
                        $mime = $types[$fileIdx] ?? '';
                        $dtId = null;
                    if ($docTypeName !== '') { $dts = $localPdo->prepare("SELECT id FROM document_types WHERE name = ? AND entity_type = ? LIMIT 1"); $dts->execute([$docTypeName, $entityType]); $dtId = $dts->fetchColumn() ?: null; }
                        $ins = $localPdo->prepare('INSERT INTO documents (entity_type, entity_id, document_type, document_type_id, original_name, stored_name, relative_path, mime_type, file_size, status, uploaded_by_user_id, uploaded_by_role, created_by_user_id, created_by_role) VALUES (:et, :eid, :dtype, :dtid, :oname, :sname, :rpath, :mime, :fsize, :status, :uid, :role, :cuid, :crole)');
                        $ins->execute([':et' => $entityType, ':eid' => $newDriverId, ':dtype' => $docTypeName ?: null, ':dtid' => $dtId, ':oname' => $origName, ':sname' => $storedName, ':rpath' => $relativeDir . '/' . $storedName, ':mime' => $mime, ':fsize' => $fs, ':status' => 'uploaded', ':uid' => (int)$_SESSION['user_id'], ':role' => $_SESSION['role_code'], ':cuid' => (int)$_SESSION['user_id'], ':crole' => $_SESSION['role_code']]);
                        $uploadedDocs[] = $docTypeName . ' (' . $origName . ')';
                    } catch (\Exception $ex) { $docErrors[] = 'Предопределённый документ «' . $docTypeName . '»: ошибка сохранения (' . $ex->getMessage() . ')'; }
                }
            }
        }

        // Custom docs
        if (!empty($_FILES['custom_doc_file']['name']) && is_array($_FILES['custom_doc_file']['name'])) {
            foreach ($_FILES['custom_doc_file']['name'] as $idx => $origName) {
                $fe = $_FILES['custom_doc_file']['error'][$idx] ?? UPLOAD_ERR_NO_FILE;
                if ($fe !== UPLOAD_ERR_OK || trim((string)$origName) === '') continue;
                $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
                $fs = $_FILES['custom_doc_file']['size'][$idx];
                if (!in_array($ext, $allowedExt, true)) { $docErrors[] = 'Произвольный документ #' . ($idx + 1) . ': недопустимый формат'; continue; }
                if ($fs > $maxSize) { $docErrors[] = 'Произвольный документ #' . ($idx + 1) . ': размер > 20 МБ'; continue; }
                if (strpos($origName, '../') !== false || strpos($origName, '..\\') !== false || strpos($origName, '/') !== false || strpos($origName, '\\') !== false) { $docErrors[] = 'Произвольный документ #' . ($idx + 1) . ': недопустимое имя'; continue; }
                $customType = $normalizeSpaces($_POST['custom_doc_type'][$idx] ?? '');
                if ($customType === '') {
                    $docErrors[] = 'Произвольный документ #' . ($idx + 1) . ': укажите тип документа';
                    continue;
                }
                // Normalize: capitalise first letter, lowercase rest (Russian-aware)
                $first = mb_substr($customType, 0, 1, 'UTF-8');
                $rest  = mb_substr($customType, 1, null, 'UTF-8');
                $docTypeName = mb_strtoupper($first, 'UTF-8') . mb_strtolower($rest, 'UTF-8');
                $dtId = null;
                // Auto-create document_type if new (INSERT IGNORE + fallback SELECT)
                try {
                    $idts = $localPdo->prepare("INSERT IGNORE INTO document_types (name, entity_type, category, created_by_user_id, created_by_role) VALUES (:name, :et, 'custom', :uid, :role)");
                    $idts->execute([':name' => $docTypeName, ':et' => $entityType, ':uid' => (int)$_SESSION['user_id'], ':role' => $_SESSION['role_code']]);
                    $dtId = $localPdo->lastInsertId();
                    if (!$dtId) {
                        $g = $localPdo->prepare("SELECT id FROM document_types WHERE name = ? AND entity_type = ? LIMIT 1");
                        $g->execute([$docTypeName, $entityType]);
                        $dtId = $g->fetchColumn() ?: null;
                    }
                } catch (\Exception $ex) {}
                try {
                    $storedName = uniqid('doc_', true) . '.' . $ext;
                    $relativeDir = 'companies/' . $companyId . '/documents/' . $entityType . '/' . $newDriverId;
                    $absoluteDir = storage_path($relativeDir);
                    if (!is_dir($absoluteDir) && !mkdir($absoluteDir, 0755, true)) { $docErrors[] = 'Произвольный документ #' . ($idx + 1) . ': не удалось создать директорию'; continue; }
                    $destPath = $absoluteDir . DIRECTORY_SEPARATOR . $storedName;
                    if (!move_uploaded_file($_FILES['custom_doc_file']['tmp_name'][$idx], $destPath)) { $docErrors[] = 'Произвольный документ #' . ($idx + 1) . ': не удалось сохранить'; continue; }
                    $mime = $_FILES['custom_doc_file']['type'][$idx];
                    $ins = $localPdo->prepare('INSERT INTO documents (entity_type, entity_id, document_type, document_type_id, original_name, stored_name, relative_path, mime_type, file_size, status, uploaded_by_user_id, uploaded_by_role, created_by_user_id, created_by_role) VALUES (:et, :eid, :dtype, :dtid, :oname, :sname, :rpath, :mime, :fsize, :status, :uid, :role, :cuid, :crole)');
                    $ins->execute([':et' => $entityType, ':eid' => $newDriverId, ':dtype' => $docTypeName ?: null, ':dtid' => $dtId, ':oname' => $origName, ':sname' => $storedName, ':rpath' => $relativeDir . '/' . $storedName, ':mime' => $mime, ':fsize' => $fs, ':status' => 'uploaded', ':uid' => (int)$_SESSION['user_id'], ':role' => $_SESSION['role_code'], ':cuid' => (int)$_SESSION['user_id'], ':crole' => $_SESSION['role_code']]);
                    $uploadedDocs[] = $docTypeName . ' (' . $origName . ')';
                } catch (\Exception $ex) { $docErrors[] = 'Произвольный документ #' . ($idx + 1) . ': ошибка сохранения (' . $ex->getMessage() . ')'; }
            }
        }

        $success = true;
    } catch (\PDOException $e) {
        $company = $company ?? null;
        $formError = 'Ошибка создания водителя. Проверьте заполнение формы и попробуйте ещё раз.';
    } catch (\Exception $e) {
        $company = $company ?? null;
        $formError = 'Ошибка создания водителя. Проверьте заполнение формы и попробуйте ещё раз.';
    }

    ob_start();
    require base_path('app/View/pages/company_drivers_create.php');
    $content = ob_get_clean();
    require base_path('app/View/layouts/main.php');
});

$router->post('/company/drivers/modal-create', function () use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);
    require_once base_path('app/Support/driver_create_handler.php');
    handleCompanyDriverCreate($config, $db, 'modal');
});

$router->get('/company/drivers/{id}', function ($id) use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);
    $pageTitle = 'Водитель';
    $pageContext = 'Водители › Компания';

    $companyId = (int)(getSessionCompanyId() ?? 0);
    $archiveError = null;
    $grants = [];
    $logists = [];

    if ($companyId <= 0) {
        $company = null;
        $driver = null;
        $dbError = null;

        ob_start();
        require base_path('app/View/pages/company_driver_view.php');
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
            $driver = null;
            $dbError = null;

            ob_start();
            require base_path('app/View/pages/company_driver_view.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $pageContext = 'Водители › Компания: ' . $company['name'];

        if ($company['status'] !== 'active') {
            $driver = null;
            $dbError = null;

            ob_start();
            require base_path('app/View/pages/company_driver_view.php');
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
            $localPdo->query("SELECT 1 FROM drivers LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $migrationSql = file_get_contents(base_path('database/migrations-local/004_create_company_drivers.sql'));
            $localPdo->exec($migrationSql);
        }

        try {
            $localPdo->query("SELECT created_by_user_id FROM drivers LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $localPdo->exec("ALTER TABLE drivers ADD COLUMN created_by_user_id INT UNSIGNED DEFAULT NULL, ADD COLUMN created_by_role VARCHAR(20) DEFAULT NULL");
        }

        try {
            $localPdo->query("SELECT 1 FROM entity_access_grants LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $migrationSql = file_get_contents(base_path('database/migrations-local/009_create_entity_access_grants.sql'));
            $localPdo->exec($migrationSql);
        }

        $driverStmt = $localPdo->prepare('SELECT * FROM drivers WHERE id = ?');
        $driverStmt->execute([(int) $id]);
        $driver = $driverStmt->fetch(PDO::FETCH_ASSOC) ?: null;

        // Role-based access check
        $accessDenied = null;
        $createdByUser = null;
        $updatedByUser = null;
        if ($driver) {
            $isLogist = ($_SESSION['role_code'] ?? '') === 'logist';
            if ($isLogist) {
                $userId = (int)$_SESSION['user_id'];
                $hasGrant = false;
                $grantCheck = $localPdo->prepare("SELECT access_level FROM entity_access_grants WHERE entity_type = 'driver' AND entity_id = ? AND granted_to_user_id = ? AND revoked_at IS NULL LIMIT 1");
                $grantCheck->execute([(int)$id, $userId]);
                $grantRow = $grantCheck->fetch(PDO::FETCH_ASSOC);
                $hasGrant = ($grantRow && in_array($grantRow['access_level'], ['view', 'edit']));
                if ((int)$driver['created_by_user_id'] !== $userId && !$hasGrant) {
                    $accessDenied = 'У вас нет доступа к этой записи.';
                }
            }
            // Load created/updated by user names
            $createdByUser = $localPdo->prepare("SELECT full_name FROM users WHERE id = ?");
            $createdByUser->execute([(int)$driver['created_by_user_id']]);
            $createdByUser = $createdByUser->fetchColumn() ?: null;
            if (!empty($driver['updated_by_user_id'])) {
                $updatedByUser = $localPdo->prepare("SELECT full_name FROM users WHERE id = ?");
                $updatedByUser->execute([(int)$driver['updated_by_user_id']]);
                $updatedByUser = $updatedByUser->fetchColumn() ?: null;
            }
        }

        if ($driver && !$accessDenied) {
            $pageTitle = 'Водитель: ' . $driver['full_name'];
        }

        // Load phones
        $phones = [];
        if ($driver && !$accessDenied) {
            $phones = $localPdo->prepare("SELECT * FROM driver_phones WHERE driver_id = ? ORDER BY is_main DESC, id ASC");
            $phones->execute([(int)$id]);
            $phones = $phones->fetchAll(PDO::FETCH_ASSOC);
        }

        // Load related driver_vehicle_blocks
        $driverBlocks = [];
        if ($driver && !$accessDenied) {
            try {
                $driverBlocks = $localPdo->prepare(
                    "SELECT dvb.id, dvb.status, dvb.driver_id, dvb.vehicle_set_id,
                     vs.set_type, vu1.plate_number AS primary_plate, vu1.brand AS primary_brand, vu1.model AS primary_model,
                     vu2.plate_number AS secondary_plate, vu2.brand AS secondary_brand, vu2.model AS secondary_model
                     FROM driver_vehicle_blocks dvb
                     JOIN vehicle_sets vs ON dvb.vehicle_set_id = vs.id
                     LEFT JOIN vehicle_units vu1 ON vs.primary_vehicle_unit_id = vu1.id
                     LEFT JOIN vehicle_units vu2 ON vs.secondary_vehicle_unit_id = vu2.id
                     WHERE dvb.driver_id = ? AND dvb.status != 'archived' ORDER BY dvb.id DESC"
                );
                $driverBlocks->execute([(int)$id]);
                $driverBlocks = $driverBlocks->fetchAll(PDO::FETCH_ASSOC);
            } catch (\Exception $e) {
                $driverBlocks = [];
            }
        }

        $grants = [];
        $logists = [];
        if (($_SESSION['role_code'] ?? '') === 'company_owner') {
            $grantsStmt = $localPdo->prepare(
                "SELECT g.*, u.full_name AS logist_name
                 FROM entity_access_grants g
                 LEFT JOIN users u ON g.granted_to_user_id = u.id
                 WHERE g.entity_type = ? AND g.entity_id = ?"
            );
            $grantsStmt->execute(['driver', (int)$id]);
            $grants = $grantsStmt->fetchAll(PDO::FETCH_ASSOC);

            $logists = $localPdo->query("SELECT id, full_name, login FROM users WHERE role_code IN ('logist', 'senior_logist') AND status='active' ORDER BY full_name")->fetchAll(PDO::FETCH_ASSOC);
        }

        $dbError = null;
    } catch (\Exception $e) {
        $company = $company ?? null;
        $driver = null;
        $grants = [];
        $logists = [];
        $phones = [];
        $driverBlocks = [];
        $dbError = 'Не удалось загрузить водителя: ' . $e->getMessage();
    }

    ob_start();
    require base_path('app/View/pages/company_driver_view.php');
    $content = ob_get_clean();
    require base_path('app/View/layouts/main.php');
});

$router->get('/company/drivers/{id}/edit', function ($id) use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);
    $pageTitle = 'Редактировать водителя';
    $pageContext = 'Водители › Компания';

    $companyId = (int)(getSessionCompanyId() ?? 0);

    if ($companyId <= 0) {
        $company = null;
        $driver = null;
        $errors = [];
        $old = [];
        $formError = null;

        ob_start();
        require base_path('app/View/pages/company_driver_edit.php');
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
            $driver = null;
            $errors = [];
            $old = [];
            $formError = null;

            ob_start();
            require base_path('app/View/pages/company_driver_edit.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $pageContext = 'Водители › Компания: ' . $company['name'];

        if ($company['status'] !== 'active') {
            $driver = null;
            $errors = [];
            $old = [];
            $formError = null;

            ob_start();
            require base_path('app/View/pages/company_driver_edit.php');
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
            $localPdo->query("SELECT 1 FROM drivers LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $migrationSql = file_get_contents(base_path('database/migrations-local/004_create_company_drivers.sql'));
            $localPdo->exec($migrationSql);
        }

        $driverStmt = $localPdo->prepare('SELECT * FROM drivers WHERE id = ?');
        $driverStmt->execute([(int) $id]);
        $driver = $driverStmt->fetch(PDO::FETCH_ASSOC) ?: null;

        if (!$driver) {
            $driver = null;
            $errors = [];
            $old = [];
            $formError = null;

            ob_start();
            require base_path('app/View/pages/company_driver_edit.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $errors = [];
        $old = $driver;
        $formError = null;

        ob_start();
        require base_path('app/View/pages/company_driver_edit.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
    } catch (\Exception $e) {
        $company = $company ?? null;
        $driver = null;
        $errors = [];
        $old = [];
        $formError = 'Не удалось загрузить водителя: ' . $e->getMessage();

        ob_start();
        require base_path('app/View/pages/company_driver_edit.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
    }
});

$router->post('/company/drivers/{id}/edit', function ($id) use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);
    $pageTitle = 'Редактировать водителя';
    $pageContext = 'Водители › Компания';

    $companyId = (int)(getSessionCompanyId() ?? 0);

    if ($companyId <= 0) {
        $company = null;
        $driver = null;
        $errors = [];
        $old = $_POST;
        $formError = 'Компания не найдена';

        ob_start();
        require base_path('app/View/pages/company_driver_edit.php');
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
            $driver = null;
            $errors = [];
            $old = $_POST;
            $formError = 'Компания не найдена';

            ob_start();
            require base_path('app/View/pages/company_driver_edit.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $pageContext = 'Водители › Компания: ' . $company['name'];

        if ($company['status'] !== 'active') {
            $driver = null;
            $errors = [];
            $old = $_POST;
            $formError = 'Редактирование водителей недоступно';

            ob_start();
            require base_path('app/View/pages/company_driver_edit.php');
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
            $localPdo->query("SELECT 1 FROM drivers LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $migrationSql = file_get_contents(base_path('database/migrations-local/004_create_company_drivers.sql'));
            $localPdo->exec($migrationSql);
        }

        $driverStmt = $localPdo->prepare('SELECT * FROM drivers WHERE id = ?');
        $driverStmt->execute([(int) $id]);
        $driver = $driverStmt->fetch(PDO::FETCH_ASSOC) ?: null;

        if (!$driver) {
            $driver = null;
            $errors = [];
            $old = $_POST;
            $formError = 'Водитель не найден';

            ob_start();
            require base_path('app/View/pages/company_driver_edit.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        // Access check for logist
        $isLogist = ($_SESSION['role_code'] ?? '') === 'logist';
        if ($isLogist) {
            $userId = (int)$_SESSION['user_id'];
            $hasGrantEdit = false;
            $gc = $localPdo->prepare("SELECT access_level FROM entity_access_grants WHERE entity_type = 'driver' AND entity_id = ? AND granted_to_user_id = ? AND revoked_at IS NULL LIMIT 1");
            $gc->execute([(int)$id, $userId]);
            $gr = $gc->fetch(PDO::FETCH_ASSOC);
            $hasGrantEdit = ($gr && $gr['access_level'] === 'edit');
            if ((int)$driver['created_by_user_id'] !== $userId && !$hasGrantEdit) {
                $errors = []; $old = $driver;
                $formError = (int)$driver['created_by_user_id'] !== $userId ? 'У вас есть доступ на просмотр, но нет права редактировать эту запись.' : 'У вас нет доступа к этой записи.';
                ob_start();
                require base_path('app/View/pages/company_driver_edit.php');
                $content = ob_get_clean();
                require base_path('app/View/layouts/main.php');
                return;
            }
        }

        $errors = [];
        $old = $_POST;
        $formError = null;

        $fullName = trim($_POST['full_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');

        if ($fullName === '') {
            $errors['full_name'] = 'Обязательное поле';
        }

        // Phone is optional — no uniqueness check (business rule: phone is not a unique identifier)

        if (!empty($errors)) {
            ob_start();
            require base_path('app/View/pages/company_driver_edit.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $update = $localPdo->prepare(
            'UPDATE drivers SET
                full_name = :full_name,
                phone = :phone,
                license_number = :license_number,
                license_category = :license_category,
                license_issue_date = :license_issue_date,
                license_expire_date = :license_expire_date,
                passport_number = :passport_number,
                passport_issued_by = :passport_issued_by,
                passport_department_code = :passport_department_code,
                passport_issue_date = :passport_issue_date,
                snils = :snils,
                status = :status,
                comments = :comments,
                updated_by_user_id = :updated_by_user_id,
                updated_by_role = :updated_by_role
             WHERE id = :id'
        );

        $update->execute([
            ':full_name'              => $fullName,
            ':phone'                  => $phone,
            ':license_number'         => $_POST['license_number'] ?? null,
            ':license_category'       => $_POST['license_category'] ?? null,
            ':license_issue_date'     => $_POST['license_issue_date'] ?? null,
            ':license_expire_date'    => $_POST['license_expire_date'] ?? null,
            ':passport_number'        => buildPassportNumber($_POST['passport_series'] ?? null, $_POST['passport_number'] ?? null),
            ':passport_issued_by'     => $_POST['passport_issued_by'] ?? null,
            ':passport_department_code' => $_POST['passport_department_code'] ?? null,
            ':passport_issue_date'    => $_POST['passport_issue_date'] ?? null,
            ':snils'                  => $_POST['snils'] ?? null,
            ':status'                 => $_POST['status'] ?? $driver['status'],
            ':comments'               => $_POST['comments'] ?? null,
            ':updated_by_user_id'     => (int)$_SESSION['user_id'],
            ':updated_by_role'        => $_POST['role_code'] ?? null,
            ':id'                     => (int) $id,
        ]);

        header('Location: /company/drivers/' . $id);
        exit;
    } catch (\Exception $e) {
        $company = $company ?? null;
        $driver = $driver ?? null;
        $errors = [];
        $old = $_POST;
        $formError = 'Ошибка сохранения: ' . $e->getMessage();

        ob_start();
        require base_path('app/View/pages/company_driver_edit.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
    }
});

$router->post('/company/drivers/{id}/archive', function ($id) use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);
    $pageTitle = 'Водитель';
    $pageContext = 'Водители › Компания';

    $companyId = (int)(getSessionCompanyId() ?? 0);
    $archiveError = null;

    if ($companyId <= 0) {
        $company = null;
        $driver = null;
        $dbError = null;

        ob_start();
        require base_path('app/View/pages/company_driver_view.php');
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
            $driver = null;
            $dbError = null;

            ob_start();
            require base_path('app/View/pages/company_driver_view.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $pageContext = 'Водители › Компания: ' . $company['name'];

        if ($company['status'] !== 'active') {
            $driver = null;
            $dbError = null;

            ob_start();
            require base_path('app/View/pages/company_driver_view.php');
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
            $localPdo->query("SELECT 1 FROM drivers LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $migrationSql = file_get_contents(base_path('database/migrations-local/004_create_company_drivers.sql'));
            $localPdo->exec($migrationSql);
        }

        $driverStmt = $localPdo->prepare('SELECT * FROM drivers WHERE id = ?');
        $driverStmt->execute([(int) $id]);
        $driver = $driverStmt->fetch(PDO::FETCH_ASSOC) ?: null;

        if (!$driver) {
            $driver = null;
            $dbError = null;

            ob_start();
            require base_path('app/View/pages/company_driver_view.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        // Archive access check: logist can only archive own records
        $isLogist = ($_SESSION['role_code'] ?? '') === 'logist';
        if ($isLogist) {
            $userId = (int)$_SESSION['user_id'];
            if ((int)$driver['created_by_user_id'] !== $userId) {
                $archiveError = 'Логист может архивировать только записи, созданные им самим.';
                $dbError = null;
                ob_start();
                require base_path('app/View/pages/company_driver_view.php');
                $content = ob_get_clean();
                require base_path('app/View/layouts/main.php');
                return;
            }
        }

        $pageTitle = 'Водитель: ' . $driver['full_name'];

        try {
            $localPdo->query("SELECT 1 FROM crews LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $migrationSql = file_get_contents(base_path('database/migrations-local/006_create_company_crews.sql'));
            $localPdo->exec($migrationSql);
        }

        try {
            $localPdo->query("SELECT 1 FROM driver_vehicle_blocks LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $migrationSql = file_get_contents(base_path('database/migrations-local/018_create_driver_vehicle_blocks.sql'));
            $localPdo->exec($migrationSql);
        }

        $crewCheck = $localPdo->prepare(
            'SELECT COUNT(*) FROM crews c
             JOIN driver_vehicle_blocks dvb ON dvb.id = c.driver_vehicle_block_id
             WHERE dvb.driver_id = ?'
        );
        $crewCheck->execute([(int) $id]);
        if ($crewCheck->fetchColumn() > 0) {
            $archiveError = 'Водитель участвует в экипажах. Сначала удалите экипажи.';
            $dbError = null;

            ob_start();
            require base_path('app/View/pages/company_driver_view.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $update = $localPdo->prepare("UPDATE drivers SET status = 'archived' WHERE id = ?");
        $update->execute([(int) $id]);

        header('Location: /company/drivers');
        exit;
    } catch (\Exception $e) {
        $company = $company ?? null;
        $driver = null;
        $dbError = 'Ошибка архивирования: ' . $e->getMessage();

        ob_start();
        require base_path('app/View/pages/company_driver_view.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
    }
});

// --- Driver Modal View (dblclick from list) ---

$router->get('/company/drivers/{id}/modal-view', function ($id) use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);

    $companyId = (int)(getSessionCompanyId() ?? 0);

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

        if (!$company || $company['status'] !== 'active') {
            http_response_code(404);
            echo '<div class="notice warn">Компания не найдена или не активна.</div>';
            exit;
        }

        $dbIdentifier = $company['db_identifier'];
        $localDbConfig = $config['database'];
        $localDbConfig['database'] = $dbIdentifier;
        $localDb = new \App\Core\Database($localDbConfig);
        $localPdo = $localDb->connection();
        applyLocalMigrations($localPdo);

        try {
            $localPdo->query("SELECT 1 FROM drivers LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $migrationSql = file_get_contents(base_path('database/migrations-local/004_create_company_drivers.sql'));
            $localPdo->exec($migrationSql);
        }

        try {
            $localPdo->query("SELECT created_by_user_id FROM drivers LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $localPdo->exec("ALTER TABLE drivers ADD COLUMN created_by_user_id INT UNSIGNED DEFAULT NULL, ADD COLUMN created_by_role VARCHAR(20) DEFAULT NULL");
        }

        try {
            $localPdo->query("SELECT 1 FROM entity_access_grants LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $migrationSql = file_get_contents(base_path('database/migrations-local/009_create_entity_access_grants.sql'));
            $localPdo->exec($migrationSql);
        }

        $driverStmt = $localPdo->prepare('SELECT * FROM drivers WHERE id = ?');
        $driverStmt->execute([(int) $id]);
        $driver = $driverStmt->fetch(PDO::FETCH_ASSOC) ?: null;

        if (!$driver) {
            http_response_code(404);
            echo '<div class="notice warn">Водитель не найден.</div>';
            exit;
        }

        // Access check
        $isLogist = ($_SESSION['role_code'] ?? '') === 'logist';
        if ($isLogist) {
            $userId = (int)$_SESSION['user_id'];
            $hasGrant = false;
            $grantCheck = $localPdo->prepare("SELECT access_level FROM entity_access_grants WHERE entity_type = 'driver' AND entity_id = ? AND granted_to_user_id = ? AND revoked_at IS NULL LIMIT 1");
            $grantCheck->execute([(int)$id, $userId]);
            $grantRow = $grantCheck->fetch(PDO::FETCH_ASSOC);
            $hasGrant = ($grantRow && in_array($grantRow['access_level'], ['view', 'edit']));
            if ((int)$driver['created_by_user_id'] !== $userId && !$hasGrant) {
                http_response_code(403);
                echo '<div class="notice warn">У вас нет доступа к этой записи.</div>';
                exit;
            }
        }

        // Load phones
        $phones = $localPdo->prepare("SELECT * FROM driver_phones WHERE driver_id = ? ORDER BY is_main DESC, id ASC");
        $phones->execute([(int)$id]);
        $phones = $phones->fetchAll(PDO::FETCH_ASSOC);

        $mainPhone = null;
        foreach ($phones as $ph) {
            if ($ph['is_main'] && !empty($ph['phone'])) {
                $mainPhone = $ph['phone'];
                break;
            }
        }
        if ($mainPhone === null && !empty($phones)) {
            $mainPhone = $phones[0]['phone'] ?? null;
        }
        // Fallback to driver.phone
        if ($mainPhone === null && !empty($driver['phone'])) {
            $mainPhone = $driver['phone'];
        }

        // Load documents
        $docStmt = $localPdo->prepare(
            "SELECT id, entity_id, document_type, original_name, mime_type, stored_name, file_size
             FROM documents
             WHERE entity_type = 'driver'
               AND entity_id = ?
               AND deleted_at IS NULL
             ORDER BY id"
        );
        $docStmt->execute([(int)$id]);
        $allDocs = $docStmt->fetchAll(PDO::FETCH_ASSOC);

        $docsByType = ['passport' => [], 'license' => [], 'snils' => [], 'other' => []];
        foreach ($allDocs as $doc) {
            $dt = mb_strtolower($doc['document_type'] ?? '');
            if (strpos($dt, 'паспорт') !== false) {
                $docsByType['passport'][] = $doc;
            } elseif (strpos($dt, 'водительск') !== false || strpos($dt, 'ву') !== false) {
                $docsByType['license'][] = $doc;
            } elseif (strpos($dt, 'снилс') !== false) {
                $docsByType['snils'][] = $doc;
            } else {
                $docsByType['other'][] = $doc;
            }
        }

        $zipAvailable = class_exists('ZipArchive');

        // Permissions
        $canEdit = false;
        $canDelete = false;
        $role = $_SESSION['role_code'] ?? '';
        if ($role === 'company_owner' || $role === 'senior_logist') {
            $canEdit = true;
            $canDelete = true;
        } elseif ($role === 'logist') {
            $userId = (int)$_SESSION['user_id'];
            if ((int)$driver['created_by_user_id'] === $userId) {
                $canEdit = true;
                $canDelete = true;
            } else {
                $gc = $localPdo->prepare("SELECT access_level FROM entity_access_grants WHERE entity_type = 'driver' AND entity_id = ? AND granted_to_user_id = ? AND revoked_at IS NULL LIMIT 1");
                $gc->execute([(int)$id, $userId]);
                $gr = $gc->fetch(PDO::FETCH_ASSOC);
                if ($gr && $gr['access_level'] === 'edit') {
                    $canEdit = true;
                }
            }
        }

        header('Content-Type: text/html; charset=utf-8');
        require base_path('app/View/partials/company_driver_modal_view.php');
        exit;

    } catch (\Exception $e) {
        http_response_code(500);
        echo '<div class="notice warn">Ошибка загрузки: ' . e($e->getMessage()) . '</div>';
        exit;
    }
});

// --- Driver Modal Edit (GET) ---

$router->get('/company/drivers/{id}/modal-edit', function ($id) use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);

    $companyId = (int)(getSessionCompanyId() ?? 0);

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

        if (!$company || $company['status'] !== 'active') {
            http_response_code(404);
            echo '<div class="notice warn">Компания не найдена или не активна.</div>';
            exit;
        }

        $dbIdentifier = $company['db_identifier'];
        $localDbConfig = $config['database'];
        $localDbConfig['database'] = $dbIdentifier;
        $localDb = new \App\Core\Database($localDbConfig);
        $localPdo = $localDb->connection();
        applyLocalMigrations($localPdo);

        try {
            $localPdo->query("SELECT 1 FROM drivers LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $migrationSql = file_get_contents(base_path('database/migrations-local/004_create_company_drivers.sql'));
            $localPdo->exec($migrationSql);
        }

        try {
            $localPdo->query("SELECT created_by_user_id FROM drivers LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $localPdo->exec("ALTER TABLE drivers ADD COLUMN created_by_user_id INT UNSIGNED DEFAULT NULL, ADD COLUMN created_by_role VARCHAR(20) DEFAULT NULL");
        }

        try {
            $localPdo->query("SELECT 1 FROM entity_access_grants LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $migrationSql = file_get_contents(base_path('database/migrations-local/009_create_entity_access_grants.sql'));
            $localPdo->exec($migrationSql);
        }

        $driverStmt = $localPdo->prepare('SELECT * FROM drivers WHERE id = ?');
        $driverStmt->execute([(int) $id]);
        $driver = $driverStmt->fetch(PDO::FETCH_ASSOC) ?: null;

        if (!$driver) {
            http_response_code(404);
            echo '<div class="notice warn">Водитель не найден.</div>';
            exit;
        }

        // Edit permission check
        $role = $_SESSION['role_code'] ?? '';
        $canEdit = false;
        $canDelete = false;
        if ($role === 'company_owner' || $role === 'senior_logist') {
            $canEdit = true;
            $canDelete = true;
        } elseif ($role === 'logist') {
            $userId = (int)$_SESSION['user_id'];
            if ((int)$driver['created_by_user_id'] === $userId) {
                $canEdit = true;
                $canDelete = true;
            } else {
                $gc = $localPdo->prepare("SELECT access_level FROM entity_access_grants WHERE entity_type = 'driver' AND entity_id = ? AND granted_to_user_id = ? AND revoked_at IS NULL LIMIT 1");
                $gc->execute([(int)$id, $userId]);
                $gr = $gc->fetch(PDO::FETCH_ASSOC);
                if ($gr && $gr['access_level'] === 'edit') {
                    $canEdit = true;
                }
            }
        }

        if (!$canEdit) {
            http_response_code(403);
            echo '<div class="notice warn">У вас нет права редактировать эту запись.</div>';
            exit;
        }

        $old = $driver;
        $errors = [];
        $formError = null;

        // Load phones
        $phones = $localPdo->prepare("SELECT * FROM driver_phones WHERE driver_id = ? ORDER BY is_main DESC, id ASC");
        $phones->execute([(int)$id]);
        $phones = $phones->fetchAll(PDO::FETCH_ASSOC);

        // Load documents
        $docStmt = $localPdo->prepare(
            "SELECT id, entity_id, document_type, original_name, mime_type, stored_name, file_size
             FROM documents
             WHERE entity_type = 'driver'
               AND entity_id = ?
               AND deleted_at IS NULL
             ORDER BY id"
        );
        $docStmt->execute([(int)$id]);
        $allDocs = $docStmt->fetchAll(PDO::FETCH_ASSOC);

        $docsByType = ['passport' => [], 'license' => [], 'snils' => [], 'other' => []];
        foreach ($allDocs as $doc) {
            $dt = mb_strtolower($doc['document_type'] ?? '');
            if (strpos($dt, 'паспорт') !== false) {
                $docsByType['passport'][] = $doc;
            } elseif (strpos($dt, 'водительск') !== false || strpos($dt, 'ву') !== false) {
                $docsByType['license'][] = $doc;
            } elseif (strpos($dt, 'снилс') !== false) {
                $docsByType['snils'][] = $doc;
            } else {
                $docsByType['other'][] = $doc;
            }
        }

        // Load docTypes (for create form JS custom doc type suggestions)
        try {
            $docTypes = $localPdo->query("SELECT id, name, code, entity_type FROM document_types WHERE entity_type = 'driver' OR entity_type IS NULL ORDER BY sort_order, name")->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            $docTypes = [];
        }

        header('Content-Type: text/html; charset=utf-8');
        require base_path('app/View/partials/company_driver_modal_edit.php');
        exit;

    } catch (\Exception $e) {
        http_response_code(500);
        echo '<div class="notice warn">Ошибка загрузки: ' . e($e->getMessage()) . '</div>';
        exit;
    }
});

// --- Driver Modal Edit (POST) ---

$router->post('/company/drivers/{id}/modal-edit', function ($id) use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);

    $companyId = (int)(getSessionCompanyId() ?? 0);

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

        if (!$company || $company['status'] !== 'active') {
            http_response_code(404);
            echo '<div class="notice warn">Компания не найдена или не активна.</div>';
            exit;
        }

        $dbIdentifier = $company['db_identifier'];
        $localDbConfig = $config['database'];
        $localDbConfig['database'] = $dbIdentifier;
        $localDb = new \App\Core\Database($localDbConfig);
        $localPdo = $localDb->connection();
        applyLocalMigrations($localPdo);

        try {
            $localPdo->query("SELECT 1 FROM drivers LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $migrationSql = file_get_contents(base_path('database/migrations-local/004_create_company_drivers.sql'));
            $localPdo->exec($migrationSql);
        }

        try {
            $localPdo->query("SELECT created_by_user_id FROM drivers LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $localPdo->exec("ALTER TABLE drivers ADD COLUMN created_by_user_id INT UNSIGNED DEFAULT NULL, ADD COLUMN created_by_role VARCHAR(20) DEFAULT NULL");
        }

        try {
            $localPdo->query("SELECT 1 FROM entity_access_grants LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $migrationSql = file_get_contents(base_path('database/migrations-local/009_create_entity_access_grants.sql'));
            $localPdo->exec($migrationSql);
        }

        try {
            $localPdo->query("SELECT 1 FROM driver_phones LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $localPdo->exec(file_get_contents(base_path('database/migrations-local/015_create_driver_phones.sql')));
        }

        $driverStmt = $localPdo->prepare('SELECT * FROM drivers WHERE id = ?');
        $driverStmt->execute([(int) $id]);
        $driver = $driverStmt->fetch(PDO::FETCH_ASSOC) ?: null;

        if (!$driver) {
            http_response_code(404);
            echo '<div class="notice warn">Водитель не найден.</div>';
            exit;
        }

        // Edit permission check
        $role = $_SESSION['role_code'] ?? '';
        $canEdit = false;
        $canDelete = false;
        if ($role === 'company_owner' || $role === 'senior_logist') {
            $canEdit = true;
            $canDelete = true;
        } elseif ($role === 'logist') {
            $userId = (int)$_SESSION['user_id'];
            if ((int)$driver['created_by_user_id'] === $userId) {
                $canEdit = true;
                $canDelete = true;
            } else {
                $gc = $localPdo->prepare("SELECT access_level FROM entity_access_grants WHERE entity_type = 'driver' AND entity_id = ? AND granted_to_user_id = ? AND revoked_at IS NULL LIMIT 1");
                $gc->execute([(int)$id, $userId]);
                $gr = $gc->fetch(PDO::FETCH_ASSOC);
                if ($gr && $gr['access_level'] === 'edit') {
                    $canEdit = true;
                }
            }
        }

        if (!$canEdit) {
            http_response_code(403);
            echo '<div class="notice warn">У вас нет права редактировать эту запись.</div>';
            exit;
        }

        // Load existing phones and docs (needed for re-render on validation errors)
        $phones = $localPdo->prepare("SELECT * FROM driver_phones WHERE driver_id = ? ORDER BY is_main DESC, id ASC");
        $phones->execute([(int)$id]);
        $phones = $phones->fetchAll(PDO::FETCH_ASSOC);

        $docStmt = $localPdo->prepare(
            "SELECT id, entity_id, document_type, original_name, mime_type, stored_name, file_size
             FROM documents
             WHERE entity_type = 'driver'
               AND entity_id = ?
               AND deleted_at IS NULL
             ORDER BY id"
        );
        $docStmt->execute([(int)$id]);
        $allDocs = $docStmt->fetchAll(PDO::FETCH_ASSOC);

        $docsByType = ['passport' => [], 'license' => [], 'snils' => [], 'other' => []];
        foreach ($allDocs as $doc) {
            $dt = mb_strtolower($doc['document_type'] ?? '');
            if (strpos($dt, 'паспорт') !== false) {
                $docsByType['passport'][] = $doc;
            } elseif (strpos($dt, 'водительск') !== false || strpos($dt, 'ву') !== false) {
                $docsByType['license'][] = $doc;
            } elseif (strpos($dt, 'снилс') !== false) {
                $docsByType['snils'][] = $doc;
            } else {
                $docsByType['other'][] = $doc;
            }
        }

        try {
            $docTypes = $localPdo->query("SELECT id, name, code, entity_type FROM document_types WHERE entity_type = 'driver' OR entity_type IS NULL ORDER BY sort_order, name")->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            $docTypes = [];
        }

        // --- Validation ---
        $errors = [];
        $old = $_POST;

        // full_name: required, 3 words, title case
        $fullName = trim($_POST['full_name'] ?? '');
        if ($fullName === '') {
            $errors['full_name'] = 'Обязательное поле';
        } else {
            $fullName = preg_replace('/\s+/', ' ', $fullName);
            $parts = explode(' ', $fullName);
            if (count($parts) !== 3) {
                $errors['full_name'] = 'ФИО должно состоять из трёх слов';
            } else {
                $titleCase = function ($w) {
                    return mb_strtoupper(mb_substr($w, 0, 1)) . mb_strtolower(mb_substr($w, 1));
                };
                $fullName = implode(' ', array_map($titleCase, $parts));
            }
        }

        // phone: optional, format +7 XXX XXX-XX-XX
        $phone = trim($_POST['phone'] ?? '');
        if ($phone !== '') {
            $digits = preg_replace('/\D/', '', $phone);
            if (strlen($digits) === 11 && $digits[0] === '8') $digits = '7' . substr($digits, 1);
            if (strlen($digits) === 10) $digits = '7' . $digits;
            if (strlen($digits) === 11 && $digits[0] === '7') {
                $phone = '+7 ' . substr($digits, 1, 3) . ' ' . substr($digits, 4, 3) . '-' . substr($digits, 7, 2) . '-' . substr($digits, 9, 2);
            } else {
                $phone = $_POST['phone']; // keep as-is, not critical
            }
        }

        // email: optional, format validation
        $email = trim($_POST['email'] ?? '');
        if ($email !== '') {
            $email = mb_strtolower($email);
            if (!preg_match('/^[^\s@]+@[^\s@]+\.[^\s@]+$/', $email)) {
                $errors['email'] = 'Неверный формат email';
            }
        }

        // passport_number: optional, 10 digits → "0000 000000"
        $passportNumber = trim($_POST['passport_number'] ?? '');
        if ($passportNumber !== '') {
            $digits = preg_replace('/\D/', '', $passportNumber);
            if (strlen($digits) === 10) {
                $passportNumber = substr($digits, 0, 4) . ' ' . substr($digits, 4);
            }
        }

        // passport_department_code: optional, 6 digits → "000-000"
        $passportDeptCode = trim($_POST['passport_department_code'] ?? '');
        if ($passportDeptCode !== '') {
            $digits = preg_replace('/\D/', '', $passportDeptCode);
            if (strlen($digits) === 6) {
                $passportDeptCode = substr($digits, 0, 3) . '-' . substr($digits, 3);
            }
        }

        $passportIssuedBy = trim($_POST['passport_issued_by'] ?? '');
        $passportIssueDate = trim($_POST['passport_issue_date'] ?? '');

        // license_number: optional, 10 digits
        $licenseNumber = trim($_POST['license_number'] ?? '');
        if ($licenseNumber !== '') {
            $digits = preg_replace('/\D/', '', $licenseNumber);
            if (strlen($digits) === 10) {
                $licenseNumber = substr($digits, 0, 4) . ' ' . substr($digits, 4);
            }
        }

        $licenseIssueDate = trim($_POST['license_issue_date'] ?? '');

        // snils: optional, 11 digits → "000-000-000 00"
        $snils = trim($_POST['snils'] ?? '');
        if ($snils !== '') {
            $digits = preg_replace('/\D/', '', $snils);
            if (strlen($digits) === 11) {
                $snils = substr($digits, 0, 3) . '-' . substr($digits, 3, 3) . '-' . substr($digits, 6, 3) . ' ' . substr($digits, 9);
            }
        }

        $comments = trim($_POST['comments'] ?? '');

        if (!empty($errors)) {
            header('Content-Type: text/html; charset=utf-8');
            require base_path('app/View/partials/company_driver_modal_edit.php');
            exit;
        }

        // UPDATE
        $update = $localPdo->prepare(
            'UPDATE drivers SET
                full_name = :full_name,
                phone = :phone,
                email = :email,
                passport_number = :passport_number,
                passport_department_code = :passport_department_code,
                passport_issued_by = :passport_issued_by,
                passport_issue_date = :passport_issue_date,
                license_number = :license_number,
                license_issue_date = :license_issue_date,
                snils = :snils,
                comments = :comments,
                updated_by_user_id = :updated_by_user_id,
                updated_by_role = :updated_by_role
             WHERE id = :id'
        );

        $update->execute([
            ':full_name'                => $fullName,
            ':phone'                    => $phone ?: null,
            ':email'                    => $email ?: null,
            ':passport_number'          => $passportNumber ?: null,
            ':passport_department_code' => $passportDeptCode ?: null,
            ':passport_issued_by'       => $passportIssuedBy ?: null,
            ':passport_issue_date'      => $passportIssueDate ?: null,
            ':license_number'           => $licenseNumber ?: null,
            ':license_issue_date'       => $licenseIssueDate ?: null,
            ':snils'                    => $snils ?: null,
            ':comments'                 => $comments ?: null,
            ':updated_by_user_id'       => (int)$_SESSION['user_id'],
            ':updated_by_role'          => $_SESSION['role_code'] ?? null,
            ':id'                       => (int) $id,
        ]);

        // Replace driver_phones: delete existing extra phones, insert new ones
        // Keep the main phone as-is (first in list or explicitly marked)
        $localPdo->prepare("DELETE FROM driver_phones WHERE driver_id = ? AND is_main = 0")->execute([(int)$id]);

        $extraPhones = $_POST['extra_phones'] ?? [];
        $extraComments = $_POST['extra_phone_comments'] ?? [];
        $insertPhone = $localPdo->prepare(
            "INSERT INTO driver_phones (driver_id, phone, is_main, comment, created_by_user_id, created_by_role)
             VALUES (?, ?, 0, ?, ?, ?)"
        );
        $userId = (int)$_SESSION['user_id'];
        $userRole = $_SESSION['role_code'] ?? null;
        foreach ($extraPhones as $idx => $ep) {
            $ep = trim($ep);
            if ($ep === '') continue;
            $comment = trim($extraComments[$idx] ?? '');
            $insertPhone->execute([(int)$id, $ep, $comment, $userId, $userRole]);
        }

        // Update main phone if provided (update the is_main=1 record, or update drivers.phone)
        $mainPhoneCheck = $localPdo->prepare("SELECT id FROM driver_phones WHERE driver_id = ? AND is_main = 1 LIMIT 1");
        $mainPhoneCheck->execute([(int)$id]);
        $mainPhoneRow = $mainPhoneCheck->fetch(PDO::FETCH_ASSOC);
        if ($mainPhoneRow && !empty($phone)) {
            $localPdo->prepare("UPDATE driver_phones SET phone = ? WHERE id = ?")->execute([$phone, $mainPhoneRow['id']]);
        } elseif (!empty($phone)) {
            // No main phone record — create one
            $localPdo->prepare(
                "INSERT INTO driver_phones (driver_id, phone, is_main, comment, created_by_user_id, created_by_role)
                 VALUES (?, ?, 1, '', ?, ?)"
            )->execute([(int)$id, $phone, $userId, $userRole]);
        }

        // --- Document handling ---
        $allowedExts = ['pdf','doc','docx','rtf','odt','xls','xlsx','csv','ods','jpg','jpeg','png','webp','gif','bmp','tif','tiff','heic','heif','txt'];
        $maxFileSizeDoc = 20 * 1024 * 1024;
        $storageBase = 'companies/' . $companyId . '/documents/driver/' . (int)$id;

        // Map predef doc codes to document_type names
        $predefDocTypeNames = [
            'passport'       => 'Паспорт',
            'driver_license' => 'Водительское удостоверение',
            'snils'          => 'СНИЛС',
        ];

        // 1. Delete specific existing documents
        $deleteExistingDocs = $_POST['delete_existing_doc'] ?? [];
        foreach ($deleteExistingDocs as $docId => $val) {
            if ($val !== '1') {
                continue;
            }
            $localPdo->prepare(
                "UPDATE documents
                    SET deleted_at = NOW(),
                        deleted_by_user_id = ?,
                        delete_comment = 'Archived via modal edit'
                  WHERE id = ?
                    AND entity_type = 'driver'
                    AND entity_id = ?
                    AND deleted_at IS NULL"
            )->execute([(int)$_SESSION['user_id'], (int)$docId, (int)$id]);
        }

        // 2. Replace specific existing documents
        if (!empty($_FILES['existing_doc_file']['name']) && is_array($_FILES['existing_doc_file']['name'])) {
            foreach ($_FILES['existing_doc_file']['name'] as $docId => $origName) {
                $uploadError = $_FILES['existing_doc_file']['error'][$docId] ?? UPLOAD_ERR_NO_FILE;
                if ($uploadError !== UPLOAD_ERR_OK || trim((string)$origName) === '') {
                    continue;
                }

                $docStmt = $localPdo->prepare(
                    "SELECT id, document_type
                       FROM documents
                      WHERE id = ?
                        AND entity_type = 'driver'
                        AND entity_id = ?
                        AND deleted_at IS NULL
                      LIMIT 1"
                );
                $docStmt->execute([(int)$docId, (int)$id]);
                $existingDoc = $docStmt->fetch(PDO::FETCH_ASSOC);
                if (!$existingDoc) {
                    continue;
                }

                $size = (int)($_FILES['existing_doc_file']['size'][$docId] ?? 0);
                if ($size > $maxFileSizeDoc) {
                    continue;
                }

                $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
                if (!in_array($ext, $allowedExts, true)) {
                    continue;
                }

                $tmp = $_FILES['existing_doc_file']['tmp_name'][$docId] ?? '';
                if ($tmp === '') {
                    continue;
                }

                $localPdo->prepare(
                    "UPDATE documents
                        SET deleted_at = NOW(),
                            deleted_by_user_id = ?,
                            delete_comment = 'Replaced via modal edit'
                      WHERE id = ?
                        AND entity_type = 'driver'
                        AND entity_id = ?
                        AND deleted_at IS NULL"
                )->execute([(int)$_SESSION['user_id'], (int)$docId, (int)$id]);

                $storedName = uniqid('doc_', true) . '.' . $ext;
                $absoluteDir = storage_path($storageBase);
                if (!is_dir($absoluteDir)) {
                    mkdir($absoluteDir, 0755, true);
                }
                move_uploaded_file($tmp, $absoluteDir . '/' . $storedName);

                $localPdo->prepare(
                    "INSERT INTO documents (entity_type, entity_id, document_type, original_name, stored_name, relative_path, mime_type, file_size, status, uploaded_by_user_id, uploaded_by_role)
                     VALUES ('driver', ?, ?, ?, ?, ?, ?, ?, 'uploaded', ?, ?)"
                )->execute([
                    (int)$id,
                    $existingDoc['document_type'] ?? null,
                    $origName,
                    $storedName,
                    $storageBase . '/' . $storedName,
                    mime_content_type($absoluteDir . '/' . $storedName) ?: 'application/octet-stream',
                    $size,
                    (int)$_SESSION['user_id'],
                    $_SESSION['role_code'] ?? null
                ]);
            }
        }

        // 2b. Legacy grouped predef replacement fallback
        if (!empty($_FILES['predef_doc']['name'])) {
            foreach ($_FILES['predef_doc']['name'] as $code => $names) {
                if (!is_array($names)) $names = [$names];
                $typeName = $predefDocTypeNames[$code] ?? null;
                if (!$typeName) continue;
                $hasNewFile = false;
                foreach ($names as $n) { if (!empty($n)) { $hasNewFile = true; break; } }
                if (!$hasNewFile) continue;

                $localPdo->prepare(
                    "UPDATE documents SET deleted_at = NOW(), deleted_by_user_id = ?, delete_comment = 'Replaced via modal edit'
                     WHERE entity_type = 'driver' AND entity_id = ? AND document_type = ? AND deleted_at IS NULL"
                )->execute([(int)$_SESSION['user_id'], (int)$id, $typeName]);

                $tmpNames = $_FILES['predef_doc']['tmp_name'][$code];
                $origNames = $_FILES['predef_doc']['name'][$code];
                $sizes = $_FILES['predef_doc']['size'][$code];
                $uploadErrors = $_FILES['predef_doc']['error'][$code];
                if (!is_array($tmpNames)) {
                    $tmpNames = [$tmpNames]; $origNames = [$origNames]; $sizes = [$sizes]; $uploadErrors = [$uploadErrors];
                }
                foreach ($tmpNames as $i => $tmp) {
                    if ($uploadErrors[$i] !== UPLOAD_ERR_OK) continue;
                    if ($sizes[$i] > $maxFileSizeDoc) continue;
                    $orig = $origNames[$i];
                    $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
                    if (!in_array($ext, $allowedExts)) continue;
                    $storedName = uniqid('doc_', true) . '.' . $ext;
                    $absoluteDir = storage_path($storageBase);
                    if (!is_dir($absoluteDir)) mkdir($absoluteDir, 0755, true);
                    move_uploaded_file($tmp, $absoluteDir . '/' . $storedName);
                    $localPdo->prepare(
                        "INSERT INTO documents (entity_type, entity_id, document_type, original_name, stored_name, relative_path, mime_type, file_size, status, uploaded_by_user_id, uploaded_by_role)
                         VALUES ('driver', ?, ?, ?, ?, ?, ?, ?, 'uploaded', ?, ?)"
                    )->execute([
                        (int)$id, $typeName, $orig, $storedName,
                        $storageBase . '/' . $storedName,
                        mime_content_type($absoluteDir . '/' . $storedName) ?: 'application/octet-stream',
                        $sizes[$i], (int)$_SESSION['user_id'], $_SESSION['role_code'] ?? null
                    ]);
                }
            }
        }

        // 3. Process custom_doc_file uploads
        if (!empty($_FILES['custom_doc_file']['name'])) {
            $customTypes = $_POST['custom_doc_type'] ?? [];
            $tmpNames = $_FILES['custom_doc_file']['tmp_name'];
            $origNames = $_FILES['custom_doc_file']['name'];
            $sizes = $_FILES['custom_doc_file']['size'];
            $uploadErrors = $_FILES['custom_doc_file']['error'];
            if (!is_array($tmpNames)) {
                $tmpNames = [$tmpNames]; $origNames = [$origNames]; $sizes = [$sizes]; $uploadErrors = [$uploadErrors];
            }
            foreach ($tmpNames as $i => $tmp) {
                if ($uploadErrors[$i] !== UPLOAD_ERR_OK) continue;
                if ($sizes[$i] > $maxFileSizeDoc) continue;
                $orig = $origNames[$i];
                $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
                if (!in_array($ext, $allowedExts)) continue;
                $typeName = trim($customTypes[$i] ?? '');
                if ($typeName === '') continue;
                $storedName = uniqid('doc_', true) . '.' . $ext;
                $absoluteDir = storage_path($storageBase);
                if (!is_dir($absoluteDir)) mkdir($absoluteDir, 0755, true);
                move_uploaded_file($tmp, $absoluteDir . '/' . $storedName);
                $localPdo->prepare(
                    "INSERT INTO documents (entity_type, entity_id, document_type, original_name, stored_name, relative_path, mime_type, file_size, status, uploaded_by_user_id, uploaded_by_role)
                     VALUES ('driver', ?, ?, ?, ?, ?, ?, ?, 'uploaded', ?, ?)"
                )->execute([
                    (int)$id, $typeName, $orig, $storedName,
                    $storageBase . '/' . $storedName,
                    mime_content_type($absoluteDir . '/' . $storedName) ?: 'application/octet-stream',
                    $sizes[$i], (int)$_SESSION['user_id'], $_SESSION['role_code'] ?? null
                ]);
            }
        }

        // Reload driver, phones, documents for view
        $driverStmt = $localPdo->prepare('SELECT * FROM drivers WHERE id = ?');
        $driverStmt->execute([(int) $id]);
        $driver = $driverStmt->fetch(PDO::FETCH_ASSOC);

        $phones = $localPdo->prepare("SELECT * FROM driver_phones WHERE driver_id = ? ORDER BY is_main DESC, id ASC");
        $phones->execute([(int)$id]);
        $phones = $phones->fetchAll(PDO::FETCH_ASSOC);

        $mainPhone = null;
        foreach ($phones as $ph) {
            if ($ph['is_main'] && !empty($ph['phone'])) {
                $mainPhone = $ph['phone'];
                break;
            }
        }
        if ($mainPhone === null && !empty($phones)) {
            $mainPhone = $phones[0]['phone'] ?? null;
        }
        if ($mainPhone === null && !empty($driver['phone'])) {
            $mainPhone = $driver['phone'];
        }

        $docStmt = $localPdo->prepare(
            "SELECT id, entity_id, document_type, original_name, mime_type, stored_name, file_size
             FROM documents
             WHERE entity_type = 'driver'
               AND entity_id = ?
               AND deleted_at IS NULL
             ORDER BY id"
        );
        $docStmt->execute([(int)$id]);
        $allDocs = $docStmt->fetchAll(PDO::FETCH_ASSOC);

        $docsByType = ['passport' => [], 'license' => [], 'snils' => [], 'other' => []];
        foreach ($allDocs as $doc) {
            $dt = mb_strtolower($doc['document_type'] ?? '');
            if (strpos($dt, 'паспорт') !== false) {
                $docsByType['passport'][] = $doc;
            } elseif (strpos($dt, 'водительск') !== false || strpos($dt, 'ву') !== false) {
                $docsByType['license'][] = $doc;
            } elseif (strpos($dt, 'снилс') !== false) {
                $docsByType['snils'][] = $doc;
            } else {
                $docsByType['other'][] = $doc;
            }
        }

        $zipAvailable = class_exists('ZipArchive');

        header('Content-Type: text/html; charset=utf-8');
        require base_path('app/View/partials/company_driver_modal_view.php');
        exit;

    } catch (\Exception $e) {
        http_response_code(500);
        echo '<div class="notice warn">Ошибка сохранения: ' . e($e->getMessage()) . '</div>';
        exit;
    }
});

// --- Driver Modal Delete (POST) ---

$router->post('/company/drivers/{id}/modal-delete', function ($id) use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);

    header('Content-Type: application/json; charset=utf-8');

    $companyId = (int)(getSessionCompanyId() ?? 0);

    if ($companyId <= 0) {
        echo json_encode(['success' => false, 'error' => 'Компания не найдена.']);
        exit;
    }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company || $company['status'] !== 'active') {
            echo json_encode(['success' => false, 'error' => 'Компания не найдена или не активна.']);
            exit;
        }

        $dbIdentifier = $company['db_identifier'];
        $localDbConfig = $config['database'];
        $localDbConfig['database'] = $dbIdentifier;
        $localDb = new \App\Core\Database($localDbConfig);
        $localPdo = $localDb->connection();
        applyLocalMigrations($localPdo);

        $driverStmt = $localPdo->prepare('SELECT * FROM drivers WHERE id = ?');
        $driverStmt->execute([(int) $id]);
        $driver = $driverStmt->fetch(PDO::FETCH_ASSOC) ?: null;

        if (!$driver) {
            echo json_encode(['success' => false, 'error' => 'Водитель не найден.']);
            exit;
        }

        // Delete permission: company_owner always, logist only created_by
        $role = $_SESSION['role_code'] ?? '';
        if ($role === 'logist') {
            $userId = (int)$_SESSION['user_id'];
            if ((int)$driver['created_by_user_id'] !== $userId) {
                echo json_encode(['success' => false, 'error' => 'У вас нет права архивировать эту запись.']);
                exit;
            }
        }

        // Check crews via driver_vehicle_blocks
        try {
            $localPdo->query("SELECT 1 FROM crews LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $migrationSql = file_get_contents(base_path('database/migrations-local/006_create_company_crews.sql'));
            $localPdo->exec($migrationSql);
        }

        try {
            $localPdo->query("SELECT 1 FROM driver_vehicle_blocks LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $migrationSql = file_get_contents(base_path('database/migrations-local/018_create_driver_vehicle_blocks.sql'));
            $localPdo->exec($migrationSql);
        }

        $crewCheck = $localPdo->prepare(
            'SELECT COUNT(*) FROM crews c
             JOIN driver_vehicle_blocks dvb ON dvb.id = c.driver_vehicle_block_id
             WHERE dvb.driver_id = ?'
        );
        $crewCheck->execute([(int) $id]);
        if ($crewCheck->fetchColumn() > 0) {
            echo json_encode(['success' => false, 'error' => 'Водитель участвует в экипажах. Сначала удалите экипажи.']);
            exit;
        }

        $driverId = (int) $id;
        $storageBase = storage_path('companies/' . $companyId . '/documents/');
        $driverDocDir = $storageBase . 'driver/' . $driverId;

        $docsStmt = $localPdo->prepare(
            "SELECT stored_name
             FROM documents
             WHERE entity_type = 'driver' AND entity_id = ?"
        );
        $docsStmt->execute([$driverId]);
        $driverDocs = $docsStmt->fetchAll(PDO::FETCH_ASSOC);

        $filePathsToDelete = [];
        $realBase = realpath($storageBase);
        foreach ($driverDocs as $doc) {
            $storedName = trim((string) ($doc['stored_name'] ?? ''));
            if ($storedName === '') {
                continue;
            }

            $filePath = $driverDocDir . DIRECTORY_SEPARATOR . $storedName;
            $realFile = realpath($filePath);
            if ($realBase !== false && $realFile !== false && str_starts_with($realFile, $realBase)) {
                $filePathsToDelete[] = $realFile;
            }
        }

        $localPdo->beginTransaction();
        $localPdo->prepare("DELETE FROM entity_access_grants WHERE entity_type = 'driver' AND entity_id = ?")->execute([$driverId]);
        $localPdo->prepare("DELETE FROM documents WHERE entity_type = 'driver' AND entity_id = ?")->execute([$driverId]);
        $localPdo->prepare("DELETE FROM driver_phones WHERE driver_id = ?")->execute([$driverId]);
        $localPdo->prepare("DELETE FROM drivers WHERE id = ?")->execute([$driverId]);
        $localPdo->commit();

        foreach (array_unique($filePathsToDelete) as $storedPath) {
            if (is_string($storedPath) && $storedPath !== '' && is_file($storedPath)) {
                @unlink($storedPath);
            }
        }

        if (is_dir($driverDocDir)) {
            $items = @scandir($driverDocDir) ?: [];
            foreach ($items as $item) {
                if ($item === '.' || $item === '..') {
                    continue;
                }
                $path = $driverDocDir . DIRECTORY_SEPARATOR . $item;
                if (is_file($path)) {
                    @unlink($path);
                }
            }
            @rmdir($driverDocDir);
        }

        echo json_encode(['success' => true]);
        exit;

    } catch (\Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Ошибка: ' . $e->getMessage()]);
        exit;
    }
});

// --- Driver Phones CRUD ---

$router->post('/company/drivers/{driver_id}/phones/create', function ($driver_id) use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);
    $driver_id = (int)$driver_id;
    $companyId = (int)(getSessionCompanyId() ?? 0);
    $redirect = '/company/drivers/' . $driver_id;

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

        $dStmt = $localPdo->prepare('SELECT * FROM drivers WHERE id = ?');
        $dStmt->execute([$driver_id]);
        $driver = $dStmt->fetch(PDO::FETCH_ASSOC);
        if (!$driver) { header('Location: /company/drivers'); exit; }

        // Access check
        $isLogist = ($_SESSION['role_code'] ?? '') === 'logist';
        if ($isLogist) {
            $userId = (int)$_SESSION['user_id'];
            $hasGrant = false;
            $gc = $localPdo->prepare("SELECT access_level FROM entity_access_grants WHERE entity_type = 'driver' AND entity_id = ? AND granted_to_user_id = ? AND revoked_at IS NULL LIMIT 1");
            $gc->execute([$driver_id, $userId]);
            $gr = $gc->fetch(PDO::FETCH_ASSOC);
            $hasGrant = ($gr && $gr['access_level'] === 'edit');
            if ((int)$driver['created_by_user_id'] !== $userId && !$hasGrant) { header('Location: ' . $redirect); exit; }
        }

        $phone = trim($_POST['phone'] ?? '');
        $comment = trim($_POST['comment'] ?? '');

        $cntStmt = $localPdo->prepare('SELECT COUNT(*) FROM driver_phones WHERE driver_id = ?');
        $cntStmt->execute([$driver_id]);
        $isFirst = ($cntStmt->fetchColumn() == 0);
        $isMain = $isFirst ? 1 : 0;

        $insert = $localPdo->prepare(
            'INSERT INTO driver_phones (driver_id, phone, is_main, comment, created_by_user_id, created_by_role) VALUES (?, ?, ?, ?, ?, ?)'
        );
        $insert->execute([$driver_id, $phone ?: null, $isMain, $comment ?: null, (int)$_SESSION['user_id'], $_SESSION['role_code'] ?? null]);
    } catch (\Exception $e) {}

    header('Location: ' . $redirect);
    exit;
});

$router->post('/company/drivers/{driver_id}/phones/{phone_id}/edit', function ($driver_id, $phone_id) use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);
    $driver_id = (int)$driver_id; $phone_id = (int)$phone_id;
    $companyId = (int)(getSessionCompanyId() ?? 0);
    $redirect = '/company/drivers/' . $driver_id;

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

        $dStmt = $localPdo->prepare('SELECT * FROM drivers WHERE id = ?');
        $dStmt->execute([$driver_id]);
        $driver = $dStmt->fetch(PDO::FETCH_ASSOC);
        if (!$driver) { header('Location: /company/drivers'); exit; }

        // Access check
        $isLogist = ($_SESSION['role_code'] ?? '') === 'logist';
        if ($isLogist) {
            $userId = (int)$_SESSION['user_id'];
            $hasGrant = false;
            $gc = $localPdo->prepare("SELECT access_level FROM entity_access_grants WHERE entity_type = 'driver' AND entity_id = ? AND granted_to_user_id = ? AND revoked_at IS NULL LIMIT 1");
            $gc->execute([$driver_id, $userId]);
            $gr = $gc->fetch(PDO::FETCH_ASSOC);
            $hasGrant = ($gr && $gr['access_level'] === 'edit');
            if ((int)$driver['created_by_user_id'] !== $userId && !$hasGrant) { header('Location: ' . $redirect); exit; }
        }

        $phone = trim($_POST['phone'] ?? '');
        $comment = trim($_POST['comment'] ?? '');

        $update = $localPdo->prepare(
            'UPDATE driver_phones SET phone = ?, comment = ?, updated_by_user_id = ?, updated_by_role = ? WHERE id = ? AND driver_id = ?'
        );
        $update->execute([$phone ?: null, $comment ?: null, (int)$_SESSION['user_id'], $_SESSION['role_code'] ?? null, $phone_id, $driver_id]);
    } catch (\Exception $e) {}

    header('Location: ' . $redirect);
    exit;
});

$router->post('/company/drivers/{driver_id}/phones/{phone_id}/delete', function ($driver_id, $phone_id) use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);
    $driver_id = (int)$driver_id; $phone_id = (int)$phone_id;
    $companyId = (int)(getSessionCompanyId() ?? 0);
    $redirect = '/company/drivers/' . $driver_id;

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

        $dStmt = $localPdo->prepare('SELECT * FROM drivers WHERE id = ?');
        $dStmt->execute([$driver_id]);
        $driver = $dStmt->fetch(PDO::FETCH_ASSOC);
        if (!$driver) { header('Location: /company/drivers'); exit; }

        // Access check
        $isLogist = ($_SESSION['role_code'] ?? '') === 'logist';
        if ($isLogist) {
            $userId = (int)$_SESSION['user_id'];
            $hasGrant = false;
            $gc = $localPdo->prepare("SELECT access_level FROM entity_access_grants WHERE entity_type = 'driver' AND entity_id = ? AND granted_to_user_id = ? AND revoked_at IS NULL LIMIT 1");
            $gc->execute([$driver_id, $userId]);
            $gr = $gc->fetch(PDO::FETCH_ASSOC);
            $hasGrant = ($gr && $gr['access_level'] === 'edit');
            if ((int)$driver['created_by_user_id'] !== $userId && !$hasGrant) { header('Location: ' . $redirect); exit; }
        }

        $phoneStmt = $localPdo->prepare('SELECT is_main FROM driver_phones WHERE id = ? AND driver_id = ?');
        $phoneStmt->execute([$phone_id, $driver_id]);
        $phoneData = $phoneStmt->fetch(PDO::FETCH_ASSOC);

        $delStmt = $localPdo->prepare('DELETE FROM driver_phones WHERE id = ? AND driver_id = ?');
        $delStmt->execute([$phone_id, $driver_id]);

        if ($phoneData && $phoneData['is_main']) {
            $first = $localPdo->prepare('SELECT id FROM driver_phones WHERE driver_id = ? ORDER BY id ASC LIMIT 1');
            $first->execute([$driver_id]);
            $firstRow = $first->fetch(PDO::FETCH_ASSOC);
            if ($firstRow) {
                $localPdo->prepare('UPDATE driver_phones SET is_main = 1 WHERE id = ?')->execute([$firstRow['id']]);
            }
        }
    } catch (\Exception $e) {}

    header('Location: ' . $redirect);
    exit;
});

$router->post('/company/drivers/{driver_id}/phones/{phone_id}/set-main', function ($driver_id, $phone_id) use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);
    $driver_id = (int)$driver_id; $phone_id = (int)$phone_id;
    $companyId = (int)(getSessionCompanyId() ?? 0);
    $redirect = '/company/drivers/' . $driver_id;

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

        $dStmt = $localPdo->prepare('SELECT * FROM drivers WHERE id = ?');
        $dStmt->execute([$driver_id]);
        $driver = $dStmt->fetch(PDO::FETCH_ASSOC);
        if (!$driver) { header('Location: /company/drivers'); exit; }

        // Access check
        $isLogist = ($_SESSION['role_code'] ?? '') === 'logist';
        if ($isLogist) {
            $userId = (int)$_SESSION['user_id'];
            $hasGrant = false;
            $gc = $localPdo->prepare("SELECT access_level FROM entity_access_grants WHERE entity_type = 'driver' AND entity_id = ? AND granted_to_user_id = ? AND revoked_at IS NULL LIMIT 1");
            $gc->execute([$driver_id, $userId]);
            $gr = $gc->fetch(PDO::FETCH_ASSOC);
            $hasGrant = ($gr && $gr['access_level'] === 'edit');
            if ((int)$driver['created_by_user_id'] !== $userId && !$hasGrant) { header('Location: ' . $redirect); exit; }
        }

        $localPdo->prepare('UPDATE driver_phones SET is_main = 0 WHERE driver_id = ?')->execute([$driver_id]);
        $localPdo->prepare('UPDATE driver_phones SET is_main = 1 WHERE id = ? AND driver_id = ?')->execute([$phone_id, $driver_id]);
    } catch (\Exception $e) {}

    header('Location: ' . $redirect);
    exit;
});
