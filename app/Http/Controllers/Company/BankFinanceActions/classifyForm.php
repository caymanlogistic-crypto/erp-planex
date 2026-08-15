<?php
requireRole(['company_owner']);
$companyId=(int)(getSessionCompanyId()??0);if($companyId<=0){http_response_code(400);echo '<div class="form-alert alert-error">Компания не найдена.</div>';return;}
try{
 $central=$db->connection();$s=$central->prepare("SELECT * FROM companies WHERE id=? AND status='active'");$s->execute([$companyId]);$company=$s->fetch(PDO::FETCH_ASSOC);if(!$company)throw new RuntimeException('Компания не найдена или неактивна.');
 $local=(new \App\Core\Database(companyDatabaseConfig($config,$company)))->connection();$tx=\App\Service\FinanceMatchingRuleService::fetchBankTransactionForClassification($local,(int)$bankTransactionId);if(!$tx)throw new RuntimeException('Банковская операция не найдена.');
 $cfu=\App\Service\FinanceMatchingRuleService::fetchCashFlowCenters($local,true);$direction=((float)($tx['credit_amount']??0)>0)?'INCOME':'EXPENSE';$dds=\App\Service\FinanceDdsCategoryService::fetchActiveForDirection($local,$direction);$ddsAllowedMap=\App\Service\FinanceStructureService::fetchAllowedMap($local,$direction);
 $employeeUsers=\App\Service\FinanceEmployeePaymentService::fetchActiveEmployees($local,$central,$companyId);
 foreach($employeeUsers as &$employeeUser){$employeeUser['id']=$employeeUser['identity_type']===\App\Service\FinanceEmployeePaymentService::IDENTITY_COMPANY_USER?-(int)$employeeUser['identity_id']:(int)$employeeUser['identity_id'];}unset($employeeUser);
 $employeeMovement=\App\Service\FinanceEmployeePaymentService::findByBankTransaction($local,(int)$bankTransactionId);
 require base_path('app/View/partials/company_bank_transaction_classify_form.php');
}catch(Throwable $e){http_response_code(422);echo '<div class="form-alert alert-error">'.e($e->getMessage()).'</div>';}
