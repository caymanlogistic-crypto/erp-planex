<?php
/** @var DriverService $service */
requireRole(['company_owner', 'senior_logist', 'logist']);
$companyId=$service->getCompanyId();
if($companyId<=0){$company=null;$driver=null;$errors=[];$old=$_POST;$formError='Компания не найдена';ob_start();require base_path('app/View/pages/company_driver_edit.php');$content=ob_get_clean();require base_path('app/View/layouts/main.php');return;}
try{$company=$service->loadCompany($companyId);if(!$company){$company=null;$driver=null;$errors=[];$old=$_POST;$formError='Компания не найдена';ob_start();require base_path('app/View/pages/company_driver_edit.php');$content=ob_get_clean();require base_path('app/View/layouts/main.php');return;}
$pageContext='Водители › Компания: '.$company['name'];if($company['status']!=='active'){$driver=null;$errors=[];$old=$_POST;$formError='Редактирование водителей недоступно';ob_start();require base_path('app/View/pages/company_driver_edit.php');$content=ob_get_clean();require base_path('app/View/layouts/main.php');return;}
$localPdo=$service->getLocalPdo($company);$driver=$service->getDriverById($localPdo,(int)$id);
if(!$driver){$driver=null;$errors=[];$old=$_POST;$formError='Водитель не найден';ob_start();require base_path('app/View/pages/company_driver_edit.php');$content=ob_get_clean();require base_path('app/View/layouts/main.php');return;}
$isLogist=($_SESSION['role_code']??'')==='logist';
if($isLogist){$userId=(int)$_SESSION['user_id'];$hasGrantEdit=$service->checkLogistCanEdit($localPdo,(int)$id,$userId,(int)$driver['created_by_user_id']);
if(!$hasGrantEdit){$errors=[];$old=$driver;$formError='У вас нет доступа к этой записи.';ob_start();require base_path('app/View/pages/company_driver_edit.php');$content=ob_get_clean();require base_path('app/View/layouts/main.php');return;}}
$fullName=trim($_POST['full_name']??'');$phone=trim($_POST['phone']??'');
if($fullName==='')$errors['full_name']='Обязательное поле';
if(!empty($errors)){ob_start();require base_path('app/View/pages/company_driver_edit.php');$content=ob_get_clean();require base_path('app/View/layouts/main.php');return;}
$service->updateDriver($localPdo,(int)$id,$_POST,(int)$_SESSION['user_id'],$_SESSION['role_code']);
header('Location: /company/drivers/'.$id);exit;}
catch(\Exception $e){$company=$company??null;$driver=$driver??null;$errors=[];$old=$_POST;$formError='Ошибка сохранения: '.$e->getMessage();ob_start();require base_path('app/View/pages/company_driver_edit.php');$content=ob_get_clean();require base_path('app/View/layouts/main.php');}
