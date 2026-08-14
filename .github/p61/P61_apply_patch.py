from pathlib import Path

def replace(path, old, new, count=1):
    p = Path(path)
    text = p.read_text(encoding='utf-8')
    if old not in text:
        raise SystemExit(f'pattern not found in {path}: {old[:120]!r}')
    text2 = text.replace(old, new, count)
    p.write_text(text2, encoding='utf-8')

# Controller methods
replace(
    'app/Http/Controllers/Company/FinanceDdsCategoryController.php',
    "    public function activeToggle(): void { $config=$this->config;$db=$this->db;require base_path('app/Http/Controllers/Company/FinanceDdsCategoryActions/active_toggle.php'); }\n    public function cfuSave(): void {",
    "    public function activeToggle(): void { $config=$this->config;$db=$this->db;require base_path('app/Http/Controllers/Company/FinanceDdsCategoryActions/active_toggle.php'); }\n    public function deleteSubmit(): void { $config=$this->config;$db=$this->db;require base_path('app/Http/Controllers/Company/FinanceDdsCategoryActions/delete_submit.php'); }\n    public function cfuSave(): void {"
)
replace(
    'app/Http/Controllers/Company/FinanceDdsCategoryController.php',
    "    public function cfuToggle(): void { $config=$this->config;$db=$this->db;require base_path('app/Http/Controllers/Company/FinanceDdsCategoryActions/cfu_toggle.php'); }\n    public function linksSave(): void {",
    "    public function cfuToggle(): void { $config=$this->config;$db=$this->db;require base_path('app/Http/Controllers/Company/FinanceDdsCategoryActions/cfu_toggle.php'); }\n    public function cfuDelete(): void { $config=$this->config;$db=$this->db;require base_path('app/Http/Controllers/Company/FinanceDdsCategoryActions/cfu_delete.php'); }\n    public function linksSave(): void {"
)

# Routes
replace(
    'app/Http/Routes/company_finance_dds_categories.php',
    "$router->post('/company/finance/settings/dds-categories/active-toggle', [$controller, 'activeToggle']);\n$router->post('/company/finance/settings/dds-categories/cfu/save', [$controller, 'cfuSave']);",
    "$router->post('/company/finance/settings/dds-categories/active-toggle', [$controller, 'activeToggle']);\n$router->post('/company/finance/settings/dds-categories/delete', [$controller, 'deleteSubmit']);\n$router->post('/company/finance/settings/dds-categories/cfu/save', [$controller, 'cfuSave']);"
)
replace(
    'app/Http/Routes/company_finance_dds_categories.php',
    "$router->post('/company/finance/settings/dds-categories/cfu/toggle', [$controller, 'cfuToggle']);\n$router->post('/company/finance/settings/dds-categories/links/save', [$controller, 'linksSave']);",
    "$router->post('/company/finance/settings/dds-categories/cfu/toggle', [$controller, 'cfuToggle']);\n$router->post('/company/finance/settings/dds-categories/cfu/delete', [$controller, 'cfuDelete']);\n$router->post('/company/finance/settings/dds-categories/links/save', [$controller, 'linksSave']);"
)

# Financial structure page: footer geometry, CFU deletion UI and dynamic remote forms.
view = 'app/View/pages/company_finance_dds_categories.php'
replace(
    view,
    ".fs-settings-danger{margin-right:auto}.fs-cfu-create-empty{margin-top:8px}",
    ".fs-settings-danger{margin-right:auto}.fs-delete-action{color:var(--danger)!important}.fs-delete-action:hover{background:var(--danger-bg)!important;border-color:var(--danger)!important}.fs-cfu-create-empty{margin-top:8px}"
)
replace(
    view,
    '<div class="modal-foot"><button type="button" class="btn btn-ghost fs-settings-danger" id="fs-settings-toggle"></button><button type="button" class="btn btn-ghost" data-close-modal="fs-cfu-settings-modal">Отмена</button><button type="submit" class="btn btn-primary" form="fs-cfu-settings-form">Сохранить</button></div>',
    '<div class="modal-foot"><button type="button" class="btn btn-ghost fs-settings-danger" id="fs-settings-toggle"></button><button type="button" class="btn btn-ghost fs-delete-action" id="fs-settings-delete">Удалить ЦФУ</button><button type="button" class="btn btn-ghost" data-close-modal="fs-cfu-settings-modal">Отмена</button><button type="submit" class="btn btn-primary" form="fs-cfu-settings-form">Сохранить</button></div>'
)
replace(
    view,
    '<form method="post" action="<?= app_url(\'/company/finance/settings/dds-categories/cfu/toggle\') ?>" id="fs-cfu-toggle-form" hidden><?= csrfField() ?><input type="hidden" name="id" id="fs-toggle-id"><input type="hidden" name="active" id="fs-toggle-active"></form>',
    '<form method="post" action="<?= app_url(\'/company/finance/settings/dds-categories/cfu/toggle\') ?>" id="fs-cfu-toggle-form" hidden><?= csrfField() ?><input type="hidden" name="id" id="fs-toggle-id"><input type="hidden" name="active" id="fs-toggle-active"></form>\n<form method="post" action="<?= app_url(\'/company/finance/settings/dds-categories/cfu/delete\') ?>" id="fs-cfu-delete-form" hidden><?= csrfField() ?><input type="hidden" name="id" id="fs-delete-id"></form>'
)
replace(
    view,
    '<div id="dds-category-create-modal" class="modal-overlay" role="dialog" aria-modal="true"><div class="modal modal-md"><div class="modal-head"><span class="modal-title">Новая статья ДДС</span><button type="button" class="modal-close" data-close-modal="dds-category-create-modal">&times;</button></div><div class="modal-body" id="dds-category-create-modal-body"></div></div></div>\n<div id="dds-category-edit-modal" class="modal-overlay" role="dialog" aria-modal="true"><div class="modal modal-md"><div class="modal-head"><span class="modal-title">Редактирование статьи ДДС</span><button type="button" class="modal-close" data-close-modal="dds-category-edit-modal">&times;</button></div><div class="modal-body" id="dds-category-edit-modal-body"></div></div></div>',
    '<div id="dds-category-create-modal" class="modal-overlay" role="dialog" aria-modal="true"><div class="modal modal-md"><div class="modal-head"><span class="modal-title">Новая статья ДДС</span><button type="button" class="modal-close" data-close-modal="dds-category-create-modal">&times;</button></div><div class="fs-remote-form" id="dds-category-create-modal-body"></div></div></div>\n<div id="dds-category-edit-modal" class="modal-overlay" role="dialog" aria-modal="true"><div class="modal modal-md"><div class="modal-head"><span class="modal-title">Редактирование статьи ДДС</span><button type="button" class="modal-close" data-close-modal="dds-category-edit-modal">&times;</button></div><div class="fs-remote-form" id="dds-category-edit-modal-body"></div></div></div>'
)
replace(
    view,
    " var settingsToggle=document.getElementById('fs-settings-toggle');\n if(settingsToggle){settingsToggle.addEventListener('click',function(){\n  var question=settingsActive?'Архивировать ЦФУ? Исторические операции сохранятся.':'Восстановить ЦФУ?';if(!confirm(question))return;\n  document.getElementById('fs-toggle-id').value=document.getElementById('fs-settings-id').value;\n  document.getElementById('fs-toggle-active').value=settingsActive?'0':'1';\n  document.getElementById('fs-cfu-toggle-form').submit();\n });}\n",
    " var settingsToggle=document.getElementById('fs-settings-toggle');\n if(settingsToggle){settingsToggle.addEventListener('click',function(){\n  var question=settingsActive?'Архивировать ЦФУ? Исторические операции сохранятся.':'Восстановить ЦФУ?';if(!confirm(question))return;\n  document.getElementById('fs-toggle-id').value=document.getElementById('fs-settings-id').value;\n  document.getElementById('fs-toggle-active').value=settingsActive?'0':'1';\n  document.getElementById('fs-cfu-toggle-form').submit();\n });}\n var settingsDelete=document.getElementById('fs-settings-delete');\n if(settingsDelete){settingsDelete.addEventListener('click',function(){\n  var name=document.getElementById('fs-settings-name').value||'этот ЦФУ';\n  if(!confirm('Удалить ЦФУ «'+name+'» безвозвратно? Если он уже используется в операциях или правилах, ERP не позволит удалить его.'))return;\n  document.getElementById('fs-delete-id').value=document.getElementById('fs-settings-id').value;\n  document.getElementById('fs-cfu-delete-form').submit();\n });}\n"
)

# DDS modal partial: hard delete button only in edit mode; separate form avoids nested forms.
partial = 'app/View/partials/company_dds_category_form.php'
replace(
    partial,
    ' <div class="modal-foot"><button type="button" class="btn btn-ghost" data-close-modal="<?= $isEdit?\'dds-category-edit-modal\':\'dds-category-create-modal\' ?>">Отмена</button><button type="submit" class="btn btn-primary"><?= $isEdit?\'Сохранить\':\'Создать\' ?></button></div>\n</form>',
    ' <div class="modal-foot"><?php if($isEdit): ?><button type="submit" class="btn btn-ghost fs-delete-action" form="dds-category-delete-form-<?= (int)$category[\'id\'] ?>">Удалить статью</button><?php endif; ?><button type="button" class="btn btn-ghost<?= $isEdit?\'\':\' modal-foot-spacer\' ?>" data-close-modal="<?= $isEdit?\'dds-category-edit-modal\':\'dds-category-create-modal\' ?>">Отмена</button><button type="submit" class="btn btn-primary"><?= $isEdit?\'Сохранить\':\'Создать\' ?></button></div>\n</form>\n<?php if($isEdit): ?><form id="dds-category-delete-form-<?= (int)$category[\'id\'] ?>" method="post" action="<?= app_url(\'/company/finance/settings/dds-categories/delete\') ?>" onsubmit="return confirm(\'Удалить статью ДДС безвозвратно? Она будет удалена из всех ЦФУ. Если статья уже используется в операциях или правилах, ERP не позволит удалить её.\');"><?= csrfField() ?><input type="hidden" name="id" value="<?= (int)$category[\'id\'] ?>"></form><?php endif; ?>'
)

# Keep footer action alignment: delete at left, regular actions at right.
replace(
    view,
    ".fs-delete-action{color:var(--danger)!important}",
    ".fs-delete-action{color:var(--danger)!important;margin-right:auto}"
)

print('P61_PATCH_OK')
