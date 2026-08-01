<?php

/**
 * PHASE 0.9C — Environment precedence regression test.
 *
 * Verifies that bootstrap/app.php does NOT overwrite an existing process
 * environment variable with a value from .env (non-overwrite semantics).
 *
 * Usage:
 *   php tests/env_precedence_test.php            — run all scenarios (isolated subprocesses)
 *   php tests/env_precedence_test.php S1         — run a single scenario in this process
 *
 * No secret values are printed. Only environment MODE indicators
 * (production / local / empty) and non-secret marker values are emitted.
 */

// --- Scenario runner mode (invoked as a subprocess) ---
if (isset($argv[1]) && preg_match('/^S[0-9]$/', (string) $argv[1])) {
    $scenario = $argv[1];
    // Set up the process environment per scenario BEFORE requiring bootstrap.
    switch ($scenario) {
        case 'S1':
            putenv('APP_ENV=production'); // process env present + .env likely APP_ENV=local
            break;
        case 'S2':
            putenv('APP_ENV');            // ensure APP_ENV absent from process env
            break;
        case 'S3':
            putenv('APP_ENV=');           // process APP_ENV explicitly empty
            break;
        case 'S4':
            putenv('PHASE09C_PRECEDENCE=from-process'); // var not defined in .env
            break;
        case 'S5':
            putenv('APP_NAME=process-name'); // var defined in .env (non-secret)
            break;
    }

    require __DIR__ . '/../bootstrap/app.php';

    $result = [];
    $result['scenario'] = $scenario;
    $result['app_env'] = getenv('APP_ENV');
    $result['marker'] = getenv('PHASE09C_PRECEDENCE');
    $result['app_name'] = getenv('APP_NAME');
    echo json_encode($result);
    exit(0);
}

// --- Test runner mode (parent process) ---
$pass = 0;
$fail = 0;
$total = 0;
$phpBin = defined('PHP_BINARY') ? PHP_BINARY : 'php';

function runScenario(string $phpBin, string $scenario): array {
    $cmd = sprintf('%s %s %s 2>&1', escapeshellarg($phpBin), escapeshellarg(__FILE__), $scenario);
    $out = shell_exec($cmd);
    $data = json_decode((string) $out, true);
    if (!is_array($data)) {
        return ['scenario' => $scenario, 'raw' => (string) $out];
    }
    return $data;
}

$cases = [
    'S1' => ['desc' => 'process APP_ENV=production + .env APP_ENV=local => production',
             'check' => fn($d) => ($d['app_env'] ?? null) === 'production'],
    'S2' => ['desc' => 'process APP_ENV absent + .env APP_ENV=local => local applied (non-empty)',
             'check' => fn($d) => isset($d['app_env']) && $d['app_env'] !== '' && $d['app_env'] !== false],
    'S3' => ['desc' => 'process APP_ENV empty => preserved (empty not overwritten by .env)',
             'check' => fn($d) => ($d['app_env'] ?? 'SET') === ''],
    'S4' => ['desc' => 'unrelated process var (not in .env) preserved',
             'check' => fn($d) => ($d['marker'] ?? null) === 'from-process'],
    'S5' => ['desc' => 'process APP_NAME wins over .env APP_NAME',
             'check' => fn($d) => ($d['app_name'] ?? null) === 'process-name'],
];

echo "=== ENV PRECEDENCE REGRESSION TEST ===\n";
foreach ($cases as $sc => $case) {
    $total++;
    $d = runScenario($phpBin, $sc);
    $ok = $case['check']($d);
    $status = $ok ? 'PASS' : 'FAIL';
    echo sprintf("  [%s] %s: %s\n", $status, $sc, $case['desc']);
    if ($ok) { $pass++; } else { $fail++; }
}
echo "RESULT: $pass/$total passed, $fail failed\n";
exit($fail === 0 ? 0 : 1);
