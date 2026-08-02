<?php

/**
 * Superadmin Dashboard — operational metrics per Package 6 UX-002.
 *
 * Variables expected:
 *   $metrics         — array of company metric counts
 *   $recentCompanies — array of last 5 created companies
 */

?>
<div class="page-head">
    <div class="page-head-left">
        <h1 class="page-title">Обзор</h1>
        <div class="page-summary"><span>ДАШБОРД</span></div>
    </div>
</div>

<div class="page-content">

    <!-- Компании: метрики -->
    <div class="panel">
        <div class="panel-head">
            <span class="panel-head-title">Компании</span>
        </div>
        <div class="panel-body">
            <div class="metric-grid">

                <div class="metric-card">
                    <div class="metric-value"><?= (int)($metrics['total_companies'] ?? 0) ?></div>
                    <div class="metric-label">Всего компаний</div>
                </div>

                <div class="metric-card">
                    <div class="metric-value"><?= (int)($metrics['active_companies'] ?? 0) ?></div>
                    <div class="metric-label">Активных</div>
                </div>

                <div class="metric-card">
                    <div class="metric-value"><?= (int)($metrics['blocked_companies'] ?? 0) ?></div>
                    <div class="metric-label">Заблокировано</div>
                </div>

                <div class="metric-card">
                    <div class="metric-value"><?= (int)($metrics['archived_companies'] ?? 0) ?></div>
                    <div class="metric-label">Архивных</div>
                </div>

                <div class="metric-card">
                    <div class="metric-value"><?= (int)($metrics['companies_without_owner'] ?? 0) ?></div>
                    <div class="metric-label">Без руководителя</div>
                </div>

                <div class="metric-card">
                    <div class="metric-value"><?= (int)($metrics['companies_without_users'] ?? 0) ?></div>
                    <div class="metric-label">Без пользователей</div>
                </div>

            </div><!-- .metric-grid -->
        </div><!-- .panel-body -->
    </div><!-- .panel -->

    <!-- Последние 5 созданных компаний -->
    <?php if (!empty($recentCompanies)): ?>
    <div class="panel">
        <div class="panel-head">
            <span class="panel-head-title">Последние компании</span>
        </div>
        <div class="panel-body">
            <table class="tbl">
                <thead>
                    <tr>
                        <th>Название</th>
                        <th>ИНН</th>
                        <th>Статус</th>
                        <th>Дата создания</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentCompanies as $rc): ?>
                    <tr>
                        <td>
                            <a href="/superadmin/companies/<?= (int)$rc['id'] ?>"><?= e($rc['name'] ?? '') ?></a>
                        </td>
                        <td><?= e($rc['inn'] ?? '') ?></td>
                        <td>
                            <?php $status = $rc['status'] ?? ''; ?>
                            <?php if ($status === 'active'): ?>
                                <span class="badge badge-ok">Активна</span>
                            <?php elseif ($status === 'blocked'): ?>
                                <span class="badge badge-danger">Заблокирована</span>
                            <?php elseif ($status === 'archived'): ?>
                                <span class="badge badge-neutral">Архив</span>
                            <?php else: ?>
                                <span class="badge badge-warning"><?= e($status) ?></span>
                            <?php endif; ?>
                        </td>
                        <td><?= e($rc['created_at'] ?? '') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

</div><!-- .page-content -->
