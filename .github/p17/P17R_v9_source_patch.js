'use strict';

function patch(source) {
  const before = "  const dds=form.locator('[name=\"dds_category_id\"]');\n  if(await dds.count()){const opts=await dds.locator('option').evaluateAll((items)=>items.map((item)=>item.value).filter(Boolean));if(opts.length)await fill(form,'dds_category_id',opts[0]);}";
  const after = [
    "  const dds=form.locator('[name=\"dds_category_id\"]');",
    "  if(await dds.count()) {",
    "    await page.waitForTimeout(300);",
    "    const opts=await dds.locator('option').evaluateAll((items)=>items.map((item)=>({value:item.value,text:(item.textContent||'').trim()})).filter((item)=>item.value));",
    "    const directionPattern=type==='EXPENSE'?/(^|\\b)OUT_|EXPENSE|РАСХОД/i:/(^|\\b)IN_|INCOME|ПОСТУП/i;",
    "    const preferred=opts.find((item)=>directionPattern.test(item.text))||opts[0];",
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

  return source;
}

module.exports = { patch };
