'use strict';
const { chromium } = require('playwright');
const BASE = 'https://plan-ex.ru/erpv2/';
const must = (v,m)=>{ if(!v) throw new Error(m); };
const pages = [
  ['matching-rules','company/finance/settings/matching-rules'],
  ['payment-calendar','company/finance/payment-calendar'],
  ['cash-flow','company/finance/reports/cash-flow'],
  ['management-balance','company/finance/reports/management-balance'],
  ['payment-plan-fact','company/finance/reports/payment-plan-fact'],
  ['bank-statement-settings','company/bank-statement-settings'],
  ['logists','company/logists'],
  ['responsible-assignments','company/responsible-assignments']
];
(async()=>{
  const creds = JSON.parse(Buffer.from(process.env.P12_CREDENTIALS_B64||'','base64').toString('utf8'));
  const owner = creds.find(x=>String(x.role||'').toUpperCase()==='OWNER');
  must(owner,'OWNER credentials missing');
  const browser = await chromium.launch({headless:true});
  const context = await browser.newContext({viewport:{width:1920,height:1080},deviceScaleFactor:1,locale:'ru-RU',timezoneId:'Europe/Moscow'});
  const page = await context.newPage();
  const bad=[]; const jsErrors=[]; const consoleErrors=[]; const metrics={};
  page.on('response',r=>{ if(r.status()>=500) bad.push({status:r.status(),url:r.url()}); });
  page.on('pageerror',e=>jsErrors.push(String(e&&e.message?e.message:e)));
  page.on('console',m=>{ if(m.type()==='error') consoleErrors.push(m.text()); });
  try {
    await page.goto(BASE+'login',{waitUntil:'domcontentloaded'});
    await page.locator('input[name="username"],input[name="email"],input[type="text"]').first().fill(owner.username);
    await page.locator('input[type="password"]').fill(owner.password);
    await page.locator('button[type="submit"],input[type="submit"]').first().click();
    await page.waitForTimeout(700);
    must(!page.url().includes('/login'),'login failed');

    for (const [key,path] of pages) {
      const beforeBad=bad.length, beforeJs=jsErrors.length, beforeConsole=consoleErrors.length;
      const resp=await page.goto(BASE+path,{waitUntil:'networkidle'});
      must(resp && resp.status()===200,key+' HTTP '+(resp&&resp.status()));
      const shell=page.locator('.ux-shell[data-ux-page="'+key+'"]');
      must(await shell.count()===1,key+' ux shell missing');
      const box=await shell.boundingBox();
      must(box && box.width>900 && box.width<=1320,key+' invalid shell width '+(box&&box.width));
      must(box && box.x>=200,key+' shell alignment invalid');
      must(await page.locator('.page-title').count()>=1,key+' page title missing');
      must(bad.length===beforeBad,key+' server error observed');
      must(jsErrors.length===beforeJs,key+' JS error observed: '+jsErrors.slice(beforeJs).join(' | '));
      const tables=await shell.locator('table').count();
      const kpis=await shell.locator('.ux-kpi').count();
      metrics[key]={width:Math.round(box.width),height:Math.round(box.height),tables,kpis};
      await page.screenshot({path:'P58_'+key+'.png',fullPage:true});
      if(consoleErrors.length>beforeConsole){
        metrics[key].consoleErrors=consoleErrors.slice(beforeConsole).slice(0,3);
      }
    }

    // Matching rules: modal must open and load without mutating data.
    await page.goto(BASE+'company/finance/settings/matching-rules',{waitUntil:'networkidle'});
    const createRule=page.locator('#matching-rule-create-btn');
    must(await createRule.count()===1,'matching create button missing');
    await createRule.click();
    const ruleModal=page.locator('#matching-rule-create-modal');
    await page.waitForFunction(()=>document.getElementById('matching-rule-create-modal')?.classList.contains('is-open'));
    await page.waitForTimeout(500);
    must((await page.locator('#matching-rule-create-modal-body').innerText()).trim().length>20,'matching create form did not load');
    await page.evaluate(()=>window.closeModal('matching-rule-create-modal'));
    must(!(await ruleModal.getAttribute('class')).split(/\s+/).includes('is-open'),'matching modal did not close');

    // Logists: create modal interaction only, no submit.
    await page.goto(BASE+'company/logists',{waitUntil:'networkidle'});
    const createUser=page.locator('[data-company-user-create-modal]').first();
    must(await createUser.count()===1,'create user button missing');
    await createUser.click();
    await page.waitForFunction(()=>document.getElementById('company-user-create-modal')?.classList.contains('is-open'));
    must(await page.locator('#company-user-create-modal input').count()>0,'user create modal form missing');
    await page.evaluate(()=>window.closeModal('company-user-create-modal'));

    // Responsible assignments: segmented navigation and bulk area when records exist.
    await page.goto(BASE+'company/responsible-assignments',{waitUntil:'networkidle'});
    must(await page.locator('.ux-segmented a').count()===4,'responsible assignment tabs missing');
    if(await page.locator('.js-mass-entity-checkbox').count()>0){
      must(await page.locator('#massReassignToolbarForm').count()===1,'bulk reassignment zone missing');
      const cb=page.locator('.js-mass-entity-checkbox').first();
      await cb.check();
      must((await page.locator('#selectedEntityCount').innerText()).includes('1'),'selected counter not updated');
      await cb.uncheck();
    }

    console.log('P58_METRICS='+JSON.stringify(metrics));
    console.log('P58_BAD_RESPONSES='+JSON.stringify(bad));
    console.log('P58_JS_ERRORS='+JSON.stringify(jsErrors));
    console.log('P58_CONSOLE_ERRORS='+JSON.stringify(consoleErrors.slice(0,20)));
    must(bad.length===0,'server errors detected');
    must(jsErrors.length===0,'page JS errors detected');
    console.log('P58_OK');
  } finally { await browser.close(); }
})().catch(e=>{ console.error(e.stack||e); process.exit(1); });
