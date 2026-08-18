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
  fs.mkdirSync('P103_screens', { recursive: true });

  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({
    viewport: { width: 1920, height: 1080 },
    deviceScaleFactor: 1,
    locale: 'ru-RU',
    timezoneId: 'Europe/Moscow'
  });
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

    const response = await page.goto(B + 'company/finance/invoices?direction=INCOMING', { waitUntil: 'networkidle', timeout: 30000 });
    ok(response && response.status() === 200, `invoices HTTP ${response ? response.status() : 'none'}`);
    const body = await page.locator('body').innerText();
    ok(!fatal.test(body), 'fatal runtime text on invoices');

    const rows = page.locator('tbody tr[data-invoice-id]');
    const target = rows.filter({ hasText: '26/3947' });
    ok(await target.count() === 1, `expected one invoice 26/3947 row, got ${await target.count()}`);
    const row = target.first();
    const text = (await row.innerText()).replace(/\u00a0/g, ' ').replace(/[ \t]+/g, ' ');

    for (const needle of [
      '195 000',
      '145 000',
      'Просроченный остаток 50 000',
      'Оплачено с просрочкой 7 дн.',
      'Оплачено на 2 дн. раньше',
      'Не оплачено · просрочка 5 дн.'
    ]) {
      ok(text.includes(needle), `missing target text: ${needle}\nROW=${text}`);
    }

    const resultRows = row.locator('.invoice-pf-result-row');
    ok(await resultRows.count() === 3, `expected 3 settlement rows, got ${await resultRows.count()}`);
    const resultTexts = (await resultRows.allInnerTexts()).map(x => x.replace(/\u00a0/g, ' ').replace(/[ \t]+/g, ' ').trim());
    ok(resultTexts[0].includes('04.08.2026') && resultTexts[0].includes('11.08.2026') && resultTexts[0].includes('58 500') && resultTexts[0].includes('Оплачено с просрочкой 7 дн.'), 'first plan settlement is not fully paid late');
    ok(resultTexts[1].includes('13.08.2026') && resultTexts[1].includes('11.08.2026') && resultTexts[1].includes('86 500') && resultTexts[1].includes('Оплачено на 2 дн. раньше'), 'second plan paid portion is incorrect');
    ok(resultTexts[2].includes('13.08.2026') && resultTexts[2].includes('50 000') && resultTexts[2].includes('Не оплачено · просрочка 5 дн.'), 'second plan unpaid remainder is incorrect');

    const box = await row.boundingBox();
    ok(box && box.width > 700 && box.height > 100, `target row geometry invalid ${JSON.stringify(box)}`);
    await row.screenshot({ path: 'P103_screens/invoice_26_3947_timeline.png' });
    await page.screenshot({ path: 'P103_screens/invoices_full_1920x1080.png', fullPage: true });

    evidence.invoice = { url: page.url(), rowText: text, resultTexts, box };
    ok(errors.length === 0, 'page errors: ' + JSON.stringify(errors));
    fs.writeFileSync('P103_VISUAL.json', JSON.stringify({ ok: true, evidence, errors }, null, 2));
    console.log('P103_INVOICE_TIMELINE_VISUAL_OK');
  } finally {
    await browser.close();
  }
})().catch(error => {
  fs.writeFileSync('P103_VISUAL.json', JSON.stringify({ ok: false, error: error.message, stack: error.stack }, null, 2));
  console.error(error);
  process.exit(1);
});