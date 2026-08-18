'use strict';

const fs = require('fs');
const { chromium } = require('playwright');
const BASE = 'https://plan-ex.ru/erpv2/';
const ok = (value, message) => { if (!value) throw new Error(message); };
const norm = value => String(value || '').replace(/\s+/g, ' ').trim().toLocaleLowerCase('ru-RU');

(async () => {
  const credentials = JSON.parse(Buffer.from(process.env.P12_CREDENTIALS_B64 || '', 'base64').toString('utf8'));
  const owner = credentials.find(x => String(x.role).toUpperCase() === 'OWNER');
  ok(owner, 'OWNER credentials missing');

  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({ viewport: { width: 1920, height: 1080 }, deviceScaleFactor: 1, locale: 'ru-RU', timezoneId: 'Europe/Moscow' });
  const page = await context.newPage();
  const browserErrors = [];
  page.on('console', msg => { if (msg.type() === 'error') browserErrors.push('console:' + msg.text()); });
  page.on('pageerror', err => browserErrors.push('page:' + err.message));

  const evidence = { cash: {}, invoice: {}, browserErrors };
  try {
    await page.goto(BASE + 'login', { waitUntil: 'domcontentloaded' });
    await page.locator('input[name="username"],input[name="email"],input[type="text"]').first().fill(owner.username);
    await page.locator('input[type="password"]').fill(owner.password);
    await page.locator('button[type="submit"],input[type="submit"]').first().click();
    await page.waitForTimeout(700);
    ok(!page.url().includes('/login'), 'OWNER login failed');

    let response = await page.goto(BASE + 'company/finance/cash', { waitUntil: 'domcontentloaded' });
    ok(response && response.status() === 200, 'cash page must return HTTP 200');
    const cashBody = await page.locator('body').innerText();
    ok(!cashBody.includes('Ошибка при загрузке данных:'), 'cash page contains runtime/database error');
    ok(!cashBody.includes('Fatal error'), 'cash page contains PHP fatal');

    const rows = page.locator('#cash-ledger-table tbody tr[data-cash-ledger-row]');
    const rowCount = await rows.count();
    ok(rowCount > 0, 'cash ledger has no rows');

    const relevant = [];
    for (let i = 0; i < rowCount; i++) {
      const row = rows.nth(i);
      const cells = row.locator('td');
      if (await cells.count() !== 8) continue;
      const texts = await cells.allInnerTexts();
      const joined = norm(texts.join(' | '));
      if (joined.includes('14 600') && joined.includes('оплата счёта') && joined.includes('неизвестное физ лицо')) {
        relevant.push({ index: i, texts, selectable: await row.getAttribute('data-cash-selectable') });
      }
    }

    ok(relevant.length === 1, 'expected exactly one cash journal row for the 14 600 employee-paid carrier invoice; found=' + relevant.length + ' rows=' + JSON.stringify(relevant));
    const cashRow = relevant[0];
    const movement = norm(cashRow.texts[3]);
    const source = norm(cashRow.texts[4]);
    const purpose = norm(cashRow.texts[5]);
    const amount = norm(cashRow.texts[6]);
    const recipient = norm(cashRow.texts[7]);
    ok(movement === norm('Получено → списано'), 'combined cash movement label mismatch: ' + cashRow.texts[3]);
    ok(source.includes('спугов'), 'combined cash row must show Spugov as source: ' + cashRow.texts[4]);
    ok(purpose.includes('оплата счёта'), 'combined cash row must show invoice-payment purpose');
    ok(amount.includes('14 600'), 'combined cash row amount must be 14 600: ' + cashRow.texts[6]);
    ok(recipient.includes('неизвестное физ лицо'), 'combined cash row must show carrier recipient: ' + cashRow.texts[7]);
    ok(cashRow.selectable !== '1', 'combined employee invoice cash lifecycle must not be selectable as unresolved cash');

    evidence.cash = { rowCount, relevantRows: relevant.length, row: cashRow.texts, selectable: cashRow.selectable };
    await page.screenshot({ path: 'P48_FINAL_cash_employee_invoice.png', fullPage: true });

    response = await page.goto(BASE + 'company/finance/invoices?direction=INCOMING', { waitUntil: 'domcontentloaded' });
    ok(response && response.status() === 200, 'incoming invoices page must return HTTP 200');
    const invoiceBody = await page.locator('body').innerText();
    ok(!invoiceBody.includes('Ошибка при загрузке данных:'), 'incoming invoices page contains runtime/database error');
    ok(!invoiceBody.includes('Fatal error'), 'incoming invoices page contains PHP fatal');

    const invoiceRows = page.locator('table.table tbody tr[data-invoice-id]');
    const invoiceMatches = [];
    for (let i = 0; i < await invoiceRows.count(); i++) {
      const row = invoiceRows.nth(i);
      const text = norm(await row.innerText());
      if (text.includes('неизвестное физ лицо') && text.includes('14 600')) {
        invoiceMatches.push({ index: i, text: await row.innerText() });
      }
    }
    ok(invoiceMatches.length === 1, 'expected exactly one matching incoming invoice for carrier/14 600; found=' + invoiceMatches.length);
    const invoiceText = norm(invoiceMatches[0].text);
    ok(invoiceText.includes('оплачен'), 'incoming carrier invoice must be paid after employee settlement: ' + invoiceMatches[0].text);
    evidence.invoice = { matches: invoiceMatches.length, row: invoiceMatches[0].text };
    await page.screenshot({ path: 'P48_FINAL_incoming_invoice.png', fullPage: true });

    ok(browserErrors.length === 0, 'browser errors: ' + JSON.stringify(browserErrors));
    fs.writeFileSync('P48_FINAL_evidence.json', JSON.stringify(evidence, null, 2));
    console.log('P48_FINAL_EMPLOYEE_INVOICE_CASH_ONE_ROW_OK');
    console.log('P48_FINAL_INCOMING_INVOICE_SETTLED_OK');
  } finally {
    await browser.close();
  }
})().catch(err => {
  console.error(err && err.stack ? err.stack : err);
  process.exit(1);
});
