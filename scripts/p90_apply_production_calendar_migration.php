<?php

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only.\n");
    exit(1);
}

require_once __DIR__ . '/../app/Support/helpers.php';
require_once __DIR__ . '/../app/Support/environment.php';
loadEnvFileNonOverwriting(dirname(__DIR__) . '/.env');
$config = require __DIR__ . '/../bootstrap/app.php';
require_once base_path('app/Support/entrypoint_dependencies.php');

use App\Core\Database;
use App\Service\ProductionCalendarService;

$migrationName = '070_production_calendar.sql';
$migrationPath = base_path('database/migrations-local/' . $migrationName);
$sql = file_get_contents($migrationPath);
if ($sql === false || trim($sql) === '') {
    throw new RuntimeException('P90 migration SQL is missing or empty.');
}
$checksum = hash('sha256', $sql);

$db = new Database($config['database']);
$central = $db->connection();
$companies = $central->query("SELECT * FROM companies WHERE status='active' ORDER BY id")->fetchAll(\PDO::FETCH_ASSOC);
$count = 0;
$appliedCount = 0;
$existingCount = 0;
$seededCount = 0;

foreach ($companies as $company) {
    $companyId = (int)($company['id'] ?? 0);
    if ($companyId <= 0) {
        throw new RuntimeException('P90 encountered an active company without a valid id.');
    }

    $local = (new Database(companyDatabaseConfig($config, $company)))->connection();
    $databaseName = (string)$local->query('SELECT DATABASE()')->fetchColumn();
    if ($databaseName === '') {
        throw new RuntimeException('P90 cannot resolve tenant database for company ' . $companyId);
    }

    $local->exec("CREATE TABLE IF NOT EXISTS `schema_migrations` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `migration` VARCHAR(255) NOT NULL,
        `checksum` VARCHAR(64) NOT NULL,
        `executed_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uk_local_migration` (`migration`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $lockName = 'planex_p90_' . substr(hash('sha256', $databaseName), 0, 32);
    $lock = $local->prepare('SELECT GET_LOCK(?,10)');
    $lock->execute([$lockName]);
    if ((int)$lock->fetchColumn() !== 1) {
        throw new RuntimeException('Cannot acquire P90 migration lock for company ' . $companyId);
    }

    try {
        $stmt = $local->prepare('SELECT checksum FROM schema_migrations WHERE migration=? LIMIT 1');
        $stmt->execute([$migrationName]);
        $stored = $stmt->fetchColumn();

        if ($stored !== false) {
            if (!hash_equals((string)$stored, $checksum)) {
                throw new RuntimeException('P90 migration checksum mismatch in company ' . $companyId);
            }
            $existingCount++;
            $migrationState = 'existing';
        } else {
            $local->exec($sql);
            $insert = $local->prepare('INSERT INTO schema_migrations (migration,checksum) VALUES (?,?)');
            $insert->execute([$migrationName, $checksum]);
            $appliedCount++;
            $migrationState = 'applied';
        }

        foreach (['production_calendar_years', 'production_calendar_days'] as $requiredTable) {
            $tableCheck = $local->prepare(
                "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?"
            );
            $tableCheck->execute([$requiredTable]);
            if ((int)$tableCheck->fetchColumn() !== 1) {
                throw new RuntimeException('P90 required table ' . $requiredTable . ' is missing for company ' . $companyId);
            }
        }

        $year2026 = ProductionCalendarService::fetchYear($local, 2026);
        if (!$year2026) {
            $seedState = ProductionCalendarService::seedOfficial2026($local);
            if ($seedState === 'seeded') {
                $seededCount++;
            }
        } elseif (ProductionCalendarService::isYearReady($local, 2026)) {
            $seedState = 'existing';
        } else {
            // Never overwrite a calendar that a user has started editing manually.
            $seedState = 'manual-draft';
        }

        if ($seedState !== 'manual-draft') {
            if (!ProductionCalendarService::isYearReady($local, 2026)) {
                throw new RuntimeException('P90 official 2026 calendar is not READY for company ' . $companyId);
            }
            $check = $local->query(
                "SELECT COUNT(*) AS total,
                        SUM(CASE WHEN is_working_day=1 THEN 1 ELSE 0 END) AS working,
                        SUM(CASE WHEN is_working_day=0 THEN 1 ELSE 0 END) AS non_working
                   FROM production_calendar_days WHERE calendar_year=2026"
            )->fetch(\PDO::FETCH_ASSOC);
            if ((int)$check['total'] !== 365 || (int)$check['working'] !== 247 || (int)$check['non_working'] !== 118) {
                throw new RuntimeException('P90 2026 calendar control totals failed for company ' . $companyId);
            }
            foreach (['2026-01-09', '2026-03-09', '2026-05-11', '2026-12-31'] as $offDate) {
                $dayStmt = $local->prepare('SELECT is_working_day FROM production_calendar_days WHERE calendar_date=?');
                $dayStmt->execute([$offDate]);
                if ((int)$dayStmt->fetchColumn() !== 0) {
                    throw new RuntimeException('P90 expected non-working date is wrong: ' . $offDate);
                }
            }
        }

        printf(
            "P90_COMPANY_OK id=%d db=%s migration=%s seed=%s\n",
            $companyId,
            $databaseName,
            $migrationState,
            $seedState
        );
    } finally {
        $release = $local->prepare('SELECT RELEASE_LOCK(?)');
        $release->execute([$lockName]);
    }
    $count++;
}

printf(
    "P90_PRODUCTION_CALENDAR_OK companies=%d applied=%d existing=%d seeded=%d checksum=%s\n",
    $count,
    $appliedCount,
    $existingCount,
    $seededCount,
    $checksum
);
