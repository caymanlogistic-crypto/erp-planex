'use strict';

const fs = require('fs');
const path = require('path');
const { chromium } = require('playwright');

const BASE = 'https://plan-ex.ru/erpv2/';
const OLD_ERP = 'https://plan-ex.ru/erp/';
const OUT = path.resolve(process.env.P15_OUT || 'P15_startup_gate_output');
fs.mkdirSync(OUT, { recursive: true });

function loadCredential() {
  const encoded = process.env.P15_CREDENTIALS_B64 || '';
  const data = encoded ? JSON.parse(Buffer.from(encoded, 'base64').toString('utf8')) : [];
  const item = Array.isArray(data)
    ? data.find((entry) => String(entry?.role || '').toUpperCase() === 'SUPERADMIN')
    : null;
  if (!item?.username || !item?.password) throw new Error('superadmin credential unavailable');
  return { username: String(item.username), password: String(item.password) };
}

const compact = (value) => String(value || '').replace(/\s+/g, ' ').trim();

(async () => {
  const result = {
    status: 'FAIL',
    generatedAt: new Date().toISOString(),
    configuration: {
      viewport: { width: 1920, height: 1080 },
      deviceScaleFactor: 1,
      locale: 'ru-RU',
      timezone: 'Europe/Moscow'
    },
    publicDeployMarker: { available: false, status: null, value: null },
    login: { httpStatus: null, finalUrl: null, superadminUiPresent: false, sameBrowserContext: true },
    companies: [],
    oldErp: { status: null, finalUrl: null, title: null },
    consoleErrors: [],
    pageErrors: [],
    requestFailures: [],
    unexpectedHttpErrors: [],
    screenshots: [],
    errorClass: null
  };

  let browser;
  let context;
  try {
    const credential = loadCredential();
    browser = await chromium.launch({ headless: true });
    context = await browser.newContext({
      viewport: result.configuration.viewport,
      deviceScaleFactor: result.configuration.deviceScaleFactor,
      locale: result.configuration.locale,
      timezoneId: result.configuration.timezone
    });
    const page = await context.newPage();

    page.on('console', (message) => {
      if (message.type() === 'error') result.consoleErrors.push(message.text());
    });
    page.on('pageerror', (error) => result.pageErrors.push(String(error?.message || error)));
    page.on('requestfailed', (request) => result.requestFailures.push({
      method: request.method(),
      url: request.url(),
      error: request.failure()?.errorText || 'failed'
    }));
    page.on('response', (response) => {
      if (response.status() >= 400) result.unexpectedHttpErrors.push({ status: response.status(), url: response.url() });
    });

    const marker = await context.request.get(BASE + 'DEPLOYED_COMMIT.txt', { timeout: 30000 }).catch(() => null);
    result.publicDeployMarker.status = marker?.status() ?? null;
    if (marker?.ok()) {
      result.publicDeployMarker.available = true;
      result.publicDeployMarker.value = compact(await marker.text());
    }

    const loginResponse = await page.goto(BASE + 'login', { waitUntil: 'domcontentloaded', timeout: 45000 });
    result.login.httpStatus = loginResponse?.status() ?? null;
    await page.locator('input[name="username"],input[name="email"],input[type="email"],input[type="text"]').first().fill(credential.username);
    await page.locator('input[type="password"]').first().fill(credential.password);
    await Promise.all([
      page.waitForLoadState('domcontentloaded', { timeout: 30000 }).catch(() => {}),
      page.locator('button[type="submit"],input[type="submit"]').first().click()
    ]);
    credential.username = '';
    credential.password = '';
    await page.waitForTimeout(700);

    result.login.finalUrl = page.url();
    result.login.superadminUiPresent = await page.locator('a[href*="/superadmin/"]').count() > 0;
    if (/\/login(?:[/?#]|$)/i.test(page.url()) || !result.login.superadminUiPresent) {
      throw new Error('superadmin login failed');
    }

    const companiesResponse = await page.goto(BASE + 'superadmin/companies', {
      waitUntil: 'domcontentloaded', timeout: 45000
    });
    if (companiesResponse?.status() !== 200) throw new Error('companies page unavailable');

    const rows = await page.locator('tbody tr').evaluateAll((items) => items.map((row) => {
      const text = (row.innerText || '').replace(/\s+/g, ' ').trim();
      const hrefs = Array.from(row.querySelectorAll('a[href]')).map((link) => link.getAttribute('href') || '');
      const href = hrefs.find((value) => /\/superadmin\/companies\/\d+(?:[/?#]|$)/.test(value)) || '';
      const match = href.match(/\/superadmin\/companies\/(\d+)/);
      return { text, id: match ? Number(match[1]) : null };
    }));

    for (const row of rows.filter((item) => item.id && (item.text.includes('UIUX TEST EXPEDITOR') || item.text.includes('ПЛАНЭКС')))) {
      const response = await page.goto(BASE + `superadmin/companies/${row.id}`, {
        waitUntil: 'domcontentloaded', timeout: 45000
      });
      await page.waitForTimeout(300);
      const screenshot = `P15_GATE_COMPANY_${row.id}_1920x1080.png`;
      await page.screenshot({ path: path.join(OUT, screenshot), fullPage: false });
      result.screenshots.push(screenshot);
      result.companies.push({
        id: row.id,
        listRowText: row.text,
        detailStatus: response?.status() ?? null,
        detailFinalUrl: page.url(),
        detailText: compact(await page.locator('body').innerText()).slice(0, 20000),
        screenshot
      });
    }

    const oldResponse = await page.goto(OLD_ERP, { waitUntil: 'domcontentloaded', timeout: 45000 }).catch(() => null);
    result.oldErp.status = oldResponse?.status() ?? null;
    result.oldErp.finalUrl = page.url();
    result.oldErp.title = await page.title().catch(() => null);

    const unexpected = result.unexpectedHttpErrors.filter((item) => !item.url.includes('/favicon'));
    const hasProduction = result.companies.some((company) => company.id === 25 && company.listRowText.includes('ПЛАНЭКС'));
    const testIds = result.companies.filter((company) => company.listRowText.includes('UIUX TEST EXPEDITOR')).map((company) => company.id);
    result.status = (
      result.login.httpStatus === 200 &&
      result.login.superadminUiPresent &&
      hasProduction && testIds.includes(26) && testIds.includes(27) &&
      result.oldErp.status === 200 && /\/erp\/login/.test(result.oldErp.finalUrl || '') &&
      result.pageErrors.length === 0 && result.requestFailures.length === 0 && unexpected.length === 0
    ) ? 'PASS' : 'FAIL';
  } catch (error) {
    result.errorClass = error?.name || 'Error';
  } finally {
    fs.writeFileSync(path.join(OUT, 'P15_startup_browser_gate.json'), JSON.stringify(result, null, 2), 'utf8');
    if (context) await context.close().catch(() => {});
    if (browser) await browser.close().catch(() => {});
    console.log(`P15_STARTUP_BROWSER=${result.status}`);
    if (result.status !== 'PASS') process.exitCode = 1;
  }
})().catch(() => {
  console.log('P15_STARTUP_BROWSER=FAIL');
  process.exitCode = 1;
});
