<?php

namespace App\Service;

use PDO;

final class FinanceStructureDeletionService
{
    public static function deleteDdsCategory(PDO $pdo, int $id): void
    {
        if ($id <= 0) {
            throw new \InvalidArgumentException('Не указана статья ДДС.');
        }
        $check = $pdo->prepare('SELECT id,name FROM finance_dds_categories WHERE id=?');
        $check->execute([$id]);
        $row = $check->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new \InvalidArgumentException('Статья ДДС не найдена.');
        }

        $usage = self::usage($pdo, [
            ['bank_transactions', 'dds_category_id', 'банковских операциях'],
            ['finance_operations', 'dds_category_id', 'финансовых операциях'],
            ['finance_matching_rules', 'target_dds_category_id', 'правилах разнесения'],
        ], $id);
        if ($usage !== []) {
            throw new \InvalidArgumentException('Нельзя удалить статью «' . $row['name'] . '»: она используется в ' . implode(', ', $usage) . '. Сначала уберите эти привязки или архивируйте статью.');
        }

        $ownTx = !$pdo->inTransaction();
        if ($ownTx) $pdo->beginTransaction();
        try {
            $pdo->prepare('DELETE FROM finance_cash_flow_center_dds_categories WHERE dds_category_id=?')->execute([$id]);
            $pdo->prepare('UPDATE finance_dds_categories SET parent_id=NULL WHERE parent_id=?')->execute([$id]);
            $pdo->prepare('DELETE FROM finance_dds_categories WHERE id=?')->execute([$id]);
            if ($ownTx) $pdo->commit();
        } catch (\Throwable $e) {
            if ($ownTx && $pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    public static function deleteCashFlowCenter(PDO $pdo, int $id): void
    {
        if ($id <= 0) {
            throw new \InvalidArgumentException('Не указан ЦФУ.');
        }
        $check = $pdo->prepare('SELECT id,name FROM finance_cash_flow_centers WHERE id=?');
        $check->execute([$id]);
        $row = $check->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new \InvalidArgumentException('ЦФУ не найден.');
        }

        $usage = self::usage($pdo, [
            ['bank_transactions', 'cash_flow_center_id', 'банковских операциях'],
            ['finance_operations', 'cash_flow_center_id', 'финансовых операциях'],
            ['finance_matching_rules', 'target_cash_flow_center_id', 'правилах разнесения'],
        ], $id);
        if ($usage !== []) {
            throw new \InvalidArgumentException('Нельзя удалить ЦФУ «' . $row['name'] . '»: он используется в ' . implode(', ', $usage) . '. Сначала уберите эти привязки или архивируйте ЦФУ.');
        }

        $ownTx = !$pdo->inTransaction();
        if ($ownTx) $pdo->beginTransaction();
        try {
            $pdo->prepare('DELETE FROM finance_cash_flow_center_dds_categories WHERE cash_flow_center_id=?')->execute([$id]);
            $pdo->prepare('DELETE FROM finance_cash_flow_centers WHERE id=?')->execute([$id]);
            if ($ownTx) $pdo->commit();
        } catch (\Throwable $e) {
            if ($ownTx && $pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    private static function usage(PDO $pdo, array $checks, int $id): array
    {
        $used = [];
        foreach ($checks as [$table, $column, $label]) {
            if (!self::columnExists($pdo, $table, $column)) continue;
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM `{$table}` WHERE `{$column}`=?");
            $stmt->execute([$id]);
            $count = (int)$stmt->fetchColumn();
            if ($count > 0) $used[] = $label . ' (' . $count . ')';
        }
        return $used;
    }

    private static function columnExists(PDO $pdo, string $table, string $column): bool
    {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');
        $stmt->execute([$table, $column]);
        return (int)$stmt->fetchColumn() > 0;
    }
}
