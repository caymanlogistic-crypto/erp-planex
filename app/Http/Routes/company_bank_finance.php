<?php

require_once base_path('app/Http/Controllers/Company/BankFinanceController.php');

$controller = new \App\Http\Controllers\Company\BankFinanceController($config, $db);

$router->get('/company/finance/bank-accounts', [$controller, 'index']);
$router->get('/company/bank-statement-settings', [$controller, 'bankStatementSettings']);
$router->post('/company/finance/bank-accounts/import', [$controller, 'import']);
$router->post('/company/finance/bank-accounts/settings', [$controller, 'settings']);
$router->post('/company/finance/bank-accounts/import/delete', [$controller, 'deleteImport']);
$router->get('/company/finance/bank-accounts/reconciliation/json', [$controller, 'reconciliationJson']);
