<?php

namespace App\Http\Controllers\Company;

use App\Core\Database;

final class FinanceDdsCategoryController
{
    public function __construct(private readonly array $config, private readonly Database $db) {}

    public function index(): void { $config=$this->config;$db=$this->db;require base_path('app/Http/Controllers/Company/FinanceDdsCategoryActions/index.php'); }
    public function createForm(): void { $config=$this->config;$db=$this->db;require base_path('app/Http/Controllers/Company/FinanceDdsCategoryActions/create_form.php'); }
    public function createSubmit(): void { $config=$this->config;$db=$this->db;require base_path('app/Http/Controllers/Company/FinanceDdsCategoryActions/create_submit.php'); }
    public function editForm(): void { $config=$this->config;$db=$this->db;require base_path('app/Http/Controllers/Company/FinanceDdsCategoryActions/edit_form.php'); }
    public function editSubmit(): void { $config=$this->config;$db=$this->db;require base_path('app/Http/Controllers/Company/FinanceDdsCategoryActions/edit_submit.php'); }
    public function activeToggle(): void { $config=$this->config;$db=$this->db;require base_path('app/Http/Controllers/Company/FinanceDdsCategoryActions/active_toggle.php'); }
    public function deleteSubmit(): void { $config=$this->config;$db=$this->db;require base_path('app/Http/Controllers/Company/FinanceDdsCategoryActions/delete_submit.php'); }
    public function cfuSave(): void { $config=$this->config;$db=$this->db;require base_path('app/Http/Controllers/Company/FinanceDdsCategoryActions/cfu_save.php'); }
    public function cfuToggle(): void { $config=$this->config;$db=$this->db;require base_path('app/Http/Controllers/Company/FinanceDdsCategoryActions/cfu_toggle.php'); }
    public function cfuDelete(): void { $config=$this->config;$db=$this->db;require base_path('app/Http/Controllers/Company/FinanceDdsCategoryActions/cfu_delete.php'); }
    public function linksSave(): void { $config=$this->config;$db=$this->db;require base_path('app/Http/Controllers/Company/FinanceDdsCategoryActions/links_save.php'); }
}
