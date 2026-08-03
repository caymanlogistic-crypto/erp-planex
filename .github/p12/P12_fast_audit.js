'use strict';
const fs = require('fs');
const path = require('path');
const { chromium } = require('playwright');

const BASE_URL = 'https://plan-ex.ru/erpv2/';
const OUT = path.resolve('P12_fast_output');
const SHOTS = path.join(OUT, 'P12_screenshots');
const TARGETS = JSON.parse(fs.readFileSync('.github/p12/P12_route_targets.json', 'utf8'));
fs.mkdirSync(SHOTS, { recursive: true });

function credentials() {
  return JSON.parse(Buffer.from(process.env.P12_CREDENTIALS_B64 || '', 'base64').toString('utf8'));
}
function slug(s) {
  return String(s || 'page').toLowerCase().replace(/^https?:\/\//,'').replace(/[^a-z0-9а-яё]+/giu,'_').replace(/^_+|_+$/g,'').slice(0,100);
}
async function login(context, cred) {
  const p = await context.newPage();
  await p.goto(BASE_URL + 'login', {waitUntil:'domcontentloaded', timeout:30000});
  await p.locator('input[name="username"],input[name="email"],input[type="email"],input[type="text"]').first().fill(cred.username);
  await p.locator('input[type="password"]').first().fill(cred.password);
  await Promise.all([
    p.waitForLoadState('domcontentloaded',{timeout:15000}).catch(()=>{}),
    p.locator('button[type="submit"],input[type="submit"]').first().click()
  ]);
  await p.waitForTimeout(600);
  if (/\/login(?:[/?#]|$)/i.test(p.url())) throw new Error(`Login failed for ${cred.role}`);
  return p;
}
async function inspect(page) {
  return page.evaluate(() => {
    function visible(el) {
      if (!(el instanceof Element)) return false;
      let cur = el;
      while (cur && cur !== document.body) {
        const s=getComputedStyle(cur), r=cur.getBoundingClientRect();
        if (cur.hidden || cur.getAttribute('aria-hidden') === 'true' || cur.classList.contains('is-hidden') || cur.classList.contains('hidden')) return false;
        if (s.display==='none'||s.visibility==='hidden'||Number(s.opacity||1)<=0.05||r.width<=1||r.height<=1) return false;
        cur=cur.parentElement;
      }
      return true;
    }
    function label(el) {
      return (el.getAttribute('aria-label')||el.getAttribute('title')||el.textContent||el.getAttribute('name')||el.tagName).trim().replace(/\s+/g,' ').slice(0,100);
    }
    const w=innerWidth,h=innerHeight,root=document.documentElement,body=document.body;
    const bodyScrollWidth=Math.max(root?.scrollWidth||0,body?.scrollWidth||0);
    const openModals=[...document.querySelectorAll('.modal-overlay.is-open')].filter(visible);
    const topModal=openModals.length ? openModals[openModals.length-1] : null;
    const controls=[...document.querySelectorAll('a[href],button,input:not([type="hidden"]),select,textarea,[role="button"],[role="combobox"]')]
      .filter(el=>{if(!visible(el))return false;if(topModal && !topModal.contains(el))return false;const r=el.getBoundingClientRect();return r.bottom>0&&r.right>0&&r.top<h&&r.left<w;});
    const clipped=[], overlaps=[];
    for(const el of controls.slice(0,500)){
      const r=el.getBoundingClientRect();
      if(r.left<-1||r.right>w+1||r.top<-1||r.bottom>h+1){if(clipped.length<15)clipped.push(label(el));}
      if(r.width<12||r.height<12)continue;
      const pts=[
        [r.left+r.width/2,r.top+r.height/2],
        [r.left+Math.min(6,r.width/4),r.top+Math.min(6,r.height/4)],
        [r.right-Math.min(6,r.width/4),r.top+Math.min(6,r.height/4)],
        [r.left+Math.min(6,r.width/4),r.bottom-Math.min(6,r.height/4)],
        [r.right-Math.min(6,r.width/4),r.bottom-Math.min(6,r.height/4)]
      ].filter(([x,y])=>x>=0&&y>=0&&x<w&&y<h);
      const blockers=new Map();
      for(const [x,y] of pts){
        const hit=document.elementFromPoint(x,y);
        if(!(hit instanceof Element)||hit===el||el.contains(hit)||hit.contains(el)||!visible(hit))continue;
        const s=getComputedStyle(hit);
        if(s.pointerEvents==='none'||Number(s.opacity||1)<=0.2)continue;
        blockers.set(hit,(blockers.get(hit)||0)+1);
      }
      let best=null,count=0;
      for(const [b,c] of blockers)if(c>count){best=b;count=c;}
      if(best&&count>=Math.max(2,Math.ceil(pts.length*.6))&&overlaps.length<15){
        overlaps.push({target:label(el),blocker:label(best),points:count,total:pts.length});
      }
    }
    return {
      bodyHorizontalScroll: bodyScrollWidth>w+1,
      bodyScrollWidth, viewportWidth:w,
      clippedControls:clipped.length, clippedSamples:clipped,
      overlapFailures:overlaps.length, overlapSamples:overlaps,
      visibleTextLength:(body?.innerText||'').trim().length,
      mojibake:/(?:Рџ|РЎ|Ð|Ñ|�)/.test(body?.innerText||''),
      tables:[...document.querySelectorAll('table')].filter(visible).map(t=>({rows:t.rows.length,columns:t.rows[0]?.cells.length||0,width:Math.round(t.getBoundingClientRect().width)})),
      forms:[...document.forms].filter(visible).map(f=>({method:(f.method||'get').toUpperCase(),action:f.action,fields:f.querySelectorAll('input,select,textarea').length}))
    };
  });
}
async function testModal(page, shotBase) {
  let opened = false, openedBy = null;
  const btn=page.locator('button[type="button"],[data-open-modal]').filter({hasText:/добавить|создать|открыть|редактировать/i}).first();
  if(await btn.count()&&await btn.isVisible().catch(()=>false)){
    await btn.click(); openedBy='button';
    await page.waitForTimeout(350);
    opened=await page.locator('.modal-overlay.is-open').count()>0;
    if(opened){
      for(let attempt=0; attempt<14; attempt++){
        const modalText=await page.locator('.modal-overlay.is-open').last().innerText().catch(()=>'');
        if(!/Загрузка\.\.\.|Загрузка…/i.test(modalText)) break;
        await page.waitForTimeout(250);
      }
      await page.waitForTimeout(150);
    }
  }
  if(!opened){
    const direct = await page.evaluate(() => {
      const all=[...document.querySelectorAll('.modal-overlay[id]')];
      const preferred=all.find(m=>m.querySelector('form')&&m.dataset.escapeLocked!=='1')||all.find(m=>m.dataset.escapeLocked!=='1');
      return preferred?.id||null;
    }).catch(()=>null);
    if(direct && await page.evaluate(()=>typeof window.openModal==='function')){
      await page.evaluate(id=>window.openModal(id),direct);
      openedBy='openModal:'+direct;
      await page.waitForTimeout(350);
      opened=await page.evaluate(id=>document.getElementById(id)?.classList.contains('is-open')||false,direct);
    }
  }
  if(!opened)return {requested:true,opened:false,openedBy,closedByEscape:null,withinViewport:null,hasClose:null,footerVisible:null};
  const before=await page.evaluate(()=>{
    const ms=[...document.querySelectorAll('.modal-overlay.is-open')].filter(m=>{const s=getComputedStyle(m),r=m.getBoundingClientRect();return s.display!=='none'&&s.visibility!=='hidden'&&r.width>1&&r.height>1;});
    const m=ms[ms.length-1],inner=m?.querySelector('.modal')||m;
    if(!m||!inner)return null;
    const r=inner.getBoundingClientRect(), footer=m.querySelector('.modal-foot,.modal-footer');
    return {
      id:m.id||null,count:ms.length,
      withinViewport:r.left>=-1&&r.top>=-1&&r.right<=innerWidth+1&&r.bottom<=innerHeight+1,
      hasClose:Boolean(m.querySelector('[data-modal-close],[data-close-modal],.modal-close,[aria-label*="закры" i]')),
      footerVisible:!footer||(()=>{const fr=footer.getBoundingClientRect(),s=getComputedStyle(footer);return s.display!=='none'&&s.visibility!=='hidden'&&fr.top>=-1&&fr.bottom<=innerHeight+1;})()
    };
  });
  await page.screenshot({path:shotBase+'__modal__1920x1080.png',fullPage:false});
  await page.keyboard.press('Escape'); await page.waitForTimeout(300);
  const after=await page.evaluate(id=>({
    targetOpen:id?Boolean(document.getElementById(id)?.classList.contains('is-open')):null,
    count:[...document.querySelectorAll('.modal-overlay.is-open')].filter(m=>{const s=getComputedStyle(m),r=m.getBoundingClientRect();return s.display!=='none'&&s.visibility!=='hidden'&&r.width>1&&r.height>1;}).length
  }),before?.id||null);
  const closed=before?.id?!after.targetOpen:after.count<(before?.count||0);
  if(!closed&&before?.id)await page.evaluate(id=>window.closeModal?.(id),before.id).catch(()=>{});
  return {requested:true,opened:true,openedBy,id:before?.id||null,closedByEscape:closed,withinViewport:before?.withinViewport,hasClose:before?.hasClose,footerVisible:before?.footerVisible,screenshot:path.relative(OUT,shotBase+'__modal__1920x1080.png')};
}

(async()=>{
  const creds=credentials();
  if(creds.length<3)throw new Error('Three credentials missing');
  const browser=await chromium.launch({headless:true});
  const results=[],roles=[],access=[];
  try{
    for(const cred of creds){
      const context=await browser.newContext({viewport:{width:1920,height:1080},deviceScaleFactor:1,ignoreHTTPSErrors:true,locale:'ru-RU',timezoneId:'Europe/Moscow'});
      const page=await login(context,cred);
      const roleTargets=TARGETS.filter(t=>t.role===cred.role);
      let idx=results.length+1;
      for(const target of roleTargets){
        const consoleErrors=[],pageErrors=[],networkErrors=[],badResponses=[];
        const c=m=>{if(m.type()==='error')consoleErrors.push(m.text())},pe=e=>pageErrors.push(String(e.message||e)),rf=r=>networkErrors.push(`${r.method()} ${r.url()} :: ${r.failure()?.errorText||'failed'}`),rs=r=>{if(r.status()>=400&&r.url()!==target.url)badResponses.push(`${r.status()} ${r.url()}`)};
        page.on('console',c);page.on('pageerror',pe);page.on('requestfailed',rf);page.on('response',rs);
        let response=null,navError=null;
        try{response=await page.goto(target.url,{waitUntil:'domcontentloaded',timeout:20000});await page.waitForTimeout(250);}catch(e){navError=String(e.message||e);}
        const layout=await inspect(page).catch(e=>({inspectError:String(e.message||e)}));
        const dir=path.join(SHOTS,cred.role.toLowerCase());fs.mkdirSync(dir,{recursive:true});
        const shot=path.join(dir,`P12_${String(idx).padStart(3,'0')}__${slug(cred.role)}__${slug(new URL(target.url).pathname)}__base__1920x1080.png`);
        await page.screenshot({path:shot,fullPage:false}).catch(e=>{navError=navError||String(e.message||e)});
        const modal=target.hadModal?await testModal(page,shot.replace(/__base__1920x1080\.png$/,'')):null;
        const defects=[],status=response?.status()||null;
        if(navError)defects.push('navigation_error');
        if(status!==target.expectedStatus)defects.push(`status:${status}!=${target.expectedStatus}`);
        if(layout.bodyHorizontalScroll)defects.push('body_horizontal_scroll');
        if(layout.overlapFailures)defects.push(`overlap:${layout.overlapFailures}`);
        if(layout.clippedControls)defects.push(`clipped:${layout.clippedControls}`);
        if(layout.mojibake)defects.push('mojibake');
        if((layout.visibleTextLength||0)<10)defects.push('empty_page');
        const effectiveConsoleErrors = target.expectedStatus === 403
          ? consoleErrors.filter(message => !/Failed to load resource:.*403/i.test(message))
          : consoleErrors;
        if(effectiveConsoleErrors.length||pageErrors.length)defects.push(`console:${effectiveConsoleErrors.length+pageErrors.length}`);
        if(networkErrors.length||badResponses.length)defects.push(`network:${networkErrors.length+badResponses.length}`);
        if(modal?.opened&&modal.closedByEscape===false)defects.push('modal_escape');
        if(modal?.opened&&modal.withinViewport===false)defects.push('modal_viewport');
        if(modal?.opened&&modal.hasClose===false)defects.push('modal_close_missing');
        if(modal?.opened&&modal.footerVisible===false)defects.push('modal_footer_hidden');
        if(target.expectedStatus===403){
          const text=await page.locator('body').innerText().catch(()=>'');
          if(!/доступ запрещ|forbidden|403/i.test(text))defects.push('forbidden_ux_missing');
        }
        results.push({role:cred.role,url:target.url,expectedStatus:target.expectedStatus,httpStatus:status,layout,modal,consoleErrors:[...effectiveConsoleErrors,...pageErrors],networkErrors:[...networkErrors,...badResponses],screenshots:[path.relative(OUT,shot),modal?.screenshot].filter(Boolean),defects,finalStatus:defects.length?'FAIL':'PASS'});
        fs.writeFileSync(path.join(OUT,'P12_FAST_ROUTE_MATRIX.checkpoint.json'),JSON.stringify(results,null,2));
        idx++;
        page.off('console',c);page.off('pageerror',pe);page.off('requestfailed',rf);page.off('response',rs);
      }
      roles.push({role:cred.role,targets:roleTargets.length});
      const probes=cred.role==='SUPERADMIN'?[['superadmin/companies',200],['company/dashboard',403]]:cred.role==='OWNER'?[['company/dashboard',200],['company/finance/operations',200],['superadmin/companies',403]]:[['company/dashboard',200],['company/finance/operations',403],['superadmin/companies',403]];
      for(const [p,expected] of probes){
        const q=await context.newPage();const rr=await q.goto(BASE_URL+p,{waitUntil:'domcontentloaded',timeout:20000}).catch(()=>null);const text=await q.locator('body').innerText().catch(()=>'');
        access.push({role:cred.role,path:'/'+p,expected,status:rr?.status()||null,pass:rr?.status()===expected&&(expected!==403||/доступ запрещ|forbidden|403/i.test(text))});
        await q.close();
      }
      await context.close();
    }
  }finally{await browser.close();}
  const fails=results.filter(r=>r.finalStatus==='FAIL'),accessFails=access.filter(a=>!a.pass);
  const summary={
    status:fails.length===0&&accessFails.length===0&&roles.length===3?'P12_PRODUCTION_ACCEPTED':'P12_NOT_ACCEPTED',
    applicationCommit:'2b61d9dcd32efe3a861341da7939b26a7cd02499',
    roles, routesTotal:results.length,routesPass:results.length-fails.length,routesFail:fails.length,
    modalsRequested:TARGETS.filter(t=>t.hadModal).length,modalsOpened:results.filter(r=>r.modal?.opened).length,modalsEscapePass:results.filter(r=>r.modal?.closedByEscape).length,
    screenshots:results.reduce((s,r)=>s+r.screenshots.length,0),
    bodyHorizontalScrollFailures:results.filter(r=>r.layout?.bodyHorizontalScroll).length,
    overlapFailures:results.reduce((s,r)=>s+Number(r.layout?.overlapFailures||0),0),
    clippedControlFailures:results.reduce((s,r)=>s+Number(r.layout?.clippedControls||0),0),
    consoleErrors:results.reduce((s,r)=>s+r.consoleErrors.length,0),networkErrors:results.reduce((s,r)=>s+r.networkErrors.length,0),
    accessChecks:access.length,accessFailures:accessFails.length
  };
  fs.writeFileSync(path.join(OUT,'P12_FAST_SUMMARY.json'),JSON.stringify(summary,null,2));
  fs.writeFileSync(path.join(OUT,'P12_FAST_ROUTE_MATRIX.json'),JSON.stringify(results,null,2));
  fs.writeFileSync(path.join(OUT,'P12_FAST_ROLE_ACCESS_MATRIX.json'),JSON.stringify(access,null,2));
  fs.writeFileSync(path.join(OUT,'P12_FAST_DEFECT_REGISTER.json'),JSON.stringify(fails,null,2));
  fs.writeFileSync(path.join(OUT,'P12_FAST_RUNTIME_REPORT.md'),[
    '# P12 deterministic production acceptance','',...Object.entries(summary).map(([k,v])=>`${k}: ${typeof v==='object'?JSON.stringify(v):v}`),'',summary.status
  ].join('\n'));
  console.log(JSON.stringify(summary));
  process.exitCode=summary.status==='P12_PRODUCTION_ACCEPTED'?0:1;
})().catch(e=>{fs.mkdirSync(OUT,{recursive:true});fs.writeFileSync(path.join(OUT,'P12_FAST_SUMMARY.json'),JSON.stringify({status:'P12_BLOCKED',error:String(e.stack||e)},null,2));console.error('P12 fast audit failed');process.exitCode=1;});
