'use strict';
const fs = require('fs');
const path = require('path');
const { chromium } = require('playwright');
const BASE_URL = (process.env.P14_BASE_URL || 'https://plan-ex.ru/erpv2/').replace(/\/+$/, '/');
const OUT_DIR = path.resolve(process.env.P14_OUT_DIR || 'P14_output');
fs.mkdirSync(OUT_DIR, { recursive: true });
function credentials() {
  try { return JSON.parse(Buffer.from(process.env.P11_CREDENTIALS_B64 || '', 'base64').toString('utf8')); }
  catch (_) { return []; }
}
(async () => {
  const evidence = {status:'FAIL', baseUrl:BASE_URL, commit:process.env.GITHUB_SHA || null};
  const rows = credentials();
  const credential = rows.find((row) => /company_owner|owner|руковод/i.test(String(row.role || ''))) || rows[0];
  if (!credential) throw new Error('Runtime credentials are unavailable.');
  const browser = await chromium.launch({headless:true});
  const context = await browser.newContext({viewport:{width:1920,height:1080}, ignoreHTTPSErrors:true, locale:'ru-RU'});
  const page = await context.newPage();
  try {
    await page.goto(new URL('login', BASE_URL).href, {waitUntil:'domcontentloaded', timeout:30000});
    const user = page.locator('input[name="username"],input[name="email"],input[type="email"],input[type="text"]').first();
    const pass = page.locator('input[type="password"]').first();
    await user.fill(credential.username);
    await pass.fill(credential.password);
    await Promise.all([page.waitForLoadState('domcontentloaded').catch(()=>{}), page.locator('button[type="submit"],input[type="submit"]').first().click()]);
    await page.goto(new URL('company/trips/linear', BASE_URL).href, {waitUntil:'domcontentloaded', timeout:30000});
    const row = page.locator('tr[data-linear-route-id]').first();
    await row.waitFor({state:'visible', timeout:15000});
    evidence.routeId = await row.getAttribute('data-linear-route-id');
    await row.dblclick();
    await page.locator('[data-linear-trip-edit-btn]').first().click();
    const form = page.locator('#linear-trip-edit-form');
    await form.waitFor({state:'visible', timeout:15000});
    evidence.formAction = await form.getAttribute('action');
    evidence.formEncoding = await form.getAttribute('enctype');
    evidence.carrierValue = await form.locator('[name="carrier_contractor_id"]').inputValue().catch(()=>null);
    evidence.executorValue = await form.locator('[name="route_executor_id"]').inputValue().catch(()=>null);
    evidence.paymentConditionsBefore = await form.evaluate((element) => {
      const result = {};
      element.querySelectorAll('select[name$="[condition_type]"]').forEach((select) => { result[select.name] = select.value; });
      return result;
    });
    await form.evaluate((element) => {
      element.querySelectorAll('select[name$="[condition_type]"]').forEach((select) => {
        if (!select.value) select.value = 'start_day';
      });
    });
    evidence.paymentConditionsAfter = await form.evaluate((element) => {
      const result = {};
      element.querySelectorAll('select[name$="[condition_type]"]').forEach((select) => { result[select.name] = select.value; });
      return result;
    });
    evidence.submit = await form.evaluate(async (element) => {
      const data = new FormData(element);
      const response = await fetch(element.action, {
        method:'POST', credentials:'same-origin',
        headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'},
        body:data
      });
      return {status:response.status, ok:response.ok, body:(await response.text()).slice(0,4000)};
    });
    try { evidence.submitJson = JSON.parse(evidence.submit.body); } catch (_) {}
    evidence.status = evidence.submit.ok && evidence.submitJson && evidence.submitJson.success === true ? 'PASS' : 'FAIL';
    await page.screenshot({path:path.join(OUT_DIR,'P14_save_result_1920x1080.png')});
  } catch (error) {
    evidence.error = String(error && error.message || error);
  } finally {
    fs.writeFileSync(path.join(OUT_DIR,'P14_runtime_result.json'), JSON.stringify(evidence,null,2));
    console.log(JSON.stringify(evidence));
    await context.close(); await browser.close();
  }
  process.exit(evidence.status === 'PASS' ? 0 : 1);
})();
