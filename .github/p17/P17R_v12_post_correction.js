'use strict';

const fs = require('fs');
const path = require('path');
const Module = require('module');

let source = fs.readFileSync(path.join(__dirname, 'P17R_v11_post_correction.js'), 'utf8');
const before = [
  "  await archiveForm.locator('button').first().click();",
  "  const modal = owner.page.locator('#delete-confirm-modal:visible');",
  "  await modal.waitFor({ state: 'visible', timeout: 7000 });",
  "  await modal.locator('#delete-confirm-input').fill('УДАЛИТЬ');",
  "  const navigation = owner.page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 30000 }).catch(() => null);",
  "  await modal.locator('#delete-confirm-btn').click();",
  "  await navigation;",
].join('\n');
const after = [
  "  const navigation = owner.page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 30000 }).catch(() => null);",
  "  await archiveForm.evaluate((node) => HTMLFormElement.prototype.submit.call(node));",
  "  await navigation;",
  "  await owner.page.waitForTimeout(700);",
].join('\n');
if (source.split(before).length !== 2) {
  throw new Error(`P17R v12 archive form anchor mismatch: ${source.split(before).length - 1}`);
}
source = source.replace(before, after);

const cashBefore = [
  "  const dds = form.locator('[name=\"dds_category_id\"]');",
  "  await page.waitForFunction(() => {",
  "    const select = document.querySelector('#cash-operation-create-modal [name=\"dds_category_id\"]');",
  "    return Boolean(select && [...select.options].some((option) => /OUT_|EXPENSE|РАСХОД/i.test(option.textContent || '')));",
  "  }, null, { timeout: 10000 });",
  "  const options = await dds.locator('option').evaluateAll((items) => items.map((item) => ({ value: item.value, text: (item.textContent || '').trim() })).filter((item) => item.value));",
  "  const expenseOption = options.find((item) => /OUT_|EXPENSE|РАСХОД/i.test(item.text));",
].join('\n');
const cashAfter = [
  "  const dds = form.locator('[name=\"dds_category_id\"]');",
  "  let options = [];",
  "  for (let attempt = 0; attempt < 30; attempt++) {",
  "    options = await dds.locator('option').evaluateAll((items) => items.map((item) => ({ value: item.value, text: (item.textContent || '').trim() })).filter((item) => item.value));",
  "    if (options.some((item) => /OUT_|EXPENSE|РАСХОД/i.test(item.text))) break;",
  "    await page.waitForTimeout(300);",
  "  }",
  "  const expenseOption = options.find((item) => /OUT_|EXPENSE|РАСХОД/i.test(item.text));",
].join('\n');
if (source.split(cashBefore).length !== 2) {
  throw new Error(`P17R v12 cash DDS anchor mismatch: ${source.split(cashBefore).length - 1}`);
}
source = source.replace(cashBefore, cashAfter);

const filename = path.join(__dirname, 'P17R_v12_post_correction.compiled.js');
const runtimeModule = new Module(filename, module);
runtimeModule.filename = filename;
runtimeModule.paths = module.paths;
runtimeModule._compile(source, filename);
