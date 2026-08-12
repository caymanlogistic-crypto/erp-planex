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

            if (!empty($routes)) {
                $routeIds = array_values(array_filter(array_map(
                    static fn(array $route): int => (int) ($route['id'] ?? 0),
                    $routes
                )));
                $crewIds = array_values(array_unique(array_filter(array_map(
                    static fn(array $route): int => (int) ($route['route_executor_id'] ?? 0),
                    $routes
                ))));

                $pointsByRoute = [];
                if ($routeIds !== []) {
                    $placeholders = implode(',', array_fill(0, count($routeIds), '?'));
                    $pointStmt = $localPdo->prepare(
                        "SELECT linear_route_id, point_type, sort_order, address_text, id
                           FROM linear_route_points
                          WHERE linear_route_id IN ({$placeholders})
                            AND deleted_at IS NULL
                          ORDER BY linear_route_id ASC, sort_order ASC, id ASC"
                    );
                    $pointStmt->execute($routeIds);
                    foreach ($pointStmt->fetchAll(PDO::FETCH_ASSOC) as $pointRow) {
                        $routeId = (int) ($pointRow['linear_route_id'] ?? 0);
                        $sortOrder = (int) ($pointRow['sort_order'] ?? 0);
                        $address = trim((string) ($pointRow['address_text'] ?? ''));
                        $key = $sortOrder . '|' . $address;
                        if (!isset($pointsByRoute[$routeId][$key])) {
                            $pointsByRoute[$routeId][$key] = [
                                'sort_order' => $sortOrder,
                                'address_text' => $address,
                                'is_loading' => false,
                                'is_unloading' => false,
                            ];
                        }
                        if (($pointRow['point_type'] ?? '') === 'loading') {
                            $pointsByRoute[$routeId][$key]['is_loading'] = true;
                        }
                        if (($pointRow['point_type'] ?? '') === 'unloading') {
                            $pointsByRoute[$routeId][$key]['is_unloading'] = true;
                        }
                    }
                }

                $driverNamesByCrew = [];
                $vehicleLabelsByCrew = [];
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
                    foreach ($driverStmt->fetchAll(PDO::FETCH_ASSOC) as $driverRow) {
                        $driverNamesByCrew[(int) $driverRow['crew_id']] = trim((string) ($driverRow['driver_names'] ?? ''));
                    }

                    $vehicleStmt = $localPdo->prepare(
                        "SELECT c.id AS crew_id,
                                vu1.brand AS primary_brand,
                                vu1.model AS primary_model,
                                vu1.plate_number AS primary_plate,
                                vu2.brand AS secondary_brand,
                                vu2.model AS secondary_model,
                                vu2.plate_number AS secondary_plate
                           FROM crews c
                      LEFT JOIN driver_vehicle_blocks dvb ON dvb.id = c.driver_vehicle_block_id
                      LEFT JOIN vehicle_sets vs ON vs.id = dvb.vehicle_set_id
                      LEFT JOIN vehicle_units vu1 ON vu1.id = vs.primary_vehicle_unit_id
                      LEFT JOIN vehicle_units vu2 ON vu2.id = vs.secondary_vehicle_unit_id
                          WHERE c.id IN ({$placeholders})"
                    );
                    $vehicleStmt->execute($crewIds);
                    foreach ($vehicleStmt->fetchAll(PDO::FETCH_ASSOC) as $vehicleRow) {
                        $parts = [];
                        foreach (['primary', 'secondary'] as $prefix) {
                            $vehicleText = trim(implode(' ', array_filter([
                                trim((string) ($vehicleRow[$prefix . '_brand'] ?? '')),
                                trim((string) ($vehicleRow[$prefix . '_model'] ?? '')),
                                trim((string) ($vehicleRow[$prefix . '_plate'] ?? '')),
                            ], static fn(string $value): bool => $value !== '')));
                            if ($vehicleText !== '') {
                                $parts[] = $vehicleText;
                            }
                        }
                        $vehicleLabelsByCrew[(int) $vehicleRow['crew_id']] = implode(' + ', $parts);
                    }
                }

                foreach ($routes as &$route) {
                    $routeId = (int) ($route['id'] ?? 0);
                    $crewId = (int) ($route['route_executor_id'] ?? 0);
                    $route['registry_points'] = array_values($pointsByRoute[$routeId] ?? []);
                    $route['registry_driver_names'] = $driverNamesByCrew[$crewId] ?? trim((string) ($route['executor_driver_name'] ?? ''));
                    $route['registry_vehicle_label'] = $vehicleLabelsByCrew[$crewId] ?? '';
                }
                unset($route);
            }

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