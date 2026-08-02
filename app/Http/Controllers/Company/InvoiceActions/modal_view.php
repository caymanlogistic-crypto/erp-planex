<?php

use App\Core\Database;
use App\Service\FinanceInvoiceService;

requireRole(['company_owner']);

$invoice = null;
$links = [];
$error = null;

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
        http_response_code(404);
        throw new \RuntimeException('Счёт не найден.');
    }

    $links = FinanceInvoiceService::fetchInvoiceLinks($localPdo, $invoiceId);

    $user = $_SESSION['user'] ?? [];
    $roleCode = (string) ($user['role_code'] ?? '');
    $canEdit = in_array($roleCode, ['company_owner'], true);
    $canDelete = $roleCode === 'company_owner';
} catch (\Throwable $e) {
    $error = $e->getMessage();
}

require base_path('app/View/partials/company_invoice_modal_view.php');
