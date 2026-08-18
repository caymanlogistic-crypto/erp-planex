'use strict';

const fs = require('fs');
const { chromium } = require('playwright');

const B = 'https://plan-ex.ru/erpv2/';
const ok = (v, m) => { if (!v) throw new Error(m); };
const norm = s => String(s || '').replace(/\s+/g, ' ').trim();
const fatal = /(Fatal error|Parse error|Uncaught (?:TypeError|Error|Exception)|Class ".+" not found)/i;
const money = s => Number(String(s || '').replace(/\s+/g, '').replace(',', '.').replace(/[^0-9.-]/g, ''));

async function login(page) {
  const credentials = JSON.parse(Buffer.from(process.env.P12_CREDENTIALS_B64 || '', 'base64').toString('utf8'));
  const owner = credentials.find(x => String(x.role).toUpperCase() === 'OWNER');
  ok(owner, 'OWNER credentials');
  await page.goto(B + 'login', { waitUntil: 'domcontentloaded', timeout: 30000 });
  await page.locator('input[name="username"],input[name="email"],input[type="text"]').first().fill(owner.username);
  await page.locator('input[type="password"]').fill(owner.password);
  await page.locator('button[type="submit"],input[type="submit"]').first().click();
  await page.waitForTimeout(700);
  ok(!page.url().includes('/login'), 'login failed');
}

async function getCashState(page) {
  const response = await page.goto(B + 'company/finance/cash', { waitUntil: 'networkidle', timeout: 30000 });
  ok(response && response.status() === 200, 'cash page unavailable');
  ok(!fatal.test(await page.locator('body').innerText()), 'fatal on cash page');
  const accountRow = page.locator('.table-card table tbody tr').filter({ hasText: 'Основная касса' }).filter({ hasText: 'RUR' }).first();
  const balanceText = await accountRow.locator('td').nth(3).innerText();
  const unresolved = page.locator('tr.cash-row-unresolved');
  const matches = [];
  for (let i = 0; i < await unresolved.count(); i++) {
    const row = unresolved.nth(i);
    const text = norm(await row.innerText());
    if (text.includes('10.08.2026') && text.includes('Основная касса') && text.includes('ПОТОК') && text.includes('счёт Н') && /300\s*000,00/.test(text)) {
      matches.push({ row, text });
    }
  }
  return { balanceText, balance: money(balanceText), matches };
}

async function openOperation(page, opId) {
  const response = await page.goto(B + 'company/finance/operations?search=' + encodeURIComponent('ПОТОК'), { waitUntil: 'networkidle', timeout: 30000 });
  ok(response && response.status() === 200, 'operations page unavailable');
  ok(!fatal.test(await page.locator('body').innerText()), 'fatal on operations page');
  const row = page.locator(`tr[data-operation-id="${opId}"]`);
  ok(await row.count() === 1, `operation #${opId} not found`);
  const rowText = norm(await row.innerText());
  ok(rowText.includes('10.08.2026') && rowText.includes('Основная касса') && rowText.includes('ПОТОК') && /300\s*000,00/.test(rowText), 'operation identity mismatch');
  await row.dblclick();
  const card = page.locator(`#operation-view-modal.is-open [data-operation-card][data-operation-id="${opId}"]`);
  await card.waitFor({ state: 'visible', timeout: 10000 });
  return { rowText, card };
}

async function directCancelAllocation(form) {
  return await form.evaluate(async (f) => {
    const reason = f.querySelector('input[name="reason"]');
    if (reason) reason.value = 'Отмена распределения ошибочной кассовой операции по указанию владельца 18.08.2026.';
    const response = await fetch(f.action, {
      method: 'POST',
      body: new FormData(f),
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    });
    return { status: response.status, text: await response.text() };
  });
}

async function directCancelOperation(page, button) {
  await button.click();
  const modal = page.locator('#operation-cancel-modal.is-open');
  await modal.waitFor({ state: 'visible', timeout: 10000 });
  await modal.locator('textarea[name="reason"]').fill('Ошибочно созданная кассовая операция. Отмена по указанию владельца 18.08.2026.');
  await page.screenshot({ path: 'P105_screens/cancel_confirmation.png', fullPage: true });
  const form = modal.locator('form.finance-cancel-form');
  return await form.evaluate(async (f) => {
    const response = await fetch(f.action, {
      method: 'POST',
      body: new FormData(f),
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    });
    return { status: response.status, text: await response.text() };
  });
}

(async () => {
  fs.mkdirSync('P105_screens', { recursive: true });
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({ viewport: { width: 1920, height: 1080 }, deviceScaleFactor: 1, locale: 'ru-RU', timezoneId: 'Europe/Moscow' });
  const page = await context.newPage();
  const pageErrors = [];
  page.on('pageerror', e => pageErrors.push(e.message));
  const evidence = { expectedOperationId: 159, expectedAmount: 300000, targetDate: '10.08.2026' };

  try {
    await login(page);
    let cash = await getCashState(page);
    evidence.beforeCashBalance = cash.balanceText;
    evidence.matchCountBefore = cash.matches.length;
    await page.screenshot({ path: 'P105_screens/cash_before_finalize.png', fullPage: true });

    if (cash.matches.length === 0) {
      const opened = await openOperation(page, 159);
      evidence.operationRowAfterPriorAttempt = opened.rowText;
      evidence.operationStatus = await opened.card.getAttribute('data-status');
      ok(evidence.operationStatus === 'CANCELLED', `target row absent but operation #159 status is ${evidence.operationStatus}`);
      evidence.result = 'already_cancelled';
      evidence.afterCashBalance = cash.balanceText;
      fs.writeFileSync('P105_RESULT.json', JSON.stringify({ ok: true, evidence, pageErrors }, null, 2));
      console.log('P105_OPERATION_ALREADY_CANCELLED');
      console.log(`P105_AFTER_CASH_BALANCE=${cash.balanceText}`);
      console.log('P105_CANCEL_ACCIDENTAL_CASH_OK');
      return;
    }

    ok(cash.matches.length === 1, `expected exactly one target row, got ${cash.matches.length}`);
    const opId = Number(await cash.matches[0].row.locator('input.cash-source-select').getAttribute('value'));
    ok(opId === 159, `safety stop: expected operation 159, got ${opId}`);
    evidence.operationId = opId;
    evidence.cashRowBefore = cash.matches[0].text;

    let opened = await openOperation(page, opId);
    evidence.operationRowBefore = opened.rowText;
    let status = await opened.card.getAttribute('data-status');
    ok(status === 'POSTED', `expected POSTED operation, got ${status}`);

    evidence.cancelledAllocations = [];
    for (let guard = 0; guard < 10; guard++) {
      const forms = opened.card.locator('form[data-allocation-cancel-form]');
      if (await forms.count() === 0) break;
      const form = forms.first();
      const rowText = norm(await form.locator('xpath=ancestor::tr[1]').innerText());
      const action = await form.getAttribute('action');
      ok(action && /\/company\/finance\/operations\/allocations\/\d+\/cancel$/.test(action), `unsafe allocation action ${action}`);
      const result = await directCancelAllocation(form);
      let payload;
      try { payload = JSON.parse(result.text); } catch (_) { payload = null; }
      ok(result.status === 200 && payload && payload.ok === true, `allocation cancel failed HTTP ${result.status}: ${result.text}`);
      evidence.cancelledAllocations.push({ action, rowText, payload });
      opened = await openOperation(page, opId);
      status = await opened.card.getAttribute('data-status');
      ok(status === 'POSTED', `operation status changed unexpectedly to ${status}`);
    }

    const allocationsLeft = await opened.card.locator('form[data-allocation-cancel-form]').count();
    ok(allocationsLeft === 0, `active allocations still present: ${allocationsLeft}`);
    evidence.allocatedBeforeOperationCancel = await opened.card.getAttribute('data-allocated');
    evidence.remainingBeforeOperationCancel = await opened.card.getAttribute('data-remaining');
    ok(Number(evidence.allocatedBeforeOperationCancel) === 0, 'allocated amount is not zero before operation cancellation');

    const cancelButton = page.locator(`#operation-view-modal.is-open [data-operation-cancel-btn][data-operation-id="${opId}"]`);
    ok(await cancelButton.count() === 1, 'cancel button missing');
    ok(!(await cancelButton.isDisabled()), 'cancel button disabled after allocation unwind');
    const cancel = await directCancelOperation(page, cancelButton);
    let cancelPayload;
    try { cancelPayload = JSON.parse(cancel.text); } catch (_) { cancelPayload = null; }
    ok(cancel.status === 200 && cancelPayload && cancelPayload.ok === true, `operation cancel failed HTTP ${cancel.status}: ${cancel.text}`);
    evidence.operationCancel = cancelPayload;

    await page.waitForTimeout(300);
    cash = await getCashState(page);
    evidence.matchCountAfter = cash.matches.length;
    evidence.afterCashBalance = cash.balanceText;
    ok(cash.matches.length === 0, 'accidental cash row remains after cancellation');
    await page.screenshot({ path: 'P105_screens/cash_after.png', fullPage: true });

    opened = await openOperation(page, opId);
    evidence.operationStatusAfter = await opened.card.getAttribute('data-status');
    ok(evidence.operationStatusAfter === 'CANCELLED', `operation status after cancellation is ${evidence.operationStatusAfter}`);
    await page.screenshot({ path: 'P105_screens/operation_cancelled.png', fullPage: true });

    evidence.result = 'cancelled';
    fs.writeFileSync('P105_RESULT.json', JSON.stringify({ ok: true, evidence, pageErrors }, null, 2));
    console.log(`P105_CANCELLED_OPERATION_ID=${opId}`);
    console.log(`P105_CANCELLED_ALLOCATIONS_THIS_RUN=${evidence.cancelledAllocations.length}`);
    console.log(`P105_BEFORE_CASH_BALANCE=${evidence.beforeCashBalance}`);
    console.log(`P105_AFTER_CASH_BALANCE=${evidence.afterCashBalance}`);
    console.log('P105_CANCEL_ACCIDENTAL_CASH_OK');
  } catch (error) {
    fs.writeFileSync('P105_RESULT.json', JSON.stringify({ ok: false, evidence, error: error.message, stack: error.stack, pageErrors }, null, 2));
    throw error;
  } finally {
    await browser.close();
  }
})().catch(error => {
  console.error(error);
  process.exit(1);
});
