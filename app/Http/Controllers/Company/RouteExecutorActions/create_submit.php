<?php

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
