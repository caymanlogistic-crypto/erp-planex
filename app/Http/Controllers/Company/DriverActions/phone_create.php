<?php
/** @var DriverService $service */
requireRole(['company_owner', 'senior_logist', 'logist']);
$driver_id=(int)$driverId;$companyId=$service->getCompanyId();$redirect='/company/drivers/'.$driver_id;
if($companyId<=0){header('Location: '.$redirect);exit;}
try{$company=$service->loadCompany($companyId);if(!$company||$company['status']!=='active'){header('Location: '.$redirect);exit;}
$localPdo=$service->getLocalPdo($company);$driver=$service->getDriverById($localPdo,$driver_id);if(!$driver){header('Location: /company/drivers');exit;}
$isLogist=($_SESSION['role_code']??'')==='logist';
if($isLogist){$userId=(int)$_SESSION['user_id'];if(!$service->checkLogistCanEdit($localPdo,$driver_id,$userId,(int)$driver['created_by_user_id'])){header('Location: '.$redirect);exit;}}
$phone=trim($_POST['phone']??'');if($phone===''){header('Location: '.$redirect);exit;}
$comment=trim($_POST['comment']??'');
$insert=$localPdo->prepare("INSERT INTO driver_phones (driver_id, phone, is_main, comment, created_by_user_id, created_by_role) VALUES (?, ?, 0, ?, ?, ?)");
$insert->execute([$driver_id,$phone,$comment?:null,(int)$_SESSION['user_id'],$_SESSION['role_code']??null]);}catch(\Exception$e){}
header('Location: '.$redirect);exit;
