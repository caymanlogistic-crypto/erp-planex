<?php

namespace App\Http\Controllers\Company;

use App\Core\Database;

final class FinanceCashController
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
        require base_path('app/Http/Controllers/Company/FinanceCashActions/index.php');
    }

    public function accountCreateForm(): void
    {
        $config = $this->config;
        $db = $this->db;
        require base_path('app/Http/Controllers/Company/FinanceCashActions/account_create_form.php');
    }

    public function accountCreateSubmit(): void
    {
        $config = $this->config;
        $db = $this->db;
        require base_path('app/Http/Controllers/Company/FinanceCashActions/account_create_submit.php');
    }

    public function operationCreateForm(): void
    {
        $config = $this->config;
        $db = $this->db;
        require base_path('app/Http/Controllers/Company/FinanceCashActions/operation_create_form.php');
    }

    public function operationCreateSubmit(): void
    {
        $config = $this->config;
        $db = $this->db;
        require base_path('app/Http/Controllers/Company/FinanceCashActions/operation_create_submit.php');
    }

    public function transferCreateForm(): void
    {
        $config = $this->config;
        $db = $this->db;
        require base_path('app/Http/Controllers/Company/FinanceCashActions/transfer_create_form.php');
    }

    public function transferCreateSubmit(): void
    {
        $config = $this->config;
        $db = $this->db;
        require base_path('app/Http/Controllers/Company/FinanceCashActions/transfer_create_submit.php');
    }

    public function dispatchEmployeeSubmit(): void
    {
        $config = $this->config;
        $db = $this->db;
        require base_path('app/Http/Controllers/Company/FinanceCashActions/dispatch_employee_submit.php');
    }
}