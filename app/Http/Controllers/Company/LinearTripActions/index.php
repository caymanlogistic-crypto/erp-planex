<?php

use App\Service\LinearRouteService;

requireRole(['company_owner', 'senior_logist', 'logist']);

$pageTitle = 'Линейные рейсы';
$pageContext = 'Рейсы › Линейные';
$companyId = (int) (getSessionCompanyId() ?? 0);
$company = null;
$routes = [];
$clients = [];
$contractors = [];
$routeExecutors = [];
$cargoTypeSuggestions = [];
$dbError = null;
$formError = $_SESSION['linear_trip_form_error'] ?? null;
$formSuccess = $_SESSION['linear_trip_form_success'] ?? null;
$old = $_SESSION['linear_trip_old'] ?? [];
$validationErrors = $_SESSION['linear_trip_validation_errors'] ?? [];
unset($_SESSION['linear_trip_form_error'], $_SESSION['linear_trip_form_success'], $_SESSION['linear_trip_old'], $_SESSION['linear_trip_validation_errors']);

if ($companyId > 0) {
    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        if ($company && ($company['status'] ?? '') === 'active') {
            $pageContext = 'Рейсы › Линейные › Компания: ' . $company['name'];

            $localDbConfig = $config['database'];
            $localDbConfig['database'] = $company['db_identifier'];
            $localDb = new \App\Core\Database($localDbConfig);
            $localPdo = $localDb->connection();
            applyLocalMigrations($localPdo);

            $sessionUser = [
                'user_id' => (int) ($_SESSION['user_id'] ?? 0),
                'role_code' => (string) ($_SESSION['role_code'] ?? ''),
            ];

            $clients = LinearRouteService::fetchVisibleClients($localPdo, $sessionUser);
            $contractors = LinearRouteService::fetchVisibleContractors($localPdo, $sessionUser);
            $routeExecutors = LinearRouteService::fetchVisibleRouteExecutors($localPdo, $sessionUser);
            $cargoTypeSuggestions = LinearRouteService::cargoTypeSuggestions($localPdo, '');

            $routes = LinearRouteService::fetchRoutesForList($localPdo, $sessionUser);
        }
    } catch (\Throwable $e) {
        $dbError = 'Не удалось подключиться к базе данных компании.';
    }
}

ob_start();
require base_path('app/View/pages/company_linear_trips.php');
$content = ob_get_clean();
require base_path('app/View/layouts/main.php');
