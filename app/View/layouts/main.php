<?php

/**
 * Base ERP PLANEX layout with dynamic sidebar and topbar.
 *
 * Variables expected:
 *   $config     — app config
 *   $pageTitle  — string, page title for topbar crumbs
 *   $pageContext — string, page context for topbar crumbs
 */

$appName = $config['app']['app_name'] ?? 'ERP PLANEX';
$roleCode = $_SESSION['role_code'] ?? '';
$companyId = $_SESSION['company_id'] ?? 0;

$userName = $_SESSION['user_name'] ?? '';
$roleLabel = match($roleCode) {
    'superadmin' => 'Суперадминистратор',
    'company_owner' => 'Руководитель',
    'logist' => 'Логист',
    default => '',
};

$initials = '';
if ($userName !== '') {
    $words = array_filter(explode(' ', trim($userName)));
    $initials = implode('', array_map(fn($w) => mb_substr($w, 0, 1), $words));
    $initials = mb_strtoupper(mb_substr($initials, 0, 2));
}
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle ?? $appName) ?> — <?= e($appName) ?></title>
    <link rel="stylesheet" href="/assets/css/erp-ui.css">
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
                <span><?= e($pageTitle ?? 'ERP PLANEX') ?></span>
                <?php if (!empty($pageContext ?? '')): ?>
                <span class="sep">—</span>
                <b><?= e($pageContext) ?></b>
                <?php endif; ?>
            </div>
            <div class="topbar-right">
                <?php if (!empty($_SESSION['user_id'])): ?>
                <div class="avatar"><?= e($initials) ?></div>
                <div class="user-info">
                    <strong><?= e($userName) ?></strong>
                    <span><?= e($roleLabel) ?></span>
                </div>
                <a href="/logout" class="btn btn-ghost">Выйти</a>
                <?php else: ?>
                <span class="text-muted">ERP PLANEX</span>
                <?php endif; ?>
            </div>
        </header>

        <aside class="app-sidebar" aria-label="Основная навигация">

            <?php if ($roleCode === 'superadmin'): ?>

            <div class="nav-group">
                <div class="nav-section-label">СИСТЕМА</div>
                <a class="nav-item<?= str_starts_with($_SERVER['REQUEST_URI'], '/superadmin') ? ' is-active' : '' ?>" href="/superadmin/companies">
                    <svg class="nav-icon" viewBox="0 0 16 16" fill="none"><rect x="2" y="4" width="12" height="11" rx="1" stroke="currentColor" stroke-width="1.4"/><path d="M5 15V11H11V15" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/><path d="M2 7H14" stroke="currentColor" stroke-width="1.4"/><rect x="5" y="5" width="2" height="2" rx=".3" fill="currentColor"/><rect x="9" y="5" width="2" height="2" rx=".3" fill="currentColor"/><rect x="5" y="9" width="2" height="2" rx=".3" fill="currentColor"/><rect x="9" y="9" width="2" height="2" rx=".3" fill="currentColor"/></svg>
                    <span class="nav-label">Компании</span>
                </a>
            </div>

            <div class="nav-bottom">
                <span class="nav-item is-disabled">
                    <svg class="nav-icon" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="2.5" stroke="currentColor" stroke-width="1.4"/><path d="M8 1.5V3.5M8 12.5V14.5M2.5 8H4.5M11.5 8H13.5M3.4 3.4L4.8 4.8M11.2 11.2L12.6 12.6M3.4 12.6L4.8 11.2M11.2 4.8L12.6 3.4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>
                    <span class="nav-label">Настройки</span>
                </span>
            </div>

            <?php elseif ($roleCode === 'company_owner'): ?>

            <div class="nav-group nav-group-spaced">
                <div class="nav-section-label">ОПЕРАЦИИ</div>
                <span class="nav-item is-disabled">
                    <svg class="nav-icon" viewBox="0 0 16 16" fill="none"><rect x="1" y="8.5" width="9.5" height="5" rx="1" stroke="currentColor" stroke-width="1.4"/><path d="M10.5 11H13C13.8 11 14.5 10.4 14.5 9.5C14.5 8.6 13.8 8.5 13 8.5H10.5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/><circle cx="3.5" cy="13.5" r="1.3" fill="currentColor"/><circle cx="8.5" cy="13.5" r="1.3" fill="currentColor"/><path d="M1 8.5V6.5C1 6 1.4 5.5 2 5.5H6.5L9 2.5H10.5C11 2.5 11.5 3 11.5 3.5V8.5" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/></svg>
                    <span class="nav-label">Рейсы</span>
                </span>
                <a class="nav-item<?= str_starts_with($_SERVER['REQUEST_URI'], '/company/drivers') ? ' is-active' : '' ?>" href="/company/drivers">
                    <svg class="nav-icon" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="5.5" r="2.8" stroke="currentColor" stroke-width="1.4"/><path d="M2 14C2 11.2 4.7 9 8 9C11.3 9 14 11.2 14 14" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>
                    <span class="nav-label">Водители</span>
                </a>
                <a class="nav-item<?= str_starts_with($_SERVER['REQUEST_URI'], '/company/vehicles') ? ' is-active' : '' ?>" href="/company/vehicles">
                    <svg class="nav-icon" viewBox="0 0 16 16" fill="none"><rect x="1" y="6.5" width="14" height="6" rx="1" stroke="currentColor" stroke-width="1.4"/><path d="M1 9H15" stroke="currentColor" stroke-width="1.4"/><path d="M5 6.5V5C5 4.4 5.4 4 6 4H10C10.6 4 11 4.4 11 5V6.5" stroke="currentColor" stroke-width="1.4"/><circle cx="4.5" cy="12.5" r="1.3" fill="currentColor"/><circle cx="11.5" cy="12.5" r="1.3" fill="currentColor"/></svg>
                    <span class="nav-label">Транспортные единицы</span>
                </a>
                <a class="nav-item<?= str_starts_with($_SERVER['REQUEST_URI'], '/company/vehicle-sets') ? ' is-active' : '' ?>" href="/company/vehicle-sets">
                    <svg class="nav-icon" viewBox="0 0 16 16" fill="none"><rect x="1.5" y="3" width="5.5" height="4" rx=".8" stroke="currentColor" stroke-width="1.4"/><rect x="9" y="3" width="5.5" height="4" rx=".8" stroke="currentColor" stroke-width="1.4"/><path d="M4 7V9.5H12V7" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/><path d="M4 11.5V13C4 13.5 4.5 14 5 14H11C11.5 14 12 13.5 12 13V11.5" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/><circle cx="6" cy="13" r=".8" fill="currentColor"/><circle cx="10" cy="13" r=".8" fill="currentColor"/></svg>
                    <span class="nav-label">Комплекты</span>
                </a>
                <a class="nav-item<?= str_starts_with($_SERVER['REQUEST_URI'], '/company/driver-vehicle-blocks') ? ' is-active' : '' ?>" href="/company/driver-vehicle-blocks">
                    <svg class="nav-icon" viewBox="0 0 16 16" fill="none"><circle cx="5.5" cy="4" r="2.2" stroke="currentColor" stroke-width="1.4"/><rect x="8.5" y="3.5" width="6" height="5" rx=".8" stroke="currentColor" stroke-width="1.4"/><path d="M1.5 11.5C1.5 9.3 3.3 7.5 5.5 7.5C7.7 7.5 9.5 9.3 9.5 11.5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/><path d="M7 13.5H10.5C11.8 13.5 13 12.3 13 11V7" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>
                    <span class="nav-label">Водитель+ТС</span>
                </a>
                <a class="nav-item<?= str_starts_with($_SERVER['REQUEST_URI'], '/company/clients') ? ' is-active' : '' ?>" href="/company/clients">
                    <svg class="nav-icon" viewBox="0 0 16 16" fill="none"><rect x="2" y="5.5" width="12" height="8.5" rx="1" stroke="currentColor" stroke-width="1.4"/><path d="M5 5.5V4C5 3.4 5.4 3 6 3H10C10.6 3 11 3.4 11 4V5.5" stroke="currentColor" stroke-width="1.4"/><path d="M2 9.5H14" stroke="currentColor" stroke-width="1.4"/><path d="M7.5 9.5V12" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>
                    <span class="nav-label">Клиенты</span>
                </a>
                <a class="nav-item<?= str_starts_with($_SERVER['REQUEST_URI'], '/company/contractors') ? ' is-active' : '' ?>" href="/company/contractors">
                    <svg class="nav-icon" viewBox="0 0 16 16" fill="none">
                        <rect x="1.5" y="5" width="13" height="9.5" rx="1" stroke="currentColor" stroke-width="1.4"/>
                        <path d="M5 5V3.5C5 2.9 5.4 2.5 6 2.5H10C10.6 2.5 11 2.9 11 3.5V5" stroke="currentColor" stroke-width="1.4"/>
                        <path d="M1.5 9H14.5" stroke="currentColor" stroke-width="1.4"/>
                        <rect x="6.5" y="6.5" width="3" height="3" rx=".5" stroke="currentColor" stroke-width="1.2"/>
                    </svg>
                    <span class="nav-label">Подрядчики</span>
                </a>
                <a class="nav-item<?= str_starts_with($_SERVER['REQUEST_URI'], '/company/crews') ? ' is-active' : '' ?>" href="/company/crews">
                    <svg class="nav-icon" viewBox="0 0 16 16" fill="none">
                        <circle cx="5.5" cy="4" r="2" stroke="currentColor" stroke-width="1.4"/>
                        <path d="M1.5 12C1.5 9.8 3.3 8 5.5 8C7.7 8 9.5 9.8 9.5 12" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/>
                        <circle cx="11" cy="6.5" r="1.6" stroke="currentColor" stroke-width="1.4"/>
                        <path d="M8 13.5C8 11.8 9.3 10.5 11 10.5C12.7 10.5 14 11.8 14 13.5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/>
                    </svg>
                    <span class="nav-label">Экипажи</span>
                </a>
            </div>

            <div class="nav-spacer"></div>

            <div class="nav-group">
                <div class="nav-section-label">СИСТЕМА</div>
                <a class="nav-item<?= str_starts_with($_SERVER['REQUEST_URI'], '/company/logists') ? ' is-active' : '' ?>" href="/company/logists">
                    <svg class="nav-icon" viewBox="0 0 16 16" fill="none">
                        <circle cx="6" cy="4.5" r="2.5" stroke="currentColor" stroke-width="1.4"/>
                        <path d="M1.5 13.5C1.5 10.5 4 8.5 6 8.5C8 8.5 10.5 10.5 10.5 13.5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/>
                        <path d="M12 3L14.5 4.5V7C14.5 9.5 12.8 11.3 12 12C11.2 11.3 9.5 9.5 9.5 7V4.5L12 3Z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/>
                        <path d="M10.8 7L11.5 8L13.2 5.8" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    <span class="nav-label">Пользователи</span>
                </a>
                <span class="nav-item is-disabled">
                    <svg class="nav-icon" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="2.5" stroke="currentColor" stroke-width="1.4"/><path d="M8 1.5V3.5M8 12.5V14.5M2.5 8H4.5M11.5 8H13.5M3.4 3.4L4.8 4.8M11.2 11.2L12.6 12.6M3.4 12.6L4.8 11.2M11.2 4.8L12.6 3.4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>
                    <span class="nav-label">Настройки</span>
                </span>
            </div>

            <?php elseif ($roleCode === 'logist'): ?>

            <div class="nav-group nav-group-spaced">
                <div class="nav-section-label">ОПЕРАЦИИ</div>
                <span class="nav-item is-disabled">
                    <svg class="nav-icon" viewBox="0 0 16 16" fill="none"><rect x="1" y="8.5" width="9.5" height="5" rx="1" stroke="currentColor" stroke-width="1.4"/><path d="M10.5 11H13C13.8 11 14.5 10.4 14.5 9.5C14.5 8.6 13.8 8.5 13 8.5H10.5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/><circle cx="3.5" cy="13.5" r="1.3" fill="currentColor"/><circle cx="8.5" cy="13.5" r="1.3" fill="currentColor"/><path d="M1 8.5V6.5C1 6 1.4 5.5 2 5.5H6.5L9 2.5H10.5C11 2.5 11.5 3 11.5 3.5V8.5" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/></svg>
                    <span class="nav-label">Рейсы</span>
                </span>
                <a class="nav-item<?= str_starts_with($_SERVER['REQUEST_URI'], '/company/drivers') ? ' is-active' : '' ?>" href="/company/drivers">
                    <svg class="nav-icon" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="5.5" r="2.8" stroke="currentColor" stroke-width="1.4"/><path d="M2 14C2 11.2 4.7 9 8 9C11.3 9 14 11.2 14 14" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>
                    <span class="nav-label">Водители</span>
                </a>
                <a class="nav-item<?= str_starts_with($_SERVER['REQUEST_URI'], '/company/vehicles') ? ' is-active' : '' ?>" href="/company/vehicles">
                    <svg class="nav-icon" viewBox="0 0 16 16" fill="none"><rect x="1" y="6.5" width="14" height="6" rx="1" stroke="currentColor" stroke-width="1.4"/><path d="M1 9H15" stroke="currentColor" stroke-width="1.4"/><path d="M5 6.5V5C5 4.4 5.4 4 6 4H10C10.6 4 11 4.4 11 5V6.5" stroke="currentColor" stroke-width="1.4"/><circle cx="4.5" cy="12.5" r="1.3" fill="currentColor"/><circle cx="11.5" cy="12.5" r="1.3" fill="currentColor"/></svg>
                    <span class="nav-label">Транспортные единицы</span>
                </a>
                <a class="nav-item<?= str_starts_with($_SERVER['REQUEST_URI'], '/company/vehicle-sets') ? ' is-active' : '' ?>" href="/company/vehicle-sets">
                    <svg class="nav-icon" viewBox="0 0 16 16" fill="none"><rect x="1.5" y="3" width="5.5" height="4" rx=".8" stroke="currentColor" stroke-width="1.4"/><rect x="9" y="3" width="5.5" height="4" rx=".8" stroke="currentColor" stroke-width="1.4"/><path d="M4 7V9.5H12V7" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/><path d="M4 11.5V13C4 13.5 4.5 14 5 14H11C11.5 14 12 13.5 12 13V11.5" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/><circle cx="6" cy="13" r=".8" fill="currentColor"/><circle cx="10" cy="13" r=".8" fill="currentColor"/></svg>
                    <span class="nav-label">Комплекты</span>
                </a>
                <a class="nav-item<?= str_starts_with($_SERVER['REQUEST_URI'], '/company/driver-vehicle-blocks') ? ' is-active' : '' ?>" href="/company/driver-vehicle-blocks">
                    <svg class="nav-icon" viewBox="0 0 16 16" fill="none"><circle cx="5.5" cy="4" r="2.2" stroke="currentColor" stroke-width="1.4"/><rect x="8.5" y="3.5" width="6" height="5" rx=".8" stroke="currentColor" stroke-width="1.4"/><path d="M1.5 11.5C1.5 9.3 3.3 7.5 5.5 7.5C7.7 7.5 9.5 9.3 9.5 11.5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/><path d="M7 13.5H10.5C11.8 13.5 13 12.3 13 11V7" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>
                    <span class="nav-label">Водитель+ТС</span>
                </a>
                <a class="nav-item<?= str_starts_with($_SERVER['REQUEST_URI'], '/company/clients') ? ' is-active' : '' ?>" href="/company/clients">
                    <svg class="nav-icon" viewBox="0 0 16 16" fill="none"><rect x="2" y="5.5" width="12" height="8.5" rx="1" stroke="currentColor" stroke-width="1.4"/><path d="M5 5.5V4C5 3.4 5.4 3 6 3H10C10.6 3 11 3.4 11 4V5.5" stroke="currentColor" stroke-width="1.4"/><path d="M2 9.5H14" stroke="currentColor" stroke-width="1.4"/><path d="M7.5 9.5V12" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>
                    <span class="nav-label">Клиенты</span>
                </a>
                <a class="nav-item<?= str_starts_with($_SERVER['REQUEST_URI'], '/company/contractors') ? ' is-active' : '' ?>" href="/company/contractors">
                    <svg class="nav-icon" viewBox="0 0 16 16" fill="none">
                        <rect x="1.5" y="5" width="13" height="9.5" rx="1" stroke="currentColor" stroke-width="1.4"/>
                        <path d="M5 5V3.5C5 2.9 5.4 2.5 6 2.5H10C10.6 2.5 11 2.9 11 3.5V5" stroke="currentColor" stroke-width="1.4"/>
                        <path d="M1.5 9H14.5" stroke="currentColor" stroke-width="1.4"/>
                        <rect x="6.5" y="6.5" width="3" height="3" rx=".5" stroke="currentColor" stroke-width="1.2"/>
                    </svg>
                    <span class="nav-label">Подрядчики</span>
                </a>
                <a class="nav-item<?= str_starts_with($_SERVER['REQUEST_URI'], '/company/crews') ? ' is-active' : '' ?>" href="/company/crews">
                    <svg class="nav-icon" viewBox="0 0 16 16" fill="none">
                        <circle cx="5.5" cy="4" r="2" stroke="currentColor" stroke-width="1.4"/>
                        <path d="M1.5 12C1.5 9.8 3.3 8 5.5 8C7.7 8 9.5 9.8 9.5 12" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/>
                        <circle cx="11" cy="6.5" r="1.6" stroke="currentColor" stroke-width="1.4"/>
                        <path d="M8 13.5C8 11.8 9.3 10.5 11 10.5C12.7 10.5 14 11.8 14 13.5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/>
                    </svg>
                    <span class="nav-label">Экипажи</span>
                </a>
            </div>

            <div class="nav-spacer"></div>

            <div class="nav-group">
                <div class="nav-section-label">СИСТЕМА</div>
                <span class="nav-item is-disabled">
                    <svg class="nav-icon" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="2.5" stroke="currentColor" stroke-width="1.4"/><path d="M8 1.5V3.5M8 12.5V14.5M2.5 8H4.5M11.5 8H13.5M3.4 3.4L4.8 4.8M11.2 11.2L12.6 12.6M3.4 12.6L4.8 11.2M11.2 4.8L12.6 3.4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>
                    <span class="nav-label">Настройки</span>
                </span>
            </div>

            <?php endif; ?>

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
