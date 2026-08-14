<?php

namespace App\Http\Controllers\Company;

use App\Core\Database;
use App\Service\FinanceCashService;
use App\Service\FinanceEmployeePaymentService;
use PDO;

final class FinanceEmployeePaymentsController
{
    public function __construct(private readonly array $config, private readonly Database $db) {}

    public function index(): void
    {
        requireRole(['company_owner']);
        [$company, $pdo] = $this->tenant();
        $filters = [
            'employee_user_id' => (int)($_GET['employee_user_id'] ?? 0),
            'source_type' => strtoupper(trim((string)($_GET['source_type'] ?? ''))),
            'date_from' => trim((string)($_GET['date_from'] ?? '')),
            'date_to' => trim((string)($_GET['date_to'] ?? '')),
        ];
        $employees = FinanceEmployeePaymentService::fetchActiveEmployees($pdo);
        $summaries = FinanceEmployeePaymentService::fetchEmployeeSummaries($pdo, $filters);
        $successFlash = $_SESSION['employee_payments_success'] ?? null;
        $errorFlash = $_SESSION['employee_payments_error'] ?? null;
        unset($_SESSION['employee_payments_success'], $_SESSION['employee_payments_error']);
        $pageTitle = 'Выплаты сотрудникам';
        $pageContext = 'Финансы › Выплаты сотрудникам';
        $topbarCrumbs = [
            ['label'=>mb_strtoupper((string)($company['name'] ?? 'Компания')), 'url'=>app_url('/company/dashboard')],
            ['label'=>'Финансы', 'url'=>app_url('/company/finance/dashboard')],
            ['label'=>'Выплаты сотрудникам', 'url'=>null],
        ];
        ob_start();
        require base_path('app/View/pages/company_finance_employee_payments.php');
        $content = ob_get_clean();
        $config = $this->config;
        $db = $this->db;
        require base_path('app/View/layouts/main.php');
    }

    public function createForm(): void
    {
        requireRole(['company_owner']);
        [, $pdo] = $this->tenant();
        $movementType = strtoupper(trim((string)($_GET['type'] ?? 'PAYMENT')));
        if (!in_array($movementType, ['PAYMENT','RETURN'], true)) $movementType = 'PAYMENT';
        $employees = FinanceEmployeePaymentService::fetchActiveEmployees($pdo);
        $cashAccounts = FinanceCashService::fetchMoneyAccounts($pdo, 'CASH', true);
        $bankCandidates = FinanceEmployeePaymentService::fetchBankCandidates($pdo, $movementType, 500);
        require base_path('app/View/partials/company_finance_employee_payment_form.php');
    }

    public function createSubmit(): void
    {
        requireRole(['company_owner']);
        verifyCsrfRequest();
        try {
            [, $pdo] = $this->tenant();
            $sourceType = strtoupper(trim((string)($_POST['source_type'] ?? '')));
            $user = ['id'=>$_SESSION['user_id'] ?? 0, 'role'=>$_SESSION['role_code'] ?? 'company_owner'];
            if ($sourceType === 'CASH') {
                FinanceEmployeePaymentService::createCashMovement($pdo, $_POST, $user);
            } elseif ($sourceType === 'BANK') {
                FinanceEmployeePaymentService::linkBankTransaction($pdo, $_POST, $user);
            } else {
                throw new \InvalidArgumentException('Выберите источник денежных средств.');
            }
            $_SESSION['employee_payments_success'] = strtoupper((string)($_POST['movement_type'] ?? '')) === 'RETURN'
                ? 'Возврат сотрудника сохранён.' : 'Выплата сотруднику сохранена.';
        } catch (\Throwable $e) {
            $_SESSION['employee_payments_error'] = $e->getMessage();
        }
        redirect_to('/company/finance/employee-payments');
    }

    public function employeeDetail(int $id): void
    {
        requireRole(['company_owner']);
        [, $pdo] = $this->tenant();
        $stmt = $pdo->prepare('SELECT id,full_name,login,status FROM users WHERE id=?');
        $stmt->execute([$id]);
        $employee = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$employee) { http_response_code(404); echo '<div class="form-alert alert-error">Сотрудник не найден.</div>'; return; }
        $ledger = FinanceEmployeePaymentService::fetchEmployeeLedger($pdo, $id);
        require base_path('app/View/partials/company_finance_employee_payment_ledger.php');
    }

    public function bankLink(): void
    {
        requireRole(['company_owner']);
        verifyCsrfRequest();
        try {
            [, $pdo] = $this->tenant();
            FinanceEmployeePaymentService::linkBankTransaction($pdo, $_POST, ['id'=>$_SESSION['user_id'] ?? 0,'role'=>$_SESSION['role_code'] ?? 'company_owner']);
            $_SESSION['bank_finance_success'] = 'Банковская операция связана с сотрудником.';
        } catch (\Throwable $e) {
            $_SESSION['bank_finance_error'] = $e->getMessage();
        }
        redirect_to('/company/finance/bank-accounts');
    }

    public function bankUnlink(): void
    {
        requireRole(['company_owner']);
        verifyCsrfRequest();
        try {
            [, $pdo] = $this->tenant();
            $bankTransactionId = (int)($_POST['bank_transaction_id'] ?? 0);
            if ($bankTransactionId <= 0) throw new \InvalidArgumentException('Банковская операция не найдена.');
            FinanceEmployeePaymentService::unlinkBankTransaction($pdo, $bankTransactionId, ['id'=>$_SESSION['user_id'] ?? 0,'role'=>$_SESSION['role_code'] ?? 'company_owner']);
            $_SESSION['bank_finance_success'] = 'Связь с сотрудником удалена.';
        } catch (\Throwable $e) {
            $_SESSION['bank_finance_error'] = $e->getMessage();
        }
        redirect_to('/company/finance/bank-accounts');
    }

    private function tenant(): array
    {
        $companyId = (int)(getSessionCompanyId() ?? 0);
        if ($companyId <= 0) throw new \RuntimeException('Компания не найдена.');
        $central = $this->db->connection();
        $stmt = $central->prepare("SELECT * FROM companies WHERE id=? AND status='active'");
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$company) throw new \RuntimeException('Компания не найдена или неактивна.');
        $pdo = (new Database(companyDatabaseConfig($this->config, $company)))->connection();
        return [$company, $pdo];
    }
}
