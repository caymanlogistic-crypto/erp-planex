<?php

namespace App\Http\Controllers\Company;

use App\Core\Database;

final class LogistController
{
    public function __construct(
        private readonly array $config,
        private readonly Database $db
    ) {
    }

    public function index(): void
    {
        $config = $this->config;
        $db = $this->db;
        require base_path('app/Http/Controllers/Company/LogistActions/index.php');
    }

    public function createForm(): void
    {
        $config = $this->config;
        $db = $this->db;
        require base_path('app/Http/Controllers/Company/LogistActions/create_form.php');
    }

    public function createSubmit(): void
    {
        $config = $this->config;
        $db = $this->db;
        require base_path('app/Http/Controllers/Company/LogistActions/create_submit.php');
    }

    public function modalCreateForm(): void
    {
        $config = $this->config;
        $db = $this->db;
        require base_path('app/Http/Controllers/Company/LogistActions/modal_create_form.php');
    }

    public function modalCreateSubmit(): void
    {
        $config = $this->config;
        $db = $this->db;
        require base_path('app/Http/Controllers/Company/LogistActions/modal_create_submit.php');
    }

    public function view(string $id): void
    {
        $config = $this->config;
        $db = $this->db;
        require base_path('app/Http/Controllers/Company/LogistActions/view.php');
    }

    public function editForm(string $id): void
    {
        $config = $this->config;
        $db = $this->db;
        require base_path('app/Http/Controllers/Company/LogistActions/edit_form.php');
    }

    public function editSubmit(string $id): void
    {
        $config = $this->config;
        $db = $this->db;
        require base_path('app/Http/Controllers/Company/LogistActions/edit_submit.php');
    }

    public function resetPassword(string $id): void
    {
        $config = $this->config;
        $db = $this->db;
        require base_path('app/Http/Controllers/Company/LogistActions/reset_password.php');
    }

    public function modalView(string $id): void
    {
        $config = $this->config;
        $db = $this->db;
        require base_path('app/Http/Controllers/Company/LogistActions/modal_view.php');
    }

    public function modalEditForm(string $id): void
    {
        $config = $this->config;
        $db = $this->db;
        require base_path('app/Http/Controllers/Company/LogistActions/modal_edit_form.php');
    }

    public function modalEditSubmit(string $id): void
    {
        $config = $this->config;
        $db = $this->db;
        require base_path('app/Http/Controllers/Company/LogistActions/modal_edit_submit.php');
    }

    public function archive(string $id): void
    {
        $config = $this->config;
        $db = $this->db;
        require base_path('app/Http/Controllers/Company/LogistActions/archive.php');
    }
}
