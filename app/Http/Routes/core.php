<?php

$router->get('/favicon.ico', function () {
    http_response_code(204);
    exit;
});

$router->get('/', function () use ($config) {
    if (!isAuthenticated()) {
        header('Location: /login');
        exit;
    }

    $role = $_SESSION['role_code'] ?? '';
    if ($role === 'superadmin') {
        header('Location: /superadmin/companies');
        exit;
    }

    header('Location: /company/dashboard');
    exit;
});

$router->get('/dev/ui-foundation', function () use ($config) {
    $pageTitle = 'UI foundation';

    ob_start();
    require base_path('app/View/pages/ui_demo.php');
    $content = ob_get_clean();

    require base_path('app/View/layouts/main.php');
});

$router->get('/test', function () {
    header('Content-Type: text/plain');
    echo 'ERP PLANEX core is running';
});

$router->get('/test-db', function () use ($db) {
    header('Content-Type: text/plain');
    try {
        $db->connection();
        echo 'DB connection OK';
    } catch (\Exception $e) {
        echo 'DB connection FAILED';
    }
});

// ============================================================
// SUPERADMIN: Companies list
// ============================================================
