<?php
/** @var DriverService $service */
requireRole(['company_owner', 'senior_logist', 'logist']);
$driver_id=(int)$driverId;$companyId=$service->getCompanyId();$redirect='/company/drivers/'.$driver_id;
if($companyId<=0){header('Location: '.$redirect);exit;}
try{$company=$service->loadCompany($companyId);if(!$company||$company['status']!=='active'){header('Location: '.$redirect);exit;}
$localPdo=$service->getLocalPdo($company);$driver=$service->getDriverById($localPdo,$driver_id);if(!$driver){header('Location: /company/drivers');exit;}
$isLogist=($_SESSION['role_code']??'')==='logist';
if($isLogist){$userId=(int)$_SESSION['user_id'];if(!$service->checkLogistCanEdit($localPdo,$driver_id,$userId,(int)$driver['created_by_user_id'])){header('Location: '.$redirect);exit;}}
$allowedExts=['pdf','jpg','jpeg','png','webp','doc','docx','xls','xlsx'];$maxSize=20*1024*1024;
if(empty($_FILES['doc_file'])||$_FILES['doc_file']['error']!==UPLOAD_ERR_OK){header('Location: '.$redirect);exit;}
$ext=strtolower(pathinfo($_FILES['doc_file']['name'],PATHINFO_EXTENSION));if(!in_array($ext,$allowedExts,true)){header('Location: '.$redirect);exit;}
if($_FILES['doc_file']['size']>$maxSize){header('Location: '.$redirect);exit;}
$docType=trim($_POST['doc_type']??'Документ');
$storedName=uniqid('doc_',true).'.'.$ext;$relativeDir='companies/'.$companyId.'/documents/driver/'.$driver_id;$absoluteDir=storage_path($relativeDir);
if(!is_dir($absoluteDir))mkdir($absoluteDir,0755,true);
move_uploaded_file($_FILES['doc_file']['tmp_name'],$absoluteDir.DIRECTORY_SEPARATOR.$storedName);
$localPdo->prepare("INSERT INTO documents (entity_type, entity_id, document_type, original_name, stored_name, relative_path, mime_type, file_size, status, uploaded_by_user_id, uploaded_by_role) VALUES ('driver', ?, ?, ?, ?, ?, ?, ?, 'uploaded', ?, ?)")->execute([
    $driver_id,$docType,$_FILES['doc_file']['name'],$storedName,$relativeDir.'/'.$storedName,$_FILES['doc_file']['type']?:'application/octet-stream',$_FILES['doc_file']['size'],(int)$_SESSION['user_id'],$_SESSION['role_code']??null]);}catch(\Exception$e){}
header('Location: '.$redirect);exit;
