<?php

    requireRole('superadmin');

    try {
        $pdo = $db->connection();

        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([(int) $id]);
        $company = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$company) {
            echo '<div class="modal-body"><div class="notice warn">Компания не найдена.</div></div>';
            return;
        }

        $ownerStmt = $pdo->prepare(
            "SELECT * FROM company_users WHERE company_id = ? AND role = 'company_owner'"
        );
        $ownerStmt->execute([(int) $id]);
        $owner = $ownerStmt->fetch(\PDO::FETCH_ASSOC) ?: null;

        if (!$owner) {
            echo '<div class="modal-body"><div class="notice warn">Руководитель не создан.</div></div>';
            return;
        }

        $errors = [];
        $old = $_POST;
        $formError = null;
        $user = $owner;
        $isOwner = true;

        if ((int)$owner['id'] === (int)$_SESSION['user_id']) {
            $newStatus = $_POST['status'] ?? $owner['status'];
            if ($newStatus !== 'active') {
                $errors['status'] = 'Нельзя деактивировать самого себя';
            }
        }

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
            require base_path('app/View/partials/superadmin_user_modal_edit.php');
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

        // Re-render view modal after successful save
        $user = $owner;
        $user['full_name'] = $fullName;
        $user['login'] = $login;
        $user['email'] = $email !== '' ? $email : null;
        $user['phone'] = trim($_POST['phone'] ?? '') ?: null;
        $user['position'] = trim($_POST['position'] ?? '') ?: null;
        $user['status'] = $_POST['status'] ?? $owner['status'];
        $user['comments'] = trim($_POST['comments'] ?? '') ?: null;
        $canEdit = true;

        require base_path('app/View/partials/superadmin_user_modal_view.php');
    } catch (\Exception $e) {
        $user = $owner ?? [];
        $isOwner = true;
        $errors = [];
        $old = $_POST;
        $formError = 'Ошибка сохранения: ' . $e->getMessage();
        require base_path('app/View/partials/superadmin_user_modal_edit.php');
    }
