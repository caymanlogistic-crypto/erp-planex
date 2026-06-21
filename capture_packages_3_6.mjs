import { chromium } from 'playwright';
import { mkdirSync } from 'fs';
import { join } from 'path';

const BASE = 'http://127.0.0.1:8016';
const OUT = 'docs/design-audit/final-ui-review-after-fixes/screenshots';
mkdirSync(OUT, { recursive: true });

let shot = 0;

async function snap(page, name) {
  shot++;
  const path = join(OUT, `${String(shot).padStart(3, '0')}_${name}`);
  await page.screenshot({ path, fullPage: true });
  console.log(`  [${shot}] ${name}`);
}

async function login(browser, loginVal, password) {
  const ctx = await browser.newContext({ viewport: { width: 1920, height: 1080 } });
  const page = await ctx.newPage();
  await page.goto(`${BASE}/login`, { waitUntil: 'networkidle' });
  await page.fill('input[name="login"]', loginVal);
  await page.fill('input[name="password"]', password);
  await page.click('button[type="submit"]');
  await page.waitForTimeout(2000);
  // check login success
  const url = page.url();
  if (url.includes('/login')) {
    const err = await page.$('.alert-error');
    const txt = err ? await err.textContent() : 'unknown';
    console.log(`  LOGIN FAILED: ${loginVal} -> ${txt}`);
    await ctx.close();
    return null;
  }
  console.log(`  LOGIN OK: ${loginVal} -> ${url}`);
  return { ctx, page };
}

async function main() {
  console.log('=== ERP PLANEX — Packages 3-6 Screenshot Capture ===\n');
  const browser = await chromium.launch({ headless: true });

  // --- LOGIN PAGE ---
  {
    const ctx = await browser.newContext({ viewport: { width: 1920, height: 1080 } });
    const page = await ctx.newPage();
    await page.goto(`${BASE}/login`, { waitUntil: 'networkidle' });
    await page.waitForTimeout(500);
    await snap(page, 'login_page.png');
    await ctx.close();
  }

  // --- SUPERADMIN ---
  console.log('\n--- SUPERADMIN ---');
  let sa = await login(browser, 'admin@planex.local', 'test123');
  if (!sa) sa = await login(browser, 'admin@planex.local', 'admin123');
  if (sa) {
    // Dashboard
    await sa.page.goto(`${BASE}/superadmin`, { waitUntil: 'networkidle' });
    await snap(sa.page, 'superadmin_dashboard.png');

    // Companies list
    await sa.page.goto(`${BASE}/superadmin/companies`, { waitUntil: 'networkidle' });
    await snap(sa.page, 'superadmin_companies.png');

    // Company view (try company 9)
    await sa.page.goto(`${BASE}/superadmin/companies/9`, { waitUntil: 'networkidle' });
    await snap(sa.page, 'superadmin_company_view.png');

    // Users table (P4 TASK-022: Block/Archive removed)
    await sa.page.goto(`${BASE}/superadmin/companies/9/users`, { waitUntil: 'networkidle' });
    await snap(sa.page, 'superadmin_company_users.png');

    // Owner view (P4 TASK-027: password reset confirm)
    await sa.page.goto(`${BASE}/superadmin/companies/9/owner`, { waitUntil: 'networkidle' });
    await snap(sa.page, 'superadmin_company_owner.png');

    // Create logist form (P4 TASK-017: masked password)
    await sa.page.goto(`${BASE}/superadmin/companies/9/users/logists/create`, { waitUntil: 'networkidle' });
    await snap(sa.page, 'superadmin_logist_create.png');

    await sa.ctx.close();
  }

  // --- COMPANY OWNER ---
  console.log('\n--- COMPANY OWNER ---');
  let ow = await login(browser, 'owner_test_runtime', 'test123');
  if (!ow) ow = await login(browser, 'owner_test_runtime', 'pass1234');
  if (ow) {
    // Dashboard (P6: metrics)
    await ow.page.goto(`${BASE}/company/dashboard`, { waitUntil: 'networkidle' });
    await snap(ow.page, 'owner_dashboard.png');

    // Logists list (P5 TASK-011: @login format)
    await ow.page.goto(`${BASE}/company/logists`, { waitUntil: 'networkidle' });
    await snap(ow.page, 'owner_logists.png');

    // Logist create (P4 TASK-017: masked password)
    await ow.page.goto(`${BASE}/company/logists/create`, { waitUntil: 'networkidle' });
    await snap(ow.page, 'owner_logist_create.png');

    // Contractors list (P5: compact table 5 cols)
    await ow.page.goto(`${BASE}/company/contractors`, { waitUntil: 'networkidle' });
    await snap(ow.page, 'owner_contractors.png');

    // Contractor view (P5 UX-009: restructured, contacts above bank)
    // Discover contractor ID first
    let ctId = null;
    try {
      ctId = await ow.page.evaluate(() => {
        const links = document.querySelectorAll('a[href*="/contractors/"]');
        for (const l of links) {
          const m = l.getAttribute('href').match(/\/contractors\/(\d+)/);
          if (m && !l.getAttribute('href').includes('/edit') && !l.getAttribute('href').includes('/create')) return m[1];
        }
        return null;
      });
    } catch(e) {}
    if (ctId) {
      await ow.page.goto(`${BASE}/company/contractors/${ctId}`, { waitUntil: 'networkidle' });
      await snap(ow.page, 'owner_contractor_view.png');
    }

    // Drivers list (P1: no passport/SNILS + P5: no "Логист #1")
    await ow.page.goto(`${BASE}/company/drivers`, { waitUntil: 'networkidle' });
    await snap(ow.page, 'owner_drivers.png');

    // Driver view (P4: danger zone separated, SNILS in main)
    let drId = null;
    try {
      drId = await ow.page.evaluate(() => {
        const links = document.querySelectorAll('a[href*="/drivers/"]');
        for (const l of links) {
          const m = l.getAttribute('href').match(/\/drivers\/(\d+)/);
          if (m && !l.getAttribute('href').includes('/edit') && !l.getAttribute('href').includes('/create')) return m[1];
        }
        return null;
      });
    } catch(e) {}
    if (drId) {
      await ow.page.goto(`${BASE}/company/drivers/${drId}`, { waitUntil: 'networkidle' });
      await snap(ow.page, 'owner_driver_view.png');

      // Driver edit (P4 TASK-024: passport fields)
      await ow.page.goto(`${BASE}/company/drivers/${drId}/edit`, { waitUntil: 'networkidle' });
      await snap(ow.page, 'owner_driver_edit.png');
    }

    // Vehicles list
    await ow.page.goto(`${BASE}/company/vehicles`, { waitUntil: 'networkidle' });
    await snap(ow.page, 'owner_vehicles.png');

    // Vehicle view (P4: danger zone)
    let vehId = null;
    try {
      vehId = await ow.page.evaluate(() => {
        const links = document.querySelectorAll('a[href*="/vehicles/"]');
        for (const l of links) {
          const m = l.getAttribute('href').match(/\/vehicles\/(\d+)(?!.*\/(edit|create))/);
          if (m) return m[1];
        }
        return null;
      });
    } catch(e) {}
    if (vehId) {
      await ow.page.goto(`${BASE}/company/vehicles/${vehId}`, { waitUntil: 'networkidle' });
      await snap(ow.page, 'owner_vehicle_view.png');
    }

    // Vehicle sets list
    await ow.page.goto(`${BASE}/company/vehicle-sets`, { waitUntil: 'networkidle' });
    await snap(ow.page, 'owner_vehicle_sets.png');

    // Vehicle set view (P5 UX-014: heading + UX-038: no VIN)
    let vsId = null;
    try {
      vsId = await ow.page.evaluate(() => {
        const links = document.querySelectorAll('a[href*="/vehicle-sets/"]');
        for (const l of links) {
          const m = l.getAttribute('href').match(/\/vehicle-sets\/(\d+)/);
          if (m && !l.getAttribute('href').includes('/edit') && !l.getAttribute('href').includes('/create')) return m[1];
        }
        return null;
      });
    } catch(e) {}
    if (vsId) {
      await ow.page.goto(`${BASE}/company/vehicle-sets/${vsId}`, { waitUntil: 'networkidle' });
      await snap(ow.page, 'owner_vehicle_set_view.png');
    }

    // Vehicle sets create (P4 TASK-014: empty select fallback)
    await ow.page.goto(`${BASE}/company/vehicle-sets/create`, { waitUntil: 'networkidle' });
    await snap(ow.page, 'owner_vehicle_set_create.png');

    // DVB list (P5 UX-040: no quotes)
    await ow.page.goto(`${BASE}/company/driver-vehicle-blocks`, { waitUntil: 'networkidle' });
    await snap(ow.page, 'owner_dvb.png');

    // DVB view (P5 UX-016: heading + P4: danger zone)
    let dvbId = null;
    try {
      dvbId = await ow.page.evaluate(() => {
        const links = document.querySelectorAll('a[href*="/driver-vehicle-blocks/"]');
        for (const l of links) {
          const m = l.getAttribute('href').match(/\/driver-vehicle-blocks\/(\d+)/);
          if (m && !l.getAttribute('href').includes('/edit') && !l.getAttribute('href').includes('/create')) return m[1];
        }
        return null;
      });
    } catch(e) {}
    if (dvbId) {
      await ow.page.goto(`${BASE}/company/driver-vehicle-blocks/${dvbId}`, { waitUntil: 'networkidle' });
      await snap(ow.page, 'owner_dvb_view.png');
    }

    // DVB create (P4 TASK-014: empty select fallback)
    await ow.page.goto(`${BASE}/company/driver-vehicle-blocks/create`, { waitUntil: 'networkidle' });
    await snap(ow.page, 'owner_dvb_create.png');

    // Crews list
    await ow.page.goto(`${BASE}/company/crews`, { waitUntil: 'networkidle' });
    await snap(ow.page, 'owner_crews.png');

    // Crew view (P4: danger zone + P3: tech info VISIBLE for owner)
    let crewId = null;
    try {
      crewId = await ow.page.evaluate(() => {
        const links = document.querySelectorAll('a[href*="/crews/"]');
        for (const l of links) {
          const m = l.getAttribute('href').match(/\/crews\/(\d+)/);
          if (m && !l.getAttribute('href').includes('/edit') && !l.getAttribute('href').includes('/create')) return m[1];
        }
        return null;
      });
    } catch(e) {}
    if (crewId) {
      await ow.page.goto(`${BASE}/company/crews/${crewId}`, { waitUntil: 'networkidle' });
      await snap(ow.page, 'owner_crew_view.png');
    }

    // Crews create (P4 TASK-014: empty select fallback)
    await ow.page.goto(`${BASE}/company/crews/create`, { waitUntil: 'networkidle' });
    await snap(ow.page, 'owner_crew_create.png');

    await ow.ctx.close();
  }

  // --- LOGIST 1 (owner of records) ---
  console.log('\n--- LOGIST 1 ---');
  let l1 = await login(browser, 'logist_runtime_1', 'pass1111');
  if (l1) {
    // Dashboard (P6: my metrics)
    await l1.page.goto(`${BASE}/company/dashboard`, { waitUntil: 'networkidle' });
    await snap(l1.page, 'logist1_dashboard.png');

    // Crew view (P3: tech info HIDDEN)
    let crewId = null;
    try {
      await l1.page.goto(`${BASE}/company/crews`, { waitUntil: 'networkidle' });
      crewId = await l1.page.evaluate(() => {
        const links = document.querySelectorAll('a[href*="/crews/"]');
        for (const l of links) {
          const m = l.getAttribute('href').match(/\/crews\/(\d+)/);
          if (m && !l.getAttribute('href').includes('/edit') && !l.getAttribute('href').includes('/create')) return m[1];
        }
        return null;
      });
    } catch(e) {}
    if (crewId) {
      await l1.page.goto(`${BASE}/company/crews/${crewId}`, { waitUntil: 'networkidle' });
      await snap(l1.page, 'logist1_crew_view.png');
    }

    // Driver view (P4: danger zone but tech info HIDDEN)
    let drId = null;
    try {
      await l1.page.goto(`${BASE}/company/drivers`, { waitUntil: 'networkidle' });
      drId = await l1.page.evaluate(() => {
        const links = document.querySelectorAll('a[href*="/drivers/"]');
        for (const l of links) {
          const m = l.getAttribute('href').match(/\/drivers\/(\d+)/);
          if (m && !l.getAttribute('href').includes('/edit') && !l.getAttribute('href').includes('/create')) return m[1];
        }
        return null;
      });
    } catch(e) {}
    if (drId) {
      await l1.page.goto(`${BASE}/company/drivers/${drId}`, { waitUntil: 'networkidle' });
      await snap(l1.page, 'logist1_driver_view.png');

      // Driver edit (P4 TASK-024: passport fields)
      await l1.page.goto(`${BASE}/company/drivers/${drId}/edit`, { waitUntil: 'networkidle' });
      await snap(l1.page, 'logist1_driver_edit.png');
    }

    await l1.ctx.close();
  }

  // --- LOGIST 2 (grant access, no own records) ---
  console.log('\n--- LOGIST 2 ---');
  let l2 = await login(browser, 'logist_runtime_2', 'pass2222');
  if (l2) {
    // Dashboard (P6: metrics or no-access message)
    await l2.page.goto(`${BASE}/company/dashboard`, { waitUntil: 'networkidle' });
    await snap(l2.page, 'logist2_dashboard.png');

    // Contractors (P3 TASK-020: "Нет доступа", create button HIDDEN)
    await l2.page.goto(`${BASE}/company/contractors`, { waitUntil: 'networkidle' });
    await snap(l2.page, 'logist2_contractors_empty.png');

    // Drivers (P3 TASK-020: "Нет доступа")
    await l2.page.goto(`${BASE}/company/drivers`, { waitUntil: 'networkidle' });
    await snap(l2.page, 'logist2_drivers_empty.png');

    // Vehicles (P3 TASK-020: "Нет доступа")
    await l2.page.goto(`${BASE}/company/vehicles`, { waitUntil: 'networkidle' });
    await snap(l2.page, 'logist2_vehicles_empty.png');

    // Vehicle sets (P3 TASK-020: "Нет доступа")
    await l2.page.goto(`${BASE}/company/vehicle-sets`, { waitUntil: 'networkidle' });
    await snap(l2.page, 'logist2_vehicle_sets_empty.png');

    // DVB (P3 TASK-020: "Нет доступа" + UX-040: no quotes)
    await l2.page.goto(`${BASE}/company/driver-vehicle-blocks`, { waitUntil: 'networkidle' });
    await snap(l2.page, 'logist2_dvb_empty.png');

    // Crews (P3 TASK-021: archive or no access message)
    await l2.page.goto(`${BASE}/company/crews`, { waitUntil: 'networkidle' });
    await snap(l2.page, 'logist2_crews_empty.png');

    // Try to access crew 1 (P3 TASK-025: 403 page)
    await l2.page.goto(`${BASE}/company/crews/1`, { waitUntil: 'networkidle' });
    await snap(l2.page, 'logist2_crew_access.png');

    await l2.ctx.close();
  }

  await browser.close();
  console.log(`\n=== DONE: ${shot} screenshots saved to ${OUT} ===`);
}

main().catch(e => { console.error(e); process.exit(1); });
