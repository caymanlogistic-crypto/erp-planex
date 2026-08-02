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
        $ddsCategories = \App\Service\FinanceDdsCategoryService::fetchActiveForDirection($localPdo, 'INCOME');
    } catch (\Throwable $e) {
        echo '<div class="form-alert alert-error">Ошибка загрузки данных.</div>';
        return;
    }

    require base_path('app/View/partials/company_cash_operation_form.php');
