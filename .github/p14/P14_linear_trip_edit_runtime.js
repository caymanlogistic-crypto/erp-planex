'use strict';

const fs = require('fs');
const path = require('path');
const { chromium } = require('playwright');

const BASE = (process.env.P14_BASE_URL || 'https://plan-ex.ru/erpv2/').replace(/\/+$/, '/');
const OUT = path.resolve(process.env.P14_OUT_DIR || 'P14_output');
const SHOTS = path.join(OUT, 'P14_discovery_screenshots');
fs.mkdirSync(SHOTS, { recursive: true });

function credentials() {
  const encoded = process.env.P11_CREDENTIALS_B64 || '';
  if (!encoded) return [];
  const parsed = JSON.parse(Buffer.from(encoded, 'base64').toString('utf8'));
  return Array.isArray(parsed) ? parsed.filter(x => x && x.username && x.password) : [];
}

function pick(items, role) {
  const wanted = String(role).toUpperCase();
  return items.find(x => String(x.role || '').toUpperCase() === wanted) || null;
}

async function login(context, credential) {
  const page = await context.newPage();
  await page.goto(new URL('login', BASE).href, { waitUntil: 'domcontentloaded', timeout: 45000 });
  await page.locator('input[name="username"],input[name="email"],input[type="email"],input[type="text"]').first().fill(credential.username);
  await page.locator('input[type="password"]').first().fill(credential.password);
  await Promise.all([
    page.waitForLoadState('domcontentloaded', { timeout: 30000 }).catch(() => {}),
    page.locator('button[type="submit"],input[type="submit"]').first().click()
  ]);
  credential.password = '';
  credential.username = '';
  await page.waitForTimeout(700);
  if (/\/login(?:[/?#]|$)/i.test(page.url())) throw new Error('login failed');
  return page;
}

async function schema(page) {
  return page.evaluate(() => {
    function labelFor(el) {
      const id = el.id;
      if (id) {
        const label = document.querySelector(`label[for="${CSS.escape(id)}"]`);
        if (label) return (label.textContent || '').trim().replace(/\s+/g, ' ').slice(0, 180);
      }
      const parentLabel = el.closest('label');
      if (parentLabel) return (parentLabel.textContent || '').trim().replace(/\s+/g, ' ').slice(0, 180);
      const field = el.closest('.field,.form-group,.form-row,.modal-field');
      const near = field?.querySelector('label,.field-label,.form-label');
      return near ? (near.textContent || '').trim().replace(/\s+/g, ' ').slice(0, 180) : '';
    }
    const forms = [...document.forms].map((form, formIndex) => ({
      index: formIndex,
      id: form.id || null,
      method: (form.method || 'get').toUpperCase(),
      action: form.action || null,
      enctype: form.enctype || null,
      fields: [...form.querySelectorAll('input,select,textarea')]
        .filter(el => el.type !== 'hidden')
        .map(el => ({
          tag: el.tagName.toLowerCase(),
          type: el.type || null,
          name: el.name || null,
          id: el.id || null,
          required: Boolean(el.required),
          placeholder: el.getAttribute('placeholder'),
          accept: el.getAttribute('accept'),
          multiple: Boolean(el.multiple),
          label: labelFor(el),
          options: el.tagName === 'SELECT' ? [...el.options].map(o => ({ value: o.value, text: (o.textContent || '').trim().replace(/\s+/g,' ').slice(0,120) })) : undefined
        })),
      buttons: [...form.querySelectorAll('button,input[type="submit"],input[type="button"]')].map(b => ({
        type: b.type || null,
        name: b.name || null,
        text: (b.textContent || b.value || '').trim().replace(/\s+/g,' ').slice(0,160)
      }))
    }));
    const links = [...document.querySelectorAll('a[href]')]
      .map(a => ({ text: (a.textContent || '').trim().replace(/\s+/g,' ').slice(0,160), href: a.href }))
      .filter(x => /созда|добав|редакт|пользоват|руковод|документ|финанс|рейс|клиент|перевоз|водител|машин|экипаж/i.test(`${x.text} ${x.href}`))
      .slice(0, 120);
    const buttons = [...document.querySelectorAll('button,[role="button"],input[type="button"],input[type="submit"]')]
      .map(b => ({ text: (b.textContent || b.value || '').trim().replace(/\s+/g,' ').slice(0,160), id: b.id || null, type: b.type || null }))
      .filter(x => x.text)
      .slice(0, 120);
    return {
      title: document.title,
      h1: [...document.querySelectorAll('h1,h2,.page-title')].map(x => (x.textContent || '').trim().replace(/\s+/g,' ').slice(0,180)).slice(0,12),
      forms,
      links,
      buttons,
      bodyTextSample: (document.body?.innerText || '').trim().replace(/\s+/g,' ').slice(0,1400)
    };
  });
}

async function inspectRole(browser, role, credential, targets, result) {
  const context = await browser.newContext({
    viewport: { width: 1920, height: 1080 },
    deviceScaleFactor: 1,
    ignoreHTTPSErrors: true,
    locale: 'ru-RU',
    timezoneId: 'Europe/Moscow'
  });
  const page = await login(context, { ...credential });
  const roleResult = { role, pages: [] };
  for (let i = 0; i < targets.length; i += 1) {
    const target = targets[i];
    const consoleErrors = [];
    const pageErrors = [];
    const requestFailures = [];
    const badResponses = [];
    const onConsole = m => { if (m.type() === 'error') consoleErrors.push(m.text()); };
    const onPageError = e => pageErrors.push(String(e?.message || e));
    const onRequestFailed = r => requestFailures.push(`${r.method()} ${r.url()} :: ${r.failure()?.errorText || 'failed'}`);
    const onResponse = r => { if (r.status() >= 400) badResponses.push(`${r.status()} ${r.url()}`); };
    page.on('console', onConsole);
    page.on('pageerror', onPageError);
    page.on('requestfailed', onRequestFailed);
    page.on('response', onResponse);
    let response = null;
    let navigationError = null;
    try {
      response = await page.goto(new URL(target.path, BASE).href, { waitUntil: 'domcontentloaded', timeout: 45000 });
      await page.waitForTimeout(500);
    } catch (error) {
      navigationError = error?.name || 'Error';
    }
    const meta = await schema(page).catch(() => ({ schemaError: true }));
    let screenshot = null;
    if (target.screenshot) {
      screenshot = `P14_DISCOVERY_${role}_${String(i + 1).padStart(2,'0')}_${target.path.replace(/[^a-z0-9]+/gi,'_')}_1920x1080.png`;
      await page.screenshot({ path: path.join(SHOTS, screenshot), fullPage: false }).catch(() => { screenshot = null; });
    }
    roleResult.pages.push({
      requestedPath: '/' + target.path,
      httpStatus: response?.status() ?? null,
      finalUrl: page.url(),
      navigationError,
      consoleErrors,
      pageErrors,
      requestFailures,
      badResponses,
      screenshot,
      meta
    });
    page.off('console', onConsole);
    page.off('pageerror', onPageError);
    page.off('requestfailed', onRequestFailed);
    page.off('response', onResponse);
  }
  result.roles.push(roleResult);
  await context.close();
}

(async () => {
  const result = { status: 'FAIL', generatedAt: new Date().toISOString(), roles: [], errorClass: null };
  const creds = credentials();
  const superadmin = pick(creds, 'SUPERADMIN');
  const owner = pick(creds, 'OWNER');
  const logist = pick(creds, 'LOGIST');
  let browser;
  try {
    if (!superadmin || !owner || !logist) throw new Error('required role credentials unavailable');
    browser = await chromium.launch({ headless: true });
    await inspectRole(browser, 'SUPERADMIN', superadmin, [
      { path: 'superadmin/companies', screenshot: true },
      { path: 'superadmin/companies/create', screenshot: true },
      { path: 'superadmin/companies/25', screenshot: true },
      { path: 'superadmin/companies/25/owner', screenshot: true },
      { path: 'superadmin/companies/25/owner/edit', screenshot: false },
      { path: 'superadmin/companies/25/users', screenshot: true },
      { path: 'superadmin/companies/25/directories', screenshot: false },
      { path: 'superadmin/companies/25/documents', screenshot: false },
      { path: 'superadmin/deleted-data', screenshot: false }
    ], result);
    await inspectRole(browser, 'OWNER', owner, [
      { path: 'company/dashboard', screenshot: true },
      { path: 'company/clients', screenshot: true },
      { path: 'company/contractors', screenshot: true },
      { path: 'company/drivers', screenshot: true },
      { path: 'company/vehicle-sets', screenshot: true },
      { path: 'company/logists', screenshot: true },
      { path: 'company/responsible-assignments', screenshot: false },
      { path: 'company/documents', screenshot: false },
      { path: 'company/trips/linear?show_create=1', screenshot: true },
      { path: 'company/finance/dashboard', screenshot: true },
      { path: 'company/finance/bank-accounts', screenshot: false },
      { path: 'company/finance/cash', screenshot: false },
      { path: 'company/finance/invoices', screenshot: false },
      { path: 'company/finance/operations', screenshot: false },
      { path: 'company/finance/payment-calendar', screenshot: false },
      { path: 'company/finance/settings/dds-categories', screenshot: false }
    ], result);
    await inspectRole(browser, 'LOGIST', logist, [
      { path: 'company/dashboard', screenshot: true },
      { path: 'company/clients', screenshot: false },
      { path: 'company/contractors', screenshot: false },
      { path: 'company/drivers', screenshot: false },
      { path: 'company/vehicle-sets', screenshot: false },
      { path: 'company/trips/linear?show_create=1', screenshot: true },
      { path: 'company/finance/dashboard', screenshot: true }
    ], result);
    result.status = 'PASS';
  } catch (error) {
    result.errorClass = error?.name || 'Error';
  } finally {
    if (browser) await browser.close().catch(() => {});
    fs.writeFileSync(path.join(OUT, 'P14_runtime_result.json'), JSON.stringify(result, null, 2));
    console.log(`P14_DISCOVERY=${result.status}`);
    process.exitCode = result.status === 'PASS' ? 0 : 1;
  }
})().catch(() => {
  console.log('P14_DISCOVERY=FAIL');
  process.exitCode = 1;
});
