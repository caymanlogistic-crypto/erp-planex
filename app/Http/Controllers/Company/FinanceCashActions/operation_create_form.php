<?php

    requireRole(['company_owner']);
    $pageTitle = 'Кассовая операция';

    $companyId = (int)(getSessionCompanyId() ?? 0);
    if ($companyId <= 0) {
        echo '<div class="form-alert alert-error">Компания не найдена.</div>';
        return;
    }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ? AND status = \'active\'');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company) {
            echo '<div class="form-alert alert-error">Компания не найдена или неактивна.</div>';
            return;
        }

        $localDbConfig = companyDatabaseConfig($config, $company);
        $localDb = new \App\Core\Database($localDbConfig);
        $localPdo = $localDb->connection();
        applyLocalMigrations($localPdo);

        $cashAccounts = \App\Service\FinanceCashService::fetchMoneyAccounts($localPdo, 'CASH', true);
        $incomeDdsCategories = \App\Service\FinanceDdsCategoryService::fetchActiveForDirection($localPdo, 'INCOME');
        $expenseDdsCategories = \App\Service\FinanceDdsCategoryService::fetchActiveForDirection($localPdo, 'EXPENSE');
        $ddsCategories = $incomeDdsCategories;
        $clientPaymentTargets = \App\Service\FinanceManualFactService::fetchClientPaymentTargets($localPdo);
        $employees = \App\Service\FinanceEmployeePaymentService::fetchActiveEmployees($localPdo, $pdo, $companyId);
        $cashFlowCenters = \App\Service\FinanceMatchingRuleService::fetchCashFlowCenters($localPdo, true);
        $allowedExpenseDdsMap = \App\Service\FinanceStructureService::fetchAllowedMap($localPdo, 'EXPENSE');
        $routes = \App\Service\FinanceAllocationService::fetchRoutesForAllocation($localPdo, [
            'role_code' => (string)($_SESSION['role_code'] ?? ''),
            'user_id' => (int)($_SESSION['user_id'] ?? 0),
        ]);
    } catch (\Throwable $e) {
        echo '<div class="form-alert alert-error">Ошибка загрузки данных: ' . e($e->getMessage()) . '</div>';
        return;
    }

    require base_path('app/View/partials/company_cash_operation_form.php');
