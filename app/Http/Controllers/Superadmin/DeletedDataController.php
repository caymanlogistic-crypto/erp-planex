<?php

namespace App\Http\Controllers\Superadmin;

use App\Core\Database;
use App\Service\AuditService;
use App\Service\LinearRouteArchiveService;
use App\Service\MutationErrorService;

final class DeletedDataController
{
    public function __construct(
        private readonly array $config,
        private readonly Database $db
    ) {}

    public function index(): void
    {
        requireRole('superadmin');
        $pageTitle = 'Удалённые данные';
        $pageContext = 'Система › Удалённые данные';

        $pdo = $this->db->connection();
        AuditService::ensureTable($pdo);

        $filters = [];
        if (!empty($_GET['company_id'])) {
            $filters['company_id'] = (int)$_GET['company_id'];
        }
        if (!empty($_GET['entity_type']) && $_GET['entity_type'] !== 'company') {
            $filters['entity_type'] = $_GET['entity_type'];
        }
        if (!empty($_GET['status'])) {
            $filters['status'] = $_GET['status'];
        }
        $filters['exclude_entity_type'] = 'company';

        $records = AuditService::listAll($pdo, $filters);

        $companies = $pdo->query("SELECT id, name FROM companies ORDER BY name")->fetchAll(\PDO::FETCH_ASSOC);
        $entityTypes = $pdo->query("SELECT DISTINCT entity_type FROM deleted_entities WHERE entity_type != 'company' ORDER BY entity_type")->fetchAll(\PDO::FETCH_COLUMN);

        ob_start();
        require base_path('app/View/pages/superadmin_deleted_data.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
    }

    public function view(string $id): void
    {
        requireRole('superadmin');
        $pageTitle = 'Просмотр удалённой записи';
        $pageContext = 'Система › Удалённые данные';

        $pdo = $this->db->connection();
        $record = AuditService::getById($pdo, (int)$id);

        if (!$record) {
            http_response_code(404);
            ob_start();
            echo '<div class="panel"><div class="panel-body"><div class="notice warn">Запись не найдена. <a href="/superadmin/deleted-data">← К списку</a></div></div></div>';
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        ob_start();
        require base_path('app/View/pages/superadmin_deleted_data_view.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
    }

    public function restore(string $id): void
    {
        requireRole('superadmin');
        $recordId = (int)$id;

        $pdo = $this->db->connection();
        $record = AuditService::getById($pdo, $recordId);

        if (!$record) {
            header('Location: /superadmin/deleted-data?error=not_found');
            exit;
        }

        if ($record['entity_type'] === 'company') {
            header('Location: /superadmin/deleted-data/' . $recordId . '?error=company_deletion_final');
            exit;
        }

        if ($record['status'] !== 'archived') {
            header('Location: /superadmin/deleted-data/' . $recordId . '?error=already_restored');
            exit;
        }

        $auditMarkedRestored = false;
        try {
            $companyStmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
            $companyStmt->execute([(int)$record['company_id']]);
            $company = $companyStmt->fetch(\PDO::FETCH_ASSOC);

            if (!$company) {
                header('Location: /superadmin/deleted-data/' . $recordId . '?error=company_not_found');
                exit;
            }

            $localDbConfig = companyDatabaseConfig($this->config, $company);
            $localDb = new Database($localDbConfig);
            $localPdo = $localDb->connection();

            $sourceTable = preg_replace('/[^a-z_]/', '', (string) $record['source_table']);
            if ($sourceTable === '' || $sourceTable !== (string) $record['source_table']) {
                throw new \RuntimeException('Invalid deleted entity source table.');
            }

            $entityStmt = $localPdo->prepare("SELECT * FROM `{$sourceTable}` WHERE id = ?");
            $entityStmt->execute([(int)$record['entity_id']]);
            $entity = $entityStmt->fetch(\PDO::FETCH_ASSOC);

            if (!$entity) {
                header('Location: /superadmin/deleted-data/' . $recordId . '?error=entity_not_found');
                exit;
            }

            if (empty($entity['deleted_at'])) {
                header('Location: /superadmin/deleted-data/' . $recordId . '?error=not_deleted');
                exit;
            }

            $localPdo->beginTransaction();

            $update = $localPdo->prepare(
                "UPDATE `{$sourceTable}`
                    SET deleted_at = NULL,
                        deleted_by_user_id = NULL,
                        deleted_by_role = NULL
                  WHERE id = ?
                    AND deleted_at IS NOT NULL"
            );
            $update->execute([(int)$record['entity_id']]);

            if ($update->rowCount() === 0) {
                $localPdo->rollBack();
                header('Location: /superadmin/deleted-data/' . $recordId . '?error=restore_failed');
                exit;
            }

            if (($record['entity_type'] ?? '') === 'linear_route') {
                LinearRouteArchiveService::restoreRelated(
                    $localPdo,
                    (int)$record['entity_id'],
                    $record['snapshot_json'] ?? null
                );
            }

            $uid = (int)($_SESSION['user_id'] ?? 0);
            $rl = (string)($_SESSION['role_code'] ?? '');
            $result = AuditService::restore($pdo, $recordId, $uid, $rl);

            if ($result !== true) {
                $localPdo->rollBack();
                header('Location: /superadmin/deleted-data/' . $recordId . '?error=' . urlencode($result));
                exit;
            }
            $auditMarkedRestored = true;

            $localPdo->commit();

            $entityType = preg_replace('/[^a-z_]/', '', (string) $record['entity_type']);
            $redirectPath = match($entityType) {
                'client' => '/company/clients/' . (int)$record['entity_id'],
                'contractor' => '/company/contractors/' . (int)$record['entity_id'],
                'driver' => '/company/drivers/' . (int)$record['entity_id'],
                'vehicle_unit' => '/company/vehicles/' . (int)$record['entity_id'],
                'vehicle_set' => '/company/vehicle-sets/' . (int)$record['entity_id'],
                'crew' => '/company/crews/' . (int)$record['entity_id'],
                'driver_vehicle_block' => '/company/driver-vehicle-blocks/' . (int)$record['entity_id'],
                'linear_route' => '/company/trips/linear',
                'user' => '/company/logists/' . (int)$record['entity_id'],
                default => '/company/dashboard',
            };

            header('Location: /superadmin/deleted-data/' . $recordId . '?restored=1&redirect=' . urlencode($redirectPath));
            exit;
        } catch (\Throwable $e) {
            if (isset($localPdo) && $localPdo instanceof \PDO && $localPdo->inTransaction()) {
                $localPdo->rollBack();
            }
            if ($auditMarkedRestored) {
                try {
                    $compensate = $pdo->prepare(
                        'UPDATE deleted_entities
                            SET status = ?,
                                restored_at = NULL,
                                restored_by_user_id = NULL,
                                restored_by_role = NULL
                          WHERE id = ?'
                    );
                    $compensate->execute(['archived', $recordId]);
                } catch (\Throwable) {
                }
            }

            $errorId = MutationErrorService::report($e, 'superadmin.deleted_data.restore', [
                'record_id' => $recordId,
                'company_id' => (int)($record['company_id'] ?? 0),
                'entity_type' => (string)($record['entity_type'] ?? ''),
                'entity_id' => (int)($record['entity_id'] ?? 0),
                'user_id' => (int)($_SESSION['user_id'] ?? 0),
            ]);
            $message = MutationErrorService::userMessage('восстановить запись', $errorId);
            header('Location: /superadmin/deleted-data/' . $recordId . '?error=' . urlencode($message));
            exit;
        }
    }
}
