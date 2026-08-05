'use strict';

const fs = require('fs');
const path = require('path');
const Module = require('module');

let source = fs.readFileSync(path.join(__dirname, 'P17_route_role_fullhd_runtime.js'), 'utf8');
const expectedAnchor = "  const expectedHttpFailures = local.httpFailures.filter((item) => target.expected === 'DENY' && item.status === 403 && item.url.includes(target.path));";
const expectedReplacement = `${expectedAnchor}\n  const expectedConsoleErrors = local.consoleErrors.filter((message) => target.expected === 'DENY' && /403/.test(String(message)));\n  const unexpectedConsoleErrors = local.consoleErrors.filter((message) => !expectedConsoleErrors.includes(message));`;
if (source.split(expectedAnchor).length !== 2) throw new Error('P17 expected-403 anchor mismatch.');
source = source.replace(expectedAnchor, expectedReplacement);
source = source.replace("    && local.consoleErrors.length === 0", "    && unexpectedConsoleErrors.length === 0");
source = source.replace("    consoleErrors: local.consoleErrors,", "    consoleErrors: unexpectedConsoleErrors,\n    expectedConsoleErrors,");
source = source.replace("  result.consoleErrors.push(...local.consoleErrors.map((message) => ({ role, route: target.path, message })));", "  result.consoleErrors.push(...unexpectedConsoleErrors.map((message) => ({ role, route: target.path, message }))); ");
const filename = path.join(__dirname, 'P17_route_role_fullhd_runtime_v2.compiled.js');
const runtimeModule = new Module(filename, module);
runtimeModule.filename = filename;
runtimeModule.paths = module.paths;
runtimeModule._compile(source, filename);
