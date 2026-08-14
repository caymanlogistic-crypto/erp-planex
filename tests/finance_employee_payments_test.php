<?php

require_once __DIR__ . '/../app/Service/FinanceAuditLogService.php';
require_once __DIR__ . '/../app/Service/FinanceCashService.php';
require_once __DIR__ . '/../app/Service/FinanceEmployeePaymentService.php';

use App\Service\FinanceEmployeePaymentService as EmployeePayments;

function ep_ok(bool $value, string $message): void
{
    if (!$value) throw new RuntimeException('FAIL: ' . $message);
}

function ep_throws(callable $fn, string $contains, string $message): void
{
    try {
        $fn();
    } catch (Throwable $e) {
        ep_ok(str_contains($e->getMessage(), $contains), $message . ' — unexpected message: ' . $e->getMessage());
        return;
    }
    throw new RuntimeException('FAIL: ' . $message . ' — exception expected');
}

$host = getenv('EMPLOYEE_PAYMENTS_DB_HOST') ?: '127.0.0.1';
$port = getenv('EMPLOYEE_PAYMENTS_DB_PORT') ?: '3306';
$dbName = getenv('EMPLOYEE_PAYMENTS_DB_NAME') ?: 'erp_employee_payments_test';
$user = getenv('EMPLOYEE_PAYMENTS_DB_USER') ?: 'root';
$password = getenv('EMPLOYEE_PAYMENTS_DB_PASSWORD') ?: 'root';

$pdo = new PDO(
    "mysql:host={$host};port={$port};dbname={$dbName};charset=utf8mb4",
    $user,
    $password,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);

$pdo->exec('SET FOREIGN_KEY_CHECKS=0');
foreach (['finance_employee_movements','finance_audit_log','finance_operations','bank_transactions','bank_accounts','finance_money_accounts','users'] as $table) {
    $pdo->exec("DROP TABLE IF EXISTS `{$table}`");
}
$pdo->exec('SET FOREIGN_KEY_CHECKS=1');

$pdo->exec("CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(255) NOT NULL,
    login VARCHAR(255) NOT NULL,
    role_code VARCHAR(30) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    deleted_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$pdo->exec("CREATE TABLE finance_money_accounts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    type VARCHAR(20) NOT NULL,
    name VARCHAR(255) NOT NULL,
    bank_account_id INT UNSIGNED NULL,
    currency VARCHAR(10) NOT NULL DEFAULT 'RUR',
    opening_balance DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    opening_balance_date DATE NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_by_user_id INT UNSIGNED NULL,
    created_by_role VARCHAR(20) NULL,
    updated_by_user_id INT UNSIGNED NULL,
    updated_by_role VARCHAR(20) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$pdo->exec("CREATE TABLE bank_accounts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    account_number VARCHAR(64) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$pdo->exec("CREATE TABLE bank_transactions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    account_id INT UNSIGNED NOT NULL,
    operation_date DATE NOT NULL,
    document_number VARCHAR(100) NULL,
    counterparty_name VARCHAR(500) NULL,
    counterparty_inn VARCHAR(20) NULL,
    debit_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    credit_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    purpose TEXT NULL,
    is_internal_transfer TINYINT(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$pdo->exec("CREATE TABLE finance_operations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    operation_type VARCHAR(20) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'POSTED',
    source VARCHAR(30) NOT NULL,
    money_account_id INT UNSIGNED NOT NULL,
    operation_date DATE NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    currency VARCHAR(10) NOT NULL DEFAULT 'RUR',
    dds_category_id INT UNSIGNED NULL,
    purpose TEXT NULL,
    comment TEXT NULL,
    bank_transaction_id INT UNSIGNED NULL,
    created_by_user_id INT UNSIGNED NULL,
    created_by_role VARCHAR(20) NULL,
    posted_by_user_id INT UNSIGNED NULL,
    posted_by_role VARCHAR(20) NULL,
    posted_at DATETIME NULL,
    cancelled_at DATETIME NULL,
    UNIQUE KEY uk_fop_bank_tx (bank_transaction_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$pdo->exec("CREATE TABLE finance_employee_movements (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employee_user_id INT UNSIGNED NOT NULL,
    movement_type VARCHAR(20) NOT NULL,
    source_type VARCHAR(20) NOT NULL,
    finance_operation_id INT UNSIGNED NOT NULL,
    bank_transaction_id INT UNSIGNED NULL,
    note VARCHAR(1000) NULL,
    created_by_user_id INT UNSIGNED NULL,
    created_by_role VARCHAR(20) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_fem_finance_operation (finance_operation_id),
    UNIQUE KEY uk_fem_bank_transaction (bank_transaction_id),
    CONSTRAINT fk_fem_employee FOREIGN KEY (employee_user_id) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_fem_operation FOREIGN KEY (finance_operation_id) REFERENCES finance_operations(id) ON DELETE RESTRICT,
    CONSTRAINT fk_fem_bank_tx FOREIGN KEY (bank_transaction_id) REFERENCES bank_transactions(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$pdo->exec("CREATE TABLE finance_audit_log (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    entity_type VARCHAR(64) NOT NULL,
    entity_id INT UNSIGNED NOT NULL,
    action VARCHAR(64) NOT NULL,
    old_values JSON NULL,
    new_values JSON NULL,
    created_by_user_id INT UNSIGNED NOT NULL,
    created_by_role VARCHAR(64) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$pdo->exec("INSERT INTO users (id,full_name,login,role_code,status,deleted_at) VALUES
    (1,'Руководитель','owner','company_owner','active',NULL),
    (2,'Логист','logist','logist','active',NULL),
    (3,'Старший логист','senior','senior_logist','active',NULL),
    (4,'Другой пользователь','other','dispatcher','active',NULL),
    (5,'Неактивный','inactive','logist','inactive',NULL)");
$pdo->exec("INSERT INTO finance_money_accounts (id,type,name,currency,opening_balance,is_active) VALUES
    (1,'CASH','Основная касса','RUR',1000.00,1),
    (2,'BANK','Основной расчётный счёт','RUR',0.00,1)");
$pdo->exec("INSERT INTO bank_accounts (id,account_number) VALUES (1,'40702810000000000001')");

$active = EmployeePayments::fetchActiveEmployees($pdo);
ep_ok(array_column($active, 'id') === [4,2,1,3] || count($active) === 4, 'all active tenant users are employees regardless of role');
ep_ok(!in_array(5, array_map('intval', array_column($active, 'id')), true), 'inactive user excluded');
$roles = array_column($active, 'role_code');
ep_ok(in_array('company_owner',$roles,true) && in_array('logist',$roles,true) && in_array('senior_logist',$roles,true) && in_array('dispatcher',$roles,true), 'owner, logist and other active roles included');

$actor = ['id'=>1,'role'=>'company_owner'];

$cashPayment = EmployeePayments::createCashMovement($pdo, [
    'movement_type'=>'PAYMENT','employee_user_id'=>2,'money_account_id'=>1,
    'amount'=>'100,10','operation_date'=>'2026-08-15','purpose'=>'Подотчёт'
], $actor);
ep_ok($cashPayment['movement_id'] > 0 && $cashPayment['finance_operation_id'] > 0, 'cash PAYMENT creates real finance operation and movement');

$cashReturn = EmployeePayments::createCashMovement($pdo, [
    'movement_type'=>'RETURN','employee_user_id'=>2,'money_account_id'=>1,
    'amount'=>'0,20','operation_date'=>'2026-08-15','purpose'=>'Возврат'
], $actor);
ep_ok($cashReturn['movement_id'] > 0, 'cash RETURN creates real finance operation');

$ledger = EmployeePayments::fetchEmployeeLedger($pdo, 2);
ep_ok(count($ledger) === 2, 'employee ledger contains cash payment and return');
ep_ok($ledger[0]['running_balance'] === '99.90', 'ledger uses exact cents: 100.10 - 0.20 = 99.90');
ep_ok(EmployeePayments::formatMoney('99.90') === '99,90', 'money display is decimal-safe');

ep_throws(function () use ($pdo,$actor) {
    EmployeePayments::createCashMovement($pdo, [
        'movement_type'=>'PAYMENT','employee_user_id'=>1,'money_account_id'=>1,
        'amount'=>'5000.00','operation_date'=>'2026-08-15'
    ], $actor);
}, 'Недостаточно средств', 'cash PAYMENT cannot overdraw cash account');

$pdo->exec("INSERT INTO bank_transactions (id,account_id,operation_date,document_number,counterparty_name,counterparty_inn,debit_amount,credit_amount,purpose,is_internal_transfer) VALUES
    (10,1,'2026-08-15','10','Иванов И.И.','7700000001',250.00,0.00,'Выплата сотруднику',0),
    (11,1,'2026-08-15','11','Иванов И.И.','7700000001',0.00,50.00,'Возврат сотрудника',0),
    (12,1,'2026-08-15','12','Внутренний перевод',NULL,75.00,0.00,'Банк в кассу',1),
    (13,1,'2026-08-15','13','Петров П.П.','7700000002',10.00,0.00,'Выплата',0)");
$pdo->exec("INSERT INTO finance_operations (id,operation_type,status,source,money_account_id,operation_date,amount,currency,purpose,bank_transaction_id,posted_at) VALUES
    (10,'EXPENSE','POSTED','BANK_STATEMENT',2,'2026-08-15',250.00,'RUR','Выплата сотруднику',10,NOW()),
    (11,'INCOME','POSTED','BANK_STATEMENT',2,'2026-08-15',50.00,'RUR','Возврат сотрудника',11,NOW()),
    (12,'TRANSFER','POSTED','TRANSFER',2,'2026-08-15',75.00,'RUR','Банк в кассу',12,NOW()),
    (13,'EXPENSE','POSTED','BANK_STATEMENT',2,'2026-08-15',10.00,'RUR','Выплата',13,NOW())");

$bankPayment = EmployeePayments::linkBankTransaction($pdo, [
    'movement_type'=>'PAYMENT','employee_user_id'=>1,'bank_transaction_id'=>10
], $actor);
ep_ok($bankPayment['finance_operation_id'] === 10, 'bank PAYMENT reuses existing posted finance operation');

ep_throws(fn()=>EmployeePayments::linkBankTransaction($pdo,[
    'movement_type'=>'PAYMENT','employee_user_id'=>2,'bank_transaction_id'=>10
],$actor),'уже связана','same bank operation cannot be linked twice');

ep_throws(fn()=>EmployeePayments::linkBankTransaction($pdo,[
    'movement_type'=>'PAYMENT','employee_user_id'=>1,'bank_transaction_id'=>11
],$actor),'не соответствует','bank credit cannot be registered as PAYMENT');

ep_throws(fn()=>EmployeePayments::linkBankTransaction($pdo,[
    'movement_type'=>'PAYMENT','employee_user_id'=>1,'bank_transaction_id'=>12
],$actor),'Внутренний перевод','internal bank-to-cash transfer cannot be employee payment');

$bankReturn = EmployeePayments::linkBankTransaction($pdo, [
    'movement_type'=>'RETURN','employee_user_id'=>1,'bank_transaction_id'=>11
], $actor);
ep_ok($bankReturn['finance_operation_id'] === 11, 'bank RETURN reuses existing posted finance operation');

EmployeePayments::unlinkBankTransaction($pdo, 10, $actor);
ep_ok(EmployeePayments::findByBankTransaction($pdo,10) === null, 'bank unlink removes only employee relation');
$operationStillExists = (int)$pdo->query('SELECT COUNT(*) FROM finance_operations WHERE id=10')->fetchColumn();
ep_ok($operationStillExists === 1, 'bank unlink never deletes money operation');
EmployeePayments::linkBankTransaction($pdo, ['movement_type'=>'PAYMENT','employee_user_id'=>3,'bank_transaction_id'=>10], $actor);
ep_ok((int)EmployeePayments::findByBankTransaction($pdo,10)['employee_user_id'] === 3, 'bank operation can be safely relinked after unlink');

$reassign = EmployeePayments::reassignMovement($pdo, (int)$cashPayment['movement_id'], 4, $actor);
ep_ok($reassign['old_employee_user_id'] === 2 && $reassign['employee_user_id'] === 4, 'cash movement can be corrected to another active employee');
$cashOperationAmount = $pdo->query('SELECT amount FROM finance_operations WHERE id='.(int)$cashPayment['finance_operation_id'])->fetchColumn();
ep_ok((string)$cashOperationAmount === '100.10', 'employee correction does not rewrite money amount');
$reassignAudit = (int)$pdo->query("SELECT COUNT(*) FROM finance_audit_log WHERE entity_type='finance_employee_movement' AND action='reassign_employee'")->fetchColumn();
ep_ok($reassignAudit === 1, 'employee correction is audited');

ep_throws(fn()=>EmployeePayments::reassignMovement($pdo,(int)$cashPayment['movement_id'],5,$actor),'неактивен','cannot reassign movement to inactive user');

$cancelOnly = EmployeePayments::createCashMovement($pdo, [
    'movement_type'=>'PAYMENT','employee_user_id'=>3,'money_account_id'=>1,
    'amount'=>'1.00','operation_date'=>'2026-08-14'
], $actor);
$pdo->prepare("UPDATE finance_operations SET status='CANCELLED',cancelled_at=NOW() WHERE id=?")->execute([$cancelOnly['finance_operation_id']]);
$summaryForSenior = EmployeePayments::fetchEmployeeSummaries($pdo,['employee_user_id'=>3]);
ep_ok(count($summaryForSenior) === 1, 'employee with only cancelled history remains visible in summaries');
ep_ok(EmployeePayments::moneySign($summaryForSenior[0]['balance_amount']) === 0, 'cancelled movement does not affect current balance');
ep_ok((int)$summaryForSenior[0]['cancelled_movement_count'] >= 1, 'cancelled movement is exposed in history metadata');

$pdo->prepare("UPDATE finance_operations SET status='CANCELLED',cancelled_at=NOW() WHERE id=?")->execute([$cashReturn['finance_operation_id']]);
$ledgerAfterCancel = EmployeePayments::fetchEmployeeLedger($pdo,2);
ep_ok(array_reduce($ledgerAfterCancel, fn($carry,$row)=>$carry || $row['status']==='CANCELLED', false), 'ledger preserves cancelled operations');

$candidatesPayment = EmployeePayments::fetchBankCandidates($pdo,'PAYMENT',500);
$candidateIds = array_map('intval',array_column($candidatesPayment,'id'));
ep_ok(in_array(13,$candidateIds,true), 'unlinked posted bank debit is offered as PAYMENT candidate');
ep_ok(!in_array(10,$candidateIds,true), 'already linked bank debit is excluded from candidates');
ep_ok(!in_array(12,$candidateIds,true), 'internal transfer is excluded from candidates');

$sourceFiltered = EmployeePayments::fetchEmployeeSummaries($pdo,['source_type'=>'BANK']);
ep_ok(count($sourceFiltered) >= 1, 'BANK source filter returns bank settlements');

$uniqueMovementCount = (int)$pdo->query('SELECT COUNT(*) FROM finance_employee_movements')->fetchColumn();
$uniqueOperationCount = (int)$pdo->query('SELECT COUNT(DISTINCT finance_operation_id) FROM finance_employee_movements')->fetchColumn();
ep_ok($uniqueMovementCount === $uniqueOperationCount, 'one employee movement per finance operation invariant holds');

echo "FINANCE_EMPLOYEE_PAYMENTS_BEHAVIOR_OK\n";
