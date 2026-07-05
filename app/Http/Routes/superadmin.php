<?php

require_once base_path('app/Http/Controllers/Superadmin/CompanyController.php');
require_once base_path('app/Http/Controllers/Superadmin/CompanyOwnerController.php');

$companyController = new \App\Http\Controllers\Superadmin\CompanyController($config, $db);
$ownerController = new \App\Http\Controllers\Superadmin\CompanyOwnerController($config, $db);

$router->get('/superadmin/companies', [$companyController, 'index']);
$router->get('/superadmin/companies/create', [$companyController, 'createForm']);
$router->post('/superadmin/companies/create', [$companyController, 'createSubmit']);
$router->get('/superadmin/companies/{id}', [$companyController, 'view']);
$router->get('/superadmin/companies/{id}/edit', [$companyController, 'editForm']);
$router->post('/superadmin/companies/{id}/edit', [$companyController, 'editSubmit']);

$router->get('/superadmin/companies/{id}/create-owner', [$ownerController, 'createForm']);
$router->post('/superadmin/companies/{id}/create-owner', [$ownerController, 'createSubmit']);
$router->get('/superadmin/companies/{id}/owner', [$ownerController, 'view']);
$router->get('/superadmin/companies/{id}/owner/edit', [$ownerController, 'editForm']);
$router->post('/superadmin/companies/{id}/owner/edit', [$ownerController, 'editSubmit']);
$router->post('/superadmin/companies/{id}/owner/reset-password', [$ownerController, 'resetPassword']);

$router->get('/superadmin/requisites/lookup-by-inn', [$companyController, 'lookupInn']);
$router->post('/superadmin/requisites/lookup-by-inn', [$companyController, 'lookupInn']);
