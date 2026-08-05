<?php

declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(E_ALL);

try {
    $config = require getcwd() . '/bootstrap/app.php';
    require_once BASE_PATH . '/app/Support/entrypoint_dependencies.php';
    require_once BASE_PATH . '/app/Support/company_database.php';

    $centralPdo = (new \App\Core\Database($config['database']))->connection();
    $stmt = $centralPdo->prepare('SELECT * FROM companies WHERE id = ?');
    $stmt->execute([27]);
    $company = $stmt->fetch(\PDO::FETCH_ASSOC);
    if (!$company) {
        throw new \RuntimeException('test tenant unavailable');
    }

    $localPdo = (new \App\Core\Database(companyDatabaseConfig($config, $company)))->connection();
    $routeStmt = $localPdo->prepare(
        "SELECT id, cargo_type_name, client_id, carrier_contractor_id, route_executor_id,
                customer_cost, carrier_cost, customer_payment_due_type,
                carrier_payment_due_type, deleted_at
         FROM linear_routes
         WHERE cargo_type_name = ?
         ORDER BY id DESC
         LIMIT 1"
    );
    $routeStmt->execute(['P15 DELETE TEMP рейс']);
    $route = $routeStmt->fetch(\PDO::FETCH_ASSOC);

    $result = [
        'status' => 'FAIL',
        'company_id' => 27,
        'route' => null,
        'relations' => [],
    ];

    if ($route) {
        $checks = [];
        foreach ([
            'client' => ['clients', (int) $route['client_id']],
            'contractor' => ['contractors', (int) $route['carrier_contractor_id']],
            'route_executor' => ['route_executors', (int) $route['route_executor_id']],
        ] as $key => [$table, $id]) {
            if (!preg_match('/^[a-z_]+$/', $table)) {
                throw new \RuntimeException('invalid table');
            }
            $q = $localPdo->prepare("SELECT COUNT(*) FROM `{$table}` WHERE id = ? AND deleted_at IS NULL");
            $q->execute([$id]);
            $checks[$key] = (int) $q->fetchColumn() === 1;
        }

        $result['route'] = [
            'id' => (int) $route['id'],
            'name' => (string) $route['cargo_type_name'],
            'deleted_at_is_null' => $route['deleted_at'] === null,
            'customer_cost' => (float) $route['customer_cost'],
            'carrier_cost' => (float) $route['carrier_cost'],
            'customer_payment_due_type_present' => trim((string) $route['customer_payment_due_type']) !== '',
            'carrier_payment_due_type_present' => trim((string) $route['carrier_payment_due_type']) !== '',
        ];
        $result['relations'] = $checks;
        $result['status'] = (
            $result['route']['deleted_at_is_null']
            && $result['route']['customer_payment_due_type_present']
            && $result['route']['carrier_payment_due_type_present']
            && !in_array(false, $checks, true)
        ) ? 'PASS' : 'FAIL';
    }

    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), PHP_EOL;
    exit($result['status'] === 'PASS' ? 0 : 1);
} catch (\Throwable $error) {
    echo json_encode([
        'status' => 'FAIL',
        'error_class' => get_class($error),
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), PHP_EOL;
    exit(1);
}
