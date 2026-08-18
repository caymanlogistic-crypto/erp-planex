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
    for (const expected of ['CASH_OPERATION', 'CLIENT_CASH_INVOICE', 'MAIN_CASH_INVOICE_PAYMENT', 'MAIN_CASH_EMPLOYEE_TRANSFER']) {
      ok(optionValues.includes(expected), 'cash scenario missing: ' + expected + '; actual=' + JSON.stringify(optionValues));
    }
    ok(!optionValues.includes('CLIENT_CASH_RECEIPT'), 'legacy route-first client cash scenario must not be exposed');
    ok(!optionValues.includes('EMPLOYEE_PERSONAL_EXPENSE'), 'employee-funded misc expense must not be created from Cash');

    await scenario.selectOption('CLIENT_CASH_INVOICE');
    const clientBlock = cashForm.locator('[data-scenario-block="CLIENT_CASH_INVOICE"]');
    ok(await clientBlock.isVisible(), 'invoice-centric client cash block did not become visible');
    ok(await clientBlock.locator('select[name="invoice_id"]').count() === 1, 'client outgoing invoice selector missing');
    ok(await clientBlock.locator('input[name="invoice_amount"]').count() === 1, 'client invoice allocation amount missing');
    ok((await clientBlock.innerText()).includes('остаток останется наличными в кассе'), 'client cash remainder semantics missing from UI');
    await page.screenshot({ path: 'P48_invoice_cash.png', fullPage: true });

    await scenario.selectOption('MAIN_CASH_INVOICE_PAYMENT');
    const carrierBlock = cashForm.locator('[data-scenario-block="MAIN_CASH_INVOICE_PAYMENT"]');
    ok(await carrierBlock.isVisible(), 'Main Cash carrier invoice block did not become visible');
    ok(await carrierBlock.locator('select[name="invoice_id"]').count() === 1, 'carrier incoming invoice selector missing');
    ok((await carrierBlock.innerText()).includes('Основной кассы'), 'carrier invoice cash source explanation missing');

    await scenario.selectOption('MAIN_CASH_EMPLOYEE_TRANSFER');
    const employeeTransferBlock = cashForm.locator('[data-scenario-block="MAIN_CASH_EMPLOYEE_TRANSFER"]');
    ok(await employeeTransferBlock.isVisible(), 'Main Cash employee transfer block did not become visible');
    ok(await employeeTransferBlock.locator('select[name="employee_ref"]').count() === 1, 'employee transfer selector missing');
    ok((await employeeTransferBlock.innerText()).includes('внутренний перевод'), 'employee transfer must be described as internal transfer');
    await page.screenshot({ path: 'P48_cash_lifecycle.png', fullPage: true });

    const ledger = page.locator('#cash-ledger-table');
    if (await ledger.count()) {
      const headers = await ledger.locator('thead th').allInnerTexts();
      const expected = ['', 'Дата', 'Касса', 'Движение', 'Получено от', 'Назначение', 'Сумма', 'Передано'];
      ok(JSON.stringify(headers.map(normalize)) === JSON.stringify(expected.map(normalize)), 'cash lifecycle headers mismatch: ' + JSON.stringify(headers));
      const rows = ledger.locator('tbody tr[data-cash-ledger-row]');
      const rowCount = await rows.count();
      ok(rowCount > 0, 'cash ledger table rendered without rows');
      const employeeInvoiceMovement = normalize('Получено → списано');
      const allowedMovements = ['Поступление', 'Списание', 'Получено', 'Получено → передано', 'Получено → разнесено', 'Получено → списано'].map(normalize);
      for (let i = 0; i < rowCount; i++) {
        const cells = rows.nth(i).locator('td');
        ok(await cells.count() === 8, 'cash lifecycle row must have eight cells');
        const movement = normalize(await cells.nth(3).innerText());
        ok(allowedMovements.includes(movement), 'invalid cash lifecycle movement label');
        if (movement === employeeInvoiceMovement) {
          ok(normalize(await cells.nth(4).innerText()) !== normalize('—'), 'employee invoice cash lifecycle must expose employee source');
          ok(normalize(await cells.nth(7).innerText()) !== normalize('—'), 'employee invoice cash lifecycle must expose carrier recipient');
          ok((await cells.nth(5).innerText()).includes('Оплата счёта'), 'employee invoice cash lifecycle must expose invoice payment purpose');
        }
      }
    } else {
      ok(bodyText.includes('Движений нет.'), 'neither cash ledger nor valid empty state is visible');
    }
    console.log('P48_CASH_LEDGER_RUNTIME_OK');
    console.log('P48_INVOICE_CENTRIC_CASH_UI_OK');

    response = await page.goto(BASE + 'company/finance/employee-payments', { waitUntil: 'domcontentloaded' });
    ok(response && response.status() === 200, 'employee payments page HTTP 200');
    bodyText = await page.locator('body').innerText();
    ok(bodyText.includes('Выплаты сотрудникам'), 'employee payments title missing');
    ok(!bodyText.includes('Ошибка при загрузке данных:'), 'employee payments page contains database/runtime error');

    const report = page.locator('.employee-report');
    await report.waitFor({ state: 'visible', timeout: 8000 });
    ok(await report.getAttribute('data-unified-employee-ledger') === 'ready', 'unified employee ledger enhancer did not finish');
    const visibleLegacyBlocks = await page.locator('.employee-money-actions').evaluateAll(nodes => nodes.filter(node => {
      const s = getComputedStyle(node); return s.display !== 'none' && s.visibility !== 'hidden';
    }).length);
    ok(visibleLegacyBlocks === 0, 'legacy employee event subtables are still visible');
    ok(await page.locator('#employee-personal-expense-list-host').count() === 0, 'duplicate employee personal-expense history host must not exist outside unified journal');
    ok(await page.locator('.employee-personal-expense-card').count() === 0, 'duplicate employee personal-expense history card must not be rendered');
    const employeeTables = page.locator('.employee-report-table');
    ok(await employeeTables.count() <= 1, 'employee movements must render in one table');
    if (await employeeTables.count() === 1) {
      const employeeTable = employeeTables.first();
      const headers = await employeeTable.locator('thead th:visible').allInnerTexts();
      const expected = ['Дата', 'Тип платежа', 'Источник', 'Комментарий', 'Поступление', 'Расход', 'Сальдо'];
      ok(JSON.stringify(headers.map(normalize)) === JSON.stringify(expected.map(normalize)), 'employee unified headers mismatch: ' + JSON.stringify(headers));

      const operationRows = employeeTable.locator('tbody tr:not(.employee-month-summary-row)');
      const monthRows = employeeTable.locator('tbody tr.employee-month-summary-row');
      if (await operationRows.count() > 0) {
        ok(await monthRows.count() > 0, 'monthly employee totals missing from unified journal');
        const monthTexts = await monthRows.allInnerTexts();
        for (const text of monthTexts) {
          ok(text.includes('За месяц:'), 'monthly employee summary missing "За месяц": ' + text);
          ok(text.includes('Поступление'), 'monthly employee summary missing income: ' + text);
          ok(text.includes('Расход'), 'monthly employee summary missing expense: ' + text);
          ok(text.includes('ИТОГО'), 'monthly employee summary missing total: ' + text);
        }
        ok(await page.locator('.employee-month-head').count() === await monthRows.count(), 'monthly totals must exist only inside the unified employee table');
      }
    }

    const invoiceButton = page.locator('#employee-invoice-payment-open');
    await invoiceButton.waitFor({ state: 'visible', timeout: 8000 });
    ok((await invoiceButton.innerText()).includes('Оплатил счёт'), 'employee invoice-payment button label mismatch');

    const miscButton = page.locator('#employee-personal-expense-open');
    await miscButton.waitFor({ state: 'visible', timeout: 8000 });
    ok((await miscButton.innerText()).includes('Прочий расход'), 'misc employee expense button label mismatch');

    const employeeInvoiceModal = page.locator('#employee-invoice-payment-modal');
    await employeeInvoiceModal.waitFor({ state: 'attached', timeout: 5000 });
    const employeeInvoiceForm = employeeInvoiceModal.locator('#employee-invoice-payment-form');
    ok(await employeeInvoiceForm.locator('select[name="invoice_id"]').count() === 1, 'employee incoming invoice selector missing');
    ok(await employeeInvoiceForm.locator('input[name="amount"]').count() === 1, 'employee invoice amount field missing');
    ok(await employeeInvoiceForm.locator('input[name="operation_date"]').count() === 1, 'employee invoice date field missing');
    ok(await employeeInvoiceForm.locator('select[name="cash_flow_center_id"]').count() === 0, 'employee invoice payment must not ask for CFU');
    ok(await employeeInvoiceForm.locator('select[name="dds_category_id"]').count() === 0, 'employee invoice payment must not ask for DDS');
    ok((await employeeInvoiceModal.innerText()).includes('ЦФУ и статью ДДС выбирать не нужно'), 'employee invoice UI must explain automatic invoice settlement classification');
    await employeeInvoiceModal.evaluate(el => el.classList.add('is-open'));
    await page.screenshot({ path: 'P48_employee_invoice_payment.png', fullPage: true });
    await employeeInvoiceModal.evaluate(el => el.classList.remove('is-open'));
    console.log('P48_EMPLOYEE_INVOICE_PAYMENT_UI_OK');

    await miscButton.click();
    const personalModal = page.locator('.employee-personal-expense-modal');
    await personalModal.waitFor({ state: 'visible', timeout: 8000 });
    const personalForm = personalModal.locator('[data-personal-expense-form]');
    await personalForm.waitFor({ state: 'visible', timeout: 5000 });
    ok(await personalForm.locator('input[name="amount"]').count() === 1, 'misc employee expense amount field missing');
    ok(await personalForm.locator('input[name="operation_date"]').count() === 1, 'misc employee expense date field missing');
    ok(await personalForm.locator('select[name="cash_flow_center_id"]').count() === 1, 'misc employee expense CFU selector missing');
    ok(await personalForm.locator('select[name="dds_category_id"]').count() === 1, 'misc employee expense DDS selector missing');
    ok(await personalForm.locator('input[name="purpose"]').count() === 1, 'misc employee expense purpose field missing');
    await page.screenshot({ path: 'P48_employee_personal_expense.png', fullPage: true });
    console.log('P48_EMPLOYEE_PERSONAL_EXPENSE_UI_OK');
    console.log('P48_EMPLOYEE_MONTHLY_TOTALS_OK');
    console.log('P48_EMPLOYEE_UNIFIED_LEDGER_UI_OK');

    ok(errors.length === 0, 'browser errors: ' + JSON.stringify(errors));
  } finally {
    await browser.close();
  }
})().catch(err => { console.error(err); process.exit(1); });
