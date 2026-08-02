<?php

    requireRole(['company_owner']);
    $pageTitle = 'Создание правила разнесения';

    $companyId = (int)(getSessionCompanyId() ?? 0);
    if ($companyId <= 0) {
        echo '<div class="form-alert alert-error">Компания не найдена.</div>';
        return;
    }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ? AND status = \'active\'');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$company) {
            echo '<div class="form-alert alert-error">Компания не найдена или неактивна.</div>';
            return;
        }

        $localDbConfig = companyDatabaseConfig($config, $company);
        $localDb = new \App\Core\Database($localDbConfig);
        $localPdo = $localDb->connection();
        applyLocalMigrations($localPdo);

        $baStmt = $localPdo->query('SELECT id, account_number, bank_name FROM bank_accounts ORDER BY bank_name ASC');
        $bankAccounts = $baStmt->fetchAll(\PDO::FETCH_ASSOC);

        $ddsCategories = \App\Service\FinanceDdsCategoryService::fetchCategories($localPdo);

        $cpStmt = $localPdo->query('SELECT id, name FROM contractors ORDER BY name ASC');
        $contractors = $cpStmt->fetchAll(\PDO::FETCH_ASSOC);
    } catch (\Throwable $e) {
        echo '<div class="form-alert alert-error">Ошибка загрузки данных.</div>';
        return;
    }

    require base_path('app/View/partials/company_finance_matching_rule_form.php');
