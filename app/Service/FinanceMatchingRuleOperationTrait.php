<?php
namespace App\Service;
use PDO;
trait FinanceMatchingRuleOperationTrait
{
    private static function fetchOperationWithTransaction(PDO $pdo,int $id,bool $lock=false): ?array
    {
        $suffix=$lock&&$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)!=='sqlite'?' FOR UPDATE':'';
        $sql="SELECT fo.*,bt.account_id,bt.document_number,bt.debit_amount,bt.credit_amount,bt.counterparty_name AS bt_counterparty_name,bt.counterparty_inn AS bt_counterparty_inn,bt.purpose AS bt_purpose,bt.is_internal_transfer,bt.linked_cash_operation_id FROM finance_operations fo LEFT JOIN bank_transactions bt ON bt.id=fo.bank_transaction_id WHERE fo.id=:id".$suffix;
        $s=$pdo->prepare($sql);$s->execute([':id'=>$id]);$r=$s->fetch(PDO::FETCH_ASSOC);if(!$r)return null;
        if(!empty($r['bank_transaction_id'])){$r['counterparty_name']=$r['bt_counterparty_name']??$r['counterparty_name'];$r['counterparty_inn']=$r['bt_counterparty_inn']??$r['counterparty_inn'];$r['purpose']=$r['bt_purpose']??$r['purpose'];}
        return $r;
    }
}
