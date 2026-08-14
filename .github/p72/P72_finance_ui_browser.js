'use strict';
const { chromium } = require('playwright');
const BASE = 'https://plan-ex.ru/erpv2/';
const ok = (v, m) => { if (!v) throw new Error(m); };
(async () => {
  const creds = JSON.parse(Buffer.from(process.env.P12_CREDENTIALS_B64 || '', 'base64').toString('utf8'));
  const owner = creds.find(x => String(x.role).toUpperCase() === 'OWNER');
  ok(owner, 'OWNER credentials missing');
  const browser = await chromium.launch({ headless: true });
  const ctx = await browser.newContext({ viewport: { width: 1920, height: 1080 }, locale: 'ru-RU', timezoneId: 'Europe/Moscow' });
  const p = await ctx.newPage();
  const errors = [];
  p.on('console', m => { if (m.type() === 'error') errors.push('console:' + m.text()); });
  p.on('pageerror', e => errors.push('page:' + e.message));
  try {
    await p.goto(BASE + 'login', { waitUntil: 'domcontentloaded' });
    await p.locator('input[name="username"],input[name="email"],input[type="text"]').first().fill(owner.username);
    await p.locator('input[type="password"]').fill(owner.password);
    await p.locator('button[type="submit"],input[type="submit"]').first().click();
    await p.waitForTimeout(600);
    ok(!p.url().includes('/login'), 'login failed');

    let r = await p.goto(BASE + 'company/finance/settings/matching-rules', { waitUntil: 'domcontentloaded' });
    ok(r && r.status() === 200, 'rules HTTP');
    ok((await p.locator('h1.page-title').first().innerText()).trim() === 'Правила разнесения', 'rules title');
    ok(await p.locator('.ux-summary-strip').count() === 0, 'summary strip must be removed');
    ok(await p.locator('.ux-kpi-grid').count() === 0, 'KPI block must be removed');
    ok(await p.locator('#rules-visible-count').count() === 0, 'visible counter must be removed');
    ok(await p.locator('#rules-search').count() === 1, 'rules search must remain');
    ok(await p.locator('#rules-list').count() === 1, 'rules table must remain');
    await p.screenshot({ path: 'P72_matching_rules.png', fullPage: true });

    r = await p.goto(BASE + 'company/finance/bank-accounts', { waitUntil: 'domcontentloaded' });
    ok(r && r.status() === 200, 'bank HTTP');
    ok((await p.locator('h1.page-title').first().innerText()).trim() === 'Выписки со счёта', 'bank title');
    ok(await p.locator('.bank-control-meta').count() === 0, 'rows/page counter must be removed');
    const search = p.locator('input[placeholder="Поиск по таблице"]');
    console.log('P72_BANK_SEARCH_COUNT=' + await search.count());
    const actions = p.locator('.page-head-actions').first().locator('button,a');
    ok(await actions.count() === 5, 'five bank header actions must remain');
    await p.screenshot({ path: 'P72_bank_accounts.png', fullPage: true });

    ok(errors.length === 0, 'browser errors: ' + JSON.stringify(errors));
    console.log('P72_RULES_SUMMARY_STRIP=0');
    console.log('P72_RULES_KPI_GRID=0');
    console.log('P72_RULES_VISIBLE_COUNT=0');
    console.log('P72_BANK_CONTROL_META=0');
    console.log('P72_ERRORS=' + JSON.stringify(errors));
    console.log('P72_OK');
  } finally {
    await browser.close();
  }
})().catch(e => { console.error(e); process.exit(1); });
