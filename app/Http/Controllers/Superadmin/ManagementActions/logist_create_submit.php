<?php
requireRole('superadmin');$cid=(int)$id;$pdo=$db->connection();$company=\App\Service\SuperadminCompanyService::loadCompany($pdo,$cid);
if(!$company){http_response_code(404);exit;}
$fullName=trim($_POST['full_name']??'');$login=trim($_POST['login']??'');$password=trim($_POST['password']??'');
if($fullName===''||$login===''||$password===''){header('Location: /superadmin/companies/'.$cid.'/users/logists/create?error=required_fields');exit;}
if(strlen($password)<6){header('Location: /superadmin/companies/'.$cid.'/users/logists/create?error=password_short');exit;}
$hash=password_hash($password,PASSWORD_DEFAULT);
$ins=$pdo->prepare("INSERT INTO company_users (company_id,role,full_name,login,password_hash,status,created_at,updated_at) VALUES (?,?,?,?,?,'active',NOW(),NOW())");
$ins->execute([$cid,'logist',$fullName,$login,$hash]);
header('Location: /superadmin/companies/'.$cid.'/users');exit;
