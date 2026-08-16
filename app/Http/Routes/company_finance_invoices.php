<?php

require_once base_path('app/Http/Controllers/Company/FinanceInvoiceController.php');

$controller = new \App\Http\Controllers\Company\FinanceInvoiceController($config, $db);

$router->get('/company/finance/invoices', [$controller, 'index']);
$router->get('/company/finance/receivables', [$controller, 'receivables']);
$router->post('/company/finance/invoices/create', [$controller, 'createSubmit']);
$router->get('/company/finance/invoices/{id}/modal-view', [$controller, 'modalView']);
$router->get('/company/finance/invoices/{id}/modal-edit', [$controller, 'modalEditForm']);
$router->post('/company/finance/invoices/{id}/modal-edit', [$controller, 'modalEditSubmit']);
$router->post('/company/finance/invoices/{id}/modal-delete', [$controller, 'modalDelete']);
$router->get('/company/finance/invoices/obligations', [$controller, 'obligations']);
$router->get('/company/finance/invoices/{id}/history', [$controller, 'history']);
$router->get('/company/finance/invoices/route-payments', [$controller, 'routePayments']);