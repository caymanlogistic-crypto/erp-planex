<?php

require_once base_path('app/Http/Controllers/AuthController.php');

$controller = new \App\Http\Controllers\AuthController($config, $db);

$router->get('/login', [$controller, 'showLogin']);
$router->post('/login', [$controller, 'login']);
$router->get('/logout', [$controller, 'logout']);
