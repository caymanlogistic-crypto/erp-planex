'use strict';
const fs = require('fs');
const { chromium } = require('playwright');
const BASE='https://plan-ex.ru/erpv2/';
const must=(v,m)=>{if(!v)throw new Error(m)};
const pages=[['matching-rules','company/finance/settings/matching-rules'],['payment-calendar','company/finance/payment-calendar'],['cash-flow','company/finance/reports/cash-flow'],['management-balance','company/finance/reports/management-balance'],['payment-plan-fact','company/finance/reports/payment-plan-fact'],['bank-statement-settings','company/bank-statement-settings'],['logists','company/logists'],['responsible-assignments','company/responsible-assignments']];
(async()=>{
 const creds=JSON.parse(Buffer.from(process.env.P12_CREDENTIALS_B64||'','base64').toString('utf8'));
 const owner=creds.find(x=>String(x.role||'').toUpperCase()==='OWNER'); must(owner,'OWNER credentials missing');
 const chrome=['/usr/bin/google-chrome','/usr/bin/google-chrome-stable','/usr/bin/chromium-browser','/usr/bin/chromium'].find(fs.existsSync); must(chrome,'system Chrome missing');
 const browser=await chromium.launch({headless:true,executablePath:chrome,args:['--no-sandbox']});
 const context=await browser.newContext({viewport:{width:1920,height:1080},deviceScaleFactor:1,locale:'ru-RU',timezoneId:'Europe/Moscow'});
 const page=await context.newPage(); const bad=[],jsErrors=[],consoleErrors=[],metrics={};
 page.on('response',r=>{if(r.status()>=500)bad.push({status:r.status(),url:r.url()})});
 page.on('pageerror',e=>jsErrors.push(String(e&&e.message?e.message:e)));
 page.on('console',m=>{if(m.type()==='error')consoleErrors.push(m.text())});
 try{
  await page.goto(BASE+'login',{waitUntil:'domcontentloaded'});
  await page.locator('input[name="username"],input[name="email"],input[type="text"]').first().fill(owner.username);
  await page.locator('input[type="password"]').fill(owner.password);
  await page.locator('button[type="submit"],input[type="submit"]').first().click();
  await page.waitForTimeout(700); must(!page.url().includes('/login'),'login failed');
  for(const [key,path] of pages){
   const b0=bad.length,j0=jsErrors.length,c0=consoleErrors.length;
   const resp=await page.goto(BASE+path,{waitUntil:'networkidle'}); must(resp&&resp.status()===200,key+' HTTP');
   const shell=page.locator('.ux-shell[data-ux-page="'+key+'"]'); must(await shell.count()===1,key+' ux shell missing');
   const box=await shell.boundingBox(); must(box&&box.width>900&&box.width<=1320,key+' shell width '+(box&&box.width));
   must(await page.locator('.page-title').count()>=1,key+' title missing'); must(bad.length===b0,key+' server error'); must(jsErrors.length===j0,key+' JS error');
   metrics[key]={width:Math.round(box.width),height:Math.round(box.height),tables:await shell.locator('table').count(),kpis:await shell.locator('.ux-kpi').count(),consoleErrors:consoleErrors.slice(c0).slice(0,3)};
   await page.screenshot({path:'P59_'+key+'.png',fullPage:true});
  }
  await page.goto(BASE+'company/finance/settings/matching-rules',{waitUntil:'networkidle'});
  must(await page.locator('#finance-cfu-directory').count()===0,'duplicate CFU directory is still visible on matching rules');
  const editCount=await page.locator('[data-edit-id]').count();
  if(editCount>0) must(await page.locator('[data-rule-remove]').count()===editCount,'delete rule actions were not adapted to new UI');
  await page.locator('#matching-rule-create-btn').click(); await page.waitForFunction(()=>document.getElementById('matching-rule-create-modal')?.classList.contains('is-open')); await page.waitForTimeout(500); must((await page.locator('#matching-rule-create-modal-body').innerText()).trim().length>20,'rule form missing'); await page.evaluate(()=>window.closeModal('matching-rule-create-modal'));
  await page.goto(BASE+'company/logists',{waitUntil:'networkidle'}); await page.locator('[data-company-user-create-modal]').first().click(); await page.waitForFunction(()=>document.getElementById('company-user-create-modal')?.classList.contains('is-open')); must(await page.locator('#company-user-create-modal input').count()>0,'user modal missing'); await page.evaluate(()=>window.closeModal('company-user-create-modal'));
  await page.goto(BASE+'company/responsible-assignments',{waitUntil:'networkidle'}); must(await page.locator('.ux-segmented a').count()===4,'assignment tabs missing'); if(await page.locator('.js-mass-entity-checkbox').count()>0){const cb=page.locator('.js-mass-entity-checkbox').first();await cb.check();must((await page.locator('#selectedEntityCount').innerText()).includes('1'),'selection counter failed');await cb.uncheck();}
  console.log('P59_CHROME='+chrome);console.log('P59_METRICS='+JSON.stringify(metrics));console.log('P59_BAD_RESPONSES='+JSON.stringify(bad));console.log('P59_JS_ERRORS='+JSON.stringify(jsErrors));console.log('P59_CONSOLE_ERRORS='+JSON.stringify(consoleErrors.slice(0,20)));must(bad.length===0,'server errors');must(jsErrors.length===0,'JS errors');console.log('P59_OK');
 }finally{await browser.close()}
})().catch(e=>{console.error(e.stack||e);process.exit(1)});
