<?php

declare(strict_types=1);

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
    if (!$tenant || (string) $tenant['name'] !== 'UIUX TEST EXPEDITOR') {
        throw new \RuntimeException('Tenant 27 identity mismatch.');
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
