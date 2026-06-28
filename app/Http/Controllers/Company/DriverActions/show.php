<?php
/** @var DriverService $service */
requireRole(['company_owner', 'senior_logist', 'logist']);
$pageTitle='Водитель';$pageContext='Водители › Компания';
$companyId=$service->getCompanyId();$archiveError=null;$grants=[];$logists=[];
if($companyId<=0){$company=null;$driver=null;$dbError=null;ob_start();require base_path('app/View/pages/company_driver_view.php');$content=ob_get_clean();require base_path('app/View/layouts/main.php');return;}
try{$company=$service->loadCompany($companyId);if(!$company){$company=null;$driver=null;$dbError=null;ob_start();require base_path('app/View/pages/company_driver_view.php');$content=ob_get_clean();require base_path('app/View/layouts/main.php');return;}
$pageContext='Водители › Компания: '.$company['name'];if($company['status']!=='active'){$driver=null;$dbError=null;ob_start();require base_path('app/View/pages/company_driver_view.php');$content=ob_get_clean();require base_path('app/View/layouts/main.php');return;}
$localPdo=$service->getLocalPdo($company);$driver=$service->getDriverById($localPdo,(int)$id);
$accessDenied=null;$createdByUser=null;$updatedByUser=null;
if($driver){$isLogist=($_SESSION['role_code']??'')==='logist';if($isLogist){$userId=(int)$_SESSION['user_id'];$grantLevel=$service->checkLogistAccess($localPdo,(int)$id,$userId);if((int)$driver['created_by_user_id']!==$userId&&!$grantLevel)$accessDenied='У вас нет доступа к этой записи.';}
$cu=$localPdo->prepare("SELECT full_name FROM users WHERE id=?");$cu->execute([(int)$driver['created_by_user_id']]);$createdByUser=$cu->fetchColumn()?:null;
if(!empty($driver['updated_by_user_id'])){$uu=$localPdo->prepare("SELECT full_name FROM users WHERE id=?");$uu->execute([(int)$driver['updated_by_user_id']]);$updatedByUser=$uu->fetchColumn()?:null;}}
if($driver&&!$accessDenied)$pageTitle='Водитель: '.$driver['full_name'];
$phones=[];if($driver&&!$accessDenied)$phones=$service->getDriverPhones($localPdo,(int)$id);
$driverBlocks=[];if($driver&&!$accessDenied)$driverBlocks=$service->getDriverBlocks($localPdo,(int)$id);
$grants=[];$logists=[];if(($_SESSION['role_code']??'')==='company_owner'){$grants=$service->getGrants($localPdo,(int)$id);$logists=$service->getLogists($localPdo);}
$dbError=null;}
catch(\Exception$e){$company=$company??null;$driver=null;$grants=[];$logists=[];$phones=[];$driverBlocks=[];$dbError='Не удалось загрузить водителя: '.$e->getMessage();}
ob_start();require base_path('app/View/pages/company_driver_view.php');$content=ob_get_clean();require base_path('app/View/layouts/main.php');
