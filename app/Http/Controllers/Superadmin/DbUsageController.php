<?php

namespace App\Http\Controllers\Superadmin;

use App\Core\Database;

final class DbUsageController
{
    public function __construct(
        private readonly array $config,
        private readonly Database $db
    ) {}

    private static function getNoteLabel(?string $note): string
    {
        return match ($note) {
            'superadmin_create' => 'Назначена при создании экспедитора',
            'superadmin_delete' => 'Освобождена после удаления экспедитора',
            default => $note !== null ? 'Системное событие' : '—',
        };
    }

    public function index(): void
    {
        requireRole('superadmin');
        $pageTitle = 'Использование БД';
        $pageContext = 'Система › Использование БД';

        $pdo = $this->db->connection();
        ensureCompanyDbPoolUsageTable($pdo);

        $pool = parseCompanyDbPool();

        $companiesStmt = $pdo->query(
            "SELECT id, name, status, db_identifier, db_host, db_port, db_username FROM companies WHERE db_identifier IS NOT NULL AND db_identifier != ''"
        );
        $companies = $companiesStmt->fetchAll(\PDO::FETCH_ASSOC);
        $companiesByIdentifier = [];
        foreach ($companies as $c) {
            $companiesByIdentifier[$c['db_identifier']] = $c;
        }

        $usageStmt = $pdo->query("SELECT * FROM company_db_pool_usage ORDER BY db_identifier");
        $usageRows = $usageStmt->fetchAll(\PDO::FETCH_ASSOC);
        $usageByIdentifier = [];
        foreach ($usageRows as $u) {
            $usageByIdentifier[$u['db_identifier']] = $u;
        }

        $poolEntries = [];
        foreach ($pool as $entry) {
            $dbId = $entry['database'];
            $assignedCompany = $companiesByIdentifier[$dbId] ?? null;
            $usage = $usageByIdentifier[$dbId] ?? null;

            $isBusyByCompany = $assignedCompany !== null;
            $isReserved = $usage !== null && $usage['released_at'] === null;

            if ($isBusyByCompany) {
                $status = 'Занята';
                $statusClass = 'badge-warn';
                $companyId = $assignedCompany['id'];
                $companyName = $assignedCompany['name'];
                $companyStatus = $assignedCompany['status'];
            } elseif ($isReserved) {
                $status = 'Зарезервирована';
                $statusClass = 'badge-warn';
                $companyId = $usage['company_id'];
                $companyName = null;
                $companyStatus = null;
            } else {
                $status = 'Свободна';
                $statusClass = 'badge-ok';
                $companyId = null;
                $companyName = null;
                $companyStatus = null;
            }

            $stateLabel = match ($status) {
                'Свободна' => 'Готова к назначению',
                'Занята' => 'Используется компанией',
                'Зарезервирована' => 'Зарезервирована, ждёт завершения операции',
                default => '',
            };

            $poolEntries[] = [
                'db_identifier' => $dbId,
                'host' => $entry['host'] ?: '—',
                'status' => $status,
                'statusClass' => $statusClass,
                'state_label' => $stateLabel,
                'company_id' => $companyId,
                'company_name' => $companyName,
                'company_status' => $companyStatus,
                'usage_created_at' => $usage['created_at'] ?? null,
                'usage_released_at' => $usage['released_at'] ?? null,
                'usage_note' => $usage['note'] ?? null,
                'usage_note_label' => self::getNoteLabel($usage['note'] ?? null),
            ];
        }

        $totalPool = count($poolEntries);

        ob_start();
        require base_path('app/View/pages/superadmin_db_usage.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
    }
}
