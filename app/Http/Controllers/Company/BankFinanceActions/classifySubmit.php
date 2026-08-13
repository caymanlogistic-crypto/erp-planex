<?php
requireRole(['company_owner']);verifyCsrfRequest();
$companyId=(int)(getSessionCompanyId()??0);if($companyId<=0){$_SESSION['bank_finance_error']='Компания не найдена.';redirect_to('/company/finance/bank-accounts');return;}
try{
 $central=$db->connection();$s=$central->prepare("SELECT * FROM companies WHERE id=? AND status='active'");$s->execute([$companyId]);$company=$s->fetch(PDO::FETCH_ASSOC);if(!$company)throw new RuntimeException('Компания не найдена или неактивна.');
 $local=(new \App\Core\Database(companyDatabaseConfig($config,$company)))->connection();$result=\App\Service\FinanceMatchingRuleService::manualClassifyBankTransaction($local,(int)$bankTransactionId,$_POST,['id'=>$_SESSION['user_id']??0,'role'=>$_SESSION['role_code']??'company_owner']);
 $_SESSION['bank_finance_success']='Операция разнесена вручную.'.(!empty($result['rule_id'])?' Создано правило #'.(int)$result['rule_id'].'.':'');
}catch(Throwable $e){$_SESSION['bank_finance_error']=$e->getMessage();}
redirect_to('/company/finance/bank-accounts');
