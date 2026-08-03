'use strict';

const fs = require('fs');
const path = require('path');
const { chromium } = require('playwright');

const BASE_URL = (process.env.P14_BASE_URL || 'https://plan-ex.ru/erpv2/').replace(/\/+$/, '/');
const OUT_DIR = path.resolve(process.env.P14_OUT_DIR || 'P14_output');
const SCREENSHOT = path.join(OUT_DIR, 'P14_linear_trip_edit_opened_1920x1080.png');
fs.mkdirSync(OUT_DIR, { recursive: true });

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

function selectOwnerCredential(credentials) {
    return credentials.find((item) => /company_owner|owner|руковод/i.test(String(item.role || ''))) || credentials[0] || null;
}

async function login(page, credential) {
    for (const candidate of [new URL('login', BASE_URL).href, BASE_URL]) {
        await page.goto(candidate, { waitUntil: 'domcontentloaded', timeout: 30000 }).catch(() => null);
        if (await page.locator('input[type="password"]').count()) break;
    }

    const userInput = page.locator('input[name="username"],input[name="email"],input[type="email"],input[type="text"]').first();
    const passwordInput = page.locator('input[type="password"]').first();
    if (!(await userInput.count()) || !(await passwordInput.count())) {
        throw new Error('Login form was not detected.');
    }

    await userInput.fill(credential.username);
    await passwordInput.fill(credential.password);
    await Promise.all([
        page.waitForLoadState('domcontentloaded', { timeout: 15000 }).catch(() => {}),
        page.locator('button[type="submit"],input[type="submit"]').first().click({ timeout: 5000 }),
    ]);
    await page.waitForTimeout(1000);

    if (/\/login(?:[/?#]|$)/i.test(page.url()) || (await page.locator('input[type="password"]').count()) > 0) {
        throw new Error('Authentication failed.');
    }
}

async function verifyEditModal(page, evidence) {
    await page.goto(new URL('company/trips/linear', BASE_URL).href, { waitUntil: 'domcontentloaded', timeout: 30000 });
    const firstRow = page.locator('tr[data-linear-route-id]').first();
    await firstRow.waitFor({ state: 'visible', timeout: 12000 });
    evidence.routeId = await firstRow.getAttribute('data-linear-route-id');
    if (!evidence.routeId) throw new Error('Linear trip id was not found.');

    const directResult = await page.evaluate(async ({ routeId }) => {
        const meta = document.querySelector('meta[name="erp-base-path"]');
        const basePath = meta ? String(meta.getAttribute('content') || '') : '';
        const url = `${basePath}/company/trips/linear/${routeId}/modal-edit`;
        const response = await fetch(url, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        return { status: response.status, url: response.url, body: (await response.text()).slice(0, 1500) };
    }, { routeId: evidence.routeId });
    evidence.directModalResponse = directResult;
    if (directResult.status !== 200 || !directResult.body.includes('linear-trip-edit-form')) {
        throw new Error('Direct modal-edit check failed: ' + JSON.stringify(directResult));
    }

    const modalResponses = [];
    const responseHandler = async (response) => {
        if (!/\/company\/trips\/linear\/\d+\/modal-edit(?:[?#]|$)/.test(response.url())) return;
        let body = '';
        try { body = await response.text(); } catch (error) {}
        modalResponses.push({ status: response.status(), url: response.url(), body: body.slice(0, 1000) });
    };
    page.on('response', responseHandler);

    await firstRow.dblclick({ timeout: 8000 });
    const editButton = page.locator('[data-linear-trip-edit-btn]').first();
    await editButton.waitFor({ state: 'visible', timeout: 10000 });
    await editButton.click();

    const form = page.locator('#linear-trip-edit-form');
    await form.waitFor({ state: 'visible', timeout: 12000 });
    const errorNotice = page.locator('.modal-overlay.is-open .notice.warn').first();
    const errorText = (await errorNotice.count()) ? (await errorNotice.innerText().catch(() => '')).trim() : '';
    if (errorText) throw new Error('Edit modal returned warning: ' + errorText);

    const token = await form.locator('input[name="_linear_trip_save_token"]').inputValue().catch(() => '');
    if (!token) throw new Error('P13 save token is absent from the edit form.');

    const response = modalResponses[modalResponses.length - 1] || null;
    if (!response || response.status !== 200) {
        throw new Error('UI modal-edit response is not HTTP 200: ' + JSON.stringify(response));
    }

    evidence.modalResponse = { status: response.status, url: response.url };
    evidence.formAction = await form.getAttribute('action');
    evidence.formEncoding = await form.getAttribute('enctype');
    evidence.tokenPresent = true;
    evidence.modalOpened = true;
    await page.screenshot({ path: SCREENSHOT, fullPage: false });
    page.off('response', responseHandler);
}

(async () => {
    const evidence = {
        status: 'FAIL',
        baseUrl: BASE_URL,
        commit: process.env.GITHUB_SHA || null,
        attempts: [],
        consoleErrors: [],
        pageErrors: [],
        failedRequests: [],
    };

    const credentials = decodeCredentials();
    const credential = selectOwnerCredential(credentials);
    if (!credential) {
        evidence.error = 'Runtime credentials are unavailable.';
        fs.writeFileSync(path.join(OUT_DIR, 'P14_runtime_result.json'), JSON.stringify(evidence, null, 2));
        process.exit(2);
    }

    const browser = await chromium.launch({ headless: true });
    const context = await browser.newContext({
        viewport: { width: 1920, height: 1080 },
        ignoreHTTPSErrors: true,
        locale: 'ru-RU',
        timezoneId: 'Europe/Moscow',
    });
    const page = await context.newPage();
    page.on('console', (message) => { if (message.type() === 'error') evidence.consoleErrors.push(message.text()); });
    page.on('pageerror', (error) => evidence.pageErrors.push(String(error.message || error)));
    page.on('requestfailed', (request) => evidence.failedRequests.push(`${request.method()} ${request.url()} :: ${request.failure()?.errorText || 'failed'}`));

    try {
        await login(page, credential);
        for (let attempt = 1; attempt <= 12; attempt += 1) {
            try {
                await verifyEditModal(page, evidence);
                evidence.attempts.push({ attempt, result: 'PASS' });
                evidence.status = 'PASS';
                break;
            } catch (error) {
                evidence.attempts.push({ attempt, result: 'FAIL', error: String(error.message || error).slice(0, 2000) });
                if (attempt < 12) await page.waitForTimeout(15000);
            }
        }
        if (evidence.status !== 'PASS') {
            evidence.error = evidence.attempts[evidence.attempts.length - 1]?.error || 'Edit modal did not open.';
        }
    } catch (error) {
        evidence.error = String(error.message || error);
    } finally {
        await page.screenshot({ path: path.join(OUT_DIR, 'P14_final_state_1920x1080.png'), fullPage: false }).catch(() => {});
        await context.close();
        await browser.close();
    }

    fs.writeFileSync(path.join(OUT_DIR, 'P14_runtime_result.json'), JSON.stringify(evidence, null, 2));
    console.log(JSON.stringify(evidence));
    process.exit(evidence.status === 'PASS' ? 0 : 1);
})();
