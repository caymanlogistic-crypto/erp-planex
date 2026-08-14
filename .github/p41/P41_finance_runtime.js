'use strict';

const { chromium } = require('playwright');
const B = 'https://plan-ex.ru/erpv2/';
const ok = (value, message) => { if (!value) throw new Error(message); };

(async () => {
  const credentials = JSON.parse(Buffer.from(process.env.P12_CREDENTIALS_B64 || '', 'base64').toString('utf8'));
  const owner = credentials.find(x => String(x.role).toUpperCase() === 'OWNER');
  ok(owner, 'OWNER credentials');

  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({ viewport: { width: 1920, height: 1080 }, locale: 'ru-RU', timezoneId: 'Europe/Moscow' });
  const page = await context.newPage();
  const errors = [];
  page.on('console', msg => { if (msg.type() === 'error') errors.push('console:' + msg.text()); });
  page.on('pageerror', err => errors.push('page:' + err.message));

  try {
    await page.goto(B + 'login', { waitUntil: 'domcontentloaded' });
    await page.locator('input[name="username"],input[name="email"],input[type="text"]').first().fill(owner.username);
    await page.locator('input[type="password"]').fill(owner.password);
    await page.locator('button[type="submit"],input[type="submit"]').first().click();
    await page.waitForTimeout(700);
    ok(!page.url().includes('/login'), 'login failed');

    let response = await page.goto(B + 'company/finance/bank-accounts', { waitUntil: 'domcontentloaded' });
    ok(response && response.status() === 200, 'bank HTTP');
    ok(await page.locator('#bank-classification-register').count() === 0, 'duplicate classification register removed');
    ok(await page.locator('#bank-classify-modal').count() === 0, 'separate classify modal removed');

    const table = page.locator('table.bank-transactions-table');
    ok(await table.count() === 1, 'single bank transactions table');
    const headers = (await table.locator('th').allTextContents()).map(s => s.trim());
    for (const label of ['Дата','Счёт','Контрагент','ИНН','Назначение','Дебет','Кредит','ЦФУ','Статья ДДС','Статус']) {
      ok(headers.includes(label), 'bank header ' + label);
    }
    ok(!headers.includes('Разнести'), 'no legacy classify column');

    const debitIndex = headers.indexOf('Дебет');
    const creditIndex = headers.indexOf('Кредит');
    ok(debitIndex >= 0 && creditIndex >= 0, 'money columns');
    const th = table.locator('th');
    ok((await th.nth(debitIndex).evaluate(el => getComputedStyle(el).textAlign)) === 'right', 'debit header right');
    ok((await th.nth(creditIndex).evaluate(el => getComputedStyle(el).textAlign)) === 'right', 'credit header right');
    for (const name of ['date_from','date_to','q','classification_status']) {
      ok(await page.locator(`form.bank-controls-filter [name="${name}"]`).count() === 1, 'filter ' + name);
    }

    const reconciliation = await page.request.get(B + 'company/finance/bank-accounts/reconciliation/json');
    ok(reconciliation.status() === 200, 'reconciliation HTTP');
    const reconJson = await reconciliation.json();
    ok(reconJson.status === 'ok', 'reconciliation service ' + (reconJson.message || ''));
    ok(reconJson.reconciliation && reconJson.reconciliation.summary, 'reconciliation payload');
    ok(reconJson.reconciliation.control_from_date === '2026-07-24', 'reconciliation cutoff');
    ok(reconJson.reconciliation.summary.all_ok === true, 'reconciliation clean after cutoff');
    console.log('P41_RECON_SUMMARY=' + JSON.stringify(reconJson.reconciliation.summary || {}));

    const rows = table.locator('tbody tr[data-tx-id]');
    ok(await rows.count() > 0, 'bank rows');
    const firstRow = rows.first();
    const cells = firstRow.locator('td');
    ok((await cells.nth(debitIndex).evaluate(el => getComputedStyle(el).textAlign)) === 'right', 'debit cell right');
    ok((await cells.nth(creditIndex).evaluate(el => getComputedStyle(el).textAlign)) === 'right', 'credit cell right');
    await firstRow.click();
    await page.waitForTimeout(250);
    ok(!await page.locator('#tx-detail-modal').evaluate(el => el.classList.contains('is-open')), 'single click must not open details');
    await firstRow.dblclick();
    const detail = page.locator('#tx-detail-modal.is-open');
    await detail.waitFor({ state: 'visible', timeout: 10000 });
    await page.waitForTimeout(400);
    const detailText = await detail.innerText();
    ok(detailText.includes('Разнесение') || detailText.includes('внутренний перевод'), 'classification inside details');
    if (!detailText.includes('внутренний перевод')) {
      ok(await detail.locator('[name="cash_flow_center_id"]').count() === 1, 'CFU in details');
      ok(await detail.locator('[name="dds_category_id"]').count() === 1, 'DDS in details');
      ok(await detail.getByText('Сохранить', { exact: true }).count() >= 1, 'save classification in details');
      ok(detailText.includes('Взаиморасчёты с сотрудником'), 'employee settlement block in bank details');
    }
    await detail.locator('.modal-close').first().click();

    response = await page.goto(B + 'company/finance/settings/matching-rules', { waitUntil: 'domcontentloaded' });
    ok(response && response.status() === 200, 'rules HTTP');
    ok((await page.locator('h1.page-title').first().innerText()).trim() === 'Правила разнесения', 'rules title');
    ok(await page.getByText('Справочник ЦФУ', { exact: true }).count() === 0, 'legacy CFU panel removed');
    const rulesTable = page.locator('#rules-list');
    if (await rulesTable.count()) {
      const ruleHeaders = (await rulesTable.locator('thead th').allTextContents()).map(s => s.trim());
      ok(JSON.stringify(ruleHeaders) === JSON.stringify(['ЦФУ','Статья','ИНН','Условие','Приоритет']), 'current rule headers ' + JSON.stringify(ruleHeaders));
    }
    const createButton = page.locator('#matching-rule-create-btn');
    ok(await createButton.count() === 1, 'create rule button');
    await createButton.click();
    const createModal = page.locator('#matching-rule-create-modal.is-open');
    await createModal.waitFor({ state: 'visible', timeout: 10000 });
    await createModal.locator('form[data-matching-rule-form]').waitFor({ state: 'visible', timeout: 10000 });
    for (const name of ['target_cash_flow_center_id','target_dds_category_id','priority','counterparty_inn','purpose_contains','bank_account_id']) {
      ok(await createModal.locator(`[name="${name}"]`).count() === 1, 'rule field ' + name);
    }
    ok(await createModal.locator('input[type="checkbox"][name="auto_apply"]').count() === 0, 'auto apply checkbox removed');
    ok(await createModal.locator('input[type="hidden"][name="auto_apply"][value="1"]').count() === 1, 'active rule auto applies');
    await createModal.locator('.modal-close').first().click();

    response = await page.goto(B + 'company/finance/employee-payments', { waitUntil: 'domcontentloaded' });
    ok(response && response.status() === 200, 'employee payments HTTP');
    ok((await page.locator('h1.page-title').first().innerText()).trim() === 'Выплаты сотрудникам', 'employee payments title');
    const paymentButton = page.getByRole('button', { name: '+ Выплата', exact: true });
    ok(await paymentButton.count() === 1, 'employee payment button');
    ok(await page.getByRole('button', { name: '+ Возврат', exact: true }).count() === 1, 'employee return button');
    ok((await page.locator('.page-summary').innerText()).includes('все активные пользователи ERP'), 'employee definition is visible');

    await paymentButton.click();
    const employeeCreate = page.locator('#employee-payment-create-modal.is-open');
    await employeeCreate.waitFor({ state: 'visible', timeout: 10000 });
    await employeeCreate.locator('form').waitFor({ state: 'visible', timeout: 10000 });
    const employeeOptions = employeeCreate.locator('select[name="employee_user_id"] option');
    ok(await employeeOptions.count() > 1, 'active employee selector is populated');
    const sourceValues = await employeeCreate.locator('select[name="source_type"] option').evaluateAll(options => options.map(o => o.value));
    ok(sourceValues.includes('CASH') && sourceValues.includes('BANK'), 'employee payment supports CASH and BANK');
    ok((await employeeCreate.innerText()).includes('руководители, логисты и другие роли'), 'employee roles are not hard-filtered');
    await employeeCreate.locator('.modal-close').first().click();

    const employeeRows = page.locator('tr[data-employee-payment-row]');
    if (await employeeRows.count() > 0) {
      await employeeRows.first().dblclick();
      const ledger = page.locator('#employee-payment-ledger-modal.is-open');
      await ledger.waitFor({ state: 'visible', timeout: 10000 });
      ok((await ledger.innerText()).includes('Исправить'), 'employee ledger exposes safe reassignment');
      ok(await ledger.locator('form[action*="/employee-payments/movements/"][action$="/reassign"]').count() > 0, 'employee reassignment is POST form');
      await ledger.locator('.modal-close').first().click();
    }

    ok(!/(Fatal error|Parse error|Uncaught TypeError)/i.test(await page.locator('body').innerText()), 'fatal runtime error');
    ok(errors.length === 0, 'browser errors: ' + JSON.stringify(errors));
    console.log('P41_ERRORS=' + JSON.stringify(errors));
    console.log('P41_FINANCE_RUNTIME_OK');
  } finally {
    await browser.close();
  }
})().catch(error => { console.error(error); process.exit(1); });
