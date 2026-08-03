<?php

namespace App\Http\Controllers\Company;

use App\Core\Database;
use App\Service\LinearTripRequestNormalizer;

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
        $this->normalizeSubmittedExecutorCarrier();

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
        $this->normalizeSubmittedExecutorCarrier();

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

    private function normalizeSubmittedExecutorCarrier(): void
    {
        $companyId = (int) (getSessionCompanyId() ?? 0);
        $sessionUser = [
            'user_id' => (int) ($_SESSION['user_id'] ?? 0),
            'role_code' => (string) ($_SESSION['role_code'] ?? ''),
        ];

        $_POST = LinearTripRequestNormalizer::deriveCarrierFromExecutor(
            $this->config,
            $this->db,
            $companyId,
            $sessionUser,
            $_POST
        );
    }
}
