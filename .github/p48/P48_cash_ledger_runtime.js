'use strict';

const { chromium } = require('playwright');
const BASE = 'https://plan-ex.ru/erpv2/';
const ok = (value, message) => { if (!value) throw new Error(message); };
const normalize = value => String(value || '').trim().toLocaleLowerCase('ru-RU');

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

    const response = await page.goto(BASE + 'company/finance/cash', { waitUntil: 'domcontentloaded' });
    ok(response && response.status() === 200, 'cash page HTTP 200');
    ok((await page.locator('h1').first().innerText()).includes('Касса'), 'cash title missing');

    const bodyText = await page.locator('body').innerText();
    ok(!bodyText.includes('Ошибка при загрузке данных:'), 'cash page database/runtime error: ' + bodyText.match(/Ошибка при загрузке данных:[^\n]*/)?.[0]);
    ok(!bodyText.includes('Работа с кассой недоступна.'), 'cash page unexpectedly unavailable');

    // Migration/runtime smoke: this block is loaded only after tenant migrations and
    // FinanceManualFactService::fetchRecentPersonalExpenses() complete successfully.
    ok(bodyText.includes('Личные расходы сотрудников из личных средств'), 'personal-funded expense section missing');

    // New release acceptance: open the real production modal without submitting anything.
    const createOperation = page.locator('#cash-operation-create-btn');
    await createOperation.waitFor({ state: 'visible', timeout: 5000 });
    await createOperation.click();
    const modal = page.locator('#cash-operation-create-modal');
    await modal.waitFor({ state: 'visible', timeout: 5000 });
    const form = modal.locator('#finance-cash-manual-form');
    await form.waitFor({ state: 'visible', timeout: 10000 });

    const scenario = form.locator('#cash-scenario');
    const optionValues = await scenario.locator('option').evaluateAll(opts => opts.map(o => o.value));
    for (const expected of ['CASH_OPERATION', 'CLIENT_CASH_RECEIPT', 'EMPLOYEE_PERSONAL_EXPENSE']) {
      ok(optionValues.includes(expected), 'manual finance scenario missing: ' + expected + '; actual=' + JSON.stringify(optionValues));
    }

    await scenario.selectOption('CLIENT_CASH_RECEIPT');
    const clientBlock = form.locator('[data-scenario-block="CLIENT_CASH_RECEIPT"]');
    ok(await clientBlock.isVisible(), 'client cash receipt block did not become visible');
    ok(await clientBlock.locator('select[name="linear_route_payment_id"]').count() === 1, 'client route/payment selector missing');
    ok(await clientBlock.locator('select[name="linear_route_payment_id"]').isEnabled(), 'client route/payment selector disabled');

    await scenario.selectOption('EMPLOYEE_PERSONAL_EXPENSE');
    const personalBlock = form.locator('[data-scenario-block="EMPLOYEE_PERSONAL_EXPENSE"]');
    ok(await personalBlock.isVisible(), 'employee personal expense block did not become visible');
    ok(await personalBlock.locator('select[name="employee_ref"]').count() === 1, 'employee selector missing');
    ok(await personalBlock.locator('select[name="cash_flow_center_id"]').count() === 1, 'CFU selector missing');
    ok(await personalBlock.locator('select[name="personal_dds_category_id"]').count() === 1, 'DDS selector missing');
    ok(await personalBlock.locator('select[name="personal_linear_route_id"]').count() === 1, 'optional route selector missing');
    ok(await form.locator('input[name="amount"]').count() === 1, 'amount field missing');
    ok(await form.locator('input[name="operation_date"]').count() === 1, 'operation date field missing');
    ok(await form.locator('input[name="purpose"]').count() === 1, 'purpose field missing');
    ok(await form.locator('input[name="purpose"]').getAttribute('required') !== null, 'purpose must be required for employee personal expense');

    await page.screenshot({ path: 'P48_cash_lifecycle.png', fullPage: true });

    // Ledger acceptance is state-aware. Empty production data is a valid state.
    const ledger = page.locator('#cash-ledger-table');
    if (await ledger.count()) {
      const headers = await ledger.locator('thead th').allInnerTexts();
      const expected = ['', 'Дата', 'Касса', 'Движение', 'Получено от', 'Назначение', 'Сумма', 'Передано'];
      ok(JSON.stringify(headers.map(normalize)) === JSON.stringify(expected.map(normalize)), 'cash lifecycle headers mismatch: ' + JSON.stringify(headers));

      const rows = ledger.locator('tbody tr[data-cash-ledger-row]');
      const rowCount = await rows.count();
      ok(rowCount > 0, 'cash ledger table rendered without rows');
      const allowedMovements = ['Поступление', 'Списание', 'Получено', 'Получено → передано', 'Получено → разнесено'].map(normalize);

      for (let i = 0; i < rowCount; i++) {
        const row = rows.nth(i);
        const direction = await row.getAttribute('data-cash-direction');
        ok(direction === 'in' || direction === 'out', 'invalid cash direction on row ' + i + ': ' + direction);
        const cells = row.locator('td');
        ok(await cells.count() === 8, 'cash lifecycle row must have eight cells');
        const movement = (await cells.nth(3).innerText()).trim();
        ok(allowedMovements.includes(normalize(movement)), 'raw/invalid lifecycle movement label on row ' + i + ': ' + movement);
        ok((await cells.nth(2).innerText()).trim() !== '', 'cash name missing on row ' + i);
        ok((await cells.nth(5).innerText()).trim() !== '', 'purpose missing on row ' + i);
        ok((await cells.nth(6).innerText()).trim() !== '', 'amount missing on row ' + i);
      }
    } else {
      ok(bodyText.includes('Движений нет.'), 'neither cash ledger nor valid empty state is visible');
    }

    // Deliberately read-only: no form is submitted and no finance record is changed.
    ok(errors.length === 0, 'browser errors: ' + JSON.stringify(errors));
    console.log('P48_CASH_LEDGER_RUNTIME_OK');
    console.log('P48_MANUAL_FINANCE_SCENARIOS_OK');
  } finally {
    await browser.close();
  }
})().catch(err => { console.error(err); process.exit(1); });
