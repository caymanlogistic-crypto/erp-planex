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
$isFinanceRealm = false;
unset($_SESSION['linear_trip_form_error'], $_SESSION['linear_trip_form_success'], $_SESSION['linear_trip_old'], $_SESSION['linear_trip_validation_errors']);

if ($companyId > 0) {
    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        if ($company && ($company['status'] ?? '') === 'active') {
            $pageContext = 'Рейсы › Линейные › Компания: ' . $company['name'];

            $localDbConfig = companyDatabaseConfig($config, $company);
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

            // A route executor can contain several drivers. Keep the legacy primary-driver
            // columns for compatibility, but use the scalable crew_drivers membership for
            // the user-facing selector so every driver assigned to the crew is visible.
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

            $cargoTypeSuggestions = LinearRouteService::cargoTypeSuggestions($localPdo, '');

            $isFinanceRealm = ($sessionUser['role_code'] ?? '') === 'company_owner';
            $routes = LinearRouteService::fetchRoutesForList($localPdo, $sessionUser, $isFinanceRealm);

            if (!empty($routes) && $isFinanceRealm) {
                $routeIds = array_map(static fn(array $r): int => (int) ($r['id'] ?? 0), $routes);
                $routeInvoiceCounts = [];
                try {
                    if (class_exists(\App\Service\FinanceInvoiceService::class)) {
                        $placeholders = implode(',', array_fill(0, count($routeIds), '?'));
                        $invoiceStmt = $localPdo->prepare(
                            "SELECT fil.linear_route_id, fil.side,
                                    COUNT(DISTINCT fil.invoice_id) AS invoice_count
                               FROM finance_invoice_links fil
                               JOIN finance_invoices fi ON fi.id = fil.invoice_id
                              WHERE fil.linear_route_id IN ({$placeholders})
                                AND fi.status NOT IN ('cancelled')
                              GROUP BY fil.linear_route_id, fil.side"
                        );
                        $invoiceStmt->execute($routeIds);
                        $invoiceRows = $invoiceStmt->fetchAll(PDO::FETCH_ASSOC);
                        foreach ($invoiceRows as $row) {
                            $rid = (int) $row['linear_route_id'];
                            $side = (string) ($row['side'] ?? '');
                            if (!isset($routeInvoiceCounts[$rid])) {
                                $routeInvoiceCounts[$rid] = [];
                            }
                            $routeInvoiceCounts[$rid][$side] = (int) $row['invoice_count'];
                        }
                    }
                } catch (\Throwable $e) {
                }
                foreach ($routes as &$route) {
                    $rid = (int) ($route['id'] ?? 0);
                    $route['invoice_counts'] = $routeInvoiceCounts[$rid] ?? [];
                }
                unset($route);
            }
        }
    } catch (\Throwable $e) {
        $dbError = 'Не удалось подключиться к базе данных компании.';
    }
}

ob_start();
require base_path('app/View/pages/company_linear_trips.php');
$content = ob_get_clean();
require base_path('app/View/layouts/main.php');
