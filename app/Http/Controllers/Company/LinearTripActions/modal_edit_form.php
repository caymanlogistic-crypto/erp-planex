<?php

use App\Service\LinearRouteService;
use App\Service\LinearTripEditTokenService;
use App\Service\LinearTripRequestNormalizer;

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
    $route['route_points'] = \App\Service\LinearRoutePointService::fetch($localPdo, $routeId);
    if (!LinearRouteService::canEditRoute($route, $localPdo, $sessionUser)) {
        http_response_code(403);
        echo '<div class="notice warn">У вас нет права редактировать эту запись.</div>';
        exit;
    }

    $route = LinearTripRequestNormalizer::normalizeRoutePaymentConditions($route);

    // Amounts are stored as DECIMAL (for example 97500.00). The generic visual formatter
    // groups digits, so a raw decimal string must first be converted to an edit-safe value.
    // Otherwise 97500.00 becomes 9 750 000 in the browser. Keep non-zero kopecks intact.
    $toEditAmount = static function ($value): string {
        $raw = trim((string) $value);
        if ($raw === '') {
            return '';
        }
        $clean = str_replace([' ', ','], ['', '.'], $raw);
        if (!preg_match('/^(\d+)(?:\.(\d{1,2}))?$/', $clean, $m)) {
            return $raw;
        }
        $fraction = $m[2] ?? '';
        if ($fraction === '' || preg_match('/^0{1,2}$/', $fraction)) {
            return $m[1];
        }
        return $m[1] . ',' . str_pad($fraction, 2, '0');
    };
    foreach (['customer', 'carrier'] as $partyRole) {
        foreach (($route['payments'][$partyRole] ?? []) as &$payment) {
            if (is_array($payment) && array_key_exists('amount', $payment)) {
                $payment['amount'] = $toEditAmount($payment['amount']);
            }
        }
        unset($payment);
    }
    foreach (($route['payments']['principals'] ?? []) as &$principalPayments) {
        if (!is_array($principalPayments)) {
            continue;
        }
        foreach ($principalPayments as &$payment) {
            if (is_array($payment) && array_key_exists('amount', $payment)) {
                $payment['amount'] = $toEditAmount($payment['amount']);
            }
        }
        unset($payment);
    }
    unset($principalPayments);

    $termsByRole = LinearRouteService::fetchRouteTerms($localPdo, $routeId);
    $docsByCode = LinearRouteService::fetchRouteDocuments($localPdo, $routeId);
    $clients = LinearRouteService::fetchVisibleClients($localPdo, $sessionUser);
    $contractors = LinearRouteService::fetchVisibleContractors($localPdo, $sessionUser);
    $routeExecutors = LinearRouteService::fetchVisibleRouteExecutors($localPdo, $sessionUser);

    if ($routeExecutors !== []) {
        $crewIds = array_values(array_filter(array_map(
            static fn(array $executor): int => (int) ($executor['id'] ?? 0),
            $routeExecutors
        )));
        if ($crewIds !== []) {
            $placeholders = implode(',', array_fill(0, count($crewIds), '?'));
            $driverStmt = $localPdo->prepare(
                "SELECT cd.crew_id,
                        GROUP_CONCAT(d.full_name ORDER BY cd.position ASC SEPARATOR ' / ') AS driver_names
                   FROM crew_drivers cd
                   JOIN drivers d ON d.id = cd.driver_id
                  WHERE cd.crew_id IN ({$placeholders})
                  GROUP BY cd.crew_id"
            );
            $driverStmt->execute($crewIds);
            $driverNamesByCrew = [];
            foreach ($driverStmt->fetchAll(PDO::FETCH_ASSOC) as $driverRow) {
                $driverNamesByCrew[(int) $driverRow['crew_id']] = trim((string) ($driverRow['driver_names'] ?? ''));
            }
            foreach ($routeExecutors as &$executor) {
                $crewId = (int) ($executor['id'] ?? 0);
                if (($driverNamesByCrew[$crewId] ?? '') !== '') {
                    $executor['driver_name'] = $driverNamesByCrew[$crewId];
                }
            }
            unset($executor);
        }
    }

    $validationErrors = [];
    $formError = null;
    $linearTripSaveToken = LinearTripEditTokenService::issue($routeId);

    header('Content-Type: text/html; charset=utf-8');
    require base_path('app/View/partials/company_linear_trip_modal_edit.php');
    exit;
} catch (Throwable $e) {
    error_log(sprintf('[P15] linear trip edit load failed company=%d route=%d: %s', $companyId, $routeId, $e->getMessage()));
    http_response_code(500);
    echo '<div class="notice warn">Не удалось открыть редактирование рейса.</div>';
    exit;
}
