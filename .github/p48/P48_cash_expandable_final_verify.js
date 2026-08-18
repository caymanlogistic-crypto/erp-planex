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

  async function openLifecycle(row, expectedKind, expectedSource, expectedRecipient, expectedAmount) {
    const cells = row.locator('td');
    ok(await cells.count() === 8, 'cash lifecycle row must preserve eight cells');
    const texts = await cells.allInnerTexts();
    ok(norm(texts[3]) === norm('Получено → списано'), 'movement mismatch: ' + texts[3]);
    ok(norm(texts[4]).includes(norm(expectedSource)), 'source mismatch: ' + texts[4]);
    ok(norm(texts[6]).includes(norm(expectedAmount)), 'amount mismatch: ' + texts[6]);
    ok(norm(texts[7]).includes(norm(expectedRecipient)), 'recipient mismatch: ' + texts[7]);
    ok(await row.getAttribute('data-cash-selectable') !== '1', 'completed lifecycle must not be unresolved/selectable');
    ok(await row.getAttribute('data-cash-expandable') === '1', 'completed lifecycle must be expandable');
    ok(await row.getAttribute('data-cash-lifecycle-kind') === expectedKind, 'lifecycle kind mismatch');

    const toggle = row.locator('[data-cash-lifecycle-toggle]');
    ok(await toggle.count() === 1, 'expand control missing');
    const detailId = await toggle.getAttribute('aria-controls');
    ok(detailId, 'detail id missing');
    const detail = page.locator('#' + detailId);
    ok(await detail.count() === 1, 'detail row missing');
    ok(await detail.isHidden(), 'detail must start collapsed');
    await toggle.click();
    ok(await toggle.getAttribute('aria-expanded') === 'true', 'expand control did not open');
    ok(await detail.isVisible(), 'detail did not become visible');
    const detailText = (await detail.innerText()).replace(/\s+/g, ' ').trim();
    ok(detailText.includes('Поступление'), 'receipt leg missing');
    ok(detailText.includes('Списание'), 'expense leg missing');
    ok(norm(detailText).includes(norm(expectedSource)), 'receipt leg source missing');
    ok(norm(detailText).includes(norm(expectedRecipient)), 'expense leg recipient missing');
    ok(norm(detailText).includes(norm('Основная касса')), 'Main Cash routing missing');
    ok(norm(detailText).includes(norm('+' + expectedAmount)), 'positive receipt amount missing');
    ok(norm(detailText).includes(norm('−' + expectedAmount)) || norm(detailText).includes(norm('-' + expectedAmount)), 'negative expense amount missing');
    return { texts, detailText };
  }

  try {
    await page.goto(BASE + 'login', { waitUntil: 'domcontentloaded' });
    await page.locator('input[name="username"],input[name="email"],input[type="text"]').first().fill(owner.username);
    await page.locator('input[type="password"]').fill(owner.password);
    await page.locator('button[type="submit"],input[type="submit"]').first().click();
    await page.waitForTimeout(700);
    ok(!page.url().includes('/login'), 'OWNER login failed');

    let response = await page.goto(BASE + 'company/finance/cash?per_page=100', { waitUntil: 'domcontentloaded' });
    ok(response && response.status() === 200, 'cash page must return HTTP 200');
    const cashBody = await page.locator('body').innerText();
    ok(!cashBody.includes('Ошибка при загрузке данных:'), 'cash page contains runtime/database error');
    ok(!cashBody.includes('Fatal error'), 'cash page contains PHP fatal');
    ok(await page.locator('.section-title').filter({ hasText: 'Расходы сотрудников из личных средств' }).count() === 0, 'duplicate lower personal-expense section still exists');

    const headers = await page.locator('#cash-ledger-table thead th').allInnerTexts();
    const expectedHeaders = ['', 'Дата', 'Касса', 'Движение', 'Получено от', 'Назначение', 'Сумма', 'Передано'];
    ok(JSON.stringify(headers.map(norm)) === JSON.stringify(expectedHeaders.map(norm)), 'cash table headers changed: ' + JSON.stringify(headers));

    const rows = page.locator('#cash-ledger-table tbody tr[data-cash-ledger-row]');
    const rowCount = await rows.count();
    ok(rowCount > 0, 'cash ledger has no rows');

    const gladkikhMatches = [];
    const spugovMatches = [];
    for (let i = 0; i < rowCount; i++) {
      const row = rows.nth(i);
      const cells = row.locator('td');
      if (await cells.count() !== 8) continue;
      const texts = await cells.allInnerTexts();
      const joined = norm(texts.join(' | '));
      if (joined.includes('1 100') && joined.includes('гладких') && (joined.includes('ати') || joined.includes('ati'))) gladkikhMatches.push(i);
      if (joined.includes('14 600') && joined.includes('спугов') && joined.includes('неизвестное физ лицо') && joined.includes('оплата счёта')) spugovMatches.push(i);
    }
    ok(gladkikhMatches.length === 1, 'expected exactly one Gladkikh/АТИ 1 100 lifecycle row; found=' + gladkikhMatches.length);
    ok(spugovMatches.length === 1, 'expected exactly one Spugov/carrier 14 600 lifecycle row; found=' + spugovMatches.length);

    const gladkikh = await openLifecycle(rows.nth(gladkikhMatches[0]), 'EMPLOYEE_PERSONAL_EXPENSE', 'Гладких', 'АТИ', '1 100');
    ok(norm(gladkikh.detailText).includes(norm('ЦФУ:')), 'Gladkikh personal-expense detail must show CFU');
    ok(norm(gladkikh.detailText).includes(norm('Статья ДДС:')), 'Gladkikh personal-expense detail must show DDS category');

    const spugov = await openLifecycle(rows.nth(spugovMatches[0]), 'EMPLOYEE_INVOICE', 'Спугов', 'Неизвестное Физ Лицо', '14 600');
    ok(norm(spugov.detailText).includes(norm('Основание: входящий счёт')), 'Spugov invoice detail must show incoming invoice basis');

    const mainCashRow = page.locator('table.table').first().locator('tbody tr').filter({ hasText: 'Основная касса' }).first();
    const mainCashText = await mainCashRow.innerText();
    evidence.cash = {
      rowCount,
      lowerDuplicateSectionCount: 0,
      gladkikh: { row: gladkikh.texts, detail: gladkikh.detailText },
      spugov: { row: spugov.texts, detail: spugov.detailText },
      mainCash: mainCashText
    };
    await page.screenshot({ path: 'P48_EXPANDABLE_cash.png', fullPage: true });

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
      if (text.includes('неизвестное физ лицо') && text.includes('14 600')) invoiceMatches.push(await row.innerText());
    }
    ok(invoiceMatches.length === 1, 'expected one matching 14 600 incoming carrier invoice; found=' + invoiceMatches.length);
    ok(norm(invoiceMatches[0]).includes('оплачен'), 'incoming carrier invoice must remain fully paid');
    evidence.invoice = { matches: invoiceMatches.length, row: invoiceMatches[0] };
    await page.screenshot({ path: 'P48_EXPANDABLE_incoming_invoice.png', fullPage: true });

    ok(browserErrors.length === 0, 'browser errors: ' + JSON.stringify(browserErrors));
    fs.writeFileSync('P48_EXPANDABLE_evidence.json', JSON.stringify(evidence, null, 2));
    console.log('P48_EXPANDABLE_GLADKIKH_ONE_ROW_OK');
    console.log('P48_EXPANDABLE_SPUGOV_ONE_ROW_OK');
    console.log('P48_EXPANDABLE_TWO_LEGS_VISIBLE_OK');
    console.log('P48_EXPANDABLE_NO_DUPLICATE_SECTION_OK');
    console.log('P48_EXPANDABLE_INVOICE_STILL_SETTLED_OK');
  } finally {
    await browser.close();
  }
})().catch(err => {
  console.error(err && err.stack ? err.stack : err);
  process.exit(1);
});
