<?php

    requireRole(['company_owner']);
    $pageTitle = 'Финансовые операции';
    $pageContext = 'Финансы › Операции';

    $companyId = (int)(getSessionCompanyId() ?? 0);

    if ($companyId <= 0) {
        $company = null;
        $operations = [];
        $postedIncomeTotal = '0.00';
        $postedExpenseTotal = '0.00';
        $dbError = null;

        ob_start();
        require base_path('app/View/pages/company_finance_operations.php');
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
            $operations = [];
            $postedIncomeTotal = '0.00';
            $postedExpenseTotal = '0.00';
            $dbError = null;

            ob_start();
            require base_path('app/View/pages/company_finance_operations.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $pageContext = 'Финансы › Операции › Компания: ' . e($company['name']);

        if ($company['status'] !== 'active') {
            $operations = [];
            $postedIncomeTotal = '0.00';
            $postedExpenseTotal = '0.00';
            $dbError = null;

            ob_start();
            require base_path('app/View/pages/company_finance_operations.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $localDbConfig = companyDatabaseConfig($config, $company);
        $localDb = new \App\Core\Database($localDbConfig);
        $localPdo = $localDb->connection();
        applyLocalMigrations($localPdo);

        \App\Service\FinanceOperationService::ensureMoneyAccountsForBankAccounts($localPdo);

        $filters = [];
        $dateFrom = $_GET['date_from'] ?? '';
        $dateTo = $_GET['date_to'] ?? '';
        $typeFilter = $_GET['type'] ?? '';
        $statusFilter = $_GET['status'] ?? '';
        $sourceFilter = $_GET['source'] ?? '';
        $searchQuery = $_GET['search'] ?? '';

        if ($dateFrom !== '') {
            $filters['date_from'] = $dateFrom;
        }
        if ($dateTo !== '') {
            $filters['date_to'] = $dateTo;
        }
        if ($typeFilter !== '') {
            $filters['operation_type'] = $typeFilter;
        }
        if ($statusFilter !== '') {
            $filters['status'] = $statusFilter;
        }
        if ($sourceFilter !== '') {
            $filters['source'] = $sourceFilter;
        }
        if ($searchQuery !== '') {
            $filters['search'] = $searchQuery;
        }
        $filters['page'] = max(1, (int) ($_GET['page'] ?? 1));
        $filters['per_page'] = max(1, min(500, (int) ($_GET['per_page'] ?? 100)));

        $opResult = \App\Service\FinanceOperationService::fetchOperations($localPdo, $filters);
        $operations = $opResult['data'];
        $opTotal = $opResult['total'];
        $opPages = $opResult['pages'];
        $currentPage = $opResult['page'];
        $currentPerPage = $opResult['per_page'];

        $summary = \App\Service\FinanceOperationService::getSummaryTotals($localPdo, $filters);
        $postedIncomeTotal = $summary['total_income'];
        $postedExpenseTotal = $summary['total_expense'];

        $dbError = null;
    } catch (\Exception $e) {
        $company = $company ?? null;
        $operations = [];
        $postedIncomeTotal = '0.00';
        $postedExpenseTotal = '0.00';
        $dbError = 'Не удалось подключиться к базе данных компании.';
    }

    ob_start();
    require base_path('app/View/pages/company_finance_operations.php');
    $content = ob_get_clean();
    require base_path('app/View/layouts/main.php');
