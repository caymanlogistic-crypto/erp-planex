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
        $this->normalizeOptionalCargo();

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
        $this->normalizeOptionalCargo();

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

    /**
     * Cargo is intentionally hidden in the current linear-trip UX, while the
     * legacy schema still requires a cargo_type_id. Keep that compatibility
     * constraint internal: an omitted cargo is represented by the neutral
     * display value "—", which the registry already renders as a dash.
     */
    private function normalizeOptionalCargo(): void
    {
        if (trim((string) ($_POST['cargo_type_name'] ?? '')) === '') {
            $_POST['cargo_type_name'] = '—';
        }
    }
}
