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
 try{
  await page.goto(BASE+'login',{waitUntil:'domcontentloaded'});
  await page.locator('input[name="username"],input[name="email"],input[type="text"]').first().fill(owner.username);
  await page.locator('input[type="password"]').fill(owner.password);
  await page.locator('button[type="submit"],input[type="submit"]').first().click();
  await page.waitForTimeout(700); must(!page.url().includes('/login'),'login failed');
  const r=await page.goto(BASE+'company/finance/bank-accounts',{waitUntil:'networkidle'}); must(r&&r.status()===200,'bank HTTP');
  const row=page.locator('tr[data-tx-id]').first(); must(await row.count(),'bank row missing');
  const attrs=await row.evaluate(el=>({id:el.getAttribute('data-tx-id'),url:el.getAttribute('data-classify-url'),date:el.getAttribute('data-tx-date'),purpose:el.getAttribute('data-tx-purpose')}));
  console.log('P47_ROW='+JSON.stringify(attrs));
  must(attrs.url,'classify url missing');
  const classify=await page.request.get(new URL(attrs.url,page.url()).href);
  const classifyText=await classify.text();
  console.log('P47_CLASSIFY_HTTP='+classify.status());
  console.log('P47_CLASSIFY_BODY='+JSON.stringify(classifyText.slice(0,1000)));
  const recon=await page.request.get(BASE+'company/finance/bank-accounts/reconciliation/json');
  const j=await recon.json();
  console.log('P47_RECON_STATUS='+j.status);
  console.log('P47_RECON_MESSAGE='+String(j.message||''));
  console.log('P47_RECON_SUMMARY='+JSON.stringify(j.reconciliation&&j.reconciliation.summary||{}));
  console.log('P47_OK');
 }finally{await browser.close()}
})().catch(e=>{console.error(e.stack||e);process.exit(1)});