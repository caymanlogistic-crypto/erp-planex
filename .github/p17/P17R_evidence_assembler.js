'use strict';
const fs = require('fs');
const path = require('path');

const routeRoot = path.resolve(process.argv[2] || 'P17R_route_output');
const runtimeRoot = path.resolve(process.argv[3] || 'P17R_full_runtime_output');
const out = path.resolve(process.argv[4] || 'P17R_final_evidence');
const sha = process.env.P17_DEPLOYED_SHA || '';
if (!sha) throw new Error('P17_DEPLOYED_SHA is required');
fs.mkdirSync(out, { recursive: true });

const read = (root, name) => JSON.parse(fs.readFileSync(path.join(root, name), 'utf8'));
const write = (name, value) => fs.writeFileSync(path.join(out, name), JSON.stringify(value, null, 2) + '\n', 'utf8');
const routeRaw = read(routeRoot, 'P17_02_ROUTE_ROLE_MATRIX.json');
const fullhdRaw = read(routeRoot, 'P17_07_FULLHD_PAGE_MATRIX.json');
const viewportRaw = read(routeRoot, 'P17_08_VIEWPORT_REGRESSION_MATRIX.json');
const routeNetwork = read(routeRoot, 'P17_09_CONSOLE_NETWORK_REPORT.json');
const crud = read(runtimeRoot, 'P17_03_CRUD_MATRIX.json');
const trip = read(runtimeRoot, 'P17_04_TRIP_LIFECYCLE_MATRIX.json');
const documents = read(runtimeRoot, 'P17_05_DOCUMENT_MATRIX.json');
const finance = read(runtimeRoot, 'P17_06_FINANCE_MATRIX.json');
const runtimeNetwork = read(runtimeRoot, 'P17_09_CONSOLE_NETWORK_REPORT_PART.json');
const defectsPart = read(runtimeRoot, 'P17_11_DEFECT_REGISTER_PART.json');
const runtimeShots = read(runtimeRoot, 'P17_12_SCREENSHOT_INDEX_PART.json');
const required = read(runtimeRoot, 'P17_REQUIRED_SCENARIOS.json');
const runtimeSummary = read(runtimeRoot, 'P17_RUNTIME_SUMMARY.json');

function canonical(items, prefix, type) {
  return items.map((item, index) => ({
    scenario_id: item.scenario_id || `${prefix}-${String(index + 1).padStart(3, '0')}`,
    entity: item.entity || type,
    action: item.action || item.route || item.id || type,
    role: item.role || null,
    url: item.url || item.route || null,
    expected_result: item.expected_result || item.expected || item.expectedStatus || null,
    actual_result: item.actual_result || item.actualStatus || item.status || null,
    http_status: item.http_status ?? item.actualStatus ?? item.status ?? null,
    final_url: item.final_url || item.finalUrl || null,
    created_id: item.created_id ?? null,
    status: item.pass === true ? 'PASS' : item.status === 'NOT_SUPPORTED_BY_PRODUCT' ? 'NOT_SUPPORTED_BY_PRODUCT' : 'FAIL',
    applicable: item.applicable !== false && item.status !== 'NOT_SUPPORTED_BY_PRODUCT',
    pass: item.pass === true,
    screenshot: item.screenshot || null,
    error_details: item.error_details || item.navigationError || null,
    evidence: item.evidence || null,
    viewport: item.viewport || null,
    body_overflow_x: item.bodyOverflowX ?? null,
    content_overflow_x: item.contentOverflowX ?? null,
    offscreen_controls: item.offscreenControls ?? null,
    modal_footer_offscreen: item.modalFooterOffscreen ?? null,
    deployed_sha: sha,
  }));
}
const routes = canonical(routeRaw, 'route', 'route');
const fullhd = canonical(fullhdRaw, 'fullhd', 'page');
const viewports = canonical(viewportRaw, 'viewport', 'viewport');

function copyScreens(sourceRoot, entries, bucket) {
  const copied = [];
  const seen = new Set();
  for (const entry of entries) {
    const rel = entry.screenshot || entry.path;
    if (!rel || seen.has(rel)) continue;
    seen.add(rel);
    const src = path.join(sourceRoot, rel);
    if (!fs.existsSync(src)) throw new Error(`Screenshot is missing: ${src}`);
    const destRel = `P17_screenshots/${bucket}/${path.basename(rel)}`;
    const dest = path.join(out, destRel);
    fs.mkdirSync(path.dirname(dest), { recursive: true });
    fs.copyFileSync(src, dest);
    copied.push({ role: entry.role || null, route: entry.route || null, section: entry.section || null, state: entry.state || null, viewport: entry.viewport || null, screenshot: destRel, deployed_sha: sha });
    entry.screenshot = destRel;
  }
  return copied;
}
const routeShots = copyScreens(routeRoot, [...routes, ...viewports], 'routes');
const runtimeCopied = copyScreens(runtimeRoot, runtimeShots, 'runtime');
const byBase = new Map(routeShots.map((item) => [path.basename(item.screenshot), item.screenshot]));
for (const item of [...routes, ...fullhd, ...viewports]) if (item.screenshot) item.screenshot = byBase.get(path.basename(item.screenshot)) || item.screenshot;
const screenshotIndex = [...routeShots, ...runtimeCopied];

const consoleErrors = [...(routeNetwork.consoleErrors || []), ...(runtimeNetwork.consoleErrors || [])];
const pageErrors = [...(routeNetwork.pageErrors || []), ...(runtimeNetwork.pageErrors || [])];
const requestFailures = [...(routeNetwork.requestFailures || []), ...(runtimeNetwork.requestFailures || [])];
const unexpectedHttp = [
  ...(routeNetwork.httpFailures || []).filter((item) => !(item.status === 403 && item.expected === 'DENY')),
  ...(runtimeNetwork.unexpectedHttp || []),
];
const expected403 = Number(routeNetwork.summary?.expected403 || 0) + Number(runtimeNetwork.expected_403 || 0);
const network = {
  status: consoleErrors.length === 0 && pageErrors.length === 0 && requestFailures.length === 0 && unexpectedHttp.length === 0 ? 'PASS' : 'FAIL',
  deployed_sha: sha,
  consoleErrors,
  pageErrors,
  requestFailures,
  unexpectedHttp,
  expected_403: expected403,
  route_summary: routeNetwork.summary || null,
};
const defects = [
  {
    id: 'P17-CASH-TIMEOUT-HARNESS', severity: 'BLOCKER', status: 'RESOLVED',
    title: 'Old runner opened Create cash account modal instead of Income/Expense modal',
    details: 'Corrected by exact trigger, modal assertion and exact form action; P17R uses new evidence.', deployed_sha: sha,
  },
  ...defectsPart.map((item) => ({ ...item, deployed_sha: sha })),
];
const finalRuntime = {
  status: runtimeSummary.status === 'PASS' && network.status === 'PASS' && routes.every((item) => item.pass) && viewports.every((item) => item.pass) ? 'PASS' : 'FAIL',
  deployed_sha: sha,
  prefix: runtimeSummary.prefix || null,
  entities: runtimeSummary.entities || {},
  error: runtimeSummary.error || null,
  route_status: routes.every((item) => item.pass) ? 'PASS' : 'FAIL',
  viewport_status: viewports.every((item) => item.pass) ? 'PASS' : 'FAIL',
  network_status: network.status,
};

write('P17_02_ROUTE_ROLE_MATRIX.json', routes);
write('P17_03_CRUD_MATRIX.json', crud);
write('P17_04_TRIP_LIFECYCLE_MATRIX.json', { ...trip, deployed_sha: sha });
write('P17_05_DOCUMENT_MATRIX.json', documents);
write('P17_06_FINANCE_MATRIX.json', finance);
write('P17_07_FULLHD_PAGE_MATRIX.json', fullhd);
write('P17_08_VIEWPORT_REGRESSION_MATRIX.json', viewports);
write('P17_09_CONSOLE_NETWORK_REPORT.json', network);
write('P17_11_DEFECT_REGISTER.json', defects);
write('P17_12_SCREENSHOT_INDEX.json', screenshotIndex);
write('P17_REQUIRED_SCENARIOS.json', required);
write('P17_RUNTIME_SUMMARY.json', finalRuntime);
console.log(`P17R_EVIDENCE_ASSEMBLER=${finalRuntime.status}`);
if (finalRuntime.status !== 'PASS') process.exitCode = 1;
