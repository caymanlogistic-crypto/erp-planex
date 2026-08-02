<?php

require_once base_path('app/Http/Controllers/Superadmin/DbUsageController.php');

$c = new \App\Http\Controllers\Superadmin\DbUsageController($config, $db);

$router->get('/superadmin/db-usage', [$c, 'index']);
