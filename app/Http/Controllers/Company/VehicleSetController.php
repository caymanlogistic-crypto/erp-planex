<?php

namespace App\Http\Controllers\Company;

use App\Core\Database;
use App\Service\VehicleSetService;

final class VehicleSetController
{
    private VehicleSetService $service;

    public function __construct(
        private readonly array $config,
        private readonly Database $db
    ) {
        $this->service = new VehicleSetService($config, $db);
    }

    public function index(): void
    { $config=$this->config; $db=$this->db; $service=$this->service; require base_path('app/Http/Controllers/Company/VehicleSetActions/index.php'); }

    public function createForm(): void
    { $config=$this->config; $db=$this->db; $service=$this->service; require base_path('app/Http/Controllers/Company/VehicleSetActions/create_form.php'); }

    public function createSubmit(): void
    { $config=$this->config; $db=$this->db; $service=$this->service; require base_path('app/Http/Controllers/Company/VehicleSetActions/create_submit.php'); }

    public function show(string $id): void
    { $config=$this->config; $db=$this->db; $service=$this->service; require base_path('app/Http/Controllers/Company/VehicleSetActions/show.php'); }

    public function modalView(string $id): void
    { $config=$this->config; $db=$this->db; $service=$this->service; require base_path('app/Http/Controllers/Company/VehicleSetActions/modal_view.php'); }

    public function modalEditForm(string $id): void
    { $config=$this->config; $db=$this->db; $service=$this->service; require base_path('app/Http/Controllers/Company/VehicleSetActions/modal_edit_form.php'); }

    public function modalEditSubmit(string $id): void
    { $config=$this->config; $db=$this->db; $service=$this->service; require base_path('app/Http/Controllers/Company/VehicleSetActions/modal_edit_submit.php'); }

    public function modalArchive(string $id): void
    { $config=$this->config; $db=$this->db; $service=$this->service; require base_path('app/Http/Controllers/Company/VehicleSetActions/modal_archive.php'); }
}
