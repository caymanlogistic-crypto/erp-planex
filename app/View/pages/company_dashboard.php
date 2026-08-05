<?php

/**
 * ERP PLANEX operational dashboard.
 * Uses only metrics already calculated by the current controller.
 */

$metric = static fn(string $key): int => (int) ($metrics[$key] ?? 0);
$ownerAttention = max(0, $metric('total_contractors') - $metric('active_contractors'))
    + max(0, $metric('total_drivers') - $metric('active_drivers'))
    + max(0, $metric('total_vehicle_sets') - $metric('active_vehicle_sets'))
    + $metric('archived_crews');
?>

<?php if ($companyError): ?>
<div class="page-head">
    <div class="page-head-left">
        <h1 class="page-title">Обзор</h1>
        <div class="page-summary"><span>Операционный центр компании</span></div>
    </div>
</div>
<div class="page-content">
    <div class="notice warn">Не удалось загрузить данные компании. Обновите страницу или обратитесь к администратору.</div>
</div>

<?php elseif ($logistNoAccess): ?>
<div class="page-head">
    <div class="page-head-left">
        <h1 class="page-title">Обзор</h1>
        <div class="page-summary"><span>Ваши рабочие данные и быстрые действия</span></div>
    </div>
</div>
<div class="page-content">
    <div class="panel">
        <div class="panel-body empty-state empty-state-left">
            <h2>Рабочие данные пока не назначены</h2>
            <p class="text-muted">У вас нет собственных записей или активных доступов. Руководитель либо Логист+ может назначить ответственного или выдать доступ.</p>
            <a class="btn btn-primary" href="<?= app_url('/company/trips/linear?show_create=1') ?>">Создать первый рейс</a>
        </div>
    </div>
</div>

<?php elseif ($roleCode === 'company_owner'): ?>
<div class="page-head">
    <div class="page-head-left">
        <h1 class="page-title">Обзор компании</h1>
        <div class="page-summary"><span>Операционные справочники, документы и действия руководителя</span></div>
    </div>
    <div class="page-head-actions">
        <a class="btn btn-secondary" href="<?= app_url('/company/finance/dashboard') ?>">Открыть финансы</a>
        <a class="btn btn-primary" href="<?= app_url('/company/trips/linear?show_create=1') ?>">Создать рейс</a>
    </div>
</div>

<div class="page-content">
    <div class="dashboard-grid">
        <a class="metric-card" href="<?= app_url('/company/contractors') ?>">
            <div class="metric-label">Активные подрядчики</div>
            <div class="metric-value"><?= $metric('active_contractors') ?></div>
            <div class="field-hint">из <?= $metric('total_contractors') ?> записей</div>
        </a>
        <a class="metric-card" href="<?= app_url('/company/drivers') ?>">
            <div class="metric-label">Активные водители</div>
            <div class="metric-value"><?= $metric('active_drivers') ?></div>
            <div class="field-hint">из <?= $metric('total_drivers') ?> записей</div>
        </a>
        <a class="metric-card" href="<?= app_url('/company/vehicle-sets') ?>">
            <div class="metric-label">Комплекты транспорта</div>
            <div class="metric-value"><?= $metric('active_vehicle_sets') ?></div>
            <div class="field-hint"><?= $metric('active_vehicles') ?> активных единиц ТС</div>
        </a>
        <a class="metric-card" href="<?= app_url('/company/trips/linear') ?>">
            <div class="metric-label">Документы в системе</div>
            <div class="metric-value"><?= $metric('total_documents') ?></div>
            <div class="field-hint">договоры, заявки и файлы сущностей</div>
        </a>
    </div>

    <div class="command-center">
        <section class="panel command-main">
            <div class="panel-head">
                <div>
                    <div class="section-title">Готовность справочников</div>
                    <div class="field-hint">Данные, необходимые для оформления и исполнения рейса</div>
                </div>
            </div>
            <div class="readiness-list">
                <div class="readiness-item <?= $metric('active_contractors') > 0 ? 'is-ok' : 'is-warn' ?>">
                    <span class="readiness-mark" aria-hidden="true"></span>
                    <span class="readiness-copy"><strong class="readiness-title">Подрядчики</strong><span class="readiness-desc">Перевозчики и исполнители для рейсов</span></span>
                    <a class="btn btn-secondary" href="<?= app_url('/company/contractors') ?>"><?= $metric('active_contractors') ?></a>
                </div>
                <div class="readiness-item <?= $metric('active_drivers') > 0 ? 'is-ok' : 'is-warn' ?>">
                    <span class="readiness-mark" aria-hidden="true"></span>
                    <span class="readiness-copy"><strong class="readiness-title">Водители</strong><span class="readiness-desc">Активные водители и их документы</span></span>
                    <a class="btn btn-secondary" href="<?= app_url('/company/drivers') ?>"><?= $metric('active_drivers') ?></a>
                </div>
                <div class="readiness-item <?= $metric('active_vehicle_sets') > 0 ? 'is-ok' : 'is-warn' ?>">
                    <span class="readiness-mark" aria-hidden="true"></span>
                    <span class="readiness-copy"><strong class="readiness-title">Транспорт</strong><span class="readiness-desc">Одиночные ТС и транспортные комплекты</span></span>
                    <a class="btn btn-secondary" href="<?= app_url('/company/vehicle-sets') ?>"><?= $metric('active_vehicle_sets') ?></a>
                </div>
                <div class="readiness-item <?= $metric('active_dvbs') > 0 ? 'is-ok' : 'is-warn' ?>">
                    <span class="readiness-mark" aria-hidden="true"></span>
                    <span class="readiness-copy"><strong class="readiness-title">Исполнители рейса</strong><span class="readiness-desc">Связки водитель + комплект ТС</span></span>
                    <a class="btn btn-secondary" href="<?= app_url('/company/route-executors') ?>"><?= $metric('active_dvbs') ?></a>
                </div>
            </div>
        </section>

        <aside class="command-aside">
            <div class="next-action <?= $ownerAttention > 0 ? 'is-critical' : '' ?>">
                <span class="next-action-label">Требует внимания</span>
                <strong><?= $ownerAttention > 0 ? $ownerAttention . ' неактивных или архивных записей' : 'Критичных пробелов не обнаружено' ?></strong>
                <span><?= $ownerAttention > 0 ? 'Проверьте архивные и неактивные справочники перед назначением исполнителей.' : 'Основные справочники готовы к операционной работе.' ?></span>
                <a class="btn btn-secondary" href="<?= app_url('/company/responsible-assignments') ?>">Ответственные</a>
            </div>
        </aside>
    </div>

    <section class="panel">
        <div class="panel-head">
            <div>
                <div class="section-title">Быстрые действия</div>
                <div class="field-hint">Частые операции руководителя</div>
            </div>
        </div>
        <div class="subject-actions">
            <a class="btn btn-primary" href="<?= app_url('/company/trips/linear?show_create=1') ?>">Новый рейс</a>
            <a class="btn btn-secondary" href="<?= app_url('/company/clients') ?>">Клиенты</a>
            <a class="btn btn-secondary" href="<?= app_url('/company/route-executors') ?>">Исполнители</a>
            <a class="btn btn-secondary" href="<?= app_url('/company/finance/invoices') ?>">Счета</a>
            <a class="btn btn-secondary" href="<?= app_url('/company/finance/operations') ?>">Операции</a>
            <a class="btn btn-secondary" href="<?= app_url('/company/logists') ?>">Команда</a>
        </div>
    </section>
</div>

<?php else: ?>
<div class="page-head">
    <div class="page-head-left">
        <h1 class="page-title">Мой рабочий обзор</h1>
        <div class="page-summary"><span>Назначенные справочники и доступные операции</span></div>
    </div>
    <div class="page-head-actions">
        <a class="btn btn-primary" href="<?= app_url('/company/trips/linear?show_create=1') ?>">Создать рейс</a>
    </div>
</div>

<div class="page-content">
    <div class="dashboard-grid">
        <a class="metric-card" href="<?= app_url('/company/contractors') ?>"><div class="metric-label">Мои подрядчики</div><div class="metric-value"><?= $metric('my_contractors') ?></div></a>
        <a class="metric-card" href="<?= app_url('/company/drivers') ?>"><div class="metric-label">Мои водители</div><div class="metric-value"><?= $metric('my_drivers') ?></div></a>
        <a class="metric-card" href="<?= app_url('/company/vehicle-sets') ?>"><div class="metric-label">Мои комплекты</div><div class="metric-value"><?= $metric('my_vehicle_sets') ?></div></a>
        <a class="metric-card" href="<?= app_url('/company/route-executors') ?>"><div class="metric-label">Исполнители</div><div class="metric-value"><?= $metric('my_dvbs') ?></div></a>
    </div>

    <div class="command-center">
        <section class="panel command-main">
            <div class="panel-head"><div><div class="section-title">Рабочие данные</div><div class="field-hint">Собственные записи и доступы, выданные руководителем</div></div></div>
            <div class="readiness-list">
                <div class="readiness-item <?= $metric('my_contractors') > 0 ? 'is-ok' : 'is-warn' ?>"><span class="readiness-mark"></span><span class="readiness-copy"><strong class="readiness-title">Подрядчики</strong><span class="readiness-desc">Доступны для выбора в рейсах</span></span><strong><?= $metric('my_contractors') ?></strong></div>
                <div class="readiness-item <?= $metric('my_drivers') > 0 ? 'is-ok' : 'is-warn' ?>"><span class="readiness-mark"></span><span class="readiness-copy"><strong class="readiness-title">Водители</strong><span class="readiness-desc">Собственные и назначенные записи</span></span><strong><?= $metric('my_drivers') ?></strong></div>
                <div class="readiness-item <?= $metric('grants_count') > 0 ? 'is-ok' : 'is-warn' ?>"><span class="readiness-mark"></span><span class="readiness-copy"><strong class="readiness-title">Дополнительные доступы</strong><span class="readiness-desc">Записи, переданные через grants</span></span><strong><?= $metric('grants_count') ?></strong></div>
            </div>
        </section>
        <aside class="command-aside">
            <div class="next-action">
                <span class="next-action-label">Следующее действие</span>
                <strong>Проверьте активные рейсы</strong>
                <span>Откройте реестр рейсов или создайте новый рейс с доступными участниками.</span>
                <a class="btn btn-primary" href="<?= app_url('/company/trips/linear') ?>">Открыть рейсы</a>
            </div>
        </aside>
    </div>
</div>
<?php endif; ?>
