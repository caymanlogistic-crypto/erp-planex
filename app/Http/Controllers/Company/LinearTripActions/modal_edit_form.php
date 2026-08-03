<?php

use App\Service\LinearRouteService;
use App\Service\LinearTripEditTokenService;

requireRole(['company_owner', 'senior_logist', 'logist']);

$companyId = (int) (getSessionCompanyId() ?? 0);
$routeId = (int) $id;
if ($companyId <= 0 || $routeId <= 0) {
    http_response_code(404);
    echo '<div class="notice warn">Рейс недоступен.</div>';
    exit;
}

try {
    $pdo = $db->connection();
    $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ? AND status = ? LIMIT 1');
    $stmt->execute([$companyId, 'active']);
    $company = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    if ($company === null) {
        http_response_code(404);
        echo '<div class="notice warn">Компания не найдена или не активна.</div>';
        exit;
    }

    $localDbConfig = companyDatabaseConfig($config, $company);
    $localDb = new \App\Core\Database($localDbConfig);
    $localPdo = $localDb->connection();

    // P13: GET must be read-only. Schema is deployed by the migration workflow.
    $sessionUser = [
        'user_id' => (int) ($_SESSION['user_id'] ?? 0),
        'role_code' => (string) ($_SESSION['role_code'] ?? ''),
    ];
    $isFinanceRealm = $sessionUser['role_code'] === 'company_owner';
    $route = LinearRouteService::fetchRouteById($localPdo, $routeId, $isFinanceRealm);
    if ($route === null) {
        http_response_code(404);
        echo '<div class="notice warn">Рейс не найден.</div>';
        exit;
    }
    if (!LinearRouteService::canEditRoute($route, $localPdo, $sessionUser)) {
        http_response_code(403);
        echo '<div class="notice warn">У вас нет права редактировать эту запись.</div>';
        exit;
    }

    $termsByRole = LinearRouteService::fetchRouteTerms($localPdo, $routeId);
    $docsByCode = LinearRouteService::fetchRouteDocuments($localPdo, $routeId);
    $clients = LinearRouteService::fetchVisibleClients($localPdo, $sessionUser);
    $contractors = LinearRouteService::fetchVisibleContractors($localPdo, $sessionUser);
    $routeExecutors = LinearRouteService::fetchVisibleRouteExecutors($localPdo, $sessionUser);
    $validationErrors = [];
    $formError = null;
    $linearTripSaveToken = LinearTripEditTokenService::issue($routeId);

    header('Content-Type: text/html; charset=utf-8');
    require base_path('app/View/partials/company_linear_trip_modal_edit.php');
    exit;
} catch (Throwable $e) {
    error_log(sprintf('[P13] linear trip edit load failed company=%d route=%d: %s', $companyId, $routeId, $e->getMessage()));
    http_response_code(500);
    echo '<div class="notice warn">Не удалось открыть редактирование рейса.</div>';
    exit;
}
