'use strict';

const fs = require('fs');
const { chromium } = require('playwright');
const B = 'https://plan-ex.ru/erpv2/';
const ok = (v, m) => { if (!v) throw new Error(m); };
const fatal = /(Fatal error|Parse error|Uncaught (?:TypeError|Error|Exception)|Class ".+" not found)/i;

(async () => {
  const credentials = JSON.parse(Buffer.from(process.env.P12_CREDENTIALS_B64 || '', 'base64').toString('utf8'));
  const owner = credentials.find(x => String(x.role).toUpperCase() === 'OWNER');
  ok(owner, 'OWNER credentials');
  fs.mkdirSync('P104_screens', { recursive: true });

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

    const response = await page.goto(B + 'company/finance/invoices?direction=OUTGOING', { waitUntil: 'networkidle', timeout: 30000 });
    ok(response && response.status() === 200, `invoice register HTTP ${response ? response.status() : 'none'}`);
    const body = await page.locator('body').innerText();
    ok(!fatal.test(body), 'fatal runtime text on invoice register');
    ok(await page.getByText('Счета', { exact: true }).count() >= 1, 'full ERP invoice register title missing');

    const row = page.locator('tbody tr[data-invoice-id="5"]');
    ok(await row.count() === 1, 'invoice #5 row missing');
    const rowText = (await row.innerText()).replace(/\s+/g, ' ').trim();
    ok(rowText.includes('ПОТОК'), 'invoice #5 is not ПОТОК');
    ok(/775\s*000/.test(rowText), 'invoice #5 amount 775000 missing');
    await page.screenshot({ path: 'P104_screens/invoice_register_before_edit.png', fullPage: true });

    await row.dblclick();
    const viewModal = page.locator('#invoice-view-modal.is-open');
    await viewModal.waitFor({ state: 'visible', timeout: 10000 });
    await viewModal.locator('#invoice-view-modal-body').getByText('Редактировать', { exact: true }).click();

    const editModal = page.locator('#invoice-edit-modal.is-open');
    await editModal.waitFor({ state: 'visible', timeout: 10000 });
    const form = editModal.locator('form.invoice-form');
    await form.waitFor({ state: 'visible', timeout: 10000 });
    const action = await form.getAttribute('action');
    ok(action && action.includes('/company/finance/invoices/5/modal-edit'), `unexpected edit action ${action}`);
    ok(await form.locator('button[type="submit"]').getByText('Сохранить', { exact: true }).count() === 1, 'save button missing');
    ok(await form.locator('select[name="counterparty_entity_id"] option:checked').innerText().then(t => t.includes('ПОТОК')), 'ПОТОК not selected in edit form');
    ok(await form.locator('input[name="amount"]').inputValue().then(v => Math.abs(parseFloat(String(v).replace(',', '.')) - 775000) < 0.01), 'edit amount changed');
    const checkedObligations = form.locator('.js-ob-check:checked');
    await page.waitForFunction(() => document.querySelectorAll('#invoice-edit-modal.is-open .js-ob-check:checked').length === 2, null, { timeout: 10000 });
    ok(await checkedObligations.count() === 2, 'expected two linked payment obligations');

    evidence.url = page.url();
    evidence.rowText = rowText;
    evidence.formAction = action;
    evidence.checkedObligations = await checkedObligations.count();
    evidence.amount = await form.locator('input[name="amount"]').inputValue();
    evidence.counterparty = await form.locator('select[name="counterparty_entity_id"] option:checked').innerText();
    await page.screenshot({ path: 'P104_screens/invoice_5_edit_modal.png', fullPage: true });

    ok(errors.length === 0, 'page errors: ' + JSON.stringify(errors));
    fs.writeFileSync('P104_VISUAL.json', JSON.stringify({ ok: true, evidence, errors }, null, 2));
    console.log('P104_INVOICE_EDIT_REDIRECT_VISUAL_OK');
  } finally {
    await browser.close();
  }
})().catch(error => {
  fs.writeFileSync('P104_VISUAL.json', JSON.stringify({ ok: false, error: error.message, stack: error.stack }, null, 2));
  console.error(error);
  process.exit(1);
});
