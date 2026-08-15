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
        [$company, $pdo, $central] = $this->tenant();
        $filters = [
            'employee_ref' => trim((string)($_GET['employee_ref'] ?? '')),
            'source_type' => strtoupper(trim((string)($_GET['source_type'] ?? ''))),
            'date_from' => trim((string)($_GET['date_from'] ?? '')),
            'date_to' => trim((string)($_GET['date_to'] ?? '')),
        ];
        $employees = FinanceEmployeePaymentService::fetchActiveEmployees($pdo, $central, (int)$company['id']);
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
        [$company, $pdo, $central] = $this->tenant();
        $movementType = strtoupper(trim((string)($_GET['type'] ?? 'PAYMENT')));
        if (!in_array($movementType, ['PAYMENT','RETURN'], true)) $movementType = 'PAYMENT';
        $employees = FinanceEmployeePaymentService::fetchActiveEmployees($pdo, $central, (int)$company['id']);
        $cashAccounts = FinanceCashService::fetchMoneyAccounts($pdo, 'CASH', true);
        $bankCandidates = FinanceEmployeePaymentService::fetchBankCandidates($pdo, $movementType, 500);
        require base_path('app/View/partials/company_finance_employee_payment_form.php');
    }

    public function createSubmit(): void
    {
        requireRole(['company_owner']);
        verifyCsrfRequest();
        try {
            [$company, $pdo, $central] = $this->tenant();
            $employee = FinanceEmployeePaymentService::resolveActiveEmployee($pdo, $central, (int)$company['id'], trim((string)($_POST['employee_ref'] ?? '')));
            $sourceType = strtoupper(trim((string)($_POST['source_type'] ?? '')));
            $user = ['id'=>$_SESSION['user_id'] ?? 0, 'role'=>$_SESSION['role_code'] ?? 'company_owner'];
            if ($sourceType === 'CASH') {
                FinanceEmployeePaymentService::createCashMovement($pdo, $_POST, $user, $employee);
            } elseif ($sourceType === 'BANK') {
                FinanceEmployeePaymentService::linkBankTransaction($pdo, $_POST, $user, $employee);
            } else {
                throw new \InvalidArgumentException('Выберите источник денежных средств.');
            }
            $_SESSION['employee_payments_success'] = strtoupper((string)($_POST['movement_type'] ?? '')) === 'RETURN' ? 'Возврат сотрудника сохранён.' : 'Выплата сотруднику сохранена.';
        } catch (\Throwable $e) {
            $_SESSION['employee_payments_error'] = $e->getMessage();
        }
        redirect_to('/company/finance/employee-payments');
    }

    public function employeeDetail(string $type, string $id): void
    {
        requireRole(['company_owner']);
        [$company, $pdo, $central] = $this->tenant();
        try {
            $employeeRef = FinanceEmployeePaymentService::makeEmployeeRef(strtoupper($type), (int)$id);
            $employee = FinanceEmployeePaymentService::resolveActiveEmployee($pdo, $central, (int)$company['id'], $employeeRef);
        } catch (\Throwable $e) {
            $employeeRef = FinanceEmployeePaymentService::makeEmployeeRef(strtoupper($type), (int)$id);
            $ledger = FinanceEmployeePaymentService::fetchEmployeeLedger($pdo, $employeeRef);
            if (empty($ledger)) { http_response_code(404); echo '<div class="form-alert alert-error">Сотрудник не найден.</div>'; return; }
            $employee = ['ref'=>$employeeRef,'full_name'=>(string)$ledger[0]['full_name'],'status'=>'inactive'];
        }
        $ledger = $ledger ?? FinanceEmployeePaymentService::fetchEmployeeLedger($pdo, $employeeRef);
        $employees = FinanceEmployeePaymentService::fetchActiveEmployees($pdo, $central, (int)$company['id']);
        require base_path('app/View/partials/company_finance_employee_payment_ledger.php');
    }

    public function reassignMovement(int $id): void
    {
        requireRole(['company_owner']);
        verifyCsrfRequest();
        try {
            [$company, $pdo, $central] = $this->tenant();
            $employee = FinanceEmployeePaymentService::resolveActiveEmployee($pdo, $central, (int)$company['id'], trim((string)($_POST['employee_ref'] ?? '')));
            FinanceEmployeePaymentService::reassignMovement($pdo, $id, $employee, ['id'=>$_SESSION['user_id'] ?? 0,'role'=>$_SESSION['role_code'] ?? 'company_owner']);
            $_SESSION['employee_payments_success'] = 'Сотрудник для операции изменён. Денежная операция не изменялась.';
        } catch (\Throwable $e) {
            $_SESSION['employee_payments_error'] = $e->getMessage();
        }
        redirect_to('/company/finance/employee-payments');
    }

    public function bankLink(): void
    {
        requireRole(['company_owner']);
        verifyCsrfRequest();
        try {
            [$company, $pdo, $central] = $this->tenant();
            $employeeRef = trim((string)($_POST['employee_ref'] ?? ''));
            if ($employeeRef === '') {
                $legacyId = (int)($_POST['employee_user_id'] ?? 0);
                if ($legacyId === 0) throw new \InvalidArgumentException('Выберите сотрудника.');
                $employeeRef = $legacyId < 0
                    ? FinanceEmployeePaymentService::makeEmployeeRef(FinanceEmployeePaymentService::IDENTITY_COMPANY_USER, abs($legacyId))
                    : FinanceEmployeePaymentService::makeEmployeeRef(FinanceEmployeePaymentService::IDENTITY_TENANT_USER, $legacyId);
            }
            $employee = FinanceEmployeePaymentService::resolveActiveEmployee($pdo, $central, (int)$company['id'], $employeeRef);
            FinanceEmployeePaymentService::linkBankTransaction($pdo, $_POST, ['id'=>$_SESSION['user_id'] ?? 0,'role'=>$_SESSION['role_code'] ?? 'company_owner'], $employee);
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
        return [$company, $pdo, $central];
    }
}
