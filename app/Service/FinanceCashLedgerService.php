<?php

namespace App\Service;

use PDO;

/**
 * Read-only projection for the cash journal.
 *
 * Finance transfers are stored as linked finance_operations rows. The cash
 * journal exposes business lifecycles rather than technical double entries:
 * - cash resolutions keep the canonical incoming source and hide the technical outflow;
 * - employee invoice payments keep the employee TRANSFER IN row and attach the
 *   carrier EXPENSE to it, so one real-world payment is rendered as one row;
 * - employee-funded personal expenses keep the employee TRANSFER IN row and
 *   attach the economic CASH EXPENSE to it, again rendering one business row.
 *
 * Tenant migrations may lag behind production code because GET requests and the
 * controlled deploy do not mutate tenant schemas. Optional event projections are
 * therefore enabled only when their required tables/columns exist.
 */
final class FinanceCashLedgerService
{
    /** @var array<int,bool> */
    private static array $employeePersonalExpenseEventCache = [];

    public static function fetchRecentMovements(PDO $pdo, int $page = 1, int $perPage = 20): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(500, $perPage));
        $offset = ($page - 1) * $perPage;
        $legacyResolutionSql = FinanceCashResolutionService::legacyResolutionProjectionSql();
        $hasEmployeeInvoiceEvents = FinanceCashResolutionService::hasEmployeeInvoicePaymentEvents($pdo);
        $hasEmployeePersonalExpenseEvents = self::hasEmployeePersonalExpenseEvents($pdo);

        $employeeInvoiceCountJoin = $hasEmployeeInvoiceEvents
            ? "\n                  LEFT JOIN finance_employee_invoice_payments employee_invoice_expense\n                         ON employee_invoice_expense.expense_finance_operation_id = fo.id\n                        AND employee_invoice_expense.status = 'POSTED'"
            : '';
        $employeeInvoiceCountWhere = $hasEmployeeInvoiceEvents
            ? "\n                        AND employee_invoice_expense.id IS NULL"
            : '';

        $employeePersonalCountJoin = $hasEmployeePersonalExpenseEvents
            ? "\n                  LEFT JOIN finance_employee_personal_expenses employee_personal_expense\n                         ON employee_personal_expense.expense_finance_operation_id = fo.id\n                        AND employee_personal_expense.status = 'POSTED'\n                        AND employee_personal_expense.event_group_id IS NOT NULL"
            : '';
        $employeePersonalCountWhere = $hasEmployeePersonalExpenseEvents
            ? "\n                        AND employee_personal_expense.id IS NULL"
            : '';

        $countSql = "SELECT COUNT(*)
                       FROM finance_operations fo
                       JOIN finance_money_accounts cash_account
                         ON cash_account.id = fo.money_account_id
                        AND cash_account.type = 'CASH'
                  LEFT JOIN finance_cash_resolutions outflow_resolution
                         ON outflow_resolution.outflow_finance_operation_id = fo.id
                  LEFT JOIN ({$legacyResolutionSql}) legacy_outflow_resolution
                         ON legacy_outflow_resolution.outflow_finance_operation_id = fo.id{$employeeInvoiceCountJoin}{$employeePersonalCountJoin}
                      WHERE outflow_resolution.id IS NULL
                        AND legacy_outflow_resolution.outflow_finance_operation_id IS NULL{$employeeInvoiceCountWhere}{$employeePersonalCountWhere}";
        $countStmt = $pdo->query($countSql);
        $total = (int) $countStmt->fetchColumn();

        $employeeInvoiceSelect = $hasEmployeeInvoiceEvents
            ? ",\n                       employee_invoice_receipt.id AS employee_invoice_payment_id,\n                       employee_invoice_receipt.invoice_id AS employee_invoice_id,\n                       employee_invoice_receipt.invoice_number_snapshot AS employee_invoice_number,\n                       employee_invoice_receipt.counterparty_name_snapshot AS employee_invoice_counterparty,\n                       employee_invoice_receipt.expense_finance_operation_id AS employee_invoice_expense_operation_id,\n                       employee_invoice_expense_op.operation_date AS employee_invoice_expense_date,\n                       employee_invoice_expense_op.amount AS employee_invoice_expense_amount,\n                       employee_invoice_expense_op.purpose AS employee_invoice_expense_purpose,\n                       employee_invoice_expense_op.comment AS employee_invoice_expense_comment"
            : ",\n                       NULL AS employee_invoice_payment_id,\n                       NULL AS employee_invoice_id,\n                       NULL AS employee_invoice_number,\n                       NULL AS employee_invoice_counterparty,\n                       NULL AS employee_invoice_expense_operation_id,\n                       NULL AS employee_invoice_expense_date,\n                       NULL AS employee_invoice_expense_amount,\n                       NULL AS employee_invoice_expense_purpose,\n                       NULL AS employee_invoice_expense_comment";
        $employeeInvoiceJoins = $hasEmployeeInvoiceEvents
            ? "\n             LEFT JOIN finance_employee_invoice_payments employee_invoice_receipt\n                    ON employee_invoice_receipt.receipt_finance_operation_id = fo.id\n                   AND employee_invoice_receipt.status = 'POSTED'\n             LEFT JOIN finance_operations employee_invoice_expense_op\n                    ON employee_invoice_expense_op.id = employee_invoice_receipt.expense_finance_operation_id\n                   AND employee_invoice_expense_op.status = 'POSTED'\n             LEFT JOIN finance_employee_invoice_payments employee_invoice_expense\n                    ON employee_invoice_expense.expense_finance_operation_id = fo.id\n                   AND employee_invoice_expense.status = 'POSTED'"
            : '';
        $employeeInvoiceWhere = $hasEmployeeInvoiceEvents
            ? "\n                   AND employee_invoice_expense.id IS NULL"
            : '';

        $employeePersonalSelect = $hasEmployeePersonalExpenseEvents
            ? ",\n                       employee_personal_receipt.id AS employee_personal_expense_id,\n                       employee_personal_receipt.counterparty_name AS employee_personal_counterparty,\n                       employee_personal_receipt.purpose AS employee_personal_purpose,\n                       employee_personal_receipt.comment AS employee_personal_comment,\n                       employee_personal_receipt.cash_flow_center_name_snapshot AS employee_personal_cfu,\n                       employee_personal_receipt.dds_category_name_snapshot AS employee_personal_dds,\n                       employee_personal_receipt.linear_route_id AS employee_personal_route_id,\n                       employee_personal_receipt.expense_finance_operation_id AS employee_personal_expense_operation_id,\n                       employee_personal_receipt.operation_date AS employee_personal_expense_date,\n                       employee_personal_expense_op.amount AS employee_personal_expense_amount"
            : ",\n                       NULL AS employee_personal_expense_id,\n                       NULL AS employee_personal_counterparty,\n                       NULL AS employee_personal_purpose,\n                       NULL AS employee_personal_comment,\n                       NULL AS employee_personal_cfu,\n                       NULL AS employee_personal_dds,\n                       NULL AS employee_personal_route_id,\n                       NULL AS employee_personal_expense_operation_id,\n                       NULL AS employee_personal_expense_date,\n                       NULL AS employee_personal_expense_amount";
        $employeePersonalJoins = $hasEmployeePersonalExpenseEvents
            ? "\n             LEFT JOIN finance_employee_personal_expenses employee_personal_receipt\n                    ON employee_personal_receipt.receipt_finance_operation_id = fo.id\n                   AND employee_personal_receipt.status = 'POSTED'\n                   AND employee_personal_receipt.event_group_id IS NOT NULL\n             LEFT JOIN finance_operations employee_personal_expense_op\n                    ON employee_personal_expense_op.id = employee_personal_receipt.expense_finance_operation_id\n                   AND employee_personal_expense_op.status = 'POSTED'\n             LEFT JOIN finance_employee_personal_expenses employee_personal_expense\n                    ON employee_personal_expense.expense_finance_operation_id = fo.id\n                   AND employee_personal_expense.status = 'POSTED'\n                   AND employee_personal_expense.event_group_id IS NOT NULL"
            : '';
        $employeePersonalWhere = $hasEmployeePersonalExpenseEvents
            ? "\n                   AND employee_personal_expense.id IS NULL"
            : '';

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
                       END AS cash_outflow_resolution_id{$employeeInvoiceSelect}{$employeePersonalSelect}
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
                    ON legacy_outflow_resolution.outflow_finance_operation_id = fo.id{$employeeInvoiceJoins}{$employeePersonalJoins}
                 WHERE outflow_resolution.id IS NULL
                   AND legacy_outflow_resolution.outflow_finance_operation_id IS NULL{$employeeInvoiceWhere}{$employeePersonalWhere}
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
            $row['is_employee_invoice_lifecycle'] = self::isEmployeeInvoiceLifecycle($row);
            $row['is_employee_personal_expense_lifecycle'] = self::isEmployeePersonalExpenseLifecycle($row);
            $row['is_expandable_employee_lifecycle'] = self::isExpandableEmployeeLifecycle($row);
            $row['employee_lifecycle_kind'] = self::employeeLifecycleKind($row);
            $row['employee_lifecycle_expense_operation_id'] = self::employeeLifecycleExpenseOperationId($row);
            $row['employee_lifecycle_cfu'] = trim((string)($row['employee_personal_cfu'] ?? ''));
            $row['employee_lifecycle_dds'] = trim((string)($row['employee_personal_dds'] ?? ''));
            $row['employee_lifecycle_route_id'] = (int)($row['employee_personal_route_id'] ?? 0);
            $row['employee_lifecycle_invoice_number'] = trim((string)($row['employee_invoice_number'] ?? ''));
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
        if (self::isExpandableEmployeeLifecycle($row)) {
            return 'Получено → списано';
        }
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

    private static function isEmployeeInvoiceLifecycle(array $row): bool
    {
        if (empty($row['employee_invoice_payment_id'])) return false;
        if (($row['status'] ?? '') !== 'POSTED') return false;
        if (trim((string)($row['account_name'] ?? '')) !== FinanceCashResolutionService::MAIN_CASH_NAME) return false;
        if (strtoupper((string)($row['operation_type'] ?? '')) !== 'TRANSFER') return false;
        if (strtolower((string)($row['transfer_direction'] ?? '')) !== 'in') return false;
        return !empty($row['employee_invoice_expense_operation_id']);
    }

    private static function isEmployeePersonalExpenseLifecycle(array $row): bool
    {
        if (empty($row['employee_personal_expense_id'])) return false;
        if (($row['status'] ?? '') !== 'POSTED') return false;
        if (trim((string)($row['account_name'] ?? '')) !== FinanceCashResolutionService::MAIN_CASH_NAME) return false;
        if (strtoupper((string)($row['operation_type'] ?? '')) !== 'TRANSFER') return false;
        if (strtolower((string)($row['transfer_direction'] ?? '')) !== 'in') return false;
        return !empty($row['employee_personal_expense_operation_id']);
    }

    private static function isExpandableEmployeeLifecycle(array $row): bool
    {
        return self::isEmployeeInvoiceLifecycle($row) || self::isEmployeePersonalExpenseLifecycle($row);
    }

    private static function employeeLifecycleKind(array $row): ?string
    {
        if (self::isEmployeeInvoiceLifecycle($row)) return 'EMPLOYEE_INVOICE';
        if (self::isEmployeePersonalExpenseLifecycle($row)) return 'EMPLOYEE_PERSONAL_EXPENSE';
        return null;
    }

    private static function employeeLifecycleExpenseOperationId(array $row): ?int
    {
        if (self::isEmployeeInvoiceLifecycle($row)) {
            $id = (int)($row['employee_invoice_expense_operation_id'] ?? 0);
            return $id > 0 ? $id : null;
        }
        if (self::isEmployeePersonalExpenseLifecycle($row)) {
            $id = (int)($row['employee_personal_expense_operation_id'] ?? 0);
            return $id > 0 ? $id : null;
        }
        return null;
    }

    private static function isUnresolvedTechnicalSource(array $row): bool
    {
        if (self::isExpandableEmployeeLifecycle($row)) return false;
        if (($row['status'] ?? '') !== 'POSTED') return false;
        if (trim((string)($row['account_name'] ?? '')) !== FinanceCashResolutionService::MAIN_CASH_NAME) return false;
        if (!empty($row['cash_resolution_id'])) return false;
        return FinanceCashResolutionService::isIncomingSource($row);
    }

    private static function isResolvedCashLifecycle(array $row): bool
    {
        if (self::isExpandableEmployeeLifecycle($row)) return true;
        if (empty($row['cash_resolution_id'])) return false;
        if (strtoupper((string)($row['cash_resolution_type'] ?? '')) === FinanceManualFactService::CASH_RESOLUTION_CLIENT_ROUTE) return false;
        if (($row['status'] ?? '') !== 'POSTED') return false;
        if (trim((string)($row['account_name'] ?? '')) !== FinanceCashResolutionService::MAIN_CASH_NAME) return false;
        return FinanceCashResolutionService::isIncomingSource($row);
    }

    private static function sourceLabel(array $row): string
    {
        if (strtoupper((string)($row['cash_resolution_type'] ?? '')) === FinanceManualFactService::CASH_RESOLUTION_CLIENT_ROUTE) {
            return trim((string)($row['cash_resolution_target'] ?? '')) ?: 'Клиент';
        }

        $employeeName = trim((string)($row['employee_name'] ?? ''));
        $movementType = strtoupper((string)($row['employee_movement_type'] ?? ''));
        if ($employeeName !== '' && $movementType === 'RETURN') {
            return self::shortEmployeeName($employeeName);
        }

        return self::compactAccountName((string)($row['counterpart_account_name'] ?? ''));
    }

    private static function handoffRecipientLabel(array $row): string
    {
        if (self::isEmployeeInvoiceLifecycle($row)) {
            $counterparty = trim((string)($row['employee_invoice_counterparty'] ?? ''));
            return $counterparty !== '' ? $counterparty : 'Перевозчик';
        }

        if (self::isEmployeePersonalExpenseLifecycle($row)) {
            $counterparty = trim((string)($row['employee_personal_counterparty'] ?? ''));
            if ($counterparty !== '') return $counterparty;
            $purpose = trim((string)($row['employee_personal_purpose'] ?? ''));
            return $purpose !== '' ? $purpose : 'Получатель расхода';
        }

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
        if (self::isEmployeeInvoiceLifecycle($row)) {
            return (string)($row['employee_invoice_expense_date'] ?? '');
        }
        if (self::isEmployeePersonalExpenseLifecycle($row)) {
            return (string)($row['employee_personal_expense_date'] ?? '');
        }
        return '';
    }

    private static function displayPurpose(array $row): string
    {
        if (self::isEmployeeInvoiceLifecycle($row)) {
            $expensePurpose = trim((string)($row['employee_invoice_expense_purpose'] ?? ''));
            if ($expensePurpose !== '') return $expensePurpose;
            $invoiceNumber = trim((string)($row['employee_invoice_number'] ?? ''));
            $counterparty = trim((string)($row['employee_invoice_counterparty'] ?? ''));
            if ($invoiceNumber !== '' || $counterparty !== '') {
                return trim('Оплата счёта ' . $invoiceNumber . ($counterparty !== '' ? ' · ' . $counterparty : ''));
            }
        }

        if (self::isEmployeePersonalExpenseLifecycle($row)) {
            $purpose = trim((string)($row['employee_personal_purpose'] ?? ''));
            if ($purpose !== '') return $purpose;
            $counterparty = trim((string)($row['employee_personal_counterparty'] ?? ''));
            if ($counterparty !== '') return 'Оплата расхода · ' . $counterparty;
        }

        $purpose = trim((string)($row['purpose'] ?? ''));
        if ($purpose !== '') return $purpose;
        $comment = trim((string)($row['comment'] ?? ''));
        return $comment !== '' ? $comment : '—';
    }

    private static function hasEmployeePersonalExpenseEvents(PDO $pdo): bool
    {
        $key = spl_object_id($pdo);
        if (array_key_exists($key, self::$employeePersonalExpenseEventCache)) {
            return self::$employeePersonalExpenseEventCache[$key];
        }

        try {
            $required = ['receipt_finance_operation_id', 'expense_finance_operation_id', 'event_group_id'];
            foreach ($required as $column) {
                $quoted = $pdo->quote($column);
                $stmt = $pdo->query("SHOW COLUMNS FROM finance_employee_personal_expenses LIKE {$quoted}");
                if ($stmt === false || $stmt->fetchColumn() === false) {
                    self::$employeePersonalExpenseEventCache[$key] = false;
                    return false;
                }
            }
            $exists = true;
        } catch (\Throwable) {
            $exists = false;
        }

        self::$employeePersonalExpenseEventCache[$key] = $exists;
        return $exists;
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
