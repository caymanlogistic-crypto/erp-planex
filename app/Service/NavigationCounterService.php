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
     * The method deliberately performs one indexed tenant aggregate per request
     * and keeps the result in request-local memory. New counters should be added
     * to the same aggregate query instead of issuing one query per menu item.
     *
     * @return array{bank_attention:int}
     */
    public static function forCompany(array $config, Database $centralDb, int $companyId, ?array $knownCompany = null): array
    {
        if ($companyId <= 0) {
            return self::emptyCounters();
        }

        $cacheKey = (string)$companyId;
        if (isset(self::$requestCache[$cacheKey])) {
            return self::$requestCache[$cacheKey];
        }

        try {
            $company = $knownCompany;
            if (!is_array($company) || (int)($company['id'] ?? 0) !== $companyId || ($company['status'] ?? '') !== 'active') {
                $stmt = $centralDb->connection()->prepare("SELECT * FROM companies WHERE id = ? AND status = 'active' LIMIT 1");
                $stmt->execute([$companyId]);
                $company = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
            }

            if (!$company) {
                return self::$requestCache[$cacheKey] = self::emptyCounters();
            }

            $tenant = (new Database(companyDatabaseConfig($config, $company)))->connection();
            $stmt = $tenant->query(
                "SELECT COUNT(*)
                   FROM bank_transactions
                  WHERE classification_status IN ('UNALLOCATED','NEEDS_REVIEW')
                    AND COALESCE(is_internal_transfer, 0) = 0"
            );

            return self::$requestCache[$cacheKey] = [
                'bank_attention' => max(0, (int)$stmt->fetchColumn()),
            ];
        } catch (Throwable) {
            // Navigation must never make an otherwise healthy ERP page fail.
            return self::$requestCache[$cacheKey] = self::emptyCounters();
        }
    }

    /** @return array{bank_attention:int} */
    private static function emptyCounters(): array
    {
        return ['bank_attention' => 0];
    }
}
