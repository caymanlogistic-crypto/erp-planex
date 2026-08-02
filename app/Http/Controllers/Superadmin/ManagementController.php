<?php

namespace App\Http\Controllers\Superadmin;

use App\Core\Database;
use App\Service\SuperadminCompanyService;

final class ManagementController
{
    public function __construct(
        private readonly array $config,
        private readonly Database $db
    ) {}

    private function getSyncService(): \App\Service\UserSyncService
    {
        return new \App\Service\UserSyncService($this->config);
    }

    public function activate(string $id): void
    {
        requireRole('superadmin');
        $pdo = $this->db->connection();
        $company = SuperadminCompanyService::loadCompany($pdo, (int)$id);
        if (!$company) { http_response_code(404); echo 'Company not found'; return; }
        if ($company['status'] !== 'active') {
            $pdo->prepare('UPDATE companies SET status = ?, updated_at = NOW() WHERE id = ?')->execute(['active', (int)$id]);
        }
        redirect_to('/superadmin/companies?status_changed=1');
    }

    public function block(string $id): void
    {
        requireRole('superadmin');
        $pdo = $this->db->connection();
        $company = SuperadminCompanyService::loadCompany($pdo, (int)$id);
        if (!$company) { http_response_code(404); echo 'Company not found'; return; }
        if (in_array($company['status'], ['active', 'inactive'], true)) {
            $pdo->prepare('UPDATE companies SET status = ?, updated_at = NOW() WHERE id = ?')->execute(['blocked', (int)$id]);
        }
        redirect_to('/superadmin/companies?status_changed=1');
    }

    public function archive(string $id): void
    {
        requireRole('superadmin');
        $pdo = $this->db->connection();
        $company = SuperadminCompanyService::loadCompany($pdo, (int)$id);
        if (!$company) { http_response_code(404); echo 'Company not found'; return; }
        $pdo->prepare('UPDATE companies SET status = ?, updated_at = NOW() WHERE id = ?')->execute(['archived', (int)$id]);
        redirect_to('/superadmin/companies?status_changed=1');
    }

    public function deactivate(string $id): void
    {
        requireRole('superadmin');
        $pdo = $this->db->connection();
        $company = SuperadminCompanyService::loadCompany($pdo, (int)$id);
        if (!$company) { redirect_to('/superadmin/companies'); }
        if ($company['status'] === 'active') {
            $pdo->prepare('UPDATE companies SET status = ?, updated_at = NOW() WHERE id = ?')->execute(['inactive', (int)$id]);
        }
        redirect_to('/superadmin/companies?status_changed=1');
    }

    // --- Directory/entity browsing pages ---

    public function users(string $id): void
    {
        requireRole('superadmin'); $config = $this->config; $db = $this->db;
        require base_path('app/Http/Controllers/Superadmin/ManagementActions/users.php');
    }

    public function directories(string $id): void
    {
        requireRole('superadmin'); $config = $this->config; $db = $this->db;
        require base_path('app/Http/Controllers/Superadmin/ManagementActions/directories.php');
    }

    public function clients(string $id): void
    {
        requireRole('superadmin'); $config = $this->config; $db = $this->db;
        $entityType = 'client';
        require base_path('app/Http/Controllers/Superadmin/ManagementActions/entity_list.php');
    }

    public function contractors(string $id): void
    {
        requireRole('superadmin'); $config = $this->config; $db = $this->db;
        $entityType = 'contractor';
        require base_path('app/Http/Controllers/Superadmin/ManagementActions/entity_list.php');
    }

    public function drivers(string $id): void
    {
        requireRole('superadmin'); $config = $this->config; $db = $this->db;
        $entityType = 'driver';
        require base_path('app/Http/Controllers/Superadmin/ManagementActions/entity_list.php');
    }

    public function vehicles(string $id): void
    {
        requireRole('superadmin'); $config = $this->config; $db = $this->db;
        $entityType = 'vehicle_unit';
        require base_path('app/Http/Controllers/Superadmin/ManagementActions/entity_list.php');
    }

    public function crews(string $id): void
    {
        requireRole('superadmin'); $config = $this->config; $db = $this->db;
        $entityType = 'crew';
        require base_path('app/Http/Controllers/Superadmin/ManagementActions/entity_list.php');
    }

    public function documents(string $id): void
    {
        requireRole('superadmin'); $config = $this->config; $db = $this->db;
        require base_path('app/Http/Controllers/Superadmin/ManagementActions/documents.php');
    }

    public function documentDownload(string $companyId, string $documentId): void
    {
        requireRole('superadmin'); $config = $this->config; $db = $this->db;
        require base_path('app/Http/Controllers/Superadmin/ManagementActions/document_download.php');
    }

    public function documentView(string $companyId, string $documentId): void
    {
        requireRole('superadmin'); $config = $this->config; $db = $this->db;
        require base_path('app/Http/Controllers/Superadmin/ManagementActions/document_view.php');
    }

    public function accessGrants(string $id): void
    {
        requireRole('superadmin'); $config = $this->config; $db = $this->db;
        require base_path('app/Http/Controllers/Superadmin/ManagementActions/access_grants.php');
    }

    public function revokeGrant(string $id, string $grantId): void
    {
        requireRole('superadmin');
        $pdo = $this->db->connection();
        $pdo->prepare("UPDATE entity_access_grants SET revoked_at = NOW(), revoked_by_user_id = ? WHERE id = ? AND revoked_at IS NULL")
            ->execute([(int)($_SESSION['user_id'] ?? 0), (int)$grantId]);
        redirect_to('/superadmin/companies/' . (int)$id . '/access-grants');
    }

    // --- Logist management ---

    public function logistCreateForm(string $id): void
    {
        requireRole('superadmin'); $config = $this->config; $db = $this->db;
        $pdo = $db->connection();
        $companyId = (int)$id;
        $company = \App\Service\SuperadminCompanyService::loadCompany($pdo, $companyId);
        $pageTitle = 'Создать пользователя';
        $success = false;
        $errors = [];
        $old = [];
        $formError = $_GET['error'] ?? null;
        $newPassword = null;
        if ($formError === 'required_fields') {
            $formError = 'Заполните все обязательные поля.';
        } elseif ($formError === 'password_short') {
            $formError = 'Пароль должен быть не менее 6 символов.';
        } elseif ($formError === 'login_taken') {
            $formError = 'Логин уже используется в центральном реестре или в БД компании.';
        } elseif ($formError === 'db_error') {
            $formError = 'Ошибка подключения к БД компании. Проверьте доступность tenant БД.';
        }
        if (isset($_SESSION['_create_success'])) {
            $success = true;
            $old = $_SESSION['_create_success'];
            $newPassword = $old['password'] ?? null;
            unset($_SESSION['_create_success']);
        }
        ob_start(); require base_path('app/View/pages/superadmin_company_logist_create.php');
        $content = ob_get_clean(); require base_path('app/View/layouts/main.php');
    }

    public function logistView(string $companyId, string $userId): void
    {
        requireRole('superadmin'); $config = $this->config; $db = $this->db;
        require base_path('app/Http/Controllers/Superadmin/ManagementActions/logist_view.php');
    }

    public function logistEditForm(string $companyId, string $userId): void
    {
        requireRole('superadmin'); $config = $this->config; $db = $this->db;
        require base_path('app/Http/Controllers/Superadmin/ManagementActions/logist_edit_form.php');
    }

    public function logistEditSubmit(string $companyId, string $userId): void
    {
        requireRole('superadmin'); $config = $this->config; $db = $this->db;
        require base_path('app/Http/Controllers/Superadmin/ManagementActions/logist_edit_submit.php');
    }

    public function logistResetPassword(string $companyId, string $userId): void
    {
        requireRole('superadmin'); $config = $this->config; $db = $this->db;
        require base_path('app/Http/Controllers/Superadmin/ManagementActions/logist_reset_password.php');
    }

    public function logistActivate(string $companyId, string $userId): void
    {
        requireRole('superadmin');
        $pdo = $this->db->connection();
        $company = \App\Service\SuperadminCompanyService::loadCompany($pdo, (int)$companyId);
        if ($company) {
            $this->getSyncService()->setStatus($pdo, $company, (int)$userId, 'active');
        }
        redirect_to('/superadmin/companies/' . (int)$companyId . '/users');
    }

    public function logistBlock(string $companyId, string $userId): void
    {
        requireRole('superadmin');
        $pdo = $this->db->connection();
        $company = \App\Service\SuperadminCompanyService::loadCompany($pdo, (int)$companyId);
        if ($company) {
            $this->getSyncService()->setStatus($pdo, $company, (int)$userId, 'blocked');
        }
        redirect_to('/superadmin/companies/' . (int)$companyId . '/users');
    }

    public function logistArchive(string $companyId, string $userId): void
    {
        requireRole('superadmin');
        $pdo = $this->db->connection();
        $company = \App\Service\SuperadminCompanyService::loadCompany($pdo, (int)$companyId);
        if ($company) {
            $this->getSyncService()->setStatus($pdo, $company, (int)$userId, 'archived');
        }
        redirect_to('/superadmin/companies/' . (int)$companyId . '/users');
    }

    public function logistCreateSubmit(string $id): void
    {
        requireRole('superadmin'); $config = $this->config; $db = $this->db;
        require base_path('app/Http/Controllers/Superadmin/ManagementActions/logist_create_submit.php');
    }

    public function logistModalCreateForm(string $id): void
    {
        requireRole('superadmin'); $config = $this->config; $db = $this->db;
        $pdo = $db->connection();
        $companyId = (int)$id;
        $company = \App\Service\SuperadminCompanyService::loadCompany($pdo, $companyId);
        if (!$company) { http_response_code(404); echo '<div class="modal-body"><div class="notice warn">Компания не найдена.</div></div>'; return; }
        $errors = [];
        $old = [];
        $formError = null;
        $generatedPassword = generatePassword();
        require base_path('app/View/partials/superadmin_logist_create_form.php');
    }

    public function logistModalCreateSubmit(string $id): void
    {
        requireRole('superadmin'); $config = $this->config; $db = $this->db;
        $pdo = $db->connection();
        $companyId = (int)$id;
        $company = \App\Service\SuperadminCompanyService::loadCompany($pdo, $companyId);
        if (!$company) { echo '<div class="modal-body"><div class="notice warn">Компания не найдена.</div></div>'; return; }
        $errors = [];
        $old = $_POST;
        $formError = null;
        $generatedPassword = null;

        $fullName = trim($_POST['full_name'] ?? '');
        $login = trim($_POST['login'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $role = trim($_POST['role'] ?? 'logist');

        if ($fullName === '') { $errors['full_name'] = 'Обязательное поле'; }
        if ($login === '') { $errors['login'] = 'Обязательное поле'; }
        elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $login)) { $errors['login'] = 'Только латинские буквы, цифры и подчёркивание'; }
        else {
            $dupStmt = $pdo->prepare('SELECT COUNT(*) FROM company_users WHERE login = ?');
            $dupStmt->execute([$login]);
            if ((int)$dupStmt->fetchColumn() > 0) { $errors['login'] = 'Логин уже используется'; }
        }

        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) { $errors['email'] = 'Некорректный email'; }
        if ($password === '') { $password = generatePassword(); }
        $allowedRoles = ['logist', 'senior_logist'];
        if (!in_array($role, $allowedRoles, true)) { $errors['role'] = 'Недопустимая роль'; }

        if (empty($errors['login'])) {
            try {
                $sync = $this->getSyncService();
                $localPdo = $sync->getLocalPdo($company);
                $sync->ensureUsersTable($localPdo);
                if ($sync->loginExistsInTenant($localPdo, $login)) {
                    $errors['login'] = 'Логин уже используется в tenant БД';
                }
            } catch (\Exception $e) {
                $errors['login'] = 'Ошибка проверки tenant БД';
            }
        }

        if (!empty($errors)) {
            $generatedPassword = generatePassword();
            require base_path('app/View/partials/superadmin_logist_create_form.php');
            return;
        }

        try {
            $sync = $this->getSyncService();
            $result = $sync->createUser($pdo, $company, $fullName, $login, $password, $role, $email !== '' ? $email : null, $phone !== '' ? $phone : null);
        } catch (\Exception $e) {
            $formError = 'Ошибка создания: ' . $e->getMessage();
            $generatedPassword = generatePassword();
            require base_path('app/View/partials/superadmin_logist_create_form.php');
            return;
        }

        echo '<div class="modal-body" data-sa-logist-create-success="1">
            <div class="notice success">Пользователь успешно создан.</div>
            <dl class="kv">
                <dt>ФИО</dt>
                <dd>' . e($fullName) . '</dd>
                <dt>Логин</dt>
                <dd><code>' . e($login) . '</code></dd>
                <dt>Временный пароль</dt>
                <dd><code class="code-hi">' . e($password) . '</code></dd>
                <dt>Роль</dt>
                <dd>' . e($role === 'senior_logist' ? 'Логист+' : 'Логист') . '</dd>
            </dl>
        </div>
        <div class="modal-foot is-spaced">
            <div class="modal-foot-actions">
                <button type="button" class="btn btn-secondary" data-sa-logist-create-close>Закрыть</button>
            </div>
        </div>';
    }

    public function logistModalView(string $companyId, string $userId): void
    {
        requireRole('superadmin'); $config = $this->config; $db = $this->db;
        $cid = (int)$companyId; $uid = (int)$userId;
        $pdo = $db->connection();
        $company = \App\Service\SuperadminCompanyService::loadCompany($pdo, $cid);
        if (!$company) { http_response_code(404); echo '<div class="modal-body"><div class="notice warn">Компания не найдена.</div></div>'; return; }
        $userStmt = $pdo->prepare("SELECT * FROM company_users WHERE id=? AND company_id=?");
        $userStmt->execute([$uid, $cid]);
        $user = $userStmt->fetch(\PDO::FETCH_ASSOC);
        if (!$user) { http_response_code(404); echo '<div class="modal-body"><div class="notice warn">Пользователь не найден.</div></div>'; return; }
        $canEdit = true;
        $isOwner = false;
        require base_path('app/View/partials/superadmin_user_modal_view.php');
    }

    public function logistModalEditForm(string $companyId, string $userId): void
    {
        requireRole('superadmin'); $config = $this->config; $db = $this->db;
        $cid = (int)$companyId; $uid = (int)$userId;
        $pdo = $db->connection();
        $company = \App\Service\SuperadminCompanyService::loadCompany($pdo, $cid);
        if (!$company) { http_response_code(404); echo '<div class="modal-body"><div class="notice warn">Компания не найдена.</div></div>'; return; }
        $userStmt = $pdo->prepare("SELECT * FROM company_users WHERE id=? AND company_id=?");
        $userStmt->execute([$uid, $cid]);
        $user = $userStmt->fetch(\PDO::FETCH_ASSOC);
        if (!$user) { http_response_code(404); echo '<div class="modal-body"><div class="notice warn">Пользователь не найден.</div></div>'; return; }
        $isOwner = false;
        $errors = [];
        $old = [];
        $formError = null;
        require base_path('app/View/partials/superadmin_user_modal_edit.php');
    }

    public function logistModalEditSubmit(string $companyId, string $userId): void
    {
        requireRole('superadmin'); $config = $this->config; $db = $this->db;
        $cid = (int)$companyId; $uid = (int)$userId;
        $pdo = $db->connection();
        $company = \App\Service\SuperadminCompanyService::loadCompany($pdo, $cid);
        if (!$company) { echo '<div class="modal-body"><div class="notice warn">Компания не найдена.</div></div>'; return; }
        $userStmt = $pdo->prepare("SELECT * FROM company_users WHERE id=? AND company_id=?");
        $userStmt->execute([$uid, $cid]);
        $user = $userStmt->fetch(\PDO::FETCH_ASSOC);
        if (!$user) { echo '<div class="modal-body"><div class="notice warn">Пользователь не найден.</div></div>'; return; }

        $errors = [];
        $old = $_POST;
        $formError = null;
        $isOwner = false;

        $fullName = trim($_POST['full_name'] ?? '');
        $login = trim($_POST['login'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $role = trim($_POST['role'] ?? $user['role'] ?? 'logist');
        $status = trim($_POST['status'] ?? $user['status'] ?? 'active');
        $comments = trim($_POST['comments'] ?? '');

        if ($fullName === '') { $errors['full_name'] = 'Обязательное поле'; }
        if ($login === '') { $errors['login'] = 'Обязательное поле'; }
        elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $login)) { $errors['login'] = 'Только латинские буквы, цифры и подчёркивание'; }
        else {
            $dupStmt = $pdo->prepare('SELECT COUNT(*) FROM company_users WHERE login = ? AND id != ?');
            $dupStmt->execute([$login, $uid]);
            if ($dupStmt->fetchColumn() > 0) { $errors['login'] = 'Логин уже используется'; }
        }

        $allowedRoles = ['logist', 'senior_logist'];
        if (!in_array($role, $allowedRoles, true)) { $errors['role'] = 'Недопустимая роль'; }

        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) { $errors['email'] = 'Некорректный email'; }

        if (empty($errors['login']) && $login !== $user['login']) {
            try {
                $sync = $this->getSyncService();
                $localPdo = $sync->getLocalPdo($company);
                $sync->ensureUsersTable($localPdo);
                if ($sync->loginExistsInTenant($localPdo, $login)) {
                    $errors['login'] = 'Логин уже используется в tenant БД';
                }
            } catch (\Exception $e) {}
        }

        if (!empty($errors)) {
            require base_path('app/View/partials/superadmin_user_modal_edit.php');
            return;
        }

        try {
            $sync = $this->getSyncService();
            $sync->updateUser($pdo, $company, $uid, $fullName, $login, $email !== '' ? $email : null, $phone !== '' ? $phone : null, $role, $status, $comments ?: null);
        } catch (\Exception $e) {
            $formError = 'Ошибка обновления: ' . $e->getMessage();
            require base_path('app/View/partials/superadmin_user_modal_edit.php');
            return;
        }

        // Re-render view
        $user['full_name'] = $fullName;
        $user['login'] = $login;
        $user['email'] = $email !== '' ? $email : null;
        $user['phone'] = $phone !== '' ? $phone : null;
        $user['role'] = $role;
        $user['status'] = $status;
        $user['comments'] = $comments ?: null;
        $canEdit = true;
        require base_path('app/View/partials/superadmin_user_modal_view.php');
    }

    // --- Reconciliation ---

    public function logistReconcile(string $companyId, string $userId): void
    {
        requireRole('superadmin');
        $pdo = $this->db->connection();
        $company = \App\Service\SuperadminCompanyService::loadCompany($pdo, (int)$companyId);
        if (!$company) { http_response_code(404); echo 'Company not found'; return; }

        $sync = $this->getSyncService();
        $result = $sync->reconcileCentralUser($pdo, $company, (int)$userId);

        if ($result['success']) {
            $_SESSION['_reconcile_result'] = 'ok';
        } else {
            $_SESSION['_reconcile_result'] = $result['error'] ?? 'error';
        }
        redirect_to('/superadmin/companies/' . (int)$companyId . '/users');
    }

    public function logistReconcileList(string $id): void
    {
        requireRole('superadmin');
        $pdo = $this->db->connection();
        $company = \App\Service\SuperadminCompanyService::loadCompany($pdo, (int)$id);
        if (!$company) { http_response_code(404); echo 'Company not found'; return; }

        $sync = $this->getSyncService();
        $orphans = $sync->listCentralOnlyUsers($pdo, $company);

        header('Content-Type: application/json');
        echo json_encode($orphans);
    }
}
