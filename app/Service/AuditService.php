<?php

namespace App\Service;

use PDO;

final class AuditService
{
    public static function ensureTable(PDO $pdo): void
    {
        $sql = file_get_contents(base_path('database/migrations/009_create_deleted_entities.sql'));
        if ($sql !== false) {
            try {
                $pdo->exec($sql);
            } catch (\Exception $e) {
                error_log('AuditService::ensureTable: ' . $e->getMessage());
            }
        }
    }

    public static function recordDeletion(
        PDO $centralPdo,
        array $company,
        string $entityType,
        int $entityId,
        string $sourceTable,
        string $displayName,
        int $deletedByUserId,
        string $deletedByRole,
        ?string $deletedByName = null,
        ?string $reason = null,
        ?string $snapshotJson = null,
        int $documentsCount = 0,
        ?string $storagePathsJson = null
    ): int {
        self::ensureTable($centralPdo);
        $stmt = $centralPdo->prepare(
            'INSERT INTO deleted_entities (company_id, company_name, local_db, entity_type, entity_id, source_table,
             display_name, deleted_by_user_id, deleted_by_role, deleted_by_name, reason, snapshot_json,
             documents_count, storage_paths_json, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            (int)$company['id'],
            $company['name'] ?? null,
            $company['db_identifier'] ?? null,
            $entityType,
            $entityId,
            $sourceTable,
            $displayName,
            $deletedByUserId,
            $deletedByRole,
            $deletedByName,
            $reason,
            $snapshotJson,
            $documentsCount,
            $storagePathsJson,
            'archived',
        ]);
        return (int)$centralPdo->lastInsertId();
    }

    public static function getById(PDO $centralPdo, int $id): ?array
    {
        $stmt = $centralPdo->prepare('SELECT * FROM deleted_entities WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public static function listAll(PDO $centralPdo, array $filters = []): array
    {
        $where = [];
        $params = [];
        if (!empty($filters['company_id'])) {
            $where[] = 'company_id = ?';
            $params[] = (int)$filters['company_id'];
        }
        if (!empty($filters['entity_type'])) {
            $where[] = 'entity_type = ?';
            $params[] = $filters['entity_type'];
        }
        if (!empty($filters['exclude_entity_type'])) {
            $where[] = 'entity_type != ?';
            $params[] = $filters['exclude_entity_type'];
        }
        if (!empty($filters['status'])) {
            $where[] = 'status = ?';
            $params[] = $filters['status'];
        }
        $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $stmt = $centralPdo->prepare("SELECT * FROM deleted_entities {$whereClause} ORDER BY deleted_at DESC");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function restore(PDO $centralPdo, int $id, int $restoredByUserId, string $restoredByRole): true|string
    {
        $record = self::getById($centralPdo, $id);
        if (!$record) {
            return 'Запись не найдена.';
        }
        if ($record['status'] !== 'archived') {
            return 'Запись уже была восстановлена ранее.';
        }
        $stmt = $centralPdo->prepare(
            'UPDATE deleted_entities SET status = ?, restored_at = NOW(), restored_by_user_id = ?, restored_by_role = ? WHERE id = ?'
        );
        $stmt->execute(['restored', $restoredByUserId, $restoredByRole, $id]);
        return true;
    }
}
