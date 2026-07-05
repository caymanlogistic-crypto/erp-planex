<?php

    requireRole(['company_owner', 'senior_logist', 'logist']);
    $crewId = (int)$crewId;
    $pageTitle = 'Исполнитель рейса';
    $pageContext = 'Исполнители рейса › Компания';
    $entityNotFound = false;

    $companyId = (int)(getSessionCompanyId() ?? 0);

    if ($companyId <= 0) {
        $company = null;
        $crew = null;
        $dbError = null;

        ob_start();
        require base_path('app/View/pages/company_route_executor_view.php');
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
            $crew = null;
            $dbError = null;

            ob_start();
            require base_path('app/View/pages/company_route_executor_view.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $pageContext = 'Исполнители рейса › Компания: ' . $company['name'];

        if ($company['status'] !== 'active') {
            $crew = null;
            $dbError = null;

            ob_start();
            require base_path('app/View/pages/company_route_executor_view.php');
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

        try {
            $localPdo->query("SELECT 1 FROM crews LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $migrationSql = file_get_contents(base_path('database/migrations-local/006_create_company_crews.sql'));
            $localPdo->exec($migrationSql);
        }

        try {
            $localPdo->query("SELECT created_by_user_id FROM crews LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $localPdo->exec("ALTER TABLE crews ADD COLUMN created_by_user_id INT UNSIGNED DEFAULT NULL, ADD COLUMN created_by_role VARCHAR(20) DEFAULT NULL");
        }

        try {
            $localPdo->query("SELECT 1 FROM entity_access_grants LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $migrationSql = file_get_contents(base_path('database/migrations-local/009_create_entity_access_grants.sql'));
            $localPdo->exec($migrationSql);
        }

        $cStmt = $localPdo->prepare(
            "SELECT c.*,
                    ct.id AS contractor_id, ct.created_by_user_id AS contractor_created_by_user_id, ct.name AS contractor_name, ct.inn AS contractor_inn,
                    d.id AS driver_id, d.created_by_user_id AS driver_created_by_user_id, d.full_name AS driver_name, d.phone AS driver_phone,
                    dvb.id AS driver_vehicle_block_id, dvb.created_by_user_id AS dvb_created_by_user_id,
                    vs.id AS vehicle_set_id, vs.created_by_user_id AS vehicle_set_created_by_user_id, vs.set_type,
                    vu1.plate_number AS primary_plate, vu1.brand AS primary_brand, vu1.model AS primary_model,
                    vu2.plate_number AS secondary_plate, vu2.brand AS secondary_brand, vu2.model AS secondary_model,
                    u.full_name AS created_by_name
             FROM crews c
             JOIN contractors ct ON c.contractor_id = ct.id
             JOIN driver_vehicle_blocks dvb ON c.driver_vehicle_block_id = dvb.id
             JOIN drivers d ON dvb.driver_id = d.id
             JOIN vehicle_sets vs ON dvb.vehicle_set_id = vs.id
             LEFT JOIN vehicle_units vu1 ON vs.primary_vehicle_unit_id = vu1.id
             LEFT JOIN vehicle_units vu2 ON vs.secondary_vehicle_unit_id = vu2.id
             LEFT JOIN users u ON c.created_by_user_id = u.id
             WHERE c.id = ?"
        );
        $cStmt->execute([$crewId]);
        $crew = $cStmt->fetch(PDO::FETCH_ASSOC);

        if (!$crew) {
            $entityNotFound = true;
        }

        // Access check for logist (cascade: crew/contractor/driver_vehicle_block/driver/vehicle_set)
        $accessDenied = null;
        if ($crew) {
            $isLogist = ($_SESSION['role_code'] ?? '') === 'logist';
            if ($isLogist) {
                $userId = (int)$_SESSION['user_id'];
                if (!hasRouteExecutorAccess($localPdo, $crew, $userId, 'view')) {
                    $accessDenied = 'У вас нет доступа к этой записи.';
                }
            }
        }

        $pageTitle = ($crew && !$accessDenied) ? 'Исполнитель рейса #' . $crew['id'] : 'Исполнитель рейса';

        $dbError = null;
    } catch (\Exception $e) {
        $company = $company ?? null;
        $crew = null;
        $dbError = 'Не удалось подключиться к базе данных компании.';
    }

    ob_start();
    require base_path('app/View/pages/company_route_executor_view.php');
    $content = ob_get_clean();
    require base_path('app/View/layouts/main.php');
