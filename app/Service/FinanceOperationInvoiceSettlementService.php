<?php

namespace App\Service;

use PDO;
use RuntimeException;
use Throwable;

/**
 * Generic real-money operation -> invoice settlement.
 *
 * BANK/CASH/employee-funded operations all use the same allocation layer.
 * INCOME settles OUTGOING invoices; EXPENSE settles INCOMING invoices.
 */
final class FinanceOperationInvoiceSettlementService
{
    public static function fetchOpenInvoices(PDO $pdo, string $operationType): array
    {
        $operationType = strtoupper(trim($operationType));
        self::assertOperationType($operationType);
        $direction = $operationType === 'INCOME'
            ? FinanceInvoiceService::DIRECTION_OUTGOING
            : FinanceInvoiceService::DIRECTION_INCOMING;

        $stmt = $pdo->prepare(
            "SELECT i.*,
                    CASE WHEN i.direction='OUTGOING' THEN c.name ELSE ct.name END AS counterparty_display_name,
                    CASE WHEN i.direction='OUTGOING' THEN c.inn ELSE ct.inn END AS counterparty_display_inn
               FROM finance_invoices i
          LEFT JOIN clients c
                 ON i.counterparty_entity_type='client' AND c.id=i.counterparty_entity_id AND c.deleted_at IS NULL
          LEFT JOIN contractors ct
                 ON i.counterparty_entity_type='contractor' AND ct.id=i.counterparty_entity_id AND ct.deleted_at IS NULL
              WHERE i.direction=?
                AND i.status NOT IN ('cancelled','paid')
              ORDER BY i.invoice_date DESC,i.id DESC"
        );
        $stmt->execute([$direction]);
        $result = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $invoice) {
            $remaining = FinanceSettlementCascadeService::getInvoiceRemainingAmount(
                $pdo,
                (int)$invoice['id'],
                (string)$invoice['amount']
            );
            if (self::compareMoney($remaining, '0.00') <= 0) {
                continue;
            }
            $invoice['remaining_amount'] = $remaining;
            $invoice['paid_amount'] = FinanceSettlementCascadeService::getInvoicePaidAmount($pdo, (int)$invoice['id']);
            $invoice['counterparty_display_name'] = trim((string)($invoice['counterparty_display_name'] ?? ''))
                ?: trim((string)($invoice['counterparty_name'] ?? ''))
                ?: 'Контрагент';
            $result[] = $invoice;
        }
        return $result;
    }

    public static function fetchInvoice(PDO $pdo, int $invoiceId, ?string $expectedOperationType = null, bool $forUpdate = false): array
    {
        if ($invoiceId <= 0) {
            throw new \InvalidArgumentException('Выберите счёт.');
        }
        $sql = "SELECT i.*,
                       CASE WHEN i.direction='OUTGOING' THEN c.name ELSE ct.name END AS counterparty_display_name,
                       CASE WHEN i.direction='OUTGOING' THEN c.inn ELSE ct.inn END AS counterparty_display_inn
                  FROM finance_invoices i
             LEFT JOIN clients c
                    ON i.counterparty_entity_type='client' AND c.id=i.counterparty_entity_id AND c.deleted_at IS NULL
             LEFT JOIN contractors ct
                    ON i.counterparty_entity_type='contractor' AND ct.id=i.counterparty_entity_id AND ct.deleted_at IS NULL
                 WHERE i.id=? LIMIT 1" . ($forUpdate ? ' FOR UPDATE' : '');
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$invoiceId]);
        $invoice = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$invoice) {
            throw new RuntimeException('Счёт не найден.');
        }
        if (in_array((string)($invoice['status'] ?? ''), ['cancelled','paid'], true)) {
            throw new RuntimeException('Счёт уже закрыт или аннулирован.');
        }
        if ($expectedOperationType !== null) {
            $expectedOperationType = strtoupper(trim($expectedOperationType));
            self::assertOperationType($expectedOperationType);
            $expectedDirection = $expectedOperationType === 'INCOME'
                ? FinanceInvoiceService::DIRECTION_OUTGOING
                : FinanceInvoiceService::DIRECTION_INCOMING;
            if ((string)$invoice['direction'] !== $expectedDirection) {
                throw new RuntimeException(
                    $expectedOperationType === 'INCOME'
                        ? 'Поступление можно распределить только на исходящий счёт клиента.'
                        : 'Расход можно распределить только на входящий счёт перевозчика.'
                );
            }
        }
        $invoice['remaining_amount'] = FinanceSettlementCascadeService::getInvoiceRemainingAmount(
            $pdo,
            $invoiceId,
            (string)$invoice['amount']
        );
        $invoice['counterparty_display_name'] = trim((string)($invoice['counterparty_display_name'] ?? ''))
            ?: trim((string)($invoice['counterparty_name'] ?? ''))
            ?: 'Контрагент';
        return $invoice;
    }

    /**
     * @return array{operation_id:int,invoice_id:int,amount:string,allocation_ids:array<int>,operation_remaining:string,invoice_remaining:string}
     */
    public static function allocate(PDO $pdo, int $operationId, int $invoiceId, string $amountInput, array $user, ?string $comment = null): array
    {
        if ($operationId <= 0) {
            throw new \InvalidArgumentException('Финансовая операция не найдена.');
        }
        $amount = self::normalizePositiveMoney($amountInput);
        if ($amount === null) {
            throw new \InvalidArgumentException('Укажите корректную сумму оплаты счёта.');
        }

        $owns = !$pdo->inTransaction();
        if ($owns) $pdo->beginTransaction();
        try {
            $opStmt = $pdo->prepare('SELECT * FROM finance_operations WHERE id=? FOR UPDATE');
            $opStmt->execute([$operationId]);
            $operation = $opStmt->fetch(PDO::FETCH_ASSOC);
            if (!$operation) throw new RuntimeException('Финансовая операция не найдена.');
            if ((string)$operation['status'] !== 'POSTED') throw new RuntimeException('Распределять можно только проведённую операцию.');
            $operationType = strtoupper((string)$operation['operation_type']);
            self::assertOperationType($operationType);

            $operationRemaining = FinanceAllocationService::getOperationRemainingAmount(
                $pdo,
                $operationId,
                (string)$operation['amount']
            );
            if (self::compareMoney($amount, $operationRemaining) > 0) {
                throw new RuntimeException('Сумма оплаты превышает нераспределённый остаток финансовой операции.');
            }

            $invoice = self::fetchInvoice($pdo, $invoiceId, $operationType, true);
            if (self::compareMoney($amount, (string)$invoice['remaining_amount']) > 0) {
                throw new RuntimeException('Сумма оплаты превышает остаток по счёту №' . ((string)($invoice['number'] ?? '') ?: $invoiceId) . '.');
            }
            self::assertCounterpartyCompatible($operation, $invoice);

            $created = self::insertAllocations($pdo, $operation, $invoice, $amount, $user, $comment);
            foreach ($created as $allocationId) {
                FinanceSettlementCascadeService::cascadeAfterAllocationCreate($pdo, $allocationId);
            }

            $operationRemainingAfter = FinanceAllocationService::getOperationRemainingAmount($pdo, $operationId, (string)$operation['amount']);
            $invoiceRemainingAfter = FinanceSettlementCascadeService::getInvoiceRemainingAmount($pdo, $invoiceId, (string)$invoice['amount']);

            if ($owns) $pdo->commit();
            return [
                'operation_id' => $operationId,
                'invoice_id' => $invoiceId,
                'amount' => $amount,
                'allocation_ids' => $created,
                'operation_remaining' => $operationRemainingAfter,
                'invoice_remaining' => $invoiceRemainingAfter,
            ];
        } catch (Throwable $e) {
            if ($owns && $pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    /** Cancel all active allocations connecting one operation to one invoice. */
    public static function cancelOperationInvoiceAllocations(PDO $pdo, int $operationId, int $invoiceId, array $user, string $reason): int
    {
        $reason = trim($reason);
        if ($reason === '') $reason = 'Отмена связанной оплаты счёта';
        [$userId, $role] = self::actor($user);
        $stmt = $pdo->prepare(
            "SELECT id FROM finance_operation_allocations
              WHERE operation_id=? AND invoice_id=? AND cancelled_at IS NULL
              ORDER BY id ASC FOR UPDATE"
        );
        $stmt->execute([$operationId, $invoiceId]);
        $ids = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        if ($ids === []) return 0;

        $upd = $pdo->prepare(
            "UPDATE finance_operation_allocations
                SET cancelled_at=NOW(),cancelled_by_user_id=?,cancelled_by_role=?,cancel_reason=?
              WHERE id=? AND cancelled_at IS NULL"
        );
        foreach ($ids as $id) {
            $upd->execute([$userId ?: null, $role, $reason, $id]);
            if ($upd->rowCount() === 1) {
                FinanceAuditLogService::log($pdo, 'finance_allocation', $id, 'allocation_cancel', null, [
                    'operation_id' => $operationId,
                    'invoice_id' => $invoiceId,
                    'cancel_reason' => $reason,
                    'linked_event' => true,
                ], $userId, $role);
                FinanceSettlementCascadeService::cascadeAfterAllocationCancel($pdo, $id);
            }
        }
        return count($ids);
    }

    private static function insertAllocations(PDO $pdo, array $operation, array $invoice, string $amount, array $user, ?string $comment): array
    {
        $invoiceId = (int)$invoice['id'];
        $linksStmt = $pdo->prepare(
            "SELECT l.*,o.source_id,o.source_parent_id,o.source_parent_type,o.source_type,o.due_date,
                    COALESCE((SELECT SUM(a.amount)
                                FROM finance_operation_allocations a
                               WHERE a.invoice_id=l.invoice_id
                                 AND a.obligation_id=l.obligation_id
                                 AND a.cancelled_at IS NULL),0) AS allocated_amount
               FROM finance_invoice_links l
          LEFT JOIN finance_obligations o ON o.id=l.obligation_id
              WHERE l.invoice_id=?
              ORDER BY CASE WHEN o.due_date IS NULL THEN 1 ELSE 0 END,o.due_date,l.id"
        );
        $linksStmt->execute([$invoiceId]);
        $links = $linksStmt->fetchAll(PDO::FETCH_ASSOC);
        [$userId, $role] = self::actor($user);
        $allocationDate = (string)($operation['operation_date'] ?? date('Y-m-d'));
        $comment = trim((string)$comment) ?: 'Ручная оплата счёта из финансовой операции.';

        if ($links === []) {
            $insert = $pdo->prepare(
                "INSERT INTO finance_operation_allocations
                    (operation_id,invoice_id,amount,allocation_date,method,comment,created_by_user_id,created_by_role)
                 VALUES (?,?,?,?,'manual',?,?,?)"
            );
            $insert->execute([(int)$operation['id'],$invoiceId,$amount,$allocationDate,$comment,$userId ?: null,$role]);
            $id = (int)$pdo->lastInsertId();
            FinanceAuditLogService::log($pdo, 'finance_allocation', $id, 'allocation_create', null, [
                'operation_id'=>(int)$operation['id'],'invoice_id'=>$invoiceId,'amount'=>$amount,'generic_invoice_settlement'=>true,
            ], $userId, $role);
            return [$id];
        }

        $capacity = '0.00';
        foreach ($links as $link) {
            if (empty($link['obligation_id'])) continue;
            $free = self::subMoney((string)$link['amount'], (string)$link['allocated_amount']);
            if (self::compareMoney($free, '0.00') > 0) $capacity = self::addMoney($capacity, $free);
        }
        if (self::compareMoney($capacity, $amount) < 0) {
            throw new RuntimeException('Связанные обязательства счёта не имеют достаточного свободного остатка.');
        }

        $remaining = $amount;
        $created = [];
        $insert = $pdo->prepare(
            "INSERT INTO finance_operation_allocations
                (operation_id,invoice_id,obligation_id,linear_route_id,linear_route_payment_id,amount,allocation_date,method,comment,created_by_user_id,created_by_role)
             VALUES (?,?,?,?,?,?,?,'manual',?,?,?)"
        );
        foreach ($links as $link) {
            if (self::compareMoney($remaining, '0.00') <= 0 || empty($link['obligation_id'])) break;
            $free = self::subMoney((string)$link['amount'], (string)$link['allocated_amount']);
            if (self::compareMoney($free, '0.00') <= 0) continue;
            $part = self::compareMoney($free, $remaining) <= 0 ? $free : $remaining;
            $insert->execute([
                (int)$operation['id'],
                $invoiceId,
                (int)$link['obligation_id'],
                (string)($link['source_parent_type'] ?? '') === 'LINEAR_ROUTE' ? (int)$link['source_parent_id'] : null,
                (string)($link['source_type'] ?? '') === 'LINEAR_ROUTE_PAYMENT' ? (int)$link['source_id'] : null,
                $part,
                $allocationDate,
                $comment,
                $userId ?: null,
                $role,
            ]);
            $id = (int)$pdo->lastInsertId();
            $created[] = $id;
            FinanceAuditLogService::log($pdo, 'finance_allocation', $id, 'allocation_create', null, [
                'operation_id'=>(int)$operation['id'],'invoice_id'=>$invoiceId,'obligation_id'=>(int)$link['obligation_id'],'amount'=>$part,'generic_invoice_settlement'=>true,
            ], $userId, $role);
            $remaining = self::subMoney($remaining, $part);
        }
        if (self::compareMoney($remaining, '0.00') !== 0) {
            throw new RuntimeException('Не удалось полностью распределить сумму оплаты по обязательствам счёта.');
        }
        return $created;
    }

    private static function assertCounterpartyCompatible(array $operation, array $invoice): void
    {
        $opType = trim((string)($operation['counterparty_entity_type'] ?? ''));
        $opId = (int)($operation['counterparty_entity_id'] ?? 0);
        if ($opType === '' || $opId <= 0) return;
        $invoiceType = trim((string)($invoice['counterparty_entity_type'] ?? ''));
        $invoiceId = (int)($invoice['counterparty_entity_id'] ?? 0);
        if ($invoiceType !== $opType || $invoiceId !== $opId) {
            throw new RuntimeException('Счёт относится к другому контрагенту.');
        }
    }

    private static function assertOperationType(string $type): void
    {
        if (!in_array($type, ['INCOME','EXPENSE'], true)) {
            throw new RuntimeException('Для оплаты счёта нужна операция прихода или расхода.');
        }
    }

    public static function normalizePositiveMoney(string $value): ?string
    {
        $value = str_replace([' ', ','], ['', '.'], trim($value));
        if (!preg_match('/^\d+(?:\.\d{1,2})?$/D', $value)) return null;
        $value = self::money($value);
        return self::compareMoney($value, '0.00') > 0 ? $value : null;
    }

    public static function money(string $value): string
    {
        $value = trim(str_replace(',', '.', $value));
        if (!preg_match('/^-?\d+(?:\.\d+)?$/D', $value)) return '0.00';
        $negative = str_starts_with($value, '-');
        if ($negative) $value = substr($value, 1);
        [$whole,$fraction] = array_pad(explode('.', $value, 2), 2, '');
        $whole = ltrim($whole, '0');
        if ($whole === '') $whole = '0';
        $fraction = str_pad(substr($fraction, 0, 2), 2, '0');
        return ($negative ? '-' : '') . $whole . '.' . $fraction;
    }

    private static function compareMoney(string $a, string $b): int { return self::toCents($a) <=> self::toCents($b); }
    private static function addMoney(string $a, string $b): string { return self::fromCents(self::toCents($a)+self::toCents($b)); }
    private static function subMoney(string $a, string $b): string { return self::fromCents(self::toCents($a)-self::toCents($b)); }
    private static function toCents(string $v): int {
        $v=self::money($v);$neg=str_starts_with($v,'-');if($neg)$v=substr($v,1);[$w,$f]=explode('.',$v,2);$c=((int)$w*100)+(int)$f;return $neg?-$c:$c;
    }
    private static function fromCents(int $c): string { $n=$c<0;$c=abs($c);return ($n?'-':'').intdiv($c,100).'.'.str_pad((string)($c%100),2,'0',STR_PAD_LEFT); }
    private static function actor(array $user): array {
        return [(int)($user['user_id'] ?? $user['id'] ?? 0), (string)($user['role_code'] ?? $user['role'] ?? 'company_owner')];
    }
}
