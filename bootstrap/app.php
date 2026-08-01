<?php

/**
 * ERP PLANEX — Bootstrap
 *
 * Определяет базовые пути, загружает .env и helper-функции,
 * формирует конфигурацию приложения.
 */

define('BASE_PATH', dirname(__DIR__));
define('STORAGE_PATH', BASE_PATH . DIRECTORY_SEPARATOR . 'storage');

require_once __DIR__ . '/../app/Support/helpers.php';

$envFile = BASE_PATH . DIRECTORY_SEPARATOR . '.env';

if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        $parts = explode('=', $line, 2);
        if (count($parts) === 2) {
            $name  = trim($parts[0]);
            $value = trim($parts[1]);
            // Non-overwrite semantics: an existing process environment variable
            // always takes precedence over a value loaded from .env.
            if (getenv($name) === false) {
                putenv("$name=$value");
                $_ENV[$name] = $value;
            }
        }
    }
}

$config = [
    'app'      => require_once __DIR__ . '/../config/app.php',
    'database' => require_once __DIR__ . '/../config/database.php',
];

return $config;
