import { chromium } from 'playwright';

const BASE = 'http://127.0.0.1:8016';
const DIR = 'docs/design-audit/full-ui-revision/screenshots';

// Runtime IDs discovered
const ids = {
  contractor: 1,
  driver: 1,
  tractor: 1,
  trailer: 2,
  coupling: 1,
  dvb: 1,
  crew: 1
};

async function loginAndCapture(creds, pages) {
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({ viewport: { width: 1920, height: 1080 } });
  const page = await context.newPage();

  await page.goto(`${BASE}/login`, { waitUntil: 'networkidle' });
  await page.fill('input[name="login"]', creds.login);
  await page.fill('input[name="password"]', creds.password);
  await page.click('button[type="submit"]');
  await page.waitForTimeout(1500);
  console.log(`Logged in as ${creds.login}`);

  for (const p of pages) {
    const url = p.url.startsWith('http') ? p.url : `${BASE}${p.url}`;
    console.log(`  ${url} -> ${p.filename}`);
    try {
      await page.goto(url, { waitUntil: 'networkidle', timeout: 15000 });
      await page.waitForTimeout(500);
      await page.screenshot({ path: `${DIR}/${p.filename}`, fullPage: true });
      console.log(`    OK`);
    } catch (e) {
      console.log(`    ERROR: ${e.message}`);
    }
  }

  await context.close();
  await browser.close();
}

// OWNER remaining pages
const ownerPages = [
  { url: `/company/drivers/${ids.driver}`, filename: 'owner__drivers__view__runtime_driver.png' },
  { url: `/company/drivers/${ids.driver}/edit`, filename: 'owner__drivers__edit__form.png' },
  { url: `/company/vehicles/${ids.tractor}`, filename: 'owner__vehicles__view__runtime_tractor.png' },
  { url: `/company/vehicles/${ids.tractor}/edit`, filename: 'owner__vehicles__edit__form.png' },
  { url: `/company/vehicles/${ids.trailer}`, filename: 'owner__vehicles__view__runtime_trailer.png' },
  { url: `/company/vehicle-sets/${ids.coupling}`, filename: 'owner__vehicle_sets__view__runtime_coupling.png' },
  { url: `/company/vehicle-sets/${ids.coupling}/edit`, filename: 'owner__vehicle_sets__edit__form.png' },
  { url: `/company/driver-vehicle-blocks/${ids.dvb}`, filename: 'owner__dvb__view__runtime_block.png' },
  { url: `/company/driver-vehicle-blocks/${ids.dvb}/edit`, filename: 'owner__dvb__edit__form.png' },
];

// LOGIST 1 remaining pages
const logist1Pages = [
  { url: `/company/drivers/${ids.driver}`, filename: 'logist1__drivers__view__own_record.png' },
  { url: `/company/drivers/${ids.driver}/edit`, filename: 'logist1__drivers__edit__own_record.png' },
  { url: `/company/vehicles/${ids.tractor}`, filename: 'logist1__vehicles__view__own_record.png' },
  { url: `/company/vehicles/${ids.tractor}/edit`, filename: 'logist1__vehicles__edit__own_record.png' },
  { url: `/company/vehicle-sets/${ids.coupling}`, filename: 'logist1__vehicle_sets__view__own_record.png' },
  { url: `/company/driver-vehicle-blocks/${ids.dvb}`, filename: 'logist1__dvb__view__own_record.png' },
];

// LOGIST 2 remaining pages
const logist2Pages = [
  { url: `/company/drivers/${ids.driver}`, filename: 'logist2__drivers__view__grant_or_denied.png' },
  { url: `/company/vehicles/${ids.tractor}`, filename: 'logist2__vehicles__view__grant_or_denied.png' },
  { url: `/company/vehicle-sets/${ids.coupling}`, filename: 'logist2__vehicle_sets__view__grant_or_denied.png' },
  { url: `/company/driver-vehicle-blocks/${ids.dvb}`, filename: 'logist2__dvb__view__grant_or_denied.png' },
];

async function main() {
  console.log('=== CAPTURING REMAINING SCREENSHOTS ===');

  await loginAndCapture({ login: 'owner_test_runtime', password: 'pass1234' }, ownerPages);
  await loginAndCapture({ login: 'logist_runtime_1', password: 'pass1111' }, logist1Pages);
  await loginAndCapture({ login: 'logist_runtime_2', password: 'pass2222' }, logist2Pages);

  console.log('Done!');
}

main().catch(console.error);
