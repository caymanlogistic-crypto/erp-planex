'use strict';

const fs = require('fs');
const path = require('path');
const Module = require('module');

let source = fs.readFileSync(path.join(__dirname, 'P17_crud_trip_finance_runtime.js'), 'utf8');
const replacements = new Map([
  ["await field(form, 'invoice_date', '05.08.2026');", "await field(form, 'invoice_date', '2026-08-05');"],
  ["await field(form, 'planned_payment_date', '25.08.2026');", "await field(form, 'planned_payment_date', '2026-08-25');"],
  ["await field(form, 'operation_date', '05.08.2026');", "await field(form, 'operation_date', '2026-08-05');"],
]);
for (const [before, after] of replacements) {
  if (source.split(before).length !== 2) throw new Error(`P17 date anchor mismatch: ${before}`);
  source = source.replace(before, after);
}
const filename = path.join(__dirname, 'P17_crud_trip_finance_runtime_v2.compiled.js');
const runtimeModule = new Module(filename, module);
runtimeModule.filename = filename;
runtimeModule.paths = module.paths;
runtimeModule._compile(source, filename);
