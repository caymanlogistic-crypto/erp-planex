<?php
/** @var DriverService $service */
requireRole(['company_owner', 'senior_logist', 'logist']);
$pageTitle='Редактировать водителя';$pageContext='Водители › Компания';
$companyId=$service->getCompanyId();
if($companyId<=0){$company=null;$driver=null;$errors=[];$old=[];$formError=null;ob_start();require base_path('app/View/pages/company_driver_edit.php');$content=ob_get_clean();require base_path('app/View/layouts/main.php');return;}
try{$company=$service->loadCompany($companyId);if(!$company){$company=null;$driver=null;$errors=[];$old=[];$formError=null;ob_start();require base_path('app/View/pages/company_driver_edit.php');$content=ob_get_clean();require base_path('app/View/layouts/main.php');return;}
$pageContext='Водители › Компания: '.$company['name'];if($company['status']!=='active'){$driver=null;$errors=[];$old=[];$formError=null;ob_start();require base_path('app/View/pages/company_driver_edit.php');$content=ob_get_clean();require base_path('app/View/layouts/main.php');return;}
$localPdo=$service->getLocalPdo($company);$driver=$service->getDriverById($localPdo,(int)$id);
if(!$driver){$driver=null;$errors=[];$old=[];$formError=null;ob_start();require base_path('app/View/pages/company_driver_edit.php');$content=ob_get_clean();require base_path('app/View/layouts/main.php');return;}
$errors=[];$old=$driver;$formError=null;ob_start();require base_path('app/View/pages/company_driver_edit.php');$content=ob_get_clean();require base_path('app/View/layouts/main.php');}
catch(\Exception $e){$company=$company??null;$driver=null;$errors=[];$old=[];$formError='Не удалось загрузить водителя: '.$e->getMessage();ob_start();require base_path('app/View/pages/company_driver_edit.php');$content=ob_get_clean();require base_path('app/View/layouts/main.php');}
