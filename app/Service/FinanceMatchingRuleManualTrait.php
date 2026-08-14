<?php
namespace App\Service;
use PDO;
trait FinanceMatchingRuleManualTrait
{
 public static function manualClassifyBankTransaction(PDO $pdo,int $bankTxId,array $data,array $user):array{
  $own=false;if(!$pdo->inTransaction()){$pdo->beginTransaction();$own=true;}
  try{
   $q=$pdo->prepare('SELECT * FROM bank_transactions WHERE id=? FOR UPDATE');$q->execute([$bankTxId]);$tx=$q->fetch(PDO::FETCH_ASSOC);if(!$tx)throw new \InvalidArgumentException('Банковская операция не найдена.');
   if(!empty($tx['is_internal_transfer']))throw new \InvalidArgumentException('Внутренний перевод уже связан с кассой.');
   $direction=self::directionForTransaction($tx);if($direction===null)throw new \InvalidArgumentException('Не удалось определить направление операции.');
   $cfu=self::nullableInt($data['cash_flow_center_id']??null);$dds=self::nullableInt($data['dds_category_id']??null);if($cfu===null||$dds===null)throw new \InvalidArgumentException('Выберите ЦФУ и статью ДДС.');self::assertCfu($pdo,$cfu);self::assertDds($pdo,$dds,$direction);FinanceStructureService::assertAllowedPair($pdo,$cfu,$dds,$direction);
   $q=$pdo->prepare("UPDATE bank_transactions SET cash_flow_center_id=?,dds_category_id=?,classification_status='MANUAL',classification_rule_id=NULL,classification_locked=1,classification_updated_at=NOW() WHERE id=?");$q->execute([$cfu,$dds,$bankTxId]);
   $q=$pdo->prepare("SELECT id FROM finance_operations WHERE bank_transaction_id=? AND status!='CANCELLED' ORDER BY id LIMIT 1");$q->execute([$bankTxId]);$opId=(int)($q->fetchColumn()?:0);
   if($opId){$q=$pdo->prepare("UPDATE finance_operations SET cash_flow_center_id=?,dds_category_id=?,classification_status='MANUAL',classification_rule_id=NULL,classification_locked=1,classification_updated_at=NOW() WHERE id=?");$q->execute([$cfu,$dds,$opId]);FinanceAuditLogService::log($pdo,'finance_operation',$opId,'classification_manual',null,['cash_flow_center_id'=>$cfu,'dds_category_id'=>$dds],(int)($user['id']??0),(string)($user['role']??'company_owner'));}
   FinanceAuditLogService::log($pdo,'bank_transaction',$bankTxId,'classification_manual',null,['cash_flow_center_id'=>$cfu,'dds_category_id'=>$dds],(int)($user['id']??0),(string)($user['role']??'company_owner'));
   $ruleId=null;if(!empty($data['create_rule'])){$inn=self::validateInn(self::nullableString($tx['counterparty_inn']??null));if($inn===null)throw new \InvalidArgumentException('У операции нет ИНН. Снимите «Создать правило» для разового разнесения.');$ruleId=self::createRule($pdo,['name'=>'ИНН '.$inn.' — ручное правило','priority'=>(int)($data['rule_priority']??100),'direction'=>$direction,'counterparty_inn'=>$inn,'purpose_contains'=>self::nullableString($data['rule_purpose_contains']??null),'action_type'=>'categorize','target_cash_flow_center_id'=>$cfu,'target_dds_category_id'=>$dds,'auto_apply'=>1],$user);}
   if($own)$pdo->commit();return ['bank_transaction_id'=>$bankTxId,'operation_id'=>$opId?:null,'rule_id'=>$ruleId,'status'=>'MANUAL'];
  }catch(\Throwable $e){if($own&&$pdo->inTransaction())$pdo->rollBack();throw $e;}
 }

 public static function clearBankTransactionClassification(PDO $pdo,int $bankTxId,array $user):array{
  $own=false;if(!$pdo->inTransaction()){$pdo->beginTransaction();$own=true;}
  try{
   $q=$pdo->prepare('SELECT * FROM bank_transactions WHERE id=? FOR UPDATE');$q->execute([$bankTxId]);$tx=$q->fetch(PDO::FETCH_ASSOC);if(!$tx)throw new \InvalidArgumentException('Банковская операция не найдена.');
   if(!empty($tx['is_internal_transfer']))throw new \InvalidArgumentException('Разнесение внутреннего перевода нельзя удалить этим действием.');
   $status=strtoupper((string)($tx['classification_status']??'UNALLOCATED'));
   if(!in_array($status,['AUTO','MANUAL'],true))throw new \InvalidArgumentException('У операции нет сохранённого ручного или автоматического разнесения.');
   $before=['cash_flow_center_id'=>self::nullableInt($tx['cash_flow_center_id']??null),'dds_category_id'=>self::nullableInt($tx['dds_category_id']??null),'classification_status'=>$status,'classification_rule_id'=>self::nullableInt($tx['classification_rule_id']??null),'classification_locked'=>(int)($tx['classification_locked']??0)];
   $q=$pdo->prepare("UPDATE bank_transactions SET cash_flow_center_id=NULL,dds_category_id=NULL,classification_status='UNALLOCATED',classification_rule_id=NULL,classification_locked=0,classification_updated_at=NOW() WHERE id=?");$q->execute([$bankTxId]);
   $q=$pdo->prepare("SELECT id FROM finance_operations WHERE bank_transaction_id=? AND status!='CANCELLED' FOR UPDATE");$q->execute([$bankTxId]);$operationIds=array_map('intval',$q->fetchAll(PDO::FETCH_COLUMN));
   if($operationIds){$placeholders=implode(',',array_fill(0,count($operationIds),'?'));$u=$pdo->prepare("UPDATE finance_operations SET cash_flow_center_id=NULL,dds_category_id=NULL,classification_status='UNALLOCATED',classification_rule_id=NULL,classification_locked=0,classification_updated_at=NOW() WHERE id IN ($placeholders)");$u->execute($operationIds);}
   $uid=(int)($user['id']??0);$role=(string)($user['role']??'company_owner');
   FinanceAuditLogService::log($pdo,'bank_transaction',$bankTxId,'classification_cleared',$before,['classification_status'=>'UNALLOCATED'],$uid,$role);
   foreach($operationIds as $opId){FinanceAuditLogService::log($pdo,'finance_operation',$opId,'classification_cleared',$before,['classification_status'=>'UNALLOCATED'],$uid,$role);}
   if($own)$pdo->commit();return ['bank_transaction_id'=>$bankTxId,'operation_ids'=>$operationIds,'previous_status'=>$status,'status'=>'UNALLOCATED'];
  }catch(\Throwable $e){if($own&&$pdo->inTransaction())$pdo->rollBack();throw $e;}
 }
}
