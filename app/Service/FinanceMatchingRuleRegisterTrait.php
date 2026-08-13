<?php
namespace App\Service;
use PDO;
trait FinanceMatchingRuleRegisterTrait{
 public static function fetchBankRegister(PDO $pdo,array $f=[]):array{
  $w=[];$p=[];
  foreach(['date_from'=>['bt.operation_date>=:df','df'],'date_to'=>['bt.operation_date<=:dt','dt'],'classification_status'=>['bt.classification_status=:cs','cs']] as $k=>$x)if(!empty($f[$k])){$w[]=$x[0];$p[$x[1]]=$f[$k];}
  if(!empty($f['search'])){$w[]='(bt.document_number LIKE :q OR bt.counterparty_name LIKE :q2 OR bt.counterparty_inn LIKE :q3 OR bt.purpose LIKE :q4 OR cfu.name LIKE :q5 OR dds.name LIKE :q6)';$v='%'.trim((string)$f['search']).'%';foreach(['q','q2','q3','q4','q5','q6'] as $k)$p[$k]=$v;}
  $where=$w?'WHERE '.implode(' AND ',$w):'';$from='FROM bank_transactions bt JOIN bank_accounts ba ON ba.id=bt.account_id LEFT JOIN finance_cash_flow_centers cfu ON cfu.id=bt.cash_flow_center_id LEFT JOIN finance_dds_categories dds ON dds.id=bt.dds_category_id';
  $s=$pdo->prepare("SELECT COUNT(*) $from $where");$s->execute($p);$total=(int)$s->fetchColumn();$page=max(1,(int)($f['page']??1));$per=max(1,min(500,(int)($f['per_page']??100)));$off=($page-1)*$per;
  $s=$pdo->prepare("SELECT bt.*,ba.account_number,cfu.name cash_flow_center_name,dds.name dds_category_name,dds.code dds_category_code $from $where ORDER BY bt.operation_date DESC,bt.id DESC LIMIT :lim OFFSET :off");foreach($p as $k=>$v)$s->bindValue(':'.$k,$v);$s->bindValue(':lim',$per,PDO::PARAM_INT);$s->bindValue(':off',$off,PDO::PARAM_INT);$s->execute();return ['data'=>$s->fetchAll(PDO::FETCH_ASSOC),'total'=>$total,'page'=>$page,'per_page'=>$per,'pages'=>(int)ceil($total/max(1,$per))];
 }
 public static function fetchBankTransactionForClassification(PDO $pdo,int $id):?array{$s=$pdo->prepare('SELECT bt.*,ba.account_number FROM bank_transactions bt JOIN bank_accounts ba ON ba.id=bt.account_id WHERE bt.id=?');$s->execute([$id]);$r=$s->fetch(PDO::FETCH_ASSOC);return $r?:null;}
}
