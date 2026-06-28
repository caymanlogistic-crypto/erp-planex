<?php

$router->get('/company/responsible-assignments', function () use ($config, $db) {
    requireRole('company_owner');
    $pageTitle = 'Ответственные логисты';
    $pageContext = 'Ответственные логисты › Компания';

    $companyId = (int)(getSessionCompanyId() ?? 0);
    $activeTab = $_GET['tab'] ?? 'route_executor';
    if (!in_array($activeTab, ['route_executor', 'contractor', 'driver', 'vehicle_set'], true)) {
        $activeTab = 'route_executor';
    }

    // Query params for after-redirect messages
    $successMessage = null;
    $formError = null;
    if (isset($_GET['msg'])) {
        switch ($_GET['msg']) {
            case 'assigned':
                $successMessage = 'Ответственность успешно переназначена.';
                break;
            case 'already_assigned':
                $formError = 'Выбранный логист уже является ответственным для некоторых записей.';
                break;
            case 'error':
                $formError = 'Ошибка при переназначении. Попробуйте снова.';
                break;
        }
    }

    if ($companyId <= 0) {
        $company = null;
        $items = [];
        $logists = [];
        $dbError = null;
        ob_start();
        require base_path('app/View/pages/company_responsible_assignments.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
        return;
    }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company || $company['status'] !== 'active') {
            $company = $company ?? null;
            $items = [];
            $logists = [];
            $dbError = null;
            ob_start();
            require base_path('app/View/pages/company_responsible_assignments.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $pageContext = 'Ответственные логисты › Компания: ' . $company['name'];

        $dbIdentifier = $company['db_identifier'];
        $localDbConfig = $config['database'];
        $localDbConfig['database'] = $dbIdentifier;
        $localDb = new \App\Core\Database($localDbConfig);
        $localPdo = $localDb->connection();
        applyLocalMigrations($localPdo);

        // Ensure key tables exist
        try { $localPdo->query("SELECT 1 FROM contractors LIMIT 1")->fetch(); }
        catch (\Exception $e) { $localPdo->exec(file_get_contents(base_path('database/migrations-local/003_create_company_contractors.sql'))); }
        try { $localPdo->query("SELECT 1 FROM drivers LIMIT 1")->fetch(); }
        catch (\Exception $e) { $localPdo->exec(file_get_contents(base_path('database/migrations-local/004_create_company_drivers.sql'))); }
        try { $localPdo->query("SELECT 1 FROM vehicle_sets LIMIT 1")->fetch(); }
        catch (\Exception $e) { $localPdo->exec(file_get_contents(base_path('database/migrations-local/017_create_vehicle_sets.sql'))); }
        try { $localPdo->query("SELECT 1 FROM crews LIMIT 1")->fetch(); }
        catch (\Exception $e) { $localPdo->exec(file_get_contents(base_path('database/migrations-local/006_create_company_crews.sql'))); }

        // Load active logists for dropdowns
        $logists = $localPdo->query(
            "SELECT id, full_name, login FROM users
             WHERE role_code = 'logist' AND status = 'active'
             ORDER BY full_name"
        )->fetchAll(PDO::FETCH_ASSOC);

        // Load items based on active tab
        switch ($activeTab) {
            case 'route_executor':
                $items = $localPdo->query(
                    "SELECT c.id AS crew_id,
                            ct.name AS contractor_name,
                            d.full_name AS driver_name,
                            CONCAT(vu1.plate_number, IFNULL(CONCAT(' + ', vu2.plate_number), '')) AS plates,
                            c.created_by_user_id AS logist_id,
                            u.full_name AS logist_name
                     FROM crews c
                     JOIN contractors ct ON c.contractor_id = ct.id
                     JOIN driver_vehicle_blocks dvb ON c.driver_vehicle_block_id = dvb.id
                     JOIN drivers d ON dvb.driver_id = d.id
                     JOIN vehicle_sets vs ON dvb.vehicle_set_id = vs.id
                     LEFT JOIN vehicle_units vu1 ON vs.primary_vehicle_unit_id = vu1.id
                     LEFT JOIN vehicle_units vu2 ON vs.secondary_vehicle_unit_id = vu2.id
                     LEFT JOIN users u ON c.created_by_user_id = u.id
                     WHERE c.status != 'archived'
                     ORDER BY ct.name"
                )->fetchAll(PDO::FETCH_ASSOC);
                break;

            case 'contractor':
                $items = $localPdo->query(
                    "SELECT c.id, c.name, c.inn,
                            c.created_by_user_id AS logist_id,
                            u.full_name AS logist_name,
                            COUNT(DISTINCT cr.id) AS crew_count
                     FROM contractors c
                     LEFT JOIN users u ON c.created_by_user_id = u.id
                     LEFT JOIN crews cr ON cr.contractor_id = c.id AND cr.status != 'archived'
                     WHERE c.status != 'archived'
                     GROUP BY c.id, c.name, c.inn, c.created_by_user_id, u.full_name
                     ORDER BY c.name"
                )->fetchAll(PDO::FETCH_ASSOC);
                break;

            case 'driver':
                $items = $localPdo->query(
                    "SELECT d.id, d.full_name, d.phone,
                            d.created_by_user_id AS logist_id,
                            u.full_name AS logist_name,
                            COUNT(DISTINCT cr.id) AS crew_count
                     FROM drivers d
                     LEFT JOIN users u ON d.created_by_user_id = u.id
                     LEFT JOIN driver_vehicle_blocks dvb ON dvb.driver_id = d.id
                     LEFT JOIN crews cr ON cr.driver_vehicle_block_id = dvb.id AND cr.status != 'archived'
                     WHERE d.status != 'archived'
                     GROUP BY d.id, d.full_name, d.phone, d.created_by_user_id, u.full_name
                     ORDER BY d.full_name"
                )->fetchAll(PDO::FETCH_ASSOC);
                break;

            case 'vehicle_set':
                $items = $localPdo->query(
                    "SELECT vs.id, vs.set_type,
                            vu1.plate_number,
                            vs.created_by_user_id AS logist_id,
                            u.full_name AS logist_name,
                            COUNT(DISTINCT cr.id) AS crew_count
                     FROM vehicle_sets vs
                     LEFT JOIN users u ON vs.created_by_user_id = u.id
                     LEFT JOIN vehicle_units vu1 ON vs.primary_vehicle_unit_id = vu1.id
                     LEFT JOIN driver_vehicle_blocks dvb ON dvb.vehicle_set_id = vs.id
                     LEFT JOIN crews cr ON cr.driver_vehicle_block_id = dvb.id AND cr.status != 'archived'
                     WHERE vs.status != 'archived'
                     GROUP BY vs.id, vs.set_type, vu1.plate_number, vs.created_by_user_id, u.full_name
                     ORDER BY vu1.plate_number"
                )->fetchAll(PDO::FETCH_ASSOC);
                break;
        }

        $dbError = null;
    } catch (\Exception $e) {
        $company = $company ?? null;
        $items = [];
        $logists = [];
        $dbError = 'Не удалось загрузить данные: ' . $e->getMessage();
    }

    ob_start();
    require base_path('app/View/pages/company_responsible_assignments.php');
    $content = ob_get_clean();
    require base_path('app/View/layouts/main.php');
});

$router->post('/company/responsible-assignments/reassign', function () use ($config, $db) {
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
});

// ============================================================
// SUPERADMIN: Company status actions (NEW)
// ============================================================
