<?php

namespace App\Service;

use App\Core\Database;
use DateInterval;
use DatePeriod;
use DateTimeImmutable;
use PDO;
use Throwable;

final class ProductionCalendarService
{
    public const STATUS_DRAFT = 'DRAFT';
    public const STATUS_READY = 'READY';

    public const TYPE_WORKDAY = 'WORKDAY';
    public const TYPE_WEEKEND = 'WEEKEND';
    public const TYPE_HOLIDAY = 'HOLIDAY';
    public const TYPE_TRANSFERRED_DAY_OFF = 'TRANSFERRED_DAY_OFF';
    public const TYPE_TRANSFERRED_WORKDAY = 'TRANSFERRED_WORKDAY';

    public const TYPE_LABELS = [
        self::TYPE_WORKDAY => 'Рабочий день',
        self::TYPE_WEEKEND => 'Выходной',
        self::TYPE_HOLIDAY => 'Праздничный нерабочий день',
        self::TYPE_TRANSFERRED_DAY_OFF => 'Перенесённый выходной',
        self::TYPE_TRANSFERRED_WORKDAY => 'Перенесённый рабочий день',
    ];

    public const OFFICIAL_2026_SOURCE_TITLE = 'Постановление Правительства РФ от 24.09.2025 № 1466; статья 112 ТК РФ';
    public const OFFICIAL_2026_SOURCE_URL = 'https://government.ru/docs/all/161028/';

    /** @var array<string,?int> */
    private static array $warningCache = [];

    public static function warningYearForCompany(
        array $config,
        Database $centralDb,
        int $companyId,
        ?array $knownCompany = null,
        ?DateTimeImmutable $today = null
    ): ?int {
        $today ??= new DateTimeImmutable('today');
        if ((int)$today->format('n') < 12 || $companyId <= 0) {
            return null;
        }

        $cacheKey = $companyId . ':' . $today->format('Y-m-d');
        if (array_key_exists($cacheKey, self::$warningCache)) {
            return self::$warningCache[$cacheKey];
        }

        $nextYear = (int)$today->format('Y') + 1;
        try {
            $company = $knownCompany;
            if (!is_array($company) || (int)($company['id'] ?? 0) !== $companyId || ($company['status'] ?? '') !== 'active') {
                $stmt = $centralDb->connection()->prepare("SELECT * FROM companies WHERE id=? AND status='active' LIMIT 1");
                $stmt->execute([$companyId]);
                $company = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
            }
            if (!$company) {
                return self::$warningCache[$cacheKey] = $nextYear;
            }
            $tenant = (new Database(companyDatabaseConfig($config, $company)))->connection();
            return self::$warningCache[$cacheKey] = (self::isYearReady($tenant, $nextYear) ? null : $nextYear);
        } catch (Throwable) {
            return self::$warningCache[$cacheKey] = $nextYear;
        }
    }

    public static function fetchYears(PDO $pdo): array
    {
        try {
            return $pdo->query(
                "SELECT y.*,
                        COUNT(d.id) AS days_count,
                        SUM(CASE WHEN d.is_working_day=1 THEN 1 ELSE 0 END) AS working_days,
                        SUM(CASE WHEN d.is_working_day=0 THEN 1 ELSE 0 END) AS non_working_days
                   FROM production_calendar_years y
              LEFT JOIN production_calendar_days d ON d.calendar_year=y.calendar_year
               GROUP BY y.calendar_year
               ORDER BY y.calendar_year DESC"
            )->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable) {
            return [];
        }
    }

    public static function fetchYear(PDO $pdo, int $year): ?array
    {
        self::assertYear($year);
        try {
            $stmt = $pdo->prepare(
                "SELECT y.*,
                        COUNT(d.id) AS days_count,
                        SUM(CASE WHEN d.is_working_day=1 THEN 1 ELSE 0 END) AS working_days,
                        SUM(CASE WHEN d.is_working_day=0 THEN 1 ELSE 0 END) AS non_working_days
                   FROM production_calendar_years y
              LEFT JOIN production_calendar_days d ON d.calendar_year=y.calendar_year
                  WHERE y.calendar_year=?
               GROUP BY y.calendar_year"
            );
            $stmt->execute([$year]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (Throwable) {
            return null;
        }
    }

    public static function fetchYearDays(PDO $pdo, int $year): array
    {
        self::assertYear($year);
        $stmt = $pdo->prepare(
            "SELECT id,calendar_date,calendar_year,day_type,is_working_day,name,note
               FROM production_calendar_days
              WHERE calendar_year=?
              ORDER BY calendar_date"
        );
        $stmt->execute([$year]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function createDraftYear(PDO $pdo, int $year, array $user): void
    {
        self::assertYear($year);
        if (self::fetchYear($pdo, $year)) {
            throw new \RuntimeException('Производственный календарь на ' . $year . ' год уже существует.');
        }

        $userId = (int)($user['id'] ?? 0);
        $role = trim((string)($user['role'] ?? 'company_owner')) ?: 'company_owner';
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                "INSERT INTO production_calendar_years
                    (calendar_year,status,note,created_by_user_id,created_by_role,updated_by_user_id,updated_by_role)
                 VALUES (?, 'DRAFT', ?, ?, ?, ?, ?)"
            );
            $stmt->execute([$year, 'Создан вручную. Перед использованием проверьте официальные праздники и переносы.', $userId ?: null, $role, $userId ?: null, $role]);
            self::insertBaselineDays($pdo, $year, $userId, $role);
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function saveDay(PDO $pdo, array $data, array $user): void
    {
        $dateString = trim((string)($data['calendar_date'] ?? ''));
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $dateString);
        if (!$date || $date->format('Y-m-d') !== $dateString) {
            throw new \InvalidArgumentException('Некорректная дата.');
        }
        $year = (int)$date->format('Y');
        self::assertYear($year);
        $type = strtoupper(trim((string)($data['day_type'] ?? '')));
        if (!array_key_exists($type, self::TYPE_LABELS)) {
            throw new \InvalidArgumentException('Выберите корректный тип дня.');
        }
        $isWorking = in_array($type, [self::TYPE_WORKDAY, self::TYPE_TRANSFERRED_WORKDAY], true) ? 1 : 0;
        $name = trim((string)($data['name'] ?? ''));
        $note = trim((string)($data['note'] ?? ''));
        $userId = (int)($user['id'] ?? 0);
        $role = trim((string)($user['role'] ?? 'company_owner')) ?: 'company_owner';

        $stmt = $pdo->prepare(
            "UPDATE production_calendar_days
                SET day_type=?,is_working_day=?,name=?,note=?,updated_by_user_id=?,updated_by_role=?
              WHERE calendar_date=? AND calendar_year=?"
        );
        $stmt->execute([$type, $isWorking, $name !== '' ? $name : null, $note !== '' ? $note : null, $userId ?: null, $role, $dateString, $year]);
        if ($stmt->rowCount() === 0) {
            $exists = $pdo->prepare('SELECT id FROM production_calendar_days WHERE calendar_date=?');
            $exists->execute([$dateString]);
            if (!$exists->fetchColumn()) {
                throw new \RuntimeException('День отсутствует в календаре. Сначала создайте календарь на этот год.');
            }
        }

        $pdo->prepare(
            "UPDATE production_calendar_years
                SET status='DRAFT',updated_by_user_id=?,updated_by_role=?
              WHERE calendar_year=?"
        )->execute([$userId ?: null, $role, $year]);
    }

    public static function markReady(PDO $pdo, int $year, array $data, array $user): void
    {
        self::assertYear($year);
        if (!self::isYearComplete($pdo, $year)) {
            throw new \RuntimeException('Календарь на ' . $year . ' год неполный. Должны быть заполнены все дни года.');
        }
        $sourceTitle = trim((string)($data['source_title'] ?? ''));
        $sourceUrl = trim((string)($data['source_url'] ?? ''));
        $note = trim((string)($data['note'] ?? ''));
        $userId = (int)($user['id'] ?? 0);
        $role = trim((string)($user['role'] ?? 'company_owner')) ?: 'company_owner';
        $stmt = $pdo->prepare(
            "UPDATE production_calendar_years
                SET status='READY',source_title=?,source_url=?,note=?,updated_by_user_id=?,updated_by_role=?
              WHERE calendar_year=?"
        );
        $stmt->execute([
            $sourceTitle !== '' ? $sourceTitle : null,
            $sourceUrl !== '' ? $sourceUrl : null,
            $note !== '' ? $note : null,
            $userId ?: null,
            $role,
            $year,
        ]);
        if ($stmt->rowCount() === 0 && !self::fetchYear($pdo, $year)) {
            throw new \RuntimeException('Календарь на ' . $year . ' год не найден.');
        }
    }

    public static function isYearReady(PDO $pdo, int $year): bool
    {
        try {
            $stmt = $pdo->prepare('SELECT status FROM production_calendar_years WHERE calendar_year=? LIMIT 1');
            $stmt->execute([$year]);
            return $stmt->fetchColumn() === self::STATUS_READY && self::isYearComplete($pdo, $year);
        } catch (Throwable) {
            return false;
        }
    }

    public static function isYearComplete(PDO $pdo, int $year): bool
    {
        self::assertYear($year);
        $expected = self::daysInYear($year);
        $stmt = $pdo->prepare(
            'SELECT COUNT(*), MIN(calendar_date), MAX(calendar_date) FROM production_calendar_days WHERE calendar_year=?'
        );
        $stmt->execute([$year]);
        $row = $stmt->fetch(PDO::FETCH_NUM);
        if (!$row || (int)$row[0] !== $expected) {
            return false;
        }
        return (string)$row[1] === sprintf('%04d-01-01', $year)
            && (string)$row[2] === sprintf('%04d-12-31', $year);
    }

    public static function isWorkingDay(PDO $pdo, string $dateString): bool
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $dateString);
        if (!$date || $date->format('Y-m-d') !== $dateString) {
            throw new \InvalidArgumentException('Некорректная дата.');
        }
        $year = (int)$date->format('Y');
        if (!self::isYearReady($pdo, $year)) {
            throw new \RuntimeException('Производственный календарь на ' . $year . ' год не загружен или не подтверждён.');
        }
        $stmt = $pdo->prepare('SELECT is_working_day FROM production_calendar_days WHERE calendar_date=? LIMIT 1');
        $stmt->execute([$dateString]);
        $value = $stmt->fetchColumn();
        if ($value === false) {
            throw new \RuntimeException('Дата отсутствует в производственном календаре.');
        }
        return (int)$value === 1;
    }

    public static function addWorkingDays(PDO $pdo, string $dateString, int $days): string
    {
        if ($days < 0) {
            throw new \InvalidArgumentException('Количество рабочих дней не может быть отрицательным.');
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $dateString);
        if (!$date || $date->format('Y-m-d') !== $dateString) {
            throw new \InvalidArgumentException('Некорректная дата.');
        }
        $added = 0;
        while ($added < $days) {
            $date = $date->modify('+1 day');
            if (self::isWorkingDay($pdo, $date->format('Y-m-d'))) {
                $added++;
            }
        }
        return $date->format('Y-m-d');
    }

    public static function seedOfficial2026(PDO $pdo): string
    {
        $existing = self::fetchYear($pdo, 2026);
        if ($existing) {
            if (self::isYearReady($pdo, 2026)) {
                return 'existing';
            }
            throw new \RuntimeException('Календарь 2026 уже существует, но не подтверждён. Автоматическая загрузка не будет перезаписывать ручные данные.');
        }

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                "INSERT INTO production_calendar_years
                    (calendar_year,status,source_title,source_url,note,created_by_role,updated_by_role)
                 VALUES (2026,'READY',?,?,?,?,?)"
            );
            $stmt->execute([
                self::OFFICIAL_2026_SOURCE_TITLE,
                self::OFFICIAL_2026_SOURCE_URL,
                'Федеральный календарь РФ для пятидневной рабочей недели. 247 рабочих и 118 нерабочих дней.',
                'system',
                'system',
            ]);
            self::insertBaselineDays($pdo, 2026, 0, 'system');

            $holidays = [
                '2026-01-01' => 'Новогодние каникулы',
                '2026-01-02' => 'Новогодние каникулы',
                '2026-01-03' => 'Новогодние каникулы',
                '2026-01-04' => 'Новогодние каникулы',
                '2026-01-05' => 'Новогодние каникулы',
                '2026-01-06' => 'Новогодние каникулы',
                '2026-01-07' => 'Рождество Христово',
                '2026-01-08' => 'Новогодние каникулы',
                '2026-02-23' => 'День защитника Отечества',
                '2026-03-08' => 'Международный женский день',
                '2026-05-01' => 'Праздник Весны и Труда',
                '2026-05-09' => 'День Победы',
                '2026-06-12' => 'День России',
                '2026-11-04' => 'День народного единства',
            ];
            foreach ($holidays as $day => $name) {
                self::setSeedDay($pdo, $day, self::TYPE_HOLIDAY, 0, $name, 'Нерабочий праздничный день по статье 112 ТК РФ.');
            }

            self::setSeedDay($pdo, '2026-01-03', self::TYPE_HOLIDAY, 0, 'Новогодние каникулы', 'Совпавший выходной перенесён на 09.01.2026 постановлением Правительства РФ № 1466.');
            self::setSeedDay($pdo, '2026-01-04', self::TYPE_HOLIDAY, 0, 'Новогодние каникулы', 'Совпавший выходной перенесён на 31.12.2026 постановлением Правительства РФ № 1466.');
            self::setSeedDay($pdo, '2026-01-09', self::TYPE_TRANSFERRED_DAY_OFF, 0, 'Перенесённый выходной', 'Перенос с субботы 03.01.2026 по постановлению Правительства РФ № 1466.');
            self::setSeedDay($pdo, '2026-03-09', self::TYPE_TRANSFERRED_DAY_OFF, 0, 'Выходной после 8 Марта', 'Перенос выходного при совпадении 08.03.2026 с воскресеньем.');
            self::setSeedDay($pdo, '2026-05-11', self::TYPE_TRANSFERRED_DAY_OFF, 0, 'Выходной после Дня Победы', 'Перенос выходного при совпадении 09.05.2026 с субботой.');
            self::setSeedDay($pdo, '2026-12-31', self::TYPE_TRANSFERRED_DAY_OFF, 0, 'Перенесённый выходной', 'Перенос с воскресенья 04.01.2026 по постановлению Правительства РФ № 1466.');

            if (!self::isYearComplete($pdo, 2026)) {
                throw new \RuntimeException('Не удалось сформировать полный календарь 2026.');
            }
            $working = (int)$pdo->query("SELECT COUNT(*) FROM production_calendar_days WHERE calendar_year=2026 AND is_working_day=1")->fetchColumn();
            $off = (int)$pdo->query("SELECT COUNT(*) FROM production_calendar_days WHERE calendar_year=2026 AND is_working_day=0")->fetchColumn();
            if ($working !== 247 || $off !== 118) {
                throw new \RuntimeException("Контроль календаря 2026 не пройден: working={$working}, off={$off}.");
            }
            $pdo->commit();
            return 'seeded';
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    private static function insertBaselineDays(PDO $pdo, int $year, int $userId, string $role): void
    {
        $start = new DateTimeImmutable(sprintf('%04d-01-01', $year));
        $end = new DateTimeImmutable(sprintf('%04d-01-01', $year + 1));
        $insert = $pdo->prepare(
            "INSERT INTO production_calendar_days
                (calendar_date,calendar_year,day_type,is_working_day,created_by_user_id,created_by_role,updated_by_user_id,updated_by_role)
             VALUES (?,?,?,?,?,?,?,?)"
        );
        foreach (new DatePeriod($start, new DateInterval('P1D'), $end) as $date) {
            $isWeekend = (int)$date->format('N') >= 6;
            $insert->execute([
                $date->format('Y-m-d'),
                $year,
                $isWeekend ? self::TYPE_WEEKEND : self::TYPE_WORKDAY,
                $isWeekend ? 0 : 1,
                $userId ?: null,
                $role,
                $userId ?: null,
                $role,
            ]);
        }
    }

    private static function setSeedDay(PDO $pdo, string $date, string $type, int $isWorking, string $name, string $note): void
    {
        $stmt = $pdo->prepare(
            "UPDATE production_calendar_days
                SET day_type=?,is_working_day=?,name=?,note=?,updated_by_role='system'
              WHERE calendar_date=?"
        );
        $stmt->execute([$type, $isWorking, $name, $note, $date]);
        if ($stmt->rowCount() !== 1) {
            throw new \RuntimeException('Не удалось заполнить производственный календарь: ' . $date);
        }
    }

    private static function daysInYear(int $year): int
    {
        return (int)(new DateTimeImmutable(sprintf('%04d-12-31', $year)))->format('z') + 1;
    }

    private static function assertYear(int $year): void
    {
        if ($year < 2000 || $year > 2100) {
            throw new \InvalidArgumentException('Год должен быть в диапазоне 2000–2100.');
        }
    }
}
