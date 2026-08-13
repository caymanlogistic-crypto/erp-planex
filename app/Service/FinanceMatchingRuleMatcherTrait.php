<?php

namespace App\Service;

use PDO;

trait FinanceMatchingRuleMatcherTrait
{
    public static function applyRuleToTransaction(PDO $pdo, array $rule, array $tx): array
    {
        if (empty($rule['active']) || !empty($rule['deleted_at'])) return ['matched'=>false,'confidence'=>'low','result'=>'no_match','reason'=>'Правило неактивно.'];
        $credit=(string)($tx['credit_amount']??'0.00'); $debit=(string)($tx['debit_amount']??'0.00');
        $direction=self::decimalCompare($credit,'0.00')>0?'INCOME':(self::decimalCompare($debit,'0.00')>0?'EXPENSE':null); $amount=$direction==='INCOME'?$credit:$debit;
        if($direction===null)return ['matched'=>false,'confidence'=>'low','result'=>'no_match','reason'=>'Операция без положительной суммы.'];
        $checks=[];
        if(!empty($rule['direction'])){if($direction!==$rule['direction'])return ['matched'=>false,'confidence'=>'low','result'=>'no_match','reason'=>'Не совпало направление.'];$checks[]='направление';}
        if(!empty($rule['bank_account_id'])){if((int)($tx['account_id']??0)!==(int)$rule['bank_account_id'])return ['matched'=>false,'confidence'=>'low','result'=>'no_match','reason'=>'Не совпал банковский счёт.'];$checks[]='счёт';}
        if(!empty($rule['counterparty_inn'])){if(trim((string)($tx['counterparty_inn']??''))!==trim((string)$rule['counterparty_inn']))return ['matched'=>false,'confidence'=>'low','result'=>'no_match','reason'=>'Не совпал ИНН.'];$checks[]='ИНН';}
        $purpose=(string)($tx['purpose']??'');
        if(!empty($rule['purpose_contains'])){if(!self::containsCaseInsensitive($purpose,(string)$rule['purpose_contains']))return ['matched'=>false,'confidence'=>'low','result'=>'no_match','reason'=>'Не совпал фрагмент назначения.'];$checks[]='назначение';}
        if(!empty($rule['purpose_regex'])){$matched=@preg_match((string)$rule['purpose_regex'],$purpose)===1;if(!$matched)return ['matched'=>false,'confidence'=>'low','result'=>'no_match','reason'=>'Не совпало регулярное выражение.'];$checks[]='regex';}
        if(!empty($rule['invoice_number_pattern'])){if(!self::containsCaseInsensitive($purpose,(string)$rule['invoice_number_pattern']))return ['matched'=>false,'confidence'=>'low','result'=>'no_match','reason'=>'Не совпал номер/шаблон счёта.'];$checks[]='счёт/документ';}
        if($rule['amount_from']!==null&&$rule['amount_from']!==''){if(self::decimalCompare($amount,(string)$rule['amount_from'])<0)return ['matched'=>false,'confidence'=>'low','result'=>'no_match','reason'=>'Сумма ниже диапазона.'];$checks[]='сумма от';}
        if($rule['amount_to']!==null&&$rule['amount_to']!==''){if(self::decimalCompare($amount,(string)$rule['amount_to'])>0)return ['matched'=>false,'confidence'=>'low','result'=>'no_match','reason'=>'Сумма выше диапазона.'];$checks[]='сумма до';}
        if($checks===[])return ['matched'=>false,'confidence'=>'low','result'=>'no_match','reason'=>'У правила нет применимых условий.'];
        return ['matched'=>true,'confidence'=>'high','result'=>!empty($rule['auto_apply'])?'auto_apply':'suggest','reason'=>'Совпали все условия: '.implode(', ',$checks).'.','direction'=>$direction,'amount'=>$amount];
    }

    public static function selectBestMatchingRule(PDO $pdo,array $rules,array $tx): ?array
    {
        $matches=[];foreach($rules as $rule){$e=self::applyRuleToTransaction($pdo,$rule,$tx);if(!empty($e['matched']))$matches[]=['rule'=>$rule,'evaluation'=>$e];}
        if($matches===[])return null;
        $max=max(array_map(static fn($m)=>(int)$m['rule']['priority'],$matches));
        $top=array_values(array_filter($matches,static fn($m)=>(int)$m['rule']['priority']===$max));
        if(count($top)>1)return ['conflict'=>true,'priority'=>$max,'matches'=>$top,'result'=>'conflict','reason'=>'Совпало несколько правил одинакового максимального приоритета: '.implode(', ',array_map(static fn($m)=>'#'.(int)$m['rule']['id'],$top)).'.'];
        return $top[0]+['conflict'=>false,'priority'=>$max];
    }

    public static function findBestMatchingRule(PDO $pdo,array $tx): ?array
    {
        return self::selectBestMatchingRule($pdo,self::fetchRules($pdo,true),$tx);
    }
}
