<?php

namespace App\Http\Controllers\Company;

use App\Core\Database;
use App\Service\FinanceObligationService;
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
        $this->syncFinanceObligations();
        $config = $this->config;
        $db = $this->db;
        require base_path('app/Http/Controllers/Company/LinearTripActions/index.php');
    }

    public function createSubmit(): void
    {
        $this->normalizeSubmittedExecutorCarrier();
        $this->normalizeOptionalCargo();
        $this->ensureLegacyPaymentDueCompatibility();
        $config = $this->config;
        $db = $this->db;
        require base_path('app/Http/Controllers/Company/LinearTripActions/create_submit.php');
    }

    public function modalView(string $id): void
    {
        $this->syncFinanceObligations();
        $config = $this->config;
        $db = $this->db;
        require base_path('app/Http/Controllers/Company/LinearTripActions/modal_view.php');
    }

    public function modalEditForm(string $id): void
    {
        $this->syncFinanceObligations();
        $config = $this->config;
        $db = $this->db;
        require base_path('app/Http/Controllers/Company/LinearTripActions/modal_edit_form.php');
    }

    public function modalEditSubmit(string $id): void
    {
        $this->normalizeSubmittedExecutorCarrier();
        $this->normalizeOptionalCargo();
        $this->ensureLegacyPaymentDueCompatibility();
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

    private function syncFinanceObligations(): void
    {
        $companyId = (int)(getSessionCompanyId() ?? 0);
        if ($companyId <= 0) return;
        try {
            $centralPdo = $this->db->connection();
            $stmt = $centralPdo->prepare('SELECT * FROM companies WHERE id=? AND status=\'active\' LIMIT 1');
            $stmt->execute([$companyId]);
            $company = $stmt->fetch(\PDO::FETCH_ASSOC);
            if (!$company) return;
            $localPdo = (new Database(companyDatabaseConfig($this->config, $company)))->connection();
            applyLocalMigrations($localPdo);
            FinanceObligationService::syncAllLinearRoutes($localPdo);
        } catch (\Throwable) {
            // The trip page remains available if finance infrastructure is temporarily unavailable.
        }
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

    private function normalizeOptionalCargo(): void
    {
        if (trim((string) ($_POST['cargo_type_name'] ?? '')) === '') {
            $_POST['cargo_type_name'] = '—';
        }
    }

    private function ensureLegacyPaymentDueCompatibility(): void
    {
        $companyId = (int) (getSessionCompanyId() ?? 0);
        if ($companyId <= 0) return;

        $centralPdo = $this->db->connection();
        $companyStmt = $centralPdo->prepare('SELECT * FROM companies WHERE id = ? LIMIT 1');
        $companyStmt->execute([$companyId]);
        $company = $companyStmt->fetch(\PDO::FETCH_ASSOC);
        if (!$company || ($company['status'] ?? '') !== 'active') return;

        $localDb = new Database(companyDatabaseConfig($this->config, $company));
        $localPdo = $localDb->connection();
        $columnStmt = $localPdo->prepare(
            "SELECT IS_NULLABLE FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME='payment_due_type' LIMIT 1"
        );
        foreach (['linear_route_financial_terms', 'linear_route_payments'] as $table) {
            $columnStmt->execute([$table]);
            if ($columnStmt->fetchColumn() === 'NO') {
                $localPdo->exec("ALTER TABLE `{$table}` MODIFY COLUMN `payment_due_type` VARCHAR(100) NULL DEFAULT NULL");
            }
        }
    }
}
