<?php

namespace App\Http\Controllers\Company;

use App\Core\Database;

final class BankFinanceController
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

        require base_path('app/Http/Controllers/Company/BankFinanceActions/index.php');
    }

    public function import(): void
    {
        $config = $this->config;
        $db = $this->db;

        require base_path('app/Http/Controllers/Company/BankFinanceActions/import.php');
    }

    public function refreshFromMail(): void
    {
        $config = $this->config;
        $db = $this->db;

        require base_path('app/Http/Controllers/Company/BankFinanceActions/refreshFromMail.php');
    }

    public function settings(): void
    {
        $config = $this->config;
        $db = $this->db;

        require base_path('app/Http/Controllers/Company/BankFinanceActions/settings.php');
    }

    public function bankStatementSettings(): void
    {
        $config = $this->config;
        $db = $this->db;

        require base_path('app/Http/Controllers/Company/BankFinanceActions/bankStatementSettings.php');
    }

    public function deleteImport(): void
    {
        $config = $this->config;
        $db = $this->db;

        require base_path('app/Http/Controllers/Company/BankFinanceActions/deleteImport.php');
    }

    public function reconciliationJson(): void
    {
        $config = $this->config;
        $db = $this->db;

        require base_path('app/Http/Controllers/Company/BankFinanceActions/reconciliationJson.php');
    }
}
