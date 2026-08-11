<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$files = [
    'database/migrations-local/029_create_crew_drivers.sql',
    'scripts/p27_migrate_crew_drivers.php',
    'app/Support/crew_driver_helpers.php',
    'app/Http/Controllers/Company/RouteExecutorActions/create_submit.php',
    'app/View/partials/company_route_executor_create_form.php',
];
foreach ($files as $file) {
    if (!is_file($root.'/'.$file)) { fwrite(STDERR, "Missing $file\n"); exit(1); }
}
foreach ($files as $file) {
    if (str_ends_with($file,'.php')) {
        exec('php -l '.escapeshellarg($root.'/'.$file), $out, $code);
        if ($code !== 0) { fwrite(STDERR, "PHP lint failed: $file\n"); exit(1); }
    }
}
$migration = file_get_contents($root.'/database/migrations-local/029_create_crew_drivers.sql');
foreach (['CREATE TABLE IF NOT EXISTS `crew_drivers`','UNIQUE KEY `uq_crew_driver`','UNIQUE KEY `uq_crew_position`','INSERT IGNORE INTO `crew_drivers`'] as $needle) {
    if (!str_contains($migration,$needle)) { fwrite(STDERR, "Migration missing: $needle\n"); exit(1); }
}
$form = file_get_contents($root.'/app/View/partials/company_route_executor_create_form.php');
foreach (['name="driver_ids[]"','+ Добавить водителя','data-crew-driver-row'] as $needle) {
    if (!str_contains($form,$needle)) { fwrite(STDERR, "UI missing: $needle\n"); exit(1); }
}
$submit = file_get_contents($root.'/app/Http/Controllers/Company/RouteExecutorActions/create_submit.php');
foreach (['normalizeCrewDriverIds','syncCrewDrivers','driver_ids'] as $needle) {
    if (!str_contains($submit,$needle)) { fwrite(STDERR, "Backend missing: $needle\n"); exit(1); }
}
$helper = file_get_contents($root.'/app/Support/crew_driver_helpers.php');
if (!str_contains($helper,'foreach ($driverIds as $position => $driverId)')) { fwrite(STDERR,"Helper is not N-driver capable\n"); exit(1); }
echo "P27 multi-driver crew static test: PASS\n";
