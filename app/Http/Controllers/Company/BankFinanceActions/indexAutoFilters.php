<?php
/**
 * Presentation wrapper for the canonical bank register action.
 *
 * The legacy/current bank action remains the single source of data and matching behavior.
 * This wrapper only hardens UX requirements for the bank register:
 * - all GET filters apply automatically;
 * - no Apply/Reset action button is required;
 * - account column is visually removed;
 * - purpose column receives the freed width;
 * - classification status is conveyed by row background instead of a dedicated column.
 */
ob_start();
require base_path('app/Http/Controllers/Company/BankFinanceActions/index.php');
$html = ob_get_clean();

$style = <<<'HTML'
<style id="bank-register-auto-filter-style">
.bank-transactions-table th:nth-child(2),
.bank-transactions-table td:nth-child(2){display:none!important}
/* Status is removed from the DOM by the runtime below; hide it immediately to avoid a flash. */
.bank-transactions-table th:nth-child(10),
.bank-transactions-table td:nth-child(10){display:none!important}
.bank-transactions-table{table-layout:fixed;width:100%}
.bank-transactions-table th:nth-child(1),.bank-transactions-table td:nth-child(1){width:82px}
.bank-transactions-table th:nth-child(3),.bank-transactions-table td:nth-child(3){width:17%}
.bank-transactions-table th:nth-child(4),.bank-transactions-table td:nth-child(4){width:112px}
.bank-transactions-table th:nth-child(5),.bank-transactions-table td:nth-child(5){width:38%;min-width:390px;white-space:normal;overflow-wrap:anywhere}
.bank-transactions-table tbody tr.bank-row-unallocated>td{background:#fff0ed!important}
.bank-transactions-table tbody tr.bank-row-unallocated:hover>td{background:#fce8e4!important}
.bank-transactions-table tbody tr.bank-row-manual>td{background:#eff8ed!important}
.bank-transactions-table tbody tr.bank-row-manual:hover>td{background:#e7f3e4!important}
.bank-transactions-table tbody tr.bank-row-auto>td{background:#fff!important}
.bank-transactions-table tbody tr.bank-row-auto:hover>td{background:#fff!important}
.bank-controls-filter button[type="submit"],
.bank-controls-filter a.btn,
.bank-controls-right>a.btn[data-bank-filter-reset]{display:none!important}
.bank-controls-filter{align-items:end}
.bank-controls-filter [name="q"]{min-width:230px}
</style>
HTML;

$script = <<<'HTML'
<script id="bank-register-auto-filter-script">
(function(){
  function classificationFromLabel(value){
    var text=String(value||'').trim().toLocaleLowerCase('ru-RU');
    if(text.indexOf('вручную')!==-1) return 'MANUAL';
    if(text.indexOf('автоматически')!==-1) return 'AUTO';
    if(text.indexOf('не разнесено')!==-1) return 'UNALLOCATED';
    if(text.indexOf('конфликт')!==-1||text.indexOf('провер')!==-1) return 'NEEDS_REVIEW';
    return 'NEEDS_REVIEW';
  }

  function initClassificationPresentation(){
    var table=document.querySelector('.bank-transactions-table');
    if(!table||table.dataset.classificationPresentationReady==='1') return;
    table.dataset.classificationPresentationReady='1';
    var headers=Array.from(table.querySelectorAll('thead th'));
    var statusHeader=headers.find(function(th){return (th.textContent||'').trim()==='Статус';});
    if(!statusHeader) return;
    var statusIndex=statusHeader.cellIndex;
    Array.from(table.querySelectorAll('tbody tr[data-tx-id]')).forEach(function(row){
      var statusCell=row.cells[statusIndex];
      if(!statusCell) return;
      var status=classificationFromLabel(statusCell.textContent||'');
      row.dataset.classificationStatus=status;
      row.classList.remove('bank-row-unallocated','bank-row-manual','bank-row-auto');
      if(status==='MANUAL') row.classList.add('bank-row-manual');
      else if(status==='AUTO') row.classList.add('bank-row-auto');
      else row.classList.add('bank-row-unallocated');
      statusCell.remove();
    });
    statusHeader.remove();
  }

  function initBankRegisterFilters(){
    initClassificationPresentation();
    var form=document.querySelector('form.bank-controls-filter');
    if(!form||form.dataset.autoFiltersReady==='1') return;
    form.dataset.autoFiltersReady='1';
    form.setAttribute('autocomplete','off');

    Array.from(form.querySelectorAll('button[type="submit"]')).forEach(function(btn){
      if((btn.textContent||'').trim()==='Применить') btn.remove();
    });
    Array.from(form.querySelectorAll('a.btn')).forEach(function(link){
      if(/сброс/i.test(link.textContent||'')) link.remove();
    });
    var right=document.querySelector('.bank-controls-right');
    if(right){
      Array.from(right.querySelectorAll('a.btn')).forEach(function(link){
        if(/сброс/i.test(link.textContent||'')) link.remove();
      });
    }

    var pending=null;
    var lastSignature=null;
    var submitNow=function(){
      if(pending){clearTimeout(pending);pending=null;}
      var params=new URLSearchParams(new FormData(form));
      Array.from(params.keys()).forEach(function(key){if((params.get(key)||'').trim()==='')params.delete(key);});
      params.delete('tx_page');
      var signature=params.toString();
      var current=new URLSearchParams(window.location.search);
      current.delete('tx_page');
      Array.from(current.keys()).forEach(function(key){if((current.get(key)||'').trim()==='')current.delete(key);});
      if(signature===current.toString()||signature===lastSignature) return;
      lastSignature=signature;
      var target=window.location.pathname+(signature?'?'+signature:'');
      window.location.assign(target);
    };

    ['date_from','date_to','classification_status'].forEach(function(name){
      var field=form.querySelector('[name="'+name+'"]');
      if(field) field.addEventListener('change',submitNow);
    });
    var search=form.querySelector('[name="q"]');
    if(search){
      search.addEventListener('input',function(){
        if(pending) clearTimeout(pending);
        pending=setTimeout(submitNow,350);
      });
      search.addEventListener('search',submitNow);
      search.addEventListener('keydown',function(event){
        if(event.key==='Enter'){event.preventDefault();submitNow();}
      });
    }
    form.addEventListener('submit',function(event){event.preventDefault();submitNow();});
  }
  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',initBankRegisterFilters);else initBankRegisterFilters();
})();
</script>
HTML;

$html = str_replace('</head>', $style . "\n</head>", $html);
$html = str_replace('</body>', $script . "\n</body>", $html);
echo $html;
