<?php

$router->get('/company/logists', function () use ($config, $db) {
    requireRole('company_owner');
    $pageTitle = 'Пользователи';
    $pageContext = 'Пользователи › Компания';

    $companyId = (int)(getSessionCompanyId() ?? 0);

    if ($companyId <= 0) {
        $company = null;
        $logists = [];
        $dbError = null;

        ob_start();
        require base_path('app/View/pages/company_logists.php');
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
            $logists = [];
            $dbError = null;

            ob_start();
            require base_path('app/View/pages/company_logists.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $pageContext = 'Пользователи › Компания: ' . $company['name'];

        if ($company['status'] !== 'active') {
            $logists = [];
            $dbError = null;

            ob_start();
            require base_path('app/View/pages/company_logists.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $dbIdentifier = $company['db_identifier'];

        // Создать локальную БД, если не существует
        try {
            $tempPdo = new PDO(
                sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $config['database']['host'] ?? '127.0.0.1', $config['database']['port'] ?? '3306'),
                $config['database']['username'] ?? 'root',
                $config['database']['password'] ?? ''
            );
            $tempPdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbIdentifier}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        } catch (\Exception $e) {
            // Игнорируем ошибку создания БД — основное подключение поймает проблему
        }
        // Release tempPdo to avoid any connection state interference
        $tempPdo = null;

        $localDbConfig = $config['database'];
        $localDbConfig['database'] = $dbIdentifier;
        $localDb = new \App\Core\Database($localDbConfig);
        $localPdo = $localDb->connection();
        applyLocalMigrations($localPdo);

        try {
            $localPdo->query("SELECT 1 FROM users LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $migrationSql = file_get_contents(base_path('database/migrations-local/001_create_company_users.sql'));
            $localPdo->exec($migrationSql);
        }

        try {
            $localPdo->query("SELECT created_by_user_id FROM users LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $localPdo->exec("ALTER TABLE users ADD COLUMN created_by_user_id INT UNSIGNED DEFAULT NULL, ADD COLUMN created_by_role VARCHAR(20) DEFAULT NULL");
        }

        try {
            $localPdo->query("SELECT 1 FROM entity_access_grants LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $migrationSql = file_get_contents(base_path('database/migrations-local/009_create_entity_access_grants.sql'));
            $localPdo->exec($migrationSql);
        }

        try {
            $localPdo->query("SELECT position FROM users LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $migrationSql = file_get_contents(base_path('database/migrations-local/010_add_position_to_users.sql'));
            if ($migrationSql !== false) {
                $localPdo->exec($migrationSql);
            }
        }

        $isLogist = ($_SESSION['role_code'] ?? '') === 'logist';
        if ($isLogist) {
            $userId = (int)$_SESSION['user_id'];
            $logistStmt = $localPdo->prepare(
                "SELECT * FROM users WHERE (created_by_user_id = ? OR id IN (SELECT entity_id FROM entity_access_grants WHERE entity_type = 'logist' AND granted_to_user_id = ? AND access_level = 'view')) ORDER BY created_at DESC"
            );
            $logistStmt->execute([$userId, $userId]);
            $logists = $logistStmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $logistStmt = $localPdo->query("SELECT * FROM users ORDER BY created_at DESC");
            $logists = $logistStmt->fetchAll(PDO::FETCH_ASSOC);
        }
        $dbError = null;
    } catch (\Exception $e) {
        $company = $company ?? null;
        $logists = [];
        $dbError = 'Не удалось подключиться к базе данных компании. Проверьте, что локальная БД создана.';
    }

    ob_start();
    require base_path('app/View/pages/company_logists.php');
    $content = ob_get_clean();
    require base_path('app/View/layouts/main.php');
});

$router->get('/company/logists/create', function () use ($config, $db) {
    requireRole('company_owner');
    $pageTitle = 'Создать пользователя';
    $pageContext = 'Пользователи › Компания';

    $companyId = (int)(getSessionCompanyId() ?? 0);

    if ($companyId <= 0) {
        $company = null;
        $success = false;
        $errors = [];
        $old = ['contacts' => contractorFormDefaultContacts()];
        $formError = null;
        $generatedPassword = null;
        $createdLogist = null;
        $tempPassword = null;

        ob_start();
        require base_path('app/View/pages/company_logists_create.php');
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
            $generatedPassword = null;
            $createdLogist = null;
            $tempPassword = null;

            ob_start();
            require base_path('app/View/pages/company_logists_create.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $pageContext = 'Пользователи › Компания: ' . $company['name'];

        $success = false;
        $errors = [];
        $old = [];
        $formError = null;
        $generatedPassword = generatePassword();
        $createdLogist = null;
        $tempPassword = null;
    } catch (\Exception $e) {
        $company = null;
        $success = false;
        $errors = [];
        $old = ['contacts' => contractorFormDefaultContacts()];
        $formError = 'Ошибка загрузки данных: ' . $e->getMessage();
        $generatedPassword = null;
        $createdLogist = null;
        $tempPassword = null;
    }

    ob_start();
    require base_path('app/View/pages/company_logists_create.php');
    $content = ob_get_clean();
    require base_path('app/View/layouts/main.php');
});

$router->post('/company/logists/create', function () use ($config, $db) {
    requireRole('company_owner');
    $pageTitle = 'Создать пользователя';
    $pageContext = 'Пользователи › Компания';

    $companyId = (int)(getSessionCompanyId() ?? 0);
    $errors = [];
    $old = $_POST;
    $formError = null;
    $success = false;
    $generatedPassword = null;
    $createdLogist = null;
    $tempPassword = null;

    if ($companyId <= 0) {
        $company = null;
        $formError = 'Компания не найдена';

        ob_start();
        require base_path('app/View/pages/company_logists_create.php');
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
            require base_path('app/View/pages/company_logists_create.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $pageContext = 'Пользователи › Компания: ' . $company['name'];

        if ($company['status'] !== 'active') {
            $formError = 'Создание пользователей недоступно';

            ob_start();
            require base_path('app/View/pages/company_logists_create.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $dbIdentifier = $company['db_identifier'];

        // Создать локальную БД, если не существует
        try {
            $tempPdo = new PDO(
                sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $config['database']['host'] ?? '127.0.0.1', $config['database']['port'] ?? '3306'),
                $config['database']['username'] ?? 'root',
                $config['database']['password'] ?? ''
            );
            $tempPdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbIdentifier}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        } catch (\Exception $e) {
            // Игнорируем ошибку создания БД — основное подключение поймает проблему
        }
        // Release tempPdo to avoid any connection state interference
        $tempPdo = null;

        $localDbConfig = $config['database'];
        $localDbConfig['database'] = $dbIdentifier;
        $localDb = new \App\Core\Database($localDbConfig);
        $localPdo = $localDb->connection();
        applyLocalMigrations($localPdo);

        try {
            $localPdo->query("SELECT 1 FROM users LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $migrationSql = file_get_contents(base_path('database/migrations-local/001_create_company_users.sql'));
            $localPdo->exec($migrationSql);
        }

        $fullName = trim($_POST['full_name'] ?? '');
        $login = trim($_POST['login'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $roleCode = trim($_POST['role_code'] ?? 'logist');

        if ($fullName === '') {
            $errors['full_name'] = 'Обязательное поле';
        }

        if ($login === '') {
            $errors['login'] = 'Обязательное поле';
        } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $login)) {
            $errors['login'] = 'Только латинские буквы, цифры и подчёркивание';
        }

        if (empty($errors['login'])) {
            $checkStmt = $localPdo->prepare('SELECT COUNT(*) FROM users WHERE login = ?');
            $checkStmt->execute([$login]);
            if ($checkStmt->fetchColumn() > 0) {
                $errors['login'] = 'Логин уже используется в этой компании';
            }
        }

        if ($password === '') {
            $password = generatePassword();
        }

        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Некорректный email';
        }

        // FR19: Validate role against whitelist
        $allowedRoles = ['logist', 'senior_logist'];
        if (!in_array($roleCode, $allowedRoles, true)) {
            $errors['role_code'] = 'Недопустимая роль';
        }

        if (!empty($errors)) {
            $generatedPassword = generatePassword();

            ob_start();
            require base_path('app/View/pages/company_logists_create.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $passwordHash = password_hash($password, PASSWORD_BCRYPT);

        $insert = $localPdo->prepare(
            'INSERT INTO users (full_name, login, email, phone, password_hash, role_code, status, created_by_user_id, created_by_role)
             VALUES (:full_name, :login, :email, :phone, :password_hash, :role_code, :status, :created_by_user_id, :created_by_role)'
        );
        $insert->execute([
            ':full_name'          => $fullName,
            ':login'              => $login,
            ':email'              => $email !== '' ? $email : null,
            ':phone'              => $phone !== '' ? $phone : null,
            ':password_hash'      => $passwordHash,
            ':role_code'          => $roleCode,
            ':status'             => 'active',
            ':created_by_user_id' => (int)$_SESSION['user_id'],
            ':created_by_role'    => $_SESSION['role_code'],
        ]);

        $createdLogist = [
            'full_name' => $fullName,
            'login'     => $login,
            'role_code' => $roleCode,
        ];
        $tempPassword = $password;
        $success = true;
    } catch (\Exception $e) {
        $company = $company ?? null;
        $formError = 'Ошибка создания пользователя: ' . $e->getMessage();
    }

    ob_start();
    require base_path('app/View/pages/company_logists_create.php');
    $content = ob_get_clean();
    require base_path('app/View/layouts/main.php');
});

$router->get('/company/logists/{id}', function ($id) use ($config, $db) {
    requireRole('company_owner');

    $companyId = (int)(getSessionCompanyId() ?? 0);

    if ($companyId <= 0) {
        $company = null;
        $logist = null;
        $dbError = null;
        $passwordReset = false;
        $newPassword = null;

        ob_start();
        require base_path('app/View/pages/company_logist_view.php');
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
            $logist = null;
            $dbError = null;
            $passwordReset = false;
            $newPassword = null;

            ob_start();
            require base_path('app/View/pages/company_logist_view.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $pageTitle = 'Пользователь';
        $pageContext = 'Пользователи › Компания: ' . $company['name'];

        if ($company['status'] !== 'active') {
            $logist = null;
            $dbError = null;
            $passwordReset = false;
            $newPassword = null;

            ob_start();
            require base_path('app/View/pages/company_logist_view.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $dbIdentifier = $company['db_identifier'];

        // Создать локальную БД, если не существует
        try {
            $tempPdo = new PDO(
                sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $config['database']['host'] ?? '127.0.0.1', $config['database']['port'] ?? '3306'),
                $config['database']['username'] ?? 'root',
                $config['database']['password'] ?? ''
            );
            $tempPdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbIdentifier}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        } catch (\Exception $e) {
            // Игнорируем ошибку создания БД — основное подключение поймает проблему
        }
        // Release tempPdo to avoid any connection state interference
        $tempPdo = null;

        $localDbConfig = $config['database'];
        $localDbConfig['database'] = $dbIdentifier;
        $localDb = new \App\Core\Database($localDbConfig);
        $localPdo = $localDb->connection();
                applyLocalMigrations($localPdo);

        try {
            $localPdo->query("SELECT 1 FROM users LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $migrationSql = file_get_contents(base_path('database/migrations-local/001_create_company_users.sql'));
            $localPdo->exec($migrationSql);
        }

        $logistStmt = $localPdo->prepare("SELECT * FROM users WHERE id = ?");
        $logistStmt->execute([(int) $id]);
        $logist = $logistStmt->fetch(PDO::FETCH_ASSOC) ?: null;

        $pageTitle = $logist ? 'Пользователь: ' . $logist['full_name'] : 'Пользователь';
        $dbError = null;
        $passwordReset = false;
        $newPassword = null;

        ob_start();
        require base_path('app/View/pages/company_logist_view.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
    } catch (\Exception $e) {
        $company = $company ?? null;
        $logist = null;
        $dbError = 'Не удалось загрузить данные: ' . $e->getMessage();
        $passwordReset = false;
        $newPassword = null;

        ob_start();
        require base_path('app/View/pages/company_logist_view.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
    }
});

$router->get('/company/logists/{id}/edit', function ($id) use ($config, $db) {
    requireRole('company_owner');

    $companyId = (int)(getSessionCompanyId() ?? 0);

    if ($companyId <= 0) {
        $company = null;
        $logist = null;
        $errors = [];
        $old = [];
        $formError = null;

        ob_start();
        require base_path('app/View/pages/company_logist_edit.php');
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
            $logist = null;
            $errors = [];
            $old = [];
            $formError = null;

            ob_start();
            require base_path('app/View/pages/company_logist_edit.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $pageTitle = 'Редактировать пользователя';
        $pageContext = 'Пользователи › Компания: ' . $company['name'];

        if ($company['status'] !== 'active') {
            $logist = null;
            $errors = [];
            $old = [];
            $formError = null;

            ob_start();
            require base_path('app/View/pages/company_logist_edit.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $dbIdentifier = $company['db_identifier'];

        // Создать локальную БД, если не существует
        try {
            $tempPdo = new PDO(
                sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $config['database']['host'] ?? '127.0.0.1', $config['database']['port'] ?? '3306'),
                $config['database']['username'] ?? 'root',
                $config['database']['password'] ?? ''
            );
            $tempPdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbIdentifier}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        } catch (\Exception $e) {
            // Игнорируем ошибку создания БД — основное подключение поймает проблему
        }
        // Release tempPdo to avoid any connection state interference
        $tempPdo = null;

        $localDbConfig = $config['database'];
        $localDbConfig['database'] = $dbIdentifier;
        $localDb = new \App\Core\Database($localDbConfig);
        $localPdo = $localDb->connection();
                applyLocalMigrations($localPdo);

        try {
            $localPdo->query("SELECT 1 FROM users LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $migrationSql = file_get_contents(base_path('database/migrations-local/001_create_company_users.sql'));
            $localPdo->exec($migrationSql);
        }

        $logistStmt = $localPdo->prepare("SELECT * FROM users WHERE id = ?");
        $logistStmt->execute([(int) $id]);
        $logist = $logistStmt->fetch(PDO::FETCH_ASSOC) ?: null;

        $errors = [];
        $old = $logist ?: [];
        $formError = null;

        ob_start();
        require base_path('app/View/pages/company_logist_edit.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
    } catch (\Exception $e) {
        $company = $company ?? null;
        $logist = null;
        $errors = [];
        $old = [];
        $formError = 'Не удалось загрузить данные: ' . $e->getMessage();

        ob_start();
        require base_path('app/View/pages/company_logist_edit.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
    }
});

$router->post('/company/logists/{id}/edit', function ($id) use ($config, $db) {
    requireRole('company_owner');

    $companyId = (int)(getSessionCompanyId() ?? 0);

    if ($companyId <= 0) {
        $company = null;
        $logist = null;
        $errors = [];
        $old = $_POST;
        $formError = 'Компания не найдена';

        ob_start();
        require base_path('app/View/pages/company_logist_edit.php');
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
            $logist = null;
            $errors = [];
            $old = $_POST;
            $formError = 'Компания не найдена';

            ob_start();
            require base_path('app/View/pages/company_logist_edit.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $pageTitle = 'Редактировать пользователя';
        $pageContext = 'Пользователи › Компания: ' . $company['name'];

        if ($company['status'] !== 'active') {
            $logist = null;
            $errors = [];
            $old = $_POST;
            $formError = 'Редактирование пользователей недоступно';

            ob_start();
            require base_path('app/View/pages/company_logist_edit.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $dbIdentifier = $company['db_identifier'];

        // Создать локальную БД, если не существует
        try {
            $tempPdo = new PDO(
                sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $config['database']['host'] ?? '127.0.0.1', $config['database']['port'] ?? '3306'),
                $config['database']['username'] ?? 'root',
                $config['database']['password'] ?? ''
            );
            $tempPdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbIdentifier}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        } catch (\Exception $e) {
            // Игнорируем ошибку создания БД — основное подключение поймает проблему
        }
        // Release tempPdo to avoid any connection state interference
        $tempPdo = null;

        $localDbConfig = $config['database'];
        $localDbConfig['database'] = $dbIdentifier;
        $localDb = new \App\Core\Database($localDbConfig);
        $localPdo = $localDb->connection();
                applyLocalMigrations($localPdo);

        try {
            $localPdo->query("SELECT 1 FROM users LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $migrationSql = file_get_contents(base_path('database/migrations-local/001_create_company_users.sql'));
            $localPdo->exec($migrationSql);
        }

        $logistStmt = $localPdo->prepare("SELECT * FROM users WHERE id = ?");
        $logistStmt->execute([(int) $id]);
        $logist = $logistStmt->fetch(PDO::FETCH_ASSOC) ?: null;

        if (!$logist) {
            $errors = [];
            $old = $_POST;
            $formError = 'Пользователь не найден';

            ob_start();
            require base_path('app/View/pages/company_logist_edit.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $errors = [];
        $old = $_POST;
        $formError = null;

        $fullName = trim($_POST['full_name'] ?? '');
        $login = trim($_POST['login'] ?? '');
        $roleCode = trim($_POST['role_code'] ?? $logist['role_code']);

        if ($fullName === '') {
            $errors['full_name'] = 'Обязательное поле';
        }

        if ($login === '') {
            $errors['login'] = 'Обязательное поле';
        } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $login)) {
            $errors['login'] = 'Только латинские буквы, цифры и подчёркивание';
        } else {
            $dupStmt = $localPdo->prepare("SELECT COUNT(*) FROM users WHERE login = ? AND id != ?");
            $dupStmt->execute([$login, (int) $id]);
            if ($dupStmt->fetchColumn() > 0) {
                $errors['login'] = 'Логин уже используется в этой компании';
            }
        }

        // FR21: Validate role
        $allowedRoles = ['logist', 'senior_logist'];
        if (!in_array($roleCode, $allowedRoles, true)) {
            $errors['role_code'] = 'Недопустимая роль';
        }

        $email = trim($_POST['email'] ?? '');
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Некорректный email';
        }

        if (!empty($errors)) {
            ob_start();
            require base_path('app/View/pages/company_logist_edit.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $update = $localPdo->prepare(
            "UPDATE users SET
                full_name = :full_name,
                login = :login,
                email = :email,
                phone = :phone,
                role_code = :role_code,
                status = :status
             WHERE id = :id"
        );

        $update->execute([
            ':full_name' => $fullName,
            ':login'     => $login,
            ':email'     => $email !== '' ? $email : null,
            ':phone'     => trim($_POST['phone'] ?? '') ?: null,
            ':role_code' => $roleCode,
            ':status'    => $_POST['status'] ?? $logist['status'],
            ':id'        => (int) $id,
        ]);

        header('Location: /company/logists/' . $id);
        exit;
    } catch (\Exception $e) {
        $company = $company ?? null;
        $logist = $logist ?? null;
        $errors = [];
        $old = $_POST;
        $formError = 'Ошибка сохранения: ' . $e->getMessage();

        ob_start();
        require base_path('app/View/pages/company_logist_edit.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
    }
});

$router->post('/company/logists/{id}/reset-password', function ($id) use ($config, $db) {
    requireRole('company_owner');

    $companyId = (int)(getSessionCompanyId() ?? 0);

    if ($companyId <= 0) {
        $company = null;
        $logist = null;
        $dbError = 'Компания не найдена';
        $passwordReset = false;
        $newPassword = null;

        ob_start();
        require base_path('app/View/pages/company_logist_view.php');
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
            $logist = null;
            $dbError = 'Компания не найдена';
            $passwordReset = false;
            $newPassword = null;

            ob_start();
            require base_path('app/View/pages/company_logist_view.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $pageTitle = 'Пользователь';
        $pageContext = 'Пользователи › Компания: ' . $company['name'];

        if ($company['status'] !== 'active') {
            $logist = null;
            $dbError = null;
            $passwordReset = false;
            $newPassword = null;

            ob_start();
            require base_path('app/View/pages/company_logist_view.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $dbIdentifier = $company['db_identifier'];

        // Создать локальную БД, если не существует
        try {
            $tempPdo = new PDO(
                sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $config['database']['host'] ?? '127.0.0.1', $config['database']['port'] ?? '3306'),
                $config['database']['username'] ?? 'root',
                $config['database']['password'] ?? ''
            );
            $tempPdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbIdentifier}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        } catch (\Exception $e) {
            // Игнорируем ошибку создания БД — основное подключение поймает проблему
        }
        // Release tempPdo to avoid any connection state interference
        $tempPdo = null;

        $localDbConfig = $config['database'];
        $localDbConfig['database'] = $dbIdentifier;
        $localDb = new \App\Core\Database($localDbConfig);
        $localPdo = $localDb->connection();
                applyLocalMigrations($localPdo);

        try {
            $localPdo->query("SELECT 1 FROM users LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $migrationSql = file_get_contents(base_path('database/migrations-local/001_create_company_users.sql'));
            $localPdo->exec($migrationSql);
        }

        $logistStmt = $localPdo->prepare("SELECT * FROM users WHERE id = ?");
        $logistStmt->execute([(int) $id]);
        $logist = $logistStmt->fetch(PDO::FETCH_ASSOC) ?: null;

        if (!$logist) {
            $pageTitle = $logist ? 'Пользователь: ' . $logist['full_name'] : 'Пользователь';
            $dbError = null;
            $passwordReset = false;
            $newPassword = null;

            ob_start();
            require base_path('app/View/pages/company_logist_view.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $pageTitle = 'Пользователь: ' . $logist['full_name'];

        $newPassword = generatePassword(10);
        $passwordHash = password_hash($newPassword, PASSWORD_BCRYPT);

        $update = $localPdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        $update->execute([$passwordHash, (int) $logist['id']]);

        $passwordReset = true;
        $dbError = null;

        ob_start();
        require base_path('app/View/pages/company_logist_view.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
    } catch (\Exception $e) {
        $company = $company ?? null;
        $logist = $logist ?? null;
        $dbError = 'Ошибка сброса пароля: ' . $e->getMessage();
        $passwordReset = false;
        $newPassword = null;

        ob_start();
        require base_path('app/View/pages/company_logist_view.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
    }
});

$router->post('/company/logists/{id}/archive', function ($id) use ($config, $db) {
    requireRole('company_owner');

    $companyId = (int)(getSessionCompanyId() ?? 0);

    if ($companyId <= 0) {
        header('Location: /company/logists');
        exit;
    }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company || $company['status'] !== 'active') {
            header('Location: /company/logists');
            exit;
        }

        $dbIdentifier = $company['db_identifier'];

        // Создать локальную БД, если не существует
        try {
            $tempPdo = new PDO(
                sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $config['database']['host'] ?? '127.0.0.1', $config['database']['port'] ?? '3306'),
                $config['database']['username'] ?? 'root',
                $config['database']['password'] ?? ''
            );
            $tempPdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbIdentifier}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        } catch (\Exception $e) {
            // Игнорируем ошибку создания БД — основное подключение поймает проблему
        }
        // Release tempPdo to avoid any connection state interference
        $tempPdo = null;

        $localDbConfig = $config['database'];
        $localDbConfig['database'] = $dbIdentifier;
        $localDb = new \App\Core\Database($localDbConfig);
        $localPdo = $localDb->connection();
                applyLocalMigrations($localPdo);

        try {
            $localPdo->query("SELECT 1 FROM users LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $migrationSql = file_get_contents(base_path('database/migrations-local/001_create_company_users.sql'));
            $localPdo->exec($migrationSql);
        }

        $update = $localPdo->prepare("UPDATE users SET status = 'archived' WHERE id = ?");
        $update->execute([(int) $id]);

        header('Location: /company/logists');
        exit;
    } catch (\Exception $e) {
        header('Location: /company/logists');
        exit;
    }
});
