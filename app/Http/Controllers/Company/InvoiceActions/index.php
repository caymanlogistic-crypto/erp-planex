<?php

use App\Core\Database;
use App\Service\FinanceInvoiceService;
use App\Service\FinanceObligationService;

requireRole(['company_owner']);

$pageTitle = 'Счета';
$pageContext = 'Финансы › Счета';

$companyId = (int)(getSessionCompanyId() ?? 0);
$company = null;
$localPdo = null;
$dbError = null;
$invoices = [];
$direction = $_GET['direction'] ?? '';

if ($companyId <= 0) {
    ob_start();
    require base_path('app/View/pages/company_finance_invoices.php');
    $content = ob_get_clean();
    require base_path('app/View/layouts/main.php');
    return;
}

try {
    $pdo = $db->connection();
    $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
    $stmt->execute([$companyId]);
    $company = $stmt->fetch(\PDO::FETCH_ASSOC);

    if (!$company) {
        $company = null;
        $dbError = 'Компания не найдена.';
    } elseif ($company['status'] !== 'active') {
        $company = null;
        $dbError = 'Компания неактивна.';
    } else {
        $pageContext = 'Финансы › Счета › Компания: ' . $company['name'];

        $localConfig = companyDatabaseConfig($config, $company);
        $localDb = new Database($localConfig);
        $localPdo = $localDb->connection();
        applyLocalMigrations($localPdo);
        FinanceObligationService::syncAllLinearRoutes($localPdo);

        $user = $_SESSION['user'] ?? [];
        $allowedDirection = in_array($direction, [FinanceInvoiceService::DIRECTION_OUTGOING, FinanceInvoiceService::DIRECTION_INCOMING], true) ? $direction : null;
        $invPage = max(1, (int) ($_GET['page'] ?? 1));
        $invPerPage = max(1, min(500, (int) ($_GET['per_page'] ?? 100)));
        $invResult = FinanceInvoiceService::fetchInvoices($localPdo, $user, $allowedDirection, $invPage, $invPerPage);
        $invoices = $invResult['data'];
        $invTotal = $invResult['total'];
        $invPages = $invResult['pages'];
    }
} catch (\Throwable $e) {
    $company = $company ?? null;
    $dbError = 'Ошибка загрузки данных: ' . $e->getMessage();
}

ob_start();
require base_path('app/View/pages/company_finance_invoices.php');
$content = ob_get_clean();
require base_path('app/View/layouts/main.php');
