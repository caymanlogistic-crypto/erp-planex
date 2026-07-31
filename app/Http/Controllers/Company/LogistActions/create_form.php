<?php
requireRole('company_owner');
$pageTitle = 'Создать пользователя';
$pageContext = 'Пользователи › Компания';

$companyId = (int)(getSessionCompanyId() ?? 0);

if ($companyId <= 0) {
    $company = null;
    $success = false;
    $errors = [];
    $old = ['contacts' => contractorFormDefaultContacts()];
    $formError = null;
    $generatedPassword = null;
    $createdLogist = null;
    $tempPassword = null;

    ob_start();
    require base_path('app/View/pages/company_logists_create.php');
    $content = ob_get_clean();
    require base_path('app/View/layouts/main.php');
    return;
}

try {
    $pdo = $db->connection();
    $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
    $stmt->execute([$companyId]);
    $company = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$company) {
        $company = null;
        $success = false;
        $errors = [];
        $old = [];
        $formError = null;
        $generatedPassword = null;
        $createdLogist = null;
        $tempPassword = null;

        ob_start();
        require base_path('app/View/pages/company_logists_create.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
        return;
    }

    $pageContext = 'Пользователи › Компания: ' . $company['name'];

    $success = false;
    $errors = [];
    $old = [];
    $formError = null;
    $generatedPassword = generatePassword();
    $createdLogist = null;
    $tempPassword = null;
} catch (\Exception $e) {
    $company = null;
    $success = false;
    $errors = [];
    $old = ['contacts' => contractorFormDefaultContacts()];
    $formError = 'Ошибка загрузки данных: ' . $e->getMessage();
    $generatedPassword = null;
    $createdLogist = null;
    $tempPassword = null;
}

ob_start();
require base_path('app/View/pages/company_logists_create.php');
$content = ob_get_clean();
require base_path('app/View/layouts/main.php');
