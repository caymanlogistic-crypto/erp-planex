'use strict';

const { chromium } = require('playwright');
const B = 'https://plan-ex.ru/erpv2/';
const ok = (value, message) => { if (!value) throw new Error(message); };

(async () => {
  const credentials = JSON.parse(Buffer.from(process.env.P12_CREDENTIALS_B64 || '', 'base64').toString('utf8'));
  const owner = credentials.find(x => String(x.role).toUpperCase() === 'OWNER');
  ok(owner, 'OWNER credentials');

  const browser = await chromium.launch({ headless: true, channel: 'chrome' });
  const context = await browser.newContext({ viewport: { width: 1920, height: 1080 }, deviceScaleFactor: 1, locale: 'ru-RU', timezoneId: 'Europe/Moscow' });
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

    const response = await page.goto(B + 'company/finance/invoices', { waitUntil: 'domcontentloaded' });
    ok(response && response.status() === 200, 'invoices HTTP ' + (response && response.status()));
    await page.waitForTimeout(300);

    const bodyText = await page.locator('body').innerText();
    ok(!/(Fatal error|Parse error|Uncaught TypeError)/i.test(bodyText), 'fatal runtime error');
    const headers = (await page.locator('table.table thead th').allTextContents()).map(v => v.trim());
    ok(headers.includes('Этапы оплаты'), 'payment stages header missing: ' + JSON.stringify(headers));
    ok(headers.includes('Статус счёта'), 'invoice status header missing');
    ok(!headers.includes('Оплачено'), 'retired paid total column remains');
    ok(!headers.some(v => /План\s*\/\s*факт оплаты/i.test(v)), 'retired plan/fact column remains');
    ok(!bodyText.includes('Соблюдение сроков'), 'retired parallel compliance block remains');

    const rows = page.locator('table.table tbody tr[data-invoice-id]');
    const rowCount = await rows.count();
    ok(rowCount > 0, 'no invoice rows');
    const cards = page.locator('.invoice-stage-card');
    const cardCount = await cards.count();
    ok(cardCount > 0, 'no payment stage cards rendered');

    for (let i = 0; i < cardCount; i++) {
      const card = cards.nth(i);
      const text = (await card.innerText()).replace(/\s+/g, ' ').trim();
      ok(/План:/i.test(text), 'stage without plan: ' + text);
      ok(/(?:Оплачено|Получено):/i.test(text), 'stage without fact: ' + text);
      ok(/Срок:/i.test(text), 'stage without due date: ' + text);
      ok(await card.locator('.invoice-stage-status .badge').count() === 1, 'stage without system badge: ' + text);
      const bg = await card.evaluate(el => getComputedStyle(el).backgroundColor);
      ok(bg === 'rgba(0, 0, 0, 0)' || bg === 'transparent', 'stage card uses state fill instead of neutral card: ' + bg);
    }

    const statusCells = page.locator('.invoice-status-cell');
    ok(await statusCells.count() === rowCount, 'invoice status cell count mismatch');
    ok(await statusCells.locator('.badge').count() >= rowCount, 'invoice overall statuses missing system badges');

    const stageTexts = (await cards.allTextContents()).join('\n');
    const observed = {
      paid: /Оплачен(?:\s|$)/i.test(stageTexts),
      early: /раньше/i.test(stageTexts),
      lateClosed: /Оплачен с просрочкой/i.test(stageTexts),
      partial: /Частично/i.test(stageTexts),
      overdue: /Просрочка\s+\d+\s*дн/i.test(stageTexts),
      waiting: /Ожидается/i.test(stageTexts)
    };
    ok(observed.paid, 'no fully paid stage observed');
    ok(observed.lateClosed, 'no historically late closed stage observed');
    ok(observed.overdue, 'no active overdue stage observed');
    ok(observed.waiting, 'no waiting stage observed');

    const multiStageRows = page.locator('table.table tbody tr[data-invoice-id]').filter({ has: page.locator('.invoice-stage-card:nth-child(2)') });
    ok(await multiStageRows.count() > 0, 'no multi-stage invoice observed');

    const tableMetrics = await page.locator('.table-scroll').evaluate(el => ({clientWidth: el.clientWidth, scrollWidth: el.scrollWidth}));
    ok(tableMetrics.scrollWidth <= tableMetrics.clientWidth + 40, 'invoice table expanded horizontally: ' + JSON.stringify(tableMetrics));

    await page.screenshot({ path: 'P54_invoices.png', fullPage: true });
    ok(errors.length === 0, 'browser errors: ' + JSON.stringify(errors));
    console.log('P54_HEADERS=' + JSON.stringify(headers));
    console.log('P54_ROWS=' + rowCount);
    console.log('P54_CARDS=' + cardCount);
    console.log('P54_OBSERVED=' + JSON.stringify(observed));
    console.log('P54_TABLE=' + JSON.stringify(tableMetrics));
    console.log('P54_ERRORS=' + JSON.stringify(errors));
    console.log('P54_INVOICE_STAGE_CARDS_OK');
  } finally {
    await browser.close();
  }
})().catch(error => { console.error(error); process.exit(1); });
