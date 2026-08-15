<?php

function opcacheRouteOk(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException('FAIL: ' . $message);
}

$controller = file_get_contents(__DIR__ . '/../app/Http/Controllers/Company/FinanceCashController.php');
$routes = file_get_contents(__DIR__ . '/../app/Http/Routes/company_finance_cash.php');

opcacheRouteOk(!str_contains($controller, 'dispatchEmployeeSubmit'), 'dispatch must not require a newly-added method on the long-lived FinanceCashController');
opcacheRouteOk(str_contains($routes, "'/company/finance/cash/dispatch-employee'"), 'dispatch endpoint missing');
opcacheRouteOk(str_contains($routes, 'static function () use ($config, $db): void'), 'dispatch endpoint must be registered through a callable closure');
opcacheRouteOk(str_contains($routes, "FinanceCashActions/dispatch_employee_submit.php"), 'dispatch closure must execute authoritative action file');
opcacheRouteOk(!str_contains($routes, "[$financeCashController, 'dispatchEmployeeSubmit']"), 'stale-controller callable regression reintroduced');

echo "CASH_DISPATCH_OPCACHE_ROUTE_OK\n";