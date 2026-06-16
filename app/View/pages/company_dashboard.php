<?php

/**
 * Company Dashboard — minimal stub page per company-dashboard.md §3-4.
 *
 * Variables expected:
 *   $companyName  — string|null, companies.name from central DB
 *   $roleCode     — 'company_owner' | 'logist'
 *   $companyId    — int, company ID from session
 *   $companyError — bool, true if company not found
 */

if ($companyError): ?>
<div class="page-head">
    <div class="page-head-left">
        <h1>Панель управления</h1>
        <p>Компания не найдена</p>
    </div>
</div>
<div class="notice warn">Не удалось загрузить данные компании. Обратитесь к администратору.</div>
<?php else: ?>
<div class="page-head">
    <div class="page-head-left">
        <h1>Компания: <?= e($companyName) ?></h1>
        <p>Панель управления</p>
    </div>
</div>

<div class="notice" style="margin-bottom:10px">
    Система в разработке. Доступные разделы:
</div>

<div class="panel">
    <div class="panel-head">
        <h2>Разделы</h2>
    </div>
    <div class="panel-body">

        <?php if ($roleCode === 'company_owner'): ?>
        <a href="/company/logists" class="dash-link">
            <span class="dash-link-label">Пользователи</span>
            <span class="dash-link-desc">Управление пользователями компании</span>
        </a>
        <?php endif; ?>

        <a href="/company/clients" class="dash-link">
            <span class="dash-link-label">Клиенты</span>
            <span class="dash-link-desc">Реестр клиентов-заказчиков перевозок</span>
        </a>

        <a href="/company/contractors" class="dash-link">
            <span class="dash-link-label">Подрядчики</span>
            <span class="dash-link-desc">Реестр подрядчиков-перевозчиков</span>
        </a>

        <a href="/company/drivers" class="dash-link">
            <span class="dash-link-label">Водители</span>
            <span class="dash-link-desc">Реестр водителей</span>
        </a>

        <a href="/company/vehicles" class="dash-link">
            <span class="dash-link-label">Транспорт</span>
            <span class="dash-link-desc">Реестр транспортных средств</span>
        </a>

        <a href="/company/crews" class="dash-link">
            <span class="dash-link-label">Экипажи</span>
            <span class="dash-link-desc">Связки подрядчик + водитель + транспорт</span>
        </a>

    </div>
</div>
<?php endif; ?>
