<?php
namespace App\Service;
use PDO;
trait FinanceMatchingRuleCrudInputTrait
{
    private static function normalizeRuleInput(PDO $pdo, array $data, ?array $old=null): array
    {
        $get=static fn(string $k,mixed $d=null)=>array_key_exists($k,$data)?$data[$k]:($old[$k]??$d);
        $row=[
            'name'=>trim((string)$get('name','')),
            'active'=>!empty($get('active',1))?1:0,
            'priority'=>(int)$get('priority',100),
            'direction'=>self::normalizeNullableString($get('direction')),
            'bank_account_id'=>self::normalizeNullableInt($get('bank_account_id')),
            'counterparty_inn'=>self::normalizeNullableString($get('counterparty_inn')),
            'counterparty_id'=>self::normalizeNullableInt($get('counterparty_id')),
            'counterparty_type'=>self::normalizeNullableString($get('counterparty_type')),
            'invoice_number_pattern'=>self::normalizeNullableString($get('invoice_number_pattern')),
            'purpose_contains'=>self::normalizeNullableString($get('purpose_contains')),
            'purpose_regex'=>self::normalizeNullableString($get('purpose_regex')),
            'amount_from'=>self::normalizeNullableString($get('amount_from')),
            'amount_to'=>self::normalizeNullableString($get('amount_to')),
            'action_type'=>trim((string)$get('action_type','classify')),
            'target_dds_category_id'=>self::normalizeNullableInt($get('target_dds_category_id')),
            'target_cash_flow_center_id'=>self::normalizeNullableInt($get('target_cash_flow_center_id')),
            'target_cash_account_id'=>self::normalizeNullableInt($get('target_cash_account_id')),
            'target_counterparty_id'=>self::normalizeNullableInt($get('target_counterparty_id')),
            'target_counterparty_type'=>self::normalizeNullableString($get('target_counterparty_type')),
            'auto_apply'=>!empty($get('auto_apply',0))?1:0,
        ];
        if($row['name']==='')throw new \InvalidArgumentException('Название правила обязательно.');
        if($row['priority']<0||$row['priority']>1000000)throw new \InvalidArgumentException('Некорректный приоритет.');
        if($row['direction']!==null){$row['direction']=strtoupper($row['direction']);if(!in_array($row['direction'],self::ALLOWED_DIRECTIONS,true))throw new \InvalidArgumentException('Недопустимое направление правила.');}
        if(!in_array($row['action_type'],self::ALLOWED_ACTION_TYPES,true))throw new \InvalidArgumentException('Недопустимое действие правила.');
        if($row['action_type']==='bank_to_cash')$row['direction']='EXPENSE';
        if($row['purpose_regex']!==null&&@preg_match($row['purpose_regex'],'test')===false)throw new \InvalidArgumentException('Некорректное регулярное выражение.');
        foreach(['amount_from','amount_to'] as $f)if($row[$f]!==null&&!preg_match('/^\d+(\.\d{1,2})?$/',str_replace(',','.',$row[$f])))throw new \InvalidArgumentException('Некорректная сумма.');
        if($row['amount_from']!==null&&$row['amount_to']!==null&&self::decimalCompare($row['amount_from'],$row['amount_to'])>0)throw new \InvalidArgumentException('Минимальная сумма больше максимальной.');
        if($row['action_type']==='classify'){
            if(!$row['target_cash_flow_center_id']||!$row['target_dds_category_id'])throw new \InvalidArgumentException('Выберите ЦФУ и статью ДДС.');
            self::assertCashFlowCenterActive($pdo,$row['target_cash_flow_center_id']);self::assertDdsCategoryForDirection($pdo,$row['target_dds_category_id'],$row['direction']);
        }elseif($row['action_type']==='bank_to_cash'){
            if(!$row['target_cash_account_id'])throw new \InvalidArgumentException('Выберите целевую кассу.');self::assertCashAccountActive($pdo,$row['target_cash_account_id']);
        }elseif($row['action_type']==='categorize'&&$row['target_dds_category_id'])self::assertDdsCategoryForDirection($pdo,$row['target_dds_category_id'],$row['direction']);
        $has=$row['direction']!==null||$row['bank_account_id']!==null||$row['counterparty_inn']!==null||$row['invoice_number_pattern']!==null||$row['purpose_contains']!==null||$row['purpose_regex']!==null||$row['amount_from']!==null||$row['amount_to']!==null;
        if(!$has)throw new \InvalidArgumentException('У правила должно быть хотя бы одно явное условие.');
        return $row;
    }
}
