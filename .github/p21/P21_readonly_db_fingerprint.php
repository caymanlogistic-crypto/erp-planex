<?php

declare(strict_types=1);

function p21_env_file(string $path): array
{
    $out = [];
    $lines = @file($path, FILE_IGNORE_NEW_LINES);
    if ($lines === false) {
        return $out;
    }
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $out[trim($key)] = trim($value, " \t\n\r\0\x0B\"'");
    }
    return $out;
}

$env = p21_env_file('/home/s/spugovxsim/planexp/public_html/erpv2/.env');
$host = $env['DB_HOST'] ?? '127.0.0.1';
$port = $env['DB_PORT'] ?? '3306';
$db = $env['DB_DATABASE'] ?? 'erp_planex';
$user = $env['DB_USERNAME'] ?? 'root';
$pass = $env['DB_PASSWORD'] ?? '';

try {
    $pdo = new PDO(
        'mysql:host=' . $host . ';port=' . $port . ';dbname=' . $db . ';charset=utf8mb4',
        $user,
        $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    sort($tables);
    $fingerprint = [];
    foreach ($tables as $table) {
        $escaped = str_replace('`', '``', (string) $table);
        $row = $pdo->query('CHECKSUM TABLE `' . $escaped . '`')->fetch(PDO::FETCH_NUM);
        $fingerprint[] = $table . ':' . ($row[1] ?? 'NULL');
    }
    echo hash('sha256', implode('|', $fingerprint));
} catch (Throwable $e) {
    exit(2);
}
