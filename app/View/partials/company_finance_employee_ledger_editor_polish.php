<?php
if (empty($selectedEmployee) || ($selectedRef ?? '') === '') return;
?>
<script>
(function(){
  const invoiceModal=document.getElementById('employee-invoice-payment-modal');
  const invoiceCancelForm=document.getElementById('employee-invoice-cancel-form');
  const invoiceCancelId=document.getElementById('employee-invoice-cancel-id');
  const invoiceEventId=document.getElementById('employee-invoice-event-id');
  const invoiceCreateOpen=document.getElementById('employee-invoice-payment-open');
  if(invoiceModal&&invoiceCancelForm&&invoiceCancelId){
    const foot=invoiceModal.querySelector('.modal-foot');
    if(foot&&!foot.querySelector('[data-standard-invoice-delete]')){
      const del=document.createElement('button');
      del.type='button';del.className='btn btn-ghost fs-delete-action';del.textContent='Удалить';del.dataset.standardInvoiceDelete='1';del.style.display='none';
      foot.insertBefore(del,foot.firstChild);
      del.addEventListener('click',()=>{
        const id=String(invoiceEventId&&invoiceEventId.value||'');
        if(!id)return;
        if(!confirm('Удалить оплату счёта? Счёт и обязательства будут пересчитаны.'))return;
        invoiceCancelId.value=id;invoiceCancelForm.submit();
      });
      const observer=new MutationObserver(()=>{del.style.display=String(invoiceEventId&&invoiceEventId.value||'')!==''?'':'none';});
      if(invoiceEventId)observer.observe(invoiceEventId,{attributes:true,childList:true,subtree:true,characterData:true});
      document.addEventListener('dblclick',()=>{setTimeout(()=>{del.style.display=String(invoiceEventId&&invoiceEventId.value||'')!==''?'':'none';},0);});
      if(invoiceCreateOpen)invoiceCreateOpen.addEventListener('click',()=>{del.style.display='none';});
    }
  }

  const personalHost=document.getElementById('employee-personal-expense-host');
  if(personalHost){
    const normalizePersonalDelete=()=>{
      const del=personalHost.querySelector('[data-personal-expense-cancel-event]');
      if(del)del.textContent='Удалить';
    };
    new MutationObserver(normalizePersonalDelete).observe(personalHost,{childList:true,subtree:true});
    normalizePersonalDelete();
  }
})();
</script>
