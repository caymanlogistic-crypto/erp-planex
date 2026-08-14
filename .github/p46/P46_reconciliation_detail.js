'use strict';
const { chromium } = require('playwright');
const BASE = 'https://plan-ex.ru/erpv2/';
function must(v,m){ if(!v) throw new Error(m); }
(async()=>{
  const creds = JSON.parse(Buffer.from(process.env.P12_CREDENTIALS_B64 || '', 'base64').toString('utf8'));
  const owner = creds.find(x => String(x.role || '').toUpperCase() === 'OWNER');
  must(owner, 'OWNER credentials missing');
  const browser = await chromium.launch({headless:true});
  const context = await browser.newContext({viewport:{width:1920,height:1080},locale:'ru-RU',timezoneId:'Europe/Moscow'});
  const page = await context.newPage();
  try {
    await page.goto(BASE + 'login', {waitUntil:'domcontentloaded'});
    await page.locator('input[name="username"],input[name="email"],input[type="text"]').first().fill(owner.username);
    await page.locator('input[type="password"]').fill(owner.password);
    await page.locator('button[type="submit"],input[type="submit"]').first().click();
    await page.waitForTimeout(700);
    must(!page.url().includes('/login'), 'login failed');
    const response = await page.request.get(BASE + 'company/finance/bank-accounts/reconciliation/json');
    must(response.status() === 200, 'reconciliation endpoint HTTP ' + response.status());
    const data = await response.json();
    must(data.status === 'ok', data.message || 'reconciliation status not ok');
    console.log('P46_DETAIL_BEGIN');
    console.log(JSON.stringify(data.reconciliation, null, 2));
    console.log('P46_DETAIL_END');
  } finally { await browser.close(); }
})().catch(e=>{ console.error(e.stack || e); process.exit(1); });
