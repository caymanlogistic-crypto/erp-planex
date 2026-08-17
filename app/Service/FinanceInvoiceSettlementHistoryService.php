<?php

namespace App\Service;

use PDO;
use Throwable;

/**
 * Read-only projection of real, posted invoice settlements.
 *
 * One finance operation may be split across several obligations of the same
 * invoice, therefore rows are grouped by operation rather than allocation.
 */
final class FinanceInvoiceSettlementHistoryService
{
    /** @return array<int,array<string,mixed>> */
    public static function forInvoice(PDO $pdo, int $invoiceId): array
    {
        if ($invoiceId <= 0) {
            return [];
        }

        try {
            $stmt = $pdo->prepare(
                "SELECT fo.id AS operation_id,
                        COALESCE(bt.operation_date,fo.operation_date,MAX(a.allocation_date)) AS actual_date,
                        SUM(a.amount) AS amount,
                        fo.bank_transaction_id,
                        fo.source,
                        fma.type AS money_account_type,
                        fma.name AS money_account_name,
                        MAX(eip.employee_name_snapshot) AS employee_name
                   FROM finance_operation_allocations a
                   JOIN finance_operations fo ON fo.id=a.operation_id
              LEFT JOIN bank_transactions bt ON bt.id=fo.bank_transaction_id
              LEFT JOIN finance_money_accounts fma ON fma.id=fo.money_account_id
              LEFT JOIN finance_employee_invoice_payments eip
                     ON eip.expense_finance_operation_id=fo.id AND eip.status='POSTED'
                  WHERE a.invoice_id=?
                    AND a.cancelled_at IS NULL
                    AND fo.status='POSTED'
                  GROUP BY fo.id,bt.operation_date,fo.operation_date,fo.bank_transaction_id,fo.source,fma.type,fma.name
                  ORDER BY COALESCE(bt.operation_date,fo.operation_date,MAX(a.allocation_date)),fo.id"
            );
            $stmt->execute([$invoiceId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable) {
            // Compatibility fallback for a tenant that has not reached the
            // employee invoice event table yet. The projection remains read-only.
            try {
                $stmt = $pdo->prepare(
                    "SELECT fo.id AS operation_id,
                            COALESCE(bt.operation_date,fo.operation_date,MAX(a.allocation_date)) AS actual_date,
                            SUM(a.amount) AS amount,
                            fo.bank_transaction_id,
                            fo.source,
                            fma.type AS money_account_type,
                            fma.name AS money_account_name,
                            NULL AS employee_name
                       FROM finance_operation_allocations a
                       JOIN finance_operations fo ON fo.id=a.operation_id
                  LEFT JOIN bank_transactions bt ON bt.id=fo.bank_transaction_id
                  LEFT JOIN finance_money_accounts fma ON fma.id=fo.money_account_id
                      WHERE a.invoice_id=?
                        AND a.cancelled_at IS NULL
                        AND fo.status='POSTED'
                      GROUP BY fo.id,bt.operation_date,fo.operation_date,fo.bank_transaction_id,fo.source,fma.type,fma.name
                      ORDER BY COALESCE(bt.operation_date,fo.operation_date,MAX(a.allocation_date)),fo.id"
                );
                $stmt->execute([$invoiceId]);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (Throwable) {
                return [];
            }
        }

        foreach ($rows as &$row) {
            [$row['channel'], $row['channel_detail']] = self::channel($row);
            $row['amount'] = self::money((string)($row['amount'] ?? '0.00'));
        }
        unset($row);

        return $rows;
    }

    /** @return array{0:string,1:string} */
    private static function channel(array $row): array
    {
        $employee = trim((string)($row['employee_name'] ?? ''));
        if ($employee !== '') {
            return ['Сотрудник', $employee];
        }
        if ((int)($row['bank_transaction_id'] ?? 0) > 0) {
            return ['Расчётный счёт', trim((string)($row['money_account_name'] ?? ''))];
        }
        if ((string)($row['money_account_type'] ?? '') === 'CASH') {
            return ['Касса', trim((string)($row['money_account_name'] ?? ''))];
        }

        $source = strtoupper(trim((string)($row['source'] ?? '')));
        $detail = trim((string)($row['money_account_name'] ?? ''));
        return [match ($source) {
            'BANK' => 'Расчётный счёт',
            'CASH' => 'Касса',
            default => 'Финансовая операция',
        }, $detail];
    }

    private static function money(string $value): string
    {
        $value = trim(str_replace(',', '.', $value));
        if (!preg_match('/^-?\d+(?:\.\d+)?$/D', $value)) {
            return '0.00';
        }
        $negative = str_starts_with($value, '-');
        if ($negative) {
            $value = substr($value, 1);
        }
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        $whole = ltrim($whole, '0');
        if ($whole === '') $whole = '0';
        $fraction = str_pad(substr($fraction, 0, 2), 2, '0');
        return ($negative ? '-' : '') . $whole . '.' . $fraction;
    }
}
