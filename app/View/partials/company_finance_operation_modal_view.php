<?php

use App\Service\FinanceOperationService;
use App\Service\FinanceAllocationService;

$fmtDate = function ($d) { return ($d && $d !== '—') ? date('d.m.Y', is_numeric(strtotime($d)) ? strtotime($d) : time()) : '—'; };
$canAllocate = $canAllocate ?? false;
?>
<?php if ($error): ?>
<div class="form-alert alert-error"><?= e($error) ?></div>
<?php elseif ($operation): ?>
<div class="driver-modal-body">
    <h3 class="driver-view-name">Операция #<?= (int) $operation['id'] ?></h3>
    <div class="driver-view-card">
        <div class="driver-view-grid">
            <div class="driver-view-row">
                <div class="driver-view-cell driver-view-cell-label">Дата</div>
                <div class="driver-view-cell driver-view-cell-value"><?= $fmtDate($operation['operation_date'] ?? '') ?></div>
            </div>
            <div class="driver-view-row">
                <div class="driver-view-cell driver-view-cell-label">Счёт</div>
                <div class="driver-view-cell driver-view-cell-value"><?= e($operation['account_name'] ?? '—') ?></div>
            </div>
            <div class="driver-view-row">
                <div class="driver-view-cell driver-view-cell-label">Тип</div>
                <div class="driver-view-cell driver-view-cell-value"><?= e(FinanceOperationService::operationTypeLabel($operation['operation_type'] ?? null)) ?></div>
            </div>
            <div class="driver-view-row">
                <div class="driver-view-cell driver-view-cell-label">Статус</div>
                <div class="driver-view-cell driver-view-cell-value">
                    <span class="<?= e(FinanceOperationService::statusBadgeClass($operation['status'] ?? null)) ?>">
                        <span class="dot"></span>
                        <?= e(FinanceOperationService::statusLabel($operation['status'] ?? null)) ?>
                    </span>
                </div>
            </div>
            <div class="driver-view-row">
                <div class="driver-view-cell driver-view-cell-label">Источник</div>
                <div class="driver-view-cell driver-view-cell-value"><?= e(FinanceOperationService::sourceLabel($operation['source'] ?? null)) ?></div>
            </div>
            <div class="driver-view-row">
                <div class="driver-view-cell driver-view-cell-label">Контрагент</div>
                <div class="driver-view-cell driver-view-cell-value">
                    <?= e($operation['counterparty_name'] ?? '—') ?>
                    <?php if (!empty($operation['counterparty_inn'])): ?>
                    <div class="driver-view-hint">ИНН <?= e($operation['counterparty_inn']) ?></div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="driver-view-row">
                <div class="driver-view-cell driver-view-cell-label">Сумма</div>
                <div class="driver-view-cell driver-view-cell-value"><?= FinanceOperationService::formatAmount($operation['amount'] ?? null) ?> ₽</div>
            </div>
            <div class="driver-view-row">
                <div class="driver-view-cell driver-view-cell-label">Разнесено</div>
                <div class="driver-view-cell driver-view-cell-value"><?= FinanceAllocationService::formatAmount($operation['allocated_amount'] ?? null) ?> ₽</div>
            </div>
            <div class="driver-view-row">
                <div class="driver-view-cell driver-view-cell-label">Остаток</div>
                <div class="driver-view-cell driver-view-cell-value"><?= FinanceAllocationService::formatAmount($operation['remaining_amount'] ?? null) ?> ₽</div>
            </div>
            <div class="driver-view-row">
                <div class="driver-view-cell driver-view-cell-label">Статус разнесения</div>
                <div class="driver-view-cell driver-view-cell-value">
                    <span class="<?= e(FinanceOperationService::allocationStatusBadgeClass($operation['allocation_status'] ?? null)) ?>">
                        <span class="dot"></span>
                        <?= e(FinanceOperationService::allocationStatusLabel($operation['allocation_status'] ?? null)) ?>
                    </span>
                </div>
            </div>
            <?php if (!empty($operation['purpose'])): ?>
            <div class="driver-view-row driver-view-row-wide">
                <div class="driver-view-cell driver-view-cell-label">Назначение</div>
                <div class="driver-view-cell driver-view-cell-value"><?= e($operation['purpose']) ?></div>
            </div>
            <?php endif; ?>
            <?php if (!empty($operation['comment'])): ?>
            <div class="driver-view-row driver-view-row-wide">
                <div class="driver-view-cell driver-view-cell-label">Комментарий</div>
                <div class="driver-view-cell driver-view-cell-value"><?= e($operation['comment']) ?></div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="section-title mt-section">
        <span>История изменений</span>
        <button type="button" class="btn btn-ghost btn-sm" data-operation-history-btn data-operation-id="<?= (int) ($operation['id'] ?? 0) ?>">История</button>
    </div>
    <div id="operation-history-container" class="mt-half">
        <div class="empty-state compact">
            <p class="empty-desc">Нажмите «История» для загрузки.</p>
        </div>
    </div>

    <?php if (!empty($allocations)): ?>
    <div class="section-title mt-section">Распределения</div>
    <div class="table-card table-card--standard">
        <div class="table-scroll">
            <table class="table">
                <thead>
                    <tr>
                        <th>Дата</th>
                        <th>Сумма</th>
                        <th>Счёт</th>
                        <th>Рейс</th>
                        <th>Комментарий</th>
                        <?php if ($canAllocate): ?>
                        <th>Действие</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($allocations as $al): ?>
                    <tr>
                        <td class="col-mono"><?= $fmtDate($al['allocation_date'] ?? '') ?></td>
                        <td class="col-mono"><?= FinanceAllocationService::formatAmount($al['amount'] ?? null) ?></td>
                        <td><?= e($al['invoice_number'] ?? '—') ?></td>
                        <td><?= e(($al['route_type'] ?? '') . ' / ' . ($al['planned_loading_date'] ?? '—')) ?></td>
                        <td><?= e($al['comment'] ?? '—') ?></td>
                        <?php if ($canAllocate): ?>
                        <td>
                            <form action="<?= app_url('/company/finance/operations/allocations/' . (int) $al['id'] . '/cancel') ?>" method="post" class="cancel-allocation-form">
                                <?= csrfField() ?>
                                <input type="hidden" name="reason" value="Отменено вручную">
                                <button type="submit" class="btn btn-ghost btn-sm">Отменить</button>
                            </form>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>
<div class="modal-foot is-spaced">
    <div class="modal-foot-actions">
        <button type="button" class="btn btn-ghost" data-close-modal="operation-view-modal">Закрыть</button>
    </div>
    <div class="modal-foot-actions">
        <?php if ($canAllocate && $operation['status'] === 'POSTED' && $operation['remaining_amount'] !== '0.00'): ?>
        <button type="button" class="btn btn-primary" data-operation-allocate-btn>Распределить</button>
        <?php endif; ?>
        <?php if ($canAllocate && $operation['status'] === 'POSTED'): ?>
        <button type="button" class="btn btn-primary btn-danger" data-operation-cancel-btn>Отменить операцию</button>
        <?php endif; ?>
    </div>
</div>

<script>
(function() {
    var modal = document.getElementById('operation-view-modal');
    if (!modal) return;

    var allocateBtn = modal.querySelector('[data-operation-allocate-btn]');
    if (allocateBtn) {
        allocateBtn.addEventListener('click', function() {
            var id = <?= (int) ($operation['id'] ?? 0) ?>;
            var allocateModal = document.getElementById('operation-allocate-modal');
            var allocateBody = document.getElementById('operation-allocate-modal-body');
            if (!allocateBody || !allocateModal) return;
            allocateBody.innerHTML = '<div class="empty-state compact"><p>Загрузка...</p></div>';
            window.openModal('operation-allocate-modal');
            allocateModal.querySelector('.modal-title').textContent = 'Распределение операции #' + id;

            fetch(window.getErpBasePath() + '/company/finance/operations/' + id + '/allocate')
                .then(function(r) { return r.text(); })
                .then(function(html) {
                    allocateBody.innerHTML = html;
                })
                .catch(function() {
                    allocateBody.innerHTML = '<div class="form-alert alert-error">Не удалось загрузить форму распределения.</div>';
                });
        });
    }

    var historyBtn = modal.querySelector('[data-operation-history-btn]');
    var historyContainer = modal.querySelector('#operation-history-container');
    if (historyBtn && historyContainer) {
        historyBtn.addEventListener('click', function() {
            var id = historyBtn.getAttribute('data-operation-id');
            historyContainer.innerHTML = '<div class="empty-state compact"><p>Загрузка...</p></div>';
            fetch(window.getErpBasePath() + '/company/finance/operations/' + id + '/history')
                .then(function(r) { return r.text(); })
                .then(function(html) {
                    historyContainer.innerHTML = html;
                })
                .catch(function() {
                    historyContainer.innerHTML = '<div class="form-alert alert-error">Не удалось загрузить историю.</div>';
                });
        });
    }

    var cancelBtn = modal.querySelector('[data-operation-cancel-btn]');
    if (cancelBtn) {
        cancelBtn.addEventListener('click', function() {
            var id = <?= (int) ($operation['id'] ?? 0) ?>;
            var cancelModal = document.getElementById('operation-cancel-modal');
            if (!cancelModal) return;
            var cancelForm = cancelModal.querySelector('.finance-cancel-form');
            if (cancelForm) {
                cancelForm.action = window.getErpBasePath() + '/company/finance/operations/' + id + '/cancel';
            }
            window.openModal('operation-cancel-modal');
        });
    }

    var cancelForms = modal.querySelectorAll('.cancel-allocation-form');
    cancelForms.forEach(function(form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            var alertBox = form.querySelector('.form-alert');
            if (alertBox) alertBox.remove();
            var submitBtn = form.querySelector('button[type="submit"]');
            if (submitBtn) submitBtn.disabled = true;
            var viewModalBody = document.getElementById('operation-view-modal-body');
            fetch(form.action, { method: 'POST', body: new FormData(form), headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function(r) { return r.text(); })
                .then(function(html) {
                    if (viewModalBody) {
                        viewModalBody.innerHTML = html;
                    }
                })
                .catch(function() {
                    if (submitBtn) submitBtn.disabled = false;
                    var errDiv = document.createElement('div');
                    errDiv.className = 'form-alert alert-error';
                    errDiv.textContent = 'Ошибка связи с сервером.';
                    form.parentNode.insertBefore(errDiv, form);
                });
        });
    });
})();
</script>
<?php endif; ?>
