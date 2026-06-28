<?php
/** @var DriverService $service */
requireRole(['company_owner', 'senior_logist', 'logist']);
$driver_id=(int)$driverId;$phone_id=(int)$phoneId;$companyId=$service->getCompanyId();$redirect='/company/drivers/'.$driver_id;
if($companyId<=0){header('Location: '.$redirect);exit;}
try{$company=$service->loadCompany($companyId);if(!$company||$company['status']!=='active'){header('Location: '.$redirect);exit;}
$localPdo=$service->getLocalPdo($company);$driver=$service->getDriverById($localPdo,$driver_id);if(!$driver){header('Location: /company/drivers');exit;}
$isLogist=($_SESSION['role_code']??'')==='logist';
if($isLogist){$userId=(int)$_SESSION['user_id'];if(!$service->checkLogistCanEdit($localPdo,$driver_id,$userId,(int)$driver['created_by_user_id'])){header('Location: '.$redirect);exit;}}
$phone=trim($_POST['phone']??'');$comment=trim($_POST['comment']??'');
$localPdo->prepare("UPDATE driver_phones SET phone=?, comment=? WHERE id=? AND driver_id=?")->execute([$phone,$comment?:null,$phone_id,$driver_id]);}catch(\Exception$e){}
header('Location: '.$redirect);exit;
