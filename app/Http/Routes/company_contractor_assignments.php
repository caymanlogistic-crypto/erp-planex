<?php

$router->post('/company/contractor-assignments/{id}/assign', function ($id) use ($config, $db) {
    requireRole('company_owner');

    $companyId = (int)(getSessionCompanyId() ?? 0);
    $contractorId = (int)$id;
    $newLogistId = (int)($_POST['new_logist_id'] ?? 0);
    $redirect = '/company/contractor-assignments';

    if ($companyId <= 0 || $contractorId <= 0 || $newLogistId <= 0) {
        header('Location: ' . $redirect);
        exit;
    }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company || $company['status'] !== 'active') {
            header('Location: ' . $redirect);
            exit;
        }

        $dbIdentifier = $company['db_identifier'];
        $localDbConfig = $config['database'];
        $localDbConfig['database'] = $dbIdentifier;
        $localDb = new \App\Core\Database($localDbConfig);
        $localPdo = $localDb->connection();
        applyLocalMigrations($localPdo);

        // Validate contractor exists
        $cStmt = $localPdo->prepare('SELECT * FROM contractors WHERE id = ?');
        $cStmt->execute([$contractorId]);
        $contractor = $cStmt->fetch(PDO::FETCH_ASSOC);
        if (!$contractor) {
            header('Location: ' . $redirect);
            exit;
        }

        // Validate new logist
        $lStmt = $localPdo->prepare(
            "SELECT id, full_name FROM users WHERE id = ? AND role_code = 'logist' AND status = 'active'"
        );
        $lStmt->execute([$newLogistId]);
        $newLogist = $lStmt->fetch(PDO::FETCH_ASSOC);
        if (!$newLogist) {
            header('Location: ' . $redirect);
            exit;
        }

        $oldLogistId = (int)($contractor['created_by_user_id'] ?? 0);

        // If already assigned to this logist, skip
        if ($oldLogistId === $newLogistId) {
            header('Location: ' . $redirect . '?msg=already_assigned');
            exit;
        }

        // Collect cascade entities
        // crews for this contractor -> blocks -> drivers -> vehicle_sets
        $cascadeStmt = $localPdo->prepare(
            "SELECT c.id AS crew_id, c.driver_vehicle_block_id AS block_id,
                    dvb.driver_id, dvb.vehicle_set_id
             FROM crews c
             JOIN driver_vehicle_blocks dvb ON c.driver_vehicle_block_id = dvb.id
             WHERE c.contractor_id = ? AND c.status != 'archived'"
        );
        $cascadeStmt->execute([$contractorId]);
        $cascadeRows = $cascadeStmt->fetchAll(PDO::FETCH_ASSOC);

        $crewIds = [];
        $blockIds = [];
        $driverIds = [];
        $vehicleSetIds = [];
        foreach ($cascadeRows as $row) {
            $crewIds[] = (int)$row['crew_id'];
            $blockIds[] = (int)$row['block_id'];
            $driverIds[] = (int)$row['driver_id'];
            $vehicleSetIds[] = (int)$row['vehicle_set_id'];
        }
        $uniqueBlockIds = array_unique($blockIds);
        $uniqueDriverIds = array_unique($driverIds);
        $uniqueVehicleSetIds = array_unique($vehicleSetIds);

        $role = 'logist';
        $changedByUserId = (int)$_SESSION['user_id'];
        $changedByRole = $_SESSION['role_code'] ?? 'company_owner';

        $summary = [
            'reassigned_contractors' => 1,
            'reassigned_crews' => 0,
            'reassigned_blocks' => 0,
            'reassigned_drivers' => 0,
            'reassigned_vehicle_sets' => 0,
        ];

        $localPdo->beginTransaction();
        try {
            // 1. Reassign contractor
            $upd = $localPdo->prepare(
                "UPDATE contractors SET created_by_user_id = ?, created_by_role = ?, updated_by_user_id = ?, updated_by_role = ? WHERE id = ?"
            );
            $upd->execute([$newLogistId, $role, $changedByUserId, $changedByRole, $contractorId]);

            // 2. Reassign crews
            if (!empty($crewIds)) {
                $placeholders = implode(',', array_fill(0, count($crewIds), '?'));
                $params = [$newLogistId, $role, $changedByUserId, $changedByRole];
                $params = array_merge($params, $crewIds);
                $localPdo->prepare(
                    "UPDATE crews SET created_by_user_id = ?, created_by_role = ?, updated_by_user_id = ?, updated_by_role = ? WHERE id IN ($placeholders)"
                )->execute($params);
                $summary['reassigned_crews'] = count($crewIds);
            }

            // 3. Reassign driver_vehicle_blocks (all blocks from this contractor's crews)
            if (!empty($uniqueBlockIds)) {
                $placeholders = implode(',', array_fill(0, count($uniqueBlockIds), '?'));
                $params = [$newLogistId, $role, $changedByUserId, $changedByRole];
                $params = array_merge($params, array_values($uniqueBlockIds));
                $localPdo->prepare(
                    "UPDATE driver_vehicle_blocks SET created_by_user_id = ?, created_by_role = ?, updated_by_user_id = ?, updated_by_role = ? WHERE id IN ($placeholders)"
                )->execute($params);
                $summary['reassigned_blocks'] = count($uniqueBlockIds);
            }

            // 4. Reassign ALL drivers (full context transfer)
            if (!empty($uniqueDriverIds)) {
                $placeholders = implode(',', array_fill(0, count($uniqueDriverIds), '?'));
                $params = [$newLogistId, $role, $changedByUserId, $changedByRole];
                $params = array_merge($params, array_values($uniqueDriverIds));
                $localPdo->prepare(
                    "UPDATE drivers SET created_by_user_id = ?, created_by_role = ?, updated_by_user_id = ?, updated_by_role = ? WHERE id IN ($placeholders)"
                )->execute($params);
                $summary['reassigned_drivers'] = count($uniqueDriverIds);
            }

            // 5. Reassign ALL vehicle_sets (full context transfer)
            if (!empty($uniqueVehicleSetIds)) {
                $placeholders = implode(',', array_fill(0, count($uniqueVehicleSetIds), '?'));
                $params = [$newLogistId, $role, $changedByUserId, $changedByRole];
                $params = array_merge($params, array_values($uniqueVehicleSetIds));
                $localPdo->prepare(
                    "UPDATE vehicle_sets SET created_by_user_id = ?, created_by_role = ?, updated_by_user_id = ?, updated_by_role = ? WHERE id IN ($placeholders)"
                )->execute($params);
                $summary['reassigned_vehicle_sets'] = count($uniqueVehicleSetIds);
            }

            // 6. Revoke existing contractor grants for this contractor
            $revokeGrantsStmt = $localPdo->prepare(
                "UPDATE entity_access_grants
                 SET revoked_at = NOW(), revoked_by_user_id = ?
                 WHERE entity_type = 'contractor'
                   AND entity_id = ?
                   AND revoked_at IS NULL"
            );
            $revokeGrantsStmt->execute([$changedByUserId, $contractorId]);

            // 7. Revoke cascade grants
            $grantSource = 'contractor_cascade:' . $contractorId;
            $revokeCascadeStmt = $localPdo->prepare(
                "UPDATE entity_access_grants
                 SET revoked_at = NOW(), revoked_by_user_id = ?
                 WHERE grant_source = ?
                   AND revoked_at IS NULL"
            );
            $revokeCascadeStmt->execute([$changedByUserId, $grantSource]);

            // 8. Record history
            $historyStmt = $localPdo->prepare(
                "INSERT INTO contractor_assignment_history
                    (contractor_id, old_logist_id, new_logist_id, changed_by_user_id, changed_by_role, summary_json, comment)
                 VALUES (?, ?, ?, ?, ?, ?, ?)"
            );
            $historyStmt->execute([
                $contractorId,
                $oldLogistId > 0 ? $oldLogistId : null,
                $newLogistId,
                $changedByUserId,
                $changedByRole,
                json_encode($summary, JSON_UNESCAPED_UNICODE),
                'Перепривязка перевозчика «' . ($contractor['name'] ?? '') . '» от логиста #' . ($oldLogistId ?: 'нет') . ' к логисту «' . $newLogist['full_name'] . '"',
            ]);

            $localPdo->commit();
        } catch (\Exception $e) {
            $localPdo->rollBack();
            error_log('Contractor assign error: ' . $e->getMessage());
        }
    } catch (\Exception $e) {
        error_log('Contractor assign setup error: ' . $e->getMessage());
    }

    header('Location: ' . $redirect . '?msg=assigned');
    exit;
});

// ============================================================
// BLOCK E2: Route executors — UI facade list
// ============================================================
