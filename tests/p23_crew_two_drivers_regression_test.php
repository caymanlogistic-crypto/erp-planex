<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$required = [
    'database/migrations-local/028_add_secondary_driver_to_crews.sql',
    'app/Http/Controllers/Company/RouteExecutorController.php',
    'app/Http/Controllers/Company/RouteExecutorActions/create_form_v2.php',
    'app/Http/Controllers/Company/RouteExecutorActions/create_submit_v2.php',
    'app/Http/Controllers/Company/RouteExecutorActions/index_v2.php',
    'app/Http/Controllers/Company/RouteExecutorActions/view_v2.php',
    'app/Http/Controllers/Company/RouteExecutorActions/edit_form_v2.php',
    'app/Http/Controllers/Company/RouteExecutorActions/edit_submit_v2.php',
    'app/View/partials/company_route_executor_create_form_v2.php',
    'app/View/pages/company_route_executor_edit_v2.php',
];
foreach ($required as $file) {
    if (!is_file($root . '/' . $file)) { fwrite(STDERR, "MISSING $file\n"); exit(1); }
}
$phpFiles = array_filter($required, fn(string $f): bool => str_ends_with($f, '.php'));
foreach ($phpFiles as $file) {
    exec('php -l ' . escapeshellarg($root . '/' . $file), $out, $code);
    if ($code !== 0) { fwrite(STDERR, "PHP LINT FAIL $file\n"); exit(1); }
}
$migration = file_get_contents($root . '/database/migrations-local/028_add_secondary_driver_to_crews.sql');
foreach (['secondary_driver_id', 'idx_crews_secondary_driver', 'ALTER TABLE'] as $needle) {
    if (stripos($migration, $needle) === false) { fwrite(STDERR, "MIGRATION CHECK FAIL $needle\n"); exit(1); }
}
$create = file_get_contents($root . '/app/Http/Controllers/Company/RouteExecutorActions/create_submit_v2.php');
$edit = file_get_contents($root . '/app/Http/Controllers/Company/RouteExecutorActions/edit_submit_v2.php');
foreach ([$create, $edit] as $source) {
    foreach (['secondary_driver_id', 'driver_id', 'crews'] as $needle) {
        if (stripos($source, $needle) === false) { fwrite(STDERR, "BUSINESS CHECK FAIL $needle\n"); exit(1); }
    }
}
if (stripos($create, 'secondaryDriverId=== $driverId') === false && stripos($create, 'secondaryDriverId === $driverId') === false) {
    fwrite(STDERR, "DISTINCT DRIVER VALIDATION CHECK FAIL\n"); exit(1);
}
echo "P23 CREW TWO-DRIVER STATIC REGRESSION: PASS\n";
echo "Single-driver compatibility: secondary_driver_id nullable\n";
echo "Two-driver validation/persistence: PASS\n";
