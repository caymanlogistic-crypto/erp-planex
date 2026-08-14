<?php

require_once base_path('app/Http/Controllers/Company/FinanceDdsCategoryController.php');

$controller = new \App\Http\Controllers\Company\FinanceDdsCategoryController($config, $db);

// Kept at the existing URL for backward compatibility; UI label is now Financial Structure.
$router->get('/company/finance/settings/dds-categories', [$controller, 'index']);
$router->get('/company/finance/settings/dds-categories/create', [$controller, 'createForm']);
$router->post('/company/finance/settings/dds-categories/create', [$controller, 'createSubmit']);
$router->get('/company/finance/settings/dds-categories/edit', [$controller, 'editForm']);
$router->post('/company/finance/settings/dds-categories/edit', [$controller, 'editSubmit']);
$router->post('/company/finance/settings/dds-categories/active-toggle', [$controller, 'activeToggle']);
$router->post('/company/finance/settings/dds-categories/cfu/save', [$controller, 'cfuSave']);
$router->post('/company/finance/settings/dds-categories/cfu/toggle', [$controller, 'cfuToggle']);
$router->post('/company/finance/settings/dds-categories/links/save', [$controller, 'linksSave']);
