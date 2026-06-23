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

// Вычисление — раскрыта ли группа «Подрядчики» (любой подпункт активен)
$contractorsOpen = ($roleCode !== 'superadmin') && (
    str_starts_with($_SERVER['REQUEST_URI'], '/company/contractors')
    || str_starts_with($_SERVER['REQUEST_URI'], '/company/driver-vehicle-blocks')
    || str_starts_with($_SERVER['REQUEST_URI'], '/company/drivers')
    || str_starts_with($_SERVER['REQUEST_URI'], '/company/vehicle-sets')
    || str_starts_with($_SERVER['REQUEST_URI'], '/company/crews')
);

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

$crumbTitle = (string)($pageTitle ?? 'ERP PLANEX');
$crumbContext = trim((string)($pageContext ?? ''));
if ($crumbContext !== '') {
    $contextLead = trim(explode('›', $crumbContext, 2)[0]);
    if ($contextLead === $crumbTitle) {
        $crumbTitle = '';
    }
}
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle ?? $appName) ?> — <?= e($appName) ?></title>
    <link rel="preload" href="/assets/fonts/IBMPlexSans-Regular.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="/assets/fonts/IBMPlexSans-Medium.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="/assets/fonts/IBMPlexSans-SemiBold.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="/assets/fonts/IBMPlexSans-Bold.woff2" as="font" type="font/woff2" crossorigin>
    <style>
        @font-face{font-family:"IBM Plex Sans";src:url("/assets/fonts/IBMPlexSans-Regular.woff2") format("woff2");font-weight:400;font-style:normal;font-display:optional}
        @font-face{font-family:"IBM Plex Sans";src:url("/assets/fonts/IBMPlexSans-Medium.woff2") format("woff2");font-weight:500;font-style:normal;font-display:optional}
        @font-face{font-family:"IBM Plex Sans";src:url("/assets/fonts/IBMPlexSans-SemiBold.woff2") format("woff2");font-weight:600;font-style:normal;font-display:optional}
        @font-face{font-family:"IBM Plex Sans";src:url("/assets/fonts/IBMPlexSans-Bold.woff2") format("woff2");font-weight:700;font-style:normal;font-display:optional}
    </style>
    <link rel="stylesheet" href="/assets/css/erp-ui.css">
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
    <div class="app">
        <header class="topbar">
            <div class="topbar-brand">
                <div class="logo-mark"></div>
                <span class="brand-name">ERP PLANEX</span>
                <span class="brand-tag">ERP</span>
            </div>
            <div class="topbar-crumbs">
                <?php if (!empty($topbarCrumbs)): ?>
                <?php foreach ($topbarCrumbs as $i => $crumb): ?>
                <?php if ($i > 0): ?><span class="sep">›</span><?php endif; ?>
                <?php if ($i === count($topbarCrumbs) - 1): ?>
                <b><?= e($crumb['label']) ?></b>
                <?php elseif (!empty($crumb['url'])): ?>
                <a href="<?= e($crumb['url']) ?>"><?= e($crumb['label']) ?></a>
                <?php else: ?>
                <span><?= e($crumb['label']) ?></span>
                <?php endif; ?>
                <?php endforeach; ?>
                <?php else: ?>
                <?php if ($crumbTitle !== ''): ?>
                <span><?= e($crumbTitle) ?></span>
                <?php endif; ?>
                <?php if ($crumbContext !== ''): ?>
                <?php if ($crumbTitle !== ''): ?>
                <span class="sep">›</span>
                <?php endif; ?>
                <b><?= e($crumbContext) ?></b>
                <?php endif; ?>
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

        <aside class="sidebar" aria-label="Основная навигация">

            <?php if ($roleCode === 'superadmin'): ?>

            <div class="nav-group">
                <div class="nav-section-label">СИСТЕМА</div>
                <a class="nav-item<?= str_starts_with($_SERVER['REQUEST_URI'], '/superadmin') ? ' is-active' : '' ?>" href="/superadmin/companies">
                    <svg class="nav-icon" viewBox="0 0 16 16" fill="none"><rect x="2" y="4" width="12" height="11" rx="1" stroke="currentColor" stroke-width="1.4"/><path d="M5 15V11H11V15" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/><path d="M2 7H14" stroke="currentColor" stroke-width="1.4"/><rect x="5" y="5" width="2" height="2" rx=".3" fill="currentColor"/><rect x="9" y="5" width="2" height="2" rx=".3" fill="currentColor"/><rect x="5" y="9" width="2" height="2" rx=".3" fill="currentColor"/><rect x="9" y="9" width="2" height="2" rx=".3" fill="currentColor"/></svg>
                    <span class="nav-label">Компании</span>
                </a>
            </div>

            <?php elseif ($roleCode === 'company_owner'): ?>

            <div class="nav-group">
                <div class="nav-section-label">ОПЕРАЦИИ</div>
                <a class="nav-item<?= str_starts_with($_SERVER['REQUEST_URI'], '/company/clients') ? ' is-active' : '' ?>" href="/company/clients">
                    <svg class="nav-icon" viewBox="0 0 16 16" fill="none"><rect x="2" y="5.5" width="12" height="8.5" rx="1" stroke="currentColor" stroke-width="1.4"/><path d="M5 5.5V4C5 3.4 5.4 3 6 3H10C10.6 3 11 3.4 11 4V5.5" stroke="currentColor" stroke-width="1.4"/><path d="M2 9.5H14" stroke="currentColor" stroke-width="1.4"/><path d="M7.5 9.5V12" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>
                    <span class="nav-label">Клиенты</span>
                </a>
                <div class="nav-item is-parent<?= $contractorsOpen ? ' is-open' : '' ?>">
                    <svg class="nav-icon" viewBox="0 0 16 16" fill="none">
                        <rect x="1.5" y="5" width="13" height="9.5" rx="1" stroke="currentColor" stroke-width="1.4"/>
                        <path d="M5 5V3.5C5 2.9 5.4 2.5 6 2.5H10C10.6 2.5 11 2.9 11 3.5V5" stroke="currentColor" stroke-width="1.4"/>
                        <path d="M1.5 9H14.5" stroke="currentColor" stroke-width="1.4"/>
                        <rect x="6.5" y="6.5" width="3" height="3" rx=".5" stroke="currentColor" stroke-width="1.2"/>
                    </svg>
                    <span class="nav-label">Подрядчики</span>
                    <svg class="nav-chevron" viewBox="0 0 14 14" fill="none"><path d="M5 3L9 7L5 11" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </div>
                <div class="nav-sub<?= $contractorsOpen ? ' is-open' : '' ?>">
                    <a class="nav-sub-item<?= str_starts_with($_SERVER['REQUEST_URI'], '/company/contractors') ? ' is-active' : '' ?>" href="/company/contractors">
                        <span>Перевозчики</span>
                    </a>
                    <a class="nav-sub-item<?= str_starts_with($_SERVER['REQUEST_URI'], '/company/driver-vehicle-blocks') ? ' is-active' : '' ?>" href="/company/driver-vehicle-blocks">
                        <span>Водители+ТС</span>
                    </a>
                    <a class="nav-sub-item<?= str_starts_with($_SERVER['REQUEST_URI'], '/company/drivers') ? ' is-active' : '' ?>" href="/company/drivers">
                        <span>Водители</span>
                    </a>
                    <a class="nav-sub-item<?= str_starts_with($_SERVER['REQUEST_URI'], '/company/vehicle-sets') ? ' is-active' : '' ?>" href="/company/vehicle-sets">
                        <span>Транспорт</span>
                    </a>
                    <a class="nav-sub-item<?= str_starts_with($_SERVER['REQUEST_URI'], '/company/crews') ? ' is-active' : '' ?>" href="/company/crews">
                        <span>Экипажи</span>
                    </a>
                </div>
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
                    <span class="nav-label">Логисты</span>
                </a>
            </div>

            <?php elseif ($roleCode === 'logist'): ?>

            <div class="nav-group">
                <div class="nav-section-label">ОПЕРАЦИИ</div>
                <a class="nav-item<?= str_starts_with($_SERVER['REQUEST_URI'], '/company/clients') ? ' is-active' : '' ?>" href="/company/clients">
                    <svg class="nav-icon" viewBox="0 0 16 16" fill="none"><rect x="2" y="5.5" width="12" height="8.5" rx="1" stroke="currentColor" stroke-width="1.4"/><path d="M5 5.5V4C5 3.4 5.4 3 6 3H10C10.6 3 11 3.4 11 4V5.5" stroke="currentColor" stroke-width="1.4"/><path d="M2 9.5H14" stroke="currentColor" stroke-width="1.4"/><path d="M7.5 9.5V12" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>
                    <span class="nav-label">Клиенты</span>
                </a>
                <div class="nav-item is-parent<?= $contractorsOpen ? ' is-open' : '' ?>">
                    <svg class="nav-icon" viewBox="0 0 16 16" fill="none">
                        <rect x="1.5" y="5" width="13" height="9.5" rx="1" stroke="currentColor" stroke-width="1.4"/>
                        <path d="M5 5V3.5C5 2.9 5.4 2.5 6 2.5H10C10.6 2.5 11 2.9 11 3.5V5" stroke="currentColor" stroke-width="1.4"/>
                        <path d="M1.5 9H14.5" stroke="currentColor" stroke-width="1.4"/>
                        <rect x="6.5" y="6.5" width="3" height="3" rx=".5" stroke="currentColor" stroke-width="1.2"/>
                    </svg>
                    <span class="nav-label">Подрядчики</span>
                    <svg class="nav-chevron" viewBox="0 0 14 14" fill="none"><path d="M5 3L9 7L5 11" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </div>
                <div class="nav-sub<?= $contractorsOpen ? ' is-open' : '' ?>">
                    <a class="nav-sub-item<?= str_starts_with($_SERVER['REQUEST_URI'], '/company/contractors') ? ' is-active' : '' ?>" href="/company/contractors">
                        <span>Перевозчики</span>
                    </a>
                    <a class="nav-sub-item<?= str_starts_with($_SERVER['REQUEST_URI'], '/company/driver-vehicle-blocks') ? ' is-active' : '' ?>" href="/company/driver-vehicle-blocks">
                        <span>Водители+ТС</span>
                    </a>
                    <a class="nav-sub-item<?= str_starts_with($_SERVER['REQUEST_URI'], '/company/drivers') ? ' is-active' : '' ?>" href="/company/drivers">
                        <span>Водители</span>
                    </a>
                    <a class="nav-sub-item<?= str_starts_with($_SERVER['REQUEST_URI'], '/company/vehicle-sets') ? ' is-active' : '' ?>" href="/company/vehicle-sets">
                        <span>Транспорт</span>
                    </a>
                    <a class="nav-sub-item<?= str_starts_with($_SERVER['REQUEST_URI'], '/company/crews') ? ' is-active' : '' ?>" href="/company/crews">
                        <span>Экипажи</span>
                    </a>
                </div>
            </div>

            <?php endif; ?>

        </aside>

        <main class="content">
            <?= $content ?>
        </main>
    </div>

    <script src="/assets/js/app.js"></script>
</body>
</html>
