<?php
namespace App\Service;
use PDO;
trait FinanceMatchingRuleEngineTrait
{
 public static function applyRuleToTransaction(?PDO $pdo,array $rule,array $tx):array{
  if(isset($rule['active'])&&!(int)$rule['active']||!empty($rule['deleted_at']))return ['matched'=>false,'result'=>'no_match','confidence'=>'low','reason'=>'Правило неактивно.'];
  $direction=self::directionForTransaction($tx);if($direction===null)return ['matched'=>false,'result'=>'no_match','confidence'=>'low','reason'=>'Не удалось определить направление.'];
  $checks=[];
  if(!empty($rule['direction'])){if(strtoupper((string)$rule['direction'])!==$direction)return self::noMatch('Не совпало направление.');$checks[]='направление';}
  if(!empty($rule['bank_account_id'])){if((int)($tx['account_id']??$tx['bank_account_id']??0)!==(int)$rule['bank_account_id'])return self::noMatch('Не совпал банковский счёт.');$checks[]='счёт';}
  if(!empty($rule['counterparty_inn'])){if(trim((string)($tx['counterparty_inn']??''))!==trim((string)$rule['counterparty_inn']))return self::noMatch('Не совпал ИНН.');$checks[]='ИНН';}
  if(!empty($rule['counterparty_id'])){if((int)($tx['counterparty_entity_id']??0)!==(int)$rule['counterparty_id'])return self::noMatch('Не совпал контрагент.');$checks[]='контрагент';}
  if(!empty($rule['counterparty_type'])){if((string)($tx['counterparty_entity_type']??'')!==(string)$rule['counterparty_type'])return self::noMatch('Не совпал тип контрагента.');$checks[]='тип контрагента';}
  $purpose=(string)($tx['purpose']??'');
  if(!empty($rule['purpose_contains'])){if(!self::containsCi($purpose,(string)$rule['purpose_contains']))return self::noMatch('Не совпал фрагмент назначения.');$checks[]='назначение';}
  if(!empty($rule['invoice_number_pattern'])){if(!self::containsCi($purpose,(string)$rule['invoice_number_pattern']))return self::noMatch('Не совпал шаблон счёта.');$checks[]='шаблон счёта';}
  if(!empty($rule['purpose_regex'])){if(@preg_match((string)$rule['purpose_regex'],$purpose)!==1)return self::noMatch('Не совпало регулярное выражение.');$checks[]='regex';}
  $amount=self::amountForTransaction($tx);
  if($rule['amount_from']??null){if(self::cents($amount)<self::cents($rule['amount_from']))return self::noMatch('Сумма ниже диапазона.');$checks[]='сумма от';}
  if($rule['amount_to']??null){if(self::cents($amount)>self::cents($rule['amount_to']))return self::noMatch('Сумма выше диапазона.');$checks[]='сумма до';}
  if($checks===[])return self::noMatch('У правила нет условий.');
  return ['matched'=>true,'result'=>'auto_apply','confidence'=>'high','reason'=>'Совпали все условия: '.implode(', ',$checks).'.','direction'=>$direction,'amount'=>$amount];
 }
 private static function noMatch(string $reason):array{return ['matched'=>false,'result'=>'no_match','confidence'=>'low','reason'=>$reason];}

 public static function resolveRulesForTransaction(array $rules,array $tx):array{
  $matches=[];foreach($rules as $rule){$e=self::applyRuleToTransaction(null,$rule,$tx);if(!empty($e['matched']))$matches[]=['rule'=>$rule,'evaluation'=>$e];}
  if($matches===[])return ['status'=>'no_match','matches'=>[],'winner'=>null];
  $max=max(array_map(static fn($m)=>(int)($m['rule']['priority']??0),$matches));
  $top=array_values(array_filter($matches,static fn($m)=>(int)($m['rule']['priority']??0)===$max));
  if(count($top)>1)return ['status'=>'conflict','priority'=>$max,'matches'=>$top,'winner'=>null,'candidate_rule_ids'=>array_map(static fn($m)=>(int)$m['rule']['id'],$top)];
  return ['status'=>'winner','priority'=>$max,'matches'=>$matches,'winner'=>$top[0]];
 }

 public static function selectBestMatchingRule(array $rules,array $transaction):?array{
  $resolved=self::resolveRulesForTransaction($rules,$transaction);if($resolved['status']==='no_match')return null;
  if($resolved['status']==='conflict')return ['conflict'=>true,'priority'=>$resolved['priority'],'matches'=>$resolved['matches'],'result'=>'conflict','candidate_rule_ids'=>$resolved['candidate_rule_ids'],'reason'=>'Несколько правил одинакового максимального приоритета.'];
  $winner=$resolved['winner'];return $winner+['conflict'=>false,'priority'=>$resolved['priority']];
 }
 public static function findBestMatchingRule(PDO $pdo,array $transaction):?array{return self::selectBestMatchingRule(self::fetchRules($pdo,['active'=>true]),$transaction);}

 public static function previewRule(PDO $pdo,array $rule,int $limit=20):array{return self::previewRuleSummary($pdo,$rule,$limit)['sample'];}
 public static function previewRuleSummary(PDO $pdo,array $rule,int $limit=20):array{
  $stmt=$pdo->query("SELECT bt.*,ba.account_number,COALESCE(bt.classification_status,'UNALLOCATED') classification_status,cfu.name cash_flow_center_name,dds.name dds_category_name FROM bank_transactions bt JOIN bank_accounts ba ON ba.id=bt.account_id LEFT JOIN finance_cash_flow_centers cfu ON cfu.id=bt.cash_flow_center_id LEFT JOIN finance_dds_categories dds ON dds.id=bt.dds_category_id ORDER BY bt.operation_date DESC,bt.id DESC LIMIT 2000");
  $sample=[];$total=0;foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $tx){$e=self::applyRuleToTransaction($pdo,$rule,$tx);if(empty($e['matched']))continue;$total++;if(count($sample)<$limit){$tx['_match']=$e;$sample[]=$tx;}}
  return ['total'=>$total,'sample'=>$sample];
 }
 public static function testRuleOnTransaction(PDO $pdo,array $rule,int $bankTransactionId):array{$s=$pdo->prepare('SELECT * FROM bank_transactions WHERE id=?');$s->execute([$bankTransactionId]);$tx=$s->fetch(PDO::FETCH_ASSOC);return $tx?self::applyRuleToTransaction($pdo,$rule,$tx):['error'=>'Transaction not found'];}
}
