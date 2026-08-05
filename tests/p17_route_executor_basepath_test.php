<?php

$view = file_get_contents(__DIR__ . '/../app/View/pages/company_route_executor_view.php');
$controller = file_get_contents(__DIR__ . '/../app/Http/Controllers/Company/RouteExecutorActions/archive.php');
$failed = 0;
function checkP17Executor(string $name, bool $condition): void
{
    global $failed;
    echo ($condition ? 'PASS' : 'FAIL') . ' - ' . $name . PHP_EOL;
    if (!$condition) $failed++;
}

checkP17Executor('executor view has no root-only company links', !str_contains($view, 'href="/company/'));
checkP17Executor('executor archive form uses app_url', str_contains($view, "app_url('/company/route-executors/"));
checkP17Executor('executor edit links use app_url', substr_count($view, "app_url('/company/route-executors/") >= 3);
checkP17Executor('executor related entity links use app_url', str_contains($view, "app_url('/company/contractors/") && str_contains($view, "app_url('/company/drivers/") && str_contains($view, "app_url('/company/vehicle-sets/"));
checkP17Executor('archive redirects preserve ERP base path', !str_contains($controller, "Location: /company/route-executors") && substr_count($controller, "app_url('/company/route-executors')") === 5);

echo 'FAILED=' . $failed . PHP_EOL;
exit($failed === 0 ? 0 : 1);
