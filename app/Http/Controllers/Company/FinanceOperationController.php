<?php

namespace App\Http\Controllers\Company;

use App\Core\Database;

final class FinanceOperationController
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

        require base_path('app/Http/Controllers/Company/FinanceOperationActions/index.php');
    }

    public function modalView(int $id): void
    {
        $config = $this->config;
        $db = $this->db;

        require base_path('app/Http/Controllers/Company/FinanceOperationActions/modal_view.php');
    }

    public function modalAllocateForm(int $id): void
    {
        $config = $this->config;
        $db = $this->db;

        require base_path('app/Http/Controllers/Company/FinanceOperationActions/modal_allocate_form.php');
    }

    public function allocateSubmit(int $id): void
    {
        $config = $this->config;
        $db = $this->db;

        require base_path('app/Http/Controllers/Company/FinanceOperationActions/allocate_submit.php');
    }

    public function allocationCancel(int $id): void
    {
        $config = $this->config;
        $db = $this->db;

        require base_path('app/Http/Controllers/Company/FinanceOperationActions/allocation_cancel.php');
    }

    public function routePayments(): void
    {
        $config = $this->config;
        $db = $this->db;

        require base_path('app/Http/Controllers/Company/FinanceOperationActions/route_payments.php');
    }

    public function cancel(int $id): void
    {
        $config = $this->config;
        $db = $this->db;

        require base_path('app/Http/Controllers/Company/FinanceOperationActions/cancel.php');
    }

    public function history(int $id): void
    {
        $config = $this->config;
        $db = $this->db;

        require base_path('app/Http/Controllers/Company/FinanceOperationActions/history.php');
    }
}
