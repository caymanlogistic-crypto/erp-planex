<?php

namespace App\Service;

use PDO;
use RuntimeException;
use Throwable;

/** Edit/cancel direct EMPLOYEE -> EMPLOYEE transfer groups. */
final class FinanceEmployeeDirectTransferEditService
{
    public static function fetchForEmployee(PDO $pdo,string $employeeRef):array
    {
        [$type,$id]=FinanceEmployeePaymentService::parseEmployeeRef($employeeRef);
        $stmt=$pdo->prepare("SELECT m.id AS movement_id,o.transfer_group_id
            FROM finance_employee_movements m
            JOIN finance_operations o ON o.id=m.finance_operation_id
            WHERE m.employee_identity_type=? AND m.employee_identity_id=?
              AND o.operation_type='TRANSFER' AND o.source='TRANSFER'
              AND o.transfer_group_id LIKE 'EMPLOYEE-DIRECT-%'");
        $stmt->execute([$type,$id]);
        $result=[];
        foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row){
            try{$group=self::loadGroup($pdo,(string)$row['transfer_group_id'],false);}catch(Throwable){continue;}
            if($group['source']['status']!=='POSTED' || $group['target']['status']!=='POSTED') continue;
            $payload=self::payload($group);
            $result[(int)$group['source']['movement_id']]=$payload;
            $result[(int)$group['target']['movement_id']]=$payload;
        }
        return $result;
    }

    public static function update(PDO $pdo, PDO $central, int $companyId, string $groupId, array $data, array $user):array
    {
        self::assertGroup($groupId);
        $sourceRef=trim((string)($data['source_employee_ref']??''));
        $targetRef=trim((string)($data['target_employee_ref']??''));
        if($sourceRef==='' || $targetRef==='') throw new \InvalidArgumentException('Выберите отправителя и получателя.');
        if($sourceRef===$targetRef) throw new \InvalidArgumentException('Нельзя передать деньги самому себе.');
        $amount=self::amount((string)($data['amount']??''));
        $date=self::date((string)($data['operation_date']??''));
        $sourceEmployee=FinanceEmployeePaymentService::resolveActiveEmployee($pdo,$central,$companyId,$sourceRef);
        $targetEmployee=FinanceEmployeePaymentService::resolveActiveEmployee($pdo,$central,$companyId,$targetRef);
        $purpose=trim((string)($data['purpose']??''));
        if($purpose==='')$purpose='Передача денег: '.$sourceEmployee['full_name'].' → '.$targetEmployee['full_name'];
        $comment=trim((string)($data['comment']??''));
        [$uid,$role]=self::actor($user);
        $owns=!$pdo->inTransaction();if($owns)$pdo->beginTransaction();
        try{
            $group=self::loadGroup($pdo,$groupId,true);
            if($group['source']['status']!=='POSTED' || $group['target']['status']!=='POSTED') throw new RuntimeException('Редактировать можно только действующую передачу.');
            $before=self::payload($group);
            $sourceAccount=FinanceEmployeeMoneyAccountService::accountId($pdo,$sourceEmployee,$user);
            $targetAccount=FinanceEmployeeMoneyAccountService::accountId($pdo,$targetEmployee,$user);
            if($sourceAccount===$targetAccount) throw new RuntimeException('Сотрудники не могут использовать один финансовый счёт.');
            self::updateMovement($pdo,(int)$group['source']['movement_id'],$sourceEmployee,$comment!==''?$comment:'Прямая передача денег другому сотруднику.');
            self::updateMovement($pdo,(int)$group['target']['movement_id'],$targetEmployee,$comment!==''?$comment:'Прямая передача денег от другого сотрудника.');
            $stmt=$pdo->prepare("UPDATE finance_operations SET money_account_id=?,transfer_account_id=?,operation_date=?,amount=?,purpose=?,comment=? WHERE id=? AND status='POSTED'");
            $stmt->execute([$sourceAccount,$targetAccount,$date,$amount,$purpose,$comment!==''?$comment:null,(int)$group['source']['finance_operation_id']]);
            $stmt->execute([$targetAccount,$sourceAccount,$date,$amount,$purpose,$comment!==''?$comment:null,(int)$group['target']['finance_operation_id']]);
            $after=[
                'transfer_group_id'=>$groupId,'source_employee_ref'=>$sourceRef,'source_employee_name'=>(string)$sourceEmployee['full_name'],
                'target_employee_ref'=>$targetRef,'target_employee_name'=>(string)$targetEmployee['full_name'],
                'operation_date'=>$date,'amount'=>$amount,'purpose'=>$purpose,'comment'=>$comment,
            ];
            FinanceAuditLogService::log($pdo,'finance_employee_transfer',(int)$group['source']['finance_operation_id'],'employee_direct_transfer_update',$before,$after,$uid,$role);
            if($owns)$pdo->commit();return $after;
        }catch(Throwable $e){if($owns&&$pdo->inTransaction())$pdo->rollBack();throw $e;}
    }

    public static function cancel(PDO $pdo,string $groupId,array $user,string $reason=''):array
    {
        self::assertGroup($groupId);[$uid,$role]=self::actor($user);
        $reason=trim($reason)?:'Удалено пользователем из взаиморасчётов с сотрудниками';
        $owns=!$pdo->inTransaction();if($owns)$pdo->beginTransaction();
        try{
            $group=self::loadGroup($pdo,$groupId,true);
            if($group['source']['status']==='CANCELLED' && $group['target']['status']==='CANCELLED'){if($owns)$pdo->commit();return self::payload($group);}
            if($group['source']['status']!=='POSTED' || $group['target']['status']!=='POSTED') throw new RuntimeException('Удалить можно только действующую передачу.');
            $before=self::payload($group);
            $stmt=$pdo->prepare("UPDATE finance_operations SET status='CANCELLED',cancelled_at=NOW(),cancelled_by_user_id=?,cancelled_by_role=?,cancellation_reason=? WHERE id=? AND status='POSTED'");
            foreach([$group['source'],$group['target']] as $leg){
                $stmt->execute([$uid?:null,$role,$reason,(int)$leg['finance_operation_id']]);
                if($stmt->rowCount()!==1) throw new RuntimeException('Не удалось удалить передачу целиком.');
            }
            FinanceAuditLogService::log($pdo,'finance_employee_transfer',(int)$group['source']['finance_operation_id'],'employee_direct_transfer_cancel',$before,['status'=>'CANCELLED','transfer_group_id'=>$groupId],$uid,$role);
            if($owns)$pdo->commit();return ['transfer_group_id'=>$groupId,'status'=>'CANCELLED'];
        }catch(Throwable $e){if($owns&&$pdo->inTransaction())$pdo->rollBack();throw $e;}
    }

    private static function loadGroup(PDO $pdo,string $groupId,bool $lock):array
    {
        self::assertGroup($groupId);
        $sql="SELECT o.id AS finance_operation_id,o.status,o.operation_date,o.amount,o.purpose,o.comment,o.transfer_group_id,o.transfer_direction,o.money_account_id,o.transfer_account_id,
                    m.id AS movement_id,m.movement_type,m.employee_identity_type,m.employee_identity_id,m.employee_name_snapshot,m.employee_role_snapshot
               FROM finance_operations o JOIN finance_employee_movements m ON m.finance_operation_id=o.id
              WHERE o.transfer_group_id=? AND o.operation_type='TRANSFER' AND o.source='TRANSFER'".($lock?' FOR UPDATE':'');
        $stmt=$pdo->prepare($sql);$stmt->execute([$groupId]);$rows=$stmt->fetchAll(PDO::FETCH_ASSOC);
        if(count($rows)!==2) throw new RuntimeException('Передача повреждена: ожидаются две связанные операции.');
        $source=$target=null;
        foreach($rows as $row){
            if($row['movement_type']==='RETURN' && $row['transfer_direction']==='out')$source=$row;
            if($row['movement_type']==='PAYMENT' && $row['transfer_direction']==='in')$target=$row;
        }
        if(!$source||!$target) throw new RuntimeException('Не удалось определить отправителя и получателя передачи.');
        return ['source'=>$source,'target'=>$target];
    }

    private static function payload(array $group):array
    {
        $s=$group['source'];$t=$group['target'];
        return [
            'transfer_group_id'=>(string)$s['transfer_group_id'],'display_id'=>(int)$s['finance_operation_id'],
            'source_employee_ref'=>FinanceEmployeePaymentService::makeEmployeeRef((string)$s['employee_identity_type'],(int)$s['employee_identity_id']),
            'source_employee_name'=>(string)$s['employee_name_snapshot'],
            'target_employee_ref'=>FinanceEmployeePaymentService::makeEmployeeRef((string)$t['employee_identity_type'],(int)$t['employee_identity_id']),
            'target_employee_name'=>(string)$t['employee_name_snapshot'],
            'operation_date'=>(string)$s['operation_date'],'amount'=>(string)$s['amount'],'purpose'=>(string)($s['purpose']??''),'comment'=>(string)($s['comment']??''),
        ];
    }

    private static function updateMovement(PDO $pdo,int $id,array $employee,string $note):void
    {
        $localUserId=(string)$employee['identity_type']===FinanceEmployeePaymentService::IDENTITY_TENANT_USER?(int)$employee['identity_id']:null;
        $stmt=$pdo->prepare("UPDATE finance_employee_movements SET employee_user_id=?,employee_identity_type=?,employee_identity_id=?,employee_name_snapshot=?,employee_role_snapshot=?,source_type='EMPLOYEE',note=? WHERE id=?");
        $stmt->execute([$localUserId,(string)$employee['identity_type'],(int)$employee['identity_id'],(string)$employee['full_name'],(string)($employee['role_code']??''),$note,$id]);
    }
    private static function assertGroup(string $id):void{if(!preg_match('/^EMPLOYEE-DIRECT-[A-Za-z0-9-]{8,100}$/D',$id))throw new \InvalidArgumentException('Прямая передача не найдена.');}
    private static function amount(string $v):string{$a=FinanceOperationInvoiceSettlementService::normalizePositiveMoney($v);if($a===null)throw new \InvalidArgumentException('Укажите корректную сумму.');return $a;}
    private static function date(string $v):string{$v=trim($v);$d=\DateTimeImmutable::createFromFormat('!Y-m-d',$v);if(!$d||$d->format('Y-m-d')!==$v)throw new \InvalidArgumentException('Укажите корректную дату.');return $v;}
    private static function actor(array $u):array{return [(int)($u['user_id']??$u['id']??0),(string)($u['role_code']??$u['role']??'company_owner')];}
}
