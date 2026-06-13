<?php

/**
 * ERP PLANEX — Entry Point
 *
 * Минимальная техническая точка входа.
 * PDO-обёртка и роутер интегрированы.
 * Бизнес-маршруты, авторизация — не подключаются.
 */

session_start();

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

function isAuthenticated(): bool
{
    return !empty($_SESSION['user_id']);
}

function requireRole(string|array $roles): void
{
    if (!isAuthenticated()) {
        header('Location: /login');
        exit;
    }
    $allowed = is_array($roles) ? $roles : [$roles];
    if (!in_array($_SESSION['role_code'] ?? '', $allowed, true)) {
        http_response_code(403);
        header('Content-Type: text/plain');
        echo '403 Forbidden';
        exit;
    }
}

function getSessionCompanyId(): ?int
{
    return isset($_SESSION['company_id']) ? (int)$_SESSION['company_id'] : null;
}

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

function formatFileSize(int $bytes): string
{
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' МБ';
    }
    if ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' КБ';
    }
    return $bytes . ' Б';
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
    requireRole('superadmin');
    $pageTitle = 'SUPERADMIN';
    $pageContext = 'Центральная панель управления';

    ob_start();
    require base_path('app/View/pages/superadmin_dashboard.php');
    $content = ob_get_clean();

    require base_path('app/View/layouts/main.php');
});

$router->get('/superadmin/companies', function () use ($config, $db) {
    requireRole('superadmin');
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
    requireRole('superadmin');
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
    requireRole('superadmin');
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

            ob_start();
            require base_path('app/View/pages/superadmin_company_edit.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $errors = [];
        $old = $company;
        $formError = null;

        ob_start();
        require base_path('app/View/pages/superadmin_company_edit.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
    } catch (\Exception $e) {
        $company = null;
        $errors = [];
        $old = [];
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

            ob_start();
            require base_path('app/View/pages/superadmin_company_edit.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

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
                contact_person = :contact_person,
                contact_phone = :contact_phone,
                contact_email = :contact_email,
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
            ':contact_person'   => $_POST['contact_person'] ?? null,
            ':contact_phone'    => $_POST['contact_phone'] ?? null,
            ':contact_email'    => $_POST['contact_email'] ?? null,
            ':status'           => $_POST['status'] ?? $company['status'],
            ':comments'         => $_POST['comments'] ?? null,
            ':id'               => (int) $id,
        ]);

        header('Location: /superadmin/companies/' . $id);
        exit;
    } catch (\Exception $e) {
        $company = $company ?? null;
        $errors = [];
        $old = $_POST;
        $formError = 'Ошибка сохранения: ' . $e->getMessage();

        ob_start();
        require base_path('app/View/pages/superadmin_company_edit.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
    }
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
                status = :status,
                comments = :comments
             WHERE id = :id'
        );

        $update->execute([
            ':full_name' => $fullName,
            ':login'     => $login,
            ':email'     => $email !== '' ? $email : null,
            ':phone'     => trim($_POST['phone'] ?? '') ?: null,
            ':status'    => $_POST['status'] ?? $owner['status'],
            ':comments'  => trim($_POST['comments'] ?? '') ?: null,
            ':id'        => (int) $owner['id'],
        ]);

        header('Location: /superadmin/companies/' . $id . '/owner');
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

$router->get('/company/logists', function () use ($config, $db) {
    requireRole('company_owner');
    $pageTitle = 'Логисты';
    $pageContext = 'Логисты — Компания';

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

        $isLogist = ($_SESSION['role_code'] ?? '') === 'logist';
        if ($isLogist) {
            $userId = (int)$_SESSION['user_id'];
            $logistStmt = $localPdo->prepare(
                "SELECT * FROM users WHERE role_code = 'logist' AND (created_by_user_id = ? OR id IN (SELECT entity_id FROM entity_access_grants WHERE entity_type = 'logist' AND granted_to_user_id = ? AND access_level = 'view')) ORDER BY created_at DESC"
            );
            $logistStmt->execute([$userId, $userId]);
            $logists = $logistStmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $logistStmt = $localPdo->query("SELECT * FROM users WHERE role_code = 'logist' ORDER BY created_at DESC");
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
    $pageTitle = 'Создать логиста';
    $pageContext = 'Логисты — Компания';

    $companyId = (int)(getSessionCompanyId() ?? 0);

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
    requireRole('company_owner');
    $pageTitle = 'Создать логиста';
    $pageContext = 'Логисты — Компания';

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
            'INSERT INTO users (full_name, login, email, phone, password_hash, role_code, status, created_by_user_id, created_by_role)
             VALUES (:full_name, :login, :email, :phone, :password_hash, :role_code, :status, :created_by_user_id, :created_by_role)'
        );
        $insert->execute([
            ':full_name'          => $fullName,
            ':login'              => $login,
            ':email'              => $email !== '' ? $email : null,
            ':phone'              => $phone !== '' ? $phone : null,
            ':password_hash'      => $passwordHash,
            ':role_code'          => 'logist',
            ':status'             => 'active',
            ':created_by_user_id' => (int)$_SESSION['user_id'],
            ':created_by_role'    => $_SESSION['role_code'],
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

        $pageTitle = 'Логист';
        $pageContext = 'Логисты — Компания: ' . $company['name'];

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

        $logistStmt = $localPdo->prepare("SELECT * FROM users WHERE id = ? AND role_code = 'logist'");
        $logistStmt->execute([(int) $id]);
        $logist = $logistStmt->fetch(PDO::FETCH_ASSOC) ?: null;

        $pageTitle = $logist ? 'Логист: ' . $logist['full_name'] : 'Логист';
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

        $pageTitle = 'Редактировать логиста';
        $pageContext = 'Логисты — Компания: ' . $company['name'];

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

        $logistStmt = $localPdo->prepare("SELECT * FROM users WHERE id = ? AND role_code = 'logist'");
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

        $pageTitle = 'Редактировать логиста';
        $pageContext = 'Логисты — Компания: ' . $company['name'];

        if ($company['status'] !== 'active') {
            $logist = null;
            $errors = [];
            $old = $_POST;
            $formError = 'Редактирование логистов недоступно';

            ob_start();
            require base_path('app/View/pages/company_logist_edit.php');
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

        $logistStmt = $localPdo->prepare("SELECT * FROM users WHERE id = ? AND role_code = 'logist'");
        $logistStmt->execute([(int) $id]);
        $logist = $logistStmt->fetch(PDO::FETCH_ASSOC) ?: null;

        if (!$logist) {
            $errors = [];
            $old = $_POST;
            $formError = null;

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

        if ($fullName === '') {
            $errors['full_name'] = 'Обязательное поле';
        }

        if ($login === '') {
            $errors['login'] = 'Обязательное поле';
        } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $login)) {
            $errors['login'] = 'Только латинские буквы, цифры и подчёркивание';
        } else {
            $dupStmt = $localPdo->prepare("SELECT COUNT(*) FROM users WHERE login = ? AND id != ? AND role_code = 'logist'");
            $dupStmt->execute([$login, (int) $id]);
            if ($dupStmt->fetchColumn() > 0) {
                $errors['login'] = 'Логин уже используется в этой компании';
            }
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
                status = :status
             WHERE id = :id AND role_code = 'logist'"
        );

        $update->execute([
            ':full_name' => $fullName,
            ':login'     => $login,
            ':email'     => $email !== '' ? $email : null,
            ':phone'     => trim($_POST['phone'] ?? '') ?: null,
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

        $pageTitle = 'Логист';
        $pageContext = 'Логисты — Компания: ' . $company['name'];

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

        $logistStmt = $localPdo->prepare("SELECT * FROM users WHERE id = ? AND role_code = 'logist'");
        $logistStmt->execute([(int) $id]);
        $logist = $logistStmt->fetch(PDO::FETCH_ASSOC) ?: null;

        if (!$logist) {
            $pageTitle = $logist ? 'Логист: ' . $logist['full_name'] : 'Логист';
            $dbError = null;
            $passwordReset = false;
            $newPassword = null;

            ob_start();
            require base_path('app/View/pages/company_logist_view.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $pageTitle = 'Логист: ' . $logist['full_name'];

        $newPassword = generatePassword(10);
        $passwordHash = password_hash($newPassword, PASSWORD_BCRYPT);

        $update = $localPdo->prepare("UPDATE users SET password_hash = ? WHERE id = ? AND role_code = 'logist'");
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

        $update = $localPdo->prepare("UPDATE users SET status = 'archived' WHERE id = ? AND role_code = 'logist'");
        $update->execute([(int) $id]);

        header('Location: /company/logists');
        exit;
    } catch (\Exception $e) {
        header('Location: /company/logists');
        exit;
    }
});

$router->get('/company/clients', function () use ($config, $db) {
    requireRole(['company_owner', 'logist']);
    $pageTitle = 'Клиенты';
    $pageContext = 'Клиенты — Компания';

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
    requireRole(['company_owner', 'logist']);
    $pageTitle = 'Создать клиента';
    $pageContext = 'Клиенты — Компания';

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
    requireRole(['company_owner', 'logist']);
    $pageTitle = 'Создать клиента';
    $pageContext = 'Клиенты — Компания';

    $companyId = (int)(getSessionCompanyId() ?? 0);
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
             contact_person, contact_phone, contact_email, status, comments, created_by_user_id, created_by_role)
             VALUES (:name, :inn, :kpp, :ogrn, :legal_address, :physical_address,
             :contact_person, :contact_phone, :contact_email, :status, :comments, :created_by_user_id, :created_by_role)'
        );
        $insert->execute([
            ':name'               => $name,
            ':inn'                => $inn,
            ':kpp'                => $kpp !== '' ? $kpp : null,
            ':ogrn'               => $ogrn !== '' ? $ogrn : null,
            ':legal_address'      => $legalAddress !== '' ? $legalAddress : null,
            ':physical_address'   => $physicalAddress !== '' ? $physicalAddress : null,
            ':contact_person'     => $contactPerson !== '' ? $contactPerson : null,
            ':contact_phone'      => $contactPhone !== '' ? $contactPhone : null,
            ':contact_email'      => $contactEmail !== '' ? $contactEmail : null,
            ':status'             => 'active',
            ':comments'           => $comments !== '' ? $comments : null,
            ':created_by_user_id' => (int)$_SESSION['user_id'],
            ':created_by_role'    => $_SESSION['role_code'],
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

$router->get('/company/clients/{id}', function ($id) use ($config, $db) {
    requireRole(['company_owner', 'logist']);

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
        $pageContext = 'Клиенты — Компания: ' . $company['name'];

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

            $logists = $localPdo->query("SELECT id, full_name, login FROM users WHERE role_code='logist' AND status='active' ORDER BY full_name")->fetchAll(PDO::FETCH_ASSOC);
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
    requireRole(['company_owner', 'logist']);

    $companyId = (int)(getSessionCompanyId() ?? 0);

    if ($companyId <= 0) {
        $company = null;
        $client = null;
        $errors = [];
        $old = [];
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
            $errors = [];
            $old = [];
            $formError = null;

            ob_start();
            require base_path('app/View/pages/company_client_edit.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $pageTitle = 'Редактировать клиента';
        $pageContext = 'Клиенты — Компания: ' . $company['name'];

        if ($company['status'] !== 'active') {
            $client = null;
            $errors = [];
            $old = [];
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

        try {
            $localPdo->query("SELECT 1 FROM clients LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $migrationSql = file_get_contents(base_path('database/migrations-local/002_create_company_clients.sql'));
            $localPdo->exec($migrationSql);
        }

        $clientStmt = $localPdo->prepare('SELECT * FROM clients WHERE id = ?');
        $clientStmt->execute([(int) $id]);
        $client = $clientStmt->fetch(PDO::FETCH_ASSOC) ?: null;

        $errors = [];
        $old = $client ?: [];
        $formError = null;

        ob_start();
        require base_path('app/View/pages/company_client_edit.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
    } catch (\Exception $e) {
        $company = $company ?? null;
        $client = null;
        $errors = [];
        $old = [];
        $formError = 'Не удалось загрузить данные: ' . $e->getMessage();

        ob_start();
        require base_path('app/View/pages/company_client_edit.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
    }
});

$router->post('/company/clients/{id}/edit', function ($id) use ($config, $db) {
    requireRole(['company_owner', 'logist']);

    $companyId = (int)(getSessionCompanyId() ?? 0);

    if ($companyId <= 0) {
        $company = null;
        $client = null;
        $errors = [];
        $old = $_POST;
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
            $errors = [];
            $old = $_POST;
            $formError = 'Компания не найдена';

            ob_start();
            require base_path('app/View/pages/company_client_edit.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $pageTitle = 'Редактировать клиента';
        $pageContext = 'Клиенты — Компания: ' . $company['name'];

        if ($company['status'] !== 'active') {
            $client = null;
            $errors = [];
            $old = $_POST;
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
            $errors = [];
            $old = $_POST;
            $formError = null;

            ob_start();
            require base_path('app/View/pages/company_client_edit.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $errors = [];
        $old = $_POST;
        $formError = null;

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
        $contactPerson = trim($_POST['contact_person'] ?? '');
        $contactPhone = trim($_POST['contact_phone'] ?? '');
        $contactEmail = trim($_POST['contact_email'] ?? '');
        $comments = trim($_POST['comments'] ?? '');

        $update = $localPdo->prepare(
            'UPDATE clients SET
                name = :name,
                inn = :inn,
                kpp = :kpp,
                ogrn = :ogrn,
                legal_address = :legal_address,
                physical_address = :physical_address,
                contact_person = :contact_person,
                contact_phone = :contact_phone,
                contact_email = :contact_email,
                status = :status,
                comments = :comments
             WHERE id = :id'
        );

        $update->execute([
            ':name'             => $name,
            ':inn'              => $inn,
            ':kpp'              => $kpp !== '' ? $kpp : null,
            ':ogrn'             => $ogrn !== '' ? $ogrn : null,
            ':legal_address'    => $legalAddress !== '' ? $legalAddress : null,
            ':physical_address' => $physicalAddress !== '' ? $physicalAddress : null,
            ':contact_person'   => $contactPerson !== '' ? $contactPerson : null,
            ':contact_phone'    => $contactPhone !== '' ? $contactPhone : null,
            ':contact_email'    => $contactEmail !== '' ? $contactEmail : null,
            ':status'           => $_POST['status'] ?? $client['status'],
            ':comments'         => $comments !== '' ? $comments : null,
            ':id'               => (int) $id,
        ]);

        header('Location: /company/clients/' . $id);
        exit;
    } catch (\Exception $e) {
        $company = $company ?? null;
        $client = $client ?? null;
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
    requireRole(['company_owner', 'logist']);

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

$router->get('/company/contractors', function () use ($config, $db) {
    requireRole(['company_owner', 'logist']);
    $pageTitle = 'Подрядчики';
    $pageContext = 'Подрядчики — Компания';

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
                "SELECT * FROM contractors WHERE (created_by_user_id = ? OR id IN (SELECT entity_id FROM entity_access_grants WHERE entity_type = 'contractor' AND granted_to_user_id = ? AND access_level = 'view')) ORDER BY created_at DESC"
            );
            $contractorStmt->execute([$userId, $userId]);
            $contractors = $contractorStmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $contractorStmt = $localPdo->query("SELECT * FROM contractors ORDER BY created_at DESC");
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
    requireRole(['company_owner', 'logist']);
    $pageTitle = 'Создать подрядчика';
    $pageContext = 'Подрядчики — Компания';

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
    requireRole(['company_owner', 'logist']);
    $pageTitle = 'Создать подрядчика';
    $pageContext = 'Подрядчики — Компания';

    $companyId = (int)(getSessionCompanyId() ?? 0);
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
             contact_person, contact_phone, contact_email, status, comments, created_by_user_id, created_by_role)
             VALUES (:name, :inn, :kpp, :ogrn, :legal_address, :physical_address,
             :contact_person, :contact_phone, :contact_email, :status, :comments, :created_by_user_id, :created_by_role)'
        );
        $insert->execute([
            ':name'               => $name,
            ':inn'                => $inn,
            ':kpp'                => $kpp !== '' ? $kpp : null,
            ':ogrn'               => $ogrn !== '' ? $ogrn : null,
            ':legal_address'      => $legalAddress !== '' ? $legalAddress : null,
            ':physical_address'   => $physicalAddress !== '' ? $physicalAddress : null,
            ':contact_person'     => $contactPerson !== '' ? $contactPerson : null,
            ':contact_phone'      => $contactPhone !== '' ? $contactPhone : null,
            ':contact_email'      => $contactEmail !== '' ? $contactEmail : null,
            ':status'             => 'active',
            ':comments'           => $comments !== '' ? $comments : null,
            ':created_by_user_id' => (int)$_SESSION['user_id'],
            ':created_by_role'    => $_SESSION['role_code'],
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

$router->get('/company/contractors/{id}', function ($id) use ($config, $db) {
    requireRole(['company_owner', 'logist']);
    $pageTitle = 'Подрядчик';
    $pageContext = 'Подрядчики — Компания';

    $companyId = (int)(getSessionCompanyId() ?? 0);
    $archiveError = null;
    $grants = [];
    $logists = [];

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

        $pageContext = 'Подрядчики — Компания: ' . $company['name'];

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

        if ($contractor) {
            $pageTitle = 'Подрядчик: ' . $contractor['name'];
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
            $grantsStmt->execute(['contractor', (int)$id]);
            $grants = $grantsStmt->fetchAll(PDO::FETCH_ASSOC);

            $logists = $localPdo->query("SELECT id, full_name, login FROM users WHERE role_code='logist' AND status='active' ORDER BY full_name")->fetchAll(PDO::FETCH_ASSOC);
        }

        $dbError = null;
    } catch (\Exception $e) {
        $company = $company ?? null;
        $contractor = null;
        $grants = [];
        $logists = [];
        $dbError = 'Не удалось загрузить подрядчика: ' . $e->getMessage();
    }

    ob_start();
    require base_path('app/View/pages/company_contractor_view.php');
    $content = ob_get_clean();
    require base_path('app/View/layouts/main.php');
});

$router->get('/company/contractors/{id}/edit', function ($id) use ($config, $db) {
    requireRole(['company_owner', 'logist']);
    $pageTitle = 'Редактировать подрядчика';
    $pageContext = 'Подрядчики — Компания';

    $companyId = (int)(getSessionCompanyId() ?? 0);

    if ($companyId <= 0) {
        $company = null;
        $contractor = null;
        $errors = [];
        $old = [];
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
            $errors = [];
            $old = [];
            $formError = null;

            ob_start();
            require base_path('app/View/pages/company_contractor_edit.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $pageContext = 'Подрядчики — Компания: ' . $company['name'];

        if ($company['status'] !== 'active') {
            $contractor = null;
            $errors = [];
            $old = [];
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
            $old = [];
            $formError = null;

            ob_start();
            require base_path('app/View/pages/company_contractor_edit.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $errors = [];
        $old = $contractor;
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
        $formError = 'Не удалось загрузить подрядчика: ' . $e->getMessage();

        ob_start();
        require base_path('app/View/pages/company_contractor_edit.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
    }
});

$router->post('/company/contractors/{id}/edit', function ($id) use ($config, $db) {
    requireRole(['company_owner', 'logist']);
    $pageTitle = 'Редактировать подрядчика';
    $pageContext = 'Подрядчики — Компания';

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

        $pageContext = 'Подрядчики — Компания: ' . $company['name'];

        if ($company['status'] !== 'active') {
            $contractor = null;
            $errors = [];
            $old = $_POST;
            $formError = 'Редактирование подрядчиков недоступно';

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
            $formError = 'Подрядчик не найден';

            ob_start();
            require base_path('app/View/pages/company_contractor_edit.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

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
                legal_address = :legal_address,
                physical_address = :physical_address,
                contact_person = :contact_person,
                contact_phone = :contact_phone,
                contact_email = :contact_email,
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
            ':contact_person'   => $_POST['contact_person'] ?? null,
            ':contact_phone'    => $_POST['contact_phone'] ?? null,
            ':contact_email'    => $_POST['contact_email'] ?? null,
            ':status'           => $_POST['status'] ?? $contractor['status'],
            ':comments'         => $_POST['comments'] ?? null,
            ':id'               => (int) $id,
        ]);

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
    requireRole(['company_owner', 'logist']);
    $pageTitle = 'Подрядчик';
    $pageContext = 'Подрядчики — Компания';

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

        $pageContext = 'Подрядчики — Компания: ' . $company['name'];

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

        $pageTitle = 'Подрядчик: ' . $contractor['name'];

        try {
            $localPdo->query("SELECT 1 FROM crews LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $migrationSql = file_get_contents(base_path('database/migrations-local/006_create_company_crews.sql'));
            $localPdo->exec($migrationSql);
        }

        $crewCheck = $localPdo->prepare('SELECT COUNT(*) FROM crews WHERE contractor_id = ?');
        $crewCheck->execute([(int) $id]);
        if ($crewCheck->fetchColumn() > 0) {
            $archiveError = 'Подрядчик участвует в экипажах. Сначала удалите экипажи.';
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

$router->get('/company/drivers', function () use ($config, $db) {
    requireRole(['company_owner', 'logist']);
    $pageTitle = 'Водители';
    $pageContext = 'Водители — Компания';

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

        $isLogist = ($_SESSION['role_code'] ?? '') === 'logist';
        if ($isLogist) {
            $userId = (int)$_SESSION['user_id'];
            $driverStmt = $localPdo->prepare(
                "SELECT * FROM drivers WHERE (created_by_user_id = ? OR id IN (SELECT entity_id FROM entity_access_grants WHERE entity_type = 'driver' AND granted_to_user_id = ? AND access_level = 'view')) ORDER BY created_at DESC"
            );
            $driverStmt->execute([$userId, $userId]);
            $drivers = $driverStmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $driverStmt = $localPdo->query("SELECT * FROM drivers ORDER BY created_at DESC");
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
    requireRole(['company_owner', 'logist']);
    $pageTitle = 'Создать водителя';
    $pageContext = 'Водители — Компания';

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
    requireRole(['company_owner', 'logist']);
    $pageTitle = 'Создать водителя';
    $pageContext = 'Водители — Компания';

    $companyId = (int)(getSessionCompanyId() ?? 0);
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
             license_issue_date, license_expire_date, status, comments, created_by_user_id, created_by_role)
             VALUES (:full_name, :phone, :license_number, :license_category,
             :license_issue_date, :license_expire_date, :status, :comments, :created_by_user_id, :created_by_role)'
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
            ':created_by_user_id'  => (int)$_SESSION['user_id'],
            ':created_by_role'     => $_SESSION['role_code'],
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

$router->get('/company/drivers/{id}', function ($id) use ($config, $db) {
    requireRole(['company_owner', 'logist']);
    $pageTitle = 'Водитель';
    $pageContext = 'Водители — Компания';

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

        $pageContext = 'Водители — Компания: ' . $company['name'];

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

        if ($driver) {
            $pageTitle = 'Водитель: ' . $driver['full_name'];
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

            $logists = $localPdo->query("SELECT id, full_name, login FROM users WHERE role_code='logist' AND status='active' ORDER BY full_name")->fetchAll(PDO::FETCH_ASSOC);
        }

        $dbError = null;
    } catch (\Exception $e) {
        $company = $company ?? null;
        $driver = null;
        $grants = [];
        $logists = [];
        $dbError = 'Не удалось загрузить водителя: ' . $e->getMessage();
    }

    ob_start();
    require base_path('app/View/pages/company_driver_view.php');
    $content = ob_get_clean();
    require base_path('app/View/layouts/main.php');
});

$router->get('/company/drivers/{id}/edit', function ($id) use ($config, $db) {
    requireRole(['company_owner', 'logist']);
    $pageTitle = 'Редактировать водителя';
    $pageContext = 'Водители — Компания';

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

        $pageContext = 'Водители — Компания: ' . $company['name'];

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
    requireRole(['company_owner', 'logist']);
    $pageTitle = 'Редактировать водителя';
    $pageContext = 'Водители — Компания';

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

        $pageContext = 'Водители — Компания: ' . $company['name'];

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

        $errors = [];
        $old = $_POST;
        $formError = null;

        $fullName = trim($_POST['full_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');

        if ($fullName === '') {
            $errors['full_name'] = 'Обязательное поле';
        }

        if ($phone === '') {
            $errors['phone'] = 'Обязательное поле';
        } else {
            $dupStmt = $localPdo->prepare('SELECT COUNT(*) FROM drivers WHERE phone = ? AND id != ?');
            $dupStmt->execute([$phone, (int) $id]);
            if ($dupStmt->fetchColumn() > 0) {
                $errors['phone'] = 'Телефон уже используется';
            }
        }

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
                status = :status,
                comments = :comments
             WHERE id = :id'
        );

        $update->execute([
            ':full_name'           => $fullName,
            ':phone'               => $phone,
            ':license_number'      => $_POST['license_number'] ?? null,
            ':license_category'    => $_POST['license_category'] ?? null,
            ':license_issue_date'  => $_POST['license_issue_date'] ?? null,
            ':license_expire_date' => $_POST['license_expire_date'] ?? null,
            ':status'              => $_POST['status'] ?? $driver['status'],
            ':comments'            => $_POST['comments'] ?? null,
            ':id'                  => (int) $id,
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
    requireRole(['company_owner', 'logist']);
    $pageTitle = 'Водитель';
    $pageContext = 'Водители — Компания';

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

        $pageContext = 'Водители — Компания: ' . $company['name'];

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

        $pageTitle = 'Водитель: ' . $driver['full_name'];

        try {
            $localPdo->query("SELECT 1 FROM crews LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $migrationSql = file_get_contents(base_path('database/migrations-local/006_create_company_crews.sql'));
            $localPdo->exec($migrationSql);
        }

        $crewCheck = $localPdo->prepare('SELECT COUNT(*) FROM crews WHERE driver_id = ?');
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

$router->get('/company/vehicles', function () use ($config, $db) {
    requireRole(['company_owner', 'logist']);
    $pageTitle = 'Транспорт';
    $pageContext = 'Транспорт — Компания';

    $companyId = (int)(getSessionCompanyId() ?? 0);

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

        try {
            $localPdo->query("SELECT created_by_user_id FROM vehicles LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $localPdo->exec("ALTER TABLE vehicles ADD COLUMN created_by_user_id INT UNSIGNED DEFAULT NULL, ADD COLUMN created_by_role VARCHAR(20) DEFAULT NULL");
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
            $vehicleStmt = $localPdo->prepare(
                "SELECT * FROM vehicles WHERE (created_by_user_id = ? OR id IN (SELECT entity_id FROM entity_access_grants WHERE entity_type = 'vehicle' AND granted_to_user_id = ? AND access_level = 'view')) ORDER BY created_at DESC"
            );
            $vehicleStmt->execute([$userId, $userId]);
            $vehicles = $vehicleStmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $vehicleStmt = $localPdo->query("SELECT * FROM vehicles ORDER BY created_at DESC");
            $vehicles = $vehicleStmt->fetchAll(PDO::FETCH_ASSOC);
        }
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
    requireRole(['company_owner', 'logist']);
    $pageTitle = 'Добавить транспорт';
    $pageContext = 'Транспорт — Компания';

    $companyId = (int)(getSessionCompanyId() ?? 0);

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
    requireRole(['company_owner', 'logist']);
    $pageTitle = 'Добавить транспорт';
    $pageContext = 'Транспорт — Компания';

    $companyId = (int)(getSessionCompanyId() ?? 0);
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
             sts_number, pts_number, capacity_tons, volume_m3, status, comments, created_by_user_id, created_by_role)
             VALUES (:plate_number, :brand, :model, :vehicle_type, :vin,
             :sts_number, :pts_number, :capacity_tons, :volume_m3, :status, :comments, :created_by_user_id, :created_by_role)'
        );
        $insert->execute([
            ':plate_number'       => $plateNumber,
            ':brand'              => $brand !== '' ? $brand : null,
            ':model'              => $model !== '' ? $model : null,
            ':vehicle_type'       => $vehicleType !== '' ? $vehicleType : null,
            ':vin'                => $vin !== '' ? $vin : null,
            ':sts_number'         => $stsNumber !== '' ? $stsNumber : null,
            ':pts_number'         => $ptsNumber !== '' ? $ptsNumber : null,
            ':capacity_tons'      => $capacityTons !== '' ? $capacityTons : null,
            ':volume_m3'          => $volumeM3 !== '' ? $volumeM3 : null,
            ':status'             => 'active',
            ':comments'           => $comments !== '' ? $comments : null,
            ':created_by_user_id' => (int)$_SESSION['user_id'],
            ':created_by_role'    => $_SESSION['role_code'],
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

// --- Vehicle View ---
$router->get('/company/vehicles/{id}', function ($vehicleId) use ($config, $db) {
    requireRole(['company_owner', 'logist']);
    $vehicleId = (int)$vehicleId;
    $pageTitle = 'Транспорт';
    $pageContext = 'Транспорт — Компания';
    $entityNotFound = false;
    $grants = [];
    $logists = [];

    $companyId = (int)(getSessionCompanyId() ?? 0);

    if ($companyId <= 0) {
        $company = null;
        $vehicle = null;
        $dbError = null;

        ob_start();
        require base_path('app/View/pages/company_vehicle_view.php');
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
            $vehicle = null;
            $dbError = null;

            ob_start();
            require base_path('app/View/pages/company_vehicle_view.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $pageContext = 'Транспорт — Компания: ' . $company['name'];

        if ($company['status'] !== 'active') {
            $vehicle = null;
            $dbError = null;

            ob_start();
            require base_path('app/View/pages/company_vehicle_view.php');
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

        try {
            $localPdo->query("SELECT created_by_user_id FROM vehicles LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $localPdo->exec("ALTER TABLE vehicles ADD COLUMN created_by_user_id INT UNSIGNED DEFAULT NULL, ADD COLUMN created_by_role VARCHAR(20) DEFAULT NULL");
        }

        try {
            $localPdo->query("SELECT 1 FROM entity_access_grants LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $migrationSql = file_get_contents(base_path('database/migrations-local/009_create_entity_access_grants.sql'));
            $localPdo->exec($migrationSql);
        }

        $vStmt = $localPdo->prepare('SELECT * FROM vehicles WHERE id = ?');
        $vStmt->execute([$vehicleId]);
        $vehicle = $vStmt->fetch(PDO::FETCH_ASSOC);

        if (!$vehicle) {
            $entityNotFound = true;
        }

        $pageTitle = $vehicle ? 'Транспорт: ' . $vehicle['plate_number'] : 'Транспорт';

        $grants = [];
        $logists = [];
        if (($_SESSION['role_code'] ?? '') === 'company_owner') {
            $grantsStmt = $localPdo->prepare(
                "SELECT g.*, u.full_name AS logist_name 
                 FROM entity_access_grants g 
                 LEFT JOIN users u ON g.granted_to_user_id = u.id 
                 WHERE g.entity_type = ? AND g.entity_id = ?"
            );
            $grantsStmt->execute(['vehicle', $vehicleId]);
            $grants = $grantsStmt->fetchAll(PDO::FETCH_ASSOC);

            $logists = $localPdo->query("SELECT id, full_name, login FROM users WHERE role_code='logist' AND status='active' ORDER BY full_name")->fetchAll(PDO::FETCH_ASSOC);
        }

        $dbError = null;
    } catch (\Exception $e) {
        $company = $company ?? null;
        $vehicle = null;
        $grants = [];
        $logists = [];
        $dbError = 'Не удалось подключиться к базе данных компании.';
    }

    ob_start();
    require base_path('app/View/pages/company_vehicle_view.php');
    $content = ob_get_clean();
    require base_path('app/View/layouts/main.php');
});

// --- Vehicle Edit (form) ---
$router->get('/company/vehicles/{id}/edit', function ($vehicleId) use ($config, $db) {
    requireRole(['company_owner', 'logist']);
    $vehicleId = (int)$vehicleId;
    $pageTitle = 'Редактировать транспорт';
    $pageContext = 'Транспорт — Компания';
    $entityNotFound = false;

    $companyId = (int)(getSessionCompanyId() ?? 0);
    $success = false;
    $formError = null;
    $errors = [];

    if ($companyId <= 0) {
        $company = null;
        $vehicle = null;
        $old = [];
        $dbError = null;

        ob_start();
        require base_path('app/View/pages/company_vehicle_edit.php');
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
            $vehicle = null;
            $old = [];
            $dbError = null;

            ob_start();
            require base_path('app/View/pages/company_vehicle_edit.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $pageContext = 'Транспорт — Компания: ' . $company['name'];

        if ($company['status'] !== 'active') {
            $vehicle = null;
            $old = [];
            $dbError = null;

            ob_start();
            require base_path('app/View/pages/company_vehicle_edit.php');
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

        $vStmt = $localPdo->prepare('SELECT * FROM vehicles WHERE id = ?');
        $vStmt->execute([$vehicleId]);
        $vehicle = $vStmt->fetch(PDO::FETCH_ASSOC);

        if (!$vehicle) {
            $entityNotFound = true;
            $old = [];
        } else {
            $old = $vehicle;
        }

        $dbError = null;
    } catch (\Exception $e) {
        $company = $company ?? null;
        $vehicle = null;
        $old = [];
        $dbError = 'Не удалось подключиться к базе данных компании.';
    }

    ob_start();
    require base_path('app/View/pages/company_vehicle_edit.php');
    $content = ob_get_clean();
    require base_path('app/View/layouts/main.php');
});

// --- Vehicle Edit (handle) ---
$router->post('/company/vehicles/{id}/edit', function ($vehicleId) use ($config, $db) {
    requireRole(['company_owner', 'logist']);
    $vehicleId = (int)$vehicleId;
    $pageTitle = 'Редактировать транспорт';
    $pageContext = 'Транспорт — Компания';
    $entityNotFound = false;

    $companyId = (int)(getSessionCompanyId() ?? 0);
    $errors = [];
    $old = $_POST;
    $formError = null;
    $success = false;

    if ($companyId <= 0) {
        $company = null;
        $vehicle = null;
        $dbError = null;
        $formError = 'Компания не найдена';

        ob_start();
        require base_path('app/View/pages/company_vehicle_edit.php');
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
            $vehicle = null;
            $dbError = null;
            $formError = 'Компания не найдена';

            ob_start();
            require base_path('app/View/pages/company_vehicle_edit.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $pageContext = 'Транспорт — Компания: ' . $company['name'];

        if ($company['status'] !== 'active') {
            $vehicle = null;
            $dbError = null;
            $formError = 'Редактирование транспорта недоступно';

            ob_start();
            require base_path('app/View/pages/company_vehicle_edit.php');
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

        $vStmt = $localPdo->prepare('SELECT * FROM vehicles WHERE id = ?');
        $vStmt->execute([$vehicleId]);
        $vehicle = $vStmt->fetch(PDO::FETCH_ASSOC);

        if (!$vehicle) {
            $entityNotFound = true;
            $dbError = null;

            ob_start();
            require base_path('app/View/pages/company_vehicle_edit.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $plateNumber = trim($_POST['plate_number'] ?? '');

        if ($plateNumber === '') {
            $errors['plate_number'] = 'Обязательное поле';
        }

        if (empty($errors['plate_number'])) {
            $checkStmt = $localPdo->prepare('SELECT COUNT(*) FROM vehicles WHERE plate_number = ? AND id != ?');
            $checkStmt->execute([$plateNumber, $vehicleId]);
            if ($checkStmt->fetchColumn() > 0) {
                $errors['plate_number'] = 'Госномер уже используется в этой компании';
            }
        }

        if (!empty($errors)) {
            ob_start();
            require base_path('app/View/pages/company_vehicle_edit.php');
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
        $status = trim($_POST['status'] ?? 'active');
        $comments = trim($_POST['comments'] ?? '');

        $update = $localPdo->prepare(
            'UPDATE vehicles SET plate_number = :plate_number, brand = :brand, model = :model,
             vehicle_type = :vehicle_type, vin = :vin, sts_number = :sts_number,
             pts_number = :pts_number, capacity_tons = :capacity_tons, volume_m3 = :volume_m3,
             status = :status, comments = :comments
             WHERE id = :id'
        );
        $update->execute([
            ':plate_number'  => $plateNumber,
            ':brand'         => $brand !== '' ? $brand : null,
            ':model'         => $model !== '' ? $model : null,
            ':vehicle_type'  => $vehicleType !== '' ? $vehicleType : null,
            ':vin'           => $vin !== '' ? $vin : null,
            ':sts_number'    => $stsNumber !== '' ? $stsNumber : null,
            ':pts_number'    => $ptsNumber !== '' ? $ptsNumber : null,
            ':capacity_tons' => $capacityTons !== '' ? $capacityTons : null,
            ':volume_m3'     => $volumeM3 !== '' ? $volumeM3 : null,
            ':status'        => $status,
            ':comments'      => $comments !== '' ? $comments : null,
            ':id'            => $vehicleId,
        ]);

        $vStmt = $localPdo->prepare('SELECT * FROM vehicles WHERE id = ?');
        $vStmt->execute([$vehicleId]);
        $vehicle = $vStmt->fetch(PDO::FETCH_ASSOC);
        $success = true;
        $dbError = null;
    } catch (\Exception $e) {
        $company = $company ?? null;
        $vehicle = $vehicle ?? null;
        $dbError = null;
        $formError = 'Ошибка обновления транспорта: ' . $e->getMessage();
    }

    ob_start();
    require base_path('app/View/pages/company_vehicle_edit.php');
    $content = ob_get_clean();
    require base_path('app/View/layouts/main.php');
});

// --- Vehicle Archive ---
$router->post('/company/vehicles/{id}/archive', function ($vehicleId) use ($config, $db) {
    requireRole(['company_owner', 'logist']);
    $vehicleId = (int)$vehicleId;

    $companyId = (int)(getSessionCompanyId() ?? 0);

    if ($companyId <= 0) {
        header('Location: /company/vehicles');
        exit;
    }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company || $company['status'] !== 'active') {
            header('Location: /company/vehicles');
            exit;
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

        try {
            $localPdo->query("SELECT 1 FROM crews LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $migrationSql = file_get_contents(base_path('database/migrations-local/006_create_company_crews.sql'));
            $localPdo->exec($migrationSql);
        }

        $crewCheck = $localPdo->prepare('SELECT COUNT(*) FROM crews WHERE vehicle_id = ? AND status = ?');
        $crewCheck->execute([$vehicleId, 'active']);
        if ($crewCheck->fetchColumn() > 0) {
            $pageTitle = 'Невозможно архивировать';
            $pageContext = 'Транспорт — Компания';
            $companyError = false;
            $company = $company;
            $message = 'Транспорт используется в активных экипажах. Сначала удалите транспорт из всех экипажей.';

            ob_start();
            echo '<div class="page-head"><div><h1>' . e($pageTitle) . '</h1></div></div>';
            echo '<div class="notice warn">' . e($message) . '</div>';
            echo '<div class="form-actions"><a href="/company/vehicles/' . $vehicleId . '" class="btn btn-ghost">← К просмотру</a></div>';
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $update = $localPdo->prepare("UPDATE vehicles SET status = 'archived' WHERE id = ?");
        $update->execute([$vehicleId]);

        header('Location: /company/vehicles/' . $vehicleId);
        exit;
    } catch (\Exception $e) {
        header('Location: /company/vehicles');
        exit;
    }
});

$router->get('/company/crews', function () use ($config, $db) {
    requireRole(['company_owner', 'logist']);
    $pageTitle = 'Экипажи';
    $pageContext = 'Экипажи — Компания';

    $companyId = (int)(getSessionCompanyId() ?? 0);

    if ($companyId <= 0) {
        $company = null;
        $crews = [];
        $dbError = null;

        ob_start();
        require base_path('app/View/pages/company_crews.php');
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
            $crews = [];
            $dbError = null;

            ob_start();
            require base_path('app/View/pages/company_crews.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $pageContext = 'Экипажи — Компания: ' . $company['name'];

        if ($company['status'] !== 'active') {
            $crews = [];
            $dbError = null;

            ob_start();
            require base_path('app/View/pages/company_crews.php');
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
            $localPdo->query("SELECT 1 FROM crews LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $migrationSql = file_get_contents(base_path('database/migrations-local/006_create_company_crews.sql'));
            $localPdo->exec($migrationSql);
        }

        try {
            $localPdo->query("SELECT created_by_user_id FROM crews LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $localPdo->exec("ALTER TABLE crews ADD COLUMN created_by_user_id INT UNSIGNED DEFAULT NULL, ADD COLUMN created_by_role VARCHAR(20) DEFAULT NULL");
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
            $crewStmt = $localPdo->prepare(
                "SELECT c.*,
                        ct.name AS contractor_name,
                        v.plate_number,
                        d.full_name AS driver_name
                 FROM crews c
                 LEFT JOIN contractors ct ON c.contractor_id = ct.id
                 LEFT JOIN vehicles v ON c.vehicle_id = v.id
                 LEFT JOIN drivers d ON c.driver_id = d.id
                 WHERE (c.created_by_user_id = ? OR c.id IN (SELECT entity_id FROM entity_access_grants WHERE entity_type = 'crew' AND granted_to_user_id = ? AND access_level = 'view'))
                 ORDER BY c.created_at DESC"
            );
            $crewStmt->execute([$userId, $userId]);
            $crews = $crewStmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $crewStmt = $localPdo->query(
                "SELECT c.*,
                        ct.name AS contractor_name,
                        v.plate_number,
                        d.full_name AS driver_name
                 FROM crews c
                 LEFT JOIN contractors ct ON c.contractor_id = ct.id
                 LEFT JOIN vehicles v ON c.vehicle_id = v.id
                 LEFT JOIN drivers d ON c.driver_id = d.id
                 ORDER BY c.created_at DESC"
            );
            $crews = $crewStmt->fetchAll(PDO::FETCH_ASSOC);
        }
        $dbError = null;
    } catch (\Exception $e) {
        $company = $company ?? null;
        $crews = [];
        $dbError = 'Не удалось подключиться к базе данных компании.';
    }

    ob_start();
    require base_path('app/View/pages/company_crews.php');
    $content = ob_get_clean();
    require base_path('app/View/layouts/main.php');
});

$router->get('/company/crews/create', function () use ($config, $db) {
    requireRole(['company_owner', 'logist']);
    $pageTitle = 'Создать экипаж';
    $pageContext = 'Экипажи — Компания';

    $companyId = (int)(getSessionCompanyId() ?? 0);

    if ($companyId <= 0) {
        $company = null;
        $success = false;
        $errors = [];
        $old = [];
        $formError = null;
        $createdCrew = null;
        $blockingNotices = [];
        $contractors = [];
        $vehicles = [];
        $drivers = [];

        ob_start();
        require base_path('app/View/pages/company_crews_create.php');
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
            $createdCrew = null;
            $blockingNotices = [];
            $contractors = [];
            $vehicles = [];
            $drivers = [];

            ob_start();
            require base_path('app/View/pages/company_crews_create.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $pageContext = 'Экипажи — Компания: ' . $company['name'];

        $success = false;
        $errors = [];
        $old = [];
        $formError = null;
        $createdCrew = null;

        if ($company['status'] !== 'active') {
            $blockingNotices = [];
            $contractors = [];
            $vehicles = [];
            $drivers = [];
        } else {
            $dbIdentifier = $company['db_identifier'];
            $localDbConfig = $config['database'];
            $localDbConfig['database'] = $dbIdentifier;
            $localDb = new \App\Core\Database($localDbConfig);
            $localPdo = $localDb->connection();

            try {
                $localPdo->query("SELECT 1 FROM crews LIMIT 1")->fetch();
            } catch (\Exception $e) {
                $migrationSql = file_get_contents(base_path('database/migrations-local/006_create_company_crews.sql'));
                $localPdo->exec($migrationSql);
            }

            $contractors = $localPdo->query(
                "SELECT id, name, inn FROM contractors WHERE status = 'active' ORDER BY name"
            )->fetchAll(PDO::FETCH_ASSOC);

            $vehicles = $localPdo->query(
                "SELECT id, plate_number, brand, model FROM vehicles WHERE status = 'active' ORDER BY plate_number"
            )->fetchAll(PDO::FETCH_ASSOC);

            $drivers = $localPdo->query(
                "SELECT id, full_name, phone FROM drivers WHERE status = 'active' ORDER BY full_name"
            )->fetchAll(PDO::FETCH_ASSOC);

            $blockingNotices = [];
            if (empty($contractors)) {
                $blockingNotices[] = [
                    'message' => 'Сначала создайте подрядчика.',
                    'link' => '/company/contractors/create',
                    'action' => 'Создать подрядчика',
                ];
            }
            if (empty($vehicles)) {
                $blockingNotices[] = [
                    'message' => 'Сначала создайте транспорт.',
                    'link' => '/company/vehicles/create',
                    'action' => 'Создать транспорт',
                ];
            }
            if (empty($drivers)) {
                $blockingNotices[] = [
                    'message' => 'Сначала создайте водителя.',
                    'link' => '/company/drivers/create',
                    'action' => 'Создать водителя',
                ];
            }
        }
    } catch (\Exception $e) {
        $company = null;
        $success = false;
        $errors = [];
        $old = [];
        $formError = 'Ошибка загрузки данных: ' . $e->getMessage();
        $createdCrew = null;
        $blockingNotices = [];
        $contractors = [];
        $vehicles = [];
        $drivers = [];
    }

    ob_start();
    require base_path('app/View/pages/company_crews_create.php');
    $content = ob_get_clean();
    require base_path('app/View/layouts/main.php');
});

$router->post('/company/crews/create', function () use ($config, $db) {
    requireRole(['company_owner', 'logist']);
    $pageTitle = 'Создать экипаж';
    $pageContext = 'Экипажи — Компания';

    $companyId = (int)(getSessionCompanyId() ?? 0);
    $errors = [];
    $old = $_POST;
    $formError = null;
    $success = false;
    $createdCrew = null;
    $blockingNotices = [];

    if ($companyId <= 0) {
        $company = null;
        $contractors = [];
        $vehicles = [];
        $drivers = [];
        $formError = 'Компания не найдена';

        ob_start();
        require base_path('app/View/pages/company_crews_create.php');
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
            $vehicles = [];
            $drivers = [];
            $formError = 'Компания не найдена';

            ob_start();
            require base_path('app/View/pages/company_crews_create.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $pageContext = 'Экипажи — Компания: ' . $company['name'];

        if ($company['status'] !== 'active') {
            $contractors = [];
            $vehicles = [];
            $drivers = [];
            $formError = 'Создание экипажа недоступно';

            ob_start();
            require base_path('app/View/pages/company_crews_create.php');
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
            $localPdo->query("SELECT 1 FROM crews LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $migrationSql = file_get_contents(base_path('database/migrations-local/006_create_company_crews.sql'));
            $localPdo->exec($migrationSql);
        }

        $contractors = $localPdo->query(
            "SELECT id, name, inn FROM contractors WHERE status = 'active' ORDER BY name"
        )->fetchAll(PDO::FETCH_ASSOC);

        $vehicles = $localPdo->query(
            "SELECT id, plate_number, brand, model FROM vehicles WHERE status = 'active' ORDER BY plate_number"
        )->fetchAll(PDO::FETCH_ASSOC);

        $drivers = $localPdo->query(
            "SELECT id, full_name, phone FROM drivers WHERE status = 'active' ORDER BY full_name"
        )->fetchAll(PDO::FETCH_ASSOC);

        $blockingNotices = [];
        if (empty($contractors)) {
            $blockingNotices[] = [
                'message' => 'Сначала создайте подрядчика.',
                'link' => '/company/contractors/create',
                'action' => 'Создать подрядчика',
            ];
        }
        if (empty($vehicles)) {
            $blockingNotices[] = [
                'message' => 'Сначала создайте транспорт.',
                'link' => '/company/vehicles/create',
                'action' => 'Создать транспорт',
            ];
        }
        if (empty($drivers)) {
            $blockingNotices[] = [
                'message' => 'Сначала создайте водителя.',
                'link' => '/company/drivers/create',
                'action' => 'Создать водителя',
            ];
        }

        if (!empty($blockingNotices)) {
            ob_start();
            require base_path('app/View/pages/company_crews_create.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $contractorId = trim($_POST['contractor_id'] ?? '');
        $vehicleId = trim($_POST['vehicle_id'] ?? '');
        $driverId = trim($_POST['driver_id'] ?? '');

        if ($contractorId === '') {
            $errors['contractor_id'] = 'Выберите подрядчика';
        } else {
            $checkStmt = $localPdo->prepare('SELECT COUNT(*) FROM contractors WHERE id = ? AND status = ?');
            $checkStmt->execute([$contractorId, 'active']);
            if ($checkStmt->fetchColumn() == 0) {
                $errors['contractor_id'] = 'Подрядчик не найден';
            }
        }

        if ($vehicleId === '') {
            $errors['vehicle_id'] = 'Выберите транспорт';
        } else {
            $checkStmt = $localPdo->prepare('SELECT COUNT(*) FROM vehicles WHERE id = ? AND status = ?');
            $checkStmt->execute([$vehicleId, 'active']);
            if ($checkStmt->fetchColumn() == 0) {
                $errors['vehicle_id'] = 'Транспорт не найден';
            }
        }

        if ($driverId === '') {
            $errors['driver_id'] = 'Выберите водителя';
        } else {
            $checkStmt = $localPdo->prepare('SELECT COUNT(*) FROM drivers WHERE id = ? AND status = ?');
            $checkStmt->execute([$driverId, 'active']);
            if ($checkStmt->fetchColumn() == 0) {
                $errors['driver_id'] = 'Водитель не найден';
            }
        }

        if (empty($errors)) {
            $dupStmt = $localPdo->prepare(
                'SELECT COUNT(*) FROM crews WHERE contractor_id = ? AND vehicle_id = ? AND driver_id = ?'
            );
            $dupStmt->execute([$contractorId, $vehicleId, $driverId]);
            if ($dupStmt->fetchColumn() > 0) {
                $formError = 'Такой экипаж уже существует в этой компании';
            }
        }

        if (!empty($errors) || $formError) {
            ob_start();
            require base_path('app/View/pages/company_crews_create.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $comments = trim($_POST['comments'] ?? '');

        $insert = $localPdo->prepare(
            'INSERT INTO crews (contractor_id, vehicle_id, driver_id, status, comments, created_by_user_id, created_by_role)
             VALUES (:contractor_id, :vehicle_id, :driver_id, :status, :comments, :created_by_user_id, :created_by_role)'
        );
        $insert->execute([
            ':contractor_id'      => $contractorId,
            ':vehicle_id'         => $vehicleId,
            ':driver_id'          => $driverId,
            ':status'             => 'active',
            ':comments'           => $comments !== '' ? $comments : null,
            ':created_by_user_id' => (int)$_SESSION['user_id'],
            ':created_by_role'    => $_SESSION['role_code'],
        ]);

        $lastId = $localPdo->lastInsertId();
        $selectStmt = $localPdo->prepare(
            "SELECT c.*,
                    ct.name AS contractor_name,
                    v.plate_number,
                    d.full_name AS driver_name
             FROM crews c
             LEFT JOIN contractors ct ON c.contractor_id = ct.id
             LEFT JOIN vehicles v ON c.vehicle_id = v.id
             LEFT JOIN drivers d ON c.driver_id = d.id
             WHERE c.id = ?"
        );
        $selectStmt->execute([$lastId]);
        $createdCrew = $selectStmt->fetch(PDO::FETCH_ASSOC);
        $success = true;
    } catch (\Exception $e) {
        $company = $company ?? null;
        $contractors = $contractors ?? [];
        $vehicles = $vehicles ?? [];
        $drivers = $drivers ?? [];
        $formError = 'Ошибка создания экипажа: ' . $e->getMessage();
    }

    ob_start();
    require base_path('app/View/pages/company_crews_create.php');
    $content = ob_get_clean();
    require base_path('app/View/layouts/main.php');
});

// --- Crew View ---
$router->get('/company/crews/{id}', function ($crewId) use ($config, $db) {
    requireRole(['company_owner', 'logist']);
    $crewId = (int)$crewId;
    $pageTitle = 'Экипаж';
    $pageContext = 'Экипажи — Компания';
    $entityNotFound = false;
    $grants = [];
    $logists = [];

    $companyId = (int)(getSessionCompanyId() ?? 0);

    if ($companyId <= 0) {
        $company = null;
        $crew = null;
        $dbError = null;

        ob_start();
        require base_path('app/View/pages/company_crew_view.php');
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
            $crew = null;
            $dbError = null;

            ob_start();
            require base_path('app/View/pages/company_crew_view.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $pageContext = 'Экипажи — Компания: ' . $company['name'];

        if ($company['status'] !== 'active') {
            $crew = null;
            $dbError = null;

            ob_start();
            require base_path('app/View/pages/company_crew_view.php');
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
            $localPdo->query("SELECT 1 FROM crews LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $migrationSql = file_get_contents(base_path('database/migrations-local/006_create_company_crews.sql'));
            $localPdo->exec($migrationSql);
        }

        try {
            $localPdo->query("SELECT created_by_user_id FROM crews LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $localPdo->exec("ALTER TABLE crews ADD COLUMN created_by_user_id INT UNSIGNED DEFAULT NULL, ADD COLUMN created_by_role VARCHAR(20) DEFAULT NULL");
        }

        try {
            $localPdo->query("SELECT 1 FROM entity_access_grants LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $migrationSql = file_get_contents(base_path('database/migrations-local/009_create_entity_access_grants.sql'));
            $localPdo->exec($migrationSql);
        }

        $cStmt = $localPdo->prepare(
            "SELECT c.*,
                    ct.name AS contractor_name,
                    v.plate_number,
                    d.full_name AS driver_name
             FROM crews c
             LEFT JOIN contractors ct ON c.contractor_id = ct.id
             LEFT JOIN vehicles v ON c.vehicle_id = v.id
             LEFT JOIN drivers d ON c.driver_id = d.id
             WHERE c.id = ?"
        );
        $cStmt->execute([$crewId]);
        $crew = $cStmt->fetch(PDO::FETCH_ASSOC);

        if (!$crew) {
            $entityNotFound = true;
        }

        $pageTitle = $crew ? 'Экипаж #' . $crew['id'] : 'Экипаж';

        $grants = [];
        $logists = [];
        if (($_SESSION['role_code'] ?? '') === 'company_owner') {
            $grantsStmt = $localPdo->prepare(
                "SELECT g.*, u.full_name AS logist_name 
                 FROM entity_access_grants g 
                 LEFT JOIN users u ON g.granted_to_user_id = u.id 
                 WHERE g.entity_type = ? AND g.entity_id = ?"
            );
            $grantsStmt->execute(['crew', $crewId]);
            $grants = $grantsStmt->fetchAll(PDO::FETCH_ASSOC);

            $logists = $localPdo->query("SELECT id, full_name, login FROM users WHERE role_code='logist' AND status='active' ORDER BY full_name")->fetchAll(PDO::FETCH_ASSOC);
        }

        $dbError = null;
    } catch (\Exception $e) {
        $company = $company ?? null;
        $crew = null;
        $grants = [];
        $logists = [];
        $dbError = 'Не удалось подключиться к базе данных компании.';
    }

    ob_start();
    require base_path('app/View/pages/company_crew_view.php');
    $content = ob_get_clean();
    require base_path('app/View/layouts/main.php');
});

// --- Crew Edit (form) ---
$router->get('/company/crews/{id}/edit', function ($crewId) use ($config, $db) {
    requireRole(['company_owner', 'logist']);
    $crewId = (int)$crewId;
    $pageTitle = 'Редактировать экипаж';
    $pageContext = 'Экипажи — Компания';
    $entityNotFound = false;

    $companyId = (int)(getSessionCompanyId() ?? 0);
    $success = false;
    $formError = null;
    $errors = [];

    if ($companyId <= 0) {
        $company = null;
        $crew = null;
        $old = [];
        $dbError = null;
        $contractors = [];
        $vehicles = [];
        $drivers = [];
        $blockingNotices = [];

        ob_start();
        require base_path('app/View/pages/company_crew_edit.php');
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
            $crew = null;
            $old = [];
            $dbError = null;
            $contractors = [];
            $vehicles = [];
            $drivers = [];
            $blockingNotices = [];

            ob_start();
            require base_path('app/View/pages/company_crew_edit.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $pageContext = 'Экипажи — Компания: ' . $company['name'];

        if ($company['status'] !== 'active') {
            $crew = null;
            $old = [];
            $dbError = null;
            $contractors = [];
            $vehicles = [];
            $drivers = [];
            $blockingNotices = [];

            ob_start();
            require base_path('app/View/pages/company_crew_edit.php');
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
            $localPdo->query("SELECT 1 FROM crews LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $migrationSql = file_get_contents(base_path('database/migrations-local/006_create_company_crews.sql'));
            $localPdo->exec($migrationSql);
        }

        $cStmt = $localPdo->prepare('SELECT * FROM crews WHERE id = ?');
        $cStmt->execute([$crewId]);
        $crew = $cStmt->fetch(PDO::FETCH_ASSOC);

        if (!$crew) {
            $entityNotFound = true;
            $old = [];
            $contractors = [];
            $vehicles = [];
            $drivers = [];
            $blockingNotices = [];
        } else {
            $old = $crew;

            $contractors = $localPdo->query(
                "SELECT id, name, inn FROM contractors WHERE status = 'active' ORDER BY name"
            )->fetchAll(PDO::FETCH_ASSOC);

            $vehicles = $localPdo->query(
                "SELECT id, plate_number, brand, model FROM vehicles WHERE status = 'active' ORDER BY plate_number"
            )->fetchAll(PDO::FETCH_ASSOC);

            $drivers = $localPdo->query(
                "SELECT id, full_name, phone FROM drivers WHERE status = 'active' ORDER BY full_name"
            )->fetchAll(PDO::FETCH_ASSOC);

            $blockingNotices = [];
            if (empty($contractors)) {
                $blockingNotices[] = [
                    'message' => 'Нет активных подрядчиков.',
                    'link' => '/company/contractors/create',
                    'action' => 'Создать подрядчика',
                ];
            }
            if (empty($vehicles)) {
                $blockingNotices[] = [
                    'message' => 'Нет активного транспорта.',
                    'link' => '/company/vehicles/create',
                    'action' => 'Создать транспорт',
                ];
            }
            if (empty($drivers)) {
                $blockingNotices[] = [
                    'message' => 'Нет активных водителей.',
                    'link' => '/company/drivers/create',
                    'action' => 'Создать водителя',
                ];
            }
        }

        $dbError = null;
    } catch (\Exception $e) {
        $company = $company ?? null;
        $crew = null;
        $old = [];
        $dbError = 'Не удалось подключиться к базе данных компании.';
        $contractors = [];
        $vehicles = [];
        $drivers = [];
        $blockingNotices = [];
    }

    ob_start();
    require base_path('app/View/pages/company_crew_edit.php');
    $content = ob_get_clean();
    require base_path('app/View/layouts/main.php');
});

// --- Crew Edit (handle) ---
$router->post('/company/crews/{id}/edit', function ($crewId) use ($config, $db) {
    requireRole(['company_owner', 'logist']);
    $crewId = (int)$crewId;
    $pageTitle = 'Редактировать экипаж';
    $pageContext = 'Экипажи — Компания';
    $entityNotFound = false;

    $companyId = (int)(getSessionCompanyId() ?? 0);
    $errors = [];
    $old = $_POST;
    $formError = null;
    $success = false;

    if ($companyId <= 0) {
        $company = null;
        $crew = null;
        $dbError = null;
        $contractors = [];
        $vehicles = [];
        $drivers = [];
        $blockingNotices = [];
        $formError = 'Компания не найдена';

        ob_start();
        require base_path('app/View/pages/company_crew_edit.php');
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
            $crew = null;
            $dbError = null;
            $contractors = [];
            $vehicles = [];
            $drivers = [];
            $blockingNotices = [];
            $formError = 'Компания не найдена';

            ob_start();
            require base_path('app/View/pages/company_crew_edit.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $pageContext = 'Экипажи — Компания: ' . $company['name'];

        if ($company['status'] !== 'active') {
            $crew = null;
            $dbError = null;
            $contractors = [];
            $vehicles = [];
            $drivers = [];
            $blockingNotices = [];
            $formError = 'Редактирование экипажа недоступно';

            ob_start();
            require base_path('app/View/pages/company_crew_edit.php');
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
            $localPdo->query("SELECT 1 FROM crews LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $migrationSql = file_get_contents(base_path('database/migrations-local/006_create_company_crews.sql'));
            $localPdo->exec($migrationSql);
        }

        $contractors = $localPdo->query(
            "SELECT id, name, inn FROM contractors WHERE status = 'active' ORDER BY name"
        )->fetchAll(PDO::FETCH_ASSOC);

        $vehicles = $localPdo->query(
            "SELECT id, plate_number, brand, model FROM vehicles WHERE status = 'active' ORDER BY plate_number"
        )->fetchAll(PDO::FETCH_ASSOC);

        $drivers = $localPdo->query(
            "SELECT id, full_name, phone FROM drivers WHERE status = 'active' ORDER BY full_name"
        )->fetchAll(PDO::FETCH_ASSOC);

        $blockingNotices = [];
        if (empty($contractors)) {
            $blockingNotices[] = [
                'message' => 'Нет активных подрядчиков.',
                'link' => '/company/contractors/create',
                'action' => 'Создать подрядчика',
            ];
        }
        if (empty($vehicles)) {
            $blockingNotices[] = [
                'message' => 'Нет активного транспорта.',
                'link' => '/company/vehicles/create',
                'action' => 'Создать транспорт',
            ];
        }
        if (empty($drivers)) {
            $blockingNotices[] = [
                'message' => 'Нет активных водителей.',
                'link' => '/company/drivers/create',
                'action' => 'Создать водителя',
            ];
        }

        if (!empty($blockingNotices)) {
            $crew = null;
            $dbError = null;

            ob_start();
            require base_path('app/View/pages/company_crew_edit.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $cStmt = $localPdo->prepare('SELECT * FROM crews WHERE id = ?');
        $cStmt->execute([$crewId]);
        $crew = $cStmt->fetch(PDO::FETCH_ASSOC);

        if (!$crew) {
            $entityNotFound = true;
            $dbError = null;

            ob_start();
            require base_path('app/View/pages/company_crew_edit.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $contractorId = trim($_POST['contractor_id'] ?? '');
        $vehicleId = trim($_POST['vehicle_id'] ?? '');
        $driverId = trim($_POST['driver_id'] ?? '');

        if ($contractorId === '') {
            $errors['contractor_id'] = 'Выберите подрядчика';
        } else {
            $checkStmt = $localPdo->prepare('SELECT COUNT(*) FROM contractors WHERE id = ? AND status = ?');
            $checkStmt->execute([$contractorId, 'active']);
            if ($checkStmt->fetchColumn() == 0) {
                $errors['contractor_id'] = 'Подрядчик не найден';
            }
        }

        if ($vehicleId === '') {
            $errors['vehicle_id'] = 'Выберите транспорт';
        } else {
            $checkStmt = $localPdo->prepare('SELECT COUNT(*) FROM vehicles WHERE id = ? AND status = ?');
            $checkStmt->execute([$vehicleId, 'active']);
            if ($checkStmt->fetchColumn() == 0) {
                $errors['vehicle_id'] = 'Транспорт не найден';
            }
        }

        if ($driverId === '') {
            $errors['driver_id'] = 'Выберите водителя';
        } else {
            $checkStmt = $localPdo->prepare('SELECT COUNT(*) FROM drivers WHERE id = ? AND status = ?');
            $checkStmt->execute([$driverId, 'active']);
            if ($checkStmt->fetchColumn() == 0) {
                $errors['driver_id'] = 'Водитель не найден';
            }
        }

        if (empty($errors)) {
            $dupStmt = $localPdo->prepare(
                'SELECT COUNT(*) FROM crews WHERE contractor_id = ? AND vehicle_id = ? AND driver_id = ? AND id != ?'
            );
            $dupStmt->execute([$contractorId, $vehicleId, $driverId, $crewId]);
            if ($dupStmt->fetchColumn() > 0) {
                $formError = 'Такой экипаж уже существует в этой компании';
            }
        }

        if (!empty($errors) || $formError) {
            ob_start();
            require base_path('app/View/pages/company_crew_edit.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $status = trim($_POST['status'] ?? 'active');
        $comments = trim($_POST['comments'] ?? '');

        $update = $localPdo->prepare(
            'UPDATE crews SET contractor_id = :contractor_id, vehicle_id = :vehicle_id,
             driver_id = :driver_id, status = :status, comments = :comments
             WHERE id = :id'
        );
        $update->execute([
            ':contractor_id' => $contractorId,
            ':vehicle_id'    => $vehicleId,
            ':driver_id'     => $driverId,
            ':status'        => $status,
            ':comments'      => $comments !== '' ? $comments : null,
            ':id'            => $crewId,
        ]);

        $cStmt = $localPdo->prepare(
            "SELECT c.*,
                    ct.name AS contractor_name,
                    v.plate_number,
                    d.full_name AS driver_name
             FROM crews c
             LEFT JOIN contractors ct ON c.contractor_id = ct.id
             LEFT JOIN vehicles v ON c.vehicle_id = v.id
             LEFT JOIN drivers d ON c.driver_id = d.id
             WHERE c.id = ?"
        );
        $cStmt->execute([$crewId]);
        $crew = $cStmt->fetch(PDO::FETCH_ASSOC);
        $success = true;
        $dbError = null;
    } catch (\Exception $e) {
        $company = $company ?? null;
        $crew = $crew ?? null;
        $dbError = null;
        $contractors = $contractors ?? [];
        $vehicles = $vehicles ?? [];
        $drivers = $drivers ?? [];
        $blockingNotices = $blockingNotices ?? [];
        $formError = 'Ошибка обновления экипажа: ' . $e->getMessage();
    }

    ob_start();
    require base_path('app/View/pages/company_crew_edit.php');
    $content = ob_get_clean();
    require base_path('app/View/layouts/main.php');
});

// --- Crew Archive ---
$router->post('/company/crews/{id}/archive', function ($crewId) use ($config, $db) {
    requireRole(['company_owner', 'logist']);
    $crewId = (int)$crewId;

    $companyId = (int)(getSessionCompanyId() ?? 0);

    if ($companyId <= 0) {
        header('Location: /company/crews');
        exit;
    }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company || $company['status'] !== 'active') {
            header('Location: /company/crews');
            exit;
        }

        $dbIdentifier = $company['db_identifier'];
        $localDbConfig = $config['database'];
        $localDbConfig['database'] = $dbIdentifier;
        $localDb = new \App\Core\Database($localDbConfig);
        $localPdo = $localDb->connection();

        try {
            $localPdo->query("SELECT 1 FROM crews LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $migrationSql = file_get_contents(base_path('database/migrations-local/006_create_company_crews.sql'));
            $localPdo->exec($migrationSql);
        }

        $update = $localPdo->prepare("UPDATE crews SET status = 'archived' WHERE id = ?");
        $update->execute([$crewId]);

        header('Location: /company/crews');
        exit;
    } catch (\Exception $e) {
        header('Location: /company/crews');
        exit;
    }
});

$router->get('/company/documents', function () use ($config, $db) {
    requireRole(['company_owner', 'logist']);

    $companyId = (int)(getSessionCompanyId() ?? 0);
    $entityType = $_GET['entity_type'] ?? '';
    $entityId = (int)($_GET['entity_id'] ?? 0);

    $whitelist = ['client' => ['label' => 'Клиент', 'labelDative' => 'клиентам', 'table' => 'clients', 'backRoute' => '/company/clients'],
                   'contractor' => ['label' => 'Подрядчик', 'labelDative' => 'подрядчикам', 'table' => 'contractors', 'backRoute' => '/company/contractors'],
                   'driver' => ['label' => 'Водитель', 'labelDative' => 'водителям', 'table' => 'drivers', 'backRoute' => '/company/drivers'],
                   'vehicle' => ['label' => 'Транспорт', 'labelDative' => 'транспорту', 'table' => 'vehicles', 'backRoute' => '/company/vehicles'],
                   'crew' => ['label' => 'Экипаж', 'labelDative' => 'экипажам', 'table' => 'crews', 'backRoute' => '/company/crews']];

    if (!isset($whitelist[$entityType])) {
        $entityTypeError = true;
        $company = null;
        $documents = [];
        $entityLabel = '';
        $entityLabelDative = '';
        $entityName = '';
        $backRoute = '';
        $dbError = null;
        $entityNotFound = false;

        $pageTitle = 'Документы';
        $pageContext = 'Документы — Компания';

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
    $pageContext = $entityLabel . ' — Компания';
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

        $pageContext = $entityLabel . ' — Компания: ' . $company['name'];

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
            case 'vehicle':
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

        $docStmt = $localPdo->prepare(
            "SELECT * FROM documents WHERE entity_type = ? AND entity_id = ? AND status != 'archived' ORDER BY created_at DESC"
        );
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
    requireRole(['company_owner', 'logist']);

    $companyId = (int)(getSessionCompanyId() ?? 0);
    $entityType = $_GET['entity_type'] ?? '';
    $entityId = (int)($_GET['entity_id'] ?? 0);

    $whitelist = ['client' => ['label' => 'Клиент', 'labelDative' => 'клиентам', 'table' => 'clients', 'backRoute' => '/company/clients'],
                   'contractor' => ['label' => 'Подрядчик', 'labelDative' => 'подрядчикам', 'table' => 'contractors', 'backRoute' => '/company/contractors'],
                   'driver' => ['label' => 'Водитель', 'labelDative' => 'водителям', 'table' => 'drivers', 'backRoute' => '/company/drivers'],
                   'vehicle' => ['label' => 'Транспорт', 'labelDative' => 'транспорту', 'table' => 'vehicles', 'backRoute' => '/company/vehicles'],
                   'crew' => ['label' => 'Экипаж', 'labelDative' => 'экипажам', 'table' => 'crews', 'backRoute' => '/company/crews']];

    $replaceDocId = (int)($_GET['replace'] ?? 0);
    $replacedDoc = null;

    $pageTitle = $replaceDocId > 0 ? 'Заменить документ' : 'Загрузить документ';
    $pageContext = ($replaceDocId > 0 ? 'Замена документа — ' : 'Загрузка документа — ') . 'Компания';
    $entityTypeError = false;
    $entityNotFound = false;
    $success = false;
    $formError = null;
    $errors = [];
    $old = [];
    $createdDoc = null;
    $dbError = null;

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

        $pageContext = $entityLabel . ' — Загрузка документа — Компания: ' . $company['name'];

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
            case 'vehicle':
                $entityName = $entity['plate_number'];
                break;
            case 'crew':
                $entityName = 'Экипаж #' . $entity['id'];
                break;
            default:
                $entityName = '';
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
    requireRole(['company_owner', 'logist']);

    $companyId = (int)(getSessionCompanyId() ?? 0);
    $entityType = $_GET['entity_type'] ?? '';
    $entityId = (int)($_GET['entity_id'] ?? 0);

    $whitelist = ['client' => ['label' => 'Клиент', 'labelDative' => 'клиентам', 'table' => 'clients', 'backRoute' => '/company/clients'],
                   'contractor' => ['label' => 'Подрядчик', 'labelDative' => 'подрядчикам', 'table' => 'contractors', 'backRoute' => '/company/contractors'],
                   'driver' => ['label' => 'Водитель', 'labelDative' => 'водителям', 'table' => 'drivers', 'backRoute' => '/company/drivers'],
                   'vehicle' => ['label' => 'Транспорт', 'labelDative' => 'транспорту', 'table' => 'vehicles', 'backRoute' => '/company/vehicles'],
                   'crew' => ['label' => 'Экипаж', 'labelDative' => 'экипажам', 'table' => 'crews', 'backRoute' => '/company/crews']];

    $pageTitle = 'Загрузить документ';
    $pageContext = 'Загрузка документа — Компания';
    $entityTypeError = false;
    $entityNotFound = false;
    $success = false;
    $formError = null;
    $errors = [];
    $old = $_POST;
    $createdDoc = null;
    $dbError = null;

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

        $pageContext = $entityLabel . ' — Загрузка документа — Компания: ' . $company['name'];

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
            case 'vehicle':
                $entityName = $entity['plate_number'];
                break;
            case 'crew':
                $entityName = 'Экипаж #' . $entity['id'];
                break;
            default:
                $entityName = '';
        }

        $documentType = trim($_POST['document_type'] ?? '');

        if ($documentType === '') {
            $errors['document_type'] = 'Укажите тип документа';
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
            $allowedExt = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx', 'xls', 'xlsx'];

            if (!in_array($ext, $allowedExt, true)) {
                $errors['document_file'] = 'Недопустимый формат файла. Разрешены: PDF, JPG, PNG, DOC, DOCX, XLS, XLSX';
            }

            if (empty($errors['document_file'])) {
                $fileSize = $_FILES['document_file']['size'];
                if ($fileSize > 10 * 1024 * 1024) {
                    $errors['document_file'] = 'Размер файла превышает 10 МБ';
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
            'INSERT INTO documents (entity_type, entity_id, document_type, original_name, stored_name,
             relative_path, mime_type, file_size, status, uploaded_by_user_id, uploaded_by_role, comments, created_by_user_id, created_by_role)
             VALUES (:entity_type, :entity_id, :document_type, :original_name, :stored_name,
             :relative_path, :mime_type, :file_size, :status, :uploaded_by_user_id, :uploaded_by_role, :comments, :created_by_user_id, :created_by_role)'
        );
        $insert->execute([
            ':entity_type'         => $entityType,
            ':entity_id'           => $entityId,
            ':document_type'       => $documentType,
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
        $pageContext = $entityLabel . ' — Компания: ' . $company['name'];
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
    requireRole(['company_owner', 'logist']);

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

$router->post('/company/documents/delete', function () use ($config, $db) {
    requireRole(['company_owner', 'logist']);

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

        $docStmt = $localPdo->prepare('SELECT * FROM documents WHERE id = ?');
        $docStmt->execute([$docId]);
        $doc = $docStmt->fetch(PDO::FETCH_ASSOC);

        if (!$doc) {
            header('Location: ' . $redirect);
            exit;
        }

        $updateStmt = $localPdo->prepare('UPDATE documents SET status = :status, updated_at = NOW() WHERE id = :id');
        $updateStmt->execute([':status' => 'archived', ':id' => $docId]);

        header('Location: ' . $redirect);
        exit;
    } catch (\Exception $e) {
        header('Location: ' . $redirect);
        exit;
    }
});

$router->post('/company/documents/replace', function () use ($config, $db) {
    requireRole(['company_owner', 'logist']);

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
        $allowedExt = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx', 'xls', 'xlsx'];
        if (!in_array($ext, $allowedExt, true)) {
            header('Location: ' . $redirect);
            exit;
        }

        $fileSize = $_FILES['document_file']['size'];
        if ($fileSize > 10 * 1024 * 1024) {
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
        $errors['login'] = 'Введите логин';
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
            "SELECT * FROM company_users WHERE login = :login AND status = 'active'"
        );
        $ownerStmt->execute([':login' => $loginValue]);
        $owner = $ownerStmt->fetch(PDO::FETCH_ASSOC);

        if ($owner && password_verify($password, $owner['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int)$owner['id'];
            $_SESSION['role_code'] = 'company_owner';
            $_SESSION['company_id'] = (int)$owner['company_id'];
            $_SESSION['user_name'] = $owner['full_name'];
            header('Location: /company/dashboard');
            exit;
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

                $logistStmt = $localPdo->prepare(
                    "SELECT * FROM users WHERE login = :login AND role_code = 'logist' AND status = 'active'"
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
                $_SESSION['role_code'] = 'logist';
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
    session_start();
    header('Location: /login', true, 302);
    exit;
});

$router->get('/company/dashboard', function () use ($config, $db) {
    requireRole(['company_owner', 'logist']);

    $pageTitle = 'Компания';
    $pageContext = 'Панель управления';

    $roleCode = $_SESSION['role_code'];
    $companyId = (int)$_SESSION['company_id'];

    $companyName = null;
    $companyError = false;

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT name FROM companies WHERE id = ?');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($company) {
            $companyName = $company['name'];
        } else {
            $companyError = true;
        }
    } catch (\Exception $e) {
        $companyError = true;
    }

    ob_start();
    require base_path('app/View/pages/company_dashboard.php');
    $content = ob_get_clean();

    require base_path('app/View/layouts/main.php');
});

$router->post('/company/access-grants/grant', function () use ($config, $db) {
    requireRole('company_owner');

    $companyId = (int)(getSessionCompanyId() ?? 0);
    $entityType = trim($_POST['entity_type'] ?? '');
    $entityId = (int)($_POST['entity_id'] ?? 0);
    $grantedToUserId = (int)($_POST['granted_to_user_id'] ?? 0);
    $redirect = $_POST['redirect'] ?? '/company/dashboard';

    $allowedEntityTypes = ['client', 'contractor', 'driver', 'vehicle', 'crew'];

    if (!in_array($entityType, $allowedEntityTypes, true)) {
        header('Location: ' . $redirect);
        exit;
    }

    if ($entityId <= 0 || $grantedToUserId <= 0 || $companyId <= 0) {
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

        try {
            $localPdo->query("SELECT 1 FROM entity_access_grants LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $migrationSql = file_get_contents(base_path('database/migrations-local/009_create_entity_access_grants.sql'));
            $localPdo->exec($migrationSql);
        }

        $insert = $localPdo->prepare(
            'INSERT INTO entity_access_grants (entity_type, entity_id, granted_to_user_id, granted_by_user_id, access_level)
             VALUES (:entity_type, :entity_id, :granted_to_user_id, :granted_by_user_id, :access_level)'
        );
        $insert->execute([
            ':entity_type'        => $entityType,
            ':entity_id'          => $entityId,
            ':granted_to_user_id' => $grantedToUserId,
            ':granted_by_user_id' => (int)$_SESSION['user_id'],
            ':access_level'       => 'view',
        ]);
    } catch (\Exception $e) {
        if ($e->getCode() != 23000) {
            error_log('Access grant error: ' . $e->getMessage());
        }
    }

    header('Location: ' . $redirect);
    exit;
});

$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
