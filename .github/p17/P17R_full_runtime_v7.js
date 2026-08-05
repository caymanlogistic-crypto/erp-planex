'use strict';

const fs = require('fs');
const path = require('path');
const Module = require('module');

let wrapper = fs.readFileSync(path.join(__dirname, 'P17R_full_runtime_v6.js'), 'utf8');

const filteredDeleted = "  \"  await sa.page.goto(U('superadmin/deleted-data?company_id='+COMPANY+'&entity_type=linear_route&status=archived'),{waitUntil:'domcontentloaded',timeout:45000});\",";
const unfilteredDeleted = [
  "  \"  await sa.page.goto(U('superadmin/deleted-data'),{waitUntil:'domcontentloaded',timeout:45000});\",",
  "  \"  for(let attempt=0;attempt<10;attempt+=1){const found=await sa.page.locator('form[action*=\\\\\"/superadmin/deleted-data/\\\\\"][action$=\\\\\"/restore\\\\\"]').count();if(found)break;await sa.page.waitForTimeout(700);await sa.page.reload({waitUntil:'domcontentloaded'});}\",",
].join('\n');
if (wrapper.split(filteredDeleted).length !== 2) throw new Error('P17R v7 deleted-data anchor mismatch.');
wrapper = wrapper.replace(filteredDeleted, unfilteredDeleted);

const compileAnchor = "const filename = path.join(__dirname, 'P17R_full_runtime_v6.compiled.js');";
if (wrapper.split(compileAnchor).length !== 2) throw new Error('P17R v7 compile anchor mismatch.');
const patches = `
const oldOrder = "await roleChecks(browser,tripId); await tripDeleteRestore(browser,page,tripId); await financeScenarios(page,{clientId,contractorId,tripId}); await documentNegativeTests(page);";
const newOrder = "await roleChecks(browser,tripId); await financeScenarios(page,{clientId,contractorId,tripId}); await documentNegativeTests(page); await tripDeleteRestore(browser,page,tripId);";
if (!source.includes(oldOrder)) throw new Error('P17R v7 lifecycle order anchor mismatch.');
source = source.replace(oldOrder, newOrder);

const ddsOld = "await page.goto(U('company/finance/settings/dds-categories'),{waitUntil:'domcontentloaded'}); await page.getByText('Создать статью',{exact:true}).first().click(); await page.waitForTimeout(200); let form=page.locator('form[action$=\\\"/company/finance/settings/dds-categories/create\\\"]:visible').first();";
const ddsNew = "await page.goto(U('company/finance/settings/dds-categories'),{waitUntil:'networkidle',timeout:45000}); const ddsResponse=page.waitForResponse((r)=>r.request().method()==='GET'&&/\\/company\\/finance\\/settings\\/dds-categories\\/create(?:[?#]|$)/.test(r.url()),{timeout:15000}); await page.locator('#dds-category-create-btn').click(); await ddsResponse; let form=page.locator('#dds-category-create-modal form[action$=\\\"/company/finance/settings/dds-categories/create\\\"]:visible').first(); await form.waitFor({state:'visible',timeout:10000});";
if (!source.includes(ddsOld)) throw new Error('P17R v7 DDS create anchor mismatch.');
source = source.replace(ddsOld, ddsNew);

const matchingOld = "await page.goto(U('company/finance/settings/matching-rules'),{waitUntil:'domcontentloaded'}); await page.getByText('Создать правило',{exact:true}).click(); await page.waitForTimeout(200); form=page.locator('form[action$=\\\"/company/finance/settings/matching-rules/create\\\"]:visible').first();";
const matchingNew = "await page.goto(U('company/finance/settings/matching-rules'),{waitUntil:'networkidle',timeout:45000}); const matchingResponse=page.waitForResponse((r)=>r.request().method()==='GET'&&/\\/company\\/finance\\/settings\\/matching-rules\\/create(?:[?#]|$)/.test(r.url()),{timeout:15000}); await page.locator('#matching-rule-create-btn').click(); await matchingResponse; form=page.locator('#matching-rule-create-modal form[action$=\\\"/company/finance/settings/matching-rules/create\\\"]:visible').first(); await form.waitFor({state:'visible',timeout:10000});";
if (!source.includes(matchingOld)) throw new Error('P17R v7 matching create anchor mismatch.');
source = source.replace(matchingOld, matchingNew);

const ddsUnsupported = "scenario(result.finance,{scenario_id:'finance-dds-update',entity:'dds-category',action:'update',status:'NOT_SUPPORTED_BY_PRODUCT',applicable:false,evidence:'DDS UI exposes create and active-toggle, but no edit action.'});";
const ddsUpdate = "const ddsRow=page.locator('tr').filter({hasText:N.ddsCode}).first(); let ddsUpdated=false; if(await ddsRow.count()){const edit=ddsRow.locator('[data-edit-id]').first();const editResponse=page.waitForResponse((r)=>r.request().method()==='GET'&&/\\/company\\/finance\\/settings\\/dds-categories\\/edit\\?id=/.test(r.url()),{timeout:15000});await edit.click();await editResponse;const editForm=page.locator('#dds-category-edit-modal form:visible').first();await editForm.waitFor({state:'visible',timeout:10000});await fill(editForm,'name',\`P17R DDS UPDATED \${RUN_KEY}\`);await submit(page,editForm);await page.goto(U('company/finance/settings/dds-categories'),{waitUntil:'networkidle'});ddsUpdated=(await page.locator('body').innerText()).includes(\`P17R DDS UPDATED \${RUN_KEY}\`);} scenario(result.finance,{scenario_id:'finance-dds-update',entity:'dds-category',action:'update',status:ddsUpdated?'PASS':'FAIL',pass:ddsUpdated});";
if (!source.includes(ddsUnsupported)) throw new Error('P17R v7 DDS update anchor mismatch.');
source = source.replace(ddsUnsupported, ddsUpdate);

${compileAnchor}`;
wrapper = wrapper.replace(compileAnchor, patches);

const filename = path.join(__dirname, 'P17R_full_runtime_v7.wrapper.compiled.js');
const runtimeModule = new Module(filename, module);
runtimeModule.filename = filename;
runtimeModule.paths = module.paths;
runtimeModule._compile(wrapper, filename);
