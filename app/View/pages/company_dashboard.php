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
            <div class="metric-grid">

                <div class="metric-card">
                    <div class="metric-value"><?= (int)($metrics['total_contractors'] ?? 0) ?></div>
                    <div class="metric-label">Подрядчики (всего)</div>
                </div>

                <div class="metric-card">
                    <div class="metric-value"><?= (int)($metrics['active_contractors'] ?? 0) ?></div>
                    <div class="metric-label">Подрядчики (активные)</div>
                </div>

                <div class="metric-card">
                    <div class="metric-value"><?= (int)($metrics['total_drivers'] ?? 0) ?></div>
                    <div class="metric-label">Водители (всего)</div>
                </div>

                <div class="metric-card">
                    <div class="metric-value"><?= (int)($metrics['active_drivers'] ?? 0) ?></div>
                    <div class="metric-label">Водители (активные)</div>
                </div>

                <div class="metric-card">
                    <div class="metric-value"><?= (int)($metrics['total_vehicles'] ?? 0) ?></div>
                    <div class="metric-label">Транспортные единицы (всего)</div>
                </div>

                <div class="metric-card">
                    <div class="metric-value"><?= (int)($metrics['active_vehicles'] ?? 0) ?></div>
                    <div class="metric-label">Транспортные единицы (активные)</div>
                </div>

                <div class="metric-card">
                    <div class="metric-value"><?= (int)($metrics['total_vehicle_sets'] ?? 0) ?></div>
                    <div class="metric-label">Транспортные комплекты (всего)</div>
                </div>

                <div class="metric-card">
                    <div class="metric-value"><?= (int)($metrics['active_vehicle_sets'] ?? 0) ?></div>
                    <div class="metric-label">Транспортные комплекты (активные)</div>
                </div>

                <div class="metric-card">
                    <div class="metric-value"><?= (int)($metrics['total_dvbs'] ?? 0) ?></div>
                    <div class="metric-label">Блоки Водитель+ТС (всего)</div>
                </div>

                <div class="metric-card">
                    <div class="metric-value"><?= (int)($metrics['active_dvbs'] ?? 0) ?></div>
                    <div class="metric-label">Блоки Водитель+ТС (активные)</div>
                </div>

                <div class="metric-card">
                    <div class="metric-value"><?= (int)($metrics['total_crews'] ?? 0) ?></div>
                    <div class="metric-label">Экипажи (всего)</div>
                </div>

                <div class="metric-card">
                    <div class="metric-value"><?= (int)($metrics['active_crews'] ?? 0) ?></div>
                    <div class="metric-label">Экипажи (активные)</div>
                </div>

                <div class="metric-card">
                    <div class="metric-value"><?= (int)($metrics['archived_crews'] ?? 0) ?></div>
                    <div class="metric-label">Экипажи (удалённые)</div>
                </div>

                <div class="metric-card">
                    <div class="metric-value"><?= (int)($metrics['total_documents'] ?? 0) ?></div>
                    <div class="metric-label">Документов загружено</div>
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
            <div class="metric-grid">

                <div class="metric-card">
                    <div class="metric-value"><?= (int)($metrics['my_contractors'] ?? 0) ?></div>
                    <div class="metric-label">Мои подрядчики</div>
                </div>

                <div class="metric-card">
                    <div class="metric-value"><?= (int)($metrics['my_drivers'] ?? 0) ?></div>
                    <div class="metric-label">Мои водители</div>
                </div>

                <div class="metric-card">
                    <div class="metric-value"><?= (int)($metrics['my_vehicles'] ?? 0) ?></div>
                    <div class="metric-label">Мои транспортные единицы</div>
                </div>

                <div class="metric-card">
                    <div class="metric-value"><?= (int)($metrics['my_vehicle_sets'] ?? 0) ?></div>
                    <div class="metric-label">Мои комплекты</div>
                </div>

                <div class="metric-card">
                    <div class="metric-value"><?= (int)($metrics['my_dvbs'] ?? 0) ?></div>
                    <div class="metric-label">Мои блоки Водитель+ТС</div>
                </div>

                <div class="metric-card">
                    <div class="metric-value"><?= (int)($metrics['my_crews'] ?? 0) ?></div>
                    <div class="metric-label">Мои экипажи</div>
                </div>

                <div class="metric-card">
                    <div class="metric-value"><?= (int)($metrics['grants_count'] ?? 0) ?></div>
                    <div class="metric-label">Доступно через grants</div>
                </div>

            </div><!-- .metric-grid -->
        </div><!-- .panel-body -->
    </div><!-- .panel -->
</div><!-- .page-content -->
<?php endif; ?>
