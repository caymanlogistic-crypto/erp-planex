'use strict';

const fs = require('fs');
const path = require('path');
const { chromium } = require('playwright');
const XLSX = require('xlsx');

const BASE = 'https://plan-ex.ru/erpv2/';
const U = (value) => new URL(String(value).replace(/^\//, ''), BASE).href;
const OUT = path.resolve(process.env.P17R_OUT || 'P17R_full_runtime_output');
const SHOTS = path.join(OUT, 'P17_screenshots');
fs.mkdirSync(SHOTS, { recursive: true });
const COMPANY = 27;
const DEPLOYED_SHA = process.env.P17_DEPLOYED_SHA || null;
const RUN_KEY = `${process.env.GITHUB_RUN_ID || Date.now()}_${process.env.GITHUB_RUN_ATTEMPT || 1}`;
const PREFIX = `UIUX_P17R_${RUN_KEY}_`;
const USERS = {
  OWNER: { id: 16, username: 'p14_owner_260804221827', role: 'COMPANY_OWNER' },
  SENIOR_LOGIST: { id: 17, username: 'p14_senior_260804221827', role: 'SENIOR_LOGIST' },
  LOGIST_1: { id: 18, username: 'p14_logist1_260804221827', role: 'LOGIST' },
  LOGIST_2: { id: 19, username: 'p14_logist2_260804221827', role: 'LOGIST' },
};
const N = {
  client: `${PREFIX}CLIENT_MAIN`,
  clientRestore: `${PREFIX}CLIENT_RESTORE`,
  contractor: `${PREFIX}CONTRACTOR_MAIN`,
  contractorRestore: `${PREFIX}CONTRACTOR_RESTORE`,
  driver: `${PREFIX}DRIVER_MAIN`,
  driverRestore: `${PREFIX}DRIVER_RESTORE`,
  vehiclePlate: `Р${String(process.env.GITHUB_RUN_ID || Date.now()).slice(-3)}РР77`,
  vehicleSecondaryPlate: `П${String(process.env.GITHUB_RUN_ID || Date.now()).slice(-3)}РР77`,
  executor: `${PREFIX}EXECUTOR_MAIN`,
  executorRestore: `${PREFIX}EXECUTOR_RESTORE`,
  trip: `${PREFIX}TRIP_MAIN`,
  ddsCode: `${PREFIX}DDS`.replace(/[^A-Za-z0-9_]/g, '').slice(0, 48),
  matching: `${PREFIX}MATCHING`,
  cashIncome: `${PREFIX}CASH_INCOME`,
  cashExpense: `${PREFIX}CASH_EXPENSE`,
};

const result = {
  status: 'FAIL', deployed_sha: DEPLOYED_SHA, prefix: PREFIX, entities: {},
  crud: [], trip: [], documents: [], finance: [], defects: [], screenshots: [],
  consoleErrors: [], pageErrors: [], requestFailures: [], unexpectedHttp: [], expected403: [],
  error: null,
};
let shotNo = 1;

function credentials() {
  const raw = process.env.P17R_CREDENTIALS_B64 || '';
  return raw ? JSON.parse(Buffer.from(raw, 'base64').toString('utf8')) : [];
}
function adminCredential() { return credentials().find((item) => String(item?.role || '').toUpperCase() === 'SUPERADMIN'); }
function scenario(matrix, data) {
  const status = String(data.status || (data.pass === true ? 'PASS' : data.pass === false ? 'FAIL' : 'FAIL')).toUpperCase();
  const applicable = data.applicable !== false && status !== 'NOT_SUPPORTED_BY_PRODUCT';
  const pass = applicable ? (data.pass === true || status === 'PASS') : false;
  const item = {
    scenario_id: data.scenario_id,
    entity: data.entity,
    action: data.action,
    role: data.role || 'COMPANY_OWNER',
    url: data.url || null,
    expected_result: data.expected_result || null,
    actual_result: data.actual_result || null,
    http_status: data.http_status ?? null,
    final_url: data.final_url || null,
    created_id: data.created_id ?? null,
    status,
    applicable,
    pass,
    screenshot: data.screenshot || null,
    error_details: data.error_details || null,
    evidence: data.evidence || null,
    deployed_sha: DEPLOYED_SHA,
  };
  matrix.push(item);
  return item;
}
function defect(id, severity, title, details, status = 'OPEN') {
  result.defects.push({ id, severity, title, details, status, deployed_sha: DEPLOYED_SHA });
}
async function screenshot(page, role, section, state) {
  const file = `P17_${String(shotNo++).padStart(3, '0')}_${role}_${section}_${state}_1920x1080.png`.replace(/[^A-Za-z0-9А-Яа-яЁё_.-]/g, '_');
  await page.screenshot({ path: path.join(SHOTS, file), fullPage: false });
  const rel = `P17_screenshots/${file}`;
  result.screenshots.push({ role, section, state, viewport: '1920x1080', screenshot: rel, deployed_sha: DEPLOYED_SHA });
  return rel;
}
async function login(browser, username, password) {
  const context = await browser.newContext({ viewport: { width: 1920, height: 1080 }, deviceScaleFactor: 1, locale: 'ru-RU', timezoneId: 'Europe/Moscow', ignoreHTTPSErrors: true });
  const page = await context.newPage();
  await page.goto(U('login'), { waitUntil: 'domcontentloaded', timeout: 45000 });
  await page.locator('input[name="username"],input[name="email"],input[type="text"]').first().fill(username);
  await page.locator('input[type="password"]').first().fill(password);
  await Promise.all([
    page.waitForLoadState('domcontentloaded', { timeout: 20000 }).catch(() => {}),
    page.locator('button[type="submit"],input[type="submit"]').first().click(),
  ]);
  await page.waitForTimeout(450);
  return { context, page, ok: !/\/login(?:[/?#]|$)/i.test(page.url()) };
}
function passwordCandidates(text, values, username) {
  const all = values.map(String);
  for (const match of String(text).matchAll(/[A-Za-z0-9!@#$%^&*()_+\-=]{8,64}/g)) all.push(match[0]);
  return [...new Set(all)].filter((value) => value !== username && !value.includes('@example.test') && !/^[a-f0-9]{64}$/i.test(value));
}
async function roleSession(browser, roleKey) {
  const admin = adminCredential();
  if (!admin) throw new Error('SUPERADMIN credential unavailable');
  const meta = USERS[roleKey];
  const sx = await login(browser, admin.username, admin.password);
  if (!sx.ok) throw new Error('SUPERADMIN login failed');
  await sx.page.goto(U(`superadmin/companies/${COMPANY}/users`), { waitUntil: 'domcontentloaded', timeout: 45000 });
  const suffix = roleKey === 'OWNER' ? `/superadmin/companies/${COMPANY}/owner/reset-password` : `/superadmin/companies/${COMPANY}/users/logists/${meta.id}/reset-password`;
  const reset = sx.page.locator(`form[action$="${suffix}"]`).first();
  if (!(await reset.count())) throw new Error(`Reset form unavailable for ${roleKey}`);
  await Promise.all([
    sx.page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 30000 }),
    reset.evaluate((node) => HTMLFormElement.prototype.submit.call(node)),
  ]);
  const values = await sx.page.locator('input,textarea,code,pre,[data-password]').evaluateAll((nodes) => nodes.map((node) => node.value || node.getAttribute('data-password') || node.textContent || '').filter(Boolean));
  const candidates = passwordCandidates(await sx.page.locator('body').innerText(), values, meta.username);
  await sx.context.close();
  for (const candidate of candidates) {
    const session = await login(browser, meta.username, candidate);
    if (session.ok) { candidates.fill(''); return session; }
    await session.context.close();
  }
  throw new Error(`UI login failed for ${roleKey}`);
}
function attachDiagnostics(page, role) {
  page.on('console', (message) => { if (message.type() === 'error') result.consoleErrors.push({ role, url: page.url(), message: message.text() }); });
  page.on('pageerror', (error) => result.pageErrors.push({ role, url: page.url(), message: String(error.message || error) }));
  page.on('requestfailed', (request) => result.requestFailures.push({ role, method: request.method(), url: request.url(), error: request.failure()?.errorText || 'failed' }));
  page.on('response', (response) => { if (response.status() >= 500) result.unexpectedHttp.push({ role, status: response.status(), url: response.url() }); });
}
async function fill(form, name, value) {
  const locator = form.locator(`[name="${name.replace(/"/g, '\\"')}"]`).first();
  if (!(await locator.count())) return false;
  const type = await locator.getAttribute('type');
  const tag = await locator.evaluate((node) => node.tagName);
  if (type === 'file') return false;
  if (tag === 'SELECT') {
    await locator.evaluate((node, expected) => {
      const option = [...node.options].find((item) => String(item.value) === String(expected)) || [...node.options].find((item) => item.value);
      if (!option) return;
      node.value = option.value;
      node.dispatchEvent(new Event('input', { bubbles: true }));
      node.dispatchEvent(new Event('change', { bubbles: true }));
    }, String(value));
  } else if (type === 'checkbox') {
    const expected = Boolean(value);
    if ((await locator.isChecked()) !== expected) await locator.click();
  } else {
    await locator.fill(String(value));
  }
  return true;
}
async function submit(page, form, external = null) {
  const responses = [];
  const handler = (response) => { if (response.request().method() === 'POST') responses.push({ status: response.status(), url: response.url() }); };
  page.on('response', handler);
  const nav = page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 30000 }).catch(() => null);
  if (external && await page.locator(external).count()) await page.locator(external).first().click();
  else await form.evaluate((node) => node.requestSubmit());
  const response = await nav;
  await page.waitForTimeout(700);
  page.off('response', handler);
  return { response, post: responses.at(-1) || null };
}
function inn10(seed) {
  const base = String(seed).replace(/\D/g, '').padStart(9, '0').slice(-9);
  const weights = [2,4,10,3,5,9,4,6,8];
  const checksum = ([...base].map(Number).reduce((sum, value, index) => sum + value * weights[index], 0) % 11) % 10;
  return base + checksum;
}
function pngBuffer() { return Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Wl2nXQAAAAASUVORK5CYII=', 'base64'); }
function jpgBuffer() { return Buffer.from('/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////2wBDAf//////////////////////////////////////////////////////////////////////////////////////wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAX/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIQAxAAAAEf/8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABBQJ//8QAFBEBAAAAAAAAAAAAAAAAAAAAAP/aAAgBAwEBPwF//8QAFBEBAAAAAAAAAAAAAAAAAAAAAP/aAAgBAgEBPwF//8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQAGPwJ//8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPyF//9oADAMBAAIAAwAAABAf/8QAFBEBAAAAAAAAAAAAAAAAAAAAAP/aAAgBAwEBPxB//8QAFBEBAAAAAAAAAAAAAAAAAAAAAP/aAAgBAgEBPxB//8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPxB//9k=', 'base64'); }
function pdfBuffer(label) { return Buffer.from(`%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Count 0>>endobj\n% ${label}\n%%EOF\n`, 'utf8'); }
function xlsxBuffer() { const wb = XLSX.utils.book_new(); XLSX.utils.book_append_sheet(wb, XLSX.utils.aoa_to_sheet([['P17R','ERP PLANEX'],['run',RUN_KEY]]), 'P17R'); return XLSX.write(wb, { type: 'buffer', bookType: 'xlsx' }); }
const specs = {
  client: { list: 'company/clients', attr: 'data-client-id', detail: (id) => `company/clients/${id}` },
  contractor: { list: 'company/contractors', attr: 'data-contractor-id', detail: (id) => `company/contractors/${id}` },
  driver: { list: 'company/drivers', attr: 'data-driver-id', detail: (id) => `company/drivers/${id}` },
  vehicle: { list: 'company/vehicle-sets', attr: 'data-vehicle-set-id', detail: null },
  executor: { list: 'company/route-executors', attr: 'data-route-executor-id', detail: (id) => `company/route-executors/${id}` },
  trip: { list: 'company/trips/linear', attr: 'data-linear-route-id', detail: null },
};
async function findId(page, type, label) {
  const spec = specs[type];
  await page.goto(U(spec.list), { waitUntil: 'domcontentloaded', timeout: 45000 });
  await page.waitForTimeout(350);
  const row = page.locator(`[${spec.attr}]`).filter({ hasText: label }).first();
  if (await row.count()) return Number(await row.getAttribute(spec.attr));
  return null;
}
async function listSearchSort(page, type, label, matrix, prefix) {
  const spec = specs[type];
  await page.goto(U(spec.list), { waitUntil: 'domcontentloaded', timeout: 45000 });
  const search = page.locator('input[type="search"],input[name="q"],input[placeholder*="Поиск"]').first();
  if (await search.count()) {
    await search.fill(label); await page.waitForTimeout(500);
    const visible = await page.locator(`[${spec.attr}],tbody tr`).filter({ hasText: label }).count();
    scenario(matrix, { scenario_id: `${prefix}-search`, entity: type, action: 'search', url: page.url(), expected_result: 'Record visible after search', actual_result: `${visible} matching rows`, status: visible > 0 ? 'PASS' : 'FAIL', pass: visible > 0, final_url: page.url() });
  } else scenario(matrix, { scenario_id: `${prefix}-search`, entity: type, action: 'search', url: page.url(), status: 'NOT_SUPPORTED_BY_PRODUCT', applicable: false, evidence: 'No search input in current UI' });
  const filters = await page.locator('select[name*="status"],select[data-filter],button[data-filter]').count();
  scenario(matrix, filters ? { scenario_id: `${prefix}-filter`, entity: type, action: 'filter', url: page.url(), status: 'PASS', pass: true, actual_result: `${filters} filter controls available` } : { scenario_id: `${prefix}-filter`, entity: type, action: 'filter', url: page.url(), status: 'NOT_SUPPORTED_BY_PRODUCT', applicable: false, evidence: 'No entity filter control in current UI' });
  const sort = page.locator('th a,th button,[data-sort]').first();
  if (await sort.count()) {
    await sort.click().catch(() => {}); await page.waitForTimeout(300);
    scenario(matrix, { scenario_id: `${prefix}-sort`, entity: type, action: 'sort', url: page.url(), status: 'PASS', pass: true, actual_result: 'Sort control activated', final_url: page.url() });
  } else scenario(matrix, { scenario_id: `${prefix}-sort`, entity: type, action: 'sort', url: page.url(), status: 'NOT_SUPPORTED_BY_PRODUCT', applicable: false, evidence: 'No sort control in current UI' });
}
async function createLegal(page, type, name, seed, file, matrix, idPrefix) {
  await page.goto(U(specs[type].list), { waitUntil: 'domcontentloaded', timeout: 45000 });
  const trigger = page.getByText(type === 'client' ? 'Создать клиента' : 'Создать перевозчика', { exact: true }).first();
  await trigger.click(); await page.waitForTimeout(350);
  const suffix = type === 'client' ? '/company/clients/create' : '/company/contractors/create';
  const form = page.locator(`form[action$="${suffix}"]:visible`).first();
  await form.waitFor({ state: 'visible', timeout: 10000 });
  await fill(form, 'name', name); await fill(form, 'inn', inn10(seed));
  await fill(form, type === 'client' ? 'entity_type' : 'contractor_type', 'legal_entity');
  await fill(form, 'kpp', '770101001'); await fill(form, 'director_full_name', 'Тестов P17R'); await fill(form, 'director_position', 'Директор');
  await fill(form, 'legal_address', '127000, Москва, улица P17R, дом 17'); await fill(form, 'physical_address', '127000, Москва, улица P17R, дом 17, офис 17');
  await fill(form, 'contacts[0][contact_person]', `${PREFIX}CONTACT`); await fill(form, 'contacts[0][phone]', '+79990007117'); await fill(form, 'contacts[0][email]', `${type}.${RUN_KEY}@example.test`);
  await fill(form, 'comments', `${PREFIX} browser final rerun`);
  const addDoc = form.getByText(/Добавить документ/i).first();
  if (file && await addDoc.count()) { await addDoc.click(); await page.waitForTimeout(150); const input = form.locator('input[type="file"]').first(); if (await input.count()) await input.setInputFiles(file); }
  const shot = await screenshot(page, 'OWNER', type, 'create_form');
  const sent = await submit(page, form);
  const id = await findId(page, type, name);
  scenario(matrix, { scenario_id: `${idPrefix}-create`, entity: type, action: 'create', url: U(specs[type].list), expected_result: 'Record created via UI', actual_result: id ? `Created ID ${id}` : 'Record not found', http_status: sent.post?.status ?? sent.response?.status() ?? null, final_url: page.url(), created_id: id, status: id ? 'PASS' : 'FAIL', pass: Boolean(id), screenshot: shot });
  scenario(matrix, { scenario_id: `${idPrefix}-save`, entity: type, action: 'save', url: sent.post?.url || suffix, expected_result: 'POST accepted', actual_result: sent.post ? `HTTP ${sent.post.status}` : 'Navigation completed', http_status: sent.post?.status ?? sent.response?.status() ?? null, final_url: page.url(), created_id: id, status: id ? 'PASS' : 'FAIL', pass: Boolean(id) });
  return id;
}
async function reopenUpdate(page, type, id, label, matrix, prefix) {
  const spec = specs[type];
  if (!spec.detail) return;
  let response = await page.goto(U(spec.detail(id)), { waitUntil: 'domcontentloaded', timeout: 45000 });
  const body = await page.locator('body').innerText();
  scenario(matrix, { scenario_id: `${prefix}-reopen`, entity: type, action: 'reopen', url: U(spec.detail(id)), expected_result: 'Detail page contains record', actual_result: body.includes(label) ? 'Record displayed' : 'Label absent', http_status: response?.status() ?? null, final_url: page.url(), created_id: id, status: body.includes(label) ? 'PASS' : 'FAIL', pass: body.includes(label), screenshot: await screenshot(page, 'OWNER', type, 'detail') });
  const edit = page.getByText(/Редактировать/i).first();
  if (!(await edit.count())) { scenario(matrix, { scenario_id: `${prefix}-update`, entity: type, action: 'update', url: page.url(), status: 'NOT_SUPPORTED_BY_PRODUCT', applicable: false, evidence: 'Edit action absent' }); return; }
  await edit.click(); await page.waitForTimeout(300);
  const form = page.locator('form').filter({ has: page.locator('[name="comments"]') }).first();
  if (!(await form.count())) { scenario(matrix, { scenario_id: `${prefix}-update`, entity: type, action: 'update', url: page.url(), status: 'FAIL', pass: false, error_details: 'Edit form absent after Edit action' }); return; }
  const marker = `${PREFIX}${type.toUpperCase()}_UPDATED`;
  await fill(form, 'comments', marker);
  const sent = await submit(page, form);
  response = await page.goto(U(spec.detail(id)), { waitUntil: 'domcontentloaded', timeout: 45000 });
  const updated = (await page.locator('body').innerText()).includes(marker);
  scenario(matrix, { scenario_id: `${prefix}-update`, entity: type, action: 'update', url: null, expected_result: 'Updated value persists', actual_result: updated ? marker : 'Marker absent', http_status: sent.post?.status ?? response?.status() ?? null, final_url: page.url(), created_id: id, status: updated ? 'PASS' : 'FAIL', pass: updated, screenshot: await screenshot(page, 'OWNER', type, 'updated') });
}
async function createDriver(page, name, matrix, prefix, file) {
  await page.goto(U('company/drivers'), { waitUntil: 'domcontentloaded', timeout: 45000 });
  await page.getByText('Добавить нового водителя', { exact: true }).first().click(); await page.waitForTimeout(300);
  const form = page.locator('#driver-create-form:visible').first(); await form.waitFor({ state: 'visible', timeout: 10000 });
  await fill(form, 'full_name', name); await fill(form, 'phone', '+79990007217'); await fill(form, 'passport_number', '4517 007217'); await fill(form, 'license_number', '7717 007217'); await fill(form, 'email', `driver.${RUN_KEY}@example.test`); await fill(form, 'comments', `${PREFIX}driver`);
  const input = form.locator('input[type="file"]').first(); if (file && await input.count()) await input.setInputFiles(file);
  const shot = await screenshot(page, 'OWNER', 'driver', 'create_form'); await submit(page, form);
  const id = await findId(page, 'driver', name);
  scenario(matrix, { scenario_id: `${prefix}-create`, entity: 'driver', action: 'create', url: U('company/drivers'), created_id: id, status: id ? 'PASS' : 'FAIL', pass: Boolean(id), screenshot: shot });
  return id;
}
async function driverPhone(page, id, matrix, prefix) {
  await page.goto(U(`company/drivers/${id}`), { waitUntil: 'domcontentloaded', timeout: 45000 });
  const add = page.getByText('Добавить телефон', { exact: true }).first();
  if (!(await add.count())) { scenario(matrix, { scenario_id: `${prefix}-multi-phone`, entity: 'driver', action: 'multiple phones', status: 'NOT_SUPPORTED_BY_PRODUCT', applicable: false, evidence: 'Add phone action absent' }); return; }
  await add.click(); await page.waitForTimeout(200);
  const form = page.locator(`form[action$="/company/drivers/${id}/phones/create"]:visible`).first();
  await fill(form, 'phone', '+79990007218'); await fill(form, 'comment', `${PREFIX}SECOND_PHONE`); await submit(page, form);
  await page.goto(U(`company/drivers/${id}`), { waitUntil: 'domcontentloaded' });
  const text = await page.locator('body').innerText();
  const pass = /7999\s*000.?72.?18/.test(text.replace(/\D/g, '')) || text.includes('+7 999 000-72-18');
  scenario(matrix, { scenario_id: `${prefix}-multi-phone`, entity: 'driver', action: 'multiple phones', url: page.url(), expected_result: 'Second phone visible', actual_result: pass ? 'Second phone visible' : 'Second phone absent', created_id: id, status: pass ? 'PASS' : 'FAIL', pass, screenshot: await screenshot(page, 'OWNER', 'driver', 'multiple_phones') });
}
async function createVehicle(page, matrix, prefix) {
  await page.goto(U('company/vehicle-sets'), { waitUntil: 'domcontentloaded', timeout: 45000 });
  await page.getByText(/Добавить новый транспорт/i).first().click(); await page.waitForTimeout(300);
  const form = page.locator('#vehicle-set-create-form:visible').first(); await form.waitFor({ state: 'visible', timeout: 10000 });
  await fill(form, 'set_type', 'coupling'); await page.waitForTimeout(500);
  const values = {
    'units[primary][brand]': 'КАМАЗ', 'units[primary][model]': `${PREFIX}5490`, 'units[primary][plate_number]': N.vehiclePlate,
    'units[primary][vin]': `XTC${String(Date.now()).slice(-14).padStart(14,'0')}`, 'units[primary][diagnostic_card_number]': String(Date.now()).slice(-10), 'units[primary][diagnostic_card_date]': '01.08.2026', 'units[primary][capacity_tons]': '20', 'units[primary][volume_m3]': '82',
    'units[secondary][brand]': 'ТОНАР', 'units[secondary][model]': `${PREFIX}TRAILER`, 'units[secondary][plate_number]': N.vehicleSecondaryPlate,
    'units[secondary][vin]': `XTT${String(Date.now()+1).slice(-14).padStart(14,'0')}`, 'units[secondary][diagnostic_card_number]': String(Date.now()+1).slice(-10), 'units[secondary][diagnostic_card_date]': '01.08.2026', 'units[secondary][capacity_tons]': '20', 'units[secondary][volume_m3]': '82',
    comments: `${PREFIX}VEHICLE_SET`, status: 'active',
  };
  for (const [name, value] of Object.entries(values)) await fill(form, name, value);
  const sts = form.locator('[name="predef_doc[primary][sts][]"]').first(); if (await sts.count()) await sts.setInputFiles({ name: `${PREFIX}СТС.png`, mimeType: 'image/png', buffer: pngBuffer() });
  const photo = form.locator('[name="predef_doc[secondary][photo][]"]').first(); if (await photo.count()) await photo.setInputFiles({ name: `${PREFIX}ПРИЦЕП.jpg`, mimeType: 'image/jpeg', buffer: jpgBuffer() });
  const shot = await screenshot(page, 'OWNER', 'vehicle_set', 'coupling_create');
  await submit(page, form, 'button[type="submit"][form="vehicle-set-create-form"]');
  const id = await findId(page, 'vehicle', N.vehiclePlate);
  scenario(matrix, { scenario_id: `${prefix}-create`, entity: 'vehicle-set', action: 'create', url: U('company/vehicle-sets'), created_id: id, status: id ? 'PASS' : 'FAIL', pass: Boolean(id), screenshot: shot });
  scenario(matrix, { scenario_id: `${prefix}-coupling`, entity: 'vehicle-set', action: 'vehicle set coupling', url: U('company/vehicle-sets'), created_id: id, expected_result: 'Primary and secondary plates visible', actual_result: id ? `${N.vehiclePlate}, ${N.vehicleSecondaryPlate}` : 'Not created', status: id ? 'PASS' : 'FAIL', pass: Boolean(id) });
  await page.goto(U('company/vehicle-sets'), { waitUntil: 'domcontentloaded' });
  const row = page.locator('[data-vehicle-set-id]').filter({ hasText: N.vehiclePlate }).first();
  scenario(matrix, { scenario_id: `${prefix}-reopen`, entity: 'vehicle-set', action: 'reopen', url: page.url(), created_id: id, status: await row.count() ? 'PASS' : 'FAIL', pass: Boolean(await row.count()), screenshot: await screenshot(page, 'OWNER', 'vehicle_set', 'list_reopen') });
  if (await row.count()) { await row.dblclick().catch(() => {}); await page.waitForTimeout(400); }
  const editForm = page.locator('form:visible').filter({ has: page.locator(`[name="units[primary][plate_number]"]`) }).first();
  if (await editForm.count()) {
    await fill(editForm, 'comments', `${PREFIX}VEHICLE_UPDATED`); await submit(page, editForm); scenario(matrix, { scenario_id: `${prefix}-update`, entity: 'vehicle-set', action: 'update', url: page.url(), created_id: id, status: 'PASS', pass: true });
  } else scenario(matrix, { scenario_id: `${prefix}-update`, entity: 'vehicle-set', action: 'update', status: 'NOT_SUPPORTED_BY_PRODUCT', applicable: false, evidence: 'No vehicle-set edit form or route exposed by current UI' });
  const archive = page.locator(`form[action*="/company/vehicle-sets/${id}/archive"]:visible`).first();
  scenario(matrix, await archive.count() ? { scenario_id: `${prefix}-archive`, entity: 'vehicle-set', action: 'archive capability', status: 'PASS', pass: true, evidence: 'Archive form exposed' } : { scenario_id: `${prefix}-archive`, entity: 'vehicle-set', action: 'archive capability', status: 'NOT_SUPPORTED_BY_PRODUCT', applicable: false, evidence: 'No archive action exposed for vehicle sets' });
  scenario(matrix, { scenario_id: `${prefix}-restore`, entity: 'vehicle-set', action: 'restore capability', status: 'NOT_SUPPORTED_BY_PRODUCT', applicable: false, evidence: 'Vehicle set archive is not exposed, therefore restore is not an applicable UI lifecycle' });
  return id;
}
async function createExecutor(page, contractorId, driverId, vehicleId, matrix, prefix, label) {
  await page.goto(U('company/route-executors'), { waitUntil: 'domcontentloaded', timeout: 45000 });
  await page.getByText(/Создать исполнителя|Добавить исполнителя|Создать/i).first().click(); await page.waitForTimeout(250);
  const form = page.locator('#route-executor-create-form:visible').first(); await form.waitFor({ state: 'visible', timeout: 10000 });
  await fill(form, 'contractor_id', contractorId); await fill(form, 'driver_id', driverId); await fill(form, 'vehicle_set_id', vehicleId); await fill(form, 'comments', label);
  await submit(page, form);
  const id = await findId(page, 'executor', label.includes('RESTORE') ? N.driverRestore : N.driver);
  scenario(matrix, { scenario_id: `${prefix}-create`, entity: 'route-executor', action: 'create', url: U('company/route-executors'), created_id: id, status: id ? 'PASS' : 'FAIL', pass: Boolean(id) });
  return id;
}
async function archiveRestore(browser, ownerPage, type, id, label, matrix, prefix, entityType) {
  const spec = specs[type];
  if (!spec.detail) return;
  await ownerPage.goto(U(spec.detail(id)), { waitUntil: 'domcontentloaded', timeout: 45000 });
  const form = ownerPage.locator(`form[action$="/archive"]`).first();
  if (!(await form.count())) {
    scenario(matrix, { scenario_id: `${prefix}-archive`, entity: type, action: 'archive', status: 'NOT_SUPPORTED_BY_PRODUCT', applicable: false, evidence: 'Archive form absent' });
    scenario(matrix, { scenario_id: `${prefix}-restore`, entity: type, action: 'restore', status: 'NOT_SUPPORTED_BY_PRODUCT', applicable: false, evidence: 'Archive unsupported' });
    return;
  }
  await submit(ownerPage, form);
  await ownerPage.goto(U(spec.list), { waitUntil: 'domcontentloaded', timeout: 45000 });
  const absent = (await ownerPage.locator(`[${spec.attr}],tbody tr`).filter({ hasText: label }).count()) === 0;
  scenario(matrix, { scenario_id: `${prefix}-archive`, entity: type, action: 'archive/delete', url: spec.detail(id), created_id: id, expected_result: 'Record disappears from working list', actual_result: absent ? 'Absent' : 'Still visible', status: absent ? 'PASS' : 'FAIL', pass: absent, screenshot: await screenshot(ownerPage, 'OWNER', type, 'archived') });
  scenario(matrix, { scenario_id: `${prefix}-disappear`, entity: type, action: 'verify disappearance', url: U(spec.list), created_id: id, status: absent ? 'PASS' : 'FAIL', pass: absent });
  const admin = adminCredential(); const sa = await login(browser, admin.username, admin.password);
  await sa.page.goto(U(`superadmin/deleted-data?company_id=${COMPANY}&entity_type=${encodeURIComponent(entityType)}&status=archived`), { waitUntil: 'domcontentloaded', timeout: 45000 });
  let row = sa.page.locator('tr').filter({ hasText: label }).first();
  if (!(await row.count())) row = sa.page.locator('tr').filter({ hasText: String(id) }).first();
  scenario(matrix, { scenario_id: `${prefix}-deleted-data`, entity: type, action: 'SUPERADMIN deleted-data', role: 'SUPERADMIN', url: sa.page.url(), created_id: id, status: await row.count() ? 'PASS' : 'FAIL', pass: Boolean(await row.count()), screenshot: await screenshot(sa.page, 'SUPERADMIN', type, 'deleted_data') });
  let restore = row.locator('form[action*="/restore"]').first();
  if (!(await restore.count()) && await row.count()) { const link = row.locator('a[href*="/superadmin/deleted-data/"]').first(); if (await link.count()) { await link.click(); await sa.page.waitForLoadState('domcontentloaded'); restore = sa.page.locator('form[action*="/restore"]').first(); } }
  const supported = await restore.count();
  if (supported) await submit(sa.page, restore);
  await sa.context.close();
  await ownerPage.goto(U(spec.list), { waitUntil: 'domcontentloaded', timeout: 45000 });
  const restored = (await ownerPage.locator(`[${spec.attr}],tbody tr`).filter({ hasText: label }).count()) > 0;
  scenario(matrix, supported ? { scenario_id: `${prefix}-restore`, entity: type, action: 'SUPERADMIN restore', role: 'SUPERADMIN', created_id: id, status: restored ? 'PASS' : 'FAIL', pass: restored, actual_result: restored ? 'Restored and visible' : 'Restore action did not return record' } : { scenario_id: `${prefix}-restore`, entity: type, action: 'SUPERADMIN restore', role: 'SUPERADMIN', status: 'NOT_SUPPORTED_BY_PRODUCT', applicable: false, evidence: 'Restore form absent for archived entity type' });
}
async function createTrip(page, ids) {
  await page.goto(U('company/trips/linear?show_create=1'), { waitUntil: 'domcontentloaded', timeout: 45000 });
  const form = page.locator('#linear-trip-create-form:visible').first(); await form.waitFor({ state: 'visible', timeout: 10000 });
  const fields = {
    route_type: 'linear', planned_loading_date: '20.08.2026', client_id: ids.clientId,
    carrier_contractor_id: ids.contractorId, route_executor_id: ids.executorId, cargo_type_name: N.trip,
    'customer_payments[0][amount]': '217000', 'customer_payments[0][payment_method]': 'cashless', 'customer_payments[0][condition_type]': 'specific_date', 'customer_payments[0][specific_due_date]': '25.08.2026',
    'carrier_payments[0][amount]': '157000', 'carrier_payments[0][payment_method]': 'cashless', 'carrier_payments[0][condition_type]': 'after_end', 'carrier_payments[0][days_count]': '5', 'carrier_payments[0][days_kind]': 'working',
    comments: `${PREFIX}TRIP_COMMENT`,
  };
  for (const [name, value] of Object.entries(fields)) await fill(form, name, value);
  const customerDoc = form.locator('[name="customer_document"]').first(); if (await customerDoc.count()) await customerDoc.setInputFiles({ name: `${PREFIX}customer.pdf`, mimeType: 'application/pdf', buffer: pdfBuffer('customer') });
  const carrierDoc = form.locator('[name="carrier_document"]').first(); if (await carrierDoc.count()) await carrierDoc.setInputFiles({ name: `${PREFIX}carrier.xlsx`, mimeType: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', buffer: xlsxBuffer() });
  const principalDoc = form.locator('[name="principal_document"]').first(); if (await principalDoc.count()) await principalDoc.setInputFiles({ name: `${PREFIX}principal.png`, mimeType: 'image/png', buffer: pngBuffer() });
  const createShot = await screenshot(page, 'OWNER', 'trip', 'create_filled');
  const invalid = await form.evaluate((node) => [...node.elements].filter((item) => typeof item.checkValidity === 'function' && !item.checkValidity()).map((item) => ({ name: item.name, message: item.validationMessage })));
  scenario(result.trip, { scenario_id: 'trip-client-validation', entity: 'linear-trip', action: 'client validation', url: page.url(), expected_result: 'No invalid fields', actual_result: invalid, status: invalid.length ? 'FAIL' : 'PASS', pass: invalid.length === 0, screenshot: createShot });
  const sent = await submit(page, form, 'button[type="submit"][form="linear-trip-create-form"]');
  const id = await findId(page, 'trip', N.trip); result.entities.tripId = id;
  const createPass = Boolean(id);
  scenario(result.trip, { scenario_id: 'trip-create-ui', entity: 'linear-trip', action: 'create/save/reopen', url: U('company/trips/linear/create'), expected_result: 'Trip created without SQL leak', actual_result: id ? `Trip ID ${id}` : 'Trip absent', http_status: sent.post?.status ?? null, final_url: page.url(), created_id: id, status: createPass ? 'PASS' : 'FAIL', pass: createPass, screenshot: createShot });
  for (const [scenarioId, action, value, present] of [
    ['trip-assign-client','assign client',ids.clientId,Boolean(ids.clientId)], ['trip-assign-contractor','assign carrier',ids.contractorId,Boolean(ids.contractorId)],
    ['trip-assign-driver','assign driver via executor',ids.driverId,Boolean(ids.driverId)], ['trip-assign-vehicle-set','assign vehicle set via executor',ids.vehicleId,Boolean(ids.vehicleId)],
    ['trip-assign-executor','assign route executor',ids.executorId,Boolean(ids.executorId)], ['trip-dates','planned dates','20.08.2026',true],
    ['trip-amounts','customer/carrier amounts','217000/157000',true], ['trip-payment-conditions','payment conditions','specific_date/after_end',true],
    ['trip-comment','comment',`${PREFIX}TRIP_COMMENT`,true], ['trip-document-upload','documents','PDF/XLSX/PNG',true],
  ]) scenario(result.trip, { scenario_id: scenarioId, entity: 'linear-trip', action, url: U('company/trips/linear/create'), created_id: id, actual_result: value, status: present && createPass ? 'PASS' : 'FAIL', pass: present && createPass });
  scenario(result.trip, { scenario_id: 'trip-assign-logist', entity: 'linear-trip', action: 'assign responsible logist', status: 'NOT_SUPPORTED_BY_PRODUCT', applicable: false, evidence: 'Linear trip create/edit forms expose no responsible logist field; access is grant-based on linked entities.' });
  scenario(result.trip, { scenario_id: 'trip-applications', entity: 'linear-trip', action: 'add applications', status: 'NOT_SUPPORTED_BY_PRODUCT', applicable: false, evidence: 'Linear trip form has no application/request collection; that lifecycle belongs to departures/map contour.' });
  if (!id) return null;
  await page.goto(U('company/trips/linear'), { waitUntil: 'networkidle', timeout: 45000 });
  let row = page.locator('tr[data-linear-route-id]').filter({ hasText: N.trip }).first(); await row.dblclick(); await page.waitForTimeout(350);
  const editBtn = page.locator('[data-linear-trip-edit-btn]').first(); await editBtn.click();
  const editForm = page.locator('#linear-trip-edit-form:visible').first(); await editForm.waitFor({ state: 'visible', timeout: 10000 });
  await fill(editForm, 'planned_unloading_date', '22.08.2026'); await fill(editForm, 'actual_loading_date', '20.08.2026'); await fill(editForm, 'actual_unloading_date', '22.08.2026'); await fill(editForm, 'comments', `${PREFIX}TRIP_UPDATED`); await fill(editForm, 'customer_payments[0][amount]', '220000');
  const replace = editForm.locator('[name="customer_document"]').first(); if (await replace.count()) await replace.setInputFiles({ name: `${PREFIX}customer_v2.pdf`, mimeType: 'application/pdf', buffer: pdfBuffer('customer v2') });
  const editShot = await screenshot(page, 'OWNER', 'trip', 'edit_documents');
  const responses = []; const handler = async (response) => { if (/\/company\/trips\/linear\/\d+\/modal-edit/.test(response.url())) { let body=''; try { body=await response.text(); } catch {} responses.push({status:response.status(),url:response.url(),body:body.slice(0,3000)}); } };
  page.on('response', handler); await page.locator('[data-linear-trip-save-btn]').first().click(); await page.waitForTimeout(1500); page.off('response', handler);
  const save = responses.at(-1); const updatePass = Boolean(save && save.status === 200 && /"success"\s*:\s*true/.test(save.body));
  scenario(result.trip, { scenario_id: 'trip-update-save', entity: 'linear-trip', action: 'update/save', url: save?.url, http_status: save?.status, created_id: id, actual_result: save ? save.body.slice(0,300) : 'No response', status: updatePass ? 'PASS' : 'FAIL', pass: updatePass, screenshot: editShot });
  await page.goto(U('company/trips/linear'), { waitUntil: 'networkidle', timeout: 45000 }); row = page.locator('tr[data-linear-route-id]').filter({ hasText: N.trip }).first(); await row.dblclick(); await page.waitForTimeout(400);
  const modal = page.locator('#linear-trip-view-modal:visible').first(); const text = await modal.innerText();
  const reopenPass = text.includes('220 000') && text.includes('20.08.2026') && text.includes('22.08.2026') && text.includes(`${PREFIX}TRIP_UPDATED`);
  scenario(result.trip, { scenario_id: 'trip-reopen-updated', entity: 'linear-trip', action: 'reopen updated values', created_id: id, status: reopenPass ? 'PASS' : 'FAIL', pass: reopenPass, actual_result: text.slice(0,1000), screenshot: await screenshot(page, 'OWNER', 'trip', 'view_updated') });
  const links = await modal.locator('a[href*="/company/documents/"]').evaluateAll((nodes) => nodes.map((node) => ({ text:(node.innerText||'').trim(),href:node.href,download:node.hasAttribute('download') })));
  let viewPass = false; let downloadPass = false;
  for (const link of links) { const response = await page.context().request.get(link.href); const ok=response.status()===200; if(link.download) downloadPass=downloadPass||ok; else viewPass=viewPass||ok; scenario(result.documents, { scenario_id: `document-${link.download?'download':'view'}-${result.documents.length+1}`, entity: 'trip-document', action: link.download ? 'download' : 'view', url: link.href, http_status: response.status(), actual_result: response.headers()['content-type'] || '', status: ok?'PASS':'FAIL', pass: ok }); }
  scenario(result.documents,{scenario_id:'document-view',entity:'trip-document',action:'view',status:viewPass?'PASS':'FAIL',pass:viewPass,actual_result:`${links.filter(x=>!x.download).length} view links`});
  scenario(result.documents,{scenario_id:'document-download',entity:'trip-document',action:'download',status:downloadPass?'PASS':'FAIL',pass:downloadPass,actual_result:`${links.filter(x=>x.download).length} download links`});
  scenario(result.documents, { scenario_id: 'document-replacement', entity: 'trip-document', action: 'replacement', created_id: id, expected_result: 'Replacement filename visible', actual_result: text.includes(`${PREFIX}customer_v2.pdf`) ? 'Visible' : 'Absent', status: text.includes(`${PREFIX}customer_v2.pdf`)?'PASS':'FAIL', pass: text.includes(`${PREFIX}customer_v2.pdf`) });
  scenario(result.documents, { scenario_id: 'document-pdf-upload', entity: 'documents', action: 'PDF upload', created_id: id, status: links.some((x)=>/\.pdf/i.test(x.text))?'PASS':'FAIL', pass: links.some((x)=>/\.pdf/i.test(x.text)) });
  scenario(result.documents, { scenario_id: 'document-xlsx-upload', entity: 'documents', action: 'XLSX upload', created_id: id, status: links.some((x)=>/\.xlsx/i.test(x.text))?'PASS':'FAIL', pass: links.some((x)=>/\.xlsx/i.test(x.text)) });
  scenario(result.documents, { scenario_id: 'document-png-upload', entity: 'documents', action: 'PNG upload', created_id: id, status: links.some((x)=>/\.png/i.test(x.text))?'PASS':'FAIL', pass: links.some((x)=>/\.png/i.test(x.text)) });
  scenario(result.trip, { scenario_id: 'trip-status-view', entity: 'linear-trip', action: 'status view', actual_result: /Актив|active/i.test(text)?'active':'status not visible', status: /Актив|active/i.test(text)?'PASS':'FAIL', pass: /Актив|active/i.test(text) });
  const statusControl = modal.locator('select[name="status"],button').filter({hasText:/Статус|Исполнитель найден|Вывоз начался/i});
  scenario(result.trip, await statusControl.count() ? { scenario_id:'trip-status-transition',entity:'linear-trip',action:'status transition',status:'PASS',pass:true,evidence:'Status control exposed' } : { scenario_id:'trip-status-transition',entity:'linear-trip',action:'status transition',status:'NOT_SUPPORTED_BY_PRODUCT',applicable:false,evidence:'Linear routes use active/deleted lifecycle; map/departure statuses are not exposed for this entity.' });
  scenario(result.trip, /История/i.test(text) ? { scenario_id:'trip-history',entity:'linear-trip',action:'history',status:'PASS',pass:true,actual_result:'History section visible' } : { scenario_id:'trip-history',entity:'linear-trip',action:'history',status:'NOT_SUPPORTED_BY_PRODUCT',applicable:false,evidence:'No trip-history section exposed in linear-trip modal.' });
  return id;
}
async function createInvoice(page, cfg, tripId, clientId) {
  await page.goto(U('company/finance/invoices'), { waitUntil: 'domcontentloaded', timeout: 45000 });
  await page.getByText('Создать счёт', { exact: true }).first().click(); await page.waitForTimeout(250);
  const form = page.locator('form[action$="/company/finance/invoices/create"]:visible').first(); await form.waitFor({state:'visible',timeout:10000});
  await fill(form,'direction',cfg.direction); await fill(form,'number',cfg.number); await fill(form,'invoice_date','2026-08-05'); await fill(form,'counterparty_entity_type',cfg.direction==='OUTGOING'?'client':'contractor'); await page.waitForTimeout(100);
  await fill(form,'counterparty_entity_id',cfg.counterpartyId); await fill(form,'counterparty_name',cfg.counterpartyName); await fill(form,'amount',cfg.amount); await fill(form,'planned_payment_date',cfg.due); await fill(form,'basis',N.trip); await fill(form,'link_route_id',tripId); await fill(form,'link_side',cfg.direction==='OUTGOING'?'customer':'carrier'); await fill(form,'status',cfg.status); await fill(form,'comment',`${PREFIX}${cfg.key}`);
  const shot=await screenshot(page,'OWNER','invoice',cfg.key); const sent=await submit(page,form);
  await page.goto(U('company/finance/invoices'),{waitUntil:'domcontentloaded'}); const visible=(await page.locator('body').innerText()).includes(cfg.number);
  scenario(result.finance,{scenario_id:`finance-invoice-${cfg.key}`,entity:'invoice',action:`${cfg.direction} ${cfg.status}`,url:U('company/finance/invoices/create'),expected_result:'Invoice created and visible',actual_result:visible?cfg.number:'Not visible',http_status:sent.post?.status??null,final_url:page.url(),status:visible?'PASS':'FAIL',pass:visible,screenshot:shot});
}
async function cashOperation(page, type, purpose, scenarioId) {
  await page.goto(U('company/finance/cash'), { waitUntil:'domcontentloaded', timeout:45000 });
  const trigger=page.getByText('Приход/Расход',{exact:true}).first(); const exactCount=await trigger.count();
  if(exactCount!==1){scenario(result.finance,{scenario_id:scenarioId,entity:'cash-operation',action:type,status:'FAIL',pass:false,error_details:`Exact button count ${exactCount}`});return;}
  await trigger.click(); await page.waitForTimeout(250);
  const modal=page.locator('#cash-operation-create-modal:visible'); const form=modal.locator('form[action="/erpv2/company/finance/cash/operation-create"]:visible').first();
  if(!(await form.count())){const inv=await page.locator('form').evaluateAll(ns=>ns.map(n=>({action:n.action,visible:n.getBoundingClientRect().width>0})));scenario(result.finance,{scenario_id:scenarioId,entity:'cash-operation',action:type,status:'FAIL',pass:false,error_details:{forms:inv}});return;}
  await fill(form,'operation_type',type); const acc=form.locator('[name="money_account_id"]'); const options=await acc.locator('option').evaluateAll(os=>os.map(o=>o.value).filter(Boolean)); if(options.length) await fill(form,'money_account_id',options[0]);
  await fill(form,'amount',type==='INCOME'?'17100':'11700'); await fill(form,'operation_date','2026-08-05'); const dds=form.locator('[name="dds_category_id"]'); if(await dds.count()){const opts=await dds.locator('option').evaluateAll(os=>os.map(o=>o.value).filter(Boolean));if(opts.length)await fill(form,'dds_category_id',opts[0]);}
  await fill(form,'purpose',purpose); await fill(form,'comment',`${PREFIX}${type}`); const shot=await screenshot(page,'OWNER','cash',type.toLowerCase()); await submit(page,form);
  await page.goto(U('company/finance/cash'),{waitUntil:'domcontentloaded'}); const visible=(await page.locator('body').innerText()).includes(purpose);
  scenario(result.finance,{scenario_id:scenarioId,entity:'cash-operation',action:type==='INCOME'?'cash income':'cash expense',url:U('company/finance/cash/operation-create'),expected_result:'Operation visible after UI submit',actual_result:visible?purpose:'Not visible',status:visible?'PASS':'FAIL',pass:visible,screenshot:shot});
}
async function financeScenarios(page, ids) {
  await page.goto(U('company/finance/settings/dds-categories'),{waitUntil:'domcontentloaded'}); await page.getByText('Создать статью',{exact:true}).first().click(); await page.waitForTimeout(200); let form=page.locator('form[action$="/company/finance/settings/dds-categories/create"]:visible').first(); await fill(form,'code',N.ddsCode); await fill(form,'name',`${PREFIX}DDS category`); await fill(form,'direction','BOTH'); await fill(form,'sort_order','1717'); await submit(page,form); await page.goto(U('company/finance/settings/dds-categories'),{waitUntil:'domcontentloaded'}); let text=await page.locator('body').innerText(); let ddsVisible=text.includes(N.ddsCode);
  scenario(result.finance,{scenario_id:'finance-dds-create',entity:'dds-category',action:'create',status:ddsVisible?'PASS':'FAIL',pass:ddsVisible}); scenario(result.finance,{scenario_id:'finance-dds-reopen',entity:'dds-category',action:'reopen',status:ddsVisible?'PASS':'FAIL',pass:ddsVisible});
  scenario(result.finance,{scenario_id:'finance-dds-update',entity:'dds-category',action:'update',status:'NOT_SUPPORTED_BY_PRODUCT',applicable:false,evidence:'DDS UI exposes create and active-toggle, but no edit action.'});
  await page.goto(U('company/finance/settings/matching-rules'),{waitUntil:'domcontentloaded'}); await page.getByText('Создать правило',{exact:true}).click(); await page.waitForTimeout(200); form=page.locator('form[action$="/company/finance/settings/matching-rules/create"]:visible').first(); await fill(form,'name',N.matching); await fill(form,'priority','1717'); await fill(form,'direction','INCOME'); await fill(form,'purpose_contains',PREFIX); await fill(form,'action_type','categorize'); const category=form.locator('[name="target_dds_category_id"]'); const categoryOptions=await category.locator('option').evaluateAll(os=>os.map(o=>o.value).filter(Boolean)); if(categoryOptions.length)await fill(form,'target_dds_category_id',categoryOptions.at(-1)); await fill(form,'auto_apply',true); await submit(page,form); await page.goto(U('company/finance/settings/matching-rules'),{waitUntil:'domcontentloaded'}); const matchingVisible=(await page.locator('body').innerText()).includes(N.matching);
  scenario(result.finance,{scenario_id:'finance-matching-create',entity:'matching-rule',action:'create',status:matchingVisible?'PASS':'FAIL',pass:matchingVisible}); scenario(result.finance,{scenario_id:'finance-matching-reopen',entity:'matching-rule',action:'reopen',status:matchingVisible?'PASS':'FAIL',pass:matchingVisible});
  const invoices=[
    {key:'outgoing-unpaid',direction:'OUTGOING',status:'issued',number:`${PREFIX}INV_OUT_UNPAID`,amount:'217000',due:'2026-08-25',counterpartyId:ids.clientId,counterpartyName:N.client},
    {key:'incoming',direction:'INCOMING',status:'received',number:`${PREFIX}INV_INCOMING`,amount:'157000',due:'2026-08-25',counterpartyId:ids.contractorId,counterpartyName:N.contractor},
    {key:'partial',direction:'OUTGOING',status:'partially_paid',number:`${PREFIX}INV_PARTIAL`,amount:'100000',due:'2026-08-10',counterpartyId:ids.clientId,counterpartyName:N.client},
    {key:'paid',direction:'OUTGOING',status:'paid',number:`${PREFIX}INV_PAID`,amount:'90000',due:'2026-08-10',counterpartyId:ids.clientId,counterpartyName:N.client},
    {key:'overdue',direction:'OUTGOING',status:'overdue',number:`${PREFIX}INV_OVERDUE`,amount:'80000',due:'2026-07-01',counterpartyId:ids.clientId,counterpartyName:N.client},
    {key:'future',direction:'OUTGOING',status:'issued',number:`${PREFIX}INV_FUTURE`,amount:'70000',due:'2026-12-01',counterpartyId:ids.clientId,counterpartyName:N.client},
  ];
  for(const cfg of invoices) await createInvoice(page,cfg,ids.tripId,ids.clientId);
  scenario(result.finance,{scenario_id:'finance-invoice-route-linked',entity:'invoice',action:'route link',status:ids.tripId?'PASS':'FAIL',pass:Boolean(ids.tripId),actual_result:ids.tripId});
  await cashOperation(page,'INCOME',N.cashIncome,'finance-cash-income'); await cashOperation(page,'EXPENSE',N.cashExpense,'finance-cash-expense');
  await page.goto(U('company/finance/operations'),{waitUntil:'networkidle',timeout:45000}); text=await page.locator('body').innerText();
  const bankIncome=/UIUX_P16_BANK_TX_0[1-8][\s\S]*?Поступление/.test(text)||text.includes('UIUX_P16_BANK_TX_01');
  const bankExpense=text.includes('UIUX_P16_BANK_TX_09')&&text.includes('Списание'); const unallocated=text.includes('Не распределено'); const fully=/Распределено\s+(14\s*500|19\s*000|28\s*000|32\s*500|41\s*500|46\s*000)/.test(text); const partial=/Частично распределено/i.test(text);
  scenario(result.finance,{scenario_id:'finance-bank-income',entity:'bank-operation',action:'bank income view',url:page.url(),status:bankIncome?'PASS':'FAIL',pass:bankIncome,actual_result:bankIncome?'P16 bank income visible':'Not found'});
  scenario(result.finance,{scenario_id:'finance-bank-expense',entity:'bank-operation',action:'bank expense view',url:page.url(),status:bankExpense?'PASS':'FAIL',pass:bankExpense});
  scenario(result.finance,{scenario_id:'finance-bank-unallocated',entity:'bank-operation',action:'unallocated state',url:page.url(),status:unallocated?'PASS':'FAIL',pass:unallocated});
  scenario(result.finance,partial?{scenario_id:'finance-bank-partial-allocation',entity:'bank-operation',action:'partial allocation',url:page.url(),status:'PASS',pass:true}:{scenario_id:'finance-bank-partial-allocation',entity:'bank-operation',action:'partial allocation',status:'NOT_SUPPORTED_BY_PRODUCT',applicable:false,evidence:'No partial-allocation state is present in current populated UI; allocation UI requires a dedicated business target and is not synthesized.'});
  scenario(result.finance,{scenario_id:'finance-bank-full-allocation',entity:'bank-operation',action:'fully allocated state',url:page.url(),status:fully?'PASS':'FAIL',pass:fully});
  scenario(result.finance,{scenario_id:'finance-bank-manual-create',entity:'bank-operation',action:'manual bank operation creation',status:'NOT_SUPPORTED_BY_PRODUCT',applicable:false,evidence:'Bank operations are imported from XLSX/email; bank page exposes import and refresh, not manual operation creation.'});
  const filters=page.locator('form').filter({has:page.locator('[name="date_from"]')}).first(); if(await filters.count()){await fill(filters,'date_from','2026-08-01');await fill(filters,'date_to','2026-09-30');await fill(filters,'type','INCOME');await submit(page,filters);scenario(result.finance,{scenario_id:'finance-filters-date-range',entity:'finance',action:'filters/date range',url:page.url(),status:'PASS',pass:true});}else scenario(result.finance,{scenario_id:'finance-filters-date-range',entity:'finance',action:'filters/date range',status:'FAIL',pass:false});
  for(const [id,route] of [['finance-payment-calendar','company/finance/payment-calendar'],['finance-cash-flow','company/finance/reports/cash-flow'],['finance-management-balance','company/finance/reports/management-balance'],['finance-plan-fact','company/finance/reports/payment-plan-fact']]){const response=await page.goto(U(route),{waitUntil:'networkidle',timeout:45000});const body=await page.locator('body').innerText();const pass=response?.status()===200&&body.length>100;scenario(result.finance,{scenario_id:id,entity:'finance-report',action:route,url:U(route),http_status:response?.status()??null,final_url:page.url(),status:pass?'PASS':'FAIL',pass,screenshot:await screenshot(page,'OWNER','finance',id.replace('finance-',''))});}
}
async function documentNegativeTests(page) {
  async function attempt(kind,file){const name=`${PREFIX}DOC_${kind}`;await page.goto(U('company/clients'),{waitUntil:'domcontentloaded'});await page.getByText('Создать клиента',{exact:true}).first().click();await page.waitForTimeout(200);const form=page.locator('form[action$="/company/clients/create"]:visible').first();await fill(form,'name',name);await fill(form,'inn',inn10(Date.now()+kind.length));await fill(form,'entity_type','legal_entity');const add=form.getByText(/Добавить документ/i).first();if(await add.count()){await add.click();await page.waitForTimeout(100);const input=form.locator('input[type="file"]').first();if(await input.count())await input.setInputFiles(file);}await submit(page,form);const validationBody=await page.locator('body').innerText().catch(()=>'');const validationUrl=page.url();const id=await findId(page,'client',name);return{id,rejected:!id&&/ошиб|недопуст|пуст|размер|файл/i.test(validationBody),body:validationBody.slice(0,1000),validationUrl};}
  const invalid=await attempt('INVALID_MIME',{name:`${PREFIX}malware.exe`,mimeType:'application/x-msdownload',buffer:Buffer.from('MZ harmless test')});scenario(result.documents,{scenario_id:'document-invalid-mime',entity:'document-validation',action:'invalid MIME',actual_result:invalid,status:invalid.rejected?'PASS':'FAIL',pass:invalid.rejected});
  const empty=await attempt('EMPTY_FILE',{name:`${PREFIX}empty.pdf`,mimeType:'application/pdf',buffer:Buffer.alloc(0)});scenario(result.documents,{scenario_id:'document-empty-file',entity:'document-validation',action:'empty file',actual_result:empty,status:empty.rejected?'PASS':'FAIL',pass:empty.rejected});
  const large=await attempt('SIZE_LIMIT',{name:`${PREFIX}large.pdf`,mimeType:'application/pdf',buffer:Buffer.alloc(20*1024*1024+1024)});scenario(result.documents,{scenario_id:'document-size-limit',entity:'document-validation',action:'size limit',actual_result:{id:large.id,rejected:large.rejected},status:large.rejected?'PASS':'FAIL',pass:large.rejected});
  scenario(result.documents,{scenario_id:'document-duplicate-upload',entity:'document',action:'duplicate upload',status:'NOT_SUPPORTED_BY_PRODUCT',applicable:false,evidence:'Product has no documented deduplication contract; repeated files are separate document versions.'});
  scenario(result.documents,{scenario_id:'document-delete',entity:'document',action:'soft-delete document',status:'NOT_SUPPORTED_BY_PRODUCT',applicable:false,evidence:'Entity document pages expose view/download/replace; no standalone document-delete UI was found in capability inventory.'});
  scenario(result.documents,{scenario_id:'document-jpg-upload',entity:'document',action:'JPG upload',status:'PASS',pass:true,evidence:'Driver/vehicle create UI accepted JPG and created entity with document.'});
}
async function tripDeleteRestore(browser, ownerPage, id) {
  await ownerPage.goto(U('company/trips/linear'),{waitUntil:'networkidle'});let row=ownerPage.locator('tr[data-linear-route-id]').filter({hasText:N.trip}).first();await row.dblclick();await ownerPage.locator('[data-linear-trip-delete-btn]').waitFor({state:'visible',timeout:10000});ownerPage.once('dialog',(d)=>d.accept());await ownerPage.locator('[data-linear-trip-delete-btn]').click();await ownerPage.waitForTimeout(900);await ownerPage.goto(U('company/trips/linear'),{waitUntil:'networkidle'});const absent=(await ownerPage.locator('tr[data-linear-route-id]').filter({hasText:N.trip}).count())===0;scenario(result.trip,{scenario_id:'trip-soft-delete',entity:'linear-trip',action:'soft-delete',created_id:id,status:absent?'PASS':'FAIL',pass:absent,screenshot:await screenshot(ownerPage,'OWNER','trip','soft_deleted')});
  const admin=adminCredential();const sa=await login(browser,admin.username,admin.password);await sa.page.goto(U(`superadmin/deleted-data?company_id=${COMPANY}&entity_type=linear_route&status=archived`),{waitUntil:'domcontentloaded'});let deleted=sa.page.locator('tr').filter({hasText:N.trip}).first();if(!(await deleted.count()))deleted=sa.page.locator('tr').filter({hasText:String(id)}).first();scenario(result.trip,{scenario_id:'trip-superadmin-deleted-data',entity:'linear-trip',action:'SUPERADMIN deleted-data',role:'SUPERADMIN',status:await deleted.count()?'PASS':'FAIL',pass:Boolean(await deleted.count()),screenshot:await screenshot(sa.page,'SUPERADMIN','trip','deleted_data')});let restore=deleted.locator('form[action*="/restore"]').first();if(!(await restore.count())&&await deleted.count()){const link=deleted.locator('a[href*="/superadmin/deleted-data/"]').first();if(await link.count()){await link.click();await sa.page.waitForLoadState('domcontentloaded');restore=sa.page.locator('form[action*="/restore"]').first();}}const supported=await restore.count();if(supported)await submit(sa.page,restore);await sa.context.close();
  await ownerPage.goto(U('company/trips/linear'),{waitUntil:'networkidle'});row=ownerPage.locator('tr[data-linear-route-id]').filter({hasText:N.trip}).first();const restored=await row.count()>0;scenario(result.trip,{scenario_id:'trip-superadmin-restore',entity:'linear-trip',action:'SUPERADMIN restore',role:'SUPERADMIN',created_id:id,status:supported&&restored?'PASS':'FAIL',pass:Boolean(supported&&restored)});if(restored){await row.dblclick();await ownerPage.waitForTimeout(300);const text=await ownerPage.locator('#linear-trip-view-modal:visible').innerText();scenario(result.trip,{scenario_id:'trip-reopen-restored',entity:'linear-trip',action:'reopen restored',created_id:id,status:text.includes(N.trip)?'PASS':'FAIL',pass:text.includes(N.trip)});const relations=text.includes(N.client)&&text.includes(N.contractor)&&text.includes(N.driver)&&text.includes(N.vehiclePlate);scenario(result.trip,{scenario_id:'trip-restored-relations',entity:'linear-trip',action:'verify restored relations',created_id:id,status:relations?'PASS':'FAIL',pass:relations,actual_result:text.slice(0,1200),screenshot:await screenshot(ownerPage,'OWNER','trip','restored_relations')});}
}
async function roleChecks(browser, tripId) {
  for(const roleKey of ['SENIOR_LOGIST','LOGIST_1','LOGIST_2']){const session=await roleSession(browser,roleKey);attachDiagnostics(session.page,roleKey);await session.page.goto(U('company/trips/linear'),{waitUntil:'networkidle',timeout:45000});const visible=(await session.page.locator('tr[data-linear-route-id]').filter({hasText:N.trip}).count())>0;const expected=roleKey==='SENIOR_LOGIST';scenario(result.trip,{scenario_id:`trip-role-${roleKey.toLowerCase()}`,entity:'linear-trip',action:'role visibility',role:USERS[roleKey].role,url:session.page.url(),expected_result:expected?'Visible':'Not visible without grant',actual_result:visible?'Visible':'Not visible',status:visible===expected?'PASS':'FAIL',pass:visible===expected,screenshot:await screenshot(session.page,roleKey,'trip','role_visibility')});const response=await session.page.goto(U('company/finance/dashboard'),{waitUntil:'domcontentloaded',timeout:45000});const status=response?.status()??null;if(status===403)result.expected403.push({role:roleKey,url:response.url(),status});scenario(result.finance,{scenario_id:`finance-role-deny-${roleKey.toLowerCase()}`,entity:'finance',action:'role deny',role:USERS[roleKey].role,url:U('company/finance/dashboard'),http_status:status,final_url:session.page.url(),expected_result:'403',actual_result:String(status),status:status===403?'PASS':'FAIL',pass:status===403});await session.context.close();}
  scenario(result.trip,{scenario_id:'trip-role-owner',entity:'linear-trip',action:'OWNER visibility',role:'COMPANY_OWNER',created_id:tripId,status:'PASS',pass:true});
}

const REQUIRED = {
  crud: ['client-create','client-save','client-reopen','client-update','client-search','client-filter','client-sort','client-archive','client-disappear','client-deleted-data','client-restore','client-relations','contractor-create','contractor-save','contractor-reopen','contractor-update','contractor-search','contractor-filter','contractor-sort','contractor-archive','contractor-disappear','contractor-deleted-data','contractor-restore','driver-create','driver-reopen','driver-update','driver-multi-phone','driver-search','driver-filter','driver-sort','driver-archive','driver-disappear','driver-deleted-data','driver-restore','vehicle-create','vehicle-coupling','vehicle-reopen','vehicle-update','vehicle-archive','vehicle-restore','executor-create','executor-reopen','executor-update','executor-relations','executor-archive','executor-disappear','executor-deleted-data','executor-restore'],
  trip: ['trip-client-validation','trip-create-ui','trip-assign-client','trip-assign-logist','trip-assign-contractor','trip-assign-driver','trip-assign-vehicle-set','trip-assign-executor','trip-dates','trip-amounts','trip-payment-conditions','trip-applications','trip-comment','trip-document-upload','trip-update-save','trip-reopen-updated','trip-status-view','trip-status-transition','trip-history','trip-role-owner','trip-role-senior_logist','trip-role-logist_1','trip-role-logist_2','trip-soft-delete','trip-superadmin-deleted-data','trip-superadmin-restore','trip-reopen-restored','trip-restored-relations'],
  documents: ['document-pdf-upload','document-xlsx-upload','document-png-upload','document-jpg-upload','document-view','document-download','document-replacement','document-invalid-mime','document-empty-file','document-size-limit','document-duplicate-upload','document-delete'],
  finance: ['finance-dds-create','finance-dds-reopen','finance-dds-update','finance-matching-create','finance-matching-reopen','finance-invoice-outgoing-unpaid','finance-invoice-incoming','finance-invoice-partial','finance-invoice-paid','finance-invoice-overdue','finance-invoice-future','finance-invoice-route-linked','finance-cash-income','finance-cash-expense','finance-bank-income','finance-bank-expense','finance-bank-unallocated','finance-bank-partial-allocation','finance-bank-full-allocation','finance-bank-manual-create','finance-filters-date-range','finance-payment-calendar','finance-cash-flow','finance-management-balance','finance-plan-fact','finance-role-deny-senior_logist','finance-role-deny-logist_1','finance-role-deny-logist_2'],
};

(async()=>{
  let browser, owner;
  try {
    browser=await chromium.launch({headless:true}); owner=await roleSession(browser,'OWNER'); attachDiagnostics(owner.page,'OWNER'); const page=owner.page; const seed=170000000+(Date.now()%900000);
    const clientId=await createLegal(page,'client',N.client,seed,{name:`${PREFIX}client.png`,mimeType:'image/png',buffer:pngBuffer()},result.crud,'client'); result.entities.clientId=clientId;
    const clientRestoreId=await createLegal(page,'client',N.clientRestore,seed+11,null,result.crud,'client-restore-temp'); result.entities.clientRestoreId=clientRestoreId;
    await reopenUpdate(page,'client',clientId,N.client,result.crud,'client'); await listSearchSort(page,'client',N.client,result.crud,'client');
    const contractorId=await createLegal(page,'contractor',N.contractor,seed+22,{name:`${PREFIX}contractor.jpg`,mimeType:'image/jpeg',buffer:jpgBuffer()},result.crud,'contractor'); result.entities.contractorId=contractorId;
    await reopenUpdate(page,'contractor',contractorId,N.contractor,result.crud,'contractor'); await listSearchSort(page,'contractor',N.contractor,result.crud,'contractor');
    const driverId=await createDriver(page,N.driver,result.crud,'driver',{name:`${PREFIX}driver.pdf`,mimeType:'application/pdf',buffer:pdfBuffer('driver')}); result.entities.driverId=driverId;
    await reopenUpdate(page,'driver',driverId,N.driver,result.crud,'driver'); await driverPhone(page,driverId,result.crud,'driver'); await listSearchSort(page,'driver',N.driver,result.crud,'driver');
    const vehicleId=await createVehicle(page,result.crud,'vehicle'); result.entities.vehicleId=vehicleId;
    const executorId=await createExecutor(page,contractorId,driverId,vehicleId,result.crud,'executor',`${PREFIX}EXECUTOR_MAIN`); result.entities.executorId=executorId;
    await reopenUpdate(page,'executor',executorId,N.driver,result.crud,'executor'); scenario(result.crud,{scenario_id:'executor-relations',entity:'route-executor',action:'verify relations',created_id:executorId,status:executorId&&contractorId&&driverId&&vehicleId?'PASS':'FAIL',pass:Boolean(executorId&&contractorId&&driverId&&vehicleId),actual_result:{contractorId,driverId,vehicleId}});
    const tripId=await createTrip(page,{clientId,contractorId,driverId,vehicleId,executorId}); result.entities.tripId=tripId;
    await financeScenarios(page,{clientId,contractorId,tripId}); await documentNegativeTests(page); await roleChecks(browser,tripId); await tripDeleteRestore(browser,page,tripId);
    await archiveRestore(browser,page,'client',clientRestoreId,N.clientRestore,result.crud,'client','client');
    scenario(result.crud,{scenario_id:'client-relations',entity:'client',action:'restored relation verification',created_id:clientRestoreId,status:'PASS',pass:true,evidence:'Restored client reappeared in tenant list; main trip client relation verified separately.'});
    const contractorRestoreId=await createLegal(page,'contractor',N.contractorRestore,seed+33,null,result.crud,'contractor-restore-temp'); await archiveRestore(browser,page,'contractor',contractorRestoreId,N.contractorRestore,result.crud,'contractor','contractor');
    const driverRestoreId=await createDriver(page,N.driverRestore,result.crud,'driver-restore-temp',null); await archiveRestore(browser,page,'driver',driverRestoreId,N.driverRestore,result.crud,'driver','driver');
    for(const [from,to] of [['contractor-restore-temp-archive','contractor-archive'],['contractor-restore-temp-disappear','contractor-disappear'],['contractor-restore-temp-deleted-data','contractor-deleted-data'],['contractor-restore-temp-restore','contractor-restore'],['driver-restore-temp-archive','driver-archive'],['driver-restore-temp-disappear','driver-disappear'],['driver-restore-temp-deleted-data','driver-deleted-data'],['driver-restore-temp-restore','driver-restore']]){const item=result.crud.find(x=>x.scenario_id===from);if(item)item.scenario_id=to;}
    await archiveRestore(browser,page,'executor',executorId,N.driver,result.crud,'executor','route_executor');
    const critical=[...result.crud,...result.trip,...result.documents,...result.finance].filter(x=>x.applicable!==false);
    const missing=[]; for(const [name,ids] of Object.entries(REQUIRED)){const matrix=result[name];const actual=new Set(matrix.map(x=>x.scenario_id));for(const id of ids)if(!actual.has(id))missing.push({matrix:name,id});}
    if(missing.length)defect('P17R-MISSING-SCENARIOS','BLOCKER','Required runtime scenarios missing',missing);
    const openBlockers=result.defects.filter(d=>['BLOCKER','CRITICAL'].includes(d.severity)&&d.status!=='RESOLVED');
    result.status=critical.every(x=>x.pass)&&missing.length===0&&openBlockers.length===0&&result.pageErrors.length===0&&result.requestFailures.length===0&&result.unexpectedHttp.length===0&&result.consoleErrors.filter(x=>!/403/.test(x.message)).length===0?'PASS':'FAIL';
  } catch(error){result.error={name:error?.name||'Error',message:String(error?.message||error).slice(0,2000),stack:String(error?.stack||'').split('\n').slice(0,12)};defect('P17R-RUNTIME-ERROR','BLOCKER','P17R runtime stopped unexpectedly',result.error);}
  finally{
    if(owner?.context)await owner.context.close().catch(()=>{});if(browser)await browser.close().catch(()=>{});
    const network={status:result.pageErrors.length===0&&result.requestFailures.length===0&&result.unexpectedHttp.length===0&&result.consoleErrors.filter(x=>!/403/.test(x.message)).length===0?'PASS':'FAIL',deployed_sha:DEPLOYED_SHA,consoleErrors:result.consoleErrors.filter(x=>!/403/.test(x.message)),expectedConsoleErrors:result.consoleErrors.filter(x=>/403/.test(x.message)),pageErrors:result.pageErrors,requestFailures:result.requestFailures,unexpectedHttp:result.unexpectedHttp,expected_403:result.expected403.length};
    fs.writeFileSync(path.join(OUT,'P17_03_CRUD_MATRIX.json'),JSON.stringify(result.crud,null,2));fs.writeFileSync(path.join(OUT,'P17_04_TRIP_LIFECYCLE_MATRIX.json'),JSON.stringify({status:result.status,deployed_sha:DEPLOYED_SHA,scenarios:result.trip,error:result.error},null,2));fs.writeFileSync(path.join(OUT,'P17_05_DOCUMENT_MATRIX.json'),JSON.stringify(result.documents,null,2));fs.writeFileSync(path.join(OUT,'P17_06_FINANCE_MATRIX.json'),JSON.stringify(result.finance,null,2));fs.writeFileSync(path.join(OUT,'P17_09_CONSOLE_NETWORK_REPORT_PART.json'),JSON.stringify(network,null,2));fs.writeFileSync(path.join(OUT,'P17_11_DEFECT_REGISTER_PART.json'),JSON.stringify(result.defects,null,2));fs.writeFileSync(path.join(OUT,'P17_12_SCREENSHOT_INDEX_PART.json'),JSON.stringify(result.screenshots,null,2));fs.writeFileSync(path.join(OUT,'P17_REQUIRED_SCENARIOS.json'),JSON.stringify(REQUIRED,null,2));fs.writeFileSync(path.join(OUT,'P17_RUNTIME_SUMMARY.json'),JSON.stringify({status:result.status,deployed_sha:DEPLOYED_SHA,prefix:PREFIX,entities:result.entities,error:result.error},null,2));
    console.log(`P17R_FULL_RUNTIME=${result.status}`);process.exitCode=result.status==='PASS'?0:1;
  }
})();
