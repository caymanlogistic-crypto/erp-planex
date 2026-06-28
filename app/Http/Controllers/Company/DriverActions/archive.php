<?php
/** @var DriverService $service */
requireRole(['company_owner', 'senior_logist', 'logist']);
$pageTitle='Водитель';$pageContext='Водители › Компания';$companyId=$service->getCompanyId();$archiveError=null;
if($companyId<=0){$company=null;$driver=null;$dbError=null;ob_start();require base_path('app/View/pages/company_driver_view.php');$content=ob_get_clean();require base_path('app/View/layouts/main.php');return;}
try{$company=$service->loadCompany($companyId);if(!$company){$company=null;$driver=null;$dbError=null;ob_start();require base_path('app/View/pages/company_driver_view.php');$content=ob_get_clean();require base_path('app/View/layouts/main.php');return;}
$pageContext='Водители › Компания: '.$company['name'];if($company['status']!=='active'){$driver=null;$dbError=null;ob_start();require base_path('app/View/pages/company_driver_view.php');$content=ob_get_clean();require base_path('app/View/layouts/main.php');return;}
$localPdo=$service->getLocalPdo($company);$driver=$service->getDriverById($localPdo,(int)$id);
if(!$driver){$driver=null;$dbError=null;ob_start();require base_path('app/View/pages/company_driver_view.php');$content=ob_get_clean();require base_path('app/View/layouts/main.php');return;}
$isLogist=($_SESSION['role_code']??'')==='logist';
if($isLogist){$userId=(int)$_SESSION['user_id'];if((int)$driver['created_by_user_id']!==$userId){$archiveError='Логист может архивировать только записи, созданные им самим.';$dbError=null;ob_start();require base_path('app/View/pages/company_driver_view.php');$content=ob_get_clean();require base_path('app/View/layouts/main.php');return;}}
$pageTitle='Водитель: '.$driver['full_name'];
if($service->checkDriverHasCrews($localPdo,(int)$id)){$archiveError='Водитель участвует в экипажах. Сначала удалите экипажи.';$dbError=null;ob_start();require base_path('app/View/pages/company_driver_view.php');$content=ob_get_clean();require base_path('app/View/layouts/main.php');return;}
$service->archiveDriver($localPdo,(int)$id);header('Location: /company/drivers');exit;}
catch(\Exception $e){$company=$company??null;$driver=null;$dbError='Ошибка архивирования: '.$e->getMessage();ob_start();require base_path('app/View/pages/company_driver_view.php');$content=ob_get_clean();require base_path('app/View/layouts/main.php');}
