<?php
require_once __DIR__.'/../app/Service/FinanceAuditLogService.php';
require_once __DIR__.'/../app/Service/FinanceCashService.php';
require_once __DIR__.'/../app/Service/FinanceEmployeePaymentService.php';
use App\Service\FinanceEmployeePaymentService as S;

function ok(bool $v,string $m):void{if(!$v)throw new RuntimeException('FAIL: '.$m);}
function throws(callable $fn,string $needle,string $m):void{try{$fn();}catch(Throwable $e){ok(str_contains($e->getMessage(),$needle),$m.' / '.$e->getMessage());return;}throw new RuntimeException('FAIL: '.$m.' / exception expected');}

$pdo=new PDO(
 'mysql:host='.(getenv('EMPLOYEE_PAYMENTS_DB_HOST')?:'127.0.0.1').';port='.(getenv('EMPLOYEE_PAYMENTS_DB_PORT')?:'3306').';dbname='.(getenv('EMPLOYEE_PAYMENTS_DB_NAME')?:'erp_employee_payments_test').';charset=utf8mb4',
 getenv('EMPLOYEE_PAYMENTS_DB_USER')?:'root',getenv('EMPLOYEE_PAYMENTS_DB_PASSWORD')?:'root',
 [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]
);
$pdo->exec('SET FOREIGN_KEY_CHECKS=0');foreach(['finance_employee_movements','finance_audit_log','finance_operations','bank_transactions','bank_accounts','finance_money_accounts','users'] as $t)$pdo->exec("DROP TABLE IF EXISTS `$t`");$pdo->exec('SET FOREIGN_KEY_CHECKS=1');
$pdo->exec("CREATE TABLE users(id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,full_name VARCHAR(255) NOT NULL,login VARCHAR(255) NOT NULL,role_code VARCHAR(30) NOT NULL,status VARCHAR(20) NOT NULL DEFAULT 'active',deleted_at DATETIME NULL) ENGINE=InnoDB");
$pdo->exec("CREATE TABLE finance_money_accounts(id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,type VARCHAR(20) NOT NULL,name VARCHAR(255) NOT NULL,bank_account_id INT UNSIGNED NULL,currency VARCHAR(10) NOT NULL DEFAULT 'RUR',opening_balance DECIMAL(15,2) NOT NULL DEFAULT 0.00,opening_balance_date DATE NULL,is_active TINYINT(1) NOT NULL DEFAULT 1,created_by_user_id INT UNSIGNED NULL,created_by_role VARCHAR(20) NULL,updated_by_user_id INT UNSIGNED NULL,updated_by_role VARCHAR(20) NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB");
$pdo->exec("CREATE TABLE bank_accounts(id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,account_number VARCHAR(64) NOT NULL) ENGINE=InnoDB");
$pdo->exec("CREATE TABLE bank_transactions(id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,account_id INT UNSIGNED NOT NULL,operation_date DATE NOT NULL,document_number VARCHAR(100) NULL,counterparty_name VARCHAR(500) NULL,counterparty_inn VARCHAR(20) NULL,debit_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,credit_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,purpose TEXT NULL,is_internal_transfer TINYINT(1) NOT NULL DEFAULT 0) ENGINE=InnoDB");
$pdo->exec("CREATE TABLE finance_operations(id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,operation_type VARCHAR(20) NOT NULL,status VARCHAR(20) NOT NULL DEFAULT 'POSTED',source VARCHAR(30) NOT NULL,money_account_id INT UNSIGNED NOT NULL,transfer_direction VARCHAR(10) NULL,operation_date DATE NOT NULL,amount DECIMAL(15,2) NOT NULL,currency VARCHAR(10) NOT NULL DEFAULT 'RUR',dds_category_id INT UNSIGNED NULL,purpose TEXT NULL,comment TEXT NULL,bank_transaction_id INT UNSIGNED NULL,created_by_user_id INT UNSIGNED NULL,created_by_role VARCHAR(20) NULL,posted_by_user_id INT UNSIGNED NULL,posted_by_role VARCHAR(20) NULL,posted_at DATETIME NULL,cancelled_at DATETIME NULL,UNIQUE KEY uk_fop_bank_tx(bank_transaction_id)) ENGINE=InnoDB");
$pdo->exec("CREATE TABLE finance_employee_movements(id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,employee_user_id INT UNSIGNED NOT NULL,movement_type VARCHAR(20) NOT NULL,source_type VARCHAR(20) NOT NULL,finance_operation_id INT UNSIGNED NOT NULL,bank_transaction_id INT UNSIGNED NULL,note VARCHAR(1000) NULL,created_by_user_id INT UNSIGNED NULL,created_by_role VARCHAR(20) NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,UNIQUE KEY uk_fem_finance_operation(finance_operation_id),UNIQUE KEY uk_fem_bank_transaction(bank_transaction_id),CONSTRAINT fk_fem_employee FOREIGN KEY(employee_user_id) REFERENCES users(id) ON DELETE RESTRICT,CONSTRAINT fk_fem_operation FOREIGN KEY(finance_operation_id) REFERENCES finance_operations(id) ON DELETE RESTRICT,CONSTRAINT fk_fem_bank_tx FOREIGN KEY(bank_transaction_id) REFERENCES bank_transactions(id) ON DELETE RESTRICT) ENGINE=InnoDB");
$pdo->exec("CREATE TABLE finance_audit_log(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,entity_type VARCHAR(64) NOT NULL,entity_id INT UNSIGNED NOT NULL,action VARCHAR(64) NOT NULL,old_values JSON NULL,new_values JSON NULL,created_by_user_id INT UNSIGNED NOT NULL,created_by_role VARCHAR(64) NOT NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB");

$pdo->exec("INSERT INTO users(id,full_name,login,role_code,status,deleted_at) VALUES(1,'Руководитель','owner','company_owner','active',NULL),(2,'Логист','logist','logist','active',NULL),(3,'Старший логист','senior','senior_logist','active',NULL),(4,'Другой пользователь','other','dispatcher','active',NULL),(5,'Неактивный','inactive','logist','inactive',NULL)");
$pdo->exec("INSERT INTO finance_money_accounts(id,type,name,currency,opening_balance,is_active) VALUES(1,'CASH','Основная касса','RUR',1000.00,1),(2,'BANK','Основной расчётный счёт','RUR',0.00,1)");
$pdo->exec("INSERT INTO bank_accounts(id,account_number) VALUES(1,'40702810000000000001')");
$actor=['id'=>1,'role'=>'company_owner'];

$active=S::fetchActiveEmployees($pdo);$roles=array_column($active,'role_code');
ok(count($active)===4,'exactly all active users returned');
foreach(['company_owner','logist','senior_logist','dispatcher'] as $role)ok(in_array($role,$roles,true),'role '.$role.' is eligible employee');
ok(!in_array(5,array_map('intval',array_column($active,'id')),true),'inactive user excluded');

$p=S::createCashMovement($pdo,['movement_type'=>'PAYMENT','employee_user_id'=>2,'money_account_id'=>1,'amount'=>'100,10','operation_date'=>'2026-08-15','purpose'=>'Подотчёт'],$actor);
$r=S::createCashMovement($pdo,['movement_type'=>'RETURN','employee_user_id'=>2,'money_account_id'=>1,'amount'=>'0,20','operation_date'=>'2026-08-15','purpose'=>'Возврат'],$actor);
$l=S::fetchEmployeeLedger($pdo,2);ok(count($l)===2,'cash PAYMENT and RETURN in ledger');ok($l[0]['running_balance']==='99.90','exact cents 100.10-0.20=99.90');ok(S::formatMoney('99.90')==='99,90','exact formatting');
throws(fn()=>S::createCashMovement($pdo,['movement_type'=>'PAYMENT','employee_user_id'=>1,'money_account_id'=>1,'amount'=>'5000.00','operation_date'=>'2026-08-15'],$actor),'Недостаточно средств','cash overdraw rejected');

$pdo->exec("INSERT INTO bank_transactions(id,account_id,operation_date,document_number,counterparty_name,counterparty_inn,debit_amount,credit_amount,purpose,is_internal_transfer) VALUES(10,1,'2026-08-15','10','Иванов','7700000001',250,0,'Выплата',0),(11,1,'2026-08-15','11','Иванов','7700000001',0,50,'Возврат',0),(12,1,'2026-08-15','12','Внутренний',NULL,75,0,'Банк в кассу',1),(13,1,'2026-08-15','13','Петров','7700000002',10,0,'Выплата',0)");
$pdo->exec("INSERT INTO finance_operations(id,operation_type,status,source,money_account_id,transfer_direction,operation_date,amount,currency,purpose,bank_transaction_id,posted_at) VALUES(10,'EXPENSE','POSTED','BANK_STATEMENT',2,NULL,'2026-08-15',250,'RUR','Выплата',10,NOW()),(11,'INCOME','POSTED','BANK_STATEMENT',2,NULL,'2026-08-15',50,'RUR','Возврат',11,NOW()),(12,'TRANSFER','POSTED','TRANSFER',2,'out','2026-08-15',75,'RUR','Банк в кассу',12,NOW()),(13,'EXPENSE','POSTED','BANK_STATEMENT',2,NULL,'2026-08-15',10,'RUR','Выплата',13,NOW())");
$b=S::linkBankTransaction($pdo,['movement_type'=>'PAYMENT','employee_user_id'=>1,'bank_transaction_id'=>10],$actor);ok($b['finance_operation_id']===10,'BANK payment reuses operation');
throws(fn()=>S::linkBankTransaction($pdo,['movement_type'=>'PAYMENT','employee_user_id'=>2,'bank_transaction_id'=>10],$actor),'уже связана','duplicate bank link rejected');
throws(fn()=>S::linkBankTransaction($pdo,['movement_type'=>'PAYMENT','employee_user_id'=>1,'bank_transaction_id'=>11],$actor),'не соответствует','wrong bank direction rejected');
throws(fn()=>S::linkBankTransaction($pdo,['movement_type'=>'PAYMENT','employee_user_id'=>1,'bank_transaction_id'=>12],$actor),'Внутренний перевод','internal transfer rejected');
S::linkBankTransaction($pdo,['movement_type'=>'RETURN','employee_user_id'=>1,'bank_transaction_id'=>11],$actor);

S::unlinkBankTransaction($pdo,10,$actor);ok(S::findByBankTransaction($pdo,10)===null,'unlink removes relation');ok((int)$pdo->query('SELECT COUNT(*) FROM finance_operations WHERE id=10')->fetchColumn()===1,'unlink keeps money operation');
S::linkBankTransaction($pdo,['movement_type'=>'PAYMENT','employee_user_id'=>3,'bank_transaction_id'=>10],$actor);ok((int)S::findByBankTransaction($pdo,10)['employee_user_id']===3,'bank relink works');

$fix=S::reassignMovement($pdo,(int)$p['movement_id'],4,$actor);ok($fix['old_employee_user_id']===2&&$fix['employee_user_id']===4,'cash movement reassigned');ok((string)$pdo->query('SELECT amount FROM finance_operations WHERE id='.(int)$p['finance_operation_id'])->fetchColumn()==='100.10','reassign keeps amount');ok((int)$pdo->query("SELECT COUNT(*) FROM finance_audit_log WHERE action='reassign_employee'")->fetchColumn()===1,'reassign audited');
throws(fn()=>S::reassignMovement($pdo,(int)$p['movement_id'],5,$actor),'неактивен','inactive reassign rejected');

$c=S::createCashMovement($pdo,['movement_type'=>'PAYMENT','employee_user_id'=>4,'money_account_id'=>1,'amount'=>'1.00','operation_date'=>'2026-08-14'],$actor);$pdo->prepare("UPDATE finance_operations SET status='CANCELLED',cancelled_at=NOW() WHERE id=?")->execute([$c['finance_operation_id']]);
$sum=S::fetchEmployeeSummaries($pdo,['employee_user_id'=>4]);ok(count($sum)===1,'cancelled-only history remains accessible');ok((int)$sum[0]['cancelled_movement_count']>=1,'cancelled history counted');
$pdo->prepare("UPDATE finance_operations SET status='CANCELLED',cancelled_at=NOW() WHERE id=?")->execute([$r['finance_operation_id']]);$l=S::fetchEmployeeLedger($pdo,2);ok(array_reduce($l,fn($v,$x)=>$v||$x['status']==='CANCELLED',false),'cancelled movement remains in ledger');

$ids=array_map('intval',array_column(S::fetchBankCandidates($pdo,'PAYMENT',500),'id'));ok(in_array(13,$ids,true),'unlinked debit candidate visible');ok(!in_array(10,$ids,true),'linked debit excluded');ok(!in_array(12,$ids,true),'internal transfer excluded');
ok(count(S::fetchEmployeeSummaries($pdo,['source_type'=>'BANK']))>=1,'BANK filter works');
ok((int)$pdo->query('SELECT COUNT(*) FROM finance_employee_movements')->fetchColumn()===(int)$pdo->query('SELECT COUNT(DISTINCT finance_operation_id) FROM finance_employee_movements')->fetchColumn(),'one movement per finance operation');

echo "FINANCE_EMPLOYEE_PAYMENTS_BEHAVIOR_OK\n";
