'use strict';
const fs = require('fs');
const path = require('path');
const Module = require('module');
let source = fs.readFileSync(path.join(__dirname, 'P15_trip_create_runtime.js'), 'utf8');
source = source.replace(
  "await setField(form, 'customer_payments[0][condition_type]', 'prepayment');",
  "await setField(form, 'customer_payments[0][condition_type]', 'specific_date');\n      await setField(form, 'customer_payments[0][specific_due_date]', '11.08.2026');"
);
const filename = path.join(__dirname, 'P15_trip_create_runtime_v2_compiled.js');
const runtimeModule = new Module(filename, module);
runtimeModule.filename = filename;
runtimeModule.paths = module.paths;
runtimeModule._compile(source, filename);
