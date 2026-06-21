<?php
require_once __DIR__ . '/../bootstrap/app.php';
require_once __DIR__ . '/../app/Core/Database.php';
use App\Core\Database;

$db = new Database($config['database']);
$pdo = $db->connection();
$stmt = $pdo->query('DESCRIBE company_users');
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $row['Field'] . ' | ' . $row['Type'] . ' | ' . $row['Null'] . ' | ' . $row['Key'] . PHP_EOL;
}
