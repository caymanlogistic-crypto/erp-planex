from pathlib import Path


def replace(path, old, new, count=-1):
    p=Path(path); s=p.read_text(encoding='utf-8')
    if old not in s:
        raise SystemExit(f'Pattern not found in {path}: {old[:120]!r}')
    s=s.replace(old,new,count)
    p.write_text(s,encoding='utf-8')

# Bank view: new title and one coherent five-action workflow.
view='app/View/pages/company_bank_accounts.php'
replace(view,'Банковские счета','Выписки со счёта')
replace(view,'Финансовый модуль: импорт и просмотр банковских выписок','Загрузка, просмотр и автоматическое разнесение банковских операций')
old='''        <button type="button" class="btn btn-primary" data-open-modal="bank-statement-upload-modal">Загрузить выписку XLSX</button>\n        <button type="button" class="btn btn-secondary" data-open-modal="bank-statements-modal">Просмотр выписок</button>'''
new='''        <button type="button" class="btn btn-secondary" data-open-modal="bank-statement-upload-modal">Загрузить выписку XLSX</button>\n        <button type="button" class="btn btn-secondary" data-open-modal="bank-statements-modal">Просмотр выписок</button>\n        <form method="post" action="<?= app_url('/company/finance/bank-accounts/refresh-from-mail') ?>" class="inline-form"><?= csrfField() ?><button type="submit" class="btn btn-secondary">Обновить из почты</button></form>\n        <a href="<?= app_url('/company/finance/settings/matching-rules') ?>" class="btn btn-secondary">Правила разнесения</a>\n        <form method="post" action="<?= app_url('/company/finance/bank-accounts/apply-rules') ?>" class="inline-form" onsubmit="return confirm('Применить действующие правила ко всем неразнесённым банковским операциям? Ручные разнесения не изменятся.');"><?= csrfField() ?><button type="submit" class="btn btn-primary">Разнести по правилам</button></form>'''
replace(view,old,new)

# Bank controller: title/context follow the new primary navigation and remove old injected mail button.
idx='app/Http/Controllers/Company/BankFinanceActions/index.php'
replace(idx,"$pageTitle='Банковские счета';","$pageTitle='Выписки со счёта';")
replace(idx,"$pageContext='Финансы › Банковские счета';","$pageContext='Финансы › Выписки со счёта';")
replace(idx,"$pageContext='Финансы › Банковские счета › Компания: '.e($company['name']);","$pageContext='Финансы › Выписки со счёта › Компания: '.e($company['name']);")
old_inject='''    $b='<button type="button" class="btn btn-secondary" data-open-modal="bank-statements-modal">Просмотр выписок</button>';\n    $f='<form method="post" action="'.e(app_url('/company/finance/bank-accounts/refresh-from-mail')).'" class="inline-form">'.csrfField().'<button type="submit" class="btn btn-secondary">Обновить из почты</button></form>';\n    $content=str_replace($b,$b.$f,$content);\n'''
replace(idx,old_inject,'')

# Matching rules: return path is visible in page header.
rules='app/View/pages/company_finance_matching_rules.php'
replace(rules,'<div class="page-head-right"><button type="button" class="btn btn-primary btn--toolbar" id="matching-rule-create-btn">+ Правило</button></div>',
'''<div class="page-head-right"><a href="<?= app_url('/company/finance/bank-accounts') ?>" class="btn btn-secondary btn--toolbar">Назад к выпискам</a><button type="button" class="btn btn-primary btn--toolbar" id="matching-rule-create-btn">+ Правило</button></div>''')

# Sidebar: statements are the parent bank workflow; matching rules disappear as a separate menu item.
layout='app/View/layouts/main.php'
replace(layout,"$bankAccountsActive = str_starts_with($requestPath, '/company/finance/bank-accounts');","$bankAccountsActive = str_starts_with($requestPath, '/company/finance/bank-accounts') || str_starts_with($requestPath, '/company/finance/settings/matching-rules');")
replace(layout,'<span class="nav-label">Банк</span>','<span class="nav-label">Выписки со счёта</span>')
old_menu='''                <a class="nav-item<?= $matchingRulesActive ? ' is-active' : '' ?>" href="<?= app_url('/company/finance/settings/matching-rules') ?>">\n                    <svg class="nav-icon" viewBox="0 0 16 16" fill="none">\n                        <rect x="2" y="2" width="12" height="12" rx="1" stroke="currentColor" stroke-width="1.4"/>\n                        <path d="M5 8L7 10L11 5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/>\n                    </svg>\n                    <span class="nav-label">Правила разнесения</span>\n                </a>\n'''
replace(layout,old_menu,'')

# Breadcrumb hierarchy mirrors the new workflow.
bc='app/View/components/breadcrumbs.php'
old_bc='''    // /company/finance/bank-accounts\n    if ($page === 'finance' && ($parts[2] ?? '') === 'bank-accounts') {\n        $crumbs[] = ['label' => 'Финансы', 'url' => null];\n        $crumbs[] = ['label' => 'Банковские счета', 'url' => null];\n        return $crumbs;\n    }\n'''
new_bc='''    // /company/finance/bank-accounts and its nested matching-rules workflow\n    if ($page === 'finance' && ($parts[2] ?? '') === 'bank-accounts') {\n        $crumbs[] = ['label' => 'Финансы', 'url' => null];\n        $crumbs[] = ['label' => 'Выписки со счёта', 'url' => null];\n        return $crumbs;\n    }\n    if ($page === 'finance' && ($parts[2] ?? '') === 'settings' && ($parts[3] ?? '') === 'matching-rules') {\n        $crumbs[] = ['label' => 'Финансы', 'url' => null];\n        $crumbs[] = ['label' => 'Выписки со счёта', 'url' => app_url('/company/finance/bank-accounts')];\n        $crumbs[] = ['label' => 'Правила разнесения', 'url' => null];\n        return $crumbs;\n    }\n'''
replace(bc,old_bc,new_bc)

print('P67_PATCH_OK')
