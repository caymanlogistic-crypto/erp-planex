<?php

namespace App\Service;

use PDO;
use RuntimeException;
use Throwable;

/** Edit/cancel a client payment received directly by an employee. */
final class FinanceEmployeeClientReceiptEditService
{
    public static function fetchForEmployee(PDO $pdo, string $employeeRef): array
    {
        [$type, $id] = FinanceEmployeePaymentService::parseEmployeeRef($employeeRef);
        $stmt = $pdo->prepare(
            "SELECT m.id AS movement_id,m.finance_operation_id,m.employee_identity_type,m.employee_identity_id,
                    o.operation_date,o.amount,o.comment,o.status,
                    MIN(a.invoice_id) AS invoice_id,COUNT(DISTINCT a.invoice_id) AS invoice_count,
                    i.number AS invoice_number,i.counterparty_name AS invoice_counterparty_name,
                    COALESCE(c.name,ct.name,i.counterparty_name,o.counterparty_name) AS counterparty_name
               FROM finance_employee_movements m
               JOIN finance_operations o ON o.id=m.finance_operation_id
          LEFT JOIN finance_operation_allocations a ON a.operation_id=o.id AND a.cancelled_at IS NULL
          LEFT JOIN finance_invoices i ON i.id=a.invoice_id
          LEFT JOIN clients c ON i.counterparty_entity_type='client' AND c.id=i.counterparty_entity_id AND c.deleted_at IS NULL
          LEFT JOIN contractors ct ON i.counterparty_entity_type='contractor' AND ct.id=i.counterparty_entity_id AND ct.deleted_at IS NULL
              WHERE m.employee_identity_type=? AND m.employee_identity_id=?
                AND m.source_type='CLIENT' AND o.operation_type='INCOME' AND o.source='EMPLOYEE'
              GROUP BY m.id,m.finance_operation_id,m.employee_identity_type,m.employee_identity_id,
                       o.operation_date,o.amount,o.comment,o.status,i.number,i.counterparty_name,
                       c.name,ct.name,o.counterparty_name
              ORDER BY o.operation_date DESC,m.id DESC"
        );
        $stmt->execute([$type,$id]);
        $result=[];
        foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row){
            if((int)$row['invoice_count']!==1 || (int)$row['invoice_id']<=0) continue;
            $result[(int)$row['movement_id']]=$row;
        }
        return $result;
    }

    public static function update(PDO $pdo, int $movementId, array $data, array $user): array
    {
        if($movementId<=0) throw new \InvalidArgumentException('Платёж не найден.');
        $amount=FinanceOperationInvoiceSettlementService::normalizePositiveMoney((string)($data['amount']??''));
        if($amount===null) throw new \InvalidArgumentException('Укажите корректную сумму.');
        $date=trim((string)($data['operation_date']??''));
        $parsed=\DateTimeImmutable::createFromFormat('!Y-m-d',$date);
        if(!$parsed || $parsed->format('Y-m-d')!==$date) throw new \InvalidArgumentException('Укажите корректную дату.');
        $invoiceId=(int)($data['invoice_id']??0);
        if($invoiceId<=0) throw new \InvalidArgumentException('Выберите счёт клиента.');
        $comment=trim((string)($data['comment']??'')) ?: null;
        [$uid,$role]=self::actor($user);
        $owns=!$pdo->inTransaction(); if($owns)$pdo->beginTransaction();
        try{
            $current=self::loadForUpdate($pdo,$movementId);
            if((string)$current['status']!=='POSTED') throw new RuntimeException('Отменённый платёж нельзя редактировать.');
            $operationId=(int)$current['finance_operation_id'];
            $oldInvoiceId=self::singleInvoiceId($pdo,$operationId,true);
            FinanceOperationInvoiceSettlementService::cancelOperationInvoiceAllocations(
                $pdo,$operationId,$oldInvoiceId,$user,'Корректировка оплаты клиента сотруднику'
            );
            $invoice=FinanceOperationInvoiceSettlementService::fetchInvoice($pdo,$invoiceId,'INCOME',true);
            if(self::cents($amount)>self::cents((string)$invoice['remaining_amount'])){
                throw new RuntimeException('Сумма превышает остаток по выбранному счёту клиента.');
            }
            $number=trim((string)($invoice['number']??'')) ?: ('#'.$invoiceId);
            $client=(string)$invoice['counterparty_display_name'];
            $purpose='Получено от клиента '.$client.' · счёт '.$number;
            $upd=$pdo->prepare("UPDATE finance_operations
                SET operation_date=?,amount=?,counterparty_entity_type=?,counterparty_entity_id=?,counterparty_name=?,purpose=?,comment=?
                WHERE id=? AND status='POSTED' AND operation_type='INCOME' AND source='EMPLOYEE'");
            $upd->execute([
                $date,$amount,(string)($invoice['counterparty_entity_type']??'') ?: null,
                !empty($invoice['counterparty_entity_id'])?(int)$invoice['counterparty_entity_id']:null,
                $client,$purpose,$comment,$operationId
            ]);
            if($upd->rowCount()!==1) throw new RuntimeException('Не удалось изменить платёж клиента.');
            $pdo->prepare('UPDATE finance_employee_movements SET note=? WHERE id=?')->execute([$purpose,$movementId]);
            FinanceOperationInvoiceSettlementService::allocate(
                $pdo,$operationId,$invoiceId,$amount,$user,'Оплата клиента получена сотрудником.'
            );
            FinanceAuditLogService::log($pdo,'finance_employee_movement',$movementId,'client_receipt_update',[
                'invoice_id'=>$oldInvoiceId,'operation_date'=>$current['operation_date'],'amount'=>$current['amount']
            ],[
                'invoice_id'=>$invoiceId,'operation_date'=>$date,'amount'=>$amount,'cash_account_used'=>false
            ],$uid,$role);
            if($owns)$pdo->commit();
            return ['movement_id'=>$movementId,'operation_id'=>$operationId,'invoice_id'=>$invoiceId,'amount'=>$amount];
        }catch(Throwable $e){if($owns&&$pdo->inTransaction())$pdo->rollBack();throw $e;}
    }

    public static function cancel(PDO $pdo, int $movementId, array $user, string $reason=''): array
    {
        if($movementId<=0) throw new \InvalidArgumentException('Платёж не найден.');
        $reason=trim($reason) ?: 'Удалено пользователем из взаиморасчётов с сотрудниками';
        [$uid,$role]=self::actor($user);
        $owns=!$pdo->inTransaction(); if($owns)$pdo->beginTransaction();
        try{
            $current=self::loadForUpdate($pdo,$movementId);
            if((string)$current['status']==='CANCELLED'){if($owns)$pdo->commit();return $current;}
            if((string)$current['status']!=='POSTED') throw new RuntimeException('Удалить можно только проведённый платёж.');
            $operationId=(int)$current['finance_operation_id'];
            $invoiceId=self::singleInvoiceId($pdo,$operationId,true);
            FinanceOperationInvoiceSettlementService::cancelOperationInvoiceAllocations($pdo,$operationId,$invoiceId,$user,$reason);
            $stmt=$pdo->prepare("UPDATE finance_operations
                SET status='CANCELLED',cancelled_at=NOW(),cancelled_by_user_id=?,cancelled_by_role=?,cancellation_reason=?
                WHERE id=? AND status='POSTED' AND operation_type='INCOME' AND source='EMPLOYEE'");
            $stmt->execute([$uid?:null,$role,$reason,$operationId]);
            if($stmt->rowCount()!==1) throw new RuntimeException('Не удалось удалить платёж клиента.');
            FinanceAuditLogService::log($pdo,'finance_employee_movement',$movementId,'client_receipt_cancel',[
                'status'=>'POSTED','invoice_id'=>$invoiceId,'amount'=>$current['amount']
            ],[
                'status'=>'CANCELLED','invoice_id'=>$invoiceId
            ],$uid,$role);
            if($owns)$pdo->commit();
            return ['movement_id'=>$movementId,'operation_id'=>$operationId,'invoice_id'=>$invoiceId,'status'=>'CANCELLED'];
        }catch(Throwable $e){if($owns&&$pdo->inTransaction())$pdo->rollBack();throw $e;}
    }

    private static function loadForUpdate(PDO $pdo,int $movementId):array
    {
        $stmt=$pdo->prepare("SELECT m.id AS movement_id,m.finance_operation_id,m.employee_identity_type,m.employee_identity_id,
                                   o.operation_date,o.amount,o.comment,o.status
                              FROM finance_employee_movements m
                              JOIN finance_operations o ON o.id=m.finance_operation_id
                             WHERE m.id=? AND m.source_type='CLIENT' AND o.operation_type='INCOME' AND o.source='EMPLOYEE'
                             FOR UPDATE");
        $stmt->execute([$movementId]);
        $row=$stmt->fetch(PDO::FETCH_ASSOC);
        if(!$row) throw new RuntimeException('Платёж клиента не найден или относится к исторической модели.');
        return $row;
    }

    private static function singleInvoiceId(PDO $pdo,int $operationId,bool $forUpdate=false):int
    {
        $sql="SELECT DISTINCT invoice_id FROM finance_operation_allocations WHERE operation_id=? AND cancelled_at IS NULL ORDER BY invoice_id";
        if($forUpdate)$sql.=' FOR UPDATE';
        $stmt=$pdo->prepare($sql);$stmt->execute([$operationId]);
        $ids=array_map('intval',$stmt->fetchAll(PDO::FETCH_COLUMN));
        if(count($ids)!==1 || $ids[0]<=0) throw new RuntimeException('Связь платежа со счётом не определена однозначно.');
        return $ids[0];
    }

    private static function actor(array $u):array
    {
        return [(int)($u['user_id']??$u['id']??0),(string)($u['role_code']??$u['role']??'company_owner')];
    }

    private static function cents(string $v):int
    {
        $v=FinanceOperationInvoiceSettlementService::money($v);$n=str_starts_with($v,'-');if($n)$v=substr($v,1);
        [$w,$f]=explode('.',$v,2);$c=((int)$w*100)+(int)$f;return $n?-$c:$c;
    }
}
