'use strict';

function patch(source) {
  const before = "  const dds=form.locator('[name=\"dds_category_id\"]');\n  if(await dds.count()){const opts=await dds.locator('option').evaluateAll((items)=>items.map((item)=>item.value).filter(Boolean));if(opts.length)await fill(form,'dds_category_id',opts[0]);}";
  const after = [
    "  const dds=form.locator('[name=\"dds_category_id\"]');",
    "  if(await dds.count()) {",
    "    await page.waitForTimeout(300);",
    "    const opts=await dds.locator('option').evaluateAll((items)=>items.map((item)=>({value:item.value,text:(item.textContent||'').trim()})).filter((item)=>item.value));",
    "    const directionPattern=type==='EXPENSE'?/(^|\\b)OUT_|EXPENSE|РАСХОД/i:/(^|\\b)IN_|INCOME|ПОСТУП/i;",
    "    const preferred=opts.find((item)=>directionPattern.test(item.value+' '+item.text))||opts[0];",
    "    if(preferred) await fill(form,'dds_category_id',preferred.value);",
    "  }",
  ].join('\n');
  if (source.split(before).length !== 2) {
    throw new Error(`P17R v9 cash DDS anchor mismatch: ${source.split(before).length - 1}`);
  }
  source = source.replace(before, after);

  const bankBefore = "  await page.goto(U('company/finance/operations'),{waitUntil:'networkidle',timeout:45000}); text=await page.locator('body').innerText();";
  const bankAfter = "  await page.goto(U('company/finance/operations'),{waitUntil:'networkidle',timeout:45000}); let text=await page.locator('body').innerText();";
  if (source.split(bankBefore).length !== 2) {
    throw new Error(`P17R v9 finance text anchor mismatch: ${source.split(bankBefore).length - 1}`);
  }
  source = source.replace(bankBefore, bankAfter);

  const absentBefore = "  const absent = (await ownerPage.locator(`[${spec.attr}],tbody tr`).filter({ hasText: label }).count()) === 0;";
  const absentAfter = "  const absent = (await ownerPage.locator(`[${spec.attr}=\\\"${id}\\\"]`).count()) === 0;";
  if (source.split(absentBefore).length !== 2) {
    throw new Error(`P17R v9 exact archive ID anchor mismatch: ${source.split(absentBefore).length - 1}`);
  }
  source = source.replace(absentBefore, absentAfter);

  const cashVerifyBefore = "  await page.goto(U('company/finance/cash'),{waitUntil:'networkidle',timeout:45000});\n  const visible=(await page.locator('body').innerText()).includes(purpose);";
  const cashVerifyAfter = [
    "  await page.goto(U('company/finance/cash'),{waitUntil:'networkidle',timeout:45000});",
    "  let visible=(await page.locator('body').innerText()).includes(purpose);",
    "  if(!visible){",
    "    await page.goto(U('company/finance/operations'),{waitUntil:'networkidle',timeout:45000});",
    "    const search=page.locator('input[type=\"search\"],input[name=\"q\"],input[placeholder*=\"Поиск\"]').first();",
    "    if(await search.count()){await search.fill(purpose);await page.waitForTimeout(500);}",
    "    visible=(await page.locator('body').innerText()).includes(purpose);",
    "  }",
  ].join('\n');
  if (source.split(cashVerifyBefore).length !== 2) {
    throw new Error(`P17R v9 cash verification anchor mismatch: ${source.split(cashVerifyBefore).length - 1}`);
  }
  source = source.replace(cashVerifyBefore, cashVerifyAfter);

  return source;
}

module.exports = { patch };
