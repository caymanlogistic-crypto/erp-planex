<?php
/** P48 production READ-ONLY finance/cash inventory. No DML/DDL. */
function qi($s) { return '`'.str_replace('`','``',$s).'`'; }
function fields($schema) { $out=array(); foreach($schema as $r){ if(isset($r['Field'])) $out[]=$r['Field']; } return $out; }
function section($name,$data){ echo "\n=== ".$name." ===\n"; print_r($data); echo "\n"; }
$live='/home/s/spugovxsim/planexp/public_html/erpv2';
$config=require $live.'/bootstrap/app.php';
$d=$config['database'];
$pdo=new PDO('mysql:host='.$d['host'].';port='.$d['port'].';dbname='.$d['database'].';charset='.$d['charset'],$d['username'],$d['password'],array(PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC));
$pdo->exec('SET SESSION TRANSACTION READ ONLY');
$pdo->beginTransaction();
try {
 section('DATABASE',array('database'=>$pdo->query('SELECT DATABASE()')->fetchColumn(),'mode'=>'READ ONLY','php'=>PHP_VERSION));
 $all=$pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
 $interesting=array(); foreach($all as $t){ if(preg_match('/finance|employee|cash|invoice|obligation|allocation|settlement|payment/i',$t)) $interesting[]=$t; } sort($interesting);
 section('INTERESTING_TABLES',$interesting);
 $schemas=array();$counts=array(); foreach($interesting as $t){$schemas[$t]=$pdo->query('SHOW COLUMNS FROM '.qi($t))->fetchAll();$counts[$t]=(int)$pdo->query('SELECT COUNT(*) FROM '.qi($t))->fetchColumn();}
 section('SCHEMAS',$schemas); section('ROW_COUNTS',$counts);
 if(in_array('finance_operations',$all,true)){
  $cols=fields($schemas['finance_operations']);
  $st=$pdo->prepare('SELECT * FROM finance_operations WHERE id=?');$st->execute(array(159));section('FINANCE_OPERATION_159',$st->fetchAll());
  section('FINANCE_OPERATIONS_ALL',$pdo->query('SELECT * FROM finance_operations ORDER BY id ASC LIMIT 10000')->fetchAll());
  $dist=array();foreach($cols as $c){if(preg_match('/status|type|source|direction|kind|method|account/i',$c)){try{$dist[$c]=$pdo->query('SELECT '.qi($c).' AS v,COUNT(*) AS n FROM finance_operations GROUP BY '.qi($c).' ORDER BY n DESC')->fetchAll();}catch(Exception $x){}}}section('FINANCE_OPERATION_DISTRIBUTIONS',$dist);
 }
 $rows=array();foreach($interesting as $t){if($t==='finance_operations')continue;$n=$counts[$t];if($n<=2000){$cols=fields($schemas[$t]);$order=in_array('id',$cols,true)?' ORDER BY id ASC':'';try{$rows[$t]=$pdo->query('SELECT * FROM '.qi($t).$order.' LIMIT 2000')->fetchAll();}catch(Exception $x){$rows[$t]=array('__error'=>$x->getMessage());}}else{$rows[$t]=array('__skipped_rows'=>$n);}}
 section('RELEVANT_ROWS',$rows);
 $hits=array();foreach($interesting as $t){foreach($schemas[$t] as $m){$c=$m['Field'];$type=strtolower($m['Type']);if(!preg_match('/char|text|enum|set/',$type))continue;try{$sql='SELECT * FROM '.qi($t).' WHERE LOWER(COALESCE('.qi($c).",'')) LIKE '%cash%' OR LOWER(COALESCE(".qi($c).",'')) LIKE '%касс%' LIMIT 200";$r=$pdo->query($sql)->fetchAll();if($r)$hits[$t.'.'.$c]=$r;}catch(Exception $x){}}}section('EXPLICIT_CASH_HITS',$hits);
 $pdo->rollBack();echo "\nP48_FINANCE_CASH_INVENTORY_OK\n";
}catch(Exception $e){if($pdo->inTransaction())$pdo->rollBack();fwrite(STDERR,'P48_ERROR: '.$e->getMessage()."\n");exit(2);}