<?php

    requireRole('superadmin');
    $pageTitle = 'Руководитель';
    $pageContext = 'Реестр компаний';

    try {
        $pdo = $db->connection();

        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([(int) $id]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company) {
            $company = null;
            $owner = null;
            $dbError = 'Компания не найдена';
            $passwordReset = false;
            $newPassword = null;

            ob_start();
            require base_path('app/View/pages/superadmin_company_owner_view.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $ownerStmt = $pdo->prepare(
            "SELECT * FROM company_users WHERE company_id = ? AND role = 'company_owner'"
        );
        $ownerStmt->execute([(int) $id]);
        $owner = $ownerStmt->fetch(PDO::FETCH_ASSOC) ?: null;

        if (!$owner) {
            $dbError = null;
            $passwordReset = false;
            $newPassword = null;

            ob_start();
            require base_path('app/View/pages/superadmin_company_owner_view.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $newPassword = generatePassword(10);
        $passwordHash = password_hash($newPassword, PASSWORD_BCRYPT);

        $update = $pdo->prepare('UPDATE company_users SET password_hash = ? WHERE id = ?');
        $update->execute([$passwordHash, (int) $owner['id']]);

        $passwordReset = true;
        $dbError = null;

        ob_start();
        require base_path('app/View/pages/superadmin_company_owner_view.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
    } catch (\Exception $e) {
        $company = $company ?? null;
        $owner = $owner ?? null;
        $dbError = 'Ошибка сброса пароля: ' . $e->getMessage();
        $passwordReset = false;
        $newPassword = null;

        ob_start();
        require base_path('app/View/pages/superadmin_company_owner_view.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
    }
