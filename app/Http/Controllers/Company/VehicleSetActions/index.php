<?php
/** @var VehicleSetService $service */
requireRole(['company_owner', 'senior_logist', 'logist']);
$pageTitle='Транспорт';$pageContext='Транспорт › Компания';
$companyId=$service->getCompanyId();
if($companyId<=0){$company=null;$vehicleSets=[];$dbError=null;ob_start();require base_path('app/View/pages/company_vehicle_sets.php');$content=ob_get_clean();require base_path('app/View/layouts/main.php');return;}
try{$company=$service->loadCompany($companyId);if(!$company){$company=null;$vehicleSets=[];$dbError=null;ob_start();require base_path('app/View/pages/company_vehicle_sets.php');$content=ob_get_clean();require base_path('app/View/layouts/main.php');return;}
$pageContext='Транспорт › Компания: '.$company['name'];
//$topbarCrumbs=[['label'=>mb_strtoupper($company['name']),'url'=>'/company/dashboard'],['label'=>'Подрядчики','url'=>null],['label'=>'Список транспорта','url'=>null]];
if($company['status']!=='active'){$vehicleSets=[];$dbError=null;ob_start();require base_path('app/View/pages/company_vehicle_sets.php');$content=ob_get_clean();require base_path('app/View/layouts/main.php');return;}
$localPdo=$service->getLocalPdo($company);
$isLogist=($_SESSION['role_code']??'')==='logist';
$vehicleSets=$service->listVehicleSets($localPdo,$isLogist,(int)$_SESSION['user_id']);
$dbError=null;}catch(\Exception $e){$company=$company??null;$vehicleSets=[];$dbError='Не удалось подключиться к базе данных компании.';}
ob_start();require base_path('app/View/pages/company_vehicle_sets.php');$content=ob_get_clean();require base_path('app/View/layouts/main.php');
