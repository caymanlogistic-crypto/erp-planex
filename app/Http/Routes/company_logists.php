<?php

require_once base_path('app/Http/Controllers/Company/LogistController.php');

$c = new \App\Http\Controllers\Company\LogistController($config, $db);

$router->get('/company/logists', [$c, 'index']);
$router->get('/company/logists/create', [$c, 'createForm']);
$router->post('/company/logists/create', [$c, 'createSubmit']);
$router->get('/company/logists/modal-create', [$c, 'modalCreateForm']);
$router->post('/company/logists/modal-create', [$c, 'modalCreateSubmit']);
$router->get('/company/logists/{id}', [$c, 'view']);
$router->get('/company/logists/{id}/edit', [$c, 'editForm']);
$router->post('/company/logists/{id}/edit', [$c, 'editSubmit']);
$router->post('/company/logists/{id}/reset-password', [$c, 'resetPassword']);
$router->get('/company/logists/{id}/modal-view', [$c, 'modalView']);
$router->get('/company/logists/{id}/modal-edit', [$c, 'modalEditForm']);
$router->post('/company/logists/{id}/modal-edit', [$c, 'modalEditSubmit']);
$router->post('/company/logists/{id}/archive', [$c, 'archive']);
