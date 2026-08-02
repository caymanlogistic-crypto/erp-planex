<?php

use App\Service\FinancePaymentPlanFactService;

$fmtDate = function ($d) {
    if (!$d || $d === '—') {
        return '—';
    }
    $ts = strtotime($d);
    if ($ts === false) {
        return '—';
    }
    return date('d.m.Y', $ts);
};

$currentDateFrom = $_GET['date_from'] ?? '';
$currentDateTo = $_GET['date_to'] ?? '';
$currentDirection = $_GET['direction'] ?? '';
$currentStatus = $_GET['status'] ?? '';
$currentSearch = $_GET['search'] ?? '';
?>
<?php if ($company === null): ?>
<div class="notice warn">Компания не найдена. Укажите корректный company_id.</div>
<?php elseif (($company['status'] ?? '') !== 'active'): ?>
<div class="page-head">
    <div class="page-head-left">
        <h1 class="page-title">План-факт оплаты</h1>
        <div class="page-summary"><span>Компания находится в неактивном статусе.</span></div>
    </div>
</div>
<div class="notice warn">Работа с отчётом недоступна.</div>
<?php elseif ($dbError !== null): ?>
<div class="page-head">
    <div class="page-head-left">
        <h1 class="page-title">План-факт оплаты</h1>
        <div class="page-summary"><span>Сводный отчёт по плановым и фактическим оплатам.</span></div>
    </div>
</div>
<div class="notice warn"><?= e($dbError) ?></div>
<?php else: ?>
<div class="page-head">
    <div class="page-head-left">
        <h1 class="page-title">План-факт оплаты</h1>
        <div class="page-summary"><span>Сводный отчёт по плановым и фактическим оплатам.</span></div>
    </div>
</div>

<form method="get" class="table-toolbar table-toolbar--ops">
    <div class="toolbar-left toolbar-left--ops">
        <input type="date" name="date_from" value="<?= e($currentDateFrom) ?>" class="toolbar-input toolbar-input--ops" placeholder="Дата с">
        <input type="date" name="date_to" value="<?= e($currentDateTo) ?>" class="toolbar-input toolbar-input--ops" placeholder="Дата по">
        <select name="direction" class="toolbar-select toolbar-select--ops">
            <option value="">Все направления</option>
            <option value="INCOME"<?= $currentDirection === 'INCOME' ? ' selected' : '' ?>>Поступления</option>
            <option value="EXPENSE"<?= $currentDirection === 'EXPENSE' ? ' selected' : '' ?>>Платежи</option>
        </select>
        <select name="status" class="toolbar-select toolbar-select--ops">
            <option value="">Все статусы</option>
            <option value="paid"<?= $currentStatus === 'paid' ? ' selected' : '' ?>>Оплачено</option>
            <option value="overdue"<?= $currentStatus === 'overdue' ? ' selected' : '' ?>>Просрочено</option>
            <option value="partial"<?= $currentStatus === 'partial' ? ' selected' : '' ?>>Частично оплачено</option>
            <option value="planned"<?= $currentStatus === 'planned' ? ' selected' : '' ?>>Запланировано</option>
        </select>
        <input type="text" name="search" value="<?= e($currentSearch) ?>" class="toolbar-search toolbar-search--ops" placeholder="Поиск по контрагенту или источнику">
        <button type="submit" class="btn btn-primary btn--ops-filter">Применить</button>
        <a href="<?= app_url('/company/finance/reports/payment-plan-fact') ?>" class="btn btn-ghost btn--ops-reset">Сбросить</a>
    </div>
</form>

<?php if (!empty($totals)): ?>
<div class="page-summary page-summary--ops">
    <span>План поступлений: <b><?= FinancePaymentPlanFactService::formatAmount($totals['expected_income']) ?></b></span>
    <span class="sep">|</span>
    <span>Факт поступлений: <b><?= FinancePaymentPlanFactService::formatAmount($totals['received_income']) ?></b></span>
    <span class="sep">|</span>
    <span>Остаток поступлений: <b><?= FinancePaymentPlanFactService::formatAmount($totals['remaining_income']) ?></b></span>
    <span class="sep">|</span>
    <span>Просрочено поступлений: <b class="text-danger"><?= FinancePaymentPlanFactService::formatAmount($totals['overdue_income']) ?></b></span>
</div>
<div class="page-summary page-summary--ops">
    <span>План платежей: <b><?= FinancePaymentPlanFactService::formatAmount($totals['expected_expense']) ?></b></span>
    <span class="sep">|</span>
    <span>Факт платежей: <b><?= FinancePaymentPlanFactService::formatAmount($totals['paid_expense']) ?></b></span>
    <span class="sep">|</span>
    <span>Остаток платежей: <b><?= FinancePaymentPlanFactService::formatAmount($totals['remaining_expense']) ?></b></span>
    <span class="sep">|</span>
    <span>Просрочено платежей: <b class="text-danger"><?= FinancePaymentPlanFactService::formatAmount($totals['overdue_expense']) ?></b></span>
</div>
<div class="page-summary page-summary--ops">
    <span>Нетто-план: <b><?= FinancePaymentPlanFactService::formatAmount($totals['net_plan']) ?></b></span>
    <span class="sep">|</span>
    <span>Нетто-факт: <b><?= FinancePaymentPlanFactService::formatAmount($totals['net_fact']) ?></b></span>
    <span class="sep">|</span>
    <span>Нетто-остаток: <b><?= FinancePaymentPlanFactService::formatAmount($totals['net_remaining']) ?></b></span>
</div>
<?php endif; ?>

<div class="page-summary page-summary--ops">
    Показано: <b><?= count($rows) ?></b> записей.
</div>

<?php if (empty($rows)): ?>
<div class="panel">
    <div class="panel-body">
        <div class="empty-state">
            <p class="empty-title">Записи не найдены.</p>
            <p class="empty-desc">Создайте счета или рейсы с плановыми платежами для отображения в отчёте.</p>
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
                    <th>Сторона</th>
                    <th>Контрагент</th>
                    <th>Источник</th>
                    <th>Рейс</th>
                    <th>План</th>
                    <th>Оплачено</th>
                    <th>Остаток</th>
                    <th>Факт. закрытие</th>
                    <th>Просрочка, дн.</th>
                    <th>Статус</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $r): ?>
                <tr>
                    <td class="col-mono"><?= $r['planned_date'] !== null ? $fmtDate($r['planned_date']) : '—' ?></td>
                    <td>
                        <?php if ($r['side'] === 'INCOME'): ?>
                        <span class="badge badge-ok"><span class="dot"></span>Поступление</span>
                        <?php else: ?>
                        <span class="badge badge-danger"><span class="dot"></span>Платёж</span>
                        <?php endif; ?>
                    </td>
                    <td><?= e($r['counterparty'] ?? '—') ?></td>
                    <td><?= e($r['source_label']) ?></td>
                    <td><?= e($r['route_label'] ?? '—') ?></td>
                    <td class="col-mono"><?= FinancePaymentPlanFactService::formatAmount($r['planned_amount']) ?></td>
                    <td class="col-mono"><?= FinancePaymentPlanFactService::formatAmount($r['paid_amount']) ?></td>
                    <td class="col-mono"><?= FinancePaymentPlanFactService::formatAmount($r['remaining']) ?></td>
                    <td class="col-mono"><?= $r['actual_closed_date'] !== null ? $fmtDate($r['actual_closed_date']) : '—' ?></td>
                    <td class="col-mono"><?= $r['overdue_days'] > 0 ? $r['overdue_days'] : '—' ?></td>
                    <td>
                        <?php if ($r['status'] === 'paid'): ?>
                        <span class="badge badge-ok"><span class="dot"></span>Оплачено</span>
                        <?php elseif ($r['status'] === 'overdue'): ?>
                        <span class="badge badge-danger"><span class="dot"></span>Просрочено</span>
                        <?php elseif ($r['status'] === 'partial'): ?>
                        <span class="badge badge-warning"><span class="dot"></span>Частично</span>
                        <?php else: ?>
                        <span class="badge badge-neutral"><span class="dot"></span>Запланировано</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>
<?php endif; ?>
