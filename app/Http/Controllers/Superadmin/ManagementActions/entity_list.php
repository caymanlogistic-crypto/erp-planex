<?php
requireRole('superadmin');
$companyId=(int)$id;$entityType=$_GET['entity_type']??'';
$labelMap=['client'=>'Клиенты','contractor'=>'Перевозчики','driver'=>'Водители','vehicle_unit'=>'ТС','crew'=>'Экипажи'];
$pageTitle=$labelMap[$entityType]??'Список';$pageContext='Superadmin › Компания';
$pdo=$db->connection();$company=SuperadminCompanyService::loadCompany($pdo,$companyId);
if(!$company){http_response_code(404);echo'Company not found';return;}
$topbarCrumbs=[['label'=>'Superadmin','url'=>'/superadmin/dashboard'],['label'=>'Компании','url'=>'/superadmin/companies'],['label'=>$company['name'],'url'=>'/superadmin/companies/'.$companyId],['label'=>$pageTitle,'url'=>null]];
$dbIdentifier=$company['db_identifier'];$cfg=$config['database'];$cfg['database']=$dbIdentifier;$ldb=new \App\Core\Database($cfg);$lpdo=$ldb->connection();applyLocalMigrations($lpdo);
$viewPage='superadmin_company_'.$entityType.'s.php';if($entityType==='vehicle_unit')$viewPage='superadmin_company_vehicles.php';
$items=[];$dbError=null;
try{$stmt=$lpdo->query("SELECT * FROM {$entityType}s ORDER BY id DESC LIMIT 100");$items=$stmt->fetchAll(PDO::FETCH_ASSOC);}catch(\Exception$e){$dbError='Ошибка загрузки: '.$e->getMessage();}
ob_start();require base_path('app/View/pages/'.$viewPage);$content=ob_get_clean();require base_path('app/View/layouts/main.php');
