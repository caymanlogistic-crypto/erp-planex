<?php
requireRole(['company_owner', 'senior_logist', 'logist']);
$companyId=(int)(getSessionCompanyId()??0);$docId=(int)($_GET['id']??0);
if($companyId<=0||$docId<=0){http_response_code(404);exit;}
try{$pdo=$db->connection();$stmt=$pdo->prepare('SELECT * FROM companies WHERE id=?');$stmt->execute([$companyId]);$company=$stmt->fetch(PDO::FETCH_ASSOC);if(!$company||$company['status']!=='active'){http_response_code(404);exit;}
$cfg=$config['database'];$cfg['database']=$company['db_identifier'];$ldb=new \App\Core\Database($cfg);$lpdo=$ldb->connection();applyLocalMigrations($lpdo);
$docStmt=$lpdo->prepare("SELECT * FROM documents WHERE id=? AND deleted_at IS NULL");$docStmt->execute([$docId]);$doc=$docStmt->fetch(PDO::FETCH_ASSOC);
if(!$doc){http_response_code(404);exit;}
$relPath=$doc['relative_path'];if($relPath===null||$relPath===''||strpos($relPath,'..')!==false){http_response_code(404);exit;}
$filePath=storage_path($relPath);if(!is_file($filePath)){http_response_code(404);exit;}
$mime=$doc['mime_type']?:'application/octet-stream';
$fs=$doc['file_size'];if(strpos($mime,'image/')===0||strpos($mime,'pdf')!==false||strpos($mime,'text/')===0){header('Content-Type: '.$mime);header('Content-Disposition: inline; filename="'.$doc['original_name'].'"');if($fs>0)header('Content-Length: '.$fs);readfile($filePath);exit;}
header('Content-Type: '.$mime);header('Content-Disposition: attachment; filename="'.$doc['original_name'].'"');if($fs>0)header('Content-Length: '.$fs);readfile($filePath);exit;}
catch(\Exception$e){http_response_code(500);exit;}
