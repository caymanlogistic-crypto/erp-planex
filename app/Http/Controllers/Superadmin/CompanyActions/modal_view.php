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

        $canEdit = true;

        ob_start();
        require base_path('app/View/partials/superadmin_company_modal_view.php');
        echo ob_get_clean();
    } catch (\Exception $e) {
        http_response_code(500);
        echo '<div class="modal-body"><div class="notice warn">Ошибка загрузки: ' . e($e->getMessage()) . '</div></div>';
    }
