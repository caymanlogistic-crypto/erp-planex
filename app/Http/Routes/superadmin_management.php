<?php

require_once base_path('app/Http/Controllers/Superadmin/ManagementController.php');

$m = new \App\Http\Controllers\Superadmin\ManagementController($config, $db);

// Company status actions
$router->post('/superadmin/companies/{id}/activate', [$m, 'activate']);
$router->post('/superadmin/companies/{id}/block', [$m, 'block']);
$router->post('/superadmin/companies/{id}/archive', [$m, 'archive']);
$router->post('/superadmin/companies/{id}/deactivate', [$m, 'deactivate']);

// Company monitoring / directory browsing
$router->get('/superadmin/companies/{id}/users', [$m, 'users']);
$router->get('/superadmin/companies/{id}/directories', [$m, 'directories']);
$router->get('/superadmin/companies/{id}/clients', [$m, 'clients']);
$router->get('/superadmin/companies/{id}/contractors', [$m, 'contractors']);
$router->get('/superadmin/companies/{id}/drivers', [$m, 'drivers']);
$router->get('/superadmin/companies/{id}/vehicles', [$m, 'vehicles']);
$router->get('/superadmin/companies/{id}/crews', [$m, 'crews']);
$router->get('/superadmin/companies/{id}/documents', [$m, 'documents']);
$router->get('/superadmin/companies/{company_id}/documents/{document_id}/download', [$m, 'documentDownload']);
$router->get('/superadmin/companies/{id}/access-grants', [$m, 'accessGrants']);
$router->post('/superadmin/companies/{id}/access-grants/{grant_id}/revoke', [$m, 'revokeGrant']);

// Logist management
$router->get('/superadmin/companies/{id}/users/logists/create', [$m, 'logistCreateForm']);
$router->get('/superadmin/companies/{company_id}/users/logists/{user_id}', [$m, 'logistView']);
$router->get('/superadmin/companies/{company_id}/users/logists/{user_id}/edit', [$m, 'logistEditForm']);
$router->post('/superadmin/companies/{company_id}/users/logists/{user_id}/edit', [$m, 'logistEditSubmit']);
$router->post('/superadmin/companies/{company_id}/users/logists/{user_id}/reset-password', [$m, 'logistResetPassword']);
$router->post('/superadmin/companies/{company_id}/users/logists/{user_id}/activate', [$m, 'logistActivate']);
$router->post('/superadmin/companies/{company_id}/users/logists/{user_id}/block', [$m, 'logistBlock']);
$router->post('/superadmin/companies/{company_id}/users/logists/{user_id}/archive', [$m, 'logistArchive']);
$router->post('/superadmin/companies/{id}/users/logists/create', [$m, 'logistCreateSubmit']);
