<?php

use App\Service\FinanceCashService;
use App\Service\FinanceOperationService;

$fmtDate = function ($d) { return ($d && $d !== '—') ? date('d.m.Y', is_numeric(strtotime($d)) ? strtotime($d) : time()) : '—'; };
?>
<?php if ($company === null): ?>
<div class="notice warn">Компания не найдена. Укажите корректный company_id.</div>
<?php elseif (($company['status'] ?? '') !== 'active'): ?>
<div class="page-head">
    <div class="page-head-left">
        <h1 class="page-title">Касса</h1>
        <div class="page-summary"><span>Компания находится в неактивном статусе.</span></div>
    </div>
</div>
<div class="notice warn">Работа с кассой недоступна.</div>
<?php elseif ($dbError !== null): ?>
<div class="page-head">
    <div class="page-head-left">
        <h1 class="page-title">Касса</h1>
        <div class="page-summary"><span>Наличные кассы, кассовые операции и внутренние переводы.</span></div>
    </div>
</div>
<div class="notice warn"><?= e($dbError) ?></div>
<?php else: ?>
<div class="page-head">
    <div class="page-head-left">
        <h1 class="page-title">Касса</h1>
        <div class="page-summary"><span>Наличные кассы, кассовые операции и внутренние переводы.</span></div>
    </div>
    <div class="page-head-right">
        <button type="button" class="btn btn-primary btn--toolbar" id="cash-account-create-btn">Создать кассу</button>
        <button type="button" class="btn btn-primary btn--toolbar" id="cash-operation-create-btn">Приход/Расход</button>
        <button type="button" class="btn btn-primary btn--toolbar" id="cash-transfer-create-btn">Перевод</button>
    </div>
</div>

<?php if (!empty($successFlash)): ?>
<div class="notice success"><?= e($successFlash) ?></div>
<?php endif; ?>
<?php if (!empty($errorFlash)): ?>
<div class="notice warn"><?= e($errorFlash) ?></div>
<?php endif; ?>

<div class="section-title">Кассы</div>
<?php if (empty($cashAccounts)): ?>
<div class="panel">
    <div class="panel-body">
        <div class="empty-state">
            <p class="empty-title">Кассы не созданы.</p>
            <p class="empty-desc">Создайте кассу для учёта наличных денежных средств.</p>
        </div>
    </div>
</div>
<?php else: ?>
<div class="table-card table-card--standard">
    <div class="table-scroll">
        <table class="table">
            <thead>
                <tr>
                    <th>Название</th>
                    <th>Валюта</th>
                    <th>Начальный остаток</th>
                    <th>Текущий остаток</th>
                    <th>Статус</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($cashAccounts as $acc): ?>
                <tr>
                    <td><?= e($acc['name'] ?? '—') ?></td>
                    <td><?= e($acc['currency'] ?? 'RUR') ?></td>
                    <td class="col-mono"><?= FinanceCashService::formatAmount($acc['opening_balance'] ?? null) ?></td>
                    <td class="col-mono"><strong><?= FinanceCashService::formatAmount($acc['computed_balance'] ?? null) ?></strong></td>
                    <td>
                        <span class="<?= ($acc['is_active'] ?? 0) ? 'badge badge-ok' : 'badge badge-neutral' ?>">
                            <span class="dot"></span>
                            <?= ($acc['is_active'] ?? 0) ? 'Активна' : 'Неактивна' ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<div class="section-title mt-section">Последние операции и переводы
    <?php if (isset($cashTotal)): ?>
    <span class="section-title-meta">— <?= count($recentOperations) ?> из <?= $cashTotal ?> (стр. <?= $cashPage ?>/<?= $cashPages ?>)</span>
    <?php endif; ?>
</div>
<?php if (empty($recentOperations)): ?>
<div class="panel">
    <div class="panel-body">
        <div class="empty-state">
            <p class="empty-title">Операций нет.</p>
            <p class="empty-desc">Проведите приход, расход или внутренний перевод.</p>
        </div>
    </div>
</div>
<?php else: ?>
<div class="table-card table-card--standard">
    <div class="table-scroll">
        <table class="table">
            <thead>
                <tr>
                    <th>Дата</th>
                    <th>Счёт</th>
                    <th>Тип</th>
                    <th>Сумма</th>
                    <th>Назначение</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recentOperations as $op): ?>
                <tr>
                    <td class="col-mono"><?= $fmtDate($op['operation_date'] ?? '') ?></td>
                    <td><?= e($op['account_name'] ?? '—') ?></td>
                    <td>
                        <?php if (($op['operation_type'] ?? '') === 'TRANSFER'): ?>
                            <?= ($op['transfer_direction'] ?? '') === 'out' ? 'Перевод (исходящий)' : 'Перевод (входящий)' ?>
                        <?php else: ?>
                            <?= e(FinanceOperationService::operationTypeLabel($op['operation_type'] ?? null)) ?>
                        <?php endif; ?>
                    </td>
                    <td class="col-mono"><?= FinanceCashService::formatAmount($op['amount'] ?? null) ?></td>
                    <td><?= e($op['purpose'] ?? ($op['comment'] ?? '—')) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<div id="cash-account-create-modal" class="modal-overlay" role="dialog" aria-modal="true" data-close-on-overlay="0" data-close-on-escape="0">
    <div class="modal modal-md">
        <div class="modal-head">
            <span class="modal-title">Создание кассы</span>
            <button type="button" class="modal-close" data-close-modal="cash-account-create-modal">&times;</button>
        </div>
        <div class="modal-body" id="cash-account-create-modal-body">
        </div>
    </div>
</div>

<div id="cash-operation-create-modal" class="modal-overlay" role="dialog" aria-modal="true" data-close-on-overlay="0" data-close-on-escape="0">
    <div class="modal modal-md">
        <div class="modal-head">
            <span class="modal-title">Кассовая операция</span>
            <button type="button" class="modal-close" data-close-modal="cash-operation-create-modal">&times;</button>
        </div>
        <div class="modal-body" id="cash-operation-create-modal-body">
        </div>
    </div>
</div>

<div id="cash-transfer-create-modal" class="modal-overlay" role="dialog" aria-modal="true" data-close-on-overlay="0" data-close-on-escape="0">
    <div class="modal modal-md">
        <div class="modal-head">
            <span class="modal-title">Внутренний перевод</span>
            <button type="button" class="modal-close" data-close-modal="cash-transfer-create-modal">&times;</button>
        </div>
        <div class="modal-body" id="cash-transfer-create-modal-body">
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    function loadModal(btnId, modalId, bodyId, url, title) {
        var btn = document.getElementById(btnId);
        if (!btn) return;
        btn.addEventListener('click', function() {
            var modal = document.getElementById(modalId);
            var body = document.getElementById(bodyId);
            if (!modal || !body) return;
            body.innerHTML = '<div class="empty-state compact"><p>Загрузка...</p></div>';
            window.openModal(modalId);
            fetch(window.getErpBasePath() + url)
                .then(function(r) { return r.text(); })
                .then(function(html) {
                    body.innerHTML = html;
                })
                .catch(function() {
                    body.innerHTML = '<div class="form-alert alert-error">Не удалось загрузить форму.</div>';
                });
        });
    }

    loadModal('cash-account-create-btn', 'cash-account-create-modal', 'cash-account-create-modal-body', '/company/finance/cash/account-create', 'Создание кассы');
    loadModal('cash-operation-create-btn', 'cash-operation-create-modal', 'cash-operation-create-modal-body', '/company/finance/cash/operation-create', 'Кассовая операция');
    loadModal('cash-transfer-create-btn', 'cash-transfer-create-modal', 'cash-transfer-create-modal-body', '/company/finance/cash/transfer-create', 'Внутренний перевод');
});
</script>
<?php endif; ?>
