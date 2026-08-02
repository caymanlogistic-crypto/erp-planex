<?php
requireRole('superadmin');$cid=(int)$companyId;$uid=(int)$userId;$pdo=$db->connection();
use App\Service\UserSyncService;
$sync = new UserSyncService($config);
$company=\App\Service\SuperadminCompanyService::loadCompany($pdo,$cid);
if(!$company){http_response_code(404);exit;}
$newPassword=trim($_POST['password']??'');
if(strlen($newPassword)<6){$newPassword=bin2hex(random_bytes(8));}
try{$sync->resetPassword($pdo,$company,$uid,$newPassword);}
catch(\Exception $e){header('Location: /superadmin/companies/'.$cid.'/users/logists/'.$uid.'?error=reset_failed');exit;}
$_SESSION['_password_reset']=['password'=>$newPassword,'full_name'=>null,'login'=>null];
header('Location: /superadmin/companies/'.$cid.'/users/logists/'.$uid);exit;
