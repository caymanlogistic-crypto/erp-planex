<?php

function backfillOk(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException('FAIL: ' . $message);
}

$pdo = new PDO(
    'mysql:host=' . (getenv('EMPLOYEE_PAYMENTS_DB_HOST') ?: '127.0.0.1')
    . ';port=' . (getenv('EMPLOYEE_PAYMENTS_DB_PORT') ?: '3306')
    . ';dbname=' . (getenv('EMPLOYEE_PAYMENTS_DB_NAME') ?: 'erp_employee_payments_test')
    . ';charset=utf8mb4',
    getenv('EMPLOYEE_PAYMENTS_DB_USER') ?: 'root',
    getenv('EMPLOYEE_PAYMENTS_DB_PASSWORD') ?: 'root',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);

$pdo->exec('SET FOREIGN_KEY_CHECKS=0');
foreach (['finance_cash_resolutions','finance_employee_movements','finance_operations','bank_transactions','finance_money_accounts'] as $table) {
    $pdo->exec("DROP TABLE IF EXISTS `$table`");
}
$pdo->exec('SET FOREIGN_KEY_CHECKS=1');

$pdo->exec("CREATE TABLE finance_money_accounts (
    id INT UNSIGNED PRIMARY KEY,
    type VARCHAR(20) NOT NULL,
    name VARCHAR(255) NOT NULL
) ENGINE=InnoDB");

$pdo->exec("CREATE TABLE bank_transactions (
    id INT UNSIGNED PRIMARY KEY,
    is_internal_transfer TINYINT(1) NOT NULL DEFAULT 0,
    linked_cash_transaction_id INT UNSIGNED NULL
) ENGINE=InnoDB");

$pdo->exec("CREATE TABLE finance_operations (
    id INT UNSIGNED PRIMARY KEY,
    operation_type VARCHAR(20) NOT NULL,
    status VARCHAR(20) NOT NULL,
    source VARCHAR(30) NOT NULL,
    money_account_id INT UNSIGNED NOT NULL,
    transfer_account_id INT UNSIGNED NULL,
    transfer_direction VARCHAR(10) NULL,
    classification_rule_id INT UNSIGNED NULL
) ENGINE=InnoDB");

$pdo->exec("CREATE TABLE finance_employee_movements (
    id INT UNSIGNED PRIMARY KEY,
    employee_identity_type VARCHAR(20) NOT NULL,
    employee_identity_id INT UNSIGNED NOT NULL,
    employee_name_snapshot VARCHAR(255) NOT NULL,
    movement_type VARCHAR(20) NOT NULL,
    finance_operation_id INT UNSIGNED NOT NULL,
    bank_transaction_id INT UNSIGNED NULL
) ENGINE=InnoDB");

$pdo->exec("CREATE TABLE finance_cash_resolutions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    source_finance_operation_id INT UNSIGNED NOT NULL,
    resolution_type VARCHAR(32) NOT NULL,
    target_identity_type VARCHAR(32) NULL,
    target_identity_id INT UNSIGNED NULL,
    target_name_snapshot VARCHAR(255) NULL,
    outflow_finance_operation_id INT UNSIGNED NOT NULL,
    employee_movement_id INT UNSIGNED NULL,
    created_by_user_id INT UNSIGNED NULL,
    created_by_role VARCHAR(20) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_fcr_source_operation(source_finance_operation_id),
    UNIQUE KEY uk_fcr_outflow_operation(outflow_finance_operation_id),
    UNIQUE KEY uk_fcr_employee_movement(employee_movement_id)
) ENGINE=InnoDB");

$pdo->exec("INSERT INTO finance_money_accounts(id,type,name) VALUES
    (1,'BANK','Расчётный счёт 40702810000000000001'),
    (2,'CASH','Основная касса')");

// Direct PAYMENT: bank -> cash transfer #101 -> employee expense #102.
// Reverse RETURN: employee income #201 -> cash transfer #202 -> bank.
$pdo->exec("INSERT INTO finance_operations
    (id,operation_type,status,source,money_account_id,transfer_account_id,transfer_direction,classification_rule_id)
VALUES
    (101,'TRANSFER','POSTED','TRANSFER',2,1,'in',10),
    (102,'EXPENSE','POSTED','CASH',2,NULL,NULL,10),
    (201,'INCOME','POSTED','CASH',2,NULL,NULL,11),
    (202,'TRANSFER','POSTED','TRANSFER',2,1,'out',11)");

$pdo->exec("INSERT INTO bank_transactions(id,is_internal_transfer,linked_cash_transaction_id) VALUES
    (1001,1,101),
    (1002,1,202)");

$pdo->exec("INSERT INTO finance_employee_movements
    (id,employee_identity_type,employee_identity_id,employee_name_snapshot,movement_type,finance_operation_id,bank_transaction_id)
VALUES
    (501,'COMPANY_USER',77,'Спугов Владимир Владимирович','PAYMENT',102,1001),
    (502,'COMPANY_USER',77,'Спугов Владимир Владимирович','RETURN',201,1002)");

$migration = file_get_contents(__DIR__ . '/../database/migrations-local/069_backfill_employee_rule_cash_resolutions.sql');
$pdo->exec($migration);
$pdo->exec($migration); // idempotency: second run must be a no-op.

$rows = $pdo->query('SELECT * FROM finance_cash_resolutions ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
backfillOk(count($rows) === 2, 'exactly two lifecycle rows after idempotent backfill');

$payment = $pdo->query("SELECT * FROM finance_cash_resolutions WHERE employee_movement_id=501")->fetch(PDO::FETCH_ASSOC);
backfillOk((int)$payment['source_finance_operation_id'] === 101, 'PAYMENT keeps incoming bank-to-cash transfer as canonical source');
backfillOk((int)$payment['outflow_finance_operation_id'] === 102, 'PAYMENT folds employee expense as outflow');
backfillOk($payment['resolution_type'] === 'EMPLOYEE', 'PAYMENT resolves to employee');
backfillOk($payment['target_name_snapshot'] === 'Спугов Владимир Владимирович', 'PAYMENT keeps employee identity snapshot');

$return = $pdo->query("SELECT * FROM finance_cash_resolutions WHERE employee_movement_id=502")->fetch(PDO::FETCH_ASSOC);
backfillOk((int)$return['source_finance_operation_id'] === 201, 'RETURN keeps employee cash income as canonical source');
backfillOk((int)$return['outflow_finance_operation_id'] === 202, 'RETURN folds cash-to-bank transfer as outflow');
backfillOk($return['resolution_type'] === 'BANK', 'RETURN resolves to bank');
backfillOk($return['target_name_snapshot'] === 'Расчётный счёт 40702810000000000001', 'RETURN keeps bank destination');

echo "FINANCE_EMPLOYEE_RULE_CASH_BACKFILL_OK\n";
