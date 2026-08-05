<?php

declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(E_ALL);

try {
    $config = require getcwd() . '/bootstrap/app.php';
    require_once BASE_PATH . '/app/Support/entrypoint_dependencies.php';
    require_once BASE_PATH . '/app/Support/company_database.php';

    $centralDb = new \App\Core\Database($config['database']);
    $centralPdo = $centralDb->connection();

    $stmt = $centralPdo->prepare(
        'SELECT id, name, inn, status, created_at, db_identifier, storage_path
         FROM companies
         WHERE name = :test_name OR id IN (25, 27)
         ORDER BY id ASC'
    );
    $stmt->execute([':test_name' => 'UIUX TEST EXPEDITOR']);
    $companies = $stmt->fetchAll(\PDO::FETCH_ASSOC);

    $result = [
        'status' => 'PASS',
        'generated_at' => gmdate('c'),
        'companies' => [],
    ];

    $businessTableCandidates = [
        'users',
        'clients',
        'client_contacts',
        'contractors',
        'contractor_contacts',
        'drivers',
        'driver_phones',
        'vehicle_units',
        'vehicle_sets',
        'driver_vehicle_blocks',
        'crews',
        'route_executors',
        'linear_routes',
        'routes',
        'trips',
        'route_applications',
        'applications',
        'documents',
        'invoices',
        'finance_invoices',
        'finance_operations',
        'cash_operations',
        'finance_cash_operations',
        'bank_operations',
        'finance_bank_operations',
        'payment_allocations',
        'finance_allocations',
        'bank_accounts',
        'dds_categories',
        'matching_rules',
        'responsible_assignments',
        'entity_access_grants',
    ];

    foreach ($companies as $company) {
        $centralUsers = $centralPdo->prepare('SELECT COUNT(*) FROM company_users WHERE company_id = ?');
        $centralUsers->execute([(int) $company['id']]);

        $entry = [
            'id' => (int) $company['id'],
            'name' => (string) $company['name'],
            'inn' => (string) ($company['inn'] ?? ''),
            'status' => (string) ($company['status'] ?? ''),
            'created_at' => (string) ($company['created_at'] ?? ''),
            'central_users_count' => (int) $centralUsers->fetchColumn(),
            'db_identifier_fingerprint' => hash('sha256', (string) ($company['db_identifier'] ?? '')),
            'storage_path_fingerprint' => hash('sha256', (string) ($company['storage_path'] ?? '')),
            'local_db_available' => false,
            'table_counts' => [],
            'business_counts' => [],
            'business_total' => 0,
            'inventory_error' => null,
        ];

        if (!empty($company['db_identifier'])) {
            try {
                $localConfig = companyDatabaseConfig($config, $company);
                $localDb = new \App\Core\Database($localConfig);
                $localPdo = $localDb->connection();
                $entry['local_db_available'] = true;

                $tables = $localPdo->query('SHOW TABLES')->fetchAll(\PDO::FETCH_COLUMN);
                foreach ($tables as $table) {
                    $table = (string) $table;
                    if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) {
                        continue;
                    }
                    $count = (int) $localPdo->query('SELECT COUNT(*) FROM `' . $table . '`')->fetchColumn();
                    $entry['table_counts'][$table] = $count;
                }
                ksort($entry['table_counts']);

                foreach ($businessTableCandidates as $table) {
                    if (array_key_exists($table, $entry['table_counts'])) {
                        $entry['business_counts'][$table] = (int) $entry['table_counts'][$table];
                    }
                }
                $entry['business_total'] = array_sum($entry['business_counts']);
            } catch (\Throwable $error) {
                $entry['inventory_error'] = get_class($error);
                $result['status'] = 'FAIL';
            }
        }

        $result['companies'][] = $entry;
    }

    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), PHP_EOL;
} catch (\Throwable $error) {
    echo json_encode([
        'status' => 'FAIL',
        'generated_at' => gmdate('c'),
        'error_class' => get_class($error),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), PHP_EOL;
    exit(1);
}
