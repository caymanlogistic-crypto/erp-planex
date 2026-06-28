<?php
/** @var VehicleSetService $service */
requireRole(['company_owner', 'senior_logist', 'logist']);
$companyId=$service->getCompanyId();if($companyId<=0){http_response_code(404);echo'<div class="notice warn">Компания не найдена.</div>';exit;}
try{$company=$service->loadCompany($companyId);if(!$company||($company['status']??'')!=='active'){http_response_code(404);echo'<div class="notice warn">Компания не найдена или неактивна.</div>';exit;}
$localPdo=$service->getLocalPdo($company);$vs=$service->getVehicleSetById($localPdo,(int)$id);if(!$vs){http_response_code(404);echo'<div class="notice warn">Транспорт не найден.</div>';exit;}
$roleCode=$_SESSION['role_code']??'';$canEdit=false;if($roleCode==='company_owner'||$roleCode==='senior_logist'){$canEdit=true;}elseif($roleCode==='logist'){$userId=(int)($_SESSION['user_id']??0);if((int)($vs['created_by_user_id']??0)===$userId)$canEdit=true;else{$gc=$localPdo->prepare("SELECT access_level FROM entity_access_grants WHERE entity_type='vehicle_set' AND entity_id=? AND granted_to_user_id=? AND revoked_at IS NULL LIMIT 1");$gc->execute([(int)$id,$userId]);$gr=$gc->fetch(PDO::FETCH_ASSOC);$canEdit=($gr['access_level']??null)==='edit';}}
if(!$canEdit){http_response_code(403);echo'<div class="notice warn">У вас нет права редактировать эту запись.</div>';exit;}
// Simple update - update status and comments on vehicle_sets, update unit fields from POST
$status=trim($_POST['status']??$vs['status']??'active');$comments=trim($_POST['comments']??'');
$localPdo->prepare("UPDATE vehicle_sets SET status=?, comments=?, updated_by_user_id=?, updated_by_role=? WHERE id=?")->execute([$status,$comments?:null,(int)$_SESSION['user_id'],$_SESSION['role_code']??null,(int)$id]);
// Reload for view
$vs=$service->getVehicleSetById($localPdo,(int)$id);$unitIds=array_values(array_filter([(int)($vs['primary_vehicle_unit_id']??0),(int)($vs['secondary_vehicle_unit_id']??0)]));
$unitsByRole=['primary'=>[],'secondary'=>[]];if(!empty($unitIds)){$units=$service->getUnitsByIds($localPdo,$unitIds);foreach($units as $unit){$uId=(int)($unit['id']??0);if($uId===(int)($vs['primary_vehicle_unit_id']??0))$unitsByRole['primary']=$unit;elseif($uId===(int)($vs['secondary_vehicle_unit_id']??0))$unitsByRole['secondary']=$unit;}}
$docsByRole=['primary'=>[],'secondary'=>[]];if(!empty($unitIds)){$docs=$service->getDocsForUnits($localPdo,$unitIds);foreach($docs as $doc){$eId=(int)($doc['entity_id']??0);if($eId===(int)($vs['primary_vehicle_unit_id']??0))$docsByRole['primary'][]=$doc;elseif($eId===(int)($vs['secondary_vehicle_unit_id']??0))$docsByRole['secondary'][]=$doc;}}
$rules=vehicleSetTypeRules();$unitTitles=['primary'=>$rules[$vs['set_type']??'']['units']['primary']['label']??'Основная единица','secondary'=>$rules[$vs['set_type']??'']['units']['secondary']['label']??'Доп. единица'];
$canEdit=$roleCode==='company_owner'||$roleCode==='senior_logist'||($roleCode==='logist'&&(int)($vs['created_by_user_id']??0)===(int)($_SESSION['user_id']??0));$canDelete=$canEdit;
$grantAccessLevel=null;if($roleCode==='logist'){$gc=$localPdo->prepare("SELECT access_level FROM entity_access_grants WHERE entity_type='vehicle_set' AND entity_id=? AND granted_to_user_id=? AND revoked_at IS NULL LIMIT 1");$gc->execute([(int)$id,(int)($_SESSION['user_id']??0)]);$gr=$gc->fetch(PDO::FETCH_ASSOC);$grantAccessLevel=$gr['access_level']??null;}
header('Content-Type: text/html; charset=utf-8');require base_path('app/View/partials/company_vehicle_set_modal_view.php');exit;}
catch(\Throwable $e){http_response_code(500);echo'<div class="notice warn">Ошибка: '.e($e->getMessage()).'</div>';exit;}
