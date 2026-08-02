<?php

use App\Service\FinancePaymentCalendarService;

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
        <h1 class="page-title">Платёжный календарь</h1>
        <div class="page-summary"><span>Компания находится в неактивном статусе.</span></div>
    </div>
</div>
<div class="notice warn">Работа с платёжным календарём недоступна.</div>
<?php elseif ($dbError !== null): ?>
<div class="page-head">
    <div class="page-head-left">
        <h1 class="page-title">Платёжный календарь</h1>
        <div class="page-summary"><span>Плановые поступления и платежи по счетам и рейсам.</span></div>
    </div>
</div>
<div class="notice warn"><?= e($dbError) ?></div>
<?php else: ?>
<div class="page-head">
    <div class="page-head-left">
        <h1 class="page-title">Платёжный календарь</h1>
        <div class="page-summary"><span>Плановые поступления и платежи по счетам и рейсам.</span></div>
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
            <option value="planned"<?= $currentStatus === 'planned' ? ' selected' : '' ?>>Запланировано</option>
            <option value="partial"<?= $currentStatus === 'partial' ? ' selected' : '' ?>>Частично оплачено</option>
            <option value="overdue"<?= $currentStatus === 'overdue' ? ' selected' : '' ?>>Просрочено</option>
            <option value="waiting_event"<?= $currentStatus === 'waiting_event' ? ' selected' : '' ?>>Ожидает события</option>
        </select>
        <input type="text" name="search" value="<?= e($currentSearch) ?>" class="toolbar-search toolbar-search--ops" placeholder="Поиск по контрагенту или источнику">
        <button type="submit" class="btn btn-primary btn--ops-filter">Применить</button>
        <a href="<?= app_url('/company/finance/payment-calendar') ?>" class="btn btn-ghost btn--ops-reset">Сбросить</a>
    </div>
</form>

<?php if (!empty($summary)): ?>
<div class="page-summary page-summary--ops page-summary--calendar-kpis">
    <span>Ожидаемые поступления: <b><?= FinancePaymentCalendarService::formatAmount($summary['expected_income']) ?></b></span>
    <span class="sep">|</span>
    <span>Ожидаемые платежи: <b><?= FinancePaymentCalendarService::formatAmount($summary['expected_expense']) ?></b></span>
    <span class="sep">|</span>
    <span>Просрочено поступлений: <b class="text-danger"><?= FinancePaymentCalendarService::formatAmount($summary['overdue_income']) ?></b></span>
    <span class="sep">|</span>
    <span>Просрочено платежей: <b class="text-danger"><?= FinancePaymentCalendarService::formatAmount($summary['overdue_expense']) ?></b></span>
    <span class="sep">|</span>
    <span>Нетто-план: <b><?= FinancePaymentCalendarService::formatAmount($summary['net_plan']) ?></b></span>
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
            <p class="empty-desc">Создайте счета или рейсы с плановыми платежами для отображения в календаре.</p>
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
                    <th>Направление</th>
                    <th>Источник</th>
                    <th>Контрагент</th>
                    <th>Рейс</th>
                    <th>Сумма</th>
                    <th>Оплачено</th>
                    <th>Остаток</th>
                    <th>Статус</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $r): ?>
                <tr>
                    <td class="col-mono"><?= $r['due_date'] !== null ? $fmtDate($r['due_date']) : 'Ожидает события' ?></td>
                    <td>
                        <?php if ($r['direction'] === 'INCOME'): ?>
                        <span class="badge badge-ok"><span class="dot"></span>Поступление</span>
                        <?php else: ?>
                        <span class="badge badge-danger"><span class="dot"></span>Платёж</span>
                        <?php endif; ?>
                    </td>
                    <td><?= e($r['source_label']) ?></td>
                    <td><?= e($r['counterparty'] ?? '—') ?></td>
                    <td><?= e($r['route_label'] ?? '—') ?></td>
                    <td class="col-mono"><?= FinancePaymentCalendarService::formatAmount($r['amount'] ?? null) ?></td>
                    <td class="col-mono"><?= FinancePaymentCalendarService::formatAmount($r['paid'] ?? null) ?></td>
                    <td class="col-mono"><?= FinancePaymentCalendarService::formatAmount($r['remaining'] ?? null) ?></td>
                    <td>
                        <?php if ($r['calendar_status'] === 'overdue'): ?>
                        <span class="badge badge-danger"><span class="dot"></span>Просрочено</span>
                        <?php elseif ($r['calendar_status'] === 'partial'): ?>
                        <span class="badge badge-warning"><span class="dot"></span>Частично</span>
                        <?php elseif ($r['calendar_status'] === 'waiting_event'): ?>
                        <span class="badge badge-neutral"><span class="dot"></span>Ожидает события</span>
                        <?php else: ?>
                        <span class="badge badge-ok"><span class="dot"></span>Запланировано</span>
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
