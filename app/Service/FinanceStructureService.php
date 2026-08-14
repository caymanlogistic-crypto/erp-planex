<?php

namespace App\Service;

use PDO;

final class FinanceStructureService
{
    public static function tableExists(PDO $pdo): bool
    {
        try {
            $stmt = $pdo->query("SHOW TABLES LIKE 'finance_cash_flow_center_dds_categories'");
            return (bool)$stmt->fetchColumn();
        } catch (\Throwable) {
            return false;
        }
    }

    public static function fetchStructure(PDO $pdo, bool $activeOnly = false): array
    {
        $centers = FinanceMatchingRuleService::fetchCashFlowCenters($pdo, $activeOnly);
        $links = [];
        if (self::tableExists($pdo)) {
            $sql = "SELECT l.cash_flow_center_id,l.dds_category_id,l.sort_order,l.is_active,
                           d.name,d.direction,d.is_active AS dds_is_active,d.is_system
                      FROM finance_cash_flow_center_dds_categories l
                      JOIN finance_dds_categories d ON d.id=l.dds_category_id
                     ORDER BY l.cash_flow_center_id,l.sort_order,d.sort_order,d.name,d.id";
            foreach ($pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $links[(int)$row['cash_flow_center_id']][] = $row;
            }
        }
        foreach ($centers as &$center) {
            $center['articles'] = $links[(int)$center['id']] ?? [];
        }
        unset($center);
        return $centers;
    }

    public static function fetchAllowedDds(PDO $pdo, int $centerId, string $operationType): array
    {
        if ($centerId <= 0 || !self::tableExists($pdo)) {
            return $centerId > 0 ? FinanceDdsCategoryService::fetchActiveForDirection($pdo, $operationType) : [];
        }
        $direction = match ($operationType) {
            'INCOME' => 'INCOME',
            'EXPENSE' => 'EXPENSE',
            default => throw new \InvalidArgumentException('Недопустимое направление операции.'),
        };
        $stmt = $pdo->prepare(
            "SELECT d.*
               FROM finance_cash_flow_center_dds_categories l
               JOIN finance_dds_categories d ON d.id=l.dds_category_id
              WHERE l.cash_flow_center_id=:cfu
                AND l.is_active=1
                AND d.is_active=1
                AND (d.direction=:direction OR d.direction='BOTH')
              ORDER BY l.sort_order ASC,d.sort_order ASC,d.name ASC,d.id ASC"
        );
        $stmt->execute([':cfu' => $centerId, ':direction' => $direction]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function fetchAllowedMap(PDO $pdo, string $operationType): array
    {
        $map = [];
        foreach (FinanceMatchingRuleService::fetchCashFlowCenters($pdo, true) as $center) {
            $id = (int)$center['id'];
            $map[$id] = array_map(static fn(array $row): int => (int)$row['id'], self::fetchAllowedDds($pdo, $id, $operationType));
        }
        return $map;
    }

    public static function assertAllowedPair(PDO $pdo, int $centerId, int $ddsId, string $operationType): void
    {
        $allowed = self::fetchAllowedDds($pdo, $centerId, $operationType);
        foreach ($allowed as $row) {
            if ((int)$row['id'] === $ddsId) return;
        }
        throw new \InvalidArgumentException('Выбранная статья ДДС не относится к указанному ЦФУ. Настройте связь в «Финансовой структуре».');
    }

    public static function link(PDO $pdo, int $centerId, int $ddsId, bool $active = true, int $sortOrder = 100): void
    {
        self::assertCenterExists($pdo, $centerId);
        if (!FinanceDdsCategoryService::findCategory($pdo, $ddsId)) {
            throw new \InvalidArgumentException('Статья ДДС не найдена.');
        }
        $stmt = $pdo->prepare(
            "INSERT INTO finance_cash_flow_center_dds_categories
                (cash_flow_center_id,dds_category_id,sort_order,is_active)
             VALUES (?,?,?,?)
             ON DUPLICATE KEY UPDATE sort_order=VALUES(sort_order),is_active=VALUES(is_active),updated_at=NOW()"
        );
        $stmt->execute([$centerId, $ddsId, $sortOrder, $active ? 1 : 0]);
    }

    public static function saveLinks(PDO $pdo, int $centerId, array $ddsIds): void
    {
        self::assertCenterExists($pdo, $centerId);
        $ids = array_values(array_unique(array_filter(array_map('intval', $ddsIds), static fn(int $v): bool => $v > 0)));
        $pdo->beginTransaction();
        try {
            $pdo->prepare('UPDATE finance_cash_flow_center_dds_categories SET is_active=0,updated_at=NOW() WHERE cash_flow_center_id=?')->execute([$centerId]);
            foreach ($ids as $id) self::link($pdo, $centerId, $id, true, 100);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    private static function assertCenterExists(PDO $pdo, int $centerId): void
    {
        $stmt = $pdo->prepare('SELECT id FROM finance_cash_flow_centers WHERE id=?');
        $stmt->execute([$centerId]);
        if (!$stmt->fetchColumn()) throw new \InvalidArgumentException('ЦФУ не найден.');
    }
}
