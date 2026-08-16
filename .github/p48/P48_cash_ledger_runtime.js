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

    const ledger = page.locator('#cash-ledger-table');
    await ledger.waitFor({ state: 'visible', timeout: 10000 });
    const headers = await ledger.locator('thead th').allInnerTexts();
    const expected = ['', 'Дата', 'Касса', 'Движение', 'Получено от', 'Назначение', 'Сумма', 'Передано'];
    ok(JSON.stringify(headers.map(normalize)) === JSON.stringify(expected.map(normalize)), 'cash lifecycle headers mismatch: ' + JSON.stringify(headers));

    const rows = ledger.locator('tbody tr[data-cash-ledger-row]');
    const rowCount = await rows.count();
    ok(rowCount > 0, 'cash ledger has no rows for runtime acceptance');

    let companyAccountNumberSeen = false;
    let resolvedLifecycleSeen = false;
    const allowedMovements = [
      'Поступление',
      'Списание',
      'Получено',
      'Получено → передано',
      'Получено → разнесено',
    ].map(normalize);

    for (let i = 0; i < rowCount; i++) {
      const row = rows.nth(i);
      const direction = await row.getAttribute('data-cash-direction');
      ok(direction === 'in' || direction === 'out', 'invalid cash direction on row ' + i + ': ' + direction);
      const cells = row.locator('td');
      ok(await cells.count() === 8, 'cash lifecycle row must have eight cells');

      const cashName = (await cells.nth(2).innerText()).trim();
      const movement = (await cells.nth(3).innerText()).trim();
      const source = (await cells.nth(4).innerText()).trim();
      const purpose = (await cells.nth(5).innerText()).trim();
      const amount = (await cells.nth(6).innerText()).trim();
      const handedTo = (await cells.nth(7).innerText()).trim();

      ok(cashName !== '', 'cash name missing on row ' + i);
      ok(allowedMovements.includes(normalize(movement)), 'raw/invalid lifecycle movement label on row ' + i + ': ' + movement);
      ok(!normalize(movement).includes(normalize('Перевод (')), 'technical transfer label leaked into cash ledger');
      ok(purpose !== '', 'purpose missing on row ' + i);
      ok(amount !== '', 'amount missing on row ' + i);
      ok(source !== '', 'source missing on row ' + i);
      ok(!normalize(source).startsWith(normalize('Расчётный счёт ')), 'cash source must display account number without prefix: ' + source);
      if (/^\d{20}$/.test(source)) companyAccountNumberSeen = true;

      if (normalize(movement) === normalize('Получено → передано') || normalize(movement) === normalize('Получено → разнесено')) {
        resolvedLifecycleSeen = true;
        ok(handedTo !== '' && handedTo !== '—', 'resolved lifecycle recipient missing on row ' + i);
        ok(!/\d{2}\.\d{2}\.\d{4}/.test(handedTo), 'resolved lifecycle handoff date must not be displayed on row ' + i + ': ' + handedTo);
      }
    }

    ok(companyAccountNumberSeen, 'company bank account number is not visible in current cash lifecycle data');
    ok(resolvedLifecycleSeen, 'current production data has no visible resolved lifecycle row');

    const ledgerText = normalize(await ledger.innerText());
    ok(!ledgerText.includes(normalize('Передача сотруднику')), 'duplicate employee handoff outflow is still visible');
    ok(!ledgerText.includes(normalize('Источник / Получатель')), 'legacy mixed source/recipient column is still visible');
    ok(!ledgerText.includes(normalize('Перевод (входящий)')), 'raw incoming transfer label visible');
    ok(!ledgerText.includes(normalize('Перевод (исходящий)')), 'raw outgoing transfer label visible');

    await page.screenshot({ path: 'P48_cash_lifecycle.png', fullPage: true });

    // Deliberately read-only: no forms are submitted and no finance records are changed.
    ok(errors.length === 0, 'browser errors: ' + JSON.stringify(errors));
    console.log('P48_CASH_LEDGER_RUNTIME_OK');
  } finally {
    await browser.close();
  }
})().catch(err => { console.error(err); process.exit(1); });