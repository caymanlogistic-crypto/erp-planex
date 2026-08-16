<?php

use App\Service\DateCalculationService;
use App\Service\FinanceInvoiceService;

$fmtDate = static function (mixed $value): string {
    $value = trim((string)$value);
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    return $date !== false && $date->format('Y-m-d') === $value ? $date->format('d.m.Y') : '—';
};
$canEdit = $canEdit ?? false;
$canDelete = $canDelete ?? false;

$effectiveStatus = (string)($invoice['status'] ?? '');
if ($effectiveStatus === 'draft') {
    $effectiveStatus = (string)($invoice['direction'] ?? '') === FinanceInvoiceService::DIRECTION_INCOMING ? 'received' : 'issued';
}
$hasOverdue = false;
$resolvedDueDates = [];
$hasUnresolvedDue = false;
foreach ($links ?? [] as $link) {
    if (in_array((string)($link['obligation_status'] ?? ''), ['overdue', 'overdue_partial'], true)) {
        $hasOverdue = true;
    }
    $due = trim((string)($link['due_date'] ?? $link['forecast_due_date'] ?? ''));
    if ($due === '') {
        $hasUnresolvedDue = true;
    } else {
        $resolvedDueDates[$due] = true;
    }
}
if (!in_array($effectiveStatus, ['cancelled', 'paid'], true) && $hasOverdue) {
    $effectiveStatus = (float)($invoice['paid_amount'] ?? 0) > 0 ? 'overdue_partial' : 'overdue';
} elseif (!in_array($effectiveStatus, ['cancelled', 'paid'], true)
    && (float)($invoice['paid_amount'] ?? 0) > 0
    && (float)($invoice['remaining_amount'] ?? 0) > 0) {
    $effectiveStatus = 'partially_paid';
}
if (($links ?? []) === []) {
    $deadlineText = '—';
} elseif ($resolvedDueDates === []) {
    $deadlineText = 'Ожидается событие';
} elseif (!$hasUnresolvedDue && count($resolvedDueDates) === 1) {
    $deadlineText = $fmtDate((string)array_key_first($resolvedDueDates));
} else {
    $deadlineText = 'Несколько сроков';
}
?>
<?php if ($error): ?>
<div class="form-alert alert-error"><?= e($error) ?></div>
<?php elseif ($invoice): ?>
<div class="driver-modal-body">
    <h3 class="driver-view-name">Счёт №<?= e($invoice['number'] ?? '') ?></h3>
    <div class="driver-view-card">
        <div class="driver-view-grid">
            <div class="driver-view-row"><div class="driver-view-cell driver-view-cell-label">Направление</div><div class="driver-view-cell driver-view-cell-value"><?= e(FinanceInvoiceService::directionLabel($invoice['direction'] ?? null)) ?></div></div>
            <div class="driver-view-row"><div class="driver-view-cell driver-view-cell-label">Дата счёта</div><div class="driver-view-cell driver-view-cell-value"><?= e($fmtDate($invoice['invoice_date'] ?? '')) ?></div></div>
            <div class="driver-view-row"><div class="driver-view-cell driver-view-cell-label">Состояние</div><div class="driver-view-cell driver-view-cell-value"><span class="<?= e(FinanceInvoiceService::statusBadgeClass($effectiveStatus)) ?>"><span class="dot"></span><?= e(FinanceInvoiceService::statusLabel($effectiveStatus)) ?></span></div></div>
            <div class="driver-view-row"><div class="driver-view-cell driver-view-cell-label">Контрагент</div><div class="driver-view-cell driver-view-cell-value"><?= e($invoice['counterparty_name'] ?? '—') ?><?php if (!empty($invoice['counterparty_inn'])): ?><div class="driver-view-hint">ИНН <?= e($invoice['counterparty_inn']) ?></div><?php endif; ?></div></div>
            <div class="driver-view-row"><div class="driver-view-cell driver-view-cell-label">Сумма</div><div class="driver-view-cell driver-view-cell-value"><?= e(FinanceInvoiceService::formatAmount($invoice['amount'] ?? null)) ?> ₽</div></div>
            <div class="driver-view-row"><div class="driver-view-cell driver-view-cell-label">НДС</div><div class="driver-view-cell driver-view-cell-value"><?= $invoice['vat_rate'] !== null ? e(((float)$invoice['vat_rate'] == 0.0 ? '0' : rtrim(rtrim((string)$invoice['vat_rate'], '0'), '.')) . '%') : 'Без НДС' ?></div></div>
            <div class="driver-view-row"><div class="driver-view-cell driver-view-cell-label">Срок оплаты</div><div class="driver-view-cell driver-view-cell-value"><?= e($deadlineText) ?></div></div>
            <div class="driver-view-row"><div class="driver-view-cell driver-view-cell-label">Оплачено</div><div class="driver-view-cell driver-view-cell-value"><?= e(FinanceInvoiceService::formatAmount($invoice['paid_amount'] ?? null)) ?> ₽</div></div>
            <div class="driver-view-row"><div class="driver-view-cell driver-view-cell-label">Остаток</div><div class="driver-view-cell driver-view-cell-value"><?= e(FinanceInvoiceService::formatAmount($invoice['remaining_amount'] ?? null)) ?> ₽</div></div>
            <?php if (!empty($invoice['basis'])): ?><div class="driver-view-row driver-view-row-wide"><div class="driver-view-cell driver-view-cell-label">Основание</div><div class="driver-view-cell driver-view-cell-value"><?= e($invoice['basis']) ?></div></div><?php endif; ?>
            <?php if (!empty($invoice['comment'])): ?><div class="driver-view-row driver-view-row-wide"><div class="driver-view-cell driver-view-cell-label">Комментарий</div><div class="driver-view-cell driver-view-cell-value"><?= e($invoice['comment']) ?></div></div><?php endif; ?>
            <?php if (!empty($links)): ?>
            <div class="driver-view-row driver-view-row-wide">
                <div class="driver-view-cell driver-view-cell-label">Платёжные обязательства</div>
                <div class="driver-view-cell driver-view-cell-value">
                    <?php foreach ($links as $link):
                        $condition = DateCalculationService::CONDITION_LABELS[(string)($link['condition_type'] ?? '')] ?? 'Условие оплаты';
                        $due = trim((string)($link['due_date'] ?? $link['forecast_due_date'] ?? ''));
                    ?>
                    <div class="driver-view-inline-row"><div><strong>Рейс #<?= (int)($link['source_parent_id'] ?? $link['linear_route_id'] ?? 0) ?></strong> · <?= e($condition) ?> · <?= $due !== '' ? e($fmtDate($due)) : 'ожидается событие' ?> · <?= e(FinanceInvoiceService::formatAmount($link['amount'] ?? null)) ?> ₽</div></div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="section-title mt-section"><span>История изменений</span><button type="button" class="btn btn-ghost btn-sm" data-invoice-history-btn data-invoice-id="<?= (int)($invoice['id'] ?? 0) ?>">История</button></div>
    <div id="invoice-history-container" class="mt-half"><div class="empty-state compact"><p class="empty-desc">Нажмите «История» для загрузки.</p></div></div>
</div>
<div class="modal-foot is-spaced">
    <div class="modal-foot-actions"><?php if ($canDelete && $effectiveStatus !== 'cancelled'): ?><button type="button" class="btn btn-ghost" data-invoice-cancel-btn>Аннулировать</button><?php endif; ?></div>
    <div class="modal-foot-actions"><button type="button" class="btn btn-ghost" data-close-modal="invoice-view-modal">Закрыть</button><?php if ($canEdit): ?><button type="button" class="btn btn-primary" data-invoice-edit-btn>Редактировать</button><?php endif; ?></div>
</div>
<script>
(function() {
    var modal = document.getElementById('invoice-view-modal');
    if (!modal) return;
    var editBtn = modal.querySelector('[data-invoice-edit-btn]');
    if (editBtn) editBtn.addEventListener('click', function() {
        var id = <?= (int)($invoice['id'] ?? 0) ?>;
        var editBody = document.getElementById('invoice-edit-modal-body');
        editBody.innerHTML = '<div class="empty-state compact"><p>Загрузка...</p></div>';
        window.openModal('invoice-edit-modal');
        fetch(window.getErpBasePath() + '/company/finance/invoices/' + id + '/modal-edit')
            .then(function(r) { if (!r.ok) throw new Error('HTTP ' + r.status); return r.text(); })
            .then(function(html) {
                if (window.planexSetHtmlAndRunScripts) window.planexSetHtmlAndRunScripts(editBody, html);
                else editBody.innerHTML = html;
            })
            .catch(function() { editBody.innerHTML = '<div class="form-alert alert-error">Не удалось загрузить форму редактирования.</div>'; });
    });
    var historyBtn = modal.querySelector('[data-invoice-history-btn]');
    var historyContainer = modal.querySelector('#invoice-history-container');
    if (historyBtn && historyContainer) historyBtn.addEventListener('click', function() {
        historyContainer.innerHTML = '<div class="empty-state compact"><p>Загрузка...</p></div>';
        fetch(window.getErpBasePath() + '/company/finance/invoices/' + historyBtn.dataset.invoiceId + '/history')
            .then(function(r) { if (!r.ok) throw new Error('HTTP ' + r.status); return r.text(); })
            .then(function(html) { historyContainer.innerHTML = html; })
            .catch(function() { historyContainer.innerHTML = '<div class="form-alert alert-error">Не удалось загрузить историю.</div>'; });
    });
    var cancelBtn = modal.querySelector('[data-invoice-cancel-btn]');
    if (cancelBtn) cancelBtn.addEventListener('click', function() {
        var cancelModal = document.getElementById('invoice-cancel-modal');
        if (!cancelModal) return;
        var cancelForm = cancelModal.querySelector('.finance-cancel-form');
        if (cancelForm) cancelForm.action = window.getErpBasePath() + '/company/finance/invoices/<?= (int)($invoice['id'] ?? 0) ?>/modal-delete';
        window.openModal('invoice-cancel-modal');
    });
})();
</script>
<?php endif; ?>