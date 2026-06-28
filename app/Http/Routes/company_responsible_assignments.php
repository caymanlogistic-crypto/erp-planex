<?php

require_once base_path('app/Http/Controllers/Company/ResponsibleAssignmentController.php');

$controller = new \App\Http\Controllers\Company\ResponsibleAssignmentController($config, $db);

$router->get('/company/responsible-assignments', [$controller, 'index']);
$router->post('/company/responsible-assignments/reassign', [$controller, 'reassign']);
