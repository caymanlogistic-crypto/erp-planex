<?php

require_once base_path('app/Http/Controllers/Company/FinanceMatchingRuleController.php');

$controller = new \App\Http\Controllers\Company\FinanceMatchingRuleController($config, $db);

$router->get('/company/finance/settings/matching-rules', [$controller, 'index']);
$router->get('/company/finance/settings/matching-rules/create', [$controller, 'createForm']);
$router->post('/company/finance/settings/matching-rules/create', [$controller, 'createSubmit']);
$router->get('/company/finance/settings/matching-rules/edit', [$controller, 'editForm']);
$router->post('/company/finance/settings/matching-rules/edit', [$controller, 'editSubmit']);
$router->post('/company/finance/settings/matching-rules/toggle', [$controller, 'toggle']);
$router->post('/company/finance/settings/matching-rules/reorder', [$controller, 'reorder']);
$router->get('/company/finance/settings/matching-rules/preview', [$controller, 'preview']);
$router->get('/company/finance/settings/matching-rules/test', [$controller, 'testOnTransaction']);
