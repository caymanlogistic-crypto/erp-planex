<?php
/** @var VehicleSetService $service */
requireRole(['company_owner', 'senior_logist', 'logist']);
header('Content-Type: application/json; charset=utf-8');
try{$companyId=$service->getCompanyId();if($companyId<=0)throw new \RuntimeException('Компания не найдена.');
$company=$service->loadCompany($companyId);if(!$company||($company['status']??'')!=='active')throw new \RuntimeException('Компания недоступна.');
$localPdo=$service->getLocalPdo($company);$vs=$service->getVehicleSetById($localPdo,(int)$id);if(!$vs)throw new \RuntimeException('Транспорт не найден.');
$roleCode=$_SESSION['role_code']??'';$userId=(int)($_SESSION['user_id']??0);
if($roleCode==='logist'&&(int)($vs['created_by_user_id']??0)!==$userId)throw new \RuntimeException('Логист может архивировать только свои записи.');
$service->archiveVehicleSet($localPdo,(int)$id);echo json_encode(['success'=>true],JSON_UNESCAPED_UNICODE);}
catch(\Throwable $e){http_response_code(200);echo json_encode(['success'=>false,'error'=>$e->getMessage()],JSON_UNESCAPED_UNICODE);}
