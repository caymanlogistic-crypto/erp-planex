'use strict';
const fs = require('fs');
const path = require('path');
const { chromium } = require('playwright');

const URL = 'https://plan-ex.ru/erpv2/login';
const OUT = path.resolve('P14_browser_capability_output');
fs.mkdirSync(OUT, { recursive: true });

(async () => {
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({
    viewport: { width: 1920, height: 1080 },
    deviceScaleFactor: 1,
    locale: 'ru-RU',
    timezoneId: 'Europe/Moscow',
    ignoreHTTPSErrors: false,
  });
  const page = await context.newPage();
  const consoleErrors = [];
  const pageErrors = [];
  const requestFailures = [];
  const badResponses = [];
  page.on('console', m => { if (m.type() === 'error') consoleErrors.push(m.text()); });
  page.on('pageerror', e => pageErrors.push(String(e.message || e)));
  page.on('requestfailed', r => requestFailures.push({ method: r.method(), url: r.url(), error: r.failure()?.errorText || 'failed' }));
  page.on('response', r => { if (r.status() >= 400) badResponses.push({ status: r.status(), url: r.url() }); });

  let response = null;
  let navigationError = null;
  try {
    response = await page.goto(URL, { waitUntil: 'networkidle', timeout: 45000 });
  } catch (e) {
    navigationError = String(e.message || e);
  }

  const selectors = {
    form: 'form',
    username: 'input[name="username"],input[name="email"],input[type="email"],input[type="text"]',
    password: 'input[type="password"]',
    submit: 'button[type="submit"],input[type="submit"]',
  };
  const result = {
    status: 'FAIL',
    requestedUrl: URL,
    httpStatus: response?.status() ?? null,
    finalUrl: page.url(),
    title: await page.title().catch(() => ''),
    viewport: page.viewportSize(),
    deviceScaleFactor: 1,
    locale: 'ru-RU',
    timezone: 'Europe/Moscow',
    formPresent: await page.locator(selectors.form).count() > 0,
    usernameInputPresent: await page.locator(selectors.username).count() > 0,
    passwordInputPresent: await page.locator(selectors.password).count() > 0,
    submitButtonPresent: await page.locator(selectors.submit).count() > 0,
    consoleErrors,
    pageErrors,
    requestFailures,
    badResponses,
    navigationError,
    screenshot: 'P14_login_1920x1080.png',
  };
  await page.screenshot({ path: path.join(OUT, result.screenshot), fullPage: false });
  result.status = !navigationError && result.httpStatus === 200 && result.formPresent && result.usernameInputPresent && result.passwordInputPresent && result.submitButtonPresent ? 'PASS' : 'FAIL';
  fs.writeFileSync(path.join(OUT, 'P14_browser_capability_result.json'), JSON.stringify(result, null, 2));
  console.log(`P14_BROWSER_SMOKE=${result.status}`);
  await context.close();
  await browser.close();
  if (result.status !== 'PASS') process.exitCode = 1;
})().catch(e => {
  fs.mkdirSync(OUT, { recursive: true });
  fs.writeFileSync(path.join(OUT, 'P14_browser_capability_fatal.txt'), String(e.stack || e));
  console.log('P14_BROWSER_SMOKE=FAIL');
  process.exitCode = 1;
});
