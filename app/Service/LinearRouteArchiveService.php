<?php

namespace App\Service;

use PDO;
use RuntimeException;

final class LinearRouteArchiveService
{
    /** @var array<string,string> */
    private const ROUTE_TABLES = [
        'linear_route_financial_terms' => 'linear_route_id',
        'linear_route_payments' => 'linear_route_id',
        'linear_route_principals' => 'linear_route_id',
    ];

    /**
     * Archive a linear route and every currently active dependent row.
     * The returned IDs are an exact restoration allowlist for the audit snapshot.
     *
     * @return array<string,list<int>>
     */
    public static function archive(PDO $pdo, int $routeId, int $userId, string $roleCode): array
    {
        $relatedIds = self::captureRelatedIds($pdo, $routeId);

        $routeUpdate = $pdo->prepare(
            'UPDATE linear_routes
                SET deleted_at = CURRENT_TIMESTAMP,
                    deleted_by_user_id = ?,
                    deleted_by_role = ?
              WHERE id = ?
                AND deleted_at IS NULL'
        );
        $routeUpdate->execute([$userId, $roleCode, $routeId]);
        if ($routeUpdate->rowCount() !== 1) {
            throw new RuntimeException('Linear route was not archived.');
        }

        foreach (self::ROUTE_TABLES as $table => $routeColumn) {
            self::archiveIds($pdo, $table, $routeColumn, $routeId, $relatedIds[$table] ?? [], $userId, $roleCode);
        }

        self::archiveDocuments($pdo, $routeId, $relatedIds['documents'] ?? [], $userId, $roleCode);

        return $relatedIds;
    }

    /**
     * Restore only dependent rows captured in the deletion snapshot.
     * Rows deleted before the audited operation remain deleted.
     *
     * @param array<string,mixed>|string|null $snapshot
     * @return array<string,int>
     */
    public static function restoreRelated(PDO $pdo, int $routeId, array|string|null $snapshot): array
    {
        $relatedIds = self::relatedIdsFromSnapshot($snapshot);
        $restored = [];

        foreach (self::ROUTE_TABLES as $table => $routeColumn) {
            $restored[$table] = self::restoreIds(
                $pdo,
                $table,
                $routeColumn,
                $routeId,
                $relatedIds[$table] ?? []
            );
        }

        $restored['documents'] = self::restoreDocuments($pdo, $routeId, $relatedIds['documents'] ?? []);

        return $restored;
    }

    /**
     * @param array<string,mixed> $route
     * @param array<string,list<int>> $relatedIds
     */
    public static function encodeSnapshot(array $route, array $relatedIds): string
    {
        $payload = [
            'schema' => 'linear_route_archive_v1',
            'route' => $route,
            'related_ids' => self::normalizeRelatedIds($relatedIds),
        ];
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new RuntimeException('Linear route deletion snapshot could not be encoded.');
        }
        return $json;
    }

    /**
     * @param array<string,mixed>|string|null $snapshot
     * @return array<string,list<int>>
     */
    public static function relatedIdsFromSnapshot(array|string|null $snapshot): array
    {
        if (is_string($snapshot)) {
            $decoded = json_decode($snapshot, true);
            $snapshot = is_array($decoded) ? $decoded : [];
        }

        if (!is_array($snapshot)) {
            return self::normalizeRelatedIds([]);
        }

        $related = $snapshot['related_ids'] ?? [];
        return self::normalizeRelatedIds(is_array($related) ? $related : []);
    }

    /**
     * @param array<string,mixed> $relatedIds
     * @return array<string,list<int>>
     */
    public static function normalizeRelatedIds(array $relatedIds): array
    {
        $normalized = [];
        foreach ([...array_keys(self::ROUTE_TABLES), 'documents'] as $key) {
            $values = $relatedIds[$key] ?? [];
            if (!is_array($values)) {
                $values = [];
            }
            $ids = [];
            foreach ($values as $value) {
                $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                if ($id !== false) {
                    $ids[] = (int) $id;
                }
            }
            $normalized[$key] = array_values(array_unique($ids));
        }
        return $normalized;
    }

    /** @return array<string,list<int>> */
    private static function captureRelatedIds(PDO $pdo, int $routeId): array
    {
        $result = [];
        foreach (self::ROUTE_TABLES as $table => $routeColumn) {
            $result[$table] = self::fetchIds($pdo, $table, $routeColumn, $routeId);
        }
        $result['documents'] = self::fetchDocumentIds($pdo, $routeId);
        return self::normalizeRelatedIds($result);
    }

    /** @return list<int> */
    private static function fetchIds(PDO $pdo, string $table, string $routeColumn, int $routeId): array
    {
        if (!self::tableExists($pdo, $table)) {
            return [];
        }
        $stmt = $pdo->prepare(
            "SELECT id FROM `{$table}` WHERE `{$routeColumn}` = ? AND deleted_at IS NULL ORDER BY id"
        );
        $stmt->execute([$routeId]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /** @return list<int> */
    private static function fetchDocumentIds(PDO $pdo, int $routeId): array
    {
        if (!self::tableExists($pdo, 'documents')) {
            return [];
        }
        $stmt = $pdo->prepare(
            "SELECT id
               FROM documents
              WHERE entity_type = 'linear_route'
                AND entity_id = ?
                AND deleted_at IS NULL
              ORDER BY id"
        );
        $stmt->execute([$routeId]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /** @param list<int> $ids */
    private static function archiveIds(
        PDO $pdo,
        string $table,
        string $routeColumn,
        int $routeId,
        array $ids,
        int $userId,
        string $roleCode
    ): void {
        if ($ids === [] || !self::tableExists($pdo, $table)) {
            return;
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare(
            "UPDATE `{$table}`
                SET deleted_at = CURRENT_TIMESTAMP,
                    deleted_by_user_id = ?,
                    deleted_by_role = ?
              WHERE `{$routeColumn}` = ?
                AND id IN ({$placeholders})
                AND deleted_at IS NULL"
        );
        $stmt->execute([$userId, $roleCode, $routeId, ...$ids]);
        if ($stmt->rowCount() !== count($ids)) {
            throw new RuntimeException("Not every {$table} row was archived.");
        }
    }

    /** @param list<int> $ids */
    private static function archiveDocuments(
        PDO $pdo,
        int $routeId,
        array $ids,
        int $userId,
        string $roleCode
    ): void {
        if ($ids === [] || !self::tableExists($pdo, 'documents')) {
            return;
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare(
            "UPDATE documents
                SET deleted_at = CURRENT_TIMESTAMP,
                    deleted_by_user_id = ?,
                    deleted_by_role = ?,
                    delete_comment = 'Linear route deleted'
              WHERE entity_type = 'linear_route'
                AND entity_id = ?
                AND id IN ({$placeholders})
                AND deleted_at IS NULL"
        );
        $stmt->execute([$userId, $roleCode, $routeId, ...$ids]);
        if ($stmt->rowCount() !== count($ids)) {
            throw new RuntimeException('Not every linear route document was archived.');
        }
    }

    /** @param list<int> $ids */
    private static function restoreIds(
        PDO $pdo,
        string $table,
        string $routeColumn,
        int $routeId,
        array $ids
    ): int {
        if ($ids === [] || !self::tableExists($pdo, $table)) {
            return 0;
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare(
            "UPDATE `{$table}`
                SET deleted_at = NULL,
                    deleted_by_user_id = NULL,
                    deleted_by_role = NULL
              WHERE `{$routeColumn}` = ?
                AND id IN ({$placeholders})
                AND deleted_at IS NOT NULL"
        );
        $stmt->execute([$routeId, ...$ids]);
        if ($stmt->rowCount() !== count($ids)) {
            throw new RuntimeException("Not every {$table} row was restored.");
        }
        return $stmt->rowCount();
    }

    /** @param list<int> $ids */
    private static function restoreDocuments(PDO $pdo, int $routeId, array $ids): int
    {
        if ($ids === [] || !self::tableExists($pdo, 'documents')) {
            return 0;
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare(
            "UPDATE documents
                SET deleted_at = NULL,
                    deleted_by_user_id = NULL,
                    deleted_by_role = NULL,
                    delete_comment = NULL
              WHERE entity_type = 'linear_route'
                AND entity_id = ?
                AND id IN ({$placeholders})
                AND deleted_at IS NOT NULL"
        );
        $stmt->execute([$routeId, ...$ids]);
        if ($stmt->rowCount() !== count($ids)) {
            throw new RuntimeException('Not every linear route document was restored.');
        }
        return $stmt->rowCount();
    }

    private static function tableExists(PDO $pdo, string $table): bool
    {
        try {
            $pdo->query("SELECT 1 FROM `{$table}` LIMIT 1");
            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
