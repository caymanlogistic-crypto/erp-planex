<?php

namespace App\Service;

use PDO;
use RuntimeException;
use Throwable;

/** Cash-side business actions built on the generic invoice settlement layer. */
final class FinanceCashInvoiceEventService
{
    public static function fetchClientInvoices(PDO $pdo): array
    {
        return FinanceOperationInvoiceSettlementService::fetchOpenInvoices($pdo, 'INCOME');
    }

    public static function fetchCarrierInvoices(PDO $pdo): array
    {
        return FinanceOperationInvoiceSettlementService::fetchOpenInvoices($pdo, 'EXPENSE');
    }

    /**
     * Cash received from a client. The received amount may exceed the amount
     * allocated to the invoice; the remainder physically stays in Main Cash.
     */
    public static function createClientCashReceipt(PDO $pdo, array $data, array $user): array
    {
        $invoiceId = (int)($data['invoice_id'] ?? 0);
        $received = FinanceOperationInvoiceSettlementService::normalizePositiveMoney((string)($data['amount'] ?? ''));
        $allocated = FinanceOperationInvoiceSettlementService::normalizePositiveMoney((string)($data['invoice_amount'] ?? ''));
        $date = self::date((string)($data['operation_date'] ?? ''));
        if ($received === null) throw new \InvalidArgumentException('Укажите сумму полученных наличных.');
        if ($allocated === null) throw new \InvalidArgumentException('Укажите сумму, которую нужно зачесть в оплату счёта.');
        if (self::cents($allocated) > self::cents($received)) {
            throw new \InvalidArgumentException('На счёт нельзя зачесть больше, чем фактически получено наличными.');
        }

        $owns = !$pdo->inTransaction();
        if ($owns) $pdo->beginTransaction();
        try {
            $invoice = FinanceOperationInvoiceSettlementService::fetchInvoice($pdo, $invoiceId, 'INCOME', true);
            if (self::cents($allocated) > self::cents((string)$invoice['remaining_amount'])) {
                throw new RuntimeException('Сумма зачёта превышает остаток по счёту.');
            }
            $mainCash = FinanceCashResolutionService::findMainCashAccount($pdo, true);
            if (!$mainCash) throw new RuntimeException('Основная касса не найдена или неактивна.');

            $number = trim((string)($invoice['number'] ?? '')) ?: ('#'.$invoiceId);
            $clientName = (string)$invoice['counterparty_display_name'];
            $purpose = 'Наличная оплата от клиента ' . $clientName . ' · счёт ' . $number;
            $comment = trim((string)($data['comment'] ?? '')) ?: null;
            $operationId = FinanceCashService::createCashOperation($pdo, [
                'operation_type'=>'INCOME',
                'money_account_id'=>(int)$mainCash['id'],
                'amount'=>$received,
                'operation_date'=>$date,
                'purpose'=>$purpose,
                'comment'=>$comment,
            ], self::cashUser($user));
            self::bindCounterparty($pdo, $operationId, $invoice);

            $settlement = FinanceOperationInvoiceSettlementService::allocate(
                $pdo,
                $operationId,
                $invoiceId,
                $allocated,
                $user,
                'Наличная оплата клиента по счёту.'
            );

            FinanceAuditLogService::log($pdo, 'finance_operation', $operationId, 'client_cash_invoice_receipt', null, [
                'invoice_id'=>$invoiceId,
                'received_amount'=>$received,
                'allocated_amount'=>$allocated,
                'unallocated_amount'=>self::fromCents(self::cents($received)-self::cents($allocated)),
                'cash_stays_in_main_cash'=>true,
            ], self::userId($user), self::role($user));

            if ($owns) $pdo->commit();
            return [
                'operation_id'=>$operationId,
                'invoice_id'=>$invoiceId,
                'invoice_number'=>$number,
                'client_name'=>$clientName,
                'amount'=>$received,
                'allocated_amount'=>$allocated,
                'remaining_operation_amount'=>$settlement['operation_remaining'],
            ];
        } catch (Throwable $e) {
            if ($owns && $pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    /** Pay an incoming carrier invoice directly from Main Cash. */
    public static function payCarrierInvoiceFromMainCash(PDO $pdo, array $data, array $user): array
    {
        $invoiceId = (int)($data['invoice_id'] ?? 0);
        $amount = FinanceOperationInvoiceSettlementService::normalizePositiveMoney((string)($data['amount'] ?? ''));
        $date = self::date((string)($data['operation_date'] ?? ''));
        if ($amount === null) throw new \InvalidArgumentException('Укажите сумму оплаты.');

        $owns = !$pdo->inTransaction();
        if ($owns) $pdo->beginTransaction();
        try {
            $invoice = FinanceOperationInvoiceSettlementService::fetchInvoice($pdo, $invoiceId, 'EXPENSE', true);
            if (self::cents($amount) > self::cents((string)$invoice['remaining_amount'])) {
                throw new RuntimeException('Сумма оплаты превышает остаток по счёту.');
            }
            $mainCash = FinanceCashResolutionService::findMainCashAccount($pdo, true);
            if (!$mainCash) throw new RuntimeException('Основная касса не найдена или неактивна.');
            $number = trim((string)($invoice['number'] ?? '')) ?: ('#'.$invoiceId);
            $carrier = (string)$invoice['counterparty_display_name'];
            $comment = trim((string)($data['comment'] ?? '')) ?: null;
            $operationId = FinanceCashService::createCashOperation($pdo, [
                'operation_type'=>'EXPENSE',
                'money_account_id'=>(int)$mainCash['id'],
                'amount'=>$amount,
                'operation_date'=>$date,
                'purpose'=>'Оплата перевозчику ' . $carrier . ' · счёт ' . $number,
                'comment'=>$comment,
            ], self::cashUser($user));
            self::bindCounterparty($pdo, $operationId, $invoice);
            $settlement = FinanceOperationInvoiceSettlementService::allocate(
                $pdo,$operationId,$invoiceId,$amount,$user,'Наличная оплата входящего счёта из Основной кассы.'
            );
            FinanceAuditLogService::log($pdo, 'finance_operation', $operationId, 'main_cash_invoice_payment', null, [
                'invoice_id'=>$invoiceId,'amount'=>$amount,'carrier'=>$carrier,
            ], self::userId($user), self::role($user));
            if ($owns) $pdo->commit();
            return ['operation_id'=>$operationId,'invoice_id'=>$invoiceId,'invoice_number'=>$number,'carrier_name'=>$carrier,'amount'=>$amount,'invoice_remaining'=>$settlement['invoice_remaining']];
        } catch (Throwable $e) {
            if ($owns && $pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    /** Fungible Main Cash -> employee handoff. Not tied to any source receipt. */
    public static function transferMainCashToEmployee(PDO $pdo, array $data, array $user, array $employee): array
    {
        $amount = FinanceOperationInvoiceSettlementService::normalizePositiveMoney((string)($data['amount'] ?? ''));
        $date = self::date((string)($data['operation_date'] ?? ''));
        if ($amount === null) throw new \InvalidArgumentException('Укажите сумму передачи сотруднику.');
        $owns = !$pdo->inTransaction();
        if ($owns) $pdo->beginTransaction();
        try {
            $mainCash = FinanceCashResolutionService::findMainCashAccount($pdo, true);
            if (!$mainCash) throw new RuntimeException('Основная касса не найдена или неактивна.');
            $comment = trim((string)($data['comment'] ?? '')) ?: null;
            $purpose = 'Передано сотруднику: ' . (string)$employee['full_name'];
            $movement = FinanceEmployeePaymentService::createCashMovement($pdo, [
                'movement_type'=>'PAYMENT',
                'money_account_id'=>(int)$mainCash['id'],
                'amount'=>$amount,
                'operation_date'=>$date,
                'purpose'=>$purpose,
                'comment'=>$comment,
            ], self::cashUser($user), $employee);
            $operationId = (int)$movement['finance_operation_id'];
            $group = 'MAIN_EMPLOYEE_' . bin2hex(random_bytes(12));
            $upd = $pdo->prepare("UPDATE finance_operations
                SET operation_type='TRANSFER',source='TRANSFER',transfer_direction='out',transfer_account_id=NULL,transfer_group_id=?
                WHERE id=? AND status='POSTED'");
            $upd->execute([$group,$operationId]);
            if ($upd->rowCount() !== 1) throw new RuntimeException('Не удалось сформировать передачу денег сотруднику.');
            $pdo->prepare('UPDATE finance_employee_movements SET note=? WHERE id=?')->execute([
                'Получено из Основной кассы', (int)$movement['movement_id'],
            ]);
            FinanceAuditLogService::log($pdo, 'finance_operation', $operationId, 'main_cash_employee_transfer', null, [
                'employee_ref'=>(string)$employee['ref'],'amount'=>$amount,'transfer_group_id'=>$group,'economic_expense'=>false,
            ], self::userId($user), self::role($user));
            if ($owns) $pdo->commit();
            return ['finance_operation_id'=>$operationId,'employee_movement_id'=>(int)$movement['movement_id'],'employee'=>$employee,'amount'=>$amount,'transfer_group_id'=>$group];
        } catch (Throwable $e) {
            if ($owns && $pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    private static function bindCounterparty(PDO $pdo, int $operationId, array $invoice): void
    {
        $stmt = $pdo->prepare('UPDATE finance_operations SET counterparty_entity_type=?,counterparty_entity_id=?,counterparty_name=? WHERE id=?');
        $stmt->execute([
            (string)($invoice['counterparty_entity_type'] ?? ''),
            !empty($invoice['counterparty_entity_id']) ? (int)$invoice['counterparty_entity_id'] : null,
            (string)$invoice['counterparty_display_name'],
            $operationId,
        ]);
    }

    private static function date(string $date): string
    {
        $date=trim($date);$d=\DateTimeImmutable::createFromFormat('!Y-m-d',$date);
        if(!$d||$d->format('Y-m-d')!==$date)throw new \InvalidArgumentException('Укажите корректную дату.');
        return $date;
    }
    private static function cashUser(array $u): array{return ['id'=>self::userId($u)?:null,'role'=>self::role($u),'user_id'=>self::userId($u),'role_code'=>self::role($u)];}
    private static function userId(array $u): int{return (int)($u['user_id']??$u['id']??0);}
    private static function role(array $u): string{return (string)($u['role_code']??$u['role']??'company_owner');}
    private static function cents(string $v): int{$v=FinanceOperationInvoiceSettlementService::money($v);$n=str_starts_with($v,'-');if($n)$v=substr($v,1);[$w,$f]=explode('.',$v,2);$c=((int)$w*100)+(int)$f;return $n?-$c:$c;}
    private static function fromCents(int $c): string{$n=$c<0;$c=abs($c);return ($n?'-':'').intdiv($c,100).'.'.str_pad((string)($c%100),2,'0',STR_PAD_LEFT);}
}
