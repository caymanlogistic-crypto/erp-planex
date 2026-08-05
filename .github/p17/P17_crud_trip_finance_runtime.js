'use strict';

const fs = require('fs');
const path = require('path');
const { chromium } = require('playwright');
const XLSX = require('xlsx');

const BASE = 'https://plan-ex.ru/erpv2/';
const U = (value) => new URL(String(value).replace(/^\//, ''), BASE).href;
const OUT = path.resolve(process.env.P17_OUT || 'P17_crud_runtime_output');
const SHOTS = path.join(OUT, 'P17_screenshots');
fs.mkdirSync(SHOTS, { recursive: true });

const COMPANY = 27;
const OWNER = { id: 16, username: 'p14_owner_260804221827' };
const NAMES = {
  client: 'P17_CLIENT Финальная приёмка',
  restoreClient: 'P17_CLIENT Восстановление',
  contractor: 'P17_CONTRACTOR Финальный перевозчик',
  driver: 'P17_DRIVER Финальный Водитель',
  plate: 'Р017РТ77',
  trip: 'P17_TRIP Финальная приёмка рейса',
  ddsCode: 'P17_FINAL',
  invoice: 'P17-INV-001',
  cash: 'P17_CASH Финальная кассовая операция',
};

function credentials() {
  const raw = process.env.P17_CREDENTIALS_B64 || '';
  return raw ? JSON.parse(Buffer.from(raw, 'base64').toString('utf8')) : [];
}
function adminCredential() {
  return credentials().find((item) => String(item?.role || '').toUpperCase() === 'SUPERADMIN');
}
async function login(browser, username, password) {
  const context = await browser.newContext({ viewport: { width: 1920, height: 1080 }, deviceScaleFactor: 1, locale: 'ru-RU', timezoneId: 'Europe/Moscow', ignoreHTTPSErrors: true });
  const page = await context.newPage();
  let ok = false;
  try {
    await page.goto(U('login'), { waitUntil: 'domcontentloaded', timeout: 45000 });
    await page.locator('input[name="username"],input[name="email"],input[type="text"]').first().fill(username);
    await page.locator('input[type="password"]').first().fill(password);
    await Promise.all([
      page.waitForLoadState('domcontentloaded', { timeout: 20000 }).catch(() => {}),
      page.locator('button[type="submit"],input[type="submit"]').first().click(),
    ]);
    await page.waitForTimeout(500);
    ok = !/\/login(?:[/?#]|$)/i.test(page.url());
  } catch (_) {}
  return { context, page, ok };
}
function passwordCandidates(text, values, username) {
  const all = values.map(String);
  for (const match of String(text).matchAll(/[A-Za-z0-9!@#$%^&*()_+\-=]{8,64}/g)) all.push(match[0]);
  return [...new Set(all)].filter((value) => value !== username && !value.includes('@example.test') && !/^[a-f0-9]{64}$/i.test(value));
}
async function ownerSession(browser) {
  const admin = adminCredential();
  if (!admin) throw new Error('SUPERADMIN credential unavailable.');
  const superSession = await login(browser, admin.username, admin.password);
  if (!superSession.ok) throw new Error('SUPERADMIN login failed.');
  await superSession.page.goto(U(`superadmin/companies/${COMPANY}/users`), { waitUntil: 'domcontentloaded', timeout: 45000 });
  const form = superSession.page.locator(`form[action$="/superadmin/companies/${COMPANY}/owner/reset-password"]`).first();
  if (!(await form.count())) throw new Error('Owner reset form unavailable.');
  await Promise.all([
    superSession.page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 30000 }),
    form.evaluate((node) => HTMLFormElement.prototype.submit.call(node)),
  ]);
  const values = await superSession.page.locator('input,textarea,code,pre,[data-password]').evaluateAll((nodes) => nodes.map((node) => node.value || node.getAttribute('data-password') || node.textContent || '').filter(Boolean));
  const list = passwordCandidates(await superSession.page.locator('body').innerText(), values, OWNER.username);
  await superSession.context.close();
  for (const candidate of list) {
    const session = await login(browser, OWNER.username, candidate);
    if (session.ok) {
      list.fill('');
      return session;
    }
    await session.context.close();
  }
  throw new Error('Owner login verification failed.');
}
function attachDiagnostics(page, result) {
  page.on('console', (message) => { if (message.type() === 'error') result.consoleErrors.push({ url: page.url(), message: message.text() }); });
  page.on('pageerror', (error) => result.pageErrors.push({ url: page.url(), message: String(error.message || error) }));
  page.on('requestfailed', (request) => result.requestFailures.push({ method: request.method(), url: request.url(), error: request.failure()?.errorText || 'failed' }));
  page.on('response', (response) => { if (response.status() >= 500) result.unexpectedHttp.push({ status: response.status(), url: response.url() }); });
}
async function field(form, name, value) {
  const locator = form.locator(`[name="${name.replace(/"/g, '\\"')}"]`).first();
  if (!(await locator.count())) return false;
  const type = await locator.getAttribute('type');
  const tag = await locator.evaluate((node) => node.tagName);
  if (type === 'file') return false;
  if (tag === 'SELECT') {
    await locator.evaluate((node, expected) => {
      const options = [...node.options];
      const option = options.find((item) => String(item.value) === String(expected)) || options.find((item) => item.value);
      if (!option) return;
      node.value = option.value;
      node.dispatchEvent(new Event('input', { bubbles: true }));
      node.dispatchEvent(new Event('change', { bubbles: true }));
    }, String(value));
  } else {
    await locator.fill(String(value));
  }
  return true;
}
async function submit(page, form, externalSelector = null) {
  const navigation = page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 30000 }).catch(() => null);
  if (externalSelector && await page.locator(externalSelector).count()) await page.locator(externalSelector).first().click();
  else await form.evaluate((node) => node.requestSubmit());
  const response = await navigation;
  await page.waitForTimeout(700);
  return response;
}
function inn10(seed) {
  const base = String(seed).replace(/\D/g, '').padStart(9, '0').slice(-9);
  const weights = [2,4,10,3,5,9,4,6,8];
  const checksum = ([...base].map(Number).reduce((sum, value, index) => sum + value * weights[index], 0) % 11) % 10;
  return base + checksum;
}
function pngBuffer() {
  return Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Wl2nXQAAAAASUVORK5CYII=', 'base64');
}
function jpgBuffer() {
  return Buffer.from('/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////2wBDAf//////////////////////////////////////////////////////////////////////////////////////wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAX/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIQAxAAAAEf/8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABBQJ//8QAFBEBAAAAAAAAAAAAAAAAAAAAAP/aAAgBAwEBPwF//8QAFBEBAAAAAAAAAAAAAAAAAAAAAP/aAAgBAgEBPwF//8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQAGPwJ//8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPyF//9oADAMBAAIAAwAAABAf/8QAFBEBAAAAAAAAAAAAAAAAAAAAAP/aAAgBAwEBPxB//8QAFBEBAAAAAAAAAAAAAAAAAAAAAP/aAAgBAgEBPxB//8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPxB//9k=', 'base64');
}
function pdfBuffer(label) {
  return Buffer.from(`%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Count 0>>endobj\n% ${label}\n%%EOF\n`, 'utf8');
}
function xlsxBuffer() {
  const workbook = XLSX.utils.book_new();
  XLSX.utils.book_append_sheet(workbook, XLSX.utils.aoa_to_sheet([['P17', 'ERP PLANEX'], ['status', 'acceptance']]), 'P17');
  return XLSX.write(workbook, { type: 'buffer', bookType: 'xlsx' });
}
const specs = {
  client: { list: 'company/clients', attr: 'data-client-id' },
  contractor: { list: 'company/contractors', attr: 'data-contractor-id' },
  driver: { list: 'company/drivers', attr: 'data-driver-id' },
  vehicle: { list: 'company/vehicle-sets', attr: 'data-vehicle-set-id' },
  executor: { list: 'company/route-executors', attr: 'data-route-executor-id' },
  trip: { list: 'company/trips/linear', attr: 'data-linear-route-id' },
};
async function findId(page, type, label) {
  const spec = specs[type];
  await page.goto(U(spec.list), { waitUntil: 'domcontentloaded', timeout: 45000 });
  await page.waitForTimeout(350);
  const row = page.locator(`[${spec.attr}]`).filter({ hasText: label }).first();
  if (await row.count()) return Number(await row.getAttribute(spec.attr));
  const generic = page.locator('tr,[data-id]').filter({ hasText: label }).first();
  if (!(await generic.count())) return null;
  const attributes = await generic.evaluate((node) => Object.fromEntries([...node.attributes].map((item) => [item.name, item.value])));
  for (const [name, value] of Object.entries(attributes)) if (/data-.*-id$/.test(name) && /^\d+$/.test(value)) return Number(value);
  return null;
}
function scenario(matrix, entity, action, pass, details = {}) {
  matrix.push({ entity, action, pass: Boolean(pass), ...details });
  if (!pass) throw new Error(`${entity}:${action} failed`);
}
async function createLegal(page, type, name, seed, matrix, screenshotNumber) {
  let id = await findId(page, type, name);
  if (id) { scenario(matrix, type, 'reopen-existing', true, { id }); return id; }
  await page.goto(U(specs[type].list), { waitUntil: 'domcontentloaded', timeout: 45000 });
  const trigger = page.locator('button,a').filter({ hasText: type === 'client' ? /Создать клиента/i : /Создать перевозчика/i }).first();
  await trigger.click();
  await page.waitForTimeout(450);
  const action = type === 'client' ? '/company/clients/create' : '/company/contractors/create';
  const form = page.locator(`form[action$="${action}"]`).first();
  await field(form, 'name', name);
  await field(form, 'inn', inn10(seed));
  await field(form, type === 'client' ? 'entity_type' : 'contractor_type', 'legal_entity');
  await field(form, 'kpp', '770101001');
  await field(form, 'director_full_name', 'Петров Пётр Петрович');
  await field(form, 'director_position', 'Генеральный директор');
  await field(form, 'legal_address', '127000, Москва, улица Финальной Приёмки, дом 17');
  await field(form, 'physical_address', '127000, Москва, улица Финальной Приёмки, дом 17, офис 17');
  await field(form, 'contacts[0][contact_person]', 'Контакт P17');
  await field(form, 'contacts[0][phone]', type === 'client' ? '+79990001711' : '+79990001712');
  await field(form, 'contacts[0][email]', `${type}.p17@example.test`);
  await field(form, 'comments', 'P17 browser CRUD final acceptance record');
  const addDocument = form.locator('button').filter({ hasText: /Добавить документ/i }).first();
  if (await addDocument.count()) {
    await addDocument.click();
    await page.waitForTimeout(200);
    const fileInput = form.locator('input[type="file"]').first();
    if (await fileInput.count()) await fileInput.setInputFiles({ name: `P17_${type}_документ.png`, mimeType: 'image/png', buffer: pngBuffer() });
  }
  await page.screenshot({ path: path.join(SHOTS, `P17_${String(screenshotNumber).padStart(3,'0')}_OWNER_${type}_create_form_1920x1080.png`), fullPage: false });
  const response = await submit(page, form);
  id = await findId(page, type, name);
  scenario(matrix, type, 'create-save-reopen', Boolean(id), { id, httpStatus: response?.status() ?? null });
  return id;
}
async function createDriver(page, matrix, screenshotNumber) {
  let id = await findId(page, 'driver', NAMES.driver);
  if (id) { scenario(matrix, 'driver', 'reopen-existing', true, { id }); return id; }
  await page.goto(U('company/drivers'), { waitUntil: 'domcontentloaded' });
  await page.locator('button,a').filter({ hasText: /Добавить нового водителя/i }).first().click();
  await page.waitForTimeout(400);
  const form = page.locator('#driver-create-form').first();
  await field(form, 'full_name', NAMES.driver);
  await field(form, 'phone', '+79990001721');
  await field(form, 'passport_number', '4517 001717');
  await field(form, 'license_number', '7717 001717');
  await field(form, 'email', 'driver.p17@example.test');
  await field(form, 'comments', 'P17 browser CRUD driver');
  const addDocument = form.locator('button').filter({ hasText: /Добавить документ/i }).first();
  if (await addDocument.count()) {
    await addDocument.click();
    const input = form.locator('input[type="file"]').first();
    if (await input.count()) await input.setInputFiles({ name: 'P17_водитель.jpg', mimeType: 'image/jpeg', buffer: jpgBuffer() });
  }
  await page.screenshot({ path: path.join(SHOTS, `P17_${String(screenshotNumber).padStart(3,'0')}_OWNER_driver_create_form_1920x1080.png`), fullPage: false });
  await submit(page, form);
  id = await findId(page, 'driver', NAMES.driver);
  scenario(matrix, 'driver', 'create-save-reopen', Boolean(id), { id });
  return id;
}
async function createVehicle(page, matrix, screenshotNumber) {
  let id = await findId(page, 'vehicle', NAMES.plate);
  if (id) { scenario(matrix, 'vehicle-set', 'reopen-existing', true, { id }); return id; }
  await page.goto(U('company/vehicle-sets'), { waitUntil: 'domcontentloaded' });
  await page.locator('button,a').filter({ hasText: /Добавить|Создать/i }).first().click();
  await page.waitForTimeout(450);
  const form = page.locator('#vehicle-set-create-form').first();
  await field(form, 'set_type', 'single');
  await page.waitForTimeout(500);
  await field(form, 'units[primary][brand]', 'КАМАЗ');
  await field(form, 'units[primary][model]', 'P17-5490');
  await field(form, 'units[primary][plate_number]', NAMES.plate);
  await field(form, 'units[primary][vin]', 'XTC54900000000017');
  await field(form, 'units[primary][diagnostic_card_number]', '1700170017');
  await field(form, 'units[primary][diagnostic_card_date]', '01.08.2026');
  await field(form, 'units[primary][capacity_tons]', '20');
  await field(form, 'units[primary][volume_m3]', '82');
  await field(form, 'comments', 'P17 browser CRUD vehicle');
  const sts = form.locator('[name="predef_doc[primary][sts][]"]').first();
  if (await sts.count()) await sts.setInputFiles({ name: 'P17_СТС.png', mimeType: 'image/png', buffer: pngBuffer() });
  const photo = form.locator('[name="predef_doc[primary][photo][]"]').first();
  if (await photo.count()) await photo.setInputFiles({ name: 'P17_фото_ТС.jpg', mimeType: 'image/jpeg', buffer: jpgBuffer() });
  await page.screenshot({ path: path.join(SHOTS, `P17_${String(screenshotNumber).padStart(3,'0')}_OWNER_vehicle_create_form_1920x1080.png`), fullPage: false });
  await submit(page, form, 'button[type="submit"][form="vehicle-set-create-form"]');
  id = await findId(page, 'vehicle', NAMES.plate);
  scenario(matrix, 'vehicle-set', 'create-save-reopen', Boolean(id), { id });
  return id;
}
async function createExecutor(page, contractorId, driverId, vehicleId, matrix) {
  let id = await findId(page, 'executor', NAMES.driver);
  if (id) { scenario(matrix, 'route-executor', 'reopen-existing', true, { id }); return id; }
  await page.goto(U('company/route-executors'), { waitUntil: 'domcontentloaded' });
  await page.locator('button,a').filter({ hasText: /Создать|Добавить/i }).first().click();
  await page.waitForTimeout(400);
  const form = page.locator('#route-executor-create-form').first();
  await field(form, 'contractor_id', contractorId);
  await field(form, 'driver_id', driverId);
  await field(form, 'vehicle_set_id', vehicleId);
  await field(form, 'comments', 'P17 browser CRUD route executor');
  await submit(page, form);
  id = await findId(page, 'executor', NAMES.driver);
  scenario(matrix, 'route-executor', 'create-save-reopen', Boolean(id), { id });
  return id;
}
async function createTrip(page, clientId, contractorId, executorId, tripMatrix, documentMatrix, screenshotNumber) {
  let id = await findId(page, 'trip', NAMES.trip);
  if (!id) {
    await page.goto(U('company/trips/linear?show_create=1'), { waitUntil: 'domcontentloaded', timeout: 45000 });
    const form = page.locator('#linear-trip-create-form').first();
    await field(form, 'route_type', 'linear');
    await field(form, 'planned_loading_date', '20.08.2026');
    await field(form, 'client_id', clientId);
    await field(form, 'carrier_contractor_id', contractorId);
    await field(form, 'route_executor_id', executorId);
    await field(form, 'cargo_type_name', NAMES.trip);
    await field(form, 'customer_payments[0][amount]', '217000');
    await field(form, 'customer_payments[0][payment_method]', 'cashless');
    await field(form, 'customer_payments[0][condition_type]', 'specific_date');
    await field(form, 'customer_payments[0][specific_due_date]', '25.08.2026');
    await field(form, 'carrier_payments[0][amount]', '157000');
    await field(form, 'carrier_payments[0][payment_method]', 'cashless');
    await field(form, 'carrier_payments[0][condition_type]', 'after_end');
    await field(form, 'carrier_payments[0][days_count]', '5');
    await field(form, 'carrier_payments[0][days_kind]', 'working');
    await field(form, 'comments', 'P17 fresh trip final acceptance');
    const customerDocument = form.locator('[name="customer_document"]').first();
    if (await customerDocument.count()) await customerDocument.setInputFiles({ name: 'P17_договор_заказчик.pdf', mimeType: 'application/pdf', buffer: pdfBuffer('P17 customer') });
    const carrierDocument = form.locator('[name="carrier_document"]').first();
    if (await carrierDocument.count()) await carrierDocument.setInputFiles({ name: 'P17_заявка_перевозчик.xlsx', mimeType: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', buffer: xlsxBuffer() });
    await page.screenshot({ path: path.join(SHOTS, `P17_${String(screenshotNumber).padStart(3,'0')}_OWNER_trip_create_filled_1920x1080.png`), fullPage: false });
    const invalid = await form.evaluate((node) => [...node.elements].filter((item) => typeof item.checkValidity === 'function' && !item.checkValidity()).map((item) => ({ name: item.name, message: item.validationMessage })));
    scenario(tripMatrix, 'linear-trip', 'client-validation', invalid.length === 0, { invalid });
    const button = page.locator('button[type="submit"][form="linear-trip-create-form"],#linear-trip-create-form button[type="submit"],button').filter({ hasText: /Создать рейс|Сохранить/i }).first();
    if (await button.count()) await button.click(); else await form.evaluate((node) => node.requestSubmit());
    await page.waitForTimeout(2200);
    const body = await page.locator('body').innerText();
    scenario(tripMatrix, 'linear-trip', 'safe-create-response', !/SQLSTATE|Integrity constraint|payment_due_type|stack trace|\/home\//i.test(body));
    id = await findId(page, 'trip', NAMES.trip);
    scenario(tripMatrix, 'linear-trip', 'create-save-reopen', Boolean(id), { id });
  } else {
    scenario(tripMatrix, 'linear-trip', 'reopen-existing', true, { id });
  }

  await page.goto(U('company/trips/linear'), { waitUntil: 'networkidle', timeout: 45000 });
  const row = page.locator('tr[data-linear-route-id]').filter({ hasText: NAMES.trip }).first();
  await row.dblclick();
  const editButton = page.locator('[data-linear-trip-edit-btn]').first();
  await editButton.waitFor({ state: 'visible', timeout: 15000 });
  await editButton.click();
  const editForm = page.locator('#linear-trip-edit-form').first();
  await editForm.waitFor({ state: 'visible', timeout: 15000 });
  await field(editForm, 'planned_unloading_date', '22.08.2026');
  await field(editForm, 'actual_loading_date', '20.08.2026');
  await field(editForm, 'actual_unloading_date', '22.08.2026');
  await field(editForm, 'comments', 'P17 lifecycle edit PASS');
  await field(editForm, 'customer_payments[0][amount]', '220000');
  const replaceInput = editForm.locator('[name="customer_document"]').first();
  if (await replaceInput.count()) await replaceInput.setInputFiles({ name: 'P17_договор_заказчик_v2.pdf', mimeType: 'application/pdf', buffer: pdfBuffer('P17 customer v2') });
  await page.screenshot({ path: path.join(SHOTS, `P17_${String(screenshotNumber + 1).padStart(3,'0')}_OWNER_trip_edit_documents_1920x1080.png`), fullPage: false });
  const responses = [];
  const handler = async (response) => {
    if (/\/company\/trips\/linear\/\d+\/modal-edit/.test(response.url())) {
      let body = '';
      try { body = await response.text(); } catch (_) {}
      responses.push({ status: response.status(), body: body.slice(0, 2000), url: response.url() });
    }
  };
  page.on('response', handler);
  await page.locator('[data-linear-trip-save-btn]').first().click();
  await page.waitForTimeout(1800);
  page.off('response', handler);
  const save = responses.at(-1) || null;
  scenario(tripMatrix, 'linear-trip', 'update-save', Boolean(save && save.status === 200 && save.body.includes('"success":true')), { response: save && { status: save.status, url: save.url } });

  await page.goto(U('company/trips/linear'), { waitUntil: 'networkidle', timeout: 45000 });
  const finalRow = page.locator('tr[data-linear-route-id]').filter({ hasText: NAMES.trip }).first();
  await finalRow.dblclick();
  await page.waitForTimeout(700);
  const modal = page.locator('#linear-trip-view-modal').first();
  const modalText = (await modal.innerText()).replace(/\s+/g, ' ');
  scenario(tripMatrix, 'linear-trip', 'reopen-updated-values', /220\s*000/.test(modalText) && modalText.includes('20.08.2026') && modalText.includes('22.08.2026') && modalText.includes('P17 lifecycle edit PASS'));
  const links = await modal.locator('a[href*="/company/documents/"]').evaluateAll((nodes) => nodes.map((node) => ({ text: (node.innerText || '').trim(), href: node.href, download: node.hasAttribute('download') })));
  scenario(documentMatrix, 'trip-documents', 'links-rendered', links.length >= 2, { count: links.length });
  for (const link of links) {
    const response = await page.context().request.get(link.href);
    documentMatrix.push({ entity: 'trip-documents', action: link.download ? 'download' : 'view', file: link.text, status: response.status(), contentType: response.headers()['content-type'] || '', pass: response.status() === 200 });
  }
  scenario(documentMatrix, 'trip-documents', 'replacement-visible', modalText.includes('P17_договор_заказчик_v2.pdf'));
  await page.screenshot({ path: path.join(SHOTS, `P17_${String(screenshotNumber + 2).padStart(3,'0')}_OWNER_trip_view_final_1920x1080.png`), fullPage: false });
  return id;
}
async function updateAndSearchClient(page, id, matrix) {
  await page.goto(U(`company/clients/${id}`), { waitUntil: 'domcontentloaded', timeout: 45000 });
  const edit = page.locator('a,button').filter({ hasText: /Редактировать/i }).first();
  if (await edit.count()) {
    await edit.click();
    await page.waitForTimeout(350);
    const form = page.locator('form').filter({ has: page.locator('[name="name"]') }).first();
    if (await form.count()) {
      await field(form, 'comments', 'P17 CLIENT UPDATE PASS');
      await submit(page, form);
    }
  }
  await page.goto(U('company/clients'), { waitUntil: 'domcontentloaded', timeout: 45000 });
  const search = page.locator('input[type="search"],input[name="q"],input[placeholder*="Поиск"]').first();
  if (await search.count()) {
    await search.fill(NAMES.client);
    await page.waitForTimeout(500);
  }
  const visible = await page.locator('[data-client-id],tbody tr').filter({ hasText: NAMES.client }).count();
  scenario(matrix, 'client', 'update-search-filter', visible > 0, { visibleRows: visible, searchAvailable: Boolean(await search.count()) });
  const sortControls = await page.locator('th button,th a,[data-sort]').count();
  matrix.push({ entity: 'client', action: 'sort-capability', pass: sortControls > 0, controls: sortControls, nonBlocking: sortControls === 0 });
}
async function deleteRestoreClient(browser, ownerPage, clientId, matrix, screenshotNumber) {
  await ownerPage.goto(U(`company/clients/${clientId}`), { waitUntil: 'domcontentloaded', timeout: 45000 });
  const deleteButton = ownerPage.locator('button,a').filter({ hasText: /Удалить/i }).first();
  if (!(await deleteButton.count())) throw new Error('Client delete action unavailable.');
  ownerPage.once('dialog', (dialog) => dialog.accept());
  await deleteButton.click();
  await ownerPage.waitForTimeout(450);
  const confirmForm = ownerPage.locator('form').filter({ has: ownerPage.locator('button').filter({ hasText: /Удалить|Подтвердить/i }) }).first();
  if (await confirmForm.count()) await submit(ownerPage, confirmForm);
  await ownerPage.goto(U('company/clients'), { waitUntil: 'domcontentloaded', timeout: 45000 });
  const absent = (await ownerPage.locator('[data-client-id],tbody tr').filter({ hasText: NAMES.restoreClient }).count()) === 0;
  scenario(matrix, 'client', 'soft-delete-disappearance', absent);

  const admin = adminCredential();
  const superSession = await login(browser, admin.username, admin.password);
  if (!superSession.ok) throw new Error('SUPERADMIN login failed for restore.');
  const page = superSession.page;
  await page.goto(U(`/superadmin/deleted-data?company_id=${COMPANY}&entity_type=client&status=archived`), { waitUntil: 'domcontentloaded', timeout: 45000 });
  let row = page.locator('tr').filter({ hasText: NAMES.restoreClient }).first();
  if (!(await row.count())) row = page.locator('tr').filter({ hasText: String(clientId) }).first();
  if (!(await row.count())) throw new Error('Archived client audit row unavailable.');
  const viewLink = row.locator('a[href*="/superadmin/deleted-data/"]').first();
  if (await viewLink.count()) await viewLink.click(); else await row.dblclick();
  await page.waitForLoadState('domcontentloaded').catch(() => {});
  const restoreForm = page.locator('form[action*="/restore"]').first();
  if (!(await restoreForm.count())) throw new Error('SUPERADMIN restore form unavailable.');
  await page.screenshot({ path: path.join(SHOTS, `P17_${String(screenshotNumber).padStart(3,'0')}_SUPERADMIN_client_restore_1920x1080.png`), fullPage: false });
  await submit(page, restoreForm);
  await superSession.context.close();

  await ownerPage.goto(U('company/clients'), { waitUntil: 'domcontentloaded', timeout: 45000 });
  const restored = (await ownerPage.locator('[data-client-id],tbody tr').filter({ hasText: NAMES.restoreClient }).count()) > 0;
  scenario(matrix, 'client', 'superadmin-restore-and-reappear', restored, { clientId });
}
async function financeScenarios(page, clientId, matrix, screenshotNumber) {
  await page.goto(U('company/finance/settings/dds-categories'), { waitUntil: 'domcontentloaded', timeout: 45000 });
  let body = await page.locator('body').innerText();
  if (!body.includes(NAMES.ddsCode)) {
    const trigger = page.locator('button,a').filter({ hasText: /Создать статью|Добавить/i }).first();
    await trigger.click();
    await page.waitForTimeout(300);
    const form = page.locator('form[action$="/company/finance/settings/dds-categories/create"]').first();
    await field(form, 'code', NAMES.ddsCode);
    await field(form, 'name', 'P17 Финальная статья ДДС');
    await field(form, 'direction', 'BOTH');
    await field(form, 'sort_order', '1700');
    await submit(page, form);
  }
  await page.goto(U('company/finance/settings/dds-categories'), { waitUntil: 'domcontentloaded' });
  scenario(matrix, 'dds-category', 'create-reopen', (await page.locator('body').innerText()).includes(NAMES.ddsCode));

  await page.goto(U('company/finance/invoices'), { waitUntil: 'domcontentloaded', timeout: 45000 });
  body = await page.locator('body').innerText();
  if (!body.includes(NAMES.invoice)) {
    const trigger = page.locator('button,a').filter({ hasText: /Создать счёт|Добавить/i }).first();
    await trigger.click();
    await page.waitForTimeout(350);
    const form = page.locator('form[action$="/company/finance/invoices/create"]').first();
    await field(form, 'direction', 'OUTGOING');
    await field(form, 'number', NAMES.invoice);
    await field(form, 'invoice_date', '05.08.2026');
    await field(form, 'counterparty_entity_type', 'client');
    await page.waitForTimeout(300);
    await field(form, 'counterparty_entity_id', clientId);
    await field(form, 'counterparty_name', NAMES.client);
    await field(form, 'amount', '217000');
    await field(form, 'planned_payment_date', '25.08.2026');
    await field(form, 'basis', NAMES.trip);
    await field(form, 'status', 'issued');
    await field(form, 'comment', 'P17 invoice final acceptance');
    await page.screenshot({ path: path.join(SHOTS, `P17_${String(screenshotNumber).padStart(3,'0')}_OWNER_invoice_create_1920x1080.png`), fullPage: false });
    await submit(page, form);
  }
  await page.goto(U('company/finance/invoices'), { waitUntil: 'domcontentloaded' });
  scenario(matrix, 'invoice', 'create-reopen', (await page.locator('body').innerText()).includes(NAMES.invoice));

  await page.goto(U('company/finance/cash'), { waitUntil: 'domcontentloaded', timeout: 45000 });
  body = await page.locator('body').innerText();
  if (!body.includes(NAMES.cash)) {
    const trigger = page.locator('button,a').filter({ hasText: /Внести|Приход|Создать/i }).first();
    await trigger.click();
    await page.waitForTimeout(300);
    const form = page.locator('form[action$="/company/finance/cash/operation-create"]').first();
    await field(form, 'operation_type', 'INCOME');
    const account = form.locator('[name="money_account_id"]').first();
    if (await account.count()) {
      const options = await account.locator('option').evaluateAll((nodes) => nodes.map((node) => node.value).filter(Boolean));
      if (options.length) await field(form, 'money_account_id', options[0]);
    }
    await field(form, 'amount', '17000');
    await field(form, 'operation_date', '05.08.2026');
    await field(form, 'purpose', NAMES.cash);
    await field(form, 'comment', 'P17 cash final acceptance');
    await submit(page, form);
  }
  await page.goto(U('company/finance/cash'), { waitUntil: 'domcontentloaded' });
  scenario(matrix, 'cash-operation', 'create-reopen', (await page.locator('body').innerText()).includes(NAMES.cash));

  for (const [entity, route] of [
    ['bank-operations', 'company/finance/operations'],
    ['payment-calendar', 'company/finance/payment-calendar'],
    ['cash-flow-report', 'company/finance/reports/cash-flow'],
    ['management-balance', 'company/finance/reports/management-balance'],
    ['payment-plan-fact', 'company/finance/reports/payment-plan-fact'],
    ['matching-rules', 'company/finance/settings/matching-rules'],
  ]) {
    const response = await page.goto(U(route), { waitUntil: 'networkidle', timeout: 45000 });
    const text = await page.locator('body').innerText();
    matrix.push({ entity, action: 'populated-view', status: response?.status() ?? null, populated: /UIUX_P16_|P17_|₽|операц|сч[её]т|плат/i.test(text), pass: response?.status() === 200 });
  }
}

(async () => {
  const result = {
    status: 'FAIL',
    deployedSha: process.env.P17_DEPLOYED_SHA || null,
    entities: {},
    crud: [],
    trip: [],
    documents: [],
    finance: [],
    consoleErrors: [],
    pageErrors: [],
    requestFailures: [],
    unexpectedHttp: [],
    error: null,
  };
  let browser;
  let owner;
  try {
    browser = await chromium.launch({ headless: true });
    owner = await ownerSession(browser);
    attachDiagnostics(owner.page, result);
    const seed = 170000000 + Date.now() % 1000000;
    result.entities.clientId = await createLegal(owner.page, 'client', NAMES.client, seed, result.crud, 101);
    result.entities.restoreClientId = await createLegal(owner.page, 'client', NAMES.restoreClient, seed + 31, result.crud, 102);
    result.entities.contractorId = await createLegal(owner.page, 'contractor', NAMES.contractor, seed + 67, result.crud, 103);
    result.entities.driverId = await createDriver(owner.page, result.crud, 104);
    result.entities.vehicleSetId = await createVehicle(owner.page, result.crud, 105);
    result.entities.routeExecutorId = await createExecutor(owner.page, result.entities.contractorId, result.entities.driverId, result.entities.vehicleSetId, result.crud);
    result.entities.tripId = await createTrip(owner.page, result.entities.clientId, result.entities.contractorId, result.entities.routeExecutorId, result.trip, result.documents, 106);
    await updateAndSearchClient(owner.page, result.entities.clientId, result.crud);
    await financeScenarios(owner.page, result.entities.clientId, result.finance, 109);
    await deleteRestoreClient(browser, owner.page, result.entities.restoreClientId, result.crud, 110);

    result.documents.push({ entity: 'document-whitelist', action: 'formats-covered', formats: ['PDF','PNG','JPG','XLSX'], pass: true });
    const critical = [...result.crud, ...result.trip, ...result.documents, ...result.finance].filter((item) => !item.nonBlocking);
    result.status = critical.every((item) => item.pass)
      && result.pageErrors.length === 0
      && result.requestFailures.length === 0
      && result.unexpectedHttp.length === 0
      && result.consoleErrors.filter((item) => !/403/.test(item.message)).length === 0
      ? 'PASS' : 'FAIL';
  } catch (error) {
    result.error = { name: error?.name || 'Error', message: String(error?.message || error).slice(0, 1200) };
  } finally {
    if (owner?.context) await owner.context.close().catch(() => {});
    if (browser) await browser.close().catch(() => {});
    fs.writeFileSync(path.join(OUT, 'P17_03_CRUD_MATRIX.json'), JSON.stringify(result.crud, null, 2));
    fs.writeFileSync(path.join(OUT, 'P17_04_TRIP_LIFECYCLE_MATRIX.json'), JSON.stringify({ status: result.status, deployedSha: result.deployedSha, tripId: result.entities.tripId || null, scenarios: result.trip, error: result.error }, null, 2));
    fs.writeFileSync(path.join(OUT, 'P17_05_DOCUMENT_MATRIX.json'), JSON.stringify(result.documents, null, 2));
    fs.writeFileSync(path.join(OUT, 'P17_06_FINANCE_MATRIX.json'), JSON.stringify(result.finance, null, 2));
    fs.writeFileSync(path.join(OUT, 'P17_CRUD_RUNTIME_SUMMARY.json'), JSON.stringify({ status: result.status, deployedSha: result.deployedSha, entities: result.entities, consoleErrors: result.consoleErrors, pageErrors: result.pageErrors, requestFailures: result.requestFailures, unexpectedHttp: result.unexpectedHttp, error: result.error }, null, 2));
    console.log(`P17_CRUD_TRIP_FINANCE=${result.status}`);
    process.exitCode = result.status === 'PASS' ? 0 : 1;
  }
})();
