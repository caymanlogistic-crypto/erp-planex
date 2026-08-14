'use strict';
const { chromium } = require('playwright');
const B='https://plan-ex.ru/erpv2/';
const ok=(v,m)=>{if(!v)throw new Error(m)};
(async()=>{
 const cs=JSON.parse(Buffer.from(process.env.P12_CREDENTIALS_B64||'','base64').toString('utf8'));
 const c=cs.find(x=>String(x.role).toUpperCase()==='OWNER'); ok(c,'OWNER credentials');
 const b=await chromium.launch({headless:true});
 const x=await b.newContext({viewport:{width:1920,height:1080},locale:'ru-RU',timezoneId:'Europe/Moscow'});
 const p=await x.newPage(); const errors=[];
 p.on('console',m=>{if(m.type()==='error')errors.push('console:'+m.text())});
 p.on('pageerror',e=>errors.push('page:'+e.message));
 try{
  await p.goto(B+'login');
  await p.locator('input[name="username"],input[name="email"],input[type="text"]').first().fill(c.username);
  await p.locator('input[type="password"]').fill(c.password);
  await p.locator('button[type="submit"],input[type="submit"]').first().click(); await p.waitForTimeout(700);
  ok(!p.url().includes('/login'),'login');
  let r=await p.goto(B+'company/finance/bank-accounts',{waitUntil:'domcontentloaded'}); ok(r.status()===200,'bank HTTP '+r.status());
  ok((await p.locator('h1.page-title').innerText()).trim()==='Выписки со счёта','bank H1');
  const actions=p.locator('.page-head-actions').first();
  const actionTexts=(await actions.locator('button,a').allTextContents()).map(s=>s.trim()).filter(Boolean);
  const expected=['Загрузить выписку XLSX','Просмотр выписок','Обновить из почты','Правила разнесения','Разнести по правилам'];
  ok(JSON.stringify(actionTexts)===JSON.stringify(expected),'action order '+JSON.stringify(actionTexts));
  for(const label of expected.slice(0,4)){
    const el=actions.getByText(label,{exact:true}).first(); ok(await el.count()===1,label+' exists');
    ok((await el.getAttribute('class')||'').includes('btn-secondary'),label+' gray');
  }
  const apply=actions.getByText('Разнести по правилам',{exact:true}).first();
  ok((await apply.getAttribute('class')||'').includes('btn-primary'),'apply primary');
  const applyForm=apply.locator('xpath=ancestor::form'); ok(await applyForm.count()===1,'apply form');
  ok((await applyForm.getAttribute('action')||'').endsWith('/company/finance/bank-accounts/apply-rules'),'apply endpoint');
  ok(await applyForm.locator('input[type="hidden"]').count()>0,'apply CSRF');
  const navLabels=(await p.locator('.nav-label').allTextContents()).map(s=>s.trim());
  ok(navLabels.includes('Выписки со счёта'),'sidebar statements');
  ok(!navLabels.includes('Правила разнесения'),'rules removed from sidebar');
  const breadcrumb=(await p.locator('body').innerText()).replace(/\s+/g,' ');
  ok(breadcrumb.includes('Финансы')&&breadcrumb.includes('Выписки со счёта'),'bank breadcrumb');
  await p.screenshot({path:'P68_bank.png',fullPage:true});

  await actions.getByText('Правила разнесения',{exact:true}).click(); await p.waitForLoadState('domcontentloaded');
  ok(p.url().includes('/company/finance/settings/matching-rules'),'rules navigation');
  ok((await p.locator('h1.page-title').innerText()).trim()==='Правила разнесения','rules H1');
  const back=p.getByText('Назад к выпискам',{exact:true}).first(); ok(await back.count()===1,'back button');
  ok((await back.getAttribute('class')||'').includes('btn-secondary'),'back gray');
  const rulesNavLabels=(await p.locator('.nav-label').allTextContents()).map(s=>s.trim());
  ok(rulesNavLabels.includes('Выписки со счёта'),'statements active parent exists');
  ok(!rulesNavLabels.includes('Правила разнесения'),'rules absent sidebar on rules page');
  const body=(await p.locator('body').innerText()).replace(/\s+/g,' ');
  ok(body.includes('Выписки со счёта')&&body.includes('Правила разнесения'),'rules breadcrumb hierarchy');
  await p.screenshot({path:'P68_rules.png',fullPage:true});
  await back.click(); await p.waitForLoadState('domcontentloaded');
  ok(p.url().includes('/company/finance/bank-accounts'),'back to statements');
  ok(errors.length===0,'browser errors '+JSON.stringify(errors));
  console.log('P68_ACTIONS='+JSON.stringify(actionTexts));
  console.log('P68_ERRORS='+JSON.stringify(errors));
  console.log('P68_OK');
 } finally { await b.close(); }
})().catch(e=>{console.error(e);process.exit(1)});
