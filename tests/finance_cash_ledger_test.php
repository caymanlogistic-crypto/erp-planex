<?php

require_once __DIR__ . '/../app/Service/FinanceCashResolutionService.php';
require_once __DIR__ . '/../app/Service/FinanceCashLedgerService.php';

use App\Service\FinanceCashLedgerService;

function assertCashLedger(bool $condition, string $message): void
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
foreach (['finance_cash_resolutions','finance_employee_movements','finance_operations','finance_money_accounts'] as $table) $pdo->exec("DROP TABLE IF EXISTS `$table`");
$pdo->exec('SET FOREIGN_KEY_CHECKS=1');

$pdo->exec("CREATE TABLE finance_money_accounts (id INT UNSIGNED PRIMARY KEY,type VARCHAR(20) NOT NULL,name VARCHAR(255) NOT NULL) ENGINE=InnoDB");
$pdo->exec("CREATE TABLE finance_operations (
    id INT UNSIGNED PRIMARY KEY, operation_type VARCHAR(30) NOT NULL, status VARCHAR(30) NOT NULL,
    source VARCHAR(30) NOT NULL, money_account_id INT UNSIGNED NOT NULL, transfer_account_id INT UNSIGNED NULL,
    transfer_group_id VARCHAR(100) NULL, transfer_direction VARCHAR(10) NULL, operation_date DATE NOT NULL,
    amount DECIMAL(15,2) NOT NULL, currency VARCHAR(10) NOT NULL DEFAULT 'RUR', purpose TEXT NULL,
    comment TEXT NULL, created_at DATETIME NOT NULL
) ENGINE=InnoDB");
$pdo->exec("CREATE TABLE finance_employee_movements (id INT UNSIGNED PRIMARY KEY,finance_operation_id INT UNSIGNED NOT NULL,employee_name_snapshot VARCHAR(255) NOT NULL,movement_type VARCHAR(20) NOT NULL) ENGINE=InnoDB");
$pdo->exec("CREATE TABLE finance_cash_resolutions (
    id INT UNSIGNED PRIMARY KEY,
    source_finance_operation_id INT UNSIGNED NOT NULL,
    resolution_type VARCHAR(32) NOT NULL,
    target_name_snapshot VARCHAR(255) NULL,
    outflow_finance_operation_id INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL,
    UNIQUE KEY uk_source(source_finance_operation_id),
    UNIQUE KEY uk_outflow(outflow_finance_operation_id)
) ENGINE=InnoDB");

$pdo->exec("INSERT INTO finance_money_accounts(id,type,name) VALUES
    (1,'BANK','Расчётный счёт 40702810000000000001'),(2,'CASH','Основная касса'),(3,'CASH','Резервная касса')");
$pdo->exec("INSERT INTO finance_operations
    (id,operation_type,status,source,money_account_id,transfer_account_id,transfer_group_id,transfer_direction,operation_date,amount,currency,purpose,comment,created_at)
VALUES
    (10,'TRANSFER','POSTED','TRANSFER',1,2,'TRF_BANK_CASH','out','2026-08-13',549.97,'RUR','Покупка 13.08.2026',NULL,'2026-08-13 10:00:00'),
    (11,'TRANSFER','POSTED','TRANSFER',2,1,'TRF_BANK_CASH','in','2026-08-13',549.97,'RUR','Покупка 13.08.2026','Банк → касса','2026-08-13 10:00:01'),
    (12,'EXPENSE','POSTED','CASH',2,NULL,NULL,NULL,'2026-08-14',5000.00,'RUR','Выдано под отчёт',NULL,'2026-08-14 09:00:00'),
    (13,'INCOME','POSTED','CASH',2,NULL,NULL,NULL,'2026-08-14',1250.00,'RUR','Возврат подотчёта',NULL,'2026-08-14 10:00:00'),
    (14,'TRANSFER','POSTED','TRANSFER',2,1,'TRF_CASH_BANK','out','2026-08-15',700.00,'RUR','Возврат на расчётный счёт',NULL,'2026-08-15 11:00:00'),
    (15,'EXPENSE','POSTED','CASH',2,NULL,NULL,NULL,'2026-08-15',549.97,'RUR','Передано сотруднику: Иванов Иван Иванович · Покупка 13.08.2026','Старое дублирующее списание','2026-08-15 14:00:00'),
    (16,'INCOME','POSTED','CASH',2,NULL,NULL,NULL,'2026-08-15',99.00,'RUR','Неразнесённый источник',NULL,'2026-08-15 15:00:00'),
    (17,'INCOME','POSTED','CASH',2,NULL,NULL,NULL,'2026-08-16',2000.00,'RUR','Возврат сотрудником',NULL,'2026-08-16 09:00:00'),
    (18,'TRANSFER','POSTED','TRANSFER',2,1,'TRF_EMP_RETURN','out','2026-08-16',2000.00,'RUR','Внесение наличных',NULL,'2026-08-16 09:00:01')");
$pdo->exec("INSERT INTO finance_employee_movements(id,finance_operation_id,employee_name_snapshot,movement_type) VALUES
    (1,12,'Петров Пётр Петрович','PAYMENT'),
    (2,13,'Иванов Иван Иванович','RETURN'),
    (3,15,'Иванов Иван Иванович','PAYMENT'),
    (4,17,'Спугов Владимир Владимирович','RETURN')");
$pdo->exec("INSERT INTO finance_cash_resolutions(id,source_finance_operation_id,resolution_type,target_name_snapshot,outflow_finance_operation_id,created_at) VALUES
    (1,11,'EMPLOYEE','Иванов Иван Иванович',15,'2026-08-15 14:00:01'),
    (2,17,'BANK','Расчётный счёт 40702810000000000001',18,'2026-08-16 09:00:02')");

$result = FinanceCashLedgerService::fetchRecentMovements($pdo, 1, 20);
assertCashLedger($result['total'] === 6, 'each resolved cash lifecycle must count once');
assertCashLedger(count($result['data']) === 6, 'cash ledger must render lifecycle rows without duplicate outflows');

$byId = [];
foreach ($result['data'] as $row) {
    $byId[(int)$row['id']] = $row;
    assertCashLedger(($row['account_type'] ?? '') === 'CASH', 'every ledger row must belong to a CASH account');
}
assertCashLedger(!isset($byId[10]), 'bank-side half of Bank → Cash transfer must be hidden');
assertCashLedger(!isset($byId[15]), 'employee payment outflow must be folded into its receipt source row');
assertCashLedger(!isset($byId[18]), 'cash-to-bank outflow must be folded into employee return source row');

assertCashLedger(isset($byId[11]), 'canonical receipt row must remain visible after employee handoff');
assertCashLedger(($byId[11]['source_label'] ?? '') === 'Расчётный счёт 40702810000000000001', 'lifecycle row keeps original funding source');
assertCashLedger(($byId[11]['display_purpose'] ?? '') === 'Покупка 13.08.2026', 'lifecycle row keeps original purpose');
assertCashLedger(($byId[11]['handoff_recipient_label'] ?? '') === 'Иванов И.И.', 'employee recipient is shown in compact FIO format');
assertCashLedger(($byId[11]['handoff_date'] ?? '') === '2026-08-15', 'lifecycle row attaches factual handoff date');
assertCashLedger(!empty($byId[11]['is_resolved_cash_lifecycle']), 'resolved receipt is marked as lifecycle row');
assertCashLedger(FinanceCashLedgerService::movementLabel($byId[11]) === 'Получено → передано', 'resolved employee receipt has lifecycle label');
assertCashLedger(empty($byId[11]['is_unresolved_cash_source']), 'resolved source must not remain selectable');

assertCashLedger(isset($byId[17]), 'employee return remains canonical incoming cash row');
assertCashLedger(($byId[17]['source_label'] ?? '') === 'Спугов В.В.', 'reverse lifecycle shows compact employee source');
assertCashLedger(($byId[17]['handoff_recipient_label'] ?? '') === 'Расчётный счёт 40702810000000000001', 'reverse lifecycle shows bank as final destination');
assertCashLedger(FinanceCashLedgerService::movementLabel($byId[17]) === 'Получено → передано', 'reverse employee-to-bank lifecycle is one human row');
assertCashLedger(empty($byId[17]['is_unresolved_cash_source']), 'reverse lifecycle is already resolved');

assertCashLedger(!empty($byId[16]['is_unresolved_cash_source']), 'unresolved technical source remains selectable');
assertCashLedger(FinanceCashLedgerService::movementLabel($byId[16]) === 'Получено', 'unresolved technical source has receipt label');
assertCashLedger(($byId[12]['handoff_recipient_label'] ?? '') === 'Петров П.П.', 'ordinary cash payment also uses compact employee FIO');
assertCashLedger(($byId[12]['source_label'] ?? '') === '—', 'ordinary employee payment does not fake a source');
assertCashLedger(FinanceCashLedgerService::movementLabel($byId[12]) === 'Списание', 'ordinary employee payment remains write-off');
assertCashLedger(($byId[13]['source_label'] ?? '') === 'Иванов И.И.', 'employee return uses compact employee FIO');
assertCashLedger(($byId[13]['handoff_recipient_label'] ?? '') === '—', 'ordinary employee return is not displayed as handoff recipient');
assertCashLedger(($byId[14]['source_label'] ?? '') === 'Расчётный счёт 40702810000000000001', 'cash transfer counterpart remains visible');

$page1 = FinanceCashLedgerService::fetchRecentMovements($pdo, 1, 2);
$page2 = FinanceCashLedgerService::fetchRecentMovements($pdo, 2, 2);
$page3 = FinanceCashLedgerService::fetchRecentMovements($pdo, 3, 2);
assertCashLedger($page1['total'] === 6 && $page1['pages'] === 3, 'single-row lifecycle pagination total/pages');
assertCashLedger(count($page1['data']) === 2 && count($page2['data']) === 2 && count($page3['data']) === 2, 'pagination covers all projected lifecycle rows');
$seen = [];
foreach ([$page1,$page2,$page3] as $page) foreach ($page['data'] as $row) $seen[(int)$row['id']] = true;
assertCashLedger(count($seen) === 6 && !isset($seen[15]) && !isset($seen[18]), 'pagination never reintroduces folded lifecycle outflows');

$view = file_get_contents(__DIR__ . '/../app/View/pages/company_finance_cash.php');
assertCashLedger(!str_contains($view, 'Источник / Получатель'), 'mixed source/recipient column removed');
assertCashLedger(str_contains($view, '<th>Получено от</th>'), 'lifecycle source column exists');
assertCashLedger(str_contains($view, '<th class="cash-col-purpose">Назначение</th>'), 'purpose follows source in lifecycle row');
assertCashLedger(str_contains($view, '<th>Сумма</th>'), 'amount column exists in lifecycle row');
assertCashLedger(str_contains($view, '<th class="cash-col-recipient">Передано</th>'), 'handoff recipient is the final lifecycle column');
assertCashLedger(str_contains($view, 'handoff_date'), 'handoff date is shown without a duplicate outflow row');
assertCashLedger(str_contains($view, 'cash-row-unresolved') && str_contains($view, 'var(--color-danger-bg)'), 'unresolved rows use light-red system danger background');
assertCashLedger(str_contains($view, 'accent-color:var(--accent)'), 'native cash checkboxes use ERP brown accent');
assertCashLedger(str_contains($view, 'cash-pagination') && str_contains($view, 'Вперёд →'), 'visible pagination exists');
assertCashLedger(str_contains($view, 'cash-source-select'), 'unresolved source checkbox present');
assertCashLedger(str_contains($view, 'Передать сотруднику'), 'employee batch action present');
assertCashLedger(str_contains($view, 'cash-dispatch-carrier-btn') && str_contains($view, 'disabled'), 'carrier action remains disabled');
assertCashLedger(!str_contains($view, 'Перевод (исходящий)') && !str_contains($view, 'Перевод (входящий)'), 'technical labels hidden');

$controller = file_get_contents(__DIR__ . '/../app/Http/Controllers/Company/FinanceCashActions/index.php');
assertCashLedger(str_contains($controller, "\$_GET['per_page'] ?? 50"), 'cash journal shows up to 50 rows by default');

$runtimeDependencies = file_get_contents(__DIR__ . '/../app/Support/entrypoint_dependencies.php');
assertCashLedger(str_contains($runtimeDependencies, "app/Service/FinanceCashLedgerService.php"), 'runtime loads ledger service');
assertCashLedger(str_contains($runtimeDependencies, "app/Service/FinanceCashResolutionService.php"), 'runtime loads resolution service');

echo "FINANCE_CASH_LEDGER_OK\n";
