'use strict';

const fs = require('fs');
const path = require('path');
const { chromium } = require('playwright');

const BASE = 'https://plan-ex.ru/erpv2/';
const url = (value) => new URL(value, BASE).href;
const OUT = path.resolve(process.env.P16_OUT || 'P16_populated_runtime_output');
const SHOTS = path.join(OUT, 'P16_screenshots');
fs.mkdirSync(SHOTS, { recursive: true });

const COMPANY = 27;
const USERS = {
  OWNER: { id: 16, username: 'p14_owner_260804221827' },
  SENIOR_LOGIST: { id: 17, username: 'p14_senior_260804221827' },
  LOGIST_1: { id: 18, username: 'p14_logist1_260804221827' },
  LOGIST_2: { id: 19, username: 'p14_logist2_260804221827' },
};
const OWNER_PAGES = [
  ['dashboard', 'company/dashboard'],
  ['clients', 'company/clients'],
  ['contractors', 'company/contractors'],
  ['drivers', 'company/drivers'],
  ['transport', 'company/vehicle-sets'],
  ['executors', 'company/route-executors'],
  ['trips', 'company/trips/linear'],
  ['finance-dashboard', 'company/finance/dashboard'],
  ['invoices', 'company/finance/invoices'],
  ['operations', 'company/finance/operations'],
  ['cash', 'company/finance/cash'],
  ['payment-calendar', 'company/finance/payment-calendar'],
  ['cash-flow', 'company/finance/reports/cash-flow'],
  ['management-balance', 'company/finance/reports/management-balance'],
  ['payment-plan-fact', 'company/finance/reports/payment-plan-fact'],
  ['dds', 'company/finance/settings/dds-categories'],
  ['matching', 'company/finance/settings/matching-rules'],
  ['bank-accounts', 'company/finance/bank-accounts'],
];
const OPERATIONAL_PAGES = [
  ['dashboard', 'company/dashboard'],
  ['clients', 'company/clients'],
  ['contractors', 'company/contractors'],
  ['drivers', 'company/drivers'],
  ['transport', 'company/vehicle-sets'],
  ['executors', 'company/route-executors'],
  ['trips', 'company/trips/linear'],
];
const VIEWPORTS = [
  { width: 1920, height: 1080, label: '1920x1080' },
  { width: 1536, height: 864, label: '1536x864' },
  { width: 1366, height: 768, label: '1366x768' },
];

function credentials() {
  const raw = process.env.P16_CREDENTIALS_B64 || '';
  return raw ? JSON.parse(Buffer.from(raw, 'base64').toString('utf8')) : [];
}
function superadmin() {
  return credentials().find((item) => String(item?.role || '').toUpperCase() === 'SUPERADMIN');
}
async function login(browser, username, password, viewport) {
  const context = await browser.newContext({
    viewport: { width: viewport.width, height: viewport.height },
    deviceScaleFactor: 1,
    locale: 'ru-RU',
    timezoneId: 'Europe/Moscow',
    ignoreHTTPSErrors: true,
  });
  const page = await context.newPage();
  let ok = false;
  try {
    await page.goto(url('login'), { waitUntil: 'domcontentloaded', timeout: 45000 });
    await page.locator('input[name="username"],input[name="email"],input[type="text"]').first().fill(username);
    await page.locator('input[type="password"]').first().fill(password);
    await Promise.all([
      page.waitForLoadState('domcontentloaded', { timeout: 20000 }).catch(() => {}),
      page.locator('button[type="submit"],input[type="submit"]').first().click(),
    ]);
    await page.waitForTimeout(500);
    ok = !/\/login(?:[/?#]|$)/i.test(page.url());
  } catch (_) {}
  return { context, page, ok };
}
function passwordCandidates(text, values, username) {
  const candidates = [...values.map(String)];
  for (const match of String(text).matchAll(/[A-Za-z0-9!@#$%^&*()_+\-=]{8,64}/g)) candidates.push(match[0]);
  return [...new Set(candidates)].filter((value) => value !== username && !value.includes('@example.test') && !/^[a-f0-9]{64}$/i.test(value));
}
async function roleSession(browser, role, viewport) {
  const admin = superadmin();
  if (!admin) throw new Error('SUPERADMIN runtime credential is unavailable.');
  const sa = await login(browser, admin.username, admin.password, viewport);
  if (!sa.ok) throw new Error('SUPERADMIN UI login failed.');
  const meta = USERS[role];
  await sa.page.goto(url(`superadmin/companies/${COMPANY}/users`), { waitUntil: 'domcontentloaded', timeout: 45000 });
  const action = role === 'OWNER'
    ? `/superadmin/companies/${COMPANY}/owner/reset-password`
    : `/superadmin/companies/${COMPANY}/users/logists/${meta.id}/reset-password`;
  const form = sa.page.locator(`form[action$="${action}"]`).first();
  if (!(await form.count())) throw new Error(`Password reset form is unavailable for ${role}.`);
  const navigation = sa.page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 30000 });
  await form.evaluate((node) => HTMLFormElement.prototype.submit.call(node));
  await navigation;
  const values = await sa.page.locator('input,textarea,code,pre,[data-password]').evaluateAll((nodes) => nodes.map((node) => node.value || node.getAttribute('data-password') || node.textContent || '').filter(Boolean));
  const candidates = passwordCandidates(await sa.page.locator('body').innerText(), values, meta.username);
  await sa.context.close();
  for (const candidate of candidates) {
    const session = await login(browser, meta.username, candidate, viewport);
    if (session.ok) {
      candidates.fill('');
      return session;
    }
    await session.context.close();
  }
  throw new Error(`UI login failed for ${role}.`);
}
function diagnostics(page, result, role, viewport) {
  page.on('console', (message) => {
    if (message.type() === 'error') result.consoleErrors.push({ role, viewport: viewport.label, url: page.url(), message: message.text() });
  });
  page.on('pageerror', (error) => result.pageErrors.push({ role, viewport: viewport.label, url: page.url(), message: String(error.message || error) }));
  page.on('requestfailed', (request) => result.requestFailures.push({ role, viewport: viewport.label, method: request.method(), url: request.url(), error: request.failure()?.errorText || 'failed' }));
  page.on('response', (response) => {
    if (response.status() >= 500) result.unexpectedHttp.push({ role, viewport: viewport.label, status: response.status(), url: response.url() });
  });
}
async function inspectPage(page, role, viewport, id, route, screenshotNumber) {
  const response = await page.goto(url(route), { waitUntil: 'networkidle', timeout: 60000 });
  await page.waitForTimeout(250);
  const status = response?.status() ?? null;
  const metrics = await page.evaluate(() => {
    const body = document.body;
    const html = document.documentElement;
    const content = document.querySelector('.content,.app-main,main');
    const visible = (node) => !!node && node.getClientRects().length > 0;
    const offscreen = [...document.querySelectorAll('button,a,input,select,textarea')].filter(visible).filter((node) => {
      const rect = node.getBoundingClientRect();
      return rect.right > window.innerWidth + 2 || rect.left < -2 || rect.bottom > window.innerHeight + 5000;
    }).length;
    return {
      bodyScrollWidth: Math.max(body.scrollWidth, html.scrollWidth),
      bodyClientWidth: html.clientWidth,
      bodyOverflowX: Math.max(body.scrollWidth, html.scrollWidth) - html.clientWidth,
      contentOverflowX: content ? content.scrollWidth - content.clientWidth : 0,
      heading: document.querySelector('h1,h2,.page-title')?.textContent?.trim() || '',
      p16PrefixVisible: (body.innerText.match(/UIUX_P16_/g) || []).length,
      visibleRows: [...document.querySelectorAll('tbody tr')].filter(visible).length,
      offscreenControls: offscreen,
      p16Stylesheet: [...document.styleSheets].some((sheet) => String(sheet.href || '').includes('p16-fullhd.css')),
    };
  });
  const file = `P16_${String(screenshotNumber).padStart(3, '0')}_${role}_${id}_populated_${viewport.label}.png`;
  await page.screenshot({ path: path.join(SHOTS, file), fullPage: false });
  return {
    role,
    viewport: viewport.label,
    id,
    route,
    status,
    finalUrl: page.url(),
    title: await page.title(),
    screenshot: `P16_screenshots/${file}`,
    ...metrics,
    pass: status === 200 && metrics.bodyOverflowX <= 2 && metrics.offscreenControls === 0,
  };
}

(async () => {
  const result = {
    status: 'FAIL',
    deployedShaExpected: process.env.P16_EXPECTED_SHA || null,
    pages: [],
    roleChecks: [],
    expected403: [],
    consoleErrors: [],
    pageErrors: [],
    requestFailures: [],
    unexpectedHttp: [],
    summary: {},
    error: null,
  };
  let browser;
  let sequence = 1;
  try {
    browser = await chromium.launch({ headless: true });
    for (const viewport of VIEWPORTS) {
      const owner = await roleSession(browser, 'OWNER', viewport);
      diagnostics(owner.page, result, 'OWNER', viewport);
      const pages = viewport.width === 1920 ? OWNER_PAGES : OWNER_PAGES.filter(([id]) => ['dashboard','clients','contractors','drivers','transport','trips','invoices','operations','payment-calendar'].includes(id));
      for (const [id, route] of pages) result.pages.push(await inspectPage(owner.page, 'OWNER', viewport, id, route, sequence++));
      await owner.context.close();
    }

    for (const role of ['SENIOR_LOGIST', 'LOGIST_1', 'LOGIST_2']) {
      const viewport = VIEWPORTS[0];
      const session = await roleSession(browser, role, viewport);
      diagnostics(session.page, result, role, viewport);
      for (const [id, route] of OPERATIONAL_PAGES) result.pages.push(await inspectPage(session.page, role, viewport, id, route, sequence++));
      const financeResponse = await session.page.goto(url('company/finance/dashboard'), { waitUntil: 'domcontentloaded', timeout: 45000 });
      const financeStatus = financeResponse?.status() ?? null;
      const financeMenuVisible = (await session.page.locator('a[href*="/company/finance/"]:visible').count()) > 0;
      const prefixVisible = (await session.page.locator('body').innerText()).includes('UIUX_P16_');
      result.roleChecks.push({ role, financeStatus, financeMenuVisible, operationalPrefixVisible: prefixVisible });
      if (financeStatus === 403) result.expected403.push({ role, route: '/company/finance/dashboard', status: financeStatus });
      await session.context.close();
    }

    const pageFailures = result.pages.filter((item) => !item.pass);
    const ownerPopulated = result.pages.filter((item) => item.role === 'OWNER' && item.viewport === '1920x1080' && ['clients','contractors','drivers','transport','trips','invoices','operations'].includes(item.id)).every((item) => item.visibleRows > 0 || item.p16PrefixVisible > 0);
    const rolePass = result.roleChecks.every((item) => item.financeStatus === 403 && item.financeMenuVisible === false);
    result.summary = {
      pages: result.pages.length,
      pass: result.pages.length - pageFailures.length,
      fail: pageFailures.length,
      fullhdPages: result.pages.filter((item) => item.viewport === '1920x1080').length,
      viewport1536Pages: result.pages.filter((item) => item.viewport === '1536x864').length,
      viewport1366Pages: result.pages.filter((item) => item.viewport === '1366x768').length,
      ownerPopulated,
      rolePass,
    };
    result.status = pageFailures.length === 0
      && ownerPopulated
      && rolePass
      && result.consoleErrors.length === 0
      && result.pageErrors.length === 0
      && result.requestFailures.length === 0
      && result.unexpectedHttp.length === 0
      ? 'PASS' : 'FAIL';
  } catch (error) {
    result.error = { name: error?.name || 'Error', message: String(error?.message || error).slice(0, 1200) };
  } finally {
    if (browser) await browser.close().catch(() => {});
    fs.writeFileSync(path.join(OUT, 'P16_07_FULLHD_PAGE_MATRIX.json'), JSON.stringify(result, null, 2));
    fs.writeFileSync(path.join(OUT, 'P16_11_SCREENSHOT_INDEX.json'), JSON.stringify(result.pages.map((item) => ({ role: item.role, viewport: item.viewport, route: item.route, screenshot: item.screenshot, pass: item.pass })), null, 2));
    console.log(`P16_POPULATED_FULLHD=${result.status}`);
    process.exitCode = result.status === 'PASS' ? 0 : 1;
  }
})();
