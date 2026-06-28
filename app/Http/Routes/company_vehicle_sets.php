<?php

require_once base_path('app/Http/Controllers/Company/VehicleSetController.php');

$c = new \App\Http\Controllers\Company\VehicleSetController($config, $db);

$router->get('/company/vehicle-sets', [$c, 'index']);
$router->get('/company/vehicle-sets/create', [$c, 'createForm']);
$router->post('/company/vehicle-sets/create', [$c, 'createSubmit']);
$router->get('/company/vehicle-sets/{id}', [$c, 'show']);
$router->get('/company/vehicle-sets/{id}/modal-view', [$c, 'modalView']);
$router->get('/company/vehicle-sets/{id}/modal-edit', [$c, 'modalEditForm']);
$router->post('/company/vehicle-sets/{id}/modal-edit', [$c, 'modalEditSubmit']);
$router->post('/company/vehicle-sets/{id}/modal-archive', [$c, 'modalArchive']);
