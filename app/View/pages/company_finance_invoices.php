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
$fmtMoney = static fn(mixed $value): string => FinanceInvoiceService::formatAmount($value) . ' ₽';
$paymentWord = static function (int $count): string {
    $n100 = $count % 100;
    $n10 = $count % 10;
    if ($n100 >= 11 && $n100 <= 14) return 'платежей';
    if ($n10 === 1) return 'платёж';
    if ($n10 >= 2 && $n10 <= 4) return 'платежа';
    return 'платежей';
};
$delayLabel = static function (?int $days): array {
    if ($days === null) return ['text' => 'срок не определён', 'class' => 'is-neutral'];
    if ($days > 0) return ['text' => '+' . $days . ' дн. просрочка', 'class' => 'is-late'];
    if ($days < 0) return ['text' => 'на ' . abs($days) . ' дн. раньше', 'class' => 'is-early'];
    return ['text' => 'в срок', 'class' => 'is-on-time'];
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
<style>
.invoice-pf-col{min-width:360px}
.invoice-payment-timeline{min-width:340px;max-width:430px;font-size:11px;line-height:1.35;color:var(--text,#2f2b25)}
.invoice-pf-head{font-weight:700;margin-bottom:3px}
.invoice-pf-section+.invoice-pf-section{margin-top:5px;padding-top:5px;border-top:1px solid var(--border,#d8d2c7)}
.invoice-pf-line{display:flex;align-items:baseline;gap:8px;justify-content:space-between;white-space:nowrap}
.invoice-pf-line-main{display:flex;gap:7px;align-items:baseline;min-width:0}
.invoice-pf-route{color:var(--muted,#777067)}
.invoice-pf-empty{color:var(--muted,#777067)}
.invoice-pf-outcome{font-weight:600}
.invoice-pf-delay{font-weight:700}
.invoice-pf-delay.is-late{color:#9b3a2a}
.invoice-pf-delay.is-on-time{color:#3f6a4d}
.invoice-pf-delay.is-early{color:#4b6657}
.invoice-pf-delay.is-neutral{color:var(--muted,#777067)}
.invoice-pf-balance{color:var(--muted,#777067);font-weight:500}
</style>
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
            <thead><tr><th>№</th><th>Дата</th><th>Направление</th><th>Контрагент</th><th>Сумма</th><th>НДС</th><th>Срок оплаты</th><th>Оплачено</th><th class="invoice-pf-col">План / факт оплаты</th><th>Статус</th></tr></thead>
            <tbody>
            <?php foreach ($invoices as $inv):
                $displayStatus = (string)($inv['display_status'] ?? $inv['status'] ?? '');
                $dueText = $fmtDue($inv['display_due_text'] ?? '—');
                $timeline = is_array($inv['payment_timeline'] ?? null) ? $inv['payment_timeline'] : null;
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
                    <td>
                        <div class="invoice-payment-timeline">
                        <?php if ($timeline === null || (int)($timeline['planned_count'] ?? 0) === 0): ?>
                            <div class="invoice-pf-empty">Нет связанных платёжных обязательств.</div>
                        <?php else: ?>
                            <div class="invoice-pf-section">
                                <div class="invoice-pf-head">План: <?= (int)$timeline['planned_count'] ?> <?= e($paymentWord((int)$timeline['planned_count'])) ?> · <?= e($fmtMoney($timeline['planned_total'] ?? '0')) ?></div>
                                <?php foreach (($timeline['plans'] ?? []) as $plan): ?>
                                <div class="invoice-pf-line">
                                    <span class="invoice-pf-line-main">
                                        <span><?= e(($plan['expected_date'] ?? null) ? $fmtDate($plan['expected_date']) : 'дата ожидается') ?></span>
                                        <?php if (!empty($plan['route_id'])): ?><span class="invoice-pf-route">рейс #<?= (int)$plan['route_id'] ?></span><?php endif; ?>
                                    </span>
                                    <span><?= e($fmtMoney($plan['amount'] ?? '0')) ?></span>
                                </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="invoice-pf-section">
                                <div class="invoice-pf-head">Факт: <?= (int)($timeline['fact_count'] ?? 0) ?> <?= e($paymentWord((int)($timeline['fact_count'] ?? 0))) ?> · <?= e($fmtMoney($timeline['paid_total'] ?? '0')) ?></div>
                                <?php if (empty($timeline['facts'])): ?>
                                    <div class="invoice-pf-empty">Оплат пока нет.</div>
                                <?php else: ?>
                                    <?php foreach ($timeline['facts'] as $fact): ?>
                                    <div class="invoice-pf-line"><span><?= e(($fact['actual_date'] ?? null) ? $fmtDate($fact['actual_date']) : 'дата не определена') ?></span><span><?= e($fmtMoney($fact['amount'] ?? '0')) ?></span></div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>

                            <div class="invoice-pf-section invoice-pf-outcome">
                                <div>Итого: оплачено <?= e($fmtMoney($timeline['paid_total'] ?? '0')) ?><?php if ((float)($timeline['remaining_total'] ?? 0) > 0): ?> <span class="invoice-pf-balance">· осталось <?= e($fmtMoney($timeline['remaining_total'])) ?></span><?php endif; ?></div>
                                <?php if (!empty($timeline['settlement_parts'])): ?>
                                    <?php foreach ($timeline['settlement_parts'] as $part): $delay = $delayLabel(isset($part['delay_days']) ? (int)$part['delay_days'] : null); ?>
                                    <div class="invoice-pf-line"><span><?= e($fmtMoney($part['amount'] ?? '0')) ?></span><span class="invoice-pf-delay <?= e($delay['class']) ?>"><?= e($delay['text']) ?></span></div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                                <?php if ((float)($timeline['late_paid_total'] ?? 0) > 0): ?>
                                <div class="invoice-pf-line"><span>С просрочкой</span><span class="invoice-pf-delay is-late"><?= e($fmtMoney($timeline['late_paid_total'])) ?> · до <?= (int)$timeline['max_delay_days'] ?> дн.</span></div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        </div>
                    </td>
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
    document.querySelectorAll('table.table tbody tr[data-invoice-id]').forEach(function(row) {
        row.addEventListener('dblclick', function() {
            var id = parseInt(this.dataset.invoiceId || '0', 10);
            if (!Number.isInteger(id) || id <= 0) return;
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