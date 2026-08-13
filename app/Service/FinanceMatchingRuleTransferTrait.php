<?php
namespace App\Service;
use PDO;
trait FinanceMatchingRuleTransferTrait
{
 public static function applyInternalTransferRuleToBankTransaction(PDO $pdo,int $bankTxId):array{
  $q=$pdo->prepare('SELECT * FROM bank_transactions WHERE id=?');$q->execute([$bankTxId]);$tx=$q->fetch(PDO::FETCH_ASSOC);
  if(!$tx)return ['handled'=>false,'reason'=>'Операция не найдена.'];
  if(!empty($tx['is_internal_transfer'])&&!empty($tx['linked_cash_transaction_id']))return ['handled'=>true,'idempotent'=>true,'cash_operation_id'=>(int)$tx['linked_cash_transaction_id']];
  if(self::isManualProtected($tx))return ['handled'=>false,'protected'=>true];
  $q=$pdo->prepare("SELECT id FROM finance_operations WHERE bank_transaction_id=? AND status!='CANCELLED' LIMIT 1");$q->execute([$bankTxId]);if($q->fetchColumn())return ['handled'=>false,'reason'=>'Операция уже связана.'];
  $resolved=self::resolveRulesForTransaction(self::fetchRules($pdo,['active'=>true]),$tx);
  if($resolved['status']==='no_match')return ['handled'=>false];
  if($resolved['status']==='conflict'){self::markBankReview($pdo,$bankTxId,null);return ['handled'=>false,'conflict'=>true,'candidate_rule_ids'=>$resolved['candidate_rule_ids']];}
  $rule=$resolved['winner']['rule'];if(($rule['action_type']??'')!=='transfer_to_cash')return ['handled'=>false];
  if(empty($rule['auto_apply'])){self::markBankReview($pdo,$bankTxId,(int)$rule['id']);return ['handled'=>false,'result'=>'suggest'];}
  return self::executeBankToCashRule($pdo,$tx,$rule);
 }
 private static function markBankReview(PDO $pdo,int $bankTxId,?int $ruleId):void{
  $q=$pdo->prepare("UPDATE bank_transactions SET classification_status='NEEDS_REVIEW',classification_rule_id=?,classification_updated_at=NOW() WHERE id=? AND classification_locked=0");$q->execute([$ruleId,$bankTxId]);
 }
}
