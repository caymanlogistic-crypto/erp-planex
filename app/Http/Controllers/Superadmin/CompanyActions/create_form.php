<?php

    requireRole('superadmin');
    $pageTitle = 'Создать экспедитора';
    $pageContext = 'Реестр компаний';
    $errors = [];
    $old = [];
    $formError = null;

    ob_start();
    require base_path('app/View/pages/superadmin_companies_create.php');
    $content = ob_get_clean();

    require base_path('app/View/layouts/main.php');
