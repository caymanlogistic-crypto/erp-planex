<?php

/**
 * ERP PLANEX — Entry Point
 *
 * Минимальная техническая точка входа.
 * Бизнес-маршруты, БД, авторизация — не подключаются.
 */

$config = require_once __DIR__ . '/../bootstrap/app.php';

require_once base_path('app/View/components/alert.php');
require_once base_path('app/View/components/button.php');
require_once base_path('app/View/components/empty_state.php');
require_once base_path('app/View/components/form_actions.php');
require_once base_path('app/View/components/input.php');
require_once base_path('app/View/components/page_header.php');
require_once base_path('app/View/components/status_badge.php');
require_once base_path('app/View/components/table.php');

$pageTitle = 'UI foundation';

ob_start();
require base_path('app/View/pages/ui_demo.php');
$content = ob_get_clean();

require base_path('app/View/layouts/main.php');
