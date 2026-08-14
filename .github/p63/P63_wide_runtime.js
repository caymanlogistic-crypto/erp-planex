'use strict';
const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');
const BASE = 'https://plan-ex.ru/erpv2/';
const EXPECTED_SHA = '953bb8056db6a711706fac2e0f9cd436c3bcdd6b';
const pages = [
  ['clients','company/clients'],
  ['linear','company/trips/linear'],
  ['departures','company/trips/departures'],
  ['route-executors','company/route-executors'],
  ['contractors','company/contractors'],
  ['drivers','company/drivers'],
  ['vehicle-sets','company/vehicle-sets'],
  ['finance-operations','company/finance/operations'],
  ['finance-invoices','company/finance/invoices'],
  ['finance-cash','company/finance/cash'],
  ['bank-accounts','company/finance/bank-accounts'],
  ['finance-dashboard','company/finance/dashboard'],
];
const assert = (v,m)=>{ if(!v) throw new Error(m); };
(async()=>{
  fs.mkdirSync('P63_artifacts',{recursive:true});
  const creds = JSON.parse(Buffer.from(process.env.P12_CREDENTIALS_B64||'','base64').toString('utf8'));
  const owner = creds.find(x=>String(x.role||'').toUpperCase()==='OWNER');
  assert(owner,'OWNER credentials missing');
  const browser = await chromium.launch({headless:true});
  const ctx = await browser.newContext({viewport:{width:1920,height:1080},deviceScaleFactor:1,locale:'ru-RU',timezoneId:'Europe/Moscow'});
  const p = await ctx.newPage();
  const errs=[];
  p.on('pageerror',e=>errs.push('PAGE:'+e.message));
  p.on('console',m=>{ if(m.type()==='error') errs.push('CONSOLE:'+m.text()); });
  p.on('response',r=>{ if(r.status()>=500) errs.push('HTTP'+r.status()+':'+r.url()); });
  try {
    await p.goto(BASE+'login',{waitUntil:'domcontentloaded'});
    await p.locator('input[name="username"],input[name="email"],input[type="text"]').first().fill(owner.username);
    await p.locator('input[type="password"]').fill(owner.password);
    await p.locator('button[type="submit"],input[type="submit"]').first().click();
    await p.waitForTimeout(700);
    assert(!p.url().includes('/login'),'login failed');
    const results=[];
    for(const [name,url] of pages){
      const beforeErr=errs.length;
      const r=await p.goto(BASE+url,{waitUntil:'domcontentloaded',timeout:30000});
      await p.waitForTimeout(350);
      assert(r && r.status()===200,`${name} HTTP ${r&&r.status()}`);
      assert(!p.url().includes('/login'),`${name} redirected to login`);
      const shell=p.locator('.workspace-shell-1690');
      assert(await shell.count()===1,`${name} missing 1690 shell`);
      const metrics=await shell.evaluate(el=>({width:el.getBoundingClientRect().width,max:getComputedStyle(el).maxWidth,contentClient:document.querySelector('main.content').clientWidth,contentScroll:document.querySelector('main.content').scrollWidth}));
      assert(metrics.width<=1690.5,`${name} shell too wide ${metrics.width}`);
      assert(metrics.width>=1650,`${name} shell unexpectedly narrow ${metrics.width}`);
      assert(metrics.contentScroll<=metrics.contentClient+1,`${name} horizontal overflow ${metrics.contentScroll}/${metrics.contentClient}`);
      const h1=(await p.locator('h1').first().textContent().catch(()=>''))?.trim()||'';
      const currentErrors=errs.slice(beforeErr);
      assert(currentErrors.length===0,`${name} runtime errors: ${currentErrors.join(' | ')}`);
      if(name==='linear'){
        const table=p.locator('table').first();
        assert(await table.count()===1,'linear table missing');
        const sig=await table.evaluate(t=>({headers:[...t.querySelectorAll('thead th')].map(x=>x.textContent.trim()),firstRowCells:t.querySelector('tbody tr')?[...t.querySelector('tbody tr').children].length:0}));
        console.log('P63_LINEAR_TABLE_SIGNATURE='+JSON.stringify(sig));
      }
      if(name==='finance-dashboard'){
        assert(await p.locator('.fd-kpis .fd-kpi').count()===5,'dashboard KPI count');
        assert(await p.locator('.fd-command-grid').count()===1,'dashboard command center');
        assert(await p.getByText('Требует внимания',{exact:true}).count()===1,'dashboard attention section');
        assert(await p.getByText('Быстрый доступ',{exact:true}).count()===1,'dashboard quick access');
        assert(await p.getByText('Управленческие отчёты',{exact:true}).count()===1,'dashboard reports');
      }
      await p.screenshot({path:path.join('P63_artifacts',name+'.png'),fullPage:true});
      results.push({name,url,finalUrl:p.url(),http:r.status(),h1,...metrics});
    }
    console.log('P63_EXPECTED_SHA='+EXPECTED_SHA);
    console.log('P63_RESULTS='+JSON.stringify(results));
    console.log('P63_HTTP_JS_ERRORS='+errs.length);
    console.log('P63_WIDE_RUNTIME_OK');
  } finally { await browser.close(); }
})().catch(e=>{console.error(e);process.exit(1)});
