<?php

declare(strict_types=1);

// Read-only production diagnostic. Run with cwd at deployed ERPv2 root.
$config = require getcwd() . '/bootstrap/app.php';
require_once getcwd() . '/app/Core/Database.php';
require_once getcwd() . '/app/Support/company_database.php';

$central = new \App\Core\Database($config['database']);
$company = $central->fetch("SELECT * FROM companies WHERE status='active' AND name LIKE '%ПЛАНЭКС%' ORDER BY id LIMIT 1");
if (!$company) {
    fwrite(STDERR, "P105_DIAG_NO_COMPANY\n");
    exit(2);
}
$local = new \App\Core\Database(companyDatabaseConfig($config, $company));
$pdo = $local->connection();

function rows(PDO $pdo, string $sql, array $params = []): array {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$out = [
    'company_id' => (int)$company['id'],
    'database_name' => (string)$pdo->query('SELECT DATABASE()')->fetchColumn(),
    'operation_159' => rows($pdo, "SELECT id, operation_type, status, source, money_account_id, operation_date, amount, currency, counterparty_name, purpose, cancelled_at, cancelled_by_user_id, cancelled_by_role, cancellation_reason, updated_at FROM finance_operations WHERE id=159"),
    'active_allocations_159' => rows($pdo, "SELECT id, operation_id, invoice_id, linear_route_id, linear_route_payment_id, amount, cancelled_at, cancel_reason FROM finance_operation_allocations WHERE operation_id=159 AND cancelled_at IS NULL"),
    'all_allocations_159' => rows($pdo, "SELECT id, operation_id, invoice_id, linear_route_id, linear_route_payment_id, amount, cancelled_at, cancelled_by_user_id, cancelled_by_role, cancel_reason FROM finance_operation_allocations WHERE operation_id=159 ORDER BY id"),
    'operation_columns' => rows($pdo, "SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, EXTRA FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='finance_operations' AND COLUMN_NAME IN ('status','cancelled_at','cancelled_by_user_id','cancelled_by_role','cancellation_reason','updated_at') ORDER BY ORDINAL_POSITION"),
    'audit_columns' => rows($pdo, "SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, EXTRA FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='finance_audit_log' ORDER BY ORDINAL_POSITION"),
    'operation_foreign_keys' => rows($pdo, "SELECT CONSTRAINT_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='finance_operations' AND REFERENCED_TABLE_NAME IS NOT NULL ORDER BY CONSTRAINT_NAME, ORDINAL_POSITION"),
    'audit_foreign_keys' => rows($pdo, "SELECT CONSTRAINT_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='finance_audit_log' AND REFERENCED_TABLE_NAME IS NOT NULL ORDER BY CONSTRAINT_NAME, ORDINAL_POSITION"),
    'operation_triggers' => rows($pdo, "SELECT TRIGGER_NAME, EVENT_MANIPULATION, ACTION_TIMING FROM INFORMATION_SCHEMA.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE() AND EVENT_OBJECT_TABLE='finance_operations'"),
    'audit_tail' => rows($pdo, "SELECT id, entity_type, entity_id, action, created_by_user_id, created_by_role, created_at FROM finance_audit_log WHERE entity_type IN ('finance_operation','finance_allocation') AND (entity_id=159 OR created_at >= NOW() - INTERVAL 2 HOUR) ORDER BY id DESC LIMIT 20"),
];

echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), "\n";
