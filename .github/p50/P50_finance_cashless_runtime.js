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
      'company/finance/receivables',
      'company/finance/payables',
      'company/finance/operations',
      'company/finance/payment-calendar',
      'company/finance/settings/matching-rules'
    ];

    for (const route of routes) {
      const response = await page.goto(B + route, { waitUntil: 'domcontentloaded' });
      ok(response && response.status() === 200, route + ' HTTP ' + (response && response.status()));
      const visible = await page.locator('body').innerText();
      ok(!/касс/iu.test(visible), route + ' contains retired terminology: ' + visible.match(/.{0,80}касс.{0,120}/iu));
      ok(await page.locator('a[href*="/company/finance/cash"]').count() === 0, route + ' exposes retired navigation');
      ok(!/(Fatal error|Parse error|Uncaught TypeError)/i.test(visible), route + ' fatal runtime error');
    }

    await page.goto(B + 'company/finance/employee-payments', { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(250);
    const title = (await page.locator('h1.page-title').first().innerText()).trim();
    ok(title === 'Взаиморасчёты с сотрудниками', 'employee workspace title: ' + title);
    const normalized = (await page.locator('.employee-report .page-head-right button:visible').allTextContents()).map(v => v.trim()).filter(Boolean);
    const expected = ['Получено от клиента', 'Передать деньги', 'Оплатить счёт', 'Прочий расход'];
    ok(JSON.stringify(normalized) === JSON.stringify(expected), 'employee action matrix mismatch: ' + JSON.stringify(normalized));
    for (const id of ['employee-client-receipt-open','employee-transfer-open','employee-invoice-payment-open','employee-personal-expense-open']) {
      ok(await page.locator('#' + id).count() === 1, id + ' missing or duplicated');
    }
    ok(await page.locator('button:visible', { hasText: /Изменить|Удалить передачу/i }).count() === 0, 'retired transfer edit/delete control visible');
    ok(await page.locator('[data-employee-transfer-edit]').count() === 0, 'retired transfer edit host remains');
    const employeeBody = await page.locator('body').innerText();
    ok(employeeBody.includes('Средства у сотрудника'), 'employee money explanation missing');
    ok(!/Выдача из|Возврат в|Основн.*касс/iu.test(employeeBody), 'retired employee semantics visible');

    await page.goto(B + 'company/finance/payables', { waitUntil: 'domcontentloaded' });
    const payablesBody = await page.locator('body').innerText();
    ok(payablesBody.includes('Расчётный счёт') && payablesBody.includes('Сотрудник'), 'approved carrier payment channels missing');
    ok(!/касс/iu.test(payablesBody), 'payables exposes retired payment channel');
    ok(await page.locator('.payables-toolbar__actions a').allTextContents().then(v => !v.some(x => /касс/iu.test(x))), 'payables toolbar exposes retired action');

    await page.goto(B + 'company/finance/settings/matching-rules', { waitUntil: 'domcontentloaded' });
    ok(await page.locator('#matching-rule-create-btn').count() === 1, 'matching rules create control missing');
    ok(!/касс/iu.test(await page.locator('body').innerText()), 'matching rules expose retired model');

    await page.goto(B + 'company/finance/invoices', { waitUntil: 'domcontentloaded' });
    ok(await page.locator('[data-invoice-cash-pay-toggle]').count() === 0, 'invoice page exposes retired payment button');
    ok(await page.locator('[data-invoice-cash-pay-form]').count() === 0, 'invoice page exposes retired payment form');

    const legacy = await page.goto(B + 'company/finance/cash', { waitUntil: 'domcontentloaded' });
    ok(legacy && legacy.status() === 200, 'legacy finance URL did not resolve safely');
    ok(page.url().includes('/company/finance/employee-payments'), 'legacy finance URL did not redirect to employee settlements');
    ok(!/касс/iu.test(await page.locator('body').innerText()), 'legacy redirect still exposes retired terminology');

    ok(errors.length === 0, 'browser errors: ' + JSON.stringify(errors));
    console.log('P50_ACTIONS=' + JSON.stringify(expected));
    console.log('P50_ROUTES=' + JSON.stringify(routes));
    console.log('P50_ERRORS=' + JSON.stringify(errors));
    console.log('P50_FINANCE_CASHLESS_UI_OK');
  } finally {
    await browser.close();
  }
})().catch(error => { console.error(error); process.exit(1); });
