<?php
require_once base_path('app/Http/Controllers/Company/ProductionCalendarController.php');
$controller = new \App\Http\Controllers\Company\ProductionCalendarController($config, $db);
$router->get('/company/misc/production-calendar', [$controller, 'index']);
$router->post('/company/misc/production-calendar/year/create', [$controller, 'createYear']);
$router->post('/company/misc/production-calendar/day/save', [$controller, 'saveDay']);
$router->post('/company/misc/production-calendar/year/confirm', [$controller, 'confirmYear']);
