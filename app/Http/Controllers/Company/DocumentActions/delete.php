<?php
requireRole(['company_owner', 'senior_logist', 'logist']);
$companyId=(int)(getSessionCompanyId()??0);$docId=(int)($_POST['id']??0);$entityType=$_POST['entity_type']??'';$entityId=(int)($_POST['entity_id']??0);
if($companyId<=0||$docId<=0){header('Location: /company/documents');exit;}
try{$pdo=$db->connection();$stmt=$pdo->prepare('SELECT * FROM companies WHERE id=?');$stmt->execute([$companyId]);$company=$stmt->fetch(PDO::FETCH_ASSOC);if(!$company||$company['status']!=='active'){header('Location: /company/documents');exit;}
$cfg=$config['database'];$cfg['database']=$company['db_identifier'];$ldb=new \App\Core\Database($cfg);$lpdo=$ldb->connection();applyLocalMigrations($lpdo);
$lpdo->prepare("UPDATE documents SET deleted_at=NOW(),deleted_by_user_id=? WHERE id=?")->execute([(int)$_SESSION['user_id'],$docId]);
header('Location: /company/documents?entity_type='.urlencode($entityType).'&entity_id='.$entityId);exit;}
catch(\Exception$e){header('Location: /company/documents?entity_type='.urlencode($entityType).'&entity_id='.$entityId);exit;}
