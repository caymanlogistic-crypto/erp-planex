<?php

namespace App\Http\Controllers\Company;

use App\Core\Database;

final class FinanceMatchingRuleController
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
        require base_path('app/Http/Controllers/Company/FinanceMatchingRuleActions/index.php');
    }

    public function createForm(): void
    {
        $config = $this->config;
        $db = $this->db;
        require base_path('app/Http/Controllers/Company/FinanceMatchingRuleActions/create_form.php');
    }

    public function createSubmit(): void
    {
        $config = $this->config;
        $db = $this->db;
        require base_path('app/Http/Controllers/Company/FinanceMatchingRuleActions/create_submit.php');
    }

    public function editForm(): void
    {
        $config = $this->config;
        $db = $this->db;
        require base_path('app/Http/Controllers/Company/FinanceMatchingRuleActions/edit_form.php');
    }

    public function editSubmit(): void
    {
        $config = $this->config;
        $db = $this->db;
        require base_path('app/Http/Controllers/Company/FinanceMatchingRuleActions/edit_submit.php');
    }

    public function toggle(): void
    {
        $config = $this->config;
        $db = $this->db;
        require base_path('app/Http/Controllers/Company/FinanceMatchingRuleActions/toggle.php');
    }

    public function reorder(): void
    {
        $config = $this->config;
        $db = $this->db;
        require base_path('app/Http/Controllers/Company/FinanceMatchingRuleActions/reorder.php');
    }

    public function preview(): void
    {
        $config = $this->config;
        $db = $this->db;
        require base_path('app/Http/Controllers/Company/FinanceMatchingRuleActions/preview.php');
    }

    public function testOnTransaction(): void
    {
        $config = $this->config;
        $db = $this->db;
        require base_path('app/Http/Controllers/Company/FinanceMatchingRuleActions/test_on_transaction.php');
    }
}
