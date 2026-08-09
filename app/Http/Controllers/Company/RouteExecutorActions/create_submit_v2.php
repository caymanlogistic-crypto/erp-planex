<?php
requireRole(['company_owner','senior_logist','logist']);
$pageTitle='Создать исполнителя рейса'; $pageContext='Исполнители рейса › Компания';
$companyId=(int)(getSessionCompanyId()??0); $errors=[]; $old=$_POST; $formError=null; $success=false; $createdExecutor=null;
$contractors=[]; $drivers=[]; $vehicleSets=[]; $blockingNotices=[];

try {
 $pdo=$db->connection(); $s=$pdo->prepare('SELECT * FROM companies WHERE id=?'); $s->execute([$companyId]); $company=$s->fetch(PDO::FETCH_ASSOC);
 if(!$company) throw new RuntimeException('Компания не найдена');
 if($company['status']!=='active') throw new RuntimeException('Создание исполнителя рейса недоступно');
 $localPdo=(new \App\Core\Database(companyDatabaseConfig($config,$company)))->connection();
 $isLogist=($_SESSION['role_code']??'')==='logist'; $userId=(int)($_SESSION['user_id']??0); $role=$_SESSION['role_code']??'';
 $contractorId=(int)($_POST['contractor_id']??0); $driverId=(int)($_POST['driver_id']??0); $secondaryDriverId=(int)($_POST['secondary_driver_id']??0); $vehicleSetId=(int)($_POST['vehicle_set_id']??0); $comments=trim($_POST['comments']??'');
 if(!$contractorId)$errors['contractor_id']='Выберите подрядчика'; if(!$driverId)$errors['driver_id']='Выберите водителя'; if($secondaryDriverId=== $driverId && $secondaryDriverId>0)$errors['secondary_driver_id']='Второй водитель должен отличаться от первого'; if(!$vehicleSetId)$errors['vehicle_set_id']='Выберите транспортный комплект';
 $hasAccess=function(string $type,int $id)use($localPdo,$isLogist,$userId):bool{ if(!$isLogist)return true; $map=['contractor'=>'contractors','driver'=>'drivers','vehicle_set'=>'vehicle_sets']; $t=$map[$type]; $s=$localPdo->prepare("SELECT created_by_user_id FROM {$t} WHERE id=? AND deleted_at IS NULL AND status='active'"); $s->execute([$id]); $owner=$s->fetchColumn(); if($owner===false)return false; if((int)$owner===$userId)return true; $g=$localPdo->prepare("SELECT COUNT(*) FROM entity_access_grants WHERE entity_type=? AND entity_id=? AND granted_to_user_id=? AND access_level IN ('view','edit') AND revoked_at IS NULL"); $g->execute([$type,$id,$userId]); return (int)$g->fetchColumn()>0; };
 if(!$errors){ if(!$hasAccess('contractor',$contractorId))$errors['contractor_id']='Подрядчик недоступен.'; if(!$hasAccess('driver',$driverId))$errors['driver_id']='Водитель недоступен.'; if($secondaryDriverId>0&&!$hasAccess('driver',$secondaryDriverId))$errors['secondary_driver_id']='Второй водитель недоступен.'; if(!$hasAccess('vehicle_set',$vehicleSetId))$errors['vehicle_set_id']='Транспортный комплект недоступен.'; }
 if(!$errors){
  $s=$localPdo->prepare('SELECT primary_vehicle_unit_id FROM vehicle_sets WHERE id=? AND deleted_at IS NULL AND status=\'active\''); $s->execute([$vehicleSetId]); $vehicleId=(int)$s->fetchColumn(); if(!$vehicleId){$errors['vehicle_set_id']='У выбранного транспортного комплекта нет основной транспортной единицы.';}
 }
 if(!$errors){
  $localPdo->beginTransaction();
  try{
   $dvb=$localPdo->prepare('SELECT id FROM driver_vehicle_blocks WHERE driver_id=? AND vehicle_set_id=? LIMIT 1'); $dvb->execute([$driverId,$vehicleSetId]); $dvbId=(int)$dvb->fetchColumn();
   if(!$dvbId){$i=$localPdo->prepare("INSERT INTO driver_vehicle_blocks(driver_id,vehicle_set_id,status,created_by_user_id,created_by_role) VALUES(?,?,\'active\',?,?)");$i->execute([$driverId,$vehicleSetId,$userId,$role]);$dvbId=(int)$localPdo->lastInsertId();}
   $dup=$localPdo->prepare('SELECT COUNT(*) FROM crews WHERE contractor_id=? AND driver_vehicle_block_id=?'); $dup->execute([$contractorId,$dvbId]); if((int)$dup->fetchColumn()>0)throw new RuntimeException('Такой исполнитель рейса уже существует в этой компании.');
   $i=$localPdo->prepare('INSERT INTO crews(contractor_id,vehicle_id,driver_id,secondary_driver_id,driver_vehicle_block_id,status,comments,created_by_user_id,created_by_role) VALUES(?,?,?,?,?,\'active\',?,?,?)'); $i->execute([$contractorId,$vehicleId,$driverId,$secondaryDriverId?:null,$dvbId,$comments?:null,$userId,$role]); $newId=(int)$localPdo->lastInsertId(); $localPdo->commit();
   $createdExecutor=['id'=>$newId]; $success=true;
  }catch(Throwable $e){if($localPdo->inTransaction())$localPdo->rollBack();throw $e;}
 }
} catch(Throwable $e){$formError=$e->getMessage();}

if(!$success){
 try{$localPdo=$localPdo??null;if($localPdo){$contractors=$localPdo->query("SELECT id,name,inn FROM contractors WHERE deleted_at IS NULL AND status='active' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);$drivers=$localPdo->query("SELECT id,full_name,phone FROM drivers WHERE deleted_at IS NULL AND status='active' ORDER BY full_name")->fetchAll(PDO::FETCH_ASSOC);$vehicleSets=$localPdo->query("SELECT vs.id,vs.set_type,vu1.plate_number primary_plate,vu2.plate_number secondary_plate FROM vehicle_sets vs LEFT JOIN vehicle_units vu1 ON vs.primary_vehicle_unit_id=vu1.id LEFT JOIN vehicle_units vu2 ON vs.secondary_vehicle_unit_id=vu2.id WHERE vs.deleted_at IS NULL AND vs.status='active' ORDER BY vs.set_type")->fetchAll(PDO::FETCH_ASSOC);}}catch(Throwable $e){}
}
if(($_POST['_is_modal']??'')==='1'){header('Content-Type:text/html; charset=utf-8');if($success){echo '<div data-route-executor-create-success="1"></div>';exit;} $routeExecutorCreateFormMode='modal';$routeExecutorFormId='route-executor-create-form';$routeExecutorFormAction=app_url('/company/route-executors/create');require base_path('app/View/partials/company_route_executor_create_form_v2.php');exit;}
ob_start();require base_path('app/View/pages/company_route_executors_create.php');$content=ob_get_clean();require base_path('app/View/layouts/main.php');
