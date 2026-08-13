<?php

namespace App\Service;

use PDO;

trait FinanceMatchingRulePreviewTrait
{
    public static function previewRule(PDO $pdo, array $rule, int $limit=20): array
    {
        $rows=$pdo->query("SELECT bt.*,fo.id AS finance_operation_id,COALESCE(fo.classification_status,'unclassified') AS classification_status FROM bank_transactions bt LEFT JOIN finance_operations fo ON fo.bank_transaction_id=bt.id AND fo.status <> 'CANCELLED' ORDER BY bt.operation_date DESC,bt.id DESC")->fetchAll(PDO::FETCH_ASSOC);
        $total=0;$sample=[];$conflicts=0;$rules=self::fetchRules($pdo,true);
        foreach($rows as $tx){$e=self::applyRuleToTransaction($pdo,$rule,$tx);if(empty($e['matched']))continue;$total++;if(count($sample)<$limit){$best=self::selectBestMatchingRule($pdo,$rules,$tx);$conflict=!empty($best['conflict']);if($conflict)$conflicts++;$sample[]=$tx+['preview_conflict'=>$conflict,'preview_result'=>$e['result'],'preview_reason'=>$e['reason']];}}
        return ['total'=>$total,'results'=>$sample,'sample_conflicts'=>$conflicts];
    }

    public static function testRuleOnTransaction(PDO $pdo,int $ruleId,int $transactionId): array
    {
        $rule=self::findRule($pdo,$ruleId);if(!$rule)throw new \InvalidArgumentException('Правило не найдено.');
        $stmt=$pdo->prepare('SELECT * FROM bank_transactions WHERE id=:id');$stmt->execute([':id'=>$transactionId]);$tx=$stmt->fetch(PDO::FETCH_ASSOC);
        if(!$tx)throw new \InvalidArgumentException('Банковская операция не найдена.');
        return self::applyRuleToTransaction($pdo,$rule,$tx);
    }
}
