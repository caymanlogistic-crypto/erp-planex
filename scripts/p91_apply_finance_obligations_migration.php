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

$migrationNames = ['071_finance_obligations.sql', '072_invoice_obligation_cleanup.sql'];
$migrations = [];
foreach ($migrationNames as $migrationName) {
    $migrationPath = base_path('database/migrations-local/' . $migrationName);
    $sql = file_get_contents($migrationPath);
    if ($sql === false || trim($sql) === '') {
        throw new RuntimeException('P91 migration SQL is missing or empty: ' . $migrationName);
    }
    $migrations[$migrationName] = ['sql' => $sql, 'checksum' => hash('sha256', $sql)];
}

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
        $states = [];
        foreach ($migrations as $migrationName => $migration) {
            $stmt = $local->prepare('SELECT checksum FROM schema_migrations WHERE migration=? LIMIT 1');
            $stmt->execute([$migrationName]);
            $stored = $stmt->fetchColumn();
            if ($stored !== false) {
                if (!hash_equals((string)$stored, (string)$migration['checksum'])) {
                    throw new RuntimeException('P91 migration checksum mismatch in company ' . $companyId . ': ' . $migrationName);
                }
                $existingCount++;
                $states[] = $migrationName . '=existing';
            } else {
                $local->exec((string)$migration['sql']);
                $local->prepare('INSERT INTO schema_migrations (migration,checksum) VALUES (?,?)')
                    ->execute([$migrationName, $migration['checksum']]);
                $appliedCount++;
                $states[] = $migrationName . '=applied';
            }
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
        $unresolvedInvoiceLinks = (int)$local->query(
            "SELECT COUNT(*) FROM finance_invoice_links l
               JOIN finance_invoices i ON i.id=l.invoice_id
              WHERE l.linear_route_payment_id IS NOT NULL
                AND l.obligation_id IS NULL
                AND i.status<>'cancelled'"
        )->fetchColumn();
        $unresolvedAllocations = (int)$local->query(
            "SELECT COUNT(*) FROM finance_operation_allocations a
               JOIN finance_operations op ON op.id=a.operation_id
              WHERE a.linear_route_payment_id IS NOT NULL
                AND a.obligation_id IS NULL
                AND a.cancelled_at IS NULL
                AND op.status='POSTED'"
        )->fetchColumn();
        $legacyDrafts = (int)$local->query("SELECT COUNT(*) FROM finance_invoices WHERE status='draft' AND cancelled_at IS NULL")->fetchColumn();
        $legacyPlannedDates = (int)$local->query("SELECT COUNT(*) FROM finance_invoices WHERE planned_payment_date IS NOT NULL")->fetchColumn();

        if ($orphanInvoiceLinks !== 0 || $orphanAllocations !== 0 || $unresolvedInvoiceLinks !== 0 || $unresolvedAllocations !== 0
            || $legacyDrafts !== 0 || $legacyPlannedDates !== 0) {
            throw new RuntimeException(sprintf(
                'P91 integrity failure company=%d orphan_invoice=%d orphan_alloc=%d unresolved_invoice=%d unresolved_alloc=%d draft=%d planned_dates=%d',
                $companyId,
                $orphanInvoiceLinks,
                $orphanAllocations,
                $unresolvedInvoiceLinks,
                $unresolvedAllocations,
                $legacyDrafts,
                $legacyPlannedDates
            ));
        }

        $activeObligations = (int)$local->query("SELECT COUNT(*) FROM finance_obligations WHERE cancelled_at IS NULL")->fetchColumn();
        printf(
            "P91_COMPANY_OK id=%d db=%s migrations=%s routes=%d synced=%d active=%d orphan_invoice_links=%d orphan_allocations=%d unresolved_invoice_links=%d unresolved_allocations=%d draft=%d planned_dates=%d\n",
            $companyId,
            $databaseName,
            implode(',', $states),
            (int)($sync['routes'] ?? 0),
            (int)($sync['obligations'] ?? 0),
            $activeObligations,
            $orphanInvoiceLinks,
            $orphanAllocations,
            $unresolvedInvoiceLinks,
            $unresolvedAllocations,
            $legacyDrafts,
            $legacyPlannedDates
        );
    } finally {
        $local->prepare('SELECT RELEASE_LOCK(?)')->execute([$lockName]);
    }
    $count++;
}

printf(
    "P91_FINANCE_OBLIGATIONS_OK companies=%d applied=%d existing=%d routes=%d obligations=%d migrations=%s\n",
    $count,
    $appliedCount,
    $existingCount,
    $totalRoutes,
    $totalObligations,
    implode(',', array_keys($migrations))
);