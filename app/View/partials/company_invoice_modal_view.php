<?php

use App\Service\FinanceInvoiceService;

$fmtDate = function ($d) { return ($d && $d !== '—') ? date('d.m.Y', is_numeric(strtotime($d)) ? strtotime($d) : time()) : '—'; };
$canEdit = $canEdit ?? false;
$canDelete = $canDelete ?? false;
?>
<?php if ($error): ?>
<div class="form-alert alert-error"><?= e($error) ?></div>
<?php elseif ($invoice): ?>
<div class="driver-modal-body">
    <h3 class="driver-view-name">Счёт №<?= e($invoice['number'] ?? '') ?></h3>
    <div class="driver-view-card">
        <div class="driver-view-grid">
            <div class="driver-view-row">
                <div class="driver-view-cell driver-view-cell-label">Направление</div>
                <div class="driver-view-cell driver-view-cell-value"><?= e(FinanceInvoiceService::directionLabel($invoice['direction'] ?? null)) ?></div>
            </div>
            <div class="driver-view-row">
                <div class="driver-view-cell driver-view-cell-label">Дата счёта</div>
                <div class="driver-view-cell driver-view-cell-value"><?= $fmtDate($invoice['invoice_date'] ?? '') ?></div>
            </div>
            <div class="driver-view-row">
                <div class="driver-view-cell driver-view-cell-label">Статус</div>
                <div class="driver-view-cell driver-view-cell-value">
                    <span class="<?= e(FinanceInvoiceService::statusBadgeClass($invoice['status'] ?? null)) ?>">
                        <span class="dot"></span>
                        <?= e(FinanceInvoiceService::statusLabel($invoice['status'] ?? null)) ?>
                    </span>
                </div>
            </div>
            <div class="driver-view-row">
                <div class="driver-view-cell driver-view-cell-label">Контрагент</div>
                <div class="driver-view-cell driver-view-cell-value">
                    <?= e($invoice['counterparty_name'] ?? '—') ?>
                    <?php if (!empty($invoice['counterparty_inn'])): ?>
                    <div class="driver-view-hint">ИНН <?= e($invoice['counterparty_inn']) ?></div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="driver-view-row">
                <div class="driver-view-cell driver-view-cell-label">Сумма</div>
                <div class="driver-view-cell driver-view-cell-value"><?= FinanceInvoiceService::formatAmount($invoice['amount'] ?? null) ?> ₽</div>
            </div>
            <div class="driver-view-row">
                <div class="driver-view-cell driver-view-cell-label">НДС</div>
                <div class="driver-view-cell driver-view-cell-value">
                    <?= $invoice['vat_rate'] !== null ? ($invoice['vat_rate'] == 0 ? '0%' : $invoice['vat_rate'] . '%') : 'Без НДС' ?>
                </div>
            </div>
            <div class="driver-view-row">
                <div class="driver-view-cell driver-view-cell-label">Срок оплаты</div>
                <div class="driver-view-cell driver-view-cell-value"><?= $fmtDate($invoice['planned_payment_date'] ?? '') ?></div>
            </div>
            <div class="driver-view-row">
                <div class="driver-view-cell driver-view-cell-label">Оплачено</div>
                <div class="driver-view-cell driver-view-cell-value"><?= FinanceInvoiceService::formatAmount($invoice['paid_amount'] ?? null) ?> ₽</div>
            </div>
            <div class="driver-view-row">
                <div class="driver-view-cell driver-view-cell-label">Остаток</div>
                <div class="driver-view-cell driver-view-cell-value"><?= FinanceInvoiceService::formatAmount($invoice['remaining_amount'] ?? null) ?> ₽</div>
            </div>
            <?php if (!empty($invoice['basis'])): ?>
            <div class="driver-view-row driver-view-row-wide">
                <div class="driver-view-cell driver-view-cell-label">Основание</div>
                <div class="driver-view-cell driver-view-cell-value"><?= e($invoice['basis']) ?></div>
            </div>
            <?php endif; ?>
            <?php if (!empty($invoice['comment'])): ?>
            <div class="driver-view-row driver-view-row-wide">
                <div class="driver-view-cell driver-view-cell-label">Комментарий</div>
                <div class="driver-view-cell driver-view-cell-value"><?= e($invoice['comment']) ?></div>
            </div>
            <?php endif; ?>
            <?php if (!empty($links)): ?>
            <div class="driver-view-row driver-view-row-wide">
                <div class="driver-view-cell driver-view-cell-label">Привязки к рейсам</div>
                <div class="driver-view-cell driver-view-cell-value">
                    <?php foreach ($links as $link): ?>
                    <div class="driver-view-inline-row">
                        <div>Рейс #<?= (int) $link['linear_route_id'] ?> (<?= e($link['route_type'] ?? '') ?>, <?= e($link['planned_loading_date'] ?? '') ?>) · <?= e($link['side'] ?? '—') ?> · <?= FinanceInvoiceService::formatAmount($link['amount']) ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="section-title mt-section">
        <span>История изменений</span>
        <button type="button" class="btn btn-ghost btn-sm" data-invoice-history-btn data-invoice-id="<?= (int) ($invoice['id'] ?? 0) ?>">История</button>
    </div>
    <div id="invoice-history-container" class="mt-half">
        <div class="empty-state compact">
            <p class="empty-desc">Нажмите «История» для загрузки.</p>
        </div>
    </div>
</div>
<div class="modal-foot is-spaced">
    <div class="modal-foot-actions">
        <?php if ($canDelete && $invoice['status'] !== 'cancelled'): ?>
        <button type="button" class="btn btn-ghost" data-invoice-cancel-btn>Аннулировать</button>
        <?php endif; ?>
    </div>
    <div class="modal-foot-actions">
        <button type="button" class="btn btn-ghost" data-close-modal="invoice-view-modal">Закрыть</button>
        <?php if ($canEdit): ?>
        <button type="button" class="btn btn-primary" data-invoice-edit-btn>Редактировать</button>
        <?php endif; ?>
    </div>
</div>

<script>
(function() {
    var modal = document.getElementById('invoice-view-modal');
    if (!modal) return;

    var editBtn = modal.querySelector('[data-invoice-edit-btn]');
    if (editBtn) {
        editBtn.addEventListener('click', function() {
            var id = <?= (int) ($invoice['id'] ?? 0) ?>;
            var editModal = document.getElementById('invoice-edit-modal');
            var editBody = document.getElementById('invoice-edit-modal-body');
            editBody.innerHTML = '<div class="empty-state compact"><p>Загрузка...</p></div>';
            window.openModal('invoice-edit-modal');

            fetch(window.getErpBasePath() + '/company/finance/invoices/' + id + '/modal-edit')
                .then(function(r) { return r.text(); })
                .then(function(html) {
                    editBody.innerHTML = html;
                })
                .catch(function() {
                    editBody.innerHTML = '<div class="form-alert alert-error">Не удалось загрузить форму редактирования.</div>';
                });
        });
    }

    var historyBtn = modal.querySelector('[data-invoice-history-btn]');
    var historyContainer = modal.querySelector('#invoice-history-container');
    if (historyBtn && historyContainer) {
        historyBtn.addEventListener('click', function() {
            var id = historyBtn.getAttribute('data-invoice-id');
            historyContainer.innerHTML = '<div class="empty-state compact"><p>Загрузка...</p></div>';
            fetch(window.getErpBasePath() + '/company/finance/invoices/' + id + '/history')
                .then(function(r) { return r.text(); })
                .then(function(html) {
                    historyContainer.innerHTML = html;
                })
                .catch(function() {
                    historyContainer.innerHTML = '<div class="form-alert alert-error">Не удалось загрузить историю.</div>';
                });
        });
    }

    var cancelBtn = modal.querySelector('[data-invoice-cancel-btn]');
    if (cancelBtn) {
        cancelBtn.addEventListener('click', function() {
            var id = <?= (int) ($invoice['id'] ?? 0) ?>;
            var cancelModal = document.getElementById('invoice-cancel-modal');
            if (!cancelModal) return;
            var cancelForm = cancelModal.querySelector('.finance-cancel-form');
            if (cancelForm) {
                cancelForm.action = window.getErpBasePath() + '/company/finance/invoices/' + id + '/modal-delete';
            }
            window.openModal('invoice-cancel-modal');
        });
    }
})();
</script>
<?php endif; ?>
