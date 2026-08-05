'use strict';

const fs = require('fs');
const path = require('path');
const Module = require('module');

let wrapper = fs.readFileSync(path.join(__dirname, 'P17R_full_runtime_v6.js'), 'utf8');

const before = "  \"  const responsePromise=page.waitForResponse((response)=>response.request().method()==='GET' && /\\\\/company\\\\/finance\\\\/cash\\\\/operation-create(?:[?#]|$)/.test(response.url()),{timeout:15000});\",";
const after = "  \"  const responsePromise=page.waitForResponse((response)=>response.request().method()==='GET' && response.url().includes('/company/finance/cash/operation-create'),{timeout:15000});\",";
if (wrapper.split(before).length !== 2) {
  throw new Error(`P17R v6 retry cash-response anchor mismatch: ${wrapper.split(before).length - 1}`);
}
wrapper = wrapper.replace(before, after);

const filename = path.join(__dirname, 'P17R_full_runtime_v6_retry.wrapper.compiled.js');
const runtimeModule = new Module(filename, module);
runtimeModule.filename = filename;
runtimeModule.paths = module.paths;
runtimeModule._compile(wrapper, filename);
