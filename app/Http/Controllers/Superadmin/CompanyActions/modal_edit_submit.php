<?php

    requireRole('superadmin');

    try {
        $pdo = $db->connection();

        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([(int) $id]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company) {
            http_response_code(404);
            echo '<div class="modal-body"><div class="notice warn">Компания не найдена.</div></div>';
            return;
        }

        $errors = [];
        $old = $_POST;
        $formError = null;

        $name = trim($_POST['name'] ?? '');
        $inn  = trim($_POST['inn'] ?? '');

        if ($name === '') {
            $errors['name'] = 'Обязательное поле';
        }

        if ($inn === '') {
            $errors['inn'] = 'Обязательное поле';
        } else {
            $dupStmt = $pdo->prepare('SELECT COUNT(*) FROM companies WHERE inn = ? AND id != ?');
            $dupStmt->execute([$inn, (int) $id]);
            if ($dupStmt->fetchColumn() > 0) {
                $errors['inn'] = 'ИНН уже используется';
            }
        }

        if (!empty($errors)) {
            ob_start();
            require base_path('app/View/partials/superadmin_company_modal_edit.php');
            echo ob_get_clean();
            return;
        }

        $update = $pdo->prepare(
            'UPDATE companies SET
                name = :name,
                inn = :inn,
                kpp = :kpp,
                ogrn = :ogrn,
                legal_address = :legal_address,
                physical_address = :physical_address,
                director_position = :director_position,
                director_full_name = :director_full_name,
                status = :status,
                comments = :comments
             WHERE id = :id'
        );

        $update->execute([
            ':name'              => $name,
            ':inn'               => $inn,
            ':kpp'               => $_POST['kpp'] ?? null,
            ':ogrn'              => $_POST['ogrn'] ?? null,
            ':legal_address'     => $_POST['legal_address'] ?? null,
            ':physical_address'  => $_POST['physical_address'] ?? null,
            ':director_position' => $_POST['director_position'] ?? null,
            ':director_full_name'=> $_POST['director_full_name'] ?? null,
            ':status'            => $_POST['status'] ?? $company['status'],
            ':comments'          => $_POST['comments'] ?? null,
            ':id'                => (int) $id,
        ]);

        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([(int) $id]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        $canEdit = true;

        ob_start();
        require base_path('app/View/partials/superadmin_company_modal_view.php');
        echo ob_get_clean();
    } catch (\Exception $e) {
        $formError = 'Ошибка сохранения: ' . $e->getMessage();
        $errors = [];
        $old = $_POST;

        ob_start();
        require base_path('app/View/partials/superadmin_company_modal_edit.php');
        echo ob_get_clean();
    }
