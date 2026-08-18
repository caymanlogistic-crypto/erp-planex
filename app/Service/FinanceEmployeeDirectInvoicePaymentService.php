<?php

namespace App\Service;

use PDO;
use RuntimeException;

/** Pay an incoming invoice directly from an employee money account. */
final class FinanceEmployeeDirectInvoicePaymentService
{
    public static function create(PDO $pdo, array $data, array $user, array $employee): array
    {
        self::assertEmployee($employee);
        $amount = self::amount((string)($data['amount'] ?? ''));
        $date = self::date((string)($data['operation_date'] ?? ''));
        $invoiceId = (int)($data['invoice_id'] ?? 0);
        $comment = trim((string)($data['comment'] ?? '')) ?: null;
        $owns = !$pdo->inTransaction();
        if ($owns) $pdo->beginTransaction();

        try {
            $invoice = FinanceOperationInvoiceSettlementService::fetchInvoice($pdo, $invoiceId, 'EXPENSE', true);
            if (self::cents($amount) > self::cents((string)$invoice['remaining_amount'])) {
                throw new RuntimeException('Сумма оплаты превышает остаток по счёту.');
            }
            $accountId = FinanceEmployeeMoneyAccountService::accountId($pdo, $employee, $user);
            $group = 'EMP_INVOICE_DIRECT_' . bin2hex(random_bytes(10));
            $invoiceNumber = trim((string)($invoice['number'] ?? '')) ?: ('#' . $invoiceId);
            $carrier = (string)$invoice['counterparty_display_name'];
            $purpose = 'Оплата счёта ' . $invoiceNumber . ' · ' . $carrier . ' · сотрудник ' . (string)$employee['full_name'];
            $uid = self::userId($user);
            $role = self::role($user);

            $insertOperation = $pdo->prepare(
                "INSERT INTO finance_operations
                    (operation_type,status,source,money_account_id,transfer_account_id,transfer_group_id,transfer_direction,
                     operation_date,amount,currency,counterparty_entity_type,counterparty_entity_id,counterparty_name,
                     purpose,comment,classification_status,classification_locked,
                     created_by_user_id,created_by_role,posted_by_user_id,posted_by_role,posted_at)
                 VALUES
                    ('EXPENSE','POSTED','EMPLOYEE',?,NULL,?,NULL,?,?,'RUR',?,?,?, ?,?,
                     'UNALLOCATED',0, ?,?,?,?,NOW())"
            );
            $insertOperation->execute([
                $accountId,
                $group,
                $date,
                $amount,
                (string)($invoice['counterparty_entity_type'] ?? '') ?: null,
                !empty($invoice['counterparty_entity_id']) ? (int)$invoice['counterparty_entity_id'] : null,
                $carrier,
                $purpose,
                $comment,
                $uid > 0 ? $uid : null,
                $role,
                $uid > 0 ? $uid : null,
                $role,
            ]);
            $operationId = (int)$pdo->lastInsertId();

            $localUserId = (string)$employee['identity_type'] === FinanceEmployeePaymentService::IDENTITY_TENANT_USER
                ? (int)$employee['identity_id'] : null;
            $movement = $pdo->prepare(
                "INSERT INTO finance_employee_movements
                    (employee_user_id,employee_identity_type,employee_identity_id,employee_name_snapshot,employee_role_snapshot,
                     movement_type,source_type,finance_operation_id,bank_transaction_id,note,created_by_user_id,created_by_role)
                 VALUES (?,?,?,?,?,'RETURN','EMPLOYEE',?,NULL,?,?,?)"
            );
            $movement->execute([
                $localUserId,
                (string)$employee['identity_type'],
                (int)$employee['identity_id'],
                (string)$employee['full_name'],
                (string)($employee['role_code'] ?? ''),
                $operationId,
                'Оплата входящего счёта ' . $invoiceNumber . ' · ' . $carrier,
                $uid > 0 ? $uid : null,
                $role,
            ]);
            $movementId = (int)$pdo->lastInsertId();

            FinanceOperationInvoiceSettlementService::allocate(
                $pdo,
                $operationId,
                $invoiceId,
                $amount,
                $user,
                'Оплата входящего счёта напрямую сотрудником.'
            );

            $insertEvent = $pdo->prepare(
                "INSERT INTO finance_employee_invoice_payments
                    (event_group_id,employee_identity_type,employee_identity_id,employee_name_snapshot,employee_role_snapshot,
                     invoice_id,invoice_number_snapshot,counterparty_name_snapshot,operation_date,amount,currency,
                     employee_movement_id,receipt_finance_operation_id,expense_finance_operation_id,comment,status,
                     created_by_user_id,created_by_role)
                 VALUES (?,?,?,?,?,?,?,?,?,?,'RUR',?,?,?,?, 'POSTED',?,?)"
            );
            $insertEvent->execute([
                $group,
                (string)$employee['identity_type'],
                (int)$employee['identity_id'],
                (string)$employee['full_name'],
                (string)($employee['role_code'] ?? ''),
                $invoiceId,
                $invoiceNumber,
                $carrier,
                $date,
                $amount,
                $movementId,
                $operationId,
                $operationId,
                $comment,
                $uid > 0 ? $uid : null,
                $role,
            ]);
            $eventId = (int)$pdo->lastInsertId();

            FinanceAuditLogService::log(
                $pdo,
                'finance_employee_invoice_payment',
                $eventId,
                'direct_create',
                null,
                [
                    'event_group_id' => $group,
                    'employee_ref' => (string)$employee['ref'],
                    'invoice_id' => $invoiceId,
                    'amount' => $amount,
                    'finance_operation_id' => $operationId,
                    'employee_movement_id' => $movementId,
                    'cash_account_used' => false,
                ],
                $uid,
                $role
            );

            if ($owns) $pdo->commit();
            return FinanceEmployeeInvoicePaymentEventService::fetchOne($pdo, $eventId) ?? ['id' => $eventId];
        } catch (\Throwable $e) {
            if ($owns && $pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    private static function assertEmployee(array $employee): void
    {
        if (empty($employee['identity_type']) || (int)($employee['identity_id'] ?? 0) <= 0 || trim((string)($employee['full_name'] ?? '')) === '') {
            throw new \InvalidArgumentException('Выберите сотрудника.');
        }
    }

    private static function amount(string $value): string
    {
        $amount = FinanceOperationInvoiceSettlementService::normalizePositiveMoney($value);
        if ($amount === null) throw new \InvalidArgumentException('Укажите корректную сумму оплаты.');
        return $amount;
    }

    private static function date(string $value): string
    {
        $value = trim($value);
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if (!$date || $date->format('Y-m-d') !== $value) throw new \InvalidArgumentException('Укажите корректную дату.');
        return $value;
    }

    private static function cents(string $value): int
    {
        $value = FinanceOperationInvoiceSettlementService::money($value);
        $negative = str_starts_with($value, '-');
        if ($negative) $value = substr($value, 1);
        [$whole, $fraction] = explode('.', $value, 2);
        $cents = ((int)$whole * 100) + (int)$fraction;
        return $negative ? -$cents : $cents;
    }

    private static function userId(array $user): int
    {
        return (int)($user['user_id'] ?? $user['id'] ?? 0);
    }

    private static function role(array $user): string
    {
        return (string)($user['role_code'] ?? $user['role'] ?? 'company_owner');
    }
}
