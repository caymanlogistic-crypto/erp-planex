<?php

namespace App\Service;

use PDO;
use RuntimeException;
use Throwable;

/**
 * Edits the actual date of an already-posted manual invoice settlement.
 *
 * Bank dates remain sourced from bank_transactions. Employee-funded invoice
 * payments update both linked cash legs atomically without recreating money
 * operations or allocations.
 */
final class FinanceInvoiceSettlementDateService
{
    public static function updateDate(PDO $pdo, int $invoiceId, int $operationId, string $date, array $user): array
    {
        if ($invoiceId <= 0 || $operationId <= 0) {
            throw new \InvalidArgumentException('Оплата счёта не найдена.');
        }
        $date = self::date($date);
        [$userId, $role] = self::actor($user);

        $owns = !$pdo->inTransaction();
        if ($owns) $pdo->beginTransaction();
        try {
            $invoiceStmt = $pdo->prepare('SELECT id,number,status,direction FROM finance_invoices WHERE id=? FOR UPDATE');
            $invoiceStmt->execute([$invoiceId]);
            $invoice = $invoiceStmt->fetch(PDO::FETCH_ASSOC);
            if (!$invoice) throw new RuntimeException('Счёт не найден.');
            if ((string)($invoice['status'] ?? '') === 'cancelled') {
                throw new RuntimeException('У аннулированного счёта нельзя менять дату оплаты.');
            }

            $operationStmt = $pdo->prepare('SELECT * FROM finance_operations WHERE id=? FOR UPDATE');
            $operationStmt->execute([$operationId]);
            $operation = $operationStmt->fetch(PDO::FETCH_ASSOC);
            if (!$operation || (string)($operation['status'] ?? '') !== 'POSTED') {
                throw new RuntimeException('Проведённая финансовая операция не найдена.');
            }
            if ((int)($operation['bank_transaction_id'] ?? 0) > 0) {
                throw new RuntimeException('Дата банковского платежа берётся из банковской выписки и редактируется в операции выписки.');
            }

            $allocationStmt = $pdo->prepare(
                'SELECT id,linear_route_payment_id,linear_route_id,allocation_date
                   FROM finance_operation_allocations
                  WHERE operation_id=? AND invoice_id=? AND cancelled_at IS NULL
                  ORDER BY id ASC FOR UPDATE'
            );
            $allocationStmt->execute([$operationId, $invoiceId]);
            $allocations = $allocationStmt->fetchAll(PDO::FETCH_ASSOC);
            if ($allocations === []) {
                throw new RuntimeException('Активная связь платежа со счётом не найдена.');
            }

            $oldDate = trim((string)($operation['operation_date'] ?? ''));
            $employeeEvent = self::employeeEvent($pdo, $invoiceId, $operationId);
            $relatedOperationIds = [$operationId];
            if ($employeeEvent !== null) {
                $relatedOperationIds = array_values(array_unique([
                    (int)$employeeEvent['receipt_finance_operation_id'],
                    (int)$employeeEvent['expense_finance_operation_id'],
                ]));
                foreach ($relatedOperationIds as $relatedId) {
                    if ($relatedId <= 0) throw new RuntimeException('Связанные операции сотрудника повреждены.');
                    $check = $pdo->prepare('SELECT status FROM finance_operations WHERE id=? FOR UPDATE');
                    $check->execute([$relatedId]);
                    if ($check->fetchColumn() !== 'POSTED') {
                        throw new RuntimeException('Связанная операция сотрудника недоступна для изменения.');
                    }
                }
            }

            $alreadySame = $oldDate === $date;
            if ($employeeEvent !== null) {
                $alreadySame = $alreadySame && trim((string)($employeeEvent['operation_date'] ?? '')) === $date;
            }

            if (!$alreadySame) {
                $updateOperation = $pdo->prepare('UPDATE finance_operations SET operation_date=? WHERE id=? AND status=\'POSTED\'');
                foreach ($relatedOperationIds as $relatedId) {
                    $updateOperation->execute([$date, $relatedId]);
                    if ($updateOperation->rowCount() > 1) {
                        throw new RuntimeException('Не удалось изменить дату связанной финансовой операции.');
                    }
                }

                $allocationUpdate = $pdo->prepare(
                    'UPDATE finance_operation_allocations
                        SET allocation_date=?
                      WHERE operation_id=? AND invoice_id=? AND cancelled_at IS NULL'
                );
                $allocationUpdate->execute([$date, $operationId, $invoiceId]);

                if ($employeeEvent !== null) {
                    $eventUpdate = $pdo->prepare(
                        "UPDATE finance_employee_invoice_payments
                            SET operation_date=?
                          WHERE id=? AND status='POSTED'"
                    );
                    $eventUpdate->execute([$date, (int)$employeeEvent['id']]);
                }

                self::syncInvoiceDates($pdo, $invoiceId);
                self::syncRoutePaymentDates($pdo, $allocations);

                FinanceAuditLogService::log(
                    $pdo,
                    'finance_invoice',
                    $invoiceId,
                    'settlement_date_update',
                    ['operation_id'=>$operationId, 'operation_date'=>$oldDate],
                    [
                        'operation_id'=>$operationId,
                        'operation_date'=>$date,
                        'employee_invoice_event_id'=>$employeeEvent !== null ? (int)$employeeEvent['id'] : null,
                    ],
                    $userId,
                    $role
                );
                if ($employeeEvent !== null) {
                    FinanceAuditLogService::log(
                        $pdo,
                        'finance_employee_invoice_payment',
                        (int)$employeeEvent['id'],
                        'date_update',
                        ['operation_date'=>$oldDate],
                        ['operation_date'=>$date, 'invoice_id'=>$invoiceId, 'operation_id'=>$operationId],
                        $userId,
                        $role
                    );
                }
            }

            if ($owns) $pdo->commit();
            return [
                'invoice_id'=>$invoiceId,
                'operation_id'=>$operationId,
                'operation_date'=>$date,
                'changed'=>!$alreadySame,
                'employee_payment'=>$employeeEvent !== null,
            ];
        } catch (Throwable $e) {
            if ($owns && $pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    private static function employeeEvent(PDO $pdo, int $invoiceId, int $operationId): ?array
    {
        try {
            $stmt = $pdo->prepare(
                "SELECT * FROM finance_employee_invoice_payments
                  WHERE invoice_id=? AND expense_finance_operation_id=? AND status='POSTED'
                  LIMIT 1 FOR UPDATE"
            );
            $stmt->execute([$invoiceId, $operationId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (Throwable) {
            // Compatibility with a tenant that has not reached the employee
            // invoice event migration yet. Direct cash editing still works.
            return null;
        }
    }

    private static function syncInvoiceDates(PDO $pdo, int $invoiceId): void
    {
        $stmt = $pdo->prepare(
            "SELECT i.amount,
                    COALESCE(SUM(CASE WHEN a.cancelled_at IS NULL AND fo.status='POSTED' THEN a.amount ELSE 0 END),0) AS paid_amount,
                    MIN(CASE WHEN a.cancelled_at IS NULL AND fo.status='POSTED' THEN a.allocation_date END) AS first_date,
                    MAX(CASE WHEN a.cancelled_at IS NULL AND fo.status='POSTED' THEN a.allocation_date END) AS last_date
               FROM finance_invoices i
          LEFT JOIN finance_operation_allocations a ON a.invoice_id=i.id
          LEFT JOIN finance_operations fo ON fo.id=a.operation_id
              WHERE i.id=?
              GROUP BY i.id,i.amount"
        );
        $stmt->execute([$invoiceId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return;
        $paid = self::cents((string)($row['paid_amount'] ?? '0.00'));
        $amount = self::cents((string)($row['amount'] ?? '0.00'));
        $fullyPaidAt = $amount > 0 && $paid >= $amount ? ($row['last_date'] ?: null) : null;
        $upd = $pdo->prepare('UPDATE finance_invoices SET first_paid_at=?,fully_paid_at=?,updated_at=NOW() WHERE id=?');
        $upd->execute([$row['first_date'] ?: null, $fullyPaidAt, $invoiceId]);
    }

    private static function syncRoutePaymentDates(PDO $pdo, array $allocations): void
    {
        $ids = [];
        foreach ($allocations as $allocation) {
            $id = (int)($allocation['linear_route_payment_id'] ?? 0);
            if ($id > 0) $ids[$id] = true;
        }
        foreach (array_keys($ids) as $paymentId) {
            $stmt = $pdo->prepare(
                "SELECT rp.amount,
                        COALESCE(SUM(CASE WHEN a.cancelled_at IS NULL AND fo.status='POSTED' THEN a.amount ELSE 0 END),0) AS paid_amount,
                        MAX(CASE WHEN a.cancelled_at IS NULL AND fo.status='POSTED' THEN a.allocation_date END) AS last_date
                   FROM linear_route_payments rp
              LEFT JOIN finance_operation_allocations a ON a.linear_route_payment_id=rp.id
              LEFT JOIN finance_operations fo ON fo.id=a.operation_id
                  WHERE rp.id=? AND rp.deleted_at IS NULL
                  GROUP BY rp.id,rp.amount"
            );
            $stmt->execute([$paymentId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) continue;
            $paidAt = self::cents((string)($row['paid_amount'] ?? '0.00')) >= self::cents((string)($row['amount'] ?? '0.00'))
                ? ($row['last_date'] ?: null)
                : null;
            $pdo->prepare('UPDATE linear_route_payments SET paid_at=? WHERE id=?')->execute([$paidAt, $paymentId]);
        }
    }

    private static function date(string $value): string
    {
        $value = trim($value);
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = \DateTimeImmutable::getLastErrors();
        if ($date === false
            || (is_array($errors) && (($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0))
            || $date->format('Y-m-d') !== $value) {
            throw new \InvalidArgumentException('Укажите корректную дату фактической оплаты.');
        }
        return $value;
    }

    private static function cents(string $value): int
    {
        $value = trim(str_replace(',', '.', $value));
        if (!preg_match('/^-?\d+(?:\.\d+)?$/D', $value)) return 0;
        $negative = str_starts_with($value, '-');
        if ($negative) $value = substr($value, 1);
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        $fraction = str_pad(substr($fraction, 0, 2), 2, '0');
        $cents = ((int)$whole * 100) + (int)$fraction;
        return $negative ? -$cents : $cents;
    }

    /** @return array{0:int,1:string} */
    private static function actor(array $user): array
    {
        $userId = (int)($user['user_id'] ?? $user['id'] ?? 0);
        $role = trim((string)($user['role_code'] ?? $user['role'] ?? 'company_owner')) ?: 'company_owner';
        return [$userId, $role];
    }
}
