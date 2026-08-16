<?php

use App\Core\Database;
use App\Service\FinanceInvoiceService;
use App\Service\FinanceObligationService;

requireRole(['company_owner']);

$invoice = null;
$links = [];
$error = null;

try {
    $companyId = (int)(getSessionCompanyId() ?? 0);
    $company = $db->fetch('SELECT * FROM companies WHERE id = ?', [$companyId]);
    if (!$company || ($company['status'] ?? '') !== 'active') {
        throw new RuntimeException('Компания недоступна.');
    }

    $localPdo = (new Database(companyDatabaseConfig($config, $company)))->connection();
    applyLocalMigrations($localPdo);
    FinanceObligationService::syncAllLinearRoutes($localPdo);

    $invoiceId = (int)$id;
    $invoice = FinanceInvoiceService::fetchInvoiceById($localPdo, $invoiceId);
    if (!$invoice) {
        http_response_code(404);
        throw new RuntimeException('Счёт не найден.');
    }
    $links = FinanceObligationService::invoiceLinks($localPdo, $invoiceId);

    $roleCode = (string)(($_SESSION['user'] ?? [])['role_code'] ?? '');
    $isCancelled = ($invoice['cancelled_at'] ?? null) !== null || ($invoice['status'] ?? '') === 'cancelled';
    $canEdit = $roleCode === 'company_owner' && !$isCancelled;
    $canDelete = $roleCode === 'company_owner';
} catch (Throwable $e) {
    $error = $e->getMessage();
}

require base_path('app/View/partials/company_invoice_modal_view.php');