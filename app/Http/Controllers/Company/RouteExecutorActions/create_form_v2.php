<?php
requireRole(['company_owner', 'senior_logist', 'logist']);
$pageTitle = 'Создать исполнителя рейса';
$pageContext = 'Исполнители рейса › Компания';
$companyId = (int)(getSessionCompanyId() ?? 0);
$success = false; $errors = []; $old = []; $formError = null; $createdExecutor = null; $blockingNotices = [];
$contractors = []; $drivers = []; $vehicleSets = [];

try {
    $pdo = $db->connection();
    $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
    $stmt->execute([$companyId]);
    $company = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$company) { $company = null; $formError = 'Компания не найдена'; }
    elseif ($company['status'] !== 'active') { $formError = 'Создание исполнителя рейса недоступно'; }
    else {
        $localPdo = (new \App\Core\Database(companyDatabaseConfig($config, $company)))->connection();
        $isLogist = ($_SESSION['role_code'] ?? '') === 'logist';
        $userId = (int)($_SESSION['user_id'] ?? 0);
        if ($isLogist) {
            $s=$localPdo->prepare("SELECT id,name,inn FROM contractors WHERE deleted_at IS NULL AND status='active' AND (created_by_user_id=? OR id IN (SELECT entity_id FROM entity_access_grants WHERE entity_type='contractor' AND granted_to_user_id=? AND access_level IN ('view','edit') AND revoked_at IS NULL)) ORDER BY name"); $s->execute([$userId,$userId]); $contractors=$s->fetchAll(PDO::FETCH_ASSOC);
            $s=$localPdo->prepare("SELECT id,full_name,phone FROM drivers WHERE deleted_at IS NULL AND status='active' AND (created_by_user_id=? OR id IN (SELECT entity_id FROM entity_access_grants WHERE entity_type='driver' AND granted_to_user_id=? AND access_level IN ('view','edit') AND revoked_at IS NULL)) ORDER BY full_name"); $s->execute([$userId,$userId]); $drivers=$s->fetchAll(PDO::FETCH_ASSOC);
            $s=$localPdo->prepare("SELECT vs.id,vs.set_type,vu1.plate_number primary_plate,vu2.plate_number secondary_plate FROM vehicle_sets vs LEFT JOIN vehicle_units vu1 ON vs.primary_vehicle_unit_id=vu1.id LEFT JOIN vehicle_units vu2 ON vs.secondary_vehicle_unit_id=vu2.id WHERE vs.deleted_at IS NULL AND vs.status='active' AND (vs.created_by_user_id=? OR vs.id IN (SELECT entity_id FROM entity_access_grants WHERE entity_type='vehicle_set' AND granted_to_user_id=? AND access_level IN ('view','edit') AND revoked_at IS NULL)) ORDER BY vs.set_type"); $s->execute([$userId,$userId]); $vehicleSets=$s->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $contractors=$localPdo->query("SELECT id,name,inn FROM contractors WHERE deleted_at IS NULL AND status='active' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
            $drivers=$localPdo->query("SELECT id,full_name,phone FROM drivers WHERE deleted_at IS NULL AND status='active' ORDER BY full_name")->fetchAll(PDO::FETCH_ASSOC);
            $vehicleSets=$localPdo->query("SELECT vs.id,vs.set_type,vu1.plate_number primary_plate,vu2.plate_number secondary_plate FROM vehicle_sets vs LEFT JOIN vehicle_units vu1 ON vs.primary_vehicle_unit_id=vu1.id LEFT JOIN vehicle_units vu2 ON vs.secondary_vehicle_unit_id=vu2.id WHERE vs.deleted_at IS NULL AND vs.status='active' ORDER BY vs.set_type")->fetchAll(PDO::FETCH_ASSOC);
        }
        if (!$contractors) $blockingNotices[]=['message'=>'Сначала создайте подрядчика.','link'=>'/company/contractors/create','action'=>'Создать подрядчика'];
        if (!$drivers) $blockingNotices[]=['message'=>'Сначала создайте водителя.','link'=>'/company/drivers','action'=>'Создать водителя'];
        if (!$vehicleSets) $blockingNotices[]=['message'=>'Сначала создайте транспортный комплект.','link'=>'/company/vehicle-sets','action'=>'Создать ТС'];
    }
} catch (Throwable $e) { $company=$company??null; $formError='Ошибка загрузки данных: '.$e->getMessage(); }

$routeExecutorCreateFormMode = ($_GET['_modal'] ?? '') === '1' ? 'modal' : 'page';
$routeExecutorFormId = 'route-executor-create-form';
$routeExecutorFormAction = app_url('/company/route-executors/create');
ob_start(); require base_path('app/View/partials/company_route_executor_create_form_v2.php'); $content=ob_get_clean();
require base_path('app/View/layouts/main.php');
