<?php

    requireRole('company_owner');

    $companyId = (int)(getSessionCompanyId() ?? 0);
    $entityType = $_POST['entity_type'] ?? '';
    $entityIds = $_POST['entity_ids'] ?? [];
    $newLogistId = (int)($_POST['new_logist_id'] ?? 0);
    $cascade = ($_POST['cascade'] ?? '0') === '1';

    $redirect = '/company/responsible-assignments?tab=' . urlencode(
        in_array($entityType, ['route_executor', 'contractor', 'driver', 'vehicle_set'], true) ? $entityType : 'route_executor'
    );

    if (!is_array($entityIds)) {
        $entityIds = [$entityIds];
    }
    $entityIds = array_map('intval', $entityIds);
    $entityIds = array_filter($entityIds, function ($id) { return $id > 0; });
    $entityIds = array_unique(array_values($entityIds));

    if ($companyId <= 0 || $newLogistId <= 0 || empty($entityIds)
        || !in_array($entityType, ['route_executor', 'contractor', 'driver', 'vehicle_set'], true)) {
        header('Location: ' . $redirect . '&msg=error');
        exit;
    }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company || $company['status'] !== 'active') {
            header('Location: ' . $redirect . '&msg=error');
            exit;
        }

        $dbIdentifier = $company['db_identifier'];
        $localDbConfig = $config['database'];
        $localDbConfig['database'] = $dbIdentifier;
        $localDb = new \App\Core\Database($localDbConfig);
        $localPdo = $localDb->connection();
        applyLocalMigrations($localPdo);

        // Validate new logist exists and is active
        $lStmt = $localPdo->prepare(
            "SELECT id, full_name FROM users WHERE id = ? AND role_code = 'logist' AND status = 'active'"
        );
        $lStmt->execute([$newLogistId]);
        $newLogist = $lStmt->fetch(PDO::FETCH_ASSOC);
        if (!$newLogist) {
            header('Location: ' . $redirect . '&msg=error');
            exit;
        }

        $role = 'logist';
        $changedByUserId = (int)$_SESSION['user_id'];
        $changedByRole = $_SESSION['role_code'] ?? 'company_owner';

        $totalSkipped = 0;
        $totalReassigned = 0;
        $summary = [];

        $localPdo->beginTransaction();
        try {
            foreach ($entityIds as $entityId) {
                $oldLogistId = 0;

                // Load entity and get old_logist_id
                switch ($entityType) {
                    case 'route_executor':
                        $eStmt = $localPdo->prepare('SELECT * FROM crews WHERE id = ?');
                        $eStmt->execute([$entityId]);
                        $entity = $eStmt->fetch(PDO::FETCH_ASSOC);
                        if (!$entity) continue 2;
                        $oldLogistId = (int)($entity['created_by_user_id'] ?? 0);
                        break;

                    case 'contractor':
                        $eStmt = $localPdo->prepare('SELECT * FROM contractors WHERE id = ?');
                        $eStmt->execute([$entityId]);
                        $entity = $eStmt->fetch(PDO::FETCH_ASSOC);
                        if (!$entity) continue 2;
                        $oldLogistId = (int)($entity['created_by_user_id'] ?? 0);
                        break;

                    case 'driver':
                        $eStmt = $localPdo->prepare('SELECT * FROM drivers WHERE id = ?');
                        $eStmt->execute([$entityId]);
                        $entity = $eStmt->fetch(PDO::FETCH_ASSOC);
                        if (!$entity) continue 2;
                        $oldLogistId = (int)($entity['created_by_user_id'] ?? 0);
                        break;

                    case 'vehicle_set':
                        $eStmt = $localPdo->prepare('SELECT * FROM vehicle_sets WHERE id = ?');
                        $eStmt->execute([$entityId]);
                        $entity = $eStmt->fetch(PDO::FETCH_ASSOC);
                        if (!$entity) continue 2;
                        $oldLogistId = (int)($entity['created_by_user_id'] ?? 0);
                        break;
                }

                // Skip if already assigned to this logist
                if ($oldLogistId === $newLogistId) {
                    $totalSkipped++;
                    continue;
                }

                $totalReassigned++;

                // --- CASCADE LOGIC ---

                if ($entityType === 'route_executor') {
                    // Full cascade: crew -> block -> contractor + driver + vehicle_set

                    $crew = $entity;
                    $contractorId = (int)($crew['contractor_id'] ?? 0);
                    $blockId = (int)($crew['driver_vehicle_block_id'] ?? 0);

                    $driverId = 0;
                    $vehicleSetId = 0;
                    if ($blockId > 0) {
                        $blockStmt = $localPdo->prepare('SELECT * FROM driver_vehicle_blocks WHERE id = ?');
                        $blockStmt->execute([$blockId]);
                        $block = $blockStmt->fetch(PDO::FETCH_ASSOC);
                        if ($block) {
                            $driverId = (int)($block['driver_id'] ?? 0);
                            $vehicleSetId = (int)($block['vehicle_set_id'] ?? 0);

                            // Update block
                            $localPdo->prepare(
                                "UPDATE driver_vehicle_blocks SET created_by_user_id = ?, created_by_role = ?, updated_by_user_id = ?, updated_by_role = ? WHERE id = ?"
                            )->execute([$newLogistId, $role, $changedByUserId, $changedByRole, $blockId]);
                        }
                    }

                    // Update crew
                    $localPdo->prepare(
                        "UPDATE crews SET created_by_user_id = ?, created_by_role = ?, updated_by_user_id = ?, updated_by_role = ? WHERE id = ?"
                    )->execute([$newLogistId, $role, $changedByUserId, $changedByRole, $entityId]);

                    // Update contractor
                    if ($contractorId > 0) {
                        $localPdo->prepare(
                            "UPDATE contractors SET created_by_user_id = ?, created_by_role = ?, updated_by_user_id = ?, updated_by_role = ? WHERE id = ?"
                        )->execute([$newLogistId, $role, $changedByUserId, $changedByRole, $contractorId]);
                    }

                    // Update driver
                    if ($driverId > 0) {
                        $localPdo->prepare(
                            "UPDATE drivers SET created_by_user_id = ?, created_by_role = ?, updated_by_user_id = ?, updated_by_role = ? WHERE id = ?"
                        )->execute([$newLogistId, $role, $changedByUserId, $changedByRole, $driverId]);
                    }

                    // Update vehicle_set
                    if ($vehicleSetId > 0) {
                        $localPdo->prepare(
                            "UPDATE vehicle_sets SET created_by_user_id = ?, created_by_role = ?, updated_by_user_id = ?, updated_by_role = ? WHERE id = ?"
                        )->execute([$newLogistId, $role, $changedByUserId, $changedByRole, $vehicleSetId]);
                    }

                    // Revoke grants for crew
                    $localPdo->prepare(
                        "UPDATE entity_access_grants SET revoked_at = NOW(), revoked_by_user_id = ?
                         WHERE entity_type = 'crew' AND entity_id = ? AND revoked_at IS NULL"
                    )->execute([$changedByUserId, $entityId]);

                    // Record history
                    $localPdo->prepare(
                        "INSERT INTO responsible_assignment_history
                            (entity_type, entity_id, old_logist_id, new_logist_id, changed_by_user_id, changed_by_role, summary_json, comment)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
                    )->execute([
                        'route_executor',
                        $entityId,
                        $oldLogistId > 0 ? $oldLogistId : null,
                        $newLogistId,
                        $changedByUserId,
                        $changedByRole,
                        json_encode(['reassigned_crew' => 1, 'reassigned_contractor' => $contractorId > 0 ? 1 : 0, 'reassigned_driver' => $driverId > 0 ? 1 : 0, 'reassigned_vehicle_set' => $vehicleSetId > 0 ? 1 : 0], JSON_UNESCAPED_UNICODE),
                        'Переназначение исполнителя рейса #' . $entityId . ' от логиста #' . ($oldLogistId ?: 'нет') . ' к логисту «' . ($newLogist['full_name'] ?? '') . '»',
                    ]);

                } elseif ($entityType === 'contractor') {
                    // Update contractor
                    $localPdo->prepare(
                        "UPDATE contractors SET created_by_user_id = ?, created_by_role = ?, updated_by_user_id = ?, updated_by_role = ? WHERE id = ?"
                    )->execute([$newLogistId, $role, $changedByUserId, $changedByRole, $entityId]);

                    if ($cascade) {
                        // Cascade to crews -> blocks -> drivers -> vehicle_sets
                        $cascStmt = $localPdo->prepare(
                            "SELECT c.id AS crew_id, c.driver_vehicle_block_id AS block_id,
                                    dvb.driver_id, dvb.vehicle_set_id
                             FROM crews c
                             JOIN driver_vehicle_blocks dvb ON c.driver_vehicle_block_id = dvb.id
                             WHERE c.contractor_id = ? AND c.status != 'archived'"
                        );
                        $cascStmt->execute([$entityId]);
                        $cascRows = $cascStmt->fetchAll(PDO::FETCH_ASSOC);

                        $crewIds = [];
                        $blockIds = [];
                        $driverIds = [];
                        $vehicleSetIds = [];
                        foreach ($cascRows as $row) {
                            $crewIds[] = (int)$row['crew_id'];
                            $blockIds[] = (int)$row['block_id'];
                            $driverIds[] = (int)$row['driver_id'];
                            $vehicleSetIds[] = (int)$row['vehicle_set_id'];
                        }
                        $uniqueBlockIds = array_unique($blockIds);
                        $uniqueDriverIds = array_unique($driverIds);
                        $uniqueVehicleSetIds = array_unique($vehicleSetIds);

                        if (!empty($crewIds)) {
                            $ph = implode(',', array_fill(0, count($crewIds), '?'));
                            $params = [$newLogistId, $role, $changedByUserId, $changedByRole];
                            $params = array_merge($params, $crewIds);
                            $localPdo->prepare(
                                "UPDATE crews SET created_by_user_id = ?, created_by_role = ?, updated_by_user_id = ?, updated_by_role = ? WHERE id IN ($ph)"
                            )->execute($params);
                        }
                        if (!empty($uniqueBlockIds)) {
                            $ph = implode(',', array_fill(0, count($uniqueBlockIds), '?'));
                            $params = [$newLogistId, $role, $changedByUserId, $changedByRole];
                            $params = array_merge($params, array_values($uniqueBlockIds));
                            $localPdo->prepare(
                                "UPDATE driver_vehicle_blocks SET created_by_user_id = ?, created_by_role = ?, updated_by_user_id = ?, updated_by_role = ? WHERE id IN ($ph)"
                            )->execute($params);
                        }
                        if (!empty($uniqueDriverIds)) {
                            $ph = implode(',', array_fill(0, count($uniqueDriverIds), '?'));
                            $params = [$newLogistId, $role, $changedByUserId, $changedByRole];
                            $params = array_merge($params, array_values($uniqueDriverIds));
                            $localPdo->prepare(
                                "UPDATE drivers SET created_by_user_id = ?, created_by_role = ?, updated_by_user_id = ?, updated_by_role = ? WHERE id IN ($ph)"
                            )->execute($params);
                        }
                        if (!empty($uniqueVehicleSetIds)) {
                            $ph = implode(',', array_fill(0, count($uniqueVehicleSetIds), '?'));
                            $params = [$newLogistId, $role, $changedByUserId, $changedByRole];
                            $params = array_merge($params, array_values($uniqueVehicleSetIds));
                            $localPdo->prepare(
                                "UPDATE vehicle_sets SET created_by_user_id = ?, created_by_role = ?, updated_by_user_id = ?, updated_by_role = ? WHERE id IN ($ph)"
                            )->execute($params);
                        }
                    }

                    // Revoke grants for contractor
                    $localPdo->prepare(
                        "UPDATE entity_access_grants SET revoked_at = NOW(), revoked_by_user_id = ?
                         WHERE entity_type = 'contractor' AND entity_id = ? AND revoked_at IS NULL"
                    )->execute([$changedByUserId, $entityId]);

                    // Record history
                    $localPdo->prepare(
                        "INSERT INTO responsible_assignment_history
                            (entity_type, entity_id, old_logist_id, new_logist_id, changed_by_user_id, changed_by_role, summary_json, comment)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
                    )->execute([
                        'contractor',
                        $entityId,
                        $oldLogistId > 0 ? $oldLogistId : null,
                        $newLogistId,
                        $changedByUserId,
                        $changedByRole,
                        json_encode(['cascade' => $cascade], JSON_UNESCAPED_UNICODE),
                        'Переназначение подрядчика #' . $entityId . ' от логиста #' . ($oldLogistId ?: 'нет') . ' к логисту «' . ($newLogist['full_name'] ?? '') . '»',
                    ]);

                } elseif ($entityType === 'driver') {
                    // Update driver
                    $localPdo->prepare(
                        "UPDATE drivers SET created_by_user_id = ?, created_by_role = ?, updated_by_user_id = ?, updated_by_role = ? WHERE id = ?"
                    )->execute([$newLogistId, $role, $changedByUserId, $changedByRole, $entityId]);

                    if ($cascade) {
                        // Cascade to crews via driver_vehicle_blocks
                        $cascStmt = $localPdo->prepare(
                            "SELECT c.id AS crew_id, dvb.id AS block_id
                             FROM crews c
                             JOIN driver_vehicle_blocks dvb ON c.driver_vehicle_block_id = dvb.id
                             WHERE dvb.driver_id = ? AND c.status != 'archived'"
                        );
                        $cascStmt->execute([$entityId]);
                        $cascRows = $cascStmt->fetchAll(PDO::FETCH_ASSOC);

                        $crewIds = [];
                        $blockIds = [];
                        foreach ($cascRows as $row) {
                            $crewIds[] = (int)$row['crew_id'];
                            $blockIds[] = (int)$row['block_id'];
                        }
                        $uniqueBlockIds = array_unique($blockIds);

                        if (!empty($crewIds)) {
                            $ph = implode(',', array_fill(0, count($crewIds), '?'));
                            $params = [$newLogistId, $role, $changedByUserId, $changedByRole];
                            $params = array_merge($params, $crewIds);
                            $localPdo->prepare(
                                "UPDATE crews SET created_by_user_id = ?, created_by_role = ?, updated_by_user_id = ?, updated_by_role = ? WHERE id IN ($ph)"
                            )->execute($params);
                        }
                        if (!empty($uniqueBlockIds)) {
                            $ph = implode(',', array_fill(0, count($uniqueBlockIds), '?'));
                            $params = [$newLogistId, $role, $changedByUserId, $changedByRole];
                            $params = array_merge($params, array_values($uniqueBlockIds));
                            $localPdo->prepare(
                                "UPDATE driver_vehicle_blocks SET created_by_user_id = ?, created_by_role = ?, updated_by_user_id = ?, updated_by_role = ? WHERE id IN ($ph)"
                            )->execute($params);
                        }
                    }

                    // Revoke grants for driver
                    $localPdo->prepare(
                        "UPDATE entity_access_grants SET revoked_at = NOW(), revoked_by_user_id = ?
                         WHERE entity_type = 'driver' AND entity_id = ? AND revoked_at IS NULL"
                    )->execute([$changedByUserId, $entityId]);

                    // Record history
                    $localPdo->prepare(
                        "INSERT INTO responsible_assignment_history
                            (entity_type, entity_id, old_logist_id, new_logist_id, changed_by_user_id, changed_by_role, summary_json, comment)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
                    )->execute([
                        'driver',
                        $entityId,
                        $oldLogistId > 0 ? $oldLogistId : null,
                        $newLogistId,
                        $changedByUserId,
                        $changedByRole,
                        json_encode(['cascade' => $cascade], JSON_UNESCAPED_UNICODE),
                        'Переназначение водителя #' . $entityId . ' от логиста #' . ($oldLogistId ?: 'нет') . ' к логисту «' . ($newLogist['full_name'] ?? '') . '»',
                    ]);

                } elseif ($entityType === 'vehicle_set') {
                    // Update vehicle_set
                    $localPdo->prepare(
                        "UPDATE vehicle_sets SET created_by_user_id = ?, created_by_role = ?, updated_by_user_id = ?, updated_by_role = ? WHERE id = ?"
                    )->execute([$newLogistId, $role, $changedByUserId, $changedByRole, $entityId]);

                    if ($cascade) {
                        // Cascade to crews via driver_vehicle_blocks
                        $cascStmt = $localPdo->prepare(
                            "SELECT c.id AS crew_id, dvb.id AS block_id
                             FROM crews c
                             JOIN driver_vehicle_blocks dvb ON c.driver_vehicle_block_id = dvb.id
                             WHERE dvb.vehicle_set_id = ? AND c.status != 'archived'"
                        );
                        $cascStmt->execute([$entityId]);
                        $cascRows = $cascStmt->fetchAll(PDO::FETCH_ASSOC);

                        $crewIds = [];
                        $blockIds = [];
                        foreach ($cascRows as $row) {
                            $crewIds[] = (int)$row['crew_id'];
                            $blockIds[] = (int)$row['block_id'];
                        }
                        $uniqueBlockIds = array_unique($blockIds);

                        if (!empty($crewIds)) {
                            $ph = implode(',', array_fill(0, count($crewIds), '?'));
                            $params = [$newLogistId, $role, $changedByUserId, $changedByRole];
                            $params = array_merge($params, $crewIds);
                            $localPdo->prepare(
                                "UPDATE crews SET created_by_user_id = ?, created_by_role = ?, updated_by_user_id = ?, updated_by_role = ? WHERE id IN ($ph)"
                            )->execute($params);
                        }
                        if (!empty($uniqueBlockIds)) {
                            $ph = implode(',', array_fill(0, count($uniqueBlockIds), '?'));
                            $params = [$newLogistId, $role, $changedByUserId, $changedByRole];
                            $params = array_merge($params, array_values($uniqueBlockIds));
                            $localPdo->prepare(
                                "UPDATE driver_vehicle_blocks SET created_by_user_id = ?, created_by_role = ?, updated_by_user_id = ?, updated_by_role = ? WHERE id IN ($ph)"
                            )->execute($params);
                        }
                    }

                    // Revoke grants for vehicle_set
                    $localPdo->prepare(
                        "UPDATE entity_access_grants SET revoked_at = NOW(), revoked_by_user_id = ?
                         WHERE entity_type = 'vehicle_set' AND entity_id = ? AND revoked_at IS NULL"
                    )->execute([$changedByUserId, $entityId]);

                    // Record history
                    $localPdo->prepare(
                        "INSERT INTO responsible_assignment_history
                            (entity_type, entity_id, old_logist_id, new_logist_id, changed_by_user_id, changed_by_role, summary_json, comment)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
                    )->execute([
                        'vehicle_set',
                        $entityId,
                        $oldLogistId > 0 ? $oldLogistId : null,
                        $newLogistId,
                        $changedByUserId,
                        $changedByRole,
                        json_encode(['cascade' => $cascade], JSON_UNESCAPED_UNICODE),
                        'Переназначение ТС #' . $entityId . ' от логиста #' . ($oldLogistId ?: 'нет') . ' к логисту «' . ($newLogist['full_name'] ?? '') . '»',
                    ]);
                }
            }

            $localPdo->commit();

            // Build redirect message
            if ($totalReassigned > 0) {
                $redirect .= '&msg=assigned';
            } elseif ($totalSkipped > 0) {
                $redirect .= '&msg=already_assigned';
            } else {
                $redirect .= '&msg=error';
            }
        } catch (\Exception $e) {
            $localPdo->rollBack();
            error_log('Responsible assignment error: ' . $e->getMessage());
            $redirect .= '&msg=error';
        }
    } catch (\Exception $e) {
        error_log('Responsible assignment setup error: ' . $e->getMessage());
        $redirect .= '&msg=error';
    }

    header('Location: ' . $redirect);
    exit;
