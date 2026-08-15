<?php

require_once __DIR__ . '/../app/Service/FinanceCashLedgerService.php';

use App\Service\FinanceCashLedgerService;

function assertCashLedger(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException('FAIL: ' . $message);
    }
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
$pdo->exec('DROP TABLE IF EXISTS finance_employee_movements');
$pdo->exec('DROP TABLE IF EXISTS finance_operations');
$pdo->exec('DROP TABLE IF EXISTS finance_money_accounts');
$pdo->exec('SET FOREIGN_KEY_CHECKS=1');

$pdo->exec("CREATE TABLE finance_money_accounts (
    id INT UNSIGNED PRIMARY KEY,
    type VARCHAR(20) NOT NULL,
    name VARCHAR(255) NOT NULL
) ENGINE=InnoDB");

$pdo->exec("CREATE TABLE finance_operations (
    id INT UNSIGNED PRIMARY KEY,
    operation_type VARCHAR(30) NOT NULL,
    status VARCHAR(30) NOT NULL,
    source VARCHAR(30) NOT NULL,
    money_account_id INT UNSIGNED NOT NULL,
    transfer_account_id INT UNSIGNED NULL,
    transfer_group_id VARCHAR(100) NULL,
    transfer_direction VARCHAR(10) NULL,
    operation_date DATE NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    currency VARCHAR(10) NOT NULL DEFAULT 'RUR',
    purpose TEXT NULL,
    comment TEXT NULL,
    created_at DATETIME NOT NULL
) ENGINE=InnoDB");

$pdo->exec("CREATE TABLE finance_employee_movements (
    id INT UNSIGNED PRIMARY KEY,
    finance_operation_id INT UNSIGNED NOT NULL,
    employee_name_snapshot VARCHAR(255) NOT NULL,
    movement_type VARCHAR(20) NOT NULL
) ENGINE=InnoDB");

$pdo->exec("INSERT INTO finance_money_accounts(id,type,name) VALUES
    (1,'BANK','Расчётный счёт 40702810000000000001'),
    (2,'CASH','Основная касса'),
    (3,'CASH','Резервная касса')");

$pdo->exec("INSERT INTO finance_operations
    (id,operation_type,status,source,money_account_id,transfer_account_id,transfer_group_id,transfer_direction,operation_date,amount,currency,purpose,comment,created_at)
VALUES
    (10,'TRANSFER','POSTED','TRANSFER',1,2,'TRF_BANK_CASH','out','2026-08-13',549.97,'RUR','Покупка 13.08.2026',NULL,'2026-08-13 10:00:00'),
    (11,'TRANSFER','POSTED','TRANSFER',2,1,'TRF_BANK_CASH','in','2026-08-13',549.97,'RUR','Покупка 13.08.2026','Банк → касса','2026-08-13 10:00:01'),
    (12,'EXPENSE','POSTED','CASH',2,NULL,NULL,NULL,'2026-08-14',5000.00,'RUR','Выдано под отчёт',NULL,'2026-08-14 09:00:00'),
    (13,'INCOME','POSTED','CASH',2,NULL,NULL,NULL,'2026-08-14',1250.00,'RUR','Возврат подотчёта',NULL,'2026-08-14 10:00:00'),
    (14,'TRANSFER','POSTED','TRANSFER',2,1,'TRF_CASH_BANK','out','2026-08-15',700.00,'RUR','Возврат на расчётный счёт',NULL,'2026-08-15 11:00:00')");

$pdo->exec("INSERT INTO finance_employee_movements(id,finance_operation_id,employee_name_snapshot,movement_type) VALUES
    (1,12,'Петров Пётр Петрович','PAYMENT'),
    (2,13,'Иванов Иван Иванович','RETURN')");

$result = FinanceCashLedgerService::fetchRecentMovements($pdo, 1, 20);
assertCashLedger($result['total'] === 4, 'cash ledger must count only CASH-side operations');
assertCashLedger(count($result['data']) === 4, 'cash ledger must return exactly four CASH-side rows');

$byId = [];
foreach ($result['data'] as $row) {
    $byId[(int) $row['id']] = $row;
    assertCashLedger(($row['account_type'] ?? '') === 'CASH', 'every ledger row must belong to a CASH account');
}

assertCashLedger(!isset($byId[10]), 'bank-side half of Bank → Cash transfer must be hidden from cash journal');
assertCashLedger(isset($byId[11]), 'cash-side half of Bank → Cash transfer must remain visible');
assertCashLedger(($byId[11]['source_recipient_label'] ?? '') === 'Расчётный счёт 40702810000000000001', 'Bank → Cash source must be the company bank account');
assertCashLedger(FinanceCashLedgerService::movementLabel($byId[11]) === 'Поступление', 'Bank → Cash must be labelled as receipt');

assertCashLedger(($byId[12]['source_recipient_label'] ?? '') === 'Сотрудник: Петров Пётр Петрович', 'cash payment must name employee as recipient');
assertCashLedger(FinanceCashLedgerService::movementLabel($byId[12]) === 'Списание', 'employee payment must be labelled as write-off');

assertCashLedger(($byId[13]['source_recipient_label'] ?? '') === 'Сотрудник: Иванов Иван Иванович', 'employee return must name employee as source');
assertCashLedger(FinanceCashLedgerService::movementLabel($byId[13]) === 'Поступление', 'employee return must be labelled as receipt');

assertCashLedger(($byId[14]['source_recipient_label'] ?? '') === 'Расчётный счёт 40702810000000000001', 'Cash → Bank recipient must be the company bank account');
assertCashLedger(FinanceCashLedgerService::movementLabel($byId[14]) === 'Списание', 'Cash → Bank must be labelled as write-off');

$page1 = FinanceCashLedgerService::fetchRecentMovements($pdo, 1, 2);
$page2 = FinanceCashLedgerService::fetchRecentMovements($pdo, 2, 2);
assertCashLedger($page1['total'] === 4 && $page1['pages'] === 2, 'cash-only pagination total/pages');
assertCashLedger(count($page1['data']) === 2 && count($page2['data']) === 2, 'cash-only pagination rows');

$view = file_get_contents(__DIR__ . '/../app/View/pages/company_finance_cash.php');
assertCashLedger(str_contains($view, 'Источник / Получатель'), 'cash journal must expose source/recipient column');
assertCashLedger(str_contains($view, 'Движение'), 'cash journal must expose human movement column');
assertCashLedger(str_contains($view, 'FinanceCashLedgerService::movementLabel'), 'cash journal must use cash movement semantics');
assertCashLedger(!str_contains($view, 'Перевод (исходящий)'), 'raw outgoing transfer label must not be shown in cash journal');
assertCashLedger(!str_contains($view, 'Перевод (входящий)'), 'raw incoming transfer label must not be shown in cash journal');

echo "FINANCE_CASH_LEDGER_OK\n";
