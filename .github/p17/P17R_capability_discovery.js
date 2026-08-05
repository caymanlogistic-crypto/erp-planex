'use strict';

const fs = require('fs');
const path = require('path');
const { chromium } = require('playwright');

const BASE = 'https://plan-ex.ru/erpv2/';
const U = (value) => new URL(String(value).replace(/^\//, ''), BASE).href;
const OUT = path.resolve(process.env.P17R_OUT || 'P17R_capability_discovery_output');
const SHOTS = path.join(OUT, 'P17R_screenshots');
fs.mkdirSync(SHOTS, { recursive: true });
const COMPANY = 27;
const OWNER = { id: 16, username: 'p14_owner_260804221827' };

function credentials() {
  const raw = process.env.P17R_CREDENTIALS_B64 || '';
  return raw ? JSON.parse(Buffer.from(raw, 'base64').toString('utf8')) : [];
}
function adminCredential() {
  return credentials().find((item) => String(item?.role || '').toUpperCase() === 'SUPERADMIN');
}
async function login(browser, username, password) {
  const context = await browser.newContext({ viewport: { width: 1920, height: 1080 }, deviceScaleFactor: 1, locale: 'ru-RU', timezoneId: 'Europe/Moscow', ignoreHTTPSErrors: true });
  const page = await context.newPage();
  await page.goto(U('login'), { waitUntil: 'domcontentloaded', timeout: 45000 });
  await page.locator('input[name="username"],input[name="email"],input[type="text"]').first().fill(username);
  await page.locator('input[type="password"]').first().fill(password);
  await Promise.all([
    page.waitForLoadState('domcontentloaded', { timeout: 20000 }).catch(() => {}),
    page.locator('button[type="submit"],input[type="submit"]').first().click(),
  ]);
  await page.waitForTimeout(450);
  return { context, page, ok: !/\/login(?:[/?#]|$)/i.test(page.url()) };
}
function passwordCandidates(text, values, username) {
  const all = values.map(String);
  for (const match of String(text).matchAll(/[A-Za-z0-9!@#$%^&*()_+\-=]{8,64}/g)) all.push(match[0]);
  return [...new Set(all)].filter((value) => value !== username && !value.includes('@example.test') && !/^[a-f0-9]{64}$/i.test(value));
}
async function ownerSession(browser) {
  const admin = adminCredential();
  if (!admin) throw new Error('SUPERADMIN credential unavailable');
  const sx = await login(browser, admin.username, admin.password);
  if (!sx.ok) throw new Error('SUPERADMIN login failed');
  await sx.page.goto(U(`superadmin/companies/${COMPANY}/users`), { waitUntil: 'domcontentloaded', timeout: 45000 });
  const reset = sx.page.locator(`form[action$="/superadmin/companies/${COMPANY}/owner/reset-password"]`).first();
  if (!(await reset.count())) throw new Error('OWNER reset form unavailable');
  await Promise.all([
    sx.page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 30000 }),
    reset.evaluate((node) => HTMLFormElement.prototype.submit.call(node)),
  ]);
  const values = await sx.page.locator('input,textarea,code,pre,[data-password]').evaluateAll((nodes) => nodes.map((node) => node.value || node.getAttribute('data-password') || node.textContent || '').filter(Boolean));
  const list = passwordCandidates(await sx.page.locator('body').innerText(), values, OWNER.username);
  await sx.context.close();
  for (const candidate of list) {
    const session = await login(browser, OWNER.username, candidate);
    if (session.ok) { list.fill(''); return session; }
    await session.context.close();
  }
  throw new Error('OWNER login verification failed');
}
async function inventory(page, id, extra = {}) {
  const data = await page.evaluate(() => {
    const isVisible = (node) => {
      const style = getComputedStyle(node);
      const rect = node.getBoundingClientRect();
      return style.display !== 'none' && style.visibility !== 'hidden' && rect.width > 0 && rect.height > 0;
    };
    return {
      title: document.title,
      url: location.href,
      heading: document.querySelector('h1,h2,.page-title')?.textContent?.trim() || '',
      bodyText: document.body.innerText.replace(/\s+/g, ' ').slice(0, 10000),
      modals: [...document.querySelectorAll('.modal,[role="dialog"],dialog')].map((node) => ({ id: node.id || null, className: node.className, visible: isVisible(node), text: (node.innerText || '').replace(/\s+/g, ' ').slice(0, 3000) })),
      forms: [...document.forms].map((form) => ({
        id: form.id || null,
        action: form.action || null,
        method: form.method,
        visible: isVisible(form),
        fields: [...form.elements].filter((field) => field.name).map((field) => ({ name: field.name, type: field.type, value: field.type === 'password' ? '[REDACTED]' : String(field.value || '').slice(0, 200), required: Boolean(field.required), disabled: Boolean(field.disabled), visible: isVisible(field), options: field.tagName === 'SELECT' ? [...field.options].map((option) => ({ value: option.value, text: option.textContent.trim() })).slice(0, 80) : undefined })),
        buttons: [...form.querySelectorAll('button,input[type="submit"]')].map((button) => ({ text: (button.innerText || button.value || '').trim(), type: button.type, visible: isVisible(button), form: button.getAttribute('form') }))
      })),
      controls: [...document.querySelectorAll('button,a[href]')].filter(isVisible).map((node) => ({ tag: node.tagName, text: (node.innerText || '').trim(), href: node.getAttribute('href'), type: node.type || null, id: node.id || null, className: node.className, onclick: node.getAttribute('onclick'), dataset: { ...node.dataset } })).filter((item) => item.text || item.href),
      rows: [...document.querySelectorAll('tr,[data-client-id],[data-contractor-id],[data-driver-id],[data-vehicle-set-id],[data-route-executor-id],[data-linear-route-id]')].filter(isVisible).slice(0, 30).map((node) => ({ tag: node.tagName, text: (node.innerText || '').replace(/\s+/g, ' ').slice(0, 1000), attrs: Object.fromEntries([...node.attributes].map((item) => [item.name, item.value])) }))
    };
  });
  return { id, ...extra, ...data };
}
async function discoverListAndDetail(page, item, output, sequence) {
  await page.goto(U(item.list), { waitUntil: 'domcontentloaded', timeout: 45000 });
  await page.waitForTimeout(300);
  output.pages.push(await inventory(page, `${item.id}_list`, { entity: item.id }));
  await page.screenshot({ path: path.join(SHOTS, `P17R_${String(sequence).padStart(3, '0')}_${item.id}_list.png`), fullPage: false });
  const row = page.locator(item.rowSelector).filter({ hasText: item.label }).first();
  if (await row.count()) {
    const rowData = await row.evaluate((node) => ({ attrs: Object.fromEntries([...node.attributes].map((a) => [a.name, a.value])), text: (node.innerText || '').replace(/\s+/g, ' ') }));
    output.entities[item.id] = rowData;
    const idValue = Object.entries(rowData.attrs).find(([name]) => /data-.*-id$/.test(name))?.[1] || item.fallbackId || null;
    if (item.detail && idValue) {
      await page.goto(U(item.detail.replace('{id}', idValue)), { waitUntil: 'domcontentloaded', timeout: 45000 });
      await page.waitForTimeout(300);
      output.pages.push(await inventory(page, `${item.id}_detail`, { entity: item.id, entityId: idValue }));
      await page.screenshot({ path: path.join(SHOTS, `P17R_${String(sequence + 1).padStart(3, '0')}_${item.id}_detail.png`), fullPage: false });
      const edit = page.getByText(/Редактировать/i).first();
      if (await edit.count()) {
        await edit.click();
        await page.waitForTimeout(400);
        output.pages.push(await inventory(page, `${item.id}_edit_open`, { entity: item.id, entityId: idValue }));
      }
    }
  }
}

(async () => {
  const result = { status: 'FAIL', deployedSha: process.env.P17_DEPLOYED_SHA || null, pages: [], entities: {}, cash: {}, consoleErrors: [], pageErrors: [], requestFailures: [], httpErrors: [], error: null };
  let browser;
  let owner;
  try {
    browser = await chromium.launch({ headless: true });
    owner = await ownerSession(browser);
    const page = owner.page;
    page.on('console', (message) => { if (message.type() === 'error') result.consoleErrors.push({ url: page.url(), message: message.text() }); });
    page.on('pageerror', (error) => result.pageErrors.push({ url: page.url(), message: String(error.message || error) }));
    page.on('requestfailed', (request) => result.requestFailures.push({ url: request.url(), method: request.method(), error: request.failure()?.errorText || '' }));
    page.on('response', (response) => { if (response.status() >= 500) result.httpErrors.push({ url: response.url(), status: response.status() }); });

    const items = [
      { id: 'client', list: 'company/clients', rowSelector: '[data-client-id],tbody tr', label: 'P17_CLIENT Финальная приёмка', detail: 'company/clients/{id}', fallbackId: 40 },
      { id: 'contractor', list: 'company/contractors', rowSelector: '[data-contractor-id],tbody tr', label: 'P17_CONTRACTOR Финальный перевозчик', detail: 'company/contractors/{id}', fallbackId: 38 },
      { id: 'driver', list: 'company/drivers', rowSelector: '[data-driver-id],tbody tr', label: 'P17_DRIVER Финальный Водитель', detail: 'company/drivers/{id}', fallbackId: 14 },
      { id: 'vehicle_set', list: 'company/vehicle-sets', rowSelector: '[data-vehicle-set-id],tbody tr', label: 'Р017РТ77', detail: 'company/vehicle-sets/{id}', fallbackId: 6 },
      { id: 'route_executor', list: 'company/route-executors', rowSelector: '[data-route-executor-id],tbody tr', label: 'P17_DRIVER Финальный Водитель', detail: 'company/route-executors/{id}', fallbackId: 6 },
      { id: 'linear_trip', list: 'company/trips/linear', rowSelector: '[data-linear-route-id],tbody tr', label: 'P17_TRIP Финальная приёмка рейса', detail: null, fallbackId: 21 },
    ];
    let sequence = 1;
    for (const item of items) {
      await discoverListAndDetail(page, item, result, sequence);
      sequence += 3;
    }

    await page.goto(U('company/finance/cash'), { waitUntil: 'domcontentloaded', timeout: 45000 });
    result.cash.before = await inventory(page, 'cash_before');
    result.cash.buttons = await page.locator('button:visible,a:visible').evaluateAll((nodes) => nodes.map((node) => (node.innerText || '').trim()).filter(Boolean));
    const exact = page.getByText('Приход/Расход', { exact: true }).first();
    result.cash.exactButtonCount = await exact.count();
    if (result.cash.exactButtonCount) {
      await exact.click();
      await page.waitForTimeout(400);
    }
    result.cash.after = await inventory(page, 'cash_operation_modal');
    result.cash.modalVisible = await page.locator('#cash-operation-create-modal:visible').count();
    result.cash.formCount = await page.locator('form[action="/erpv2/company/finance/cash/operation-create"]:visible').count();
    await page.screenshot({ path: path.join(SHOTS, 'P17R_050_cash_operation_modal.png'), fullPage: false });

    for (const [id, route, buttonText] of [
      ['invoice', 'company/finance/invoices', 'Создать счёт'],
      ['dds', 'company/finance/settings/dds-categories', 'Создать статью'],
      ['matching', 'company/finance/settings/matching-rules', 'Создать правило'],
      ['documents', 'company/documents', null],
      ['operations', 'company/finance/operations', null],
      ['bank_accounts', 'company/finance/bank-accounts', null],
    ]) {
      await page.goto(U(route), { waitUntil: 'domcontentloaded', timeout: 45000 });
      if (buttonText) {
        const target = page.getByText(buttonText, { exact: true }).first();
        if (await target.count()) { await target.click(); await page.waitForTimeout(400); }
      }
      result.pages.push(await inventory(page, `${id}_inventory`));
      await page.screenshot({ path: path.join(SHOTS, `P17R_${String(sequence++).padStart(3, '0')}_${id}.png`), fullPage: false });
    }

    const admin = adminCredential();
    const sa = await login(browser, admin.username, admin.password);
    if (sa.ok) {
      await sa.page.goto(U(`superadmin/deleted-data?company_id=${COMPANY}`), { waitUntil: 'domcontentloaded', timeout: 45000 });
      result.pages.push(await inventory(sa.page, 'superadmin_deleted_data'));
      await sa.page.screenshot({ path: path.join(SHOTS, 'P17R_090_superadmin_deleted_data.png'), fullPage: false });
    }
    await sa.context.close();

    result.status = result.cash.exactButtonCount === 1 && result.cash.modalVisible === 1 && result.cash.formCount === 1 && result.pageErrors.length === 0 && result.requestFailures.length === 0 && result.httpErrors.length === 0 ? 'PASS' : 'FAIL';
  } catch (error) {
    result.error = { name: error?.name || 'Error', message: String(error?.message || error).slice(0, 2000), stack: String(error?.stack || '').split('\n').slice(0, 8) };
  } finally {
    if (owner?.context) await owner.context.close().catch(() => {});
    if (browser) await browser.close().catch(() => {});
    fs.writeFileSync(path.join(OUT, 'P17R_CAPABILITY_DISCOVERY.json'), JSON.stringify(result, null, 2));
    console.log(`P17R_CAPABILITY_DISCOVERY=${result.status}`);
    process.exitCode = result.status === 'PASS' ? 0 : 1;
  }
})();
