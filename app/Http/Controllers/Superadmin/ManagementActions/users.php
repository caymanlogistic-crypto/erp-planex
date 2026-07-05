<?php
requireRole('superadmin');$pageTitle='Пользователи компании';$pageContext='Superadmin › Компания';
$companyId=(int)$id;$pdo=$db->connection();$company=\App\Service\SuperadminCompanyService::loadCompany($pdo,$companyId);
if(!$company){http_response_code(404);echo'Company not found';return;}
$topbarCrumbs=[['label'=>'Superadmin','url'=>'/superadmin/dashboard'],['label'=>'Компании','url'=>'/superadmin/companies'],['label'=>$company['name'],'url'=>'/superadmin/companies/'.$companyId],['label'=>'Пользователи','url'=>null]];
$usersStmt=$pdo->prepare("SELECT * FROM company_users WHERE company_id=? ORDER BY role,full_name");$usersStmt->execute([$companyId]);$users=$usersStmt->fetchAll(PDO::FETCH_ASSOC);
$owner=null;$logists=[];$admins=[];
foreach($users as $u){if($u['role']==='company_owner')$owner=$u;elseif($u['role']==='logist')$logists[]=$u;}
ob_start();require base_path('app/View/pages/superadmin_company_users.php');$content=ob_get_clean();require base_path('app/View/layouts/main.php');
