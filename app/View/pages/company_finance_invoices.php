<?php

use App\Service\FinanceInvoiceService;

$fmtDate = static function (mixed $value): string {
    $value = trim((string)$value);
    if ($value === '' || $value === '—') {
        return '—';
    }
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    return $date !== false && $date->format('Y-m-d') === $value ? $date->format('d.m.Y') : '—';
};
$fmtDue = static function (mixed $value) use ($fmtDate): string {
    $value = trim((string)$value);
    if ($value === '') {
        return '—';
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value) === 1) {
        return $fmtDate($value);
    }
    return $value;
};
$successFlash = $_SESSION['invoice_success'] ?? null;
unset($_SESSION['invoice_success']);
$currentDirection = $_GET['direction'] ?? '';
$selectedDirection = in_array($currentDirection, [FinanceInvoiceService::DIRECTION_OUTGOING, FinanceInvoiceService::DIRECTION_INCOMING], true) ? $currentDirection : '';
?>
<?php if ($company === null): ?>
<div class="notice warn">Компания не найдена.</div>
<?php elseif (($company['status'] ?? '') !== 'active'): ?>
<div class="page-head"><div class="page-head-left"><h1 class="page-title">Счета</h1><div class="page-summary"><span>Компания находится в неактивном статусе.</span></div></div></div>
<div class="notice warn">Работа со счетами недоступна.</div>
<?php elseif ($dbError !== null): ?>
<div class="page-head"><div class="page-head-left"><h1 class="page-title">Счета</h1><div class="page-summary"><span>Реестр счетов компании.</span></div></div></div>
<div class="notice warn"><?= e($dbError) ?></div>
<?php else: ?>
<div class="page-head">
    <div class="page-head-left">
        <h1 class="page-title">Счета</h1>
        <div class="page-summary"><span>Реестр счетов компании: выставленные и полученные.</span></div>
    </div>
    <div class="page-head-actions">
        <a class="btn btn-secondary" href="<?= e(app_url('/company/finance/receivables')) ?>">Дебиторка</a>
        <button type="button" class="btn btn-primary" data-open-modal="invoice-create-modal">Создать счёт</button>
    </div>
</div>

<?php if ($successFlash): ?><div class="notice success"><?= e($successFlash) ?></div><?php endif; ?>
<?php if (!empty($formError)): ?><div class="notice warn"><?= e($formError) ?></div><?php endif; ?>

<div class="tabs-page">
    <a href="<?= app_url('/company/finance/invoices') ?>" class="tab-link<?= $selectedDirection === '' ? ' is-active' : '' ?>">Все счета</a>
    <a href="<?= app_url('/company/finance/invoices?direction=' . FinanceInvoiceService::DIRECTION_OUTGOING) ?>" class="tab-link<?= $selectedDirection === FinanceInvoiceService::DIRECTION_OUTGOING ? ' is-active' : '' ?>">Выставленные</a>
    <a href="<?= app_url('/company/finance/invoices?direction=' . FinanceInvoiceService::DIRECTION_INCOMING) ?>" class="tab-link<?= $selectedDirection === FinanceInvoiceService::DIRECTION_INCOMING ? ' is-active' : '' ?>">Полученные</a>
</div>

<?php if (empty($invoices)): ?>
<div class="panel"><div class="panel-body"><div class="empty-state"><p class="empty-title">Счета ещё не созданы.</p><p class="empty-desc">Создайте первый счёт через модальное окно.</p></div></div></div>
<?php else: ?>
<div class="table-card table-card--standard">
    <div class="table-toolbar">
        <div class="found-label">Найдено: <b><?= count($invoices) ?></b> из <b><?= $invTotal ?></b> счетов (страница <?= $invPage ?>/<?= $invPages ?>)</div>
        <div class="toolbar-right"><input type="text" class="toolbar-search" placeholder="Поиск по таблице"></div>
    </div>
    <div class="table-scroll">
        <table class="table">
            <thead><tr><th>№</th><th>Дата</th><th>Направление</th><th>Контрагент</th><th>Сумма</th><th>НДС</th><th>Срок оплаты</th><th>Оплачено</th><th>Статус</th></tr></thead>
            <tbody>
            <?php foreach ($invoices as $inv):
                $displayStatus = (string)($inv['display_status'] ?? $inv['status'] ?? '');
                $dueText = $fmtDue($inv['display_due_text'] ?? '—');
            ?>
                <tr data-invoice-id="<?= (int)($inv['id'] ?? 0) ?>">
                    <td class="col-mono"><?= e($inv['number'] ?? '—') ?></td>
                    <td class="col-mono"><?= e($fmtDate($inv['invoice_date'] ?? '')) ?></td>
                    <td><?= e(FinanceInvoiceService::directionLabel($inv['direction'] ?? null)) ?></td>
                    <td><?= e(($inv['counterparty_name'] ?? '') !== '' ? $inv['counterparty_name'] : '—') ?></td>
                    <td class="col-mono"><?= e(FinanceInvoiceService::formatAmount($inv['amount'] ?? null)) ?></td>
                    <td><?= $inv['vat_rate'] !== null ? e(((float)$inv['vat_rate'] == 0.0 ? '0' : rtrim(rtrim((string)$inv['vat_rate'], '0'), '.')) . '%') : 'Без НДС' ?></td>
                    <td class="col-mono"><?= e($dueText) ?></td>
                    <td class="col-mono"><?= e(FinanceInvoiceService::formatAmount($inv['paid_amount'] ?? null)) ?></td>
                    <td><span class="<?= e(FinanceInvoiceService::statusBadgeClass($displayStatus)) ?>"><span class="dot"></span><?= e(FinanceInvoiceService::statusLabel($displayStatus)) ?></span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<div id="invoice-create-modal" class="modal-overlay" role="dialog" aria-modal="true" data-close-on-overlay="0" data-close-on-escape="0">
    <div class="modal modal-lg">
        <div class="modal-head"><span class="modal-title">Создать счёт</span><button type="button" class="modal-close" data-close-modal="invoice-create-modal">&times;</button></div>
        <?php if ($localPdo !== null): ?>
        <?php
        $isEdit = false;
        $invoice = $old ?? null;
        $clients = FinanceInvoiceService::fetchClientsForSelect($localPdo);
        $contractors = FinanceInvoiceService::fetchContractorsForSelect($localPdo);
        require base_path('app/View/partials/company_invoice_create_form.php');
        ?>
        <?php else: ?>
        <div class="modal-body"><div class="form-alert alert-error">Данные компании недоступны.</div></div>
        <?php endif; ?>
    </div>
</div>

<div id="invoice-view-modal" class="modal-overlay" role="dialog" aria-modal="true" data-close-on-overlay="1" data-close-on-escape="1">
    <div class="modal modal-lg">
        <div class="modal-head"><span class="modal-title">Счёт</span><button type="button" class="modal-close" data-close-modal="invoice-view-modal">&times;</button></div>
        <div class="modal-body" id="invoice-view-modal-body"></div>
    </div>
</div>

<div id="invoice-edit-modal" class="modal-overlay" role="dialog" aria-modal="true" data-close-on-overlay="0" data-close-on-escape="0">
    <div class="modal modal-lg">
        <div class="modal-head"><span class="modal-title">Редактировать счёт</span><button type="button" class="modal-close" data-close-modal="invoice-edit-modal">&times;</button></div>
        <div class="modal-body" id="invoice-edit-modal-body"></div>
    </div>
</div>

<?php
$cancelModalId = 'invoice-cancel-modal';
$cancelActionUrl = app_url('/company/finance/invoices/0/modal-delete');
$cancelEntityLabel = 'счёта';
require base_path('app/View/partials/company_finance_cancel_modal.php');
?>

<script>
window.planexSetHtmlAndRunScripts = window.planexSetHtmlAndRunScripts || function(container, html) {
    container.innerHTML = html;
    Array.from(container.querySelectorAll('script')).forEach(function(oldScript) {
        var script = document.createElement('script');
        Array.from(oldScript.attributes).forEach(function(attr) { script.setAttribute(attr.name, attr.value); });
        script.textContent = oldScript.textContent;
        oldScript.replaceWith(script);
    });
};

document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('[data-invoice-id]').forEach(function(row) {
        row.addEventListener('dblclick', function() {
            var id = this.dataset.invoiceId;
            var body = document.getElementById('invoice-view-modal-body');
            body.innerHTML = '<div class="empty-state compact"><p>Загрузка...</p></div>';
            window.openModal('invoice-view-modal');
            fetch(window.getErpBasePath() + '/company/finance/invoices/' + id + '/modal-view')
                .then(function(r) { if (!r.ok) throw new Error('HTTP ' + r.status); return r.text(); })
                .then(function(html) { window.planexSetHtmlAndRunScripts(body, html); })
                .catch(function() { body.innerHTML = '<div class="form-alert alert-error">Не удалось загрузить данные счёта.</div>'; });
        });
    });
    var createBtn = document.querySelector('[data-open-modal="invoice-create-modal"]');
    if (createBtn) createBtn.addEventListener('click', function() { window.openModal('invoice-create-modal'); });
});
</script>
<?php endif; ?>