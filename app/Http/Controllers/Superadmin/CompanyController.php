<?php

namespace App\Http\Controllers\Superadmin;

use App\Core\Database;

final class CompanyController
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

        require base_path('app/Http/Controllers/Superadmin/CompanyActions/index.php');
    }

    public function createForm(): void
    {
        $config = $this->config;

        require base_path('app/Http/Controllers/Superadmin/CompanyActions/create_form.php');
    }

    public function createSubmit(): void
    {
        $config = $this->config;
        $db = $this->db;

        require base_path('app/Http/Controllers/Superadmin/CompanyActions/create_submit.php');
    }

    public function view(string $id): void
    {
        $config = $this->config;
        $db = $this->db;

        require base_path('app/Http/Controllers/Superadmin/CompanyActions/view.php');
    }

    public function editForm(string $id): void
    {
        $config = $this->config;
        $db = $this->db;

        require base_path('app/Http/Controllers/Superadmin/CompanyActions/edit_form.php');
    }

    public function editSubmit(string $id): void
    {
        $config = $this->config;
        $db = $this->db;

        require base_path('app/Http/Controllers/Superadmin/CompanyActions/edit_submit.php');
    }
}
