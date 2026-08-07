'use strict';

const fs = require('fs');
const path = require('path');
const { chromium } = require('playwright');

const BASE = 'https://plan-ex.ru/erpv2/';
const OUT = path.resolve(process.env.P19_OUT || 'P19_patch_a_runtime_output');
const SHOTS = path.join(OUT, 'screenshots');
fs.mkdirSync(SHOTS, { recursive: true });

const VIEWPORTS = [
  { width: 1920, height: 1080 },
  { width: 1536, height: 864 },
  { width: 1366, height: 768 },
];

const ROLE_ROUTES = {
  SUPERADMIN: [
    { path: '/superadmin/companies', label: 'superadmin_companies', expected: 'ALLOW' },
    { path: '/superadmin/companies/27', label: 'superadmin_company_view', expected: 'ALLOW' },
    { path: '/superadmin/deleted-data', label: 'superadmin_deleted_data', expected: 'ALLOW' },
  ],
  OWNER: [
    { path: '/company/dashboard', label: 'dashboard', expected: 'ALLOW' },
    { path: '/company/clients', label: 'clients', expected: 'ALLOW' },
    { path: '/company/contractors', label: 'contractors', expected: 'ALLOW' },
    { path: '/company/drivers', label: 'drivers', expected: 'ALLOW' },
    { path: '/company/vehicle-sets', label: 'vehicles', expected: 'ALLOW' },
    { path: '/company/trips/linear', label: 'linear_trips', expected: 'ALLOW' },
    { path: '/company/documents', label: 'documents', expected: 'ALLOW' },
    { path: '/company/finance/operations', label: 'finance_operations', expected: 'ALLOW' },
  ],
  LOGIST: [
    { path: '/company/dashboard', label: 'dashboard', expected: 'ALLOW' },
    { path: '/company/clients', label: 'clients', expected: 'ALLOW' },
    { path: '/company/contractors', label: 'contractors', expected: 'ALLOW' },
    { path: '/company/drivers', label: 'drivers', expected: 'ALLOW' },
    { path: '/company/vehicle-sets', label: 'vehicles', expected: 'ALLOW' },
    { path: '/company/trips/linear', label: 'linear_trips', expected: 'ALLOW' },
    { path: '/company/documents', label: 'documents', expected: 'ALLOW' },
    { path: '/company/finance/operations', label: 'finance_operations_forbidden', expected: 'DENY' },
  ],
};

function url(p) { return new URL(p.replace(/^\//, ''), BASE).href; }
function slug(v) { return String(v).replace(/[^A-Za-z0-9]+/g, '_').replace(/^_|_$/g, ''); }
function credentials() {
  const encoded = process.env.P19_CREDENTIALS_B64 || '';
  if (!encoded) throw new Error('Runtime credential bundle unavailable.');
  const data = JSON.parse(Buffer.from(encoded, 'base64').toString('utf8'));
  if (!Array.isArray(data)) throw new Error('Runtime credential bundle is not an array.');
  return data;
}
function credentialFor(role) {
  const item = credentials().find((x) => String(x && x.role || '').toUpperCase() === role);
  if (!item || !item.username || !item.password) throw new Error(`${role} credential unavailable.`);
  return item;
}
function safeError(err) {
  return String(err && (err.message || err) || 'unknown').replace(/[A-Za-z0-9+/=]{24,}/g, '[REDACTED]').slice(0, 900);
}

async function newContext(browser, viewport) {
  return browser.newContext({
    viewport,
    deviceScaleFactor: 1,
    locale: 'ru-RU',
    timezoneId: 'Europe/Moscow',
    ignoreHTTPSErrors: false,
  });
}

async function login(browser, role, viewport) {
  const cred = credentialFor(role);
  const context = await newContext(browser, viewport);
  const page = await context.newPage();
  await page.goto(url('/login'), { waitUntil: 'domcontentloaded', timeout: 45000 });
  await page.locator('input[name="username"],input[name="email"],input[type="text"]').first().fill(cred.username);
  await page.locator('input[type="password"]').first().fill(cred.password);
  await Promise.all([
    page.waitForLoadState('domcontentloaded', { timeout: 20000 }).catch(() => {}),
    page.locator('button[type="submit"],input[type="submit"]').first().click(),
  ]);
  await page.waitForTimeout(500);
  if (/\/login(?:[/?#]|$)/i.test(page.url())) {
    await context.close();
    throw new Error(`${role} login failed.`);
  }
  return { context, page };
}

function attachDiagnostics(page) {
  const d = { consoleErrors: [], pageErrors: [], requestFailures: [], badResponses: [], assetFailures: [] };
  const onConsole = (m) => { if (m.type() === 'error') d.consoleErrors.push(m.text()); };
  const onPageError = (e) => d.pageErrors.push(String(e && (e.message || e) || 'pageerror'));
  const onRequestFailed = (r) => {
    const row = { method: r.method(), url: r.url(), error: r.failure() && r.failure().errorText || 'failed' };
    d.requestFailures.push(row);
    if (['stylesheet','script','font','image'].includes(r.resourceType())) d.assetFailures.push(row);
  };
  const onResponse = (r) => {
    if (r.status() >= 400) {
      const row = { status: r.status(), url: r.url(), resourceType: r.request().resourceType() };
      d.badResponses.push(row);
      if (['stylesheet','script','font','image'].includes(row.resourceType)) d.assetFailures.push(row);
    }
  };
  page.on('console', onConsole);
  page.on('pageerror', onPageError);
  page.on('requestfailed', onRequestFailed);
  page.on('response', onResponse);
  return {
    data: d,
    detach() {
      page.off('console', onConsole);
      page.off('pageerror', onPageError);
      page.off('requestfailed', onRequestFailed);
      page.off('response', onResponse);
    },
  };
}

async function themeMetrics(page) {
  return page.evaluate(() => {
    const root = getComputedStyle(document.documentElement);
    const accent = root.getPropertyValue('--accent').trim().toLowerCase();
    const links = [...document.querySelectorAll('link[rel="stylesheet"]')].map((x) => x.href);
    const icon = document.querySelector('link[rel~="icon"]')?.getAttribute('href') || '';
    const temp = document.createElement('button');
    temp.className = 'btn btn-primary';
    temp.textContent = 'P19';
    temp.style.position = 'fixed';
    temp.style.left = '-10000px';
    document.body.appendChild(temp);
    const btnStyle = getComputedStyle(temp);
    const button = {
      backgroundColor: btnStyle.backgroundColor,
      backgroundImage: btnStyle.backgroundImage,
      borderColor: btnStyle.borderColor,
      color: btnStyle.color,
    };
    temp.remove();
    const active = document.querySelector('.sidebar .active,.nav-item.active,.nav-sub-item.active,[aria-current="page"]');
    const activeStyle = active ? getComputedStyle(active) : null;
    const activeNav = activeStyle ? {
      present: true,
      backgroundColor: activeStyle.backgroundColor,
      borderLeftColor: activeStyle.borderLeftColor,
      color: activeStyle.color,
    } : { present: false };
    let userCardRule = null;
    let linearRemoveRule = null;
    for (const sheet of [...document.styleSheets]) {
      let rules = [];
      try { rules = [...(sheet.cssRules || [])]; } catch (_) { continue; }
      for (const rule of rules) {
        if (rule.selectorText && rule.selectorText.includes('.user-view-card')) {
          userCardRule = { background: rule.style.background, borderRadius: rule.style.borderRadius };
        }
        if (rule.selectorText && rule.selectorText.includes('.linear-trip-row-remove')) {
          linearRemoveRule = { color: rule.style.color };
        }
      }
    }
    const card = document.querySelector('.user-view-card');
    const cardStyle = card ? getComputedStyle(card) : null;
    return {
      accent,
      button,
      activeNav,
      favicon: icon,
      loadedStylesheets: links,
      p16Loaded: links.some((x) => /p16-fullhd\.css/i.test(x)),
      p17Loaded: links.some((x) => /p17-corrections\.css/i.test(x)),
      greenTokenInHead: /#0f766e|%230f766e|rgb\(15,\s*118,\s*110\)/i.test(document.head.innerHTML),
      userCardRule,
      userCardComputed: cardStyle ? { backgroundColor: cardStyle.backgroundColor, borderRadius: cardStyle.borderRadius } : null,
      linearRemoveRule,
    };
  });
}

async function layoutMetrics(page) {
  return page.evaluate(() => ({
    scrollWidth: document.documentElement.scrollWidth,
    clientWidth: document.documentElement.clientWidth,
    horizontalOverflow: Math.max(0, document.documentElement.scrollWidth - document.documentElement.clientWidth),
    title: document.title,
    h1: document.querySelector('h1,h2,.page-title')?.textContent?.trim() || '',
  }));
}

async function ownerInteractionSmoke(page) {
  const result = { search: 'NOT_RUN', modal: 'NOT_RUN', error: null };
  try {
    await page.goto(url('/company/clients'), { waitUntil: 'domcontentloaded', timeout: 45000 });
    await page.waitForTimeout(250);
    const search = page.locator('input[type="search"],input[name="q"],input[placeholder*="поиск" i],input[placeholder*="найти" i]').first();
    if (await search.count()) {
      await search.fill('P19_RUNTIME_SMOKE_NO_MATCH');
      await page.waitForTimeout(150);
      await search.fill('');
      result.search = 'PASS';
    } else {
      result.search = 'FAIL_CONTROL_NOT_FOUND';
    }
    const trigger = page.locator('button,a').filter({ hasText: /Создать клиента|Добавить клиента/i }).first();
    if (await trigger.count()) {
      await trigger.click();
      await page.waitForTimeout(300);
      const dialog = page.locator('[role="dialog"],.modal-overlay,.modal-shell,.modal').filter({ visible: true }).first();
      const visible = await dialog.count() ? await dialog.isVisible().catch(() => false) : false;
      if (visible) {
        const close = page.locator('button[aria-label*="Закр" i],.modal-close,button').filter({ hasText: /Закрыть|Отмена/i }).first();
        if (await close.count()) await close.click().catch(() => {}); else await page.keyboard.press('Escape');
        await page.waitForTimeout(200);
        const stillVisible = await dialog.isVisible().catch(() => false);
        result.modal = stillVisible ? 'FAIL_CLOSE' : 'PASS';
      } else {
        result.modal = 'FAIL_OPEN';
      }
    } else {
      result.modal = 'FAIL_TRIGGER_NOT_FOUND';
    }
  } catch (e) {
    result.error = safeError(e);
  }
  return result;
}

async function anonymousLoginAudit(browser, viewport, sequence, aggregate) {
  const context = await newContext(browser, viewport);
  const page = await context.newPage();
  const diag = attachDiagnostics(page);
  let response = null;
  let navigationError = null;
  try {
    response = await page.goto(url('/login'), { waitUntil: 'networkidle', timeout: 45000 });
  } catch (e) { navigationError = safeError(e); }
  const layout = await layoutMetrics(page).catch(() => ({ horizontalOverflow: 9999 }));
  const theme = await themeMetrics(page).catch(() => null);
  const shot = `P19A_${String(sequence).padStart(3,'0')}_ANON_login_${viewport.width}x${viewport.height}.png`;
  await page.screenshot({ path: path.join(SHOTS, shot), fullPage: false }).catch(() => {});
  diag.detach();
  const pass = response?.status() === 200 && !navigationError && layout.horizontalOverflow <= 2 && diag.data.consoleErrors.length === 0 && diag.data.pageErrors.length === 0 && diag.data.requestFailures.length === 0 && diag.data.badResponses.length === 0 && diag.data.assetFailures.length === 0;
  aggregate.push({ role: 'ANONYMOUS', route: '/login', expected: 'ALLOW', viewport: `${viewport.width}x${viewport.height}`, httpStatus: response?.status() ?? null, finalUrl: page.url(), navigationError, ...layout, theme, diagnostics: diag.data, screenshot: `screenshots/${shot}`, pass });
  await context.close();
}

async function routeAudit(session, role, target, viewport, sequence, aggregate) {
  const page = session.page;
  const diag = attachDiagnostics(page);
  let response = null;
  let navigationError = null;
  try {
    response = await page.goto(url(target.path), { waitUntil: 'networkidle', timeout: 50000 });
  } catch (e) { navigationError = safeError(e); }
  await page.waitForTimeout(150);
  const layout = await layoutMetrics(page).catch(() => ({ horizontalOverflow: 9999 }));
  const theme = await themeMetrics(page).catch(() => null);
  const shot = `P19A_${String(sequence).padStart(3,'0')}_${role}_${slug(target.label)}_${viewport.width}x${viewport.height}.png`;
  await page.screenshot({ path: path.join(SHOTS, shot), fullPage: false }).catch(() => {});
  diag.detach();
  const status = response?.status() ?? null;
  const expectedDeny = target.expected === 'DENY';
  const expectedHttpRows = expectedDeny ? diag.data.badResponses.filter((x) => x.status === 403 && x.url.includes(target.path)) : [];
  const unexpectedBad = diag.data.badResponses.filter((x) => !expectedHttpRows.includes(x));
  const authPass = expectedDeny ? (status === 403 || /403|доступ запрещ|forbidden/i.test(await page.locator('body').innerText().catch(() => ''))) : status === 200;
  const brown = theme && theme.accent === '#7c4718' && !theme.p16Loaded && !theme.p17Loaded && !theme.greenTokenInHead && /%237c4718|#7c4718/i.test(theme.favicon) && !/rgb\(15,\s*118,\s*110\)|#0f766e/i.test(JSON.stringify(theme.button));
  const cssRulesPass = theme && theme.userCardRule && theme.userCardRule.borderRadius === '2px' && /var\(--surface-strong\)/.test(theme.userCardRule.background || '') && theme.linearRemoveRule && /var\(--text-main\)/.test(theme.linearRemoveRule.color || '');
  const pass = authPass && !navigationError && layout.horizontalOverflow <= 2 && diag.data.consoleErrors.length === 0 && diag.data.pageErrors.length === 0 && diag.data.requestFailures.length === 0 && unexpectedBad.length === 0 && diag.data.assetFailures.length === 0 && brown && cssRulesPass;
  aggregate.push({ role, route: target.path, expected: target.expected, viewport: `${viewport.width}x${viewport.height}`, httpStatus: status, finalUrl: page.url(), navigationError, ...layout, theme, diagnostics: { ...diag.data, unexpectedBadResponses: unexpectedBad }, screenshot: `screenshots/${shot}`, pass });
}

(async () => {
  const result = {
    status: 'FAIL',
    deployedSha: process.env.P19_DEPLOYED_SHA || null,
    seniorLogist: 'NOT_TESTED_CREDENTIAL_UNAVAILABLE',
    routes: [],
    interactionSmoke: null,
    summary: {},
    fatal: null,
  };
  let browser;
  let sequence = 1;
  try {
    browser = await chromium.launch({ headless: true });
    for (const viewport of VIEWPORTS) {
      await anonymousLoginAudit(browser, viewport, sequence++, result.routes);
      for (const role of ['SUPERADMIN','OWNER','LOGIST']) {
        const session = await login(browser, role, viewport);
        for (const target of ROLE_ROUTES[role]) {
          await routeAudit(session, role, target, viewport, sequence++, result.routes);
        }
        if (role === 'OWNER' && viewport.width === 1920) {
          result.interactionSmoke = await ownerInteractionSmoke(session.page);
        }
        await session.context.close();
      }
    }
    const allPass = result.routes.every((x) => x.pass);
    const roleSummary = {};
    for (const role of ['ANONYMOUS','SUPERADMIN','OWNER','LOGIST']) {
      const rows = result.routes.filter((x) => x.role === role);
      roleSummary[role] = { total: rows.length, pass: rows.filter((x) => x.pass).length, fail: rows.filter((x) => !x.pass).length };
    }
    const unexpectedConsole = result.routes.reduce((n, x) => n + x.diagnostics.consoleErrors.length, 0);
    const pageErrors = result.routes.reduce((n, x) => n + x.diagnostics.pageErrors.length, 0);
    const requestFailures = result.routes.reduce((n, x) => n + x.diagnostics.requestFailures.length, 0);
    const assetFailures = result.routes.reduce((n, x) => n + x.diagnostics.assetFailures.length, 0);
    const unexpectedHttp = result.routes.reduce((n, x) => n + (x.diagnostics.unexpectedBadResponses || []).length, 0);
    result.summary = { roleSummary, total: result.routes.length, pass: result.routes.filter((x) => x.pass).length, fail: result.routes.filter((x) => !x.pass).length, unexpectedConsole, pageErrors, requestFailures, assetFailures, unexpectedHttp, interactionSmoke: result.interactionSmoke };
    const interactionPass = result.interactionSmoke && result.interactionSmoke.search === 'PASS' && result.interactionSmoke.modal === 'PASS' && !result.interactionSmoke.error;
    result.status = allPass && interactionPass && unexpectedConsole === 0 && pageErrors === 0 && requestFailures === 0 && assetFailures === 0 && unexpectedHttp === 0 ? 'PASS' : 'FAIL';
  } catch (e) {
    result.fatal = safeError(e);
  } finally {
    if (browser) await browser.close().catch(() => {});
    fs.writeFileSync(path.join(OUT, 'P19_06_PATCH_A_BROWSER_MATRIX.json'), JSON.stringify(result.routes, null, 2));
    fs.writeFileSync(path.join(OUT, 'P19_07_PATCH_A_RUNTIME_SUMMARY.json'), JSON.stringify(result, null, 2));
    fs.writeFileSync(path.join(OUT, 'P19_08_PATCH_A_CONSOLE_NETWORK.json'), JSON.stringify({ status: result.status, summary: result.summary, fatal: result.fatal }, null, 2));
    console.log(`P19_PATCH_A_RUNTIME=${result.status}`);
    process.exitCode = result.status === 'PASS' ? 0 : 1;
  }
})().catch((e) => {
  fs.mkdirSync(OUT, { recursive: true });
  fs.writeFileSync(path.join(OUT, 'P19_07_PATCH_A_RUNTIME_SUMMARY.json'), JSON.stringify({ status: 'FAIL', fatal: safeError(e) }, null, 2));
  console.log('P19_PATCH_A_RUNTIME=FAIL');
  process.exitCode = 1;
});
