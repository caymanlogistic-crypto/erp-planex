<?php

namespace App\Http\Controllers\Company;

use App\Core\Database;

final class FinancePaymentPlanFactController
{
    public function __construct(
        private readonly array $config,
        private readonly Database $db
    ) {}

    public function index(): void
    {
        $config = $this->config;
        $db = $this->db;
        require base_path('app/Http/Controllers/Company/FinancePaymentPlanFactActions/index.php');
    }
}
