<?php
requireRole('superadmin');$cid=(int)$companyId;$uid=(int)$userId;$pdo=$db->connection();
$newPassword=trim($_POST['password']??'');
if(strlen($newPassword)<6){header('Location: /superadmin/companies/'.$cid.'/users/logists/'.$uid.'/view?error=password_short');exit;}
$hash=password_hash($newPassword,PASSWORD_DEFAULT);
$pdo->prepare("UPDATE company_users SET password_hash=?, updated_at=NOW() WHERE id=? AND company_id=?")->execute([$hash,$uid,$cid]);
header('Location: /superadmin/companies/'.$cid.'/users');exit;
