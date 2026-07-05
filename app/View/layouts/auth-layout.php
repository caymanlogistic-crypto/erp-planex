<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Вход — ERP PLANEX</title>
    <link rel="preload" href="<?= app_url('/assets/fonts/IBMPlexSans-Regular.woff2') ?>" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="<?= app_url('/assets/fonts/IBMPlexSans-Medium.woff2') ?>" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="<?= app_url('/assets/fonts/IBMPlexSans-SemiBold.woff2') ?>" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="<?= app_url('/assets/fonts/IBMPlexSans-Bold.woff2') ?>" as="font" type="font/woff2" crossorigin>
    <style>
        @font-face{font-family:"IBM Plex Sans";src:url("<?= app_url('/assets/fonts/IBMPlexSans-Regular.woff2') ?>") format("woff2");font-weight:400;font-style:normal;font-display:swap}
        @font-face{font-family:"IBM Plex Sans";src:url("<?= app_url('/assets/fonts/IBMPlexSans-Medium.woff2') ?>") format("woff2");font-weight:500;font-style:normal;font-display:swap}
        @font-face{font-family:"IBM Plex Sans";src:url("<?= app_url('/assets/fonts/IBMPlexSans-SemiBold.woff2') ?>") format("woff2");font-weight:600;font-style:normal;font-display:swap}
        @font-face{font-family:"IBM Plex Sans";src:url("<?= app_url('/assets/fonts/IBMPlexSans-Bold.woff2') ?>") format("woff2");font-weight:700;font-style:normal;font-display:swap}
    </style>
    <link rel="stylesheet" href="<?= app_url('/assets/css/app.css') ?>">
</head>
<body>
    <div class="auth-shell">
        <div class="auth-content">
            <?= $content ?>
        </div>
    </div>
</body>
</html>
