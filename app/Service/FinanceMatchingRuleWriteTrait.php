<?php

namespace App\Service;

use PDO;

trait FinanceMatchingRuleWriteTrait
{
    public static function createRule(PDO $pdo, array $data, array $user): int
    {
        $row = self::normalizeRuleInput($pdo, $data);
        $stmt = $pdo->prepare('INSERT INTO finance_matching_rules (name,active,priority,direction,bank_account_id,counterparty_inn,counterparty_id,counterparty_type,invoice_number_pattern,purpose_contains,purpose_regex,amount_from,amount_to,action_type,target_dds_category_id,target_cash_flow_center_id,target_cash_account_id,target_counterparty_id,target_counterparty_type,auto_apply,created_by_user_id,created_by_role,updated_by_user_id,updated_by_role) VALUES (:name,:active,:priority,:direction,:bank_account_id,:counterparty_inn,:counterparty_id,:counterparty_type,:invoice_number_pattern,:purpose_contains,:purpose_regex,:amount_from,:amount_to,:action_type,:target_dds_category_id,:target_cash_flow_center_id,:target_cash_account_id,:target_counterparty_id,:target_counterparty_type,:auto_apply,:created_by_user_id,:created_by_role,:updated_by_user_id,:updated_by_role)');
        $stmt->execute($row + ['created_by_user_id'=>$user['id']??null,'created_by_role'=>$user['role']??null,'updated_by_user_id'=>$user['id']??null,'updated_by_role'=>$user['role']??null]);
        return (int)$pdo->lastInsertId();
    }

    public static function updateRule(PDO $pdo, int $id, array $data, array $user): void
    {
        $existing=self::findRule($pdo,$id); if(!$existing)throw new \InvalidArgumentException('Правило не найдено.');
        $row=self::normalizeRuleInput($pdo,$data,$existing); $row['id']=$id; $row['updated_by_user_id']=$user['id']??null; $row['updated_by_role']=$user['role']??null;
        $stmt=$pdo->prepare('UPDATE finance_matching_rules SET name=:name,active=:active,priority=:priority,direction=:direction,bank_account_id=:bank_account_id,counterparty_inn=:counterparty_inn,counterparty_id=:counterparty_id,counterparty_type=:counterparty_type,invoice_number_pattern=:invoice_number_pattern,purpose_contains=:purpose_contains,purpose_regex=:purpose_regex,amount_from=:amount_from,amount_to=:amount_to,action_type=:action_type,target_dds_category_id=:target_dds_category_id,target_cash_flow_center_id=:target_cash_flow_center_id,target_cash_account_id=:target_cash_account_id,target_counterparty_id=:target_counterparty_id,target_counterparty_type=:target_counterparty_type,auto_apply=:auto_apply,updated_by_user_id=:updated_by_user_id,updated_by_role=:updated_by_role WHERE id=:id AND deleted_at IS NULL');
        $stmt->execute($row);
    }

    public static function setActive(PDO $pdo,int $id,bool $active,array $user): void
    {
        if(!self::findRule($pdo,$id))throw new \InvalidArgumentException('Правило не найдено.');
        $stmt=$pdo->prepare('UPDATE finance_matching_rules SET active=:active,updated_by_user_id=:uid,updated_by_role=:role WHERE id=:id AND deleted_at IS NULL');
        $stmt->execute([':active'=>$active?1:0,':uid'=>$user['id']??null,':role'=>$user['role']??null,':id'=>$id]);
    }

    public static function setPriority(PDO $pdo,int $id,int $priority,array $user): void
    {
        if($priority<0||$priority>1000000)throw new \InvalidArgumentException('Некорректный приоритет.');
        if(!self::findRule($pdo,$id))throw new \InvalidArgumentException('Правило не найдено.');
        $stmt=$pdo->prepare('UPDATE finance_matching_rules SET priority=:priority,updated_by_user_id=:uid,updated_by_role=:role WHERE id=:id AND deleted_at IS NULL');
        $stmt->execute([':priority'=>$priority,':uid'=>$user['id']??null,':role'=>$user['role']??null,':id'=>$id]);
    }

    public static function deleteRule(PDO $pdo,int $id,array $user): void
    {
        if(!self::findRule($pdo,$id))throw new \InvalidArgumentException('Правило не найдено.');
        $stmt=$pdo->prepare('UPDATE finance_matching_rules SET active=0,deleted_at=NOW(),updated_by_user_id=:uid,updated_by_role=:role WHERE id=:id AND deleted_at IS NULL');
        $stmt->execute([':uid'=>$user['id']??null,':role'=>$user['role']??null,':id'=>$id]);
    }
}
