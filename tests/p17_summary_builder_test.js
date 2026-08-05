'use strict';
const assert = require('assert');
const fs = require('fs');
const os = require('os');
const path = require('path');
const { buildSummary } = require('../.github/p17/P17_summary_builder');

const SHA = '8e1b9659638d5aa86f156b7ece201da591753c82';
function write(root, name, value) { fs.writeFileSync(path.join(root, name), JSON.stringify(value, null, 2)); }
function scenario(id, extra = {}) { return { scenario_id: id, entity: 'x', action: id, status: 'PASS', applicable: true, pass: true, deployed_sha: SHA, ...extra }; }
function fixture(mutator = null) {
  const root = fs.mkdtempSync(path.join(os.tmpdir(), 'p17-summary-'));
  fs.mkdirSync(path.join(root, 'shots'));
  fs.writeFileSync(path.join(root, 'shots', 'one.png'), 'png');
  write(root, 'P17_02_ROUTE_ROLE_MATRIX.json', [scenario('route-1')]);
  write(root, 'P17_03_CRUD_MATRIX.json', [scenario('crud-1'), { scenario_id: 'crud-ns', status: 'NOT_SUPPORTED_BY_PRODUCT', applicable: false, pass: false, deployed_sha: SHA }]);
  write(root, 'P17_04_TRIP_LIFECYCLE_MATRIX.json', { deployed_sha: SHA, scenarios: [scenario('trip-1')] });
  write(root, 'P17_05_DOCUMENT_MATRIX.json', [scenario('doc-1')]);
  write(root, 'P17_06_FINANCE_MATRIX.json', [scenario('fin-1')]);
  write(root, 'P17_07_FULLHD_PAGE_MATRIX.json', [scenario('fullhd-1')]);
  write(root, 'P17_08_VIEWPORT_REGRESSION_MATRIX.json', [scenario('vp-1536', { viewport: '1536x864' }), scenario('vp-1366', { viewport: '1366x768' })]);
  write(root, 'P17_09_CONSOLE_NETWORK_REPORT.json', { status: 'PASS', deployed_sha: SHA, consoleErrors: [], pageErrors: [], requestFailures: [], unexpectedHttp: [], expected_403: 3 });
  write(root, 'P17_11_DEFECT_REGISTER.json', []);
  write(root, 'P17_12_SCREENSHOT_INDEX.json', [{ screenshot: 'shots/one.png' }]);
  write(root, 'P17_REQUIRED_SCENARIOS.json', { crud: ['crud-1', 'crud-ns'], trip: ['trip-1'], documents: ['doc-1'], finance: ['fin-1'] });
  write(root, 'P17_RUNTIME_SUMMARY.json', { status: 'PASS', deployed_sha: SHA, error: null });
  if (mutator) mutator(root);
  return root;
}

{
  const root = fixture();
  const s = buildSummary(root, { deployedSha: SHA });
  assert.strictEqual(s.status, 'PASS');
  assert.strictEqual(s.crud_scenarios, 1);
  assert.strictEqual(s.crud_not_supported, 1);
}
{
  const root = fixture((dir) => write(dir, 'P17_RUNTIME_SUMMARY.json', { summary: { status: 'PASS', deployed_sha: SHA }, error: null }));
  assert.strictEqual(buildSummary(root, { deployedSha: SHA }).status, 'PASS');
}
{
  const root = fixture((dir) => fs.unlinkSync(path.join(dir, 'P17_06_FINANCE_MATRIX.json')));
  const s = buildSummary(root, { deployedSha: SHA });
  assert.strictEqual(s.status, 'FAIL');
  assert(s.missing_or_failed.some((item) => item.code === 'MISSING_MATRIX'));
}
{
  const root = fixture((dir) => write(dir, 'P17_REQUIRED_SCENARIOS.json', { crud: ['crud-1', 'crud-missing'], trip: ['trip-1'], documents: ['doc-1'], finance: ['fin-1'] }));
  const s = buildSummary(root, { deployedSha: SHA });
  assert.strictEqual(s.status, 'FAIL');
  assert(s.missing_or_failed.some((item) => item.code === 'MISSING_REQUIRED_SCENARIO'));
}
{
  const root = fixture((dir) => write(dir, 'P17_RUNTIME_SUMMARY.json', { status: 'FAIL', deployed_sha: SHA, error: { name: 'TimeoutError', message: 'timeout' } }));
  const s = buildSummary(root, { deployedSha: SHA });
  assert.strictEqual(s.status, 'FAIL');
  assert(s.missing_or_failed.some((item) => item.code === 'TIMEOUT_RUNTIME'));
}
{
  const root = fixture((dir) => {
    const data = JSON.parse(fs.readFileSync(path.join(dir, 'P17_03_CRUD_MATRIX.json')));
    data[0].pass = false; data[0].status = 'FAIL';
    write(dir, 'P17_03_CRUD_MATRIX.json', data);
  });
  const s = buildSummary(root, { deployedSha: SHA });
  assert.strictEqual(s.status, 'FAIL');
  assert.strictEqual(s.crud_fail, 1);
}
console.log('P17_SUMMARY_BUILDER_TESTS=PASS');
