<?php

// SUPERADMIN — Центральная панель управления ERP PLANEX
// Admin/settings pattern: строгая панель управления

?>
<div class="page-head">
    <div>
        <h1>SUPERADMIN</h1>
        <p class="text-muted">Центральная панель управления ERP PLANEX</p>
    </div>
</div>

<div class="notice">
    Центральная панель управления ERP PLANEX. Функционал находится в разработке.
</div>

<div class="panel">
    <div class="panel-head">
        <h3 class="panel-head-title">Управление компаниями</h3>
        <span class="badge">В разработке</span>
    </div>
    <div class="panel-body">
        <p class="text-muted">Создание, настройка и управление локальными ERP-системами</p>
        <button class="btn btn-secondary disabled">Настроить позже</button>
    </div>
</div>

<div class="panel">
    <div class="panel-head">
        <h3 class="panel-head-title">Пользователи SUPERADMIN</h3>
        <span class="badge">В разработке</span>
    </div>
    <div class="panel-body">
        <p class="text-muted">Управление учётными записями администраторов системы</p>
        <button class="btn btn-secondary disabled">Настроить позже</button>
    </div>
</div>

<div class="panel">
    <div class="panel-head">
        <h3 class="panel-head-title">Feature toggles</h3>
        <span class="badge">В разработке</span>
    </div>
    <div class="panel-body">
        <p class="text-muted">Управление доступностью модулей, страниц и отчётов по компаниям</p>
        <button class="btn btn-secondary disabled">Настроить позже</button>
    </div>
</div>

<div class="panel">
    <div class="panel-head">
        <h3 class="panel-head-title">Системные настройки</h3>
        <span class="badge">В разработке</span>
    </div>
    <div class="panel-body">
        <p class="text-muted">Общие параметры системы, мониторинг, логи, аудит</p>
        <button class="btn btn-secondary disabled">Настроить позже</button>
    </div>
</div>

<div class="panel">
    <div class="panel-head">
        <h3 class="panel-head-title">Системная информация</h3>
    </div>
    <div class="panel-body">
        <dl class="kv">
            <dt>Система</dt>
            <dd><?= e($config['app']['app_name'] ?? 'ERP PLANEX') ?></dd>
            <dt>Среда</dt>
            <dd><?= e($config['app']['app_env'] ?? 'development') ?></dd>
            <dt>Статус БД</dt>
            <dd>не проверялся</dd>
        </dl>
    </div>
</div>
