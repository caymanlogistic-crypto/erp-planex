<?php

namespace App\Http\Controllers\Company;

use App\Core\Database;
use App\Service\ProductionCalendarService;
use PDO;

final class ProductionCalendarController
{
    public function __construct(
        private readonly array $config,
        private readonly Database $db
    ) {}

    public function index(): void
    {
        requireRole(['company_owner']);
        [$company, $pdo] = $this->tenant();

        $years = ProductionCalendarService::fetchYears($pdo);
        $requestedYear = (int)($_GET['year'] ?? 0);
        if ($requestedYear < 2000 || $requestedYear > 2100) {
            $requestedYear = (int)date('Y');
        }
        $year = $requestedYear;
        $calendar = ProductionCalendarService::fetchYear($pdo, $year);
        $days = $calendar ? ProductionCalendarService::fetchYearDays($pdo, $year) : [];
        $warningYear = ProductionCalendarService::warningYearForCompany(
            $this->config,
            $this->db,
            (int)$company['id'],
            $company
        );

        $pageTitle = 'Производственный календарь';
        $pageContext = 'Прочее › Производственный календарь';
        $topbarCrumbs = [
            ['label' => mb_strtoupper((string)($company['name'] ?? 'Компания')), 'url' => app_url('/company/dashboard')],
            ['label' => 'Прочее', 'url' => null],
            ['label' => 'Производственный календарь', 'url' => null],
        ];
        $successFlash = $_SESSION['production_calendar_success'] ?? null;
        $errorFlash = $_SESSION['production_calendar_error'] ?? null;
        unset($_SESSION['production_calendar_success'], $_SESSION['production_calendar_error']);

        ob_start();
        require base_path('app/View/pages/company_production_calendar.php');
        $content = ob_get_clean();
        $config = $this->config;
        $db = $this->db;
        require base_path('app/View/layouts/main.php');
    }

    public function createYear(): void
    {
        requireRole(['company_owner']);
        verifyCsrfRequest();
        $year = (int)($_POST['year'] ?? 0);
        try {
            [, $pdo] = $this->tenant();
            ProductionCalendarService::createDraftYear($pdo, $year, $this->user());
            $_SESSION['production_calendar_success'] = 'Календарь на ' . $year . ' год создан как черновик. Проверьте официальные праздники и переносы, затем подтвердите календарь.';
        } catch (\Throwable $e) {
            $_SESSION['production_calendar_error'] = $e->getMessage();
        }
        $this->redirectToYear($year);
    }

    public function saveDay(): void
    {
        requireRole(['company_owner']);
        verifyCsrfRequest();
        $date = trim((string)($_POST['calendar_date'] ?? ''));
        $year = (int)substr($date, 0, 4);
        try {
            [, $pdo] = $this->tenant();
            ProductionCalendarService::saveDay($pdo, $_POST, $this->user());
            $_SESSION['production_calendar_success'] = 'День календаря сохранён. Статус года переведён в «Черновик» до повторного подтверждения.';
        } catch (\Throwable $e) {
            $_SESSION['production_calendar_error'] = $e->getMessage();
        }
        $this->redirectToYear($year);
    }

    public function confirmYear(): void
    {
        requireRole(['company_owner']);
        verifyCsrfRequest();
        $year = (int)($_POST['year'] ?? 0);
        try {
            [, $pdo] = $this->tenant();
            ProductionCalendarService::markReady($pdo, $year, $_POST, $this->user());
            $_SESSION['production_calendar_success'] = 'Производственный календарь на ' . $year . ' год подтверждён и готов к использованию.';
        } catch (\Throwable $e) {
            $_SESSION['production_calendar_error'] = $e->getMessage();
        }
        $this->redirectToYear($year);
    }

    private function user(): array
    {
        return [
            'id' => (int)($_SESSION['user_id'] ?? 0),
            'role' => (string)($_SESSION['role_code'] ?? 'company_owner'),
        ];
    }

    private function redirectToYear(int $year): void
    {
        $url = '/company/misc/production-calendar';
        if ($year >= 2000 && $year <= 2100) {
            $url .= '?year=' . $year;
        }
        redirect_to($url);
    }

    private function tenant(): array
    {
        $companyId = (int)(getSessionCompanyId() ?? 0);
        if ($companyId <= 0) {
            throw new \RuntimeException('Компания не найдена.');
        }
        $central = $this->db->connection();
        $stmt = $central->prepare("SELECT * FROM companies WHERE id=? AND status='active'");
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$company) {
            throw new \RuntimeException('Компания не найдена или неактивна.');
        }
        $pdo = (new Database(companyDatabaseConfig($this->config, $company)))->connection();
        return [$company, $pdo, $central];
    }
}
