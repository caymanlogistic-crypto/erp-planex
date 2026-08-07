<?php
$root = getenv('P20_ROOT') ?: '/home/s/spugovxsim/planexp/public_html/erpv2';
$ownerLogin = trim((string)getenv('P20_OWNER_LOGIN'));
if ($ownerLogin === '') { fwrite(STDERR, "OWNER login missing\n"); exit(2); }
chdir($root);
$config = require $root . '/bootstrap/app.php';
require_once $root . '/app/Core/Database.php';
require_once $root . '/app/Support/crypto_helper.php';
require_once $root . '/app/Support/company_database.php';
use App\Core\Database;
$central=(new Database($config['database']))->connection();
$stmt=$central->prepare("SELECT c.id,c.name,c.status,c.db_identifier,c.db_host,c.db_port,c.db_username,c.db_password FROM company_users cu JOIN companies c ON c.id=cu.company_id WHERE cu.login=:login AND cu.role='company_owner' AND c.status='active' LIMIT 1");
$stmt->execute([':login'=>$ownerLogin]);$ownerCompany=$stmt->fetch(PDO::FETCH_ASSOC);if(!$ownerCompany)throw new RuntimeException('OWNER company not found');
$ownerPdo=(new Database(companyDatabaseConfig($config,$ownerCompany)))->connection();
$ops=$ownerPdo->query("SELECT fo.id,fo.operation_type,fo.status,fo.source,fo.money_account_id,COALESCE(fma.type,'') account_type,(SELECT COUNT(*) FROM finance_audit_log fal WHERE (fal.entity_type='finance_operation' AND fal.entity_id=fo.id) OR (fal.entity_type='finance_allocation' AND fal.entity_id IN (SELECT a.id FROM finance_operation_allocations a WHERE a.operation_id=fo.id))) history_count FROM finance_operations fo LEFT JOIN finance_money_accounts fma ON fma.id=fo.money_account_id ORDER BY fo.id DESC")->fetchAll(PDO::FETCH_ASSOC);
$pick=function($rows,$pred){foreach($rows as $r){if($pred($r))return $r;}return null;};
$withHistory=$pick($ops,fn($r)=>(int)$r['history_count']>0);
$withoutHistory=$pick($ops,fn($r)=>(int)$r['history_count']===0);
$bank=$pick($ops,fn($r)=>$r['source']==='BANK_STATEMENT'||$r['account_type']==='BANK');
$cash=$pick($ops,fn($r)=>$r['source']==='CASH'||$r['account_type']==='CASH');
$maxId=$ops?max(array_map(fn($r)=>(int)$r['id'],$ops)):0;$nonexistent=$maxId+1000000;
$other=null;$companies=$central->query("SELECT id,name,status,db_identifier,db_host,db_port,db_username,db_password FROM companies WHERE status='active' AND id<>".(int)$ownerCompany['id']." ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
$ownerIds=array_fill_keys(array_map(fn($r)=>(int)$r['id'],$ops),true);
foreach($companies as $c){try{$pdo=(new Database(companyDatabaseConfig($config,$c)))->connection();$rows=$pdo->query('SELECT id,operation_type,status,source FROM finance_operations ORDER BY id DESC')->fetchAll(PDO::FETCH_ASSOC);foreach($rows as $r){$id=(int)$r['id'];if(!isset($ownerIds[$id])){$other=['company_id'=>(int)$c['id'],'operation'=>$r];break 2;}}}catch(Throwable $e){}}
$out=['owner_company_id'=>(int)$ownerCompany['id'],'operation_count'=>count($ops),'with_history'=>$withHistory,'without_history'=>$withoutHistory,'bank_operation'=>$bank,'cash_operation'=>$cash,'nonexistent_id'=>$nonexistent,'foreign_operation'=>$other,'sample'=>array_slice($ops,0,20)];
echo json_encode($out,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT),PHP_EOL;
