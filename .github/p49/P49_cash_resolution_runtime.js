'use strict';

const { chromium } = require('playwright');
const BASE = 'https://plan-ex.ru/erpv2/';
const ok = (value, message) => { if (!value) throw new Error(message); };

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

    const response = await page.goto(BASE + 'company/finance/cash', { waitUntil: 'domcontentloaded' });
    ok(response && response.status() === 200, 'cash page HTTP 200');
    ok((await page.locator('h1').first().innerText()).includes('Касса'), 'cash title missing');
    const bodyText = await page.locator('body').innerText();
    ok(!bodyText.includes('Ошибка при загрузке данных:'), 'cash page database/runtime error');

    const sources = page.locator('.cash-source-select');
    const sourceCount = await sources.count();
    const employeeAction = page.locator('#cash-dispatch-employee-btn');
    const carrierAction = page.locator('#cash-dispatch-carrier-btn');

    if (sourceCount === 0) {
      // Zero unresolved positions is a valid and healthy production state.
      ok(await page.locator('#cash-clearing-alert').count() === 0, 'cash clearing alert shown with zero unresolved rows');
      ok(await page.locator('a.nav-item[href*="/company/finance/cash"] .nav-count.is-alert').count() === 0, 'cash sidebar alert badge shown with zero unresolved rows');
      ok(await page.locator('.cash-technical-status.is-alert').count() === 0, 'Main Cash incorrectly marked as requiring allocation');
      const mainOk = page.locator('.cash-technical-status.is-ok');
      if (await mainOk.count()) ok((await mainOk.first().innerText()).trim() === 'В норме', 'unexpected Main Cash healthy status');
      if (await employeeAction.count()) ok(await employeeAction.isDisabled(), 'employee action must remain disabled with no selectable sources');
      if (await carrierAction.count()) ok(await carrierAction.isDisabled(), 'carrier action must remain disabled');
      await page.screenshot({ path: 'P49_cash_checkbox.png', fullPage: true });
      ok(errors.length === 0, 'browser errors: ' + JSON.stringify(errors));
      console.log('P49_CASH_RESOLUTION_RUNTIME_OK');
      console.log('P49_ZERO_UNRESOLVED_STATE_OK');
      return;
    }

    const selectAll = page.locator('#cash-select-all');
    await selectAll.waitFor({ state: 'visible', timeout: 5000 });
    const firstSource = sources.first();
    const visual = await firstSource.evaluate(el => {
      const row = el.closest('tr');
      const probe = document.createElement('span');
      probe.style.color = 'var(--accent)';
      probe.style.backgroundColor = 'var(--color-danger-bg)';
      document.body.appendChild(probe);
      const probeStyle = getComputedStyle(probe);
      const expectedAccent = probeStyle.color;
      const expectedDangerBg = probeStyle.backgroundColor;
      probe.remove();
      return {
        accentColor: getComputedStyle(el).accentColor,
        expectedAccent,
        rowBackground: row && row.cells.length ? getComputedStyle(row.cells[0]).backgroundColor : '',
        expectedDangerBg,
        rowClass: row ? row.className : '',
      };
    });
    ok(visual.rowClass.includes('cash-row-unresolved'), 'selectable technical cash row lacks unresolved visual class');
    ok(visual.rowBackground === visual.expectedDangerBg, 'unresolved row is not using system light-red danger background: ' + JSON.stringify(visual));
    ok(visual.accentColor === visual.expectedAccent, 'cash checkbox is not using ERP brown accent: ' + JSON.stringify(visual));

    ok(await employeeAction.isDisabled(), 'employee action must start disabled before selection');
    ok(await carrierAction.isDisabled(), 'carrier action must remain disabled for next implementation stage');

    const alertText = (await page.locator('#cash-clearing-alert').innerText()).trim();
    ok(alertText.includes('Требует разнесения'), 'technical cash attention alert missing');
    const alertMatch = alertText.match(/Требует разнесения:\s*(\d+)/i);
    ok(alertMatch, 'unresolved count missing from technical cash alert');
    const unresolvedCount = Number(alertMatch[1]);
    ok(unresolvedCount >= sourceCount, 'unresolved count is lower than selectable rows on page');

    const cashNav = page.locator('a.nav-item[href*="/company/finance/cash"]');
    const navBadge = cashNav.locator('.nav-count.is-alert');
    ok(await navBadge.count() === 1, 'cash sidebar attention badge missing');
    ok(Number((await navBadge.innerText()).trim()) === unresolvedCount, 'cash sidebar badge does not match unresolved count');

    const technicalStatus = page.locator('.cash-technical-status.is-alert');
    ok(await technicalStatus.count() === 1, 'Main Cash must visibly require allocation while unresolved positions exist');

    await firstSource.check();
    ok(!(await employeeAction.isDisabled()), 'employee action did not enable after selecting a source');
    ok((await page.locator('#cash-selected-count').innerText()).trim() === '1', 'selected counter did not update');
    await page.screenshot({ path: 'P49_cash_checkbox.png', fullPage: true });

    await employeeAction.click();
    const modal = page.locator('#cash-dispatch-employee-modal');
    await modal.waitFor({ state: 'visible', timeout: 5000 });
    const employeeSelect = modal.locator('#cash-employee-ref');
    ok(await employeeSelect.count() === 1, 'employee selector missing');
    ok(await employeeSelect.locator('option').count() > 1, 'active employee directory is empty');
    ok(await modal.locator('input[name="source_operation_ids[]"]').count() === 1, 'selected source not copied to confirmation form');
    ok((await modal.locator('#cash-dispatch-modal-total').innerText()).includes('₽'), 'confirmation total missing');

    // Read-only acceptance: never submit the dispatch form.
    await page.locator('[data-close-modal="cash-dispatch-employee-modal"]').first().click();
    ok(errors.length === 0, 'browser errors: ' + JSON.stringify(errors));
    console.log('P49_CASH_RESOLUTION_RUNTIME_OK');
  } finally {
    await browser.close();
  }
})().catch(err => { console.error(err); process.exit(1); });
