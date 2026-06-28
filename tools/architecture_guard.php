<?php
/**
 * ERP PLANEX — Architecture Guard (E14-E15)
 *
 * Validates modular structure, controller wiring, table name safety,
 * mojibake, and thin entry points.
 * Exit code 0 = no ERRORs (WARNINGs acceptable for legacy files).
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
        $legacyPaths = [
            'legacy', 'crews_legacy', 'driver_vehicle_blocks_legacy', 'contractor_assignments',
            'superadmin_management', 'company_contractors', 'company_clients', 'company_drivers',
            'company_vehicle_sets', 'company_documents', 'company_vehicles',
        ];
        $isLegacy = false;
        foreach ($legacyPaths as $lp) {
            if (str_contains($rel, $lp)) { $isLegacy = true; break; }
        }
        if ($isLegacy) {
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

function checkFileNotContains(string $rel, string $needle, string $label): void {
    global $root, $warnings;
    $path = $root . DIRECTORY_SEPARATOR . $rel;
    if (!is_file($path)) return;
    $content = file_get_contents($path);
    if (strpos($content, $needle) !== false) {
        $warnings[] = "FOUND in $label: '$needle'";
    }
}

// === Structure checks ===
checkExists('public/index.php');
checkExists('bootstrap/app.php');
checkExists('app/Http/Routes', false);
checkExists('app/Http/Controllers', false);
checkExists('app/Service', false);
checkExists('app/Support', false);

// === Thin entry points ===
checkSize('public/index.php', 150, 'public/index.php');
checkSize('bootstrap/app.php', 80, 'bootstrap/app.php');

// === Route file sizes ===
checkSize('app/Http/Routes/auth.php', 80, 'auth.php');
checkSize('app/Http/Routes/core.php', 60, 'core.php');
// Route files with inline closures (bulky — refactoring candidates)
checkSize('app/Http/Routes/company_dashboard.php', 999, 'company_dashboard.php (inline closures)');
checkSize('app/Http/Routes/company_logists.php', 999, 'company_logists.php (inline closures)');
checkSize('app/Http/Routes/superadmin_company_delete.php', 999, 'superadmin_company_delete.php (inline closures)');
checkSize('app/Http/Routes/legacy_redirects.php', 120, 'legacy_redirects.php');

// === Legacy route files — WARNING only ===
checkSize('app/Http/Routes/company_contractors.php', 9999, 'company_contractors.php (legacy)');
checkSize('app/Http/Routes/company_clients.php', 9999, 'company_clients.php (legacy)');
checkSize('app/Http/Routes/company_drivers.php', 9999, 'company_drivers.php (legacy)');
checkSize('app/Http/Routes/company_vehicle_sets.php', 9999, 'company_vehicle_sets.php (legacy)');
checkSize('app/Http/Routes/company_documents.php', 9999, 'company_documents.php (legacy)');
checkSize('app/Http/Routes/superadmin_management.php', 9999, 'superadmin_management.php (legacy)');
checkSize('app/Http/Routes/company_vehicles.php', 9999, 'company_vehicles.php (legacy)');
checkSize('app/Http/Routes/company_crews_legacy.php', 9999, 'company_crews_legacy.php (legacy)');
checkSize('app/Http/Routes/company_driver_vehicle_blocks_legacy.php', 9999, 'company_driver_vehicle_blocks_legacy.php (legacy)');
checkSize('app/Http/Routes/company_contractor_assignments.php', 9999, 'company_contractor_assignments.php (legacy)');

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

// === All controller-wired route files must reference their controller ===
$routeFiles = [
    'auth.php' => 'App\\Http\\Controllers\\AuthController',
    'company_clients.php' => 'App\\Http\\Controllers\\Company\\ClientController',
    'company_contractors.php' => 'App\\Http\\Controllers\\Company\\ContractorController',
    'company_drivers.php' => 'App\\Http\\Controllers\\Company\\DriverController',
    'company_vehicle_sets.php' => 'App\\Http\\Controllers\\Company\\VehicleSetController',
    'company_documents.php' => 'App\\Http\\Controllers\\Company\\DocumentController',
    'company_route_executors.php' => 'App\\Http\\Controllers\\Company\\RouteExecutorController',
    'company_responsible_assignments.php' => 'App\\Http\\Controllers\\Company\\ResponsibleAssignmentController',
    'superadmin.php' => 'CompanyController',
    'superadmin_management.php' => 'ManagementController',
];
foreach ($routeFiles as $file => $ctrl) {
    checkFileContains("app/Http/Routes/$file", $ctrl, "$file -> $ctrl");
}

// === Files using inline closures (non-controller pattern) — verify they at least have routes ===
$closureRouteFiles = ['company_dashboard.php', 'company_logists.php', 'superadmin_company_delete.php'];
foreach ($closureRouteFiles as $cf) {
    checkFileContains("app/Http/Routes/$cf", '$router->', "$cf -> has route definitions");
}

// === Service autoloading in index.php ===
$services = ['ContractorContactService', 'ClientContactService', 'AccessControlService', 'LocalMigrationService'];
foreach ($services as $svc) {
    checkFileContains('public/index.php', $svc, "index.php -> $svc require");
}

// === Dynamic table name whitelist check (entity_list.php) ===
$entityListPath = $root . '/app/Http/Controllers/Superadmin/ManagementActions/entity_list.php';
if (is_file($entityListPath)) {
    $elContent = file_get_contents($entityListPath);
    // Must have a whitelist map
    if (strpos($elContent, 'entityMap') === false) {
        $errors[] = "entity_list.php: missing whitelist entityMap";
    } else {
        // Verify each entity_type is validated against the map before use
        if (strpos($elContent, '!isset($entityMap[$entityType])') === false) {
            $errors[] = "entity_list.php: entityType not validated against whitelist";
        }
    }
}

// === Check for risky dynamic table name patterns elsewhere ===
$riskyPatterns = [];
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/app'));
foreach ($iterator as $file) {
    if (!$file->isFile() || $file->getExtension() !== 'php') continue;
    $content = file_get_contents($file->getPathname());
    // Pattern: interpolated table name from user input
    if (preg_match('/FROM\s+`?\$[a-z_]+`?/i', $content) &&
        strpos($file->getPathname(), 'entity_list.php') === false &&
        strpos($file->getPathname(), 'core_runtime.php') === false) {
        $warnings[] = "RISKY TABLE: potential dynamic table name in " . $file->getPathname();
    }
}

// === No route_executors table (business model: use crews + driver_vehicle_blocks) ===
$migrationDir = $root . '/database/migrations-local';
if (is_dir($migrationDir)) {
    $miIterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($migrationDir));
    foreach ($miIterator as $mf) {
        if (!$mf->isFile() || $mf->getExtension() !== 'sql') continue;
        $sqlContent = strtolower(file_get_contents($mf->getPathname()));
        if (strpos($sqlContent, 'create table route_executors') !== false) {
            $errors[] = "FORBIDDEN: route_executors table created in " . $mf->getFilename();
        }
    }
}

// === driver_vehicle_blocks must NOT have vehicle_id field ===
// Check migration SQL files
if (is_dir($migrationDir)) {
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($migrationDir)) as $mf) {
        if (!$mf->isFile() || $mf->getExtension() !== 'sql') continue;
        $sqlContent = file_get_contents($mf->getPathname());
        if (preg_match('/CREATE\s+TABLE\s+`?driver_vehicle_blocks`?/i', $sqlContent)) {
            if (preg_match('/vehicle_id/i', $sqlContent)) {
                $errors[] = "FORBIDDEN: driver_vehicle_blocks has vehicle_id in " . $mf->getFilename();
            }
        }
    }
}

// === Check driver_vehicle_blocks action files for vehicle_id ===
foreach (['index.php', 'create_form.php', 'create_submit.php', 'edit_form.php'] as $af) {
    $path = $root . '/app/Http/Controllers/Company/DriverActions/' . $af;
    if (is_file($path)) {
        $ac = file_get_contents($path);
        if (preg_match('/vehicle_id/i', $ac)) {
            $errors[] = "FORBIDDEN: vehicle_id in DriverActions/$af (use vehicle_set_id)";
        }
    }
}

// === Mojibake: check all text files for valid UTF-8 and mojibake markers ===
function isUtf8($content): bool {
    if (function_exists('mb_check_encoding')) {
        return mb_check_encoding($content, 'UTF-8');
    }
    // Fallback: check no invalid UTF-8 sequences
    return (bool)preg_match('//u', $content);
}

$dirsToCheck = ['app', 'docs', 'public', 'database', 'tools'];
// Reliable mojibake indicators: Ð (0xC3 0x90) and Ñ (0xC3 0x91) 
// when they appear in likely-double-encoded contexts
// Check for the literal UTF-8 bytes of Ð and Ñ in PHP/SQL files
// which indicate UTF-8 bytes were read as Latin-1 and re-saved
$mojibakeRx = '/\xC3[\x90-\x91][\x80-\xBF]/';  // Ð or Ñ followed by a continuation byte
foreach ($dirsToCheck as $dir) {
    $rdir = $root . DIRECTORY_SEPARATOR . $dir;
    if (!is_dir($rdir)) continue;
    $mbIterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($rdir));
    foreach ($mbIterator as $file) {
        if (!$file->isFile()) continue;
        $ext = strtolower($file->getExtension());
        if (!in_array($ext, ['php', 'md', 'sql', 'html', 'env'])) continue;
        $path = $file->getPathname();
        // Skip vendor (shouldn't exist but just in case)
        if (strpos($path, DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR) !== false) continue;
        if (strpos($path, DIRECTORY_SEPARATOR . 'node_modules' . DIRECTORY_SEPARATOR) !== false) continue;
        // Skip self (patterns match own source)
        if (strpos($path, 'architecture_guard.php') !== false) continue;
        $content = file_get_contents($path);
        if (strlen($content) === 0) continue;
        if (!isUtf8($content)) {
            $warnings[] = "NOT UTF-8: $path";
            continue;
        }
        // Explicit mojibake marker: Ð/Ñ followed by continuation byte
        if (preg_match($mojibakeRx, $content)) {
            $warnings[] = "MOJIBAKE: $path";
        }
    }
}

// === Check sidebar/layout for old menu items ===
$layoutPath = $root . '/app/View/layouts/main.php';
if (is_file($layoutPath)) {
    $layoutContent = file_get_contents($layoutPath);
    $oldMenuPatterns = ['/company/crews', '/company/driver-vehicle-blocks', '/company/contractor-assignments'];
    foreach ($oldMenuPatterns as $omp) {
        $count = substr_count($layoutContent, 'href="' . $omp . '"');
        if ($count > 0) {
            $warnings[] = "OLD MENU: $omp appears $count time(s) in sidebar/layout";
        }
    }
}

// === DocumentActions consistency checks ===
$docIndex = $root . '/app/Http/Controllers/Company/DocumentActions/index.php';
if (is_file($docIndex)) {
    $di = file_get_contents($docIndex);
    // Must use whitelist-based displayField, not universal SELECT name,full_name,plate_number
    if (strpos($di, 'displayField') === false) {
        $errors[] = "DocumentActions/index.php: missing whitelist displayField map";
    }
    if (preg_match('/SELECT\s+name,\s*full_name,\s*plate_number/i', $di)) {
        $errors[] = "DocumentActions/index.php: risky universal SELECT name,full_name,plate_number";
    }
    // Must validate entity_type against whitelist before SQL
    if (strpos($di, '!isset($whitelist[$entityType])') === false && strpos($di, '!isset($entityInfo[$entityType])') === false) {
        $errors[] = "DocumentActions/index.php: entity_type not validated against whitelist";
    }
}
$docUploadForm = $root . '/app/Http/Controllers/Company/DocumentActions/upload_form.php';
if (is_file($docUploadForm)) {
    $uf = file_get_contents($docUploadForm);
    // Must set company, entityType, entityId, docTypes, entityName, etc.
    $requiredVars = ['$entityType=', '$entityId=', '$docTypes=', '$entityName=', '$entityLabel='];
    foreach ($requiredVars as $rv) {
        if (strpos($uf, $rv) === false) {
            $warnings[] = "DocumentActions/upload_form.php: missing required context '$rv'";
        }
    }
}
$docUploadView = $root . '/app/View/pages/company_documents_upload.php';
if (is_file($docUploadView)) {
    $uv = file_get_contents($docUploadView);
    // File input name must match upload_submit.php: doc_file
    if (strpos($uv, 'name="doc_file"') === false) {
        $errors[] = "company_documents_upload.php: file input name mismatch (expected doc_file)";
    }
    // Must have hidden entity_type and entity_id fields
    if (strpos($uv, 'name="entity_type"') === false) {
        $errors[] = "company_documents_upload.php: missing hidden entity_type field";
    }
    if (strpos($uv, 'name="entity_id"') === false) {
        $errors[] = "company_documents_upload.php: missing hidden entity_id field";
    }
}
$docSubmit = $root . '/app/Http/Controllers/Company/DocumentActions/upload_submit.php';
if (is_file($docSubmit)) {
    $ds = file_get_contents($docSubmit);
    // Must read from POST
    if (strpos($ds, '$_POST') === false) {
        $warnings[] = "upload_submit.php: does not read from POST";
    }
}
$docDelete = $root . '/app/Http/Controllers/Company/DocumentActions/delete.php';
if (is_file($docDelete)) {
    $dd = file_get_contents($docDelete);
    // Must read id from POST
    if (strpos($dd, '$_POST[\'id\']') === false && strpos($dd, '$_POST[\"id\"]') === false) {
        $errors[] = "DocumentActions/delete.php: does not read id from POST";
    }
}
$docView = $root . '/app/View/pages/company_documents.php';
if (is_file($docView)) {
    $dv = file_get_contents($docView);
    // Delete form must have hidden POST fields
    if (strpos($dv, 'name="id"') === false || strpos($dv, 'name="entity_type"') === false) {
        $errors[] = "company_documents.php: delete form missing hidden POST fields";
    }
}

// === Output ===
echo "=== Architecture Guard E14-E15 ===\n\n";

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
