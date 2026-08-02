<?php
requireRole('superadmin');$pageTitle='Редактировать логиста';$pageContext='Superadmin › Компания';
$cid=(int)$companyId;$uid=(int)$userId;$pdo=$db->connection();$company=\App\Service\SuperadminCompanyService::loadCompany($pdo,$cid);
if(!$company){http_response_code(404);exit;}
//$topbarCrumbs=[['label'=>'Superadmin','url'=>'/superadmin/dashboard'],['label'=>'Компании','url'=>'/superadmin/companies'],['label'=>$company['name'],'url'=>'/superadmin/companies/'.$cid],['label'=>'Логисты','url'=>'/superadmin/companies/'.$cid.'/users'],['label'=>'Редактировать','url'=>null]];
$userStmt=$pdo->prepare("SELECT * FROM company_users WHERE id=? AND company_id=? AND role IN ('logist','senior_logist')");$userStmt->execute([$uid,$cid]);$logist=$userStmt->fetch(\PDO::FETCH_ASSOC);
if(!$logist){http_response_code(404);exit;}
$logistId=$uid;$old=$logist;$errors=[];$formError=null;
ob_start();require base_path('app/View/pages/superadmin_company_logist_edit.php');$content=ob_get_clean();require base_path('app/View/layouts/main.php');
