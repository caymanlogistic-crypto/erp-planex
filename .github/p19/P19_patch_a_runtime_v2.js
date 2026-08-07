'use strict';

const fs = require('fs');
const path = require('path');
const { chromium } = require('playwright');

const BASE = 'https://plan-ex.ru/erpv2/';
const OUT = path.resolve(process.env.P19_OUT || 'P19_patch_a_runtime_v2_output');
const SHOTS = path.join(OUT, 'screenshots');
fs.mkdirSync(SHOTS, { recursive: true });

const VIEWPORTS = [
  { width: 1920, height: 1080 },
  { width: 1536, height: 864 },
  { width: 1366, height: 768 },
];
const ROUTES = {
  SUPERADMIN: [
    ['/superadmin/companies', 'superadmin_companies', 'ALLOW'],
    ['/superadmin/companies/27', 'superadmin_company_view', 'ALLOW'],
    ['/superadmin/deleted-data', 'superadmin_deleted_data', 'ALLOW'],
  ],
  OWNER: [
    ['/company/dashboard', 'dashboard', 'ALLOW'],
    ['/company/clients', 'clients', 'ALLOW'],
    ['/company/contractors', 'contractors', 'ALLOW'],
    ['/company/drivers', 'drivers', 'ALLOW'],
    ['/company/vehicle-sets', 'vehicles', 'ALLOW'],
    ['/company/trips/linear', 'linear_trips', 'ALLOW'],
    ['/company/documents', 'documents', 'ALLOW'],
    ['/company/finance/operations', 'finance_operations', 'ALLOW'],
  ],
  LOGIST: [
    ['/company/dashboard', 'dashboard', 'ALLOW'],
    ['/company/clients', 'clients', 'ALLOW'],
    ['/company/contractors', 'contractors', 'ALLOW'],
    ['/company/drivers', 'drivers', 'ALLOW'],
    ['/company/vehicle-sets', 'vehicles', 'ALLOW'],
    ['/company/trips/linear', 'linear_trips', 'ALLOW'],
    ['/company/documents', 'documents', 'ALLOW'],
    ['/company/finance/operations', 'finance_operations_forbidden', 'DENY'],
  ],
};

function U(p) { return new URL(String(p).replace(/^\//, ''), BASE).href; }
function slug(v) { return String(v).replace(/[^A-Za-z0-9]+/g, '_').replace(/^_|_$/g, ''); }
function safeError(e) { return String(e && (e.message || e) || 'unknown').replace(/[A-Za-z0-9+/=]{24,}/g, '[REDACTED]').slice(0, 900); }
function bundle() {
  const b64 = process.env.P19_CREDENTIALS_B64 || '';
  if (!b64) throw new Error('Credential bundle unavailable.');
  const v = JSON.parse(Buffer.from(b64, 'base64').toString('utf8'));
  if (!Array.isArray(v)) throw new Error('Credential bundle invalid.');
  return v;
}
function credential(role) {
  const v = bundle().find(x => String(x && x.role || '').toUpperCase() === role);
  if (!v || !v.username || !v.password) throw new Error(`${role} credential unavailable.`);
  return v;
}
async function context(browser, viewport) {
  return browser.newContext({ viewport, deviceScaleFactor: 1, locale: 'ru-RU', timezoneId: 'Europe/Moscow', ignoreHTTPSErrors: false });
}
async function login(browser, role, viewport) {
  const c = await context(browser, viewport);
  const page = await c.newPage();
  const cr = credential(role);
  await page.goto(U('/login'), { waitUntil: 'domcontentloaded', timeout: 45000 });
  await page.locator('input[name="username"],input[name="email"],input[type="text"]').first().fill(cr.username);
  await page.locator('input[type="password"]').first().fill(cr.password);
  await Promise.all([
    page.waitForLoadState('domcontentloaded', { timeout: 20000 }).catch(() => {}),
    page.locator('button[type="submit"],input[type="submit"]').first().click(),
  ]);
  await page.waitForTimeout(400);
  if (/\/login(?:[/?#]|$)/i.test(page.url())) { await c.close(); throw new Error(`${role} login failed.`); }
  return { context: c, page };
}

function diagnostics(page) {
  const d = { consoleErrors: [], pageErrors: [], requestFailures: [], badResponses: [], assetFailures: [] };
  const oc = m => { if (m.type() === 'error') d.consoleErrors.push(m.text()); };
  const oe = e => d.pageErrors.push(String(e && (e.message || e) || 'pageerror'));
  const of = r => {
    const x = { method: r.method(), url: r.url(), resourceType: r.resourceType(), error: r.failure() && r.failure().errorText || 'failed' };
    d.requestFailures.push(x);
    if (['stylesheet','script','font','image'].includes(x.resourceType)) d.assetFailures.push(x);
  };
  const or = r => {
    if (r.status() >= 400) {
      const x = { status: r.status(), url: r.url(), resourceType: r.request().resourceType() };
      d.badResponses.push(x);
      if (['stylesheet','script','font','image'].includes(x.resourceType)) d.assetFailures.push(x);
    }
  };
  page.on('console', oc); page.on('pageerror', oe); page.on('requestfailed', of); page.on('response', or);
  return { data: d, detach() { page.off('console', oc); page.off('pageerror', oe); page.off('requestfailed', of); page.off('response', or); } };
}

async function metrics(page) {
  return page.evaluate(() => {
    const root = getComputedStyle(document.documentElement);
    const cssLinks = [...document.querySelectorAll('link[rel="stylesheet"]')].map(x => x.href);
    const favicon = document.querySelector('link[rel~="icon"]')?.getAttribute('href') || '';
    const probe = document.createElement('button');
    probe.className = 'btn btn-primary'; probe.textContent = 'P19';
    probe.style.cssText = 'position:fixed;left:-10000px;top:-10000px';
    document.body.appendChild(probe);
    const bs = getComputedStyle(probe);
    const primary = { backgroundColor: bs.backgroundColor, backgroundImage: bs.backgroundImage, borderColor: bs.borderColor, color: bs.color };
    probe.remove();
    const active = document.querySelector('.nav-item.is-active,.nav-sub-item.is-active,[aria-current="page"]');
    const as = active ? getComputedStyle(active) : null;
    const activeNav = as ? { present: true, className: active.className, backgroundColor: as.backgroundColor, borderLeftColor: as.borderLeftColor, color: as.color } : { present: false };
    let userCardRule = null, linearRemoveRule = null;
    for (const sheet of [...document.styleSheets]) {
      let rules = [];
      try { rules = [...(sheet.cssRules || [])]; } catch (_) { continue; }
      for (const r of rules) {
        if (r.selectorText === '.user-view-card') userCardRule = { background: r.style.background, borderRadius: r.style.borderRadius };
        if (r.selectorText === '.linear-trip-row-remove') linearRemoveRule = { color: r.style.color };
      }
    }
    const html = document.documentElement;
    return {
      title: document.title,
      h1: document.querySelector('h1,h2,.page-title')?.textContent?.trim() || '',
      scrollWidth: html.scrollWidth,
      clientWidth: html.clientWidth,
      horizontalOverflow: Math.max(0, html.scrollWidth - html.clientWidth),
      accent: root.getPropertyValue('--accent').trim().toLowerCase(),
      primary,
      favicon,
      activeNav,
      loadedStylesheets: cssLinks,
      p16Loaded: cssLinks.some(x => /p16-fullhd\.css/i.test(x)),
      p17Loaded: cssLinks.some(x => /p17-corrections\.css/i.test(x)),
      greenTokenInHead: /#0f766e|%230f766e|rgb\(15,\s*118,\s*110\)/i.test(document.head.innerHTML),
      userCardRule,
      linearRemoveRule,
    };
  });
}

function themePass(m) {
  if (!m) return false;
  const primaryText = JSON.stringify(m.primary || {});
  return m.accent === '#7c4718'
    && !m.p16Loaded && !m.p17Loaded && !m.greenTokenInHead
    && /%237c4718|#7c4718/i.test(m.favicon)
    && !/rgb\(15,\s*118,\s*110\)|#0f766e/i.test(primaryText)
    && m.userCardRule && m.userCardRule.background === 'var(--surface-strong)' && m.userCardRule.borderRadius === '2px'
    && m.linearRemoveRule && m.linearRemoveRule.color === 'var(--text-main)';
}

async function auditAnonymous(browser, viewport, seq, rows) {
  const c = await context(browser, viewport); const page = await c.newPage(); const dg = diagnostics(page);
  let response = null, navigationError = null;
  try { response = await page.goto(U('/login'), { waitUntil: 'networkidle', timeout: 45000 }); } catch (e) { navigationError = safeError(e); }
  const m = await metrics(page).catch(() => null);
  const shot = `P19A2_${String(seq).padStart(3,'0')}_ANON_login_${viewport.width}x${viewport.height}.png`;
  await page.screenshot({ path: path.join(SHOTS, shot), fullPage: false }).catch(() => {}); dg.detach();
  const pass = response?.status() === 200 && !navigationError && m && m.horizontalOverflow <= 2 && dg.data.consoleErrors.length === 0 && dg.data.pageErrors.length === 0 && dg.data.requestFailures.length === 0 && dg.data.badResponses.length === 0 && dg.data.assetFailures.length === 0;
  rows.push({ role:'ANONYMOUS', route:'/login', expected:'ALLOW', viewport:`${viewport.width}x${viewport.height}`, httpStatus:response?.status()??null, finalUrl:page.url(), navigationError, metrics:m, diagnostics:dg.data, screenshot:`screenshots/${shot}`, pass });
  await c.close();
}

async function auditRoute(session, role, spec, viewport, seq, rows) {
  const [route, label, expected] = spec; const page = session.page; const dg = diagnostics(page);
  let response = null, navigationError = null;
  try { response = await page.goto(U(route), { waitUntil: 'networkidle', timeout: 50000 }); } catch (e) { navigationError = safeError(e); }
  await page.waitForTimeout(100);
  const m = await metrics(page).catch(() => null);
  const shot = `P19A2_${String(seq).padStart(3,'0')}_${role}_${slug(label)}_${viewport.width}x${viewport.height}.png`;
  await page.screenshot({ path:path.join(SHOTS,shot), fullPage:false }).catch(() => {}); dg.detach();
  const status = response?.status() ?? null; const deny = expected === 'DENY';
  const expectedHttp = deny ? dg.data.badResponses.filter(x => x.status === 403 && x.resourceType === 'document' && x.url.includes(route)) : [];
  const unexpectedHttp = dg.data.badResponses.filter(x => !expectedHttp.includes(x));
  const unexpectedConsole = deny ? dg.data.consoleErrors.filter(x => !/status of 403|\b403\b/i.test(x)) : dg.data.consoleErrors.slice();
  const authPass = deny ? status === 403 : status === 200;
  const activeRequired = role !== 'SUPERADMIN' && expected === 'ALLOW';
  const activePass = !activeRequired || (m && m.activeNav && m.activeNav.present);
  const pass = authPass && !navigationError && m && m.horizontalOverflow <= 2 && themePass(m) && activePass
    && unexpectedConsole.length === 0 && dg.data.pageErrors.length === 0 && dg.data.requestFailures.length === 0 && unexpectedHttp.length === 0 && dg.data.assetFailures.length === 0;
  rows.push({ role, route, expected, viewport:`${viewport.width}x${viewport.height}`, httpStatus:status, finalUrl:page.url(), navigationError, metrics:m, diagnostics:{...dg.data, expectedHttp, unexpectedHttp, unexpectedConsole}, screenshot:`screenshots/${shot}`, pass });
}

async function interactionSmoke(page, viewport) {
  const r = { viewport:`${viewport.width}x${viewport.height}`, search:'FAIL', modal:'FAIL', activeNavigation:'FAIL', error:null };
  try {
    await page.goto(U('/company/clients'), { waitUntil:'domcontentloaded', timeout:45000 }); await page.waitForTimeout(250);
    const active = page.locator('a.nav-item.is-active[href$="/company/clients"]').first();
    if (await active.count() && await active.isVisible()) {
      const s = await active.evaluate(el => { const x=getComputedStyle(el); return {backgroundColor:x.backgroundColor,borderLeftColor:x.borderLeftColor,color:x.color}; });
      r.activeNavigation = !/rgb\(15,\s*118,\s*110\)|#0f766e/i.test(JSON.stringify(s)) ? 'PASS' : 'FAIL_GREEN';
      r.activeNavigationStyle = s;
    }
    const search = page.locator('input.toolbar-search[placeholder="Поиск по таблице"]').first();
    if (await search.count()) {
      const rows = page.locator('[data-erp-grid] tbody tr');
      const before = await rows.count();
      await search.fill('P19_RUNTIME_SMOKE_NO_MATCH_8C71'); await page.waitForTimeout(200);
      const visibleAfter = await rows.evaluateAll(ns => ns.filter(n => getComputedStyle(n).display !== 'none' && n.getClientRects().length).length);
      await search.fill(''); await page.waitForTimeout(200);
      const visibleRestored = await rows.evaluateAll(ns => ns.filter(n => getComputedStyle(n).display !== 'none' && n.getClientRects().length).length);
      r.search = before > 0 && visibleAfter === 0 && visibleRestored === before ? 'PASS' : 'FAIL_FILTER_BEHAVIOR';
      r.searchCounts = { before, visibleAfter, visibleRestored };
    } else r.search = 'FAIL_CONTROL_NOT_FOUND';
    const trigger = page.locator('button[onclick*="openModal"][onclick*="le-client-modal"]').first();
    const modal = page.locator('#le-client-modal');
    if (await trigger.count() && await modal.count()) {
      await trigger.click(); await page.waitForTimeout(250);
      const opened = await modal.isVisible().catch(() => false);
      const close = modal.locator('.modal-close').first();
      if (opened && await close.count()) {
        await close.click(); await page.waitForTimeout(200);
        const closed = !(await modal.isVisible().catch(() => false));
        r.modal = closed ? 'PASS' : 'FAIL_CLOSE';
      } else r.modal = opened ? 'FAIL_CLOSE_CONTROL' : 'FAIL_OPEN';
    } else r.modal = 'FAIL_TRIGGER_OR_MODAL_NOT_FOUND';
  } catch (e) { r.error = safeError(e); }
  return r;
}

(async () => {
  const result = { status:'FAIL', deployedSha:process.env.P19_DEPLOYED_SHA||null, seniorLogist:'NOT_TESTED_CREDENTIAL_UNAVAILABLE', routes:[], interactions:[], summary:{}, fatal:null };
  let browser, seq=1;
  try {
    browser = await chromium.launch({headless:true});
    for (const vp of VIEWPORTS) {
      await auditAnonymous(browser,vp,seq++,result.routes);
      for (const role of ['SUPERADMIN','OWNER','LOGIST']) {
        const s = await login(browser,role,vp);
        for (const spec of ROUTES[role]) await auditRoute(s,role,spec,vp,seq++,result.routes);
        if (role === 'OWNER') result.interactions.push(await interactionSmoke(s.page,vp));
        await s.context.close();
      }
    }
    const unexpectedConsole = result.routes.reduce((n,x)=>n+(x.diagnostics.unexpectedConsole||x.diagnostics.consoleErrors||[]).length,0);
    const pageErrors = result.routes.reduce((n,x)=>n+(x.diagnostics.pageErrors||[]).length,0);
    const requestFailures = result.routes.reduce((n,x)=>n+(x.diagnostics.requestFailures||[]).length,0);
    const unexpectedHttp = result.routes.reduce((n,x)=>n+(x.diagnostics.unexpectedHttp||x.diagnostics.badResponses||[]).length,0);
    const assetFailures = result.routes.reduce((n,x)=>n+(x.diagnostics.assetFailures||[]).length,0);
    const roleSummary = {};
    for (const role of ['ANONYMOUS','SUPERADMIN','OWNER','LOGIST']) { const a=result.routes.filter(x=>x.role===role); roleSummary[role]={total:a.length,pass:a.filter(x=>x.pass).length,fail:a.filter(x=>!x.pass).length}; }
    const interactionsPass = result.interactions.length === 3 && result.interactions.every(x=>x.search==='PASS'&&x.modal==='PASS'&&x.activeNavigation==='PASS'&&!x.error);
    result.summary = { total:result.routes.length, pass:result.routes.filter(x=>x.pass).length, fail:result.routes.filter(x=>!x.pass).length, roleSummary, unexpectedConsole, pageErrors, requestFailures, unexpectedHttp, assetFailures, interactionsPass };
    result.status = result.summary.fail===0 && interactionsPass && unexpectedConsole===0 && pageErrors===0 && requestFailures===0 && unexpectedHttp===0 && assetFailures===0 ? 'PASS' : 'FAIL';
  } catch (e) { result.fatal=safeError(e); }
  finally {
    if (browser) await browser.close().catch(()=>{});
    fs.writeFileSync(path.join(OUT,'P19_16_PATCH_A_BROWSER_MATRIX_V2.json'),JSON.stringify(result.routes,null,2));
    fs.writeFileSync(path.join(OUT,'P19_17_PATCH_A_INTERACTIONS_V2.json'),JSON.stringify(result.interactions,null,2));
    fs.writeFileSync(path.join(OUT,'P19_18_PATCH_A_RUNTIME_SUMMARY_V2.json'),JSON.stringify(result,null,2));
    console.log(`P19_PATCH_A_RUNTIME_V2=${result.status}`);
    process.exitCode=result.status==='PASS'?0:1;
  }
})().catch(e=>{ console.error(safeError(e)); process.exitCode=1; });
