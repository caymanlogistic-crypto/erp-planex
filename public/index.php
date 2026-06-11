<?php

/**
 * ERP PLANEX — Entry Point
 *
 * Минимальная техническая точка входа.
 * Бизнес-маршруты, БД, авторизация — не подключаются.
 */

$config = require_once __DIR__ . '/../bootstrap/app.php';

header('Content-Type: text/plain; charset=utf-8');
echo 'ERP PLANEX technical skeleton is running';
