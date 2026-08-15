<?php

declare(strict_types=1);

$host = getenv('EMPLOYEE_PAYMENTS_DB_HOST') ?: '127.0.0.1';
$port = getenv('EMPLOYEE_PAYMENTS_DB_PORT') ?: '3306';
$user = getenv('EMPLOYEE_PAYMENTS_DB_USER') ?: 'root';
$password = getenv('EMPLOYEE_PAYMENTS_DB_PASSWORD') ?: 'root';
$dbName = 'erp_navigation_counter_test';

$server = new PDO(
    sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $host, $port),
    $user,
    $password,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
$server->exec("DROP DATABASE IF EXISTS `{$dbName}`");
$server->exec("CREATE DATABASE `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

try {
    $pdo = new PDO(
        sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $dbName),
        $user,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $pdo->exec("CREATE TABLE bank_transactions (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        classification_status VARCHAR(32) NOT NULL,
        is_internal_transfer TINYINT(1) NOT NULL DEFAULT 0
    ) ENGINE=InnoDB");
    $pdo->exec("INSERT INTO bank_transactions (classification_status,is_internal_transfer) VALUES
        ('UNALLOCATED',0),('NEEDS_REVIEW',0),('CLASSIFIED',0),('UNALLOCATED',1)");

    $pdo->exec("CREATE TABLE finance_money_accounts (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        type VARCHAR(16) NOT NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        name VARCHAR(255) NOT NULL
    ) ENGINE=InnoDB");
    $pdo->exec("INSERT INTO finance_money_accounts (id,type,is_active,name) VALUES (1,'CASH',1,'Основная касса')");

    $pdo->exec("CREATE TABLE finance_operations (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        money_account_id INT UNSIGNED NOT NULL,
        status VARCHAR(16) NOT NULL,
        operation_type VARCHAR(16) NOT NULL,
        transfer_direction VARCHAR(8) DEFAULT NULL
    ) ENGINE=InnoDB");
    $pdo->exec("INSERT INTO finance_operations (money_account_id,status,operation_type,transfer_direction)
        VALUES (1,'POSTED','INCOME',NULL)");

    // Deliberately DO NOT create finance_cash_resolutions. The cash counter must fail
    // in isolation while the unrelated bank counter remains available.

    require_once __DIR__ . '/../app/Core/Database.php';
    require_once __DIR__ . '/../app/Support/company_database.php';
    require_once __DIR__ . '/../app/Service/NavigationCounterService.php';

    $config = [
        'database' => [
            'host' => $host,
            'port' => $port,
            'database' => $dbName,
            'username' => $user,
            'password' => $password,
            'charset' => 'utf8mb4',
        ],
    ];
    $company = [
        'id' => 77,
        'status' => 'active',
        'db_identifier' => $dbName,
    ];

    $centralDb = new \App\Core\Database($config['database']);
    $counters = \App\Service\NavigationCounterService::forCompany($config, $centralDb, 77, $company);

    if (($counters['bank_attention'] ?? null) !== 2) {
        throw new RuntimeException('Bank attention badge was suppressed by unrelated missing cash schema.');
    }
    if (($counters['cash_attention'] ?? null) !== 0) {
        throw new RuntimeException('Cash attention must fail closed when finance_cash_resolutions is absent.');
    }

    echo "NAVIGATION_COUNTER_ISOLATION_OK bank=2 cash=0\n";
} finally {
    $server->exec("DROP DATABASE IF EXISTS `{$dbName}`");
}
