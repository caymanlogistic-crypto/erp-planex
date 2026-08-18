<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require_once __DIR__.'/../app/Service/FinanceAuditLogService.php';
require_once __DIR__.'/../app/Service/FinanceStructureService.php';
require_once __DIR__.'/../app/Service/FinanceMatchingRuleService.php';

use App\Service\FinanceMatchingRuleService as Rules;

function ok(bool $condition,string $message): void { if (!$condition) throw new RuntimeException('FAIL: '.$message); }
function one(PDO $pdo,string $sql,array $params=[]): array { $s=$pdo->prepare($sql);$s->execute($params);$r=$s->fetch(PDO::FETCH_ASSOC);return $r?:[]; }
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
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,name VARCHAR(255) NOT NULL,active TINYINT NOT NULL DEFAULT 1,priority INT NOT NULL DEFAULT 100,direction VARCHAR(20) NULL,bank_account_id INT UNSIGNED NULL,counterparty_inn VARCHAR(20) NULL,counterparty_id INT UNSIGNED NULL,counterparty_type VARCHAR(20) NULL,invoice_number_pattern VARCHAR(255) NULL,purpose_contains VARCHAR(255) NULL,purpose_regex VARCHAR(500) NULL,amount_from DECIMAL(15,2) NULL,amount_to DECIMAL(15,2) NULL,action_type VARCHAR(30) NOT NULL DEFAULT 'categorize',target_dds_category_id INT UNSIGNED NULL,target_cash_flow_center_id INT UNSIGNED NULL,target_cash_account_id INT UNSIGNED NULL,target_employee_identity_type VARCHAR(30) NULL,target_employee_identity_id INT UNSIGNED NULL,target_employee_name_snapshot VARCHAR(255) NULL,target_employee_role_snapshot VARCHAR(255) NULL,target_counterparty_id INT UNSIGNED NULL,target_counterparty_type VARCHAR(20) NULL,auto_apply TINYINT NOT NULL DEFAULT 1,deleted_at DATETIME NULL,created_by_user_id INT UNSIGNED NULL,created_by_role VARCHAR(20) NULL,updated_by_user_id INT UNSIGNED NULL,updated_by_role VARCHAR(20) NULL,created_at DATETIME DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
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
$pdo->exec("INSERT INTO finance_dds_categories VALUES (1,'Хозяйственные расходы','EXPENSE',1,0,100)");
$pdo->exec("INSERT INTO finance_cash_flow_center_dds_categories VALUES (1,1,100,1)");
$pdo->exec("INSERT INTO finance_money_accounts VALUES (10,'Основная касса','CASH',1),(20,'Расчётный счёт','BANK',1)");
$pdo->exec("INSERT INTO finance_allocations VALUES (1,'sentinel')");
$pdo->exec("INSERT INTO finance_invoices VALUES (1,'sentinel')");
$userContext=['id'=>7,'role'=>'company_owner'];

// A historical categorize_to_cash configuration may still exist, but execution is classification-only.
$ruleId=Rules::createRule($pdo,[
    'name'=>'Legacy classify with cash target','priority'=>500,'direction'=>'EXPENSE','counterparty_inn'=>'7701234567','purpose_contains'=>'выдача наличных',
    'action_type'=>'categorize','target_cash_flow_center_id'=>1,'target_dds_category_id'=>1,'target_cash_account_id'=>10,
],$userContext);
$stored=one($pdo,'SELECT * FROM finance_matching_rules WHERE id=?',[$ruleId]);
ok(($stored['action_type']??'')==='categorize_to_cash','legacy combined action remains representable for audit compatibility');

$pdo->exec("INSERT INTO bank_transactions (id,account_id,operation_date,debit_amount,credit_amount,counterparty_inn,counterparty_name,purpose) VALUES (200,1,'2026-08-15',1234.56,0,'7701234567','Банк','Выдача наличных по чеку')");
$pdo->exec("INSERT INTO finance_operations (id,operation_type,status,source,money_account_id,operation_date,amount,currency,purpose,bank_transaction_id,counterparty_inn,counterparty_name) VALUES (1000,'EXPENSE','POSTED','BANK_STATEMENT',20,'2026-08-15',1234.56,'RUR','Выдача наличных по чеку',200,'7701234567','Банк')");
$opsBefore=countRows($pdo,'finance_operations');
$result=Rules::applyAutoMatchToOperation($pdo,1000);
ok(!empty($result['matched']),'legacy combined rule still classifies');
ok(empty($result['transferred_to_cash']) && empty($result['cash_operation_id']) && ($result['cash_operation_created']??null)===false,'legacy combined rule creates no CASH leg');
ok(countRows($pdo,'finance_operations')===$opsBefore,'classification-only execution creates no finance operation');
$bank=one($pdo,'SELECT * FROM bank_transactions WHERE id=200');
$op=one($pdo,'SELECT * FROM finance_operations WHERE id=1000');
ok((int)$bank['cash_flow_center_id']===1&&(int)$bank['dds_category_id']===1&&$bank['classification_status']==='AUTO','bank transaction is classified');
ok((int)$bank['is_internal_transfer']===0&&$bank['linked_cash_transaction_id']===null,'bank transaction is never rewritten as internal CASH transfer');
ok($op['operation_type']==='EXPENSE'&&$op['source']==='BANK_STATEMENT'&&$op['transfer_direction']===null,'source operation remains the real bank expense');
ok((int)$op['cash_flow_center_id']===1&&(int)$op['dds_category_id']===1,'source operation keeps CFU and DDS');

// A transfer_to_cash rule is historical-only and must fail closed into review.
$legacyTransferId=Rules::createRule($pdo,[
    'name'=>'Legacy cash transfer','priority'=>600,'direction'=>'EXPENSE','counterparty_inn'=>'7709999999','purpose_contains'=>'legacy transfer',
    'action_type'=>'transfer_to_cash','target_cash_account_id'=>10,
],$userContext);
$pdo->exec("INSERT INTO bank_transactions (id,account_id,operation_date,debit_amount,credit_amount,counterparty_inn,counterparty_name,purpose) VALUES (201,1,'2026-08-15',700.00,0,'7709999999','Банк','Legacy transfer')");
$pdo->exec("INSERT INTO finance_operations (id,operation_type,status,source,money_account_id,operation_date,amount,currency,purpose,bank_transaction_id,counterparty_inn,counterparty_name) VALUES (1100,'EXPENSE','POSTED','BANK_STATEMENT',20,'2026-08-15',700.00,'RUR','Legacy transfer',201,'7709999999','Банк')");
$opsBeforeLegacy=countRows($pdo,'finance_operations');
$legacy=Rules::applyAutoMatchToOperation($pdo,1100);
ok(!empty($legacy['needs_review'])&&!empty($legacy['legacy_cash_rule'])&&empty($legacy['matched']),'transfer_to_cash is suppressed and requires review');
ok(countRows($pdo,'finance_operations')===$opsBeforeLegacy,'suppressed transfer_to_cash creates no operation');
$legacyBank=one($pdo,'SELECT * FROM bank_transactions WHERE id=201');
$legacyOp=one($pdo,'SELECT * FROM finance_operations WHERE id=1100');
ok($legacyBank['classification_status']==='NEEDS_REVIEW'&&(int)$legacyBank['is_internal_transfer']===0,'legacy bank row is review-only');
ok($legacyOp['operation_type']==='EXPENSE'&&$legacyOp['source']==='BANK_STATEMENT','legacy source operation is not rewritten');

// Manual classifications remain protected from all automation.
$pdo->exec("INSERT INTO bank_transactions (id,account_id,operation_date,debit_amount,credit_amount,counterparty_inn,counterparty_name,purpose,classification_status,classification_locked) VALUES (202,1,'2026-08-15',900.00,0,'7701234567','Банк','Выдача наличных вручную','MANUAL',1)");
$pdo->exec("INSERT INTO finance_operations (id,operation_type,status,source,money_account_id,operation_date,amount,currency,purpose,bank_transaction_id,counterparty_inn,counterparty_name,classification_status,classification_locked) VALUES (1200,'EXPENSE','POSTED','BANK_STATEMENT',20,'2026-08-15',900.00,'RUR','Выдача наличных вручную',202,'7701234567','Банк','MANUAL',1)");
$manualBefore=countRows($pdo,'finance_operations');
$manual=Rules::applyAutoMatchToOperation($pdo,1200);
ok(!empty($manual['protected']),'manual classification stays protected');
ok(countRows($pdo,'finance_operations')===$manualBefore,'manual-protected operation creates no CASH transfer');

ok(countRows($pdo,'finance_allocations')===1&&countRows($pdo,'finance_invoices')===1,'allocations and invoices are untouched');
$classification=file_get_contents(__DIR__.'/../app/Service/FinanceMatchingRuleClassificationTrait.php');
ok(str_contains($classification,'cash_rule_deprecated'),'legacy cash-only rules have an explicit deprecated path');
ok(str_contains($classification,"'cash_operation_created'=>false"),'classification path explicitly records zero CASH writes');

echo "FINANCE_MATCHING_CASH_TRANSFER_OK\n";
