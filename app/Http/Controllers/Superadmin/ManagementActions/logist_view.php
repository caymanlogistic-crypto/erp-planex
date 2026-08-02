<?php
requireRole('superadmin');$pageTitle='Логист';$pageContext='Superadmin › Компания';
$cid=(int)$companyId;$uid=(int)$userId;$pdo=$db->connection();$company=\App\Service\SuperadminCompanyService::loadCompany($pdo,$cid);
if(!$company){http_response_code(404);exit;}
//$topbarCrumbs=[['label'=>'Superadmin','url'=>'/superadmin/dashboard'],['label'=>'Компании','url'=>'/superadmin/companies'],['label'=>$company['name'],'url'=>'/superadmin/companies/'.$cid],['label'=>'Логисты','url'=>'/superadmin/companies/'.$cid.'/users'],['label'=>'Просмотр','url'=>null]];
use App\Service\UserSyncService;
$sync = new UserSyncService($config);
$userStmt=$pdo->prepare("SELECT * FROM company_users WHERE id=? AND company_id=? AND role IN ('logist','senior_logist')");$userStmt->execute([$uid,$cid]);$logist=$userStmt->fetch(\PDO::FETCH_ASSOC);
if(!$logist){http_response_code(404);exit;}
$logistId=$uid;
$passwordReset=false;$newPassword=null;
if(isset($_SESSION['_password_reset'])){$pr=$_SESSION['_password_reset'];$newPassword=$pr['password'];$passwordReset=true;unset($_SESSION['_password_reset']);}
$counts=[];$grantsCount=null;$countsIncomplete=false;
try{$localPdo=$sync->getLocalPdo($company);$sync->ensureUsersTable($localPdo);
$tables=['clients'=>'clients','contractors'=>'contractors','drivers'=>'drivers','vehicles'=>'vehicle_units','crews'=>'crews','documents'=>'documents'];
foreach($tables as $key=>$table){try{$s=$localPdo->query("SELECT COUNT(*) FROM {$table} WHERE created_by_user_id=".(int)$uid);$counts[$key]=(int)$s->fetchColumn();}catch(\Exception $e){$counts[$key]=null;$countsIncomplete=true;}}
try{$g=$localPdo->prepare("SELECT COUNT(*) FROM entity_access_grants WHERE created_by_user_id=? AND revoked_at IS NULL");$g->execute([$uid]);$grantsCount=(int)$g->fetchColumn();}catch(\Exception $e){$grantsCount=null;}
}catch(\Exception $e){$countsIncomplete=true;}
ob_start();require base_path('app/View/pages/superadmin_company_logist_view.php');$content=ob_get_clean();require base_path('app/View/layouts/main.php');
