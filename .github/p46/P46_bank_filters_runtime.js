'use strict';

const { chromium } = require('playwright');
const BASE = 'https://plan-ex.ru/erpv2/';
const ok = (value, message) => { if (!value) throw new Error(message); };
const statusMap = {
  'Не разнесено': 'UNALLOCATED',
  'Разнесено автоматически': 'AUTO',
  'Разнесено вручную': 'MANUAL',
  'Конфликт / требует проверки': 'NEEDS_REVIEW'
};

async function waitForUrlParam(page, name, expected) {
  await page.waitForURL(url => {
    const v = new URL(url).searchParams.get(name);
    return expected === null ? v === null : v === expected;
  }, { timeout: 10000 });
  await page.waitForLoadState('domcontentloaded');
}

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

    let response = await page.goto(BASE + 'company/finance/bank-accounts', { waitUntil: 'domcontentloaded' });
    ok(response && response.status() === 200, 'bank page HTTP 200');
    ok(await page.locator('#bank-register-auto-filter-script').count() === 1, 'auto-filter runtime script missing');
    ok(await page.locator('#bank-register-auto-filter-style').count() === 1, 'auto-filter runtime style missing');

    const filterForm = page.locator('form.bank-controls-filter');
    ok(await filterForm.count() === 1, 'bank filter form missing');
    for (const name of ['date_from','date_to','classification_status','q']) {
      ok(await filterForm.locator(`[name="${name}"]`).count() === 1, 'filter missing: ' + name);
    }
    ok(await filterForm.getByRole('button', { name: 'Применить', exact: true }).count() === 0, 'Apply button must be removed');

    const table = page.locator('table.bank-transactions-table');
    ok(await table.count() === 1, 'bank table missing');
    const headers = table.locator('thead th');
    const headerTexts = (await headers.allTextContents()).map(x => x.trim());
    const accountIndex = headerTexts.indexOf('Счёт');
    const purposeIndex = headerTexts.indexOf('Назначение');
    ok(accountIndex >= 0 && purposeIndex >= 0, 'expected underlying headers missing');
    ok((await headers.nth(accountIndex).evaluate(el => getComputedStyle(el).display)) === 'none', 'Account column is still visible');
    const purposeWidth = await headers.nth(purposeIndex).evaluate(el => el.getBoundingClientRect().width);
    ok(purposeWidth >= 300, 'Purpose column not widened enough: ' + purposeWidth);

    let rows = table.locator('tbody tr[data-tx-id]');
    ok(await rows.count() > 0, 'no production bank rows available for read-only filter acceptance');

    const firstStatusText = (await rows.first().locator('td').last().innerText()).split('\n')[0].trim();
    const statusValue = statusMap[firstStatusText];
    ok(statusValue, 'cannot map first row status: ' + firstStatusText);
    await filterForm.locator('[name="classification_status"]').selectOption(statusValue);
    await waitForUrlParam(page, 'classification_status', statusValue);
    rows = page.locator('table.bank-transactions-table tbody tr[data-tx-id]');
    ok(await rows.count() > 0, 'status filter returned no rows for existing status');
    for (let i = 0; i < Math.min(await rows.count(), 100); i++) {
      const text = (await rows.nth(i).locator('td').last().innerText()).split('\n')[0].trim();
      ok(statusMap[text] === statusValue, `status filter mismatch row ${i}: ${text}`);
    }

    await page.locator('form.bank-controls-filter [name="classification_status"]').selectOption('');
    await waitForUrlParam(page, 'classification_status', null);

    rows = page.locator('table.bank-transactions-table tbody tr[data-tx-id]');
    const row = rows.first();
    const inn = (await row.getAttribute('data-tx-inn') || '').trim();
    const cparty = (await row.getAttribute('data-tx-cparty') || '').trim();
    const purpose = (await row.getAttribute('data-tx-purpose') || '').trim();
    const searchTerm = inn || cparty.split(/\s+/)[0] || purpose.split(/\s+/)[0];
    ok(searchTerm, 'no usable search term from production row');
    await page.locator('form.bank-controls-filter [name="q"]').fill(searchTerm);
    await waitForUrlParam(page, 'q', searchTerm);
    rows = page.locator('table.bank-transactions-table tbody tr[data-tx-id]');
    ok(await rows.count() > 0, 'search returned no rows for existing term');
    const termLower = searchTerm.toLocaleLowerCase('ru-RU');
    for (let i = 0; i < Math.min(await rows.count(), 100); i++) {
      const hay = [
        await rows.nth(i).getAttribute('data-tx-docnum') || '',
        await rows.nth(i).getAttribute('data-tx-cparty') || '',
        await rows.nth(i).getAttribute('data-tx-inn') || '',
        await rows.nth(i).getAttribute('data-tx-purpose') || '',
        await rows.nth(i).innerText()
      ].join(' ').toLocaleLowerCase('ru-RU');
      ok(hay.includes(termLower), `search mismatch row ${i}`);
    }

    await page.locator('form.bank-controls-filter [name="q"]').fill('');
    await waitForUrlParam(page, 'q', null);

    rows = page.locator('table.bank-transactions-table tbody tr[data-tx-id]');
    const date = await rows.first().getAttribute('data-tx-date');
    ok(/^\d{4}-\d{2}-\d{2}$/.test(date || ''), 'invalid row date: ' + date);
    await page.locator('form.bank-controls-filter [name="date_from"]').fill(date);
    await page.locator('form.bank-controls-filter [name="date_from"]').dispatchEvent('change');
    await waitForUrlParam(page, 'date_from', date);
    await page.locator('form.bank-controls-filter [name="date_to"]').fill(date);
    await page.locator('form.bank-controls-filter [name="date_to"]').dispatchEvent('change');
    await waitForUrlParam(page, 'date_to', date);
    rows = page.locator('table.bank-transactions-table tbody tr[data-tx-id]');
    ok(await rows.count() > 0, 'date filter returned no rows for existing date');
    for (let i = 0; i < Math.min(await rows.count(), 100); i++) {
      ok((await rows.nth(i).getAttribute('data-tx-date')) === date, `date filter mismatch row ${i}`);
    }

    await page.locator('form.bank-controls-filter [name="date_from"]').fill('');
    await page.locator('form.bank-controls-filter [name="date_from"]').dispatchEvent('change');
    await waitForUrlParam(page, 'date_from', null);
    await page.locator('form.bank-controls-filter [name="date_to"]').fill('');
    await page.locator('form.bank-controls-filter [name="date_to"]').dispatchEvent('change');
    await waitForUrlParam(page, 'date_to', null);

    response = await page.goto(BASE + 'company/finance/employee-payments', { waitUntil: 'domcontentloaded' });
    ok(response && response.status() === 200, 'employee payments HTTP 200');
    ok(!(await page.locator('body').innerText()).includes('Двойной клик — полный журнал'), 'obsolete employee hint is visible');
    await page.getByRole('button', { name: '+ Выплата', exact: true }).click();
    const modal = page.locator('#employee-payment-create-modal.is-open');
    await modal.waitFor({ state: 'visible', timeout: 10000 });
    await modal.locator('form').waitFor({ state: 'visible', timeout: 10000 });
    const values = await modal.locator('select[name="employee_ref"] option').evaluateAll(opts => opts.map(o => o.value));
    ok(values.some(v => v.startsWith('COMPANY_USER:')), 'company owner missing from employee selector');
    ok(values.some(v => v.startsWith('TENANT_USER:')), 'tenant logist/user missing from employee selector');

    ok(errors.length === 0, 'browser errors: ' + JSON.stringify(errors));
    console.log('P46_BANK_FILTERS_RUNTIME_OK');
  } finally {
    await browser.close();
  }
})().catch(err => { console.error(err); process.exit(1); });
