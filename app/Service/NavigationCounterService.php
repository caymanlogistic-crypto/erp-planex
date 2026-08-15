<?php

namespace App\Service;

use App\Core\Database;
use PDO;
use Throwable;

final class NavigationCounterService
{
    /** @var array<string,array<string,int>> */
    private static array $requestCache = [];

    /**
     * Return lightweight navigation counters for one tenant.
     *
     * Every counter is intentionally isolated. A missing/temporarily unavailable
     * schema object for one finance module must never suppress unrelated badges.
     *
     * @return array{bank_attention:int,cash_attention:int}
     */
    public static function forCompany(array $config, Database $centralDb, int $companyId, ?array $knownCompany = null): array
    {
        if ($companyId <= 0) return self::emptyCounters();

        $cacheKey = (string)$companyId;
        if (isset(self::$requestCache[$cacheKey])) return self::$requestCache[$cacheKey];

        try {
            $company = $knownCompany;
            if (!is_array($company) || (int)($company['id'] ?? 0) !== $companyId || ($company['status'] ?? '') !== 'active') {
                $stmt = $centralDb->connection()->prepare("SELECT * FROM companies WHERE id = ? AND status = 'active' LIMIT 1");
                $stmt->execute([$companyId]);
                $company = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
            }
            if (!$company) return self::$requestCache[$cacheKey] = self::emptyCounters();

            $tenant = (new Database(companyDatabaseConfig($config, $company)))->connection();
        } catch (Throwable) {
            // Tenant resolution itself failed, so no finance counter can be trusted.
            return self::$requestCache[$cacheKey] = self::emptyCounters();
        }

        $counters = self::emptyCounters();

        try {
            $counters['bank_attention'] = max(0, (int)$tenant->query(
                "SELECT COUNT(*)
                   FROM bank_transactions
                  WHERE classification_status IN ('UNALLOCATED','NEEDS_REVIEW')
                    AND COALESCE(is_internal_transfer, 0) = 0"
            )->fetchColumn());
        } catch (Throwable) {
            // Keep the bank badge at zero only; other counters remain independent.
            $counters['bank_attention'] = 0;
        }

        try {
            $counters['cash_attention'] = max(0, (int)$tenant->query(
                "SELECT COUNT(*)
                   FROM finance_operations fo
                   JOIN finance_money_accounts fma
                     ON fma.id = fo.money_account_id
                    AND fma.type = 'CASH'
                    AND fma.is_active = 1
                    AND fma.name = 'Основная касса'
              LEFT JOIN finance_cash_resolutions fcr
                     ON fcr.source_finance_operation_id = fo.id
                  WHERE fo.status = 'POSTED'
                    AND (fo.operation_type = 'INCOME'
                         OR (fo.operation_type = 'TRANSFER' AND fo.transfer_direction = 'in'))
                    AND fcr.id IS NULL"
            )->fetchColumn());
        } catch (Throwable) {
            // A cash-schema rollout issue must not hide the bank attention badge.
            $counters['cash_attention'] = 0;
        }

        return self::$requestCache[$cacheKey] = $counters;
    }

    /** @return array{bank_attention:int,cash_attention:int} */
    private static function emptyCounters(): array
    {
        return ['bank_attention' => 0, 'cash_attention' => 0];
    }
}
