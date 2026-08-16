<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/Service/ProductionCalendarService.php';

use App\Service\ProductionCalendarService;

function pcAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException('FAIL: ' . $message);
    }
}

$pdo = new PDO(
    'mysql:host=' . (getenv('PRODUCTION_CALENDAR_DB_HOST') ?: '127.0.0.1')
    . ';port=' . (getenv('PRODUCTION_CALENDAR_DB_PORT') ?: '3306')
    . ';dbname=' . (getenv('PRODUCTION_CALENDAR_DB_NAME') ?: 'erp_production_calendar_test')
    . ';charset=utf8mb4',
    getenv('PRODUCTION_CALENDAR_DB_USER') ?: 'root',
    getenv('PRODUCTION_CALENDAR_DB_PASSWORD') ?: 'root',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);

$pdo->exec('SET FOREIGN_KEY_CHECKS=0');
$pdo->exec('DROP TABLE IF EXISTS production_calendar_days');
$pdo->exec('DROP TABLE IF EXISTS production_calendar_years');
$pdo->exec('SET FOREIGN_KEY_CHECKS=1');
$sql = file_get_contents(__DIR__ . '/../database/migrations-local/070_production_calendar.sql');
pcAssert(is_string($sql) && trim($sql) !== '', 'migration must exist');
$pdo->exec($sql);

$seedState = ProductionCalendarService::seedOfficial2026($pdo);
pcAssert($seedState === 'seeded', 'official 2026 calendar must seed into empty schema');
pcAssert(ProductionCalendarService::isYearReady($pdo, 2026), '2026 must be READY after official seed');

$totals = $pdo->query(
    "SELECT COUNT(*) AS total,
            SUM(CASE WHEN is_working_day=1 THEN 1 ELSE 0 END) AS working,
            SUM(CASE WHEN is_working_day=0 THEN 1 ELSE 0 END) AS non_working
       FROM production_calendar_days WHERE calendar_year=2026"
)->fetch();
pcAssert((int)$totals['total'] === 365, '2026 must contain all 365 dates');
pcAssert((int)$totals['working'] === 247, '2026 must contain 247 working days');
pcAssert((int)$totals['non_working'] === 118, '2026 must contain 118 non-working days');

foreach (['2026-01-01','2026-01-09','2026-02-23','2026-03-09','2026-05-11','2026-06-12','2026-11-04','2026-12-31'] as $date) {
    pcAssert(!ProductionCalendarService::isWorkingDay($pdo, $date), $date . ' must be non-working');
}
pcAssert(ProductionCalendarService::isWorkingDay($pdo, '2026-01-12'), '2026-01-12 must be working');
pcAssert(ProductionCalendarService::addWorkingDays($pdo, '2026-01-08', 1) === '2026-01-12', 'working-day calculation must cross Jan 9-11 correctly');
pcAssert(ProductionCalendarService::addWorkingDays($pdo, '2026-03-06', 1) === '2026-03-10', 'working-day calculation must cross March 7-9 correctly');

$year = ProductionCalendarService::fetchYear($pdo, 2026);
pcAssert(($year['source_title'] ?? '') === ProductionCalendarService::OFFICIAL_2026_SOURCE_TITLE, 'official source title must be stored');
pcAssert(($year['source_url'] ?? '') === ProductionCalendarService::OFFICIAL_2026_SOURCE_URL, 'official source URL must be stored');

ProductionCalendarService::createDraftYear($pdo, 2027, ['id'=>10,'role'=>'company_owner']);
pcAssert(!ProductionCalendarService::isYearReady($pdo, 2027), 'new manual year must start as DRAFT');
$days2027 = ProductionCalendarService::fetchYearDays($pdo, 2027);
pcAssert(count($days2027) === 365, 'draft 2027 must still contain every date');

ProductionCalendarService::markReady(
    $pdo,
    2027,
    ['source_title'=>'Тестовый источник','source_url'=>'https://example.invalid','note'=>'Тест'],
    ['id'=>10,'role'=>'company_owner']
);
pcAssert(ProductionCalendarService::isYearReady($pdo, 2027), 'complete 2027 can be confirmed');

ProductionCalendarService::saveDay(
    $pdo,
    ['calendar_date'=>'2027-01-11','day_type'=>ProductionCalendarService::TYPE_HOLIDAY,'name'=>'Тестовый праздник','note'=>'Проверка'],
    ['id'=>10,'role'=>'company_owner']
);
pcAssert(!ProductionCalendarService::isYearReady($pdo, 2027), 'manual day edit must return year to DRAFT');
pcAssert(!ProductionCalendarService::isWorkingDay($pdo, '2026-01-09'), 'editing another year must not affect ready 2026');

$thrown = false;
try {
    ProductionCalendarService::isWorkingDay($pdo, '2027-01-11');
} catch (RuntimeException $e) {
    $thrown = str_contains($e->getMessage(), 'не загружен или не подтверждён');
}
pcAssert($thrown, 'working-day API must refuse unconfirmed calendar years');

echo "PRODUCTION_CALENDAR_BEHAVIOR_OK\n";
