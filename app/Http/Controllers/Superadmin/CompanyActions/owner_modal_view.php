<?php

    requireRole('superadmin');

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([(int) $id]);
        $company = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$company) {
            http_response_code(404);
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

        $user = $owner;
        $canEdit = true;
        $isOwner = true;

        require base_path('app/View/partials/superadmin_user_modal_view.php');
    } catch (\Exception $e) {
        http_response_code(500);
        echo '<div class="modal-body"><div class="notice danger">Ошибка загрузки данных: ' . e($e->getMessage()) . '</div></div>';
    }
