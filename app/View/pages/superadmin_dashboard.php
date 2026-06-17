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
        <span class="page-eyebrow">ДАШБОРД</span>
        <span class="page-title">Обзор</span>
    </div>
</div>

<div class="page-content">

    <!-- Компании: метрики -->
    <div class="panel">
        <div class="panel-head">
            <span class="panel-head-title">Компании</span>
        </div>
        <div class="panel-body">
            <div class="metric-grid" style="display:grid; grid-template-columns:repeat(auto-fill, minmax(180px, 1fr)); gap:12px;">

                <div class="metric-card" style="background:var(--surface-strong, #f8f6f0); padding:12px; border:1px solid var(--line-soft, #d9d5cc); border-radius:2px;">
                    <div class="metric-value" style="font-size:24px; font-weight:600;"><?= (int)($metrics['total_companies'] ?? 0) ?></div>
                    <div class="metric-label" style="font-size:11px; color:var(--text-faint, #999); text-transform:uppercase;">Всего компаний</div>
                </div>

                <div class="metric-card" style="background:var(--surface-strong, #f8f6f0); padding:12px; border:1px solid var(--line-soft, #d9d5cc); border-radius:2px;">
                    <div class="metric-value" style="font-size:24px; font-weight:600;"><?= (int)($metrics['active_companies'] ?? 0) ?></div>
                    <div class="metric-label" style="font-size:11px; color:var(--text-faint, #999); text-transform:uppercase;">Активных</div>
                </div>

                <div class="metric-card" style="background:var(--surface-strong, #f8f6f0); padding:12px; border:1px solid var(--line-soft, #d9d5cc); border-radius:2px;">
                    <div class="metric-value" style="font-size:24px; font-weight:600;"><?= (int)($metrics['blocked_companies'] ?? 0) ?></div>
                    <div class="metric-label" style="font-size:11px; color:var(--text-faint, #999); text-transform:uppercase;">Заблокировано</div>
                </div>

                <div class="metric-card" style="background:var(--surface-strong, #f8f6f0); padding:12px; border:1px solid var(--line-soft, #d9d5cc); border-radius:2px;">
                    <div class="metric-value" style="font-size:24px; font-weight:600;"><?= (int)($metrics['archived_companies'] ?? 0) ?></div>
                    <div class="metric-label" style="font-size:11px; color:var(--text-faint, #999); text-transform:uppercase;">Архивных</div>
                </div>

                <div class="metric-card" style="background:var(--surface-strong, #f8f6f0); padding:12px; border:1px solid var(--line-soft, #d9d5cc); border-radius:2px;">
                    <div class="metric-value" style="font-size:24px; font-weight:600;"><?= (int)($metrics['companies_without_owner'] ?? 0) ?></div>
                    <div class="metric-label" style="font-size:11px; color:var(--text-faint, #999); text-transform:uppercase;">Без руководителя</div>
                </div>

                <div class="metric-card" style="background:var(--surface-strong, #f8f6f0); padding:12px; border:1px solid var(--line-soft, #d9d5cc); border-radius:2px;">
                    <div class="metric-value" style="font-size:24px; font-weight:600;"><?= (int)($metrics['companies_without_users'] ?? 0) ?></div>
                    <div class="metric-label" style="font-size:11px; color:var(--text-faint, #999); text-transform:uppercase;">Без пользователей</div>
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
