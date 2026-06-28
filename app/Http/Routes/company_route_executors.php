<?php

require_once base_path('app/Http/Controllers/Company/RouteExecutorController.php');

$controller = new \App\Http\Controllers\Company\RouteExecutorController($config, $db);

$router->get('/company/route-executors', [$controller, 'index']);
$router->get('/company/route-executors/create', [$controller, 'createForm']);
$router->post('/company/route-executors/create', [$controller, 'createSubmit']);
$router->get('/company/route-executors/{id}', [$controller, 'view']);
$router->get('/company/route-executors/{id}/edit', [$controller, 'editForm']);
$router->post('/company/route-executors/{id}/edit', [$controller, 'editSubmit']);
$router->post('/company/route-executors/{id}/archive', [$controller, 'archive']);
