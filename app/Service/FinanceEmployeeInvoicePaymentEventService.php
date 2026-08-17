<?php

namespace App\Service;

use PDO;
use RuntimeException;
use Throwable;

/**
 * Employee-held cash pays a carrier invoice.
 *
 * Employee balance decreases through RETURN. Main Cash receives a technical
 * TRANSFER IN and immediately pays the incoming invoice through a real EXPENSE.
 * Main Cash net effect is zero; invoice/obligation settlement is real.
 */
final class FinanceEmployeeInvoicePaymentEventService
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
            $mainCash = FinanceCashResolutionService::findMainCashAccount($pdo, true);
            if (!$mainCash) throw new RuntimeException('Основная касса не найдена или неактивна.');

            $group = 'EMP_INVOICE_' . bin2hex(random_bytes(12));
            $invoiceNumber = trim((string)($invoice['number'] ?? '')) ?: ('#'.$invoiceId);
            $carrier = (string)$invoice['counterparty_display_name'];
            $purpose = 'Оплата счёта ' . $invoiceNumber . ' · ' . $carrier . ' · сотрудник ' . (string)$employee['full_name'];

            $movement = FinanceEmployeePaymentService::createCashMovement($pdo, [
                'movement_type'=>'RETURN',
                'money_account_id'=>(int)$mainCash['id'],
                'amount'=>$amount,
                'operation_date'=>$date,
                'purpose'=>$purpose,
                'comment'=>$comment,
            ], self::actorUser($user), $employee);
            $receiptOperationId = (int)$movement['finance_operation_id'];
            $employeeMovementId = (int)$movement['movement_id'];

            $technical = $pdo->prepare("UPDATE finance_operations
                SET operation_type='TRANSFER',source='TRANSFER',transfer_direction='in',transfer_account_id=NULL,transfer_group_id=?,counterparty_name=?
                WHERE id=? AND status='POSTED'");
            $technical->execute([$group,(string)$employee['full_name'],$receiptOperationId]);
            if ($technical->rowCount() !== 1) throw new RuntimeException('Не удалось сформировать техническое поступление от сотрудника.');
            $pdo->prepare('UPDATE finance_employee_movements SET note=? WHERE id=?')->execute([
                'Оплата входящего счёта ' . $invoiceNumber . ' · ' . $carrier,
                $employeeMovementId,
            ]);

            $expenseOperationId = FinanceCashService::createCashOperation($pdo, [
                'operation_type'=>'EXPENSE',
                'money_account_id'=>(int)$mainCash['id'],
                'amount'=>$amount,
                'operation_date'=>$date,
                'purpose'=>$purpose,
                'comment'=>$comment,
            ], self::actorUser($user));
            self::bindExpense($pdo, $expenseOperationId, $invoice, $group, $purpose, $comment, $date, $amount);

            FinanceOperationInvoiceSettlementService::allocate(
                $pdo,$expenseOperationId,$invoiceId,$amount,$user,'Оплата входящего счёта сотрудником из находящихся у него наличных.'
            );

            $insert = $pdo->prepare("INSERT INTO finance_employee_invoice_payments
                (event_group_id,employee_identity_type,employee_identity_id,employee_name_snapshot,employee_role_snapshot,
                 invoice_id,invoice_number_snapshot,counterparty_name_snapshot,operation_date,amount,currency,
                 employee_movement_id,receipt_finance_operation_id,expense_finance_operation_id,comment,status,
                 created_by_user_id,created_by_role)
                VALUES (?,?,?,?,?,?,?,?,?,?,'RUR',?,?,?,?, 'POSTED',?,?)");
            $insert->execute([
                $group,(string)$employee['identity_type'],(int)$employee['identity_id'],(string)$employee['full_name'],(string)($employee['role_code']??''),
                $invoiceId,$invoiceNumber,$carrier,$date,$amount,$employeeMovementId,$receiptOperationId,$expenseOperationId,$comment,
                self::userId($user)?:null,self::role($user),
            ]);
            $eventId = (int)$pdo->lastInsertId();

            FinanceAuditLogService::log($pdo, 'finance_employee_invoice_payment', $eventId, 'create', null, [
                'event_group_id'=>$group,'employee_ref'=>(string)$employee['ref'],'invoice_id'=>$invoiceId,'amount'=>$amount,
                'employee_movement_id'=>$employeeMovementId,'receipt_finance_operation_id'=>$receiptOperationId,'expense_finance_operation_id'=>$expenseOperationId,
                'main_cash_net_effect'=>'0.00',
            ], self::userId($user), self::role($user));

            if ($owns) $pdo->commit();
            return self::fetchOne($pdo,$eventId) ?? ['id'=>$eventId];
        } catch (Throwable $e) {
            if ($owns && $pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    public static function update(PDO $pdo, int $eventId, array $data, array $user): array
    {
        if ($eventId <= 0) throw new \InvalidArgumentException('Операция не найдена.');
        $amount = self::amount((string)($data['amount'] ?? ''));
        $date = self::date((string)($data['operation_date'] ?? ''));
        $invoiceId = (int)($data['invoice_id'] ?? 0);
        $comment = trim((string)($data['comment'] ?? '')) ?: null;
        $owns = !$pdo->inTransaction();
        if ($owns) $pdo->beginTransaction();
        try {
            $event = self::fetchOneForUpdate($pdo,$eventId);
            if (!$event) throw new \InvalidArgumentException('Операция не найдена.');
            if ((string)$event['status'] !== 'POSTED') throw new RuntimeException('Отменённую операцию нельзя редактировать.');
            $before = $event;

            FinanceOperationInvoiceSettlementService::cancelOperationInvoiceAllocations(
                $pdo,(int)$event['expense_finance_operation_id'],(int)$event['invoice_id'],$user,'Пересохранение оплаты счёта сотрудником'
            );
            $invoice = FinanceOperationInvoiceSettlementService::fetchInvoice($pdo,$invoiceId,'EXPENSE',true);
            if (self::cents($amount) > self::cents((string)$invoice['remaining_amount'])) {
                throw new RuntimeException('Сумма оплаты превышает остаток по выбранному счёту.');
            }
            $invoiceNumber = trim((string)($invoice['number'] ?? '')) ?: ('#'.$invoiceId);
            $carrier = (string)$invoice['counterparty_display_name'];
            $purpose = 'Оплата счёта ' . $invoiceNumber . ' · ' . $carrier . ' · сотрудник ' . (string)$event['employee_name_snapshot'];

            $receipt = $pdo->prepare("UPDATE finance_operations SET operation_date=?,amount=?,purpose=?,comment=?,counterparty_name=? WHERE id=? AND status='POSTED'");
            $receipt->execute([$date,$amount,$purpose,$comment,(string)$event['employee_name_snapshot'],(int)$event['receipt_finance_operation_id']]);
            self::assertPosted($pdo,(int)$event['receipt_finance_operation_id']);
            self::bindExpense($pdo,(int)$event['expense_finance_operation_id'],$invoice,(string)$event['event_group_id'],$purpose,$comment,$date,$amount);
            self::assertPosted($pdo,(int)$event['expense_finance_operation_id']);
            $pdo->prepare('UPDATE finance_employee_movements SET note=? WHERE id=?')->execute([
                'Оплата входящего счёта ' . $invoiceNumber . ' · ' . $carrier,(int)$event['employee_movement_id'],
            ]);

            FinanceOperationInvoiceSettlementService::allocate(
                $pdo,(int)$event['expense_finance_operation_id'],$invoiceId,$amount,$user,'Оплата входящего счёта сотрудником из находящихся у него наличных.'
            );
            $pdo->prepare("UPDATE finance_employee_invoice_payments
                SET invoice_id=?,invoice_number_snapshot=?,counterparty_name_snapshot=?,operation_date=?,amount=?,comment=?
                WHERE id=?")->execute([$invoiceId,$invoiceNumber,$carrier,$date,$amount,$comment,$eventId]);

            $after=self::fetchOne($pdo,$eventId);
            FinanceAuditLogService::log($pdo,'finance_employee_invoice_payment',$eventId,'update',self::audit($before),self::audit($after?:[]),self::userId($user),self::role($user));
            if($owns)$pdo->commit();
            return $after?:['id'=>$eventId];
        }catch(Throwable $e){if($owns&&$pdo->inTransaction())$pdo->rollBack();throw $e;}
    }

    public static function cancel(PDO $pdo, int $eventId, array $user, ?string $reason=null): array
    {
        if($eventId<=0)throw new \InvalidArgumentException('Операция не найдена.');
        $reason=trim((string)$reason)?:'Отмена оплаты счёта сотрудником';
        $owns=!$pdo->inTransaction();if($owns)$pdo->beginTransaction();
        try{
            $event=self::fetchOneForUpdate($pdo,$eventId);
            if(!$event)throw new \InvalidArgumentException('Операция не найдена.');
            if((string)$event['status']==='CANCELLED'){if($owns)$pdo->commit();return $event;}
            FinanceOperationInvoiceSettlementService::cancelOperationInvoiceAllocations($pdo,(int)$event['expense_finance_operation_id'],(int)$event['invoice_id'],$user,$reason);
            $cancel=$pdo->prepare("UPDATE finance_operations SET status='CANCELLED',cancelled_at=NOW(),cancelled_by_user_id=?,cancelled_by_role=?,cancellation_reason=? WHERE id=? AND status='POSTED'");
            foreach([(int)$event['receipt_finance_operation_id'],(int)$event['expense_finance_operation_id']] as $opId){
                $cancel->execute([self::userId($user)?:null,self::role($user),$reason,$opId]);
            }
            $pdo->prepare("UPDATE finance_employee_invoice_payments SET status='CANCELLED',cancelled_at=NOW(),cancelled_by_user_id=?,cancelled_by_role=?,cancellation_reason=? WHERE id=?")
                ->execute([self::userId($user)?:null,self::role($user),$reason,$eventId]);
            $after=self::fetchOne($pdo,$eventId);
            FinanceAuditLogService::log($pdo,'finance_employee_invoice_payment',$eventId,'cancel',self::audit($event),self::audit($after?:[]),self::userId($user),self::role($user));
            if($owns)$pdo->commit();return $after?:['id'=>$eventId,'status'=>'CANCELLED'];
        }catch(Throwable $e){if($owns&&$pdo->inTransaction())$pdo->rollBack();throw $e;}
    }

    public static function fetchForEmployee(PDO $pdo,string $employeeRef,int $limit=100):array
    {
        [$type,$id]=FinanceEmployeePaymentService::parseEmployeeRef($employeeRef);
        $limit=max(1,min(200,$limit));
        $stmt=$pdo->prepare("SELECT p.*,i.status AS invoice_status
            FROM finance_employee_invoice_payments p
            LEFT JOIN finance_invoices i ON i.id=p.invoice_id
            WHERE p.employee_identity_type=:employee_type AND p.employee_identity_id=:employee_id
            ORDER BY p.operation_date DESC,p.id DESC LIMIT :limit");
        $stmt->bindValue(':employee_type',$type);$stmt->bindValue(':employee_id',$id,PDO::PARAM_INT);$stmt->bindValue(':limit',$limit,PDO::PARAM_INT);$stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function fetchOne(PDO $pdo,int $eventId):?array
    {
        $stmt=$pdo->prepare('SELECT * FROM finance_employee_invoice_payments WHERE id=? LIMIT 1');$stmt->execute([$eventId]);$row=$stmt->fetch(PDO::FETCH_ASSOC);return $row?:null;
    }
    private static function fetchOneForUpdate(PDO $pdo,int $eventId):?array
    {
        $stmt=$pdo->prepare('SELECT * FROM finance_employee_invoice_payments WHERE id=? FOR UPDATE');$stmt->execute([$eventId]);$row=$stmt->fetch(PDO::FETCH_ASSOC);return $row?:null;
    }
    private static function bindExpense(PDO $pdo,int $operationId,array $invoice,string $group,string $purpose,?string $comment,string $date,string $amount):void
    {
        $stmt=$pdo->prepare("UPDATE finance_operations SET operation_date=?,amount=?,purpose=?,comment=?,counterparty_entity_type=?,counterparty_entity_id=?,counterparty_name=?,transfer_group_id=? WHERE id=? AND status='POSTED'");
        $stmt->execute([$date,$amount,$purpose,$comment,(string)($invoice['counterparty_entity_type']??''),!empty($invoice['counterparty_entity_id'])?(int)$invoice['counterparty_entity_id']:null,(string)$invoice['counterparty_display_name'],$group,$operationId]);
    }
    private static function assertPosted(PDO $pdo,int $id):void{$s=$pdo->prepare('SELECT status FROM finance_operations WHERE id=?');$s->execute([$id]);if($s->fetchColumn()!=='POSTED')throw new RuntimeException('Связанная финансовая операция недоступна для изменения.');}
    private static function assertEmployee(array $e):void{if(empty($e['identity_type'])||(int)($e['identity_id']??0)<=0||trim((string)($e['full_name']??''))==='')throw new \InvalidArgumentException('Выберите сотрудника.');}
    private static function amount(string $v):string{$a=FinanceOperationInvoiceSettlementService::normalizePositiveMoney($v);if($a===null)throw new \InvalidArgumentException('Укажите корректную сумму оплаты.');return $a;}
    private static function date(string $v):string{$v=trim($v);$d=\DateTimeImmutable::createFromFormat('!Y-m-d',$v);if(!$d||$d->format('Y-m-d')!==$v)throw new \InvalidArgumentException('Укажите корректную дату.');return $v;}
    private static function cents(string $v):int{$v=FinanceOperationInvoiceSettlementService::money($v);$n=str_starts_with($v,'-');if($n)$v=substr($v,1);[$w,$f]=explode('.',$v,2);$c=((int)$w*100)+(int)$f;return $n?-$c:$c;}
    private static function actorUser(array $u):array{return ['id'=>self::userId($u)?:null,'role'=>self::role($u),'user_id'=>self::userId($u),'role_code'=>self::role($u)];}
    private static function userId(array $u):int{return (int)($u['user_id']??$u['id']??0);}
    private static function role(array $u):string{return (string)($u['role_code']??$u['role']??'company_owner');}
    private static function audit(array $r):array{return ['status'=>$r['status']??null,'invoice_id'=>$r['invoice_id']??null,'operation_date'=>$r['operation_date']??null,'amount'=>$r['amount']??null,'employee_movement_id'=>$r['employee_movement_id']??null,'receipt_finance_operation_id'=>$r['receipt_finance_operation_id']??null,'expense_finance_operation_id'=>$r['expense_finance_operation_id']??null];}
}
