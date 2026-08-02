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
require_once __DIR__ . '/../app/Support/environment.php';

$envFile = BASE_PATH . DIRECTORY_SEPARATOR . '.env';
loadEnvFileNonOverwriting($envFile);

$config = [
    'app'      => require_once __DIR__ . '/../config/app.php',
    'database' => require_once __DIR__ . '/../config/database.php',
];

return $config;
