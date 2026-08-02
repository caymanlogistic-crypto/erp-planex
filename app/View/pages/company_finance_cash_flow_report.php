<?php

use App\Service\FinanceCashFlowReportService;

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
$currentMoneyAccountId = $_GET['money_account_id'] ?? '';
$currentDirection = $_GET['direction'] ?? 'all';
$currentDdsCategoryId = $_GET['dds_category_id'] ?? '';
$currentSearch = $_GET['search'] ?? '';
?>
<?php if ($company === null): ?>
<div class="notice warn">Компания не найдена. Укажите корректный company_id.</div>
<?php elseif (($company['status'] ?? '') !== 'active'): ?>
<div class="page-head">
    <div class="page-head-left">
        <h1 class="page-title">БДДС</h1>
        <div class="page-summary"><span>Компания находится в неактивном статусе.</span></div>
    </div>
</div>
<div class="notice warn">Работа с отчётом БДДС недоступна.</div>
<?php elseif ($dbError !== null): ?>
<div class="page-head">
    <div class="page-head-left">
        <h1 class="page-title">БДДС</h1>
        <div class="page-summary"><span>Фактическое движение денежных средств по статьям ДДС.</span></div>
    </div>
</div>
<div class="notice warn"><?= e($dbError) ?></div>
<?php else: ?>
<div class="page-head">
    <div class="page-head-left">
        <h1 class="page-title">БДДС</h1>
        <div class="page-summary"><span>Фактическое движение денежных средств по статьям ДДС.</span></div>
    </div>
</div>

<form method="get" class="table-toolbar table-toolbar--ops">
    <div class="toolbar-left toolbar-left--ops">
        <input type="date" name="date_from" value="<?= e($currentDateFrom) ?>" class="toolbar-input toolbar-input--ops" placeholder="Дата с">
        <input type="date" name="date_to" value="<?= e($currentDateTo) ?>" class="toolbar-input toolbar-input--ops" placeholder="Дата по">
        <select name="money_account_id" class="toolbar-select toolbar-select--ops">
            <option value="">Все счета</option>
            <?php if (!empty($moneyAccounts)): ?>
            <?php foreach ($moneyAccounts as $ma): ?>
            <option value="<?= e($ma['id']) ?>"<?= $currentMoneyAccountId === (string)$ma['id'] ? ' selected' : '' ?>><?= e($ma['name']) ?> (<?= e($ma['type']) ?>)</option>
            <?php endforeach; ?>
            <?php endif; ?>
        </select>
        <select name="direction" class="toolbar-select toolbar-select--ops">
            <option value="all"<?= $currentDirection === 'all' ? ' selected' : '' ?>>Все направления</option>
            <option value="INCOME"<?= $currentDirection === 'INCOME' ? ' selected' : '' ?>>Поступления</option>
            <option value="EXPENSE"<?= $currentDirection === 'EXPENSE' ? ' selected' : '' ?>>Расходы</option>
        </select>
        <select name="dds_category_id" class="toolbar-select toolbar-select--ops">
            <option value="">Все статьи</option>
            <option value="null"<?= $currentDdsCategoryId === 'null' ? ' selected' : '' ?>>Без статьи</option>
            <?php if (!empty($ddsCategories)): ?>
            <?php foreach ($ddsCategories as $dc): ?>
            <option value="<?= e($dc['id']) ?>"<?= $currentDdsCategoryId === (string)$dc['id'] ? ' selected' : '' ?>><?= e($dc['code']) ?> — <?= e($dc['name']) ?></option>
            <?php endforeach; ?>
            <?php endif; ?>
        </select>
        <input type="text" name="search" value="<?= e($currentSearch) ?>" class="toolbar-search toolbar-search--ops" placeholder="Поиск по статье ДДС">
        <button type="submit" class="btn btn-primary btn--ops-filter">Применить</button>
        <a href="<?= app_url('/company/finance/reports/cash-flow') ?>" class="btn btn-ghost btn--ops-reset">Сбросить</a>
    </div>
</form>

<?php if (!empty($summary)): ?>
<div class="page-summary page-summary--ops compact-summary">
    <span>Входящий остаток: <b><?= FinanceCashFlowReportService::formatAmount($summary['opening_balance']) ?></b></span>
    <span class="sep">|</span>
    <span>Поступления: <b class="text-success"><?= FinanceCashFlowReportService::formatAmount($summary['total_income']) ?></b></span>
    <span class="sep">|</span>
    <span>Расходы: <b class="text-danger"><?= FinanceCashFlowReportService::formatAmount($summary['total_expense']) ?></b></span>
    <span class="sep">|</span>
    <span>Чистый поток: <b><?= FinanceCashFlowReportService::formatAmount($summary['net_cash_flow']) ?></b></span>
    <span class="sep">|</span>
    <span>Исходящий остаток: <b><?= FinanceCashFlowReportService::formatAmount($summary['closing_balance']) ?></b></span>
</div>
<div class="page-summary page-summary--ops compact-summary text-muted">
    <span>Переводы: входящие <b><?= FinanceCashFlowReportService::formatAmount($summary['transfer_in']) ?></b>, исходящие <b><?= FinanceCashFlowReportService::formatAmount($summary['transfer_out']) ?></b>, нетто <b><?= FinanceCashFlowReportService::formatAmount($summary['transfer_net']) ?></b></span>
    <span class="sep">|</span>
    <span>Корректировки: <b><?= FinanceCashFlowReportService::formatAmount($summary['adjustment_total']) ?></b></span>
</div>
<div class="page-summary page-summary--ops">
    <span class="text-muted">Внутренние переводы — справочное движение и не влияют на чистый поток БДДС. Если выбран конкретный счёт, нетто-перевод может быть ненулевым.</span>
</div>
<?php endif; ?>

<div class="page-summary page-summary--ops">
    Показано: <b><?= count($rows) ?></b> статей.
</div>

<?php if (empty($rows)): ?>
<div class="panel">
    <div class="panel-body">
        <div class="empty-state">
            <p class="empty-title">Записи не найдены.</p>
            <p class="empty-desc">Создайте проведённые операции для отображения в отчёте БДДС.</p>
        </div>
    </div>
</div>
<?php else: ?>
<div class="table-card table-card--standard">
    <div class="table-scroll">
        <table class="table">
            <thead>
                <tr>
                    <th>Статья ДДС</th>
                    <th>Код</th>
                    <th>Направление</th>
                    <th>Поступления</th>
                    <th>Расходы</th>
                    <th>Нетто</th>
                    <th>Кол-во операций</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $r): ?>
                <tr>
                    <td>
                        <?php if ($r['is_uncategorized']): ?>
                        <?= e($r['name']) ?>
                        <?php else:
                        $linkParams = 'date_from=' . urlencode($currentDateFrom ?: date('Y-m-01')) . '&date_to=' . urlencode($currentDateTo ?: date('Y-m-t'));
                        if ($r['direction'] === 'INCOME') {
                            $linkParams .= '&type=INCOME';
                        } elseif ($r['direction'] === 'EXPENSE') {
                            $linkParams .= '&type=EXPENSE';
                        }
                        ?>
                        <a href="<?= app_url('/company/finance/operations?' . $linkParams) ?>" class="link">
                            <?= e($r['name']) ?>
                        </a>
                        <?php endif; ?>
                    </td>
                    <td class="col-mono"><?= e($r['code'] ?: '—') ?></td>
                    <td>
                        <?php if ($r['direction'] === 'INCOME'): ?>
                        <span class="badge badge-ok"><span class="dot"></span>Поступление</span>
                        <?php elseif ($r['direction'] === 'EXPENSE'): ?>
                        <span class="badge badge-danger"><span class="dot"></span>Расход</span>
                        <?php else: ?>
                        <span class="badge badge-neutral"><span class="dot"></span>Оба</span>
                        <?php endif; ?>
                    </td>
                    <td class="col-mono"><?= FinanceCashFlowReportService::formatAmount($r['fact_income']) ?></td>
                    <td class="col-mono"><?= FinanceCashFlowReportService::formatAmount($r['fact_expense']) ?></td>
                    <td class="col-mono"><?= FinanceCashFlowReportService::formatAmount($r['net_flow']) ?></td>
                    <td><?= (int) $r['operation_count'] ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>
<?php endif; ?>
