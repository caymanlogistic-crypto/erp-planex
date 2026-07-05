<?php

    requireRole('superadmin');
    $pageTitle = 'Редактировать Руководителя';
    $pageContext = 'Реестр компаний';

    try {
        $pdo = $db->connection();

        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([(int) $id]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company) {
            $company = null;
            $owner = null;
            $errors = [];
            $old = $_POST;
            $formError = 'Компания не найдена';

            ob_start();
            require base_path('app/View/pages/superadmin_company_owner_edit.php');
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
            $errors = [];
            $old = $_POST;
            $formError = 'Руководитель не создан';

            ob_start();
            require base_path('app/View/pages/superadmin_company_owner_edit.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $errors = [];
        $old = $_POST;
        $formError = null;

        // Prevent self-deactivation
        if ((int)$owner['id'] === (int)$_SESSION['user_id']) {
            $newStatus = $_POST['status'] ?? $owner['status'];
            if ($newStatus !== 'active') {
                $errors['status'] = 'Нельзя деактивировать самого себя';
            }
        }

        // Prevent deactivating last active owner
        $newStatus = $_POST['status'] ?? $owner['status'];
        if ($newStatus !== 'active' && $owner['status'] === 'active') {
            $activeOwnerCount = $pdo->prepare(
                "SELECT COUNT(*) FROM company_users WHERE company_id = ? AND role = 'company_owner' AND status = 'active' AND id != ?"
            );
            $activeOwnerCount->execute([(int)$id, (int)$owner['id']]);
            if ((int)$activeOwnerCount->fetchColumn() === 0) {
                $errors['status'] = 'Нельзя деактивировать последнего активного Руководителя';
            }
        }

        $fullName = trim($_POST['full_name'] ?? '');
        $login = trim($_POST['login'] ?? '');

        if ($fullName === '') {
            $errors['full_name'] = 'Обязательное поле';
        }

        if ($login === '') {
            $errors['login'] = 'Обязательное поле';
        } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $login)) {
            $errors['login'] = 'Только латинские буквы, цифры и подчёркивание';
        } else {
            $dupStmt = $pdo->prepare('SELECT COUNT(*) FROM company_users WHERE login = ? AND id != ?');
            $dupStmt->execute([$login, (int) $owner['id']]);
            if ($dupStmt->fetchColumn() > 0) {
                $errors['login'] = 'Логин уже используется';
            }
        }

        $email = trim($_POST['email'] ?? '');
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Некорректный email';
        }

        if (!empty($errors)) {
            ob_start();
            require base_path('app/View/pages/superadmin_company_owner_edit.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $update = $pdo->prepare(
            'UPDATE company_users SET
                full_name = :full_name,
                login = :login,
                email = :email,
                phone = :phone,
                position = :position,
                status = :status,
                comments = :comments
             WHERE id = :id'
        );

        $update->execute([
            ':full_name' => $fullName,
            ':login'     => $login,
            ':email'     => $email !== '' ? $email : null,
            ':phone'     => trim($_POST['phone'] ?? '') ?: null,
            ':position'  => trim($_POST['position'] ?? '') ?: null,
            ':status'    => $_POST['status'] ?? $owner['status'],
            ':comments'  => trim($_POST['comments'] ?? '') ?: null,
            ':id'        => (int) $owner['id'],
        ]);

        redirect_to('/superadmin/companies/' . $id . '/owner?success=1');
    } catch (\Exception $e) {
        $company = $company ?? null;
        $owner = $owner ?? null;
        $errors = [];
        $old = $_POST;
        $formError = 'Ошибка сохранения: ' . $e->getMessage();

        ob_start();
        require base_path('app/View/pages/superadmin_company_owner_edit.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
    }
