'use strict';
const fs = require('fs');
const { chromium } = require('playwright');

const BASE = 'https://plan-ex.ru/erpv2/';
const OUT = 'P65_runtime_output';
fs.mkdirSync(OUT, { recursive: true });

function credentials() {
  return JSON.parse(Buffer.from(process.env.P12_CREDENTIALS_B64 || '', 'base64').toString('utf8'));
}

async function login(context, cred) {
  const page = await context.newPage();
  await page.goto(BASE + 'login', { waitUntil: 'domcontentloaded', timeout: 30000 });
  await page.locator('input[name="username"],input[name="email"],input[type="email"],input[type="text"]').first().fill(cred.username);
  await page.locator('input[type="password"]').first().fill(cred.password);
  await Promise.all([
    page.waitForLoadState('domcontentloaded', { timeout: 15000 }).catch(() => {}),
    page.locator('button[type="submit"],input[type="submit"]').first().click(),
  ]);
  await page.waitForTimeout(700);
  if (/\/login(?:[/?#]|$)/i.test(page.url())) throw new Error('OWNER login failed');
  return page;
}

async function inspect(page) {
  return page.evaluate(() => {
    const table = document.querySelector('.linear-trip-registry');
    const scroll = table?.closest('.table-scroll');
    const root = document.documentElement;
    const body = document.body;
    const cargos = [...document.querySelectorAll('.linear-trip-registry td.registry-cargo')].map(cell => {
      const style = getComputedStyle(cell);
      const rect = cell.getBoundingClientRect();
      const lh = parseFloat(style.lineHeight) || 0;
      return {
        text: (cell.textContent || '').trim().replace(/\s+/g, ' '),
        width: Math.round(rect.width),
        height: Math.round(rect.height),
        clientWidth: cell.clientWidth,
        scrollWidth: cell.scrollWidth,
        whiteSpace: style.whiteSpace,
        overflowWrap: style.overflowWrap,
        lineHeight: lh,
        estimatedLines: lh > 0 ? Math.round((rect.height / lh) * 10) / 10 : null,
      };
    });
    return {
      viewport: { width: innerWidth, height: innerHeight, dpr: devicePixelRatio },
      table: table ? {
        width: Math.round(table.getBoundingClientRect().width),
        minWidth: getComputedStyle(table).minWidth,
        tableLayout: getComputedStyle(table).tableLayout,
      } : null,
      scroll: scroll ? {
        clientWidth: scroll.clientWidth,
        scrollWidth: scroll.scrollWidth,
        horizontalOverflow: scroll.scrollWidth > scroll.clientWidth + 1,
      } : null,
      body: {
        clientWidth: root.clientWidth,
        scrollWidth: Math.max(root.scrollWidth, body?.scrollWidth || 0),
        horizontalOverflow: Math.max(root.scrollWidth, body?.scrollWidth || 0) > root.clientWidth + 1,
      },
      cargos,
    };
  });
}

(async () => {
  const creds = credentials();
  const owner = creds.find(c => /owner/i.test(String(c.role || ''))) || creds[0];
  if (!owner) throw new Error('No runtime credential found');

  const browser = await chromium.launch({ headless: true });
  const cases = [
    { label: 'FULLHD_100', viewport: { width: 1920, height: 1080 }, deviceScaleFactor: 1 },
    { label: 'FULLHD_125', viewport: { width: 1536, height: 864 }, deviceScaleFactor: 1.25 },
  ];
  const results = [];
  try {
    for (const c of cases) {
      const context = await browser.newContext({
        viewport: c.viewport,
        deviceScaleFactor: c.deviceScaleFactor,
        locale: 'ru-RU',
        timezoneId: 'Europe/Moscow',
        ignoreHTTPSErrors: true,
      });
      const page = await login(context, owner);
      const consoleErrors = [];
      const pageErrors = [];
      page.on('console', m => { if (m.type() === 'error') consoleErrors.push(m.text()); });
      page.on('pageerror', e => pageErrors.push(String(e.message || e)));
      const response = await page.goto(BASE + 'company/trips/linear', { waitUntil: 'domcontentloaded', timeout: 30000 });
      await page.waitForTimeout(700);
      const data = await inspect(page);
      await page.screenshot({ path: `${OUT}/${c.label}.png`, fullPage: false });

      const multiwordCargo = data.cargos.find(x => /\s/.test(x.text) && x.text.length >= 14);
      const failures = [];
      if (response?.status() !== 200) failures.push(`http:${response?.status()}`);
      if (!data.table || !data.scroll) failures.push('registry_missing');
      if (data.scroll?.horizontalOverflow) failures.push(`table_scroll:${data.scroll.scrollWidth}>${data.scroll.clientWidth}`);
      if (data.body.horizontalOverflow) failures.push(`body_scroll:${data.body.scrollWidth}>${data.body.clientWidth}`);
      if (multiwordCargo && multiwordCargo.whiteSpace !== 'normal') failures.push(`cargo_whitespace:${multiwordCargo.whiteSpace}`);
      if (multiwordCargo && multiwordCargo.scrollWidth > multiwordCargo.clientWidth + 1) failures.push(`cargo_overflow:${multiwordCargo.scrollWidth}>${multiwordCargo.clientWidth}`);
      if (consoleErrors.length || pageErrors.length) failures.push(`js_errors:${consoleErrors.length + pageErrors.length}`);

      results.push({ label: c.label, httpStatus: response?.status() || null, data, multiwordCargo, consoleErrors, pageErrors, failures, pass: failures.length === 0 });
      await context.close();
    }
  } finally {
    await browser.close();
  }

  fs.writeFileSync(`${OUT}/P65_RESULT.json`, JSON.stringify(results, null, 2));
  console.log(JSON.stringify(results, null, 2));
  if (results.some(r => !r.pass)) process.exit(1);
})();
