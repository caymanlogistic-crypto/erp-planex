'use strict';

const fs = require('fs');
const path = require('path');
const { chromium } = require('playwright');

const BASE = (process.env.P14_BASE_URL || 'https://plan-ex.ru/erpv2/').replace(/\/+$/, '/');
const OUT = path.resolve(process.env.P14_OUT_DIR || 'P14_output');
fs.mkdirSync(OUT, { recursive: true });

function superadminCredential() {
  const encoded = process.env.P11_CREDENTIALS_B64 || '';
  if (!encoded) throw new Error('credential payload unavailable');
  const items = JSON.parse(Buffer.from(encoded, 'base64').toString('utf8'));
  const item = Array.isArray(items) ? items.find(x => String(x?.role || '').toUpperCase() === 'SUPERADMIN') : null;
  if (!item?.username || !item?.password) throw new Error('superadmin credential unavailable');
  return { username: String(item.username), password: String(item.password) };
}

(async () => {
  const result = {
    status: 'FAIL',
    loginHttpStatus: null,
    finalUrlAfterLogin: null,
    superadminUiPresent: false,
    protectedPages: [],
    sameBrowserContext: true,
    consoleErrors: [],
    pageErrors: [],
    requestFailures: [],
    badResponses: [],
    screenshots: [],
    errorClass: null
  };
  let browser;
  let context;
  try {
    const credential = superadminCredential();
    browser = await chromium.launch({ headless: true });
    context = await browser.newContext({
      viewport: { width: 1920, height: 1080 },
      deviceScaleFactor: 1,
      ignoreHTTPSErrors: true,
      locale: 'ru-RU',
      timezoneId: 'Europe/Moscow'
    });
    const page = await context.newPage();
    page.on('console', message => { if (message.type() === 'error') result.consoleErrors.push(message.text()); });
    page.on('pageerror', error => result.pageErrors.push(String(error?.message || error)));
    page.on('requestfailed', request => result.requestFailures.push({ method: request.method(), url: request.url(), error: request.failure()?.errorText || 'failed' }));
    page.on('response', response => { if (response.status() >= 400) result.badResponses.push({ status: response.status(), url: response.url() }); });

    const loginResponse = await page.goto(new URL('login', BASE).href, { waitUntil: 'domcontentloaded', timeout: 45000 });
    result.loginHttpStatus = loginResponse?.status() ?? null;
    await page.locator('input[name="username"],input[name="email"],input[type="email"],input[type="text"]').first().fill(credential.username);
    await page.locator('input[type="password"]').first().fill(credential.password);
    await Promise.all([
      page.waitForLoadState('domcontentloaded', { timeout: 30000 }).catch(() => {}),
      page.locator('button[type="submit"],input[type="submit"]').first().click()
    ]);
    credential.password = '';
    credential.username = '';
    await page.waitForTimeout(1000);
    result.finalUrlAfterLogin = page.url();
    const noLongerLogin = !/\/login(?:[/?#]|$)/i.test(page.url());
    result.superadminUiPresent = await page.locator('a[href*="/superadmin/"]').count() > 0;

    for (const target of ['superadmin/companies', 'superadmin/db-usage']) {
      const response = await page.goto(new URL(target, BASE).href, { waitUntil: 'domcontentloaded', timeout: 45000 }).catch(() => null);
      await page.waitForTimeout(500);
      const screenshot = `P14_${target.replace(/\//g, '_')}_1920x1080.png`;
      await page.screenshot({ path: path.join(OUT, screenshot), fullPage: false });
      result.screenshots.push(screenshot);
      result.protectedPages.push({
        path: '/' + target,
        status: response?.status() ?? null,
        finalUrl: page.url(),
        pass: response?.status() === 200 && !/\/login(?:[/?#]|$)/i.test(page.url())
      });
    }

    result.status = noLongerLogin && result.superadminUiPresent && result.protectedPages.every(item => item.pass) ? 'PASS' : 'FAIL';
  } catch (error) {
    result.errorClass = error?.name || 'Error';
  } finally {
    fs.writeFileSync(path.join(OUT, 'P14_runtime_result.json'), JSON.stringify(result, null, 2));
    if (context) await context.close().catch(() => {});
    if (browser) await browser.close().catch(() => {});
    console.log(`SUPERADMIN_LOGIN=${result.status}`);
    process.exitCode = result.status === 'PASS' ? 0 : 1;
  }
})().catch(() => {
  console.log('SUPERADMIN_LOGIN=FAIL');
  process.exitCode = 1;
});
