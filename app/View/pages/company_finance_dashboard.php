<?php

use App\Service\FinanceDashboardService;

?>
<?php if ($company === null): ?>
<div class="notice warn">Компания не найдена. Укажите корректный company_id.</div>
<?php elseif (($company['status'] ?? '') !== 'active'): ?>
<div class="page-head">
    <div class="page-head-left">
        <h1 class="page-title">Финансовый дашборд</h1>
        <div class="page-summary"><span>Компания находится в неактивном статусе.</span></div>
    </div>
</div>
<div class="notice warn">Работа с финансовым дашбордом недоступна.</div>
<?php elseif ($dbError !== null): ?>
<div class="page-head">
    <div class="page-head-left">
        <h1 class="page-title">Финансовый дашборд</h1>
        <div class="page-summary"><span>Сводка финансового состояния компании.</span></div>
    </div>
</div>
<div class="notice warn"><?= e($dbError) ?></div>
<?php else: ?>
<div class="page-head">
    <div class="page-head-left">
        <h1 class="page-title">Финансовый дашборд</h1>
        <div class="page-summary"><span>Сводка финансового состояния компании.</span></div>
    </div>
</div>

<div class="panel-section">
    <div class="section-title">Денежные средства</div>
    <div class="dashboard-grid">
        <a href="<?= app_url('/company/finance/bank-accounts') ?>" class="dashboard-card">
            <div class="dashboard-card-label">Остатки по банкам</div>
            <div class="dashboard-card-value"><?= FinanceDashboardService::formatAmount($dashboard['bank_total'] ?? '0.00') ?></div>
            <?php if (!empty($dashboard['bank_accounts'])): ?>
            <div class="dashboard-card-meta">
                <?php foreach ($dashboard['bank_accounts'] as $ba): ?>
                <span><?= e($ba['name']) ?>: <?= FinanceDashboardService::formatAmount($ba['balance']) ?></span>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </a>
        <a href="<?= app_url('/company/finance/cash') ?>" class="dashboard-card">
            <div class="dashboard-card-label">Остатки по кассам</div>
            <div class="dashboard-card-value"><?= FinanceDashboardService::formatAmount($dashboard['cash_total'] ?? '0.00') ?></div>
            <?php if (!empty($dashboard['cash_accounts'])): ?>
            <div class="dashboard-card-meta">
                <?php foreach ($dashboard['cash_accounts'] as $ca): ?>
                <span><?= e($ca['name']) ?>: <?= FinanceDashboardService::formatAmount($ca['balance']) ?></span>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </a>
        <a href="<?= app_url('/company/finance/bank-accounts') ?>" class="dashboard-card dashboard-card--accent">
            <div class="dashboard-card-label">Общая сумма денег</div>
            <div class="dashboard-card-value"><?= FinanceDashboardService::formatAmount($dashboard['total_cash'] ?? '0.00') ?></div>
        </a>
    </div>
</div>

<div class="panel-section">
    <div class="section-title">Планирование</div>
    <div class="dashboard-grid">
        <a href="<?= app_url('/company/finance/payment-calendar') ?>?direction=INCOME" class="dashboard-card">
            <div class="dashboard-card-label">Ожидаемые поступления</div>
            <div class="dashboard-card-value"><?= FinanceDashboardService::formatAmount($dashboard['expected_inflow'] ?? '0.00') ?></div>
        </a>
        <a href="<?= app_url('/company/finance/payment-calendar') ?>?direction=EXPENSE" class="dashboard-card dashboard-card--outflow">
            <div class="dashboard-card-label">Предстоящие платежи</div>
            <div class="dashboard-card-value"><?= FinanceDashboardService::formatAmount($dashboard['expected_outflow'] ?? '0.00') ?></div>
        </a>
        <a href="<?= app_url('/company/finance/payment-calendar') ?>?status=overdue&direction=INCOME" class="dashboard-card dashboard-card--danger">
            <div class="dashboard-card-label">Просроченная дебиторка</div>
            <div class="dashboard-card-value"><?= FinanceDashboardService::formatAmount($dashboard['overdue_receivables'] ?? '0.00') ?></div>
        </a>
        <a href="<?= app_url('/company/finance/payment-calendar') ?>?status=overdue&direction=EXPENSE" class="dashboard-card dashboard-card--danger">
            <div class="dashboard-card-label">Просроченная кредиторка</div>
            <div class="dashboard-card-value"><?= FinanceDashboardService::formatAmount($dashboard['overdue_payables'] ?? '0.00') ?></div>
        </a>
    </div>
</div>

<div class="panel-section">
    <div class="section-title">Операции и риски</div>
    <div class="dashboard-grid">
        <a href="<?= app_url('/company/finance/operations') ?>" class="dashboard-card">
            <div class="dashboard-card-label">Неразобранные операции</div>
            <div class="dashboard-card-value"><?= (int)($dashboard['unallocated_count'] ?? 0) ?></div>
            <div class="dashboard-card-meta">операций без разнесения</div>
        </a>
        <a href="<?= app_url('/company/finance/payment-calendar') ?>" class="dashboard-card <?= ($dashboard['cash_gap_date'] ?? null) ? 'dashboard-card--danger' : 'dashboard-card--ok' ?>">
            <div class="dashboard-card-label">Кассовый разрыв</div>
            <?php if ($dashboard['cash_gap_date'] ?? null): ?>
            <div class="dashboard-card-value"><?= FinanceDashboardService::formatAmount($dashboard['cash_gap_amount']) ?></div>
            <div class="dashboard-card-meta">на <?= date('d.m.Y', strtotime($dashboard['cash_gap_date'])) ?></div>
            <?php else: ?>
            <div class="dashboard-card-value">—</div>
            <div class="dashboard-card-meta">не прогнозируется</div>
            <?php endif; ?>
        </a>
    </div>
</div>
<?php endif; ?>
