<?php

use App\Core\Database;
use App\Service\FinanceInvoiceService;

requireRole(['company_owner']);

$invoice = null;
$error = null;
$routes = [];
$routePayments = [];

try {
    $companyId = (int)(getSessionCompanyId() ?? 0);
    $company = $db->fetch('SELECT * FROM companies WHERE id = ?', [$companyId]);

    if (!$company || $company['status'] !== 'active') {
        throw new \RuntimeException('Компания недоступна.');
    }

    $localConfig = companyDatabaseConfig($config, $company);
    $localDb = new Database($localConfig);
    $localPdo = $localDb->connection();
    applyLocalMigrations($localPdo);

    $invoiceId = (int) $id;
    $invoice = FinanceInvoiceService::fetchInvoiceById($localPdo, $invoiceId);

    if (!$invoice) {
        throw new \RuntimeException('Счёт не найден.');
    }

    $user = $_SESSION['user'] ?? [];
    $links = FinanceInvoiceService::fetchInvoiceLinks($localPdo, $invoiceId);
    $linkRouteId = !empty($links[0]['linear_route_id']) ? (int) $links[0]['linear_route_id'] : null;
    $linkPaymentId = !empty($links[0]['linear_route_payment_id']) ? (int) $links[0]['linear_route_payment_id'] : null;
    $linkSide = $links[0]['side'] ?? null;

    $routes = FinanceInvoiceService::fetchRoutesForSelect($localPdo, $user);

    if ($linkRouteId) {
        $routePayments = FinanceInvoiceService::fetchRoutePaymentsForSelect($localPdo, $linkRouteId);
    }

    $clients = FinanceInvoiceService::fetchClientsForSelect($localPdo);
    $contractors = FinanceInvoiceService::fetchContractorsForSelect($localPdo);
} catch (\Throwable $e) {
    $error = $e->getMessage();
}

$isEdit = true;
require base_path('app/View/partials/company_invoice_create_form.php');
