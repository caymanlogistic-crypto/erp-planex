<?php

    requireRole('superadmin');
    $pageTitle = 'Создать Руководителя';
    $pageContext = 'Реестр компаний';

    $errors = [];
    $old = $_POST;
    $formError = null;
    $success = false;
    $generatedPassword = null;
    $createdOwner = null;
    $tempPassword = null;
    $ownerExists = false;
    $existingOwner = null;
    $isModal = ($_POST['is_modal'] ?? '0') === '1';

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([(int) $id]);
        $company = $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;

        if (!$company) {
            if ($isModal) {
                echo '<div class="modal-body"><div class="notice warn">Компания не найдена.</div></div>';
                return;
            }
            ob_start();
            require base_path('app/View/pages/superadmin_company_owner_create.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $ownerStmt = $pdo->prepare("SELECT * FROM company_users WHERE company_id = ? AND role = 'company_owner' LIMIT 1");
        $ownerStmt->execute([(int) $id]);
        $existingOwner = $ownerStmt->fetch(\PDO::FETCH_ASSOC) ?: null;
        $ownerExists = $existingOwner !== null;

        if ($ownerExists) {
            if ($isModal) {
                echo '<div class="modal-body"><div class="notice warn">Руководитель для этой компании уже создан: <strong>' . e($existingOwner['full_name']) . '</strong> (логин: ' . e($existingOwner['login']) . '). Дублирование невозможно.</div></div>';
                return;
            }
            ob_start();
            require base_path('app/View/pages/superadmin_company_owner_create.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $fullName = trim($_POST['full_name'] ?? '');
        $login = trim($_POST['login'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $position = trim($_POST['position'] ?? '');
        $comments = trim($_POST['comments'] ?? '');

        if ($fullName === '') {
            $errors['full_name'] = 'Обязательное поле';
        }

        if ($login === '') {
            $errors['login'] = 'Обязательное поле';
        } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $login)) {
            $errors['login'] = 'Только латинские буквы, цифры и подчёркивание';
        } else {
            $dupStmt = $pdo->prepare('SELECT COUNT(*) FROM company_users WHERE login = ?');
            $dupStmt->execute([$login]);
            if ((int) $dupStmt->fetchColumn() > 0) {
                $errors['login'] = 'Такой логин уже используется';
            }
        }

        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Некорректный email';
        }

        if ($password === '') {
            $password = generatePassword();
        }

        if (!empty($errors)) {
            $generatedPassword = generatePassword();
            if ($isModal) {
                ob_start();
                require base_path('app/View/partials/superadmin_company_owner_create_form.php');
                $modalBody = ob_get_clean();
                echo '<div class="modal-body">' . $modalBody . '</div>
                    <div class="modal-foot is-spaced">
                        <div class="modal-required-note"><span class="req">*</span> — обязательные поля</div>
                        <div class="modal-foot-actions">
                            <button type="button" class="btn btn-ghost" data-owner-create-close>Отмена</button>
                            <button type="submit" form="owner-create-form" class="btn btn-primary">Создать руководителя</button>
                        </div>
                    </div>';
                return;
            }
            ob_start();
            require base_path('app/View/pages/superadmin_company_owner_create.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $insert = $pdo->prepare(
            'INSERT INTO company_users (company_id, full_name, login, email, phone, position, password_hash, role, status, comments)
             VALUES (:company_id, :full_name, :login, :email, :phone, :position, :password_hash, :role, :status, :comments)'
        );
        $insert->execute([
            ':company_id'    => (int) $id,
            ':full_name'     => $fullName,
            ':login'         => $login,
            ':email'         => $email !== '' ? $email : null,
            ':phone'         => $phone !== '' ? $phone : null,
            ':position'      => $position !== '' ? $position : null,
            ':password_hash' => password_hash($password, PASSWORD_BCRYPT),
            ':role'          => 'company_owner',
            ':status'        => 'active',
            ':comments'      => $comments !== '' ? $comments : null,
        ]);

        $createdOwner = [
            'full_name' => $fullName,
            'login'     => $login,
        ];
        $tempPassword = $password;
        $success = true;
    } catch (\Exception $e) {
        $company = $company ?? null;
        $formError = 'Ошибка создания Руководителя: ' . $e->getMessage();
    }

    if ($isModal) {
        if ($success) {
            echo '<div class="modal-body" data-owner-create-success="1">
                <div class="notice success">Главный пользователь успешно создан.</div>
                <dl class="kv owner-create-success-dl">
                    <dt>Компания</dt>
                    <dd>' . e($company['name']) . '</dd>
                    <dt>ФИО</dt>
                    <dd>' . e($createdOwner['full_name']) . '</dd>
                    <dt>Логин</dt>
                    <dd><code>' . e($createdOwner['login']) . '</code></dd>
                    <dt>Временный пароль</dt>
                    <dd><code class="code-hi">' . e($tempPassword) . '</code></dd>
                    <dt>Роль</dt>
                    <dd>Руководитель</dd>
                </dl>
            </div>
            <div class="modal-foot is-spaced">
                <div class="modal-foot-actions">
                    <button type="button" class="btn btn-secondary" data-owner-create-close>Закрыть</button>
                </div>
            </div>';
            return;
        }

        ob_start();
        require base_path('app/View/partials/superadmin_company_owner_create_form.php');
        $modalBody = ob_get_clean();
        echo '<div class="modal-body">' . $modalBody . '</div>
            <div class="modal-foot is-spaced">
                <div class="modal-required-note"><span class="req">*</span> — обязательные поля</div>
                <div class="modal-foot-actions">
                    <button type="button" class="btn btn-ghost" data-owner-create-close>Отмена</button>
                    <button type="submit" form="owner-create-form" class="btn btn-primary">Создать руководителя</button>
                </div>
            </div>';
        return;
    }

    ob_start();
    require base_path('app/View/pages/superadmin_company_owner_create.php');
    $content = ob_get_clean();
    require base_path('app/View/layouts/main.php');
