<?php
/**
 * ERP PLANEX — Architecture Guard (E7.2)
 *
 * Validates that the modular structure is intact.
 * Exit code 0 = no ERRORs (WARNINGs are acceptable for legacy files).
 */

$root = dirname(__DIR__);
$errors = [];
$warnings = [];

function checkExists(string $rel, bool $isFile = true): void {
    global $root, $errors;
    $path = $root . DIRECTORY_SEPARATOR . $rel;
    if ($isFile ? !is_file($path) : !is_dir($path)) {
        $errors[] = "MISSING: $rel";
    }
}

function checkSize(string $rel, int $maxLines, string $label = ''): void {
    global $root, $warnings, $errors;
    $path = $root . DIRECTORY_SEPARATOR . $rel;
    if (!is_file($path)) {
        $errors[] = "MISSING: $rel";
        return;
    }
    $lines = count(file($path) ?: []);
    $name = $label ?: $rel;
    if ($lines > $maxLines) {
        if (str_contains($rel, 'legacy') || str_contains($rel, 'superadmin_management') || str_contains($rel, 'company_contractors.php') || str_contains($rel, 'company_clients.php') || str_contains($rel, 'company_drivers.php') || str_contains($rel, 'company_vehicle_sets.php')) {
            $warnings[] = sprintf("WARNING [%s]: %d lines (legacy, >%d)", $name, $lines, $maxLines);
        } else {
            $errors[] = sprintf("ERROR [%s]: %d lines (>%d limit)", $name, $lines, $maxLines);
        }
    }
}

function checkFileContains(string $rel, string $needle, string $label): void {
    global $root, $errors;
    $path = $root . DIRECTORY_SEPARATOR . $rel;
    if (!is_file($path)) {
        $errors[] = "MISSING: $rel";
        return;
    }
    $content = file_get_contents($path);
    if (strpos($content, $needle) === false) {
        $errors[] = "MISSING CONTENT in $label: '$needle'";
    }
}

// === Structure checks ===
checkExists('public/index.php');
checkExists('bootstrap/app.php', true);
checkExists('app/Http/Routes', false);
checkExists('app/Http/Controllers', false);
checkExists('app/Service', false);
checkExists('app/Support', false);

// === public/index.php size ===
checkSize('public/index.php', 150, 'public/index.php');

// === New route file sizes ===
checkSize('app/Http/Routes/auth.php', 80, 'auth.php');
checkSize('app/Http/Routes/company_route_executors.php', 80, 'company_route_executors.php');
checkSize('app/Http/Routes/company_responsible_assignments.php', 80, 'company_responsible_assignments.php');
checkSize('app/Http/Routes/superadmin.php', 120, 'superadmin.php');
checkSize('app/Http/Routes/legacy_redirects.php', 120, 'legacy_redirects.php');

// === Legacy route files — WARNING only ===
checkSize('app/Http/Routes/company_contractors.php', 9999, 'company_contractors.php (legacy)');
checkSize('app/Http/Routes/company_clients.php', 9999, 'company_clients.php (legacy)');
checkSize('app/Http/Routes/company_drivers.php', 9999, 'company_drivers.php (legacy)');
checkSize('app/Http/Routes/company_vehicle_sets.php', 9999, 'company_vehicle_sets.php (legacy)');
checkSize('app/Http/Routes/company_documents.php', 9999, 'company_documents.php (legacy)');
checkSize('app/Http/Routes/superadmin_management.php', 9999, 'superadmin_management.php (legacy)');

// === legacy_redirects.php exists ===
checkExists('app/Http/Routes/legacy_redirects.php');

// === legacy_redirects.php loaded before legacy files in index.php ===
$indexContent = file_get_contents($root . '/public/index.php');
$lrPos = strpos($indexContent, 'legacy_redirects.php');
$legacyFiles = ['company_crews_legacy.php', 'company_driver_vehicle_blocks_legacy.php'];
if ($lrPos === false) {
    $errors[] = "MISSING: legacy_redirects.php not required in public/index.php";
} else {
    foreach ($legacyFiles as $lf) {
        $lfPos = strpos($indexContent, $lf);
        if ($lfPos !== false && $lfPos < $lrPos) {
            $errors[] = "ORDER: $lf loaded BEFORE legacy_redirects.php in index.php";
        }
    }
}

// === New route files call their controllers ===
checkFileContains('app/Http/Routes/auth.php', 'AuthController', 'auth.php -> AuthController');
checkFileContains('app/Http/Routes/company_route_executors.php', 'RouteExecutorController', 'company_route_executors.php -> RouteExecutorController');
checkFileContains('app/Http/Routes/company_responsible_assignments.php', 'ResponsibleAssignmentController', 'company_responsible_assignments.php -> ResponsibleAssignmentController');

// === Superadmin controllers ===
$superadminContent = file_get_contents($root . '/app/Http/Routes/superadmin.php');
if (strpos($superadminContent, 'CompanyController') === false && strpos($superadminContent, 'CompanyOwnerController') === false) {
    $errors[] = "MISSING CONTENT in superadmin.php: no superadmin controller reference";
}

// === Service autoloading in index.php ===
$services = ['ContractorContactService', 'ClientContactService', 'AccessControlService', 'LocalMigrationService'];
foreach ($services as $svc) {
    checkFileContains('public/index.php', $svc, "index.php -> $svc require");
}

// === Output ===
echo "=== Architecture Guard E7.2 ===\n\n";

if ($warnings) {
    echo "WARNINGS:\n";
    foreach ($warnings as $w) {
        echo "  ⚠  $w\n";
    }
    echo "\n";
}

if ($errors) {
    echo "ERRORS:\n";
    foreach ($errors as $e) {
        echo "  ✗  $e\n";
    }
    echo "\n";
    echo "RESULT: FAIL (" . count($errors) . " errors, " . count($warnings) . " warnings)\n";
    exit(1);
}

echo "RESULT: PASS (0 errors, " . count($warnings) . " warnings)\n";
exit(0);
