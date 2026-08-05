'use strict';

const fs = require('fs');
const path = require('path');
const Module = require('module');

let source = fs.readFileSync(path.join(__dirname, 'P17R_full_runtime.js'), 'utf8');
const replacements = new Map([
  ["  driver: `${PREFIX}DRIVER_MAIN`,", "  driver: `${PREFIX} DRIVER MAIN`,"],
  ["  driverRestore: `${PREFIX}DRIVER_RESTORE`,", "  driverRestore: `${PREFIX} DRIVER RESTORE`,"],
  ["  if (type === 'file') return false;\n  if (tag === 'SELECT') {", "  if (type === 'file') return false;\n  if (tag !== 'SELECT' && !(await locator.isVisible())) return false;\n  if (tag === 'SELECT') {"],
  ["    'units[primary][brand]': 'КАМАЗ',", "    'units[primary][unit_type]': 'tractor', 'units[primary][brand]': 'КАМАЗ',"],
  ["    'units[secondary][brand]': 'ТОНАР',", "    'units[secondary][unit_type]': 'trailer', 'units[secondary][brand]': 'ТОНАР',"],
  ["'units[primary][diagnostic_card_date]': '01.08.2026'", "'units[primary][diagnostic_card_date]': '2026-08-01'"],
  ["'units[secondary][diagnostic_card_date]': '01.08.2026'", "'units[secondary][diagnostic_card_date]': '2026-08-01'"],
]);
for (const [before, after] of replacements) {
  if (source.split(before).length !== 2) throw new Error(`P17R v2 anchor mismatch: ${before}`);
  source = source.replace(before, after);
}
const filename = path.join(__dirname, 'P17R_full_runtime_v2.compiled.js');
const runtimeModule = new Module(filename, module);
runtimeModule.filename = filename;
runtimeModule.paths = module.paths;
runtimeModule._compile(source, filename);
