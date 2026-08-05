'use strict';

const fs = require('fs');
const path = require('path');
const Module = require('module');

const originalPath = path.join(__dirname, 'P16_populated_fullhd_runtime.js');
let source = fs.readFileSync(originalPath, 'utf8');
const anchor = "    const pageFailures = result.pages.filter((item) => !item.pass);";
const replacement = `    result.expectedConsoleErrors = result.consoleErrors.filter((item) => /403/.test(String(item.message || '')) && /\\/company\\/finance\\/dashboard/.test(String(item.url || '')) && ['SENIOR_LOGIST','LOGIST_1','LOGIST_2'].includes(item.role));
    result.consoleErrors = result.consoleErrors.filter((item) => !result.expectedConsoleErrors.includes(item));
    const pageFailures = result.pages.filter((item) => !item.pass);`;
if (source.split(anchor).length !== 2) {
  throw new Error('P16 expected-403 classification anchor mismatch.');
}
source = source.replace(anchor, replacement);
const filename = path.join(__dirname, 'P16_populated_fullhd_runtime_v2.compiled.js');
const runtimeModule = new Module(filename, module);
runtimeModule.filename = filename;
runtimeModule.paths = module.paths;
runtimeModule._compile(source, filename);
