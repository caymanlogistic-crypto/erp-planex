<?php

namespace App\Http\Controllers\Company;

use App\Core\Database;
use App\Service\ClientService;

final class ClientController
{
    private ClientService $service;

    public function __construct(
        private readonly array $config,
        private readonly Database $db
    ) {
        $this->service = new ClientService($config, $db);
    }

    public function index(): void
    {
        $config = $this->config;
        $db = $this->db;
        $service = $this->service;
        require base_path('app/Http/Controllers/Company/ClientActions/index.php');
    }

    public function createForm(): void
    {
        $config = $this->config;
        $db = $this->db;
        $service = $this->service;
        require base_path('app/Http/Controllers/Company/ClientActions/create_form.php');
    }

    public function createSubmit(): void
    {
        $config = $this->config;
        $db = $this->db;
        $service = $this->service;
        require base_path('app/Http/Controllers/Company/ClientActions/create_submit.php');
    }

    public function show(string $id): void
    {
        $config = $this->config;
        $db = $this->db;
        $service = $this->service;
        require base_path('app/Http/Controllers/Company/ClientActions/show.php');
    }

    public function editForm(string $id): void
    {
        $config = $this->config;
        $db = $this->db;
        $service = $this->service;
        require base_path('app/Http/Controllers/Company/ClientActions/edit_form.php');
    }

    public function editSubmit(string $id): void
    {
        $config = $this->config;
        $db = $this->db;
        $service = $this->service;
        require base_path('app/Http/Controllers/Company/ClientActions/edit_submit.php');
    }

    public function archive(string $id): void
    {
        $config = $this->config;
        $db = $this->db;
        $service = $this->service;
        require base_path('app/Http/Controllers/Company/ClientActions/archive.php');
    }

    public function modalView(string $id): void
    {
        $config = $this->config;
        $db = $this->db;
        $service = $this->service;
        require base_path('app/Http/Controllers/Company/ClientActions/modal_view.php');
    }

    public function modalEditForm(string $id): void
    {
        $config = $this->config;
        $db = $this->db;
        $service = $this->service;
        require base_path('app/Http/Controllers/Company/ClientActions/modal_edit_form.php');
    }

    public function modalEditSubmit(string $id): void
    {
        $config = $this->config;
        $db = $this->db;
        $service = $this->service;
        require base_path('app/Http/Controllers/Company/ClientActions/modal_edit_submit.php');
    }

    public function modalArchive(string $id): void
    {
        $config = $this->config;
        $db = $this->db;
        $service = $this->service;
        require base_path('app/Http/Controllers/Company/ClientActions/modal_archive.php');
    }
}
