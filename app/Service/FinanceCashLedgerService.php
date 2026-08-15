<?php

namespace App\Service;

use PDO;

/**
 * Read-only projection for the cash journal.
 *
 * Finance transfers are stored as two linked finance_operations rows. The cash
 * journal exposes only the CASH side and describes the real counterpart. It
 * also marks unresolved incoming rows of the technical "Основная касса" so the
 * UI can use them as the work queue without mutating the source history.
 */
final class FinanceCashLedgerService
{
    public static function fetchRecentMovements(PDO $pdo, int $page = 1, int $perPage = 20): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(500, $perPage));
        $offset = ($page - 1) * $perPage;

        $countSql = "SELECT COUNT(*)
                       FROM finance_operations fo
                       JOIN finance_money_accounts cash_account
                         ON cash_account.id = fo.money_account_id
                        AND cash_account.type = 'CASH'";
        $countStmt = $pdo->query($countSql);
        $total = (int) $countStmt->fetchColumn();

        $sql = "SELECT fo.*,
                       cash_account.name AS account_name,
                       cash_account.type AS account_type,
                       counterpart.name AS counterpart_account_name,
                       counterpart.type AS counterpart_account_type,
                       employee.employee_name_snapshot AS employee_name,
                       employee.movement_type AS employee_movement_type,
                       resolution.id AS cash_resolution_id,
                       resolution.resolution_type AS cash_resolution_type,
                       resolution.target_name_snapshot AS cash_resolution_target
                  FROM finance_operations fo
                  JOIN finance_money_accounts cash_account
                    ON cash_account.id = fo.money_account_id
                   AND cash_account.type = 'CASH'
             LEFT JOIN finance_money_accounts counterpart
                    ON counterpart.id = fo.transfer_account_id
             LEFT JOIN (
                       SELECT finance_operation_id,
                              MAX(employee_name_snapshot) AS employee_name_snapshot,
                              MAX(movement_type) AS movement_type
                         FROM finance_employee_movements
                        GROUP BY finance_operation_id
                       ) employee
                    ON employee.finance_operation_id = fo.id
             LEFT JOIN finance_cash_resolutions resolution
                    ON resolution.source_finance_operation_id = fo.id
              ORDER BY fo.created_at DESC, fo.id DESC
                 LIMIT :limit OFFSET :offset";

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$row) {
            $row['cash_direction'] = self::cashDirection($row);
            $row['source_recipient_label'] = self::sourceRecipientLabel($row);
            $row['is_unresolved_cash_source'] = self::isUnresolvedTechnicalSource($row);
        }
        unset($row);

        return [
            'data' => $rows,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'pages' => (int) ceil($total / max(1, $perPage)),
        ];
    }

    public static function movementLabel(array $row): string
    {
        return self::cashDirection($row) === 'out' ? 'Списание' : 'Поступление';
    }

    private static function cashDirection(array $row): string
    {
        $operationType = strtoupper((string) ($row['operation_type'] ?? ''));
        if ($operationType === 'TRANSFER') {
            return strtolower((string) ($row['transfer_direction'] ?? '')) === 'out' ? 'out' : 'in';
        }
        return $operationType === 'EXPENSE' ? 'out' : 'in';
    }

    private static function isUnresolvedTechnicalSource(array $row): bool
    {
        if (($row['status'] ?? '') !== 'POSTED') return false;
        if (trim((string)($row['account_name'] ?? '')) !== FinanceCashResolutionService::MAIN_CASH_NAME) return false;
        if (!empty($row['cash_resolution_id'])) return false;
        return FinanceCashResolutionService::isIncomingSource($row);
    }

    private static function sourceRecipientLabel(array $row): string
    {
        $employeeName = trim((string) ($row['employee_name'] ?? ''));
        if ($employeeName !== '') {
            return 'Сотрудник: ' . $employeeName;
        }

        $counterpart = trim((string) ($row['counterpart_account_name'] ?? ''));
        if ($counterpart !== '') {
            return $counterpart;
        }

        return '—';
    }
}
