'use strict';
const { chromium } = require('playwright');
const BASE='https://plan-ex.ru/erpv2/';
const must=(v,m)=>{if(!v)throw new Error(m)};
(async()=>{
 const creds=JSON.parse(Buffer.from(process.env.P12_CREDENTIALS_B64||'','base64').toString('utf8'));
 const owner=creds.find(x=>String(x.role||'').toUpperCase()==='OWNER'); must(owner,'OWNER credentials');
 const browser=await chromium.launch({headless:true});
 const context=await browser.newContext({viewport:{width:1920,height:1080},deviceScaleFactor:1,locale:'ru-RU',timezoneId:'Europe/Moscow'});
 const page=await context.newPage();
 const bad=[]; const jsErrors=[];
 page.on('response',r=>{if(r.status()>=400)bad.push({status:r.status(),url:r.url()})});
 page.on('pageerror',e=>jsErrors.push(String(e&&e.message?e.message:e)));
 try{
  await page.goto(BASE+'login',{waitUntil:'domcontentloaded'});
  await page.locator('input[name="username"],input[name="email"],input[type="text"]').first().fill(owner.username);
  await page.locator('input[type="password"]').fill(owner.password);
  await page.locator('button[type="submit"],input[type="submit"]').first().click();
  await page.waitForTimeout(700); must(!page.url().includes('/login'),'login failed');

  const resp=await page.goto(BASE+'company/finance/settings/dds-categories',{waitUntil:'networkidle'});
  must(resp&&resp.status()===200,'structure HTTP');
  must(await page.locator('.fs-workspace').count()===1,'master-detail workspace missing');
  must(await page.locator('.fs-sidebar').count()===1,'CFU sidebar missing');
  const nav=page.locator('[data-cfu-nav]'); must(await nav.count()>0,'CFU navigation missing');
  must(await page.locator('[data-cfu-pane]:visible').count()===1,'exactly one CFU pane must be visible');
  must(await page.locator('.fs-link-grid').count()===0,'legacy checkbox grid is still present');
  must(await page.locator('details.fs-links').count()===0,'legacy inline composition section is still present');
  must(await page.locator('input[name="code"]').count()===0,'technical DDS code input visible');
  const visibleText=await page.locator('.finance-structure-shell').innerText();
  must(!/Статья\s*#\d+/i.test(visibleText),'article technical ID is visible');
  must(!/(^|\s)ID\s*\d+/i.test(visibleText),'CFU technical ID is visible');
  const shellBox=await page.locator('.finance-structure-shell').boundingBox();
  must(shellBox&&shellBox.width<=1400,'workspace still stretches too wide');
  const wsBox=await page.locator('.fs-workspace').boundingBox();
  must(wsBox&&wsBox.width>900&&wsBox.height>=500,'workspace sizing is not desktop-usable');

  const activeNav=page.locator('[data-cfu-nav].is-active').first(); must(await activeNav.count()===1,'active CFU missing');
  const firstCenter=await activeNav.getAttribute('data-cfu-nav');
  const pane=page.locator('[data-cfu-pane="'+firstCenter+'"]');
  must(await pane.locator('.fs-pane-title').count()===1,'selected CFU title missing');
  must(await pane.locator('[data-article-row]').count()>0,'selected CFU article list missing');
  must(await pane.locator('[data-article-search]').count()===1,'article search missing');

  const cfuSearch=page.locator('#fs-cfu-search'); must(await cfuSearch.count()===1,'CFU search missing');
  const activeName=(await activeNav.locator('.fs-nav-name').innerText()).trim();
  await cfuSearch.fill(activeName.slice(0,Math.min(5,activeName.length)));
  must(await page.locator('[data-cfu-nav]:visible').count()>=1,'CFU search hides matching center');
  await cfuSearch.fill('');

  const articleSearch=pane.locator('[data-article-search]');
  const firstArticleName=(await pane.locator('[data-article-row]').first().locator('.fs-article-name').innerText()).trim();
  await articleSearch.fill(firstArticleName.slice(0,Math.min(6,firstArticleName.length)));
  must(await pane.locator('[data-article-row]:visible').count()>=1,'article search hides matching article');
  await articleSearch.fill('');

  const composition=pane.locator('[data-fs-links]').first(); must(await composition.count()===1,'composition button missing');
  await composition.click();
  const linksModal=page.locator('#fs-links-modal');
  must(await linksModal.evaluate(el=>el.classList.contains('is-open')),'composition modal did not open');
  must(await linksModal.locator('#fs-links-search').count()===1,'composition search missing');
  must(await linksModal.locator('#fs-link-list .fs-link-row').count()>0,'composition article rows missing');
  const selectedText=(await linksModal.locator('#fs-link-selected-count').innerText()).trim(); must(/^Выбрано:\s*\d+/.test(selectedText),'selected article count missing');
  const linkRows=linksModal.locator('#fs-link-list .fs-link-row');
  const linkName=(await linkRows.first().locator('.fs-link-name').innerText()).trim();
  await linksModal.locator('#fs-links-search').fill(linkName.slice(0,Math.min(6,linkName.length)));
  must(await linksModal.locator('#fs-link-list .fs-link-row:visible').count()>=1,'composition search failed');
  await linksModal.locator('[data-close-modal="fs-links-modal"]').first().click();
  await page.waitForTimeout(180);
  must(!(await linksModal.evaluate(el=>el.classList.contains('is-open'))),'composition modal did not close');

  const settings=pane.locator('[data-cfu-settings]').first(); must(await settings.count()===1,'CFU settings button missing');
  await settings.click();
  const settingsModal=page.locator('#fs-cfu-settings-modal');
  must(await settingsModal.evaluate(el=>el.classList.contains('is-open')),'CFU settings modal did not open');
  must(await settingsModal.locator('#fs-settings-name').count()===1,'CFU settings name missing');
  must(await settingsModal.locator('#fs-settings-toggle').count()===1,'archive/restore action missing');
  await settingsModal.locator('[data-close-modal="fs-cfu-settings-modal"]').first().click();
  await page.waitForTimeout(180);
  must(!(await settingsModal.evaluate(el=>el.classList.contains('is-open'))),'CFU settings modal did not close');

  await page.screenshot({path:'P56_financial_structure.png',fullPage:true});
  const metrics={cfuCount:await nav.count(),visiblePanes:await page.locator('[data-cfu-pane]:visible').count(),articleCount:await pane.locator('[data-article-row]').count(),shellWidth:Math.round(shellBox.width),workspaceWidth:Math.round(wsBox.width),workspaceHeight:Math.round(wsBox.height),selectedText};
  console.log('P56_METRICS='+JSON.stringify(metrics));
  console.log('P56_BAD_RESPONSES='+JSON.stringify(bad));
  console.log('P56_JS_ERRORS='+JSON.stringify(jsErrors));
  must(!bad.some(x=>x.status>=500),'server error observed');
  must(jsErrors.length===0,'JavaScript error observed');
  console.log('P56_OK');
 }finally{await browser.close();}
})().catch(e=>{console.error(e.stack||e);process.exit(1)});
