<?php

$router->get('/login', function () use ($config, $db) {
    if (isAuthenticated()) {
        $role = $_SESSION['role_code'] ?? '';
        if ($role === 'superadmin') {
            header('Location: /superadmin/companies');
            exit;
        }
        header('Location: /company/dashboard');
        exit;
    }

    $loginValue = '';
    $errors = [];
    $authError = null;
    $multiLogistError = null;
    $devSeedPassword = null;

    try {
        $pdo = $db->connection();
        $count = $pdo->query("SELECT COUNT(*) FROM superadmin_users")->fetchColumn();
        if ((int)$count === 0) {
            $tempPassword = generatePassword(10);
            $hash = password_hash($tempPassword, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare(
                "INSERT INTO superadmin_users (name, email, password_hash, role, is_active, created_at, updated_at)
                 VALUES (:name, :email, :hash, :role, 1, NOW(), NOW())"
            );
            $stmt->execute([
                ':name' => 'Super Admin',
                ':email' => 'admin@planex.local',
                ':hash' => $hash,
                ':role' => 'admin',
            ]);
            $devSeedPassword = $tempPassword;
        }
    } catch (\Exception $e) {
        $devSeedPassword = null;
    }

    ob_start();
    require base_path('app/View/pages/login_form.php');
    $content = ob_get_clean();

    require base_path('app/View/layouts/auth-layout.php');
});

$router->post('/login', function () use ($config, $db) {
    if (isAuthenticated()) {
        header('Location: /company/dashboard');
        exit;
    }

    $loginValue = trim($_POST['login'] ?? '');
    $password = $_POST['password'] ?? '';
    $errors = [];
    $authError = null;
    $multiLogistError = null;
    $devSeedPassword = null;

    if ($loginValue === '') {
        $errors['login'] = 'Введите логин или email';
    }

    if ($password === '') {
        $errors['password'] = 'Введите пароль';
    }

    if (!empty($errors)) {
        ob_start();
        require base_path('app/View/pages/login_form.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/auth-layout.php');
        return;
    }

    try {
        $pdo = $db->connection();

        $superStmt = $pdo->prepare(
            "SELECT * FROM superadmin_users WHERE email = :login AND is_active = 1"
        );
        $superStmt->execute([':login' => $loginValue]);
        $superUser = $superStmt->fetch(PDO::FETCH_ASSOC);

        if ($superUser && password_verify($password, $superUser['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int)$superUser['id'];
            $_SESSION['role_code'] = 'superadmin';
            $_SESSION['company_id'] = null;
            $_SESSION['user_name'] = $superUser['name'] ?? 'Super Admin';
            header('Location: /superadmin/companies');
            exit;
        }

        $ownerStmt = $pdo->prepare(
            "SELECT cu.*, c.status as company_status
             FROM company_users cu
             JOIN companies c ON cu.company_id = c.id
             WHERE cu.login = :login"
        );
        $ownerStmt->execute([':login' => $loginValue]);
        $owner = $ownerStmt->fetch(PDO::FETCH_ASSOC);

        if ($owner && password_verify($password, $owner['password_hash'])) {
            if ($owner['status'] !== 'active') {
                $authError = 'Доступ к компании временно ограничен. Обратитесь к администратору.';
            } elseif (!in_array($owner['company_status'], ['active'], true)) {
                $authError = 'Доступ к компании временно ограничен. Обратитесь к администратору.';
            } else {
                session_regenerate_id(true);
                $_SESSION['user_id'] = (int)$owner['id'];
                $_SESSION['role_code'] = 'company_owner';
                $_SESSION['company_id'] = (int)$owner['company_id'];
                $_SESSION['user_name'] = $owner['full_name'];
                header('Location: /company/dashboard');
                exit;
            }
        }

        $companiesStmt = $pdo->query("SELECT id, db_identifier FROM companies WHERE status = 'active'");
        $activeCompanies = $companiesStmt->fetchAll(PDO::FETCH_ASSOC);

        $logistCandidates = [];

        foreach ($activeCompanies as $ac) {
            try {
                $localDbConfig = $config['database'];
                $localDbConfig['database'] = $ac['db_identifier'];
                $localDb = new \App\Core\Database($localDbConfig);
                $localPdo = $localDb->connection();
                applyLocalMigrations($localPdo);

                $logistStmt = $localPdo->prepare(
                    "SELECT * FROM users WHERE login = :login AND status = 'active'"
                );
                $logistStmt->execute([':login' => $loginValue]);
                $logistUser = $logistStmt->fetch(PDO::FETCH_ASSOC);

                if ($logistUser) {
                    $logistCandidates[] = [
                        'user' => $logistUser,
                        'company_id' => (int)$ac['id'],
                        'db_identifier' => $ac['db_identifier'],
                    ];
                }
            } catch (\Exception $e) {
                continue;
            }
        }

        if (count($logistCandidates) === 1) {
            $candidate = $logistCandidates[0];
            if (password_verify($password, $candidate['user']['password_hash'])) {
                session_regenerate_id(true);
                $_SESSION['user_id'] = (int)$candidate['user']['id'];
                $_SESSION['role_code'] = $candidate['user']['role_code'] ?? 'logist';
                $_SESSION['company_id'] = $candidate['company_id'];
                $_SESSION['user_name'] = $candidate['user']['full_name'];
                header('Location: /company/dashboard');
                exit;
            }
            $authError = 'Неверный логин или пароль.';
        } elseif (count($logistCandidates) > 1) {
            $multiLogistError = 'Логин найден в нескольких компаниях, обратитесь к администратору.';
        } else {
            $authError = 'Неверный логин или пароль.';
        }

    } catch (\Exception $e) {
        $authError = 'Неверный логин или пароль.';
    }

    ob_start();
    require base_path('app/View/pages/login_form.php');
    $content = ob_get_clean();
    require base_path('app/View/layouts/auth-layout.php');
});

$router->get('/logout', function () {
    session_destroy();
    header('Location: /login', true, 302);
    exit;
});
