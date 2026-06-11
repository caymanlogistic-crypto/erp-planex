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
        <aside class="app-sidebar" aria-label="Основная навигация">
            <div class="brand">
                <span class="brand-mark">PX</span>
                <span>
                    <strong><?= e($appName) ?></strong>
                    <small>technical UI</small>
                </span>
            </div>

            <nav class="nav-list">
                <a class="nav-item is-active" href="/">
                    <span class="nav-dot"></span>
                    UI foundation
                </a>
                <span class="nav-section">Будущие модули</span>
                <a class="nav-item<?= str_starts_with($_SERVER['REQUEST_URI'], '/superadmin') ? ' is-active' : '' ?>" href="/superadmin">
                    <span class="nav-dot"></span>
                    SUPERADMIN
                </a>
                <span class="nav-item is-disabled">Клиенты</span>
                <span class="nav-item is-disabled">Рейсы</span>
            </nav>
        </aside>

        <div class="app-main">
            <header class="topbar">
                <div>
                    <span class="topbar-label">ERP PLANEX</span>
                    <strong>Единый дизайн-фундамент</strong>
                </div>
                <span class="environment-badge">Без БД и бизнес-логики</span>
            </header>

            <main class="content">
                <?= $content ?>
            </main>
        </div>
    </div>

    <script src="/assets/js/app.js"></script>
</body>
</html>
