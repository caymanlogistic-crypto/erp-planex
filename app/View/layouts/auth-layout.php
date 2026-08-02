<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= e(csrfToken()) ?>">
    <title>Вход — ERP PLANEX</title>
    <link rel="preload" href="<?= app_url('/assets/fonts/IBMPlexSans-Regular.woff2') ?>" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="<?= app_url('/assets/fonts/IBMPlexSans-Medium.woff2') ?>" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="<?= app_url('/assets/fonts/IBMPlexSans-SemiBold.woff2') ?>" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="<?= app_url('/assets/fonts/IBMPlexSans-Bold.woff2') ?>" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="<?= app_url('/assets/css/app.css') ?>?v=<?= filemtime(base_path('public/assets/css/app.css')) ?>">
    <link rel="stylesheet" href="<?= app_url('/assets/css/erp-ui.css') ?>?v=<?= filemtime(base_path('public/assets/css/erp-ui.css')) ?>">
</head>
<body>
    <div class="auth-shell">
        <div class="auth-content">
            <?= $content ?>
        </div>
    </div>
</body>
</html>
