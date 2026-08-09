<?php

namespace App\Http\Controllers\Company;

use App\Core\Database;

final class RouteExecutorController
{
    public function __construct(private readonly array $config, private readonly Database $db) {}
    public function index(): void { $config=$this->config; $db=$this->db; require base_path('app/Http/Controllers/Company/RouteExecutorActions/index_v2.php'); }
    public function createForm(): void { $config=$this->config; $db=$this->db; require base_path('app/Http/Controllers/Company/RouteExecutorActions/create_form_v2.php'); }
    public function createSubmit(): void { $config=$this->config; $db=$this->db; require base_path('app/Http/Controllers/Company/RouteExecutorActions/create_submit_v2.php'); }
    public function view(string $crewId): void { $config=$this->config; $db=$this->db; require base_path('app/Http/Controllers/Company/RouteExecutorActions/view_v2.php'); }
    public function editForm(string $crewId): void { $config=$this->config; $db=$this->db; require base_path('app/Http/Controllers/Company/RouteExecutorActions/edit_form_v2.php'); }
    public function editSubmit(string $crewId): void { $config=$this->config; $db=$this->db; require base_path('app/Http/Controllers/Company/RouteExecutorActions/edit_submit_v2.php'); }
    public function archive(string $crewId): void { $config=$this->config; $db=$this->db; $id=$crewId; require base_path('app/Http/Controllers/Company/RouteExecutorActions/archive.php'); }
}
