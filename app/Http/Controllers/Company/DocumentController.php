<?php

namespace App\Http\Controllers\Company;

use App\Core\Database;

final class DocumentController
{
    public function __construct(
        private readonly array $config,
        private readonly Database $db
    ) {}

    public function index(): void
    { $config=$this->config; $db=$this->db; require base_path('app/Http/Controllers/Company/DocumentActions/index.php'); }

    public function uploadForm(): void
    { $config=$this->config; $db=$this->db; require base_path('app/Http/Controllers/Company/DocumentActions/upload_form.php'); }

    public function uploadSubmit(): void
    { $config=$this->config; $db=$this->db; require base_path('app/Http/Controllers/Company/DocumentActions/upload_submit.php'); }

    public function download(): void
    { $config=$this->config; $db=$this->db; require base_path('app/Http/Controllers/Company/DocumentActions/download.php'); }

    public function view(): void
    { $config=$this->config; $db=$this->db; require base_path('app/Http/Controllers/Company/DocumentActions/view.php'); }

    public function delete(): void
    { $config=$this->config; $db=$this->db; require base_path('app/Http/Controllers/Company/DocumentActions/delete.php'); }

    public function replace(): void
    { $config=$this->config; $db=$this->db; require base_path('app/Http/Controllers/Company/DocumentActions/replace.php'); }

    public function docTypesIndex(): void
    { $config=$this->config; $db=$this->db; require base_path('app/Http/Controllers/Company/DocumentActions/doc_types_index.php'); }

    public function docTypesCreateForm(): void
    { $config=$this->config; $db=$this->db; require base_path('app/Http/Controllers/Company/DocumentActions/doc_types_create_form.php'); }

    public function docTypesCreateSubmit(): void
    { $config=$this->config; $db=$this->db; require base_path('app/Http/Controllers/Company/DocumentActions/doc_types_create_submit.php'); }

    public function docTypesEditForm(string $id): void
    { $config=$this->config; $db=$this->db; require base_path('app/Http/Controllers/Company/DocumentActions/doc_types_edit_form.php'); }

    public function docTypesEditSubmit(string $id): void
    { $config=$this->config; $db=$this->db; require base_path('app/Http/Controllers/Company/DocumentActions/doc_types_edit_submit.php'); }

    public function docTypesDelete(string $id): void
    { $config=$this->config; $db=$this->db; require base_path('app/Http/Controllers/Company/DocumentActions/doc_types_delete.php'); }
}
