<?php

function opcacheRouteOk(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException('FAIL: ' . $message);
}

$controller = file_get_contents(__DIR__ . '/../app/Http/Controllers/Company/FinanceCashController.php');
$routes = file_get_contents(__DIR__ . '/../app/Http/Routes/company_finance_cash.php');

// Historical controller code remains for audit compatibility, but the active
// route table must never bootstrap it or execute a historical mutation path.
opcacheRouteOk(!str_contains($routes, "require_once base_path('app/Http/Controllers/Company/FinanceCashController.php')"), 'retired routes must not bootstrap FinanceCashController');
opcacheRouteOk(!str_contains($routes, 'new \\App\\Http\\Controllers\\Company\\FinanceCashController($config, $db)'), 'retired routes must not construct FinanceCashController');
opcacheRouteOk(str_contains($routes, "'/company/finance/cash/dispatch-employee'"), 'legacy dispatch URL must remain registered for bookmark compatibility');
opcacheRouteOk(str_contains($routes, '$legacyMoneyAccountPost'), 'legacy dispatch must use the neutral retired POST guard');
opcacheRouteOk(str_contains($routes, 'Этот устаревший способ операции отключён'), 'retired POST guard must reject historical mutations');
opcacheRouteOk(str_contains($routes, "redirect_to('/company/finance/employee-payments')"), 'legacy URLs must redirect to employee settlements');
opcacheRouteOk(!preg_match('/касс/ui', preg_replace("~'/company/finance/cash[^']*'~", "''", $routes)), 'route messages must contain no retired user-facing terminology');
opcacheRouteOk(!str_contains($routes, 'FinanceCashActions/dispatch_employee_submit.php'), 'historical dispatch action must not execute');
opcacheRouteOk(!str_contains($routes, "[$controller, 'dispatchEmployeeSubmit']"), 'stale controller callable must remain absent');
opcacheRouteOk(!str_contains($controller, 'dispatchEmployeeSubmit'), 'legacy controller must not regain dispatch mutation method');

echo "CASH_DISPATCH_OPCACHE_ROUTE_OK\n";
