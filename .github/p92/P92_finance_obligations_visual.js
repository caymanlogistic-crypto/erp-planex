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
  fs.mkdirSync('P92_screens', { recursive: true });

  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({ viewport: { width: 1920, height: 1080 }, deviceScaleFactor: 1, locale: 'ru-RU', timezoneId: 'Europe/Moscow' });
  const page = await context.newPage();
  const pageErrors = [];
  page.on('pageerror', e => pageErrors.push(e.message));
  const evidence = {};

  async function inspect(path, name, selector) {
    const response = await page.goto(B + path, { waitUntil: 'networkidle', timeout: 30000 });
    const status = response ? response.status() : 0;
    const body = await page.locator('body').innerText().catch(() => '');
    if (status !== 200) {
      await page.screenshot({ path: `P92_screens/${name}_http_${status || 'none'}.png`, fullPage: true }).catch(() => {});
      throw new Error(`${name} HTTP ${status || 'none'} BODY ${body.slice(0, 2400).replace(/\s+/g, ' ')}`);
    }
    ok(!fatal.test(body), `${name} fatal runtime text`);
    if (selector) ok(await page.locator(selector).count() > 0, `${name} selector ${selector}`);
    const viewport = page.viewportSize();
    const root = selector ? page.locator(selector).first() : page.locator('main.content').first();
    const box = await root.boundingBox();
    ok(box && box.width > 300 && box.height > 60, `${name} collapsed geometry ${JSON.stringify(box)}`);
    evidence[name] = { url: page.url(), viewport, box, bodyLength: body.length };
    await page.screenshot({ path: `P92_screens/${name}.png`, fullPage: true });
  }

  async function waitObligationsSettled(modal, label) {
    const box = modal.locator('.js-obligations-box');
    const deadline = Date.now() + 10000;
    let text = '';
    while (Date.now() < deadline) {
      text = (await box.innerText()).trim();
      if (!text.includes('Загрузка…')) break;
      await page.waitForTimeout(150);
    }
    ok(!text.includes('Загрузка…'), `${label} obligations loading timeout`);
    ok(!text.includes('Не удалось загрузить платёжные обязательства.'), `${label} obligations load failed: ${text}`);
    ok(text.includes('Открытых обязательств для этого контрагента нет.') || text.includes('Рейс #'), `${label} obligations unexpected state: ${text}`);
    return text;
  }

  try {
    await page.goto(B + 'login', { waitUntil: 'domcontentloaded' });
    await page.locator('input[name="username"],input[name="email"],input[type="text"]').first().fill(owner.username);
    await page.locator('input[type="password"]').fill(owner.password);
    await page.locator('button[type="submit"],input[type="submit"]').first().click();
    await page.waitForTimeout(700);
    ok(!page.url().includes('/login'), 'login failed');

    await inspect('company/finance/invoices', 'invoices', 'main.content');
    const create = page.locator('[data-open-modal="invoice-create-modal"]');
    ok(await create.count() === 1, 'create invoice control');
    await create.click();
    const modal = page.locator('#invoice-create-modal.is-open');
    await modal.waitFor({ state: 'visible', timeout: 10000 });
    ok(await modal.getByText('Платёжные обязательства', { exact: true }).count() === 1, 'obligation block visible');
    ok(await modal.getByText('Тип контрагента', { exact: true }).count() === 0, 'counterparty type field removed');
    ok(await modal.getByText('Наименование (вручную)', { exact: true }).count() === 0, 'manual counterparty name removed');
    ok(await modal.getByText('Плановая дата оплаты', { exact: true }).count() === 0, 'manual planned date removed');
    ok(await modal.locator('select[name="status"]').count() === 0, 'manual status selector removed');
    ok(await modal.getByText('— Ошибка загрузки —', { exact: true }).count() === 0, 'counterparty load error absent');

    const direction = modal.locator('select[name="direction"]');
    const counterparty = modal.locator('select[name="counterparty_entity_id"]');
    const inn = modal.locator('.js-inv-cparty-inn');
    ok(await counterparty.locator('option').count() > 1, 'outgoing clients loaded');
    const firstClient = await counterparty.locator('option:not([value=""])').first().getAttribute('value');
    ok(firstClient, 'client option available');
    await counterparty.selectOption(firstClient);
    ok((await inn.inputValue()).trim().length > 0, 'client INN populated');
    const outgoingObligations = await waitObligationsSettled(modal, 'outgoing');
    await page.screenshot({ path: 'P92_screens/invoice_create_outgoing.png', fullPage: true });

    await direction.selectOption('INCOMING');
    ok(await counterparty.locator('option').count() > 1, 'incoming contractors loaded');
    const firstContractor = await counterparty.locator('option:not([value=""])').first().getAttribute('value');
    ok(firstContractor, 'contractor option available');
    await counterparty.selectOption(firstContractor);
    ok((await inn.inputValue()).trim().length > 0, 'contractor INN populated');
    const incomingObligations = await waitObligationsSettled(modal, 'incoming');
    ok(await modal.getByText('— Ошибка загрузки —', { exact: true }).count() === 0, 'incoming counterparty load error absent');

    const mbox = await modal.locator('.modal').boundingBox();
    ok(mbox && mbox.width > 700 && mbox.height > 350, 'invoice modal collapsed ' + JSON.stringify(mbox));
    ok(mbox.x >= 0 && mbox.x + mbox.width <= 1920, 'invoice modal horizontal overflow');
    evidence.invoice_create_modal = {
      box: mbox,
      scrollHeight: await modal.locator('.modal').evaluate(el => el.scrollHeight),
      clientHeight: await modal.locator('.modal').evaluate(el => el.clientHeight),
      outgoingClients: true,
      incomingContractors: true,
      outgoingObligations,
      incomingObligations
    };
    await page.screenshot({ path: 'P92_screens/invoice_create_modal.png', fullPage: true });
    await modal.locator('.modal-close').first().click();

    await inspect('company/finance/receivables', 'receivables', '.receivables-page');
    ok(await page.getByText('Дебиторская задолженность', { exact: true }).count() >= 1, 'receivables title');
    ok(await page.getByText('Просрочено', { exact: true }).count() >= 1, 'overdue summary visible');
    const summaryBox = await page.locator('.receivables-summary').boundingBox();
    const tableBox = await page.locator('.receivables-table-wrap').boundingBox();
    ok(summaryBox && summaryBox.height > 50, 'receivables summary collapsed');
    ok(tableBox && tableBox.height > 50, 'receivables table collapsed');
    ok(tableBox.y >= summaryBox.y + summaryBox.height - 2, 'receivables blocks overlap');
    evidence.receivables.summaryBox = summaryBox;
    evidence.receivables.tableBox = tableBox;

    await inspect('company/finance/payment-calendar', 'payment_calendar', 'main.content');
    await inspect('company/trips/linear', 'linear_trips', 'main.content');
    await inspect('company/finance/bank-accounts', 'bank_accounts', 'main.content');

    ok(pageErrors.length === 0, 'page errors: ' + JSON.stringify(pageErrors));
    fs.writeFileSync('P92_VISUAL.json', JSON.stringify({ ok: true, pageErrors, evidence }, null, 2));
    console.log('P92_FINANCE_OBLIGATIONS_VISUAL_OK');
  } finally {
    await browser.close();
  }
})().catch(e => {
  fs.writeFileSync('P92_VISUAL.json', JSON.stringify({ ok: false, error: e.message, stack: e.stack }, null, 2));
  console.error(e);
  process.exit(1);
});