<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__);
if (!defined('BASE_PATH')) {
    define('BASE_PATH', $root);
}
if (!defined('STORAGE_PATH')) {
    define('STORAGE_PATH', $root . DIRECTORY_SEPARATOR . 'storage');
}

require_once $root . '/app/Support/helpers.php';
require_once $root . '/app/Support/entrypoint_dependencies.php';

$requiredClasses = [
    App\Service\LinearTripDocumentUploadException::class,
    App\Service\LinearTripEditTokenService::class,
    App\Service\LinearTripDocumentUploadService::class,
    App\Service\LinearTripEditSaveService::class,
];

$failed = 0;
echo "=== P14 Linear Trip Runtime Dependencies ===\n";
foreach ($requiredClasses as $className) {
    $loaded = class_exists($className);
    echo ($loaded ? 'PASS' : 'FAIL') . ' - ' . $className . "\n";
    if (!$loaded) {
        $failed++;
    }
}

$manifest = file_get_contents($root . '/app/Support/entrypoint_dependencies.php');
$orderedFiles = [
    'LinearTripDocumentUploadException.php',
    'LinearTripEditTokenService.php',
    'LinearTripDocumentUploadService.php',
    'LinearTripEditSaveService.php',
];
$lastPosition = -1;
foreach ($orderedFiles as $fileName) {
    $position = strpos((string) $manifest, $fileName);
    $ordered = $position !== false && $position > $lastPosition;
    echo ($ordered ? 'PASS' : 'FAIL') . ' - manifest order ' . $fileName . "\n";
    if (!$ordered) {
        $failed++;
    }
    if ($position !== false) {
        $lastPosition = $position;
    }
}

echo 'TOTAL=' . (count($requiredClasses) + count($orderedFiles)) . '; FAILED=' . $failed . "\n";
exit($failed === 0 ? 0 : 1);
