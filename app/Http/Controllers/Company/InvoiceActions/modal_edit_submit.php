<?php

use App\Core\Database;
use App\Service\FinanceInvoiceService;
use App\Service\FinanceObligationService;

requireRole(['company_owner']);
verifyCsrfRequest();
$errors=[];

try {
    $companyId=(int)(getSessionCompanyId()??0);
    $company=$db->fetch('SELECT * FROM companies WHERE id=?',[$companyId]);
    if(!$company||$company['status']!=='active') throw new RuntimeException('Компания недоступна.');
    $localPdo=(new Database(companyDatabaseConfig($config,$company)))->connection();
    applyLocalMigrations($localPdo); FinanceObligationService::syncAllLinearRoutes($localPdo);
    $user=$_SESSION['user']??[]; $userId=(int)($user['user_id']??0); $roleCode=(string)($user['role_code']??'');
    $invoiceId=(int)$id; $existing=FinanceInvoiceService::fetchInvoiceById($localPdo,$invoiceId);
    if(!$existing) throw new RuntimeException('Счёт не найден.');

    $direction=$_POST['direction']??''; $number=trim((string)($_POST['number']??'')); $invoiceDate=trim((string)($_POST['invoice_date']??''));
    $cp=FinanceInvoiceService::normalizeCounterpartyInput($localPdo,$_POST['counterparty_entity_type']??null,$_POST['counterparty_entity_id']??null,$_POST['counterparty_name']??'',$_POST['counterparty_inn']??'');
    $errors=array_merge($errors,$cp['errors']);
    $amount=trim((string)($_POST['amount']??'0')); $vatRate=($_POST['vat_rate']??'')!==''?$_POST['vat_rate']:null;
    $basis=trim((string)($_POST['basis']??'')); $plannedDate=trim((string)($_POST['planned_payment_date']??'')); $comment=trim((string)($_POST['comment']??'')); $status=$_POST['status']??'draft';
    if(!in_array($direction,[FinanceInvoiceService::DIRECTION_OUTGOING,FinanceInvoiceService::DIRECTION_INCOMING],true))$errors[]='Укажите направление счёта.';
    if($number==='')$errors[]='Укажите номер счёта.'; if($invoiceDate==='')$errors[]='Укажите дату счёта.';
    $normalizedAmount=FinanceInvoiceService::normalizeMoneyInput($amount); if($normalizedAmount===null)$errors[]='Сумма должна быть больше нуля.';
    if(!FinanceInvoiceService::isVatRateInputValid($vatRate))$errors[]='Некорректное значение НДС.'; $normalizedVat=FinanceInvoiceService::normalizeVatRateInput($vatRate);

    $ids=is_array($_POST['obligation_id']??null)?$_POST['obligation_id']:[]; $amounts=is_array($_POST['obligation_amount']??null)?$_POST['obligation_amount']:[]; $obRows=[];
    foreach($ids as $idx=>$obId){if((int)$obId>0)$obRows[]=['obligation_id'=>(int)$obId,'amount'=>(string)($amounts[$idx]??'')];}

    if($errors===[]){
        $localPdo->beginTransaction();
        try{
            FinanceInvoiceService::updateInvoice($localPdo,$invoiceId,[
                'direction'=>$direction,'number'=>$number,'invoice_date'=>$invoiceDate,'counterparty_entity_type'=>$cp['type'],'counterparty_entity_id'=>$cp['id'],'counterparty_name'=>$cp['name'],'counterparty_inn'=>$cp['inn'],'amount'=>$normalizedAmount,'vat_rate'=>$normalizedVat,'basis'=>$basis,'planned_payment_date'=>$plannedDate!==''?$plannedDate:null,'comment'=>$comment,'status'=>$status,
            ],$userId,$roleCode);
            FinanceObligationService::replaceInvoiceLinks($localPdo,$invoiceId,$obRows,$user);
            $localPdo->commit();
        }catch(Throwable $e){if($localPdo->inTransaction())$localPdo->rollBack();throw $e;}
        $invoice=FinanceInvoiceService::fetchInvoiceById($localPdo,$invoiceId); $links=FinanceInvoiceService::fetchInvoiceLinks($localPdo,$invoiceId); $canEdit=true; $canDelete=true;
        require base_path('app/View/partials/company_invoice_modal_view.php'); exit;
    }
    $formError=implode(' ',$errors); $invoice=$existing; $clients=FinanceInvoiceService::fetchClientsForSelect($localPdo); $contractors=FinanceInvoiceService::fetchContractorsForSelect($localPdo); $isEdit=true;
    require base_path('app/View/partials/company_invoice_create_form.php');
}catch(Throwable $e){echo '<div class="form-alert alert-error">Ошибка: '.e($e->getMessage()).'</div>';}
