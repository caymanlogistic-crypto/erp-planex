<?php
requireRole('superadmin');$cid=(int)$companyId;$uid=(int)$userId;$pdo=$db->connection();
use App\Service\UserSyncService;
$sync = new UserSyncService($config);
$company=\App\Service\SuperadminCompanyService::loadCompany($pdo,$cid);
if(!$company){http_response_code(404);exit;}
$fullName=trim($_POST['full_name']??'');$login=trim($_POST['login']??'');$email=trim($_POST['email']??'');
$phone=trim($_POST['phone']??'');$role=trim($_POST['role_code']??null);
if($fullName===''){header('Location: /superadmin/companies/'.$cid.'/users/logists/'.$uid.'/edit?error=name_required');exit;}
$user=$sync->getCentralUser($pdo,$cid,$uid);
if(!$user){http_response_code(404);exit;}
if($login!==$user['login']){
if($sync->loginExistsInCentral($pdo,$login,$uid)){header('Location: /superadmin/companies/'.$cid.'/users/logists/'.$uid.'/edit?error=login_taken');exit;}
try{$localPdo=$sync->getLocalPdo($company);$sync->ensureUsersTable($localPdo);
if($sync->loginExistsInTenant($localPdo,$login)){header('Location: /superadmin/companies/'.$cid.'/users/logists/'.$uid.'/edit?error=login_taken');exit;}
}catch(\Exception $e){}}
$sync->updateUser($pdo,$company,$uid,$fullName,$login,$email?:null,$phone?:null,$role);
header('Location: /superadmin/companies/'.$cid.'/users');exit;
