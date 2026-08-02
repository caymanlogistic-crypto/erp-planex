<?php

    requireRole(['company_owner']);
    verifyCsrfRequest();

    $companyId = (int)(getSessionCompanyId() ?? 0);
    if ($companyId <= 0) {
        $_SESSION['finance_matching_rules_error'] = 'Компания не найдена.';
        redirect_to('/company/finance/settings/matching-rules');
        return;
    }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ? AND status = \'active\'');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$company) {
            $_SESSION['finance_matching_rules_error'] = 'Компания не найдена или неактивна.';
            redirect_to('/company/finance/settings/matching-rules');
            return;
        }

        $localDbConfig = companyDatabaseConfig($config, $company);
        $localDb = new \App\Core\Database($localDbConfig);
        $localPdo = $localDb->connection();
        applyLocalMigrations($localPdo);

        $user = [
            'id' => $_SESSION['user_id'] ?? null,
            'role' => $_SESSION['role_code'] ?? null,
        ];

        \App\Service\FinanceMatchingRuleService::createRule($localPdo, $_POST, $user);

        $_SESSION['finance_matching_rules_success'] = 'Правило разнесения успешно создано.';
    } catch (\InvalidArgumentException $e) {
        $_SESSION['finance_matching_rules_error'] = $e->getMessage();
    } catch (\Throwable $e) {
        $_SESSION['finance_matching_rules_error'] = 'Ошибка при создании правила: ' . $e->getMessage();
    }

    redirect_to('/company/finance/settings/matching-rules');
