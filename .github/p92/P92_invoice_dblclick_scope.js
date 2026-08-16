'use strict';

const { chromium } = require('playwright');
const ok = (v, m) => { if (!v) throw new Error(m); };
const B = 'https://plan-ex.ru/erpv2/';

(async () => {
  const credentials = JSON.parse(Buffer.from(process.env.P12_CREDENTIALS_B64 || '', 'base64').toString('utf8'));
  const owner = credentials.find(x => String(x.role).toUpperCase() === 'OWNER');
  ok(owner, 'OWNER credentials');

  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({ viewport: { width: 1920, height: 1080 }, deviceScaleFactor: 1, locale: 'ru-RU', timezoneId: 'Europe/Moscow' });
  const page = await context.newPage();
  const badRequests = [];
  page.on('response', response => {
    if (/\/company\/finance\/invoices\/0\/modal-view(?:\?|$)/.test(response.url())) {
      badRequests.push({ url: response.url(), status: response.status() });
    }
  });

  try {
    await page.goto(B + 'login', { waitUntil: 'domcontentloaded', timeout: 30000 });
    await page.locator('input[name="username"],input[name="email"],input[type="text"]').first().fill(owner.username);
    await page.locator('input[type="password"]').fill(owner.password);
    await page.locator('button[type="submit"],input[type="submit"]').first().click();
    await page.waitForTimeout(700);
    ok(!page.url().includes('/login'), 'login failed');

    await page.goto(B + 'company/finance/invoices', { waitUntil: 'networkidle', timeout: 30000 });
    await page.locator('[data-open-modal="invoice-create-modal"]').click();
    const createModal = page.locator('#invoice-create-modal.is-open');
    await createModal.waitFor({ state: 'visible', timeout: 10000 });

    const form = createModal.locator('form.invoice-form');
    ok(await form.count() === 1, 'invoice create form missing');
    await form.dblclick({ position: { x: 180, y: 40 } });
    await page.waitForTimeout(500);

    ok(badRequests.length === 0, 'create form double-click requested invoice #0: ' + JSON.stringify(badRequests));
    ok(await page.locator('#invoice-view-modal.is-open').count() === 0, 'create form double-click opened invoice view modal');
    ok(await page.getByText('Не удалось загрузить данные счёта.', { exact: true }).count() === 0, 'create form double-click displayed invoice load error');

    const rows = page.locator('table.table tbody tr[data-invoice-id]');
    if (await rows.count() > 0) {
      await createModal.locator('.modal-close').first().click();
      const first = rows.first();
      const id = await first.getAttribute('data-invoice-id');
      ok(id && Number(id) > 0, 'registry invoice row has invalid id');
      await first.dblclick();
      const viewModal = page.locator('#invoice-view-modal.is-open');
      await viewModal.waitFor({ state: 'visible', timeout: 10000 });
      const body = (await page.locator('#invoice-view-modal-body').innerText()).trim();
      ok(!body.includes('Не удалось загрузить данные счёта.'), 'real invoice row failed to load');
    }

    console.log('P92_INVOICE_DBLCLICK_SCOPE_OK');
  } finally {
    await browser.close();
  }
})().catch(err => {
  console.error(err);
  process.exit(1);
});
