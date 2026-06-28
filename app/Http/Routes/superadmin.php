<?php

$router->get('/superadmin/companies', function () use ($config, $db) {
    requireRole('superadmin');
    $pageTitle = 'Реестр компаний';
    $pageContext = 'Реестр компаний';

    $search = trim($_GET['search'] ?? '');
    $filterStatus = trim($_GET['status'] ?? '');

    try {
        $pdo = $db->connection();

        $sql = 'SELECT c.*,
                       cu.full_name as owner_name,
                       (SELECT COUNT(*) FROM company_users WHERE company_id = c.id) as user_count
                FROM companies c
                LEFT JOIN company_users cu ON c.id = cu.company_id AND cu.role = :owner_role';

        $conditions = [];
        $params = [':owner_role' => 'company_owner'];

        if ($search !== '') {
            $conditions[] = '(c.name LIKE :search OR c.inn LIKE :search2)';
            $params[':search'] = '%' . $search . '%';
            $params[':search2'] = '%' . $search . '%';
        }

        if ($filterStatus !== '') {
            $conditions[] = 'c.status = :status';
            $params[':status'] = $filterStatus;
        }

        if (!empty($conditions)) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }

        $sql .= ' ORDER BY c.created_at DESC';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $companies = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $dbError = null;
    } catch (\Exception $e) {
        $companies = [];
        $dbError = 'Не удалось загрузить список компаний. Попробуйте позже.';
    }

    ob_start();
    require base_path('app/View/pages/superadmin_companies.php');
    $content = ob_get_clean();

    require base_path('app/View/layouts/main.php');
});

// ============================================================
// SUPERADMIN: Company create
// ============================================================

$router->get('/superadmin/companies/create', function () use ($config) {
    requireRole('superadmin');
    $pageTitle = 'Создать экспедитора';
    $pageContext = 'Реестр компаний';
    $errors = [];
    $old = [];
    $formError = null;

    ob_start();
    require base_path('app/View/pages/superadmin_companies_create.php');
    $content = ob_get_clean();

    require base_path('app/View/layouts/main.php');
});

$router->post('/superadmin/companies/create', function () use ($config, $db) {
    requireRole('superadmin');
    $pageTitle = 'Создать экспедитора';
    $pageContext = 'Реестр компаний';
    $errors = [];
    $old = $_POST;
    $formError = null;

    $name = trim($_POST['name'] ?? '');
    $inn  = trim($_POST['inn'] ?? '');

    if ($name === '') {
        $errors['name'] = 'Обязательное поле';
    }

    if ($inn === '') {
        $errors['inn'] = 'Обязательное поле';
    }

    if (!empty($errors)) {
        ob_start();
        require base_path('app/View/pages/superadmin_companies_create.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
        return;
    }

    try {
        $pdo = $db->connection();

        $key = 'company_' . uniqid();

        $insert = $pdo->prepare(
            'INSERT INTO companies (`key`, name, inn, kpp, ogrn, legal_address, physical_address,
                director_position, director_full_name, comments, status)
             VALUES (:key, :name, :inn, :kpp, :ogrn, :legal_address, :physical_address,
                :director_position, :director_full_name, :comments, :status)'
        );

        $insert->execute([
            ':key'              => $key,
            ':name'             => $name,
            ':inn'              => $inn,
            ':kpp'              => $_POST['kpp'] ?? null,
            ':ogrn'             => $_POST['ogrn'] ?? null,
            ':legal_address'    => $_POST['legal_address'] ?? null,
            ':physical_address' => $_POST['physical_address'] ?? null,
            ':director_position' => $_POST['director_position'] ?? null,
            ':director_full_name' => $_POST['director_full_name'] ?? null,
            ':comments'         => $_POST['comments'] ?? null,
            ':status'           => 'provisioning',
        ]);

        $companyId = (int) $pdo->lastInsertId();
        $dbName = 'erp_company_' . $companyId;
        $storageDir = storage_path('companies/' . $companyId);

        $newKey = 'company_' . $companyId;

        try {
            $dbConfig = $config['database'];
            $dbConfig['database'] = '';
            $sysDb = new \App\Core\Database($dbConfig);
            $sysPdo = $sysDb->connection();
            $sysPdo->exec('CREATE DATABASE IF NOT EXISTS `' . $dbName . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        } catch (\Exception $e) {
            $pdo->prepare('UPDATE companies SET status = :status, error_message = :msg WHERE id = :id')
                ->execute([
                    ':status' => 'error',
                    ':msg'    => 'Не удалось создать базу данных: ' . $e->getMessage(),
                    ':id'     => $companyId,
                ]);

            $formError = 'Ошибка создания инфраструктуры. База данных не создана. Запись сохранена со статусом "Ошибка".';

            ob_start();
            require base_path('app/View/pages/superadmin_companies_create.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        try {
            if (!is_dir($storageDir)) {
                mkdir($storageDir, 0755, true);
            }
        } catch (\Exception $e) {
            $pdo->prepare('UPDATE companies SET status = :status, error_message = :msg, db_identifier = :db WHERE id = :id')
                ->execute([
                    ':status' => 'error',
                    ':msg'    => 'БД создана, но не удалось создать storage-папку: ' . $e->getMessage(),
                    ':db'     => $dbName,
                    ':id'     => $companyId,
                ]);

            $formError = 'База данных создана, но не удалось создать storage-папку. Запись сохранена со статусом "Ошибка".';

            ob_start();
            require base_path('app/View/pages/superadmin_companies_create.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $pdo->prepare('UPDATE companies SET status = :status, db_identifier = :db, storage_path = :storage, `key` = :new_key WHERE id = :id')
            ->execute([
                ':status'  => 'active',
                ':db'      => $dbName,
                ':storage' => 'storage/companies/' . $companyId . '/',
                ':new_key' => $newKey,
                ':id'      => $companyId,
            ]);

        header('Location: /superadmin/companies');
        exit;
    } catch (\Exception $e) {
        $formError = 'Не удалось создать экспедитора: ' . $e->getMessage();

        ob_start();
        require base_path('app/View/pages/superadmin_companies_create.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
    }
});

$router->get('/superadmin/companies/{id}', function ($id) use ($config, $db) {
    requireRole('superadmin');
    $pageTitle = 'Карточка компании';
    $pageContext = 'Реестр компаний';

    try {
        $pdo = $db->connection();

        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([(int) $id]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company) {
            $company = null;
            $owner = null;
            $dbError = null;

            ob_start();
            require base_path('app/View/pages/superadmin_company_view.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $pageTitle = 'Компания: ' . $company['name'];

        $ownerStmt = $pdo->prepare(
            "SELECT * FROM company_users WHERE company_id = ? AND role = 'company_owner'"
        );
        $ownerStmt->execute([(int) $id]);
        $owner = $ownerStmt->fetch(PDO::FETCH_ASSOC) ?: null;

        $userStats = ['total' => 0, 'active' => 0, 'blocked' => 0, 'owner_count' => 0, 'logist_count' => 0];
        $ownerCountStmt = $pdo->prepare(
            "SELECT COUNT(*) as owner_total,
                    SUM(CASE WHEN status='active' THEN 1 ELSE 0 END) as owner_active,
                    SUM(CASE WHEN status='blocked' THEN 1 ELSE 0 END) as owner_blocked
             FROM company_users WHERE company_id = ?"
        );
        $ownerCountStmt->execute([(int)$id]);
        $ownerCounts = $ownerCountStmt->fetch(PDO::FETCH_ASSOC);
        $userStats['owner_count'] = (int)($ownerCounts['owner_total'] ?? 0);
        $userStats['total'] += $userStats['owner_count'];
        $userStats['active'] += (int)($ownerCounts['owner_active'] ?? 0);
        $userStats['blocked'] += (int)($ownerCounts['owner_blocked'] ?? 0);

        $dirs = [
            'clients_total' => 0, 'clients_active' => 0, 'clients_archived' => 0,
            'contractors_total' => 0, 'contractors_active' => 0, 'contractors_archived' => 0,
            'drivers_total' => 0, 'drivers_active' => 0, 'drivers_archived' => 0,
            'vehicle_units_total' => 0, 'vehicle_units_active' => 0, 'vehicle_units_archived' => 0,
            'vehicle_sets_total' => 0, 'vehicle_sets_active' => 0, 'vehicle_sets_archived' => 0,
            'driver_vehicle_blocks_total' => 0, 'driver_vehicle_blocks_active' => 0, 'driver_vehicle_blocks_archived' => 0,
            'crews_total' => 0, 'crews_active' => 0, 'crews_archived' => 0,
        ];
        $docStats = ['total' => 0, 'active' => 0];
        $accessStats = ['total' => 0];
        $localDbExists = false;
        $storageExists = false;

        if (!empty($company['db_identifier'])) {
            try {
                $localDbConfig = $config['database'];
                $localDbConfig['database'] = $company['db_identifier'];
                $localDb = new \App\Core\Database($localDbConfig);
                $localPdo = $localDb->connection();
                applyLocalMigrations($localPdo);
                $localDbExists = true;

                $logistCountStmt = $localPdo->prepare(
                    "SELECT COUNT(*) as logist_total,
                            SUM(CASE WHEN status='active' THEN 1 ELSE 0 END) as logist_active,
                            SUM(CASE WHEN status='blocked' THEN 1 ELSE 0 END) as logist_blocked
                     FROM users WHERE role_code IN ('logist', 'senior_logist')"
                );
                $logistCountStmt->execute();
                $logistCounts = $logistCountStmt->fetch(PDO::FETCH_ASSOC);
                $userStats['logist_count'] = (int)($logistCounts['logist_total'] ?? 0);
                $userStats['total'] += $userStats['logist_count'];
                $userStats['active'] += (int)($logistCounts['logist_active'] ?? 0);
                $userStats['blocked'] += (int)($logistCounts['logist_blocked'] ?? 0);

                $dirTables = ['clients', 'contractors', 'drivers', 'vehicle_units', 'vehicle_sets', 'driver_vehicle_blocks', 'crews'];
                foreach ($dirTables as $table) {
                    $dirStmt = $localPdo->prepare(
                        "SELECT COUNT(*) as total,
                                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
                                SUM(CASE WHEN status = 'archived' THEN 1 ELSE 0 END) as archived
                         FROM `{$table}`"
                    );
                    $dirStmt->execute();
                    $row = $dirStmt->fetch(PDO::FETCH_ASSOC);
                    $dirs[$table . '_total'] = (int)($row['total'] ?? 0);
                    $dirs[$table . '_active'] = (int)($row['active'] ?? 0);
                    $dirs[$table . '_archived'] = (int)($row['archived'] ?? 0);
                }

                $docCountStmt = $localPdo->prepare(
                    "SELECT COUNT(*) as total,
                            SUM(CASE WHEN deleted_at IS NULL THEN 1 ELSE 0 END) as active
                     FROM documents"
                );
                $docCountStmt->execute();
                $docRow = $docCountStmt->fetch(PDO::FETCH_ASSOC);
                $docStats['total'] = (int)($docRow['total'] ?? 0);
                $docStats['active'] = (int)($docRow['active'] ?? 0);

                $accessCountStmt = $localPdo->prepare("SELECT COUNT(*) as total FROM entity_access_grants");
                $accessCountStmt->execute();
                $accessStats['total'] = (int)$accessCountStmt->fetchColumn();
            } catch (\Exception $e) {
            }
        }

        if (!empty($company['storage_path'])) {
            $storageExists = is_dir($company['storage_path']);
        }

        $dbError = null;

        ob_start();
        require base_path('app/View/pages/superadmin_company_view.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
    } catch (\Exception $e) {
        $company = null;
        $owner = null;
        $dbError = 'Не удалось загрузить компанию: ' . $e->getMessage();

        ob_start();
        require base_path('app/View/pages/superadmin_company_view.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
    }
});

$router->get('/superadmin/companies/{id}/edit', function ($id) use ($config, $db) {
    requireRole('superadmin');
    $pageTitle = 'Редактировать компанию';
    $pageContext = 'Реестр компаний';

    try {
        $pdo = $db->connection();

        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([(int) $id]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company) {
            $company = null;
            $errors = [];
            $old = [];
            $formError = 'Компания не найдена';
            $owner = null;

            ob_start();
            require base_path('app/View/pages/superadmin_company_edit.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $errors = [];
        $old = $company;
        $formError = null;

        $owner = null; // Director now stored in companies table, not company_users

        ob_start();
        require base_path('app/View/pages/superadmin_company_edit.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
    } catch (\Exception $e) {
        $company = null;
        $contacts = [];
        $errors = [];
        $old = ['contacts' => contractorFormDefaultContacts()];
        $formError = 'Не удалось загрузить компанию: ' . $e->getMessage();

        ob_start();
        require base_path('app/View/pages/superadmin_company_edit.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
    }
});

$router->post('/superadmin/companies/{id}/edit', function ($id) use ($config, $db) {
    requireRole('superadmin');
    $pageTitle = 'Редактировать компанию';
    $pageContext = 'Реестр компаний';

    try {
        $pdo = $db->connection();

        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([(int) $id]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company) {
            $company = null;
            $errors = [];
            $old = $_POST;
            $formError = 'Компания не найдена';
            $owner = null;

            ob_start();
            require base_path('app/View/pages/superadmin_company_edit.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $errors = [];
        $old = $_POST;
        $old['contacts'] = $_POST['contacts'] ?? ($contacts !== [] ? $contacts : clientFormDefaultContacts());
        $formError = null;
        $contactPayload = ContractorContactService::normalizeSubmittedContacts($_POST['contacts'] ?? []);
        $submittedContacts = $contactPayload['contacts'];
        if (!empty($contactPayload['errors'])) {
            $errors['contacts'] = $contactPayload['errors'];
        }

        $owner = null;

        $name = trim($_POST['name'] ?? '');
        $inn  = trim($_POST['inn'] ?? '');

        if ($name === '') {
            $errors['name'] = 'Обязательное поле';
        }

        if ($inn === '') {
            $errors['inn'] = 'Обязательное поле';
        } else {
            $dupStmt = $pdo->prepare('SELECT COUNT(*) FROM companies WHERE inn = ? AND id != ?');
            $dupStmt->execute([$inn, (int) $id]);
            if ($dupStmt->fetchColumn() > 0) {
                $errors['inn'] = 'ИНН уже используется';
            }
        }

        if (!empty($errors)) {
            ob_start();
            require base_path('app/View/pages/superadmin_company_edit.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $update = $pdo->prepare(
            'UPDATE companies SET
                name = :name,
                inn = :inn,
                kpp = :kpp,
                ogrn = :ogrn,
                legal_address = :legal_address,
                physical_address = :physical_address,
                director_position = :director_position,
                director_full_name = :director_full_name,
                status = :status,
                comments = :comments
             WHERE id = :id'
        );

        $update->execute([
            ':name'             => $name,
            ':inn'              => $inn,
            ':kpp'              => $_POST['kpp'] ?? null,
            ':ogrn'             => $_POST['ogrn'] ?? null,
            ':legal_address'    => $_POST['legal_address'] ?? null,
            ':physical_address' => $_POST['physical_address'] ?? null,
            ':director_position' => $_POST['director_position'] ?? null,
            ':director_full_name' => $_POST['director_full_name'] ?? null,
            ':status'           => $_POST['status'] ?? $company['status'],
            ':comments'         => $_POST['comments'] ?? null,
            ':id'               => (int) $id,
        ]);

        header('Location: /superadmin/companies/' . $id);
        exit;
    } catch (\Exception $e) {
        $company = $company ?? null;
        $owner = $owner ?? null;
        $errors = [];
        $old = $_POST;
        $formError = 'Ошибка сохранения: ' . $e->getMessage();

        ob_start();
        require base_path('app/View/pages/superadmin_company_edit.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
    }
});

// ============================================================
// SUPERADMIN: Create company owner
// ============================================================

$router->get('/superadmin/companies/{id}/create-owner', function ($id) use ($config, $db) {
    requireRole('superadmin');
    $pageTitle = 'Создать Руководителя';
    $pageContext = 'Реестр компаний';

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([(int) $id]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        $ownerExists = false;
        $existingOwner = null;
        if ($company) {
            $ownerStmt = $pdo->prepare("SELECT * FROM company_users WHERE company_id = ? AND role = 'company_owner' LIMIT 1");
            $ownerStmt->execute([(int) $id]);
            $existingOwner = $ownerStmt->fetch(PDO::FETCH_ASSOC) ?: null;
            $ownerExists = $existingOwner !== null;
        }

        $success = false;
        $errors = [];
        $old = [];
        $formError = null;
        $generatedPassword = generatePassword();
        $createdOwner = null;
        $tempPassword = null;
    } catch (\Exception $e) {
        $company = null;
        $ownerExists = false;
        $existingOwner = null;
        $success = false;
        $errors = [];
        $old = [];
        $formError = 'Ошибка загрузки данных: ' . $e->getMessage();
        $generatedPassword = null;
        $createdOwner = null;
        $tempPassword = null;
    }

    ob_start();
    require base_path('app/View/pages/superadmin_company_owner_create.php');
    $content = ob_get_clean();
    require base_path('app/View/layouts/main.php');
});

$router->post('/superadmin/companies/{id}/create-owner', function ($id) use ($config, $db) {
    requireRole('superadmin');
    $pageTitle = 'Создать Руководителя';
    $pageContext = 'Реестр компаний';

    $errors = [];
    $old = $_POST;
    $formError = null;
    $success = false;
    $generatedPassword = null;
    $createdOwner = null;
    $tempPassword = null;
    $ownerExists = false;
    $existingOwner = null;

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([(int) $id]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        if (!$company) {
            ob_start();
            require base_path('app/View/pages/superadmin_company_owner_create.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $ownerStmt = $pdo->prepare("SELECT * FROM company_users WHERE company_id = ? AND role = 'company_owner' LIMIT 1");
        $ownerStmt->execute([(int) $id]);
        $existingOwner = $ownerStmt->fetch(PDO::FETCH_ASSOC) ?: null;
        $ownerExists = $existingOwner !== null;

        if ($ownerExists) {
            ob_start();
            require base_path('app/View/pages/superadmin_company_owner_create.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $fullName = trim($_POST['full_name'] ?? '');
        $login = trim($_POST['login'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $position = trim($_POST['position'] ?? '');
        $comments = trim($_POST['comments'] ?? '');

        if ($fullName === '') {
            $errors['full_name'] = 'Обязательное поле';
        }

        if ($login === '') {
            $errors['login'] = 'Обязательное поле';
        } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $login)) {
            $errors['login'] = 'Только латинские буквы, цифры и подчёркивание';
        } else {
            $dupStmt = $pdo->prepare('SELECT COUNT(*) FROM company_users WHERE login = ?');
            $dupStmt->execute([$login]);
            if ((int) $dupStmt->fetchColumn() > 0) {
                $errors['login'] = 'Такой логин уже используется';
            }
        }

        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Некорректный email';
        }

        if ($password === '') {
            $password = generatePassword();
        }

        if (!empty($errors)) {
            $generatedPassword = generatePassword();
            ob_start();
            require base_path('app/View/pages/superadmin_company_owner_create.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $insert = $pdo->prepare(
            'INSERT INTO company_users (company_id, full_name, login, email, phone, position, password_hash, role, status, comments)
             VALUES (:company_id, :full_name, :login, :email, :phone, :position, :password_hash, :role, :status, :comments)'
        );
        $insert->execute([
            ':company_id'    => (int) $id,
            ':full_name'     => $fullName,
            ':login'         => $login,
            ':email'         => $email !== '' ? $email : null,
            ':phone'         => $phone !== '' ? $phone : null,
            ':position'      => $position !== '' ? $position : null,
            ':password_hash' => password_hash($password, PASSWORD_BCRYPT),
            ':role'          => 'company_owner',
            ':status'        => 'active',
            ':comments'      => $comments !== '' ? $comments : null,
        ]);

        $createdOwner = [
            'full_name' => $fullName,
            'login'     => $login,
        ];
        $tempPassword = $password;
        $success = true;
    } catch (\Exception $e) {
        $company = $company ?? null;
        $formError = 'Ошибка создания Руководителя: ' . $e->getMessage();
    }

    ob_start();
    require base_path('app/View/pages/superadmin_company_owner_create.php');
    $content = ob_get_clean();
    require base_path('app/View/layouts/main.php');
});

$router->get('/superadmin/companies/{id}/owner', function ($id) use ($config, $db) {
    requireRole('superadmin');
    $pageTitle = 'Руководитель';
    $pageContext = 'Реестр компаний';

    try {
        $pdo = $db->connection();

        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([(int) $id]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company) {
            $company = null;
            $owner = null;
            $dbError = null;
            $passwordReset = false;
            $newPassword = null;

            ob_start();
            require base_path('app/View/pages/superadmin_company_owner_view.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $ownerStmt = $pdo->prepare(
            "SELECT * FROM company_users WHERE company_id = ? AND role = 'company_owner'"
        );
        $ownerStmt->execute([(int) $id]);
        $owner = $ownerStmt->fetch(PDO::FETCH_ASSOC) ?: null;

        $pageTitle = $owner ? 'Руководитель: ' . $owner['full_name'] : 'Руководитель';
        $dbError = null;
        $passwordReset = false;
        $newPassword = null;

        ob_start();
        require base_path('app/View/pages/superadmin_company_owner_view.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
    } catch (\Exception $e) {
        $company = null;
        $owner = null;
        $dbError = 'Не удалось загрузить данные: ' . $e->getMessage();
        $passwordReset = false;
        $newPassword = null;

        ob_start();
        require base_path('app/View/pages/superadmin_company_owner_view.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
    }
});

$router->get('/superadmin/companies/{id}/owner/edit', function ($id) use ($config, $db) {
    requireRole('superadmin');
    $pageTitle = 'Редактировать Руководителя';
    $pageContext = 'Реестр компаний';

    try {
        $pdo = $db->connection();

        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([(int) $id]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company) {
            $company = null;
            $owner = null;
            $errors = [];
            $old = [];
            $formError = 'Компания не найдена';

            ob_start();
            require base_path('app/View/pages/superadmin_company_owner_edit.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $ownerStmt = $pdo->prepare(
            "SELECT * FROM company_users WHERE company_id = ? AND role = 'company_owner'"
        );
        $ownerStmt->execute([(int) $id]);
        $owner = $ownerStmt->fetch(PDO::FETCH_ASSOC) ?: null;

        if (!$owner) {
            $errors = [];
            $old = [];
            $formError = 'Руководитель не создан';

            ob_start();
            require base_path('app/View/pages/superadmin_company_owner_edit.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $errors = [];
        $old = $owner;
        $formError = null;

        ob_start();
        require base_path('app/View/pages/superadmin_company_owner_edit.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
    } catch (\Exception $e) {
        $company = null;
        $owner = null;
        $errors = [];
        $old = [];
        $formError = 'Не удалось загрузить данные: ' . $e->getMessage();

        ob_start();
        require base_path('app/View/pages/superadmin_company_owner_edit.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
    }
});

$router->post('/superadmin/companies/{id}/owner/edit', function ($id) use ($config, $db) {
    requireRole('superadmin');
    $pageTitle = 'Редактировать Руководителя';
    $pageContext = 'Реестр компаний';

    try {
        $pdo = $db->connection();

        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([(int) $id]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company) {
            $company = null;
            $owner = null;
            $errors = [];
            $old = $_POST;
            $formError = 'Компания не найдена';

            ob_start();
            require base_path('app/View/pages/superadmin_company_owner_edit.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $ownerStmt = $pdo->prepare(
            "SELECT * FROM company_users WHERE company_id = ? AND role = 'company_owner'"
        );
        $ownerStmt->execute([(int) $id]);
        $owner = $ownerStmt->fetch(PDO::FETCH_ASSOC) ?: null;

        if (!$owner) {
            $errors = [];
            $old = $_POST;
            $formError = 'Руководитель не создан';

            ob_start();
            require base_path('app/View/pages/superadmin_company_owner_edit.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $errors = [];
        $old = $_POST;
        $formError = null;

        // Prevent self-deactivation
        if ((int)$owner['id'] === (int)$_SESSION['user_id']) {
            $newStatus = $_POST['status'] ?? $owner['status'];
            if ($newStatus !== 'active') {
                $errors['status'] = 'Нельзя деактивировать самого себя';
            }
        }

        // Prevent deactivating last active owner
        $newStatus = $_POST['status'] ?? $owner['status'];
        if ($newStatus !== 'active' && $owner['status'] === 'active') {
            $activeOwnerCount = $pdo->prepare(
                "SELECT COUNT(*) FROM company_users WHERE company_id = ? AND role = 'company_owner' AND status = 'active' AND id != ?"
            );
            $activeOwnerCount->execute([(int)$id, (int)$owner['id']]);
            if ((int)$activeOwnerCount->fetchColumn() === 0) {
                $errors['status'] = 'Нельзя деактивировать последнего активного Руководителя';
            }
        }

        $fullName = trim($_POST['full_name'] ?? '');
        $login = trim($_POST['login'] ?? '');

        if ($fullName === '') {
            $errors['full_name'] = 'Обязательное поле';
        }

        if ($login === '') {
            $errors['login'] = 'Обязательное поле';
        } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $login)) {
            $errors['login'] = 'Только латинские буквы, цифры и подчёркивание';
        } else {
            $dupStmt = $pdo->prepare('SELECT COUNT(*) FROM company_users WHERE login = ? AND id != ?');
            $dupStmt->execute([$login, (int) $owner['id']]);
            if ($dupStmt->fetchColumn() > 0) {
                $errors['login'] = 'Логин уже используется';
            }
        }

        $email = trim($_POST['email'] ?? '');
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Некорректный email';
        }

        if (!empty($errors)) {
            ob_start();
            require base_path('app/View/pages/superadmin_company_owner_edit.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $update = $pdo->prepare(
            'UPDATE company_users SET
                full_name = :full_name,
                login = :login,
                email = :email,
                phone = :phone,
                position = :position,
                status = :status,
                comments = :comments
             WHERE id = :id'
        );

        $update->execute([
            ':full_name' => $fullName,
            ':login'     => $login,
            ':email'     => $email !== '' ? $email : null,
            ':phone'     => trim($_POST['phone'] ?? '') ?: null,
            ':position'  => trim($_POST['position'] ?? '') ?: null,
            ':status'    => $_POST['status'] ?? $owner['status'],
            ':comments'  => trim($_POST['comments'] ?? '') ?: null,
            ':id'        => (int) $owner['id'],
        ]);

        header('Location: /superadmin/companies/' . $id . '/owner?success=1');
        exit;
    } catch (\Exception $e) {
        $company = $company ?? null;
        $owner = $owner ?? null;
        $errors = [];
        $old = $_POST;
        $formError = 'Ошибка сохранения: ' . $e->getMessage();

        ob_start();
        require base_path('app/View/pages/superadmin_company_owner_edit.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
    }
});

$router->post('/superadmin/companies/{id}/owner/reset-password', function ($id) use ($config, $db) {
    requireRole('superadmin');
    $pageTitle = 'Руководитель';
    $pageContext = 'Реестр компаний';

    try {
        $pdo = $db->connection();

        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([(int) $id]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company) {
            $company = null;
            $owner = null;
            $dbError = 'Компания не найдена';
            $passwordReset = false;
            $newPassword = null;

            ob_start();
            require base_path('app/View/pages/superadmin_company_owner_view.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $ownerStmt = $pdo->prepare(
            "SELECT * FROM company_users WHERE company_id = ? AND role = 'company_owner'"
        );
        $ownerStmt->execute([(int) $id]);
        $owner = $ownerStmt->fetch(PDO::FETCH_ASSOC) ?: null;

        if (!$owner) {
            $dbError = null;
            $passwordReset = false;
            $newPassword = null;

            ob_start();
            require base_path('app/View/pages/superadmin_company_owner_view.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $newPassword = generatePassword(10);
        $passwordHash = password_hash($newPassword, PASSWORD_BCRYPT);

        $update = $pdo->prepare('UPDATE company_users SET password_hash = ? WHERE id = ?');
        $update->execute([$passwordHash, (int) $owner['id']]);

        $passwordReset = true;
        $dbError = null;

        ob_start();
        require base_path('app/View/pages/superadmin_company_owner_view.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
    } catch (\Exception $e) {
        $company = $company ?? null;
        $owner = $owner ?? null;
        $dbError = 'Ошибка сброса пароля: ' . $e->getMessage();
        $passwordReset = false;
        $newPassword = null;

        ob_start();
        require base_path('app/View/pages/superadmin_company_owner_view.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
    }
});
