<?php

use App\Service\FinanceOperationService;
use App\Service\FinanceAllocationService;

$fmtDate = function ($d) { return ($d && $d !== '—') ? date('d.m.Y', is_numeric(strtotime($d)) ? strtotime($d) : time()) : '—'; };

$currentType = $_GET['type'] ?? '';
$currentStatus = $_GET['status'] ?? '';
$currentSource = $_GET['source'] ?? '';
$currentSearch = $_GET['search'] ?? '';
$currentDateFrom = $_GET['date_from'] ?? '';
$currentDateTo = $_GET['date_to'] ?? '';

$successFlash = $_SESSION['finance_success'] ?? null;
unset($_SESSION['finance_success']);
$errorFlash = $_SESSION['finance_error'] ?? null;
unset($_SESSION['finance_error']);
?>
<?php if ($company === null): ?>
<div class="notice warn">Компания не найдена. Укажите корректный company_id.</div>
<?php elseif (($company['status'] ?? '') !== 'active'): ?>
<div class="page-head">
    <div class="page-head-left">
        <h1 class="page-title">Финансовые операции</h1>
        <div class="page-summary"><span>Компания находится в неактивном статусе.</span></div>
    </div>
</div>
<div class="notice warn">Работа с операциями недоступна.</div>
<?php elseif ($dbError !== null): ?>
<div class="page-head">
    <div class="page-head-left">
        <h1 class="page-title">Финансовые операции</h1>
        <div class="page-summary"><span>Проведённые и плановые движения денежных средств компании.</span></div>
    </div>
</div>
<div class="notice warn"><?= e($dbError) ?></div>
<?php else: ?>
<div class="page-head">
    <div class="page-head-left">
        <h1 class="page-title">Финансовые операции</h1>
        <div class="page-summary"><span>Проведённые и плановые движения денежных средств компании.</span></div>
    </div>
</div>

<?php if ($successFlash): ?>
<div class="notice success"><?= e($successFlash) ?></div>
<?php endif; ?>
<?php if ($errorFlash): ?>
<div class="notice warn"><?= e($errorFlash) ?></div>
<?php endif; ?>

<form method="get" class="table-toolbar table-toolbar--ops">
    <div class="toolbar-left toolbar-left--ops">
        <input type="date" name="date_from" value="<?= e($currentDateFrom) ?>" class="toolbar-input toolbar-input--ops" placeholder="Дата с">
        <input type="date" name="date_to" value="<?= e($currentDateTo) ?>" class="toolbar-input toolbar-input--ops" placeholder="Дата по">
        <select name="type" class="toolbar-select toolbar-select--ops">
            <option value="">Все типы</option>
            <?php foreach (FinanceOperationService::OPERATION_TYPES as $k => $v): ?>
            <option value="<?= e($k) ?>"<?= $currentType === $k ? ' selected' : '' ?>><?= e($v) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="status" class="toolbar-select toolbar-select--ops">
            <option value="">Все статусы</option>
            <?php foreach (FinanceOperationService::STATUSES as $k => $v): ?>
            <option value="<?= e($k) ?>"<?= $currentStatus === $k ? ' selected' : '' ?>><?= e($v) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="source" class="toolbar-select toolbar-select--ops">
            <option value="">Все источники</option>
            <?php foreach (FinanceOperationService::SOURCES as $k => $v): ?>
            <option value="<?= e($k) ?>"<?= $currentSource === $k ? ' selected' : '' ?>><?= e($v) ?></option>
            <?php endforeach; ?>
        </select>
        <input type="text" name="search" value="<?= e($currentSearch) ?>" class="toolbar-search toolbar-search--ops" placeholder="Поиск по контрагенту или назначению">
        <button type="submit" class="btn btn-primary btn--ops-filter">Применить</button>
        <a href="<?= app_url('/company/finance/operations') ?>" class="btn btn-ghost btn--ops-reset">Сбросить</a>
    </div>
</form>

<div class="page-summary page-summary--ops">
    Показано: <b><?= count($operations) ?></b> из <b><?= $opTotal ?></b> операций.
    Страница <b><?= $currentPage ?></b> из <b><?= $opPages ?></b>.
    Поступления: <b><?= FinanceOperationService::formatAmount($postedIncomeTotal) ?></b>,
    Списания: <b><?= FinanceOperationService::formatAmount($postedExpenseTotal) ?></b>
</div>

<?php if (empty($operations)): ?>
<div class="panel">
    <div class="panel-body">
        <div class="empty-state">
            <p class="empty-title">Операции не найдены.</p>
            <p class="empty-desc">Импортируйте банковскую выписку для автоматического создания операций.</p>
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
                    <th>Статус</th>
                    <th>Источник</th>
                    <th>Контрагент</th>
                    <th>Назначение</th>
                    <th>Поступление</th>
                    <th>Списание</th>
                    <th>Разнесено</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($operations as $op): ?>
                <tr class="tr--ops-row" data-operation-id="<?= (int) ($op['id'] ?? 0) ?>">
                    <td class="col-mono"><?= $fmtDate($op['operation_date'] ?? '') ?></td>
                    <td><?= e($op['account_name'] ?? '—') ?></td>
                    <td><?= e(FinanceOperationService::operationTypeLabel($op['operation_type'] ?? null)) ?></td>
                    <td>
                        <span class="<?= e(FinanceOperationService::statusBadgeClass($op['status'] ?? null)) ?>">
                            <span class="dot"></span>
                            <?= e(FinanceOperationService::statusLabel($op['status'] ?? null)) ?>
                        </span>
                    </td>
                    <td><?= e(FinanceOperationService::sourceLabel($op['source'] ?? null)) ?></td>
                    <td class="td--ops-counterparty" title="<?= e($op['counterparty_name'] ?? '') ?>"><?= e($op['counterparty_name'] ?: '—') ?></td>
                    <td class="td--ops-purpose" title="<?= e($op['purpose'] ?? '') ?>"><?= e($op['purpose'] ?: '—') ?></td>
                    <td class="col-mono"><?= $op['operation_type'] === 'INCOME' ? FinanceOperationService::formatAmount($op['amount'] ?? null) : '—' ?></td>
                    <td class="col-mono"><?= $op['operation_type'] === 'EXPENSE' ? FinanceOperationService::formatAmount($op['amount'] ?? null) : '—' ?></td>
                    <td>
                        <span class="<?= e(FinanceOperationService::allocationStatusBadgeClass($op['allocation_status'] ?? null)) ?>">
                            <span class="dot"></span>
                            <?= e(FinanceOperationService::allocationStatusLabel($op['allocation_status'] ?? null)) ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<div id="operation-view-modal" class="modal-overlay" role="dialog" aria-modal="true" data-close-on-overlay="1" data-close-on-escape="1">
    <div class="modal modal-lg">
        <div class="modal-head">
            <span class="modal-title">Операция</span>
            <button type="button" class="modal-close" data-close-modal="operation-view-modal">&times;</button>
        </div>
        <div class="modal-body" id="operation-view-modal-body">
        </div>
    </div>
</div>

<div id="operation-allocate-modal" class="modal-overlay" role="dialog" aria-modal="true" data-close-on-overlay="0" data-close-on-escape="0">
    <div class="modal modal-lg">
        <div class="modal-head">
            <span class="modal-title">Распределение операции</span>
            <button type="button" class="modal-close" data-close-modal="operation-allocate-modal">&times;</button>
        </div>
        <div class="modal-body" id="operation-allocate-modal-body">
        </div>
    </div>
</div>

<?php
$cancelModalId = 'operation-cancel-modal';
$cancelActionUrl = app_url('/company/finance/operations/0/cancel');
$cancelEntityLabel = 'операции';
require base_path('app/View/partials/company_finance_cancel_modal.php');
?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.table tbody tr').forEach(function(row) {
        row.classList.add('tr--ops-clickable');
        row.addEventListener('dblclick', function() {
            var id = this.dataset.operationId;
            var modal = document.getElementById('operation-view-modal');
            var body = document.getElementById('operation-view-modal-body');
            if (!id || !modal || !body) return;
            body.innerHTML = '<div class="empty-state compact"><p>Загрузка...</p></div>';
            window.openModal('operation-view-modal');
            fetch(window.getErpBasePath() + '/company/finance/operations/' + id + '/modal-view')
                .then(function(r) { return r.text(); })
                .then(function(html) {
                    body.innerHTML = html;
                })
                .catch(function() {
                    body.innerHTML = '<div class="form-alert alert-error">Не удалось загрузить данные операции.</div>';
                });
        });
    });
});
</script>
<?php endif; ?>
