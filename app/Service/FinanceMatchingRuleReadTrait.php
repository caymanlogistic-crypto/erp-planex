<?php

namespace App\Service;

use PDO;

trait FinanceMatchingRuleReadTrait
{
    public static function fetchRules(PDO $pdo, bool $activeOnly = false): array
    {
        $where = 'WHERE deleted_at IS NULL';
        if ($activeOnly) $where .= ' AND active = 1';
        $stmt = $pdo->query("SELECT * FROM finance_matching_rules {$where} ORDER BY priority DESC, id ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function findRule(PDO $pdo, int $id): ?array
    {
        $stmt = $pdo->prepare('SELECT * FROM finance_matching_rules WHERE id = :id AND deleted_at IS NULL');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}
