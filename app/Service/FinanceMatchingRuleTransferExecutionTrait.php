<?php
namespace App\Service;
use PDO;
trait FinanceMatchingRuleTransferExecutionTrait{
 private static function convertBankOperationToCashTransfer(PDO $pdo,array $op,array $rule):array{
  if(($op['operation_type']??'')!=='EXPENSE'||($op['source']??'')!=='BANK_STATEMENT')throw new \RuntimeException('Действие Банк → Касса допустимо только для банковского расхода.');
  $cash=self::nullableInt($rule['target_cash_account_id']??null);if(!$cash)throw new \RuntimeException('В правиле не выбрана касса.');self::assertCashAccount($pdo,$cash);
  $tx=(int)($op['bank_transaction_id']??0);$s=$pdo->prepare('SELECT is_internal_transfer,linked_cash_transaction_id FROM bank_transactions WHERE id=?');$s->execute([$tx]);$b=$s->fetch(PDO::FETCH_ASSOC);
  if($b&&!empty($b['is_internal_transfer']))return ['handled'=>true,'idempotent'=>true,'cash_operation_id'=>(int)($b['linked_cash_transaction_id']??0)];
  $own=false;if(!$pdo->inTransaction()){$pdo->beginTransaction();$own=true;}
  try{
   $group='TRF_RULE_'.bin2hex(random_bytes(10));$hash=hash('sha256','rule_cash_transfer:'.$tx);
   $q=$pdo->prepare("INSERT INTO finance_operations (operation_type,status,source,money_account_id,transfer_account_id,transfer_group_id,transfer_direction,operation_date,amount,currency,purpose,comment,dedupe_hash,classification_status,classification_rule_id,classification_locked,classification_updated_at,created_by_user_id,created_by_role,posted_by_user_id,posted_by_role,posted_at) VALUES ('TRANSFER','POSTED','TRANSFER',?,?,?,'in',?,?,?,?,?,?,'AUTO',?,0,NOW(),0,'system',0,'system',NOW())");
   $q->execute([$cash,(int)$op['money_account_id'],$group,$op['operation_date'],$op['amount'],$op['currency']??'RUR',$op['purpose']??null,'Банк → касса, правило #'.(int)$rule['id'],$hash,(int)$rule['id']]);$cashOp=(int)$pdo->lastInsertId();
   $q=$pdo->prepare("UPDATE finance_operations SET operation_type='TRANSFER',source='TRANSFER',transfer_account_id=?,transfer_group_id=?,transfer_direction='out',classification_status='AUTO',classification_rule_id=?,classification_updated_at=NOW() WHERE id=?");$q->execute([$cash,$group,(int)$rule['id'],(int)$op['id']]);
   $q=$pdo->prepare("UPDATE bank_transactions SET is_internal_transfer=1,linked_cash_transaction_id=?,classification_status='AUTO',classification_rule_id=?,classification_updated_at=NOW() WHERE id=?");$q->execute([$cashOp,(int)$rule['id'],$tx]);
   if($own)$pdo->commit();return ['handled'=>true,'rule_id'=>(int)$rule['id'],'cash_operation_id'=>$cashOp,'transfer_group_id'=>$group];
  }catch(\Throwable $e){if($own&&$pdo->inTransaction())$pdo->rollBack();throw $e;}
 }
}
