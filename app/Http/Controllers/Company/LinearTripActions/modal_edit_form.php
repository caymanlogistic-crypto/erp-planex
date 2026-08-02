<?php

use App\Service\LinearRouteService;

requireRole(['company_owner', 'senior_logist', 'logist']);

$companyId = (int) (getSessionCompanyId() ?? 0);
if ($companyId <= 0) {
    http_response_code(404);
    echo '<div class="notice warn">Компания не найдена.</div>';
    exit;
}

try {
    $pdo = $db->connection();
    $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
    $stmt->execute([$companyId]);
    $company = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    if (!$company || ($company['status'] ?? '') !== 'active') {
        http_response_code(404);
        echo '<div class="notice warn">Компания не найдена или не активна.</div>';
        exit;
    }

    $localDbConfig = companyDatabaseConfig($config, $company);
    $localDb = new \App\Core\Database($localDbConfig);
    $localPdo = $localDb->connection();
    applyLocalMigrations($localPdo);

    $sessionUser = [
        'user_id' => (int) ($_SESSION['user_id'] ?? 0),
        'role_code' => (string) ($_SESSION['role_code'] ?? ''),
    ];
    $isFinanceRealm = ($sessionUser['role_code'] ?? '') === 'company_owner';
    $route = LinearRouteService::fetchRouteById($localPdo, (int) $id, $isFinanceRealm);
    if (!$route) {
        http_response_code(404);
        echo '<div class="notice warn">Рейс не найден.</div>';
        exit;
    }

    if (!LinearRouteService::canEditRoute($route, $localPdo, $sessionUser)) {
        http_response_code(403);
        echo '<div class="notice warn">У вас нет права редактировать эту запись.</div>';
        exit;
    }

    $termsByRole = LinearRouteService::fetchRouteTerms($localPdo, (int) $id);
    $docsByCode = LinearRouteService::fetchRouteDocuments($localPdo, (int) $id);
    $clients = LinearRouteService::fetchVisibleClients($localPdo, $sessionUser);
    $contractors = LinearRouteService::fetchVisibleContractors($localPdo, $sessionUser);
    $routeExecutors = LinearRouteService::fetchVisibleRouteExecutors($localPdo, $sessionUser);
    $validationErrors = [];
    $formError = null;

    header('Content-Type: text/html; charset=utf-8');
    require base_path('app/View/partials/company_linear_trip_modal_edit.php');
    exit;
} catch (\Throwable $e) {
    http_response_code(500);
    echo '<div class="notice warn">Ошибка загрузки: ' . e($e->getMessage()) . '</div>';
    exit;
}
