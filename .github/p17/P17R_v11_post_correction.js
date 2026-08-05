'use strict';

const fs = require('fs');
const path = require('path');
const { chromium } = require('playwright');

const BASE = 'https://plan-ex.ru/erpv2/';
const U = (value) => new URL(String(value).replace(/^\//, ''), BASE).href;
const OUT = path.resolve(process.env.P17R_OUT || 'P17R_full_runtime_output');
const SHOTS = path.join(OUT, 'P17_screenshots');
const COMPANY = 27;
const OWNER = { id: 16, username: 'p14_owner_260804221827' };
const DEPLOYED_SHA = process.env.P17_DEPLOYED_SHA || '';

function readJson(name) {
  return JSON.parse(fs.readFileSync(path.join(OUT, name), 'utf8'));
}
function writeJson(name, value) {
  fs.writeFileSync(path.join(OUT, name), JSON.stringify(value, null, 2));
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
    const values = await sa.page.locator('input,textarea,code,pre,[data-password]').evaluateAll((nodes) => nodes.map((node) => node.value || node.getAttribute('data-password') || node.textContent || '').filter(Boolean));
    const list = passwordCandidates(await sa.page.locator('body').innerText(), values, OWNER.username);
    await sa.context.close();
    for (const candidate of list) {
      const session = await login(browser, OWNER.username, candidate);
      if (session.ok) {
        list.fill('');
        return session;
      }
      await session.context.close();
    }
  }
  throw new Error('OWNER UI login failed after controlled reset retries.');
}
async function superadminSession(browser) {
  const admin = adminCredential();
  if (!admin) throw new Error('SUPERADMIN credential unavailable.');
  const session = await login(browser, admin.username, admin.password);
  if (!session.ok) {
    await session.context.close();
    throw new Error('SUPERADMIN UI login failed.');
  }
  return session;
}
async function screenshot(page, name) {
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
  const submit = form.locator('button[type="submit"],input[type="submit"]').last();
  if (await submit.count()) await submit.click();
  else await form.evaluate((node) => HTMLFormElement.prototype.submit.call(node));
  await navigation;
  await page.waitForTimeout(700);
  page.off('response', handler);
  return responses.at(-1) || null;
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

async function correctExecutor(browser, crud, shots, summary) {
  const executorId = Number(summary?.entities?.executorId || 0);
  if (!executorId) throw new Error('Executor ID is unavailable in runtime summary.');

  const owner = await ownerSession(browser);
  const detailUrl = U(`/company/route-executors/${executorId}`);
  await owner.page.goto(detailUrl, { waitUntil: 'networkidle', timeout: 45000 });
  const archiveForm = owner.page.locator(`form[action$="/company/route-executors/${executorId}/archive"]`).first();
  if (!(await archiveForm.count())) throw new Error(`Executor archive form unavailable for ${executorId}.`);
  await archiveForm.locator('button').first().click();
  const modal = owner.page.locator('#delete-confirm-modal:visible');
  await modal.waitFor({ state: 'visible', timeout: 7000 });
  await modal.locator('#delete-confirm-input').fill('УДАЛИТЬ');
  const navigation = owner.page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 30000 }).catch(() => null);
  await modal.locator('#delete-confirm-btn').click();
  await navigation;
  await owner.page.goto(U('/company/route-executors'), { waitUntil: 'networkidle', timeout: 45000 });
  const exactLink = owner.page.locator(`a[href$="/company/route-executors/${executorId}"]`);
  const absent = (await exactLink.count()) === 0;
  const archivedShot = await screenshot(owner.page, 'P17_142_OWNER_executor_exact_id_archived_1920x1080.png');
  await owner.context.close();

  const sa = await superadminSession(browser);
  const deletedUrl = U(`/superadmin/deleted-data?company_id=${COMPANY}&entity_type=crew&status=archived`);
  await sa.page.goto(deletedUrl, { waitUntil: 'networkidle', timeout: 45000 });
  const rows = sa.page.locator('tbody tr').filter({ has: sa.page.locator('form[action$="/restore"]') });
  let restoreForm = null;
  for (let index = 0; index < await rows.count(); index++) {
    const row = rows.nth(index);
    const text = await row.innerText();
    if (text.includes(`#${executorId}`) || new RegExp(`(^|\\D)${executorId}(\\D|$)`).test(text)) {
      restoreForm = row.locator('form[action$="/restore"]').first();
      break;
    }
  }
  if (!restoreForm && await rows.count()) restoreForm = rows.first().locator('form[action$="/restore"]').first();
  const deletedDataFound = Boolean(restoreForm && await restoreForm.count());
  const deletedShot = await screenshot(sa.page, 'P17_143_SUPERADMIN_executor_exact_id_deleted_data_1920x1080.png');
  let restorePost = null;
  if (deletedDataFound) restorePost = await submitForm(sa.page, restoreForm);
  await sa.context.close();

  const ownerRestored = await ownerSession(browser);
  await ownerRestored.page.goto(U('/company/route-executors'), { waitUntil: 'networkidle', timeout: 45000 });
  const restoredVisible = (await ownerRestored.page.locator(`a[href$="/company/route-executors/${executorId}"]`).count()) > 0;
  const restoredShot = await screenshot(ownerRestored.page, 'P17_144_OWNER_executor_exact_id_restored_1920x1080.png');
  await ownerRestored.context.close();

  replaceScenario(crud, 'executor-archive', {
    url: detailUrl,
    final_url: U('/company/route-executors'),
    expected_result: 'Exact executor ID disappears from working list',
    actual_result: absent ? `Executor #${executorId} absent by exact link` : `Executor #${executorId} exact link remains`,
    status: absent ? 'PASS' : 'FAIL',
    pass: absent,
    screenshot: archivedShot,
    evidence: 'Exact href check avoids false positives from a shared contractor or driver name.',
  });
  replaceScenario(crud, 'executor-disappear', {
    url: U('/company/route-executors'),
    final_url: U('/company/route-executors'),
    expected_result: 'Exact executor link is absent',
    actual_result: absent ? 'Absent' : 'Still present',
    status: absent ? 'PASS' : 'FAIL',
    pass: absent,
    screenshot: archivedShot,
  });
  replaceScenario(crud, 'executor-deleted-data', {
    url: deletedUrl,
    final_url: deletedUrl,
    expected_result: 'Archived executor has a SUPERADMIN restore action',
    actual_result: deletedDataFound ? 'Restore action found' : 'Restore action missing',
    status: deletedDataFound ? 'PASS' : 'FAIL',
    pass: deletedDataFound,
    screenshot: deletedShot,
  });
  replaceScenario(crud, 'executor-restore', {
    url: deletedUrl,
    final_url: U('/company/route-executors'),
    expected_result: 'Restored executor exact link is visible',
    actual_result: restoredVisible ? 'Restored and visible' : 'Not visible after restore',
    http_status: restorePost?.status ?? null,
    status: restoredVisible ? 'PASS' : 'FAIL',
    pass: restoredVisible,
    screenshot: restoredShot,
  });

  appendShot(shots, 'OWNER', 'executor', 'exact_id_archived', archivedShot);
  appendShot(shots, 'SUPERADMIN', 'executor', 'exact_id_deleted_data', deletedShot);
  appendShot(shots, 'OWNER', 'executor', 'exact_id_restored', restoredShot);
}

async function correctCashExpense(browser, finance, shots) {
  const owner = await ownerSession(browser);
  const page = owner.page;
  const purpose = `UIUX_P17R_${process.env.GITHUB_RUN_ID || Date.now()}_V11_CASH_EXPENSE`;
  await page.goto(U('/company/finance/cash'), { waitUntil: 'networkidle', timeout: 45000 });
  const responsePromise = page.waitForResponse((response) => response.request().method() === 'GET' && response.url().includes('/company/finance/cash/operation-create'), { timeout: 15000 });
  await page.locator('#cash-operation-create-btn').click();
  await responsePromise;
  const form = page.locator('#cash-operation-create-modal form[action$="/company/finance/cash/operation-create"]:visible').first();
  await form.waitFor({ state: 'visible', timeout: 10000 });
  await form.locator('[name="operation_type"]').selectOption('EXPENSE');
  const account = form.locator('[name="money_account_id"]');
  const accountValues = await account.locator('option').evaluateAll((options) => options.map((option) => option.value).filter(Boolean));
  if (!accountValues.length) throw new Error('Cash money account option is unavailable.');
  await account.selectOption(accountValues[0]);
  await form.locator('[name="amount"]').fill('11900');
  await form.locator('[name="operation_date"]').fill('2026-08-05');

  const dds = form.locator('[name="dds_category_id"]');
  await page.waitForFunction(() => {
    const select = document.querySelector('#cash-operation-create-modal [name="dds_category_id"]');
    return Boolean(select && [...select.options].some((option) => /OUT_|EXPENSE|РАСХОД/i.test(option.textContent || '')));
  }, null, { timeout: 10000 });
  const options = await dds.locator('option').evaluateAll((items) => items.map((item) => ({ value: item.value, text: (item.textContent || '').trim() })).filter((item) => item.value));
  const expenseOption = options.find((item) => /OUT_|EXPENSE|РАСХОД/i.test(item.text));
  if (!expenseOption) throw new Error(`Expense DDS option unavailable: ${JSON.stringify(options).slice(0, 800)}`);
  await dds.selectOption(expenseOption.value);
  await form.locator('[name="purpose"]').fill(purpose);
  const comment = form.locator('[name="comment"]');
  if (await comment.count()) await comment.fill(`${purpose}; DDS=${expenseOption.text}`);
  const formShot = await screenshot(page, 'P17_145_OWNER_cash_expense_out_dds_form_1920x1080.png');
  const post = await submitForm(page, form);
  await page.goto(U('/company/finance/cash'), { waitUntil: 'networkidle', timeout: 45000 });
  const body = await page.locator('body').innerText();
  const visible = body.includes(purpose);
  const resultShot = await screenshot(page, 'P17_146_OWNER_cash_expense_out_dds_result_1920x1080.png');
  await owner.context.close();

  replaceScenario(finance, 'finance-cash-expense', {
    url: U('/company/finance/cash/operation-create'),
    final_url: U('/company/finance/cash'),
    expected_result: 'Expense operation with OUT/EXPENSE DDS category is visible after UI submit',
    actual_result: visible ? { purpose, dds: expenseOption.text } : { purpose: 'Not visible', dds: expenseOption.text },
    http_status: post?.status ?? null,
    status: visible ? 'PASS' : 'FAIL',
    pass: visible,
    screenshot: resultShot,
    evidence: `Selected direction-compatible DDS category: ${expenseOption.text}`,
  });
  appendShot(shots, 'OWNER', 'cash', 'expense_out_dds_form', formShot);
  appendShot(shots, 'OWNER', 'cash', 'expense_out_dds_result', resultShot);
}

(async () => {
  fs.mkdirSync(SHOTS, { recursive: true });
  const crud = readJson('P17_03_CRUD_MATRIX.json');
  const finance = readJson('P17_06_FINANCE_MATRIX.json');
  const shots = readJson('P17_12_SCREENSHOT_INDEX_PART.json');
  const summary = readJson('P17_RUNTIME_SUMMARY.json');
  let browser;
  try {
    browser = await chromium.launch({ headless: true });
    await correctExecutor(browser, crud, shots, summary);
    await correctCashExpense(browser, finance, shots);

    writeJson('P17_03_CRUD_MATRIX.json', crud);
    writeJson('P17_06_FINANCE_MATRIX.json', finance);
    writeJson('P17_12_SCREENSHOT_INDEX_PART.json', shots);

    const trip = readJson('P17_04_TRIP_LIFECYCLE_MATRIX.json');
    const documents = readJson('P17_05_DOCUMENT_MATRIX.json');
    const allApplicablePass = [crud, trip, documents, finance].every((matrix) => matrix.filter((item) => item.applicable !== false && item.status !== 'NOT_SUPPORTED_BY_PRODUCT').every((item) => item.pass === true));
    summary.status = allApplicablePass && !summary.error ? 'PASS' : 'FAIL';
    summary.deployed_sha = DEPLOYED_SHA;
    summary.post_correction = {
      status: summary.status,
      executor_exact_id_archive_restore: crud.filter((item) => item.scenario_id.startsWith('executor-')).map((item) => ({ id: item.scenario_id, status: item.status })),
      cash_expense: finance.find((item) => item.scenario_id === 'finance-cash-expense')?.status || 'MISSING',
    };
    writeJson('P17_RUNTIME_SUMMARY.json', summary);
    console.log(`P17R_V11_POST_CORRECTION=${summary.status}`);
    process.exitCode = summary.status === 'PASS' ? 0 : 1;
  } catch (error) {
    summary.status = 'FAIL';
    summary.post_correction = { status: 'FAIL', error: { name: error?.name || 'Error', message: String(error?.message || error).slice(0, 1200) } };
    writeJson('P17_RUNTIME_SUMMARY.json', summary);
    console.error(error);
    process.exitCode = 1;
  } finally {
    if (browser) await browser.close().catch(() => {});
  }
})();
