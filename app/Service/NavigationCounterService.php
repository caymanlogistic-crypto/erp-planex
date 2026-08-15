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
     * One aggregate statement covers all finance attention badges.
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
            $stmt = $tenant->query(
                "SELECT
                    (SELECT COUNT(*)
                       FROM bank_transactions
                      WHERE classification_status IN ('UNALLOCATED','NEEDS_REVIEW')
                        AND COALESCE(is_internal_transfer, 0) = 0) AS bank_attention,
                    (SELECT COUNT(*)
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
                        AND fcr.id IS NULL) AS cash_attention"
            );
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

            return self::$requestCache[$cacheKey] = [
                'bank_attention' => max(0, (int)($row['bank_attention'] ?? 0)),
                'cash_attention' => max(0, (int)($row['cash_attention'] ?? 0)),
            ];
        } catch (Throwable) {
            // Navigation must never make an otherwise healthy ERP page fail.
            return self::$requestCache[$cacheKey] = self::emptyCounters();
        }
    }

    /** @return array{bank_attention:int,cash_attention:int} */
    private static function emptyCounters(): array
    {
        return ['bank_attention' => 0, 'cash_attention' => 0];
    }
}