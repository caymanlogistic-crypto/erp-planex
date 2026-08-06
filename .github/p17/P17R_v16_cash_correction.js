'use strict';

const fs = require('fs');
const path = require('path');
const { chromium } = require('playwright');

const BASE = 'https://plan-ex.ru/erpv2/';
const U = (value) => new URL(String(value).replace(/^\//, ''), BASE).href;
const OUT = path.resolve(process.env.P17R_OUT || 'P17R_full_runtime_output');
const SHOTS = path.join(OUT, 'P17_screenshots');
const COMPANY = 27;
const OWNER = { username: 'p14_owner_260804221827' };
const DEPLOYED_SHA = process.env.P17_DEPLOYED_SHA || '';

const readJson = (name) => JSON.parse(fs.readFileSync(path.join(OUT, name), 'utf8'));
const writeJson = (name, value) => fs.writeFileSync(path.join(OUT, name), JSON.stringify(value, null, 2));

function scenarioList(matrix) {
  if (Array.isArray(matrix)) return matrix;
  if (matrix && Array.isArray(matrix.scenarios)) return matrix.scenarios;
  return [];
}

function credentials() {
  const encoded = process.env.P17R_CREDENTIALS_B64 || process.env.P17_CREDENTIALS_B64 || '';
  return encoded ? JSON.parse(Buffer.from(encoded, 'base64').toString('utf8')) : [];
}

function adminCredential() {
  return credentials().find((item) => String(item?.role || '').toUpperCase() === 'SUPERADMIN');
}

async function login(browser, username, password) {
  const context = await browser.newContext({
    viewport: { width: 1920, height: 1080 },
    deviceScaleFactor: 1,
    locale: 'ru-RU',
    timezoneId: 'Europe/Moscow',
    ignoreHTTPSErrors: true,
  });
  const page = await context.newPage();
  try {
    await page.goto(U('/login'), { waitUntil: 'domcontentloaded', timeout: 45000 });
    await page.locator('input[name="username"],input[name="email"],input[type="text"]').first().fill(username);
    await page.locator('input[type="password"]').first().fill(password);
    await Promise.all([
      page.waitForLoadState('domcontentloaded', { timeout: 20000 }).catch(() => {}),
      page.locator('button[type="submit"],input[type="submit"]').first().click(),
    ]);
    await page.waitForTimeout(500);
    return { context, page, ok: !/\/login(?:[/?#]|$)/i.test(page.url()) };
  } catch (error) {
    return { context, page, ok: false, error: String(error?.message || error) };
  }
}

function passwordCandidates(text, values, username) {
  const candidates = values.map(String);
  for (const match of String(text).matchAll(/[A-Za-z0-9!@#$%^&*()_+\-=]{8,64}/g)) candidates.push(match[0]);
  return [...new Set(candidates)].filter((value) => value !== username && !value.includes('@example.test') && !/^[a-f0-9]{64}$/i.test(value));
}

async function ownerSession(browser) {
  const admin = adminCredential();
  if (!admin) throw new Error('SUPERADMIN credential unavailable.');

  for (let attempt = 1; attempt <= 4; attempt++) {
    const sa = await login(browser, admin.username, admin.password);
    if (!sa.ok) {
      await sa.context.close();
      continue;
    }

    await sa.page.goto(U(`/superadmin/companies/${COMPANY}/users`), { waitUntil: 'domcontentloaded', timeout: 45000 });
    const form = sa.page.locator(`form[action$="/superadmin/companies/${COMPANY}/owner/reset-password"]`).first();
    if (!(await form.count())) {
      await sa.context.close();
      continue;
    }

    const navigation = sa.page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 30000 }).catch(() => null);
    await form.evaluate((node) => HTMLFormElement.prototype.submit.call(node));
    await navigation;

    const values = await sa.page.locator('input,textarea,code,pre,[data-password]').evaluateAll((nodes) =>
      nodes.map((node) => node.value || node.getAttribute('data-password') || node.textContent || '').filter(Boolean)
    );
    const candidates = passwordCandidates(await sa.page.locator('body').innerText(), values, OWNER.username);
    await sa.context.close();

    for (const candidate of candidates) {
      const session = await login(browser, OWNER.username, candidate);
      if (session.ok) {
        candidates.fill('');
        return session;
      }
      await session.context.close();
    }
  }

  throw new Error('OWNER UI login failed after controlled reset retries.');
}

async function screenshot(page, name) {
  fs.mkdirSync(SHOTS, { recursive: true });
  const relative = `P17_screenshots/${name}`;
  await page.screenshot({ path: path.join(OUT, relative), fullPage: false });
  return relative;
}

async function submitForm(page, form) {
  const responses = [];
  const handler = (response) => {
    if (response.request().method() === 'POST') responses.push({ status: response.status(), url: response.url() });
  };
  page.on('response', handler);

  const navigation = page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 30000 }).catch(() => null);
  const button = form.locator('button[type="submit"],input[type="submit"]').last();
  if (await button.count()) await button.click();
  else await form.evaluate((node) => HTMLFormElement.prototype.submit.call(node));
  await navigation;
  await page.waitForTimeout(800);

  page.off('response', handler);
  return responses.at(-1) || null;
}

async function openCashOperation(page) {
  await page.goto(U('/company/finance/cash'), { waitUntil: 'networkidle', timeout: 45000 });
  const responsePromise = page.waitForResponse(
    (response) => response.request().method() === 'GET' && response.url().includes('/company/finance/cash/operation-create'),
    { timeout: 15000 }
  );
  await page.locator('#cash-operation-create-btn').click();
  await responsePromise;
  const form = page.locator('#cash-operation-create-modal form[action$="/company/finance/cash/operation-create"]:visible').first();
  await form.waitFor({ state: 'visible', timeout: 10000 });
  return form;
}

async function performCashOperation(page, config) {
  const form = await openCashOperation(page);
  await form.locator('[name="operation_type"]').selectOption(config.type);

  const account = form.locator('[name="money_account_id"]');
  const accountOptions = await account.locator('option').evaluateAll((items) =>
    items.map((item) => ({ value: item.value, text: (item.textContent || '').trim() })).filter((item) => item.value)
  );
  const selectedAccount = config.accountId && accountOptions.some((item) => item.value === config.accountId)
    ? config.accountId
    : accountOptions[0]?.value;
  if (!selectedAccount) throw new Error('No active cash account is available.');
  await account.selectOption(selectedAccount);

  await form.locator('[name="amount"]').fill(config.amount);
  await form.locator('[name="operation_date"]').fill(new Date().toISOString().slice(0, 10));

  const dds = form.locator('[name="dds_category_id"]');
  const ddsInventory = await dds.locator('option').evaluateAll((items) => items.map((item) => ({
    value: item.value,
    text: (item.textContent || '').trim(),
    direction: item.getAttribute('data-direction') || '',
    disabled: item.disabled,
    hidden: item.hidden,
  })));
  // The product explicitly allows a cash operation without a DDS category.
  await dds.selectOption('');

  await form.locator('[name="purpose"]').fill(config.purpose);
  const comment = form.locator('[name="comment"]');
  if (await comment.count()) await comment.fill(`${config.purpose}; DDS optional; ${config.type}`);

  const formShot = await screenshot(page, config.formShot);
  const post = await submitForm(page, form);
  await page.goto(U('/company/finance/cash'), { waitUntil: 'networkidle', timeout: 45000 });
  const body = await page.locator('body').innerText();
  const visible = body.includes(config.purpose);
  const pageShot = await screenshot(page, config.resultShot);
  const notices = await page.locator('.notice,.form-alert').allTextContents();

  return {
    accountId: selectedAccount,
    accountLabel: accountOptions.find((item) => item.value === selectedAccount)?.text || '',
    ddsInventory,
    ddsSelected: null,
    formShot,
    pageShot,
    post,
    visible,
    notices,
  };
}

function replaceScenario(matrix, id, record) {
  const index = matrix.findIndex((item) => item.scenario_id === id);
  if (index < 0) throw new Error(`Scenario not found: ${id}`);
  matrix[index] = {
    ...matrix[index],
    ...record,
    scenario_id: id,
    deployed_sha: DEPLOYED_SHA,
    applicable: true,
  };
}

function appendShot(index, role, section, state, screenshotPath) {
  index.push({ role, section, state, viewport: '1920x1080', screenshot: screenshotPath, deployed_sha: DEPLOYED_SHA });
}

(async () => {
  const finance = readJson('P17_06_FINANCE_MATRIX.json');
  const crud = readJson('P17_03_CRUD_MATRIX.json');
  const tripRaw = readJson('P17_04_TRIP_LIFECYCLE_MATRIX.json');
  const documents = readJson('P17_05_DOCUMENT_MATRIX.json');
  const shots = readJson('P17_12_SCREENSHOT_INDEX_PART.json');
  const summary = readJson('P17_RUNTIME_SUMMARY.json');
  const evidence = { version: 'V16', prefund: null, expense: null, errors: [] };
  let browser;

  try {
    browser = await chromium.launch({ headless: true });
    const owner = await ownerSession(browser);
    const runKey = String(process.env.GITHUB_RUN_ID || Date.now());

    try {
      evidence.prefund = await performCashOperation(owner.page, {
        type: 'INCOME',
        amount: '1000.00',
        purpose: `UIUX_P17R_${runKey}_V16_CASH_PREFUND`,
        formShot: 'P17_153_OWNER_cash_prefund_form_v16_1920x1080.png',
        resultShot: 'P17_154_OWNER_cash_prefund_result_v16_1920x1080.png',
      });
      if (!evidence.prefund.visible) {
        throw new Error(`Cash prefund was not visible after submit: ${JSON.stringify(evidence.prefund.notices)}`);
      }

      evidence.expense = await performCashOperation(owner.page, {
        type: 'EXPENSE',
        amount: '117.00',
        purpose: `UIUX_P17R_${runKey}_V16_CASH_EXPENSE`,
        accountId: evidence.prefund.accountId,
        formShot: 'P17_155_OWNER_cash_expense_form_v16_1920x1080.png',
        resultShot: 'P17_156_OWNER_cash_expense_result_v16_1920x1080.png',
      });

      const pass = evidence.expense.visible && Number(evidence.expense.post?.status || 0) < 400;
      replaceScenario(finance, 'finance-cash-expense', {
        url: U('/company/finance/cash/operation-create'),
        final_url: U('/company/finance/cash'),
        expected_result: 'Expense is posted to a prefunded cash account and visible in the cash journal',
        actual_result: {
          expense_visible: evidence.expense.visible,
          expense_post_status: evidence.expense.post?.status ?? null,
          prefund_visible: evidence.prefund.visible,
          account_id: evidence.expense.accountId,
          dds_category: null,
          dds_optional_by_product: true,
          notices: evidence.expense.notices,
        },
        http_status: evidence.expense.post?.status ?? null,
        status: pass ? 'PASS' : 'FAIL',
        pass,
        screenshot: evidence.expense.pageShot,
        evidence: 'The same cash account was prefunded through the UI; DDS is optional in the product and intentionally left empty.',
      });

      appendShot(shots, 'OWNER', 'cash', 'prefund_form_v16', evidence.prefund.formShot);
      appendShot(shots, 'OWNER', 'cash', 'prefund_result_v16', evidence.prefund.pageShot);
      appendShot(shots, 'OWNER', 'cash', 'expense_form_v16', evidence.expense.formShot);
      appendShot(shots, 'OWNER', 'cash', 'expense_result_v16', evidence.expense.pageShot);
    } catch (error) {
      evidence.errors.push({ name: error?.name || 'Error', message: String(error?.message || error).slice(0, 1800) });
    } finally {
      await owner.context.close();
      writeJson('P17_06_FINANCE_MATRIX.json', finance);
      writeJson('P17_12_SCREENSHOT_INDEX_PART.json', shots);
      writeJson('P17_V16_CASH_DIAGNOSTIC.json', evidence);
    }

    const matrices = [
      scenarioList(crud),
      scenarioList(tripRaw),
      scenarioList(documents),
      scenarioList(finance),
    ];
    const allApplicablePass = matrices.every((matrix) =>
      matrix.filter((item) => item.applicable !== false && item.status !== 'NOT_SUPPORTED_BY_PRODUCT').every((item) => item.pass === true)
    );

    summary.status = allApplicablePass && evidence.errors.length === 0 ? 'PASS' : 'FAIL';
    summary.deployed_sha = DEPLOYED_SHA;
    summary.post_correction = { ...evidence, status: summary.status };
    delete summary.error;
    writeJson('P17_RUNTIME_SUMMARY.json', summary);

    console.log(`P17R_V16_CASH_CORRECTION=${summary.status}`);
    if (summary.status !== 'PASS') console.log(JSON.stringify(evidence, null, 2));
    process.exitCode = summary.status === 'PASS' ? 0 : 1;
  } catch (error) {
    summary.status = 'FAIL';
    summary.post_correction = {
      version: 'V16',
      status: 'FAIL',
      error: { name: error?.name || 'Error', message: String(error?.message || error).slice(0, 1800) },
      ...evidence,
    };
    writeJson('P17_RUNTIME_SUMMARY.json', summary);
    writeJson('P17_V16_CASH_DIAGNOSTIC.json', summary.post_correction);
    console.error(error);
    process.exitCode = 1;
  } finally {
    if (browser) await browser.close().catch(() => {});
  }
})();
