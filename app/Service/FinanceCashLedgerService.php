<?php

namespace App\Service;

use PDO;

/**
 * Read-only projection for the cash journal.
 *
 * Finance transfers are stored as two linked finance_operations rows. The cash
 * journal exposes only the CASH side. Resolved technical-cash movements are
 * projected as one lifecycle row: the incoming source remains canonical and
 * the later handoff is attached to it instead of being rendered as a duplicate.
 */
final class FinanceCashLedgerService
{
    public static function fetchRecentMovements(PDO $pdo, int $page = 1, int $perPage = 20): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(500, $perPage));
        $offset = ($page - 1) * $perPage;
        $legacyResolutionSql = FinanceCashResolutionService::legacyResolutionProjectionSql();

        $countSql = "SELECT COUNT(*)
                       FROM finance_operations fo
                       JOIN finance_money_accounts cash_account
                         ON cash_account.id = fo.money_account_id
                        AND cash_account.type = 'CASH'
                  LEFT JOIN finance_cash_resolutions outflow_resolution
                         ON outflow_resolution.outflow_finance_operation_id = fo.id
                  LEFT JOIN ({$legacyResolutionSql}) legacy_outflow_resolution
                         ON legacy_outflow_resolution.outflow_finance_operation_id = fo.id
                      WHERE outflow_resolution.id IS NULL
                        AND legacy_outflow_resolution.outflow_finance_operation_id IS NULL";
        $countStmt = $pdo->query($countSql);
        $total = (int) $countStmt->fetchColumn();

        $sql = "SELECT fo.*,
                       cash_account.name AS account_name,
                       cash_account.type AS account_type,
                       counterpart.name AS counterpart_account_name,
                       counterpart.type AS counterpart_account_type,
                       employee.employee_name_snapshot AS employee_name,
                       employee.movement_type AS employee_movement_type,
                       CASE
                           WHEN source_resolution.id IS NOT NULL THEN source_resolution.id
                           WHEN legacy_source_resolution.source_finance_operation_id IS NOT NULL
                               THEN -legacy_source_resolution.source_finance_operation_id
                           ELSE NULL
                       END AS cash_resolution_id,
                       COALESCE(source_resolution.resolution_type, legacy_source_resolution.resolution_type) AS cash_resolution_type,
                       COALESCE(source_resolution.target_name_snapshot, legacy_source_resolution.target_name_snapshot) AS cash_resolution_target,
                       COALESCE(source_resolution.created_at, legacy_source_resolution.created_at) AS cash_resolution_created_at,
                       COALESCE(source_resolution.outflow_finance_operation_id, legacy_source_resolution.outflow_finance_operation_id) AS cash_resolution_outflow_id,
                       CASE
                           WHEN outflow_resolution.id IS NOT NULL THEN outflow_resolution.id
                           WHEN legacy_outflow_resolution.outflow_finance_operation_id IS NOT NULL
                               THEN -legacy_outflow_resolution.outflow_finance_operation_id
                           ELSE NULL
                       END AS cash_outflow_resolution_id
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
             LEFT JOIN finance_cash_resolutions source_resolution
                    ON source_resolution.source_finance_operation_id = fo.id
             LEFT JOIN finance_cash_resolutions outflow_resolution
                    ON outflow_resolution.outflow_finance_operation_id = fo.id
             LEFT JOIN ({$legacyResolutionSql}) legacy_source_resolution
                    ON legacy_source_resolution.source_finance_operation_id = fo.id
             LEFT JOIN ({$legacyResolutionSql}) legacy_outflow_resolution
                    ON legacy_outflow_resolution.outflow_finance_operation_id = fo.id
                 WHERE outflow_resolution.id IS NULL
                   AND legacy_outflow_resolution.outflow_finance_operation_id IS NULL
              ORDER BY fo.created_at DESC, fo.id DESC
                 LIMIT :limit OFFSET :offset";

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$row) {
            $row['cash_direction'] = self::cashDirection($row);
            $row['source_recipient_label'] = self::legacySourceRecipientLabel($row);
            $row['journal_date'] = (string)($row['operation_date'] ?? '');
            $row['source_label'] = self::sourceLabel($row);
            $row['handoff_recipient_label'] = self::handoffRecipientLabel($row);
            $row['handoff_date'] = self::handoffDate($row);
            $row['display_purpose'] = self::displayPurpose($row);
            $row['is_unresolved_cash_source'] = self::isUnresolvedTechnicalSource($row);
            $row['is_resolved_cash_lifecycle'] = self::isResolvedCashLifecycle($row);
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
        if (self::isResolvedCashLifecycle($row)) {
            return 'Получено → передано';
        }
        if (self::isUnresolvedTechnicalSource($row)) {
            return 'Получено';
        }
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

    private static function isResolvedCashLifecycle(array $row): bool
    {
        if (empty($row['cash_resolution_id'])) return false;
        if (($row['status'] ?? '') !== 'POSTED') return false;
        if (trim((string)($row['account_name'] ?? '')) !== FinanceCashResolutionService::MAIN_CASH_NAME) return false;
        return FinanceCashResolutionService::isIncomingSource($row);
    }

    private static function sourceLabel(array $row): string
    {
        $employeeName = trim((string)($row['employee_name'] ?? ''));
        $movementType = strtoupper((string)($row['employee_movement_type'] ?? ''));
        if ($employeeName !== '' && $movementType === 'RETURN') {
            return self::shortEmployeeName($employeeName);
        }

        return self::compactAccountName((string)($row['counterpart_account_name'] ?? ''));
    }

    private static function handoffRecipientLabel(array $row): string
    {
        if (self::isResolvedCashLifecycle($row)) {
            $target = trim((string)($row['cash_resolution_target'] ?? ''));
            if ($target !== '') {
                return strtoupper((string)($row['cash_resolution_type'] ?? '')) === FinanceCashResolutionService::RESOLUTION_EMPLOYEE
                    ? self::shortEmployeeName($target)
                    : self::compactAccountName($target);
            }
        }

        $employeeName = trim((string)($row['employee_name'] ?? ''));
        $movementType = strtoupper((string)($row['employee_movement_type'] ?? ''));
        if ($employeeName !== '' && $movementType === 'PAYMENT') {
            return self::shortEmployeeName($employeeName);
        }

        return '—';
    }

    private static function handoffDate(array $row): string
    {
        // The factual handoff timestamp stays in resolution history, but the
        // compact cash journal intentionally displays only the recipient.
        return '';
    }

    private static function displayPurpose(array $row): string
    {
        $purpose = trim((string)($row['purpose'] ?? ''));
        if ($purpose !== '') return $purpose;
        $comment = trim((string)($row['comment'] ?? ''));
        return $comment !== '' ? $comment : '—';
    }

    private static function shortEmployeeName(string $fullName): string
    {
        $parts = preg_split('/\s+/u', trim($fullName), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if ($parts === []) return '—';
        $surname = array_shift($parts);
        $initials = '';
        foreach (array_slice($parts, 0, 2) as $part) {
            $initials .= mb_strtoupper(mb_substr($part, 0, 1)) . '.';
        }
        return trim($surname . ($initials !== '' ? ' ' . $initials : ''));
    }

    private static function compactAccountName(string $name): string
    {
        $name = trim($name);
        if ($name === '') return '—';
        return trim((string)preg_replace('/^Расчётный счёт\s+/u', '', $name));
    }

    /**
     * Backward-compatible projection kept for callers outside the cash table.
     * New UI uses source_label + handoff_recipient_label instead.
     */
    private static function legacySourceRecipientLabel(array $row): string
    {
        $employeeName = trim((string) ($row['employee_name'] ?? ''));
        if ($employeeName !== '') {
            return 'Сотрудник: ' . self::shortEmployeeName($employeeName);
        }

        $counterpart = trim((string) ($row['counterpart_account_name'] ?? ''));
        if ($counterpart !== '') {
            return $counterpart;
        }

        return '—';
    }
}
