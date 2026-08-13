<?php
namespace App\Service;
use PDO;
trait FinanceMatchingRuleCfuTrait
{
 public static function fetchCashFlowCenters(PDO $pdo,bool $activeOnly=false):array{
  $where=$activeOnly?'WHERE is_active=1':'';$stmt=$pdo->query("SELECT * FROM finance_cash_flow_centers $where ORDER BY sort_order ASC,name ASC,id ASC");return $stmt->fetchAll(PDO::FETCH_ASSOC);
 }
 public static function saveCashFlowCenter(PDO $pdo,array $data,array $user):int{
  $id=(int)($data['id']??0);$name=trim((string)($data['name']??''));$sort=(int)($data['sort_order']??100);
  if($name==='')throw new \InvalidArgumentException('Название ЦФУ обязательно.');if($sort<0||$sort>100000)throw new \InvalidArgumentException('Некорректный порядок ЦФУ.');
  if($id>0){$stmt=$pdo->prepare('UPDATE finance_cash_flow_centers SET name=?,sort_order=?,updated_by_user_id=?,updated_by_role=? WHERE id=?');$stmt->execute([$name,$sort,$user['id']??null,$user['role']??null,$id]);return $id;}
  $stmt=$pdo->prepare('INSERT INTO finance_cash_flow_centers (name,is_active,sort_order,created_by_user_id,created_by_role,updated_by_user_id,updated_by_role) VALUES (?,1,?,?,?,?,?)');$stmt->execute([$name,$sort,$user['id']??null,$user['role']??null,$user['id']??null,$user['role']??null]);return (int)$pdo->lastInsertId();
 }
 public static function setCashFlowCenterActive(PDO $pdo,int $id,bool $active,array $user):void{
  $check=$pdo->prepare('SELECT id FROM finance_cash_flow_centers WHERE id=?');$check->execute([$id]);if(!$check->fetchColumn())throw new \InvalidArgumentException('ЦФУ не найден.');
  $stmt=$pdo->prepare('UPDATE finance_cash_flow_centers SET is_active=?,updated_by_user_id=?,updated_by_role=? WHERE id=?');$stmt->execute([$active?1:0,$user['id']??null,$user['role']??null,$id]);
 }
}
