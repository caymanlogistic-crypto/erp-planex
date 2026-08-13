<?php
namespace App\Service;
use PDO;
trait FinanceMatchingRuleTransferExecutionTrait
{
 private static function executeBankToCashRule(PDO $pdo,array $tx,array $rule):array{
  if(self::directionForTransaction($tx)!=='EXPENSE')throw new \RuntimeException('Перевод Банк → Касса применим только к расходу.');
  $cashId=self::nullableInt($rule['target_cash_account_id']??null);if($cashId===null)throw new \RuntimeException('В правиле не указана касса.');self::assertCashAccount($pdo,$cashId);
  $q=$pdo->prepare("SELECT id FROM finance_money_accounts WHERE bank_account_id=? AND type='BANK' AND is_active=1");$q->execute([(int)$tx['account_id']]);$bankId=(int)($q->fetchColumn()?:0);
  if(!$bankId){FinanceOperationService::ensureMoneyAccountsForBankAccounts($pdo);$q->execute([(int)$tx['account_id']]);$bankId=(int)($q->fetchColumn()?:0);}if(!$bankId)throw new \RuntimeException('Не найден финансовый счёт банка.');
  $transfer=FinanceCashService::createTransfer($pdo,['from_account_id'=>$bankId,'to_account_id'=>$cashId,'amount'=>(string)$tx['debit_amount'],'date'=>$tx['operation_date'],'purpose'=>$tx['purpose']??null,'comment'=>'По правилу #'.(int)$rule['id']],['id'=>0,'role'=>'system']);
  FinanceOperationService::confirmTransfer($pdo,(string)$transfer['transfer_group_id'],(int)$tx['id'],['user_id'=>0,'role_code'=>'system']);
  $cashOp=(int)$transfer['in_operation_id'];$q=$pdo->prepare("UPDATE bank_transactions SET is_internal_transfer=1,linked_cash_transaction_id=?,classification_status='AUTO',classification_rule_id=?,classification_locked=0,classification_updated_at=NOW() WHERE id=?");$q->execute([$cashOp,(int)$rule['id'],(int)$tx['id']]);
  $q=$pdo->prepare("UPDATE finance_operations SET classification_status='AUTO',classification_rule_id=?,classification_updated_at=NOW() WHERE transfer_group_id=?");$q->execute([(int)$rule['id'],$transfer['transfer_group_id']]);
  FinanceAuditLogService::log($pdo,'finance_operation',$cashOp,'classification_auto_transfer',null,['rule_id'=>(int)$rule['id'],'bank_transaction_id'=>(int)$tx['id']],0,'system');
  return ['handled'=>true,'rule_id'=>(int)$rule['id'],'cash_operation_id'=>$cashOp,'transfer_group_id'=>$transfer['transfer_group_id']];
 }
}
