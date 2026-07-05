<?php
requireRole('superadmin');$pageTitle='Документы компании';$pageContext='Superadmin › Компания';
$companyId=(int)$id;$pdo=$db->connection();$company=\App\Service\SuperadminCompanyService::loadCompany($pdo,$companyId);
if(!$company){http_response_code(404);echo'Company not found';return;}
$topbarCrumbs=[['label'=>'Superadmin','url'=>'/superadmin/dashboard'],['label'=>'Компании','url'=>'/superadmin/companies'],['label'=>$company['name'],'url'=>'/superadmin/companies/'.$companyId],['label'=>'Документы','url'=>null]];
$cfg=companyDatabaseConfig($config,$company);$ldb=new \App\Core\Database($cfg);$lpdo=$ldb->connection();applyLocalMigrations($lpdo);
$documents=$lpdo->query("SELECT d.*,dt.name AS type_name FROM documents d LEFT JOIN document_types dt ON d.document_type_id=dt.id WHERE d.deleted_at IS NULL ORDER BY d.id DESC LIMIT 200")->fetchAll(PDO::FETCH_ASSOC);
ob_start();require base_path('app/View/pages/superadmin_company_documents.php');$content=ob_get_clean();require base_path('app/View/layouts/main.php');
