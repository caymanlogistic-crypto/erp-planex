<?php

namespace App\Service;

use PDO;

final class LinearRoutePointService
{
    public const TYPE_LOADING = 'loading';
    public const TYPE_UNLOADING = 'unloading';

    public static function normalizeSubmitted(array $post): array
    {
        $raw = (array) ($post['route_points'] ?? []);
        $result = [self::TYPE_LOADING => [], self::TYPE_UNLOADING => []];

        foreach ([self::TYPE_LOADING, self::TYPE_UNLOADING] as $type) {
            foreach (array_values((array) ($raw[$type] ?? [])) as $value) {
                $address = trim(preg_replace('/\s+/u', ' ', (string) $value));
                if ($address === '') {
                    continue;
                }
                $result[$type][] = $address;
            }
        }

        return $result;
    }

    public static function validate(array $points, array &$errors): void
    {
        if (($points[self::TYPE_LOADING] ?? []) === []) {
            $errors['route_points.loading.0'] = 'Укажите хотя бы одну точку загрузки.';
        }
        if (($points[self::TYPE_UNLOADING] ?? []) === []) {
            $errors['route_points.unloading.0'] = 'Укажите хотя бы одну точку выгрузки.';
        }
    }

    public static function fetch(PDO $pdo, int $routeId): array
    {
        $result = [self::TYPE_LOADING => [], self::TYPE_UNLOADING => []];
        if ($routeId <= 0 || !self::tableExists($pdo)) {
            return $result;
        }

        $stmt = $pdo->prepare(
            "SELECT point_type, address_text
               FROM linear_route_points
              WHERE linear_route_id = ?
                AND deleted_at IS NULL
              ORDER BY point_type = 'unloading', sort_order ASC, id ASC"
        );
        $stmt->execute([$routeId]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $type = (string) ($row['point_type'] ?? '');
            if (!array_key_exists($type, $result)) {
                continue;
            }
            $result[$type][] = (string) ($row['address_text'] ?? '');
        }

        return $result;
    }

    public static function store(PDO $pdo, int $routeId, array $points, int $userId, string $roleCode): void
    {
        if ($routeId <= 0) {
            return;
        }

        $pdo->prepare(
            "UPDATE linear_route_points
                SET deleted_at = NOW(),
                    updated_by_user_id = ?,
                    updated_by_role = ?
              WHERE linear_route_id = ?
                AND deleted_at IS NULL"
        )->execute([$userId, $roleCode, $routeId]);

        $insert = $pdo->prepare(
            "INSERT INTO linear_route_points (
                linear_route_id, point_type, sort_order, address_text,
                created_by_user_id, created_by_role, updated_by_user_id, updated_by_role
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );

        foreach ([self::TYPE_LOADING, self::TYPE_UNLOADING] as $type) {
            foreach (array_values((array) ($points[$type] ?? [])) as $index => $address) {
                $insert->execute([
                    $routeId,
                    $type,
                    $index + 1,
                    trim((string) $address),
                    $userId,
                    $roleCode,
                    $userId,
                    $roleCode,
                ]);
            }
        }
    }

    private static function tableExists(PDO $pdo): bool
    {
        $stmt = $pdo->prepare(
            "SELECT 1 FROM information_schema.TABLES
              WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = 'linear_route_points'
              LIMIT 1"
        );
        $stmt->execute();
        return $stmt->fetchColumn() !== false;
    }
}
