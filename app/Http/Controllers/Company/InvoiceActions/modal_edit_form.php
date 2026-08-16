<?php

use App\Core\Database;
use App\Service\FinanceInvoiceService;
use App\Service\FinanceObligationService;

requireRole(['company_owner']);
$invoice=null; $formError=null; $routes=[]; $routePayments=[]; $clients=[]; $contractors=[];

try {
    $companyId=(int)(getSessionCompanyId()??0);
    $company=$db->fetch('SELECT * FROM companies WHERE id=?',[$companyId]);
    if(!$company||$company['status']!=='active') throw new RuntimeException('Компания недоступна.');
    $localPdo=(new Database(companyDatabaseConfig($config,$company)))->connection();
    applyLocalMigrations($localPdo);
    FinanceObligationService::syncAllLinearRoutes($localPdo);
    $invoiceId=(int)$id;
    $invoice=FinanceInvoiceService::fetchInvoiceById($localPdo,$invoiceId);
    if(!$invoice) throw new RuntimeException('Счёт не найден.');
    $clients=FinanceInvoiceService::fetchClientsForSelect($localPdo);
    $contractors=FinanceInvoiceService::fetchContractorsForSelect($localPdo);
} catch(Throwable $e) {
    $formError=$e->getMessage();
}
$isEdit=true;
require base_path('app/View/partials/company_invoice_create_form.php');
