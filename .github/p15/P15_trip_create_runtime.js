'use strict';

const fs = require('fs');
const path = require('path');
const { chromium } = require('playwright');

const BASE = 'https://plan-ex.ru/erpv2/';
const U = (value) => new URL(value, BASE).href;
const OUT = path.resolve(process.env.P15_OUT || 'P15_trip_runtime_output');
const SHOTS = path.join(OUT, 'P15_screenshots');
const COMPANY_ID = 27;
const OWNER = { id: 16, username: 'p14_owner_260804221827' };
const TRIP_NAME = 'P15 UI рейс полный жизненный цикл';
fs.mkdirSync(SHOTS, { recursive: true });

function credentials() {
  const encoded = process.env.P15_CREDENTIALS_B64 || '';
  return encoded ? JSON.parse(Buffer.from(encoded, 'base64').toString('utf8')) : [];
}
function superadmin() {
  return credentials().find((item) => String(item?.role || '').toUpperCase() === 'SUPERADMIN');
}
async function login(browser, username, password) {
  const context = await browser.newContext({
    viewport: { width: 1920, height: 1080 }, deviceScaleFactor: 1,
    locale: 'ru-RU', timezoneId: 'Europe/Moscow'
  });
  const page = await context.newPage();
  let ok = false;
  try {
    await page.goto(U('login'), { waitUntil: 'domcontentloaded', timeout: 45000 });
    await page.locator('input[name="username"],input[name="email"],input[type="text"]').first().fill(username);
    await page.locator('input[type="password"]').first().fill(password);
    await Promise.all([
      page.waitForLoadState('domcontentloaded', { timeout: 20000 }).catch(() => {}),
      page.locator('button[type="submit"],input[type="submit"]').first().click()
    ]);
    await page.waitForTimeout(500);
    ok = !/\/login(?:[/?#]|$)/i.test(page.url());
  } catch {}
  return { context, page, ok };
}
function candidates(text, values) {
  const found = [...values];
  for (const match of String(text || '').matchAll(/[A-Za-z0-9!@#$%^&*()_+\-=]{8,64}/g)) found.push(match[0]);
  return [...new Set(found)].filter((value) => value !== OWNER.username && !value.includes('@example.test') && !/^[a-f0-9]{64}$/i.test(value));
}
async function ownerSession(browser) {
  const credential = superadmin();
  if (!credential) throw new Error('superadmin credential unavailable');
  const sx = await login(browser, credential.username, credential.password);
  if (!sx.ok) throw new Error('superadmin login failed');
  await sx.page.goto(U(`superadmin/companies/${COMPANY_ID}/users`), { waitUntil: 'domcontentloaded', timeout: 45000 });
  const form = sx.page.locator(`form[action$="/superadmin/companies/${COMPANY_ID}/owner/reset-password"]`).first();
  if (!(await form.count())) throw new Error('owner reset form unavailable');
  await Promise.all([
    sx.page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 30000 }),
    form.evaluate((node) => HTMLFormElement.prototype.submit.call(node))
  ]);
  const values = await sx.page.locator('input,textarea,code,pre,[data-password]').evaluateAll((nodes) => nodes.map((node) => node.value || node.getAttribute('data-password') || node.textContent || '').filter(Boolean));
  const list = candidates(await sx.page.locator('body').innerText(), values);
  await sx.context.close();
  for (const candidate of list) {
    const session = await login(browser, OWNER.username, candidate);
    if (session.ok) {
      for (let index = 0; index < list.length; index++) list[index] = '';
      return session;
    }
    await session.context.close();
  }
  throw new Error('owner login verification failed');
}
async function setField(form, name, value) {
  const field = form.locator(`[name="${name}"]`).first();
  if (!(await field.count())) return false;
  const tag = await field.evaluate((node) => node.tagName);
  if (tag === 'SELECT') {
    await field.evaluate((node, expected) => {
      const option = [...node.options].find((item) => String(item.value) === String(expected));
      if (!option) return;
      node.value = option.value;
      node.dispatchEvent(new Event('input', { bubbles: true }));
      node.dispatchEvent(new Event('change', { bubbles: true }));
    }, String(value));
  } else {
    await field.fill(String(value));
  }
  return true;
}
function attachDiagnostics(page, result) {
  page.on('console', (message) => { if (message.type() === 'error') result.consoleErrors.push(message.text()); });
  page.on('pageerror', (error) => result.pageErrors.push(String(error?.message || error)));
  page.on('requestfailed', (request) => result.requestFailures.push({ method: request.method(), url: request.url(), error: request.failure()?.errorText || '' }));
  page.on('response', (response) => { if (response.status() >= 400) result.httpFailures.push({ status: response.status(), url: response.url() }); });
}

(async () => {
  const result = {
    status: 'FAIL', deployedSha: '8c7571c01bcd87f42c4f2e9300fc52a2499935fe',
    tripName: TRIP_NAME, tripId: null, createResponse: null, finalUrl: null,
    invalidFields: [], messages: [], sqlLeak: false, domInventory: {},
    consoleErrors: [], pageErrors: [], requestFailures: [], httpFailures: [], errorClass: null
  };
  let browser;
  let owner;
  try {
    browser = await chromium.launch({ headless: true });
    owner = await ownerSession(browser);
    const page = owner.page;
    attachDiagnostics(page, result);
    await page.goto(U('company/trips/linear'), { waitUntil: 'domcontentloaded', timeout: 45000 });
    let row = page.locator('[data-linear-route-id]').filter({ hasText: TRIP_NAME }).first();
    if (!(await row.count())) {
      await page.goto(U('company/trips/linear?show_create=1'), { waitUntil: 'domcontentloaded', timeout: 45000 });
      const form = page.locator('#linear-trip-create-form');
      await setField(form, 'route_type', 'linear');
      await setField(form, 'planned_loading_date', '10.08.2026');
      await setField(form, 'client_id', '1');
      await setField(form, 'carrier_contractor_id', '1');
      await setField(form, 'route_executor_id', '1');
      await setField(form, 'cargo_type_name', TRIP_NAME);
      await setField(form, 'customer_payments[0][amount]', '155000');
      await setField(form, 'customer_payments[0][payment_method]', 'cashless');
      await setField(form, 'customer_payments[0][vat_rate]', '');
      await setField(form, 'customer_payments[0][condition_type]', 'prepayment');
      await setField(form, 'carrier_payments[0][amount]', '99000');
      await setField(form, 'carrier_payments[0][payment_method]', 'cashless');
      await setField(form, 'carrier_payments[0][vat_rate]', '');
      await setField(form, 'carrier_payments[0][condition_type]', 'after_end');
      await setField(form, 'carrier_payments[0][days_count]', '5');
      await setField(form, 'carrier_payments[0][days_kind]', 'working');
      await setField(form, 'comments', 'P15 изолированный рейс: создание, редактирование, статусы и документы');
      result.invalidFields = await form.evaluate((node) => [...node.elements].filter((item) => typeof item.checkValidity === 'function' && !item.checkValidity()).map((item) => ({ name: item.name, message: item.validationMessage })));
      const responses = [];
      const responseHandler = async (response) => {
        if (response.url().includes('/company/trips/linear/create')) {
          let body = '';
          try { body = await response.text(); } catch {}
          responses.push({ status: response.status(), url: response.url(), body: body.slice(0, 3000) });
        }
      };
      page.on('response', responseHandler);
      await page.screenshot({ path: path.join(SHOTS, 'P15_001_OWNER_trip_create_filled_1920x1080.png'), fullPage: false });
      const button = page.locator('button[type="submit"][form="linear-trip-create-form"],#linear-trip-create-form button[type="submit"],button').filter({ hasText: /Создать рейс|Сохранить/i }).first();
      if (await button.count()) await button.click(); else await form.evaluate((node) => node.requestSubmit());
      await page.waitForTimeout(2500);
      page.off('response', responseHandler);
      result.createResponse = responses.at(-1) || null;
      result.finalUrl = page.url();
      result.messages = await page.locator('.field-msg,.notice,.alert,.error,.invalid-feedback').evaluateAll((nodes) => nodes.map((node) => (node.innerText || '').trim()).filter(Boolean));
      const bodyText = await page.locator('body').innerText();
      result.sqlLeak = /SQLSTATE|Integrity constraint|payment_due_type|stack trace/i.test(bodyText);
      await page.screenshot({ path: path.join(SHOTS, 'P15_002_OWNER_trip_create_result_1920x1080.png'), fullPage: false });
    }

    await page.goto(U('company/trips/linear'), { waitUntil: 'domcontentloaded', timeout: 45000 });
    row = page.locator('[data-linear-route-id]').filter({ hasText: TRIP_NAME }).first();
    if (!(await row.count())) throw new Error('created trip unavailable in list');
    result.tripId = Number(await row.getAttribute('data-linear-route-id'));
    await row.scrollIntoViewIfNeeded();
    await page.screenshot({ path: path.join(SHOTS, 'P15_003_OWNER_trip_list_created_1920x1080.png'), fullPage: false });
    result.domInventory = await row.evaluate((node) => ({
      text: (node.innerText || '').replace(/\s+/g, ' ').trim(),
      links: [...node.querySelectorAll('a[href]')].map((item) => ({ text: (item.textContent || '').trim(), href: item.getAttribute('href') })),
      buttons: [...node.querySelectorAll('button')].map((item) => ({ text: (item.textContent || '').trim(), type: item.type, dataset: { ...item.dataset } })),
      forms: [...node.querySelectorAll('form')].map((item) => ({ action: item.getAttribute('action'), method: item.method, text: (item.innerText || '').trim() }))
    }));
    result.status = result.tripId > 0 && !result.sqlLeak && result.invalidFields.length === 0 ? 'PASS' : 'FAIL';
  } catch (error) {
    result.errorClass = error?.name || 'Error';
    result.errorMessage = String(error?.message || error).slice(0, 500);
  } finally {
    if (owner?.context) await owner.context.close().catch(() => {});
    if (browser) await browser.close().catch(() => {});
    fs.writeFileSync(path.join(OUT, 'P15_TRIP_CREATE_RUNTIME.json'), JSON.stringify(result, null, 2), 'utf8');
    fs.writeFileSync(path.join(OUT, 'P15_TRIP_CREATE_STATUS.txt'), `P15_TRIP_CREATE=${result.status}\nTRIP_ID=${result.tripId || ''}\nSQL_LEAK=${result.sqlLeak}\n`, 'utf8');
    console.log(`P15_TRIP_CREATE=${result.status}`);
    if (result.status !== 'PASS') process.exitCode = 1;
  }
})().catch(() => { console.log('P15_TRIP_CREATE=FAIL'); process.exitCode = 1; });
