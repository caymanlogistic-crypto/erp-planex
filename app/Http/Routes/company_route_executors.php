<?php

$router->get('/company/route-executors', function () use ($config, $db) {
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
});

// ============================================================
// BLOCK E3: Route executors — CREATE
// ============================================================

$router->get('/company/route-executors/create', function () use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);
    $pageTitle = 'Создать исполнителя рейса';
    $pageContext = 'Исполнители рейса › Компания';

    $companyId = (int)(getSessionCompanyId() ?? 0);

    if ($companyId <= 0) {
        $company = null;
        $success = false; $errors = []; $old = []; $formError = null; $createdExecutor = null;
        $blockingNotices = []; $contractors = []; $drivers = []; $vehicleSets = [];
        ob_start(); require base_path('app/View/pages/company_route_executors_create.php');
        $content = ob_get_clean(); require base_path('app/View/layouts/main.php');
        return;
    }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company) {
            $company = null;
            $success = false; $errors = []; $old = []; $formError = null; $createdExecutor = null;
            $blockingNotices = []; $contractors = []; $drivers = []; $vehicleSets = [];
            ob_start(); require base_path('app/View/pages/company_route_executors_create.php');
            $content = ob_get_clean(); require base_path('app/View/layouts/main.php');
            return;
        }

        $pageContext = 'Исполнители рейса › Компания: ' . $company['name'];

        $success = false; $errors = []; $old = []; $formError = null; $createdExecutor = null;

        if ($company['status'] !== 'active') {
            $blockingNotices = []; $contractors = []; $drivers = []; $vehicleSets = [];
        } else {
            $dbIdentifier = $company['db_identifier'];
            $localDbConfig = $config['database']; $localDbConfig['database'] = $dbIdentifier;
            $localDb = new \App\Core\Database($localDbConfig); $localPdo = $localDb->connection();
            applyLocalMigrations($localPdo);

            try { $localPdo->query("SELECT 1 FROM crews LIMIT 1")->fetch(); }
            catch (\Exception $e) { $localPdo->exec(file_get_contents(base_path('database/migrations-local/006_create_company_crews.sql'))); }

            try { $localPdo->query("SELECT created_by_user_id FROM crews LIMIT 1")->fetch(); }
            catch (\Exception $e) { $localPdo->exec("ALTER TABLE crews ADD COLUMN created_by_user_id INT UNSIGNED DEFAULT NULL, ADD COLUMN created_by_role VARCHAR(20) DEFAULT NULL"); }

            try { $localPdo->query("SELECT 1 FROM entity_access_grants LIMIT 1")->fetch(); }
            catch (\Exception $e) { $localPdo->exec(file_get_contents(base_path('database/migrations-local/009_create_entity_access_grants.sql'))); }

            try { $localPdo->query("SELECT 1 FROM driver_vehicle_blocks LIMIT 1")->fetch(); }
            catch (\Exception $e) { $localPdo->exec(file_get_contents(base_path('database/migrations-local/018_create_driver_vehicle_blocks.sql'))); }

            $isLogist = ($_SESSION['role_code'] ?? '') === 'logist';
            $userId = (int)$_SESSION['user_id'];

            // Load contractors for dropdown
            if ($isLogist) {
                $cStmt = $localPdo->prepare("SELECT id, name, inn FROM contractors WHERE status = 'active' AND (created_by_user_id = ? OR id IN (SELECT entity_id FROM entity_access_grants WHERE entity_type = 'contractor' AND granted_to_user_id = ? AND access_level IN ('view','edit') AND revoked_at IS NULL)) ORDER BY name");
                $cStmt->execute([$userId, $userId]);
                $contractors = $cStmt->fetchAll(PDO::FETCH_ASSOC);
            } else {
                $contractors = $localPdo->query("SELECT id, name, inn FROM contractors WHERE status = 'active' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
            }

            // Load drivers for dropdown
            if ($isLogist) {
                $dStmt = $localPdo->prepare("SELECT id, full_name, phone FROM drivers WHERE status = 'active' AND (created_by_user_id = ? OR id IN (SELECT entity_id FROM entity_access_grants WHERE entity_type = 'driver' AND granted_to_user_id = ? AND access_level IN ('view','edit') AND revoked_at IS NULL)) ORDER BY full_name");
                $dStmt->execute([$userId, $userId]);
                $drivers = $dStmt->fetchAll(PDO::FETCH_ASSOC);
            } else {
                $drivers = $localPdo->query("SELECT id, full_name, phone FROM drivers WHERE status = 'active' ORDER BY full_name")->fetchAll(PDO::FETCH_ASSOC);
            }

            // Load vehicle sets for dropdown
            if ($isLogist) {
                $vsStmt = $localPdo->prepare("SELECT vs.id, vs.set_type, vu1.plate_number AS primary_plate, vu2.plate_number AS secondary_plate FROM vehicle_sets vs LEFT JOIN vehicle_units vu1 ON vs.primary_vehicle_unit_id = vu1.id LEFT JOIN vehicle_units vu2 ON vs.secondary_vehicle_unit_id = vu2.id WHERE vs.status = 'active' AND (vs.created_by_user_id = ? OR vs.id IN (SELECT entity_id FROM entity_access_grants WHERE entity_type = 'vehicle_set' AND granted_to_user_id = ? AND access_level IN ('view','edit') AND revoked_at IS NULL)) ORDER BY vs.set_type");
                $vsStmt->execute([$userId, $userId]);
                $vehicleSets = $vsStmt->fetchAll(PDO::FETCH_ASSOC);
            } else {
                $vehicleSets = $localPdo->query("SELECT vs.id, vs.set_type, vu1.plate_number AS primary_plate, vu2.plate_number AS secondary_plate FROM vehicle_sets vs LEFT JOIN vehicle_units vu1 ON vs.primary_vehicle_unit_id = vu1.id LEFT JOIN vehicle_units vu2 ON vs.secondary_vehicle_unit_id = vu2.id WHERE vs.status = 'active' ORDER BY vs.set_type")->fetchAll(PDO::FETCH_ASSOC);
            }

            $blockingNotices = [];
            if (empty($contractors)) {
                $blockingNotices[] = ['message' => 'Сначала создайте подрядчика.', 'link' => '/company/contractors/create', 'action' => 'Создать подрядчика'];
            }
            if (empty($drivers)) {
                $blockingNotices[] = ['message' => 'Сначала создайте водителя.', 'link' => '/company/drivers', 'action' => 'Создать водителя'];
            }
            if (empty($vehicleSets)) {
                $blockingNotices[] = ['message' => 'Сначала создайте транспортный комплект.', 'link' => '/company/vehicle-sets', 'action' => 'Создать ТС'];
            }
        }
    } catch (\Exception $e) {
        $company = null;
        $success = false; $errors = []; $old = []; $formError = 'Ошибка загрузки данных: ' . $e->getMessage();
        $createdExecutor = null; $blockingNotices = []; $contractors = []; $drivers = []; $vehicleSets = [];
    }

    ob_start();
    require base_path('app/View/pages/company_route_executors_create.php');
    $content = ob_get_clean();
    require base_path('app/View/layouts/main.php');
});

$router->post('/company/route-executors/create', function () use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);
    $pageTitle = 'Создать исполнителя рейса';
    $pageContext = 'Исполнители рейса › Компания';

    $companyId = (int)(getSessionCompanyId() ?? 0);
    $errors = [];
    $old = $_POST;
    $formError = null;
    $success = false;
    $createdExecutor = null;
    $blockingNotices = [];
    $contractors = [];
    $drivers = [];
    $vehicleSets = [];

    if ($companyId <= 0) {
        $company = null;
        $formError = 'Компания не найдена';
        ob_start(); require base_path('app/View/pages/company_route_executors_create.php');
        $content = ob_get_clean(); require base_path('app/View/layouts/main.php');
        return;
    }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company) {
            $company = null;
            $formError = 'Компания не найдена';
            ob_start(); require base_path('app/View/pages/company_route_executors_create.php');
            $content = ob_get_clean(); require base_path('app/View/layouts/main.php');
            return;
        }

        $pageContext = 'Исполнители рейса › Компания: ' . $company['name'];

        if ($company['status'] !== 'active') {
            $formError = 'Создание исполнителя рейса недоступно';
            ob_start(); require base_path('app/View/pages/company_route_executors_create.php');
            $content = ob_get_clean(); require base_path('app/View/layouts/main.php');
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
            $localPdo->query("SELECT 1 FROM driver_vehicle_blocks LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $migrationSql = file_get_contents(base_path('database/migrations-local/018_create_driver_vehicle_blocks.sql'));
            $localPdo->exec($migrationSql);
        }

        $isLogist = ($_SESSION['role_code'] ?? '') === 'logist';
        $userId = (int)$_SESSION['user_id'];
        $userRole = $_SESSION['role_code'];

        // Reload dropdowns for re-render
        if ($isLogist) {
            $cStmt = $localPdo->prepare("SELECT id, name, inn FROM contractors WHERE status = 'active' AND (created_by_user_id = ? OR id IN (SELECT entity_id FROM entity_access_grants WHERE entity_type = 'contractor' AND granted_to_user_id = ? AND access_level IN ('view','edit') AND revoked_at IS NULL)) ORDER BY name");
            $cStmt->execute([$userId, $userId]);
            $contractors = $cStmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $contractors = $localPdo->query("SELECT id, name, inn FROM contractors WHERE status = 'active' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
        }

        if ($isLogist) {
            $dStmt = $localPdo->prepare("SELECT id, full_name, phone FROM drivers WHERE status = 'active' AND (created_by_user_id = ? OR id IN (SELECT entity_id FROM entity_access_grants WHERE entity_type = 'driver' AND granted_to_user_id = ? AND access_level IN ('view','edit') AND revoked_at IS NULL)) ORDER BY full_name");
            $dStmt->execute([$userId, $userId]);
            $drivers = $dStmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $drivers = $localPdo->query("SELECT id, full_name, phone FROM drivers WHERE status = 'active' ORDER BY full_name")->fetchAll(PDO::FETCH_ASSOC);
        }

        if ($isLogist) {
            $vsStmt = $localPdo->prepare("SELECT vs.id, vs.set_type, vu1.plate_number AS primary_plate, vu2.plate_number AS secondary_plate FROM vehicle_sets vs LEFT JOIN vehicle_units vu1 ON vs.primary_vehicle_unit_id = vu1.id LEFT JOIN vehicle_units vu2 ON vs.secondary_vehicle_unit_id = vu2.id WHERE vs.status = 'active' AND (vs.created_by_user_id = ? OR vs.id IN (SELECT entity_id FROM entity_access_grants WHERE entity_type = 'vehicle_set' AND granted_to_user_id = ? AND access_level IN ('view','edit') AND revoked_at IS NULL)) ORDER BY vs.set_type");
            $vsStmt->execute([$userId, $userId]);
            $vehicleSets = $vsStmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $vehicleSets = $localPdo->query("SELECT vs.id, vs.set_type, vu1.plate_number AS primary_plate, vu2.plate_number AS secondary_plate FROM vehicle_sets vs LEFT JOIN vehicle_units vu1 ON vs.primary_vehicle_unit_id = vu1.id LEFT JOIN vehicle_units vu2 ON vs.secondary_vehicle_unit_id = vu2.id WHERE vs.status = 'active' ORDER BY vs.set_type")->fetchAll(PDO::FETCH_ASSOC);
        }

        $blockingNotices = [];
        if (empty($contractors)) {
            $blockingNotices[] = ['message' => 'Сначала создайте подрядчика.', 'link' => '/company/contractors/create', 'action' => 'Создать подрядчика'];
        }
        if (empty($drivers)) {
            $blockingNotices[] = ['message' => 'Сначала создайте водителя.', 'link' => '/company/drivers', 'action' => 'Создать водителя'];
        }
        if (empty($vehicleSets)) {
            $blockingNotices[] = ['message' => 'Сначала создайте транспортный комплект.', 'link' => '/company/vehicle-sets', 'action' => 'Создать ТС'];
        }

        $contractorId = trim($_POST['contractor_id'] ?? '');
        $driverId = trim($_POST['driver_id'] ?? '');
        $vehicleSetId = trim($_POST['vehicle_set_id'] ?? '');
        $comments = trim($_POST['comments'] ?? '');

        // Validation
        if ($contractorId === '') {
            $errors['contractor_id'] = 'Обязательное поле';
        }
        if ($driverId === '') {
            $errors['driver_id'] = 'Обязательное поле';
        }
        if ($vehicleSetId === '') {
            $errors['vehicle_set_id'] = 'Обязательное поле';
        }

        // Backend access validation for logist
        if (empty($errors) && $isLogist) {
            // Check contractor access
            $cCheck = $localPdo->prepare("SELECT created_by_user_id FROM contractors WHERE id = ? AND status = 'active'");
            $cCheck->execute([(int)$contractorId]);
            $cOwner = $cCheck->fetchColumn();
            if ($cOwner === false) {
                $errors['contractor_id'] = 'Подрядчик не найден или неактивен.';
            } elseif ((int)$cOwner !== $userId) {
                $cGrant = $localPdo->prepare("SELECT COUNT(*) FROM entity_access_grants WHERE entity_type = 'contractor' AND entity_id = ? AND granted_to_user_id = ? AND access_level IN ('view','edit') AND revoked_at IS NULL");
                $cGrant->execute([(int)$contractorId, $userId]);
                if ($cGrant->fetchColumn() == 0) {
                    $errors['contractor_id'] = 'Подрядчик недоступен.';
                }
            }

            // Check driver access
            $dCheck = $localPdo->prepare("SELECT created_by_user_id FROM drivers WHERE id = ? AND status = 'active'");
            $dCheck->execute([(int)$driverId]);
            $dOwner = $dCheck->fetchColumn();
            if ($dOwner === false) {
                $errors['driver_id'] = 'Водитель не найден или неактивен.';
            } elseif ((int)$dOwner !== $userId) {
                $dGrant = $localPdo->prepare("SELECT COUNT(*) FROM entity_access_grants WHERE entity_type = 'driver' AND entity_id = ? AND granted_to_user_id = ? AND access_level IN ('view','edit') AND revoked_at IS NULL");
                $dGrant->execute([(int)$driverId, $userId]);
                if ($dGrant->fetchColumn() == 0) {
                    $errors['driver_id'] = 'Водитель недоступен.';
                }
            }

            // Check vehicle_set access
            $vsCheck = $localPdo->prepare("SELECT created_by_user_id FROM vehicle_sets WHERE id = ? AND status = 'active'");
            $vsCheck->execute([(int)$vehicleSetId]);
            $vsOwner = $vsCheck->fetchColumn();
            if ($vsOwner === false) {
                $errors['vehicle_set_id'] = 'Транспортный комплект не найден или неактивен.';
            } elseif ((int)$vsOwner !== $userId) {
                $vsGrant = $localPdo->prepare("SELECT COUNT(*) FROM entity_access_grants WHERE entity_type = 'vehicle_set' AND entity_id = ? AND granted_to_user_id = ? AND access_level IN ('view','edit') AND revoked_at IS NULL");
                $vsGrant->execute([(int)$vehicleSetId, $userId]);
                if ($vsGrant->fetchColumn() == 0) {
                    $errors['vehicle_set_id'] = 'Транспортный комплект недоступен.';
                }
            }
        }

        if (!empty($errors)) {
            ob_start(); require base_path('app/View/pages/company_route_executors_create.php');
            $content = ob_get_clean(); require base_path('app/View/layouts/main.php');
            return;
        }

        // --- Core logic: find or create driver_vehicle_block, then create crew ---
        $localPdo->beginTransaction();
        try {
            // The real DB schema: driver_vehicle_blocks has driver_id + vehicle_set_id.
            // crews still stores legacy driver_id + vehicle_id, where vehicle_id is the primary vehicle unit.
            $vehicleUnitStmt = $localPdo->prepare("SELECT primary_vehicle_unit_id FROM vehicle_sets WHERE id = ? AND status = 'active' LIMIT 1");
            $vehicleUnitStmt->execute([(int)$vehicleSetId]);
            $vehicleUnitId = (int)$vehicleUnitStmt->fetchColumn();

            if ($vehicleUnitId <= 0) {
                $localPdo->rollBack();
                $errors['vehicle_set_id'] = 'У выбранного транспортного комплекта не найдена основная транспортная единица.';
                ob_start(); require base_path('app/View/pages/company_route_executors_create.php');
                $content = ob_get_clean(); require base_path('app/View/layouts/main.php');
                return;
            }

            // 1. Find existing driver_vehicle_block
            $dvbStmt = $localPdo->prepare("SELECT id FROM driver_vehicle_blocks WHERE driver_id = ? AND vehicle_set_id = ? LIMIT 1");
            $dvbStmt->execute([(int)$driverId, (int)$vehicleSetId]);
            $dvbId = $dvbStmt->fetchColumn();

            if (!$dvbId) {
                // Create new driver_vehicle_block according to the current schema.
                $dvbInsert = $localPdo->prepare(
                    "INSERT INTO driver_vehicle_blocks (driver_id, vehicle_set_id, status, created_by_user_id, created_by_role)
                     VALUES (:driver_id, :vehicle_set_id, 'active', :uid, :role)"
                );
                $dvbInsert->execute([
                    ':driver_id' => (int)$driverId,
                    ':vehicle_set_id' => (int)$vehicleSetId,
                    ':uid' => $userId,
                    ':role' => $userRole,
                ]);
                $dvbId = (int)$localPdo->lastInsertId();
            }

            // 2. Check duplicate crew
            $dupStmt = $localPdo->prepare("SELECT COUNT(*) FROM crews WHERE contractor_id = ? AND driver_vehicle_block_id = ?");
            $dupStmt->execute([(int)$contractorId, $dvbId]);
            if ($dupStmt->fetchColumn() > 0) {
                $localPdo->rollBack();
                $errors['vehicle_set_id'] = 'Такой исполнитель рейса уже существует (подрядчик + водитель + ТС).';
                ob_start(); require base_path('app/View/pages/company_route_executors_create.php');
                $content = ob_get_clean(); require base_path('app/View/layouts/main.php');
                return;
            }

            // 3. Create crew. Keep legacy driver_id and vehicle_id filled for older company DBs.
            $insert = $localPdo->prepare(
                'INSERT INTO crews (contractor_id, vehicle_id, driver_id, driver_vehicle_block_id, status, comments, created_by_user_id, created_by_role)
                 VALUES (:contractor_id, :vehicle_id, :driver_id, :driver_vehicle_block_id, :status, :comments, :uid, :role)'
            );
            $insert->execute([
                ':contractor_id' => (int)$contractorId,
                ':vehicle_id' => $vehicleUnitId,
                ':driver_id' => (int)$driverId,
                ':driver_vehicle_block_id' => $dvbId,
                ':status' => 'active',
                ':comments' => $comments !== '' ? $comments : null,
                ':uid' => $userId,
                ':role' => $userRole,
            ]);

            $newId = (int)$localPdo->lastInsertId();
            $localPdo->commit();

            // Fetch created names for success display
            $ctrName = $localPdo->prepare("SELECT name FROM contractors WHERE id = ?");
            $ctrName->execute([(int)$contractorId]);
            $drvName = $localPdo->prepare("SELECT full_name FROM drivers WHERE id = ?");
            $drvName->execute([(int)$driverId]);
            $pltStmt = $localPdo->prepare("SELECT vu1.plate_number FROM vehicle_sets vs LEFT JOIN vehicle_units vu1 ON vs.primary_vehicle_unit_id = vu1.id WHERE vs.id = ?");
            $pltStmt->execute([(int)$vehicleSetId]);

            $createdExecutor = [
                'id' => $newId,
                'contractor_name' => $ctrName->fetchColumn() ?: '',
                'driver_name' => $drvName->fetchColumn() ?: '',
                'plate_number' => $pltStmt->fetchColumn() ?: '—',
            ];
            $success = true;
        } catch (\Exception $e) {
            $localPdo->rollBack();
            $formError = 'Ошибка создания исполнителя рейса: ' . $e->getMessage();
        }
    } catch (\Exception $e) {
        $company = $company ?? null;
        $formError = 'Ошибка создания исполнителя рейса: ' . $e->getMessage();
    }

    ob_start();
    require base_path('app/View/pages/company_route_executors_create.php');
    $content = ob_get_clean();
    require base_path('app/View/layouts/main.php');
});

// ============================================================
// BLOCK E3: Route executors — VIEW
// ============================================================

$router->get('/company/route-executors/{id}', function ($crewId) use ($config, $db) {
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

        // Access check for logist
        $accessDenied = null;
        if ($crew) {
            $isLogist = ($_SESSION['role_code'] ?? '') === 'logist';
            if ($isLogist) {
                $userId = (int)$_SESSION['user_id'];
                $hasGrant = false;
                $gc = $localPdo->prepare("SELECT access_level FROM entity_access_grants WHERE entity_type = 'crew' AND entity_id = ? AND granted_to_user_id = ? AND revoked_at IS NULL LIMIT 1");
                $gc->execute([$crewId, $userId]);
                $gr = $gc->fetch(PDO::FETCH_ASSOC);
                $hasGrant = ($gr && in_array($gr['access_level'], ['view', 'edit']));
                if ((int)$crew['created_by_user_id'] !== $userId && !$hasGrant) {
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
});

// ============================================================
// BLOCK E3: Route executors — EDIT
// ============================================================

$router->get('/company/route-executors/{id}/edit', function ($crewId) use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);
    $crewId = (int)$crewId;
    $pageTitle = 'Редактировать исполнителя рейса';
    $pageContext = 'Исполнители рейса › Компания';
    $entityNotFound = false;

    $companyId = (int)(getSessionCompanyId() ?? 0);
    $success = false;
    $formError = null;
    $errors = [];

    if ($companyId <= 0) {
        $company = null;
        $crew = null; $old = []; $dbError = null;
        $contractors = []; $drivers = []; $vehicleSets = [];
        $blockingNotices = [];

        ob_start();
        require base_path('app/View/pages/company_route_executor_edit.php');
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
            $crew = null; $old = []; $dbError = null;
            $contractors = []; $drivers = []; $vehicleSets = [];
            $blockingNotices = [];

            ob_start();
            require base_path('app/View/pages/company_route_executor_edit.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $pageContext = 'Исполнители рейса › Компания: ' . $company['name'];

        if ($company['status'] !== 'active') {
            $crew = null; $old = []; $dbError = null;
            $contractors = []; $drivers = []; $vehicleSets = [];
            $blockingNotices = [];

            ob_start();
            require base_path('app/View/pages/company_route_executor_edit.php');
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

        // Load crew with JOIN to get driver_id and vehicle_set_id
        $cStmt = $localPdo->prepare(
            "SELECT c.*,
                    ct.created_by_user_id AS contractor_created_by_user_id,
                    d.created_by_user_id AS driver_created_by_user_id,
                    dvb.driver_id, dvb.vehicle_set_id, dvb.created_by_user_id AS dvb_created_by_user_id,
                    vs.created_by_user_id AS vehicle_set_created_by_user_id
             FROM crews c
             JOIN contractors ct ON c.contractor_id = ct.id
             JOIN driver_vehicle_blocks dvb ON c.driver_vehicle_block_id = dvb.id
             JOIN drivers d ON dvb.driver_id = d.id
             JOIN vehicle_sets vs ON dvb.vehicle_set_id = vs.id
             WHERE c.id = ?"
        );
        $cStmt->execute([$crewId]);
        $crew = $cStmt->fetch(PDO::FETCH_ASSOC);

        if (!$crew) {
            $entityNotFound = true;
            $old = [];
            $contractors = []; $drivers = []; $vehicleSets = [];
            $blockingNotices = [];
        } else {
            // Permission check
            $currentUserId = (int)($_SESSION['user_id'] ?? 0);
            $currentRole = $_SESSION['role_code'] ?? '';

            if ($currentRole !== 'company_owner' && $currentRole !== 'senior_logist') {
                if (!hasRouteExecutorAccess($localPdo, $crew, $currentUserId, 'edit')) {
                    http_response_code(403);
                    header('Content-Type: text/plain; charset=utf-8');
                    echo 'Нет доступа к редактированию исполнителя рейса.';
                    exit;
                }
            }

            $old = $crew;

            $isLogist = ($_SESSION['role_code'] ?? '') === 'logist';
            $userId = (int)$_SESSION['user_id'];

            // Load contractors
            if ($isLogist) {
                $cStmt = $localPdo->prepare("SELECT id, name, inn FROM contractors WHERE status = 'active' AND (created_by_user_id = ? OR id IN (SELECT entity_id FROM entity_access_grants WHERE entity_type = 'contractor' AND granted_to_user_id = ? AND access_level IN ('view','edit') AND revoked_at IS NULL)) ORDER BY name");
                $cStmt->execute([$userId, $userId]);
                $contractors = $cStmt->fetchAll(PDO::FETCH_ASSOC);
            } else {
                $contractors = $localPdo->query("SELECT id, name, inn FROM contractors WHERE status = 'active' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
            }

            // Load drivers (include current driver even if inactive)
            $currentDriverId = (int)($crew['driver_id'] ?? 0);
            if ($isLogist) {
                $dStmt = $localPdo->prepare(
                    "SELECT id, full_name, phone FROM drivers
                     WHERE (status = 'active'" . ($currentDriverId > 0 ? " OR id = ?" : "") . ")
                       AND (created_by_user_id = ? OR id IN (SELECT entity_id FROM entity_access_grants WHERE entity_type = 'driver' AND granted_to_user_id = ? AND access_level IN ('view','edit') AND revoked_at IS NULL))
                     ORDER BY full_name"
                );
                $params = $currentDriverId > 0 ? [$currentDriverId, $userId, $userId] : [$userId, $userId];
                $dStmt->execute($params);
                $drivers = $dStmt->fetchAll(PDO::FETCH_ASSOC);
            } else {
                $drivers = $localPdo->query(
                    "SELECT id, full_name, phone FROM drivers
                     WHERE status = 'active'" . ($currentDriverId > 0 ? " OR id = " . $currentDriverId : "") . "
                     ORDER BY full_name"
                )->fetchAll(PDO::FETCH_ASSOC);
            }

            // Load vehicle sets (include current even if inactive)
            $currentVsId = (int)($crew['vehicle_set_id'] ?? 0);
            if ($isLogist) {
                $vsStmt = $localPdo->prepare(
                    "SELECT vs.id, vs.set_type, vu1.plate_number AS primary_plate, vu2.plate_number AS secondary_plate
                     FROM vehicle_sets vs
                     LEFT JOIN vehicle_units vu1 ON vs.primary_vehicle_unit_id = vu1.id
                     LEFT JOIN vehicle_units vu2 ON vs.secondary_vehicle_unit_id = vu2.id
                     WHERE (vs.status = 'active'" . ($currentVsId > 0 ? " OR vs.id = ?" : "") . ")
                       AND (vs.created_by_user_id = ? OR vs.id IN (SELECT entity_id FROM entity_access_grants WHERE entity_type = 'vehicle_set' AND granted_to_user_id = ? AND access_level IN ('view','edit') AND revoked_at IS NULL))
                     ORDER BY vs.set_type"
                );
                $params = $currentVsId > 0 ? [$currentVsId, $userId, $userId] : [$userId, $userId];
                $vsStmt->execute($params);
                $vehicleSets = $vsStmt->fetchAll(PDO::FETCH_ASSOC);
            } else {
                $vehicleSets = $localPdo->query(
                    "SELECT vs.id, vs.set_type, vu1.plate_number AS primary_plate, vu2.plate_number AS secondary_plate
                     FROM vehicle_sets vs
                     LEFT JOIN vehicle_units vu1 ON vs.primary_vehicle_unit_id = vu1.id
                     LEFT JOIN vehicle_units vu2 ON vs.secondary_vehicle_unit_id = vu2.id
                     WHERE vs.status = 'active'" . ($currentVsId > 0 ? " OR vs.id = " . $currentVsId : "") . "
                     ORDER BY vs.set_type"
                )->fetchAll(PDO::FETCH_ASSOC);
            }

            $blockingNotices = [];
            if (empty($contractors)) {
                $blockingNotices[] = ['message' => 'Нет активных подрядчиков.', 'link' => '/company/contractors/create', 'action' => 'Создать подрядчика'];
            }
            if (empty($drivers)) {
                $blockingNotices[] = ['message' => 'Нет активных водителей.', 'link' => '/company/drivers', 'action' => 'Создать водителя'];
            }
            if (empty($vehicleSets)) {
                $blockingNotices[] = ['message' => 'Нет активных транспортных комплектов.', 'link' => '/company/vehicle-sets', 'action' => 'Создать ТС'];
            }
        }

        $dbError = null;
    } catch (\Exception $e) {
        $company = $company ?? null;
        $crew = null; $old = [];
        $dbError = 'Не удалось подключиться к базе данных компании.';
        $contractors = []; $drivers = []; $vehicleSets = [];
        $blockingNotices = [];
    }

    ob_start();
    require base_path('app/View/pages/company_route_executor_edit.php');
    $content = ob_get_clean();
    require base_path('app/View/layouts/main.php');
});

$router->post('/company/route-executors/{id}/edit', function ($crewId) use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);
    $crewId = (int)$crewId;
    $pageTitle = 'Редактировать исполнителя рейса';
    $pageContext = 'Исполнители рейса › Компания';
    $entityNotFound = false;

    $companyId = (int)(getSessionCompanyId() ?? 0);
    $errors = [];
    $old = $_POST;
    $formError = null;
    $success = false;

    if ($companyId <= 0) {
        $company = null; $crew = null; $dbError = null;
        $contractors = []; $drivers = []; $vehicleSets = [];
        $blockingNotices = [];
        $formError = 'Компания не найдена';

        ob_start();
        require base_path('app/View/pages/company_route_executor_edit.php');
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
            $company = null; $crew = null; $dbError = null;
            $contractors = []; $drivers = []; $vehicleSets = [];
            $blockingNotices = [];
            $formError = 'Компания не найдена';

            ob_start();
            require base_path('app/View/pages/company_route_executor_edit.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $pageContext = 'Исполнители рейса › Компания: ' . $company['name'];

        if ($company['status'] !== 'active') {
            $crew = null; $dbError = null;
            $contractors = []; $drivers = []; $vehicleSets = [];
            $blockingNotices = [];
            $formError = 'Редактирование исполнителя рейса недоступно';

            ob_start();
            require base_path('app/View/pages/company_route_executor_edit.php');
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
            $localPdo->query("SELECT 1 FROM driver_vehicle_blocks LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $migrationSql = file_get_contents(base_path('database/migrations-local/018_create_driver_vehicle_blocks.sql'));
            $localPdo->exec($migrationSql);
        }

        $isLogist = ($_SESSION['role_code'] ?? '') === 'logist';
        $userId = (int)$_SESSION['user_id'];
        $userRole = $_SESSION['role_code'];

        // Load current crew with DV block info
        $cStmt = $localPdo->prepare(
            "SELECT c.*,
                    ct.created_by_user_id AS contractor_created_by_user_id,
                    d.created_by_user_id AS driver_created_by_user_id,
                    dvb.driver_id, dvb.vehicle_set_id, dvb.created_by_user_id AS dvb_created_by_user_id,
                    vs.created_by_user_id AS vehicle_set_created_by_user_id
             FROM crews c
             JOIN contractors ct ON c.contractor_id = ct.id
             JOIN driver_vehicle_blocks dvb ON c.driver_vehicle_block_id = dvb.id
             JOIN drivers d ON dvb.driver_id = d.id
             JOIN vehicle_sets vs ON dvb.vehicle_set_id = vs.id
             WHERE c.id = ?"
        );
        $cStmt->execute([$crewId]);
        $crew = $cStmt->fetch(PDO::FETCH_ASSOC);

        if (!$crew) {
            $entityNotFound = true;
            $dbError = null;
            $contractors = []; $drivers = []; $vehicleSets = [];
            $blockingNotices = [];

            ob_start();
            require base_path('app/View/pages/company_route_executor_edit.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        // Permission check
        $currentUserId = (int)($_SESSION['user_id'] ?? 0);
        $currentRole = $_SESSION['role_code'] ?? '';

        if ($currentRole !== 'company_owner' && $currentRole !== 'senior_logist') {
            if (!hasRouteExecutorAccess($localPdo, $crew, $currentUserId, 'edit')) {
                http_response_code(403);
                header('Content-Type: text/plain; charset=utf-8');
                echo 'Нет доступа к редактированию исполнителя рейса.';
                exit;
            }
        }

        // Reload dropdowns
        if ($isLogist) {
            $cStmt = $localPdo->prepare("SELECT id, name, inn FROM contractors WHERE status = 'active' AND (created_by_user_id = ? OR id IN (SELECT entity_id FROM entity_access_grants WHERE entity_type = 'contractor' AND granted_to_user_id = ? AND access_level IN ('view','edit') AND revoked_at IS NULL)) ORDER BY name");
            $cStmt->execute([$userId, $userId]);
            $contractors = $cStmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $contractors = $localPdo->query("SELECT id, name, inn FROM contractors WHERE status = 'active' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
        }

        $currentDriverId = (int)($crew['driver_id'] ?? 0);
        if ($isLogist) {
            $dStmt = $localPdo->prepare(
                "SELECT id, full_name, phone FROM drivers
                 WHERE (status = 'active'" . ($currentDriverId > 0 ? " OR id = ?" : "") . ")
                   AND (created_by_user_id = ? OR id IN (SELECT entity_id FROM entity_access_grants WHERE entity_type = 'driver' AND granted_to_user_id = ? AND access_level IN ('view','edit') AND revoked_at IS NULL))
                 ORDER BY full_name"
            );
            $params = $currentDriverId > 0 ? [$currentDriverId, $userId, $userId] : [$userId, $userId];
            $dStmt->execute($params);
            $drivers = $dStmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $drivers = $localPdo->query(
                "SELECT id, full_name, phone FROM drivers
                 WHERE status = 'active'" . ($currentDriverId > 0 ? " OR id = " . $currentDriverId : "") . "
                 ORDER BY full_name"
            )->fetchAll(PDO::FETCH_ASSOC);
        }

        $currentVsId = (int)($crew['vehicle_set_id'] ?? 0);
        if ($isLogist) {
            $vsStmt = $localPdo->prepare(
                "SELECT vs.id, vs.set_type, vu1.plate_number AS primary_plate, vu2.plate_number AS secondary_plate
                 FROM vehicle_sets vs
                 LEFT JOIN vehicle_units vu1 ON vs.primary_vehicle_unit_id = vu1.id
                 LEFT JOIN vehicle_units vu2 ON vs.secondary_vehicle_unit_id = vu2.id
                 WHERE (vs.status = 'active'" . ($currentVsId > 0 ? " OR vs.id = ?" : "") . ")
                   AND (vs.created_by_user_id = ? OR vs.id IN (SELECT entity_id FROM entity_access_grants WHERE entity_type = 'vehicle_set' AND granted_to_user_id = ? AND access_level IN ('view','edit') AND revoked_at IS NULL))
                 ORDER BY vs.set_type"
            );
            $params = $currentVsId > 0 ? [$currentVsId, $userId, $userId] : [$userId, $userId];
            $vsStmt->execute($params);
            $vehicleSets = $vsStmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $vehicleSets = $localPdo->query(
                "SELECT vs.id, vs.set_type, vu1.plate_number AS primary_plate, vu2.plate_number AS secondary_plate
                 FROM vehicle_sets vs
                 LEFT JOIN vehicle_units vu1 ON vs.primary_vehicle_unit_id = vu1.id
                 LEFT JOIN vehicle_units vu2 ON vs.secondary_vehicle_unit_id = vu2.id
                 WHERE vs.status = 'active'" . ($currentVsId > 0 ? " OR vs.id = " . $currentVsId : "") . "
                 ORDER BY vs.set_type"
            )->fetchAll(PDO::FETCH_ASSOC);
        }

        $blockingNotices = [];
        if (empty($contractors)) {
            $blockingNotices[] = ['message' => 'Нет активных подрядчиков.', 'link' => '/company/contractors/create', 'action' => 'Создать подрядчика'];
        }
        if (empty($drivers)) {
            $blockingNotices[] = ['message' => 'Нет активных водителей.', 'link' => '/company/drivers', 'action' => 'Создать водителя'];
        }
        if (empty($vehicleSets)) {
            $blockingNotices[] = ['message' => 'Нет активных транспортных комплектов.', 'link' => '/company/vehicle-sets', 'action' => 'Создать ТС'];
        }

        if (!empty($blockingNotices)) {
            $dbError = null;

            ob_start();
            require base_path('app/View/pages/company_route_executor_edit.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $contractorId = trim($_POST['contractor_id'] ?? '');
        $driverId = trim($_POST['driver_id'] ?? '');
        $vehicleSetId = trim($_POST['vehicle_set_id'] ?? '');

        if ($contractorId === '') {
            $errors['contractor_id'] = 'Выберите подрядчика';
        }
        if ($driverId === '') {
            $errors['driver_id'] = 'Выберите водителя';
        }
        if ($vehicleSetId === '') {
            $errors['vehicle_set_id'] = 'Выберите транспортный комплект';
        }

        // Backend access validation for logist
        if (empty($errors) && $isLogist) {
            $cCheck = $localPdo->prepare("SELECT created_by_user_id FROM contractors WHERE id = ?");
            $cCheck->execute([(int)$contractorId]);
            $cOwner = $cCheck->fetchColumn();
            if ($cOwner !== false && (int)$cOwner !== $userId) {
                $cGrant = $localPdo->prepare("SELECT COUNT(*) FROM entity_access_grants WHERE entity_type = 'contractor' AND entity_id = ? AND granted_to_user_id = ? AND access_level IN ('view','edit') AND revoked_at IS NULL");
                $cGrant->execute([(int)$contractorId, $userId]);
                if ($cGrant->fetchColumn() == 0) {
                    $errors['contractor_id'] = 'Подрядчик недоступен.';
                }
            }

            $dCheck = $localPdo->prepare("SELECT created_by_user_id FROM drivers WHERE id = ?");
            $dCheck->execute([(int)$driverId]);
            $dOwner = $dCheck->fetchColumn();
            if ($dOwner !== false && (int)$dOwner !== $userId) {
                $dGrant = $localPdo->prepare("SELECT COUNT(*) FROM entity_access_grants WHERE entity_type = 'driver' AND entity_id = ? AND granted_to_user_id = ? AND access_level IN ('view','edit') AND revoked_at IS NULL");
                $dGrant->execute([(int)$driverId, $userId]);
                if ($dGrant->fetchColumn() == 0) {
                    $errors['driver_id'] = 'Водитель недоступен.';
                }
            }

            $vsCheck = $localPdo->prepare("SELECT created_by_user_id FROM vehicle_sets WHERE id = ?");
            $vsCheck->execute([(int)$vehicleSetId]);
            $vsOwner = $vsCheck->fetchColumn();
            if ($vsOwner !== false && (int)$vsOwner !== $userId) {
                $vsGrant = $localPdo->prepare("SELECT COUNT(*) FROM entity_access_grants WHERE entity_type = 'vehicle_set' AND entity_id = ? AND granted_to_user_id = ? AND access_level IN ('view','edit') AND revoked_at IS NULL");
                $vsGrant->execute([(int)$vehicleSetId, $userId]);
                if ($vsGrant->fetchColumn() == 0) {
                    $errors['vehicle_set_id'] = 'Транспортный комплект недоступен.';
                }
            }
        }

        if (!empty($errors)) {
            ob_start();
            require base_path('app/View/pages/company_route_executor_edit.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        // --- Core logic: find or create driver_vehicle_block, then update crew ---
        $localPdo->beginTransaction();
        try {
            // 1. Find or create driver_vehicle_block for new driver+vehicle_set
            $dvbStmt = $localPdo->prepare("SELECT id FROM driver_vehicle_blocks WHERE driver_id = ? AND vehicle_set_id = ? LIMIT 1");
            $dvbStmt->execute([(int)$driverId, (int)$vehicleSetId]);
            $newDvbId = $dvbStmt->fetchColumn();

            if (!$newDvbId) {
                $dvbInsert = $localPdo->prepare(
                    "INSERT INTO driver_vehicle_blocks (driver_id, vehicle_set_id, status, created_by_user_id, created_by_role)
                     VALUES (:driver_id, :vehicle_set_id, 'active', :uid, :role)"
                );
                $dvbInsert->execute([
                    ':driver_id' => (int)$driverId,
                    ':vehicle_set_id' => (int)$vehicleSetId,
                    ':uid' => $userId,
                    ':role' => $userRole,
                ]);
                $newDvbId = (int)$localPdo->lastInsertId();
            }

            // 2. Check duplicate (excluding current crew)
            $dupStmt = $localPdo->prepare(
                "SELECT COUNT(*) FROM crews WHERE contractor_id = ? AND driver_vehicle_block_id = ? AND id != ?"
            );
            $dupStmt->execute([(int)$contractorId, $newDvbId, $crewId]);
            if ($dupStmt->fetchColumn() > 0) {
                $localPdo->rollBack();
                $formError = 'Такой исполнитель рейса уже существует в этой компании.';
                ob_start();
                require base_path('app/View/pages/company_route_executor_edit.php');
                $content = ob_get_clean();
                require base_path('app/View/layouts/main.php');
                return;
            }

            // 3. Update crew
            $status = trim($_POST['status'] ?? 'active');
            $comments = trim($_POST['comments'] ?? '');

            $update = $localPdo->prepare(
                'UPDATE crews SET contractor_id = :contractor_id, driver_vehicle_block_id = :driver_vehicle_block_id,
                 status = :status, comments = :comments,
                 updated_by_user_id = :uid, updated_by_role = :role
                 WHERE id = :id'
            );
            $update->execute([
                ':contractor_id' => (int)$contractorId,
                ':driver_vehicle_block_id' => $newDvbId,
                ':status' => $status,
                ':comments' => $comments !== '' ? $comments : null,
                ':uid' => $userId,
                ':role' => $userRole,
                ':id' => $crewId,
            ]);

            $localPdo->commit();

            // Fetch updated crew for success display
            $cStmt = $localPdo->prepare(
                "SELECT c.*,
                        ct.name AS contractor_name,
                        vu1.plate_number,
                        d.full_name AS driver_name
                 FROM crews c
                 JOIN contractors ct ON c.contractor_id = ct.id
                 JOIN driver_vehicle_blocks dvb ON c.driver_vehicle_block_id = dvb.id
                 JOIN drivers d ON dvb.driver_id = d.id
                 JOIN vehicle_sets vs ON dvb.vehicle_set_id = vs.id
                 LEFT JOIN vehicle_units vu1 ON vs.primary_vehicle_unit_id = vu1.id
                 WHERE c.id = ?"
            );
            $cStmt->execute([$crewId]);
            $crew = $cStmt->fetch(PDO::FETCH_ASSOC);
            $success = true;
            $dbError = null;
        } catch (\Exception $e) {
            $localPdo->rollBack();
            $formError = 'Ошибка обновления исполнителя рейса: ' . $e->getMessage();
        }
    } catch (\Exception $e) {
        $company = $company ?? null;
        $crew = $crew ?? null;
        $dbError = null;
        $contractors = $contractors ?? [];
        $drivers = $drivers ?? [];
        $vehicleSets = $vehicleSets ?? [];
        $blockingNotices = $blockingNotices ?? [];
        $formError = 'Ошибка обновления исполнителя рейса: ' . $e->getMessage();
    }

    ob_start();
    require base_path('app/View/pages/company_route_executor_edit.php');
    $content = ob_get_clean();
    require base_path('app/View/layouts/main.php');
});

// ============================================================
// BLOCK E3: Route executors — ARCHIVE
// ============================================================

$router->post('/company/route-executors/{id}/archive', function ($crewId) use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);
    $crewId = (int)$crewId;

    $companyId = (int)(getSessionCompanyId() ?? 0);

    if ($companyId <= 0) {
        header('Location: /company/route-executors');
        exit;
    }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company || $company['status'] !== 'active') {
            header('Location: /company/route-executors');
            exit;
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

        // Permission check
        $currentUserId = (int)($_SESSION['user_id'] ?? 0);
        $currentRole = $_SESSION['role_code'] ?? '';

        $crewStmt = $localPdo->prepare('SELECT * FROM crews WHERE id = ?');
        $crewStmt->execute([$crewId]);
        $crew = $crewStmt->fetch(PDO::FETCH_ASSOC);

        if (!$crew) {
            header('Location: /company/route-executors');
            exit;
        }

        if ($currentRole !== 'company_owner' && $currentRole !== 'senior_logist') {
            $isCreator = ($crew['created_by_user_id'] ?? 0) === $currentUserId;

            $grantStmt = $localPdo->prepare(
                "SELECT 1 FROM entity_access_grants
                 WHERE entity_type = 'crew' AND entity_id = ?
                 AND granted_to_user_id = ? AND access_level = 'edit'
                 AND (revoked_at IS NULL)"
            );
            $grantStmt->execute([$crewId, $currentUserId]);
            $hasEditGrant = (bool)$grantStmt->fetchColumn();

            if (!$isCreator && !$hasEditGrant) {
                http_response_code(403);
                header('Content-Type: text/plain');
                echo '403 Forbidden';
                exit;
            }
        }

        $update = $localPdo->prepare("UPDATE crews SET status = 'archived' WHERE id = ?");
        $update->execute([$crewId]);

        header('Location: /company/route-executors');
        exit;
    } catch (\Exception $e) {
        header('Location: /company/route-executors');
        exit;
    }
});

// ============================================================
// BLOCK E4: Responsible assignments — logist reassignment
// ============================================================
