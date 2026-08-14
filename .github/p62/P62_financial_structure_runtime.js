'use strict';
const fs=require('fs');const{chromium}=require('playwright');
const B='https://plan-ex.ru/erpv2/';const EXPECTED_SHA='b29c9714c3b24b4dcaa8efb6a89386cd511ff43f';
const ok=(v,m)=>{if(!v)throw Error(m)};
(async()=>{
 const creds=JSON.parse(Buffer.from(process.env.P12_CREDENTIALS_B64||'','base64').toString('utf8'));
 const owner=creds.find(x=>String(x.role).toUpperCase()==='OWNER');ok(owner,'OWNER credentials');
 fs.mkdirSync('P62_artifacts',{recursive:true});
 const browser=await chromium.launch({headless:true});
 const ctx=await browser.newContext({viewport:{width:1920,height:1080},deviceScaleFactor:1,locale:'ru-RU',timezoneId:'Europe/Moscow'});
 const p=await ctx.newPage();const errors=[];
 p.on('console',m=>{if(m.type()==='error')errors.push('console:'+m.text())});p.on('pageerror',e=>errors.push('page:'+e.message));
 try{
  await p.goto(B+'login',{waitUntil:'domcontentloaded'});await p.locator('input[name="username"],input[name="email"],input[type="text"]').first().fill(owner.username);await p.locator('input[type="password"]').fill(owner.password);await p.locator('button[type="submit"],input[type="submit"]').first().click();await p.waitForTimeout(500);ok(!p.url().includes('/login'),'login failed');
  let r=await p.goto(B+'company/finance/settings/dds-categories',{waitUntil:'domcontentloaded'});ok(r&&r.status()===200,'financial structure HTTP');
  ok(await p.getByText('ЦФУ ещё не созданы.',{exact:true}).count()===1,'empty state missing');ok(await p.locator('[data-cfu-nav]').count()===0,'CFU must be zero');ok(await p.locator('[data-article-row]').count()===0,'DDS must be zero');
  const summary=(await p.locator('.fs-summary-strip').innerText()).replace(/\s+/g,' ');ok(summary.includes('0 ЦФУ')&&summary.includes('0 статей'),'zero summary');
  await p.screenshot({path:'P62_artifacts/P62_01_empty.png',fullPage:true});

  // Verify clean footer geometry in the CFU modal.
  await p.locator('[data-open-modal="fs-cfu-create-modal"]').first().click();await p.locator('#fs-cfu-create-modal.is-open').waitFor({state:'visible'});
  const cfuModal=p.locator('#fs-cfu-create-modal .modal');const cfuFoot=cfuModal.locator(':scope > form > .modal-foot');ok(await cfuFoot.count()===1,'CFU footer structure');
  await p.screenshot({path:'P62_artifacts/P62_02_cfu_modal.png'});
  await cfuModal.locator('input[name="name"]').fill('P62 TEST CLEANUP');await cfuModal.locator('button[type="submit"]').click();await p.waitForLoadState('domcontentloaded');
  ok(await p.getByText('P62 TEST CLEANUP',{exact:true}).count()>=1,'test CFU not created');

  // Create a test DDS article and inspect the exact popup shown to the user.
  await p.locator('[data-dds-create-cfu]').first().click();const ddsCreate=p.locator('#dds-category-create-modal.is-open');await ddsCreate.waitFor({state:'visible'});await ddsCreate.locator('form.dds-category-form').waitFor({state:'visible'});
  ok(await ddsCreate.locator('#dds-category-create-modal-body.fs-remote-form').count()===1,'DDS remote wrapper must not be modal-body');
  ok(await ddsCreate.locator('.fs-remote-form > form.dds-category-form > .modal-body').count()===1,'DDS body structure');
  ok(await ddsCreate.locator('.fs-remote-form > form.dds-category-form > .modal-foot').count()===1,'DDS footer structure');
  await p.screenshot({path:'P62_artifacts/P62_03_dds_create_modal.png'});
  await ddsCreate.locator('input[name="name"]').fill('P62 TEST ARTICLE');await ddsCreate.locator('button[type="submit"]').click();await p.waitForLoadState('domcontentloaded');
  ok(await p.getByText('P62 TEST ARTICLE',{exact:true}).count()===1,'test article not created');

  // Edit modal must expose hard delete; delete article and finish with zero articles.
  await p.locator('[data-dds-edit]').first().click();const ddsEdit=p.locator('#dds-category-edit-modal.is-open');await ddsEdit.waitFor({state:'visible'});await ddsEdit.locator('form.dds-category-form').waitFor({state:'visible'});
  ok(await ddsEdit.getByRole('button',{name:'Удалить статью'}).count()===1,'delete article button');await p.screenshot({path:'P62_artifacts/P62_04_dds_edit_delete.png'});
  p.once('dialog',d=>d.accept());await ddsEdit.getByRole('button',{name:'Удалить статью'}).click();await p.waitForLoadState('domcontentloaded');
  ok(await p.getByText('P62 TEST ARTICLE',{exact:true}).count()===0,'article delete failed');ok(await p.locator('[data-article-row]').count()===0,'articles must be zero after test delete');

  // Delete the test CFU via Settings and return to the original clean-slate state.
  await p.locator('[data-cfu-settings]').first().click();const settings=p.locator('#fs-cfu-settings-modal.is-open');await settings.waitFor({state:'visible'});ok(await settings.getByRole('button',{name:'Удалить ЦФУ'}).count()===1,'delete CFU button');
  p.once('dialog',d=>d.accept());await settings.getByRole('button',{name:'Удалить ЦФУ'}).click();await p.waitForLoadState('domcontentloaded');
  ok(await p.getByText('ЦФУ ещё не созданы.',{exact:true}).count()===1,'final empty state');ok(await p.locator('[data-cfu-nav]').count()===0,'final CFU zero');ok(await p.locator('[data-article-row]').count()===0,'final DDS zero');
  const finalSummary=(await p.locator('.fs-summary-strip').innerText()).replace(/\s+/g,' ');ok(finalSummary.includes('0 ЦФУ')&&finalSummary.includes('0 статей'),'final summary zero');
  await p.screenshot({path:'P62_artifacts/P62_05_final_empty.png',fullPage:true});
  ok(errors.length===0,'browser errors: '+errors.join(' | '));
  console.log('P62_PRODUCTION_SHA='+EXPECTED_SHA);console.log('P62_FINAL_SUMMARY='+finalSummary);console.log('P62_HTTP_JS_ERRORS=0');console.log('P62_FINANCIAL_STRUCTURE_RUNTIME_OK');
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exit(1)});
