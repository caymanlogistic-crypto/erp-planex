<?php

    requireRole('superadmin');

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([(int) $id]);
        $company = $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;

        if (!$company) {
            http_response_code(404);
            echo '<div class="modal-body"><div class="notice warn">Компания не найдена.</div></div>';
            return;
        }

        $ownerStmt = $pdo->prepare("SELECT * FROM company_users WHERE company_id = ? AND role = 'company_owner' LIMIT 1");
        $ownerStmt->execute([(int) $id]);
        $existingOwner = $ownerStmt->fetch(\PDO::FETCH_ASSOC) ?: null;
        $ownerExists = $existingOwner !== null;

        if ($ownerExists) {
            echo '<div class="modal-body"><div class="notice warn">Руководитель для этой компании уже создан: <strong>' . e($existingOwner['full_name']) . '</strong> (логин: ' . e($existingOwner['login']) . '). Дублирование невозможно.</div></div>';
            return;
        }

        $errors = [];
        $old = [];
        $formError = null;
        $generatedPassword = generatePassword();
        $isModal = true;
    } catch (\Exception $e) {
        http_response_code(500);
        echo '<div class="modal-body"><div class="notice warn">Ошибка загрузки данных: ' . e($e->getMessage()) . '</div></div>';
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
