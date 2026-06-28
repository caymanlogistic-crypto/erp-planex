<?php

require_once base_path('app/Http/Controllers/Company/ClientController.php');

$controller = new \App\Http\Controllers\Company\ClientController($config, $db);

$router->get('/company/clients', [$controller, 'index']);
$router->get('/company/clients/create', [$controller, 'createForm']);
$router->post('/company/clients/create', [$controller, 'createSubmit']);
$router->get('/company/clients/{id}', [$controller, 'show']);
$router->get('/company/clients/{id}/edit', [$controller, 'editForm']);
$router->post('/company/clients/{id}/edit', [$controller, 'editSubmit']);
$router->post('/company/clients/{id}/archive', [$controller, 'archive']);
$router->get('/company/clients/{id}/modal-view', [$controller, 'modalView']);
$router->get('/company/clients/{id}/modal-edit', [$controller, 'modalEditForm']);
$router->post('/company/clients/{id}/modal-edit', [$controller, 'modalEditSubmit']);
$router->post('/company/clients/{id}/modal-archive', [$controller, 'modalArchive']);
