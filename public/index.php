<?php

/**
 * ERP PLANEX — Entry Point
 *
 * Минимальная техническая точка входа.
 * PDO-обёртка и роутер интегрированы.
 * Бизнес-маршруты, авторизация — не подключаются.
 */

$config = require_once __DIR__ . '/../bootstrap/app.php';

require_once base_path('app/Core/Database.php');
require_once base_path('app/Http/Router.php');

require_once base_path('app/View/components/alert.php');
require_once base_path('app/View/components/button.php');
require_once base_path('app/View/components/empty_state.php');
require_once base_path('app/View/components/form_actions.php');
require_once base_path('app/View/components/input.php');
require_once base_path('app/View/components/page_header.php');
require_once base_path('app/View/components/status_badge.php');
require_once base_path('app/View/components/table.php');

use App\Core\Database;
use App\Http\Router;

$db     = new Database($config['database']);
$router = new Router();

function generatePassword(int $length = 10): string
{
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $password;
}

$router->get('/', function () use ($config) {
    $pageTitle = 'UI foundation';

    ob_start();
    require base_path('app/View/pages/ui_demo.php');
    $content = ob_get_clean();

    require base_path('app/View/layouts/main.php');
});

$router->get('/test', function () {
    header('Content-Type: text/plain');
    echo 'ERP PLANEX core is running';
});

$router->get('/test-db', function () use ($db) {
    header('Content-Type: text/plain');
    try {
        $db->connection();
        echo 'DB connection OK';
    } catch (\Exception $e) {
        echo 'DB connection FAILED';
    }
});

$router->get('/superadmin', function () use ($config) {
    $pageTitle = 'SUPERADMIN';
    $pageContext = 'Центральная панель управления';

    ob_start();
    require base_path('app/View/pages/superadmin_dashboard.php');
    $content = ob_get_clean();

    require base_path('app/View/layouts/main.php');
});

$router->get('/superadmin/companies', function () use ($config, $db) {
    $pageTitle = 'Реестр компаний';
    $pageContext = 'Реестр компаний';

    try {
        $pdo = $db->connection();
        $stmt = $pdo->query('SELECT * FROM companies ORDER BY created_at DESC');
        $companies = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $ownerMap = [];
        $companyIds = array_column($companies, 'id');
        if (!empty($companyIds)) {
            $placeholders = implode(',', array_fill(0, count($companyIds), '?'));
            $ownerStmt = $pdo->prepare(
                "SELECT id, company_id, full_name FROM company_users 
                 WHERE company_id IN ($placeholders) AND role = 'company_owner' AND status = 'active'"
            );
            $ownerStmt->execute($companyIds);
            foreach ($ownerStmt->fetchAll() as $owner) {
                $ownerMap[$owner['company_id']] = $owner;
            }
        }
        foreach ($companies as &$c) {
            $c['owner_name'] = $ownerMap[$c['id']]['full_name'] ?? null;
            $c['owner_id'] = $ownerMap[$c['id']]['id'] ?? null;
        }
        unset($c);

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

$router->get('/superadmin/companies/create', function () use ($config) {
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
                contact_person, contact_phone, contact_email, comments, status)
             VALUES (:key, :name, :inn, :kpp, :ogrn, :legal_address, :physical_address,
                :contact_person, :contact_phone, :contact_email, :comments, :status)'
        );

        $insert->execute([
            ':key'              => $key,
            ':name'             => $name,
            ':inn'              => $inn,
            ':kpp'              => $_POST['kpp'] ?? null,
            ':ogrn'             => $_POST['ogrn'] ?? null,
            ':legal_address'   => $_POST['legal_address'] ?? null,
            ':physical_address' => $_POST['physical_address'] ?? null,
            ':contact_person'  => $_POST['contact_person'] ?? null,
            ':contact_phone'   => $_POST['contact_phone'] ?? null,
            ':contact_email'   => $_POST['contact_email'] ?? null,
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

$router->get('/superadmin/companies/{id}/create-owner', function ($id) use ($config, $db) {
    $pageTitle = 'Создать Руководителя';
    $pageContext = 'Реестр компаний';

    try {
        $pdo = $db->connection();

        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([(int) $id]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company) {
            $company = null;
            $ownerExists = false;
            $existingOwner = null;
            $success = false;
            $errors = [];
            $old = [];
            $formError = null;
            $generatedPassword = null;

            ob_start();
            require base_path('app/View/pages/superadmin_company_owner_create.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $ownerStmt = $pdo->prepare(
            "SELECT * FROM company_users WHERE company_id = ? AND role = 'company_owner' AND status = 'active'"
        );
        $ownerStmt->execute([(int) $id]);
        $existingOwner = $ownerStmt->fetch(PDO::FETCH_ASSOC);

        $ownerExists = $existingOwner !== false;
        $success = false;
        $errors = [];
        $old = [];
        $formError = null;
        $generatedPassword = generatePassword();

        ob_start();
        require base_path('app/View/pages/superadmin_company_owner_create.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
    } catch (\Exception $e) {
        $company = null;
        $ownerExists = false;
        $existingOwner = null;
        $success = false;
        $errors = [];
        $old = [];
        $formError = 'Ошибка загрузки данных: ' . $e->getMessage();
        $generatedPassword = null;

        ob_start();
        require base_path('app/View/pages/superadmin_company_owner_create.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
    }
});

$router->post('/superadmin/companies/{id}/create-owner', function ($id) use ($config, $db) {
    $pageTitle = 'Создать Руководителя';
    $pageContext = 'Реестр компаний';

    try {
        $pdo = $db->connection();

        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([(int) $id]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company) {
            $company = null;
            $ownerExists = false;
            $existingOwner = null;
            $success = false;
            $errors = [];
            $old = $_POST;
            $formError = 'Компания не найдена';
            $generatedPassword = null;

            ob_start();
            require base_path('app/View/pages/superadmin_company_owner_create.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $ownerStmt = $pdo->prepare(
            "SELECT * FROM company_users WHERE company_id = ? AND role = 'company_owner' AND status = 'active'"
        );
        $ownerStmt->execute([(int) $id]);
        $existingOwner = $ownerStmt->fetch(PDO::FETCH_ASSOC);

        if ($existingOwner) {
            $ownerExists = true;
            $success = false;
            $errors = [];
            $old = $_POST;
            $formError = null;
            $generatedPassword = null;

            ob_start();
            require base_path('app/View/pages/superadmin_company_owner_create.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $ownerExists = false;
        $errors = [];
        $old = $_POST;

        $fullName = trim($_POST['full_name'] ?? '');
        $login = trim($_POST['login'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $comments = trim($_POST['comments'] ?? '');

        if ($fullName === '') {
            $errors['full_name'] = 'Обязательное поле';
        }

        if ($login === '') {
            $errors['login'] = 'Обязательное поле';
        } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $login)) {
            $errors['login'] = 'Только латинские буквы, цифры и подчёркивание';
        }

        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Некорректный email';
        }

        if ($password === '') {
            $password = generatePassword();
        }

        if (!empty($errors)) {
            $formError = null;
            $success = false;
            $generatedPassword = generatePassword();

            ob_start();
            require base_path('app/View/pages/superadmin_company_owner_create.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $passwordHash = password_hash($password, PASSWORD_BCRYPT);

        $insert = $pdo->prepare(
            'INSERT INTO company_users (company_id, full_name, login, email, phone, password_hash, role, status, comments)
             VALUES (:company_id, :full_name, :login, :email, :phone, :password_hash, :role, :status, :comments)'
        );
        $insert->execute([
            ':company_id'    => (int) $id,
            ':full_name'     => $fullName,
            ':login'         => $login,
            ':email'         => $email !== '' ? $email : null,
            ':phone'         => $phone !== '' ? $phone : null,
            ':password_hash' => $passwordHash,
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

        ob_start();
        require base_path('app/View/pages/superadmin_company_owner_create.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
    } catch (\Exception $e) {
        $company = $company ?? null;
        $ownerExists = false;
        $existingOwner = null;
        $success = false;
        $errors = [];
        $old = $_POST;
        $formError = 'Ошибка создания Руководителя: ' . $e->getMessage();
        $generatedPassword = null;

        ob_start();
        require base_path('app/View/pages/superadmin_company_owner_create.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
    }
});

$router->get('/company/logists', function () use ($config, $db) {
    $pageTitle = 'Логисты';
    $pageContext = 'Логисты — Компания';

    $companyId = (int)($_GET['company_id'] ?? 0);

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

        $pageContext = 'Логисты — Компания: ' . $company['name'];

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
        $localDbConfig = $config['database'];
        $localDbConfig['database'] = $dbIdentifier;
        $localDb = new \App\Core\Database($localDbConfig);
        $localPdo = $localDb->connection();

        try {
            $localPdo->query("SELECT 1 FROM users LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $migrationSql = file_get_contents(base_path('database/migrations-local/001_create_company_users.sql'));
            $localPdo->exec($migrationSql);
        }

        $logistStmt = $localPdo->query("SELECT * FROM users WHERE role_code = 'logist' ORDER BY created_at DESC");
        $logists = $logistStmt->fetchAll(PDO::FETCH_ASSOC);
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
    $pageTitle = 'Создать логиста';
    $pageContext = 'Логисты — Компания';

    $companyId = (int)($_GET['company_id'] ?? 0);

    if ($companyId <= 0) {
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

        $pageContext = 'Логисты — Компания: ' . $company['name'];

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
        $old = [];
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
    $pageTitle = 'Создать логиста';
    $pageContext = 'Логисты — Компания';

    $companyId = (int)($_GET['company_id'] ?? 0);
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

        $pageContext = 'Логисты — Компания: ' . $company['name'];

        if ($company['status'] !== 'active') {
            $formError = 'Создание логистов недоступно';

            ob_start();
            require base_path('app/View/pages/company_logists_create.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $dbIdentifier = $company['db_identifier'];
        $localDbConfig = $config['database'];
        $localDbConfig['database'] = $dbIdentifier;
        $localDb = new \App\Core\Database($localDbConfig);
        $localPdo = $localDb->connection();

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
            'INSERT INTO users (full_name, login, email, phone, password_hash, role_code, status)
             VALUES (:full_name, :login, :email, :phone, :password_hash, :role_code, :status)'
        );
        $insert->execute([
            ':full_name'     => $fullName,
            ':login'         => $login,
            ':email'         => $email !== '' ? $email : null,
            ':phone'         => $phone !== '' ? $phone : null,
            ':password_hash' => $passwordHash,
            ':role_code'     => 'logist',
            ':status'        => 'active',
        ]);

        $createdLogist = [
            'full_name' => $fullName,
            'login'     => $login,
        ];
        $tempPassword = $password;
        $success = true;
    } catch (\Exception $e) {
        $company = $company ?? null;
        $formError = 'Ошибка создания логиста: ' . $e->getMessage();
    }

    ob_start();
    require base_path('app/View/pages/company_logists_create.php');
    $content = ob_get_clean();
    require base_path('app/View/layouts/main.php');
});

$router->get('/company/clients', function () use ($config, $db) {
    $pageTitle = 'Клиенты';
    $pageContext = 'Клиенты — Компания';

    $companyId = (int)($_GET['company_id'] ?? 0);

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

        $pageContext = 'Клиенты — Компания: ' . $company['name'];

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

        try {
            $localPdo->query("SELECT 1 FROM clients LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $migrationSql = file_get_contents(base_path('database/migrations-local/002_create_company_clients.sql'));
            $localPdo->exec($migrationSql);
        }

        $clientStmt = $localPdo->query("SELECT * FROM clients ORDER BY created_at DESC");
        $clients = $clientStmt->fetchAll(PDO::FETCH_ASSOC);
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
    $pageTitle = 'Создать клиента';
    $pageContext = 'Клиенты — Компания';

    $companyId = (int)($_GET['company_id'] ?? 0);

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

        $pageContext = 'Клиенты — Компания: ' . $company['name'];

        $success = false;
        $errors = [];
        $old = [];
        $formError = null;
        $createdClient = null;
    } catch (\Exception $e) {
        $company = null;
        $success = false;
        $errors = [];
        $old = [];
        $formError = 'Ошибка загрузки данных: ' . $e->getMessage();
        $createdClient = null;
    }

    ob_start();
    require base_path('app/View/pages/company_clients_create.php');
    $content = ob_get_clean();
    require base_path('app/View/layouts/main.php');
});

$router->post('/company/clients/create', function () use ($config, $db) {
    $pageTitle = 'Создать клиента';
    $pageContext = 'Клиенты — Компания';

    $companyId = (int)($_GET['company_id'] ?? 0);
    $errors = [];
    $old = $_POST;
    $formError = null;
    $success = false;
    $createdClient = null;

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

        $pageContext = 'Клиенты — Компания: ' . $company['name'];

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

        try {
            $localPdo->query("SELECT 1 FROM clients LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $migrationSql = file_get_contents(base_path('database/migrations-local/002_create_company_clients.sql'));
            $localPdo->exec($migrationSql);
        }

        $name = trim($_POST['name'] ?? '');
        $inn = trim($_POST['inn'] ?? '');
        $kpp = trim($_POST['kpp'] ?? '');
        $ogrn = trim($_POST['ogrn'] ?? '');
        $legalAddress = trim($_POST['legal_address'] ?? '');
        $physicalAddress = trim($_POST['physical_address'] ?? '');
        $contactPerson = trim($_POST['contact_person'] ?? '');
        $contactPhone = trim($_POST['contact_phone'] ?? '');
        $contactEmail = trim($_POST['contact_email'] ?? '');
        $comments = trim($_POST['comments'] ?? '');

        if ($name === '') {
            $errors['name'] = 'Обязательное поле';
        }

        if ($inn === '') {
            $errors['inn'] = 'Обязательное поле';
        }

        if (empty($errors['inn'])) {
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

        $insert = $localPdo->prepare(
            'INSERT INTO clients (name, inn, kpp, ogrn, legal_address, physical_address,
             contact_person, contact_phone, contact_email, status, comments)
             VALUES (:name, :inn, :kpp, :ogrn, :legal_address, :physical_address,
             :contact_person, :contact_phone, :contact_email, :status, :comments)'
        );
        $insert->execute([
            ':name'             => $name,
            ':inn'              => $inn,
            ':kpp'              => $kpp !== '' ? $kpp : null,
            ':ogrn'             => $ogrn !== '' ? $ogrn : null,
            ':legal_address'    => $legalAddress !== '' ? $legalAddress : null,
            ':physical_address' => $physicalAddress !== '' ? $physicalAddress : null,
            ':contact_person'   => $contactPerson !== '' ? $contactPerson : null,
            ':contact_phone'    => $contactPhone !== '' ? $contactPhone : null,
            ':contact_email'    => $contactEmail !== '' ? $contactEmail : null,
            ':status'           => 'active',
            ':comments'         => $comments !== '' ? $comments : null,
        ]);

        $createdClient = [
            'id'   => $localPdo->lastInsertId(),
            'name' => $name,
            'inn'  => $inn,
        ];
        $success = true;
    } catch (\Exception $e) {
        $company = $company ?? null;
        $formError = 'Ошибка создания клиента: ' . $e->getMessage();
    }

    ob_start();
    require base_path('app/View/pages/company_clients_create.php');
    $content = ob_get_clean();
    require base_path('app/View/layouts/main.php');
});

$router->get('/company/contractors', function () use ($config, $db) {
    $pageTitle = 'Подрядчики';
    $pageContext = 'Подрядчики — Компания';

    $companyId = (int)($_GET['company_id'] ?? 0);

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

        $pageContext = 'Подрядчики — Компания: ' . $company['name'];

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

        try {
            $localPdo->query("SELECT 1 FROM contractors LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $migrationSql = file_get_contents(base_path('database/migrations-local/003_create_company_contractors.sql'));
            $localPdo->exec($migrationSql);
        }

        $contractorStmt = $localPdo->query("SELECT * FROM contractors ORDER BY created_at DESC");
        $contractors = $contractorStmt->fetchAll(PDO::FETCH_ASSOC);
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
    $pageTitle = 'Создать подрядчика';
    $pageContext = 'Подрядчики — Компания';

    $companyId = (int)($_GET['company_id'] ?? 0);

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

        $pageContext = 'Подрядчики — Компания: ' . $company['name'];

        $success = false;
        $errors = [];
        $old = [];
        $formError = null;
        $createdContractor = null;
    } catch (\Exception $e) {
        $company = null;
        $success = false;
        $errors = [];
        $old = [];
        $formError = 'Ошибка загрузки данных: ' . $e->getMessage();
        $createdContractor = null;
    }

    ob_start();
    require base_path('app/View/pages/company_contractors_create.php');
    $content = ob_get_clean();
    require base_path('app/View/layouts/main.php');
});

$router->post('/company/contractors/create', function () use ($config, $db) {
    $pageTitle = 'Создать подрядчика';
    $pageContext = 'Подрядчики — Компания';

    $companyId = (int)($_GET['company_id'] ?? 0);
    $errors = [];
    $old = $_POST;
    $formError = null;
    $success = false;
    $createdContractor = null;

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

        $pageContext = 'Подрядчики — Компания: ' . $company['name'];

        if ($company['status'] !== 'active') {
            $formError = 'Создание подрядчиков недоступно';

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

        try {
            $localPdo->query("SELECT 1 FROM contractors LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $migrationSql = file_get_contents(base_path('database/migrations-local/003_create_company_contractors.sql'));
            $localPdo->exec($migrationSql);
        }

        $name = trim($_POST['name'] ?? '');
        $inn = trim($_POST['inn'] ?? '');
        $kpp = trim($_POST['kpp'] ?? '');
        $ogrn = trim($_POST['ogrn'] ?? '');
        $legalAddress = trim($_POST['legal_address'] ?? '');
        $physicalAddress = trim($_POST['physical_address'] ?? '');
        $contactPerson = trim($_POST['contact_person'] ?? '');
        $contactPhone = trim($_POST['contact_phone'] ?? '');
        $contactEmail = trim($_POST['contact_email'] ?? '');
        $comments = trim($_POST['comments'] ?? '');

        if ($name === '') {
            $errors['name'] = 'Обязательное поле';
        }

        if ($inn === '') {
            $errors['inn'] = 'Обязательное поле';
        }

        if (empty($errors['inn'])) {
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

        $insert = $localPdo->prepare(
            'INSERT INTO contractors (name, inn, kpp, ogrn, legal_address, physical_address,
             contact_person, contact_phone, contact_email, status, comments)
             VALUES (:name, :inn, :kpp, :ogrn, :legal_address, :physical_address,
             :contact_person, :contact_phone, :contact_email, :status, :comments)'
        );
        $insert->execute([
            ':name'             => $name,
            ':inn'              => $inn,
            ':kpp'              => $kpp !== '' ? $kpp : null,
            ':ogrn'             => $ogrn !== '' ? $ogrn : null,
            ':legal_address'    => $legalAddress !== '' ? $legalAddress : null,
            ':physical_address' => $physicalAddress !== '' ? $physicalAddress : null,
            ':contact_person'   => $contactPerson !== '' ? $contactPerson : null,
            ':contact_phone'    => $contactPhone !== '' ? $contactPhone : null,
            ':contact_email'    => $contactEmail !== '' ? $contactEmail : null,
            ':status'           => 'active',
            ':comments'         => $comments !== '' ? $comments : null,
        ]);

        $createdContractor = [
            'id'   => $localPdo->lastInsertId(),
            'name' => $name,
            'inn'  => $inn,
        ];
        $success = true;
    } catch (\Exception $e) {
        $company = $company ?? null;
        $formError = 'Ошибка создания подрядчика: ' . $e->getMessage();
    }

    ob_start();
    require base_path('app/View/pages/company_contractors_create.php');
    $content = ob_get_clean();
    require base_path('app/View/layouts/main.php');
});

$router->get('/company/drivers', function () use ($config, $db) {
    $pageTitle = 'Водители';
    $pageContext = 'Водители — Компания';

    $companyId = (int)($_GET['company_id'] ?? 0);

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

        $pageContext = 'Водители — Компания: ' . $company['name'];

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

        try {
            $localPdo->query("SELECT 1 FROM drivers LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $migrationSql = file_get_contents(base_path('database/migrations-local/004_create_company_drivers.sql'));
            $localPdo->exec($migrationSql);
        }

        $driverStmt = $localPdo->query("SELECT * FROM drivers ORDER BY created_at DESC");
        $drivers = $driverStmt->fetchAll(PDO::FETCH_ASSOC);
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
    $pageTitle = 'Создать водителя';
    $pageContext = 'Водители — Компания';

    $companyId = (int)($_GET['company_id'] ?? 0);

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

        $pageContext = 'Водители — Компания: ' . $company['name'];

        $success = false;
        $errors = [];
        $old = [];
        $formError = null;
        $createdDriver = null;
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
    $pageTitle = 'Создать водителя';
    $pageContext = 'Водители — Компания';

    $companyId = (int)($_GET['company_id'] ?? 0);
    $errors = [];
    $old = $_POST;
    $formError = null;
    $success = false;
    $createdDriver = null;

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

        $pageContext = 'Водители — Компания: ' . $company['name'];

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

        try {
            $localPdo->query("SELECT 1 FROM drivers LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $migrationSql = file_get_contents(base_path('database/migrations-local/004_create_company_drivers.sql'));
            $localPdo->exec($migrationSql);
        }

        $fullName = trim($_POST['full_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $licenseNumber = trim($_POST['license_number'] ?? '');
        $licenseCategory = trim($_POST['license_category'] ?? '');
        $licenseIssueDate = trim($_POST['license_issue_date'] ?? '');
        $licenseExpireDate = trim($_POST['license_expire_date'] ?? '');
        $comments = trim($_POST['comments'] ?? '');

        if ($fullName === '') {
            $errors['full_name'] = 'Обязательное поле';
        }

        if ($phone === '') {
            $errors['phone'] = 'Обязательное поле';
        }

        if (empty($errors['phone'])) {
            $checkStmt = $localPdo->prepare('SELECT COUNT(*) FROM drivers WHERE phone = ?');
            $checkStmt->execute([$phone]);
            if ($checkStmt->fetchColumn() > 0) {
                $errors['phone'] = 'Телефон уже используется в этой компании';
            }
        }

        if (!empty($errors)) {
            ob_start();
            require base_path('app/View/pages/company_drivers_create.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $insert = $localPdo->prepare(
            'INSERT INTO drivers (full_name, phone, license_number, license_category,
             license_issue_date, license_expire_date, status, comments)
             VALUES (:full_name, :phone, :license_number, :license_category,
             :license_issue_date, :license_expire_date, :status, :comments)'
        );
        $insert->execute([
            ':full_name'           => $fullName,
            ':phone'               => $phone,
            ':license_number'      => $licenseNumber !== '' ? $licenseNumber : null,
            ':license_category'    => $licenseCategory !== '' ? $licenseCategory : null,
            ':license_issue_date'  => $licenseIssueDate !== '' ? $licenseIssueDate : null,
            ':license_expire_date' => $licenseExpireDate !== '' ? $licenseExpireDate : null,
            ':status'              => 'active',
            ':comments'            => $comments !== '' ? $comments : null,
        ]);

        $createdDriver = [
            'id'                => $localPdo->lastInsertId(),
            'full_name'         => $fullName,
            'phone'             => $phone,
            'license_number'    => $licenseNumber !== '' ? $licenseNumber : null,
            'license_category'  => $licenseCategory !== '' ? $licenseCategory : null,
        ];
        $success = true;
    } catch (\Exception $e) {
        $company = $company ?? null;
        $formError = 'Ошибка создания водителя: ' . $e->getMessage();
    }

    ob_start();
    require base_path('app/View/pages/company_drivers_create.php');
    $content = ob_get_clean();
    require base_path('app/View/layouts/main.php');
});

$router->get('/company/vehicles', function () use ($config, $db) {
    $pageTitle = 'Транспорт';
    $pageContext = 'Транспорт — Компания';

    $companyId = (int)($_GET['company_id'] ?? 0);

    if ($companyId <= 0) {
        $company = null;
        $vehicles = [];
        $dbError = null;

        ob_start();
        require base_path('app/View/pages/company_vehicles.php');
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
            $vehicles = [];
            $dbError = null;

            ob_start();
            require base_path('app/View/pages/company_vehicles.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $pageContext = 'Транспорт — Компания: ' . $company['name'];

        if ($company['status'] !== 'active') {
            $vehicles = [];
            $dbError = null;

            ob_start();
            require base_path('app/View/pages/company_vehicles.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $dbIdentifier = $company['db_identifier'];
        $localDbConfig = $config['database'];
        $localDbConfig['database'] = $dbIdentifier;
        $localDb = new \App\Core\Database($localDbConfig);
        $localPdo = $localDb->connection();

        try {
            $localPdo->query("SELECT 1 FROM vehicles LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $migrationSql = file_get_contents(base_path('database/migrations-local/005_create_company_vehicles.sql'));
            $localPdo->exec($migrationSql);
        }

        $vehicleStmt = $localPdo->query("SELECT * FROM vehicles ORDER BY created_at DESC");
        $vehicles = $vehicleStmt->fetchAll(PDO::FETCH_ASSOC);
        $dbError = null;
    } catch (\Exception $e) {
        $company = $company ?? null;
        $vehicles = [];
        $dbError = 'Не удалось подключиться к базе данных компании.';
    }

    ob_start();
    require base_path('app/View/pages/company_vehicles.php');
    $content = ob_get_clean();
    require base_path('app/View/layouts/main.php');
});

$router->get('/company/vehicles/create', function () use ($config, $db) {
    $pageTitle = 'Добавить транспорт';
    $pageContext = 'Транспорт — Компания';

    $companyId = (int)($_GET['company_id'] ?? 0);

    if ($companyId <= 0) {
        $company = null;
        $success = false;
        $errors = [];
        $old = [];
        $formError = null;
        $createdVehicle = null;

        ob_start();
        require base_path('app/View/pages/company_vehicles_create.php');
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
            $createdVehicle = null;

            ob_start();
            require base_path('app/View/pages/company_vehicles_create.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $pageContext = 'Транспорт — Компания: ' . $company['name'];

        $success = false;
        $errors = [];
        $old = [];
        $formError = null;
        $createdVehicle = null;
    } catch (\Exception $e) {
        $company = null;
        $success = false;
        $errors = [];
        $old = [];
        $formError = 'Ошибка загрузки данных: ' . $e->getMessage();
        $createdVehicle = null;
    }

    ob_start();
    require base_path('app/View/pages/company_vehicles_create.php');
    $content = ob_get_clean();
    require base_path('app/View/layouts/main.php');
});

$router->post('/company/vehicles/create', function () use ($config, $db) {
    $pageTitle = 'Добавить транспорт';
    $pageContext = 'Транспорт — Компания';

    $companyId = (int)($_GET['company_id'] ?? 0);
    $errors = [];
    $old = $_POST;
    $formError = null;
    $success = false;
    $createdVehicle = null;

    if ($companyId <= 0) {
        $company = null;
        $formError = 'Компания не найдена';

        ob_start();
        require base_path('app/View/pages/company_vehicles_create.php');
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
            require base_path('app/View/pages/company_vehicles_create.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $pageContext = 'Транспорт — Компания: ' . $company['name'];

        if ($company['status'] !== 'active') {
            $formError = 'Добавление транспорта недоступно';

            ob_start();
            require base_path('app/View/pages/company_vehicles_create.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $dbIdentifier = $company['db_identifier'];
        $localDbConfig = $config['database'];
        $localDbConfig['database'] = $dbIdentifier;
        $localDb = new \App\Core\Database($localDbConfig);
        $localPdo = $localDb->connection();

        try {
            $localPdo->query("SELECT 1 FROM vehicles LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $migrationSql = file_get_contents(base_path('database/migrations-local/005_create_company_vehicles.sql'));
            $localPdo->exec($migrationSql);
        }

        $plateNumber = trim($_POST['plate_number'] ?? '');

        if ($plateNumber === '') {
            $errors['plate_number'] = 'Обязательное поле';
        }

        if (empty($errors['plate_number'])) {
            $checkStmt = $localPdo->prepare('SELECT COUNT(*) FROM vehicles WHERE plate_number = ?');
            $checkStmt->execute([$plateNumber]);
            if ($checkStmt->fetchColumn() > 0) {
                $errors['plate_number'] = 'Госномер уже используется в этой компании';
            }
        }

        if (!empty($errors)) {
            ob_start();
            require base_path('app/View/pages/company_vehicles_create.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $brand = trim($_POST['brand'] ?? '');
        $model = trim($_POST['model'] ?? '');
        $vehicleType = trim($_POST['vehicle_type'] ?? '');
        $vin = trim($_POST['vin'] ?? '');
        $stsNumber = trim($_POST['sts_number'] ?? '');
        $ptsNumber = trim($_POST['pts_number'] ?? '');
        $capacityTons = trim($_POST['capacity_tons'] ?? '');
        $volumeM3 = trim($_POST['volume_m3'] ?? '');
        $comments = trim($_POST['comments'] ?? '');

        $insert = $localPdo->prepare(
            'INSERT INTO vehicles (plate_number, brand, model, vehicle_type, vin,
             sts_number, pts_number, capacity_tons, volume_m3, status, comments)
             VALUES (:plate_number, :brand, :model, :vehicle_type, :vin,
             :sts_number, :pts_number, :capacity_tons, :volume_m3, :status, :comments)'
        );
        $insert->execute([
            ':plate_number'  => $plateNumber,
            ':brand'         => $brand !== '' ? $brand : null,
            ':model'         => $model !== '' ? $model : null,
            ':vehicle_type'  => $vehicleType !== '' ? $vehicleType : null,
            ':vin'           => $vin !== '' ? $vin : null,
            ':sts_number'    => $stsNumber !== '' ? $stsNumber : null,
            ':pts_number'    => $ptsNumber !== '' ? $ptsNumber : null,
            ':capacity_tons' => $capacityTons !== '' ? $capacityTons : null,
            ':volume_m3'     => $volumeM3 !== '' ? $volumeM3 : null,
            ':status'        => 'active',
            ':comments'      => $comments !== '' ? $comments : null,
        ]);

        $lastId = $localPdo->lastInsertId();
        $selectStmt = $localPdo->prepare('SELECT * FROM vehicles WHERE id = ?');
        $selectStmt->execute([$lastId]);
        $createdVehicle = $selectStmt->fetch(PDO::FETCH_ASSOC);
        $success = true;
    } catch (\Exception $e) {
        $company = $company ?? null;
        $formError = 'Ошибка добавления транспорта: ' . $e->getMessage();
    }

    ob_start();
    require base_path('app/View/pages/company_vehicles_create.php');
    $content = ob_get_clean();
    require base_path('app/View/layouts/main.php');
});

$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
