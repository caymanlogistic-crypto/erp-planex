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
use App\Service\FinanceObligationService;

$migrationName = '071_finance_obligations.sql';
$migrationPath = base_path('database/migrations-local/' . $migrationName);
$sql = file_get_contents($migrationPath);
if ($sql === false || trim($sql) === '') {
    throw new RuntimeException('P91 migration SQL is missing or empty.');
}
$checksum = hash('sha256', $sql);

$central = (new Database($config['database']))->connection();
$companies = $central->query("SELECT * FROM companies WHERE status='active' ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
$count = 0;
$appliedCount = 0;
$existingCount = 0;
$totalRoutes = 0;
$totalObligations = 0;

foreach ($companies as $company) {
    $companyId = (int)($company['id'] ?? 0);
    if ($companyId <= 0) {
        throw new RuntimeException('P91 encountered an active company without a valid id.');
    }
    $local = (new Database(companyDatabaseConfig($config, $company)))->connection();
    $databaseName = (string)$local->query('SELECT DATABASE()')->fetchColumn();
    if ($databaseName === '') {
        throw new RuntimeException('P91 cannot resolve tenant database for company ' . $companyId);
    }

    $local->exec("CREATE TABLE IF NOT EXISTS `schema_migrations` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `migration` VARCHAR(255) NOT NULL,
        `checksum` VARCHAR(64) NOT NULL,
        `executed_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uk_local_migration` (`migration`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $lockName = 'planex_p91_' . substr(hash('sha256', $databaseName), 0, 32);
    $lock = $local->prepare('SELECT GET_LOCK(?,10)');
    $lock->execute([$lockName]);
    if ((int)$lock->fetchColumn() !== 1) {
        throw new RuntimeException('Cannot acquire P91 migration lock for company ' . $companyId);
    }

    try {
        $stmt = $local->prepare('SELECT checksum FROM schema_migrations WHERE migration=? LIMIT 1');
        $stmt->execute([$migrationName]);
        $stored = $stmt->fetchColumn();
        if ($stored !== false) {
            if (!hash_equals((string)$stored, $checksum)) {
                throw new RuntimeException('P91 migration checksum mismatch in company ' . $companyId);
            }
            $existingCount++;
            $migrationState = 'existing';
        } else {
            $local->exec($sql);
            $local->prepare('INSERT INTO schema_migrations (migration,checksum) VALUES (?,?)')->execute([$migrationName, $checksum]);
            $appliedCount++;
            $migrationState = 'applied';
        }

        if (!FinanceObligationService::schemaReady($local)) {
            throw new RuntimeException('P91 obligation schema is incomplete for company ' . $companyId);
        }
        $sync = FinanceObligationService::syncAllLinearRoutes($local);
        $totalRoutes += (int)($sync['routes'] ?? 0);
        $totalObligations += (int)($sync['obligations'] ?? 0);

        $orphanInvoiceLinks = (int)$local->query(
            "SELECT COUNT(*) FROM finance_invoice_links l
              WHERE l.obligation_id IS NOT NULL
                AND NOT EXISTS (SELECT 1 FROM finance_obligations o WHERE o.id=l.obligation_id)"
        )->fetchColumn();
        $orphanAllocations = (int)$local->query(
            "SELECT COUNT(*) FROM finance_operation_allocations a
              WHERE a.obligation_id IS NOT NULL
                AND NOT EXISTS (SELECT 1 FROM finance_obligations o WHERE o.id=a.obligation_id)"
        )->fetchColumn();
        if ($orphanInvoiceLinks !== 0 || $orphanAllocations !== 0) {
            throw new RuntimeException('P91 found orphan obligation references for company ' . $companyId);
        }

        $activeObligations = (int)$local->query("SELECT COUNT(*) FROM finance_obligations WHERE cancelled_at IS NULL")->fetchColumn();
        printf(
            "P91_COMPANY_OK id=%d db=%s migration=%s routes=%d synced=%d active=%d orphan_invoice_links=%d orphan_allocations=%d\n",
            $companyId,
            $databaseName,
            $migrationState,
            (int)($sync['routes'] ?? 0),
            (int)($sync['obligations'] ?? 0),
            $activeObligations,
            $orphanInvoiceLinks,
            $orphanAllocations
        );
    } finally {
        $local->prepare('SELECT RELEASE_LOCK(?)')->execute([$lockName]);
    }
    $count++;
}

printf(
    "P91_FINANCE_OBLIGATIONS_OK companies=%d applied=%d existing=%d routes=%d obligations=%d checksum=%s\n",
    $count,
    $appliedCount,
    $existingCount,
    $totalRoutes,
    $totalObligations,
    $checksum
);
