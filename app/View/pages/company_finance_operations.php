<?php

use App\Service\FinanceOperationService;

$fmtDate = static fn($d) => ($d && $d !== '—') ? date('d.m.Y', strtotime((string) $d) ?: time()) : '—';
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
<link rel="stylesheet" href="<?= app_url('/assets/css/finance-operation-actions.css') ?>?v=<?= filemtime(base_path('public/assets/css/finance-operation-actions.css')) ?>">
<?php if ($company === null): ?>
<div class="notice warn">Компания не найдена.</div>
<?php elseif (($company['status'] ?? '') !== 'active'): ?>
<div class="page-head"><div class="page-head-left"><h1 class="page-title">Финансовые операции</h1></div></div><div class="notice warn">Работа с операциями недоступна.</div>
<?php elseif ($dbError !== null): ?>
<div class="page-head"><div class="page-head-left"><h1 class="page-title">Финансовые операции</h1></div></div><div class="notice warn"><?= e($dbError) ?></div>
<?php else: ?>
<div class="page-head"><div class="page-head-left"><h1 class="page-title">Финансовые операции</h1><div class="page-summary"><span>Проведённые и плановые движения денежных средств компании.</span></div></div></div>
<?php if ($successFlash): ?><div class="notice success"><?= e($successFlash) ?></div><?php endif; ?>
<?php if ($errorFlash): ?><div class="notice warn"><?= e($errorFlash) ?></div><?php endif; ?>
<form method="get" class="table-toolbar table-toolbar--ops"><div class="toolbar-left toolbar-left--ops">
<input type="date" name="date_from" value="<?= e($currentDateFrom) ?>" class="toolbar-input toolbar-input--ops"><input type="date" name="date_to" value="<?= e($currentDateTo) ?>" class="toolbar-input toolbar-input--ops">
<select name="type" class="toolbar-select toolbar-select--ops"><option value="">Все типы</option><?php foreach (FinanceOperationService::OPERATION_TYPES as $key => $label): ?><option value="<?= e($key) ?>"<?= $currentType === $key ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select>
<select name="status" class="toolbar-select toolbar-select--ops"><option value="">Все статусы</option><?php foreach (FinanceOperationService::STATUSES as $key => $label): ?><option value="<?= e($key) ?>"<?= $currentStatus === $key ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select>
<select name="source" class="toolbar-select toolbar-select--ops"><option value="">Все источники</option><?php foreach (FinanceOperationService::SOURCES as $key => $label): ?><option value="<?= e($key) ?>"<?= $currentSource === $key ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select>
<input type="text" name="search" value="<?= e($currentSearch) ?>" class="toolbar-search toolbar-search--ops" placeholder="Контрагент или назначение"><button type="submit" class="btn btn-primary btn--ops-filter">Применить</button><a href="<?= app_url('/company/finance/operations') ?>" class="btn btn-ghost btn--ops-reset">Сбросить</a>
</div></form>
<div class="page-summary page-summary--ops">Показано: <b><?= count($operations) ?></b> из <b><?= $opTotal ?></b>. Страница <b><?= $currentPage ?></b> из <b><?= $opPages ?></b>. Поступления: <b><?= FinanceOperationService::formatAmount($postedIncomeTotal) ?></b>, списания: <b><?= FinanceOperationService::formatAmount($postedExpenseTotal) ?></b>. Откройте операцию двойным щелчком.</div>
<?php if (empty($operations)): ?><div class="panel"><div class="panel-body"><div class="empty-state"><p class="empty-title">Операции не найдены.</p><p class="empty-desc">Импортируйте банковскую выписку для автоматического создания операций.</p></div></div></div>
<?php else: ?><div class="table-card table-card--standard"><div class="table-scroll"><table class="table"><thead><tr><th>Дата</th><th>Счёт</th><th>Тип</th><th>Статус</th><th>Источник</th><th>Контрагент</th><th>Назначение</th><th>Поступление</th><th>Списание</th><th>Разнесено</th></tr></thead><tbody>
<?php foreach ($operations as $op): ?><tr class="tr--ops-row tr--ops-clickable" data-operation-id="<?= (int) $op['id'] ?>"><td class="col-mono"><?= $fmtDate($op['operation_date'] ?? '') ?></td><td><?= e($op['account_name'] ?? '—') ?></td><td><?= e(FinanceOperationService::operationTypeLabel($op['operation_type'] ?? null)) ?></td><td><span class="<?= e(FinanceOperationService::statusBadgeClass($op['status'] ?? null)) ?>"><span class="dot"></span><span data-role="operation-status"><?= e(FinanceOperationService::statusLabel($op['status'] ?? null)) ?></span></span></td><td><?= e(FinanceOperationService::sourceLabel($op['source'] ?? null)) ?></td><td class="td--ops-counterparty" title="<?= e($op['counterparty_name'] ?? '') ?>"><?= e($op['counterparty_name'] ?: '—') ?></td><td class="td--ops-purpose" title="<?= e($op['purpose'] ?? '') ?>"><?= e($op['purpose'] ?: '—') ?></td><td class="col-mono"><?= ($op['operation_type'] ?? '') === 'INCOME' ? FinanceOperationService::formatAmount($op['amount'] ?? null) : '—' ?></td><td class="col-mono"><?= ($op['operation_type'] ?? '') === 'EXPENSE' ? FinanceOperationService::formatAmount($op['amount'] ?? null) : '—' ?></td><td><span class="<?= e(FinanceOperationService::allocationStatusBadgeClass($op['allocation_status'] ?? null)) ?>"><span class="dot"></span><span data-role="allocation-status"><?= e(FinanceOperationService::allocationStatusLabel($op['allocation_status'] ?? null)) ?></span></span><small data-role="allocated-amount"><?= FinanceOperationService::formatAmount($op['allocated_amount'] ?? '0.00') ?></small></td></tr><?php endforeach; ?>
</tbody></table></div></div><?php endif; ?>
<div id="operation-view-modal" class="modal-overlay" role="dialog" aria-modal="true" data-close-on-overlay="1" data-close-on-escape="1"><div class="modal modal-lg"><div class="modal-head"><span class="modal-title">Операция</span><button type="button" class="modal-close" data-close-modal="operation-view-modal">&times;</button></div><div class="modal-body" id="operation-view-modal-body"></div></div></div>
<div id="operation-allocate-modal" class="modal-overlay" role="dialog" aria-modal="true" data-close-on-overlay="0" data-close-on-escape="1"><div class="modal modal-lg"><div class="modal-head"><span class="modal-title">Распределение операции</span><button type="button" class="modal-close" data-close-modal="operation-allocate-modal">&times;</button></div><div class="modal-body" id="operation-allocate-modal-body"></div></div></div>
<?php $cancelModalId = 'operation-cancel-modal'; $cancelActionUrl = app_url('/company/finance/operations/0/cancel'); $cancelEntityLabel = 'операции'; require base_path('app/View/partials/company_finance_cancel_modal.php'); ?>
<script src="<?= app_url('/assets/js/finance-operation-actions.js') ?>?v=<?= filemtime(base_path('public/assets/js/finance-operation-actions.js')) ?>"></script>
<?php endif; ?>
