<?php

namespace App\Service;

use PDO;

final class LinearRoutePointService
{
    public const TYPE_LOADING = 'loading';
    public const TYPE_UNLOADING = 'unloading';

    /**
     * Canonical form:
     * [
     *   'points' => [
     *      ['address' => '...', 'loading' => true, 'unloading' => false],
     *   ],
     *   'loading' => [...],   // aligned compatibility projection for legacy views/forms
     *   'unloading' => [...], // aligned compatibility projection for legacy views/forms
     * ]
     *
     * The DB schema already supports a point with both operations safely: such a logical
     * point is persisted as two rows with the same global sort_order/address, one row per
     * point_type. No schema mutation is required.
     */
    public static function normalizeSubmitted(array $post): array
    {
        $raw = (array) ($post['route_points'] ?? []);
        $submittedPoints = (array) ($raw['points'] ?? $raw['items'] ?? []);
        $points = [];

        if ($submittedPoints !== []) {
            foreach (array_values($submittedPoints) as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $address = self::normalizeAddress((string) ($row['address'] ?? ''));
                $loading = self::truthy($row['loading'] ?? false);
                $unloading = self::truthy($row['unloading'] ?? false);

                // Keep selected-but-empty rows so validation can explain the problem.
                if ($address === '' && !$loading && !$unloading) {
                    continue;
                }
                $points[] = [
                    'address' => $address,
                    'loading' => $loading,
                    'unloading' => $unloading,
                ];
            }

            return self::project($points);
        }

        // Backward-compatible fallback for the former two independent columns.
        $loadingRows = array_values((array) ($raw[self::TYPE_LOADING] ?? []));
        $unloadingRows = array_values((array) ($raw[self::TYPE_UNLOADING] ?? []));
        $max = max(count($loadingRows), count($unloadingRows));

        for ($i = 0; $i < $max; $i++) {
            $loadingAddress = self::normalizeAddress((string) ($loadingRows[$i] ?? ''));
            $unloadingAddress = self::normalizeAddress((string) ($unloadingRows[$i] ?? ''));

            if ($loadingAddress !== '' && $unloadingAddress !== '' && $loadingAddress === $unloadingAddress) {
                $points[] = ['address' => $loadingAddress, 'loading' => true, 'unloading' => true];
                continue;
            }
            if ($loadingAddress !== '') {
                $points[] = ['address' => $loadingAddress, 'loading' => true, 'unloading' => false];
            }
            if ($unloadingAddress !== '') {
                $points[] = ['address' => $unloadingAddress, 'loading' => false, 'unloading' => true];
            }
        }

        return self::project($points);
    }

    public static function validate(array $points, array &$errors): void
    {
        $canonical = self::canonicalPoints($points);
        $hasLoading = false;
        $hasUnloading = false;

        foreach ($canonical as $index => $point) {
            $address = self::normalizeAddress((string) ($point['address'] ?? ''));
            $loading = !empty($point['loading']);
            $unloading = !empty($point['unloading']);

            if (!$loading && !$unloading) {
                $errors['route_points.points.' . $index . '.operation'] = 'Выберите для точки загрузку, выгрузку или обе операции.';
            }
            if ($address === '') {
                $errors['route_points.points.' . $index . '.address'] = 'Укажите адрес или место операции.';
            }
            $hasLoading = $hasLoading || $loading;
            $hasUnloading = $hasUnloading || $unloading;
        }

        if (!$hasLoading) {
            $errors['route_points.loading.0'] = 'Укажите хотя бы одну точку загрузки.';
        }
        if (!$hasUnloading) {
            $errors['route_points.unloading.0'] = 'Укажите хотя бы одну точку выгрузки.';
        }
        if ($canonical === []) {
            $errors['route_points.points.0.address'] = 'Добавьте точки загрузки / выгрузки.';
        }
    }

    public static function fetch(PDO $pdo, int $routeId): array
    {
        if ($routeId <= 0 || !self::tableExists($pdo)) {
            return self::project([]);
        }

        $stmt = $pdo->prepare(
            "SELECT id, point_type, sort_order, address_text
               FROM linear_route_points
              WHERE linear_route_id = ?
                AND deleted_at IS NULL
              ORDER BY sort_order ASC, id ASC"
        );
        $stmt->execute([$routeId]);

        $points = [];
        $keyToIndex = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $type = (string) ($row['point_type'] ?? '');
            if (!in_array($type, [self::TYPE_LOADING, self::TYPE_UNLOADING], true)) {
                continue;
            }
            $address = self::normalizeAddress((string) ($row['address_text'] ?? ''));
            if ($address === '') {
                continue;
            }
            $sort = max(1, (int) ($row['sort_order'] ?? 1));
            $key = $sort . "\n" . $address;

            if (!array_key_exists($key, $keyToIndex)) {
                $keyToIndex[$key] = count($points);
                $points[] = [
                    'address' => $address,
                    'loading' => false,
                    'unloading' => false,
                    '_sort' => $sort,
                ];
            }
            $idx = $keyToIndex[$key];
            $points[$idx][$type] = true;
        }

        foreach ($points as &$point) {
            unset($point['_sort']);
        }
        unset($point);

        return self::project($points);
    }

    public static function store(PDO $pdo, int $routeId, array $points, int $userId, string $roleCode): void
    {
        if ($routeId <= 0) {
            return;
        }

        $canonical = self::canonicalPoints($points);

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

        foreach (array_values($canonical) as $index => $point) {
            $address = self::normalizeAddress((string) ($point['address'] ?? ''));
            if ($address === '') {
                continue;
            }
            $sortOrder = $index + 1;
            foreach ([self::TYPE_LOADING, self::TYPE_UNLOADING] as $type) {
                if (empty($point[$type])) {
                    continue;
                }
                $insert->execute([
                    $routeId,
                    $type,
                    $sortOrder,
                    $address,
                    $userId,
                    $roleCode,
                    $userId,
                    $roleCode,
                ]);
            }
        }
    }

    public static function canonicalPoints(array $value): array
    {
        if (isset($value['points']) && is_array($value['points'])) {
            return array_values(array_filter(array_map(static function ($row): ?array {
                if (!is_array($row)) {
                    return null;
                }
                return [
                    'address' => self::normalizeAddress((string) ($row['address'] ?? '')),
                    'loading' => !empty($row['loading']),
                    'unloading' => !empty($row['unloading']),
                ];
            }, $value['points'])));
        }

        // Compatibility for callers still passing the old shape.
        return self::normalizeSubmitted(['route_points' => $value])['points'];
    }

    private static function project(array $points): array
    {
        $canonical = [];
        $loading = [];
        $unloading = [];

        foreach (array_values($points) as $point) {
            if (!is_array($point)) {
                continue;
            }
            $address = self::normalizeAddress((string) ($point['address'] ?? ''));
            $isLoading = !empty($point['loading']);
            $isUnloading = !empty($point['unloading']);
            $canonical[] = [
                'address' => $address,
                'loading' => $isLoading,
                'unloading' => $isUnloading,
            ];
            // Aligned projections preserve global order through the legacy PHP partial.
            $loading[] = $isLoading ? $address : '';
            $unloading[] = $isUnloading ? $address : '';
        }

        return [
            'points' => $canonical,
            self::TYPE_LOADING => $loading,
            self::TYPE_UNLOADING => $unloading,
        ];
    }

    private static function normalizeAddress(string $value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }

    private static function truthy(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'on', 'yes'], true);
    }

    private static function tableExists(PDO $pdo): bool
    {
        try {
            $stmt = $pdo->query("SHOW TABLES LIKE 'linear_route_points'");
            return $stmt !== false && $stmt->fetchColumn() !== false;
        } catch (\Throwable) {
            return false;
        }
    }
}
