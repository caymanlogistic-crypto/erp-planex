<?php
/** @var DriverService $service */
requireRole(['company_owner', 'senior_logist', 'logist']);
$driver_id=(int)$driverId;$doc_id=(int)$docId;$companyId=$service->getCompanyId();$redirect='/company/drivers/'.$driver_id;
if($companyId<=0){header('Location: '.$redirect);exit;}
try{$company=$service->loadCompany($companyId);if(!$company||$company['status']!=='active'){header('Location: '.$redirect);exit;}
$localPdo=$service->getLocalPdo($company);$driver=$service->getDriverById($localPdo,$driver_id);if(!$driver){header('Location: /company/drivers');exit;}
$isLogist=($_SESSION['role_code']??'')==='logist';
if($isLogist){$userId=(int)$_SESSION['user_id'];if(!$service->checkLogistCanEdit($localPdo,$driver_id,$userId,(int)$driver['created_by_user_id'])){header('Location: '.$redirect);exit;}}
$localPdo->prepare("UPDATE documents SET deleted_at=NOW(), deleted_by_user_id=? WHERE id=? AND entity_type='driver' AND entity_id=?")->execute([(int)$_SESSION['user_id'],$doc_id,$driver_id]);}catch(\Exception$e){}
header('Location: '.$redirect);exit;
