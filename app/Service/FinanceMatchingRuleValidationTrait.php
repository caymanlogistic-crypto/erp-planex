<?php

namespace App\Service;

use PDO;

trait FinanceMatchingRuleValidationTrait
{
    private static function normalizeRule(PDO $pdo, array $data, ?array $old = null): array
    {
        $pick=static fn(string $key,mixed $default=null): mixed => array_key_exists($key,$data)?$data[$key]:($old[$key]??$default);
        $name=trim((string)$pick('name',''));
        if($name==='') throw new \InvalidArgumentException('Название правила обязательно.');
        $priority=(int)$pick('priority',100);
        if($priority<1||$priority>100000) throw new \InvalidArgumentException('Приоритет должен быть от 1 до 100000.');
        $direction=self::nullableString($pick('direction'));
        if($direction!==null){$direction=strtoupper($direction);if(!in_array($direction,self::ALLOWED_DIRECTIONS,true))throw new \InvalidArgumentException('Недопустимое направление правила.');}
        $action=(string)$pick('action_type','categorize');
        if(!in_array($action,self::ALLOWED_ACTION_TYPES,true)) throw new \InvalidArgumentException('Недопустимое действие правила.');
        $inn=self::validateInn(self::nullableString($pick('counterparty_inn')));
        $from=self::moneyOrNull($pick('amount_from'));$to=self::moneyOrNull($pick('amount_to'));
        if($from!==null&&$to!==null&&self::cents($from)>self::cents($to))throw new \InvalidArgumentException('Сумма «от» больше суммы «до».');
        $cfu=self::nullableInt($pick('target_cash_flow_center_id'));$dds=self::nullableInt($pick('target_dds_category_id'));$cash=self::nullableInt($pick('target_cash_account_id'));
        if($cfu!==null)self::assertCfu($pdo,$cfu);if($dds!==null)self::assertDds($pdo,$dds,$direction);if($cash!==null)self::assertCashAccount($pdo,$cash);
        if($cfu!==null&&$dds!==null)FinanceStructureService::assertLinkedPair($pdo,$cfu,$dds);
        if($action==='categorize'&&$cfu===null&&$dds===null)throw new \InvalidArgumentException('Для разнесения выберите ЦФУ и/или статью ДДС.');
        if($action==='transfer_to_cash'){
            if($cash===null)throw new \InvalidArgumentException('Для перевода выберите целевую кассу.');
            if($direction!==null&&$direction!=='EXPENSE')throw new \InvalidArgumentException('Перевод Банк → Касса применим только к расходу.');
            $direction='EXPENSE';
        }
        return [
            'name'=>$name,'priority'=>$priority,'direction'=>$direction,'bank_account_id'=>self::nullableInt($pick('bank_account_id')),
            'counterparty_inn'=>$inn,'counterparty_id'=>self::nullableInt($pick('counterparty_id')),'counterparty_type'=>self::nullableString($pick('counterparty_type')),
            'invoice_number_pattern'=>self::nullableString($pick('invoice_number_pattern')),'purpose_contains'=>self::nullableString($pick('purpose_contains')),
            'purpose_regex'=>self::validateRegex(self::nullableString($pick('purpose_regex'))),'amount_from'=>$from,'amount_to'=>$to,'action_type'=>$action,
            'target_dds_category_id'=>$dds,'target_cash_flow_center_id'=>$cfu,'target_cash_account_id'=>$cash,
            'target_counterparty_id'=>self::nullableInt($pick('target_counterparty_id')),'target_counterparty_type'=>self::nullableString($pick('target_counterparty_type')),
            // UI semantics: an active rule always applies automatically. The column
            // remains for backward-compatible storage but is no longer a user choice.
            'auto_apply'=>1,
        ];
    }

    private static function auditRule(PDO $pdo,int $id,string $action,?array $old,?array $new,array $user): void
    {
        FinanceAuditLogService::log($pdo,'finance_matching_rule',$id,$action,$old,$new,(int)($user['id']??0),(string)($user['role']??'system'));
    }
}
