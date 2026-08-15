<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require_once __DIR__.'/../app/Service/FinanceAuditLogService.php';
require_once __DIR__.'/../app/Service/FinanceStructureService.php';
require_once __DIR__.'/../app/Service/FinanceMatchingRuleService.php';

use App\Service\FinanceMatchingRuleService as Rules;

function ok(bool $condition,string $message): void {
    if (!$condition) throw new RuntimeException('FAIL: '.$message);
}
function one(PDO $pdo,string $sql,array $params=[]): array {
    $stmt=$pdo->prepare($sql);$stmt->execute($params);$row=$stmt->fetch(PDO::FETCH_ASSOC);return $row?:[];
}
function countRows(PDO $pdo,string $table): int { return (int)$pdo->query('SELECT COUNT(*) FROM `'.$table.'`')->fetchColumn(); }

$host=getenv('EMPLOYEE_PAYMENTS_DB_HOST')?:'127.0.0.1';
$port=getenv('EMPLOYEE_PAYMENTS_DB_PORT')?:'3306';
$name=getenv('EMPLOYEE_PAYMENTS_DB_NAME')?:'erp_employee_payments_test';
$user=getenv('EMPLOYEE_PAYMENTS_DB_USER')?:'root';
$pass=getenv('EMPLOYEE_PAYMENTS_DB_PASSWORD')?:'root';
$pdo=new PDO("mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4",$user,$pass,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);

$pdo->exec('SET FOREIGN_KEY_CHECKS=0');
foreach(['finance_matching_results','finance_audit_log','finance_cash_flow_center_dds_categories','finance_matching_rules','finance_operations','bank_transactions','finance_money_accounts','finance_dds_categories','finance_cash_flow_centers','finance_allocations','finance_invoices'] as $table){$pdo->exec('DROP TABLE IF EXISTS `'.$table.'`');}
$pdo->exec('SET FOREIGN_KEY_CHECKS=1');

$pdo->exec("CREATE TABLE finance_cash_flow_centers (id INT UNSIGNED PRIMARY KEY,name VARCHAR(255) NOT NULL,is_active TINYINT NOT NULL DEFAULT 1,sort_order INT NOT NULL DEFAULT 100)");
$pdo->exec("CREATE TABLE finance_dds_categories (id INT UNSIGNED PRIMARY KEY,name VARCHAR(255) NOT NULL,direction VARCHAR(20) NOT NULL,is_active TINYINT NOT NULL DEFAULT 1,is_system TINYINT NOT NULL DEFAULT 0,sort_order INT NOT NULL DEFAULT 100)");
$pdo->exec("CREATE TABLE finance_cash_flow_center_dds_categories (cash_flow_center_id INT UNSIGNED NOT NULL,dds_category_id INT UNSIGNED NOT NULL,sort_order INT NOT NULL DEFAULT 100,is_active TINYINT NOT NULL DEFAULT 1,PRIMARY KEY(cash_flow_center_id,dds_category_id))");
$pdo->exec("CREATE TABLE finance_money_accounts (id INT UNSIGNED PRIMARY KEY,name VARCHAR(255) NOT NULL,type VARCHAR(20) NOT NULL,is_active TINYINT NOT NULL DEFAULT 1)");
$pdo->exec("CREATE TABLE finance_matching_rules (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,name VARCHAR(255) NOT NULL,active TINYINT NOT NULL DEFAULT 1,priority INT NOT NULL DEFAULT 100,direction VARCHAR(20) NULL,bank_account_id INT UNSIGNED NULL,counterparty_inn VARCHAR(20) NULL,counterparty_id INT UNSIGNED NULL,counterparty_type VARCHAR(20) NULL,invoice_number_pattern VARCHAR(255) NULL,purpose_contains VARCHAR(255) NULL,purpose_regex VARCHAR(500) NULL,amount_from DECIMAL(15,2) NULL,amount_to DECIMAL(15,2) NULL,action_type VARCHAR(30) NOT NULL DEFAULT 'categorize',target_dds_category_id INT UNSIGNED NULL,target_cash_flow_center_id INT UNSIGNED NULL,target_cash_account_id INT UNSIGNED NULL,target_counterparty_id INT UNSIGNED NULL,target_counterparty_type VARCHAR(20) NULL,auto_apply TINYINT NOT NULL DEFAULT 1,deleted_at DATETIME NULL,created_by_user_id INT UNSIGNED NULL,created_by_role VARCHAR(20) NULL,updated_by_user_id INT UNSIGNED NULL,updated_by_role VARCHAR(20) NULL,created_at DATETIME DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)");
$pdo->exec("CREATE TABLE bank_transactions (
 id INT UNSIGNED PRIMARY KEY,account_id INT UNSIGNED NOT NULL,operation_date DATE NOT NULL,credit_amount DECIMAL(15,2) NOT NULL DEFAULT 0,debit_amount DECIMAL(15,2) NOT NULL DEFAULT 0,counterparty_inn VARCHAR(20) NULL,counterparty_name VARCHAR(255) NULL,purpose TEXT NULL,cash_flow_center_id INT UNSIGNED NULL,dds_category_id INT UNSIGNED NULL,classification_status VARCHAR(30) NOT NULL DEFAULT 'UNALLOCATED',classification_rule_id INT UNSIGNED NULL,classification_locked TINYINT NOT NULL DEFAULT 0,classification_updated_at DATETIME NULL,is_internal_transfer TINYINT NOT NULL DEFAULT 0,linked_cash_transaction_id INT UNSIGNED NULL
)");
$pdo->exec("CREATE TABLE finance_operations (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,operation_type VARCHAR(20) NOT NULL,status VARCHAR(20) NOT NULL DEFAULT 'POSTED',source VARCHAR(30) NOT NULL,money_account_id INT UNSIGNED NULL,transfer_account_id INT UNSIGNED NULL,transfer_group_id VARCHAR(80) NULL,transfer_direction VARCHAR(10) NULL,operation_date DATE NOT NULL,amount DECIMAL(15,2) NOT NULL,currency VARCHAR(10) NOT NULL DEFAULT 'RUR',purpose TEXT NULL,comment TEXT NULL,dedupe_hash VARCHAR(64) NULL UNIQUE,bank_transaction_id INT UNSIGNED NULL,counterparty_entity_id INT UNSIGNED NULL,counterparty_entity_type VARCHAR(30) NULL,counterparty_inn VARCHAR(20) NULL,counterparty_name VARCHAR(255) NULL,cash_flow_center_id INT UNSIGNED NULL,dds_category_id INT UNSIGNED NULL,classification_status VARCHAR(30) NOT NULL DEFAULT 'UNALLOCATED',classification_rule_id INT UNSIGNED NULL,classification_locked TINYINT NOT NULL DEFAULT 0,classification_updated_at DATETIME NULL,created_by_user_id INT UNSIGNED NULL,created_by_role VARCHAR(20) NULL,posted_by_user_id INT UNSIGNED NULL,posted_by_role VARCHAR(20) NULL,posted_at DATETIME NULL
)");
$pdo->exec("CREATE TABLE finance_matching_results (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,finance_operation_id INT UNSIGNED NOT NULL,rule_id INT UNSIGNED NULL,reason TEXT NULL,confidence VARCHAR(20) NULL,result VARCHAR(30) NULL,applied_at DATETIME NULL,source VARCHAR(20) NULL,created_at DATETIME NOT NULL)");
$pdo->exec("CREATE TABLE finance_audit_log (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,entity_type VARCHAR(64) NOT NULL,entity_id INT UNSIGNED NOT NULL,action VARCHAR(64) NOT NULL,old_values JSON NULL,new_values JSON NULL,created_by_user_id INT UNSIGNED NOT NULL,created_by_role VARCHAR(64) NOT NULL,created_at DATETIME NOT NULL)");
$pdo->exec("CREATE TABLE finance_allocations (id INT UNSIGNED PRIMARY KEY,note VARCHAR(50) NULL)");
$pdo->exec("CREATE TABLE finance_invoices (id INT UNSIGNED PRIMARY KEY,note VARCHAR(50) NULL)");

$pdo->exec("INSERT INTO finance_cash_flow_centers VALUES (1,'Операционные расходы',1,100)");
$pdo->exec("INSERT INTO finance_dds_categories VALUES (1,'Хозяйственные расходы','EXPENSE',1,0,100),(2,'Прочие поступления','INCOME',1,0,100)");
$pdo->exec("INSERT INTO finance_cash_flow_center_dds_categories VALUES (1,1,100,1),(1,2,100,1)");
$pdo->exec("INSERT INTO finance_money_accounts VALUES (10,'Основная касса','CASH',1),(11,'Резервная касса','CASH',1),(20,'Расчётный счёт','BANK',1)");
$pdo->exec("INSERT INTO finance_allocations VALUES (1,'sentinel')");
$pdo->exec("INSERT INTO finance_invoices VALUES (1,'sentinel')");
$userContext=['id'=>7,'role'=>'company_owner'];

$ruleId=Rules::createRule($pdo,[
    'name'=>'Комиссия с выдачей в кассу','priority'=>500,'direction'=>'EXPENSE','counterparty_inn'=>'7701234567','purpose_contains'=>'выдача наличных',
    'action_type'=>'categorize','target_cash_flow_center_id'=>1,'target_dds_category_id'=>1,'target_cash_account_id'=>10,
],$userContext);
$stored=one($pdo,'SELECT * FROM finance_matching_rules WHERE id=?',[$ruleId]);
ok(($stored['action_type']??'')==='categorize_to_cash','cash selection stores combined action type');
ok(($stored['direction']??'')==='EXPENSE','combined rule is expense-only');

$pdo->exec("INSERT INTO bank_transactions (id,account_id,operation_date,debit_amount,credit_amount,counterparty_inn,counterparty_name,purpose) VALUES (200,1,'2026-08-15',1234.56,0,'7701234567','Банк','Выдача наличных по чеку')");
$pdo->exec("INSERT INTO finance_operations (id,operation_type,status,source,money_account_id,operation_date,amount,currency,purpose,bank_transaction_id,counterparty_inn,counterparty_name) VALUES (1000,'EXPENSE','POSTED','BANK_STATEMENT',20,'2026-08-15',1234.56,'RUR','Выдача наличных по чеку',200,'7701234567','Банк')");

$result=Rules::applyAutoMatchToOperation($pdo,1000);
ok(!empty($result['matched'])&&!empty($result['transferred_to_cash']),'combined rule classifies and transfers');
$cashOperationId=(int)($result['cash_operation_id']??0);ok($cashOperationId>0&&$cashOperationId!==1000,'cash transfer operation created');

$bank=one($pdo,'SELECT * FROM bank_transactions WHERE id=200');
ok((int)$bank['cash_flow_center_id']===1&&(int)$bank['dds_category_id']===1,'bank transaction keeps CFU and DDS');
ok($bank['classification_status']==='AUTO'&&(int)$bank['classification_rule_id']===$ruleId,'bank classification points to rule');
ok((int)$bank['is_internal_transfer']===1&&(int)$bank['linked_cash_transaction_id']===$cashOperationId,'bank transaction linked to cash transfer');

$out=one($pdo,'SELECT * FROM finance_operations WHERE id=1000');
ok($out['operation_type']==='TRANSFER'&&$out['source']==='TRANSFER'&&$out['transfer_direction']==='out','original bank operation becomes transfer-out');
ok((int)$out['cash_flow_center_id']===1&&(int)$out['dds_category_id']===1,'outgoing transfer preserves CFU and DDS');
ok((int)$out['transfer_account_id']===10,'outgoing transfer targets selected cash account');
$in=one($pdo,'SELECT * FROM finance_operations WHERE id=?',[$cashOperationId]);
ok($in['operation_type']==='TRANSFER'&&$in['source']==='TRANSFER'&&$in['transfer_direction']==='in','paired cash operation is transfer-in');
ok((int)$in['money_account_id']===10&&(int)$in['transfer_account_id']===20,'cash leg links cash and original bank accounts');
ok((string)$in['amount']==='1234.56','cash leg preserves exact amount');
ok($in['cash_flow_center_id']===null&&$in['dds_category_id']===null,'cash leg does not duplicate P&L classification');

$beforeRepeat=countRows($pdo,'finance_operations');
$repeat=Rules::applyAutoMatchToOperation($pdo,1000);
ok(!empty($repeat['idempotent']),'reapply is explicitly idempotent');
ok((int)($repeat['cash_operation_id']??0)===$cashOperationId,'reapply points to same cash operation');
ok(countRows($pdo,'finance_operations')===$beforeRepeat,'reapply creates no duplicate transfer');

$plainRuleId=Rules::createRule($pdo,[
    'name'=>'Только разнесение','priority'=>400,'direction'=>'EXPENSE','counterparty_inn'=>'7707654321','purpose_contains'=>'без кассы',
    'action_type'=>'categorize','target_cash_flow_center_id'=>1,'target_dds_category_id'=>1,'target_cash_account_id'=>'',
],$userContext);
$plainStored=one($pdo,'SELECT action_type FROM finance_matching_rules WHERE id=?',[$plainRuleId]);ok($plainStored['action_type']==='categorize','empty cash keeps ordinary categorize action');
$pdo->exec("INSERT INTO bank_transactions (id,account_id,operation_date,debit_amount,credit_amount,counterparty_inn,counterparty_name,purpose) VALUES (201,1,'2026-08-15',500.00,0,'7707654321','Поставщик','Оплата без кассы')");
$pdo->exec("INSERT INTO finance_operations (id,operation_type,status,source,money_account_id,operation_date,amount,currency,purpose,bank_transaction_id,counterparty_inn,counterparty_name) VALUES (1100,'EXPENSE','POSTED','BANK_STATEMENT',20,'2026-08-15',500.00,'RUR','Оплата без кассы',201,'7707654321','Поставщик')");
$opsBeforePlain=countRows($pdo,'finance_operations');$plain=Rules::applyAutoMatchToOperation($pdo,1100);ok(!empty($plain['matched'])&&empty($plain['transferred_to_cash']),'ordinary categorize still works without transfer');ok(countRows($pdo,'finance_operations')===$opsBeforePlain,'ordinary categorize creates no cash leg');

$invalidIncome=false;
try{Rules::createRule($pdo,[
    'name'=>'Нельзя доход в кассу','priority'=>300,'direction'=>'INCOME','counterparty_inn'=>'7700000001','purpose_contains'=>'доход',
    'action_type'=>'categorize','target_cash_flow_center_id'=>1,'target_dds_category_id'=>2,'target_cash_account_id'=>10,
],$userContext);}catch(InvalidArgumentException $e){$invalidIncome=str_contains($e->getMessage(),'только к расходу');}
ok($invalidIncome,'income classification cannot be configured with bank-to-cash transfer');

$rollbackRuleId=Rules::createRule($pdo,[
    'name'=>'Атомарность','priority'=>600,'direction'=>'EXPENSE','counterparty_inn'=>'7709999999','purpose_contains'=>'проверка отката',
    'action_type'=>'categorize','target_cash_flow_center_id'=>1,'target_dds_category_id'=>1,'target_cash_account_id'=>11,
],$userContext);
$pdo->exec("UPDATE finance_money_accounts SET is_active=0 WHERE id=11");
$pdo->exec("INSERT INTO bank_transactions (id,account_id,operation_date,debit_amount,credit_amount,counterparty_inn,counterparty_name,purpose) VALUES (202,1,'2026-08-15',700.00,0,'7709999999','Банк','Проверка отката')");
$pdo->exec("INSERT INTO finance_operations (id,operation_type,status,source,money_account_id,operation_date,amount,currency,purpose,bank_transaction_id,counterparty_inn,counterparty_name) VALUES (1200,'EXPENSE','POSTED','BANK_STATEMENT',20,'2026-08-15',700.00,'RUR','Проверка отката',202,'7709999999','Банк')");
$opsBeforeRollback=countRows($pdo,'finance_operations');$thrown=false;try{Rules::applyAutoMatchToOperation($pdo,1200);}catch(Throwable){$thrown=true;}ok($thrown,'transfer failure is surfaced');
$rollbackBank=one($pdo,'SELECT * FROM bank_transactions WHERE id=202');$rollbackOp=one($pdo,'SELECT * FROM finance_operations WHERE id=1200');
ok($rollbackBank['classification_status']==='UNALLOCATED'&&$rollbackBank['cash_flow_center_id']===null&&$rollbackBank['dds_category_id']===null,'failed transfer rolls back bank classification');
ok($rollbackOp['classification_status']==='UNALLOCATED'&&$rollbackOp['cash_flow_center_id']===null&&$rollbackOp['dds_category_id']===null,'failed transfer rolls back finance operation classification');
ok((int)$rollbackBank['is_internal_transfer']===0&&countRows($pdo,'finance_operations')===$opsBeforeRollback,'failed transfer creates no cash movement');

$pdo->exec("INSERT INTO bank_transactions (id,account_id,operation_date,debit_amount,credit_amount,counterparty_inn,counterparty_name,purpose,classification_status,classification_locked) VALUES (203,1,'2026-08-15',900.00,0,'7701234567','Банк','Выдача наличных вручную','MANUAL',1)");
$pdo->exec("INSERT INTO finance_operations (id,operation_type,status,source,money_account_id,operation_date,amount,currency,purpose,bank_transaction_id,counterparty_inn,counterparty_name,classification_status,classification_locked) VALUES (1300,'EXPENSE','POSTED','BANK_STATEMENT',20,'2026-08-15',900.00,'RUR','Выдача наличных вручную',203,'7701234567','Банк','MANUAL',1)");
$opsBeforeManual=countRows($pdo,'finance_operations');$manual=Rules::applyAutoMatchToOperation($pdo,1300);ok(!empty($manual['protected']),'manual classification stays protected');ok(countRows($pdo,'finance_operations')===$opsBeforeManual,'manual-protected operation creates no cash transfer');

ok(countRows($pdo,'finance_allocations')===1&&countRows($pdo,'finance_invoices')===1,'allocations and invoices are untouched');

$crud=file_get_contents(__DIR__.'/../app/Service/FinanceMatchingRuleCrudTrait.php');
ok(str_contains($crud,"operation_type,''))<>'TRANSFER'"),'rule removal never rewrites executed transfer operations');

echo "FINANCE_MATCHING_CASH_TRANSFER_OK\n";