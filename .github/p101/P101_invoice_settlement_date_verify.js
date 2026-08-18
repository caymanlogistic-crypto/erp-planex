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
  const context = await browser.newContext({
    viewport: { width: 1920, height: 1080 },
    deviceScaleFactor: 1,
    locale: 'ru-RU',
    timezoneId: 'Europe/Moscow'
  });
  const page = await context.newPage();
  const browserErrors = [];
  page.on('pageerror', error => browserErrors.push(error.message));
  const evidence = {};

  try {
    await page.goto(BASE + 'login', { waitUntil: 'domcontentloaded', timeout: 30000 });
    await page.locator('input[name="username"],input[name="email"],input[type="text"]').first().fill(owner.username);
    await page.locator('input[type="password"]').fill(owner.password);
    await page.locator('button[type="submit"],input[type="submit"]').first().click();
    await page.waitForTimeout(700);
    ok(!page.url().includes('/login'), 'OWNER login failed');

    async function openTargetInvoice() {
      const response = await page.goto(BASE + 'company/finance/invoices?direction=INCOMING', { waitUntil: 'networkidle', timeout: 30000 });
      ok(response && response.status() === 200, 'incoming invoices HTTP failure');
      const rows = page.locator('tbody tr[data-invoice-id]');
      let target = null;
      for (let i = 0; i < await rows.count(); i++) {
        const row = rows.nth(i);
        const text = norm(await row.innerText());
        if (text.includes('неизвестное физ лицо') && text.includes('14 600')) {
          target = row;
          break;
        }
      }
      ok(target, 'target 14 600 incoming invoice not found');
      await target.dblclick();
      const modal = page.locator('#invoice-view-modal.is-open');
      await modal.waitFor({ state: 'visible', timeout: 10000 });
      await modal.getByText('Фактические оплаты', { exact: true }).waitFor({ state: 'visible', timeout: 10000 });
      return modal;
    }

    async function findEditable14600Row(modal) {
      const rows = modal.locator('.invoice-settlement-row');
      await rows.first().waitFor({ state: 'visible', timeout: 10000 });
      const texts = [];
      for (let i = 0; i < await rows.count(); i++) {
        const row = rows.nth(i);
        const raw = await row.innerText();
        texts.push(raw.replace(/\s+/g, ' ').trim());
        if (norm(raw).includes('14 600') && (await row.locator('[data-settlement-date-edit]').count()) === 1) {
          return { row, texts };
        }
      }
      console.log('P101_SETTLEMENT_ROWS=' + JSON.stringify(texts));
      return { row: null, texts };
    }

    let modal = await openTargetInvoice();
    let found = await findEditable14600Row(modal);
    ok(found.row, 'editable 14 600 settlement row not found; rows=' + JSON.stringify(found.texts));
    let paymentRow = found.row;
    const initialText = (await paymentRow.innerText()).replace(/\s+/g, ' ').trim();
    console.log('P101_TARGET_SETTLEMENT=' + initialText);
    ok(norm(await paymentRow.locator('.invoice-settlement-date-value').innerText()) === norm('18.08.2026'), 'unexpected current actual payment date');

    await paymentRow.locator('[data-settlement-date-edit]').click();
    const form = paymentRow.locator('[data-settlement-date-form]');
    ok(await form.isVisible(), 'inline date edit form did not open');
    const input = form.locator('input[name="operation_date"]');
    ok((await input.inputValue()) === '2026-08-18', 'date input does not contain the current actual payment date');
    await page.screenshot({ path: 'P101_invoice_payment_date_editor.png', fullPage: true });

    // Submit the same date. This exercises the real POST + CSRF + service but
    // remains a no-op at the data layer. Assert the API response directly;
    // the UI reload is intentionally not part of the API contract.
    const responsePromise = page.waitForResponse(response =>
      response.request().method() === 'POST' && response.url().includes('/settlements/') && response.url().endsWith('/date'),
      { timeout: 15000 }
    );
    await form.locator('[data-settlement-date-save]').click();
    const saveResponse = await responsePromise;
    const saveText = await saveResponse.text();
    let saveJson = null;
    try { saveJson = JSON.parse(saveText); } catch (_) {}
    console.log('P101_SAVE_RESPONSE=' + saveResponse.status() + ' ' + saveText);
    ok(saveResponse.status() === 200, 'settlement date POST HTTP ' + saveResponse.status() + ': ' + saveText);
    ok(saveJson && saveJson.ok === true, 'settlement date POST did not return ok=true: ' + saveText);
    ok(saveJson.operation_date === '2026-08-18', 'settlement date POST returned unexpected date: ' + saveText);

    // The modal script may reload the current page after a successful save.
    // Navigate afresh so verification is deterministic regardless of reload timing.
    await page.waitForTimeout(500);
    modal = await openTargetInvoice();
    found = await findEditable14600Row(modal);
    ok(found.row, 'settlement disappeared after idempotent save; rows=' + JSON.stringify(found.texts));
    paymentRow = found.row;
    ok(norm(await paymentRow.locator('.invoice-settlement-date-value').innerText()) === norm('18.08.2026'), 'actual payment date changed during no-op verification');
    ok((await paymentRow.locator('[data-settlement-date-edit]').count()) === 1, 'date editor missing after reload');
    await page.screenshot({ path: 'P101_invoice_payment_date_after_noop.png', fullPage: true });

    evidence.invoice = {
      settlementText: await paymentRow.innerText(),
      actualDate: await paymentRow.locator('.invoice-settlement-date-value').innerText(),
      operationId: await paymentRow.getAttribute('data-invoice-settlement-operation'),
      saveResponse: saveJson
    };
    evidence.browserErrors = browserErrors;
    ok(browserErrors.length === 0, 'browser errors: ' + JSON.stringify(browserErrors));
    fs.writeFileSync('P101_invoice_settlement_date_evidence.json', JSON.stringify(evidence, null, 2));
    console.log('P101_SETTLEMENT_DATE_BUTTON_OK');
    console.log('P101_SETTLEMENT_DATE_FORM_OK');
    console.log('P101_SETTLEMENT_DATE_NOOP_POST_OK');
    console.log('P101_SETTLEMENT_DATE_PRESERVED_OK');
  } finally {
    await browser.close();
  }
})().catch(error => {
  fs.writeFileSync('P101_invoice_settlement_date_evidence.json', JSON.stringify({ ok: false, error: error.message, stack: error.stack }, null, 2));
  console.error(error && error.stack ? error.stack : error);
  process.exit(1);
});
