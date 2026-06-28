<?php

namespace App\Http\Controllers\Company;

use App\Core\Database;
use App\Service\ContractorService;

final class ContractorController
{
    private ContractorService $service;

    public function __construct(
        private readonly array $config,
        private readonly Database $db
    ) {
        $this->service = new ContractorService($config, $db);
    }

    public function index(): void
    {
        $config = $this->config;
        $db = $this->db;
        $service = $this->service;
        require base_path('app/Http/Controllers/Company/ContractorActions/index.php');
    }

    public function createForm(): void
    {
        $config = $this->config;
        $db = $this->db;
        $service = $this->service;
        require base_path('app/Http/Controllers/Company/ContractorActions/create_form.php');
    }

    public function createSubmit(): void
    {
        $config = $this->config;
        $db = $this->db;
        $service = $this->service;
        require base_path('app/Http/Controllers/Company/ContractorActions/create_submit.php');
    }

    public function lookupInn(): void
    {
        $config = $this->config;
        $db = $this->db;
        $service = $this->service;
        require base_path('app/Http/Controllers/Company/ContractorActions/lookup_inn.php');
    }

    public function createFullForm(): void
    {
        $config = $this->config;
        $db = $this->db;
        $service = $this->service;
        require base_path('app/Http/Controllers/Company/ContractorActions/create_full_form.php');
    }

    public function createFullSubmit(): void
    {
        $config = $this->config;
        $db = $this->db;
        $service = $this->service;
        require base_path('app/Http/Controllers/Company/ContractorActions/create_full_submit.php');
    }

    public function addCrewForm(string $id): void
    {
        $config = $this->config;
        $db = $this->db;
        $service = $this->service;
        require base_path('app/Http/Controllers/Company/ContractorActions/add_crew_form.php');
    }

    public function addCrewSubmit(string $id): void
    {
        $config = $this->config;
        $db = $this->db;
        $service = $this->service;
        require base_path('app/Http/Controllers/Company/ContractorActions/add_crew_submit.php');
    }

    public function show(string $id): void
    {
        $config = $this->config;
        $db = $this->db;
        $service = $this->service;
        require base_path('app/Http/Controllers/Company/ContractorActions/show.php');
    }

    public function editForm(string $id): void
    {
        $config = $this->config;
        $db = $this->db;
        $service = $this->service;
        require base_path('app/Http/Controllers/Company/ContractorActions/edit_form.php');
    }

    public function editSubmit(string $id): void
    {
        $config = $this->config;
        $db = $this->db;
        $service = $this->service;
        require base_path('app/Http/Controllers/Company/ContractorActions/edit_submit.php');
    }

    public function archive(string $id): void
    {
        $config = $this->config;
        $db = $this->db;
        $service = $this->service;
        require base_path('app/Http/Controllers/Company/ContractorActions/archive.php');
    }

    public function modalView(string $id): void
    {
        $config = $this->config;
        $db = $this->db;
        $service = $this->service;
        require base_path('app/Http/Controllers/Company/ContractorActions/modal_view.php');
    }

    public function modalEditForm(string $id): void
    {
        $config = $this->config;
        $db = $this->db;
        $service = $this->service;
        require base_path('app/Http/Controllers/Company/ContractorActions/modal_edit_form.php');
    }

    public function modalEditSubmit(string $id): void
    {
        $config = $this->config;
        $db = $this->db;
        $service = $this->service;
        require base_path('app/Http/Controllers/Company/ContractorActions/modal_edit_submit.php');
    }

    public function modalArchive(string $id): void
    {
        $config = $this->config;
        $db = $this->db;
        $service = $this->service;
        require base_path('app/Http/Controllers/Company/ContractorActions/modal_archive.php');
    }

    public function contactCreate(string $contractorId): void
    {
        $config = $this->config;
        $db = $this->db;
        $service = $this->service;
        require base_path('app/Http/Controllers/Company/ContractorActions/contact_create.php');
    }

    public function contactEdit(string $contractorId, string $contactId): void
    {
        $config = $this->config;
        $db = $this->db;
        $service = $this->service;
        require base_path('app/Http/Controllers/Company/ContractorActions/contact_edit.php');
    }

    public function contactDelete(string $contractorId, string $contactId): void
    {
        $config = $this->config;
        $db = $this->db;
        $service = $this->service;
        require base_path('app/Http/Controllers/Company/ContractorActions/contact_delete.php');
    }

    public function contactSetPrimary(string $contractorId, string $contactId): void
    {
        $config = $this->config;
        $db = $this->db;
        $service = $this->service;
        require base_path('app/Http/Controllers/Company/ContractorActions/contact_set_primary.php');
    }

    public function contactSetDocumentEmail(string $contractorId, string $contactId): void
    {
        $config = $this->config;
        $db = $this->db;
        $service = $this->service;
        require base_path('app/Http/Controllers/Company/ContractorActions/contact_set_document_email.php');
    }

    public function taxHistoryCreate(string $contractorId): void
    {
        $config = $this->config;
        $db = $this->db;
        $service = $this->service;
        require base_path('app/Http/Controllers/Company/ContractorActions/tax_history_create.php');
    }
}
