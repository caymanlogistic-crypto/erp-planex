'use strict';

const fs = require('fs');
const path = require('path');
const Module = require('module');

let wrapper = fs.readFileSync(path.join(__dirname, 'P17R_full_runtime_v3.js'), 'utf8');
const tail = "const filename = path.join(__dirname, 'P17R_full_runtime_v3.compiled.js');\nconst runtimeModule = new Module(filename, module);\nruntimeModule.filename = filename;\nruntimeModule.paths = module.paths;\nruntimeModule._compile(source, filename);\n";
if (wrapper.split(tail).length !== 2) throw new Error('P17R v6 tail anchor mismatch.');

const replacement = `const roleSessionPattern = /async function roleSession\\(browser, roleKey\\) \\{[\\s\\S]*?\\n\\}\\nfunction attachDiagnostics/;
if (!roleSessionPattern.test(source)) throw new Error('P17R v6 roleSession anchor mismatch.');
const roleSessionReplacement = [
  "async function roleSession(browser, roleKey) {",
  "  const admin = adminCredential();",
  "  if (!admin) throw new Error('SUPERADMIN credential unavailable');",
  "  const meta = USERS[roleKey];",
  "  let lastError = null;",
  "  for (let attempt = 1; attempt <= 5; attempt += 1) {",
  "    let sx = null;",
  "    try {",
  "      sx = await login(browser, admin.username, admin.password);",
  "      if (!sx.ok) throw new Error('SUPERADMIN login failed');",
  "      await sx.page.goto(U('superadmin/companies/' + COMPANY + '/users'), { waitUntil: 'domcontentloaded', timeout: 45000 });",
  "      const suffix = roleKey === 'OWNER'",
  "        ? '/superadmin/companies/' + COMPANY + '/owner/reset-password'",
  "        : '/superadmin/companies/' + COMPANY + '/users/logists/' + meta.id + '/reset-password';",
  "      const reset = sx.page.locator('form[action$=\\\"' + suffix + '\\\"]').first();",
  "      if (!(await reset.count())) throw new Error('Reset form unavailable for ' + roleKey);",
  "      await Promise.all([",
  "        sx.page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 30000 }),",
  "        reset.evaluate((node) => HTMLFormElement.prototype.submit.call(node)),",
  "      ]);",
  "      const values = await sx.page.locator('input,textarea,code,pre,[data-password]').evaluateAll((nodes) => nodes.map((node) => node.value || node.getAttribute('data-password') || node.textContent || '').filter(Boolean));",
  "      const list = passwordCandidates(await sx.page.locator('body').innerText(), values, meta.username);",
  "      await sx.context.close();",
  "      sx = null;",
  "      for (const candidate of list) {",
  "        const session = await login(browser, meta.username, candidate);",
  "        if (session.ok) { list.fill(''); return session; }",
  "        await session.context.close();",
  "      }",
  "      list.fill('');",
  "      lastError = new Error('Generated password verification failed for ' + roleKey + ' on attempt ' + attempt);",
  "    } catch (error) {",
  "      lastError = error;",
  "      if (sx?.context) await sx.context.close().catch(() => {});",
  "    }",
  "    await new Promise((resolve) => setTimeout(resolve, 900 * attempt));",
  "  }",
  "  throw new Error('UI login failed for ' + roleKey + ' after credential-race retries: ' + String(lastError?.message || lastError || 'unknown').slice(0, 180));",
  "}",
].join('\\n');
source = source.replace(roleSessionPattern, roleSessionReplacement + '\\nfunction attachDiagnostics');

const cashPattern = /async function cashOperation\\(page, type, purpose, scenarioId\\) \\{[\\s\\S]*?\\n\\}\\nasync function financeScenarios/;
if (!cashPattern.test(source)) throw new Error('P17R v6 cashOperation anchor mismatch.');
const cashReplacement = [
  "async function cashOperation(page, type, purpose, scenarioId) {",
  "  await page.goto(U('company/finance/cash'), { waitUntil:'networkidle', timeout:45000 });",
  "  const trigger=page.locator('#cash-operation-create-btn').first();",
  "  await trigger.waitFor({state:'visible',timeout:10000});",
  "  const responsePromise=page.waitForResponse((response)=>response.request().method()==='GET' && /\\/company\\/finance\\/cash\\/operation-create(?:[?#]|$)/.test(response.url()),{timeout:15000});",
  "  await trigger.click();",
  "  const formResponse=await responsePromise;",
  "  const modal=page.locator('#cash-operation-create-modal');",
  "  await modal.waitFor({state:'visible',timeout:10000});",
  "  const form=modal.locator('form[action$=\\\"/company/finance/cash/operation-create\\\"]:visible').first();",
  "  await form.waitFor({state:'visible',timeout:10000});",
  "  await fill(form,'operation_type',type);",
  "  const acc=form.locator('[name=\\\"money_account_id\\\"]');",
  "  const options=await acc.locator('option').evaluateAll((items)=>items.map((item)=>item.value).filter(Boolean));",
  "  if(options.length) await fill(form,'money_account_id',options[0]);",
  "  await fill(form,'amount',type==='INCOME'?'17100':'11700');",
  "  await fill(form,'operation_date','2026-08-05');",
  "  const dds=form.locator('[name=\\\"dds_category_id\\\"]');",
  "  if(await dds.count()){const opts=await dds.locator('option').evaluateAll((items)=>items.map((item)=>item.value).filter(Boolean));if(opts.length)await fill(form,'dds_category_id',opts[0]);}",
  "  await fill(form,'purpose',purpose);",
  "  await fill(form,'comment',PREFIX+type);",
  "  const shot=await screenshot(page,'OWNER','cash',type.toLowerCase());",
  "  const sent=await submit(page,form);",
  "  await page.goto(U('company/finance/cash'),{waitUntil:'networkidle',timeout:45000});",
  "  const visible=(await page.locator('body').innerText()).includes(purpose);",
  "  scenario(result.finance,{scenario_id:scenarioId,entity:'cash-operation',action:type==='INCOME'?'cash income':'cash expense',url:U('company/finance/cash/operation-create'),expected_result:'Operation visible after UI submit',actual_result:visible?purpose:'Not visible',http_status:sent.post?.status??formResponse.status(),final_url:page.url(),status:visible?'PASS':'FAIL',pass:visible,screenshot:shot});",
  "}",
].join('\\n');
source = source.replace(cashPattern, cashReplacement + '\\nasync function financeScenarios');

const tripDeletePattern = /async function tripDeleteRestore\\(browser, ownerPage, id\\) \\{[\\s\\S]*?\\n\\}\\nasync function roleChecks/;
if (!tripDeletePattern.test(source)) throw new Error('P17R v6 tripDeleteRestore anchor mismatch.');
const tripDeleteReplacement = [
  "async function tripDeleteRestore(browser, ownerPage, id) {",
  "  await ownerPage.goto(U('company/trips/linear'),{waitUntil:'networkidle',timeout:45000});",
  "  let row=ownerPage.locator('tr[data-linear-route-id]').filter({hasText:N.trip}).first();",
  "  await row.waitFor({state:'visible',timeout:10000});",
  "  await row.dblclick();",
  "  const deleteBtn=ownerPage.locator('#linear-trip-view-modal:visible [data-linear-trip-delete-btn]').first();",
  "  await deleteBtn.waitFor({state:'visible',timeout:10000});",
  "  await deleteBtn.click();",
  "  const confirm=ownerPage.locator('#linear-trip-delete-confirm-modal');",
  "  await confirm.waitFor({state:'visible',timeout:10000});",
  "  const input=confirm.locator('[data-confirm-input]').first();",
  "  await input.fill('УДАЛИТЬ');",
  "  const action=confirm.locator('[data-confirm-action]').first();",
  "  await action.waitFor({state:'visible',timeout:10000});",
  "  await ownerPage.waitForFunction(()=>{const button=document.querySelector('#linear-trip-delete-confirm-modal [data-confirm-action]');return button && !button.disabled;},{timeout:10000});",
  "  await action.click();",
  "  await ownerPage.waitForTimeout(1800);",
  "  await ownerPage.goto(U('company/trips/linear'),{waitUntil:'networkidle',timeout:45000});",
  "  const absent=(await ownerPage.locator('tr[data-linear-route-id]').filter({hasText:N.trip}).count())===0;",
  "  scenario(result.trip,{scenario_id:'trip-soft-delete',entity:'linear-trip',action:'soft-delete',created_id:id,status:absent?'PASS':'FAIL',pass:absent,screenshot:await screenshot(ownerPage,'OWNER','trip','soft_deleted')});",
  "  const admin=adminCredential();",
  "  const sa=await login(browser,admin.username,admin.password);",
  "  if(!sa.ok) throw new Error('SUPERADMIN login failed during trip restore');",
  "  await sa.page.goto(U('superadmin/deleted-data?company_id='+COMPANY+'&entity_type=linear_route&status=archived'),{waitUntil:'domcontentloaded',timeout:45000});",
  "  let deleted=sa.page.locator('tr').filter({hasText:N.trip}).first();",
  "  if(!(await deleted.count())) deleted=sa.page.locator('tr').filter({hasText:String(id)}).first();",
  "  const deletedVisible=(await deleted.count())>0;",
  "  scenario(result.trip,{scenario_id:'trip-superadmin-deleted-data',entity:'linear-trip',action:'SUPERADMIN deleted-data',role:'SUPERADMIN',status:deletedVisible?'PASS':'FAIL',pass:deletedVisible,screenshot:await screenshot(sa.page,'SUPERADMIN','trip','deleted_data')});",
  "  let restore=deleted.locator('form[action*=\\\"/restore\\\"]').first();",
  "  if(!(await restore.count())&&deletedVisible){const link=deleted.locator('a[href*=\\\"/superadmin/deleted-data/\\\"]').first();if(await link.count()){await link.click();await sa.page.waitForLoadState('domcontentloaded');restore=sa.page.locator('form[action*=\\\"/restore\\\"]').first();}}",
  "  const supported=(await restore.count())>0;",
  "  if(supported) await nativeSubmit(sa.page,restore);",
  "  await sa.context.close();",
  "  await ownerPage.goto(U('company/trips/linear'),{waitUntil:'networkidle',timeout:45000});",
  "  row=ownerPage.locator('tr[data-linear-route-id]').filter({hasText:N.trip}).first();",
  "  const restored=(await row.count())>0;",
  "  scenario(result.trip,{scenario_id:'trip-superadmin-restore',entity:'linear-trip',action:'SUPERADMIN restore',role:'SUPERADMIN',created_id:id,status:supported&&restored?'PASS':'FAIL',pass:Boolean(supported&&restored)});",
  "  if(restored){await row.dblclick();const modal=ownerPage.locator('#linear-trip-view-modal:visible');await modal.waitFor({state:'visible',timeout:10000});await ownerPage.waitForTimeout(600);const text=await modal.innerText();scenario(result.trip,{scenario_id:'trip-reopen-restored',entity:'linear-trip',action:'reopen restored',created_id:id,status:text.includes(N.trip)?'PASS':'FAIL',pass:text.includes(N.trip)});const normalized=text.toLocaleLowerCase('ru-RU');const relations=normalized.includes(N.client.toLocaleLowerCase('ru-RU'))&&normalized.includes(N.contractor.toLocaleLowerCase('ru-RU'))&&normalized.includes(N.driver.toLocaleLowerCase('ru-RU'))&&normalized.includes(N.vehiclePlate.toLocaleLowerCase('ru-RU'));scenario(result.trip,{scenario_id:'trip-restored-relations',entity:'linear-trip',action:'verify restored relations',created_id:id,status:relations?'PASS':'FAIL',pass:relations,actual_result:text.slice(0,1200),screenshot:await screenshot(ownerPage,'OWNER','trip','restored_relations')});}",
  "}",
].join('\\n');
source = source.replace(tripDeletePattern, tripDeleteReplacement + '\\nasync function roleChecks');

const executorEntityAnchor = "await archiveRestore(browser,page,'executor',executorId,N.driver,result.crud,'executor','route_executor');";
if (source.split(executorEntityAnchor).length !== 2) throw new Error('P17R v6 executor entity anchor mismatch.');
source = source.replace(executorEntityAnchor, "await archiveRestore(browser,page,'executor',executorId,N.driver,result.crud,'executor','crew');");

const filename = path.join(__dirname, 'P17R_full_runtime_v6.compiled.js');
const runtimeModule = new Module(filename, module);
runtimeModule.filename = filename;
runtimeModule.paths = module.paths;
runtimeModule._compile(source, filename);
`;

wrapper = wrapper.replace(tail, replacement);
const filename = path.join(__dirname, 'P17R_full_runtime_v6.wrapper.compiled.js');
const runtimeModule = new Module(filename, module);
runtimeModule.filename = filename;
runtimeModule.paths = module.paths;
runtimeModule._compile(wrapper, filename);
