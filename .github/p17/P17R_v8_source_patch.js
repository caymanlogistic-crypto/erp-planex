'use strict';

function replaceRegex(source, pattern, replacement, label) {
  if (!pattern.test(source)) throw new Error(`P17R v8 source anchor mismatch: ${label}`);
  return source.replace(pattern, replacement);
}

function patch(source) {
  const invoiceFunction = [
    "async function createInvoice(page, cfg, tripId, clientId) {",
    "  await page.goto(U('company/finance/invoices'), { waitUntil: 'domcontentloaded', timeout: 45000 });",
    "  await page.getByText('Создать счёт', { exact: true }).first().click();",
    "  const form = page.locator('form[action$=\"/company/finance/invoices/create\"]:visible').first();",
    "  await form.waitFor({ state: 'visible', timeout: 10000 });",
    "  await fill(form, 'direction', cfg.direction);",
    "  await fill(form, 'number', cfg.number);",
    "  await fill(form, 'invoice_date', '2026-08-05');",
    "  await fill(form, 'counterparty_entity_type', cfg.direction === 'OUTGOING' ? 'client' : 'contractor');",
    "  await selectDynamic(form, 'counterparty_entity_id', cfg.counterpartyId);",
    "  await fill(form, 'counterparty_name', cfg.counterpartyName);",
    "  await fill(form, 'amount', cfg.amount);",
    "  await fill(form, 'planned_payment_date', cfg.due);",
    "  await fill(form, 'basis', N.trip);",
    "  let routeLinked = false;",
    "  let routeLinkError = null;",
    "  if (cfg.linkRoute) {",
    "    try {",
    "      routeLinked = await selectDynamic(form, 'link_route_id', tripId);",
    "      if (routeLinked) await fill(form, 'link_side', cfg.direction === 'OUTGOING' ? 'customer' : 'carrier');",
    "    } catch (error) {",
    "      routeLinkError = String(error?.message || error).slice(0, 500);",
    "    }",
    "  }",
    "  await fill(form, 'status', cfg.status);",
    "  await fill(form, 'comment', PREFIX + cfg.key);",
    "  const shot = await screenshot(page, 'OWNER', 'invoice', cfg.key);",
    "  const sent = await submit(page, form);",
    "  await page.goto(U('company/finance/invoices'), { waitUntil: 'domcontentloaded', timeout: 45000 });",
    "  const search = page.locator('input[type=\"search\"],input[placeholder*=\"Поиск\"]').first();",
    "  if (await search.count()) { await search.fill(cfg.number); await page.waitForTimeout(350); }",
    "  const visible = (await page.locator('body').innerText()).includes(cfg.number);",
    "  const pass = visible && (!cfg.linkRoute || routeLinked);",
    "  scenario(result.finance, { scenario_id: `finance-invoice-${cfg.key}`, entity: 'invoice', action: `${cfg.direction} ${cfg.status}`, url: U('company/finance/invoices/create'), expected_result: cfg.linkRoute ? 'Invoice created and linked to restored route' : 'Invoice created and visible', actual_result: { visible, routeLinked, routeLinkError }, http_status: sent.post?.status ?? null, final_url: page.url(), status: pass ? 'PASS' : 'FAIL', pass, screenshot: shot, error_details: routeLinkError });",
    "  return { visible, routeLinked, routeLinkError };",
    "}",
    "async function cashOperation",
  ].join('\n');
  source = replaceRegex(
    source,
    /async function createInvoice\(page, cfg, tripId, clientId\) \{[\s\S]*?\n\}\nasync function cashOperation/,
    invoiceFunction,
    'createInvoice'
  );

  const financePrefix = [
    "async function financeScenarios(page, ids) {",
    "  const ddsName = `P17R DDS ${RUN_KEY}`;",
    "  const ddsUpdatedName = `${ddsName} UPDATED`;",
    "  await page.goto(U('company/finance/settings/dds-categories'), { waitUntil: 'networkidle', timeout: 45000 });",
    "  const ddsCreateResponse = page.waitForResponse((response) => response.request().method() === 'GET' && response.url().includes('/company/finance/settings/dds-categories/create'), { timeout: 15000 });",
    "  await page.locator('#dds-category-create-btn').click();",
    "  await ddsCreateResponse;",
    "  let form = page.locator('#dds-category-create-modal form[action$=\"/company/finance/settings/dds-categories/create\"]:visible').first();",
    "  await form.waitFor({ state: 'visible', timeout: 10000 });",
    "  await fill(form, 'code', N.ddsCode);",
    "  await fill(form, 'name', ddsName);",
    "  await fill(form, 'direction', 'BOTH');",
    "  await fill(form, 'sort_order', '117');",
    "  const ddsSent = await submit(page, form);",
    "  await page.goto(U('company/finance/settings/dds-categories'), { waitUntil: 'networkidle', timeout: 45000 });",
    "  let ddsRow = page.locator('tr').filter({ hasText: N.ddsCode }).first();",
    "  const ddsVisible = (await ddsRow.count()) > 0;",
    "  scenario(result.finance, { scenario_id: 'finance-dds-create', entity: 'dds-category', action: 'create', url: page.url(), http_status: ddsSent.post?.status ?? null, status: ddsVisible ? 'PASS' : 'FAIL', pass: ddsVisible, actual_result: ddsVisible ? N.ddsCode : (await page.locator('body').innerText()).slice(0, 1200) });",
    "  scenario(result.finance, { scenario_id: 'finance-dds-reopen', entity: 'dds-category', action: 'reopen', url: page.url(), status: ddsVisible ? 'PASS' : 'FAIL', pass: ddsVisible });",
    "  let ddsUpdated = false;",
    "  if (ddsVisible) {",
    "    const editButton = ddsRow.locator('[data-edit-id]').first();",
    "    const ddsId = await editButton.getAttribute('data-edit-id');",
    "    const editResponse = page.waitForResponse((response) => response.request().method() === 'GET' && response.url().includes('/company/finance/settings/dds-categories/edit?id=' + ddsId), { timeout: 15000 });",
    "    await editButton.click();",
    "    await editResponse;",
    "    const editForm = page.locator('#dds-category-edit-modal form[action$=\"/company/finance/settings/dds-categories/edit\"]:visible').first();",
    "    await editForm.waitFor({ state: 'visible', timeout: 10000 });",
    "    await fill(editForm, 'name', ddsUpdatedName);",
    "    await fill(editForm, 'sort_order', '118');",
    "    await submit(page, editForm);",
    "    await page.goto(U('company/finance/settings/dds-categories'), { waitUntil: 'networkidle', timeout: 45000 });",
    "    ddsRow = page.locator('tr').filter({ hasText: N.ddsCode }).first();",
    "    ddsUpdated = (await ddsRow.count()) > 0 && (await ddsRow.innerText()).includes(ddsUpdatedName);",
    "  }",
    "  scenario(result.finance, { scenario_id: 'finance-dds-update', entity: 'dds-category', action: 'update', url: page.url(), status: ddsUpdated ? 'PASS' : 'FAIL', pass: ddsUpdated, actual_result: ddsUpdated ? ddsUpdatedName : 'Updated DDS row not found' });",
    "",
    "  const matchingUpdatedName = `${N.matching}_UPDATED`;",
    "  await page.goto(U('company/finance/settings/matching-rules'), { waitUntil: 'networkidle', timeout: 45000 });",
    "  const matchingCreateResponse = page.waitForResponse((response) => response.request().method() === 'GET' && response.url().includes('/company/finance/settings/matching-rules/create'), { timeout: 15000 });",
    "  await page.locator('#matching-rule-create-btn').click();",
    "  await matchingCreateResponse;",
    "  form = page.locator('#matching-rule-create-modal form[action$=\"/company/finance/settings/matching-rules/create\"]:visible').first();",
    "  await form.waitFor({ state: 'visible', timeout: 10000 });",
    "  await fill(form, 'name', N.matching);",
    "  await fill(form, 'priority', '118');",
    "  await fill(form, 'direction', 'INCOME');",
    "  await fill(form, 'purpose_contains', `P17R_${String(process.env.GITHUB_RUN_ID || '').slice(-6)}`);",
    "  await fill(form, 'action_type', 'match_counterparty');",
    "  await selectDynamic(form, 'target_counterparty_id', ids.contractorId);",
    "  await fill(form, 'auto_apply', true);",
    "  const matchingSent = await submit(page, form);",
    "  await page.goto(U('company/finance/settings/matching-rules'), { waitUntil: 'networkidle', timeout: 45000 });",
    "  let matchingRow = page.locator('tr').filter({ hasText: N.matching }).first();",
    "  const matchingVisible = (await matchingRow.count()) > 0;",
    "  scenario(result.finance, { scenario_id: 'finance-matching-create', entity: 'matching-rule', action: 'create', url: page.url(), http_status: matchingSent.post?.status ?? null, status: matchingVisible ? 'PASS' : 'FAIL', pass: matchingVisible, actual_result: matchingVisible ? N.matching : (await page.locator('body').innerText()).slice(0, 1200) });",
    "  scenario(result.finance, { scenario_id: 'finance-matching-reopen', entity: 'matching-rule', action: 'reopen', url: page.url(), status: matchingVisible ? 'PASS' : 'FAIL', pass: matchingVisible });",
    "  let matchingUpdated = false;",
    "  if (matchingVisible) {",
    "    const editButton = matchingRow.locator('[data-edit-id]').first();",
    "    const matchingId = await editButton.getAttribute('data-edit-id');",
    "    const editResponse = page.waitForResponse((response) => response.request().method() === 'GET' && response.url().includes('/company/finance/settings/matching-rules/edit?id=' + matchingId), { timeout: 15000 });",
    "    await editButton.click();",
    "    await editResponse;",
    "    const editForm = page.locator('#matching-rule-edit-modal form[action$=\"/company/finance/settings/matching-rules/edit\"]:visible').first();",
    "    await editForm.waitFor({ state: 'visible', timeout: 10000 });",
    "    await fill(editForm, 'name', matchingUpdatedName);",
    "    await fill(editForm, 'priority', '119');",
    "    await submit(page, editForm);",
    "    await page.goto(U('company/finance/settings/matching-rules'), { waitUntil: 'networkidle', timeout: 45000 });",
    "    matchingRow = page.locator('tr').filter({ hasText: matchingUpdatedName }).first();",
    "    matchingUpdated = (await matchingRow.count()) > 0;",
    "  }",
    "  scenario(result.finance, { scenario_id: 'finance-matching-update', entity: 'matching-rule', action: 'update', url: page.url(), status: matchingUpdated ? 'PASS' : 'FAIL', pass: matchingUpdated, actual_result: matchingUpdated ? matchingUpdatedName : 'Updated matching rule not found' });",
    "  const invoices=[",
  ].join('\n');
  source = replaceRegex(
    source,
    /async function financeScenarios\(page, ids\) \{[\s\S]*?  const invoices=\[/,
    financePrefix,
    'financeScenarios prefix'
  );

  source = source.replace(
    "{key:'outgoing-unpaid',direction:'OUTGOING'",
    "{key:'outgoing-unpaid',linkRoute:true,direction:'OUTGOING'"
  );

  source = source.replace(
    "  for(const cfg of invoices) await createInvoice(page,cfg,ids.tripId,ids.clientId);\n  scenario(result.finance,{scenario_id:'finance-invoice-route-linked',entity:'invoice',action:'route link',status:ids.tripId?'PASS':'FAIL',pass:Boolean(ids.tripId),actual_result:ids.tripId});",
    "  let routeInvoiceLinked = false;\n  let routeInvoiceError = null;\n  for (const cfg of invoices) { const outcome = await createInvoice(page, cfg, ids.tripId, ids.clientId); if (cfg.linkRoute) { routeInvoiceLinked = Boolean(outcome.routeLinked); routeInvoiceError = outcome.routeLinkError; } }\n  scenario(result.finance, { scenario_id: 'finance-invoice-route-linked', entity: 'invoice', action: 'route link', status: routeInvoiceLinked ? 'PASS' : 'FAIL', pass: routeInvoiceLinked, actual_result: { tripId: ids.tripId, routeInvoiceLinked }, error_details: routeInvoiceError });"
  );

  source = source.replace(
    "const relations=normalized.includes(N.client.toLocaleLowerCase('ru-RU'))&&normalized.includes(N.contractor.toLocaleLowerCase('ru-RU'))&&normalized.includes(N.driver.toLocaleLowerCase('ru-RU'))&&normalized.includes(N.vehiclePlate.toLocaleLowerCase('ru-RU'));",
    "const relations=normalized.includes(N.client.toLocaleLowerCase('ru-RU'))&&normalized.includes(N.contractor.toLocaleLowerCase('ru-RU'))&&normalized.includes(N.driver.toLocaleLowerCase('ru-RU'))&&normalized.includes(N.vehiclePlate.toLocaleLowerCase('ru-RU'))&&text.includes(PREFIX+'customer_v2.pdf')&&text.includes('220 000')&&/После загрузки/i.test(text);"
  );

  return source;
}

module.exports = { patch };
