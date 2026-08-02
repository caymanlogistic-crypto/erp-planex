<?php

    requireRole(['company_owner']);
    $pageTitle = 'Касса';
    $pageContext = 'Финансы › Касса';

    $companyId = (int)(getSessionCompanyId() ?? 0);

    if ($companyId <= 0) {
        $company = null;
        $cashAccounts = [];
        $recentOperations = [];
        $dbError = null;

        ob_start();
        require base_path('app/View/pages/company_finance_cash.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
        return;
    }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company) {
            $company = null;
            $cashAccounts = [];
            $recentOperations = [];
            $dbError = null;

            ob_start();
            require base_path('app/View/pages/company_finance_cash.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $pageContext = 'Финансы › Касса › Компания: ' . e($company['name']);

        if ($company['status'] !== 'active') {
            $cashAccounts = [];
            $recentOperations = [];
            $dbError = null;

            ob_start();
            require base_path('app/View/pages/company_finance_cash.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $localDbConfig = companyDatabaseConfig($config, $company);
        $localDb = new \App\Core\Database($localDbConfig);
        $localPdo = $localDb->connection();
        applyLocalMigrations($localPdo);

        $cashAccounts = \App\Service\FinanceCashService::fetchMoneyAccounts($localPdo, 'CASH', true);
        $cashPage = max(1, (int) ($_GET['page'] ?? 1));
        $cashPerPage = max(1, min(500, (int) ($_GET['per_page'] ?? 20)));
        $cashResult = \App\Service\FinanceCashService::fetchRecentOperations($localPdo, $cashPage, $cashPerPage);
        $recentOperations = $cashResult['data'];
        $cashTotal = $cashResult['total'];
        $cashPages = $cashResult['pages'];

        $successFlash = $_SESSION['finance_success'] ?? null;
        unset($_SESSION['finance_success']);
        $errorFlash = $_SESSION['finance_error'] ?? null;
        unset($_SESSION['finance_error']);

        $dbError = null;
    } catch (\Throwable $e) {
        $company = $company ?? null;
        $cashAccounts = [];
        $recentOperations = [];
        $dbError = 'Ошибка при загрузке данных: ' . $e->getMessage();
        $successFlash = null;
        $errorFlash = null;
    }

    ob_start();
    require base_path('app/View/pages/company_finance_cash.php');
    $content = ob_get_clean();
    require base_path('app/View/layouts/main.php');
