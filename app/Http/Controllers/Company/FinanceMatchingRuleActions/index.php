<?php

    requireRole(['company_owner']);
    $pageTitle = 'Правила разнесения';
    $pageContext = 'Финансы › Правила разнесения';

    $companyId = (int)(getSessionCompanyId() ?? 0);

    if ($companyId <= 0) {
        $company = null;
        $rules = [];
        $bankAccounts = [];
        $ddsCategories = [];
        $dbError = null;

        ob_start();
        require base_path('app/View/pages/company_finance_matching_rules.php');
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
            $rules = [];
            $bankAccounts = [];
            $ddsCategories = [];
            $dbError = null;

            ob_start();
            require base_path('app/View/pages/company_finance_matching_rules.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $pageContext = 'Финансы › Правила разнесения › Компания: ' . $company['name'];

        if ($company['status'] !== 'active') {
            $rules = [];
            $bankAccounts = [];
            $ddsCategories = [];
            $dbError = null;

            ob_start();
            require base_path('app/View/pages/company_finance_matching_rules.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $localDbConfig = companyDatabaseConfig($config, $company);
        $localDb = new \App\Core\Database($localDbConfig);
        $localPdo = $localDb->connection();
        applyLocalMigrations($localPdo);

        $rules = \App\Service\FinanceMatchingRuleService::fetchRules($localPdo);

        $baStmt = $localPdo->query('SELECT id, account_number, bank_name FROM bank_accounts ORDER BY bank_name ASC');
        $bankAccounts = $baStmt->fetchAll(\PDO::FETCH_ASSOC);

        $ddsCategories = \App\Service\FinanceDdsCategoryService::fetchCategories($localPdo);

        $successFlash = $_SESSION['finance_matching_rules_success'] ?? null;
        unset($_SESSION['finance_matching_rules_success']);
        $errorFlash = $_SESSION['finance_matching_rules_error'] ?? null;
        unset($_SESSION['finance_matching_rules_error']);

        $dbError = null;
    } catch (\Throwable $e) {
        $company = $company ?? null;
        $rules = [];
        $bankAccounts = [];
        $ddsCategories = [];
        $dbError = 'Ошибка при загрузке данных: ' . $e->getMessage();
        $successFlash = null;
        $errorFlash = null;
    }

    ob_start();
    require base_path('app/View/pages/company_finance_matching_rules.php');
    $content = ob_get_clean();
    require base_path('app/View/layouts/main.php');
