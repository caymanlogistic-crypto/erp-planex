<?php
namespace App\Service;
use PDO;
trait FinanceMatchingRuleCrudTrait
{
 public static function fetchRules(PDO $pdo,array $filters=[]):array{
  $w=['deleted_at IS NULL'];$p=[];
  if(array_key_exists('active',$filters)){$w[]='active=:active';$p['active']=$filters['active']?1:0;}
  if(!empty($filters['direction'])){$w[]='(direction=:direction OR direction IS NULL)';$p['direction']=$filters['direction'];}
  $s=$pdo->prepare('SELECT * FROM finance_matching_rules WHERE '.implode(' AND ',$w).' ORDER BY priority DESC,id ASC');$s->execute($p);return $s->fetchAll(PDO::FETCH_ASSOC);
 }
 public static function findRule(PDO $pdo,int $id):?array{$s=$pdo->prepare('SELECT * FROM finance_matching_rules WHERE id=? AND deleted_at IS NULL');$s->execute([$id]);$r=$s->fetch(PDO::FETCH_ASSOC);return $r?:null;}
 public static function createRule(PDO $pdo,array $data,array $user):int{
  $r=self::normalizeRule($pdo,$data);
  $q='INSERT INTO finance_matching_rules (name,active,priority,direction,bank_account_id,counterparty_inn,counterparty_id,counterparty_type,invoice_number_pattern,purpose_contains,purpose_regex,amount_from,amount_to,action_type,target_dds_category_id,target_cash_flow_center_id,target_cash_account_id,target_counterparty_id,target_counterparty_type,auto_apply,created_by_user_id,created_by_role,updated_by_user_id,updated_by_role) VALUES (:name,1,:priority,:direction,:bank_account_id,:counterparty_inn,:counterparty_id,:counterparty_type,:invoice_number_pattern,:purpose_contains,:purpose_regex,:amount_from,:amount_to,:action_type,:target_dds_category_id,:target_cash_flow_center_id,:target_cash_account_id,:target_counterparty_id,:target_counterparty_type,:auto_apply,:uid,:role,:uid2,:role2)';
  $s=$pdo->prepare($q);$s->execute($r+['uid'=>$user['id']??null,'role'=>$user['role']??null,'uid2'=>$user['id']??null,'role2'=>$user['role']??null]);$id=(int)$pdo->lastInsertId();self::auditRule($pdo,$id,'create',null,$r,$user);return $id;
 }
 public static function updateRule(PDO $pdo,int $id,array $data,array $user):void{
  $old=self::findRule($pdo,$id);if(!$old)throw new \InvalidArgumentException('Правило не найдено.');$r=self::normalizeRule($pdo,$data,$old);$set=[];$p=['id'=>$id,'uid'=>$user['id']??null,'role'=>$user['role']??null];
  foreach($r as $k=>$v){$set[]="$k=:$k";$p[$k]=$v;}$set[]='updated_by_user_id=:uid';$set[]='updated_by_role=:role';$s=$pdo->prepare('UPDATE finance_matching_rules SET '.implode(',',$set).' WHERE id=:id AND deleted_at IS NULL');$s->execute($p);self::auditRule($pdo,$id,'update',$old,$r,$user);
 }
 public static function setActive(PDO $pdo,int $id,bool $active,array $user):void{
  $old=self::findRule($pdo,$id);if(!$old)throw new \InvalidArgumentException('Правило не найдено.');$s=$pdo->prepare('UPDATE finance_matching_rules SET active=?,updated_by_user_id=?,updated_by_role=? WHERE id=? AND deleted_at IS NULL');$s->execute([$active?1:0,$user['id']??null,$user['role']??null,$id]);self::auditRule($pdo,$id,'toggle',['active'=>(int)$old['active']],['active'=>$active?1:0],$user);
 }
 public static function setPriority(PDO $pdo,int $id,int $priority,array $user):void{
  if($priority<1||$priority>100000)throw new \InvalidArgumentException('Некорректный приоритет.');$old=self::findRule($pdo,$id);if(!$old)throw new \InvalidArgumentException('Правило не найдено.');$s=$pdo->prepare('UPDATE finance_matching_rules SET priority=?,updated_by_user_id=?,updated_by_role=? WHERE id=? AND deleted_at IS NULL');$s->execute([$priority,$user['id']??null,$user['role']??null,$id]);self::auditRule($pdo,$id,'priority',['priority'=>(int)$old['priority']],['priority'=>$priority],$user);
 }
 public static function deleteRule(PDO $pdo,int $id,array $user):void{
  $old=self::findRule($pdo,$id);if(!$old)throw new \InvalidArgumentException('Правило не найдено.');$s=$pdo->prepare('UPDATE finance_matching_rules SET active=0,deleted_at=NOW(),updated_by_user_id=?,updated_by_role=? WHERE id=? AND deleted_at IS NULL');$s->execute([$user['id']??null,$user['role']??null,$id]);self::auditRule($pdo,$id,'remove',['active'=>(int)$old['active']],['active'=>0,'removed'=>true],$user);
 }
}
