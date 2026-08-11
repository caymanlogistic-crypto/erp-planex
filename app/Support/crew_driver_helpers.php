<?php

declare(strict_types=1);

if (!function_exists('normalizeCrewDriverIds')) {
    function normalizeCrewDriverIds(mixed $raw): array
    {
        $items = is_array($raw) ? $raw : [$raw];
        $ids = [];
        foreach ($items as $value) {
            $id = (int)$value;
            if ($id > 0 && !in_array($id, $ids, true)) {
                $ids[] = $id;
            }
        }
        return $ids;
    }
}

if (!function_exists('loadCrewDriverIds')) {
    function loadCrewDriverIds(PDO $pdo, int $crewId, int $legacyDriverId = 0): array
    {
        try {
            $stmt = $pdo->prepare('SELECT driver_id FROM crew_drivers WHERE crew_id = ? ORDER BY position, id');
            $stmt->execute([$crewId]);
            $ids = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
            if ($ids) return $ids;
        } catch (Throwable $e) {
        }
        return $legacyDriverId > 0 ? [$legacyDriverId] : [];
    }
}

if (!function_exists('syncCrewDrivers')) {
    function syncCrewDrivers(PDO $pdo, int $crewId, array $driverIds): void
    {
        $driverIds = normalizeCrewDriverIds($driverIds);
        if (!$driverIds) {
            throw new InvalidArgumentException('У исполнителя рейса должен быть хотя бы один водитель.');
        }
        $pdo->prepare('DELETE FROM crew_drivers WHERE crew_id = ?')->execute([$crewId]);
        $insert = $pdo->prepare('INSERT INTO crew_drivers (crew_id, driver_id, position) VALUES (?, ?, ?)');
        foreach ($driverIds as $position => $driverId) {
            $insert->execute([$crewId, $driverId, $position + 1]);
        }
    }
}

if (!function_exists('crewDriverNames')) {
    function crewDriverNames(PDO $pdo, int $crewId, string $fallback = ''): string
    {
        try {
            $stmt = $pdo->prepare("SELECT d.full_name FROM crew_drivers cd JOIN drivers d ON d.id=cd.driver_id WHERE cd.crew_id=? ORDER BY cd.position,cd.id");
            $stmt->execute([$crewId]);
            $names = array_values(array_filter(array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN))));
            if ($names) return implode(' + ', $names);
        } catch (Throwable $e) {
        }
        return $fallback;
    }
}
