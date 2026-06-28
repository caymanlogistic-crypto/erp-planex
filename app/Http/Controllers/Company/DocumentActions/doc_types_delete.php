<?php
requireRole(['company_owner', 'senior_logist', 'logist']);
$companyId=(int)(getSessionCompanyId()??0);$typeId=(int)$id;if($companyId<=0||$typeId<=0){header('Location: /company/document-types');exit;}
try{$pdo=$db->connection();$stmt=$pdo->prepare('SELECT * FROM companies WHERE id=?');$stmt->execute([$companyId]);$company=$stmt->fetch(PDO::FETCH_ASSOC);if(!$company||$company['status']!=='active'){header('Location: /company/document-types');exit;}
$cfg=$config['database'];$cfg['database']=$company['db_identifier'];$ldb=new \App\Core\Database($cfg);$lpdo=$ldb->connection();applyLocalMigrations($lpdo);
$lpdo->prepare("DELETE FROM document_types WHERE id=?")->execute([$typeId]);header('Location: /company/document-types');exit;}
catch(\Exception$e){header('Location: /company/document-types?error=delete_failed');exit;}
