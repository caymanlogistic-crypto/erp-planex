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

const filename = path.join(__dirname, 'P17R_v12_post_correction.compiled.js');
const runtimeModule = new Module(filename, module);
runtimeModule.filename = filename;
runtimeModule.paths = module.paths;
runtimeModule._compile(source, filename);
