<?php

/**
 * Company Dashboard — operational metrics per Package 6 TASK-008.
 *
 * Variables expected:
 *   $companyName     — string|null
 *   $roleCode        — 'company_owner' | 'logist'
 *   $companyError    — bool
 *   $metrics         — array of metric counts
 *   $logistNoAccess  — bool, true when logist has no data/grants
 */

if ($companyError): ?>
<div class="page-head">
    <div class="page-head-left">
        <span class="page-eyebrow">ДАШБОРД</span>
        <span class="page-title">Обзор</span>
    </div>
</div>
<div class="page-content">
    <div class="notice warn">Не удалось загрузить данные компании. Обратитесь к администратору.</div>
</div>

<?php elseif ($logistNoAccess): ?>
<div class="page-head">
    <div class="page-head-left">
        <span class="page-eyebrow">ДАШБОРД</span>
        <span class="page-title">Обзор</span>
    </div>
</div>
<div class="page-content">
    <div class="panel">
        <div class="panel-body">
            <p class="text-muted">У вас пока нет доступа к данным компании. Обратитесь к руководителю для получения доступа.</p>
        </div>
    </div>
</div>

<?php elseif ($roleCode === 'company_owner'): ?>
<div class="page-head">
    <div class="page-head-left">
        <span class="page-eyebrow">ДАШБОРД</span>
        <span class="page-title">Обзор</span>
    </div>
</div>
<div class="page-content">
    <!-- Метрики компании -->
    <div class="panel">
        <div class="panel-body">
            <div class="metric-grid" style="display:grid; grid-template-columns:repeat(auto-fill, minmax(180px, 1fr)); gap:12px;">

                <div class="metric-card" style="background:var(--surface-strong, #f8f6f0); padding:12px; border:1px solid var(--line-soft, #d9d5cc); border-radius:2px;">
                    <div class="metric-value" style="font-size:24px; font-weight:600;"><?= (int)($metrics['total_contractors'] ?? 0) ?></div>
                    <div class="metric-label" style="font-size:11px; color:var(--text-faint, #999); text-transform:uppercase;">Подрядчики (всего)</div>
                </div>

                <div class="metric-card" style="background:var(--surface-strong, #f8f6f0); padding:12px; border:1px solid var(--line-soft, #d9d5cc); border-radius:2px;">
                    <div class="metric-value" style="font-size:24px; font-weight:600;"><?= (int)($metrics['active_contractors'] ?? 0) ?></div>
                    <div class="metric-label" style="font-size:11px; color:var(--text-faint, #999); text-transform:uppercase;">Подрядчики (активные)</div>
                </div>

                <div class="metric-card" style="background:var(--surface-strong, #f8f6f0); padding:12px; border:1px solid var(--line-soft, #d9d5cc); border-radius:2px;">
                    <div class="metric-value" style="font-size:24px; font-weight:600;"><?= (int)($metrics['total_drivers'] ?? 0) ?></div>
                    <div class="metric-label" style="font-size:11px; color:var(--text-faint, #999); text-transform:uppercase;">Водители (всего)</div>
                </div>

                <div class="metric-card" style="background:var(--surface-strong, #f8f6f0); padding:12px; border:1px solid var(--line-soft, #d9d5cc); border-radius:2px;">
                    <div class="metric-value" style="font-size:24px; font-weight:600;"><?= (int)($metrics['active_drivers'] ?? 0) ?></div>
                    <div class="metric-label" style="font-size:11px; color:var(--text-faint, #999); text-transform:uppercase;">Водители (активные)</div>
                </div>

                <div class="metric-card" style="background:var(--surface-strong, #f8f6f0); padding:12px; border:1px solid var(--line-soft, #d9d5cc); border-radius:2px;">
                    <div class="metric-value" style="font-size:24px; font-weight:600;"><?= (int)($metrics['total_vehicles'] ?? 0) ?></div>
                    <div class="metric-label" style="font-size:11px; color:var(--text-faint, #999); text-transform:uppercase;">Транспортные единицы (всего)</div>
                </div>

                <div class="metric-card" style="background:var(--surface-strong, #f8f6f0); padding:12px; border:1px solid var(--line-soft, #d9d5cc); border-radius:2px;">
                    <div class="metric-value" style="font-size:24px; font-weight:600;"><?= (int)($metrics['active_vehicles'] ?? 0) ?></div>
                    <div class="metric-label" style="font-size:11px; color:var(--text-faint, #999); text-transform:uppercase;">Транспортные единицы (активные)</div>
                </div>

                <div class="metric-card" style="background:var(--surface-strong, #f8f6f0); padding:12px; border:1px solid var(--line-soft, #d9d5cc); border-radius:2px;">
                    <div class="metric-value" style="font-size:24px; font-weight:600;"><?= (int)($metrics['total_vehicle_sets'] ?? 0) ?></div>
                    <div class="metric-label" style="font-size:11px; color:var(--text-faint, #999); text-transform:uppercase;">Транспортные комплекты (всего)</div>
                </div>

                <div class="metric-card" style="background:var(--surface-strong, #f8f6f0); padding:12px; border:1px solid var(--line-soft, #d9d5cc); border-radius:2px;">
                    <div class="metric-value" style="font-size:24px; font-weight:600;"><?= (int)($metrics['active_vehicle_sets'] ?? 0) ?></div>
                    <div class="metric-label" style="font-size:11px; color:var(--text-faint, #999); text-transform:uppercase;">Транспортные комплекты (активные)</div>
                </div>

                <div class="metric-card" style="background:var(--surface-strong, #f8f6f0); padding:12px; border:1px solid var(--line-soft, #d9d5cc); border-radius:2px;">
                    <div class="metric-value" style="font-size:24px; font-weight:600;"><?= (int)($metrics['total_dvbs'] ?? 0) ?></div>
                    <div class="metric-label" style="font-size:11px; color:var(--text-faint, #999); text-transform:uppercase;">Блоки Водитель+ТС (всего)</div>
                </div>

                <div class="metric-card" style="background:var(--surface-strong, #f8f6f0); padding:12px; border:1px solid var(--line-soft, #d9d5cc); border-radius:2px;">
                    <div class="metric-value" style="font-size:24px; font-weight:600;"><?= (int)($metrics['active_dvbs'] ?? 0) ?></div>
                    <div class="metric-label" style="font-size:11px; color:var(--text-faint, #999); text-transform:uppercase;">Блоки Водитель+ТС (активные)</div>
                </div>

                <div class="metric-card" style="background:var(--surface-strong, #f8f6f0); padding:12px; border:1px solid var(--line-soft, #d9d5cc); border-radius:2px;">
                    <div class="metric-value" style="font-size:24px; font-weight:600;"><?= (int)($metrics['total_crews'] ?? 0) ?></div>
                    <div class="metric-label" style="font-size:11px; color:var(--text-faint, #999); text-transform:uppercase;">Экипажи (всего)</div>
                </div>

                <div class="metric-card" style="background:var(--surface-strong, #f8f6f0); padding:12px; border:1px solid var(--line-soft, #d9d5cc); border-radius:2px;">
                    <div class="metric-value" style="font-size:24px; font-weight:600;"><?= (int)($metrics['active_crews'] ?? 0) ?></div>
                    <div class="metric-label" style="font-size:11px; color:var(--text-faint, #999); text-transform:uppercase;">Экипажи (активные)</div>
                </div>

                <div class="metric-card" style="background:var(--surface-strong, #f8f6f0); padding:12px; border:1px solid var(--line-soft, #d9d5cc); border-radius:2px;">
                    <div class="metric-value" style="font-size:24px; font-weight:600;"><?= (int)($metrics['archived_crews'] ?? 0) ?></div>
                    <div class="metric-label" style="font-size:11px; color:var(--text-faint, #999); text-transform:uppercase;">Экипажи (архивные)</div>
                </div>

                <div class="metric-card" style="background:var(--surface-strong, #f8f6f0); padding:12px; border:1px solid var(--line-soft, #d9d5cc); border-radius:2px;">
                    <div class="metric-value" style="font-size:24px; font-weight:600;"><?= (int)($metrics['total_documents'] ?? 0) ?></div>
                    <div class="metric-label" style="font-size:11px; color:var(--text-faint, #999); text-transform:uppercase;">Документов загружено</div>
                </div>

            </div><!-- .metric-grid -->
        </div><!-- .panel-body -->
    </div><!-- .panel -->
</div><!-- .page-content -->

<?php else: /* logist with data */ ?>
<div class="page-head">
    <div class="page-head-left">
        <span class="page-eyebrow">ДАШБОРД</span>
        <span class="page-title">Обзор</span>
    </div>
</div>
<div class="page-content">
    <!-- Мои метрики -->
    <div class="panel">
        <div class="panel-body">
            <div class="metric-grid" style="display:grid; grid-template-columns:repeat(auto-fill, minmax(180px, 1fr)); gap:12px;">

                <div class="metric-card" style="background:var(--surface-strong, #f8f6f0); padding:12px; border:1px solid var(--line-soft, #d9d5cc); border-radius:2px;">
                    <div class="metric-value" style="font-size:24px; font-weight:600;"><?= (int)($metrics['my_contractors'] ?? 0) ?></div>
                    <div class="metric-label" style="font-size:11px; color:var(--text-faint, #999); text-transform:uppercase;">Мои подрядчики</div>
                </div>

                <div class="metric-card" style="background:var(--surface-strong, #f8f6f0); padding:12px; border:1px solid var(--line-soft, #d9d5cc); border-radius:2px;">
                    <div class="metric-value" style="font-size:24px; font-weight:600;"><?= (int)($metrics['my_drivers'] ?? 0) ?></div>
                    <div class="metric-label" style="font-size:11px; color:var(--text-faint, #999); text-transform:uppercase;">Мои водители</div>
                </div>

                <div class="metric-card" style="background:var(--surface-strong, #f8f6f0); padding:12px; border:1px solid var(--line-soft, #d9d5cc); border-radius:2px;">
                    <div class="metric-value" style="font-size:24px; font-weight:600;"><?= (int)($metrics['my_vehicles'] ?? 0) ?></div>
                    <div class="metric-label" style="font-size:11px; color:var(--text-faint, #999); text-transform:uppercase;">Мои транспортные единицы</div>
                </div>

                <div class="metric-card" style="background:var(--surface-strong, #f8f6f0); padding:12px; border:1px solid var(--line-soft, #d9d5cc); border-radius:2px;">
                    <div class="metric-value" style="font-size:24px; font-weight:600;"><?= (int)($metrics['my_vehicle_sets'] ?? 0) ?></div>
                    <div class="metric-label" style="font-size:11px; color:var(--text-faint, #999); text-transform:uppercase;">Мои комплекты</div>
                </div>

                <div class="metric-card" style="background:var(--surface-strong, #f8f6f0); padding:12px; border:1px solid var(--line-soft, #d9d5cc); border-radius:2px;">
                    <div class="metric-value" style="font-size:24px; font-weight:600;"><?= (int)($metrics['my_dvbs'] ?? 0) ?></div>
                    <div class="metric-label" style="font-size:11px; color:var(--text-faint, #999); text-transform:uppercase;">Мои блоки Водитель+ТС</div>
                </div>

                <div class="metric-card" style="background:var(--surface-strong, #f8f6f0); padding:12px; border:1px solid var(--line-soft, #d9d5cc); border-radius:2px;">
                    <div class="metric-value" style="font-size:24px; font-weight:600;"><?= (int)($metrics['my_crews'] ?? 0) ?></div>
                    <div class="metric-label" style="font-size:11px; color:var(--text-faint, #999); text-transform:uppercase;">Мои экипажи</div>
                </div>

                <div class="metric-card" style="background:var(--surface-strong, #f8f6f0); padding:12px; border:1px solid var(--line-soft, #d9d5cc); border-radius:2px;">
                    <div class="metric-value" style="font-size:24px; font-weight:600;"><?= (int)($metrics['grants_count'] ?? 0) ?></div>
                    <div class="metric-label" style="font-size:11px; color:var(--text-faint, #999); text-transform:uppercase;">Доступно через grants</div>
                </div>

            </div><!-- .metric-grid -->
        </div><!-- .panel-body -->
    </div><!-- .panel -->
</div><!-- .page-content -->
<?php endif; ?>
