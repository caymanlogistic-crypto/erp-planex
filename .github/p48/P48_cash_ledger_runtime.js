'use strict';

const { chromium } = require('playwright');
const BASE = 'https://plan-ex.ru/erpv2/';
const ok = (value, message) => { if (!value) throw new Error(message); };
const normalize = value => String(value || '').trim().toLocaleLowerCase('ru-RU');

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

    let response = await page.goto(BASE + 'company/finance/cash', { waitUntil: 'domcontentloaded' });
    ok(response && response.status() === 200, 'cash page HTTP 200');
    ok((await page.locator('h1').first().innerText()).includes('Касса'), 'cash title missing');

    let bodyText = await page.locator('body').innerText();
    ok(!bodyText.includes('Ошибка при загрузке данных:'), 'cash page database/runtime error');
    ok(!bodyText.includes('Работа с кассой недоступна.'), 'cash page unexpectedly unavailable');

    const personalExpenseSection = page.locator('.section-title').filter({ hasText: 'Расходы сотрудников из личных средств' });
    ok(await personalExpenseSection.count() === 1, 'personal-funded expense history section missing');

    const createOperation = page.locator('#cash-operation-create-btn');
    await createOperation.waitFor({ state: 'visible', timeout: 5000 });
    await createOperation.click();
    const cashModal = page.locator('#cash-operation-create-modal');
    await cashModal.waitFor({ state: 'visible', timeout: 5000 });
    const cashForm = cashModal.locator('#finance-cash-manual-form');
    await cashForm.waitFor({ state: 'visible', timeout: 10000 });
    const scenario = cashForm.locator('#cash-scenario');
    const optionValues = await scenario.locator('option').evaluateAll(opts => opts.map(o => o.value));
    for (const expected of ['CASH_OPERATION', 'CLIENT_CASH_RECEIPT']) {
      ok(optionValues.includes(expected), 'cash scenario missing: ' + expected + '; actual=' + JSON.stringify(optionValues));
    }
    ok(!optionValues.includes('EMPLOYEE_PERSONAL_EXPENSE'), 'employee-funded expense must no longer be created from Cash');
    ok(await cashForm.locator('[data-scenario-block="EMPLOYEE_PERSONAL_EXPENSE"]').count() === 0, 'obsolete employee personal expense cash block still rendered');

    await scenario.selectOption('CLIENT_CASH_RECEIPT');
    const clientBlock = cashForm.locator('[data-scenario-block="CLIENT_CASH_RECEIPT"]');
    ok(await clientBlock.isVisible(), 'client cash receipt block did not become visible');
    ok(await clientBlock.locator('select[name="linear_route_payment_id"]').count() === 1, 'client route/payment selector missing');
    await page.screenshot({ path: 'P48_cash_lifecycle.png', fullPage: true });

    const ledger = page.locator('#cash-ledger-table');
    if (await ledger.count()) {
      const headers = await ledger.locator('thead th').allInnerTexts();
      const expected = ['', 'Дата', 'Касса', 'Движение', 'Получено от', 'Назначение', 'Сумма', 'Передано'];
      ok(JSON.stringify(headers.map(normalize)) === JSON.stringify(expected.map(normalize)), 'cash lifecycle headers mismatch: ' + JSON.stringify(headers));
      const rows = ledger.locator('tbody tr[data-cash-ledger-row]');
      const rowCount = await rows.count();
      ok(rowCount > 0, 'cash ledger table rendered without rows');
      const allowedMovements = ['Поступление', 'Списание', 'Получено', 'Получено → передано', 'Получено → разнесено'].map(normalize);
      for (let i = 0; i < rowCount; i++) {
        const cells = rows.nth(i).locator('td');
        ok(await cells.count() === 8, 'cash lifecycle row must have eight cells');
        ok(allowedMovements.includes(normalize(await cells.nth(3).innerText())), 'invalid cash lifecycle movement label');
      }
    } else {
      ok(bodyText.includes('Движений нет.'), 'neither cash ledger nor valid empty state is visible');
    }

    response = await page.goto(BASE + 'company/finance/employee-payments', { waitUntil: 'domcontentloaded' });
    ok(response && response.status() === 200, 'employee payments page HTTP 200');
    bodyText = await page.locator('body').innerText();
    ok(bodyText.includes('Выплаты сотрудникам'), 'employee payments title missing');
    ok(!bodyText.includes('Ошибка'), 'employee payments page contains runtime error: ' + bodyText.match(/Ошибка[^\n]*/)?.[0]);

    const personalButton = page.locator('#employee-personal-expense-open');
    await personalButton.waitFor({ state: 'visible', timeout: 8000 });
    ok((await personalButton.innerText()).includes('Оплачено сотрудником'), 'new employee-funded expense button label mismatch');

    const listHost = page.locator('#employee-personal-expense-list-host');
    await listHost.waitFor({ state: 'attached', timeout: 5000 });
    const loadedCard = listHost.locator('.employee-personal-expense-card');
    const warning = listHost.locator('.notice.warn');
    let listLoaded = false;
    for (let i = 0; i < 40; i++) {
      if ((await loadedCard.count()) > 0 || (await warning.count()) > 0) { listLoaded = true; break; }
      await page.waitForTimeout(200);
    }
    ok(listLoaded, 'linked employee expense list did not finish loading');
    ok(await warning.count() === 0, 'linked employee expense list failed to load: ' + (await listHost.innerText()));

    await personalButton.click();
    const personalModal = page.locator('.employee-personal-expense-modal');
    await personalModal.waitFor({ state: 'visible', timeout: 8000 });
    const personalForm = personalModal.locator('[data-personal-expense-form]');
    await personalForm.waitFor({ state: 'visible', timeout: 5000 });
    ok(await personalForm.locator('input[name="amount"]').count() === 1, 'employee-funded expense amount field missing');
    ok(await personalForm.locator('input[name="operation_date"]').count() === 1, 'employee-funded expense date field missing');
    ok(await personalForm.locator('select[name="cash_flow_center_id"]').count() === 1, 'employee-funded expense CFU selector missing');
    ok(await personalForm.locator('select[name="dds_category_id"]').count() === 1, 'employee-funded expense DDS selector missing');
    ok(await personalForm.locator('input[name="purpose"]').count() === 1, 'employee-funded expense purpose field missing');
    ok((await personalModal.innerText()).includes('Основную кассу'), 'modal must explain automatic Main Cash postings');
    ok((await personalModal.innerText()).includes('Остаток Основной кассы'), 'modal must explain zero-net Main Cash effect');
    await page.screenshot({ path: 'P48_employee_personal_expense.png', fullPage: true });

    ok(errors.length === 0, 'browser errors: ' + JSON.stringify(errors));
    console.log('P48_CASH_LEDGER_RUNTIME_OK');
    console.log('P48_EMPLOYEE_PERSONAL_EXPENSE_UI_OK');
  } finally {
    await browser.close();
  }
})().catch(err => { console.error(err); process.exit(1); });
