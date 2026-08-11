<?php

requireRole(['company_owner', 'senior_logist', 'logist']);
require_once base_path('app/Support/crew_driver_helpers.php');
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
$company = null;

$render = static function () use (&$company,&$success,&$errors,&$old,&$formError,&$createdExecutor,&$blockingNotices,&$contractors,&$drivers,&$vehicleSets,$config): void {
    $renderMode = ($_POST['_is_modal'] ?? '') === '1' ? 'modal' : 'page';
    if ($renderMode === 'modal') {
        header('Content-Type: text/html; charset=utf-8');
        if ($success) { echo '<div data-route-executor-create-success="1"></div>'; return; }
        $routeExecutorCreateFormMode = 'modal';
        $routeExecutorFormId = 'route-executor-create-form';
        $routeExecutorFormAction = app_url('/company/route-executors/create');
        require base_path('app/View/partials/company_route_executor_create_form.php');
        return;
    }
    ob_start();
    require base_path('app/View/pages/company_route_executors_create.php');
    $content = ob_get_clean();
    require base_path('app/View/layouts/main.php');
};

try {
    if ($companyId <= 0) throw new RuntimeException('Компания не найдена.');
    $pdo = $db->connection();
    $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
    $stmt->execute([$companyId]);
    $company = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    if (!$company) throw new RuntimeException('Компания не найдена.');
    if (($company['status'] ?? '') !== 'active') throw new RuntimeException('Создание исполнителя рейса недоступно для неактивной компании.');
    $pageContext = 'Исполнители рейса › Компания: ' . $company['name'];

    $localPdo = (new \App\Core\Database(companyDatabaseConfig($config, $company)))->connection();
    $isLogist = ($_SESSION['role_code'] ?? '') === 'logist';
    $userId = (int)($_SESSION['user_id'] ?? 0);
    $userRole = (string)($_SESSION['role_code'] ?? '');

    if ($isLogist) {
        $s=$localPdo->prepare("SELECT id,name,inn FROM contractors WHERE deleted_at IS NULL AND status='active' AND (created_by_user_id=? OR id IN(SELECT entity_id FROM entity_access_grants WHERE entity_type='contractor' AND granted_to_user_id=? AND access_level IN('view','edit') AND revoked_at IS NULL)) ORDER BY name"); $s->execute([$userId,$userId]); $contractors=$s->fetchAll(PDO::FETCH_ASSOC);
        $s=$localPdo->prepare("SELECT id,full_name,phone FROM drivers WHERE deleted_at IS NULL AND status='active' AND (created_by_user_id=? OR id IN(SELECT entity_id FROM entity_access_grants WHERE entity_type='driver' AND granted_to_user_id=? AND access_level IN('view','edit') AND revoked_at IS NULL)) ORDER BY full_name"); $s->execute([$userId,$userId]); $drivers=$s->fetchAll(PDO::FETCH_ASSOC);
        $s=$localPdo->prepare("SELECT vs.id,vs.set_type,vu1.plate_number primary_plate,vu2.plate_number secondary_plate FROM vehicle_sets vs LEFT JOIN vehicle_units vu1 ON vs.primary_vehicle_unit_id=vu1.id LEFT JOIN vehicle_units vu2 ON vs.secondary_vehicle_unit_id=vu2.id WHERE vs.deleted_at IS NULL AND vs.status='active' AND (vs.created_by_user_id=? OR vs.id IN(SELECT entity_id FROM entity_access_grants WHERE entity_type='vehicle_set' AND granted_to_user_id=? AND access_level IN('view','edit') AND revoked_at IS NULL)) ORDER BY vs.set_type"); $s->execute([$userId,$userId]); $vehicleSets=$s->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $contractors=$localPdo->query("SELECT id,name,inn FROM contractors WHERE deleted_at IS NULL AND status='active' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
        $drivers=$localPdo->query("SELECT id,full_name,phone FROM drivers WHERE deleted_at IS NULL AND status='active' ORDER BY full_name")->fetchAll(PDO::FETCH_ASSOC);
        $vehicleSets=$localPdo->query("SELECT vs.id,vs.set_type,vu1.plate_number primary_plate,vu2.plate_number secondary_plate FROM vehicle_sets vs LEFT JOIN vehicle_units vu1 ON vs.primary_vehicle_unit_id=vu1.id LEFT JOIN vehicle_units vu2 ON vs.secondary_vehicle_unit_id=vu2.id WHERE vs.deleted_at IS NULL AND vs.status='active' ORDER BY vs.set_type")->fetchAll(PDO::FETCH_ASSOC);
    }

    $contractorId=(int)($_POST['contractor_id']??0);
    $vehicleSetId=(int)($_POST['vehicle_set_id']??0);
    $rawDriverIds=is_array($_POST['driver_ids']??null)?$_POST['driver_ids']:[];
    $driverIds=normalizeCrewDriverIds($rawDriverIds);
    $old['driver_ids']=$rawDriverIds ?: [''];
    $comments=trim((string)($_POST['comments']??''));

    if($contractorId<=0)$errors['contractor_id']='Выберите подрядчика.';
    if(!$driverIds)$errors['driver_ids']='Выберите хотя бы одного водителя.';
    if(count($driverIds)!==count(array_filter(array_map('intval',$rawDriverIds))))$errors['driver_ids']='Один и тот же водитель не может быть добавлен в экипаж дважды.';
    if($vehicleSetId<=0)$errors['vehicle_set_id']='Выберите транспортный комплект.';

    $checkDriverAccess = static function(PDO $pdo,int $driverId,bool $isLogist,int $userId): bool {
        $s=$pdo->prepare("SELECT created_by_user_id FROM drivers WHERE id=? AND deleted_at IS NULL AND status='active'");$s->execute([$driverId]);$owner=$s->fetchColumn();
        if($owner===false)return false; if(!$isLogist||(int)$owner===$userId)return true;
        $g=$pdo->prepare("SELECT COUNT(*) FROM entity_access_grants WHERE entity_type='driver' AND entity_id=? AND granted_to_user_id=? AND access_level IN('view','edit') AND revoked_at IS NULL");$g->execute([$driverId,$userId]);return (int)$g->fetchColumn()>0;
    };
    foreach($driverIds as $driverId){ if(!$checkDriverAccess($localPdo,$driverId,$isLogist,$userId)){ $errors['driver_ids']='Один из выбранных водителей недоступен или неактивен.'; break; } }

    if(!$errors){
        $s=$localPdo->prepare("SELECT primary_vehicle_unit_id FROM vehicle_sets WHERE id=? AND deleted_at IS NULL AND status='active'");$s->execute([$vehicleSetId]);$vehicleUnitId=(int)$s->fetchColumn();
        if($vehicleUnitId<=0)$errors['vehicle_set_id']='У выбранного транспортного комплекта не найдена основная транспортная единица.';
    }
    if($isLogist&&!$errors){
        $s=$localPdo->prepare("SELECT created_by_user_id FROM contractors WHERE id=? AND deleted_at IS NULL AND status='active'");$s->execute([$contractorId]);$owner=$s->fetchColumn();
        if($owner===false)$errors['contractor_id']='Подрядчик не найден или неактивен.';
        elseif((int)$owner!==$userId){$g=$localPdo->prepare("SELECT COUNT(*) FROM entity_access_grants WHERE entity_type='contractor' AND entity_id=? AND granted_to_user_id=? AND access_level IN('view','edit') AND revoked_at IS NULL");$g->execute([$contractorId,$userId]);if((int)$g->fetchColumn()===0)$errors['contractor_id']='Подрядчик недоступен.';}
        $s=$localPdo->prepare("SELECT created_by_user_id FROM vehicle_sets WHERE id=? AND deleted_at IS NULL AND status='active'");$s->execute([$vehicleSetId]);$owner=$s->fetchColumn();
        if($owner===false)$errors['vehicle_set_id']='Транспортный комплект не найден или неактивен.';
        elseif((int)$owner!==$userId){$g=$localPdo->prepare("SELECT COUNT(*) FROM entity_access_grants WHERE entity_type='vehicle_set' AND entity_id=? AND granted_to_user_id=? AND access_level IN('view','edit') AND revoked_at IS NULL");$g->execute([$vehicleSetId,$userId]);if((int)$g->fetchColumn()===0)$errors['vehicle_set_id']='Транспортный комплект недоступен.';}
    }

    if(!$errors){
        $tableExists=(int)$localPdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='crew_drivers'")->fetchColumn();
        if(!$tableExists)throw new RuntimeException('Структура экипажей ещё не обновлена. Повторите попытку после завершения обновления системы.');
        $primaryDriverId=$driverIds[0];
        $localPdo->beginTransaction();
        try{
            $s=$localPdo->prepare('SELECT id FROM driver_vehicle_blocks WHERE driver_id=? AND vehicle_set_id=? LIMIT 1');$s->execute([$primaryDriverId,$vehicleSetId]);$dvbId=(int)$s->fetchColumn();
            if(!$dvbId){$i=$localPdo->prepare("INSERT INTO driver_vehicle_blocks(driver_id,vehicle_set_id,status,created_by_user_id,created_by_role) VALUES(?,?, 'active',?,?)");$i->execute([$primaryDriverId,$vehicleSetId,$userId,$userRole]);$dvbId=(int)$localPdo->lastInsertId();}
            $dup=$localPdo->prepare('SELECT COUNT(*) FROM crews WHERE contractor_id=? AND driver_vehicle_block_id=?');$dup->execute([$contractorId,$dvbId]);
            if((int)$dup->fetchColumn()>0)throw new RuntimeException('Такой исполнитель рейса уже существует для выбранного подрядчика, первого водителя и ТС.');
            $i=$localPdo->prepare("INSERT INTO crews(contractor_id,vehicle_id,driver_id,driver_vehicle_block_id,status,comments,created_by_user_id,created_by_role) VALUES(?,?,?,?, 'active',?,?,?)");
            $i->execute([$contractorId,$vehicleUnitId,$primaryDriverId,$dvbId,$comments!==''?$comments:null,$userId,$userRole]);
            $crewId=(int)$localPdo->lastInsertId();
            syncCrewDrivers($localPdo,$crewId,$driverIds);
            $localPdo->commit();

            $s=$localPdo->prepare('SELECT name FROM contractors WHERE id=?');$s->execute([$contractorId]);$contractorName=(string)$s->fetchColumn();
            $placeholders=implode(',',array_fill(0,count($driverIds),'?'));$s=$localPdo->prepare("SELECT id,full_name FROM drivers WHERE id IN ($placeholders)");$s->execute($driverIds);$nameMap=[];foreach($s->fetchAll(PDO::FETCH_ASSOC) as $row)$nameMap[(int)$row['id']]=$row['full_name'];$names=[];foreach($driverIds as $id)$names[]=$nameMap[$id]??('#'.$id);
            $s=$localPdo->prepare("SELECT vu1.plate_number FROM vehicle_sets vs LEFT JOIN vehicle_units vu1 ON vs.primary_vehicle_unit_id=vu1.id WHERE vs.id=?");$s->execute([$vehicleSetId]);
            $createdExecutor=['id'=>$crewId,'contractor_name'=>$contractorName,'driver_name'=>implode(' + ',$names),'plate_number'=>(string)($s->fetchColumn()?:'—')];
            $success=true;
        }catch(Throwable $e){if($localPdo->inTransaction())$localPdo->rollBack();throw $e;}
    }
} catch (Throwable $e) {
    $formError = 'Не удалось создать исполнителя рейса: ' . $e->getMessage();
}

$render();
