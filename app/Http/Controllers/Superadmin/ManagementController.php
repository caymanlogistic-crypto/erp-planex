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
        $pageTitle = 'Создать логиста'; $companyId = (int)$id;
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
        $pdo->prepare("UPDATE company_users SET status = 'active', updated_at = NOW() WHERE id = ? AND company_id = ?")
            ->execute([(int)$userId, (int)$companyId]);
        redirect_to('/superadmin/companies/' . (int)$companyId . '/users');
    }

    public function logistBlock(string $companyId, string $userId): void
    {
        requireRole('superadmin');
        $pdo = $this->db->connection();
        $pdo->prepare("UPDATE company_users SET status = 'blocked', updated_at = NOW() WHERE id = ? AND company_id = ?")
            ->execute([(int)$userId, (int)$companyId]);
        redirect_to('/superadmin/companies/' . (int)$companyId . '/users');
    }

    public function logistArchive(string $companyId, string $userId): void
    {
        requireRole('superadmin');
        $pdo = $this->db->connection();
        $pdo->prepare("UPDATE company_users SET status = 'archived', updated_at = NOW() WHERE id = ? AND company_id = ?")
            ->execute([(int)$userId, (int)$companyId]);
        redirect_to('/superadmin/companies/' . (int)$companyId . '/users');
    }

    public function logistCreateSubmit(string $id): void
    {
        requireRole('superadmin'); $config = $this->config; $db = $this->db;
        require base_path('app/Http/Controllers/Superadmin/ManagementActions/logist_create_submit.php');
    }
}
