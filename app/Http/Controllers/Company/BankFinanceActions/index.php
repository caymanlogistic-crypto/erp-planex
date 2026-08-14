<?php
requireRole(['company_owner']);
$pageTitle='Банковские счета';
$pageContext='Финансы › Банковские счета';
$companyId=(int)(getSessionCompanyId()??0);
$bankFinanceSuccess=$_SESSION['bank_finance_success']??null;
$bankFinanceError=$_SESSION['bank_finance_error']??null;
unset($_SESSION['bank_finance_success'],$_SESSION['bank_finance_error']);

$company=null;$accounts=[];$imports=[];$transactions=[];$dailyBalances=[];$bankSettings=[];$reconciliation=[];$dbError=null;
$txTotal=0;$txPages=1;$txPage=1;$search='';$classificationStatus='';

if($companyId>0){
    try{
        $central=$db->connection();
        $stmt=$central->prepare('SELECT * FROM companies WHERE id=?');
        $stmt->execute([$companyId]);
        $company=$stmt->fetch(PDO::FETCH_ASSOC)?:null;
        if($company&&($company['status']??'')==='active'){
            $pageContext='Финансы › Банковские счета › Компания: '.e($company['name']);
            $localPdo=(new \App\Core\Database(companyDatabaseConfig($config,$company)))->connection();
            $accounts=\App\Service\BankFinanceService::getAccounts($localPdo);
            $imports=\App\Service\BankFinanceService::getImports($localPdo);
            $dateFrom=$_GET['date_from']??null;
            $dateTo=$_GET['date_to']??null;
            $txPage=max(1,(int)($_GET['tx_page']??1));
            $txPerPage=max(1,min(500,(int)($_GET['tx_per_page']??100)));
            $search=trim((string)($_GET['q']??''));
            $classificationStatus=(string)($_GET['classification_status']??'');
            if($classificationStatus===''&&$search!==''){
                if(preg_match('/вручн/iu',$search))$classificationStatus='MANUAL';
                elseif(preg_match('/автомат/iu',$search))$classificationStatus='AUTO';
                elseif(preg_match('/конфликт|провер/iu',$search))$classificationStatus='NEEDS_REVIEW';
                elseif(preg_match('/не разнес/iu',$search))$classificationStatus='UNALLOCATED';
            }
            $txResult=\App\Service\FinanceMatchingRuleService::fetchBankRegister($localPdo,[
                'date_from'=>$dateFrom,'date_to'=>$dateTo,'search'=>$search,
                'classification_status'=>$classificationStatus,'page'=>$txPage,'per_page'=>$txPerPage
            ]);
            $transactions=$txResult['data'];
            $txTotal=$txResult['total'];
            $txPages=max(1,$txResult['pages']);
            $dailyBalances=\App\Service\BankFinanceService::getDailyBalances($localPdo,null,1,100,$dateFrom,$dateTo)['data'];
            try{
                $reconciliation=\App\Service\FinanceBankReconciliationService::reconcileAll($localPdo);
            }catch(Throwable $e){
                error_log('Bank reconciliation read error: '.$e->getMessage());
                $reconciliation=['accounts'=>[],'summary'=>['all_ok'=>false,'service_error'=>true]];
            }
        }
    }catch(Throwable $e){
        error_log('Bank accounts read error: '.$e->getMessage());
        $dbError='Не удалось загрузить банковские данные компании.';
    }
}

ob_start();
require base_path('app/View/pages/company_bank_accounts.php');
$content=ob_get_clean();

if(($company['status']??'')==='active'&&$dbError===null){
    if($bankFinanceSuccess){$content='<div class="notice success">'.e($bankFinanceSuccess).'</div>'.$content;}
    if($bankFinanceError){$content='<div class="notice warn">'.e($bankFinanceError).'</div>'.$content;}

    $statusOptions=[''=>'Все статусы','UNALLOCATED'=>'Не разнесено','AUTO'=>'Разнесено автоматически','MANUAL'=>'Разнесено вручную','NEEDS_REVIEW'=>'Конфликт / требует проверки'];
    $statusHtml='<label class="bank-date-field"><span class="bank-control-label">Статус</span><select name="classification_status" class="field-select">';
    foreach($statusOptions as $value=>$label){
        $statusHtml.='<option value="'.e($value).'"'.($classificationStatus===$value?' selected':'').'>'.e($label).'</option>';
    }
    $statusHtml.='</select></label>';
    $statusHtml.='<label class="bank-date-field"><span class="bank-control-label">Поиск</span><input type="search" name="q" class="field-input" value="'.e($search).'" placeholder="№, контрагент, ИНН, назначение, ЦФУ, ДДС"></label>';
    $submit='<button type="submit" class="btn btn-secondary btn-toolbar">Применить</button>';
    $content=str_replace($submit,$statusHtml.$submit,$content);

    $hasFilters=!empty($_GET['date_from'])||!empty($_GET['date_to'])||$classificationStatus!==''||$search!=='';
    $toolbarSearch='<input type="text" class="toolbar-search" placeholder="Поиск по таблице">';
    $toolbarReplacement=$hasFilters?'<a href="'.e(app_url('/company/finance/bank-accounts')).'" class="btn btn-ghost btn-toolbar">Сбросить фильтры</a>':'';
    $content=str_replace($toolbarSearch,$toolbarReplacement,$content);

    $creditHeader='<th class="col-tight">Кредит</th>';
    $extraHeaders='<th>ЦФУ</th><th>Статья ДДС</th><th>Статус</th><th></th>';
    $content=str_replace($creditHeader,$creditHeader.$extraHeaders,$content);

    foreach($transactions as $tx){
        $id=(int)($tx['id']??0);
        if($id<=0)continue;
        $rowStart=strpos($content,'<tr data-tx-id="'.$id.'"');
        if($rowStart===false)continue;
        $rowEnd=strpos($content,'</tr>',$rowStart);
        if($rowEnd===false)continue;
        $status=(string)($tx['classification_status']??'UNALLOCATED');
        $dds=trim((string)($tx['dds_category_code']??'').' '.(string)($tx['dds_category_name']??''));
        $cells='<td>'.e($tx['cash_flow_center_name']?:'—').'</td>';
        $cells.='<td>'.e($dds!==''?$dds:'—').'</td>';
        $cells.='<td><span class="'.e(\App\Service\FinanceMatchingRuleService::classificationBadgeClass($status)).'"><span class="dot"></span>'.e(\App\Service\FinanceMatchingRuleService::classificationStatusLabel($status)).'</span>';
        if(!empty($tx['is_internal_transfer'])){$cells.='<div class="field-note">Внутренний перевод</div>';}
        $cells.='</td>';
        $cells.='<td><button type="button" class="btn btn-secondary btn-sm" data-classify-url="'.e(app_url('/company/finance/bank-transactions/'.$id.'/classify')).'">'.(!empty($tx['is_internal_transfer'])?'Открыть':'Разнести').'</button></td>';
        $content=substr_replace($content,$cells,$rowEnd,0);
    }

    $classificationModal='<div id="bank-classify-modal" class="modal-overlay" role="dialog" aria-modal="true" data-close-on-overlay="0" data-close-on-escape="0"><div class="modal modal-lg"><div class="modal-head"><span class="modal-title">Разнести банковскую операцию</span><button type="button" class="modal-close" data-close-modal="bank-classify-modal">&times;</button></div><div id="bank-classify-modal-body"><div class="modal-body"><div class="empty-state compact"><p>Загрузка...</p></div></div></div></div></div>';
    $classificationModal.='<script>document.addEventListener("DOMContentLoaded",function(){document.querySelectorAll("[data-classify-url]").forEach(function(btn){btn.addEventListener("click",function(){var body=document.getElementById("bank-classify-modal-body");body.innerHTML="<div class=\"modal-body\"><div class=\"empty-state compact\"><p>Загрузка...</p></div></div>";window.openModal("bank-classify-modal");fetch(this.dataset.classifyUrl,{credentials:"same-origin"}).then(function(r){return r.text()}).then(function(html){body.innerHTML=html}).catch(function(){body.innerHTML="<div class=\"modal-body\"><div class=\"form-alert alert-error\">Не удалось загрузить форму.</div></div>";});});});});</script>';
    $content.=$classificationModal;

    $b='<button type="button" class="btn btn-secondary" data-open-modal="bank-statements-modal">Просмотр выписок</button>';
    $f='<form method="post" action="'.e(app_url('/company/finance/bank-accounts/refresh-from-mail')).'" class="inline-form">'.csrfField().'<button type="submit" class="btn btn-secondary">Обновить из почты</button></form>';
    $content=str_replace($b,$b.$f,$content);
}

require base_path('app/View/layouts/main.php');
