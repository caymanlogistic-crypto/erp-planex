<?php
/** P48 production READ-ONLY finance/cash inventory. No DML/DDL. */
function qi($s) { return '`'.str_replace('`','``',$s).'`'; }
function fields($schema) { $out=array(); foreach($schema as $r){ if(isset($r['Field'])) $out[]=$r['Field']; } return $out; }
function section($name,$data){ echo "\n=== ".$name." ===\n"; print_r($data); echo "\n"; }
function connectCfg($d){ return new PDO('mysql:host='.$d['host'].';port='.$d['port'].';dbname='.$d['database'].';charset='.$d['charset'],$d['username'],$d['password'],array(PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC)); }
$live='/home/s/spugovxsim/planexp/public_html/erpv2';
$config=require $live.'/bootstrap/app.php';
require_once $live.'/app/Support/crypto_helper.php';
require_once $live.'/app/Support/company_database.php';
$central=connectCfg($config['database']);
$central->exec('SET SESSION TRANSACTION READ ONLY');
$central->beginTransaction();
try {
 $active=$central->query("SELECT id,name,status,db_identifier,db_host,db_port,db_username FROM companies WHERE status='active' ORDER BY id")->fetchAll();
 section('ACTIVE_COMPANIES_SAFE',$active);
 $candidates=$central->query("SELECT * FROM companies WHERE status='active' AND (UPPER(name) LIKE '%PLANEX%' OR name LIKE '%ПЛАНЭКС%') ORDER BY id")->fetchAll();
 if(count($candidates)!==1){
   if(count($active)===1){ $st=$central->prepare('SELECT * FROM companies WHERE id=?'); $st->execute(array($active[0]['id'])); $company=$st->fetch(); }
   else { throw new RuntimeException('PLANEX tenant resolution is not unique.'); }
 } else { $company=$candidates[0]; }
 section('SELECTED_COMPANY_SAFE',array('id'=>$company['id'],'name'=>$company['name'],'db_identifier'=>$company['db_identifier'],'db_host'=>$company['db_host'],'db_port'=>$company['db_port'],'db_username'=>$company['db_username']));
 $tenantCfg=companyDatabaseConfig($config,$company);
 $tenant=connectCfg($tenantCfg);
 $tenant->exec('SET SESSION TRANSACTION READ ONLY');
 $tenant->beginTransaction();
 section('TENANT_DATABASE',array('database'=>$tenant->query('SELECT DATABASE()')->fetchColumn(),'mode'=>'READ ONLY','php'=>PHP_VERSION));
 $all=$tenant->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
 $interesting=array(); foreach($all as $t){ if(preg_match('/finance|employee|cash|invoice|obligation|allocation|settlement|payment|bank/i',$t)) $interesting[]=$t; } sort($interesting);
 section('INTERESTING_TABLES',$interesting);
 $schemas=array();$counts=array(); foreach($interesting as $t){$schemas[$t]=$tenant->query('SHOW COLUMNS FROM '.qi($t))->fetchAll();$counts[$t]=(int)$tenant->query('SELECT COUNT(*) FROM '.qi($t))->fetchColumn();}
 section('ROW_COUNTS',$counts);
 section('SCHEMAS',$schemas);
 if(in_array('finance_operations',$all,true)){
  $cols=fields($schemas['finance_operations']);
  $st=$tenant->prepare('SELECT * FROM finance_operations WHERE id=?');$st->execute(array(159));section('FINANCE_OPERATION_159',$st->fetchAll());
  section('FINANCE_OPERATIONS_ALL',$tenant->query('SELECT * FROM finance_operations ORDER BY id ASC LIMIT 10000')->fetchAll());
  $dist=array();foreach($cols as $c){if(preg_match('/status|type|source|direction|kind|method|account/i',$c)){try{$dist[$c]=$tenant->query('SELECT '.qi($c).' AS v,COUNT(*) AS n FROM finance_operations GROUP BY '.qi($c).' ORDER BY n DESC')->fetchAll();}catch(Exception $x){}}}section('FINANCE_OPERATION_DISTRIBUTIONS',$dist);
 }
 $rows=array();foreach($interesting as $t){if($t==='finance_operations')continue;$n=$counts[$t];if($n<=3000){$cols=fields($schemas[$t]);$order=in_array('id',$cols,true)?' ORDER BY id ASC':'';try{$rows[$t]=$tenant->query('SELECT * FROM '.qi($t).$order.' LIMIT 3000')->fetchAll();}catch(Exception $x){$rows[$t]=array('__error'=>$x->getMessage());}}else{$rows[$t]=array('__skipped_rows'=>$n);}}
 section('RELEVANT_ROWS',$rows);
 $hits=array();foreach($interesting as $t){foreach($schemas[$t] as $m){$c=$m['Field'];$type=strtolower($m['Type']);if(!preg_match('/char|text|enum|set/',$type))continue;try{$sql='SELECT * FROM '.qi($t).' WHERE LOWER(COALESCE('.qi($c).",'')) LIKE '%cash%' OR LOWER(COALESCE(".qi($c).",'')) LIKE '%касс%' LIMIT 200";$r=$tenant->query($sql)->fetchAll();if($r)$hits[$t.'.'.$c]=$r;}catch(Exception $x){}}}section('EXPLICIT_CASH_HITS',$hits);
 $tenant->rollBack();
 $central->rollBack();
 echo "\nP48_FINANCE_CASH_INVENTORY_OK\n";
}catch(Throwable $e){if(isset($tenant)&&$tenant->inTransaction())$tenant->rollBack();if($central->inTransaction())$central->rollBack();fwrite(STDERR,'P48_ERROR: '.$e->getMessage()."\n");exit(2);}