<?php

namespace App\Service;

use PDO;

/**
 * Resolution layer for the technical cash clearing account.
 *
 * The account named "Основная касса" is a transit buffer. Incoming cash-side
 * finance_operations remain immutable history; resolving a source creates a
 * real cash outflow through FinanceCashService/FinanceEmployeePaymentService
 * and stores only the source->destination trace in finance_cash_resolutions.
 */
final class FinanceCashResolutionService
{
    public const MAIN_CASH_NAME = 'Основная касса';
    public const RESOLUTION_EMPLOYEE = 'EMPLOYEE';

    public static function findMainCashAccount(PDO $pdo, bool $lock = false): ?array
    {
        $sql = "SELECT *
                  FROM finance_money_accounts
                 WHERE type = 'CASH' AND is_active = 1 AND name = ?
                 ORDER BY id ASC
                 LIMIT 1" . ($lock ? ' FOR UPDATE' : '');
        $stmt = $pdo->prepare($sql);
        $stmt->execute([self::MAIN_CASH_NAME]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** @return array{count:int,amount:string,account_id:int|null} */
    public static function unresolvedSummary(PDO $pdo): array
    {
        $account = self::findMainCashAccount($pdo);
        if (!$account) {
            return ['count' => 0, 'amount' => '0.00', 'account_id' => null];
        }

        $legacyResolutionSql = self::legacyResolutionProjectionSql();
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) AS unresolved_count,
                    COALESCE(SUM(fo.amount), 0) AS unresolved_amount
               FROM finance_operations fo
          LEFT JOIN finance_cash_resolutions fcr
                 ON fcr.source_finance_operation_id = fo.id
          LEFT JOIN ({$legacyResolutionSql}) legacy_resolution
                 ON legacy_resolution.source_finance_operation_id = fo.id
          LEFT JOIN finance_employee_invoice_payments employee_invoice_payment
                 ON employee_invoice_payment.receipt_finance_operation_id = fo.id
                AND employee_invoice_payment.status = 'POSTED'
              WHERE fo.money_account_id = ?
                AND fo.status = 'POSTED'
                AND (
                     fo.operation_type = 'INCOME'
                     OR (fo.operation_type = 'TRANSFER' AND fo.transfer_direction = 'in')
                )
                AND fcr.id IS NULL
                AND legacy_resolution.source_finance_operation_id IS NULL
                AND employee_invoice_payment.id IS NULL"
        );
        $stmt->execute([(int)$account['id']]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'count' => max(0, (int)($row['unresolved_count'] ?? 0)),
            'amount' => number_format((float)($row['unresolved_amount'] ?? 0), 2, '.', ''),
            'account_id' => (int)$account['id'],
        ];
    }

    public static function unresolvedCount(PDO $pdo): int
    {
        return self::unresolvedSummary($pdo)['count'];
    }

    /**
     * Read-only compatibility projection for employee cash chains created by
     * matching rules before finance_cash_resolutions was introduced.
     *
     * It mirrors migration 069, but does not mutate production data. This is
     * required because controlled /erpv2 deploys intentionally run with
     * migrations disabled and normal web requests cannot apply local schema/data
     * migrations.
     */
    public static function legacyResolutionProjectionSql(): string
    {
        return <<<'SQL'
SELECT DISTINCT
       CASE
           WHEN UPPER(fem.movement_type) = 'PAYMENT' THEN cash_transfer.id
           ELSE employee_op.id
       END AS source_finance_operation_id,
       CASE
           WHEN UPPER(fem.movement_type) = 'PAYMENT' THEN employee_op.id
           ELSE cash_transfer.id
       END AS outflow_finance_operation_id,
       CASE
           WHEN UPPER(fem.movement_type) = 'PAYMENT' THEN 'EMPLOYEE'
           ELSE 'BANK'
       END AS resolution_type,
       CASE
           WHEN UPPER(fem.movement_type) = 'PAYMENT' THEN fem.employee_identity_type
           ELSE 'MONEY_ACCOUNT'
       END AS target_identity_type,
       CASE
           WHEN UPPER(fem.movement_type) = 'PAYMENT' THEN fem.employee_identity_id
           ELSE cash_transfer.transfer_account_id
       END AS target_identity_id,
       CASE
           WHEN UPPER(fem.movement_type) = 'PAYMENT' THEN fem.employee_name_snapshot
           ELSE COALESCE(NULLIF(bank_account.name, ''), 'Расчётный счёт')
       END AS target_name_snapshot,
       fem.id AS employee_movement_id,
       employee_op.created_at AS created_at
  FROM finance_employee_movements fem
  JOIN bank_transactions bt
    ON bt.id = fem.bank_transaction_id
   AND COALESCE(bt.is_internal_transfer, 0) = 1
   AND bt.linked_cash_transaction_id IS NOT NULL
  JOIN finance_operations employee_op
    ON employee_op.id = fem.finance_operation_id
   AND employee_op.status = 'POSTED'
   AND employee_op.source = 'CASH'
  JOIN finance_operations cash_transfer
    ON cash_transfer.id = bt.linked_cash_transaction_id
   AND cash_transfer.status = 'POSTED'
   AND cash_transfer.operation_type = 'TRANSFER'
   AND cash_transfer.source = 'TRANSFER'
   AND cash_transfer.money_account_id = employee_op.money_account_id
  LEFT JOIN finance_money_accounts bank_account
    ON bank_account.id = cash_transfer.transfer_account_id
 WHERE UPPER(fem.movement_type) IN ('PAYMENT', 'RETURN')
   AND employee_op.classification_rule_id IS NOT NULL
   AND cash_transfer.classification_rule_id = employee_op.classification_rule_id
   AND (
        (UPPER(fem.movement_type) = 'PAYMENT'
         AND employee_op.operation_type = 'EXPENSE'
         AND LOWER(COALESCE(cash_transfer.transfer_direction, '')) = 'in')
        OR
        (UPPER(fem.movement_type) = 'RETURN'
         AND employee_op.operation_type = 'INCOME'
         AND LOWER(COALESCE(cash_transfer.transfer_direction, '')) = 'out')
       )
SQL;
    }

    /**
     * Atomically dispatch one or many unresolved technical-cash source rows to
     * a single active employee. One employee movement is created per source so
     * provenance stays one-to-one and repeated dispatch is fail-closed.
     *
     * @return array{count:int,total:string,employee_ref:string,employee_name:string}
     */
    public static function dispatchToEmployee(
        PDO $localPdo,
        PDO $centralPdo,
        int $companyId,
        array $sourceOperationIds,
        string $employeeRef,
        array $user
    ): array {
        $ids = array_values(array_unique(array_filter(
            array_map(static fn($id): int => (int)$id, $sourceOperationIds),
            static fn(int $id): bool => $id > 0
        )));
        if ($ids === []) {
            throw new \InvalidArgumentException('Выберите хотя бы одну позицию для передачи сотруднику.');
        }
        if (count($ids) > 200) {
            throw new \InvalidArgumentException('За один раз можно разнести не более 200 позиций.');
        }

        $employee = FinanceEmployeePaymentService::resolveActiveEmployee(
            $localPdo,
            $centralPdo,
            $companyId,
            trim($employeeRef)
        );

        $started = !$localPdo->inTransaction();
        if ($started) $localPdo->beginTransaction();

        try {
            $mainCash = self::findMainCashAccount($localPdo, true);
            if (!$mainCash) {
                throw new \RuntimeException('Техническая касса «' . self::MAIN_CASH_NAME . '» не найдена или неактивна.');
            }
            $mainCashId = (int)$mainCash['id'];

            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $localPdo->prepare(
                "SELECT fo.*, fcr.id AS resolution_id,
                        employee_invoice_payment.id AS employee_invoice_payment_id
                   FROM finance_operations fo
              LEFT JOIN finance_cash_resolutions fcr
                     ON fcr.source_finance_operation_id = fo.id
              LEFT JOIN finance_employee_invoice_payments employee_invoice_payment
                     ON employee_invoice_payment.receipt_finance_operation_id = fo.id
                    AND employee_invoice_payment.status = 'POSTED'
                  WHERE fo.id IN ({$placeholders})
                  ORDER BY fo.id ASC
                    FOR UPDATE"
            );
            $stmt->execute($ids);
            $sources = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (count($sources) !== count($ids)) {
                throw new \RuntimeException('Одна или несколько выбранных кассовых позиций не найдены. Обновите страницу.');
            }

            $totalCents = 0;
            foreach ($sources as $source) {
                if ((int)($source['money_account_id'] ?? 0) !== $mainCashId) {
                    throw new \RuntimeException('Выбранная позиция #' . (int)$source['id'] . ' не относится к технической Основной кассе.');
                }
                if (($source['status'] ?? '') !== 'POSTED' || !self::isIncomingSource($source)) {
                    throw new \RuntimeException('Позиция #' . (int)$source['id'] . ' не является неразнесённым поступлением.');
                }
                if (!empty($source['resolution_id']) || !empty($source['employee_invoice_payment_id'])) {
                    throw new \RuntimeException('Позиция #' . (int)$source['id'] . ' уже разнесена. Обновите страницу.');
                }
                $totalCents += self::toCents((string)($source['amount'] ?? '0'));
            }

            // The cash outflow represents the handoff event itself, not the date
            // of the historical bank/card purchase that funded the transit cash.
            // Capture once so an entire batch always has one factual handoff date.
            $dispatchDate = date('Y-m-d');

            foreach ($sources as $source) {
                $sourceId = (int)$source['id'];
                $sourcePurpose = trim((string)($source['purpose'] ?? ''));
                $purpose = $sourcePurpose !== ''
                    ? mb_substr($sourcePurpose, 0, 950)
                    : 'Разнесение технической кассы';

                $movement = FinanceEmployeePaymentService::createCashMovement(
                    $localPdo,
                    [
                        'movement_type' => 'PAYMENT',
                        'money_account_id' => $mainCashId,
                        'amount' => (string)$source['amount'],
                        'operation_date' => $dispatchDate,
                        'purpose' => $purpose,
                        'comment' => 'Разнесение технической кассы. Источник finance_operation #' . $sourceId . '.',
                    ],
                    $user,
                    $employee
                );

                $insert = $localPdo->prepare(
                    "INSERT INTO finance_cash_resolutions
                        (source_finance_operation_id, resolution_type,
                         target_identity_type, target_identity_id, target_name_snapshot,
                         outflow_finance_operation_id, employee_movement_id,
                         created_by_user_id, created_by_role)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
                );
                $insert->execute([
                    $sourceId,
                    self::RESOLUTION_EMPLOYEE,
                    (string)$employee['identity_type'],
                    (int)$employee['identity_id'],
                    (string)$employee['full_name'],
                    (int)$movement['finance_operation_id'],
                    (int)$movement['movement_id'],
                    (int)($user['id'] ?? 0) ?: null,
                    (string)($user['role'] ?? 'company_owner'),
                ]);
            }

            if ($started) $localPdo->commit();

            return [
                'count' => count($sources),
                'total' => self::fromCents($totalCents),
                'employee_ref' => (string)$employee['ref'],
                'employee_name' => (string)$employee['full_name'],
            ];
        } catch (\Throwable $e) {
            if ($started && $localPdo->inTransaction()) $localPdo->rollBack();
            throw $e;
        }
    }

    public static function isIncomingSource(array $row): bool
    {
        $type = strtoupper((string)($row['operation_type'] ?? ''));
        if ($type === 'INCOME') return true;
        return $type === 'TRANSFER' && strtolower((string)($row['transfer_direction'] ?? '')) === 'in';
    }

    private static function toCents(string $value): int
    {
        $normalized = str_replace(',', '.', trim($value));
        if (!preg_match('/^-?\d+(?:\.\d+)?$/', $normalized)) return 0;
        $negative = str_starts_with($normalized, '-');
        if ($negative) $normalized = substr($normalized, 1);
        [$whole, $fraction] = array_pad(explode('.', $normalized, 2), 2, '');
        $fraction = str_pad(substr($fraction, 0, 2), 2, '0');
        $cents = ((int)$whole * 100) + (int)$fraction;
        return $negative ? -$cents : $cents;
    }

    private static function fromCents(int $cents): string
    {
        $negative = $cents < 0;
        $cents = abs($cents);
        $value = intdiv($cents, 100) . '.' . str_pad((string)($cents % 100), 2, '0', STR_PAD_LEFT);
        return $negative ? '-' . $value : $value;
    }
}
