<?php

declare(strict_types=1);

function invoicePolicyGuard(bool $ok, string $message): void
{
    if (!$ok) {
        throw new RuntimeException('FAIL: ' . $message);
    }
}

$root = dirname(__DIR__);
$form = file_get_contents($root . '/app/View/partials/company_invoice_create_form.php');
$create = file_get_contents($root . '/app/Http/Controllers/Company/InvoiceActions/create_submit.php');
$edit = file_get_contents($root . '/app/Http/Controllers/Company/InvoiceActions/modal_edit_submit.php');
$editForm = file_get_contents($root . '/app/Http/Controllers/Company/InvoiceActions/modal_edit_form.php');
$routes = file_get_contents($root . '/app/Http/Routes/company_finance_invoices.php');
$controller = file_get_contents($root . '/app/Http/Controllers/Company/FinanceInvoiceController.php');
$calendar = file_get_contents($root . '/app/Service/FinancePaymentCalendarService.php');
$invoicePage = file_get_contents($root . '/app/View/pages/company_finance_invoices.php');
$invoiceModal = file_get_contents($root . '/app/View/partials/company_invoice_modal_view.php');
$cleanup = file_get_contents($root . '/database/migrations-local/072_invoice_obligation_cleanup.sql');

invoicePolicyGuard(!str_contains($form, 'Тип контрагента'), 'manual counterparty type field removed');
invoicePolicyGuard(!str_contains($form, 'Наименование (вручную)'), 'manual counterparty name removed');
invoicePolicyGuard(!str_contains($form, 'name="planned_payment_date"'), 'manual planned payment date removed');
invoicePolicyGuard(!str_contains($form, 'name="status"'), 'manual invoice status removed');
invoicePolicyGuard(!str_contains($form, '/counterparty-list'), 'counterparty AJAX loader removed');
invoicePolicyGuard(str_contains($form, "dir.value === 'INCOMING' ? 'contractor' : 'client'"), 'direction derives counterparty type in UI');
invoicePolicyGuard(str_contains($form, "'client' =>") && str_contains($form, "'contractor' =>"), 'counterparty catalogs are server-provided');

foreach ([$create, $edit] as $source) {
    invoicePolicyGuard(!str_contains($source, "\$_POST['planned_payment_date']"), 'controller ignores posted planned payment date');
    invoicePolicyGuard(!str_contains($source, "\$_POST['status']"), 'controller ignores posted invoice status');
    invoicePolicyGuard(!str_contains($source, "\$_POST['counterparty_entity_type']"), 'controller ignores posted counterparty type');
    invoicePolicyGuard(!str_contains($source, "\$_POST['counterparty_name']"), 'controller ignores manual counterparty name');
    invoicePolicyGuard(str_contains($source, "? 'client'"), 'outgoing invoice derives client type');
    invoicePolicyGuard(str_contains($source, "? 'issued' : 'received'"), 'invoice base lifecycle is system-derived');
}
invoicePolicyGuard(str_contains($edit, 'После поступления оплаты нельзя менять контрагента'), 'paid invoice counterparty protected');
invoicePolicyGuard(str_contains($edit, 'нельзя менять привязки счёта к платёжным обязательствам'), 'paid invoice obligation links protected');
invoicePolicyGuard(str_contains($editForm, 'Аннулированный счёт нельзя редактировать'), 'cancelled invoice edit blocked');

invoicePolicyGuard(!str_contains($routes, 'counterparty-list'), 'obsolete counterparty route removed');
invoicePolicyGuard(!str_contains($controller, 'counterpartyList'), 'obsolete counterparty action removed');
invoicePolicyGuard(!is_file($root . '/app/Http/Controllers/Company/InvoiceActions/counterparty_list.php'), 'obsolete counterparty loader deleted');

invoicePolicyGuard(str_contains($calendar, 'FROM finance_obligations o'), 'payment calendar uses obligations source of truth');
invoicePolicyGuard(!str_contains($calendar, 'planned_payment_date'), 'payment calendar no longer uses invoice planned date');
invoicePolicyGuard(!str_contains($invoicePage, 'planned_payment_date'), 'invoice registry no longer uses invoice planned date');
invoicePolicyGuard(!str_contains($invoiceModal, 'planned_payment_date'), 'invoice modal no longer uses invoice planned date');
invoicePolicyGuard(str_contains($invoicePage, 'display_due_text'), 'invoice registry displays obligation-derived deadline');

invoicePolicyGuard(str_contains($cleanup, "SET planned_payment_date = NULL"), 'legacy manual planned dates are cleared');
invoicePolicyGuard(str_contains($cleanup, "WHERE status = 'draft'"), 'legacy draft statuses are normalized');

echo "FINANCE_INVOICE_FORM_POLICY_OK\n";