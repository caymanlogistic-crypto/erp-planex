<?php

use App\Service\AuditService;
use App\Service\LinearRouteArchiveService;
use App\Service\LinearRouteService;
use App\Service\MutationErrorService;

requireRole(['company_owner', 'senior_logist', 'logist']);
header('Content-Type: application/json; charset=utf-8');

$companyId = (int) (getSessionCompanyId() ?? 0);
if ($companyId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Компания не найдена.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$auditId = 0;
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

    $routeId = (int) $id;
    $route = LinearRouteService::fetchRouteById($localPdo, $routeId);
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
    $userName = (string) ($_SESSION['user_name'] ?? '');

    $localPdo->beginTransaction();
    $relatedIds = LinearRouteArchiveService::archive($localPdo, $routeId, $userId, $roleCode);
    $snapshotJson = LinearRouteArchiveService::encodeSnapshot($route, $relatedIds);

    $displayParts = ['Рейс #' . $routeId];
    $cargoName = trim((string) ($route['cargo_type_name'] ?? ''));
    $clientName = trim((string) ($route['client_name'] ?? ''));
    if ($cargoName !== '') {
        $displayParts[] = $cargoName;
    }
    if ($clientName !== '') {
        $displayParts[] = $clientName;
    }

    $auditId = AuditService::recordDeletion(
        $pdo,
        $company,
        'linear_route',
        $routeId,
        'linear_routes',
        implode(' — ', $displayParts),
        $userId,
        $roleCode,
        $userName,
        'Удаление линейного рейса через интерфейс компании',
        $snapshotJson,
        count($relatedIds['documents'] ?? [])
    );

    $localPdo->commit();

    echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
    exit;
} catch (\Throwable $e) {
    if (isset($localPdo) && $localPdo instanceof PDO && $localPdo->inTransaction()) {
        $localPdo->rollBack();
    }
    if ($auditId > 0 && isset($pdo) && $pdo instanceof PDO) {
        try {
            $cleanup = $pdo->prepare("DELETE FROM deleted_entities WHERE id = ? AND status = 'archived'");
            $cleanup->execute([$auditId]);
        } catch (\Throwable) {
        }
    }

    $errorId = MutationErrorService::report($e, 'linear_trip.delete', [
        'company_id' => $companyId,
        'route_id' => (int) $id,
        'user_id' => (int) ($_SESSION['user_id'] ?? 0),
        'role_code' => (string) ($_SESSION['role_code'] ?? ''),
    ]);
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => MutationErrorService::userMessage('удалить рейс', $errorId),
        'error_id' => $errorId,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
