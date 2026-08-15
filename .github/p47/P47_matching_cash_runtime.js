'use strict';

const { chromium } = require('playwright');
const BASE = 'https://plan-ex.ru/erpv2/';
const ok = (value, message) => { if (!value) throw new Error(message); };

(async () => {
  const credentials = JSON.parse(Buffer.from(process.env.P12_CREDENTIALS_B64 || '', 'base64').toString('utf8'));
  const owner = credentials.find(x => String(x.role).toUpperCase() === 'OWNER');
  ok(owner, 'OWNER credentials missing');

  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({ viewport: { width: 1920, height: 1080 }, locale: 'ru-RU', timezoneId: 'Europe/Moscow' });
  const page = await context.newPage();
  const errors = [];
  page.on('console', msg => { if (msg.type() === 'error') errors.push('console:' + msg.text()); });
  page.on('pageerror', err => errors.push('page:' + err.message));

  try {
    await page.goto(BASE + 'login', { waitUntil: 'domcontentloaded' });
    await page.locator('input[name="username"],input[name="email"],input[type="text"]').first().fill(owner.username);
    await page.locator('input[type="password"]').fill(owner.password);
    await page.locator('button[type="submit"],input[type="submit"]').first().click();
    await page.waitForTimeout(700);
    ok(!page.url().includes('/login'), 'OWNER login failed');

    const response = await page.goto(BASE + 'company/finance/settings/matching-rules', { waitUntil: 'domcontentloaded' });
    ok(response && response.status() === 200, 'matching rules HTTP 200');
    ok((await page.locator('h1').first().innerText()).includes('Правила разнесения'), 'matching rules title missing');

    await page.getByRole('button', { name: '+ Правило', exact: true }).click();
    const modal = page.locator('#matching-rule-create-modal.is-open');
    await modal.waitFor({ state: 'visible', timeout: 10000 });
    await modal.locator('form[data-matching-rule-form]').waitFor({ state: 'visible', timeout: 10000 });

    const cfu = modal.locator('#matching-rule-cfu');
    const dds = modal.locator('#matching-rule-dds');
    const cash = modal.locator('#matching-rule-cash');
    ok(await cfu.count() === 1, 'CFU selector missing');
    ok(await dds.count() === 1, 'DDS selector missing');
    ok(await cash.count() === 1, 'cash transfer selector missing');
    ok((await modal.innerText()).includes('После разнесения'), 'cash transfer UX label missing');
    ok((await modal.innerText()).includes('Повторный перевод не создаётся'), 'idempotency hint missing');
    ok(await cash.isDisabled(), 'cash selector must be disabled until an expense DDS is selected');

    const options = dds.locator('option');
    const optionData = await options.evaluateAll(opts => opts.map(o => ({ value: o.value, direction: (o.dataset.direction || '').toUpperCase(), disabled: o.disabled })));
    const expense = optionData.find(o => o.value && o.direction === 'EXPENSE' && !o.disabled);
    ok(expense, 'no active expense DDS available for runtime UI acceptance');
    await dds.selectOption(expense.value);
    ok(!(await cash.isDisabled()), 'cash selector did not enable for expense DDS');

    const cashOptions = await cash.locator('option').count();
    ok(cashOptions >= 2, 'no active cash account available in selector');
    await cash.selectOption({ index: 1 });
    ok((await cash.inputValue()) !== '', 'cash account cannot be selected');

    const income = optionData.find(o => o.value && o.direction === 'INCOME' && !o.disabled);
    if (income) {
      await dds.selectOption(income.value);
      ok(await cash.isDisabled(), 'cash selector must disable for income DDS');
      ok((await cash.inputValue()) === '', 'cash selection must clear when switching to income DDS');
    }

    // The runtime acceptance is deliberately read-only: do not submit the rule form.
    ok(errors.length === 0, 'browser errors: ' + JSON.stringify(errors));
    console.log('P47_MATCHING_CASH_RUNTIME_OK');
  } finally {
    await browser.close();
  }
})().catch(err => { console.error(err); process.exit(1); });
