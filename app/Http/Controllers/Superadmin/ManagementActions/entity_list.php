<?php
requireRole('superadmin');
$companyId=(int)$id;
// Whitelist map: entity_type => [table, view_page, label, label_plural]
$entityMap=[
    'client'=>['table'=>'clients','view'=>'superadmin_company_clients.php','label'=>'Клиент','plural'=>'Клиенты'],
    'contractor'=>['table'=>'contractors','view'=>'superadmin_company_contractors.php','label'=>'Перевозчик','plural'=>'Перевозчики'],
    'driver'=>['table'=>'drivers','view'=>'superadmin_company_drivers.php','label'=>'Водитель','plural'=>'Водители'],
    'vehicle_unit'=>['table'=>'vehicle_units','view'=>'superadmin_company_vehicles.php','label'=>'ТС','plural'=>'Транспорт'],
    'crew'=>['table'=>'crews','view'=>'superadmin_company_crews.php','label'=>'Экипаж','plural'=>'Экипажи'],
];
if(!isset($entityMap[$entityType])){http_response_code(400);echo'Invalid entity type';return;}
$meta=$entityMap[$entityType];
$pageTitle=$meta['plural'];$pageContext='Superadmin › Компания';
$pdo=$db->connection();$company=\App\Service\SuperadminCompanyService::loadCompany($pdo,$companyId);
if(!$company){http_response_code(404);echo'Company not found';return;}
$topbarCrumbs=[['label'=>'Superadmin','url'=>'/superadmin/dashboard'],['label'=>'Компании','url'=>'/superadmin/companies'],['label'=>$company['name'],'url'=>'/superadmin/companies/'.$companyId],['label'=>$pageTitle,'url'=>null]];
$dbIdentifier=$company['db_identifier'];$cfg=$config['database'];$cfg['database']=$dbIdentifier;$ldb=new \App\Core\Database($cfg);$lpdo=$ldb->connection();applyLocalMigrations($lpdo);
$tableName=$meta['table'];$items=[];$dbError=null;
try{$stmt=$lpdo->query("SELECT * FROM `$tableName` ORDER BY id DESC LIMIT 100");$items=$stmt->fetchAll(PDO::FETCH_ASSOC);}catch(\Exception$e){$dbError='Ошибка загрузки: '.$e->getMessage();}
ob_start();require base_path('app/View/pages/'.$meta['view']);$content=ob_get_clean();require base_path('app/View/layouts/main.php');
