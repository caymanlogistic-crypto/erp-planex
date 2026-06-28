<?php

namespace App\Service;

use PDO;

final class SuperadminCompanyService
{
    public static function loadCompany(PDO $pdo, int $companyId): ?array
    {
        if ($companyId <= 0) {
            return null;
        }

        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ? LIMIT 1');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        return $company ?: null;
    }

    public static function loadCompanyOwner(PDO $pdo, int $companyId): ?array
    {
        if ($companyId <= 0) {
            return null;
        }

        $stmt = $pdo->prepare(
            "SELECT * FROM company_users
             WHERE company_id = ?
               AND role = 'company_owner'
             LIMIT 1"
        );
        $stmt->execute([$companyId]);
        $owner = $stmt->fetch(PDO::FETCH_ASSOC);

        return $owner ?: null;
    }
}
