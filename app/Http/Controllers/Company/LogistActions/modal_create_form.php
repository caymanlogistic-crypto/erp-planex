<?php
requireRole('company_owner');

$companyId = (int)(getSessionCompanyId() ?? 0);

if ($companyId <= 0) {
    echo '<div class="modal-body"><div class="notice warn">Компания не найдена.</div></div>';
    return;
}

try {
    $pdo = $db->connection();
    $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
    $stmt->execute([$companyId]);
    $company = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$company || $company['status'] !== 'active') {
        echo '<div class="modal-body"><div class="notice warn">Компания недоступна.</div></div>';
        return;
    }

    $errors = [];
    $old = [];
    $formError = null;
    $generatedPassword = generatePassword();
    require base_path('app/View/partials/company_user_create_form.php');
} catch (\Exception $e) {
    echo '<div class="modal-body"><div class="notice danger">Ошибка загрузки данных: ' . e($e->getMessage()) . '</div></div>';
}
