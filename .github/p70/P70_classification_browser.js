'use strict';
const {chromium}=require('playwright');
const B='https://plan-ex.ru/erpv2/';
const ok=(v,m)=>{if(!v)throw new Error(m)};
(async()=>{
 const creds=JSON.parse(Buffer.from(process.env.P12_CREDENTIALS_B64||'','base64').toString('utf8'));
 const owner=creds.find(x=>String(x.role).toUpperCase()==='OWNER');ok(owner,'OWNER credentials');
 const browser=await chromium.launch({headless:true});
 const ctx=await browser.newContext({viewport:{width:1920,height:1080},locale:'ru-RU',timezoneId:'Europe/Moscow'});
 const p=await ctx.newPage();const errs=[];let nativeDialogs=0;
 p.on('console',m=>{if(m.type()==='error')errs.push('console:'+m.text())});
 p.on('pageerror',e=>errs.push('page:'+e.message));
 p.on('dialog',async d=>{nativeDialogs++;errs.push('native-dialog:'+d.message());await d.dismiss()});
 try{
  await p.goto(B+'login',{waitUntil:'domcontentloaded'});
  await p.locator('input[name="username"],input[name="email"],input[type="text"]').first().fill(owner.username);
  await p.locator('input[type="password"]').fill(owner.password);
  await p.locator('button[type="submit"],input[type="submit"]').first().click();
  await p.waitForTimeout(600);ok(!p.url().includes('/login'),'login failed');

  let r=await p.goto(B+'company/finance/bank-accounts',{waitUntil:'domcontentloaded'});ok(r&&r.status()===200,'bank HTTP');
  ok((await p.locator('h1.page-title').first().innerText()).trim()==='Выписки со счёта','bank title');
  const badges=p.locator('table.bank-transactions-table tbody .badge');
  const badgeData=[];
  for(let i=0;i<await badges.count();i++){
   const b=badges.nth(i);const text=(await b.innerText()).trim();const cls=await b.getAttribute('class');badgeData.push({text,cls});
   if(text.includes('Не разнесено'))ok(cls.includes('badge-danger'),'unallocated must be red');
   if(text.includes('Разнесено автоматически'))ok(cls.includes('badge-neutral'),'auto must be blue-violet');
   if(text.includes('Разнесено вручную'))ok(cls.includes('badge-ok'),'manual must be green');
   if(text.includes('Конфликт'))ok(cls.includes('badge-warning'),'review must be warning');
  }
  console.log('P70_BADGES='+JSON.stringify(badgeData));
  const rows=p.locator('table.bank-transactions-table tbody tr[data-tx-id]');
  let classified=null;
  for(let i=0;i<await rows.count();i++){
   const t=await rows.nth(i).innerText();if(t.includes('Разнесено вручную')||t.includes('Разнесено автоматически')){classified=rows.nth(i);break;}
  }
  ok(classified,'classified bank operation required for read-only UI acceptance');
  await classified.dblclick();
  const detail=p.locator('#tx-detail-modal.is-open');await detail.waitFor({state:'visible',timeout:10000});
  await detail.getByText('Удалить разнесение',{exact:true}).first().waitFor({state:'visible',timeout:10000});
  const clear=detail.getByText('Удалить разнесение',{exact:true}).first();
  ok((await clear.getAttribute('class')).includes('btn-danger'),'clear classification danger style');
  await clear.click();await p.waitForTimeout(150);
  const clearConfirm=p.locator('[id^="tx-clear-classification-modal-"].is-open');
  ok(await clearConfirm.count()===1,'ERP clear-classification confirmation modal');
  ok((await clearConfirm.innerText()).includes('Удалить разнесение этой операции?'),'clear confirmation copy');
  await p.screenshot({path:'P70_bank_clear_modal.png',fullPage:true});
  await clearConfirm.getByText('Отмена',{exact:true}).click();
  await p.locator('#tx-detail-modal .modal-close').first().click();

  r=await p.goto(B+'company/finance/settings/matching-rules',{waitUntil:'domcontentloaded'});ok(r&&r.status()===200,'rules HTTP');
  const table=p.locator('table#rules-list');ok(await table.count()===1,'standard rules table');
  const heads=(await table.locator('thead th').allTextContents()).map(x=>x.trim());
  for(const h of ['Приоритет','Правило','Условия','Результат','Статус','Действия'])ok(heads.includes(h),'rules header '+h);
  ok(await p.locator('.ux-rule-list').count()===0,'old card list removed');
  const deleteForm=p.locator('form[data-rule-delete-form]').first();ok(await deleteForm.count()===1,'rule delete action');
  await deleteForm.locator('button[type="submit"]').click();await p.waitForTimeout(150);
  const ruleConfirm=p.locator('#matching-rule-delete-modal.is-open');ok(await ruleConfirm.count()===1,'ERP rule delete confirmation modal');
  ok((await ruleConfirm.innerText()).includes('Ручные разнесения и другие правила не изменятся'),'safe rule delete explanation');
  await p.screenshot({path:'P70_rules_delete_modal.png',fullPage:true});
  await ruleConfirm.getByText('Отмена',{exact:true}).click();
  ok(nativeDialogs===0,'native dialogs are forbidden for tested destructive actions');
  ok(errs.length===0,'browser errors: '+JSON.stringify(errs));
  console.log('P70_ERRORS='+JSON.stringify(errs));
  console.log('P70_OK');
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exit(1)});
