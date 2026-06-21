import { chromium } from 'playwright';

const BASE = 'http://127.0.0.1:8016';
const DIR = 'docs/design-audit/full-ui-revision/screenshots';

async function capture() {
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({ viewport: { width: 1920, height: 1080 } });
  const page = await context.newPage();

  // Login page
  console.log('Capturing login page...');
  await page.goto(`${BASE}/login`, { waitUntil: 'networkidle' });
  await page.waitForTimeout(500);
  await page.screenshot({ path: `${DIR}/auth__login__form__empty.png`, fullPage: true });
  console.log('Login page captured');

  // Login as superadmin
  await page.fill('input[name="login"]', 'admin@planex.local');
  await page.fill('input[name="password"]', 'admin123');
  await page.click('button[type="submit"]');
  await page.waitForTimeout(2000);
  console.log('Logged in as superadmin');

  // Add missing superadmin screenshots
  // superadmin/companies/9/owner/edit
  await page.goto(`${BASE}/superadmin/companies/9/owner/edit`, { waitUntil: 'networkidle' });
  await page.waitForTimeout(500);
  await page.screenshot({ path: `${DIR}/superadmin__company__owner_edit__form.png`, fullPage: true });
  console.log('Owner edit captured');

  await context.close();

  // Login as owner for missing screenshots
  const ctx2 = await browser.newContext({ viewport: { width: 1920, height: 1080 } });
  const page2 = await ctx2.newPage();
  await page2.goto(`${BASE}/login`, { waitUntil: 'networkidle' });
  await page2.fill('input[name="login"]', 'owner_test_runtime');
  await page2.fill('input[name="password"]', 'pass1234');
  await page2.click('button[type="submit"]');
  await page2.waitForTimeout(2000);
  console.log('Logged in as owner');

  // Check runtime data - look for specific entities
  // Drivers
  await page2.goto(`${BASE}/company/drivers`, { waitUntil: 'networkidle' });
  await page2.waitForTimeout(500);
  const driverInfo = await page2.evaluate(() => {
    const rows = document.querySelectorAll('table tbody tr');
    const results = [];
    for (const row of rows) {
      const cells = row.querySelectorAll('td');
      const links = row.querySelectorAll('a[href]');
      const text = row.textContent || '';
      for (const link of links) {
        const href = link.getAttribute('href');
        const match = href.match(/\/drivers\/(\d+)/);
        if (match) results.push({ id: match[1], text: text.substring(0, 100) });
        break;
      }
    }
    return results;
  });
  console.log('Drivers:', JSON.stringify(driverInfo));

  // Vehicles
  await page2.goto(`${BASE}/company/vehicles`, { waitUntil: 'networkidle' });
  await page2.waitForTimeout(500);
  const vehicleInfo = await page2.evaluate(() => {
    const rows = document.querySelectorAll('table tbody tr');
    const results = [];
    for (const row of rows) {
      const links = row.querySelectorAll('a[href]');
      const text = row.textContent || '';
      for (const link of links) {
        const href = link.getAttribute('href');
        const match = href.match(/\/vehicles\/(\d+)/);
        if (match) results.push({ id: match[1], text: text.substring(0, 100) });
        break;
      }
    }
    return results;
  });
  console.log('Vehicles:', JSON.stringify(vehicleInfo));

  // Vehicle Sets
  await page2.goto(`${BASE}/company/vehicle-sets`, { waitUntil: 'networkidle' });
  await page2.waitForTimeout(500);
  const setInfo = await page2.evaluate(() => {
    const rows = document.querySelectorAll('table tbody tr');
    const results = [];
    for (const row of rows) {
      const links = row.querySelectorAll('a[href]');
      const text = row.textContent || '';
      for (const link of links) {
        const href = link.getAttribute('href');
        const match = href.match(/\/vehicle-sets\/(\d+)/);
        if (match) results.push({ id: match[1], text: text.substring(0, 100) });
        break;
      }
    }
    return results;
  });
  console.log('Vehicle Sets:', JSON.stringify(setInfo));

  // DVB
  await page2.goto(`${BASE}/company/driver-vehicle-blocks`, { waitUntil: 'networkidle' });
  await page2.waitForTimeout(500);
  const dvbInfo = await page2.evaluate(() => {
    const rows = document.querySelectorAll('table tbody tr');
    const results = [];
    for (const row of rows) {
      const links = row.querySelectorAll('a[href]');
      const text = row.textContent || '';
      for (const link of links) {
        const href = link.getAttribute('href');
        const match = href.match(/\/driver-vehicle-blocks\/(\d+)/);
        if (match) results.push({ id: match[1], text: text.substring(0, 100) });
        break;
      }
    }
    return results;
  });
  console.log('DVB:', JSON.stringify(dvbInfo));

  // Crews
  await page2.goto(`${BASE}/company/crews`, { waitUntil: 'networkidle' });
  await page2.waitForTimeout(500);
  const crewInfo = await page2.evaluate(() => {
    const rows = document.querySelectorAll('table tbody tr');
    const results = [];
    for (const row of rows) {
      const links = row.querySelectorAll('a[href]');
      const text = row.textContent || '';
      for (const link of links) {
        const href = link.getAttribute('href');
        const match = href.match(/\/crews\/(\d+)/);
        if (match) results.push({ id: match[1], text: text.substring(0, 100) });
        break;
      }
    }
    return results;
  });
  console.log('Crews:', JSON.stringify(crewInfo));

  await ctx2.close();

  // Logist dashboard fix - recapture
  for (const logistCreds of [
    { login: 'logist_runtime_1', password: 'pass1111', label: '1' },
    { login: 'logist_runtime_2', password: 'pass2222', label: '2' }
  ]) {
    const ctx = await browser.newContext({ viewport: { width: 1920, height: 1080 } });
    const pg = await ctx.newPage();
    await pg.goto(`${BASE}/login`, { waitUntil: 'networkidle' });
    await pg.fill('input[name="login"]', logistCreds.login);
    await pg.fill('input[name="password"]', logistCreds.password);
    await pg.click('button[type="submit"]');
    await pg.waitForTimeout(2000);
    console.log(`Logged in as logist ${logistCreds.label}`);

    await pg.goto(`${BASE}/company/dashboard`, { waitUntil: 'networkidle' });
    await pg.waitForTimeout(500);
    await pg.screenshot({ path: `${DIR}/logist${logistCreds.label}__dashboard__dashboard__filled.png`, fullPage: true });
    console.log(`Logist ${logistCreds.label} dashboard captured`);

    await ctx.close();
  }

  await browser.close();
  console.log('Done!');
}

capture().catch(console.error);
