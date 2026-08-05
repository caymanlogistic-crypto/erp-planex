'use strict';

const fs = require('fs');
const path = require('path');
const Module = require('module');

let wrapper = fs.readFileSync(path.join(__dirname, 'P17R_full_runtime_v6.js'), 'utf8');

const malformedCash = "  \"  const responsePromise=page.waitForResponse((response)=>response.request().method()==='GET' && /\\\\/company\\\\/finance\\\\/cash\\\\/operation-create(?:[?#]|$)/.test(response.url()),{timeout:15000});\",";
const fixedCash = "  \"  const responsePromise=page.waitForResponse((response)=>response.request().method()==='GET' && response.url().includes('/company/finance/cash/operation-create'),{timeout:15000});\",";
if (wrapper.split(malformedCash).length !== 2) {
  throw new Error(`P17R v9 cash-response anchor mismatch: ${wrapper.split(malformedCash).length - 1}`);
}
wrapper = wrapper.replace(malformedCash, fixedCash);

const compileTail = "const filename = path.join(__dirname, 'P17R_full_runtime_v6.compiled.js');\nconst runtimeModule = new Module(filename, module);\nruntimeModule.filename = filename;\nruntimeModule.paths = module.paths;\nruntimeModule._compile(source, filename);\n";
if (wrapper.split(compileTail).length !== 2) {
  throw new Error(`P17R v9 compile-tail anchor mismatch: ${wrapper.split(compileTail).length - 1}`);
}
wrapper = wrapper.replace(
  compileTail,
  "source = require(path.join(__dirname, 'P17R_v8_source_patch.js')).patch(source);\n" +
  "source = require(path.join(__dirname, 'P17R_v9_source_patch.js')).patch(source);\n" +
  compileTail
);

const filename = path.join(__dirname, 'P17R_full_runtime_v9.wrapper.compiled.js');
const runtimeModule = new Module(filename, module);
runtimeModule.filename = filename;
runtimeModule.paths = module.paths;
runtimeModule._compile(wrapper, filename);
