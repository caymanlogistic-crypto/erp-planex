<?php
requireRole('superadmin');$cid=(int)$companyId;$uid=(int)$userId;$pdo=$db->connection();
$fullName=trim($_POST['full_name']??'');$login=trim($_POST['login']??'');$email=trim($_POST['email']??'');
if($fullName===''){header('Location: /superadmin/companies/'.$cid.'/users/logists/'.$uid.'/edit?error=name_required');exit;}
$upd=$pdo->prepare("UPDATE company_users SET full_name=:fn, login=:login, email=:email, updated_at=NOW() WHERE id=:id AND company_id=:cid AND role='logist'");
$upd->execute([':fn'=>$fullName,':login'=>$login,':email'=>$email?:null,':id'=>$uid,':cid'=>$cid]);
header('Location: /superadmin/companies/'.$cid.'/users');exit;
