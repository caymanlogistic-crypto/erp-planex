<?php

require_once base_path('app/Http/Controllers/Superadmin/DeletedDataController.php');

$c = new \App\Http\Controllers\Superadmin\DeletedDataController($config, $db);

$router->get('/superadmin/deleted-data', [$c, 'index']);
$router->get('/superadmin/deleted-data/{id}', [$c, 'view']);
$router->post('/superadmin/deleted-data/{id}/restore', [$c, 'restore']);
