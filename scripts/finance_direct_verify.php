<?php
if(PHP_SAPI!=='cli'&&PHP_SAPI!=='cgi-fcgi'){fwrite(STDERR,"CLI/CGI only.\n");exit(1);}
$envRoot=getenv('PLANEX_ROOT'); $argRoot=isset($argv[1])?$argv[1]:''; $root=rtrim((string)($envRoot!==false&&$envRoot!==''?$envRoot:$argRoot),'/');
if($root===''||!is_file($root.'/bootstrap/app.php')) throw new RuntimeException('PLANEX_ROOT is invalid.');
require_once $root.'/app/Support/helpers.php'; require_once $root.'/app/Support/environment.php'; loadEnvFileNonOverwriting($root.'/.env'); $config=require $root.'/bootstrap/app.php'; require_once $root.'/app/Support/entrypoint_dependencies.php';
$central=(new \App\Core\Database($config['database']))->connection(); $stmt=$central->prepare("SELECT * FROM companies WHERE id=25 AND status='active'"); $stmt->execute(); $company=$stmt->fetch(PDO::FETCH_ASSOC); if(!$company) throw new RuntimeException('PLANEX company id=25 missing.');
$pdo=(new \App\Core\Database(companyDatabaseConfig($config,$company)))->connection(); $dbName=(string)$pdo->query('SELECT DATABASE()')->fetchColumn(); if($dbName!=='spugovxsim_1') throw new RuntimeException('Unexpected tenant '.$dbName);
$pdo->exec('SET TRANSACTION READ ONLY'); $pdo->beginTransaction();
try{
 $scalar=function($pdo,$sql,$params=array()){ $q=$pdo->prepare($sql);$q->execute($params);$v=$q->fetchColumn();return $v===false||$v===null?'0':(string)$v;};
 $exists=function($pdo,$table){$q=$pdo->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?");$q->execute(array($table));return (int)$q->fetchColumn()===1;};
 $snap=array(
 'company_id'=>25,'tenant_db'=>$dbName,
 'finance_operations'=>(int)$scalar($pdo,'SELECT COUNT(*) FROM finance_operations'),
 'finance_cash_resolutions'=>(int)$scalar($pdo,'SELECT COUNT(*) FROM finance_cash_resolutions'),
 'finance_employee_movements'=>(int)$scalar($pdo,'SELECT COUNT(*) FROM finance_employee_movements'),
 'finance_employee_invoice_payments'=>(int)$scalar($pdo,'SELECT COUNT(*) FROM finance_employee_invoice_payments'),
 'finance_employee_personal_expenses'=>(int)$scalar($pdo,'SELECT COUNT(*) FROM finance_employee_personal_expenses'),
 'finance_matching_results'=>(int)$scalar($pdo,'SELECT COUNT(*) FROM finance_matching_results'),
 'finance_operation_allocations'=>(int)$scalar($pdo,'SELECT COUNT(*) FROM finance_operation_allocations'),
 'cash_operations_all_statuses'=>(int)$scalar($pdo,"SELECT COUNT(*) FROM finance_operations fo JOIN finance_money_accounts ma ON ma.id=fo.money_account_id WHERE ma.type='CASH'"),
 'cash_posted_balance'=>number_format((float)$scalar($pdo,"SELECT COALESCE(SUM(CASE WHEN fo.status<>'POSTED' THEN 0 WHEN fo.operation_type='INCOME' THEN fo.amount WHEN fo.operation_type='EXPENSE' THEN -fo.amount WHEN fo.operation_type='TRANSFER' AND fo.transfer_direction='in' THEN fo.amount WHEN fo.operation_type='TRANSFER' AND fo.transfer_direction='out' THEN -fo.amount ELSE 0 END),0) FROM finance_operations fo JOIN finance_money_accounts ma ON ma.id=fo.money_account_id WHERE ma.type='CASH'"),2,'.',''),
 'cancelled_159_count'=>(int)$scalar($pdo,"SELECT COUNT(*) FROM finance_operations WHERE id=159 AND status='CANCELLED' AND amount=300000.00"),
 'employee_account_table_exists'=>$exists($pdo,'finance_employee_money_accounts'),
 'employee_account_rows'=>$exists($pdo,'finance_employee_money_accounts')?(int)$scalar($pdo,'SELECT COUNT(*) FROM finance_employee_money_accounts'):0,
 'migration_076_applied'=>$exists($pdo,'schema_migrations')?(int)$scalar($pdo,"SELECT COUNT(*) FROM schema_migrations WHERE migration='076_finance_employee_money_accounts.sql'"):0);
 if($snap['cash_posted_balance']!=='0.00') throw new RuntimeException('Historical CASH balance '.$snap['cash_posted_balance']); if($snap['cancelled_159_count']!==1) throw new RuntimeException('Cancelled #159 invariant failed.');
 $canonical=json_encode($snap); if($canonical===false) throw new RuntimeException('JSON encode failed.'); $fp=hash('sha256',$canonical); echo 'FINANCE_DIRECT_READ_ONLY_SNAPSHOT='.$canonical.PHP_EOL.'FINANCE_DIRECT_READ_ONLY_FINGERPRINT='.$fp.PHP_EOL."FINANCE_DIRECT_READ_ONLY_OK\n"; $pdo->rollBack();
}catch(Exception $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
