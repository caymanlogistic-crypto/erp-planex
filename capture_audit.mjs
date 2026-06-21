import { chromium } from 'playwright';
import { mkdirSync, writeFileSync } from 'fs';
import { join } from 'path';

const BASE = 'http://127.0.0.1:8016';
const SCREENSHOT_DIR = 'docs/design-audit/full-ui-revision/screenshots';
const MANIFEST_PATH = 'docs/design-audit/full-ui-revision/SCREENSHOT_MANIFEST.md';

const ROLES = {
  superadmin: { login: 'admin@planex.local', password: 'admin123', role: 'superadmin' },
  owner: { login: 'owner_test_runtime', password: 'pass1234', role: 'company_owner' },
  logist1: { login: 'logist_runtime_1', password: 'pass1111', role: 'logist' },
  logist2: { login: 'logist_runtime_2', password: 'pass2222', role: 'logist' },
};

const COMPANY_ID = 9;

// Runtime entity IDs - will be discovered
const runtimeIds = {
  contractor: null,
  driver: null,
  tractor: null,
  trailer: null,
  coupling: null,
  dvb: null,
  crew: null,
};

const manifest = [];

function addToManifest(entry) {
  manifest.push(entry);
}

async function saveManifest() {
  let md = '# SCREENSHOT MANIFEST вЂ” FULL UI REVISION\n\n';
  md += `Generated: ${new Date().toISOString()}\n\n`;
  md += '## Summary\n\n';
  
  let currentRole = '';
  for (const entry of manifest) {
    if (entry.role !== currentRole) {
      currentRole = entry.role;
      md += `\n### ${currentRole.toUpperCase()}\n\n`;
    }
    md += `#### ${entry.filename}\n\n`;
    md += `- **URL**: \`${entry.url}\`\n`;
    md += `- **Role**: ${entry.role}\n`;
    md += `- **Login**: ${entry.login}\n`;
    md += `- **Menu**: ${entry.menu}\n`;
    md += `- **Page**: ${entry.page}\n`;
    md += `- **Function**: ${entry.function}\n`;
    md += `- **State**: ${entry.state}\n`;
    md += `- **Dimensions**: ${entry.width}x${entry.height}\n`;
    md += `- **Valid**: ${entry.valid ? 'YES' : 'NO вЂ” ' + (entry.issues || 'unknown')}\n`;
    if (entry.comment) md += `- **Comment**: ${entry.comment}\n`;
    md += '\n';
  }

  writeFileSync(MANIFEST_PATH, md, 'utf8');
  console.log(`Manifest saved: ${manifest.length} entries`);
}

async function login(page, credentials) {
  await page.goto(`${BASE}/login`, { waitUntil: 'networkidle' });
  await page.fill('input[name="login"]', credentials.login);
  await page.fill('input[name="password"]', credentials.password);
  await page.click('button[type="submit"]');
  await page.waitForURL('**/company**', { timeout: 10000 }).catch(() => {});
  await page.waitForTimeout(1000);
  
  // Verify login
  const currentUrl = page.url();
  if (currentUrl.includes('/login')) {
    // Check for error message
    const errorEl = await page.$('.form-alert, .alert-error, .field-msg');
    if (errorEl) {
      const errorText = await errorEl.textContent();
      throw new Error(`Login failed for ${credentials.login}: ${errorText}`);
    }
    throw new Error(`Login failed for ${credentials.login} вЂ” still on login page`);
  }
  console.log(`Logged in as ${credentials.login} (${credentials.role})`);
}

async function capturePage(page, url, filename, meta) {
  const fullUrl = url.startsWith('http') ? url : `${BASE}${url}`;
  console.log(`  Capturing: ${fullUrl} -> ${filename}`);
  
  let response;
  try {
    response = await page.goto(fullUrl, { waitUntil: 'networkidle', timeout: 30000 });
  } catch (e) {
    console.log(`  ERROR navigating to ${fullUrl}: ${e.message}`);
    addToManifest({
      ...meta,
      filename,
      url,
      width: 0, height: 0,
      valid: false,
      issues: `Navigation error: ${e.message}`,
    });
    return null;
  }

  const status = response ? response.status() : 0;
  if (status >= 400) {
    console.log(`  HTTP ${status} for ${fullUrl}`);
    addToManifest({
      ...meta,
      filename,
      url,
      width: 0, height: 0,
      valid: false,
      issues: `HTTP ${status}`,
    });
    return null;
  }

  await page.waitForTimeout(500);

  const filepath = join(SCREENSHOT_DIR, filename);
  await page.screenshot({ path: filepath, fullPage: true });

  // Get image dimensions using Playwright's screenshot info
  const img = await page.evaluate(async (fname) => {
    return new Promise((resolve) => {
      const img = new Image();
      img.onload = () => resolve({ width: img.naturalWidth, height: img.naturalHeight });
      img.onerror = () => resolve({ width: 0, height: 0 });
      img.src = fname + '?t=' + Date.now();
    });
  }, filename);
  
  // Alternative: use page viewport to estimate
  const pageHeight = await page.evaluate(() => document.body.scrollHeight);
  const pageWidth = await page.evaluate(() => document.body.scrollWidth);
  
  const width = img.width || pageWidth || 1920;
  const height = img.height || pageHeight || 1080;

  console.log(`    Saved: ${width}x${height}`);

  const valid = width >= 1920 && height >= 1080;
  const issues = !valid 
    ? `Invalid dimensions: ${width}x${height} (need 1920x1080+)` 
    : null;

  addToManifest({
    ...meta,
    filename,
    url,
    width,
    height,
    valid,
    issues,
  });

  return { width, height, valid };
}

async function discoverRuntimeIds(page) {
  // Discover runtime entity IDs from the owner's lists
  console.log('\n=== DISCOVERING RUNTIME IDs ===');
  
  // Contractors
  await page.goto(`${BASE}/company/contractors`, { waitUntil: 'networkidle' });
  await page.waitForTimeout(500);
  const contractorId = await page.evaluate(() => {
    const rows = document.querySelectorAll('.table tbody tr, table tbody tr');
    for (const row of rows) {
      const text = row.textContent || '';
      if (text.includes('РџРѕРґСЂСЏРґС‡РёРє Runtime') || text.includes('Runtime')) {
        const link = row.querySelector('a[href*="/contractors/"]');
        if (link) {
          const href = link.getAttribute('href');
          const match = href.match(/\/contractors\/(\d+)/);
          return match ? match[1] : null;
        }
        // Try to find any link
        const anyLink = row.querySelector('a');
        if (anyLink) {
          const href = anyLink.getAttribute('href');
          const match = href.match(/(\d+)/);
          return match ? match[1] : null;
        }
      }
    }
    return null;
  });
  runtimeIds.contractor = contractorId;
  console.log(`  Contractor: ${contractorId}`);

  // Drivers
  await page.goto(`${BASE}/company/drivers`, { waitUntil: 'networkidle' });
  await page.waitForTimeout(500);
  const driverId = await page.evaluate(() => {
    const rows = document.querySelectorAll('.table tbody tr, table tbody tr');
    for (const row of rows) {
      const text = row.textContent || '';
      if (text.includes('РџРµС‚СЂРѕРІ') || text.includes('Runtime')) {
        const link = row.querySelector('a');
        if (link) {
          const href = link.getAttribute('href');
          const match = href.match(/(\d+)/);
          return match ? match[1] : null;
        }
      }
    }
    return null;
  });
  runtimeIds.driver = driverId;
  console.log(`  Driver: ${driverId}`);

  // Vehicles
  await page.goto(`${BASE}/company/vehicles`, { waitUntil: 'networkidle' });
  await page.waitForTimeout(500);
  const vehicleIds = await page.evaluate(() => {
    const rows = document.querySelectorAll('.table tbody tr, table tbody tr');
    const ids = [];
    for (const row of rows) {
      const text = row.textContent || '';
      if (text.includes('Рђ111РђРђ') || text.includes('Runtime')) {
        const link = row.querySelector('a');
        if (link) {
          const href = link.getAttribute('href');
          const match = href.match(/(\d+)/);
          if (match) ids.push(match[1]);
        }
      }
    }
    return ids;
  });
  runtimeIds.tractor = vehicleIds[0] || null;
  runtimeIds.trailer = vehicleIds[1] || null;
  console.log(`  Tractor: ${runtimeIds.tractor}, Trailer: ${runtimeIds.trailer}`);

  // Vehicle Sets
  await page.goto(`${BASE}/company/vehicle-sets`, { waitUntil: 'networkidle' });
  await page.waitForTimeout(500);
  const couplingId = await page.evaluate(() => {
    const rows = document.querySelectorAll('.table tbody tr, table tbody tr');
    for (const row of rows) {
      const text = row.textContent || '';
      if (text.includes('Runtime') || text.includes('Рђ111РђРђ')) {
        const link = row.querySelector('a');
        if (link) {
          const href = link.getAttribute('href');
          const match = href.match(/(\d+)/);
          return match ? match[1] : null;
        }
      }
    }
    return null;
  });
  runtimeIds.coupling = couplingId;
  console.log(`  Coupling: ${couplingId}`);

  // DVB
  await page.goto(`${BASE}/company/driver-vehicle-blocks`, { waitUntil: 'networkidle' });
  await page.waitForTimeout(500);
  const dvbId = await page.evaluate(() => {
    const rows = document.querySelectorAll('.table tbody tr, table tbody tr');
    for (const row of rows) {
      const text = row.textContent || '';
      if (text.includes('Runtime') || text.includes('РџРµС‚СЂРѕРІ')) {
        const link = row.querySelector('a');
        if (link) {
          const href = link.getAttribute('href');
          const match = href.match(/(\d+)/);
          return match ? match[1] : null;
        }
      }
    }
    return null;
  });
  runtimeIds.dvb = dvbId;
  console.log(`  DVB: ${dvbId}`);

  // Crews
  await page.goto(`${BASE}/company/crews`, { waitUntil: 'networkidle' });
  await page.waitForTimeout(500);
  const crewId = await page.evaluate(() => {
    const rows = document.querySelectorAll('.table tbody tr, table tbody tr');
    for (const row of rows) {
      const text = row.textContent || '';
      if (text.includes('Runtime') || text.includes('РџРµС‚СЂРѕРІ')) {
        const link = row.querySelector('a');
        if (link) {
          const href = link.getAttribute('href');
          const match = href.match(/(\d+)/);
          return match ? match[1] : null;
        }
      }
    }
    return null;
  });
  runtimeIds.crew = crewId;
  console.log(`  Crew: ${crewId}`);

  return runtimeIds;
}

async function captureSuperadmin(browser) {
  console.log('\n=== SUPERADMIN SCREENSHOTS ===');
  const context = await browser.newContext({ viewport: { width: 1920, height: 1080 } });
  const page = await context.newPage();
  
  await login(page, ROLES.superadmin);
  const credLogin = ROLES.superadmin.login;
  const role = 'superadmin';
  const cid = COMPANY_ID;

  const pages = [
    { url: '/superadmin', menu: 'РЎРРЎРўР•РњРђ', page: 'SUPERADMIN Dashboard', func: 'Р¦РµРЅС‚СЂР°Р»СЊРЅР°СЏ РїР°РЅРµР»СЊ СѓРїСЂР°РІР»РµРЅРёСЏ', state: 'filled', filename: 'superadmin__dashboard__dashboard__filled.png' },
    { url: '/superadmin/companies', menu: 'РЎРРЎРўР•РњРђ / РљРѕРјРїР°РЅРёРё', page: 'Р РµРµСЃС‚СЂ РєРѕРјРїР°РЅРёР№', func: 'РЎРїРёСЃРѕРє РІСЃРµС… РєРѕРјРїР°РЅРёР№ РІ СЃРёСЃС‚РµРјРµ', state: 'filled', filename: 'superadmin__companies__list__filled.png' },
    { url: '/superadmin/companies/create', menu: 'РЎРРЎРўР•РњРђ / РљРѕРјРїР°РЅРёРё', page: 'РЎРѕР·РґР°С‚СЊ СЌРєСЃРїРµРґРёС‚РѕСЂР°', func: 'Р¤РѕСЂРјР° СЃРѕР·РґР°РЅРёСЏ РЅРѕРІРѕР№ РєРѕРјРїР°РЅРёРё', state: 'empty_form', filename: 'superadmin__companies__create__form.png' },
    { url: `/superadmin/companies/${cid}`, menu: 'РЎРРЎРўР•РњРђ / РљРѕРјРїР°РЅРёРё', page: 'РљР°СЂС‚РѕС‡РєР° РєРѕРјРїР°РЅРёРё', func: 'Р”РµС‚Р°Р»СЊРЅР°СЏ РёРЅС„РѕСЂРјР°С†РёСЏ Рѕ РєРѕРјРїР°РЅРёРё РћРћРћ "РўРµСЃС‚ Р­С‚Р°Рї 2 Runtime"', state: 'filled', filename: 'superadmin__company__view__runtime_company.png' },
    { url: `/superadmin/companies/${cid}/edit`, menu: 'РЎРРЎРўР•РњРђ / РљРѕРјРїР°РЅРёРё', page: 'Р РµРґР°РєС‚РёСЂРѕРІР°С‚СЊ РєРѕРјРїР°РЅРёСЋ', func: 'Р¤РѕСЂРјР° СЂРµРґР°РєС‚РёСЂРѕРІР°РЅРёСЏ СЂРµРєРІРёР·РёС‚РѕРІ РєРѕРјРїР°РЅРёРё', state: 'filled_form', filename: 'superadmin__company__edit__form.png' },
    { url: `/superadmin/companies/${cid}/users`, menu: 'РЎРРЎРўР•РњРђ / РљРѕРјРїР°РЅРёРё', page: 'РџРѕР»СЊР·РѕРІР°С‚РµР»Рё РєРѕРјРїР°РЅРёРё', func: 'РЎРїРёСЃРѕРє РїРѕР»СЊР·РѕРІР°С‚РµР»РµР№ РєРѕРјРїР°РЅРёРё (СЂСѓРєРѕРІРѕРґРёС‚РµР»СЊ + Р»РѕРіРёСЃС‚С‹)', state: 'filled', filename: 'superadmin__company__users__list.png' },
    { url: `/superadmin/companies/${cid}/directories`, menu: 'РЎРРЎРўР•РњРђ / РљРѕРјРїР°РЅРёРё', page: 'РЎРїСЂР°РІРѕС‡РЅРёРєРё РєРѕРјРїР°РЅРёРё', func: 'РћР±Р·РѕСЂ СЃРїСЂР°РІРѕС‡РЅРёРєРѕРІ РєРѕРјРїР°РЅРёРё СЃРѕ СЃС‚Р°С‚РёСЃС‚РёРєРѕР№', state: 'filled', filename: 'superadmin__company__directories__stats.png' },
    { url: `/superadmin/companies/${cid}/documents`, menu: 'РЎРРЎРўР•РњРђ / РљРѕРјРїР°РЅРёРё', page: 'Р”РѕРєСѓРјРµРЅС‚С‹ РєРѕРјРїР°РЅРёРё', func: 'РЎРїРёСЃРѕРє РІСЃРµС… РґРѕРєСѓРјРµРЅС‚РѕРІ РєРѕРјРїР°РЅРёРё', state: 'filled', filename: 'superadmin__company__documents__list.png' },
    { url: `/superadmin/companies/${cid}/access-grants`, menu: 'РЎРРЎРўР•РњРђ / РљРѕРјРїР°РЅРёРё', page: 'Р”РѕСЃС‚СѓРїС‹ РєРѕРјРїР°РЅРёРё', func: 'РЎРїРёСЃРѕРє РІСЃРµС… РіСЂР°РЅС‚РѕРІ РґРѕСЃС‚СѓРїР°', state: 'filled', filename: 'superadmin__company__access_grants__list.png' },
    { url: `/superadmin/companies/${cid}/delete`, menu: 'РЎРРЎРўР•РњРђ / РљРѕРјРїР°РЅРёРё', page: 'РЈРґР°Р»РµРЅРёРµ РєРѕРјРїР°РЅРёРё', func: 'РЎС‚СЂР°РЅРёС†Р° РїРѕРґС‚РІРµСЂР¶РґРµРЅРёСЏ СѓРґР°Р»РµРЅРёСЏ РєРѕРјРїР°РЅРёРё', state: 'danger_confirm', filename: 'superadmin__company__delete__confirm.png' },
    { url: `/superadmin/companies/${cid}/owner`, menu: 'РЎРРЎРўР•РњРђ / РљРѕРјРїР°РЅРёРё', page: 'Р СѓРєРѕРІРѕРґРёС‚РµР»СЊ РєРѕРјРїР°РЅРёРё', func: 'РљР°СЂС‚РѕС‡РєР° СЂСѓРєРѕРІРѕРґРёС‚РµР»СЏ РєРѕРјРїР°РЅРёРё', state: 'filled', filename: 'superadmin__company__owner__view.png' },
    { url: `/superadmin/companies/${cid}/create-owner`, menu: 'РЎРРЎРўР•РњРђ / РљРѕРјРїР°РЅРёРё', page: 'РЎРѕР·РґР°С‚СЊ Р СѓРєРѕРІРѕРґРёС‚РµР»СЏ', func: 'Р¤РѕСЂРјР° СЃРѕР·РґР°РЅРёСЏ СЂСѓРєРѕРІРѕРґРёС‚РµР»СЏ РєРѕРјРїР°РЅРёРё (РµСЃР»Рё РЅРµ СЃРѕР·РґР°РЅ)', state: 'filled_or_exists', filename: 'superadmin__company__create_owner__form.png' },
    // SUPERADMIN СЃРїСЂР°РІРѕС‡РЅРёРєРё
    { url: `/superadmin/companies/${cid}/clients`, menu: 'РЎРРЎРўР•РњРђ / РљРѕРјРїР°РЅРёРё', page: 'РљР»РёРµРЅС‚С‹ РєРѕРјРїР°РЅРёРё', func: 'РЎРїРёСЃРѕРє РєР»РёРµРЅС‚РѕРІ РІ РєРѕРјРїР°РЅРёРё', state: 'filled', filename: 'superadmin__company__clients__list.png' },
    { url: `/superadmin/companies/${cid}/contractors`, menu: 'РЎРРЎРўР•РњРђ / РљРѕРјРїР°РЅРёРё', page: 'РџРѕРґСЂСЏРґС‡РёРєРё РєРѕРјРїР°РЅРёРё', func: 'РЎРїРёСЃРѕРє РїРѕРґСЂСЏРґС‡РёРєРѕРІ РІ РєРѕРјРїР°РЅРёРё', state: 'filled', filename: 'superadmin__company__contractors__list.png' },
    { url: `/superadmin/companies/${cid}/drivers`, menu: 'РЎРРЎРўР•РњРђ / РљРѕРјРїР°РЅРёРё', page: 'Р’РѕРґРёС‚РµР»Рё РєРѕРјРїР°РЅРёРё', func: 'РЎРїРёСЃРѕРє РІРѕРґРёС‚РµР»РµР№ РІ РєРѕРјРїР°РЅРёРё', state: 'filled', filename: 'superadmin__company__drivers__list.png' },
    { url: `/superadmin/companies/${cid}/vehicles`, menu: 'РЎРРЎРўР•РњРђ / РљРѕРјРїР°РЅРёРё', page: 'РўСЂР°РЅСЃРїРѕСЂС‚РЅС‹Рµ РµРґРёРЅРёС†С‹ РєРѕРјРїР°РЅРёРё', func: 'РЎРїРёСЃРѕРє С‚СЂР°РЅСЃРїРѕСЂС‚РЅС‹С… РµРґРёРЅРёС†', state: 'filled', filename: 'superadmin__company__vehicles__list.png' },
    { url: `/superadmin/companies/${cid}/crews`, menu: 'РЎРРЎРўР•РњРђ / РљРѕРјРїР°РЅРёРё', page: 'Р­РєРёРїР°Р¶Рё РєРѕРјРїР°РЅРёРё', func: 'РЎРїРёСЃРѕРє СЌРєРёРїР°Р¶РµР№', state: 'filled', filename: 'superadmin__company__crews__list.png' },
  ];

  for (const p of pages) {
    await capturePage(page, p.url, p.filename, {
      role, login: credLogin, menu: p.menu, page: p.page, function: p.func, state: p.state, comment: null,
    });
  }

  // Capture user create form
  await capturePage(page, `/superadmin/companies/${cid}/users/logists/create`, 'superadmin__company__logist_create__form.png', {
    role, login: credLogin, menu: 'РЎРРЎРўР•РњРђ / РљРѕРјРїР°РЅРёРё', page: 'РЎРѕР·РґР°С‚СЊ РїРѕР»СЊР·РѕРІР°С‚РµР»СЏ', function: 'Р¤РѕСЂРјР° СЃРѕР·РґР°РЅРёСЏ РїРѕР»СЊР·РѕРІР°С‚РµР»СЏ-Р»РѕРіРёСЃС‚Р°', state: 'empty_form', comment: 'SUPERADMIN СЃРѕР·РґР°РµС‚ РїРѕР»СЊР·РѕРІР°С‚РµР»СЏ РєРѕРјРїР°РЅРёРё',
  });

  await context.close();
}

async function captureOwner(browser, ids) {
  console.log('\n=== COMPANY OWNER SCREENSHOTS ===');
  const context = await browser.newContext({ viewport: { width: 1920, height: 1080 } });
  const page = await context.newPage();
  
  await login(page, ROLES.owner);
  const credLogin = ROLES.owner.login;
  const role = 'company_owner';
  const cid = COMPANY_ID;
  const ct = ids.contractor;
  const dr = ids.driver;
  const tr = ids.tractor;
  const tl = ids.trailer;
  const cp = ids.coupling;
  const dvb = ids.dvb;
  const cr = ids.crew;

  const pages = [
    // Dashboard & Logists
    { url: '/company/dashboard', menu: 'РћРџР•Р РђР¦РР', page: 'Dashboard РєРѕРјРїР°РЅРёРё', func: 'Р“Р»Р°РІРЅР°СЏ СЃС‚СЂР°РЅРёС†Р° РєРѕРјРїР°РЅРёРё', state: 'filled', filename: 'owner__dashboard__dashboard__filled.png' },
    { url: '/company/logists', menu: 'РЎРРЎРўР•РњРђ / РџРѕР»СЊР·РѕРІР°С‚РµР»Рё', page: 'РџРѕР»СЊР·РѕРІР°С‚РµР»Рё', func: 'РЎРїРёСЃРѕРє РїРѕР»СЊР·РѕРІР°С‚РµР»РµР№ РєРѕРјРїР°РЅРёРё', state: 'filled', filename: 'owner__logists__list__filled.png' },
    { url: '/company/logists/create', menu: 'РЎРРЎРўР•РњРђ / РџРѕР»СЊР·РѕРІР°С‚РµР»Рё', page: 'РЎРѕР·РґР°С‚СЊ РїРѕР»СЊР·РѕРІР°С‚РµР»СЏ', func: 'Р¤РѕСЂРјР° СЃРѕР·РґР°РЅРёСЏ РїРѕР»СЊР·РѕРІР°С‚РµР»СЏ-Р»РѕРіРёСЃС‚Р°', state: 'empty_form', filename: 'owner__logists__create__form.png' },
    
    // Contractors
    { url: '/company/contractors', menu: 'РћРџР•Р РђР¦РР / РџРѕРґСЂСЏРґС‡РёРєРё', page: 'РџРѕРґСЂСЏРґС‡РёРєРё', func: 'РЎРїРёСЃРѕРє РїРѕРґСЂСЏРґС‡РёРєРѕРІ РєРѕРјРїР°РЅРёРё', state: 'filled', filename: 'owner__contractors__list__filled.png' },
    { url: '/company/contractors/create', menu: 'РћРџР•Р РђР¦РР / РџРѕРґСЂСЏРґС‡РёРєРё', page: 'РЎРѕР·РґР°С‚СЊ РїРѕРґСЂСЏРґС‡РёРєР°', func: 'Р¤РѕСЂРјР° СЃРѕР·РґР°РЅРёСЏ РїРѕРґСЂСЏРґС‡РёРєР°', state: 'empty_form', filename: 'owner__contractors__create__form.png' },
  ];

  // Add entity-specific pages if IDs exist
  if (ct) {
    pages.push({ url: `/company/contractors/${ct}`, menu: 'РћРџР•Р РђР¦РР / РџРѕРґСЂСЏРґС‡РёРєРё', page: 'РљР°СЂС‚РѕС‡РєР° РїРѕРґСЂСЏРґС‡РёРєР°', func: 'Р”РµС‚Р°Р»СЊРЅР°СЏ РєР°СЂС‚РѕС‡РєР° РїРѕРґСЂСЏРґС‡РёРєР° СЃ РєРѕРЅС‚Р°РєС‚Р°РјРё, РЅР°Р»РѕРіРѕРІРѕР№ РёСЃС‚РѕСЂРёРµР№, РґРѕРєСѓРјРµРЅС‚Р°РјРё, РіСЂР°РЅС‚Р°РјРё', state: 'filled', filename: 'owner__contractors__view__runtime_contractor.png' });
    pages.push({ url: `/company/contractors/${ct}/edit`, menu: 'РћРџР•Р РђР¦РР / РџРѕРґСЂСЏРґС‡РёРєРё', page: 'Р РµРґР°РєС‚РёСЂРѕРІР°С‚СЊ РїРѕРґСЂСЏРґС‡РёРєР°', func: 'Р¤РѕСЂРјР° СЂРµРґР°РєС‚РёСЂРѕРІР°РЅРёСЏ РїРѕРґСЂСЏРґС‡РёРєР°', state: 'filled_form', filename: 'owner__contractors__edit__form.png' });
  }
  if (dr) {
    pages.push({ url: `/company/drivers/${dr}`, menu: 'РћРџР•Р РђР¦РР / Р’РѕРґРёС‚РµР»Рё', page: 'РљР°СЂС‚РѕС‡РєР° РІРѕРґРёС‚РµР»СЏ', func: 'Р”РµС‚Р°Р»СЊРЅР°СЏ РєР°СЂС‚РѕС‡РєР° РІРѕРґРёС‚РµР»СЏ СЃ С‚РµР»РµС„РѕРЅР°РјРё, РґРѕРєСѓРјРµРЅС‚Р°РјРё, Р±Р»РѕРєР°РјРё, РіСЂР°РЅС‚Р°РјРё', state: 'filled', filename: 'owner__drivers__view__runtime_driver.png' });
    pages.push({ url: `/company/drivers/${dr}/edit`, menu: 'РћРџР•Р РђР¦РР / Р’РѕРґРёС‚РµР»Рё', page: 'Р РµРґР°РєС‚РёСЂРѕРІР°С‚СЊ РІРѕРґРёС‚РµР»СЏ', func: 'Р¤РѕСЂРјР° СЂРµРґР°РєС‚РёСЂРѕРІР°РЅРёСЏ РІРѕРґРёС‚РµР»СЏ', state: 'filled_form', filename: 'owner__drivers__edit__form.png' });
  }
  if (tr) {
    pages.push({ url: `/company/vehicles/${tr}`, menu: 'РћРџР•Р РђР¦РР / РўСЂР°РЅСЃРїРѕСЂС‚РЅС‹Рµ РµРґРёРЅРёС†С‹', page: 'РљР°СЂС‚РѕС‡РєР° С‚СЂР°РЅСЃРїРѕСЂС‚РЅРѕР№ РµРґРёРЅРёС†С‹', func: 'Р”РµС‚Р°Р»СЊРЅР°СЏ РєР°СЂС‚РѕС‡РєР° С‚СЏРіР°С‡Р°/РїСЂРёС†РµРїР° СЃ РґРѕРєСѓРјРµРЅС‚Р°РјРё, РіСЂР°РЅС‚Р°РјРё', state: 'filled', filename: 'owner__vehicles__view__runtime_tractor.png' });
    pages.push({ url: `/company/vehicles/${tr}/edit`, menu: 'РћРџР•Р РђР¦РР / РўСЂР°РЅСЃРїРѕСЂС‚РЅС‹Рµ РµРґРёРЅРёС†С‹', page: 'Р РµРґР°РєС‚РёСЂРѕРІР°С‚СЊ РўР•', func: 'Р¤РѕСЂРјР° СЂРµРґР°РєС‚РёСЂРѕРІР°РЅРёСЏ С‚СЂР°РЅСЃРїРѕСЂС‚РЅРѕР№ РµРґРёРЅРёС†С‹', state: 'filled_form', filename: 'owner__vehicles__edit__form.png' });
  }
  if (tl) {
    pages.push({ url: `/company/vehicles/${tl}`, menu: 'РћРџР•Р РђР¦РР / РўСЂР°РЅСЃРїРѕСЂС‚РЅС‹Рµ РµРґРёРЅРёС†С‹', page: 'РљР°СЂС‚РѕС‡РєР° РїРѕР»СѓРїСЂРёС†РµРїР°', func: 'Р”РµС‚Р°Р»СЊРЅР°СЏ РєР°СЂС‚РѕС‡РєР° РїРѕР»СѓРїСЂРёС†РµРїР°', state: 'filled', filename: 'owner__vehicles__view__runtime_trailer.png' });
  }
  if (cp) {
    pages.push({ url: `/company/vehicle-sets/${cp}`, menu: 'РћРџР•Р РђР¦РР / РўСЂР°РЅСЃРїРѕСЂС‚РЅС‹Рµ РєРѕРјРїР»РµРєС‚С‹', page: 'РљР°СЂС‚РѕС‡РєР° С‚СЂР°РЅСЃРїРѕСЂС‚РЅРѕРіРѕ РєРѕРјРїР»РµРєС‚Р°', func: 'Р”РµС‚Р°Р»СЊРЅР°СЏ РєР°СЂС‚РѕС‡РєР° СЃС†РµРїРєРё', state: 'filled', filename: 'owner__vehicle_sets__view__runtime_coupling.png' });
    pages.push({ url: `/company/vehicle-sets/${cp}/edit`, menu: 'РћРџР•Р РђР¦РР / РўСЂР°РЅСЃРїРѕСЂС‚РЅС‹Рµ РєРѕРјРїР»РµРєС‚С‹', page: 'Р РµРґР°РєС‚РёСЂРѕРІР°С‚СЊ РєРѕРјРїР»РµРєС‚', func: 'Р¤РѕСЂРјР° СЂРµРґР°РєС‚РёСЂРѕРІР°РЅРёСЏ С‚СЂР°РЅСЃРїРѕСЂС‚РЅРѕРіРѕ РєРѕРјРїР»РµРєС‚Р°', state: 'filled_form', filename: 'owner__vehicle_sets__edit__form.png' });
  }
  if (dvb) {
    pages.push({ url: `/company/driver-vehicle-blocks/${dvb}`, menu: 'РћРџР•Р РђР¦РР / Р’РѕРґРёС‚РµР»СЊ+РўРЎ', page: 'РљР°СЂС‚РѕС‡РєР° Р±Р»РѕРєР° Р’РѕРґРёС‚РµР»СЊ+РўРЎ', func: 'Р”РµС‚Р°Р»СЊРЅР°СЏ РєР°СЂС‚РѕС‡РєР° Р±Р»РѕРєР°', state: 'filled', filename: 'owner__dvb__view__runtime_block.png' });
    pages.push({ url: `/company/driver-vehicle-blocks/${dvb}/edit`, menu: 'РћРџР•Р РђР¦РР / Р’РѕРґРёС‚РµР»СЊ+РўРЎ', page: 'Р РµРґР°РєС‚РёСЂРѕРІР°С‚СЊ Р±Р»РѕРє', func: 'Р¤РѕСЂРјР° СЂРµРґР°РєС‚РёСЂРѕРІР°РЅРёСЏ Р±Р»РѕРєР° Р’РѕРґРёС‚РµР»СЊ+РўРЎ', state: 'filled_form', filename: 'owner__dvb__edit__form.png' });
  }
  if (cr) {
    pages.push({ url: `/company/crews/${cr}`, menu: 'РћРџР•Р РђР¦РР / Р­РєРёРїР°Р¶Рё', page: 'РљР°СЂС‚РѕС‡РєР° СЌРєРёРїР°Р¶Р°', func: 'Р”РµС‚Р°Р»СЊРЅР°СЏ РєР°СЂС‚РѕС‡РєР° СЌРєРёРїР°Р¶Р°', state: 'filled', filename: 'owner__crews__view__runtime_crew.png' });
    pages.push({ url: `/company/crews/${cr}/edit`, menu: 'РћРџР•Р РђР¦РР / Р­РєРёРїР°Р¶Рё', page: 'Р РµРґР°РєС‚РёСЂРѕРІР°С‚СЊ СЌРєРёРїР°Р¶', func: 'Р¤РѕСЂРјР° СЂРµРґР°РєС‚РёСЂРѕРІР°РЅРёСЏ СЌРєРёРїР°Р¶Р°', state: 'filled_form', filename: 'owner__crews__edit__form.png' });
  }

  // Always capture list pages and create pages
  const listPages = [
    { url: '/company/drivers', menu: 'РћРџР•Р РђР¦РР / Р’РѕРґРёС‚РµР»Рё', page: 'Р’РѕРґРёС‚РµР»Рё', func: 'РЎРїРёСЃРѕРє РІРѕРґРёС‚РµР»РµР№', state: 'filled', filename: 'owner__drivers__list__filled.png' },
    { url: '/company/drivers/create', menu: 'РћРџР•Р РђР¦РР / Р’РѕРґРёС‚РµР»Рё', page: 'РЎРѕР·РґР°С‚СЊ РІРѕРґРёС‚РµР»СЏ', func: 'Р¤РѕСЂРјР° СЃРѕР·РґР°РЅРёСЏ РІРѕРґРёС‚РµР»СЏ', state: 'empty_form', filename: 'owner__drivers__create__form.png' },
    { url: '/company/vehicles', menu: 'РћРџР•Р РђР¦РР / РўСЂР°РЅСЃРїРѕСЂС‚РЅС‹Рµ РµРґРёРЅРёС†С‹', page: 'РўСЂР°РЅСЃРїРѕСЂС‚РЅС‹Рµ РµРґРёРЅРёС†С‹', func: 'РЎРїРёСЃРѕРє С‚СЂР°РЅСЃРїРѕСЂС‚РЅС‹С… РµРґРёРЅРёС†', state: 'filled', filename: 'owner__vehicles__list__filled.png' },
    { url: '/company/vehicles/create', menu: 'РћРџР•Р РђР¦РР / РўСЂР°РЅСЃРїРѕСЂС‚РЅС‹Рµ РµРґРёРЅРёС†С‹', page: 'РЎРѕР·РґР°С‚СЊ РўР•', func: 'Р¤РѕСЂРјР° СЃРѕР·РґР°РЅРёСЏ С‚СЂР°РЅСЃРїРѕСЂС‚РЅРѕР№ РµРґРёРЅРёС†С‹', state: 'empty_form', filename: 'owner__vehicles__create__form.png' },
    { url: '/company/vehicle-sets', menu: 'РћРџР•Р РђР¦РР / РўСЂР°РЅСЃРїРѕСЂС‚РЅС‹Рµ РєРѕРјРїР»РµРєС‚С‹', page: 'РўСЂР°РЅСЃРїРѕСЂС‚РЅС‹Рµ РєРѕРјРїР»РµРєС‚С‹', func: 'РЎРїРёСЃРѕРє С‚СЂР°РЅСЃРїРѕСЂС‚РЅС‹С… РєРѕРјРїР»РµРєС‚РѕРІ', state: 'filled', filename: 'owner__vehicle_sets__list__filled.png' },
    { url: '/company/vehicle-sets/create', menu: 'РћРџР•Р РђР¦РР / РўСЂР°РЅСЃРїРѕСЂС‚РЅС‹Рµ РєРѕРјРїР»РµРєС‚С‹', page: 'РЎРѕР·РґР°С‚СЊ РєРѕРјРїР»РµРєС‚', func: 'Р¤РѕСЂРјР° СЃРѕР·РґР°РЅРёСЏ С‚СЂР°РЅСЃРїРѕСЂС‚РЅРѕРіРѕ РєРѕРјРїР»РµРєС‚Р°', state: 'empty_form', filename: 'owner__vehicle_sets__create__form.png' },
    { url: '/company/driver-vehicle-blocks', menu: 'РћРџР•Р РђР¦РР / Р’РѕРґРёС‚РµР»СЊ+РўРЎ', page: 'Р’РѕРґРёС‚РµР»СЊ+РўРЎ', func: 'РЎРїРёСЃРѕРє Р±Р»РѕРєРѕРІ Р’РѕРґРёС‚РµР»СЊ+РўРЎ', state: 'filled', filename: 'owner__dvb__list__filled.png' },
    { url: '/company/driver-vehicle-blocks/create', menu: 'РћРџР•Р РђР¦РР / Р’РѕРґРёС‚РµР»СЊ+РўРЎ', page: 'РЎРѕР·РґР°С‚СЊ Р±Р»РѕРє', func: 'Р¤РѕСЂРјР° СЃРѕР·РґР°РЅРёСЏ Р±Р»РѕРєР° Р’РѕРґРёС‚РµР»СЊ+РўРЎ', state: 'empty_form', filename: 'owner__dvb__create__form.png' },
    { url: '/company/crews', menu: 'РћРџР•Р РђР¦РР / Р­РєРёРїР°Р¶Рё', page: 'Р­РєРёРїР°Р¶Рё', func: 'РЎРїРёСЃРѕРє СЌРєРёРїР°Р¶РµР№', state: 'filled', filename: 'owner__crews__list__filled.png' },
    { url: '/company/crews/create', menu: 'РћРџР•Р РђР¦РР / Р­РєРёРїР°Р¶Рё', page: 'РЎРѕР·РґР°С‚СЊ СЌРєРёРїР°Р¶', func: 'Р¤РѕСЂРјР° СЃРѕР·РґР°РЅРёСЏ СЌРєРёРїР°Р¶Р°', state: 'empty_form', filename: 'owner__crews__create__form.png' },
    { url: '/company/documents', menu: 'РћРџР•Р РђР¦РР / Р”РѕРєСѓРјРµРЅС‚С‹', page: 'Р”РѕРєСѓРјРµРЅС‚С‹', func: 'РЎРїРёСЃРѕРє РІСЃРµС… РґРѕРєСѓРјРµРЅС‚РѕРІ РєРѕРјРїР°РЅРёРё', state: 'filled', filename: 'owner__documents__list__filled.png' },
    { url: '/company/documents/upload', menu: 'РћРџР•Р РђР¦РР / Р”РѕРєСѓРјРµРЅС‚С‹', page: 'Р—Р°РіСЂСѓР·РёС‚СЊ РґРѕРєСѓРјРµРЅС‚', func: 'Р¤РѕСЂРјР° Р·Р°РіСЂСѓР·РєРё РґРѕРєСѓРјРµРЅС‚Р°', state: 'empty_form', filename: 'owner__documents__upload__form.png' },
    { url: '/company/clients', menu: 'РћРџР•Р РђР¦РР / РљР»РёРµРЅС‚С‹', page: 'РљР»РёРµРЅС‚С‹', func: 'РЎРїРёСЃРѕРє РєР»РёРµРЅС‚РѕРІ', state: 'filled', filename: 'owner__clients__list__filled.png' },
  ];

  const allPages = [...pages, ...listPages];
  for (const p of allPages) {
    await capturePage(page, p.url, p.filename, {
      role, login: credLogin, menu: p.menu, page: p.page, function: p.func, state: p.state, comment: null,
    });
  }

  await context.close();
}

async function captureLogist(browser, creds, label, ids) {
  console.log(`\n=== LOGIST ${label} SCREENSHOTS ===`);
  const context = await browser.newContext({ viewport: { width: 1920, height: 1080 } });
  const page = await context.newPage();
  
  await login(page, creds);
  const credLogin = creds.login;
  const role = 'logist';
  const ct = ids.contractor;
  const dr = ids.driver;
  const tr = ids.tractor;
  const tl = ids.trailer;
  const cp = ids.coupling;
  const dvb = ids.dvb;
  const cr = ids.crew;

  const pages = [
    { url: '/company', menu: 'РћРџР•Р РђР¦РР', page: 'Dashboard', func: 'Р“Р»Р°РІРЅР°СЏ СЃС‚СЂР°РЅРёС†Р° РєРѕРјРїР°РЅРёРё (Р»РѕРіРёСЃС‚)', state: 'filled', filename: `logist${label}__dashboard__dashboard__filled.png` },
    { url: '/company/contractors', menu: 'РћРџР•Р РђР¦РР / РџРѕРґСЂСЏРґС‡РёРєРё', page: 'РџРѕРґСЂСЏРґС‡РёРєРё', func: 'РЎРїРёСЃРѕРє РїРѕРґСЂСЏРґС‡РёРєРѕРІ (СЃ СѓС‡РµС‚РѕРј РїСЂР°РІ)', state: 'filled', filename: `logist${label}__contractors__list.png` },
    { url: '/company/drivers', menu: 'РћРџР•Р РђР¦РР / Р’РѕРґРёС‚РµР»Рё', page: 'Р’РѕРґРёС‚РµР»Рё', func: 'РЎРїРёСЃРѕРє РІРѕРґРёС‚РµР»РµР№ (СЃ СѓС‡РµС‚РѕРј РїСЂР°РІ)', state: 'filled', filename: `logist${label}__drivers__list.png` },
    { url: '/company/vehicles', menu: 'РћРџР•Р РђР¦РР / РўСЂР°РЅСЃРїРѕСЂС‚РЅС‹Рµ РµРґРёРЅРёС†С‹', page: 'РўСЂР°РЅСЃРїРѕСЂС‚РЅС‹Рµ РµРґРёРЅРёС†С‹', func: 'РЎРїРёСЃРѕРє РўР• (СЃ СѓС‡РµС‚РѕРј РїСЂР°РІ)', state: 'filled', filename: `logist${label}__vehicles__list.png` },
    { url: '/company/vehicle-sets', menu: 'РћРџР•Р РђР¦РР / РўСЂР°РЅСЃРїРѕСЂС‚РЅС‹Рµ РєРѕРјРїР»РµРєС‚С‹', page: 'РўСЂР°РЅСЃРїРѕСЂС‚РЅС‹Рµ РєРѕРјРїР»РµРєС‚С‹', func: 'РЎРїРёСЃРѕРє РєРѕРјРїР»РµРєС‚РѕРІ (СЃ СѓС‡РµС‚РѕРј РїСЂР°РІ)', state: 'filled', filename: `logist${label}__vehicle_sets__list.png` },
    { url: '/company/driver-vehicle-blocks', menu: 'РћРџР•Р РђР¦РР / Р’РѕРґРёС‚РµР»СЊ+РўРЎ', page: 'Р’РѕРґРёС‚РµР»СЊ+РўРЎ', func: 'РЎРїРёСЃРѕРє Р±Р»РѕРєРѕРІ (СЃ СѓС‡РµС‚РѕРј РїСЂР°РІ)', state: 'filled', filename: `logist${label}__dvb__list.png` },
    { url: '/company/crews', menu: 'РћРџР•Р РђР¦РР / Р­РєРёРїР°Р¶Рё', page: 'Р­РєРёРїР°Р¶Рё', func: 'РЎРїРёСЃРѕРє СЌРєРёРїР°Р¶РµР№ (СЃ СѓС‡РµС‚РѕРј РїСЂР°РІ)', state: 'filled', filename: `logist${label}__crews__list.png` },
  ];

  // Add view pages for logist1 (owner)
  if (label === '1' && ct) {
    pages.push({ url: `/company/contractors/${ct}`, menu: 'РћРџР•Р РђР¦РР / РџРѕРґСЂСЏРґС‡РёРєРё', page: 'РљР°СЂС‚РѕС‡РєР° РїРѕРґСЂСЏРґС‡РёРєР°', func: 'РљР°СЂС‚РѕС‡РєР° РїРѕРґСЂСЏРґС‡РёРєР° (Р»РѕРіРёСЃС‚-РІР»Р°РґРµР»РµС†)', state: 'filled', filename: `logist1__contractors__view__own_record.png` });
    pages.push({ url: `/company/contractors/${ct}/edit`, menu: 'РћРџР•Р РђР¦РР / РџРѕРґСЂСЏРґС‡РёРєРё', page: 'Р РµРґР°РєС‚РёСЂРѕРІР°С‚СЊ РїРѕРґСЂСЏРґС‡РёРєР°', func: 'Р РµРґР°РєС‚РёСЂРѕРІР°РЅРёРµ СЃРІРѕРµРіРѕ РїРѕРґСЂСЏРґС‡РёРєР°', state: 'filled_form', filename: `logist1__contractors__edit__own_record.png` });
  }
  if (label === '1' && dr) {
    pages.push({ url: `/company/drivers/${dr}`, menu: 'РћРџР•Р РђР¦РР / Р’РѕРґРёС‚РµР»Рё', page: 'РљР°СЂС‚РѕС‡РєР° РІРѕРґРёС‚РµР»СЏ', func: 'РљР°СЂС‚РѕС‡РєР° РІРѕРґРёС‚РµР»СЏ (Р»РѕРіРёСЃС‚-РІР»Р°РґРµР»РµС†)', state: 'filled', filename: `logist1__drivers__view__own_record.png` });
    pages.push({ url: `/company/drivers/${dr}/edit`, menu: 'РћРџР•Р РђР¦РР / Р’РѕРґРёС‚РµР»Рё', page: 'Р РµРґР°РєС‚РёСЂРѕРІР°С‚СЊ РІРѕРґРёС‚РµР»СЏ', func: 'Р РµРґР°РєС‚РёСЂРѕРІР°РЅРёРµ СЃРІРѕРµРіРѕ РІРѕРґРёС‚РµР»СЏ', state: 'filled_form', filename: `logist1__drivers__edit__own_record.png` });
  }
  if (label === '1' && tr) {
    pages.push({ url: `/company/vehicles/${tr}`, menu: 'РћРџР•Р РђР¦РР / РўСЂР°РЅСЃРїРѕСЂС‚РЅС‹Рµ РµРґРёРЅРёС†С‹', page: 'РљР°СЂС‚РѕС‡РєР° РўР•', func: 'РљР°СЂС‚РѕС‡РєР° РўР• (Р»РѕРіРёСЃС‚-РІР»Р°РґРµР»РµС†)', state: 'filled', filename: `logist1__vehicles__view__own_record.png` });
  }
  if (label === '1' && cp) {
    pages.push({ url: `/company/vehicle-sets/${cp}`, menu: 'РћРџР•Р РђР¦РР / РўСЂР°РЅСЃРїРѕСЂС‚РЅС‹Рµ РєРѕРјРїР»РµРєС‚С‹', page: 'РљР°СЂС‚РѕС‡РєР° РєРѕРјРїР»РµРєС‚Р°', func: 'РљР°СЂС‚РѕС‡РєР° РєРѕРјРїР»РµРєС‚Р° (Р»РѕРіРёСЃС‚-РІР»Р°РґРµР»РµС†)', state: 'filled', filename: `logist1__vehicle_sets__view__own_record.png` });
  }
  if (label === '1' && dvb) {
    pages.push({ url: `/company/driver-vehicle-blocks/${dvb}`, menu: 'РћРџР•Р РђР¦РР / Р’РѕРґРёС‚РµР»СЊ+РўРЎ', page: 'РљР°СЂС‚РѕС‡РєР° Р±Р»РѕРєР°', func: 'РљР°СЂС‚РѕС‡РєР° Р±Р»РѕРєР° (Р»РѕРіРёСЃС‚-РІР»Р°РґРµР»РµС†)', state: 'filled', filename: `logist1__dvb__view__own_record.png` });
  }
  if (label === '1' && cr) {
    pages.push({ url: `/company/crews/${cr}`, menu: 'РћРџР•Р РђР¦РР / Р­РєРёРїР°Р¶Рё', page: 'РљР°СЂС‚РѕС‡РєР° СЌРєРёРїР°Р¶Р°', func: 'РљР°СЂС‚РѕС‡РєР° СЌРєРёРїР°Р¶Р° (Р»РѕРіРёСЃС‚-РІР»Р°РґРµР»РµС†)', state: 'filled', filename: `logist1__crews__view__own_record.png` });
  }

  // For logist2 - grant views
  if (label === '2' && cr) {
    pages.push({ url: `/company/crews/${cr}`, menu: 'РћРџР•Р РђР¦РР / Р­РєРёРїР°Р¶Рё', page: 'РљР°СЂС‚РѕС‡РєР° СЌРєРёРїР°Р¶Р°', func: 'РљР°СЂС‚РѕС‡РєР° СЌРєРёРїР°Р¶Р° (Р»РѕРіРёСЃС‚ СЃ grant-РґРѕСЃС‚СѓРїРѕРј)', state: 'grant_view', filename: `logist2__crews__view__grant_access.png` });
  }

  for (const p of pages) {
    await capturePage(page, p.url, p.filename, {
      role, login: credLogin, menu: p.menu, page: p.page, function: p.func, state: p.state, comment: null,
    });
  }

  await context.close();
}

async function main() {
  console.log('=== ERP PLANEX FULL UI REVISION вЂ” SCREENSHOT CAPTURE ===');
  console.log(`Base URL: ${BASE}`);
  console.log(`Output: ${SCREENSHOT_DIR}`);
  
  mkdirSync(SCREENSHOT_DIR, { recursive: true });

  const browser = await chromium.launch({ headless: true });

  try {
    // Step 1: Login as superadmin and capture
    await captureSuperadmin(browser);

    // Step 2: Login as owner, discover IDs, capture
    const ownerContext = await browser.newContext({ viewport: { width: 1920, height: 1080 } });
    const ownerPage = await ownerContext.newPage();
    await login(ownerPage, ROLES.owner);
    const ids = await discoverRuntimeIds(ownerPage);
    await ownerContext.close();

    // Step 3: Capture owner pages with discovered IDs
    await captureOwner(browser, ids);

    // Step 4: Capture logist1 pages
    await captureLogist(browser, ROLES.logist1, '1', ids);

    // Step 5: Capture logist2 pages
    await captureLogist(browser, ROLES.logist2, '2', ids);

  } finally {
    await browser.close();
  }

  // Save manifest
  await saveManifest();

  // Save runtime IDs
  const runtimeNotesPath = 'docs/design-audit/full-ui-revision/RUNTIME_ACCESS_NOTES.md';
  let notes = '# RUNTIME ACCESS NOTES вЂ” FULL UI REVISION\n\n';
  notes += `Generated: ${new Date().toISOString()}\n\n`;
  notes += '## Accounts Used\n\n';
  notes += '| Login | Role | Password |\n';
  notes += '|---|---|---|\n';
  notes += '| admin@planex.local | superadmin | admin123 |\n';
  notes += '| owner_test_runtime | company_owner | pass1234 |\n';
  notes += '| logist_runtime_1 | logist | pass1111 |\n';
  notes += '| logist_runtime_2 | logist | pass2222 |\n';
  notes += '\n## Runtime Entities Discovered\n\n';
  notes += `- Contractor ID: ${ids.contractor}\n`;
  notes += `- Driver ID: ${ids.driver}\n`;
  notes += `- Tractor ID: ${ids.tractor}\n`;
  notes += `- Trailer ID: ${ids.trailer}\n`;
  notes += `- Vehicle Set (Coupling) ID: ${ids.coupling}\n`;
  notes += `- Driver-Vehicle Block ID: ${ids.dvb}\n`;
  notes += `- Crew ID: ${ids.crew}\n`;
  notes += '\n## Company\n\n';
  notes += '- Name: РћРћРћ "РўРµСЃС‚ Р­С‚Р°Рї 2 Runtime"\n';
  notes += '- ID: 9\n';
  notes += '- Local DB: erp_company_9\n';
  writeFileSync(runtimeNotesPath, notes, 'utf8');

  console.log('\n=== CAPTURE COMPLETE ===');
  console.log(`Total screenshots: ${manifest.length}`);
  const valid = manifest.filter(m => m.valid).length;
  const invalid = manifest.filter(m => !m.valid).length;
  console.log(`Valid: ${valid}, Invalid: ${invalid}`);
}

main().catch(console.error);
