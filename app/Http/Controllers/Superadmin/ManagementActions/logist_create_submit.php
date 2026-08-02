<?php
requireRole('superadmin');$cid=(int)$id;$pdo=$db->connection();$company=\App\Service\SuperadminCompanyService::loadCompany($pdo,$cid);
if(!$company){http_response_code(404);exit;}
use App\Service\UserSyncService;
$sync = new UserSyncService($config);
$fullName=trim($_POST['full_name']??'');$login=trim($_POST['login']??'');$password=trim($_POST['password']??'');
$email=trim($_POST['email']??'');$phone=trim($_POST['phone']??'');$role=trim($_POST['role']??'logist');
if($fullName===''||$login===''||$password===''){redirect_to('/superadmin/companies/'.$cid.'/users/logists/create?error=required_fields');}
if(strlen($password)<6){redirect_to('/superadmin/companies/'.$cid.'/users/logists/create?error=password_short');}
$allowedRoles=['logist','senior_logist'];
if(!in_array($role,$allowedRoles,true)){$role='logist';}
if($sync->loginExistsInCentral($pdo,$login)){redirect_to('/superadmin/companies/'.$cid.'/users/logists/create?error=login_taken');}
try{$localPdo=$sync->getLocalPdo($company);$sync->ensureUsersTable($localPdo);
if($sync->loginExistsInTenant($localPdo,$login)){redirect_to('/superadmin/companies/'.$cid.'/users/logists/create?error=login_taken');}
}catch(\Exception $e){redirect_to('/superadmin/companies/'.$cid.'/users/logists/create?error=db_error');}
$sync->createUser($pdo,$company,$fullName,$login,$password,$role,$email?:null,$phone?:null);
$_SESSION['_create_success']=['login'=>$login,'password'=>$password,'role'=>$role,'full_name'=>$fullName];
redirect_to('/superadmin/companies/'.$cid.'/users/logists/create');
