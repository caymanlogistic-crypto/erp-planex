<?php

require_once base_path('app/Http/Controllers/Company/DriverController.php');

$controller = new \App\Http\Controllers\Company\DriverController($config, $db);

$router->get('/company/drivers', [$controller, 'index']);
$router->get('/company/drivers/create', [$controller, 'createForm']);
$router->post('/company/drivers/create', [$controller, 'createSubmit']);
$router->post('/company/drivers/modal-create', [$controller, 'modalCreate']);
$router->get('/company/drivers/{id}', [$controller, 'show']);
$router->get('/company/drivers/{id}/edit', [$controller, 'editForm']);
$router->post('/company/drivers/{id}/edit', [$controller, 'editSubmit']);
$router->post('/company/drivers/{id}/archive', [$controller, 'archive']);
$router->get('/company/drivers/{id}/modal-view', [$controller, 'modalView']);
$router->get('/company/drivers/{id}/modal-edit', [$controller, 'modalEditForm']);
$router->post('/company/drivers/{id}/modal-edit', [$controller, 'modalEditSubmit']);
$router->post('/company/drivers/{id}/modal-delete', [$controller, 'modalDelete']);

// Phones
$router->post('/company/drivers/{driver_id}/phones/create', [$controller, 'phoneCreate']);
$router->post('/company/drivers/{driver_id}/phones/{phone_id}/edit', [$controller, 'phoneEdit']);
$router->post('/company/drivers/{driver_id}/phones/{phone_id}/delete', [$controller, 'phoneDelete']);
$router->post('/company/drivers/{driver_id}/phones/{phone_id}/set-main', [$controller, 'phoneSetMain']);

// Documents
$router->post('/company/drivers/{driver_id}/documents/upload', [$controller, 'documentUpload']);
$router->post('/company/drivers/{driver_id}/documents/{doc_id}/delete', [$controller, 'documentDelete']);
