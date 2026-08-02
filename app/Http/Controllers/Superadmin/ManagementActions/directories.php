<?php
requireRole('superadmin');$pageTitle='Каталоги компании';$pageContext='Superadmin › Компания';
$companyId=(int)$id;$pdo=$db->connection();$company=\App\Service\SuperadminCompanyService::loadCompany($pdo,$companyId);
if(!$company){http_response_code(404);echo'Company not found';return;}
//$topbarCrumbs=[['label'=>'Superadmin','url'=>'/superadmin/dashboard'],['label'=>'Компании','url'=>'/superadmin/companies'],['label'=>$company['name'],'url'=>'/superadmin/companies/'.$companyId],['label'=>'Каталоги','url'=>null]];
$cfg=companyDatabaseConfig($config,$company);$ldb=new \App\Core\Database($cfg);$lpdo=$ldb->connection();
$dirs=[];
foreach(['clients','contractors','drivers','vehicle_units','vehicle_sets','driver_vehicle_blocks','crews'] as $table){
    $stmt=$lpdo->query("SELECT COUNT(*) AS total, SUM(status='active') AS active, SUM(status='archived') AS archived FROM `{$table}`");
    $row=$stmt->fetch(\PDO::FETCH_ASSOC)?:[];
    $dirs[$table]=['total'=>(int)($row['total']??0),'active'=>(int)($row['active']??0),'archived'=>(int)($row['archived']??0)];
}
ob_start();require base_path('app/View/pages/superadmin_company_directories.php');$content=ob_get_clean();require base_path('app/View/layouts/main.php');
