<?php

namespace App\Http\Controllers\Superadmin;

use App\Core\Database;

final class CompanyOwnerController
{
    public function __construct(
        private readonly array $config,
        private readonly Database $db
    ) {
    }

    public function createForm(string $id): void
    {
        $config = $this->config;
        $db = $this->db;

        require base_path('app/Http/Controllers/Superadmin/CompanyActions/create_owner_form.php');
    }

    public function createFormModal(string $id): void
    {
        $config = $this->config;
        $db = $this->db;

        require base_path('app/Http/Controllers/Superadmin/CompanyActions/create_owner_form_modal.php');
    }

    public function createSubmit(string $id): void
    {
        $config = $this->config;
        $db = $this->db;

        require base_path('app/Http/Controllers/Superadmin/CompanyActions/create_owner_submit.php');
    }

    public function view(string $id): void
    {
        $config = $this->config;
        $db = $this->db;

        require base_path('app/Http/Controllers/Superadmin/CompanyActions/owner_view.php');
    }

    public function editForm(string $id): void
    {
        $config = $this->config;
        $db = $this->db;

        require base_path('app/Http/Controllers/Superadmin/CompanyActions/owner_edit_form.php');
    }

    public function editSubmit(string $id): void
    {
        $config = $this->config;
        $db = $this->db;

        require base_path('app/Http/Controllers/Superadmin/CompanyActions/owner_edit_submit.php');
    }

    public function resetPassword(string $id): void
    {
        $config = $this->config;
        $db = $this->db;

        require base_path('app/Http/Controllers/Superadmin/CompanyActions/owner_reset_password.php');
    }

    public function modalView(string $id): void
    {
        $config = $this->config;
        $db = $this->db;

        require base_path('app/Http/Controllers/Superadmin/CompanyActions/owner_modal_view.php');
    }

    public function modalEditForm(string $id): void
    {
        $config = $this->config;
        $db = $this->db;

        require base_path('app/Http/Controllers/Superadmin/CompanyActions/owner_modal_edit_form.php');
    }

    public function modalEditSubmit(string $id): void
    {
        $config = $this->config;
        $db = $this->db;

        require base_path('app/Http/Controllers/Superadmin/CompanyActions/owner_modal_edit_submit.php');
    }
}
