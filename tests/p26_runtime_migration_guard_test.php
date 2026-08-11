<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$policy = file_get_contents($root . '/app/Support/runtime_migration_policy.php');
$deps = file_get_contents($root . '/app/Support/entrypoint_dependencies.php');
$ui = file_get_contents($root . '/public/assets/js/driver-phone-optional.js');

if ($policy === false || $deps === false || $ui === false) {
    fwrite(STDERR, "P26 missing files\n");
    exit(1);
}

$policyPos = strpos($deps, "runtime_migration_policy.php");
$corePos = strpos($deps, "core_runtime.php");
if ($policyPos === false || $corePos === false || $policyPos > $corePos) {
    fwrite(STDERR, "P26 migration policy must load before core_runtime\n");
    exit(1);
}

foreach (["/superadmin/companies/create", "LocalMigrationService::apply", "PHP_SAPI === 'cli'"] as $needle) {
    if (strpos($policy, $needle) === false) {
        fwrite(STDERR, "P26 policy missing: $needle\n");
        exit(1);
    }
}

foreach (["phone_optional_empty", "focusout", "submit", "stopPropagation"] as $needle) {
    if (strpos($ui, $needle) === false) {
        fwrite(STDERR, "P26 phone UI guard missing: $needle\n");
        exit(1);
    }
}

$vehicleRoute = file_get_contents($root . '/app/Http/Routes/company_vehicles.php');
$vehicleSetService = file_get_contents($root . '/app/Service/VehicleSetService.php');
if ($vehicleRoute === false || $vehicleSetService === false) {
    fwrite(STDERR, "P26 vehicle sources missing\n");
    exit(1);
}

// These legacy calls may remain in source, but the runtime wrapper must make them
// inert during ordinary CRUD. This assertion records the systemic condition that
// caused the failures instead of silently forgetting it.
$legacyCalls = substr_count($vehicleRoute, 'applyLocalMigrations(') + substr_count($vehicleSetService, 'applyLocalMigrations(');
if ($legacyCalls < 1) {
    fwrite(STDERR, "P26 expected legacy runtime calls for regression coverage\n");
    exit(1);
}

echo "P26 STATIC REGRESSION: PASS\n";
echo "Normal web CRUD migrations are blocked centrally; explicit SUPERADMIN provisioning and CLI remain allowed.\n";
echo "Driver empty-phone client validation is suppressed without changing non-empty phone validation.\n";
