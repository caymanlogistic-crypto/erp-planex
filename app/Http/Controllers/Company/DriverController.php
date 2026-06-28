<?php

namespace App\Http\Controllers\Company;

use App\Core\Database;
use App\Service\DriverService;

final class DriverController
{
    private DriverService $service;

    public function __construct(
        private readonly array $config,
        private readonly Database $db
    ) {
        $this->service = new DriverService($config, $db);
    }

    public function index(): void
    {
        $config = $this->config; $db = $this->db; $service = $this->service;
        require base_path('app/Http/Controllers/Company/DriverActions/index.php');
    }

    public function createForm(): void
    {
        $config = $this->config; $db = $this->db; $service = $this->service;
        require base_path('app/Http/Controllers/Company/DriverActions/create_form.php');
    }

    public function createSubmit(): void
    {
        $config = $this->config; $db = $this->db; $service = $this->service;
        require base_path('app/Http/Controllers/Company/DriverActions/create_submit.php');
    }

    public function modalCreate(): void
    {
        $config = $this->config; $db = $this->db; $service = $this->service;
        require base_path('app/Http/Controllers/Company/DriverActions/modal_create.php');
    }

    public function show(string $id): void
    {
        $config = $this->config; $db = $this->db; $service = $this->service;
        require base_path('app/Http/Controllers/Company/DriverActions/show.php');
    }

    public function editForm(string $id): void
    {
        $config = $this->config; $db = $this->db; $service = $this->service;
        require base_path('app/Http/Controllers/Company/DriverActions/edit_form.php');
    }

    public function editSubmit(string $id): void
    {
        $config = $this->config; $db = $this->db; $service = $this->service;
        require base_path('app/Http/Controllers/Company/DriverActions/edit_submit.php');
    }

    public function archive(string $id): void
    {
        $config = $this->config; $db = $this->db; $service = $this->service;
        require base_path('app/Http/Controllers/Company/DriverActions/archive.php');
    }

    public function modalView(string $id): void
    {
        $config = $this->config; $db = $this->db; $service = $this->service;
        require base_path('app/Http/Controllers/Company/DriverActions/modal_view.php');
    }

    public function modalEditForm(string $id): void
    {
        $config = $this->config; $db = $this->db; $service = $this->service;
        require base_path('app/Http/Controllers/Company/DriverActions/modal_edit_form.php');
    }

    public function modalEditSubmit(string $id): void
    {
        $config = $this->config; $db = $this->db; $service = $this->service;
        require base_path('app/Http/Controllers/Company/DriverActions/modal_edit_submit.php');
    }

    public function modalDelete(string $id): void
    {
        $config = $this->config; $db = $this->db; $service = $this->service;
        require base_path('app/Http/Controllers/Company/DriverActions/modal_delete.php');
    }

    public function phoneCreate(string $driverId): void
    {
        $config = $this->config; $db = $this->db; $service = $this->service;
        require base_path('app/Http/Controllers/Company/DriverActions/phone_create.php');
    }

    public function phoneEdit(string $driverId, string $phoneId): void
    {
        $config = $this->config; $db = $this->db; $service = $this->service;
        require base_path('app/Http/Controllers/Company/DriverActions/phone_edit.php');
    }

    public function phoneDelete(string $driverId, string $phoneId): void
    {
        $config = $this->config; $db = $this->db; $service = $this->service;
        require base_path('app/Http/Controllers/Company/DriverActions/phone_delete.php');
    }

    public function phoneSetMain(string $driverId, string $phoneId): void
    {
        $config = $this->config; $db = $this->db; $service = $this->service;
        require base_path('app/Http/Controllers/Company/DriverActions/phone_set_main.php');
    }

    public function documentUpload(string $driverId): void
    {
        $config = $this->config; $db = $this->db; $service = $this->service;
        require base_path('app/Http/Controllers/Company/DriverActions/document_upload.php');
    }

    public function documentDelete(string $driverId, string $docId): void
    {
        $config = $this->config; $db = $this->db; $service = $this->service;
        require base_path('app/Http/Controllers/Company/DriverActions/document_delete.php');
    }
}
