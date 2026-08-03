<?php

use App\Service\LinearRouteService;
use App\Service\LinearTripDocumentUploadException;
use App\Service\LinearTripEditSaveService;
use App\Service\LinearTripEditTokenService;

requireRole(['company_owner', 'senior_logist', 'logist']);
header('Content-Type: application/json; charset=utf-8');

$companyId = (int) (getSessionCompanyId() ?? 0);
$routeId = (int) $id;
$sessionUser = [
    'user_id' => (int) ($_SESSION['user_id'] ?? 0),
    'role_code' => (string) ($_SESSION['role_code'] ?? ''),
];

$respond = static function (array $payload, int $status = 200): never {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    exit;
};

if ($companyId <= 0 || $routeId <= 0 || $sessionUser['user_id'] <= 0) {
    $respond(['success' => false, 'message' => 'Рейс недоступен.', 'error_code' => 'invalid_context'], 404);
}

$requestToken = trim((string) ($_POST['_linear_trip_save_token'] ?? ''));
if (!LinearTripEditTokenService::consume($routeId, $requestToken)) {
    $respond([
        'success' => false,
        'message' => 'Запрос сохранения устарел или уже был выполнен. Повторно откройте редактирование рейса.',
        'error_code' => 'duplicate_or_expired_request',
        'retry_token' => LinearTripEditTokenService::issue($routeId),
    ], 409);
}

try {
    $pdo = $db->connection();
    $companyStmt = $pdo->prepare('SELECT * FROM companies WHERE id = ? AND status = ? LIMIT 1');
    $companyStmt->execute([$companyId, 'active']);
    $company = $companyStmt->fetch(PDO::FETCH_ASSOC) ?: null;
    if ($company === null) {
        $respond([
            'success' => false,
            'message' => 'Компания недоступна для работы.',
            'error_code' => 'company_unavailable',
            'retry_token' => LinearTripEditTokenService::issue($routeId),
        ], 404);
    }

    $localDbConfig = companyDatabaseConfig($config, $company);
    $localDb = new \App\Core\Database($localDbConfig);
    $localPdo = $localDb->connection();

    // P13: normal save requests must never run migrations or schema workarounds.
    $route = LinearRouteService::fetchRouteById($localPdo, $routeId, $sessionUser['role_code'] === 'company_owner');
    if ($route === null) {
        $respond([
            'success' => false,
            'message' => 'Рейс не найден.',
            'error_code' => 'route_not_found',
            'retry_token' => LinearTripEditTokenService::issue($routeId),
        ], 404);
    }
    if (!LinearRouteService::canEditRoute($route, $localPdo, $sessionUser)) {
        $respond([
            'success' => false,
            'message' => 'У вас нет права редактировать этот рейс.',
            'error_code' => 'route_access_denied',
            'retry_token' => LinearTripEditTokenService::issue($routeId),
        ], 403);
    }

    $result = LinearTripEditSaveService::save(
        $localPdo,
        $companyId,
        $routeId,
        $sessionUser,
        $_POST,
        $_FILES,
        $requestToken,
        LinearRouteService::fetchRouteDocuments($localPdo, $routeId)
    );

    if (!$result['success']) {
        $respond([
            'success' => false,
            'message' => $result['message'],
            'errors' => $result['errors'],
            'error_code' => 'validation_failed',
            'retry_token' => LinearTripEditTokenService::issue($routeId),
        ], 422);
    }

    $respond(['success' => true, 'message' => $result['message'], 'route_id' => $routeId]);
} catch (LinearTripDocumentUploadException $e) {
    $respond([
        'success' => false,
        'message' => $e->getMessage(),
        'errors' => ['documents' => $e->getMessage()],
        'error_code' => $e->errorCode,
        'retry_token' => LinearTripEditTokenService::issue($routeId),
    ], 422);
} catch (Throwable $e) {
    error_log(sprintf(
        '[P13] linear trip save failed company=%d route=%d user=%d: %s',
        $companyId,
        $routeId,
        $sessionUser['user_id'],
        $e->getMessage()
    ));
    $respond([
        'success' => false,
        'message' => 'Не удалось сохранить рейс из-за внутренней ошибки. Изменения не применены.',
        'error_code' => 'internal_save_error',
        'retry_token' => LinearTripEditTokenService::issue($routeId),
    ], 500);
}
