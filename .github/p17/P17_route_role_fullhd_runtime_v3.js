'use strict';

const fs = require('fs');
const path = require('path');
const Module = require('module');

let source = fs.readFileSync(path.join(__dirname, 'P17_route_role_fullhd_runtime.js'), 'utf8');
function replaceOnce(before, after, label) {
  if (source.split(before).length !== 2) throw new Error(`P17 route v3 anchor mismatch: ${label || before}`);
  source = source.replace(before, after);
}

const expectedAnchor = "  const expectedHttpFailures = local.httpFailures.filter((item) => target.expected === 'DENY' && item.status === 403 && item.url.includes(target.path));";
replaceOnce(expectedAnchor, `${expectedAnchor}\n  const expectedConsoleErrors = local.consoleErrors.filter((message) => target.expected === 'DENY' && /403/.test(String(message)));\n  const unexpectedConsoleErrors = local.consoleErrors.filter((message) => !expectedConsoleErrors.includes(message));`, 'expected 403');
replaceOnce("[...document.querySelectorAll('.modal-foot,.modal-footer')]", "[...document.querySelectorAll('.modal-overlay.is-open .modal-foot,.modal-overlay.is-open .modal-footer,.modal.is-open .modal-foot,.modal.is-open .modal-footer')]", 'open modal footer');
replaceOnce("    && local.consoleErrors.length === 0", "    && unexpectedConsoleErrors.length === 0", 'unexpected console gate');
replaceOnce("    consoleErrors: local.consoleErrors,", "    consoleErrors: unexpectedConsoleErrors,\n    expectedConsoleErrors,", 'record expected console');
replaceOnce("  result.consoleErrors.push(...local.consoleErrors.map((message) => ({ role, route: target.path, message })));", "  result.consoleErrors.push(...unexpectedConsoleErrors.map((message) => ({ role, route: target.path, message })));", 'aggregate unexpected console');

const rolePattern = /async function roleSession\(browser, role, viewport\) \{[\s\S]*?\n\}\nfunction slug/;
if (!rolePattern.test(source)) throw new Error('P17 route v3 roleSession anchor mismatch.');
const roleReplacement = [
  "async function roleSession(browser, role, viewport) {",
  "  const admin = adminCredential();",
  "  if (!admin) throw new Error('SUPERADMIN credential unavailable.');",
  "  if (role === 'SUPERADMIN') {",
  "    let last = null;",
  "    for (let attempt = 1; attempt <= 3; attempt += 1) {",
  "      const session = await login(browser, admin.username, admin.password, viewport);",
  "      if (session.ok) return session;",
  "      last = new Error('SUPERADMIN UI login failed on attempt ' + attempt);",
  "      await session.context.close();",
  "      await new Promise((resolve) => setTimeout(resolve, 500 * attempt));",
  "    }",
  "    throw last || new Error('SUPERADMIN UI login failed.');",
  "  }",
  "  const meta = USERS[role];",
  "  let lastError = null;",
  "  for (let attempt = 1; attempt <= 5; attempt += 1) {",
  "    let superSession = null;",
  "    try {",
  "      superSession = await login(browser, admin.username, admin.password, viewport);",
  "      if (!superSession.ok) throw new Error('SUPERADMIN UI login failed before ' + role);",
  "      await superSession.page.goto(U('/superadmin/companies/' + COMPANY + '/users'), { waitUntil: 'domcontentloaded', timeout: 45000 });",
  "      const action = role === 'OWNER'",
  "        ? '/superadmin/companies/' + COMPANY + '/owner/reset-password'",
  "        : '/superadmin/companies/' + COMPANY + '/users/logists/' + meta.id + '/reset-password';",
  "      const form = superSession.page.locator('form[action$=\\\"' + action + '\\\"]').first();",
  "      if (!(await form.count())) throw new Error('Reset form unavailable for ' + role);",
  "      await Promise.all([",
  "        superSession.page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 30000 }),",
  "        form.evaluate((node) => HTMLFormElement.prototype.submit.call(node)),",
  "      ]);",
  "      const values = await superSession.page.locator('input,textarea,code,pre,[data-password]').evaluateAll((nodes) => nodes.map((node) => node.value || node.getAttribute('data-password') || node.textContent || '').filter(Boolean));",
  "      const list = candidates(await superSession.page.locator('body').innerText(), values, meta.username);",
  "      await superSession.context.close();",
  "      superSession = null;",
  "      for (const password of list) {",
  "        const session = await login(browser, meta.username, password, viewport);",
  "        if (session.ok) { list.fill(''); return session; }",
  "        await session.context.close();",
  "      }",
  "      list.fill('');",
  "      lastError = new Error('Generated password verification failed for ' + role + ' on attempt ' + attempt);",
  "    } catch (error) {",
  "      lastError = error;",
  "      if (superSession?.context) await superSession.context.close().catch(() => {});",
  "    }",
  "    await new Promise((resolve) => setTimeout(resolve, 900 * attempt));",
  "  }",
  "  throw new Error('UI login failed for ' + role + ' after retries: ' + String(lastError?.message || lastError || 'unknown').slice(0, 180));",
  "}",
].join('\n');
source = source.replace(rolePattern, roleReplacement + '\nfunction slug');

const filename = path.join(__dirname, 'P17_route_role_fullhd_runtime_v3.compiled.js');
const runtimeModule = new Module(filename, module);
runtimeModule.filename = filename;
runtimeModule.paths = module.paths;
runtimeModule._compile(source, filename);
