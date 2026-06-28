<?php
requireRole('superadmin');$companyId=(int)$companyId;$documentId=(int)$documentId;
$pdo=$db->connection();$company=SuperadminCompanyService::loadCompany($pdo,$companyId);
if(!$company){http_response_code(404);exit;}
$dbIdentifier=$company['db_identifier'];$cfg=$config['database'];$cfg['database']=$dbIdentifier;$ldb=new \App\Core\Database($cfg);$lpdo=$ldb->connection();applyLocalMigrations($lpdo);
$doc=$lpdo->prepare("SELECT * FROM documents WHERE id=? AND deleted_at IS NULL");$doc->execute([$documentId]);$doc=$doc->fetch(PDO::FETCH_ASSOC);
if(!$doc){http_response_code(404);exit;}
$filePath=storage_path($doc['relative_path']);if(!is_file($filePath)){http_response_code(404);exit;}
header('Content-Type: '.($doc['mime_type']?:'application/octet-stream'));header('Content-Disposition: attachment; filename="'.$doc['original_name'].'"');readfile($filePath);exit;
