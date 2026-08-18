'use strict';

const fs = require('fs');
const { chromium } = require('playwright');

const B = 'https://plan-ex.ru/erpv2/';
const ok = (v, m) => { if (!v) throw new Error(m); };
const fatal = /(Fatal error|Parse error|Uncaught (?:TypeError|Error|Exception)|Class ".+" not found)/i;
const normalize = s => String(s || '').replace(/\s+/g, ' ').trim();

(async () => {
  const credentials = JSON.parse(Buffer.from(process.env.P12_CREDENTIALS_B64 || '', 'base64').toString('utf8'));
  const owner = credentials.find(x => String(x.role).toUpperCase() === 'OWNER');
  ok(owner, 'OWNER credentials');

  fs.mkdirSync('P105_screens', { recursive: true });
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

  const evidence = {
    target: {
      date: '10.08.2026',
      account: 'Основная касса',
      purposeContains: 'Наличная оплата от клиента ООО "ПОТОК"',
      invoiceFragment: 'счёт Н',
      amount: '300 000,00'
    }
  };

  try {
    await page.goto(B + 'login', { waitUntil: 'domcontentloaded', timeout: 30000 });
    await page.locator('input[name="username"],input[name="email"],input[type="text"]').first().fill(owner.username);
    await page.locator('input[type="password"]').fill(owner.password);
    await page.locator('button[type="submit"],input[type="submit"]').first().click();
    await page.waitForTimeout(700);
    ok(!page.url().includes('/login'), 'login failed');

    let response = await page.goto(B + 'company/finance/cash', { waitUntil: 'networkidle', timeout: 30000 });
    ok(response && response.status() === 200, `cash page HTTP ${response ? response.status() : 'none'}`);
    let body = await page.locator('body').innerText();
    ok(!fatal.test(body), 'fatal runtime text on cash page');

    const accountRows = page.locator('.table-card table tbody tr');
    let beforeBalance = null;
    for (let i = 0; i < await accountRows.count(); i++) {
      const t = normalize(await accountRows.nth(i).innerText());
      if (t.includes('Основная касса') && t.includes('RUR')) {
        const cells = accountRows.nth(i).locator('td');
        if (await cells.count() >= 4) beforeBalance = normalize(await cells.nth(3).innerText());
        break;
      }
    }
    evidence.beforeCashBalance = beforeBalance;

    const unresolved = page.locator('tr.cash-row-unresolved');
    const matches = [];
    for (let i = 0; i < await unresolved.count(); i++) {
      const row = unresolved.nth(i);
      const text = normalize(await row.innerText());
      if (
        text.includes('10.08.2026') &&
        text.includes('Основная касса') &&
        text.includes('ПОТОК') &&
        text.includes('счёт Н') &&
        /300\s*000,00/.test(text)
      ) {
        matches.push({ row, text });
      }
    }

    evidence.matchCountBefore = matches.length;
    await page.screenshot({ path: 'P105_screens/cash_before.png', fullPage: true });

    if (matches.length === 0) {
      evidence.result = 'already_absent';
      fs.writeFileSync('P105_RESULT.json', JSON.stringify({ ok: true, evidence, errors }, null, 2));
      console.log('P105_TARGET_ALREADY_ABSENT');
      console.log('P105_CANCEL_ACCIDENTAL_CASH_OK');
      return;
    }

    ok(matches.length === 1, `expected exactly one accidental cash row, got ${matches.length}`);
    const targetRow = matches[0].row;
    const rowText = matches[0].text;
    const selector = targetRow.locator('input.cash-source-select');
    ok(await selector.count() === 1, 'target row is not an unresolved cash source');
    const operationId = await selector.getAttribute('value');
    ok(/^\d+$/.test(String(operationId || '')), `invalid operation id ${operationId}`);
    evidence.operationId = Number(operationId);
    evidence.cashRowTextBefore = rowText;

    response = await page.goto(B + 'company/finance/operations?search=' + encodeURIComponent('ПОТОК'), { waitUntil: 'networkidle', timeout: 30000 });
    ok(response && response.status() === 200, `operations page HTTP ${response ? response.status() : 'none'}`);
    body = await page.locator('body').innerText();
    ok(!fatal.test(body), 'fatal runtime text on operations page');

    const opRow = page.locator(`tr[data-operation-id="${operationId}"]`);
    ok(await opRow.count() === 1, `operation #${operationId} not found in operations register`);
    const opText = normalize(await opRow.innerText());
    ok(opText.includes('10.08.2026'), 'operation date mismatch');
    ok(opText.includes('Основная касса'), 'operation account mismatch');
    ok(opText.includes('ПОТОК'), 'operation counterparty/purpose mismatch');
    ok(/300\s*000,00/.test(opText), 'operation amount mismatch');
    evidence.operationRowTextBefore = opText;

    await opRow.dblclick();
    const viewModal = page.locator('#operation-view-modal.is-open');
    await viewModal.waitFor({ state: 'visible', timeout: 10000 });
    const card = viewModal.locator(`[data-operation-card][data-operation-id="${operationId}"]`);
    await card.waitFor({ state: 'visible', timeout: 10000 });
    ok(await card.getAttribute('data-status') === 'POSTED', 'target operation is not POSTED');
    ok(/300\s*000,00/.test(normalize(await card.innerText())), 'modal amount mismatch');
    ok(normalize(await card.innerText()).includes('ПОТОК'), 'modal target mismatch');

    const cancelButton = viewModal.locator(`[data-operation-cancel-btn][data-operation-id="${operationId}"]`);
    ok(await cancelButton.count() === 1, 'cancel operation button missing');
    ok(!(await cancelButton.isDisabled()), 'cancel operation button disabled; operation may have allocations');
    await cancelButton.click();

    const cancelModal = page.locator('#operation-cancel-modal.is-open');
    await cancelModal.waitFor({ state: 'visible', timeout: 10000 });
    const summary = normalize(await cancelModal.innerText());
    ok(summary.includes(`Операция №${operationId}`), 'cancel modal operation id mismatch');
    ok(summary.includes('10.08.2026'), 'cancel modal date mismatch');
    ok(/300\s*000,00/.test(summary), 'cancel modal amount mismatch');
    await cancelModal.locator('textarea[name="reason"]').fill('Ошибочно созданная кассовая операция. Отмена по указанию владельца 18.08.2026.');
    await page.screenshot({ path: 'P105_screens/cancel_confirmation.png', fullPage: true });

    const cancelResponsePromise = page.waitForResponse(r =>
      r.url().includes(`/company/finance/operations/${operationId}/cancel`) && r.request().method() === 'POST',
      { timeout: 15000 }
    );
    await cancelModal.locator('button[type="submit"]').click();
    const cancelResponse = await cancelResponsePromise;
    const cancelPayload = await cancelResponse.json();
    ok(cancelResponse.status() === 200, `cancel HTTP ${cancelResponse.status()}`);
    ok(cancelPayload && cancelPayload.ok === true, `cancel failed: ${JSON.stringify(cancelPayload)}`);
    evidence.cancelResponse = cancelPayload;

    const cancelledCard = page.locator(`#operation-view-modal.is-open [data-operation-card][data-operation-id="${operationId}"][data-status="CANCELLED"]`);
    await cancelledCard.waitFor({ state: 'visible', timeout: 10000 });
    evidence.cancelledCardText = normalize(await cancelledCard.innerText());
    await page.screenshot({ path: 'P105_screens/operation_cancelled.png', fullPage: true });

    response = await page.goto(B + 'company/finance/cash', { waitUntil: 'networkidle', timeout: 30000 });
    ok(response && response.status() === 200, `cash page after cancel HTTP ${response ? response.status() : 'none'}`);
    body = await page.locator('body').innerText();
    ok(!fatal.test(body), 'fatal runtime text on cash page after cancel');

    const unresolvedAfter = page.locator('tr.cash-row-unresolved');
    let stillPresent = 0;
    for (let i = 0; i < await unresolvedAfter.count(); i++) {
      const text = normalize(await unresolvedAfter.nth(i).innerText());
      if (
        text.includes('10.08.2026') && text.includes('Основная касса') && text.includes('ПОТОК') &&
        text.includes('счёт Н') && /300\s*000,00/.test(text)
      ) stillPresent++;
    }
    ok(stillPresent === 0, 'accidental cash row still present after cancellation');
    evidence.matchCountAfter = stillPresent;

    let afterBalance = null;
    const accountRowsAfter = page.locator('.table-card table tbody tr');
    for (let i = 0; i < await accountRowsAfter.count(); i++) {
      const t = normalize(await accountRowsAfter.nth(i).innerText());
      if (t.includes('Основная касса') && t.includes('RUR')) {
        const cells = accountRowsAfter.nth(i).locator('td');
        if (await cells.count() >= 4) afterBalance = normalize(await cells.nth(3).innerText());
        break;
      }
    }
    evidence.afterCashBalance = afterBalance;
    evidence.result = 'cancelled';
    await page.screenshot({ path: 'P105_screens/cash_after.png', fullPage: true });

    ok(errors.length === 0, 'page errors: ' + JSON.stringify(errors));
    fs.writeFileSync('P105_RESULT.json', JSON.stringify({ ok: true, evidence, errors }, null, 2));
    console.log(`P105_CANCELLED_OPERATION_ID=${operationId}`);
    console.log(`P105_BEFORE_CASH_BALANCE=${beforeBalance}`);
    console.log(`P105_AFTER_CASH_BALANCE=${afterBalance}`);
    console.log('P105_CANCEL_ACCIDENTAL_CASH_OK');
  } finally {
    await browser.close();
  }
})().catch(error => {
  fs.writeFileSync('P105_RESULT.json', JSON.stringify({ ok: false, error: error.message, stack: error.stack }, null, 2));
  console.error(error);
  process.exit(1);
});
