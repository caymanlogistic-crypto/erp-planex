'use strict';

const fs = require('fs');
const path = require('path');
const { chromium } = require('playwright');

const TARGET = 'https://plan-ex.ru/erpv2/login';
const OUT = path.resolve('P14_browser_capability_output');
fs.mkdirSync(OUT, { recursive: true });

(async () => {
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({
    viewport: { width: 1920, height: 1080 },
    deviceScaleFactor: 1,
    locale: 'ru-RU',
    timezoneId: 'Europe/Moscow',
    ignoreHTTPSErrors: false
  });
  const page = await context.newPage();

  const consoleErrors = [];
  const pageErrors = [];
  const requestFailures = [];
  const badResponses = [];

  page.on('console', message => {
    if (message.type() === 'error') consoleErrors.push(message.text());
  });
  page.on('pageerror', error => pageErrors.push(String(error.message || error)));
  page.on('requestfailed', request => {
    requestFailures.push({
      method: request.method(),
      url: request.url(),
      error: request.failure()?.errorText || 'failed'
    });
  });
  page.on('response', response => {
    if (response.status() >= 400) {
      badResponses.push({ status: response.status(), url: response.url() });
    }
  });

  let response = null;
  let navigationError = null;
  try {
    response = await page.goto(TARGET, {
      waitUntil: 'networkidle',
      timeout: 45000
    });
  } catch (error) {
    navigationError = String(error.message || error);
  }

  const selectors = await page.evaluate(() => {
    const visible = element => {
      if (!(element instanceof Element)) return false;
      const style = getComputedStyle(element);
      const rect = element.getBoundingClientRect();
      return style.display !== 'none' && style.visibility !== 'hidden' && rect.width > 0 && rect.height > 0;
    };
    const form = [...document.querySelectorAll('form')].find(visible) || null;
    const username = [...document.querySelectorAll('input[name="username"], input[name="email"], input[type="email"], input[type="text"]')].find(visible) || null;
    const password = [...document.querySelectorAll('input[type="password"]')].find(visible) || null;
    const submit = [...document.querySelectorAll('button[type="submit"], input[type="submit"]')].find(visible) || null;
    return {
      hasForm: Boolean(form),
      hasUsernameOrEmailInput: Boolean(username),
      usernameInputName: username?.getAttribute('name') || null,
      hasPasswordInput: Boolean(password),
      passwordInputName: password?.getAttribute('name') || null,
      hasSubmitButton: Boolean(submit),
      submitText: (submit?.textContent || submit?.getAttribute('value') || '').trim().slice(0, 100)
    };
  }).catch(() => ({
    hasForm: false,
    hasUsernameOrEmailInput: false,
    usernameInputName: null,
    hasPasswordInput: false,
    passwordInputName: null,
    hasSubmitButton: false,
    submitText: ''
  }));

  const screenshotPath = path.join(OUT, 'P14_login_1920x1080.png');
  await page.screenshot({ path: screenshotPath, fullPage: false });

  const result = {
    target: TARGET,
    httpStatus: response?.status() || null,
    finalUrl: page.url(),
    title: await page.title(),
    viewport: { width: 1920, height: 1080, deviceScaleFactor: 1 },
    locale: 'ru-RU',
    timezone: 'Europe/Moscow',
    ...selectors,
    navigationError,
    consoleErrors,
    pageErrors,
    requestFailures,
    responsesStatusGte400: badResponses,
    screenshot: path.basename(screenshotPath)
  };

  result.pass = !navigationError &&
    result.httpStatus === 200 &&
    /\/erpv2\/login(?:[/?#]|$)/i.test(result.finalUrl) &&
    result.hasForm &&
    result.hasUsernameOrEmailInput &&
    result.hasPasswordInput &&
    result.hasSubmitButton &&
    result.pageErrors.length === 0 &&
    result.requestFailures.length === 0;

  fs.writeFileSync(path.join(OUT, 'P14_browser_capability_result.json'), JSON.stringify(result, null, 2));
  console.log(`P14_BROWSER_SMOKE=${result.pass ? 'PASS' : 'FAIL'}`);

  await context.close();
  await browser.close();
  process.exitCode = result.pass ? 0 : 1;
})().catch(error => {
  fs.mkdirSync(OUT, { recursive: true });
  fs.writeFileSync(path.join(OUT, 'P14_browser_capability_fatal.txt'), String(error.stack || error));
  console.log('P14_BROWSER_SMOKE=FAIL');
  process.exitCode = 1;
});
