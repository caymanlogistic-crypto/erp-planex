'use strict';

const fs = require('fs');
const path = require('path');

const REQUIRED_FILES = [
  'P17_02_ROUTE_ROLE_MATRIX.json',
  'P17_03_CRUD_MATRIX.json',
  'P17_04_TRIP_LIFECYCLE_MATRIX.json',
  'P17_05_DOCUMENT_MATRIX.json',
  'P17_06_FINANCE_MATRIX.json',
  'P17_07_FULLHD_PAGE_MATRIX.json',
  'P17_08_VIEWPORT_REGRESSION_MATRIX.json',
  'P17_09_CONSOLE_NETWORK_REPORT.json',
  'P17_11_DEFECT_REGISTER.json',
  'P17_12_SCREENSHOT_INDEX.json',
  'P17_REQUIRED_SCENARIOS.json',
  'P17_RUNTIME_SUMMARY.json',
];

function readJson(root, file, errors) {
  const target = path.join(root, file);
  if (!fs.existsSync(target)) {
    errors.push({ code: 'MISSING_MATRIX', file });
    return null;
  }
  try {
    return JSON.parse(fs.readFileSync(target, 'utf8'));
  } catch (error) {
    errors.push({ code: 'INVALID_JSON', file, message: String(error.message || error) });
    return null;
  }
}

function scenariosFrom(value, name, errors) {
  if (Array.isArray(value)) return value;
  if (value && Array.isArray(value.scenarios)) return value.scenarios;
  errors.push({ code: 'INVALID_MATRIX_SHAPE', matrix: name });
  return [];
}

function normalizeScenario(item, matrixName, index, errors) {
  if (!item || typeof item !== 'object') {
    errors.push({ code: 'INVALID_SCENARIO', matrix: matrixName, index });
    return { id: `${matrixName}:${index}`, status: 'FAIL', applicable: true, pass: false };
  }
  const status = String(item.status || (item.pass === true ? 'PASS' : item.pass === false ? 'FAIL' : '')).toUpperCase();
  const applicable = item.applicable !== false && status !== 'NOT_SUPPORTED_BY_PRODUCT';
  const pass = applicable ? (item.pass === true || status === 'PASS') : false;
  const id = String(item.scenario_id || item.id || `${item.entity || matrixName}:${item.action || index}`);
  if (!status) errors.push({ code: 'MISSING_SCENARIO_STATUS', matrix: matrixName, id, index });
  if (applicable && typeof item.pass !== 'boolean' && status !== 'PASS' && status !== 'FAIL') {
    errors.push({ code: 'MISSING_SCENARIO_PASS', matrix: matrixName, id, index });
  }
  return { ...item, id, status: status || 'FAIL', applicable, pass };
}

function countMatrix(items) {
  const applicable = items.filter((item) => item.applicable);
  return {
    total: applicable.length,
    pass: applicable.filter((item) => item.pass).length,
    fail: applicable.filter((item) => !item.pass).length,
    not_supported: items.filter((item) => !item.applicable || item.status === 'NOT_SUPPORTED_BY_PRODUCT').length,
  };
}

function collectSha(value, source, shas) {
  if (!value || typeof value !== 'object') return;
  const candidates = [value.deployed_sha, value.deployedSha, value.expected_deployed_sha, value.summary?.deployed_sha];
  for (const candidate of candidates) if (typeof candidate === 'string' && candidate.trim()) shas.push({ source, sha: candidate.trim() });
}

function buildSummary(root, options = {}) {
  const errors = [];
  const data = {};
  for (const file of REQUIRED_FILES) data[file] = readJson(root, file, errors);

  const routes = scenariosFrom(data['P17_02_ROUTE_ROLE_MATRIX.json'], 'routes', errors).map((item, index) => normalizeScenario(item, 'routes', index, errors));
  const crud = scenariosFrom(data['P17_03_CRUD_MATRIX.json'], 'crud', errors).map((item, index) => normalizeScenario(item, 'crud', index, errors));
  const trip = scenariosFrom(data['P17_04_TRIP_LIFECYCLE_MATRIX.json'], 'trip', errors).map((item, index) => normalizeScenario(item, 'trip', index, errors));
  const documents = scenariosFrom(data['P17_05_DOCUMENT_MATRIX.json'], 'documents', errors).map((item, index) => normalizeScenario(item, 'documents', index, errors));
  const finance = scenariosFrom(data['P17_06_FINANCE_MATRIX.json'], 'finance', errors).map((item, index) => normalizeScenario(item, 'finance', index, errors));
  const fullhd = scenariosFrom(data['P17_07_FULLHD_PAGE_MATRIX.json'], 'fullhd', errors).map((item, index) => normalizeScenario(item, 'fullhd', index, errors));
  const viewports = scenariosFrom(data['P17_08_VIEWPORT_REGRESSION_MATRIX.json'], 'viewports', errors).map((item, index) => normalizeScenario(item, 'viewports', index, errors));
  const defects = Array.isArray(data['P17_11_DEFECT_REGISTER.json']) ? data['P17_11_DEFECT_REGISTER.json'] : [];
  if (!Array.isArray(data['P17_11_DEFECT_REGISTER.json'])) errors.push({ code: 'INVALID_DEFECT_REGISTER' });
  const screenshots = Array.isArray(data['P17_12_SCREENSHOT_INDEX.json']) ? data['P17_12_SCREENSHOT_INDEX.json'] : [];
  if (!Array.isArray(data['P17_12_SCREENSHOT_INDEX.json'])) errors.push({ code: 'INVALID_SCREENSHOT_INDEX' });
  const required = data['P17_REQUIRED_SCENARIOS.json'];
  const runtime = data['P17_RUNTIME_SUMMARY.json'];
  const network = data['P17_09_CONSOLE_NETWORK_REPORT.json'] || {};

  const matrixMap = { crud, trip, documents, finance };
  if (required && typeof required === 'object') {
    for (const [matrixName, requiredIds] of Object.entries(required)) {
      if (!Array.isArray(requiredIds) || !matrixMap[matrixName]) continue;
      const actualIds = new Set(matrixMap[matrixName].map((item) => item.id));
      for (const id of requiredIds) if (!actualIds.has(id)) errors.push({ code: 'MISSING_REQUIRED_SCENARIO', matrix: matrixName, id });
      const duplicates = matrixMap[matrixName].map((item) => item.id).filter((id, index, all) => all.indexOf(id) !== index);
      for (const id of [...new Set(duplicates)]) errors.push({ code: 'DUPLICATE_SCENARIO_ID', matrix: matrixName, id });
    }
  } else if (!errors.some((item) => item.file === 'P17_REQUIRED_SCENARIOS.json')) {
    errors.push({ code: 'INVALID_REQUIRED_SCENARIOS' });
  }

  const screenshotMissing = [];
  for (const entry of screenshots) {
    const rel = entry?.screenshot || entry?.path;
    if (!rel || typeof rel !== 'string') {
      errors.push({ code: 'INVALID_SCREENSHOT_ENTRY', entry });
      continue;
    }
    if (!fs.existsSync(path.join(root, rel))) screenshotMissing.push(rel);
  }
  if (screenshotMissing.length) errors.push({ code: 'MISSING_SCREENSHOT_FILES', files: screenshotMissing });
  const actualPngs = [];
  const walk = (dir) => {
    if (!fs.existsSync(dir)) return;
    for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
      const absolute = path.join(dir, entry.name);
      if (entry.isDirectory()) walk(absolute);
      else if (/\.png$/i.test(entry.name)) actualPngs.push(path.relative(root, absolute).replace(/\\/g, '/'));
    }
  };
  walk(root);
  if (screenshots.length !== actualPngs.length) errors.push({ code: 'SCREENSHOT_COUNT_MISMATCH', index: screenshots.length, files: actualPngs.length });

  const shas = [];
  for (const [file, value] of Object.entries(data)) collectSha(value, file, shas);
  const uniqueShas = [...new Set(shas.map((item) => item.sha))];
  if (uniqueShas.length !== 1) errors.push({ code: 'DEPLOYED_SHA_INCONSISTENT', values: shas });
  const deployedSha = uniqueShas[0] || options.deployedSha || null;
  if (options.deployedSha && deployedSha && options.deployedSha !== deployedSha) errors.push({ code: 'EXPECTED_DEPLOYED_SHA_MISMATCH', expected: options.deployedSha, actual: deployedSha });

  const runtimeError = runtime?.error || runtime?.summary?.error || null;
  if (runtimeError) errors.push({ code: /timeout/i.test(JSON.stringify(runtimeError)) ? 'TIMEOUT_RUNTIME' : 'RUNTIME_ERROR', error: runtimeError });
  if (runtime && String(runtime.status || runtime.summary?.status || '').toUpperCase() !== 'PASS') errors.push({ code: 'RUNTIME_STATUS_NOT_PASS', status: runtime.status || runtime.summary?.status || null });

  const expected403 = Number(network.expected_403 ?? network.summary?.expected403 ?? (Array.isArray(network.httpFailures) ? network.httpFailures.filter((item) => item.expected === 'DENY' && item.status === 403).length : 0));
  const consoleErrors = Array.isArray(network.consoleErrors) ? network.consoleErrors.length : Number(network.console_errors || 0);
  const pageErrors = Array.isArray(network.pageErrors) ? network.pageErrors.length : Number(network.page_errors || 0);
  const requestFailures = Array.isArray(network.requestFailures) ? network.requestFailures.length : Number(network.request_failures || 0);
  const unexpectedHttp = Array.isArray(network.unexpectedHttp) ? network.unexpectedHttp.length : Number(network.unexpected_http_errors || 0);
  if (consoleErrors) errors.push({ code: 'CONSOLE_ERRORS', count: consoleErrors });
  if (pageErrors) errors.push({ code: 'PAGE_ERRORS', count: pageErrors });
  if (requestFailures) errors.push({ code: 'REQUEST_FAILURES', count: requestFailures });
  if (unexpectedHttp) errors.push({ code: 'UNEXPECTED_HTTP_ERRORS', count: unexpectedHttp });

  const severities = ['BLOCKER', 'CRITICAL', 'HIGH', 'MEDIUM', 'LOW'];
  const defectCounts = Object.fromEntries(severities.map((severity) => [severity.toLowerCase(), defects.filter((item) => String(item.severity || '').toUpperCase() === severity && String(item.status || 'OPEN').toUpperCase() !== 'RESOLVED').length]));
  if (defectCounts.blocker) errors.push({ code: 'OPEN_BLOCKER', count: defectCounts.blocker });
  if (defectCounts.critical) errors.push({ code: 'OPEN_CRITICAL', count: defectCounts.critical });

  const counts = {
    routes: countMatrix(routes),
    crud: countMatrix(crud),
    trip: countMatrix(trip),
    documents: countMatrix(documents),
    finance: countMatrix(finance),
    fullhd: countMatrix(fullhd),
    viewports: countMatrix(viewports),
  };
  for (const [name, count] of Object.entries(counts)) if (count.fail) errors.push({ code: 'FAILED_SCENARIOS', matrix: name, count: count.fail, ids: ({ routes, crud, trip, documents, finance, fullhd, viewports })[name].filter((item) => item.applicable && !item.pass).map((item) => item.id) });

  return {
    status: errors.length === 0 ? 'PASS' : 'FAIL',
    deployed_sha: deployedSha,
    routes_reviewed: counts.routes.total,
    routes_pass: counts.routes.pass,
    routes_fail: counts.routes.fail,
    crud_scenarios: counts.crud.total,
    crud_pass: counts.crud.pass,
    crud_fail: counts.crud.fail,
    crud_not_supported: counts.crud.not_supported,
    trip_scenarios: counts.trip.total,
    trip_pass: counts.trip.pass,
    trip_fail: counts.trip.fail,
    document_scenarios: counts.documents.total,
    document_pass: counts.documents.pass,
    document_fail: counts.documents.fail,
    finance_scenarios: counts.finance.total,
    finance_pass: counts.finance.pass,
    finance_fail: counts.finance.fail,
    fullhd_pages: counts.fullhd.total,
    viewport_1536_pages: viewports.filter((item) => item.viewport === '1536x864' && item.applicable).length,
    viewport_1366_pages: viewports.filter((item) => item.viewport === '1366x768' && item.applicable).length,
    console_errors: consoleErrors,
    page_errors: pageErrors,
    request_failures: requestFailures,
    expected_403: expected403,
    unexpected_http_errors: unexpectedHttp,
    defects: defectCounts,
    screenshot_index_count: screenshots.length,
    screenshot_file_count: actualPngs.length,
    summary_consistency_check: errors.length === 0 ? 'PASS' : 'FAIL',
    missing_or_failed: errors,
  };
}

function cli(argv = process.argv.slice(2)) {
  const root = path.resolve(argv[0] || '.');
  const output = path.resolve(argv[1] || path.join(root, 'P17_16_FINAL_SUMMARY.json'));
  const summary = buildSummary(root, { deployedSha: process.env.P17_DEPLOYED_SHA || null });
  fs.writeFileSync(output, JSON.stringify(summary, null, 2) + '\n', 'utf8');
  console.log(`P17_SUMMARY_BUILDER=${summary.status}`);
  if (summary.status !== 'PASS') process.exitCode = 1;
}

if (require.main === module) cli();
module.exports = { buildSummary, countMatrix, normalizeScenario };
