'use strict';

const fs = require('fs');
const path = require('path');
const { chromium } = require('playwright');

const BASE_URL = (process.env.P12_BASE_URL || 'https://plan-ex.ru/erpv2/').replace(/\/+$/, '/');
const OUT_DIR = path.resolve(process.env.P12_OUT_DIR || 'P12_output');
const SCREEN_DIR = path.join(OUT_DIR, 'P12_screenshots');
const MAX_PATTERNS = Number(process.env.P12_MAX_PATTERNS || 120);
const VIEWPORT = { width: 1920, height: 1080 };

fs.mkdirSync(SCREEN_DIR, { recursive: true });

function decodeCredentials() {
  const encoded = process.env.P12_CREDENTIALS_B64 || '';
  if (!encoded) return [];
  try {
    const parsed = JSON.parse(Buffer.from(encoded, 'base64').toString('utf8'));
    return Array.isArray(parsed) ? parsed.filter((x) => x && x.role && x.username && x.password) : [];
  } catch (_) {
    return [];
  }
}

function slug(value) {
  return String(value || 'page')
    .toLowerCase()
    .replace(/^https?:\/\//, '')
    .replace(/[^a-z0-9а-яё]+/giu, '_')
    .replace(/^_+|_+$/g, '')
    .slice(0, 110) || 'page';
}

function normalizeUrl(raw) {
  const url = new URL(raw, BASE_URL);
  url.hash = '';
  return url.href;
}

function routePattern(raw) {
  try {
    const url = new URL(raw, BASE_URL);
    const normalizedPath = url.pathname
      .replace(/\/\d+(?=\/|$)/g, '/{id}')
      .replace(/\/[0-9a-f]{8}-[0-9a-f-]{27,}(?=\/|$)/gi, '/{uuid}');
    const params = [...url.searchParams.entries()]
      .filter(([key]) => !/token|csrf|signature|password|secret/i.test(key))
      .map(([key, value]) => {
        const safeValue = /^\d+$/.test(value) ? '{id}' : value.slice(0, 40);
        return `${key}=${safeValue}`;
      })
      .sort();
    return `${normalizedPath}${params.length ? '?' + params.join('&') : ''}`;
  } catch (_) {
    return String(raw);
  }
}

function isSafePageUrl(raw) {
  try {
    const url = new URL(raw, BASE_URL);
    const base = new URL(BASE_URL);
    if (url.origin !== base.origin) return false;
    const basePath = base.pathname.replace(/\/$/, '');
    if (!url.pathname.startsWith(basePath)) return false;
    if (/\/(logout|delete|destroy|remove|archive|restore|purge|hard-delete|execute|refresh-from-mail|import|sync|cancel|block|deactivate|reset-password)(\/|$)/i.test(url.pathname)) return false;
    if (/\/(download|file|export)(\/|$)/i.test(url.pathname)) return false;
    if ([...url.searchParams.keys()].some((key) => /token|csrf|password|secret|delete|destroy/i.test(key))) return false;
    return true;
  } catch (_) {
    return false;
  }
}

function csvEscape(value) {
  const text = typeof value === 'string' ? value : JSON.stringify(value ?? '');
  return `"${String(text).replace(/"/g, '""')}"`;
}

async function layoutEvidence(page) {
  return page.evaluate(() => {
    function visibleSelf(element) {
      if (!(element instanceof Element)) return false;
      const style = getComputedStyle(element);
      const rect = element.getBoundingClientRect();
      return style.display !== 'none'
        && style.visibility !== 'hidden'
        && Number(style.opacity || 1) > 0.05
        && rect.width > 1
        && rect.height > 1;
    }

    function visibleWithAncestors(element) {
      if (!visibleSelf(element)) return false;
      let current = element.parentElement;
      while (current && current !== document.body) {
        const style = getComputedStyle(current);
        if (style.display === 'none' || style.visibility === 'hidden' || Number(style.opacity || 1) <= 0.05) return false;
        current = current.parentElement;
      }
      return true;
    }

    function topmostBlockerAt(element, x, y) {
      const hit = document.elementFromPoint(x, y);
      if (!(hit instanceof Element)) return null;
      if (hit === element || element.contains(hit) || hit.contains(element)) return null;
      if (!visibleWithAncestors(hit)) return null;
      const style = getComputedStyle(hit);
      if (style.pointerEvents === 'none' || Number(style.opacity || 1) <= 0.2) return null;
      return hit;
    }

    function labelOf(element) {
      return (element.getAttribute('aria-label')
        || element.getAttribute('title')
        || element.textContent
        || element.getAttribute('name')
        || element.tagName).trim().replace(/\s+/g, ' ').slice(0, 120);
    }

    const root = document.documentElement;
    const body = document.body;
    const viewportWidth = window.innerWidth;
    const viewportHeight = window.innerHeight;
    const bodyScrollWidth = Math.max(root?.scrollWidth || 0, body?.scrollWidth || 0);
    const bodyHorizontalScroll = bodyScrollWidth > viewportWidth + 1;

    const candidates = [...document.querySelectorAll(
      'a[href],button,input:not([type="hidden"]),select,textarea,[role="button"],[role="combobox"],[tabindex]:not([tabindex="-1"]),h1,h2,h3,th,td'
    )].filter((element) => {
      if (!visibleWithAncestors(element)) return false;
      const rect = element.getBoundingClientRect();
      return rect.bottom > 0 && rect.right > 0 && rect.top < viewportHeight && rect.left < viewportWidth;
    }).slice(0, 700);

    let clippedControls = 0;
    const clippedSamples = [];
    let overlapFailures = 0;
    const overlapSamples = [];

    for (const element of candidates) {
      const rect = element.getBoundingClientRect();
      const interactive = element.matches('a[href],button,input,select,textarea,[role="button"],[role="combobox"],[tabindex]');
      if (interactive && (rect.left < -1 || rect.right > viewportWidth + 1 || rect.top < -1 || rect.bottom > viewportHeight + 1)) {
        clippedControls += 1;
        if (clippedSamples.length < 12) clippedSamples.push(labelOf(element));
      }

      if (rect.width < 12 || rect.height < 12) continue;
      const insetX = Math.min(6, rect.width / 4);
      const insetY = Math.min(6, rect.height / 4);
      const points = [
        [rect.left + rect.width / 2, rect.top + rect.height / 2],
        [rect.left + insetX, rect.top + insetY],
        [rect.right - insetX, rect.top + insetY],
        [rect.left + insetX, rect.bottom - insetY],
        [rect.right - insetX, rect.bottom - insetY]
      ].filter(([x, y]) => x >= 0 && y >= 0 && x < viewportWidth && y < viewportHeight);

      const blockerCounts = new Map();
      for (const [x, y] of points) {
        const blocker = topmostBlockerAt(element, x, y);
        if (!blocker) continue;
        blockerCounts.set(blocker, (blockerCounts.get(blocker) || 0) + 1);
      }
      let strongest = null;
      let strongestCount = 0;
      for (const [blocker, count] of blockerCounts.entries()) {
        if (count > strongestCount) {
          strongest = blocker;
          strongestCount = count;
        }
      }
      const required = Math.max(2, Math.ceil(points.length * 0.6));
      if (strongest && strongestCount >= required) {
        overlapFailures += 1;
        if (overlapSamples.length < 12) {
          overlapSamples.push({
            target: labelOf(element),
            blocker: labelOf(strongest),
            blockedPoints: strongestCount,
            sampledPoints: points.length
          });
        }
      }
    }

    const tables = [...document.querySelectorAll('table')].filter(visibleWithAncestors).map((table) => {
      let parent = table.parentElement;
      let scrollContainer = null;
      while (parent && parent !== body) {
        const style = getComputedStyle(parent);
        if (/(auto|scroll)/.test(style.overflowX)) {
          scrollContainer = parent;
          break;
        }
        parent = parent.parentElement;
      }
      return {
        rows: table.rows.length,
        columns: table.rows[0]?.cells.length || 0,
        width: Math.round(table.getBoundingClientRect().width),
        hasHorizontalContainer: Boolean(scrollContainer),
        containerScrollWidth: scrollContainer ? scrollContainer.scrollWidth : null,
        containerClientWidth: scrollContainer ? scrollContainer.clientWidth : null
      };
    });

    const forms = [...document.forms].filter(visibleWithAncestors).map((form) => ({
      method: (form.method || 'get').toUpperCase(),
      action: form.action || location.href,
      fields: form.querySelectorAll('input,select,textarea').length,
      submitButtons: form.querySelectorAll('button[type="submit"],input[type="submit"]').length
    }));

    return {
      bodyHorizontalScroll,
      bodyScrollWidth,
      viewportWidth,
      viewportHeight,
      clippedControls,
      clippedSamples,
      overlapFailures,
      overlapSamples,
      tables,
      forms,
      selects: [...document.querySelectorAll('select')].filter(visibleWithAncestors).length,
      buttons: [...document.querySelectorAll('button,[role="button"],a.btn')].filter(visibleWithAncestors).length,
      visibleTextLength: (body?.innerText || '').trim().length,
      mojibake: /(?:Рџ|РЎ|Ð|Ñ|�)/.test(body?.innerText || '')
    };
  });
}

async function collectLinks(page) {
  const links = await page.locator('a[href]').evaluateAll((nodes) => nodes.map((node) => node.href));
  return [...new Set(links.map(normalizeUrl).filter(isSafePageUrl))];
}

async function modalInteraction(page, screenshotBase) {
  const result = {
    attempted: false,
    opened: false,
    openedBy: null,
    modalId: null,
    withinViewport: null,
    hasCloseControl: null,
    footerVisible: null,
    closedByEscape: null,
    screenshot: null,
    errors: []
  };

  const safe = /(добавить|создать|просмотр|открыть|редактировать|подробнее|выбрать)/i;
  const unsafe = /(удалить|архив|восстановить|сохранить|отправить|обновить.*почт|импорт|синхрон|выйти|оплатить|провести|отменить операц)/i;
  const candidates = page.locator('button[type="button"],[data-open-modal],a[role="button"]');
  const count = Math.min(await candidates.count(), 60);

  for (let i = 0; i < count; i += 1) {
    const item = candidates.nth(i);
    try {
      if (!(await item.isVisible())) continue;
      const text = `${await item.innerText().catch(() => '')} ${await item.getAttribute('aria-label') || ''}`.trim();
      if (!safe.test(text) || unsafe.test(text)) continue;
      result.attempted = true;
      await item.click({ timeout: 2000 });
      await page.waitForTimeout(450);
      const open = page.locator('.modal-overlay.is-open').last();
      if (await open.count() && await open.isVisible().catch(() => false)) {
        result.opened = true;
        result.openedBy = `click:${text.slice(0, 80)}`;
        break;
      }
    } catch (error) {
      result.errors.push(String(error.message || error).slice(0, 240));
    }
  }

  if (!result.opened) {
    const directId = await page.evaluate(() => {
      const overlays = [...document.querySelectorAll('.modal-overlay[id]')];
      const preferred = overlays.find((m) => m.querySelector('form') && m.dataset.escapeLocked !== '1');
      return preferred ? preferred.id : null;
    }).catch(() => null);
    if (directId) {
      try {
        result.attempted = true;
        await page.evaluate((id) => window.openModal(id), directId);
        await page.waitForTimeout(300);
        const open = page.locator('.modal-overlay.is-open').last();
        if (await open.count() && await open.isVisible().catch(() => false)) {
          result.opened = true;
          result.openedBy = `window.openModal:${directId}`;
        }
      } catch (error) {
        result.errors.push(String(error.message || error).slice(0, 240));
      }
    }
  }

  if (!result.opened) return result;

  const before = await page.evaluate(() => {
    const opens = [...document.querySelectorAll('.modal-overlay.is-open')].filter((m) => {
      const style = getComputedStyle(m);
      const rect = m.getBoundingClientRect();
      return style.display !== 'none' && style.visibility !== 'hidden' && rect.width > 1 && rect.height > 1;
    });
    const modal = opens[opens.length - 1] || null;
    if (!modal) return null;
    const rect = modal.getBoundingClientRect();
    const inner = modal.querySelector('.modal') || modal;
    const innerRect = inner.getBoundingClientRect();
    const footer = modal.querySelector('.modal-foot,.modal-footer');
    const close = modal.querySelector('[data-modal-close],[data-close-modal],.modal-close,[aria-label*="закры" i]');
    return {
      id: modal.id || null,
      openCount: opens.length,
      withinViewport: innerRect.left >= -1 && innerRect.top >= -1 && innerRect.right <= innerWidth + 1 && innerRect.bottom <= innerHeight + 1,
      hasCloseControl: Boolean(close),
      footerVisible: !footer || (() => {
        const fr = footer.getBoundingClientRect();
        const fs = getComputedStyle(footer);
        return fs.display !== 'none' && fs.visibility !== 'hidden' && fr.bottom <= innerHeight + 1 && fr.top >= -1;
      })(),
      width: Math.round(innerRect.width),
      height: Math.round(innerRect.height),
      overlayRect: { left: rect.left, top: rect.top, right: rect.right, bottom: rect.bottom }
    };
  });

  if (!before) return result;
  result.modalId = before.id;
  result.withinViewport = before.withinViewport;
  result.hasCloseControl = before.hasCloseControl;
  result.footerVisible = before.footerVisible;
  result.screenshot = `${screenshotBase}__modal__1920x1080.png`;
  await page.screenshot({ path: result.screenshot, fullPage: false });

  await page.keyboard.press('Escape');
  await page.waitForTimeout(400);
  const after = await page.evaluate((id) => {
    const target = id ? document.getElementById(id) : null;
    const targetOpen = Boolean(target && target.classList.contains('is-open'));
    const openCount = [...document.querySelectorAll('.modal-overlay.is-open')].filter((m) => {
      const style = getComputedStyle(m);
      const rect = m.getBoundingClientRect();
      return style.display !== 'none' && style.visibility !== 'hidden' && rect.width > 1 && rect.height > 1;
    }).length;
    return { targetOpen, openCount };
  }, before.id);

  result.closedByEscape = before.id
    ? !after.targetOpen
    : after.openCount < before.openCount;

  if (!result.closedByEscape && before.id) {
    await page.evaluate((id) => {
      if (typeof window.closeModal === 'function') window.closeModal(id);
      else document.getElementById(id)?.classList.remove('is-open');
    }, before.id).catch(() => {});
  }
  return result;
}

async function login(context, credential) {
  const page = await context.newPage();
  const loginUrl = new URL('login', BASE_URL).href;
  const response = await page.goto(loginUrl, { waitUntil: 'domcontentloaded', timeout: 45000 }).catch(() => null);
  const user = page.locator('input[name="username"],input[name="email"],input[type="email"],input[type="text"]').first();
  const password = page.locator('input[type="password"]').first();
  if (!(await user.count()) || !(await password.count())) {
    await page.close();
    return { ok: false, reason: 'login form not detected', status: response?.status() || null };
  }
  await user.fill(credential.username);
  await password.fill(credential.password);
  await Promise.all([
    page.waitForLoadState('domcontentloaded', { timeout: 20000 }).catch(() => {}),
    page.locator('button[type="submit"],input[type="submit"]').first().click()
  ]);
  await page.waitForTimeout(900);
  const stillLogin = /\/login(?:[/?#]|$)/i.test(page.url()) || await page.locator('input[type="password"]').count() > 0;
  if (stillLogin) {
    await page.locator('input[type="password"]').fill('').catch(() => {});
    await page.close();
    return { ok: false, reason: 'authentication failed' };
  }
  return { ok: true, page };
}

async function accessProbe(context, role, pathname, expected) {
  const page = await context.newPage();
  const errors = [];
  page.on('pageerror', (e) => errors.push(String(e.message || e)));
  const response = await page.goto(new URL(pathname.replace(/^\//, ''), BASE_URL).href, {
    waitUntil: 'domcontentloaded',
    timeout: 30000
  }).catch(() => null);
  await page.waitForTimeout(250);
  const status = response?.status() || null;
  const redirectedToLogin = /\/login(?:[/?#]|$)/i.test(page.url());
  const bodyText = await page.locator('body').innerText().catch(() => '');
  const pass = expected.includes(status) && !redirectedToLogin;
  const result = {
    role,
    pathname,
    expected,
    status,
    finalUrl: page.url(),
    redirectedToLogin,
    bodyExcerpt: bodyText.replace(/[\w.+-]+@[\w.-]+\.[A-Za-z]{2,}/g, '[EMAIL]').replace(/\+?\d[\d\s()-]{8,}\d/g, '[PHONE]').slice(0, 300),
    errors,
    pass
  };
  await page.close();
  return result;
}

async function auditRole(browser, credential, aggregate) {
  const context = await browser.newContext({
    viewport: VIEWPORT,
    deviceScaleFactor: 1,
    ignoreHTTPSErrors: true,
    locale: 'ru-RU',
    timezoneId: 'Europe/Moscow'
  });
  const auth = await login(context, credential);
  if (!auth.ok) {
    aggregate.blockedRoles.push({ role: credential.role, reason: auth.reason });
    await context.close();
    return;
  }

  const page = auth.page;
  const queue = [normalizeUrl(page.url())];
  const patternsSeen = new Set();
  const exactSeen = new Set();
  let index = aggregate.routes.length + 1;

  while (queue.length && patternsSeen.size < MAX_PATTERNS) {
    const url = queue.shift();
    if (!url || exactSeen.has(url) || !isSafePageUrl(url)) continue;
    exactSeen.add(url);
    const pattern = routePattern(url);
    if (patternsSeen.has(pattern)) continue;
    patternsSeen.add(pattern);

    const consoleErrors = [];
    const pageErrors = [];
    const networkErrors = [];
    const badResponses = [];
    const c = (msg) => { if (msg.type() === 'error') consoleErrors.push(msg.text()); };
    const pe = (error) => pageErrors.push(String(error.message || error));
    const rf = (request) => networkErrors.push(`${request.method()} ${request.url()} :: ${request.failure()?.errorText || 'failed'}`);
    const rs = (response) => { if (response.status() >= 400) badResponses.push(`${response.status()} ${response.url()}`); };
    page.on('console', c);
    page.on('pageerror', pe);
    page.on('requestfailed', rf);
    page.on('response', rs);

    let response = null;
    let navigationError = null;
    try {
      response = await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 30000 });
      await page.waitForTimeout(400);
    } catch (error) {
      navigationError = String(error.message || error);
    }

    const finalUrl = page.url();
    const contentType = response?.headers()['content-type'] || '';
    if (contentType && !/text\/html|application\/xhtml\+xml/i.test(contentType)) {
      aggregate.actions.push({
        role: credential.role,
        url,
        finalUrl,
        status: response?.status() || null,
        contentType,
        pass: Boolean(response && response.status() < 400)
      });
      page.off('console', c); page.off('pageerror', pe); page.off('requestfailed', rf); page.off('response', rs);
      continue;
    }

    const links = await collectLinks(page).catch(() => []);
    for (const link of links) if (!exactSeen.has(link) && queue.length < MAX_PATTERNS * 4) queue.push(link);

    const layout = await layoutEvidence(page).catch((error) => ({ inspectError: String(error.message || error) }));
    const roleDir = path.join(SCREEN_DIR, slug(credential.role));
    fs.mkdirSync(roleDir, { recursive: true });
    const baseName = `P12_${String(index).padStart(3, '0')}__${slug(credential.role)}__${slug(new URL(finalUrl).pathname)}__base__1920x1080.png`;
    const screenshot = path.join(roleDir, baseName);
    await page.locator('input[type="password"]').fill('').catch(() => {});
    await page.screenshot({ path: screenshot, fullPage: false }).catch((error) => {
      navigationError = navigationError || String(error.message || error);
    });
    const screenshotBase = screenshot.replace(/__base__1920x1080\.png$/, '');
    const modal = await modalInteraction(page, screenshotBase).catch((error) => ({
      attempted: true, opened: false, closedByEscape: null, errors: [String(error.message || error)]
    }));

    const defects = [];
    const status = response?.status() || null;
    if (navigationError) defects.push(`navigation_error:${navigationError.slice(0, 240)}`);
    if (!response || status >= 400) defects.push(`http_status:${status || 'NO_RESPONSE'}`);
    if (layout.bodyHorizontalScroll) defects.push(`body_horizontal_scroll:${layout.bodyScrollWidth}>${layout.viewportWidth}`);
    if ((layout.overlapFailures || 0) > 0) defects.push(`real_overlap:${layout.overlapFailures}`);
    if ((layout.clippedControls || 0) > 0) defects.push(`clipped_controls:${layout.clippedControls}`);
    if (layout.mojibake) defects.push('mojibake');
    if ((layout.visibleTextLength || 0) < 10) defects.push('empty_or_near_empty_page');
    if (consoleErrors.length || pageErrors.length) defects.push(`console_or_page_errors:${consoleErrors.length + pageErrors.length}`);
    if (networkErrors.length || badResponses.length) defects.push(`network_errors:${networkErrors.length + badResponses.length}`);
    if (modal.opened && modal.withinViewport === false) defects.push('modal_outside_viewport');
    if (modal.opened && modal.hasCloseControl === false) defects.push('modal_missing_close_control');
    if (modal.opened && modal.footerVisible === false) defects.push('modal_footer_not_visible');
    if (modal.opened && modal.closedByEscape === false) defects.push('modal_escape_did_not_close');

    aggregate.routes.push({
      role: credential.role,
      url,
      finalUrl,
      routePattern: pattern,
      httpStatus: status,
      title: await page.title().catch(() => ''),
      heading: await page.locator('h1,h2,.page-title').first().innerText().catch(() => ''),
      layout,
      modal,
      consoleErrors: [...consoleErrors, ...pageErrors],
      networkErrors: [...networkErrors, ...badResponses],
      screenshots: [
        path.relative(OUT_DIR, screenshot),
        modal.screenshot ? path.relative(OUT_DIR, modal.screenshot) : null
      ].filter(Boolean),
      defects,
      finalStatus: defects.length ? 'FAIL' : 'PASS'
    });

    fs.writeFileSync(path.join(OUT_DIR, 'P12_route_matrix.checkpoint.json'), JSON.stringify(aggregate.routes, null, 2));
    index += 1;
    page.off('console', c); page.off('pageerror', pe); page.off('requestfailed', rf); page.off('response', rs);
  }

  aggregate.roleInventories.push({
    role: credential.role,
    routesChecked: aggregate.routes.filter((r) => r.role === credential.role).length,
    routePatterns: patternsSeen.size,
    queueRemaining: queue.length
  });

  const probes = credential.role === 'SUPERADMIN'
    ? [
        ['/superadmin/companies', [200]],
        ['/company/dashboard', [403]]
      ]
    : credential.role === 'OWNER'
      ? [
          ['/company/dashboard', [200]],
          ['/company/finance/operations', [200]],
          ['/superadmin/companies', [403]]
        ]
      : [
          ['/company/dashboard', [200]],
          ['/company/finance/operations', [403]],
          ['/superadmin/companies', [403]]
        ];
  for (const [pathname, expected] of probes) {
    aggregate.accessMatrix.push(await accessProbe(context, credential.role, pathname, expected));
  }

  await context.close();
}

function writeReports(aggregate, startedAt) {
  const routes = aggregate.routes;
  const failures = routes.filter((r) => r.finalStatus === 'FAIL');
  const screenshots = routes.reduce((sum, r) => sum + r.screenshots.length, 0);
  const completedRoles = aggregate.roleInventories.map((r) => r.role);
  const requiredRoles = ['SUPERADMIN', 'OWNER', 'LOGIST'];
  const missingRoles = requiredRoles.filter((r) => !completedRoles.includes(r));
  const accessFailures = aggregate.accessMatrix.filter((r) => !r.pass);
  const acceptance = failures.length === 0
    && aggregate.blockedRoles.length === 0
    && missingRoles.length === 0
    && accessFailures.length === 0;
  const status = acceptance ? 'P12_PRODUCTION_ACCEPTED' : 'P12_NOT_ACCEPTED';

  const summary = {
    status,
    startedAt,
    finishedAt: new Date().toISOString(),
    baseUrl: BASE_URL,
    viewport: '1920x1080',
    zoom: '100%',
    deviceScaleFactor: 1,
    requiredRoles,
    completedRoles,
    missingRoles,
    blockedRoles: aggregate.blockedRoles,
    routesTotal: routes.length,
    routesPass: routes.filter((r) => r.finalStatus === 'PASS').length,
    routesFail: failures.length,
    modalsOpened: routes.filter((r) => r.modal?.opened).length,
    modalsEscapePass: routes.filter((r) => r.modal?.opened && r.modal?.closedByEscape).length,
    screenshots,
    bodyHorizontalScrollFailures: routes.filter((r) => r.layout?.bodyHorizontalScroll).length,
    overlapFailures: routes.reduce((s, r) => s + Number(r.layout?.overlapFailures || 0), 0),
    clippedControlFailures: routes.reduce((s, r) => s + Number(r.layout?.clippedControls || 0), 0),
    consoleErrors: routes.reduce((s, r) => s + r.consoleErrors.length, 0),
    networkErrors: routes.reduce((s, r) => s + r.networkErrors.length, 0),
    mojibakeFailures: routes.filter((r) => r.layout?.mojibake).length,
    accessChecks: aggregate.accessMatrix.length,
    accessFailures: accessFailures.length,
    deferredUnsafeForms: [...new Set(routes.flatMap((r) => (r.layout?.forms || [])
      .filter((f) => f.method === 'POST')
      .map((f) => `${r.role}|${f.action}`)))].length
  };

  fs.writeFileSync(path.join(OUT_DIR, 'P12_SUMMARY.json'), JSON.stringify(summary, null, 2));
  fs.writeFileSync(path.join(OUT_DIR, 'P12_ROUTE_MATRIX.json'), JSON.stringify(routes, null, 2));
  fs.writeFileSync(path.join(OUT_DIR, 'P12_ROLE_ACCESS_MATRIX.json'), JSON.stringify(aggregate.accessMatrix, null, 2));
  fs.writeFileSync(path.join(OUT_DIR, 'P12_ACTION_ENDPOINTS.json'), JSON.stringify(aggregate.actions, null, 2));

  const csvHeader = ['role','url','httpStatus','bodyHorizontalScroll','overlaps','clippedControls','modalOpened','modalClosedByEscape','consoleErrors','networkErrors','finalStatus','screenshots'];
  const csvRows = routes.map((r) => [
    r.role, r.finalUrl, r.httpStatus, r.layout?.bodyHorizontalScroll || false,
    r.layout?.overlapFailures || 0, r.layout?.clippedControls || 0,
    r.modal?.opened || false, r.modal?.closedByEscape ?? '',
    r.consoleErrors.length, r.networkErrors.length, r.finalStatus, r.screenshots.join('|')
  ].map(csvEscape).join(','));
  fs.writeFileSync(path.join(OUT_DIR, 'P12_VISUAL_MATRIX.csv'), [csvHeader.join(','), ...csvRows].join('\n'));

  const defects = failures.map((r) => ({
    role: r.role,
    url: r.finalUrl,
    defects: r.defects,
    screenshots: r.screenshots,
    overlapSamples: r.layout?.overlapSamples || [],
    clippedSamples: r.layout?.clippedSamples || []
  }));
  fs.writeFileSync(path.join(OUT_DIR, 'P12_DEFECT_REGISTER.json'), JSON.stringify(defects, null, 2));

  const runtimeReport = [
    '# P12 ERP PLANEX Production Full HD Acceptance',
    '',
    `STATUS: ${status}`,
    `BASE_URL: ${BASE_URL}`,
    'VIEWPORT: 1920x1080',
    'ZOOM: 100%',
    'deviceScaleFactor: 1',
    `ROLES: ${completedRoles.join(', ')}`,
    `ROUTES_TOTAL: ${summary.routesTotal}`,
    `ROUTES_PASS: ${summary.routesPass}`,
    `ROUTES_FAIL: ${summary.routesFail}`,
    `MODALS_OPENED: ${summary.modalsOpened}`,
    `MODALS_ESCAPE_PASS: ${summary.modalsEscapePass}`,
    `SCREENSHOTS: ${summary.screenshots}`,
    `BODY_HORIZONTAL_SCROLL_FAILURES: ${summary.bodyHorizontalScrollFailures}`,
    `OVERLAP_FAILURES: ${summary.overlapFailures}`,
    `CLIPPED_CONTROL_FAILURES: ${summary.clippedControlFailures}`,
    `CONSOLE_ERRORS: ${summary.consoleErrors}`,
    `NETWORK_ERRORS: ${summary.networkErrors}`,
    `MOJIBAKE_FAILURES: ${summary.mojibakeFailures}`,
    `ACCESS_CHECKS: ${summary.accessChecks}`,
    `ACCESS_FAILURES: ${summary.accessFailures}`,
    '',
    'Production-mutating POST forms were inventoried but not submitted.',
    'No passwords, cookies, session IDs, CSRF tokens or private keys are written to the evidence.',
    '',
    status
  ].join('\n');
  fs.writeFileSync(path.join(OUT_DIR, 'P12_RUNTIME_REPORT.md'), runtimeReport);
  fs.writeFileSync(path.join(OUT_DIR, 'P12_PRODUCTION_UNCHANGED.json'), JSON.stringify({
    productionDbMutated: false,
    productionPostFormsSubmitted: false,
    navigationPolicy: 'GET pages and non-submit UI interactions only',
    credentialsPersisted: false
  }, null, 2));
  return summary;
}

(async () => {
  const startedAt = new Date().toISOString();
  const credentials = decodeCredentials();
  const aggregate = {
    routes: [],
    actions: [],
    accessMatrix: [],
    blockedRoles: [],
    roleInventories: []
  };
  if (credentials.length < 3) throw new Error('Three runtime credentials were not securely resolved');
  const browser = await chromium.launch({ headless: true });
  try {
    for (const credential of credentials) {
      await auditRole(browser, credential, aggregate);
    }
  } finally {
    await browser.close();
  }
  const summary = writeReports(aggregate, startedAt);
  process.stdout.write(JSON.stringify(summary));
  process.exitCode = summary.status === 'P12_PRODUCTION_ACCEPTED' ? 0 : 1;
})().catch((error) => {
  fs.mkdirSync(OUT_DIR, { recursive: true });
  fs.writeFileSync(path.join(OUT_DIR, 'P12_SUMMARY.json'), JSON.stringify({
    status: 'P12_BLOCKED',
    error: String(error.stack || error)
  }, null, 2));
  console.error('P12 final audit failed without exposing credentials.');
  process.exitCode = 1;
});
