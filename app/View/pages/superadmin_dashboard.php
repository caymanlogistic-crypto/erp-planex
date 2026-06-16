<?php

// SUPERADMIN — Центральная панель управления ERP PLANEX

?>
<div class="page-head">
    <div class="page-head-left">
        <span class="page-eyebrow">SUPERADMIN</span>
        <span class="page-title">Центральная панель</span>
    </div>
</div>

<div class="page-content">

    <div class="panel">
        <div class="panel-head">
            <span class="panel-head-title">Управление компаниями</span>
        </div>
        <a href="/superadmin/companies" class="dash-link">
            <span class="dash-link-label">Реестр компаний</span>
            <span class="dash-link-desc">Создание, настройка и управление локальными ERP-системами →</span>
        </a>
    </div>

    <div class="panel">
        <div class="panel-head">
            <span class="panel-head-title">Системная информация</span>
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

    <div class="panel">
        <div class="panel-head">
            <span class="panel-head-title">В разработке</span>
        </div>
        <div class="panel-body">
            <div class="notice info">
                Следующие разделы находятся в разработке: Пользователи SUPERADMIN, Feature toggles, Системные настройки и мониторинг.
            </div>
        </div>
    </div>

</div>
