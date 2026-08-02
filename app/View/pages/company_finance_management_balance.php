<?php

$currentAsOf = $_GET['as_of'] ?? date('Y-m-d');
?>
<?php if ($company === null): ?>
<div class="notice warn">Компания не найдена. Укажите корректный company_id.</div>
<?php elseif (($company['status'] ?? '') !== 'active'): ?>
<div class="page-head">
    <div class="page-head-left">
        <h1 class="page-title">Управленческий баланс</h1>
        <div class="page-summary"><span>Компания находится в неактивном статусе.</span></div>
    </div>
</div>
<div class="notice warn">Работа с отчётом недоступна.</div>
<?php elseif ($dbError !== null): ?>
<div class="page-head">
    <div class="page-head-left">
        <h1 class="page-title">Управленческий баланс</h1>
        <div class="page-summary"><span>Сводный отчёт об активах и обязательствах компании.</span></div>
    </div>
</div>
<div class="notice warn"><?= e($dbError) ?></div>
<?php else: ?>
<div class="page-head">
    <div class="page-head-left">
        <h1 class="page-title">Управленческий баланс</h1>
        <div class="page-summary"><span>Сводный отчёт об активах и обязательствах компании.</span></div>
    </div>
</div>

<form method="get" class="table-toolbar table-toolbar--ops">
    <div class="toolbar-left toolbar-left--ops">
        <label class="toolbar-label">На дату:</label>
        <input type="date" name="as_of" value="<?= e($currentAsOf) ?>" class="toolbar-input toolbar-input--ops" max="<?= date('Y-m-d') ?>">
        <button type="submit" class="btn btn-primary btn--ops-filter">Применить</button>
        <a href="<?= app_url('/company/finance/reports/management-balance') ?>" class="btn btn-ghost btn--ops-reset">Сегодня</a>
    </div>
</form>

<div class="page-summary page-summary--ops compact-summary">
    <span>Дата отчёта: <b><?= e(date('d.m.Y', strtotime($balanceData['as_of_date']))) ?></b></span>
</div>

<div class="page-summary page-summary--ops compact-summary">
    <span>Активы: <b><?= \App\Service\FinanceManagementBalanceService::formatAmount($balanceData['total_assets']) ?></b></span>
    <span class="sep">|</span>
    <span>Обязательства: <b class="text-danger"><?= \App\Service\FinanceManagementBalanceService::formatAmount($balanceData['total_liabilities']) ?></b></span>
    <span class="sep">|</span>
    <span>Чистые активы: <b><?= \App\Service\FinanceManagementBalanceService::formatAmount($balanceData['net_assets']) ?></b></span>
</div>

<div class="table-card table-card--standard">
    <div class="table-scroll">
        <table class="table">
            <thead>
                <tr>
                    <th>Группа</th>
                    <th>Наименование</th>
                    <th>Контрагент</th>
                    <th>Источник</th>
                    <th>Сумма</th>
                </tr>
            </thead>
            <tbody>
                <tr class="table-group-row">
                    <td colspan="5"><strong>Активы</strong></td>
                </tr>

                <tr class="table-subgroup-row">
                    <td colspan="5"><strong>Денежные средства</strong></td>
                </tr>
                <?php if (empty($balanceData['money_accounts'])): ?>
                <tr>
                    <td colspan="5" class="text-muted">Нет денежных средств на счетах.</td>
                </tr>
                <?php else: ?>
                <?php foreach ($balanceData['money_accounts'] as $acc): ?>
                <tr>
                    <td></td>
                    <td><?= e($acc['name']) ?></td>
                    <td>—</td>
                    <td><?= e($acc['type']) ?></td>
                    <td class="col-mono"><?= \App\Service\FinanceManagementBalanceService::formatAmount($acc['balance']) ?></td>
                </tr>
                <?php endforeach; ?>
                <tr class="table-total-row">
                    <td colspan="4" class="text-right"><strong>Итого денежные средства</strong></td>
                    <td class="col-mono"><strong><?= \App\Service\FinanceManagementBalanceService::formatAmount($balanceData['total_cash']) ?></strong></td>
                </tr>
                <?php endif; ?>

                <tr class="table-subgroup-row">
                    <td colspan="5"><strong>Дебиторская задолженность</strong></td>
                </tr>
                <?php if (empty($balanceData['receivables'])): ?>
                <tr>
                    <td colspan="5" class="text-muted">Дебиторская задолженность отсутствует.</td>
                </tr>
                <?php else: ?>
                <?php foreach ($balanceData['receivables'] as $r): ?>
                <tr>
                    <td></td>
                    <td><a href="<?= e($r['drilldown_url']) ?>" class="link"><?= e($r['name']) ?></a></td>
                    <td><?= e($r['counterparty']) ?></td>
                    <td><?= e($r['source']) ?></td>
                    <td class="col-mono"><?= \App\Service\FinanceManagementBalanceService::formatAmount($r['remaining']) ?></td>
                </tr>
                <?php endforeach; ?>
                <tr class="table-total-row">
                    <td colspan="4" class="text-right"><strong>Итого дебиторская задолженность</strong></td>
                    <td class="col-mono"><strong><?= \App\Service\FinanceManagementBalanceService::formatAmount($balanceData['total_receivables']) ?></strong></td>
                </tr>
                <?php endif; ?>

                <tr class="table-group-row">
                    <td colspan="5"><strong>Обязательства</strong></td>
                </tr>

                <tr class="table-subgroup-row">
                    <td colspan="5"><strong>Кредиторская задолженность</strong></td>
                </tr>
                <?php if (empty($balanceData['payables'])): ?>
                <tr>
                    <td colspan="5" class="text-muted">Кредиторская задолженность отсутствует.</td>
                </tr>
                <?php else: ?>
                <?php foreach ($balanceData['payables'] as $p): ?>
                <tr>
                    <td></td>
                    <td><a href="<?= e($p['drilldown_url']) ?>" class="link"><?= e($p['name']) ?></a></td>
                    <td><?= e($p['counterparty']) ?></td>
                    <td><?= e($p['source']) ?></td>
                    <td class="col-mono"><?= \App\Service\FinanceManagementBalanceService::formatAmount($p['remaining']) ?></td>
                </tr>
                <?php endforeach; ?>
                <tr class="table-total-row">
                    <td colspan="4" class="text-right"><strong>Итого кредиторская задолженность</strong></td>
                    <td class="col-mono"><strong><?= \App\Service\FinanceManagementBalanceService::formatAmount($balanceData['total_payables']) ?></strong></td>
                </tr>
                <?php endif; ?>

                <tr class="table-subgroup-row">
                    <td colspan="5"><strong>Авансы выданные / полученные</strong></td>
                </tr>
                <tr>
                    <td></td>
                    <td>Авансы выданные</td>
                    <td>—</td>
                    <td>Расчёт будет расширен после внедрения авансов</td>
                    <td class="col-mono">0,00</td>
                </tr>
                <tr>
                    <td></td>
                    <td>Авансы полученные</td>
                    <td>—</td>
                    <td>Расчёт будет расширен после внедрения авансов</td>
                    <td class="col-mono">0,00</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<div class="page-summary page-summary--ops compact-summary">
    <span>Итого Активы: <b><?= \App\Service\FinanceManagementBalanceService::formatAmount($balanceData['total_assets']) ?></b></span>
    <span class="sep">|</span>
    <span>Итого Обязательства: <b class="text-danger"><?= \App\Service\FinanceManagementBalanceService::formatAmount($balanceData['total_liabilities']) ?></b></span>
    <span class="sep">|</span>
    <span>Чистые активы (Активы − Обязательства): <b><?= \App\Service\FinanceManagementBalanceService::formatAmount($balanceData['net_assets']) ?></b></span>
</div>
<?php endif; ?>
