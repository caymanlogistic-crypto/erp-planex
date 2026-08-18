<?php

use App\Core\Database;
use App\Service\FinanceEmployeePaymentService;

requireRole(['company_owner']);
verifyCsrfRequest();

try {
    $companyId = (int)(getSessionCompanyId() ?? 0);
    if ($companyId <= 0) {
        throw new RuntimeException('Компания не найдена.');
    }
    $central = $db->connection();
    $stmt = $central->prepare("SELECT * FROM companies WHERE id=? AND status='active'");
    $stmt->execute([$companyId]);
    $company = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$company) {
        throw new RuntimeException('Компания не найдена или неактивна.');
    }
    $pdo = (new Database(companyDatabaseConfig($config, $company)))->connection();
    $employee = FinanceEmployeePaymentService::resolveActiveEmployee(
        $pdo,
        $central,
        $companyId,
        trim((string)($_POST['employee_ref'] ?? ''))
    );
    $result = FinanceEmployeePaymentService::linkBankTransaction(
        $pdo,
        $_POST,
        ['id' => $_SESSION['user_id'] ?? 0, 'role' => $_SESSION['role_code'] ?? 'company_owner'],
        $employee
    );
    $_SESSION['bank_finance_success'] = 'Банковская операция напрямую связана с сотрудником. Техническая касса не использована.';
    $_SESSION['employee_payments_success'] = 'Банковская операция напрямую отражена у сотрудника.';
} catch (Throwable $e) {
    $_SESSION['bank_finance_error'] = $e->getMessage();
    $_SESSION['employee_payments_error'] = $e->getMessage();
}

$back = trim((string)($_POST['return_to'] ?? ''));
if ($back === 'employee') {
    $ref = trim((string)($_POST['employee_ref'] ?? ''));
    redirect_to('/company/finance/employee-payments' . ($ref !== '' ? '?employee_ref=' . rawurlencode($ref) : ''));
}
redirect_to('/company/finance/bank-accounts');
