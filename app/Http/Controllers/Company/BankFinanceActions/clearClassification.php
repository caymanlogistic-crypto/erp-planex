<?php
requireRole(['company_owner']);
verifyCsrfRequest();
$companyId=(int)(getSessionCompanyId()??0);
try{
 if($companyId<=0||$bankTransactionId<=0)throw new RuntimeException('Операция или компания не найдены.');
 $central=$db->connection();
 $s=$central->prepare("SELECT * FROM companies WHERE id=? AND status='active'");
 $s->execute([$companyId]);
 $company=$s->fetch(PDO::FETCH_ASSOC);
 if(!$company)throw new RuntimeException('Компания не найдена или неактивна.');
 $local=(new \App\Core\Database(companyDatabaseConfig($config,$company)))->connection();
 \App\Service\FinanceMatchingRuleService::clearBankTransactionClassification($local,(int)$bankTransactionId,['id'=>$_SESSION['user_id']??0,'role'=>$_SESSION['role_code']??'company_owner']);
 $_SESSION['bank_finance_success']='Разнесение удалено. Операция возвращена в статус «Не разнесено».';
}catch(Throwable $e){
 $_SESSION['bank_finance_error']=$e->getMessage();
}
redirect_to('/company/finance/bank-accounts');
