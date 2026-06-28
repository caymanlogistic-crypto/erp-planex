<?php

    requireRole(['company_owner', 'senior_logist', 'logist']);
    $pageTitle = 'Исполнители рейса';
    $pageContext = 'Исполнители рейса › Компания';

    $companyId = (int)(getSessionCompanyId() ?? 0);

    if ($companyId <= 0) {
        $company = null;
        $executors = [];
        $dbError = null;

        ob_start();
        require base_path('app/View/pages/company_route_executors.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
        return;
    }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company) {
            $company = null;
            $executors = [];
            $dbError = null;

            ob_start();
            require base_path('app/View/pages/company_route_executors.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $pageContext = 'Исполнители рейса › Компания: ' . $company['name'];

        if ($company['status'] !== 'active') {
            $executors = [];
            $dbError = null;

            ob_start();
            require base_path('app/View/pages/company_route_executors.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $dbIdentifier = $company['db_identifier'];
        $localDbConfig = $config['database'];
        $localDbConfig['database'] = $dbIdentifier;
        $localDb = new \App\Core\Database($localDbConfig);
        $localPdo = $localDb->connection();
        applyLocalMigrations($localPdo);

        // Ensure crews table exists
        try {
            $localPdo->query("SELECT 1 FROM crews LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $migrationSql = file_get_contents(base_path('database/migrations-local/006_create_company_crews.sql'));
            $localPdo->exec($migrationSql);
        }

        // Ensure created_by columns exist
        try {
            $localPdo->query("SELECT created_by_user_id FROM crews LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $localPdo->exec("ALTER TABLE crews ADD COLUMN created_by_user_id INT UNSIGNED DEFAULT NULL, ADD COLUMN created_by_role VARCHAR(20) DEFAULT NULL");
        }

        // Ensure entity_access_grants table exists
        try {
            $localPdo->query("SELECT 1 FROM entity_access_grants LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $migrationSql = file_get_contents(base_path('database/migrations-local/009_create_entity_access_grants.sql'));
            $localPdo->exec($migrationSql);
        }

        $isLogist = ($_SESSION['role_code'] ?? '') === 'logist';
        $hasGrantsButAllArchived = false;

        $baseSql = "SELECT c.id AS crew_id,
                           c.status AS crew_status,
                           c.created_at AS crew_created_at,
                           c.created_by_user_id,
                           c.created_by_role,
                           ct.id AS contractor_id,
                           ct.created_by_user_id AS contractor_created_by_user_id,
                           ct.name AS contractor_name,
                           d.id AS driver_id,
                           d.created_by_user_id AS driver_created_by_user_id,
                           d.full_name AS driver_name,
                           d.phone AS driver_phone,
                           dvb.id AS driver_vehicle_block_id,
                           dvb.created_by_user_id AS dvb_created_by_user_id,
                           vs.id AS vehicle_set_id,
                           vs.created_by_user_id AS vehicle_set_created_by_user_id,
                           vs.set_type,
                           CONCAT(vu1.plate_number, IFNULL(CONCAT(' + ', vu2.plate_number), '')) AS plates,
                           vu1.plate_number AS primary_plate,
                           u.full_name AS created_by_name
                    FROM crews c
                    JOIN contractors ct ON c.contractor_id = ct.id
                    JOIN driver_vehicle_blocks dvb ON c.driver_vehicle_block_id = dvb.id
                    JOIN drivers d ON dvb.driver_id = d.id
                    JOIN vehicle_sets vs ON dvb.vehicle_set_id = vs.id
                    LEFT JOIN vehicle_units vu1 ON vs.primary_vehicle_unit_id = vu1.id
                    LEFT JOIN vehicle_units vu2 ON vs.secondary_vehicle_unit_id = vu2.id
                    LEFT JOIN users u ON c.created_by_user_id = u.id";

        if ($isLogist) {
            $userId = (int)$_SESSION['user_id'];
            $sql = $baseSql . " WHERE (
                    c.created_by_user_id = ?
                    OR c.id IN (
                        SELECT entity_id FROM entity_access_grants
                        WHERE entity_type = 'crew'
                          AND granted_to_user_id = ?
                          AND access_level IN ('view','edit')
                          AND revoked_at IS NULL
                    )
                    OR (
                        (ct.created_by_user_id = ? OR ct.id IN (
                            SELECT entity_id FROM entity_access_grants
                            WHERE entity_type = 'contractor'
                              AND granted_to_user_id = ?
                              AND access_level IN ('view','edit')
                              AND revoked_at IS NULL
                        ))
                        AND (
                            dvb.created_by_user_id = ? OR dvb.id IN (
                                SELECT entity_id FROM entity_access_grants
                                WHERE entity_type = 'driver_vehicle_block'
                                  AND granted_to_user_id = ?
                                  AND access_level IN ('view','edit')
                                  AND revoked_at IS NULL
                            )
                            OR (
                                (d.created_by_user_id = ? OR d.id IN (
                                    SELECT entity_id FROM entity_access_grants
                                    WHERE entity_type = 'driver'
                                      AND granted_to_user_id = ?
                                      AND access_level IN ('view','edit')
                                      AND revoked_at IS NULL
                                ))
                                AND
                                (vs.created_by_user_id = ? OR vs.id IN (
                                    SELECT entity_id FROM entity_access_grants
                                    WHERE entity_type = 'vehicle_set'
                                      AND granted_to_user_id = ?
                                      AND access_level IN ('view','edit')
                                      AND revoked_at IS NULL
                                ))
                            )
                        )
                    )
                )
                ORDER BY c.created_at DESC";
            $stmt = $localPdo->prepare($sql);
            $stmt->execute([$userId, $userId, $userId, $userId, $userId, $userId, $userId, $userId, $userId, $userId]);
            $executors = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($executors)) {
                $grantCountStmt = $localPdo->prepare(
                    "SELECT COUNT(*) FROM entity_access_grants WHERE entity_type = 'crew' AND granted_to_user_id = ? AND revoked_at IS NULL"
                );
                $grantCountStmt->execute([$userId]);
                $hasGrantsButAllArchived = ((int)$grantCountStmt->fetchColumn() > 0);
            }
        } else {
            $sql = $baseSql . " ORDER BY c.created_at DESC";
            $executors = $localPdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        }

        $dbError = null;
    } catch (\Exception $e) {
        $company = $company ?? null;
        $executors = [];
        $hasGrantsButAllArchived = false;
        $dbError = 'Не удалось подключиться к базе данных компании.';
    }

    ob_start();
    require base_path('app/View/pages/company_route_executors.php');
    $content = ob_get_clean();
    require base_path('app/View/layouts/main.php');
