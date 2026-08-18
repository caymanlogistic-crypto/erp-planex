<?php

namespace App\Service;

use PDO;
use RuntimeException;

/** Client payment received directly by an employee and allocated to an outgoing invoice. */
final class FinanceEmployeeClientReceiptService
{
    public static function create(PDO $pdo, array $data, array $user, array $employee): array
    {
        $amount = FinanceOperationInvoiceSettlementService::normalizePositiveMoney((string)($data['amount'] ?? ''));
        $date = trim((string)($data['operation_date'] ?? ''));
        $invoiceId = (int)($data['invoice_id'] ?? 0);
        $comment = trim((string)($data['comment'] ?? '')) ?: null;
        if ($amount === null) throw new \InvalidArgumentException('Укажите корректную сумму.');
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if (!$parsed || $parsed->format('Y-m-d') !== $date) throw new \InvalidArgumentException('Укажите корректную дату.');
        if ($invoiceId <= 0) throw new \InvalidArgumentException('Выберите счёт клиента.');

        $owns = !$pdo->inTransaction();
        if ($owns) $pdo->beginTransaction();
        try {
            $invoice = FinanceOperationInvoiceSettlementService::fetchInvoice($pdo, $invoiceId, 'INCOME', true);
            if (self::cents($amount) > self::cents((string)$invoice['remaining_amount'])) {
                throw new RuntimeException('Сумма превышает остаток по счёту клиента.');
            }
            $accountId = FinanceEmployeeMoneyAccountService::accountId($pdo, $employee, $user);
            $number = trim((string)($invoice['number'] ?? '')) ?: ('#'.$invoiceId);
            $client = (string)$invoice['counterparty_display_name'];
            $group = 'EMP_CLIENT_DIRECT_' . bin2hex(random_bytes(10));
            $uid = (int)($user['id'] ?? $user['user_id'] ?? 0);
            $role = (string)($user['role'] ?? $user['role_code'] ?? 'company_owner');

            $op = $pdo->prepare("INSERT INTO finance_operations
                (operation_type,status,source,money_account_id,transfer_account_id,transfer_group_id,transfer_direction,
                 operation_date,amount,currency,counterparty_entity_type,counterparty_entity_id,counterparty_name,
                 purpose,comment,classification_status,classification_locked,
                 created_by_user_id,created_by_role,posted_by_user_id,posted_by_role,posted_at)
                VALUES ('INCOME','POSTED','EMPLOYEE',?,NULL,?,NULL,?,?,'RUR',?,?,?,?,?,'UNALLOCATED',0,?,?,?,?,NOW())");
            $op->execute([
                $accountId,$group,$date,$amount,
                (string)($invoice['counterparty_entity_type'] ?? '') ?: null,
                !empty($invoice['counterparty_entity_id']) ? (int)$invoice['counterparty_entity_id'] : null,
                $client,
                'Получено от клиента '.$client.' · счёт '.$number,
                $comment,
                $uid > 0 ? $uid : null,$role,$uid > 0 ? $uid : null,$role,
            ]);
            $operationId = (int)$pdo->lastInsertId();

            $localUserId = (string)$employee['identity_type'] === FinanceEmployeePaymentService::IDENTITY_TENANT_USER
                ? (int)$employee['identity_id'] : null;
            $movement = $pdo->prepare("INSERT INTO finance_employee_movements
                (employee_user_id,employee_identity_type,employee_identity_id,employee_name_snapshot,employee_role_snapshot,
                 movement_type,source_type,finance_operation_id,bank_transaction_id,note,created_by_user_id,created_by_role)
                VALUES (?,?,?,?,?,'PAYMENT','CLIENT',?,NULL,?,?,?)");
            $movement->execute([
                $localUserId,(string)$employee['identity_type'],(int)$employee['identity_id'],
                (string)$employee['full_name'],(string)($employee['role_code'] ?? ''),
                $operationId,'Получено от клиента '.$client.' · счёт '.$number,
                $uid > 0 ? $uid : null,$role,
            ]);
            $movementId = (int)$pdo->lastInsertId();

            $settlement = FinanceOperationInvoiceSettlementService::allocate(
                $pdo,$operationId,$invoiceId,$amount,$user,'Оплата клиента получена сотрудником.'
            );
            FinanceAuditLogService::log($pdo,'finance_employee_movement',$movementId,'client_receipt_direct',null,[
                'employee_ref'=>(string)$employee['ref'],'invoice_id'=>$invoiceId,'client'=>$client,
                'amount'=>$amount,'finance_operation_id'=>$operationId,'cash_account_used'=>false,
            ],$uid,$role);

            if ($owns) $pdo->commit();
            return [
                'movement_id'=>$movementId,'operation_id'=>$operationId,'invoice_id'=>$invoiceId,
                'invoice_number'=>$number,'client_name'=>$client,'amount'=>$amount,
                'invoice_remaining'=>$settlement['invoice_remaining'],'employee'=>$employee,
            ];
        } catch (\Throwable $e) {
            if ($owns && $pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    private static function cents(string $value): int
    {
        $value = FinanceOperationInvoiceSettlementService::money($value);
        $negative = str_starts_with($value, '-');
        if ($negative) $value = substr($value, 1);
        [$whole,$fraction] = explode('.', $value, 2);
        $cents = ((int)$whole * 100) + (int)$fraction;
        return $negative ? -$cents : $cents;
    }
}
