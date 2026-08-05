<?php

declare(strict_types=1);

// P16 Recovery Gate: read-only live schema, FK and safe sample inventory.
ini_set('display_errors', '0');
error_reporting(E_ALL);

$sanitize = static function (\Throwable $error): string {
    $message = (string) $error->getMessage();
    $message = preg_replace('#/home/[^/\s]+/#', '~/', $message) ?? $message;
    $message = preg_replace('/(password|passwd|secret|token|key)\s*[=:]\s*[^\s,;]+/i', '$1=[REDACTED]', $message) ?? $message;
    return function_exists('mb_substr') ? mb_substr($message, 0, 500, 'UTF-8') : substr($message, 0, 500);
};

try {
    $config = require getcwd() . '/bootstrap/app.php';
    require_once BASE_PATH . '/app/Support/entrypoint_dependencies.php';
    require_once BASE_PATH . '/app/Support/company_database.php';

    $centralDb = new \App\Core\Database($config['database']);
    $centralPdo = $centralDb->connection();
    $stmt = $centralPdo->prepare(
        'SELECT id, name, status, created_at, db_identifier, storage_path, db_host, db_port, db_username, db_password
         FROM companies WHERE id IN (25, 26, 27) OR name = :test_name ORDER BY id'
    );
    $stmt->execute([':test_name' => 'UIUX TEST EXPEDITOR']);
    $companies = $stmt->fetchAll(\PDO::FETCH_ASSOC);

    $result = [
        'status' => 'PASS',
        'generated_at' => gmdate('c'),
        'php_version' => PHP_VERSION,
        'companies' => [],
        'tenant_27_schema' => [],
        'tenant_27_foreign_keys' => [],
        'tenant_27_safe_samples' => [],
    ];

    foreach ($companies as $company) {
        $usersStmt = $centralPdo->prepare('SELECT COUNT(*) FROM company_users WHERE company_id = ?');
        $usersStmt->execute([(int) $company['id']]);
        $result['companies'][] = [
            'id' => (int) $company['id'],
            'name' => (string) $company['name'],
            'status' => (string) $company['status'],
            'created_at' => (string) ($company['created_at'] ?? ''),
            'central_users_count' => (int) $usersStmt->fetchColumn(),
            'db_fingerprint' => hash('sha256', (string) ($company['db_identifier'] ?? '')),
            'storage_fingerprint' => hash('sha256', (string) ($company['storage_path'] ?? '')),
        ];
    }

    $tenant = null;
    foreach ($companies as $company) {
        if ((int) $company['id'] === 27) {
            $tenant = $company;
            break;
        }
    }
    if (!$tenant || (string) $tenant['name'] !== 'UIUX TEST EXPEDITOR' || (string) $tenant['status'] !== 'active') {
        throw new \RuntimeException('Tenant 27 identity/status mismatch.');
    }

    $localDb = new \App\Core\Database(companyDatabaseConfig($config, $tenant));
    $pdo = $localDb->connection();
    $tables = $pdo->query('SHOW TABLES')->fetchAll(\PDO::FETCH_COLUMN);
    sort($tables);

    foreach ($tables as $table) {
        $table = (string) $table;
        if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) {
            continue;
        }
        $columns = [];
        foreach ($pdo->query('SHOW COLUMNS FROM `' . $table . '`')->fetchAll(\PDO::FETCH_ASSOC) as $column) {
            $columns[] = [
                'name' => (string) $column['Field'],
                'type' => (string) $column['Type'],
                'nullable' => (string) $column['Null'] === 'YES',
                'key' => (string) ($column['Key'] ?? ''),
                'default' => $column['Default'],
                'extra' => (string) ($column['Extra'] ?? ''),
            ];
        }
        $indexes = [];
        foreach ($pdo->query('SHOW INDEX FROM `' . $table . '`')->fetchAll(\PDO::FETCH_ASSOC) as $index) {
            $indexes[] = [
                'name' => (string) $index['Key_name'],
                'unique' => (int) $index['Non_unique'] === 0,
                'sequence' => (int) $index['Seq_in_index'],
                'column' => (string) $index['Column_name'],
            ];
        }
        $result['tenant_27_schema'][$table] = [
            'row_count' => (int) $pdo->query('SELECT COUNT(*) FROM `' . $table . '`')->fetchColumn(),
            'columns' => $columns,
            'indexes' => $indexes,
        ];
    }

    $fkSql = "SELECT TABLE_NAME, COLUMN_NAME, CONSTRAINT_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
              FROM information_schema.KEY_COLUMN_USAGE
              WHERE TABLE_SCHEMA = DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL
              ORDER BY TABLE_NAME, CONSTRAINT_NAME, ORDINAL_POSITION";
    foreach ($pdo->query($fkSql)->fetchAll(\PDO::FETCH_ASSOC) as $fk) {
        $result['tenant_27_foreign_keys'][] = [
            'table' => (string) $fk['TABLE_NAME'],
            'column' => (string) $fk['COLUMN_NAME'],
            'constraint' => (string) $fk['CONSTRAINT_NAME'],
            'referenced_table' => (string) $fk['REFERENCED_TABLE_NAME'],
            'referenced_column' => (string) $fk['REFERENCED_COLUMN_NAME'],
        ];
    }

    $sampleQueries = [
        'users' => 'SELECT id, full_name, login, role_code, status FROM users ORDER BY id LIMIT 20',
        'cargo_types' => 'SELECT id, name, status FROM cargo_types WHERE deleted_at IS NULL ORDER BY id LIMIT 20',
        'clients' => 'SELECT id, name, status, created_by_user_id FROM clients ORDER BY id LIMIT 20',
        'contractors' => 'SELECT id, name, status, created_by_user_id FROM contractors ORDER BY id LIMIT 20',
        'drivers' => 'SELECT id, full_name, phone, status, created_by_user_id FROM drivers ORDER BY id LIMIT 20',
        'vehicle_units' => 'SELECT id, plate_number, brand, model, status FROM vehicle_units ORDER BY id LIMIT 20',
        'vehicle_sets' => 'SELECT id, set_type, primary_vehicle_unit_id, secondary_vehicle_unit_id, status FROM vehicle_sets ORDER BY id LIMIT 20',
        'driver_vehicle_blocks' => 'SELECT id, driver_id, vehicle_set_id, status FROM driver_vehicle_blocks ORDER BY id LIMIT 20',
        'crews' => 'SELECT id, contractor_id, vehicle_id, driver_id, driver_vehicle_block_id, status FROM crews ORDER BY id LIMIT 20',
        'linear_routes' => 'SELECT id, route_type, client_id, carrier_contractor_id, route_executor_id, cargo_type_id, status, deleted_at FROM linear_routes ORDER BY id LIMIT 20',
        'linear_route_financial_terms' => 'SELECT id, linear_route_id, party_role, amount, payment_type, payment_due_type, payment_due_days, payment_due_days_kind FROM linear_route_financial_terms ORDER BY id LIMIT 30',
        'linear_route_payments' => 'SELECT id, linear_route_id, party_role, side, amount, payment_type, payment_due_type, condition_type, payment_status, paid_amount, deleted_at FROM linear_route_payments ORDER BY id DESC LIMIT 40',
        'finance_dds_categories' => 'SELECT id, code, name, direction, is_active FROM finance_dds_categories ORDER BY id LIMIT 40',
        'finance_invoices' => 'SELECT id, direction, number, amount, status, counterparty_entity_type, counterparty_entity_id, planned_payment_date FROM finance_invoices ORDER BY id LIMIT 20',
    ];
    foreach ($sampleQueries as $name => $sql) {
        if (isset($result['tenant_27_schema'][$name])) {
            $result['tenant_27_safe_samples'][$name] = $pdo->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
        }
    }

    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), PHP_EOL;
} catch (\Throwable $error) {
    echo json_encode([
        'status' => 'FAIL',
        'generated_at' => gmdate('c'),
        'error_class' => get_class($error),
        'error_message' => $sanitize($error),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), PHP_EOL;
    exit(1);
}
