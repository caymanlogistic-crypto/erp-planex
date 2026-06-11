<?php

echo ui_page_header('SUPERADMIN — Центральная панель', '');

?>

<div class="summary-cards">
    <div class="summary-card">
        <div class="summary-card-icon">[=]</div>
        <div class="summary-card-value">&mdash;</div>
        <div class="summary-card-label">Компании</div>
    </div>
    <div class="summary-card">
        <div class="summary-card-icon">[#]</div>
        <div class="summary-card-value">&mdash;</div>
        <div class="summary-card-label">Пользователи SUPERADMIN</div>
    </div>
    <div class="summary-card">
        <div class="summary-card-icon">[~]</div>
        <div class="summary-card-value">&mdash;</div>
        <div class="summary-card-label">Активные features</div>
    </div>
    <div class="summary-card">
        <div class="summary-card-icon">[v]</div>
        <div class="summary-card-value">OK</div>
        <div class="summary-card-label">Статус системы</div>
    </div>
</div>

<div class="alert alert-info">
    <strong>SUPERADMIN &mdash; центральная панель управления ERP PLANEX.</strong>
    <br>
    Здесь вы сможете управлять компаниями, пользователями SUPERADMIN, доступностью функций (feature toggles) и системными настройками.
    <br>
    Функционал находится в разработке и будет добавляться поэтапно.
</div>

<h2>Будущие разделы SUPERADMIN</h2>

<div class="placeholder-nav">
    <div class="placeholder-nav-item is-disabled" aria-disabled="true">
        <span class="placeholder-nav-icon">[&#x1F3E2;]</span>
        <span>Управление компаниями</span>
        <span class="badge-soon">Скоро</span>
    </div>
    <div class="placeholder-nav-item is-disabled" aria-disabled="true">
        <span class="placeholder-nav-icon">[&#x1F464;]</span>
        <span>Пользователи SUPERADMIN</span>
        <span class="badge-soon">Скоро</span>
    </div>
    <div class="placeholder-nav-item is-disabled" aria-disabled="true">
        <span class="placeholder-nav-icon">[&#x2699;]</span>
        <span>Feature toggles</span>
        <span class="badge-soon">Скоро</span>
    </div>
    <div class="placeholder-nav-item is-disabled" aria-disabled="true">
        <span class="placeholder-nav-icon">[&#x1F527;]</span>
        <span>Системные настройки</span>
        <span class="badge-soon">Скоро</span>
    </div>
    <div class="placeholder-nav-item is-disabled" aria-disabled="true">
        <span class="placeholder-nav-icon">[&#x1F4CA;]</span>
        <span>Мониторинг</span>
        <span class="badge-soon">Скоро</span>
    </div>
    <div class="placeholder-nav-item is-disabled" aria-disabled="true">
        <span class="placeholder-nav-icon">[&#x1F4CB;]</span>
        <span>Логи и аудит</span>
        <span class="badge-soon">Скоро</span>
    </div>
</div>

<div class="cards-grid">
    <div class="module-card">
        <div class="module-card-icon">[&#x1F3E2;]</div>
        <div class="module-card-title">Управление компаниями</div>
        <div class="module-card-status status status-neutral">В разработке</div>
        <div class="module-card-desc">Создание и настройка локальных ERP-систем, управление папками и БД компаний</div>
    </div>
    <div class="module-card">
        <div class="module-card-icon">[&#x1F464;]</div>
        <div class="module-card-title">Пользователи SUPERADMIN</div>
        <div class="module-card-status status status-neutral">В разработке</div>
        <div class="module-card-desc">Управление учётными записями SUPERADMIN, авторизация, роли</div>
    </div>
    <div class="module-card">
        <div class="module-card-icon">[&#x2699;]</div>
        <div class="module-card-title">Feature toggles</div>
        <div class="module-card-status status status-neutral">В разработке</div>
        <div class="module-card-desc">Управление доступностью модулей, страниц и отчётов по компаниям</div>
    </div>
    <div class="module-card">
        <div class="module-card-icon">[&#x1F527;]</div>
        <div class="module-card-title">Системные настройки</div>
        <div class="module-card-status status status-neutral">В разработке</div>
        <div class="module-card-desc">Общие параметры системы, мониторинг, логи и аудит</div>
    </div>
</div>

<div class="empty-state" hidden>
    <h3>Модули SUPERADMIN ещё не реализованы</h3>
    <p>Модули SUPERADMIN ещё не реализованы. Следите за обновлениями.</p>
</div>
