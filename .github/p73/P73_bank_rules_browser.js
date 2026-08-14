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
    await p.waitForTimeout(650);
    ok(!p.url().includes('/login'), 'login failed');

    let response = await p.goto(B + 'company/finance/bank-accounts', { waitUntil: 'domcontentloaded' });
    ok(response && response.status() === 200, 'bank page HTTP ' + (response && response.status()));
    ok((await p.locator('h1.page-title').first().innerText()).trim() === 'Выписки со счёта', 'bank title mismatch');

    const bankSummary = p.locator('.bank-transactions-card > .bank-controls').first();
    ok(await bankSummary.count() === 1, 'bank summary bar missing');
    const summaryBg = await bankSummary.evaluate(el => getComputedStyle(el).backgroundColor);
    ok(summaryBg === 'rgb(232, 228, 219)', 'bank summary must be light gray; got ' + summaryBg);

    const bankNav = p.locator('a.nav-item', { hasText: 'Выписки со счёта' }).first();
    ok(await bankNav.count() === 1, 'bank navigation item missing');
    const alertBadge = bankNav.locator('.nav-count.is-alert');
    ok(await alertBadge.count() === 1, 'bank attention badge missing');
    const attentionText = (await alertBadge.innerText()).trim();
    ok(/^\d+$|^999\+$/.test(attentionText), 'invalid bank attention badge text: ' + attentionText);
    if (attentionText !== '999+') ok(Number(attentionText) > 0, 'bank attention badge must be positive');
    const alertBg = await alertBadge.evaluate(el => getComputedStyle(el).backgroundColor);
    ok(alertBg !== 'rgba(0, 0, 0, 0)', 'alert badge background missing');
    ok(!(await p.locator('text=/Строки:\s*\d+/').count()), 'obsolete row counter is visible');
    console.log('P73_BANK_SUMMARY_BG=' + summaryBg);
    console.log('P73_BANK_ATTENTION=' + attentionText);
    console.log('P73_BANK_ALERT_BG=' + alertBg);
    await p.screenshot({ path: 'P73_bank_accounts.png', fullPage: true });

    response = await p.goto(B + 'company/finance/settings/matching-rules', { waitUntil: 'domcontentloaded' });
    ok(response && response.status() === 200, 'rules page HTTP ' + (response && response.status()));
    const table = p.locator('#rules-list');
    ok(await table.count() === 1, 'rules table missing');
    const headers = (await table.locator('thead th').allTextContents()).map(x => x.trim());
    ok(JSON.stringify(headers) === JSON.stringify(['ЦФУ','Статья','ИНН','Условие','Приоритет']), 'unexpected headers: ' + JSON.stringify(headers));
    ok(await table.locator('tbody button, tbody a, tbody form').count() === 0, 'row action controls must be removed');
    const tableText = await table.innerText();
    ok(!tableText.includes('ручное правило'), 'internal rule name is still visible');
    ok(!tableText.includes('Автоматическое применение'), 'automatic-application technical label is still visible');
    ok(await p.locator('.ux-kpi-grid').count() === 0, 'obsolete KPI strip still visible');

    const firstRow = table.locator('tbody tr[data-edit-id]').first();
    ok(await firstRow.count() === 1, 'no rule row available for edit acceptance');
    await firstRow.dblclick();
    const modal = p.locator('#matching-rule-edit-modal.is-open');
    await modal.waitFor({ state: 'visible', timeout: 10000 });
    const form = modal.locator('form[data-matching-rule-form]');
    await form.waitFor({ state: 'visible', timeout: 10000 });

    const modalText = await modal.innerText();
    ok(!modalText.includes('Применять автоматически'), 'auto-apply checkbox is still visible');
    ok(await form.locator('input[name="auto_apply"][type="hidden"][value="1"]').count() === 1, 'automatic semantics hidden value missing');

    const remoteDisplay = await modal.locator('.rule-remote-form').evaluate(el => getComputedStyle(el).display);
    ok(remoteDisplay === 'contents', 'remote form wrapper must not create white modal body: ' + remoteDisplay);
    ok(await modal.locator('.rule-remote-form > form.matching-rule-form > .modal-body').count() === 1, 'modal body hierarchy invalid');
    ok(await modal.locator('.rule-remote-form > form.matching-rule-form > .modal-foot').count() === 1, 'modal footer hierarchy invalid');

    const leftButtons = (await modal.locator('.matching-rule-foot-left .btn').allTextContents()).map(x => x.trim());
    ok(leftButtons.length === 3, 'expected 3 left edit actions: ' + JSON.stringify(leftButtons));
    ok(leftButtons[0] === 'Проверить', 'first left action must be Проверить');
    ok(leftButtons[1] === 'Отключить' || leftButtons[1] === 'Включить', 'second left action must be Отключить/Включить');
    ok(leftButtons[2] === 'Удалить', 'third left action must be Удалить');
    const rightButtons = (await modal.locator('.matching-rule-foot-right .btn').allTextContents()).map(x => x.trim());
    ok(JSON.stringify(rightButtons) === JSON.stringify(['Сохранить','Отмена']), 'right actions order mismatch: ' + JSON.stringify(rightButtons));
    const allEditButtons = modal.locator('.matching-rule-foot .btn');
    for (let i = 0; i < await allEditButtons.count(); i++) {
      const cls = await allEditButtons.nth(i).getAttribute('class');
      ok((cls || '').includes('btn-ghost'), 'edit footer action is not ghost styled: ' + cls);
    }
    const footerMargin = await modal.locator('.matching-rule-form > .modal-foot').evaluate(el => getComputedStyle(el).margin);
    ok(footerMargin === '0px', 'modal footer has unwanted white margin: ' + footerMargin);

    console.log('P73_RULE_HEADERS=' + JSON.stringify(headers));
    console.log('P73_LEFT_ACTIONS=' + JSON.stringify(leftButtons));
    console.log('P73_RIGHT_ACTIONS=' + JSON.stringify(rightButtons));
    console.log('P73_REMOTE_DISPLAY=' + remoteDisplay);
    console.log('P73_FOOTER_MARGIN=' + footerMargin);
    await p.screenshot({ path: 'P73_matching_rule_edit.png', fullPage: true });

    ok(errors.length === 0, 'browser errors: ' + JSON.stringify(errors));
    console.log('P73_ERRORS=' + JSON.stringify(errors));
    console.log('P73_OK');
  } finally {
    await browser.close();
  }
})().catch(e => { console.error(e); process.exit(1); });
