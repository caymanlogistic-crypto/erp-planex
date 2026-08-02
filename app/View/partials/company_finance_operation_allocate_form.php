<?php

use App\Service\FinanceOperationService;
use App\Service\FinanceAllocationService;

$operation = $operation ?? [];
$invoices = $invoices ?? [];
$routes = $routes ?? [];
$routePayments = $routePayments ?? [];

$fmtDate = function ($d) { return ($d && $d !== '—') ? date('d.m.Y', is_numeric(strtotime($d)) ? strtotime($d) : time()) : '—'; };
?>
<?php if ($error): ?>
<div class="form-alert alert-error"><?= e($error) ?></div>
<?php elseif ($operation): ?>
<form action="<?= app_url('/company/finance/operations/' . (int) ($operation['id'] ?? 0) . '/allocate') ?>" method="post" class="allocate-form">
    <?= csrfField() ?>
    <div class="modal-body">
        <div class="driver-view-card mb-2">
            <div class="driver-view-grid">
                <div class="driver-view-row">
                    <div class="driver-view-cell driver-view-cell-label">Операция</div>
                    <div class="driver-view-cell driver-view-cell-value">#<?= (int) ($operation['id'] ?? 0) ?></div>
                </div>
                <div class="driver-view-row">
                    <div class="driver-view-cell driver-view-cell-label">Дата</div>
                    <div class="driver-view-cell driver-view-cell-value"><?= $fmtDate($operation['operation_date'] ?? '') ?></div>
                </div>
                <div class="driver-view-row">
                    <div class="driver-view-cell driver-view-cell-label">Тип</div>
                    <div class="driver-view-cell driver-view-cell-value"><?= e(FinanceOperationService::operationTypeLabel($operation['operation_type'] ?? null)) ?></div>
                </div>
                <div class="driver-view-row">
                    <div class="driver-view-cell driver-view-cell-label">Сумма</div>
                    <div class="driver-view-cell driver-view-cell-value"><?= FinanceOperationService::formatAmount($operation['amount'] ?? null) ?> ₽</div>
                </div>
                <div class="driver-view-row">
                    <div class="driver-view-cell driver-view-cell-label">Остаток к распределению</div>
                    <div class="driver-view-cell driver-view-cell-value"><strong><?= FinanceAllocationService::formatAmount($operation['remaining_amount'] ?? null) ?> ₽</strong></div>
                </div>
                <?php if (!empty($operation['counterparty_name'])): ?>
                <div class="driver-view-row">
                    <div class="driver-view-cell driver-view-cell-label">Контрагент</div>
                    <div class="driver-view-cell driver-view-cell-value"><?= e($operation['counterparty_name']) ?></div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="section-title mt-section">Данные распределения</div>
        <div class="form-grid-3">
            <div class="field">
                <label class="field-label">Дата распределения</label>
                <input type="date" name="allocation_date" class="field-input" value="<?= e(date('Y-m-d')) ?>" required>
            </div>
            <div class="field">
                <label class="field-label">Сумма</label>
                <input type="text" name="amount" class="field-input" placeholder="0.00" required>
            </div>
        </div>

        <div class="section-title mt-section">Цель распределения</div>
        <div class="form-grid-3">
            <div class="field">
                <label class="field-label">Счёт</label>
                <select name="invoice_id" class="field-select" id="al-invoice-select">
                    <option value="">— Не выбран —</option>
                    <?php foreach ($invoices as $inv): ?>
                    <option value="<?= (int) $inv['id'] ?>" data-remaining="<?= e($inv['remaining_amount'] ?? '0') ?>">
                        №<?= e($inv['number'] ?? '') ?> · <?= e($inv['counterparty_name'] ?? '') ?> · <?= FinanceAllocationService::formatAmount($inv['remaining_amount'] ?? null) ?> ₽
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label class="field-label">Рейс</label>
                <select name="linear_route_id" class="field-select" id="al-route-select">
                    <option value="">— Не выбран —</option>
                    <?php foreach ($routes as $r): ?>
                    <option value="<?= (int) $r['id'] ?>">
                        #<?= (int) $r['id'] ?> (<?= e($r['route_type'] ?? '') ?>, <?= e($r['planned_loading_date'] ?? '') ?>) — <?= e($r['client_name'] ?? '') ?> / <?= e($r['carrier_name'] ?? '') ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label class="field-label">Платёжная строка</label>
                <select name="linear_route_payment_id" class="field-select" id="al-payment-select">
                    <option value="">— Все строки —</option>
                    <?php foreach ($routePayments as $p): ?>
                    <option value="<?= (int) $p['id'] ?>">
                        <?= FinanceAllocationService::formatAmount($p['amount'] ?? null) ?> · <?= e($p['party_role'] ?? '') ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="field">
            <label class="field-label">Статья ДДС</label>
            <input type="text" class="field-input" value="Будет доступно после справочника статей" disabled>
        </div>
        <div class="field">
            <label class="field-label">Комментарий</label>
            <textarea name="comment" class="field-textarea" rows="2"></textarea>
        </div>
    </div>
    <div class="modal-foot is-spaced">
        <div class="modal-foot-actions">
            <button type="button" class="btn btn-ghost" data-close-modal="operation-allocate-modal">Отмена</button>
        </div>
        <div class="modal-foot-actions">
            <button type="submit" class="btn btn-primary">Создать распределение</button>
        </div>
    </div>
</form>

<script>
(function() {
    var form = document.querySelector('.allocate-form');
    if (!form) return;

    var routeSelect = form.querySelector('#al-route-select');
    var paymentSelect = form.querySelector('#al-payment-select');

    if (routeSelect) {
        routeSelect.addEventListener('change', function() {
            var routeId = this.value;
            if (!routeId) {
                paymentSelect.innerHTML = '<option value="">— Все строки —</option>';
                return;
            }
            var opType = '<?= e($operation['operation_type'] ?? 'EXPENSE') ?>';
            var url = window.getErpBasePath() + '/company/finance/operations/route-payments?route_id=' + encodeURIComponent(routeId) + '&operation_type=' + encodeURIComponent(opType);
            paymentSelect.innerHTML = '<option value="">— Загрузка... —</option>';
            fetch(url)
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    paymentSelect.innerHTML = '<option value="">— Все строки —</option>';
                    data.forEach(function(item) {
                        var opt = document.createElement('option');
                        opt.value = item.id;
                        opt.textContent = item.label;
                        paymentSelect.appendChild(opt);
                    });
                })
                .catch(function() {
                    paymentSelect.innerHTML = '<option value="">— Ошибка загрузки —</option>';
                });
        });
    }

    form.addEventListener('submit', function(e) {
        e.preventDefault();
        var alertBox = form.querySelector('.form-alert');
        if (alertBox) alertBox.remove();
        var submitBtn = form.querySelector('button[type="submit"]');
        if (submitBtn) submitBtn.disabled = true;
        var opId = <?= (int) ($operation['id'] ?? 0) ?>;
        var allocateModal = document.getElementById('operation-allocate-modal');
        var viewModalBody = document.getElementById('operation-view-modal-body');
        fetch(form.action, { method: 'POST', body: new FormData(form), headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function(r) { return r.text(); })
            .then(function(html) {
                if (allocateModal) window.closeModal('operation-allocate-modal');
                if (viewModalBody) {
                    viewModalBody.innerHTML = '<div class="empty-state compact"><p>Загрузка...</p></div>';
                    fetch(window.getErpBasePath() + '/company/finance/operations/' + opId + '/modal-view')
                        .then(function(r2) { return r2.text(); })
                        .then(function(html2) {
                            viewModalBody.innerHTML = html2;
                        })
                        .catch(function() {
                            viewModalBody.innerHTML = '<div class="form-alert alert-error">Не удалось обновить карточку.</div>';
                        });
                }
            })
            .catch(function() {
                if (submitBtn) submitBtn.disabled = false;
                var errDiv = document.createElement('div');
                errDiv.className = 'form-alert alert-error';
                errDiv.textContent = 'Ошибка связи с сервером.';
                form.insertBefore(errDiv, form.firstChild);
            });
    });
})();
</script>
<?php endif; ?>
