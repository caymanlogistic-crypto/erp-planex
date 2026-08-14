'use strict';
const { chromium } = require('playwright');
const B = 'https://plan-ex.ru/erpv2/';
const ok = (v, m) => { if (!v) throw new Error(m); };

(async () => {
  const credentials = JSON.parse(Buffer.from(process.env.P12_CREDENTIALS_B64 || '', 'base64').toString('utf8'));
  const owner = credentials.find(x => String(x.role).toUpperCase() === 'OWNER');
  ok(owner, 'OWNER credentials not found');

  const browser = await chromium.launch({ headless: true });
  const ctx = await browser.newContext({ viewport: { width: 1920, height: 1080 }, locale: 'ru-RU', timezoneId: 'Europe/Moscow' });
  const p = await ctx.newPage();
  const errors = [];
  p.on('console', m => { if (m.type() === 'error') errors.push('console:' + m.text()); });
  p.on('pageerror', e => errors.push('page:' + e.message));

  try {
    await p.goto(B + 'login', { waitUntil: 'domcontentloaded' });
    await p.locator('input[name="username"],input[name="email"],input[type="text"]').first().fill(owner.username);
    await p.locator('input[type="password"]').fill(owner.password);
    await p.locator('button[type="submit"],input[type="submit"]').first().click();
    await p.waitForTimeout(700);
    ok(!p.url().includes('/login'), 'login failed');

    let response = await p.goto(B + 'company/finance/employee-payments', { waitUntil: 'domcontentloaded' });
    ok(response && response.status() === 200, 'employee payments page HTTP ' + (response && response.status()));
    ok((await p.locator('h1.page-title').first().innerText()).trim() === 'Выплаты сотрудникам', 'employee payments title mismatch');
    const nav = p.locator('a.nav-item', { hasText: 'Выплаты сотрудникам' }).first();
    ok(await nav.count() === 1, 'employee payments navigation item missing');
    ok(((await nav.getAttribute('class')) || '').includes('is-active'), 'employee payments navigation item not active');
    const crumbText = (await p.locator('.topbar-crumbs').innerText()).replace(/\s+/g, ' ').trim();
    ok(crumbText.includes('Финансы') && crumbText.includes('Выплаты сотрудникам'), 'employee payments breadcrumbs invalid: ' + crumbText);
    ok(await p.getByRole('button', { name: '+ Выплата', exact: true }).count() === 1, 'payment button missing');
    ok(await p.getByRole('button', { name: '+ Возврат', exact: true }).count() === 1, 'return button missing');
    const filterLabels = await p.locator('.employee-payments-filters .field-label').allTextContents();
    for (const label of ['Сотрудник','Источник','Дата с','Дата по']) ok(filterLabels.map(x=>x.trim()).includes(label), 'filter missing: ' + label);
    const summaryTable = p.locator('.employee-payments-table');
    if (await summaryTable.count()) {
      const headers = (await summaryTable.locator('thead th').allTextContents()).map(x => x.trim());
      ok(JSON.stringify(headers) === JSON.stringify(['Сотрудник','Выплачено','Возвращено','Сальдо','Последняя операция']), 'summary headers invalid: ' + JSON.stringify(headers));
    } else {
      ok((await p.locator('.ux-empty').innerText()).includes('Операций с сотрудниками пока нет'), 'empty state missing');
    }
    const bodyOverflow = await p.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth);
    ok(!bodyOverflow, 'employee payments page horizontal overflow');
    await p.screenshot({ path: 'P75_employee_payments.png', fullPage: true });

    async function verifyCreateModal(type, buttonText, expectedTitle) {
      await p.getByRole('button', { name: buttonText, exact: true }).click();
      const modal = p.locator('#employee-payment-create-modal.is-open');
      await modal.waitFor({ state: 'visible', timeout: 10000 });
      await modal.locator('form').waitFor({ state: 'visible', timeout: 10000 });
      ok((await p.locator('#employee-payment-create-title').innerText()).trim() === expectedTitle, 'create modal title mismatch');
      const employeeSelect = modal.locator('select[name="employee_user_id"]');
      ok(await employeeSelect.count() === 1, 'employee select missing');
      ok(await employeeSelect.locator('option').count() >= 2, 'active employee list is empty');
      const source = modal.locator('select[name="source_type"]');
      ok(await source.count() === 1, 'source selector missing');
      const sourceOptions = (await source.locator('option').allTextContents()).map(x => x.trim());
      ok(sourceOptions.includes('Касса') && sourceOptions.includes('Расчётный счёт'), 'source options invalid: ' + JSON.stringify(sourceOptions));
      ok(await modal.locator('[data-source-panel="CASH"] select[name="money_account_id"]').count() === 1, 'cash account selector missing');
      ok(await modal.locator('[data-source-panel="CASH"] input[name="operation_date"]').count() === 1, 'cash date missing');
      ok(await modal.locator('[data-source-panel="CASH"] input[name="amount"]').count() === 1, 'cash amount missing');
      await source.selectOption('BANK');
      const bankSelect = modal.locator('[data-source-panel="BANK"] select[name="bank_transaction_id"]');
      ok(await bankSelect.count() === 1, 'bank transaction selector missing');
      ok(!(await bankSelect.isDisabled()), 'bank transaction selector stayed disabled');
      ok(await modal.locator('[data-source-panel="CASH"] input[name="amount"]').isDisabled(), 'hidden cash fields are not disabled');
      const footerButtons = (await modal.locator('.modal-foot .btn').allTextContents()).map(x => x.trim());
      ok(footerButtons[footerButtons.length - 1] === 'Отмена', 'modal cancel missing');
      await p.screenshot({ path: type === 'PAYMENT' ? 'P75_payment_modal.png' : 'P75_return_modal.png', fullPage: true });
      await modal.locator('[data-close-modal="employee-payment-create-modal"]').click();
      await modal.waitFor({ state: 'hidden', timeout: 5000 });
    }

    await verifyCreateModal('PAYMENT', '+ Выплата', 'Выплата сотруднику');
    await verifyCreateModal('RETURN', '+ Возврат', 'Возврат от сотрудника');

    response = await p.goto(B + 'company/finance/bank-accounts', { waitUntil: 'domcontentloaded' });
    ok(response && response.status() === 200, 'bank page HTTP ' + (response && response.status()));
    const rows = p.locator('table.bank-transactions-table tbody tr[data-tx-id]');
    ok(await rows.count() > 0, 'no bank transaction rows');
    let bankBlockFound = false;
    for (let i = 0; i < Math.min(await rows.count(), 20); i++) {
      await rows.nth(i).dblclick();
      const detail = p.locator('#tx-detail-modal.is-open');
      try {
        await detail.waitFor({ state: 'visible', timeout: 5000 });
        await p.waitForTimeout(250);
        const text = await detail.innerText();
        if (text.includes('Взаиморасчёты с сотрудником')) {
          bankBlockFound = true;
          ok(text.includes('Списание может быть выплатой сотруднику') || text.includes('Поступление может быть возвратом сотрудника') || text.includes('учтено в разделе'), 'employee bank direction copy missing');
          const employeeSelect = detail.locator('select[name="employee_user_id"]');
          if (await employeeSelect.count()) ok(await employeeSelect.locator('option').count() >= 2, 'bank employee selector is empty');
          await p.screenshot({ path: 'P75_bank_employee_link.png', fullPage: true });
          await detail.locator('.modal-close').first().click();
          break;
        }
        await detail.locator('.modal-close').first().click();
      } catch (_) {
        // Try the next row; internal transfer/unsupported rows may not expose the block.
        if (await p.locator('#tx-detail-modal.is-open').count()) await p.locator('#tx-detail-modal.is-open .modal-close').first().click();
      }
    }
    ok(bankBlockFound, 'employee settlement block not found in bank operation details');

    ok(errors.length === 0, 'browser errors: ' + JSON.stringify(errors));
    console.log('P75_PAGE=OK');
    console.log('P75_FORMS=PAYMENT,RETURN');
    console.log('P75_BANK_LINK=OK');
    console.log('P75_WRITES=0');
    console.log('P75_ERRORS=' + JSON.stringify(errors));
    console.log('P75_OK');
  } finally {
    await browser.close();
  }
})().catch(e => { console.error(e); process.exit(1); });
