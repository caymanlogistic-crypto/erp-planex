'use strict';
const fs = require('fs');
const { chromium } = require('playwright');
const BASE = 'https://plan-ex.ru/erpv2/';
const OUT = 'P66_runtime_output';
fs.mkdirSync(OUT, { recursive: true });
function credentials(){ return JSON.parse(Buffer.from(process.env.P12_CREDENTIALS_B64 || '', 'base64').toString('utf8')); }
async function login(context, cred){
  const p=await context.newPage();
  await p.goto(BASE+'login',{waitUntil:'domcontentloaded',timeout:30000});
  await p.locator('input[name="username"],input[name="email"],input[type="email"],input[type="text"]').first().fill(cred.username);
  await p.locator('input[type="password"]').first().fill(cred.password);
  await Promise.all([p.waitForLoadState('domcontentloaded',{timeout:15000}).catch(()=>{}),p.locator('button[type="submit"],input[type="submit"]').first().click()]);
  await p.waitForTimeout(700);
  if(/\/login(?:[/?#]|$)/i.test(p.url())) throw new Error('OWNER login failed');
  return p;
}
async function inspect(page){ return page.evaluate(()=>{
  const table=document.querySelector('.linear-trip-registry');
  const scroll=table?.closest('.table-scroll');
  const root=document.documentElement, body=document.body;
  const cargos=[...document.querySelectorAll('.linear-trip-registry td.registry-cargo')].map(cell=>{
    const s=getComputedStyle(cell),r=cell.getBoundingClientRect();
    return {text:(cell.textContent||'').trim().replace(/\s+/g,' '),width:Math.round(r.width),clientWidth:cell.clientWidth,scrollWidth:cell.scrollWidth,whiteSpace:s.whiteSpace,overflowWrap:s.overflowWrap};
  });
  const headers=[...document.querySelectorAll('.linear-trip-registry thead th')].map(th=>({text:(th.textContent||'').trim(),width:Math.round(th.getBoundingClientRect().width)}));
  return {
    viewport:{width:innerWidth,height:innerHeight,dpr:devicePixelRatio},
    table:table?{width:Math.round(table.getBoundingClientRect().width),minWidth:getComputedStyle(table).minWidth,tableLayout:getComputedStyle(table).tableLayout}:null,
    scroll:scroll?{clientWidth:scroll.clientWidth,scrollWidth:scroll.scrollWidth,horizontalOverflow:scroll.scrollWidth>scroll.clientWidth+1}:null,
    body:{clientWidth:root.clientWidth,scrollWidth:Math.max(root.scrollWidth,body?.scrollWidth||0),horizontalOverflow:Math.max(root.scrollWidth,body?.scrollWidth||0)>root.clientWidth+1},
    cargos,headers
  };
}); }
(async()=>{
  const creds=credentials(); const owner=creds.find(c=>/owner/i.test(String(c.role||'')))||creds[0]; if(!owner)throw new Error('No runtime credential');
  const browser=await chromium.launch({headless:true}); const results=[];
  try{
    for(const c of [
      {label:'FULLHD_100',viewport:{width:1920,height:1080},deviceScaleFactor:1},
      {label:'FULLHD_125',viewport:{width:1536,height:864},deviceScaleFactor:1.25}
    ]){
      const context=await browser.newContext({viewport:c.viewport,deviceScaleFactor:c.deviceScaleFactor,locale:'ru-RU',timezoneId:'Europe/Moscow',ignoreHTTPSErrors:true});
      const page=await login(context,owner); const consoleErrors=[],pageErrors=[];
      page.on('console',m=>{if(m.type()==='error')consoleErrors.push(m.text())}); page.on('pageerror',e=>pageErrors.push(String(e.message||e)));
      const response=await page.goto(BASE+'company/trips/linear',{waitUntil:'domcontentloaded',timeout:30000}); await page.waitForTimeout(700);
      const data=await inspect(page); await page.screenshot({path:`${OUT}/${c.label}.png`,fullPage:false});
      const cargo=data.cargos.find(x=>/\s/.test(x.text)&&x.text.length>=14); const failures=[];
      if(response?.status()!==200)failures.push(`http:${response?.status()}`);
      if(!data.table||!data.scroll)failures.push('registry_missing');
      if(data.table?.tableLayout!=='fixed')failures.push(`table_layout:${data.table?.tableLayout}`);
      if(data.scroll?.horizontalOverflow)failures.push(`table_scroll:${data.scroll.scrollWidth}>${data.scroll.clientWidth}`);
      if(data.body.horizontalOverflow)failures.push(`body_scroll:${data.body.scrollWidth}>${data.body.clientWidth}`);
      if(cargo&&cargo.whiteSpace!=='normal')failures.push(`cargo_whitespace:${cargo.whiteSpace}`);
      if(cargo&&cargo.scrollWidth>cargo.clientWidth+1)failures.push(`cargo_overflow:${cargo.scrollWidth}>${cargo.clientWidth}`);
      if(cargo&&cargo.width<70)failures.push(`cargo_too_narrow:${cargo.width}`);
      if(consoleErrors.length||pageErrors.length)failures.push(`js_errors:${consoleErrors.length+pageErrors.length}`);
      results.push({label:c.label,httpStatus:response?.status()||null,data,multiwordCargo:cargo,consoleErrors,pageErrors,failures,pass:failures.length===0});
      await context.close();
    }
  } finally { await browser.close(); }
  fs.writeFileSync(`${OUT}/P66_RESULT.json`,JSON.stringify(results,null,2)); console.log(JSON.stringify(results,null,2));
  if(results.some(r=>!r.pass))process.exit(1);
})();
