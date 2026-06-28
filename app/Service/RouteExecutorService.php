<?php

namespace App\Service;

use PDO;

final class RouteExecutorService
{
    public static function findOrCreateDriverVehicleBlock(
        PDO $localPdo,
        int $driverId,
        int $vehicleSetId,
        int $userId,
        string $roleCode
    ): int {
        $stmt = $localPdo->prepare(
            'SELECT id FROM driver_vehicle_blocks WHERE driver_id = ? AND vehicle_set_id = ? LIMIT 1'
        );
        $stmt->execute([$driverId, $vehicleSetId]);
        $existingId = (int) $stmt->fetchColumn();

        if ($existingId > 0) {
            return $existingId;
        }

        $insert = $localPdo->prepare(
            "INSERT INTO driver_vehicle_blocks (
                driver_id,
                vehicle_set_id,
                status,
                created_by_user_id,
                created_by_role
            ) VALUES (?, ?, 'active', ?, ?)"
        );
        $insert->execute([$driverId, $vehicleSetId, $userId, $roleCode]);

        return (int) $localPdo->lastInsertId();
    }

    public static function hasDuplicateCrew(
        PDO $localPdo,
        int $contractorId,
        int $driverId,
        int $vehicleSetId,
        ?int $excludeCrewId = null
    ): bool {
        $sql = "SELECT COUNT(*)
                FROM crews c
                JOIN driver_vehicle_blocks dvb ON dvb.id = c.driver_vehicle_block_id
                WHERE c.contractor_id = ?
                  AND dvb.driver_id = ?
                  AND dvb.vehicle_set_id = ?";
        $params = [$contractorId, $driverId, $vehicleSetId];

        if ($excludeCrewId !== null && $excludeCrewId > 0) {
            $sql .= ' AND c.id != ?';
            $params[] = $excludeCrewId;
        }

        $stmt = $localPdo->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn() > 0;
    }
}
