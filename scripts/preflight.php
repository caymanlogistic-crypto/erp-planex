<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

define('BASE_PATH', dirname(__DIR__));
define('STORAGE_PATH', BASE_PATH . DIRECTORY_SEPARATOR . 'storage');

require_once BASE_PATH . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Support' . DIRECTORY_SEPARATOR . 'environment.php';

loadEnvFileNonOverwriting(BASE_PATH . DIRECTORY_SEPARATOR . '.env');

$errors = [];
$warnings = [];

if (version_compare(PHP_VERSION, '8.1.0', '<')) {
    $errors[] = 'PHP 8.1 or newer is required; found ' . PHP_VERSION . '.';
}

foreach (['pdo', 'pdo_mysql', 'openssl', 'mbstring', 'zip', 'simplexml', 'fileinfo', 'imap'] as $extension) {
    if (!extension_loaded($extension)) {
        $errors[] = 'Required PHP extension is missing: ' . $extension . '.';
    }
}

$environment = strtolower((string) (getenv('APP_ENV') ?: 'local'));
if ($environment === 'local') {
    $errors[] = 'APP_ENV must not be local in production.';
}
if (filter_var(getenv('APP_DEBUG') ?: 'false', FILTER_VALIDATE_BOOLEAN)) {
    $errors[] = 'APP_DEBUG must be false in production.';
}
if (filter_var(getenv('APP_ALLOW_DEV_SEED') ?: 'false', FILTER_VALIDATE_BOOLEAN)) {
    $errors[] = 'APP_ALLOW_DEV_SEED must be false in production.';
}

$encryptionKey = (string) (getenv('APP_ENCRYPTION_KEY') ?: '');
if (strlen($encryptionKey) < 32) {
    $errors[] = 'APP_ENCRYPTION_KEY must contain at least 32 characters.';
}

foreach (['DB_HOST', 'DB_DATABASE', 'DB_USERNAME'] as $name) {
    if (trim((string) (getenv($name) ?: '')) === '') {
        $errors[] = $name . ' is not configured.';
    }
}

$poolJson = trim((string) (getenv('COMPANY_DB_POOL_JSON') ?: ''));
if ($poolJson === '') {
    $warnings[] = 'COMPANY_DB_POOL_JSON is empty; new tenant provisioning will be unavailable.';
} else {
    $pool = json_decode($poolJson, true);
    if (!is_array($pool) || json_last_error() !== JSON_ERROR_NONE) {
        $errors[] = 'COMPANY_DB_POOL_JSON is not valid JSON.';
    } else {
        foreach ($pool as $index => $entry) {
            if (!is_array($entry) || empty($entry['database']) || empty($entry['username']) || !array_key_exists('password', $entry)) {
                $errors[] = 'COMPANY_DB_POOL_JSON entry #' . ($index + 1) . ' is incomplete.';
            }
        }
    }
}

if (!is_dir(STORAGE_PATH)) {
    $errors[] = 'Storage directory does not exist: ' . STORAGE_PATH;
} elseif (!is_writable(STORAGE_PATH)) {
    $errors[] = 'Storage directory is not writable by the PHP user.';
}

foreach ($warnings as $warning) {
    fwrite(STDOUT, '[WARN] ' . $warning . PHP_EOL);
}
foreach ($errors as $error) {
    fwrite(STDERR, '[FAIL] ' . $error . PHP_EOL);
}

if ($errors !== []) {
    fwrite(STDERR, 'Preflight failed with ' . count($errors) . ' error(s).' . PHP_EOL);
    exit(1);
}

fwrite(STDOUT, 'Preflight passed.' . PHP_EOL);
