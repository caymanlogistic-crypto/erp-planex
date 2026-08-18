'use strict';

const { chromium } = require('playwright');
const B = 'https://plan-ex.ru/erpv2/';
const ok = (value, message) => { if (!value) throw new Error(message); };

(async () => {
  const credentials = JSON.parse(Buffer.from(process.env.P12_CREDENTIALS_B64 || '', 'base64').toString('utf8'));
  const owner = credentials.find(x => String(x.role).toUpperCase() === 'OWNER');
  ok(owner, 'OWNER credentials');

  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({ viewport: { width: 1920, height: 1080 }, locale: 'ru-RU', timezoneId: 'Europe/Moscow' });
  const page = await context.newPage();
  const errors = [];
  page.on('console', msg => { if (msg.type() === 'error') errors.push('console:' + msg.text()); });
  page.on('pageerror', err => errors.push('page:' + err.message));

  try {
    await page.goto(B + 'login', { waitUntil: 'domcontentloaded' });
    await page.locator('input[name="username"],input[name="email"],input[type="text"]').first().fill(owner.username);
    await page.locator('input[type="password"]').fill(owner.password);
    await page.locator('button[type="submit"],input[type="submit"]').first().click();
    await page.waitForTimeout(700);
    ok(!page.url().includes('/login'), 'login failed');

    const routes = [
      'company/finance/employee-payments',
      'company/finance/dashboard',
      'company/finance/bank-accounts',
      'company/finance/invoices',
      'company/finance/operations',
      'company/finance/payment-calendar'
    ];

    for (const route of routes) {
      const response = await page.goto(B + route, { waitUntil: 'domcontentloaded' });
      ok(response && response.status() === 200, route + ' HTTP ' + (response && response.status()));
      const visible = await page.locator('body').innerText();
      ok(!/касс/iu.test(visible), route + ' contains retired cash terminology: ' + visible.match(/.{0,80}касс.{0,120}/iu));
      ok(await page.locator('a[href*="/company/finance/cash"]').count() === 0, route + ' exposes retired cash navigation');
      ok(!/(Fatal error|Parse error|Uncaught TypeError)/i.test(visible), route + ' fatal runtime error');
    }

    await page.goto(B + 'company/finance/employee-payments', { waitUntil: 'domcontentloaded' });
    const title = (await page.locator('h1.page-title').first().innerText()).trim();
    ok(title === 'Взаиморасчёты с сотрудниками', 'employee workspace title: ' + title);
    const body = await page.locator('body').innerText();
    ok(body.includes('Движение средств сотрудника'), 'new employee money explanation missing');
    ok(body.includes('Передать деньги'), 'employee transfer action missing');

    const legacy = await page.goto(B + 'company/finance/cash', { waitUntil: 'domcontentloaded' });
    ok(legacy && legacy.status() === 200, 'legacy finance URL did not resolve safely');
    ok(page.url().includes('/company/finance/employee-payments'), 'legacy finance URL did not redirect to employee settlements');
    const legacyBody = await page.locator('body').innerText();
    ok(!/касс/iu.test(legacyBody), 'legacy redirect still exposes retired terminology');

    ok(errors.length === 0, 'browser errors: ' + JSON.stringify(errors));
    console.log('P50_ERRORS=' + JSON.stringify(errors));
    console.log('P50_FINANCE_CASHLESS_UI_OK');
  } finally {
    await browser.close();
  }
})().catch(error => { console.error(error); process.exit(1); });
