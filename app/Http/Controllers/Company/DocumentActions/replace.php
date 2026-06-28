<?php
requireRole(['company_owner', 'senior_logist', 'logist']);
$companyId=(int)(getSessionCompanyId()??0);$entityType=$_POST['entity_type']??'';$entityId=(int)($_POST['entity_id']??0);$replaceDocId=(int)($_POST['replace_doc_id']??0);
$allowedExt=['pdf','doc','docx','rtf','odt','xls','xlsx','csv','ods','jpg','jpeg','png','webp','gif','bmp','tif','tiff','heic','heif','txt'];$maxSize=20*1024*1024;
if($companyId<=0||$entityType===''||$entityId<=0||$replaceDocId<=0){header('Location: /company/documents?error=invalid_request');exit;}
try{$pdo=$db->connection();$stmt=$pdo->prepare('SELECT * FROM companies WHERE id=?');$stmt->execute([$companyId]);$company=$stmt->fetch(PDO::FETCH_ASSOC);if(!$company||$company['status']!=='active'){header('Location: /company/documents?entity_type='.urlencode($entityType).'&entity_id='.$entityId);exit;}
$cfg=$config['database'];$cfg['database']=$company['db_identifier'];$ldb=new \App\Core\Database($cfg);$lpdo=$ldb->connection();applyLocalMigrations($lpdo);
// Load existing document
$docStmt=$lpdo->prepare("SELECT * FROM documents WHERE id=? AND entity_type=? AND entity_id=? AND deleted_at IS NULL");$docStmt->execute([$replaceDocId,$entityType,$entityId]);$oldDoc=$docStmt->fetch(PDO::FETCH_ASSOC);
if(!$oldDoc){header('Location: /company/documents?entity_type='.urlencode($entityType).'&entity_id='.$entityId.'&error=doc_not_found');exit;}
// Soft-delete old document
$lpdo->prepare("UPDATE documents SET deleted_at=NOW(),deleted_by_user_id=?,delete_comment='Replaced' WHERE id=?")->execute([(int)$_SESSION['user_id'],$replaceDocId]);
// Upload new file if provided (name="doc_file")
if(!empty($_FILES['doc_file'])&&$_FILES['doc_file']['error']===UPLOAD_ERR_OK){
    $ext=strtolower(pathinfo($_FILES['doc_file']['name'],PATHINFO_EXTENSION));
    if(!in_array($ext,$allowedExt,true)){header('Location: /company/documents?entity_type='.urlencode($entityType).'&entity_id='.$entityId.'&error=invalid_extension');exit;}
    if($_FILES['doc_file']['size']>$maxSize){header('Location: /company/documents?entity_type='.urlencode($entityType).'&entity_id='.$entityId.'&error=file_too_large');exit;}
    $storedName=uniqid('doc_',true).'.'.$ext;$relativeDir='companies/'.$companyId.'/documents/'.$entityType.'/'.$entityId;$absoluteDir=storage_path($relativeDir);
    if(!is_dir($absoluteDir))mkdir($absoluteDir,0755,true);
    move_uploaded_file($_FILES['doc_file']['tmp_name'],$absoluteDir.DIRECTORY_SEPARATOR.$storedName);
    $mime=$_FILES['doc_file']['type']?:'application/octet-stream';
    $docType=trim($_POST['document_type']??$_POST['doc_type']??$oldDoc['document_type']??'');
    $dtId=null;if($docType!==''){$dts=$lpdo->prepare("SELECT id FROM document_types WHERE name=? AND (entity_type=? OR entity_type IS NULL) LIMIT 1");$dts->execute([$docType,$entityType]);$dtId=$dts->fetchColumn()?:null;}
    $ins=$lpdo->prepare('INSERT INTO documents(entity_type,entity_id,document_type,document_type_id,original_name,stored_name,relative_path,mime_type,file_size,status,uploaded_by_user_id,uploaded_by_role,created_by_user_id,created_by_role)VALUES(:et,:eid,:dtype,:dtid,:oname,:sname,:rpath,:mime,:fsize,:status,:uid,:role,:cuid,:crole)');
    $ins->execute([':et'=>$entityType,':eid'=>$entityId,':dtype'=>$docType?:null,':dtid'=>$dtId,':oname'=>$_FILES['doc_file']['name'],':sname'=>$storedName,':rpath'=>$relativeDir.'/'.$storedName,':mime'=>$mime,':fsize'=>$_FILES['doc_file']['size'],':status'=>'uploaded',':uid'=>(int)$_SESSION['user_id'],':role'=>$_SESSION['role_code'],':cuid'=>(int)$_SESSION['user_id'],':crole'=>$_SESSION['role_code']]);
}
header('Location: /company/documents?entity_type='.urlencode($entityType).'&entity_id='.$entityId);exit;}
catch(\Exception$e){header('Location: /company/documents?entity_type='.urlencode($entityType).'&entity_id='.$entityId);exit;}
