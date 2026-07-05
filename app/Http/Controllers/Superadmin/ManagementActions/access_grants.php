<?php
requireRole('superadmin');$pageTitle='Доступы';$pageContext='Superadmin › Компания';
$companyId=(int)$id;$pdo=$db->connection();$company=\App\Service\SuperadminCompanyService::loadCompany($pdo,$companyId);
if(!$company){http_response_code(404);echo'Company not found';return;}
$topbarCrumbs=[['label'=>'Superadmin','url'=>'/superadmin/dashboard'],['label'=>'Компании','url'=>'/superadmin/companies'],['label'=>$company['name'],'url'=>'/superadmin/companies/'.$companyId],['label'=>'Доступы','url'=>null]];
$cfg=companyDatabaseConfig($config,$company);$ldb=new \App\Core\Database($cfg);$lpdo=$ldb->connection();applyLocalMigrations($lpdo);
$grants=$lpdo->query("SELECT g.*,u.full_name AS logist_name FROM entity_access_grants g LEFT JOIN users u ON g.granted_to_user_id=u.id ORDER BY g.id DESC LIMIT 200")->fetchAll(PDO::FETCH_ASSOC);
ob_start();require base_path('app/View/pages/superadmin_company_access_grants.php');$content=ob_get_clean();require base_path('app/View/layouts/main.php');
