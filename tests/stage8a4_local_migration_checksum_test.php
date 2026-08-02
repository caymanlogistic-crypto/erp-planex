<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require_once __DIR__ . '/../app/Service/LocalMigrationService.php';
use App\Service\LocalMigrationService;

$passCount = 0;
$failCount = 0;
$testResults = [];

function test(string $name, $expected, $actual, string $description = ''): void {
    global $passCount, $failCount, $testResults;
    $pass = $expected === $actual;
    if ($pass) { $passCount++; } else { $failCount++; }
    $testResults[] = [
        'name' => $name, 'pass' => $pass,
        'expected' => $expected, 'actual' => $actual,
        'description' => $description,
    ];
}

function sourceContains(string $file, string $pattern): bool {
    if (!file_exists($file)) return false;
    $content = file_get_contents($file);
    return preg_match($pattern, $content) === 1;
}

echo "=== ERP PLANEX Stage 8A4: Local Migration Checksum Compatibility ===\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n\n";

// ======== 1. Method existence via reflection ========
echo "--- 1. verifyMigration019Compatible method exists ---\n";

$hasMethod = method_exists(LocalMigrationService::class, 'verifyMigration019Compatible');
test(
    '1. verifyMigration019Compatible method exists',
    true,
    $hasMethod,
    'LocalMigrationService must have a method for 019-specific schema compatibility check'
);
echo $hasMethod ? "  [PASS] Method exists\n" : "  [FAIL] Method does not exist\n";

// ======== 2 & 3: Source code patterns ========
echo "--- 2-3. Source code pattern analysis ---\n";

$lmFile = __DIR__ . '/../app/Service/LocalMigrationService.php';

$has019CompatCall = sourceContains(
    $lmFile,
    '/019_update_crews.*verifyMigration019Compatible/s'
);
test(
    '2a. apply() calls verifyMigration019Compatible for 019',
    true,
    $has019CompatCall,
    'apply() must use verifyMigration019Compatible for 019_update_crews.sql checksum mismatch'
);
echo $has019CompatCall ? "  [PASS] 019 compat call exists\n" : "  [FAIL] 019 compat call missing\n";

$hasGeneralThrow = sourceContains(
    $lmFile,
    '/throw\s+new\s+\\\\RuntimeException\s*\([^)]*Local migration checksum mismatch/s'
);
test(
    '3a. General RuntimeException throw exists for non-019 checksum mismatch',
    true,
    $hasGeneralThrow,
    'apply() must still throw RuntimeException for non-019 checksum mismatches'
);
echo $hasGeneralThrow ? "  [PASS] General throw exists\n" : "  [FAIL] General throw missing\n";

$has019ThenElseThrow = sourceContains(
    $lmFile,
    '/019_update_crews.*verifyMigration019Compatible.*else\s*\{[^}]*throw[^}]*checksum\s+mismatch/s'
);
test(
    '3b. Throw is inside else after 019 compat check',
    true,
    $has019ThenElseThrow,
    'The RuntimeException must be in the else branch after 019 compatibility block'
);
echo $has019ThenElseThrow ? "  [PASS] Throw is in else after 019 compat\n" : "  [FAIL] Throw not correctly guarded by 019 compat path\n";

// ======== 4. Schema check logic simulation ========
echo "\n--- 4-10: Schema compatibility logic simulation ---\n";

class SchemaChecker {
    private array $tables;

    public function __construct(array $tables) {
        $this->tables = $tables;
    }

    public function isCompatible(): array {
        $result = [
            'pass' => false,
            'checks' => [],
        ];

        $crewsExists = isset($this->tables['crews']);
        $result['checks']['crews_exists'] = $crewsExists;
        if (!$crewsExists) {
            $result['fail_reason'] = 'crews table does not exist';
            return $result;
        }

        $cols = $this->tables['crews']['columns'] ?? [];
        $indices = $this->tables['crews']['indices'] ?? [];

        $requiredCols = ['driver_vehicle_block_id', 'updated_by_user_id', 'updated_by_role'];
        foreach ($requiredCols as $col) {
            $colExists = in_array($col, $cols, true);
            $result['checks']['column_' . $col] = $colExists;
            if (!$colExists) {
                $result['fail_reason'] = "Missing required column: {$col}";
                return $result;
            }
        }

        $hasUkCrew = isset($indices['uk_crew']);
        $result['checks']['uk_crew_exists'] = $hasUkCrew;
        if (!$hasUkCrew) {
            $result['fail_reason'] = 'uk_crew index does not exist';
            return $result;
        }

        $ukCrewCols = $indices['uk_crew'];
        $ukCrewStr = implode(',', $ukCrewCols);
        $ukCrewCorrect = $ukCrewStr === 'contractor_id,driver_vehicle_block_id';
        $result['checks']['uk_crew_columns'] = $ukCrewCorrect;
        if (!$ukCrewCorrect) {
            $result['fail_reason'] = "uk_crew columns are '{$ukCrewStr}', expected 'contractor_id,driver_vehicle_block_id'";
            return $result;
        }

        $result['pass'] = true;
        return $result;
    }
}

// Scenario 4: Fully compatible schema
echo "  4. Fully compatible schema (all required elements present) ... ";
$schema4 = new SchemaChecker([
    'crews' => [
        'columns' => ['id', 'contractor_id', 'driver_id', 'vehicle_id', 'driver_vehicle_block_id', 'updated_by_user_id', 'updated_by_role'],
        'indices' => [
            'uk_crew' => ['contractor_id', 'driver_vehicle_block_id'],
        ],
    ],
]);
$r4 = $schema4->isCompatible();
test('Fully compatible schema passes', true, $r4['pass'], 'All required elements present should pass');
echo $r4['pass'] ? "PASS\n" : "FAIL\n";

// Scenario 5: Missing driver_vehicle_block_id
echo "  5. Missing driver_vehicle_block_id column ... ";
$schema5 = new SchemaChecker([
    'crews' => [
        'columns' => ['id', 'contractor_id', 'driver_id', 'vehicle_id', 'updated_by_user_id', 'updated_by_role'],
        'indices' => [
            'uk_crew' => ['contractor_id', 'driver_vehicle_block_id'],
        ],
    ],
]);
$r5 = $schema5->isCompatible();
test('Missing driver_vehicle_block_id fails', false, $r5['pass'], 'Must fail without driver_vehicle_block_id');
echo !$r5['pass'] ? "PASS\n" : "FAIL\n";

// Scenario 6: Missing updated_by_user_id
echo "  6. Missing updated_by_user_id column ... ";
$schema6 = new SchemaChecker([
    'crews' => [
        'columns' => ['id', 'contractor_id', 'driver_id', 'vehicle_id', 'driver_vehicle_block_id', 'updated_by_role'],
        'indices' => [
            'uk_crew' => ['contractor_id', 'driver_vehicle_block_id'],
        ],
    ],
]);
$r6 = $schema6->isCompatible();
test('Missing updated_by_user_id fails', false, $r6['pass'], 'Must fail without updated_by_user_id');
echo !$r6['pass'] ? "PASS\n" : "FAIL\n";

// Scenario 7: Missing updated_by_role
echo "  7. Missing updated_by_role column ... ";
$schema7 = new SchemaChecker([
    'crews' => [
        'columns' => ['id', 'contractor_id', 'driver_id', 'vehicle_id', 'driver_vehicle_block_id', 'updated_by_user_id'],
        'indices' => [
            'uk_crew' => ['contractor_id', 'driver_vehicle_block_id'],
        ],
    ],
]);
$r7 = $schema7->isCompatible();
test('Missing updated_by_role fails', false, $r7['pass'], 'Must fail without updated_by_role');
echo !$r7['pass'] ? "PASS\n" : "FAIL\n";

// Scenario 8: Wrong uk_crew columns (extra column)
echo "  8. Wrong uk_crew columns (contractor_id,driver_vehicle_block_id,driver_id) ... ";
$schema8 = new SchemaChecker([
    'crews' => [
        'columns' => ['id', 'contractor_id', 'driver_id', 'vehicle_id', 'driver_vehicle_block_id', 'updated_by_user_id', 'updated_by_role'],
        'indices' => [
            'uk_crew' => ['contractor_id', 'driver_vehicle_block_id', 'driver_id'],
        ],
    ],
]);
$r8 = $schema8->isCompatible();
test('Wrong uk_crew columns fails', false, $r8['pass'], 'Must fail when uk_crew has extra/different columns');
echo !$r8['pass'] ? "PASS\n" : "FAIL\n";

// Scenario 9: contractor_id_2 exists but schema is still compatible
echo "  9. contractor_id_2 index still exists (non-conflicting) ... ";
$schema9 = new SchemaChecker([
    'crews' => [
        'columns' => ['id', 'contractor_id', 'driver_id', 'vehicle_id', 'driver_vehicle_block_id', 'updated_by_user_id', 'updated_by_role'],
        'indices' => [
            'uk_crew' => ['contractor_id', 'driver_vehicle_block_id'],
            'contractor_id_2' => ['contractor_id', 'driver_id'],
        ],
    ],
]);
$r9 = $schema9->isCompatible();
test('contractor_id_2 still exists passes', true, $r9['pass'], 'contractor_id_2 is non-conflicting — schema is compatible');
echo $r9['pass'] ? "PASS\n" : "FAIL\n";

// Scenario 10: crews table doesn't exist
echo "  10. crews table does not exist ... ";
$schema10 = new SchemaChecker([]);
$r10 = $schema10->isCompatible();
test('Missing crews table fails', false, $r10['pass'], 'Must fail when crews table is absent');
echo !$r10['pass'] ? "PASS\n" : "FAIL\n";

// ======== Summary ========
$total = $passCount + $failCount;
echo "\n=== Results: {$passCount}/{$total} PASS, {$failCount}/{$total} FAIL ===\n";

exit($failCount > 0 ? 1 : 0);
