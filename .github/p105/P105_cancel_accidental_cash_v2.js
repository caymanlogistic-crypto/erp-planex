'use strict';

const fs = require('fs');
const { chromium } = require('playwright');
const B = 'https://plan-ex.ru/erpv2/';
const ok = (v, m) => { if (!v) throw new Error(m); };
const norm = s => String(s || '').replace(/\s+/g, ' ').trim();
const fatal = /(Fatal error|Parse error|Uncaught (?:TypeError|Error|Exception)|Class ".+" not found)/i;
const money = s => Number(String(s || '').replace(/\s+/g, '').replace(',', '.').replace(/[^0-9.-]/g, ''));

(async () => {
  const credentials = JSON.parse(Buffer.from(process.env.P12_CREDENTIALS_B64 || '', 'base64').toString('utf8'));
  const owner = credentials.find(x => String(x.role).toUpperCase() === 'OWNER');
  ok(owner, 'OWNER credentials');
  fs.mkdirSync('P105_screens', { recursive: true });

  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({ viewport: { width: 1920, height: 1080 }, deviceScaleFactor: 1, locale: 'ru-RU', timezoneId: 'Europe/Moscow' });
  const page = await context.newPage();
  const errors = [];
  page.on('pageerror', e => errors.push(e.message));
  const evidence = { target: { date: '10.08.2026', account: 'Основная касса', counterparty: 'ПОТОК', invoiceFragment: 'счёт Н', amount: 300000 } };

  try {
    await page.goto(B + 'login', { waitUntil: 'domcontentloaded', timeout: 30000 });
    await page.locator('input[name="username"],input[name="email"],input[type="text"]').first().fill(owner.username);
    await page.locator('input[type="password"]').fill(owner.password);
    await page.locator('button[type="submit"],input[type="submit"]').first().click();
    await page.waitForTimeout(700);
    ok(!page.url().includes('/login'), 'login failed');

    let response = await page.goto(B + 'company/finance/cash', { waitUntil: 'networkidle', timeout: 30000 });
    ok(response && response.status() === 200, 'cash page unavailable');
    ok(!fatal.test(await page.locator('body').innerText()), 'fatal on cash page');

    const cashAccountRow = page.locator('.table-card table tbody tr').filter({ hasText: 'Основная касса' }).filter({ hasText: 'RUR' }).first();
    evidence.beforeCashBalance = await cashAccountRow.locator('td').nth(3).innerText();

    const unresolved = page.locator('tr.cash-row-unresolved');
    const matches = [];
    for (let i = 0; i < await unresolved.count(); i++) {
      const row = unresolved.nth(i);
      const t = norm(await row.innerText());
      if (t.includes('10.08.2026') && t.includes('Основная касса') && t.includes('ПОТОК') && t.includes('счёт Н') && /300\s*000,00/.test(t)) matches.push({ row, text: t });
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

    ok(matches.length === 1, `expected one target row, got ${matches.length}`);
    const targetRow = matches[0].row;
    const opId = await targetRow.locator('input.cash-source-select').getAttribute('value');
    ok(/^\d+$/.test(String(opId || '')), 'invalid operation id');
    evidence.operationId = Number(opId);
    evidence.cashRowTextBefore = matches[0].text;

    response = await page.goto(B + 'company/finance/operations?search=' + encodeURIComponent('ПОТОК'), { waitUntil: 'networkidle', timeout: 30000 });
    ok(response && response.status() === 200, 'operations page unavailable');
    const opRow = page.locator(`tr[data-operation-id="${opId}"]`);
    ok(await opRow.count() === 1, `operation #${opId} missing`);
    const opText = norm(await opRow.innerText());
    ok(opText.includes('10.08.2026') && opText.includes('Основная касса') && opText.includes('ПОТОК') && /300\s*000,00/.test(opText), 'operation identity mismatch');
    evidence.operationRowTextBefore = opText;

    await opRow.dblclick();
    let card = page.locator(`#operation-view-modal.is-open [data-operation-card][data-operation-id="${opId}"]`);
    await card.waitFor({ state: 'visible', timeout: 10000 });
    ok(await card.getAttribute('data-status') === 'POSTED', 'operation is not POSTED');
    const allocatedBefore = Number(await card.getAttribute('data-allocated'));
    const remainingBefore = Number(await card.getAttribute('data-remaining'));
    evidence.allocatedBefore = allocatedBefore;
    evidence.remainingBefore = remainingBefore;
    ok(Math.abs((allocatedBefore + remainingBefore) - 300000) < 0.01, `operation accounting mismatch ${allocatedBefore}+${remainingBefore}`);

    const allocationEvidence = [];
    while (true) {
      card = page.locator(`#operation-view-modal.is-open [data-operation-card][data-operation-id="${opId}"]`);
      await card.waitFor({ state: 'visible', timeout: 10000 });
      const forms = card.locator('form[data-allocation-cancel-form]');
      if (await forms.count() === 0) break;
      const form = forms.first();
      const allocationRow = form.locator('xpath=ancestor::tr[1]');
      const rowText = norm(await allocationRow.innerText());
      const action = await form.getAttribute('action');
      ok(action && /\/company\/finance\/operations\/allocations\/\d+\/cancel$/.test(action), `unexpected allocation cancel action ${action}`);
      const cells = allocationRow.locator('td');
      const allocationAmount = money(await cells.nth(1).innerText());
      ok(allocationAmount > 0 && allocationAmount <= 300000, `invalid allocation amount ${allocationAmount}`);
      allocationEvidence.push({ action, rowText, amount: allocationAmount });
      const reason = form.locator('input[name="reason"]');
      if (await reason.count()) await reason.fill('Отмена распределения ошибочной кассовой операции по указанию владельца 18.08.2026.');

      const postPromise = page.waitForResponse(r => r.url().includes('/company/finance/operations/allocations/') && r.url().endsWith('/cancel') && r.request().method() === 'POST', { timeout: 15000 });
      const refreshPromise = page.waitForResponse(r => r.url().includes(`/company/finance/operations/${opId}/modal-view`) && r.request().method() === 'GET', { timeout: 15000 });
      await form.locator('button[type="submit"]').click();
      const post = await postPromise;
      const payload = await post.json();
      ok(post.status() === 200 && payload && payload.ok === true, `allocation cancel failed ${JSON.stringify(payload)}`);
      await refreshPromise;
      await page.waitForTimeout(150);
    }
    evidence.cancelledAllocations = allocationEvidence;

    card = page.locator(`#operation-view-modal.is-open [data-operation-card][data-operation-id="${opId}"]`);
    await card.waitFor({ state: 'visible', timeout: 10000 });
    evidence.allocatedAfterUnwind = Number(await card.getAttribute('data-allocated'));
    evidence.remainingAfterUnwind = Number(await card.getAttribute('data-remaining'));
    ok(evidence.allocatedAfterUnwind === 0, 'allocations remain after unwind');
    ok(Math.abs(evidence.remainingAfterUnwind - 300000) < 0.01, 'operation remaining amount not restored');

    const cancelButton = card.locator(`[data-operation-cancel-btn][data-operation-id="${opId}"]`);
    ok(await cancelButton.count() === 1 && !(await cancelButton.isDisabled()), 'operation cancel button still unavailable');
    await cancelButton.click();
    const cancelModal = page.locator('#operation-cancel-modal.is-open');
    await cancelModal.waitFor({ state: 'visible', timeout: 10000 });
    const summary = norm(await cancelModal.innerText());
    ok(summary.includes(`Операция №${opId}`) && summary.includes('10.08.2026') && /300\s*000,00/.test(summary), 'cancel confirmation mismatch');
    await cancelModal.locator('textarea[name="reason"]').fill('Ошибочно созданная кассовая операция. Отмена по указанию владельца 18.08.2026.');
    await page.screenshot({ path: 'P105_screens/cancel_confirmation.png', fullPage: true });

    const cancelPostPromise = page.waitForResponse(r => r.url().includes(`/company/finance/operations/${opId}/cancel`) && r.request().method() === 'POST', { timeout: 15000 });
    const cancelRefreshPromise = page.waitForResponse(r => r.url().includes(`/company/finance/operations/${opId}/modal-view`) && r.request().method() === 'GET', { timeout: 15000 });
    await cancelModal.locator('button[type="submit"]').click();
    const cancelPost = await cancelPostPromise;
    const cancelPayload = await cancelPost.json();
    ok(cancelPost.status() === 200 && cancelPayload && cancelPayload.ok === true, `operation cancel failed ${JSON.stringify(cancelPayload)}`);
    await cancelRefreshPromise;
    const cancelledCard = page.locator(`#operation-view-modal.is-open [data-operation-card][data-operation-id="${opId}"][data-status="CANCELLED"]`);
    await cancelledCard.waitFor({ state: 'visible', timeout: 10000 });
    evidence.cancelResponse = cancelPayload;
    await page.screenshot({ path: 'P105_screens/operation_cancelled.png', fullPage: true });

    response = await page.goto(B + 'company/finance/cash', { waitUntil: 'networkidle', timeout: 30000 });
    ok(response && response.status() === 200, 'cash page after cancellation unavailable');
    ok(!fatal.test(await page.locator('body').innerText()), 'fatal on cash page after cancellation');
    let stillPresent = 0;
    const unresolvedAfter = page.locator('tr.cash-row-unresolved');
    for (let i = 0; i < await unresolvedAfter.count(); i++) {
      const t = norm(await unresolvedAfter.nth(i).innerText());
      if (t.includes('10.08.2026') && t.includes('Основная касса') && t.includes('ПОТОК') && t.includes('счёт Н') && /300\s*000,00/.test(t)) stillPresent++;
    }
    ok(stillPresent === 0, 'target cash row still present');
    evidence.matchCountAfter = stillPresent;
    const cashAccountRowAfter = page.locator('.table-card table tbody tr').filter({ hasText: 'Основная касса' }).filter({ hasText: 'RUR' }).first();
    evidence.afterCashBalance = await cashAccountRowAfter.locator('td').nth(3).innerText();
    evidence.result = 'cancelled';
    await page.screenshot({ path: 'P105_screens/cash_after.png', fullPage: true });

    ok(errors.length === 0, 'page errors: ' + JSON.stringify(errors));
    fs.writeFileSync('P105_RESULT.json', JSON.stringify({ ok: true, evidence, errors }, null, 2));
    console.log(`P105_CANCELLED_OPERATION_ID=${opId}`);
    console.log(`P105_CANCELLED_ALLOCATIONS=${allocationEvidence.length}`);
    console.log(`P105_BEFORE_CASH_BALANCE=${evidence.beforeCashBalance}`);
    console.log(`P105_AFTER_CASH_BALANCE=${evidence.afterCashBalance}`);
    console.log('P105_CANCEL_ACCIDENTAL_CASH_OK');
  } finally {
    await browser.close();
  }
})().catch(error => {
  fs.writeFileSync('P105_RESULT.json', JSON.stringify({ ok: false, error: error.message, stack: error.stack }, null, 2));
  console.error(error);
  process.exit(1);
});
