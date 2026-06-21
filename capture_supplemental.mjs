import { chromium } from 'playwright';
import { mkdirSync } from 'fs';
import { join } from 'path';

const BASE = 'http://127.0.0.1:8016';
const OUT = 'docs/design-audit/final-ui-review-after-fixes/screenshots';
let shot = 38; // continue numbering

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
  if (page.url().includes('/login')) {
    console.log(`  LOGIN FAILED: ${loginVal}`);
    await ctx.close();
    return null;
  }
  console.log(`  LOGIN OK: ${loginVal}`);
  return { ctx, page };
}

async function main() {
  console.log('=== ERP PLANEX — Supplemental Screenshots (P2 gaps + edge cases) ===\n');
  const browser = await chromium.launch({ headless: true });

  // --- SUPERADMIN: Package 2 pages ---
  console.log('\n--- SUPERADMIN (P2 gaps) ---');
  let sa = await login(browser, 'admin@planex.local', 'test123');
  if (sa) {
    // SA vehicles (P2 TASK-018: "Транспортные единицы" heading)
    await sa.page.goto(`${BASE}/superadmin/companies/9/vehicles`, { waitUntil: 'networkidle' });
    await snap(sa.page, 'superadmin_company_vehicles.png');

    // SA documents (P2 TASK-019: translated entity_type, uploaded_by_name)
    await sa.page.goto(`${BASE}/superadmin/companies/9/documents`, { waitUntil: 'networkidle' });
    await snap(sa.page, 'superadmin_company_documents.png');

    // SA access grants (P2 TASK-013: translated entity_type + access_level)
    await sa.page.goto(`${BASE}/superadmin/companies/9/access-grants`, { waitUntil: 'networkidle' });
    await snap(sa.page, 'superadmin_company_access_grants.png');

    // SA create owner form (P4 TASK-017: password masking on owner create)
    await sa.page.goto(`${BASE}/superadmin/companies/9/create-owner`, { waitUntil: 'networkidle' });
    await snap(sa.page, 'superadmin_create_owner.png');

    // SA directories overview
    await sa.page.goto(`${BASE}/superadmin/companies/9/directories`, { waitUntil: 'networkidle' });
    await snap(sa.page, 'superadmin_company_directories.png');

    // SA edit company
    await sa.page.goto(`${BASE}/superadmin/companies/9/edit`, { waitUntil: 'networkidle' });
    await snap(sa.page, 'superadmin_company_edit.png');

    await sa.ctx.close();
  }

  // --- OWNER: missing pages ---
  console.log('\n--- OWNER (missing pages) ---');
  let ow = await login(browser, 'owner_test_runtime', 'test123');
  if (ow) {
    // Contractor edit (P5: contractor form)
    let ctId = null;
    try {
      await ow.page.goto(`${BASE}/company/contractors`, { waitUntil: 'networkidle' });
      ctId = await ow.page.evaluate(() => {
        const links = document.querySelectorAll('a[href*="/contractors/"]');
        for (const l of links) {
          const m = l.getAttribute('href').match(/\/contractors\/(\d+)(?![^/]*\/)/);
          if (m && !l.getAttribute('href').includes('/edit') && !l.getAttribute('href').includes('/create')) return m[1];
        }
        return null;
      });
    } catch(e) {}
    if (ctId) {
      await ow.page.goto(`${BASE}/company/contractors/${ctId}/edit`, { waitUntil: 'networkidle' });
      await snap(ow.page, 'owner_contractor_edit.png');
    }

    // Vehicle edit (P4: danger zone visible)
    let vehId = null;
    try {
      await ow.page.goto(`${BASE}/company/vehicles`, { waitUntil: 'networkidle' });
      vehId = await ow.page.evaluate(() => {
        const links = document.querySelectorAll('a[href*="/vehicles/"]');
        for (const l of links) {
          const m = l.getAttribute('href').match(/\/vehicles\/(\d+)(?![^/]*\/)/);
          if (m && !l.href.includes('/edit') && !l.href.includes('/create')) return m[1];
        }
        return null;
      });
    } catch(e) {}
    if (vehId) {
      await ow.page.goto(`${BASE}/company/vehicles/${vehId}/edit`, { waitUntil: 'networkidle' });
      await snap(ow.page, 'owner_vehicle_edit.png');
    }

    // Vehicle set edit (P5: heading)
    let vsId = null;
    try {
      await ow.page.goto(`${BASE}/company/vehicle-sets`, { waitUntil: 'networkidle' });
      vsId = await ow.page.evaluate(() => {
        const links = document.querySelectorAll('a[href*="/vehicle-sets/"]');
        for (const l of links) {
          const m = l.getAttribute('href').match(/\/vehicle-sets\/(\d+)(?![^/]*\/)/);
          if (m && !l.href.includes('/edit') && !l.href.includes('/create')) return m[1];
        }
        return null;
      });
    } catch(e) {}
    if (vsId) {
      await ow.page.goto(`${BASE}/company/vehicle-sets/${vsId}/edit`, { waitUntil: 'networkidle' });
      await snap(ow.page, 'owner_vehicle_set_edit.png');
    }

    // DVB edit
    let dvbId = null;
    try {
      await ow.page.goto(`${BASE}/company/driver-vehicle-blocks`, { waitUntil: 'networkidle' });
      dvbId = await ow.page.evaluate(() => {
        const links = document.querySelectorAll('a[href*="/driver-vehicle-blocks/"]');
        for (const l of links) {
          const m = l.getAttribute('href').match(/\/driver-vehicle-blocks\/(\d+)(?![^/]*\/)/);
          if (m && !l.href.includes('/edit') && !l.href.includes('/create')) return m[1];
        }
        return null;
      });
    } catch(e) {}
    if (dvbId) {
      await ow.page.goto(`${BASE}/company/driver-vehicle-blocks/${dvbId}/edit`, { waitUntil: 'networkidle' });
      await snap(ow.page, 'owner_dvb_edit.png');
    }

    // Crew edit
    let crewId = null;
    try {
      await ow.page.goto(`${BASE}/company/crews`, { waitUntil: 'networkidle' });
      crewId = await ow.page.evaluate(() => {
        const links = document.querySelectorAll('a[href*="/crews/"]');
        for (const l of links) {
          const m = l.getAttribute('href').match(/\/crews\/(\d+)(?![^/]*\/)/);
          if (m && !l.href.includes('/edit') && !l.href.includes('/create')) return m[1];
        }
        return null;
      });
    } catch(e) {}
    if (crewId) {
      await ow.page.goto(`${BASE}/company/crews/${crewId}/edit`, { waitUntil: 'networkidle' });
      await snap(ow.page, 'owner_crew_edit.png');
    }

    // Logist view (P5 UX-019: @login, no role column)
    let logistId = null;
    try {
      await ow.page.goto(`${BASE}/company/logists`, { waitUntil: 'networkidle' });
      logistId = await ow.page.evaluate(() => {
        const links = document.querySelectorAll('a[href*="/logists/"]');
        for (const l of links) {
          const m = l.getAttribute('href').match(/\/logists\/(\d+)/);
          if (m) return m[1];
        }
        return null;
      });
    } catch(e) {}
    if (logistId) {
      await ow.page.goto(`${BASE}/company/logists/${logistId}`, { waitUntil: 'networkidle' });
      await snap(ow.page, 'owner_logist_view.png');
    }

    await ow.ctx.close();
  }

  // --- LOGIST 1: vehicle set view (no VIN) ---
  console.log('\n--- LOGIST 1 (supplemental) ---');
  let l1 = await login(browser, 'logist_runtime_1', 'pass1111');
  if (l1) {
    // Vehicle set view (P5 UX-038: no VIN, UX-014: heading)
    let vsId = null;
    try {
      await l1.page.goto(`${BASE}/company/vehicle-sets`, { waitUntil: 'networkidle' });
      vsId = await l1.page.evaluate(() => {
        const links = document.querySelectorAll('a[href*="/vehicle-sets/"]');
        for (const l of links) {
          const m = l.getAttribute('href').match(/\/vehicle-sets\/(\d+)/);
          if (m && !l.href.includes('/edit') && !l.href.includes('/create')) return m[1];
        }
        return null;
      });
    } catch(e) {}
    if (vsId) {
      await l1.page.goto(`${BASE}/company/vehicle-sets/${vsId}`, { waitUntil: 'networkidle' });
      await snap(l1.page, 'logist1_vehicle_set_view.png');
    }

    // DVB view (P5 UX-016: heading)
    let dvbId = null;
    try {
      await l1.page.goto(`${BASE}/company/driver-vehicle-blocks`, { waitUntil: 'networkidle' });
      dvbId = await l1.page.evaluate(() => {
        const links = document.querySelectorAll('a[href*="/driver-vehicle-blocks/"]');
        for (const l of links) {
          const m = l.getAttribute('href').match(/\/driver-vehicle-blocks\/(\d+)/);
          if (m && !l.href.includes('/edit') && !l.href.includes('/create')) return m[1];
        }
        return null;
      });
    } catch(e) {}
    if (dvbId) {
      await l1.page.goto(`${BASE}/company/driver-vehicle-blocks/${dvbId}`, { waitUntil: 'networkidle' });
      await snap(l1.page, 'logist1_dvb_view.png');
    }

    await l1.ctx.close();
  }

  await browser.close();
  console.log(`\n=== DONE: ${shot - 38} supplemental screenshots (total: ${shot}) ===`);
}

main().catch(e => { console.error(e); process.exit(1); });
