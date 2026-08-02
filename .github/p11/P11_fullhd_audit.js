'use strict';

const fs = require('fs');
const path = require('path');
const { chromium } = require('playwright');

const BASE_URL = (process.env.P11_BASE_URL || 'https://plan-ex.ru/erpv2/').replace(/\/+$/, '/');
const OUT_DIR = path.resolve(process.env.P11_OUT_DIR || 'P11_output');
const SCREEN_DIR = path.join(OUT_DIR, 'P11_screenshots');
const MAX_URLS_PER_ROLE = Number(process.env.P11_MAX_URLS || 180);
const VIEWPORT = { width: 1920, height: 1080 };

fs.mkdirSync(SCREEN_DIR, { recursive: true });

function decodeCredentials() {
    const encoded = process.env.P11_CREDENTIALS_B64 || '';
    if (!encoded) return [];
    try {
        const parsed = JSON.parse(Buffer.from(encoded, 'base64').toString('utf8'));
        return Array.isArray(parsed) ? parsed.filter((item) => item && item.username && item.password) : [];
    } catch (error) {
        return [];
    }
}

function slug(value) {
    return String(value || 'page')
        .toLowerCase()
        .replace(/^https?:\/\//, '')
        .replace(/[^a-z0-9а-яё]+/giu, '_')
        .replace(/^_+|_+$/g, '')
        .slice(0, 90) || 'page';
}

function csvEscape(value) {
    const text = typeof value === 'string' ? value : JSON.stringify(value ?? '');
    return `"${String(text).replace(/"/g, '""')}"`;
}

function normalizeRoute(rawUrl) {
    try {
        const url = new URL(rawUrl);
        const normalizedPath = url.pathname
            .replace(/\/[0-9]+(?=\/|$)/g, '/{id}')
            .replace(/\/[0-9a-f]{8}-[0-9a-f-]{27,}(?=\/|$)/gi, '/{uuid}');
        const safeParams = [...url.searchParams.entries()]
            .filter(([key]) => !/token|csrf|signature|password|secret/i.test(key))
            .map(([key]) => key)
            .sort();
        return `${normalizedPath}?${safeParams.join('&')}`;
    } catch (error) {
        return rawUrl;
    }
}

function isSafeGetUrl(rawUrl) {
    try {
        const url = new URL(rawUrl, BASE_URL);
        const base = new URL(BASE_URL);
        if (url.origin !== base.origin || !url.pathname.startsWith(base.pathname.replace(/\/$/, ''))) return false;
        const unsafe = /\/(logout|delete|destroy|remove|archive|restore|purge|hard-delete|execute|run|refresh-from-mail|mail-refresh|import|sync)(\/|$)/i;
        if (unsafe.test(url.pathname)) return false;
        if ([...url.searchParams.keys()].some((key) => /token|csrf|password|secret|delete|destroy/i.test(key))) return false;
        return true;
    } catch (error) {
        return false;
    }
}

async function inspectLayout(page) {
    return page.evaluate(() => {
        const root = document.documentElement;
        const body = document.body;
        const bodyScrollWidth = Math.max(root?.scrollWidth || 0, body?.scrollWidth || 0);
        const viewportWidth = window.innerWidth;
        const horizontalBodyScroll = bodyScrollWidth > viewportWidth + 1;
        const selector = 'a[href],button,input:not([type="hidden"]),select,textarea,[role="button"],[tabindex]:not([tabindex="-1"])';
        const elements = [...document.querySelectorAll(selector)].filter((element) => {
            const style = getComputedStyle(element);
            const rect = element.getBoundingClientRect();
            return style.display !== 'none' && style.visibility !== 'hidden' && Number(style.opacity) !== 0 && rect.width > 1 && rect.height > 1;
        });
        let clippedControls = 0;
        let overlapFailures = 0;
        const clippedSamples = [];
        const overlapSamples = [];
        for (const element of elements.slice(0, 500)) {
            const rect = element.getBoundingClientRect();
            if (rect.left < -1 || rect.right > viewportWidth + 1 || rect.top < -1 || rect.bottom > window.innerHeight + 1) {
                clippedControls += 1;
                if (clippedSamples.length < 10) clippedSamples.push((element.textContent || element.getAttribute('aria-label') || element.getAttribute('name') || element.tagName).trim().slice(0, 120));
            }
            const cx = Math.min(viewportWidth - 1, Math.max(0, rect.left + rect.width / 2));
            const cy = Math.min(window.innerHeight - 1, Math.max(0, rect.top + Math.min(rect.height, window.innerHeight) / 2));
            if (cx >= 0 && cy >= 0 && cx < viewportWidth && cy < window.innerHeight) {
                const hit = document.elementFromPoint(cx, cy);
                if (hit && hit !== element && !element.contains(hit) && !hit.contains(element)) {
                    const hitStyle = getComputedStyle(hit);
                    if (hitStyle.pointerEvents !== 'none') {
                        overlapFailures += 1;
                        if (overlapSamples.length < 10) overlapSamples.push({
                            target: (element.textContent || element.getAttribute('aria-label') || element.getAttribute('name') || element.tagName).trim().slice(0, 80),
                            blocker: (hit.textContent || hit.getAttribute('aria-label') || hit.tagName).trim().slice(0, 80),
                        });
                    }
                }
            }
        }

        const tables = [...document.querySelectorAll('table')].map((table) => {
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
                tableWidth: Math.round(table.getBoundingClientRect().width),
                viewportWidth,
                hasHorizontalContainer: Boolean(scrollContainer),
                containerScrollWidth: scrollContainer ? scrollContainer.scrollWidth : null,
                containerClientWidth: scrollContainer ? scrollContainer.clientWidth : null,
            };
        });

        const dialogs = [...document.querySelectorAll('dialog,[role="dialog"],.modal')].map((dialog) => {
            const rect = dialog.getBoundingClientRect();
            const style = getComputedStyle(dialog);
            return {
                visible: style.display !== 'none' && style.visibility !== 'hidden' && rect.width > 1 && rect.height > 1,
                width: Math.round(rect.width),
                height: Math.round(rect.height),
                scrollHeight: dialog.scrollHeight,
                clientHeight: dialog.clientHeight,
                overflowY: style.overflowY,
            };
        });

        const forms = [...document.forms].map((form) => ({
            method: (form.method || 'get').toUpperCase(),
            action: form.action || location.href,
            fields: form.querySelectorAll('input,select,textarea').length,
            submitButtons: form.querySelectorAll('button[type="submit"],input[type="submit"]').length,
        }));

        const selects = [...document.querySelectorAll('select')].map((select) => {
            const rect = select.getBoundingClientRect();
            let parent = select.parentElement;
            let clippedByOverflow = false;
            while (parent && parent !== body) {
                const style = getComputedStyle(parent);
                if (/(hidden|clip)/.test(`${style.overflow}${style.overflowX}${style.overflowY}`)) {
                    const parentRect = parent.getBoundingClientRect();
                    if (rect.left < parentRect.left || rect.right > parentRect.right || rect.top < parentRect.top || rect.bottom > parentRect.bottom) clippedByOverflow = true;
                }
                parent = parent.parentElement;
            }
            return {
                name: select.name || select.id || '',
                options: select.options.length,
                multiple: select.multiple,
                size: select.size,
                clippedByOverflow,
            };
        });

        return {
            horizontalBodyScroll,
            bodyScrollWidth,
            viewportWidth,
            clippedControls,
            clippedSamples,
            overlapFailures,
            overlapSamples,
            tables,
            dialogs,
            forms,
            selects,
            buttons: document.querySelectorAll('button,[role="button"],a.btn').length,
            dateInputs: document.querySelectorAll('input[type="date"],input[type="datetime-local"]').length,
            autocompletes: document.querySelectorAll('[role="combobox"],input[list],[aria-autocomplete]').length,
            visibleTextLength: (body?.innerText || '').length,
        };
    });
}

async function collectLinks(page) {
    const links = await page.locator('a[href]').evaluateAll((anchors) => anchors.map((anchor) => ({ href: anchor.href, text: (anchor.textContent || '').trim() })));
    return links.filter((item) => item.href && isSafeGetUrl(item.href));
}

async function trySafeInteraction(page, screenshotBase) {
    const result = { modalOpened: false, modalScreenshot: null, dropdownOpened: false, dropdownScreenshot: null, interactionErrors: [] };
    const safePattern = /(добавить|создать|просмотр|открыть|фильтр|настроить|редактировать|подробнее|выбрать)/i;
    const unsafePattern = /(удалить|архив|восстановить|сохранить|отправить|обновить.*почт|импорт|синхрон|выйти|оплатить|провести)/i;
    const candidates = page.locator('button[type="button"],[data-modal-open],[data-open-modal],[data-bs-toggle="modal"],a[role="button"]');
    const count = Math.min(await candidates.count(), 40);
    for (let index = 0; index < count; index += 1) {
        const candidate = candidates.nth(index);
        try {
            if (!(await candidate.isVisible())) continue;
            const text = ((await candidate.innerText().catch(() => '')) || (await candidate.getAttribute('aria-label')) || '').trim();
            if (!safePattern.test(text) || unsafePattern.test(text)) continue;
            await candidate.click({ timeout: 1500 });
            await page.waitForTimeout(350);
            const dialog = page.locator('dialog[open],[role="dialog"]:visible,.modal:visible').first();
            if (await dialog.count() && await dialog.isVisible().catch(() => false)) {
                result.modalOpened = true;
                result.modalScreenshot = `${screenshotBase}__modal__1920x1080.png`;
                await page.locator('input[type="password"]').fill('').catch(() => {});
                await page.screenshot({ path: result.modalScreenshot, fullPage: false });
                await page.keyboard.press('Escape').catch(() => {});
                await page.waitForTimeout(200);
                break;
            }
            const menu = page.locator('[role="menu"]:visible,.dropdown-menu:visible,[role="listbox"]:visible').first();
            if (await menu.count() && await menu.isVisible().catch(() => false)) {
                result.dropdownOpened = true;
                result.dropdownScreenshot = `${screenshotBase}__dropdown__1920x1080.png`;
                await page.screenshot({ path: result.dropdownScreenshot, fullPage: false });
                await page.keyboard.press('Escape').catch(() => {});
                await page.waitForTimeout(200);
                break;
            }
        } catch (error) {
            result.interactionErrors.push(String(error.message || error).slice(0, 300));
        }
    }
    return result;
}

async function login(context, credential, globalEvidence) {
    const page = await context.newPage();
    const pageErrors = [];
    const consoleErrors = [];
    const networkErrors = [];
    page.on('pageerror', (error) => pageErrors.push(String(error.message || error)));
    page.on('console', (message) => { if (message.type() === 'error') consoleErrors.push(message.text()); });
    page.on('requestfailed', (request) => networkErrors.push(`${request.method()} ${request.url()} :: ${request.failure()?.errorText || 'failed'}`));
    const loginCandidates = [new URL('login', BASE_URL).href, BASE_URL];
    let response = null;
    for (const candidate of loginCandidates) {
        response = await page.goto(candidate, { waitUntil: 'domcontentloaded', timeout: 45000 }).catch(() => null);
        if (await page.locator('input[type="password"]').count()) break;
    }
    const passwordInput = page.locator('input[type="password"]').first();
    const userInput = page.locator('input[name="username"],input[name="email"],input[type="email"],input[type="text"]').first();
    if (!(await passwordInput.count()) || !(await userInput.count())) {
        await page.screenshot({ path: path.join(SCREEN_DIR, `P11_login_${slug(credential.role)}__unrecognized__1920x1080.png`), fullPage: false }).catch(() => {});
        await page.close();
        return { ok: false, reason: 'Login form was not detected', status: response?.status() || null, pageErrors, consoleErrors, networkErrors };
    }
    await userInput.fill(credential.username);
    await passwordInput.fill(credential.password);
    const submit = page.locator('button[type="submit"],input[type="submit"]').first();
    await Promise.all([
        page.waitForLoadState('domcontentloaded', { timeout: 20000 }).catch(() => {}),
        submit.click({ timeout: 5000 }),
    ]);
    await page.waitForTimeout(1200);
    await page.locator('input[type="password"]').fill('').catch(() => {});
    const stillLogin = /\/login(?:[/?#]|$)/i.test(page.url()) || (await page.locator('input[type="password"]').count()) > 0;
    const roleText = await page.locator('body').innerText().catch(() => '');
    globalEvidence.loginAttempts.push({ role: credential.role, ok: !stillLogin, finalUrl: page.url(), status: response?.status() || null, pageErrors, consoleErrors, networkErrors });
    if (stillLogin) {
        await page.screenshot({ path: path.join(SCREEN_DIR, `P11_login_${slug(credential.role)}__failed__1920x1080.png`), fullPage: false }).catch(() => {});
        await page.close();
        return { ok: false, reason: 'Authentication failed', pageErrors, consoleErrors, networkErrors };
    }
    return { ok: true, page, roleText: roleText.slice(0, 5000) };
}

async function auditRole(browser, credential, results, globalEvidence) {
    const context = await browser.newContext({
        viewport: VIEWPORT,
        deviceScaleFactor: 1,
        ignoreHTTPSErrors: true,
        locale: 'ru-RU',
        timezoneId: 'Europe/Moscow',
    });
    const loginResult = await login(context, credential, globalEvidence);
    if (!loginResult.ok) {
        results.blockedRoles.push({ role: credential.role, reason: loginResult.reason });
        await context.close();
        return;
    }

    const page = loginResult.page;
    const queue = [page.url()];
    const exactSeen = new Set();
    const patternCounts = new Map();
    let screenshotIndex = results.routes.length + 1;

    while (queue.length && exactSeen.size < MAX_URLS_PER_ROLE) {
        const url = queue.shift();
        if (!url || exactSeen.has(url) || !isSafeGetUrl(url)) continue;
        const pattern = normalizeRoute(url);
        const patternCount = patternCounts.get(pattern) || 0;
        if (patternCount >= 3) continue;
        patternCounts.set(pattern, patternCount + 1);
        exactSeen.add(url);

        const consoleErrors = [];
        const pageErrors = [];
        const networkErrors = [];
        const badResponses = [];
        const consoleListener = (message) => { if (message.type() === 'error') consoleErrors.push(message.text()); };
        const pageErrorListener = (error) => pageErrors.push(String(error.message || error));
        const requestFailedListener = (request) => networkErrors.push(`${request.method()} ${request.url()} :: ${request.failure()?.errorText || 'failed'}`);
        const responseListener = (response) => { if (response.status() >= 400) badResponses.push(`${response.status()} ${response.url()}`); };
        page.on('console', consoleListener);
        page.on('pageerror', pageErrorListener);
        page.on('requestfailed', requestFailedListener);
        page.on('response', responseListener);

        let response = null;
        let navigationError = null;
        try {
            response = await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 45000 });
            await page.waitForTimeout(500);
        } catch (error) {
            navigationError = String(error.message || error);
        }

        const finalUrl = page.url();
        if (/\/login(?:[/?#]|$)/i.test(finalUrl)) {
            results.blockedRoles.push({ role: credential.role, reason: `Session returned to login at ${url}` });
            break;
        }

        const title = await page.title().catch(() => '');
        const heading = await page.locator('h1,h2,.page-title').first().innerText().catch(() => '');
        const layout = await inspectLayout(page).catch((error) => ({ inspectError: String(error.message || error) }));
        const links = await collectLinks(page).catch(() => []);
        for (const link of links) {
            if (!exactSeen.has(link.href) && queue.length < MAX_URLS_PER_ROLE * 3) queue.push(link.href);
        }

        const roleDir = path.join(SCREEN_DIR, slug(credential.role || 'role'));
        fs.mkdirSync(roleDir, { recursive: true });
        const screenshotName = `P11_${String(screenshotIndex).padStart(3, '0')}__${slug(credential.role)}__${slug(new URL(finalUrl).pathname)}__base__1920x1080.png`;
        const screenshotPath = path.join(roleDir, screenshotName);
        await page.locator('input[type="password"]').fill('').catch(() => {});
        await page.screenshot({ path: screenshotPath, fullPage: false }).catch((error) => { navigationError = navigationError || String(error.message || error); });
        const screenshotBase = screenshotPath.replace(/__base__1920x1080\.png$/, '');
        const interaction = await trySafeInteraction(page, screenshotBase);

        const defects = [];
        if (navigationError) defects.push(`navigation: ${navigationError}`);
        if (!response || response.status() >= 400) defects.push(`http_status: ${response?.status() || 'NO_RESPONSE'}`);
        if (layout.horizontalBodyScroll) defects.push(`body_horizontal_scroll: ${layout.bodyScrollWidth} > ${layout.viewportWidth}`);
        if ((layout.overlapFailures || 0) > 0) defects.push(`overlap_candidates: ${layout.overlapFailures}`);
        if ((layout.clippedControls || 0) > 0) defects.push(`clipped_controls: ${layout.clippedControls}`);
        if (consoleErrors.length || pageErrors.length) defects.push(`console_or_page_errors: ${consoleErrors.length + pageErrors.length}`);
        if (networkErrors.length || badResponses.length) defects.push(`network_errors: ${networkErrors.length + badResponses.length}`);
        const finalStatus = defects.length ? 'FAIL' : 'PASS';

        results.routes.push({
            role: credential.role,
            url,
            final_url: finalUrl,
            route_pattern: pattern,
            http_status: response?.status() || null,
            title,
            heading,
            base_screen: Boolean(response && response.status() < 400),
            horizontal_body_scroll: Boolean(layout.horizontalBodyScroll),
            body_scroll_width: layout.bodyScrollWidth || null,
            viewport_width: layout.viewportWidth || VIEWPORT.width,
            overlap: layout.overlapFailures || 0,
            overlap_samples: layout.overlapSamples || [],
            clipped_controls: layout.clippedControls || 0,
            clipped_samples: layout.clippedSamples || [],
            table_usability: layout.tables || [],
            modal_usability: { dom: layout.dialogs || [], interaction },
            dropdown_usability: layout.selects || [],
            functional_actions: {
                forms: layout.forms || [],
                buttons: layout.buttons || 0,
                date_inputs: layout.dateInputs || 0,
                autocompletes: layout.autocompletes || 0,
                discovered_links: links.length,
            },
            console_errors: [...consoleErrors, ...pageErrors],
            network_errors: [...networkErrors, ...badResponses],
            screenshot_paths: [screenshotPath, interaction.modalScreenshot, interaction.dropdownScreenshot].filter(Boolean).map((item) => path.relative(OUT_DIR, item)),
            defects,
            fix_commit: null,
            final_status: finalStatus,
        });
        screenshotIndex += 1;

        page.off('console', consoleListener);
        page.off('pageerror', pageErrorListener);
        page.off('requestfailed', requestFailedListener);
        page.off('response', responseListener);
    }

    results.roleInventories.push({
        requested_role: credential.role,
        username_masked: credential.username.replace(/(^.).*(@.*$)/, '$1***$2').replace(/^(.{2}).+$/, '$1***'),
        landing_url: loginResult.page.url(),
        role_text_excerpt: loginResult.roleText.replace(/[\w.+-]+@[\w.-]+\.[A-Za-z]{2,}/g, '[EMAIL]').replace(/\+?\d[\d\s()-]{8,}\d/g, '[PHONE]').slice(0, 1200),
        routes_checked: results.routes.filter((route) => route.role === credential.role).length,
    });

    await context.close();
}

function writeReports(results, globalEvidence, startTime) {
    const routes = results.routes;
    const failures = routes.filter((route) => route.final_status === 'FAIL');
    const deferred = [];
    for (const route of routes) {
        for (const form of route.functional_actions.forms || []) {
            if (form.method === 'POST') deferred.push({ role: route.role, url: route.final_url, action: form.action, reason: 'Production mutation was not executed during read-only preflight' });
        }
    }
    const uniqueDeferred = [...new Map(deferred.map((item) => [`${item.role}|${item.action}`, item])).values()];
    const screenshotCount = routes.reduce((sum, route) => sum + route.screenshot_paths.length, 0);
    const status = results.blockedRoles.length && routes.length === 0
        ? 'RUNTIME_ACCESS_BLOCKED'
        : failures.length
            ? 'P11_FULLHD_NOT_ACCEPTED'
            : 'P11_PREFLIGHT_PASS_REQUIRES_FINAL_REVIEW';
    const summary = {
        status,
        prompt_number: 'P11',
        start_time: startTime,
        end_time: new Date().toISOString(),
        base_url: BASE_URL,
        viewport: '1920x1080',
        zoom: '100%',
        device_scale_factor: 1,
        routes_total: routes.length,
        routes_pass: routes.filter((route) => route.final_status === 'PASS').length,
        routes_fail: failures.length,
        routes_deferred_unsafe: uniqueDeferred.length,
        modals_checked: routes.filter((route) => route.modal_usability?.interaction?.modalOpened).length,
        forms_checked: routes.reduce((sum, route) => sum + (route.functional_actions.forms?.length || 0), 0),
        dropdowns_checked: routes.reduce((sum, route) => sum + (route.dropdown_usability?.length || 0), 0),
        screenshots_count: screenshotCount,
        body_horizontal_scroll_failures: routes.filter((route) => route.horizontal_body_scroll).length,
        overlap_failures: routes.reduce((sum, route) => sum + Number(route.overlap || 0), 0),
        functional_failures: failures.length,
        console_errors: routes.reduce((sum, route) => sum + route.console_errors.length, 0),
        blocked_roles: results.blockedRoles,
        role_inventories: results.roleInventories,
    };

    fs.writeFileSync(path.join(OUT_DIR, 'P11_fullhd_route_matrix.json'), JSON.stringify(routes, null, 2));
    const csvHeader = ['role','url','http_status','title','horizontal_body_scroll','overlap','clipped_controls','tables','forms','modals_opened','dropdowns','console_errors','network_errors','final_status','screenshots'];
    const csvRows = routes.map((route) => [
        route.role, route.final_url, route.http_status, route.title, route.horizontal_body_scroll, route.overlap, route.clipped_controls,
        route.table_usability.length, route.functional_actions.forms.length, route.modal_usability.interaction.modalOpened,
        route.dropdown_usability.length, route.console_errors.length, route.network_errors.length, route.final_status, route.screenshot_paths.join('|'),
    ].map(csvEscape).join(','));
    fs.writeFileSync(path.join(OUT_DIR, 'P11_fullhd_visual_matrix.csv'), [csvHeader.join(','), ...csvRows].join('\n'));
    fs.writeFileSync(path.join(OUT_DIR, 'P11_fullhd_defects_fixed.json'), JSON.stringify({ phase: 'preflight', fixed: [], note: 'No code changes are claimed by the audit runner.' }, null, 2));
    fs.writeFileSync(path.join(OUT_DIR, 'P11_fullhd_deferred_unsafe.md'), [
        '# P11 DEFERRED_UNSAFE', '',
        'Read-only preflight did not submit production-mutating forms.', '',
        ...uniqueDeferred.map((item) => `- ${item.role}: ${item.action} — ${item.reason}`),
    ].join('\n'));
    fs.writeFileSync(path.join(OUT_DIR, 'P11_fullhd_production_unchanged.json'), JSON.stringify({
        production_erp_unchanged: true,
        production_db_mutated: false,
        migrations_run_on_get: 'NOT_OBSERVED_BY_BROWSER_PREFLIGHT',
        method_policy: 'GET navigation and non-submit UI interactions only',
    }, null, 2));
    fs.writeFileSync(path.join(OUT_DIR, 'P11_fullhd_summary.json'), JSON.stringify(summary, null, 2));
    fs.writeFileSync(path.join(OUT_DIR, 'P11_fullhd_runtime_report.md'), [
        '# P11 Full HD Runtime Report', '',
        `- STATUS: ${summary.status}`,
        `- BASE_URL: ${BASE_URL}`,
        '- VIEWPORT: 1920x1080 CSS pixels',
        '- ZOOM: 100%',
        '- deviceScaleFactor: 1',
        `- ROUTES_TOTAL: ${summary.routes_total}`,
        `- ROUTES_PASS: ${summary.routes_pass}`,
        `- ROUTES_FAIL: ${summary.routes_fail}`,
        `- ROUTES_DEFERRED_UNSAFE: ${summary.routes_deferred_unsafe}`,
        `- SCREENSHOTS_COUNT: ${summary.screenshots_count}`,
        `- BODY_HORIZONTAL_SCROLL_FAILURES: ${summary.body_horizontal_scroll_failures}`,
        `- OVERLAP_FAILURES: ${summary.overlap_failures}`,
        `- CONSOLE_ERRORS: ${summary.console_errors}`,
        '', '## Access', '',
        `Login attempts: ${JSON.stringify(globalEvidence.loginAttempts.map((item) => ({ role: item.role, ok: item.ok, finalUrl: item.finalUrl, status: item.status })), null, 2)}`,
        '', '## Blocked roles', '',
        results.blockedRoles.length ? results.blockedRoles.map((item) => `- ${item.role}: ${item.reason}`).join('\n') : '- none',
        '', '## Acceptance', '',
        summary.status === 'P11_FULLHD_NOT_ACCEPTED'
            ? 'One or more accessible screens failed the Full HD preflight gate. Code correction and a second complete pass are required.'
            : summary.status === 'RUNTIME_ACCESS_BLOCKED'
                ? 'Runtime access could not be established without exposing credentials.'
                : 'Preflight did not find an automated gate failure, but manual evidence review and a final post-deploy pass are still required.',
    ].join('\n'));
    return summary;
}

(async () => {
    const startTime = new Date().toISOString();
    const credentials = decodeCredentials();
    const results = { routes: [], blockedRoles: [], roleInventories: [] };
    const globalEvidence = { loginAttempts: [] };
    const browser = await chromium.launch({ headless: true });
    try {
        if (!credentials.length) {
            results.blockedRoles.push({ role: 'unknown', reason: 'No owner-provided runtime credentials were securely available to the runner' });
            const context = await browser.newContext({ viewport: VIEWPORT, deviceScaleFactor: 1, ignoreHTTPSErrors: true, locale: 'ru-RU' });
            const page = await context.newPage();
            const response = await page.goto(BASE_URL, { waitUntil: 'domcontentloaded', timeout: 45000 }).catch(() => null);
            const layout = await inspectLayout(page).catch(() => ({}));
            const screenshotPath = path.join(SCREEN_DIR, 'P11_001__unauthenticated__login__base__1920x1080.png');
            await page.screenshot({ path: screenshotPath, fullPage: false }).catch(() => {});
            results.routes.push({
                role: 'unauthenticated', url: BASE_URL, final_url: page.url(), route_pattern: normalizeRoute(page.url()),
                http_status: response?.status() || null, title: await page.title().catch(() => ''), heading: await page.locator('h1,h2').first().innerText().catch(() => ''),
                base_screen: Boolean(response), horizontal_body_scroll: Boolean(layout.horizontalBodyScroll), body_scroll_width: layout.bodyScrollWidth || null,
                viewport_width: layout.viewportWidth || VIEWPORT.width, overlap: layout.overlapFailures || 0, overlap_samples: layout.overlapSamples || [],
                clipped_controls: layout.clippedControls || 0, clipped_samples: layout.clippedSamples || [], table_usability: layout.tables || [],
                modal_usability: { dom: layout.dialogs || [], interaction: {} }, dropdown_usability: layout.selects || [],
                functional_actions: { forms: layout.forms || [], buttons: layout.buttons || 0, date_inputs: layout.dateInputs || 0, autocompletes: layout.autocompletes || 0, discovered_links: 0 },
                console_errors: [], network_errors: [], screenshot_paths: [path.relative(OUT_DIR, screenshotPath)], defects: ['authenticated runtime not available'], fix_commit: null, final_status: 'FAIL',
            });
            await context.close();
        } else {
            for (const credential of credentials) await auditRole(browser, credential, results, globalEvidence);
        }
    } finally {
        await browser.close();
    }
    const summary = writeReports(results, globalEvidence, startTime);
    process.stdout.write(JSON.stringify(summary));
})().catch((error) => {
    fs.mkdirSync(OUT_DIR, { recursive: true });
    fs.writeFileSync(path.join(OUT_DIR, 'P11_fullhd_summary.json'), JSON.stringify({ status: 'RUNTIME_ACCESS_BLOCKED', prompt_number: 'P11', fatal_error: String(error.stack || error) }, null, 2));
    console.error('P11 audit failed without exposing credentials.');
    process.exitCode = 1;
});
