'use strict';
const { chromium } = require('playwright');
const BASE='https://plan-ex.ru/erpv2/';
const must=(v,m)=>{if(!v)throw new Error(m)};
(async()=>{
 const creds=JSON.parse(Buffer.from(process.env.P12_CREDENTIALS_B64||'','base64').toString('utf8'));
 const owner=creds.find(x=>String(x.role||'').toUpperCase()==='OWNER'); must(owner,'OWNER credentials');
 const browser=await chromium.launch({headless:true});
 const context=await browser.newContext({viewport:{width:1920,height:1080},locale:'ru-RU',timezoneId:'Europe/Moscow'});
 const page=await context.newPage();
 const consoleErrors=[]; const pageErrors=[]; const badResponses=[];
 page.on('console',m=>{if(m.type()==='error')consoleErrors.push(m.text())});
 page.on('pageerror',e=>pageErrors.push(String(e)));
 page.on('response',r=>{if(r.status()>=400)badResponses.push({status:r.status(),url:r.url()})});
 try{
  await page.goto(BASE+'login',{waitUntil:'domcontentloaded'});
  await page.locator('input[name="username"],input[name="email"],input[type="text"]').first().fill(owner.username);
  await page.locator('input[type="password"]').fill(owner.password);
  await page.locator('button[type="submit"],input[type="submit"]').first().click();
  await page.waitForTimeout(700); must(!page.url().includes('/login'),'login failed');
  const r=await page.goto(BASE+'company/finance/bank-accounts',{waitUntil:'networkidle'}); must(r&&r.status()===200,'bank HTTP');
  const body=(await page.locator('body').innerText()).split(/\r?\n/).map(s=>s.trim()).filter(Boolean);
  const suspicious=body.filter(s=>/(ошиб|расхожд|не удалось|предупреж|некоррект|разрыв|дубликат|несовпад)/i.test(s));
  console.log('P47_SUSPICIOUS='+JSON.stringify([...new Set(suspicious)]));
  console.log('P47_CONSOLE_ERRORS='+JSON.stringify(consoleErrors));
  console.log('P47_PAGE_ERRORS='+JSON.stringify(pageErrors));
  console.log('P47_BAD_RESPONSES='+JSON.stringify(badResponses));
  const recon=await page.request.get(BASE+'company/finance/bank-accounts/reconciliation/json');
  console.log('P47_RECON_HTTP='+recon.status());
  const j=await recon.json();
  console.log('P47_RECON_STATUS='+j.status);
  console.log('P47_RECON_SUMMARY='+JSON.stringify(j.reconciliation&&j.reconciliation.summary||{}));
  for(const a of (j.reconciliation&&j.reconciliation.accounts||[])){
   const badArithmetic=(a.statement_arithmetic||[]).filter(x=>x.status!=='OK');
   const badContinuity=((a.continuity||{}).items||[]).filter(x=>!['OK','FIRST'].includes(x.status));
   const mismatches=((a.transaction_aggregates||{}).mismatches||[]);
   console.log('P47_ACCOUNT='+JSON.stringify({account_id:a.account_id,account_number:a.account_number,status:a.status,badArithmetic,badContinuity,duplicateCount:(a.duplicate_check||[]).length,transactionMismatches:mismatches,current_balance:a.current_balance}));
  }
  await page.screenshot({path:'P47_bank.png',fullPage:true});
  console.log('P47_OK');
 }finally{await browser.close()}
})().catch(e=>{console.error(e.stack||e);process.exit(1)});
