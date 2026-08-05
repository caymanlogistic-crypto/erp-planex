'use strict';

const fs = require('fs');
const path = require('path');
const { chromium } = require('playwright');

const BASE = 'https://plan-ex.ru/erpv2/';
const U = (value) => new URL(value.replace(/^\//, ''), BASE).href;
const OUT = path.resolve(process.env.P17_OUT || 'P17_route_runtime_output');
const SHOTS = path.join(OUT, 'P17_screenshots');
const targets = JSON.parse(fs.readFileSync(path.join(__dirname, 'P17_route_targets.json'), 'utf8')).targets;
fs.mkdirSync(SHOTS, { recursive: true });

const COMPANY = 27;
const USERS = {
  OWNER: { id: 16, username: 'p14_owner_260804221827' },
  SENIOR_LOGIST: { id: 17, username: 'p14_senior_260804221827' },
  LOGIST_1: { id: 18, username: 'p14_logist1_260804221827' },
  LOGIST_2: { id: 19, username: 'p14_logist2_260804221827' },
};
const critical = [
  ['OWNER', '/company/dashboard'],
  ['OWNER', '/company/clients'],
  ['OWNER', '/company/contractors'],
  ['OWNER', '/company/drivers'],
  ['OWNER', '/company/vehicle-sets'],
  ['OWNER', '/company/trips/linear'],
  ['OWNER', '/company/documents'],
  ['OWNER', '/company/finance/invoices'],
  ['OWNER', '/company/finance/operations'],
  ['OWNER', '/company/finance/payment-calendar'],
  ['SUPERADMIN', '/superadmin/companies'],
  ['SUPERADMIN', '/superadmin/companies/27'],
  ['SUPERADMIN', '/superadmin/deleted-data'],
];

function runtimeCredentials() {
  const encoded = process.env.P17_CREDENTIALS_B64 || '';
  return encoded ? JSON.parse(Buffer.from(encoded, 'base64').toString('utf8')) : [];
}
function adminCredential() {
  return runtimeCredentials().find((item) => String(item?.role || '').toUpperCase() === 'SUPERADMIN');
}
async function login(browser, username, password, viewport) {
  const context = await browser.newContext({
    viewport,
    deviceScaleFactor: 1,
    locale: 'ru-RU',
    timezoneId: 'Europe/Moscow',
    ignoreHTTPSErrors: true,
  });
  const page = await context.newPage();
  let ok = false;
  try {
    await page.goto(U('/login'), { waitUntil: 'domcontentloaded', timeout: 45000 });
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
function candidates(text, values, username) {
  const all = values.map(String);
  for (const match of String(text).matchAll(/[A-Za-z0-9!@#$%^&*()_+\-=]{8,64}/g)) all.push(match[0]);
  return [...new Set(all)].filter((value) => value !== username && !value.includes('@example.test') && !/^[a-f0-9]{64}$/i.test(value));
}
async function roleSession(browser, role, viewport) {
  const admin = adminCredential();
  if (!admin) throw new Error('SUPERADMIN credential unavailable.');
  if (role === 'SUPERADMIN') {
    const session = await login(browser, admin.username, admin.password, viewport);
    if (!session.ok) throw new Error('SUPERADMIN UI login failed.');
    return session;
  }
  const superSession = await login(browser, admin.username, admin.password, viewport);
  if (!superSession.ok) throw new Error(`SUPERADMIN UI login failed before ${role}.`);
  const meta = USERS[role];
  await superSession.page.goto(U(`/superadmin/companies/${COMPANY}/users`), { waitUntil: 'domcontentloaded', timeout: 45000 });
  const action = role === 'OWNER'
    ? `/superadmin/companies/${COMPANY}/owner/reset-password`
    : `/superadmin/companies/${COMPANY}/users/logists/${meta.id}/reset-password`;
  const form = superSession.page.locator(`form[action$="${action}"]`).first();
  if (!(await form.count())) throw new Error(`Reset form unavailable for ${role}.`);
  const navigation = superSession.page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 30000 });
  await form.evaluate((node) => HTMLFormElement.prototype.submit.call(node));
  await navigation;
  const values = await superSession.page.locator('input,textarea,code,pre,[data-password]').evaluateAll((nodes) => nodes.map((node) => node.value || node.getAttribute('data-password') || node.textContent || '').filter(Boolean));
  const list = candidates(await superSession.page.locator('body').innerText(), values, meta.username);
  await superSession.context.close();
  for (const password of list) {
    const session = await login(browser, meta.username, password, viewport);
    if (session.ok) {
      list.fill('');
      return session;
    }
    await session.context.close();
  }
  throw new Error(`UI login failed for ${role}.`);
}
function slug(value) {
  return value.replace(/^\//, '').replace(/[^A-Za-z0-9]+/g, '_').replace(/^_|_$/g, '') || 'root';
}
async function inspect(page, role, target, viewport, sequence, result, screenshotSuffix) {
  const local = { consoleErrors: [], pageErrors: [], requestFailures: [], httpFailures: [] };
  const onConsole = (message) => { if (message.type() === 'error') local.consoleErrors.push(message.text()); };
  const onPageError = (error) => local.pageErrors.push(String(error.message || error));
  const onRequestFailed = (request) => local.requestFailures.push({ method: request.method(), url: request.url(), error: request.failure()?.errorText || 'failed' });
  const onResponse = (response) => { if (response.status() >= 400) local.httpFailures.push({ status: response.status(), url: response.url() }); };
  page.on('console', onConsole);
  page.on('pageerror', onPageError);
  page.on('requestfailed', onRequestFailed);
  page.on('response', onResponse);
  let response = null;
  let navigationError = null;
  try {
    response = await page.goto(U(target.path), { waitUntil: 'networkidle', timeout: 60000 });
  } catch (error) {
    navigationError = String(error.message || error);
  }
  await page.waitForTimeout(200);
  const status = response?.status() ?? null;
  const layout = await page.evaluate(() => {
    const body = document.body;
    const html = document.documentElement;
    const content = document.querySelector('.content,.app-main,main');
    const visible = (node) => !!node && node.getClientRects().length > 0;
    const controls = [...document.querySelectorAll('button,a,input,select,textarea')].filter(visible);
    return {
      bodyOverflowX: Math.max(body.scrollWidth, html.scrollWidth) - html.clientWidth,
      contentOverflowX: content ? content.scrollWidth - content.clientWidth : 0,
      heading: document.querySelector('h1,h2,.page-title')?.textContent?.trim() || '',
      p16PrefixVisible: (body.innerText.match(/UIUX_P16_/g) || []).length,
      visibleRows: [...document.querySelectorAll('tbody tr')].filter(visible).length,
      offscreenControls: controls.filter((node) => {
        const rect = node.getBoundingClientRect();
        return rect.left < -2 || rect.right > window.innerWidth + 2;
      }).length,
      modalFooterOffscreen: [...document.querySelectorAll('.modal-foot,.modal-footer')].filter(visible).filter((node) => node.getBoundingClientRect().bottom > window.innerHeight + 2).length,
    };
  }).catch(() => ({ bodyOverflowX: 0, contentOverflowX: 0, heading: '', p16PrefixVisible: 0, visibleRows: 0, offscreenControls: 0, modalFooterOffscreen: 0 }));
  const expectedStatus = target.expected === 'ALLOW' ? 200 : 403;
  const expectedHttpFailures = local.httpFailures.filter((item) => target.expected === 'DENY' && item.status === 403 && item.url.includes(target.path));
  const unexpectedHttp = local.httpFailures.filter((item) => !expectedHttpFailures.includes(item));
  const pass = status === expectedStatus
    && !navigationError
    && layout.bodyOverflowX <= 2
    && layout.offscreenControls === 0
    && layout.modalFooterOffscreen === 0
    && local.consoleErrors.length === 0
    && local.pageErrors.length === 0
    && local.requestFailures.length === 0
    && unexpectedHttp.every((item) => item.status === 403 && target.expected === 'DENY');
  const file = `P17_${String(sequence).padStart(3, '0')}_${role}_${slug(target.path)}_${screenshotSuffix}.png`;
  await page.screenshot({ path: path.join(SHOTS, file), fullPage: false }).catch(() => {});
  page.off('console', onConsole);
  page.off('pageerror', onPageError);
  page.off('requestfailed', onRequestFailed);
  page.off('response', onResponse);
  const record = {
    role,
    route: target.path,
    expected: target.expected,
    expectedStatus,
    actualStatus: status,
    finalUrl: page.url(),
    title: await page.title().catch(() => ''),
    viewport: `${viewport.width}x${viewport.height}`,
    screenshot: `P17_screenshots/${file}`,
    navigationError,
    ...layout,
    consoleErrors: local.consoleErrors,
    pageErrors: local.pageErrors,
    requestFailures: local.requestFailures,
    httpFailures: local.httpFailures,
    pass,
  };
  result.consoleErrors.push(...local.consoleErrors.map((message) => ({ role, route: target.path, message })));
  result.pageErrors.push(...local.pageErrors.map((message) => ({ role, route: target.path, message })));
  result.requestFailures.push(...local.requestFailures.map((item) => ({ role, route: target.path, ...item })));
  result.httpFailures.push(...local.httpFailures.map((item) => ({ role, route: target.path, expected: target.expected, ...item })));
  return record;
}

(async () => {
  const result = {
    status: 'FAIL',
    deployedSha: process.env.P17_DEPLOYED_SHA || null,
    routeMatrix: [],
    viewportMatrix: [],
    consoleErrors: [],
    pageErrors: [],
    requestFailures: [],
    httpFailures: [],
    summary: {},
    error: null,
  };
  let browser;
  let sequence = 1;
  try {
    browser = await chromium.launch({ headless: true });
    const roleOrder = ['SUPERADMIN','OWNER','SENIOR_LOGIST','LOGIST_1','LOGIST_2'];
    for (const role of roleOrder) {
      const session = await roleSession(browser, role, { width: 1920, height: 1080 });
      for (const target of targets.filter((item) => item.role === role)) {
        result.routeMatrix.push(await inspect(session.page, role, target, { width: 1920, height: 1080 }, sequence++, result, '1920x1080'));
      }
      await session.context.close();
    }
    for (const viewport of [{ width: 1536, height: 864 }, { width: 1366, height: 768 }]) {
      for (const role of ['SUPERADMIN','OWNER']) {
        const session = await roleSession(browser, role, viewport);
        for (const [targetRole, targetPath] of critical.filter(([itemRole]) => itemRole === role)) {
          result.viewportMatrix.push(await inspect(session.page, targetRole, { path: targetPath, expected: 'ALLOW' }, viewport, sequence++, result, `${viewport.width}x${viewport.height}`));
        }
        await session.context.close();
      }
    }
    const all = [...result.routeMatrix, ...result.viewportMatrix];
    const expected403 = result.routeMatrix.filter((item) => item.expected === 'DENY' && item.actualStatus === 403).length;
    result.summary = {
      routesReviewed: result.routeMatrix.length,
      routesPass: result.routeMatrix.filter((item) => item.pass).length,
      routesFail: result.routeMatrix.filter((item) => !item.pass).length,
      fullhdPages: result.routeMatrix.length,
      viewport1536Pages: result.viewportMatrix.filter((item) => item.viewport === '1536x864').length,
      viewport1366Pages: result.viewportMatrix.filter((item) => item.viewport === '1366x768').length,
      expected403,
      viewportPass: result.viewportMatrix.filter((item) => item.pass).length,
      viewportFail: result.viewportMatrix.filter((item) => !item.pass).length,
    };
    result.status = all.every((item) => item.pass) ? 'PASS' : 'FAIL';
  } catch (error) {
    result.error = { name: error?.name || 'Error', message: String(error?.message || error).slice(0, 1200) };
  } finally {
    if (browser) await browser.close().catch(() => {});
    fs.writeFileSync(path.join(OUT, 'P17_02_ROUTE_ROLE_MATRIX.json'), JSON.stringify(result.routeMatrix, null, 2));
    fs.writeFileSync(path.join(OUT, 'P17_07_FULLHD_PAGE_MATRIX.json'), JSON.stringify(result.routeMatrix, null, 2));
    fs.writeFileSync(path.join(OUT, 'P17_08_VIEWPORT_REGRESSION_MATRIX.json'), JSON.stringify(result.viewportMatrix, null, 2));
    fs.writeFileSync(path.join(OUT, 'P17_09_CONSOLE_NETWORK_REPORT.json'), JSON.stringify({ status: result.status, deployedSha: result.deployedSha, consoleErrors: result.consoleErrors, pageErrors: result.pageErrors, requestFailures: result.requestFailures, httpFailures: result.httpFailures, summary: result.summary, error: result.error }, null, 2));
    fs.writeFileSync(path.join(OUT, 'P17_12_SCREENSHOT_INDEX.json'), JSON.stringify([...result.routeMatrix, ...result.viewportMatrix].map((item) => ({ role: item.role, route: item.route, viewport: item.viewport, screenshot: item.screenshot, pass: item.pass })), null, 2));
    console.log(`P17_ROUTE_ROLE_FULLHD=${result.status}`);
    process.exitCode = result.status === 'PASS' ? 0 : 1;
  }
})();
