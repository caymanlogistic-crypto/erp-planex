<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/Service/FinanceAuditLogService.php';
require_once __DIR__ . '/../app/Service/FinanceCashService.php';
require_once __DIR__ . '/../app/Service/FinanceEmployeePaymentService.php';
require_once __DIR__ . '/../app/Service/FinanceCashResolutionService.php';
require_once __DIR__ . '/../app/Service/FinanceEmployeeTransferService.php';

use App\Service\FinanceEmployeePaymentService;
use App\Service\FinanceEmployeeTransferService;

function transferOk(bool $value, string $message): void
{
    if (!$value) {
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
foreach (['finance_cash_resolutions', 'finance_employee_movements', 'finance_audit_log', 'finance_operations', 'finance_money_accounts', 'company_users', 'users'] as $table) {
    $pdo->exec("DROP TABLE IF EXISTS `{$table}`");
}
$pdo->exec('SET FOREIGN_KEY_CHECKS=1');

$pdo->exec("CREATE TABLE company_users(
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    full_name VARCHAR(255) NOT NULL,
    login VARCHAR(100) NOT NULL,
    role VARCHAR(50) NOT NULL,
    status VARCHAR(20) NOT NULL
) ENGINE=InnoDB");

$pdo->exec("CREATE TABLE users(
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(255) NOT NULL,
    login VARCHAR(255) NOT NULL,
    role_code VARCHAR(30) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    deleted_at DATETIME NULL
) ENGINE=InnoDB");

$pdo->exec("CREATE TABLE finance_money_accounts(
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
) ENGINE=InnoDB");

$pdo->exec("CREATE TABLE finance_operations(
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    operation_type VARCHAR(20) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'POSTED',
    source VARCHAR(30) NOT NULL,
    money_account_id INT UNSIGNED NOT NULL,
    transfer_account_id INT UNSIGNED NULL,
    transfer_group_id VARCHAR(64) NULL,
    transfer_direction VARCHAR(10) NULL,
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
    cancelled_at DATETIME NULL
) ENGINE=InnoDB");

$pdo->exec("CREATE TABLE finance_employee_movements(
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employee_user_id INT UNSIGNED NULL,
    employee_identity_type VARCHAR(20) NOT NULL DEFAULT 'TENANT_USER',
    employee_identity_id INT UNSIGNED NULL,
    employee_name_snapshot VARCHAR(255) NULL,
    employee_role_snapshot VARCHAR(50) NULL,
    movement_type VARCHAR(20) NOT NULL,
    source_type VARCHAR(20) NOT NULL,
    finance_operation_id INT UNSIGNED NOT NULL,
    bank_transaction_id INT UNSIGNED NULL,
    note VARCHAR(1000) NULL,
    created_by_user_id INT UNSIGNED NULL,
    created_by_role VARCHAR(20) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_fem_finance_operation(finance_operation_id),
    UNIQUE KEY uk_fem_bank_transaction(bank_transaction_id)
) ENGINE=InnoDB");

$pdo->exec("CREATE TABLE finance_cash_resolutions(
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

$pdo->exec("CREATE TABLE finance_audit_log(
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    entity_type VARCHAR(64) NOT NULL,
    entity_id INT UNSIGNED NOT NULL,
    action VARCHAR(64) NOT NULL,
    old_values JSON NULL,
    new_values JSON NULL,
    created_by_user_id INT UNSIGNED NOT NULL,
    created_by_role VARCHAR(64) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB");

$pdo->exec("INSERT INTO company_users(id,company_id,full_name,login,role,status)
            VALUES(100,1,'Руководитель','owner','company_owner','active')");
$pdo->exec("INSERT INTO users(id,full_name,login,role_code,status,deleted_at) VALUES
            (2,'Иванов Иван Иванович','ivanov','logist','active',NULL),
            (3,'Петров Пётр Петрович','petrov','logist','active',NULL),
            (4,'Сидорова Анна Сергеевна','sidorova','logist','active',NULL)");
$pdo->exec("INSERT INTO finance_money_accounts(id,type,name,currency,opening_balance,is_active)
            VALUES(1,'CASH','Основная касса','RUR',0.00,1)");

$result = FinanceEmployeeTransferService::transfer(
    $pdo,
    $pdo,
    1,
    [
        'source_employee_ref' => 'TENANT_USER:2',
        'target_employee_ref' => 'TENANT_USER:3',
        'amount' => '500,00',
        'operation_date' => '2026-08-16',
        'purpose' => 'Передача подотчётных средств',
    ],
    ['id' => 100, 'role' => 'company_owner']
);

transferOk($result['amount'] === '500.00', 'transfer amount normalized exactly');

$senderLedger = FinanceEmployeePaymentService::fetchEmployeeLedger($pdo, 'TENANT_USER:2');
$recipientLedger = FinanceEmployeePaymentService::fetchEmployeeLedger($pdo, 'TENANT_USER:3');
transferOk(count($senderLedger) === 1, 'sender receives exactly one settlement movement');
transferOk(count($recipientLedger) === 1, 'recipient receives exactly one settlement movement');
transferOk($senderLedger[0]['movement_type'] === 'RETURN', 'sender movement is RETURN');
transferOk($recipientLedger[0]['movement_type'] === 'PAYMENT', 'recipient movement is PAYMENT');
transferOk($senderLedger[0]['running_balance'] === '-500.00', 'sender is allowed to become negative');
transferOk($recipientLedger[0]['running_balance'] === '500.00', 'recipient balance increases by transfer amount');

$ops = $pdo->query("SELECT id, operation_type, source, transfer_group_id, transfer_direction, amount
                     FROM finance_operations ORDER BY id")->fetchAll();
transferOk(count($ops) === 2, 'exactly two technical cash operations created');
transferOk($ops[0]['operation_type'] === 'TRANSFER' && $ops[1]['operation_type'] === 'TRANSFER', 'both legs are technical transfers');
transferOk($ops[0]['source'] === 'TRANSFER' && $ops[1]['source'] === 'TRANSFER', 'both legs use transfer source');
transferOk($ops[0]['transfer_direction'] === 'in', 'sender to main cash is transfer in');
transferOk($ops[1]['transfer_direction'] === 'out', 'main cash to recipient is transfer out');
transferOk($ops[0]['transfer_group_id'] !== '' && $ops[0]['transfer_group_id'] === $ops[1]['transfer_group_id'], 'both legs share one transfer group');

$economicCount = (int)$pdo->query("SELECT COUNT(*) FROM finance_operations WHERE operation_type IN ('INCOME','EXPENSE')")->fetchColumn();
transferOk($economicCount === 0, 'employee-to-employee transfer does not enter economic DDS');

$cashBalance = (string)$pdo->query("SELECT CAST(
    COALESCE(SUM(CASE WHEN operation_type='TRANSFER' AND transfer_direction='in' THEN amount ELSE 0 END),0)
    - COALESCE(SUM(CASE WHEN operation_type='TRANSFER' AND transfer_direction='out' THEN amount ELSE 0 END),0)
    AS DECIMAL(15,2))
    FROM finance_operations WHERE money_account_id=1 AND status='POSTED'")->fetchColumn();
transferOk($cashBalance === '0.00', 'main cash net balance remains zero');

$resolution = $pdo->query("SELECT * FROM finance_cash_resolutions")->fetch();
transferOk((bool)$resolution, 'cash lifecycle resolution created');
transferOk($resolution['resolution_type'] === 'EMPLOYEE', 'resolution points to employee');
transferOk($resolution['target_identity_type'] === 'TENANT_USER' && (int)$resolution['target_identity_id'] === 3, 'resolution target is recipient');
transferOk((int)$resolution['source_finance_operation_id'] === (int)$result['source_finance_operation_id'], 'resolution source is sender return leg');
transferOk((int)$resolution['outflow_finance_operation_id'] === (int)$result['target_finance_operation_id'], 'resolution outflow is recipient payment leg');
transferOk((int)$resolution['employee_movement_id'] === (int)$result['target_movement_id'], 'resolution links recipient employee movement');

$auditCount = (int)$pdo->query("SELECT COUNT(*) FROM finance_audit_log WHERE action='employee_transfer'")->fetchColumn();
transferOk($auditCount === 2, 'both transfer legs are explicitly audited');

$decorated = FinanceEmployeeTransferService::decorateLedger($pdo, $senderLedger);
transferOk(count($decorated) === 1, 'active transfer remains visible in employee report');
transferOk(isset($decorated[0]['employee_transfer']), 'active employee transfer gets edit metadata');
transferOk($decorated[0]['employee_transfer']['transfer_group_id'] === $result['transfer_group_id'], 'edit metadata points to exact transfer group');
transferOk($decorated[0]['employee_transfer']['source_employee_ref'] === 'TENANT_USER:2', 'edit metadata keeps sender');
transferOk($decorated[0]['employee_transfer']['target_employee_ref'] === 'TENANT_USER:3', 'edit metadata keeps recipient');
transferOk($decorated[0]['employee_transfer']['amount'] === '500.00', 'edit metadata keeps exact amount');

$updated = FinanceEmployeeTransferService::updateTransfer(
    $pdo,
    $pdo,
    1,
    $result['transfer_group_id'],
    [
        'source_employee_ref' => 'TENANT_USER:2',
        'target_employee_ref' => 'TENANT_USER:4',
        'amount' => '750,25',
        'operation_date' => '2026-08-17',
        'purpose' => 'Передача на закупку материалов',
        'comment' => 'Исправлено руководителем',
    ],
    ['id' => 100, 'role' => 'company_owner']
);
transferOk($updated['amount'] === '750.25', 'edited amount normalized exactly');
transferOk($updated['transfer_group_id'] === $result['transfer_group_id'], 'editing preserves transfer group identity');
transferOk($updated['target_employee']['ref'] === 'TENANT_USER:4', 'editing can change recipient');

$senderLedgerAfterEdit = FinanceEmployeePaymentService::fetchEmployeeLedger($pdo, 'TENANT_USER:2');
$oldRecipientLedgerAfterEdit = FinanceEmployeePaymentService::fetchEmployeeLedger($pdo, 'TENANT_USER:3');
$newRecipientLedgerAfterEdit = FinanceEmployeePaymentService::fetchEmployeeLedger($pdo, 'TENANT_USER:4');
transferOk(count($senderLedgerAfterEdit) === 1 && $senderLedgerAfterEdit[0]['running_balance'] === '-750.25', 'sender balance recalculates after edit');
transferOk(count($oldRecipientLedgerAfterEdit) === 0, 'old recipient no longer owns edited transfer');
transferOk(count($newRecipientLedgerAfterEdit) === 1 && $newRecipientLedgerAfterEdit[0]['running_balance'] === '750.25', 'new recipient receives edited transfer');
transferOk($newRecipientLedgerAfterEdit[0]['operation_date'] === '2026-08-17', 'edited date is reflected in ledger');
transferOk($newRecipientLedgerAfterEdit[0]['purpose'] === 'Передача на закупку материалов', 'edited basis is reflected in ledger');
transferOk(str_contains((string)$newRecipientLedgerAfterEdit[0]['comment'], 'Исправлено руководителем'), 'edited user comment is preserved');

$editedOps = $pdo->query("SELECT operation_type, source, status, operation_date, amount, purpose, transfer_group_id, transfer_direction
                           FROM finance_operations ORDER BY id")->fetchAll();
transferOk(count($editedOps) === 2, 'editing does not duplicate technical operations');
foreach ($editedOps as $editedOp) {
    transferOk($editedOp['operation_type'] === 'TRANSFER' && $editedOp['source'] === 'TRANSFER', 'edited legs remain technical transfers');
    transferOk($editedOp['status'] === 'POSTED', 'edited legs remain posted');
    transferOk($editedOp['operation_date'] === '2026-08-17', 'both legs get edited date');
    transferOk((string)$editedOp['amount'] === '750.25', 'both legs get edited amount');
    transferOk($editedOp['purpose'] === 'Передача на закупку материалов', 'both legs get edited basis');
    transferOk($editedOp['transfer_group_id'] === $result['transfer_group_id'], 'both legs retain transfer group');
}

$resolutionAfterEdit = $pdo->query("SELECT * FROM finance_cash_resolutions")->fetch();
transferOk($resolutionAfterEdit['target_identity_type'] === 'TENANT_USER' && (int)$resolutionAfterEdit['target_identity_id'] === 4, 'resolution follows edited recipient');
transferOk($resolutionAfterEdit['target_name_snapshot'] === 'Сидорова Анна Сергеевна', 'resolution recipient snapshot updates');
$editAuditCount = (int)$pdo->query("SELECT COUNT(*) FROM finance_audit_log WHERE action='employee_transfer_edit'")->fetchColumn();
transferOk($editAuditCount === 2, 'both technical legs audit transfer edit');

$cashBalanceAfterEdit = (string)$pdo->query("SELECT CAST(
    COALESCE(SUM(CASE WHEN operation_type='TRANSFER' AND transfer_direction='in' THEN amount ELSE 0 END),0)
    - COALESCE(SUM(CASE WHEN operation_type='TRANSFER' AND transfer_direction='out' THEN amount ELSE 0 END),0)
    AS DECIMAL(15,2))
    FROM finance_operations WHERE money_account_id=1 AND status='POSTED'")->fetchColumn();
transferOk($cashBalanceAfterEdit === '0.00', 'main cash remains zero after editing transfer');
$economicCountAfterEdit = (int)$pdo->query("SELECT COUNT(*) FROM finance_operations WHERE operation_type IN ('INCOME','EXPENSE')")->fetchColumn();
transferOk($economicCountAfterEdit === 0, 'editing transfer cannot introduce DDS income or expense');

$deleted = FinanceEmployeeTransferService::deleteTransfer(
    $pdo,
    $result['transfer_group_id'],
    ['id' => 100, 'role' => 'company_owner']
);
transferOk($deleted['transfer_group_id'] === $result['transfer_group_id'], 'delete targets exact transfer group');

$cancelledCount = (int)$pdo->query("SELECT COUNT(*) FROM finance_operations WHERE status='CANCELLED'")->fetchColumn();
transferOk($cancelledCount === 2, 'delete atomically cancels both technical legs');
$postedCount = (int)$pdo->query("SELECT COUNT(*) FROM finance_operations WHERE status='POSTED'")->fetchColumn();
transferOk($postedCount === 0, 'deleted transfer has no posted legs');
$deleteAuditCount = (int)$pdo->query("SELECT COUNT(*) FROM finance_audit_log WHERE action='employee_transfer_delete'")->fetchColumn();
transferOk($deleteAuditCount === 2, 'both technical legs audit transfer deletion');

$senderRawAfterDelete = FinanceEmployeePaymentService::fetchEmployeeLedger($pdo, 'TENANT_USER:2');
transferOk(count($senderRawAfterDelete) === 1 && $senderRawAfterDelete[0]['status'] === 'CANCELLED', 'soft deletion preserves raw audit row');
$senderVisibleAfterDelete = FinanceEmployeeTransferService::decorateLedger($pdo, $senderRawAfterDelete);
$newRecipientVisibleAfterDelete = FinanceEmployeeTransferService::decorateLedger(
    $pdo,
    FinanceEmployeePaymentService::fetchEmployeeLedger($pdo, 'TENANT_USER:4')
);
transferOk($senderVisibleAfterDelete === [], 'deleted transfer disappears from sender report');
transferOk($newRecipientVisibleAfterDelete === [], 'deleted transfer disappears from recipient report');

$cashBalanceAfterDelete = (string)$pdo->query("SELECT CAST(
    COALESCE(SUM(CASE WHEN operation_type='TRANSFER' AND transfer_direction='in' THEN amount ELSE 0 END),0)
    - COALESCE(SUM(CASE WHEN operation_type='TRANSFER' AND transfer_direction='out' THEN amount ELSE 0 END),0)
    AS DECIMAL(15,2))
    FROM finance_operations WHERE money_account_id=1 AND status='POSTED'")->fetchColumn();
transferOk($cashBalanceAfterDelete === '0.00', 'main cash remains zero after deletion');
$economicCountAfterDelete = (int)$pdo->query("SELECT COUNT(*) FROM finance_operations WHERE operation_type IN ('INCOME','EXPENSE')")->fetchColumn();
transferOk($economicCountAfterDelete === 0, 'deletion cannot introduce DDS income or expense');

echo "FINANCE_EMPLOYEE_TRANSFER_BEHAVIOR_OK\n";
