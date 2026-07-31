<?php
requireRole('company_owner');

$companyId = (int)(getSessionCompanyId() ?? 0);

if ($companyId <= 0) {
    header('Location: /company/logists');
    exit;
}

try {
    $pdo = $db->connection();
    $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
    $stmt->execute([$companyId]);
    $company = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$company || $company['status'] !== 'active') {
        header('Location: /company/logists');
        exit;
    }

    $dbIdentifier = $company['db_identifier'];

    try {
        $tempPdo = new PDO(
            sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $config['database']['host'] ?? '127.0.0.1', $config['database']['port'] ?? '3306'),
            $config['database']['username'] ?? 'root',
            $config['database']['password'] ?? ''
        );
        $tempPdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbIdentifier}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    } catch (\Exception $e) {
    }
    $tempPdo = null;

    $localDbConfig = companyDatabaseConfig($config, $company);
    $localDb = new \App\Core\Database($localDbConfig);
    $localPdo = $localDb->connection();
    applyLocalMigrations($localPdo);

    try {
        $localPdo->query("SELECT 1 FROM users LIMIT 1")->fetch();
    } catch (\Exception $e) {
        $migrationSql = file_get_contents(base_path('database/migrations-local/001_create_company_users.sql'));
        $localPdo->exec($migrationSql);
    }

    $userStmt = $localPdo->prepare('SELECT * FROM users WHERE id = ?');
    $userStmt->execute([(int)$id]);
    $userForDelete = $userStmt->fetch(PDO::FETCH_ASSOC);

    $uid = (int)($_SESSION['user_id'] ?? 0);
    $rl = (string)($_SESSION['role_code'] ?? '');
    $update = $localPdo->prepare("UPDATE users SET deleted_at = NOW(), deleted_by_user_id = ?, deleted_by_role = ? WHERE id = ?");
    $update->execute([$uid, $rl, (int)$id]);

    if ($userForDelete) {
        $displayName = $userForDelete['full_name'] ?? '#' . $id;
        $snapshot = json_encode($userForDelete, JSON_UNESCAPED_UNICODE);
        $un = $_SESSION['user_name'] ?? '';
        try {
            $cp = $db->connection();
            \App\Service\AuditService::recordDeletion($cp, $company, 'user', (int)$id, 'users', $displayName, $uid, $rl, $un, null, $snapshot);
        } catch (\Exception $auditEx) {
            error_log('Audit failed for user ' . $id . ': ' . $auditEx->getMessage());
        }
    }

    header('Location: /company/logists');
    exit;
} catch (\Exception $e) {
    header('Location: /company/logists');
    exit;
}
