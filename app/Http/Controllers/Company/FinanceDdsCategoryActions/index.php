<?php

    requireRole(['company_owner']);
    $pageTitle = 'Статьи ДДС';
    $pageContext = 'Финансы › Статьи ДДС';

    $companyId = (int)(getSessionCompanyId() ?? 0);

    if ($companyId <= 0) {
        $company = null;
        $categories = [];
        $dbError = null;

        ob_start();
        require base_path('app/View/pages/company_finance_dds_categories.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
        return;
    }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$company) {
            $company = null;
            $categories = [];
            $dbError = null;

            ob_start();
            require base_path('app/View/pages/company_finance_dds_categories.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $pageContext = 'Финансы › Статьи ДДС › Компания: ' . $company['name'];

        if ($company['status'] !== 'active') {
            $categories = [];
            $dbError = null;

            ob_start();
            require base_path('app/View/pages/company_finance_dds_categories.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $localDbConfig = companyDatabaseConfig($config, $company);
        $localDb = new \App\Core\Database($localDbConfig);
        $localPdo = $localDb->connection();
        applyLocalMigrations($localPdo);

        $categories = \App\Service\FinanceDdsCategoryService::fetchCategories($localPdo);

        $successFlash = $_SESSION['finance_dds_success'] ?? null;
        unset($_SESSION['finance_dds_success']);
        $errorFlash = $_SESSION['finance_dds_error'] ?? null;
        unset($_SESSION['finance_dds_error']);

        $dbError = null;
    } catch (\Throwable $e) {
        $company = $company ?? null;
        $categories = [];
        $dbError = 'Ошибка при загрузке данных: ' . $e->getMessage();
        $successFlash = null;
        $errorFlash = null;
    }

    ob_start();
    require base_path('app/View/pages/company_finance_dds_categories.php');
    $content = ob_get_clean();
    require base_path('app/View/layouts/main.php');
