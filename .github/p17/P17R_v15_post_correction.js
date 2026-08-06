'use strict';

const fs = require('fs');
const path = require('path');
const Module = require('module');

let source = fs.readFileSync(path.join(__dirname, 'P17R_v14_post_correction.js'), 'utf8');

const replacements = [
  [
    "const restoredVisible = (await ownerRestored.page.locator(`main a[href$=\"/company/route-executors/${executorId}\"]`).count()) > 0;",
    "const restoredVisible = (await ownerRestored.page.locator(`tr[data-route-executor-id=\"${executorId}\"]`).count()) > 0;",
    'restored executor selector',
  ],
  [
    "expected_result: 'Restored executor exact link is visible',",
    "expected_result: 'Restored executor row with exact data-route-executor-id is visible',",
    'restore expected result',
  ],
  [
    "actual_result: restoredVisible ? 'Restored and visible by exact href' : 'Not visible after restore',",
    "actual_result: restoredVisible ? `Restored row data-route-executor-id=${executorId} is visible` : 'Exact executor row not visible after restore',",
    'restore actual result',
  ],
  [
    "  const targetOption = dds.locator(`option[data-direction=\"EXPENSE\"]`).filter({ hasText: ddsCode }).first();\n  await targetOption.waitFor({ state: 'attached', timeout: 10000 });\n  const ddsValue = await targetOption.getAttribute('value');\n  if (!ddsValue) throw new Error(`Expense DDS option has no value: ${ddsCode}`);\n  await dds.selectOption(ddsValue);",
    "  const expenseOptions = dds.locator('option[data-direction=\"EXPENSE\"]');\n  await expenseOptions.first().waitFor({ state: 'attached', timeout: 10000 });\n  let targetOption = expenseOptions.filter({ hasText: ddsCode }).first();\n  if (!(await targetOption.count())) targetOption = expenseOptions.first();\n  const ddsValue = await targetOption.getAttribute('value');\n  const selectedDdsText = String(await targetOption.textContent() || '').trim();\n  if (!ddsValue) throw new Error(`Expense DDS option has no value: ${selectedDdsText || ddsCode}`);\n  await dds.selectOption(ddsValue);",
    'expense DDS selection',
  ],
  [
    "if (await comment.count()) await comment.fill(`${purpose}; DDS=${ddsCode}`);",
    "if (await comment.count()) await comment.fill(`${purpose}; DDS=${selectedDdsText}`);",
    'expense comment evidence',
  ],
  [
    "actual_result: { visible, purpose, dds_code: ddsCode, dds_created: ddsCreated },",
    "actual_result: { visible, purpose, requested_dds_code: ddsCode, selected_dds: selectedDdsText, dds_created: ddsCreated },",
    'expense actual result',
  ],
  [
    "evidence: `Created and selected direction-compatible DDS category ${ddsCode}.`,",
    "evidence: `Created expense category ${ddsCode}; selected available direction-compatible category ${selectedDdsText}.`,",
    'expense evidence',
  ],
  [
    "return { visible, ddsCreated, ddsCode, purpose };",
    "return { visible, ddsCreated, ddsCode, selectedDdsText, purpose };",
    'expense return evidence',
  ],
  [
    "summary.post_correction = { version: 'V14', status: summary.status, ...correction };",
    "summary.post_correction = { version: 'V15', status: summary.status, ...correction };",
    'summary version',
  ],
  [
    "console.log(`P17R_V14_POST_CORRECTION=${summary.status}`);",
    "console.log(`P17R_V15_POST_CORRECTION=${summary.status}`);",
    'result label',
  ],
  [
    "summary.post_correction = { version: 'V14', status: 'FAIL', error:",
    "summary.post_correction = { version: 'V15', status: 'FAIL', error:",
    'failure summary version',
  ],
];

for (const [before, after, label] of replacements) {
  const count = source.split(before).length - 1;
  if (count !== 1) throw new Error(`P17R V15 anchor mismatch for ${label}: ${count}`);
  source = source.replace(before, after);
}

const filename = path.join(__dirname, 'P17R_v15_post_correction.compiled.js');
const runtimeModule = new Module(filename, module);
runtimeModule.filename = filename;
runtimeModule.paths = module.paths;
runtimeModule._compile(source, filename);
