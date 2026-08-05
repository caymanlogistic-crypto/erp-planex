'use strict';

const fs = require('fs');
const path = require('path');
const Module = require('module');

let wrapper = fs.readFileSync(path.join(__dirname, 'P17R_full_runtime_v3.js'), 'utf8');
const tail = "const filename = path.join(__dirname, 'P17R_full_runtime_v3.compiled.js');\nconst runtimeModule = new Module(filename, module);\nruntimeModule.filename = filename;\nruntimeModule.paths = module.paths;\nruntimeModule._compile(source, filename);\n";
if (wrapper.split(tail).length !== 2) throw new Error('P17R v4 tail anchor mismatch.');

const replacement = `const roleSessionPattern = /async function roleSession\\(browser, roleKey\\) \\{[\\s\\S]*?\\n\\}\\nfunction attachDiagnostics/;
const roleSessionMatch = source.match(roleSessionPattern);
if (!roleSessionMatch) throw new Error('P17R v4 roleSession anchor mismatch.');
source = source.replace(roleSessionPattern, \`async function roleSession(browser, roleKey) {
  const admin = adminCredential();
  if (!admin) throw new Error('SUPERADMIN credential unavailable');
  const meta = USERS[roleKey];
  let lastError = null;
  for (let attempt = 1; attempt <= 4; attempt += 1) {
    let sx = null;
    try {
      sx = await login(browser, admin.username, admin.password);
      if (!sx.ok) throw new Error('SUPERADMIN login failed');
      await sx.page.goto(U(\\\`superadmin/companies/\\${COMPANY}/users\\\`), { waitUntil: 'domcontentloaded', timeout: 45000 });
      const suffix = roleKey === 'OWNER'
        ? \\\`/superadmin/companies/\\${COMPANY}/owner/reset-password\\\`
        : \\\`/superadmin/companies/\\${COMPANY}/users/logists/\\${meta.id}/reset-password\\\`;
      const reset = sx.page.locator(\\\`form[action$=\\\"\\${suffix}\\\"]\\\`).first();
      if (!(await reset.count())) throw new Error(\\\`Reset form unavailable for \\${roleKey}\\\`);
      await Promise.all([
        sx.page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 30000 }),
        reset.evaluate((node) => HTMLFormElement.prototype.submit.call(node)),
      ]);
      const values = await sx.page.locator('input,textarea,code,pre,[data-password]').evaluateAll((nodes) => nodes.map((node) => node.value || node.getAttribute('data-password') || node.textContent || '').filter(Boolean));
      const list = passwordCandidates(await sx.page.locator('body').innerText(), values, meta.username);
      await sx.context.close();
      sx = null;
      for (const candidate of list) {
        const session = await login(browser, meta.username, candidate);
        if (session.ok) {
          list.fill('');
          return session;
        }
        await session.context.close();
      }
      list.fill('');
      lastError = new Error(\\\`Generated password verification failed for \\${roleKey} on attempt \\${attempt}\\\`);
    } catch (error) {
      lastError = error;
      if (sx?.context) await sx.context.close().catch(() => {});
    }
    await new Promise((resolve) => setTimeout(resolve, 750 * attempt));
  }
  throw new Error(\\\`UI login failed for \\${roleKey} after credential-race retries: \\${String(lastError?.message || lastError || 'unknown').slice(0, 180)}\\\`);
}
function attachDiagnostics\`);

const filename = path.join(__dirname, 'P17R_full_runtime_v4.compiled.js');
const runtimeModule = new Module(filename, module);
runtimeModule.filename = filename;
runtimeModule.paths = module.paths;
runtimeModule._compile(source, filename);
`;

wrapper = wrapper.replace(tail, replacement);
const filename = path.join(__dirname, 'P17R_full_runtime_v4.wrapper.compiled.js');
const runtimeModule = new Module(filename, module);
runtimeModule.filename = filename;
runtimeModule.paths = module.paths;
runtimeModule._compile(wrapper, filename);
