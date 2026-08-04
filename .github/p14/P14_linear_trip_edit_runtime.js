'use strict';

const fs = require('fs');
const path = require('path');
const { chromium } = require('playwright');

const BASE = (process.env.P14_BASE_URL || 'https://plan-ex.ru/erpv2/').replace(/\/+$/, '/');
const OUT = path.resolve(process.env.P14_OUT_DIR || 'P14_output');
const SHOTS = path.join(OUT, 'P14_modal_discovery_screenshots');
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
async function login(context, credential) {
  const page = await context.newPage();
  await page.goto(new URL('login', BASE).href, { waitUntil: 'domcontentloaded', timeout: 45000 });
  await page.locator('input[name="username"],input[name="email"],input[type="email"],input[type="text"]').first().fill(credential.username);
  await page.locator('input[type="password"]').first().fill(credential.password);
  await Promise.all([
    page.waitForLoadState('domcontentloaded', { timeout: 30000 }).catch(() => {}),
    page.locator('button[type="submit"],input[type="submit"]').first().click()
  ]);
  await page.waitForTimeout(700);
  credential.password = '';
  credential.username = '';
  if (/\/login(?:[/?#]|$)/i.test(page.url())) throw new Error('login failed');
  return page;
}
async function pageSchema(page) {
  return page.evaluate(() => {
    function visible(el) {
      const style = getComputedStyle(el);
      const rect = el.getBoundingClientRect();
      return style.display !== 'none' && style.visibility !== 'hidden' && Number(style.opacity || 1) > 0.05 && rect.width > 1 && rect.height > 1;
    }
    function labelFor(el) {
      if (el.id) {
        const label = document.querySelector(`label[for="${CSS.escape(el.id)}"]`);
        if (label) return (label.textContent || '').trim().replace(/\s+/g,' ').slice(0,180);
      }
      const parent = el.closest('label');
      if (parent) return (parent.textContent || '').trim().replace(/\s+/g,' ').slice(0,180);
      const field = el.closest('.field,.form-group,.form-row,.modal-field,.form-grid-item');
      const near = field?.querySelector('label,.field-label,.form-label');
      return near ? (near.textContent || '').trim().replace(/\s+/g,' ').slice(0,180) : '';
    }
    const activeModals = [...document.querySelectorAll('.modal-overlay.is-open,[role="dialog"]')].filter(visible).map(m => ({
      id: m.id || null,
      text: (m.innerText || '').trim().replace(/\s+/g,' ').slice(0,1000)
    }));
    const forms = [...document.forms].filter(visible).map((form, index) => ({
      index,
      id: form.id || null,
      method: (form.method || 'get').toUpperCase(),
      action: form.action || null,
      fields: [...form.querySelectorAll('input,select,textarea')]
        .filter(el => el.type !== 'hidden' && visible(el))
        .map(el => ({
          tag: el.tagName.toLowerCase(),
          type: el.type || null,
          name: el.name || null,
          id: el.id || null,
          required: Boolean(el.required),
          placeholder: el.getAttribute('placeholder'),
          accept: el.getAttribute('accept'),
          label: labelFor(el),
          options: el.tagName === 'SELECT' ? [...el.options].map(o => ({ value:o.value, text:(o.textContent||'').trim().replace(/\s+/g,' ').slice(0,120) })) : undefined
        })),
      buttons: [...form.querySelectorAll('button,input[type="submit"],input[type="button"]')].filter(visible).map(b => ({
        type: b.type || null,
        name: b.name || null,
        text: (b.textContent || b.value || '').trim().replace(/\s+/g,' ').slice(0,160)
      }))
    }));
    return {
      title: document.title,
      finalUrl: location.href,
      activeModals,
      forms,
      visibleButtons: [...document.querySelectorAll('button,[role="button"],a.btn,a.button')].filter(visible).map(b => (b.textContent||'').trim().replace(/\s+/g,' ').slice(0,160)).filter(Boolean).slice(0,120)
    };
  });
}
async function openAndCapture(page, spec, result, role, index) {
  const entry = { role, path: spec.path, trigger: String(spec.trigger), status: 'FAIL', clickError: null, schema: null, screenshot: null };
  try {
    await page.goto(new URL(spec.path, BASE).href, { waitUntil: 'domcontentloaded', timeout: 45000 });
    await page.waitForTimeout(500);
    const candidates = page.locator('button,a,[role="button"],input[type="button"],input[type="submit"]');
    const target = candidates.filter({ hasText: spec.trigger }).first();
    if (!(await target.count())) throw new Error('trigger not found');
    await target.click({ timeout: 10000 });
    await page.waitForTimeout(spec.wait || 1200);
    entry.schema = await pageSchema(page);
    entry.screenshot = `P14_MODAL_${role}_${String(index).padStart(2,'0')}_${spec.path.replace(/[^a-z0-9]+/gi,'_')}_${String(spec.trigger).replace(/[^a-z0-9а-яё]+/giu,'_')}_1920x1080.png`;
    await page.screenshot({ path: path.join(SHOTS, entry.screenshot), fullPage: false });
    entry.status = entry.schema.forms.length > 0 ? 'PASS' : 'FAIL';
  } catch (error) {
    entry.clickError = error?.name || 'Error';
    entry.schema = await pageSchema(page).catch(() => null);
  }
  result.actions.push(entry);
  await page.keyboard.press('Escape').catch(() => {});
  await page.waitForTimeout(200);
}

(async () => {
  const result = { status:'FAIL', actions:[], errors:[] };
  let browser;
  try {
    const creds = decodeCredentials();
    const superadmin = pick(creds,'SUPERADMIN');
    const owner = pick(creds,'OWNER');
    if (!superadmin || !owner) throw new Error('credentials unavailable');
    browser = await chromium.launch({ headless:true });

    const superContext = await browser.newContext({ viewport:{width:1920,height:1080},deviceScaleFactor:1,ignoreHTTPSErrors:true,locale:'ru-RU',timezoneId:'Europe/Moscow' });
    const superPage = await login(superContext,{...superadmin});
    const superSpecs = [
      { path:'superadmin/companies/25/users', trigger:/Создать пользователя/i },
      { path:'superadmin/companies', trigger:/Создать экспедитора/i }
    ];
    for (let i=0;i<superSpecs.length;i+=1) await openAndCapture(superPage,superSpecs[i],result,'SUPERADMIN',i+1);
    await superContext.close();

    const ownerContext = await browser.newContext({ viewport:{width:1920,height:1080},deviceScaleFactor:1,ignoreHTTPSErrors:true,locale:'ru-RU',timezoneId:'Europe/Moscow' });
    const ownerPage = await login(ownerContext,{...owner});
    const ownerSpecs = [
      { path:'company/clients', trigger:/Создать клиента/i },
      { path:'company/contractors', trigger:/^Создать перевозчика$/i },
      { path:'company/drivers', trigger:/Добавить нового водителя/i },
      { path:'company/vehicle-sets', trigger:/Добавить|Создать/i },
      { path:'company/logists', trigger:/Добавить|Создать/i },
      { path:'company/finance/cash', trigger:/Внести|Приход/i },
      { path:'company/finance/cash', trigger:/Снять|Расход/i },
      { path:'company/finance/invoices', trigger:/Создать счёт/i },
      { path:'company/finance/settings/dds-categories', trigger:/Создать статью/i }
    ];
    for (let i=0;i<ownerSpecs.length;i+=1) await openAndCapture(ownerPage,ownerSpecs[i],result,'OWNER',i+1);
    await ownerContext.close();

    result.status = result.actions.every(a => a.status === 'PASS') ? 'PASS' : 'PARTIAL';
  } catch (error) {
    result.errors.push(error?.name || 'Error');
  } finally {
    if (browser) await browser.close().catch(() => {});
    fs.writeFileSync(path.join(OUT,'P14_runtime_result.json'),JSON.stringify(result,null,2));
    console.log(`P14_MODAL_DISCOVERY=${result.status}`);
    process.exitCode = result.status === 'FAIL' ? 1 : 0;
  }
})().catch(() => {
  console.log('P14_MODAL_DISCOVERY=FAIL');
  process.exitCode=1;
});
