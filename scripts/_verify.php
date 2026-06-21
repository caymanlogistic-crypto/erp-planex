<?php
require_once __DIR__ . '/../bootstrap/app.php';
require_once __DIR__ . '/../app/Core/Database.php';
use App\Core\Database;

$db = new Database($config['database']);
$pdo = $db->connection();

echo "=== Password hash check ===\n";
$stmt = $pdo->query('SELECT id, company_id, login, password_hash, role FROM company_users ORDER BY id DESC LIMIT 1');
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if ($row) {
    echo "ID: " . $row['id'] . "\n";
    echo "Company ID: " . $row['company_id'] . "\n";
    echo "Login: " . $row['login'] . "\n";
    echo "Role: " . $row['role'] . "\n";
    echo "Password hash: " . $row['password_hash'] . "\n";
    echo "Is bcrypt: " . (str_starts_with($row['password_hash'], '$2y$') ? 'YES' : 'NO') . "\n";
}

echo "\n=== All company_users ===\n";
$stmt = $pdo->query('SELECT * FROM company_users');
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Count: " . count($rows) . "\n";
foreach ($rows as $r) {
    echo "  ID={$r['id']}, company_id={$r['company_id']}, login={$r['login']}, role={$r['role']}, status={$r['status']}\n";
}
