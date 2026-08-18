<?php

function opcacheRouteOk(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException('FAIL: ' . $message);
}

$controller = file_get_contents(__DIR__ . '/../app/Http/Controllers/Company/FinanceCashController.php');
$routes = file_get_contents(__DIR__ . '/../app/Http/Routes/company_finance_cash.php');

// The legacy controller remains in source history but must not be loaded by the
// active route table after CASH retirement.
opcacheRouteOk(!str_contains($routes, "require_once base_path('app/Http/Controllers/Company/FinanceCashController.php')"), 'retired cash routes must not bootstrap FinanceCashController');
opcacheRouteOk(!str_contains($routes, 'new \\App\\Http\\Controllers\\Company\\FinanceCashController($config, $db)'), 'retired cash routes must not construct FinanceCashController');
opcacheRouteOk(str_contains($routes, "'/company/finance/cash/dispatch-employee'"), 'legacy dispatch URL must remain registered for compatibility');
opcacheRouteOk(str_contains($routes, '$cashRetiredPost'), 'legacy dispatch must be routed to retired POST guard');
opcacheRouteOk(str_contains($routes, 'Создание и изменение кассовых операций отключено'), 'retired POST guard must reject cash mutations');
opcacheRouteOk(str_contains($routes, "redirect_to('/company/finance/employee-payments')"), 'legacy cash URLs must redirect to direct employee finance');
opcacheRouteOk(!str_contains($routes, 'FinanceCashActions/dispatch_employee_submit.php'), 'authoritative cash dispatch action must not execute');
opcacheRouteOk(!str_contains($routes, "[$controller, 'dispatchEmployeeSubmit']"), 'stale controller callable must remain absent');
opcacheRouteOk(!str_contains($controller, 'dispatchEmployeeSubmit'), 'legacy controller must not regain dispatch mutation method');

echo "CASH_DISPATCH_OPCACHE_ROUTE_OK\n";
