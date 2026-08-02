<?php
/** @var DriverService $service */
requireRole(['company_owner', 'senior_logist', 'logist']);
$pageTitle = 'Создать водителя';
$pageContext = 'Водители › Компания';
$companyId = $service->getCompanyId();
if ($companyId <= 0) { $company=null; $success=false; $errors=[]; $old=[]; $formError=null; $createdDriver=null; ob_start(); require base_path('app/View/pages/company_drivers_create.php'); $content=ob_get_clean(); require base_path('app/View/layouts/main.php'); return; }
try {
    $company=$service->loadCompany($companyId);
    if(!$company){$company=null;$success=false;$errors=[];$old=[];$formError=null;$createdDriver=null;ob_start();require base_path('app/View/pages/company_drivers_create.php');$content=ob_get_clean();require base_path('app/View/layouts/main.php');return;}
    //$topbarCrumbs=[['label'=>mb_strtoupper($company['name']),'url'=>'/company/dashboard'],['label'=>'Подрядчики','url'=>null],['label'=>'Водители','url'=>'/company/drivers'],['label'=>'Создать водителя','url'=>null]];
    $success=false;$errors=[];$old=[];$formError=null;$createdDriver=null;$docTypes=[];
    if($company['status']==='active'){try{$localPdo=$service->getLocalPdo($company);$docTypes=$service->getDocTypes($localPdo);}catch(\Exception$e){$docTypes=[];}}
} catch(\Exception $e){$company=null;$success=false;$errors=[];$old=[];$formError='Ошибка загрузки данных: '.$e->getMessage();$createdDriver=null;}
ob_start();require base_path('app/View/pages/company_drivers_create.php');$content=ob_get_clean();require base_path('app/View/layouts/main.php');
