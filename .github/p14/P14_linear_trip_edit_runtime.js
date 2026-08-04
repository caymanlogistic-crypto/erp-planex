'use strict';

const fs = require('fs');
const path = require('path');
const crypto = require('crypto');
const { chromium } = require('playwright');

const BASE = (process.env.P14_BASE_URL || 'https://plan-ex.ru/erpv2/').replace(/\/+$/, '/');
const OUT = path.resolve(process.env.P14_OUT_DIR || 'P14_output');
const SHOTS = path.join(OUT, 'P14_tenant_setup_screenshots');
fs.mkdirSync(SHOTS, { recursive: true });

function decodeCredentials() {
  const encoded = process.env.P11_CREDENTIALS_B64 || '';
  if (!encoded) return [];
  const parsed = JSON.parse(Buffer.from(encoded, 'base64').toString('utf8'));
  return Array.isArray(parsed) ? parsed.filter(x => x && x.username && x.password) : [];
}
function pick(items, role) {
  return items.find(x => String(x.role || '').toUpperCase() === role) || null;
}
function randomPassword() {
  return `P14!${crypto.randomBytes(18).toString('base64url')}aA7`;
}
function validInn10(seed) {
  const digits = String(seed).replace(/\D/g, '').padStart(9, '0').slice(-9).split('').map(Number);
  const weights = [2,4,10,3,5,9,4,6,8];
  const check = (digits.reduce((sum, d, i) => sum + d * weights[i], 0) % 11) % 10;
  return digits.join('') + check;
}
function slugNow() {
  const d = new Date();
  return `${String(d.getUTCFullYear()).slice(-2)}${String(d.getUTCMonth()+1).padStart(2,'0')}${String(d.getUTCDate()).padStart(2,'0')}${String(d.getUTCHours()).padStart(2,'0')}${String(d.getUTCMinutes()).padStart(2,'0')}${String(d.getUTCSeconds()).padStart(2,'0')}`;
}
async function login(context, credential) {
  const page = await context.newPage();
  await page.goto(new URL('login', BASE).href, { waitUntil:'domcontentloaded', timeout:45000 });
  await page.locator('input[name="username"],input[name="email"],input[type="email"],input[type="text"]').first().fill(credential.username);
  await page.locator('input[type="password"]').first().fill(credential.password);
  await Promise.all([
    page.waitForLoadState('domcontentloaded', { timeout:30000 }).catch(() => {}),
    page.locator('button[type="submit"],input[type="submit"]').first().click()
  ]);
  await page.waitForTimeout(900);
  if (/\/login(?:[/?#]|$)/i.test(page.url())) throw new Error('authentication failed');
  return page;
}
async function fillByName(form, name, value) {
  const locator = form.locator(`[name="${name.replace(/"/g,'\\"')}"]`).first();
  if (!(await locator.count())) return false;
  const tag = await locator.evaluate(el => el.tagName.toLowerCase());
  if (tag === 'select') await locator.selectOption(String(value));
  else await locator.fill(String(value));
  return true;
}
async function submitForm(page, form) {
  const responsePromise = page.waitForResponse(r => r.request().method() === 'POST', { timeout:20000 }).catch(() => null);
  const navigationPromise = page.waitForLoadState('domcontentloaded', { timeout:20000 }).catch(() => null);
  await form.evaluate(f => f.requestSubmit());
  const response = await responsePromise;
  await navigationPromise;
  await page.waitForTimeout(900);
  return response;
}
async function locateCompanyId(page, companyName) {
  const fromUrl = page.url().match(/\/superadmin\/companies\/(\d+)(?:[/?#]|$)/);
  if (fromUrl) return Number(fromUrl[1]);
  await page.goto(new URL('superadmin/companies', BASE).href, { waitUntil:'domcontentloaded', timeout:45000 });
  const search = page.locator('input[name="search"]').first();
  if (await search.count()) {
    await search.fill(companyName);
    await Promise.all([
      page.waitForLoadState('domcontentloaded', { timeout:20000 }).catch(() => {}),
      page.locator('form[method="get"] button[type="submit"],form[method="get"] input[type="submit"]').first().click()
    ]).catch(() => {});
    await page.waitForTimeout(500);
  }
  const rowLink = page.locator('a[href*="/superadmin/companies/"]').filter({ hasText: companyName }).first();
  if (await rowLink.count()) {
    const href = await rowLink.getAttribute('href');
    const match = String(href).match(/\/superadmin\/companies\/(\d+)/);
    if (match) return Number(match[1]);
  }
  const hrefs = await page.locator('a[href*="/superadmin/companies/"]').evaluateAll(nodes => nodes.map(n => ({ href:n.href,text:(n.textContent||'').trim() })));
  const candidate = hrefs.find(x => x.text.includes(companyName));
  const match = String(candidate?.href || '').match(/\/superadmin\/companies\/(\d+)/);
  return match ? Number(match[1]) : null;
}
async function createCompany(page, companyName, inn, result) {
  await page.goto(new URL('superadmin/companies/create', BASE).href, { waitUntil:'domcontentloaded', timeout:45000 });
  const form = page.locator('form[action$="/superadmin/companies/create"]').first();
  if (!(await form.count())) throw new Error('company create form unavailable');
  await fillByName(form,'name',companyName);
  await fillByName(form,'inn',inn);
  await fillByName(form,'kpp','770101001');
  await fillByName(form,'ogrn','1027700132195');
  await fillByName(form,'director_full_name','Тестов Владелец П14');
  await fillByName(form,'director_position','Генеральный директор');
  await fillByName(form,'legal_address','127000, г. Москва, Тестовый проезд, д. 14');
  await fillByName(form,'physical_address','127000, г. Москва, Тестовый проезд, д. 14');
  await fillByName(form,'bank_account','40702810900000000014');
  await fillByName(form,'bank_bik','044525225');
  await fillByName(form,'bank_name','ПАО ТЕСТ БАНК');
  await fillByName(form,'bank_corr_account','30101810400000000225');
  await fillByName(form,'comments','P14 isolated UI/UX audit tenant. Do not use for production operations.');
  const response = await submitForm(page, form);
  result.companyCreateHttpStatus = response?.status() ?? null;
  const text = await page.locator('body').innerText().catch(() => '');
  if (/ошиб|не удалось|некоррект/i.test(text) && !/компания создана|успеш/i.test(text)) throw new Error('company create returned validation error');
  const id = await locateCompanyId(page, companyName);
  if (!id) throw new Error('created company id unavailable');
  return id;
}
async function createOwner(page, companyId, account) {
  const ownerUrl = new URL(`superadmin/companies/${companyId}/owner`, BASE).href;
  await page.goto(ownerUrl, { waitUntil:'domcontentloaded', timeout:45000 });
  const existing = await page.locator('body').innerText().catch(() => '');
  if (/руководитель.*актив|владелец.*актив/i.test(existing) && !(await page.locator('form').filter({ has: page.locator('[name="full_name"]') }).count())) {
    throw new Error('test company unexpectedly already has owner');
  }
  let form = page.locator('form').filter({ has: page.locator('[name="full_name"]') }).first();
  if (!(await form.count())) {
    const createButton = page.locator('button,a').filter({ hasText:/создать.*руковод|добавить.*руковод/i }).first();
    if (await createButton.count()) {
      await createButton.click();
      await page.waitForTimeout(700);
      form = page.locator('form').filter({ has: page.locator('[name="full_name"]') }).first();
    }
  }
  if (!(await form.count())) {
    await page.goto(new URL(`superadmin/companies/${companyId}/owner/edit`, BASE).href, { waitUntil:'domcontentloaded', timeout:45000 });
    form = page.locator('form').filter({ has: page.locator('[name="full_name"]') }).first();
  }
  if (!(await form.count())) throw new Error('owner create form unavailable');
  await fillByName(form,'full_name','Тестов Владелец П14');
  await fillByName(form,'login',account.username);
  await fillByName(form,'email',`${account.username}@example.test`);
  await fillByName(form,'phone','+79990001401');
  await fillByName(form,'position','Генеральный директор');
  await fillByName(form,'status','active');
  await fillByName(form,'comments','P14 isolated test owner');
  const passwordFilled = await fillByName(form,'password',account.password);
  if (!passwordFilled) {
    const gen = page.locator('button').filter({ hasText:/сгенерировать/i }).first();
    if (await gen.count()) {
      await gen.click();
      const passInput = form.locator('input[type="password"]').first();
      if (await passInput.count()) await passInput.fill(account.password);
    }
  }
  const response = await submitForm(page,form);
  if (response && response.status() >= 400) throw new Error('owner create http failure');
}
async function createCompanyUser(page, companyId, account, role) {
  await page.goto(new URL(`superadmin/companies/${companyId}/users`, BASE).href, { waitUntil:'domcontentloaded', timeout:45000 });
  const trigger = page.locator('button,a').filter({ hasText:/создать пользователя/i }).first();
  if (!(await trigger.count())) throw new Error('create user trigger unavailable');
  await trigger.click();
  await page.waitForTimeout(700);
  const form = page.locator('#sa-logist-create-form,form[action*="/users/logists/modal-create"]').first();
  if (!(await form.count())) throw new Error('company user form unavailable');
  await fillByName(form,'full_name',account.fullName);
  await fillByName(form,'login',account.username);
  await fillByName(form,'email',`${account.username}@example.test`);
  await fillByName(form,'phone',account.phone);
  await fillByName(form,'role',role);
  await fillByName(form,'password',account.password);
  const response = await submitForm(page,form);
  if (response && response.status() >= 400) throw new Error('company user create http failure');
  const body = await page.locator('body').innerText().catch(() => '');
  if (/ошиб|не удалось|уже существует/i.test(body) && !body.includes(account.fullName)) throw new Error('company user validation failure');
}
async function verifyRole(browser, account, role, result) {
  const context = await browser.newContext({ viewport:{width:1920,height:1080},deviceScaleFactor:1,ignoreHTTPSErrors:true,locale:'ru-RU',timezoneId:'Europe/Moscow' });
  const page = await login(context,{ username:account.username,password:account.password });
  const expectedPath = role === 'COMPANY_OWNER' ? 'company/dashboard' : 'company/dashboard';
  const response = await page.goto(new URL(expectedPath,BASE).href,{waitUntil:'domcontentloaded',timeout:45000}).catch(() => null);
  await page.waitForTimeout(500);
  const body = await page.locator('body').innerText().catch(() => '');
  const pass = response?.status() === 200 && !/\/login(?:[/?#]|$)/i.test(page.url()) && !/доступ запрещ|forbidden/i.test(body);
  const shot = `P14_ROLE_${role}_DASHBOARD_1920x1080.png`;
  await page.screenshot({path:path.join(SHOTS,shot),fullPage:false}).catch(() => {});
  result.roleLoginChecks.push({ role, httpStatus:response?.status() ?? null, finalUrl:page.url(), pass, screenshot:shot });
  await context.close();
  if (!pass) throw new Error(`role login failed: ${role}`);
}

(async () => {
  const result = {
    status:'FAIL',
    companyName:'UIUX TEST EXPEDITOR',
    companyId:null,
    companyCreateHttpStatus:null,
    createdRoles:[],
    roleLoginChecks:[],
    screenshots:[],
    errors:[]
  };
  let browser;
  try {
    const superadmin = pick(decodeCredentials(),'SUPERADMIN');
    if (!superadmin) throw new Error('superadmin credential unavailable');
    const suffix = slugNow();
    const owner = { role:'COMPANY_OWNER', fullName:'Тестов Владелец П14', username:`p14_owner_${suffix}`, password:randomPassword(), phone:'+79990001401' };
    const senior = { role:'SENIOR_LOGIST', fullName:'Тестов Старший Логист П14', username:`p14_senior_${suffix}`, password:randomPassword(), phone:'+79990001402' };
    const logist1 = { role:'LOGIST_1', fullName:'Тестов Логист Один П14', username:`p14_logist1_${suffix}`, password:randomPassword(), phone:'+79990001403' };
    const logist2 = { role:'LOGIST_2', fullName:'Тестов Логист Два П14', username:`p14_logist2_${suffix}`, password:randomPassword(), phone:'+79990001404' };
    browser = await chromium.launch({headless:true});
    const context = await browser.newContext({viewport:{width:1920,height:1080},deviceScaleFactor:1,ignoreHTTPSErrors:true,locale:'ru-RU',timezoneId:'Europe/Moscow'});
    const page = await login(context,{...superadmin});
    result.companyId = await createCompany(page,result.companyName,validInn10(Date.now()),result);
    await page.goto(new URL(`superadmin/companies/${result.companyId}`,BASE).href,{waitUntil:'domcontentloaded',timeout:45000});
    const companyShot='P14_TEST_COMPANY_CREATED_1920x1080.png';
    await page.screenshot({path:path.join(SHOTS,companyShot),fullPage:false});
    result.screenshots.push(companyShot);
    await createOwner(page,result.companyId,owner);
    result.createdRoles.push('COMPANY_OWNER');
    await createCompanyUser(page,result.companyId,senior,'senior_logist');
    result.createdRoles.push('SENIOR_LOGIST');
    await createCompanyUser(page,result.companyId,logist1,'logist');
    result.createdRoles.push('LOGIST_1');
    await createCompanyUser(page,result.companyId,logist2,'logist');
    result.createdRoles.push('LOGIST_2');
    await page.goto(new URL(`superadmin/companies/${result.companyId}/users`,BASE).href,{waitUntil:'domcontentloaded',timeout:45000});
    const usersShot='P14_TEST_COMPANY_USERS_1920x1080.png';
    await page.screenshot({path:path.join(SHOTS,usersShot),fullPage:false});
    result.screenshots.push(usersShot);
    await context.close();
    await verifyRole(browser,owner,'COMPANY_OWNER',result);
    await verifyRole(browser,senior,'SENIOR_LOGIST',result);
    await verifyRole(browser,logist1,'LOGIST_1',result);
    await verifyRole(browser,logist2,'LOGIST_2',result);
    result.status='PASS';
    owner.password=''; senior.password=''; logist1.password=''; logist2.password='';
  } catch (error) {
    result.errors.push({ name:error?.name || 'Error', message:String(error?.message || error).replace(/p14_[a-z0-9_]+/gi,'[REDACTED_LOGIN]').slice(0,800) });
  } finally {
    if (browser) await browser.close().catch(() => {});
    fs.writeFileSync(path.join(OUT,'P14_runtime_result.json'),JSON.stringify(result,null,2));
    console.log(`P14_TENANT_SETUP=${result.status}`);
    process.exitCode=result.status==='PASS'?0:1;
  }
})().catch(() => {
  console.log('P14_TENANT_SETUP=FAIL');
  process.exitCode=1;
});
