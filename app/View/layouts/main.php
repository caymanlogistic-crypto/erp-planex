<?php

/**
 * Base ERP PLANEX layout.
 *
 * Technical UI foundation only: no business routes, DB or auth.
 */

$appName = $config['app']['app_name'] ?? 'ERP PLANEX';
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle ?? $appName) ?> — <?= e($appName) ?></title>
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
    <div class="app-shell">
        <header class="topbar">
            <div class="topbar-brand">
                <div class="logo-mark"></div>
                <span class="brand-name">ERP PLANEX</span>
            </div>
            <div class="topbar-crumbs">
                <span><?= e($pageTitle ?? 'SUPERADMIN') ?></span>
                <span class="sep">—</span>
                <b><?= $pageContext ?? 'Центральная панель управления' ?></b>
            </div>
            <div class="topbar-right">
                <div class="avatar">EA</div>
                <div class="user-info">
                    <strong>ERP Admin</strong>
                    <span>Суперадминистратор</span>
                </div>
            </div>
        </header>

        <aside class="app-sidebar" aria-label="Основная навигация">
            <div class="nav-group" style="padding-top:8px">
                <div class="nav-section-label">ОПЕРАЦИИ</div>
                <span class="nav-item is-disabled">
                    <svg class="nav-icon" viewBox="0 0 16 16" fill="none"><rect x="1" y="8.5" width="9.5" height="5" rx="1" stroke="currentColor" stroke-width="1.4"/><path d="M10.5 11H13C13.8 11 14.5 10.4 14.5 9.5C14.5 8.6 13.8 8.5 13 8.5H10.5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/><circle cx="3.5" cy="13.5" r="1.3" fill="currentColor"/><circle cx="8.5" cy="13.5" r="1.3" fill="currentColor"/><path d="M1 8.5V6.5C1 6 1.4 5.5 2 5.5H6.5L9 2.5H10.5C11 2.5 11.5 3 11.5 3.5V8.5" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/></svg>
                    <span class="nav-label">Рейсы</span>
                </span>
                <span class="nav-item is-disabled">
                    <svg class="nav-icon" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="5.5" r="2.8" stroke="currentColor" stroke-width="1.4"/><path d="M2 14C2 11.2 4.7 9 8 9C11.3 9 14 11.2 14 14" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>
                    <span class="nav-label">Водители</span>
                </span>
                <span class="nav-item is-disabled">
                    <svg class="nav-icon" viewBox="0 0 16 16" fill="none"><rect x="1" y="6.5" width="14" height="6" rx="1" stroke="currentColor" stroke-width="1.4"/><path d="M1 9H15" stroke="currentColor" stroke-width="1.4"/><path d="M5 6.5V5C5 4.4 5.4 4 6 4H10C10.6 4 11 4.4 11 5V6.5" stroke="currentColor" stroke-width="1.4"/><circle cx="4.5" cy="12.5" r="1.3" fill="currentColor"/><circle cx="11.5" cy="12.5" r="1.3" fill="currentColor"/></svg>
                    <span class="nav-label">Транспорт</span>
                </span>
                <span class="nav-item is-disabled">
                    <svg class="nav-icon" viewBox="0 0 16 16" fill="none"><rect x="2" y="5.5" width="12" height="8.5" rx="1" stroke="currentColor" stroke-width="1.4"/><path d="M5 5.5V4C5 3.4 5.4 3 6 3H10C10.6 3 11 3.4 11 4V5.5" stroke="currentColor" stroke-width="1.4"/><path d="M2 9.5H14" stroke="currentColor" stroke-width="1.4"/><path d="M7.5 9.5V12" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>
                    <span class="nav-label">Клиенты</span>
                </span>
            </div>

            <div class="nav-spacer"></div>

            <div class="nav-group">
                <div class="nav-section-label">СИСТЕМА</div>
                <a class="nav-item<?= str_starts_with($_SERVER['REQUEST_URI'], '/superadmin') ? ' is-active' : '' ?>" href="/superadmin">
                    <svg class="nav-icon" viewBox="0 0 16 16" fill="none"><path d="M8 2L14 4.5V8C14 11.5 11 14 8 15C5 14 2 11.5 2 8V4.5L8 2Z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/><path d="M5.5 8L7.5 10L10.5 6" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    <span class="nav-label">SUPERADMIN</span>
                </a>
            </div>

            <div class="nav-bottom">
                <span class="nav-item is-disabled">
                    <svg class="nav-icon" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="2.5" stroke="currentColor" stroke-width="1.4"/><path d="M8 1.5V3.5M8 12.5V14.5M2.5 8H4.5M11.5 8H13.5M3.4 3.4L4.8 4.8M11.2 11.2L12.6 12.6M3.4 12.6L4.8 11.2M11.2 4.8L12.6 3.4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>
                    <span class="nav-label">Настройки</span>
                </span>
            </div>
        </aside>

        <div class="app-main">
            <main class="content">
                <?= $content ?>
            </main>
        </div>
    </div>

    <script src="/assets/js/app.js"></script>
</body>
</html>
