<?php

    requireRole(['company_owner']);
    $pageTitle = 'Банковские счета';
    $pageContext = 'Финансы › Банковские счета';

    $companyId = (int)(getSessionCompanyId() ?? 0);

    if ($companyId <= 0) {
        $company = null;
        $accounts = [];
        $imports = [];
        $transactions = [];
        $dailyBalances = [];
        $bankSettings = [];
        $dbError = null;

        ob_start();
        require base_path('app/View/pages/company_bank_accounts.php');
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
            $accounts = [];
            $imports = [];
            $transactions = [];
            $dailyBalances = [];
            $bankSettings = [];
            $dbError = null;

            ob_start();
            require base_path('app/View/pages/company_bank_accounts.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $pageContext = 'Финансы › Банковские счета › Компания: ' . e($company['name']);

        if ($company['status'] !== 'active') {
            $accounts = [];
            $imports = [];
            $transactions = [];
            $dailyBalances = [];
            $bankSettings = [];
            $dbError = null;

            ob_start();
            require base_path('app/View/pages/company_bank_accounts.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $localDbConfig = companyDatabaseConfig($config, $company);
        $localDb = new \App\Core\Database($localDbConfig);
        $localPdo = $localDb->connection();
        applyLocalMigrations($localPdo);

        $accounts = \App\Service\BankFinanceService::getAccounts($localPdo);
        $imports = \App\Service\BankFinanceService::getImports($localPdo);

        $dateFrom = $_GET['date_from'] ?? null;
        $dateTo = $_GET['date_to'] ?? null;
        $txPage = max(1, (int) ($_GET['tx_page'] ?? 1));
        $txPerPage = max(1, min(500, (int) ($_GET['tx_per_page'] ?? 100)));
        $txResult = \App\Service\BankFinanceService::getTransactions($localPdo, null, $txPage, $txPerPage, $dateFrom, $dateTo);
        $transactions = $txResult['data'];
        $txTotal = $txResult['total'];
        $txPages = $txResult['pages'];
        $dbResult = \App\Service\BankFinanceService::getDailyBalances($localPdo, null, 1, 100, $dateFrom, $dateTo);
        $dailyBalances = $dbResult['data'];

        $reconciliation = \App\Service\FinanceBankReconciliationService::reconcileAll($localPdo);

        $dbError = null;
    } catch (\Exception $e) {
        $company = $company ?? null;
        $accounts = [];
        $imports = [];
        $transactions = [];
        $dailyBalances = [];
        $bankSettings = [];
        $dbError = 'Не удалось подключиться к базе данных компании.';
    }

    ob_start();
    require base_path('app/View/pages/company_bank_accounts.php');
    $content = ob_get_clean();
    require base_path('app/View/layouts/main.php');
