<?php
requireRole('superadmin');$pageTitle='Логист';$pageContext='Superadmin › Компания';
$cid=(int)$companyId;$uid=(int)$userId;$pdo=$db->connection();$company=SuperadminCompanyService::loadCompany($pdo,$cid);
if(!$company){http_response_code(404);exit;}
$topbarCrumbs=[['label'=>'Superadmin','url'=>'/superadmin/dashboard'],['label'=>'Компании','url'=>'/superadmin/companies'],['label'=>$company['name'],'url'=>'/superadmin/companies/'.$cid],['label'=>'Логисты','url'=>'/superadmin/companies/'.$cid.'/users'],['label'=>'Просмотр','url'=>null]];
$userStmt=$pdo->prepare("SELECT * FROM company_users WHERE id=? AND company_id=? AND role='logist'");$userStmt->execute([$uid,$cid]);$logist=$userStmt->fetch(PDO::FETCH_ASSOC);
if(!$logist){http_response_code(404);exit;}
ob_start();require base_path('app/View/pages/superadmin_company_logist_view.php');$content=ob_get_clean();require base_path('app/View/layouts/main.php');
