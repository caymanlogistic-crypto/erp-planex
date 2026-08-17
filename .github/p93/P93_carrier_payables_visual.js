'use strict';

const fs = require('fs');
const { chromium } = require('playwright');
const B = 'https://plan-ex.ru/erpv2/';
const ok = (value, message) => { if (!value) throw new Error(message); };
const fatal = /(Fatal error|Parse error|Uncaught (?:TypeError|Error|Exception)|Class ".+" not found)/i;

(async () => {
  const credentials = JSON.parse(Buffer.from(process.env.P12_CREDENTIALS_B64 || '', 'base64').toString('utf8'));
  const owner = credentials.find(x => String(x.role).toUpperCase() === 'OWNER');
  ok(owner, 'OWNER credentials');
  fs.mkdirSync('P93_screens', { recursive: true });

  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({ viewport: { width: 1920, height: 1080 }, deviceScaleFactor: 1, locale: 'ru-RU', timezoneId: 'Europe/Moscow' });
  const page = await context.newPage();
  const errors = [];
  page.on('pageerror', e => errors.push(e.message));
  const evidence = {};

  try {
    await page.goto(B + 'login', { waitUntil: 'domcontentloaded', timeout: 30000 });
    await page.locator('input[name="username"],input[name="email"],input[type="text"]').first().fill(owner.username);
    await page.locator('input[type="password"]').fill(owner.password);
    await page.locator('button[type="submit"],input[type="submit"]').first().click();
    await page.waitForTimeout(700);
    ok(!page.url().includes('/login'), 'login failed');

    const response = await page.goto(B + 'company/finance/payables', { waitUntil: 'networkidle', timeout: 30000 });
    ok(response && response.status() === 200, `payables HTTP ${response ? response.status() : 'none'}`);
    const body = await page.locator('body').innerText();
    fs.writeFileSync('P93_PAYABLES_BODY.txt', body);
    await page.screenshot({ path: 'P93_screens/payables_before_assert.png', fullPage: true });
    const fatalMatch = body.match(fatal);
    if (fatalMatch) {
      const index = fatalMatch.index || 0;
      const snippet = body.slice(Math.max(0, index - 300), Math.min(body.length, index + 1200)).replace(/\s+/g, ' ');
      throw new Error('fatal runtime text on payables: ' + snippet);
    }
    ok(await page.getByText('Кредиторская задолженность', { exact: true }).count() >= 1, 'payables title');
    for (const label of ['Всего к оплате', 'Просрочено', 'Расчётный счёт', 'Касса', 'Сотрудник']) ok(await page.getByText(label, { exact: true }).count() >= 1, `missing ${label}`);
    ok(await page.locator('[data-payables-search]').count() === 1, 'payables search');
    ok(await page.locator('[data-payables-status]').count() === 1, 'payables status filter');
    ok(await page.locator('.payables-table').count() === 1, 'single payables table');

    const main = await page.locator('main.content').boundingBox();
    const pageBox = await page.locator('.payables-page').boundingBox();
    const summaryBox = await page.locator('.payables-summary').boundingBox();
    const channelBox = await page.locator('.payables-channels').boundingBox();
    const tableWrap = await page.locator('.payables-table-wrap').boundingBox();
    ok(main && pageBox && summaryBox && channelBox && tableWrap, 'payables geometry missing');
    ok(pageBox.width > 900 && pageBox.height > 300, `payables collapsed ${JSON.stringify(pageBox)}`);
    ok(tableWrap.width <= main.width + 3, `payables table wrapper overflow ${tableWrap.width}/${main.width}`);
    ok(channelBox.y >= summaryBox.y + summaryBox.height - 2, 'summary/channel overlap');
    ok(tableWrap.y >= channelBox.y + channelBox.height - 2, 'channel/table overlap');

    const headers = await page.locator('.payables-table thead th').allTextContents();
    for (const label of ['Перевозчик', 'Рейс / событие', 'Входящие счета', 'Срок', 'Оплачено', 'Остаток', 'Источник оплаты']) ok(headers.map(x => x.trim()).includes(label), `missing table header ${label}`);
    evidence.payables = { url: page.url(), viewport: page.viewportSize(), main, pageBox, summaryBox, channelBox, tableWrap, headers, rows: await page.locator('[data-payables-row]').count() };
    await page.screenshot({ path: 'P93_screens/payables_1920x1080.png', fullPage: true });

    await page.locator('[data-payables-status]').selectOption('overdue');
    await page.locator('[data-payables-search]').fill('тест-поиск-без-изменения-данных');
    await page.waitForTimeout(100);
    await page.locator('[data-payables-search]').fill('');
    await page.locator('[data-payables-status]').selectOption('all');

    await page.goto(B + 'company/finance/receivables', { waitUntil: 'networkidle', timeout: 30000 });
    ok(await page.getByRole('link', { name: 'Кредиторка', exact: true }).count() === 1, 'receivables -> payables link');
    await page.screenshot({ path: 'P93_screens/receivables_crosslink.png', fullPage: true });

    await page.goto(B + 'company/finance/invoices?direction=INCOMING', { waitUntil: 'networkidle', timeout: 30000 });
    const rows = page.locator('tbody tr[data-invoice-id]');
    const incomingCount = await rows.count();
    evidence.incomingInvoices = incomingCount;
    if (incomingCount > 0) {
      await rows.first().dblclick();
      const modal = page.locator('#invoice-view-modal.is-open');
      await modal.waitFor({ state: 'visible', timeout: 10000 });
      ok(await modal.getByText('Фактические оплаты', { exact: true }).count() === 1, 'settlement history section');
      const modalBox = await modal.locator('.modal').boundingBox();
      ok(modalBox && modalBox.width > 650 && modalBox.height > 300, `invoice modal collapsed ${JSON.stringify(modalBox)}`);
      ok(modalBox.x >= 0 && modalBox.x + modalBox.width <= 1920, 'invoice modal horizontal overflow');
      evidence.invoiceModal = { box: modalBox, hasPaySection: await modal.getByText('Оплатить входящий счёт', { exact: true }).count() > 0, paymentRows: await modal.locator('.invoice-settlement-row').count() };
      await page.screenshot({ path: 'P93_screens/incoming_invoice_modal.png', fullPage: true });
    }

    ok(errors.length === 0, 'page errors: ' + JSON.stringify(errors));
    fs.writeFileSync('P93_VISUAL.json', JSON.stringify({ ok: true, evidence, errors }, null, 2));
    console.log('P93_CARRIER_PAYABLES_VISUAL_OK');
  } finally {
    await browser.close();
  }
})().catch(error => {
  fs.writeFileSync('P93_VISUAL.json', JSON.stringify({ ok: false, error: error.message, stack: error.stack }, null, 2));
  console.error(error);
  process.exit(1);
});
