<?php
function p19_env_file($path) {
    $out = array();
    $lines = @file($path, FILE_IGNORE_NEW_LINES);
    if ($lines === false) return $out;
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || substr($line, 0, 1) === '#' || strpos($line, '=') === false) continue;
        $parts = explode('=', $line, 2);
        $out[trim($parts[0])] = trim($parts[1], " \t\n\r\0\x0B\"'");
    }
    return $out;
}
$e = p19_env_file('/home/s/spugovxsim/planexp/public_html/erpv2/.env');
$host = isset($e['DB_HOST']) ? $e['DB_HOST'] : '127.0.0.1';
$port = isset($e['DB_PORT']) ? $e['DB_PORT'] : '3306';
$db = isset($e['DB_DATABASE']) ? $e['DB_DATABASE'] : 'erp_planex';
$user = isset($e['DB_USERNAME']) ? $e['DB_USERNAME'] : 'root';
$pass = isset($e['DB_PASSWORD']) ? $e['DB_PASSWORD'] : '';
try {
    $pdo = new PDO('mysql:host='.$host.';port='.$port.';dbname='.$db.';charset=utf8mb4', $user, $pass, array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    sort($tables);
    $fp = array();
    foreach ($tables as $table) {
        $escaped = str_replace('`', '``', $table);
        $row = $pdo->query('CHECKSUM TABLE `'.$escaped.'`')->fetch(PDO::FETCH_NUM);
        $fp[] = $table.':'.(isset($row[1]) ? $row[1] : 'NULL');
    }
    echo hash('sha256', implode('|', $fp));
} catch (Exception $ex) {
    exit(2);
}
