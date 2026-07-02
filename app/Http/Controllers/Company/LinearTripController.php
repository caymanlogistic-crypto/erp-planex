<?php

namespace App\Http\Controllers\Company;

use App\Core\Database;

final class LinearTripController
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

        require base_path('app/Http/Controllers/Company/LinearTripActions/index.php');
    }

    public function createSubmit(): void
    {
        $config = $this->config;
        $db = $this->db;

        require base_path('app/Http/Controllers/Company/LinearTripActions/create_submit.php');
    }

    public function modalView(string $id): void
    {
        $config = $this->config;
        $db = $this->db;

        require base_path('app/Http/Controllers/Company/LinearTripActions/modal_view.php');
    }

    public function modalEditForm(string $id): void
    {
        $config = $this->config;
        $db = $this->db;

        require base_path('app/Http/Controllers/Company/LinearTripActions/modal_edit_form.php');
    }

    public function modalEditSubmit(string $id): void
    {
        $config = $this->config;
        $db = $this->db;

        require base_path('app/Http/Controllers/Company/LinearTripActions/modal_edit_submit.php');
    }

    public function modalDelete(string $id): void
    {
        $config = $this->config;
        $db = $this->db;

        require base_path('app/Http/Controllers/Company/LinearTripActions/modal_delete.php');
    }

    public function cargoTypes(): void
    {
        $config = $this->config;
        $db = $this->db;

        require base_path('app/Http/Controllers/Company/LinearTripActions/cargo_types.php');
    }

    public function departuresPlaceholder(): void
    {
        $config = $this->config;
        $db = $this->db;

        require base_path('app/Http/Controllers/Company/LinearTripActions/departures_placeholder.php');
    }
}
