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
                       source_resolution.id AS cash_resolution_id,
                       source_resolution.resolution_type AS cash_resolution_type,
                       source_resolution.target_name_snapshot AS cash_resolution_target,
                       outflow_resolution.id AS cash_outflow_resolution_id,
                       outflow_resolution.resolution_type AS cash_outflow_resolution_type,
                       outflow_resolution.target_name_snapshot AS cash_handoff_target,
                       outflow_resolution.created_at AS cash_handoff_created_at,
                       resolution_source.operation_date AS cash_handoff_source_date,
                       resolution_source.purpose AS cash_handoff_source_purpose,
                       resolution_source_counterpart.name AS cash_handoff_source_account
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
             LEFT JOIN finance_operations resolution_source
                    ON resolution_source.id = outflow_resolution.source_finance_operation_id
             LEFT JOIN finance_money_accounts resolution_source_counterpart
                    ON resolution_source_counterpart.id = resolution_source.transfer_account_id
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
            $row['journal_date'] = self::journalDate($row);
            $row['source_label'] = self::sourceLabel($row);
            $row['handoff_recipient_label'] = self::handoffRecipientLabel($row);
            $row['display_purpose'] = self::displayPurpose($row);
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
        if (self::isEmployeeHandoffOutflow($row)) {
            return 'Передача сотруднику';
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

    private static function isEmployeeHandoffOutflow(array $row): bool
    {
        return !empty($row['cash_outflow_resolution_id'])
            && strtoupper((string)($row['cash_outflow_resolution_type'] ?? '')) === FinanceCashResolutionService::RESOLUTION_EMPLOYEE;
    }

    private static function journalDate(array $row): string
    {
        if (self::isEmployeeHandoffOutflow($row)) {
            $createdAt = trim((string)($row['cash_handoff_created_at'] ?? ''));
            if ($createdAt !== '') {
                return substr($createdAt, 0, 10);
            }
        }
        return (string)($row['operation_date'] ?? '');
    }

    private static function sourceLabel(array $row): string
    {
        if (self::isEmployeeHandoffOutflow($row)) {
            $sourceAccount = trim((string)($row['cash_handoff_source_account'] ?? ''));
            return $sourceAccount !== '' ? $sourceAccount : '—';
        }

        $employeeName = trim((string)($row['employee_name'] ?? ''));
        $movementType = strtoupper((string)($row['employee_movement_type'] ?? ''));
        if ($employeeName !== '' && $movementType === 'RETURN') {
            return $employeeName;
        }

        $counterpart = trim((string)($row['counterpart_account_name'] ?? ''));
        return $counterpart !== '' ? $counterpart : '—';
    }

    private static function handoffRecipientLabel(array $row): string
    {
        if (self::isEmployeeHandoffOutflow($row)) {
            $target = trim((string)($row['cash_handoff_target'] ?? ''));
            if ($target !== '') return $target;
        }

        $employeeName = trim((string)($row['employee_name'] ?? ''));
        $movementType = strtoupper((string)($row['employee_movement_type'] ?? ''));
        if ($employeeName !== '' && $movementType === 'PAYMENT') {
            return $employeeName;
        }

        return '—';
    }

    private static function displayPurpose(array $row): string
    {
        if (self::isEmployeeHandoffOutflow($row)) {
            $sourcePurpose = trim((string)($row['cash_handoff_source_purpose'] ?? ''));
            return $sourcePurpose !== '' ? $sourcePurpose : '—';
        }

        $purpose = trim((string)($row['purpose'] ?? ''));
        if ($purpose !== '') return $purpose;
        $comment = trim((string)($row['comment'] ?? ''));
        return $comment !== '' ? $comment : '—';
    }

    /**
     * Backward-compatible projection kept for callers outside the cash table.
     * New UI uses source_label + handoff_recipient_label instead.
     */
    private static function legacySourceRecipientLabel(array $row): string
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
