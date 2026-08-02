<?php
requireRole('superadmin');$pageTitle='Каталоги компании';$pageContext='Superadmin › Компания';
$companyId=(int)$id;$pdo=$db->connection();$company=\App\Service\SuperadminCompanyService::loadCompany($pdo,$companyId);
if(!$company){http_response_code(404);echo'Company not found';return;}
//$topbarCrumbs=[['label'=>'Superadmin','url'=>'/superadmin/dashboard'],['label'=>'Компании','url'=>'/superadmin/companies'],['label'=>$company['name'],'url'=>'/superadmin/companies/'.$companyId],['label'=>'Каталоги','url'=>null]];
$cfg=companyDatabaseConfig($config,$company);$ldb=new \App\Core\Database($cfg);$lpdo=$ldb->connection();
$stmtClients=$lpdo->query("SELECT COUNT(*) FROM clients");$clientsCount=$stmtClients->fetchColumn();
$stmtContractors=$lpdo->query("SELECT COUNT(*) FROM contractors");$contractorsCount=$stmtContractors->fetchColumn();
$stmtDrivers=$lpdo->query("SELECT COUNT(*) FROM drivers");$driversCount=$stmtDrivers->fetchColumn();
$stmtVehicleUnits=$lpdo->query("SELECT COUNT(*) FROM vehicle_units");$vehicleUnitsCount=$stmtVehicleUnits->fetchColumn();
$stmtVehicleSets=$lpdo->query("SELECT COUNT(*) FROM vehicle_sets");$vehicleSetsCount=$stmtVehicleSets->fetchColumn();
$stmtCrews=$lpdo->query("SELECT COUNT(*) FROM crews");$crewsCount=$stmtCrews->fetchColumn();
ob_start();require base_path('app/View/pages/superadmin_company_directories.php');$content=ob_get_clean();require base_path('app/View/layouts/main.php');
