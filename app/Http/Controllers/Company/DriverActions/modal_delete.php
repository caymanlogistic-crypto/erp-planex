<?php
/** @var DriverService $service */
requireRole(['company_owner', 'senior_logist', 'logist']);
header('Content-Type: application/json; charset=utf-8');
$companyId=$service->getCompanyId();
if($companyId<=0){echo json_encode(['success'=>false,'error'=>'Компания не найдена.']);exit;}
try{$company=$service->loadCompany($companyId);if(!$company||$company['status']!=='active'){echo json_encode(['success'=>false,'error'=>'Компания не найдена или не активна.']);exit;}
$localPdo=$service->getLocalPdo($company);$driver=$service->getDriverById($localPdo,(int)$id);
if(!$driver){echo json_encode(['success'=>false,'error'=>'Водитель не найден.']);exit;}
$role=$_SESSION['role_code']??'';if($role==='logist'){$userId=(int)$_SESSION['user_id'];if((int)$driver['created_by_user_id']!==$userId){echo json_encode(['success'=>false,'error'=>'У вас нет права архивировать эту запись.']);exit;}}
if($service->checkDriverHasCrews($localPdo,(int)$id)){echo json_encode(['success'=>false,'error'=>'Водитель участвует в экипажах. Сначала удалите экипажи.']);exit;}
$storageBase=storage_path('companies/'.$companyId.'/documents/');$driverDocDir=$storageBase.'driver/'.(int)$id;
$docsStmt=$localPdo->prepare("SELECT stored_name FROM documents WHERE entity_type='driver' AND entity_id=?");$docsStmt->execute([(int)$id]);$driverDocs=$docsStmt->fetchAll(PDO::FETCH_ASSOC);
$filePathsToDelete=[];$realBase=realpath($storageBase);foreach($driverDocs as $doc){$sn=trim((string)($doc['stored_name']??''));if($sn==='')continue;$fp=$driverDocDir.DIRECTORY_SEPARATOR.$sn;$rf=realpath($fp);if($realBase!==false&&$rf!==false&&str_starts_with($rf,$realBase))$filePathsToDelete[]=$rf;}
$localPdo->beginTransaction();
$localPdo->prepare("DELETE FROM entity_access_grants WHERE entity_type='driver' AND entity_id=?")->execute([(int)$id]);
$localPdo->prepare("DELETE FROM documents WHERE entity_type='driver' AND entity_id=?")->execute([(int)$id]);
$localPdo->prepare("DELETE FROM driver_phones WHERE driver_id=?")->execute([(int)$id]);
$localPdo->prepare("DELETE FROM drivers WHERE id=?")->execute([(int)$id]);
$localPdo->commit();
foreach(array_unique($filePathsToDelete) as $sp){if(is_string($sp)&&$sp!==''&&is_file($sp))@unlink($sp);}
if(is_dir($driverDocDir)){$items=@scandir($driverDocDir)?:[];foreach($items as $item){if($item==='.'||$item==='..')continue;$path=$driverDocDir.DIRECTORY_SEPARATOR.$item;if(is_file($path))@unlink($path);}@rmdir($driverDocDir);}
echo json_encode(['success'=>true]);exit;}
catch(\Exception $e){echo json_encode(['success'=>false,'error'=>'Ошибка: '.$e->getMessage()]);exit;}
