<?php

use App\Service\LinearRouteService;

requireRole(['company_owner', 'senior_logist', 'logist']);
header('Content-Type: application/json; charset=utf-8');

$companyId = (int) (getSessionCompanyId() ?? 0);
if ($companyId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Компания не найдена.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $pdo = $db->connection();
    $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
    $stmt->execute([$companyId]);
    $company = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    if (!$company || ($company['status'] ?? '') !== 'active') {
        echo json_encode(['success' => false, 'error' => 'Компания не найдена или не активна.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $localDbConfig = companyDatabaseConfig($config, $company);
    $localDb = new \App\Core\Database($localDbConfig);
    $localPdo = $localDb->connection();
    applyLocalMigrations($localPdo);

    $route = LinearRouteService::fetchRouteById($localPdo, (int) $id);
    if (!$route) {
        echo json_encode(['success' => false, 'error' => 'Рейс не найден.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $sessionUser = [
        'user_id' => (int) ($_SESSION['user_id'] ?? 0),
        'role_code' => (string) ($_SESSION['role_code'] ?? ''),
    ];
    if (!LinearRouteService::canDeleteRoute($route, $localPdo, $sessionUser)) {
        echo json_encode(['success' => false, 'error' => 'У вас нет права удалить эту запись.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $userId = (int) ($_SESSION['user_id'] ?? 0);
    $roleCode = (string) ($_SESSION['role_code'] ?? '');

    $localPdo->beginTransaction();
    $localPdo->prepare("UPDATE linear_routes SET deleted_at = NOW(), deleted_by_user_id = ?, deleted_by_role = ? WHERE id = ? AND deleted_at IS NULL")
        ->execute([$userId, $roleCode, (int) $id]);
    $localPdo->prepare("UPDATE linear_route_financial_terms SET deleted_at = NOW(), deleted_by_user_id = ?, deleted_by_role = ? WHERE linear_route_id = ? AND deleted_at IS NULL")
        ->execute([$userId, $roleCode, (int) $id]);
    try {
        $localPdo->prepare("UPDATE linear_route_payments SET deleted_at = NOW(), deleted_by_user_id = ?, deleted_by_role = ? WHERE linear_route_id = ? AND deleted_at IS NULL")
            ->execute([$userId, $roleCode, (int) $id]);
    } catch (\Throwable $e) {
    }
    try {
        $localPdo->prepare("UPDATE linear_route_principals SET deleted_at = NOW(), deleted_by_user_id = ?, deleted_by_role = ? WHERE linear_route_id = ? AND deleted_at IS NULL")
            ->execute([$userId, $roleCode, (int) $id]);
    } catch (\Throwable $e) {
    }
    $localPdo->prepare(
        "UPDATE documents
            SET deleted_at = NOW(),
                deleted_by_user_id = ?,
                deleted_by_role = ?,
                delete_comment = 'Linear route deleted'
          WHERE entity_type = 'linear_route'
            AND entity_id = ?
            AND deleted_at IS NULL"
    )->execute([$userId, $roleCode, (int) $id]);
    $localPdo->commit();

    echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
    exit;
} catch (\Throwable $e) {
    if (isset($localPdo) && $localPdo instanceof PDO && $localPdo->inTransaction()) {
        $localPdo->rollBack();
    }
    echo json_encode(['success' => false, 'error' => 'Ошибка: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
}
