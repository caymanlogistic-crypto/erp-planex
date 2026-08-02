<?php

use App\Service\FinanceInvoiceService;

$fmtDate = function ($d) { return ($d && $d !== '—') ? date('d.m.Y', is_numeric(strtotime($d)) ? strtotime($d) : time()) : '—'; };
$successFlash = $_SESSION['invoice_success'] ?? null;
unset($_SESSION['invoice_success']);

$currentDirection = $_GET['direction'] ?? '';
$selectedDirection = in_array($currentDirection, [FinanceInvoiceService::DIRECTION_OUTGOING, FinanceInvoiceService::DIRECTION_INCOMING], true) ? $currentDirection : '';
?>
<?php if ($company === null): ?>
<div class="notice warn">Компания не найдена.</div>
<?php elseif (($company['status'] ?? '') !== 'active'): ?>
<div class="page-head">
    <div class="page-head-left">
        <h1 class="page-title">Счета</h1>
        <div class="page-summary"><span>Компания находится в неактивном статусе.</span></div>
    </div>
</div>
<div class="notice warn">Работа со счетами недоступна.</div>
<?php elseif ($dbError !== null): ?>
<div class="page-head">
    <div class="page-head-left">
        <h1 class="page-title">Счета</h1>
        <div class="page-summary"><span>Реестр счетов компании.</span></div>
    </div>
</div>
<div class="notice warn"><?= e($dbError) ?></div>
<?php else: ?>
<div class="page-head">
    <div class="page-head-left">
        <h1 class="page-title">Счета</h1>
        <div class="page-summary"><span>Реестр счетов компании: выставленные и полученные.</span></div>
    </div>
    <div class="page-head-actions">
        <button type="button" class="btn btn-primary" data-open-modal="invoice-create-modal">Создать счёт</button>
    </div>
</div>

<?php if ($successFlash): ?>
<div class="notice success"><?= e($successFlash) ?></div>
<?php endif; ?>
<?php if (!empty($formError)): ?>
<div class="notice warn"><?= e($formError) ?></div>
<?php endif; ?>

<div class="tabs-page">
    <a href="<?= app_url('/company/finance/invoices') ?>" class="tab-link<?= $selectedDirection === '' ? ' is-active' : '' ?>">Все счета</a>
    <a href="<?= app_url('/company/finance/invoices?direction=' . FinanceInvoiceService::DIRECTION_OUTGOING) ?>" class="tab-link<?= $selectedDirection === FinanceInvoiceService::DIRECTION_OUTGOING ? ' is-active' : '' ?>">Выставленные</a>
    <a href="<?= app_url('/company/finance/invoices?direction=' . FinanceInvoiceService::DIRECTION_INCOMING) ?>" class="tab-link<?= $selectedDirection === FinanceInvoiceService::DIRECTION_INCOMING ? ' is-active' : '' ?>">Полученные</a>
</div>

<?php if (empty($invoices)): ?>
<div class="panel">
    <div class="panel-body">
        <div class="empty-state">
            <p class="empty-title">Счета ещё не созданы.</p>
            <p class="empty-desc">Создайте первый счёт через модальное окно.</p>
        </div>
    </div>
</div>
<?php else: ?>
<div class="table-card table-card--standard">
    <div class="table-toolbar">
        <div class="found-label">Найдено: <b><?= count($invoices) ?></b> из <b><?= $invTotal ?></b> счетов (страница <?= $invPage ?>/<?= $invPages ?>)</div>
        <div class="toolbar-right">
            <input type="text" class="toolbar-search" placeholder="Поиск по таблице">
        </div>
    </div>
    <div class="table-scroll">
        <table class="table">
            <thead>
                <tr>
                    <th>№</th>
                    <th>Дата</th>
                    <th>Направление</th>
                    <th>Контрагент</th>
                    <th>Сумма</th>
                    <th>НДС</th>
                    <th>Срок оплаты</th>
                    <th>Оплачено</th>
                    <th>Статус</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($invoices as $inv): ?>
                <tr data-invoice-id="<?= (int) ($inv['id'] ?? 0) ?>"
                    data-invoice-number="<?= e($inv['number'] ?? '') ?>"
                    data-invoice-date="<?= e($inv['invoice_date'] ?? '') ?>"
                    data-invoice-direction="<?= e($inv['direction'] ?? '') ?>"
                    data-invoice-cparty="<?= e($inv['counterparty_name'] ?? '') ?>"
                    data-invoice-inn="<?= e($inv['counterparty_inn'] ?? '') ?>"
                    data-invoice-amount="<?= e($inv['amount'] ?? '0') ?>"
                    data-invoice-vat="<?= e($inv['vat_rate'] ?? '') ?>"
                    data-invoice-basis="<?= e($inv['basis'] ?? '') ?>"
                    data-invoice-planned="<?= e($inv['planned_payment_date'] ?? '') ?>"
                    data-invoice-comment="<?= e($inv['comment'] ?? '') ?>"
                    data-invoice-status="<?= e($inv['status'] ?? '') ?>"
                    data-invoice-paid="<?= e($inv['paid_amount'] ?? '0') ?>"
                    data-invoice-remaining="<?= e($inv['remaining_amount'] ?? '0') ?>">
                    <td class="col-mono"><?= e($inv['number'] ?? '—') ?></td>
                    <td class="col-mono"><?= $fmtDate($inv['invoice_date'] ?? '') ?></td>
                    <td><?= e(FinanceInvoiceService::directionLabel($inv['direction'] ?? null)) ?></td>
                    <td><?= e($inv['counterparty_name'] ?: '—') ?></td>
                    <td class="col-mono"><?= FinanceInvoiceService::formatAmount($inv['amount'] ?? null) ?></td>
                    <td><?= $inv['vat_rate'] !== null ? ($inv['vat_rate'] == 0 ? '0%' : $inv['vat_rate'] . '%') : 'Без НДС' ?></td>
                    <td class="col-mono"><?= $fmtDate($inv['planned_payment_date'] ?? '') ?></td>
                    <td class="col-mono"><?= FinanceInvoiceService::formatAmount($inv['paid_amount'] ?? null) ?></td>
                    <td>
                        <span class="<?= e(FinanceInvoiceService::statusBadgeClass($inv['status'] ?? null)) ?>">
                            <span class="dot"></span>
                            <?= e(FinanceInvoiceService::statusLabel($inv['status'] ?? null)) ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<div id="invoice-create-modal" class="modal-overlay" role="dialog" aria-modal="true" data-close-on-overlay="0" data-close-on-escape="0">
    <div class="modal modal-lg">
        <div class="modal-head">
            <span class="modal-title">Создать счёт</span>
            <button type="button" class="modal-close" data-close-modal="invoice-create-modal">&times;</button>
        </div>
        <?php if ($localPdo !== null): ?>
        <?php
        $isEdit = false;
        $invoice = $old ?? null;
        $routes = FinanceInvoiceService::fetchRoutesForSelect($localPdo, $_SESSION['user'] ?? []);
        $clients = FinanceInvoiceService::fetchClientsForSelect($localPdo);
        $contractors = FinanceInvoiceService::fetchContractorsForSelect($localPdo);
        require base_path('app/View/partials/company_invoice_create_form.php');
        ?>
        <?php else: ?>
        <div class="modal-body">
            <div class="form-alert alert-error">Данные компании недоступны.</div>
        </div>
        <?php endif; ?>
    </div>
</div>

<div id="invoice-view-modal" class="modal-overlay" role="dialog" aria-modal="true" data-close-on-overlay="1" data-close-on-escape="1">
    <div class="modal modal-lg">
        <div class="modal-head">
            <span class="modal-title">Счёт</span>
            <button type="button" class="modal-close" data-close-modal="invoice-view-modal">&times;</button>
        </div>
        <div class="modal-body" id="invoice-view-modal-body">
        </div>
    </div>
</div>

<div id="invoice-edit-modal" class="modal-overlay" role="dialog" aria-modal="true" data-close-on-overlay="0" data-close-on-escape="0">
    <div class="modal modal-lg">
        <div class="modal-head">
            <span class="modal-title">Редактировать счёт</span>
            <button type="button" class="modal-close" data-close-modal="invoice-edit-modal">&times;</button>
        </div>
        <div class="modal-body" id="invoice-edit-modal-body">
        </div>
    </div>
</div>

<?php
$cancelModalId = 'invoice-cancel-modal';
$cancelActionUrl = app_url('/company/finance/invoices/0/modal-delete');
$cancelEntityLabel = 'счёта';
require base_path('app/View/partials/company_finance_cancel_modal.php');
?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var rows = document.querySelectorAll('[data-invoice-id]');
    rows.forEach(function(row) {
        row.addEventListener('dblclick', function() {
            var id = this.dataset.invoiceId;
            var modal = document.getElementById('invoice-view-modal');
            var body = document.getElementById('invoice-view-modal-body');
            body.innerHTML = '<div class="empty-state compact"><p>Загрузка...</p></div>';
            window.openModal('invoice-view-modal');

            fetch(window.getErpBasePath() + '/company/finance/invoices/' + id + '/modal-view')
                .then(function(r) { return r.text(); })
                .then(function(html) {
                    body.innerHTML = html;
                })
                .catch(function() {
                    body.innerHTML = '<div class="form-alert alert-error">Не удалось загрузить данные счёта.</div>';
                });
        });
    });

    document.querySelector('[data-open-modal="invoice-create-modal"]').addEventListener('click', function() {
        window.openModal('invoice-create-modal');
    });
});
</script>
<?php endif; ?>
