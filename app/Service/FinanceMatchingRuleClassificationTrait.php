<?php
namespace App\Service;
use PDO;
trait FinanceMatchingRuleClassificationTrait
{
 private static function setClassificationState(PDO $pdo,int $bankTxId,?int $operationId,string $status,?int $ruleId,?int $cfuId=null,?int $ddsId=null):void{
  $stmt=$pdo->prepare('UPDATE bank_transactions SET cash_flow_center_id=?,dds_category_id=?,classification_status=?,classification_rule_id=?,classification_locked=0,classification_updated_at=NOW() WHERE id=? AND classification_locked=0');
  $stmt->execute([$cfuId,$ddsId,$status,$ruleId,$bankTxId]);
  if($operationId){$stmt=$pdo->prepare('UPDATE finance_operations SET cash_flow_center_id=?,dds_category_id=?,classification_status=?,classification_rule_id=?,classification_locked=0,classification_updated_at=NOW() WHERE id=? AND classification_locked=0');$stmt->execute([$cfuId,$ddsId,$status,$ruleId,$operationId]);}
 }

 public static function applyAutoMatchToOperation(PDO $pdo,int $operationId):array{
  $stmt=$pdo->prepare('SELECT fo.*,bt.id bank_tx_id,bt.account_id,bt.credit_amount,bt.debit_amount,bt.counterparty_inn bt_counterparty_inn,bt.counterparty_name bt_counterparty_name,bt.purpose bt_purpose,bt.classification_status bt_classification_status,bt.classification_locked bt_classification_locked FROM finance_operations fo LEFT JOIN bank_transactions bt ON bt.id=fo.bank_transaction_id WHERE fo.id=?');$stmt->execute([$operationId]);$op=$stmt->fetch(PDO::FETCH_ASSOC);
  if(!$op||empty($op['bank_tx_id']))return ['matched'=>false,'reason'=>'Операция не связана с банковской транзакцией.'];
  if(self::isManualProtected($op)||!empty($op['bt_classification_locked'])||strtoupper((string)$op['bt_classification_status'])==='MANUAL')return ['matched'=>false,'protected'=>true,'reason'=>'Ручное разнесение защищено.'];
  $tx=$op;$tx['counterparty_inn']=$op['bt_counterparty_inn']??$op['counterparty_inn'];$tx['counterparty_name']=$op['bt_counterparty_name']??$op['counterparty_name'];$tx['purpose']=$op['bt_purpose']??$op['purpose'];
  $resolved=self::resolveRulesForTransaction(self::fetchRules($pdo,['active'=>true]),$tx);
  if($resolved['status']==='no_match')return ['matched'=>false,'reason'=>'Нет подходящего правила.'];
  if($resolved['status']==='conflict'){
   self::setClassificationState($pdo,(int)$op['bank_tx_id'],$operationId,'NEEDS_REVIEW',null,$op['cash_flow_center_id']?:(null),$op['dds_category_id']?:(null));
   self::persistMatchingOutcome($pdo,$operationId,null,'conflict','low','Конфликт правил максимального приоритета.',null,'system');
   return ['matched'=>false,'conflict'=>true,'candidate_rule_ids'=>$resolved['candidate_rule_ids']];
  }
  $winner=$resolved['winner'];$rule=$winner['rule'];$evaluation=$winner['evaluation'];
  if(empty($rule['auto_apply'])){
   self::setClassificationState($pdo,(int)$op['bank_tx_id'],$operationId,'NEEDS_REVIEW',(int)$rule['id'],$op['cash_flow_center_id']?:(null),$op['dds_category_id']?:(null));
   self::persistMatchingOutcome($pdo,$operationId,(int)$rule['id'],'suggest','high',$evaluation['reason'],null,'system');
   return ['matched'=>true,'result'=>'suggest','rule_id'=>(int)$rule['id']];
  }
  $action=(string)$rule['action_type'];
  if($action==='categorize'){
   $cfu=self::nullableInt($rule['target_cash_flow_center_id']??null);$dds=self::nullableInt($rule['target_dds_category_id']??null);
   self::setClassificationState($pdo,(int)$op['bank_tx_id'],$operationId,'AUTO',(int)$rule['id'],$cfu,$dds);
   self::persistMatchingOutcome($pdo,$operationId,(int)$rule['id'],'auto_apply','high',$evaluation['reason'],date('Y-m-d H:i:s'),'system');
   FinanceAuditLogService::log($pdo,'finance_operation',$operationId,'classification_auto',null,['cash_flow_center_id'=>$cfu,'dds_category_id'=>$dds,'rule_id'=>(int)$rule['id']],0,'system');
   return ['matched'=>true,'result'=>'auto_apply','rule_id'=>(int)$rule['id'],'updates'=>['cash_flow_center_id'=>$cfu,'dds_category_id'=>$dds]];
  }
  if($action==='match_counterparty'&&!empty($rule['target_counterparty_id'])){
   $stmt=$pdo->prepare('UPDATE finance_operations SET counterparty_entity_id=?,counterparty_entity_type=? WHERE id=?');$stmt->execute([(int)$rule['target_counterparty_id'],$rule['target_counterparty_type']??null,$operationId]);
  }
  self::persistMatchingOutcome($pdo,$operationId,(int)$rule['id'],'auto_apply','high',$evaluation['reason'],date('Y-m-d H:i:s'),'system');
  return ['matched'=>true,'result'=>'auto_apply','rule_id'=>(int)$rule['id'],'updates'=>[]];
 }

 public static function persistMatchingResult(PDO $pdo,int $operationId,array $rule,array $result,string $source='system'):void{
  self::persistMatchingOutcome($pdo,$operationId,(int)($rule['id']??0)?:null,(string)($result['result']??'no_match'),(string)($result['confidence']??'low'),(string)($result['reason']??''),$result['applied_at']??null,$source);
 }
 private static function persistMatchingOutcome(PDO $pdo,int $operationId,?int $ruleId,string $result,string $confidence,string $reason,?string $appliedAt,string $source):void{
  $stmt=$pdo->prepare('INSERT INTO finance_matching_results (finance_operation_id,rule_id,reason,confidence,result,applied_at,source,created_at) VALUES (?,?,?,?,?,?,?,NOW())');$stmt->execute([$operationId,$ruleId,$reason,$confidence,$result,$appliedAt,$source]);
 }
}
