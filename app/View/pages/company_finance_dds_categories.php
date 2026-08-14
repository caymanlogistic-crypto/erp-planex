<?php
$directionLabel = static fn(string $d): string => match ($d) {
    'INCOME' => 'Поступление',
    'EXPENSE' => 'Расход',
    'BOTH' => 'Поступление / расход',
    default => '—',
};

$activeCategories = array_values(array_filter(
    $categories ?? [],
    static fn(array $c): bool => !empty($c['is_active'])
));
$activeCentersCount = count(array_filter(
    $structure ?? [],
    static fn(array $c): bool => !empty($c['is_active'])
));
$defaultCenterId = 0;
foreach (($structure ?? []) as $center) {
    if (!empty($center['is_active'])) {
        $defaultCenterId = (int)$center['id'];
        break;
    }
}
if ($defaultCenterId === 0 && !empty($structure)) {
    $defaultCenterId = (int)$structure[0]['id'];
}

$linkMap = [];
foreach (($structure ?? []) as $center) {
    $linked = array_values(array_filter(
        $center['articles'] ?? [],
        static fn(array $a): bool => !empty($a['is_active'])
    ));
    $linkMap[(int)$center['id']] = array_map(
        static fn(array $a): int => (int)$a['dds_category_id'],
        $linked
    );
}

$structureJs = [
    'articles' => array_map(static fn(array $cat): array => [
        'id' => (int)$cat['id'],
        'name' => (string)$cat['name'],
        'direction' => (string)($cat['direction'] ?? ''),
    ], $activeCategories),
    'links' => $linkMap,
];
?>
<style>
.finance-structure-shell{max-width:1320px;margin:0 auto}.fs-summary-strip{display:flex;align-items:center;justify-content:space-between;gap:16px;margin:8px 0 12px;padding:9px 12px;border:1px solid var(--line-soft);background:var(--surface-form);color:var(--text-muted);font-size:11px}.fs-summary-strip strong{color:var(--text-main)}.fs-workspace{display:grid;grid-template-columns:290px minmax(0,1fr);min-height:600px;border:1px solid var(--line-soft);background:var(--surface-strong)}.fs-sidebar{min-width:0;border-right:1px solid var(--line-soft);background:var(--surface-form)}.fs-sidebar-head{padding:14px 14px 10px;border-bottom:1px solid var(--line-hair)}.fs-sidebar-title{font-size:12px;font-weight:700;color:var(--text-main)}.fs-sidebar-meta{margin-top:2px;color:var(--text-faint);font-size:10px}.fs-sidebar-search{padding:10px 12px 8px}.fs-sidebar-search .field-input{height:31px}.fs-nav{padding:0 8px 10px}.fs-nav-item{position:relative;display:grid;width:100%;grid-template-columns:minmax(0,1fr) auto;gap:8px;align-items:center;margin:2px 0;padding:9px 10px 9px 12px;border:1px solid transparent;border-radius:2px;background:transparent;color:var(--text-main);font:inherit;text-align:left;cursor:pointer}.fs-nav-item:hover{background:var(--surface-strong);border-color:var(--line-hair)}.fs-nav-item.is-active{background:var(--surface-strong);border-color:var(--line-soft);box-shadow:inset 3px 0 0 var(--accent)}.fs-nav-name{display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:11.5px;font-weight:650}.fs-nav-sub{display:block;margin-top:2px;color:var(--text-faint);font-size:9.5px}.fs-nav-count{min-width:24px;padding:2px 5px;border:1px solid var(--line-soft);border-radius:10px;background:var(--surface-muted);color:var(--text-muted);font-size:9.5px;text-align:center}.fs-nav-item.is-archived{opacity:.58}.fs-nav-empty{padding:12px;color:var(--text-faint);font-size:11px}.fs-main{min-width:0;background:var(--surface-strong)}.fs-pane[hidden]{display:none!important}.fs-pane-head{display:flex;align-items:flex-start;justify-content:space-between;gap:18px;padding:18px 20px 14px;border-bottom:1px solid var(--line-soft)}.fs-pane-title{font-size:18px;line-height:1.2;font-weight:700;color:var(--text-main)}.fs-pane-meta{display:flex;align-items:center;gap:7px;margin-top:5px;color:var(--text-faint);font-size:10.5px}.fs-pane-actions{display:flex;align-items:center;gap:7px;flex:0 0 auto}.fs-toolbar{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:10px 20px;border-bottom:1px solid var(--line-hair);background:var(--surface-form)}.fs-toolbar-title{font-size:11px;font-weight:700;color:var(--text-main)}.fs-toolbar-search{width:min(320px,45%)}.fs-toolbar-search .field-input{height:30px}.fs-article-head,.fs-article-row{display:grid;grid-template-columns:minmax(0,1fr) 160px 90px;gap:12px;align-items:center}.fs-article-head{min-height:31px;padding:0 20px;border-bottom:1px solid var(--line-soft);background:var(--surface-muted);color:var(--text-faint);font-size:9.5px;font-weight:700;text-transform:uppercase;letter-spacing:.035em}.fs-article-row{min-height:46px;padding:0 20px;border-bottom:1px solid var(--line-hair)}.fs-article-row:hover{background:var(--surface-form)}.fs-article-name{min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:var(--text-main);font-size:11.5px;font-weight:600}.fs-direction{display:inline-flex;align-items:center;min-height:22px;padding:2px 7px;border:1px solid var(--line-soft);border-radius:10px;background:var(--surface-form);color:var(--text-muted);font-size:10px}.fs-article-action{text-align:right}.fs-empty-state{padding:42px 20px;text-align:center;color:var(--text-faint);font-size:11px}.fs-empty-title{margin-bottom:4px;color:var(--text-main);font-size:12px;font-weight:700}.fs-archived-note{margin:14px 20px;padding:10px 12px;border:1px solid var(--line-soft);background:var(--surface-form);color:var(--text-muted);font-size:11px}.fs-modal-grid{display:grid;grid-template-columns:minmax(0,1fr) 120px;gap:10px}.fs-links-tools{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:10px}.fs-links-tools .field-input{width:300px;max-width:100%}.fs-selected-count{color:var(--text-muted);font-size:10.5px;white-space:nowrap}.fs-link-list{max-height:430px;overflow:auto;border:1px solid var(--line-soft);background:var(--surface-strong)}.fs-link-row{display:grid;grid-template-columns:24px minmax(0,1fr) 145px;gap:8px;align-items:center;min-height:38px;padding:0 10px;border-bottom:1px solid var(--line-hair);cursor:pointer}.fs-link-row:last-child{border-bottom:0}.fs-link-row:hover{background:var(--surface-form)}.fs-link-row input{width:15px;height:15px;margin:0;accent-color:var(--accent)}.fs-link-name{font-size:11px;font-weight:600;color:var(--text-main)}.fs-link-direction{color:var(--text-faint);font-size:10px}.fs-settings-danger{margin-right:auto}.fs-delete-action{color:var(--danger)!important;margin-right:auto}.fs-delete-action:hover{background:var(--danger-bg)!important;border-color:var(--danger)!important}.fs-cfu-create-empty{margin-top:8px}.fs-status-dot{display:inline-block;width:6px;height:6px;border-radius:50%;background:var(--accent)}.fs-status-dot.is-archived{background:var(--text-faint)}
@media(max-width:1180px){.fs-workspace{grid-template-columns:250px minmax(0,1fr)}.fs-article-head,.fs-article-row{grid-template-columns:minmax(0,1fr) 130px 82px}.fs-pane-head{padding-left:16px;padding-right:16px}.fs-toolbar,.fs-article-head,.fs-article-row{padding-left:16px;padding-right:16px}}
@media(max-width:860px){.fs-workspace{display:block}.fs-sidebar{border-right:0;border-bottom:1px solid var(--line-soft)}.fs-nav{display:flex;overflow-x:auto;padding-bottom:8px}.fs-nav-item{min-width:210px}.fs-pane-head{flex-direction:column}.fs-pane-actions{width:100%;flex-wrap:wrap}.fs-toolbar{align-items:flex-start;flex-direction:column}.fs-toolbar-search{width:100%}.fs-article-head{display:none}.fs-article-row{grid-template-columns:minmax(0,1fr) auto;gap:8px;padding-top:8px;padding-bottom:8px}.fs-article-action{grid-column:2;grid-row:1/3}.fs-direction{grid-column:1}.fs-summary-strip{align-items:flex-start;flex-direction:column}.fs-link-row{grid-template-columns:24px minmax(0,1fr)}.fs-link-direction{grid-column:2}}
</style>

<?php if ($company === null): ?>
<div class="notice warn">Компания не найдена.</div>
<?php elseif (($company['status'] ?? '') !== 'active'): ?>
<div class="page-head"><div class="page-head-left"><h1 class="page-title">Финансовая структура</h1><div class="page-summary"><span>Компания находится в неактивном статусе.</span></div></div></div>
<?php elseif ($dbError !== null): ?>
<div class="page-head"><div class="page-head-left"><h1 class="page-title">Финансовая структура</h1></div></div><div class="notice warn"><?= e($dbError) ?></div>
<?php else: ?>
<div class="finance-structure-shell">
 <div class="page-head">
  <div class="page-head-left">
   <h1 class="page-title">Финансовая структура</h1>
   <div class="page-summary"><span>ЦФУ определяет зону ответственности, статья ДДС — экономический смысл платежа.</span></div>
  </div>
  <div class="page-head-right"><button type="button" class="btn btn-primary btn--toolbar" data-open-modal="fs-cfu-create-modal">+ ЦФУ</button></div>
 </div>

 <?php if (!empty($successFlash)): ?><div class="notice success"><?= e($successFlash) ?></div><?php endif; ?>
 <?php if (!empty($errorFlash)): ?><div class="notice warn"><?= e($errorFlash) ?></div><?php endif; ?>

 <div class="fs-summary-strip">
  <div><strong>Структура разнесения</strong> · сначала выбирается ЦФУ, затем доступная для него статья ДДС.</div>
  <div><?= $activeCentersCount ?> ЦФУ · <?= count($activeCategories) ?> <?= count($activeCategories) === 1 ? 'статья' : 'статей' ?></div>
 </div>

 <?php if (empty($structure)): ?>
 <div class="panel fs-cfu-create-empty"><div class="panel-body"><div class="empty-state"><p class="empty-title">ЦФУ ещё не созданы.</p><p class="empty-desc">Создайте первый ЦФУ, затем добавьте к нему статьи ДДС.</p><p><button type="button" class="btn btn-primary" data-open-modal="fs-cfu-create-modal">Создать ЦФУ</button></p></div></div></div>
 <?php else: ?>
 <div class="fs-workspace" data-finance-structure-workspace>
  <aside class="fs-sidebar">
   <div class="fs-sidebar-head"><div class="fs-sidebar-title">Центры финансового учёта</div><div class="fs-sidebar-meta">Выберите ЦФУ для настройки</div></div>
   <div class="fs-sidebar-search"><input type="search" class="field-input" id="fs-cfu-search" placeholder="Найти ЦФУ"></div>
   <div class="fs-nav" id="fs-cfu-nav">
    <?php foreach ($structure as $center):
     $centerId=(int)$center['id'];
     $linked=array_values(array_filter($center['articles']??[],static fn(array $a):bool=>!empty($a['is_active'])));
     $active=!empty($center['is_active']); ?>
    <button type="button" class="fs-nav-item<?= $centerId===$defaultCenterId?' is-active':'' ?><?= $active?'':' is-archived' ?>" data-cfu-nav="<?= $centerId ?>" data-cfu-search-name="<?= e(mb_strtolower((string)$center['name'])) ?>">
     <span><span class="fs-nav-name"><?= e($center['name']) ?></span><span class="fs-nav-sub"><?= $active?'Активен':'В архиве' ?></span></span>
     <span class="fs-nav-count"><?= count($linked) ?></span>
    </button>
    <?php endforeach; ?>
    <div class="fs-nav-empty" id="fs-cfu-search-empty" hidden>Ничего не найдено.</div>
   </div>
  </aside>

  <main class="fs-main">
   <?php foreach ($structure as $center):
    $centerId=(int)$center['id'];
    $linked=array_values(array_filter($center['articles']??[],static fn(array $a):bool=>!empty($a['is_active'])));
    $active=!empty($center['is_active']); ?>
   <section class="fs-pane" data-cfu-pane="<?= $centerId ?>" <?= $centerId===$defaultCenterId?'':'hidden' ?>>
    <header class="fs-pane-head">
     <div>
      <div class="fs-pane-title"><?= e($center['name']) ?></div>
      <div class="fs-pane-meta"><span class="fs-status-dot<?= $active?'':' is-archived' ?>"></span><span><?= $active?'Активный ЦФУ':'ЦФУ в архиве' ?></span><span>·</span><span><?= count($linked) ?> <?= count($linked) === 1 ? 'статья' : 'статей' ?></span></div>
     </div>
     <div class="fs-pane-actions">
      <?php if ($active): ?>
      <button type="button" class="btn btn-primary btn-sm" data-dds-create-cfu="<?= $centerId ?>">+ Статья</button>
      <button type="button" class="btn btn-secondary btn-sm" data-fs-links="<?= $centerId ?>" data-fs-center-name="<?= e($center['name']) ?>">Состав статей</button>
      <?php endif; ?>
      <button type="button" class="btn btn-ghost btn-sm" data-cfu-settings="<?= $centerId ?>" data-cfu-name="<?= e($center['name']) ?>" data-cfu-order="<?= (int)($center['sort_order']??100) ?>" data-cfu-active="<?= $active?'1':'0' ?>">Настройки</button>
     </div>
    </header>

    <?php if (!$active): ?><div class="fs-archived-note">Этот ЦФУ находится в архиве. Исторические операции сохранены, но для новых разнесений он недоступен. Восстановить его можно в «Настройках».</div><?php endif; ?>

    <div class="fs-toolbar">
     <div class="fs-toolbar-title">Статьи ДДС этого ЦФУ</div>
     <div class="fs-toolbar-search"><input type="search" class="field-input" data-article-search="<?= $centerId ?>" placeholder="Поиск по статьям"></div>
    </div>

    <?php if (!empty($linked)): ?>
    <div class="fs-article-list" data-article-list="<?= $centerId ?>">
     <div class="fs-article-head"><div>Статья ДДС</div><div>Направление</div><div></div></div>
     <?php foreach ($linked as $article): ?>
     <div class="fs-article-row" data-article-row data-article-name="<?= e(mb_strtolower((string)$article['name'])) ?>">
      <div class="fs-article-name"><?= e($article['name']) ?></div>
      <div><span class="fs-direction"><?= e($directionLabel((string)$article['direction'])) ?></span></div>
      <div class="fs-article-action"><button type="button" class="btn btn-ghost btn-sm" data-dds-edit="<?= (int)$article['dds_category_id'] ?>">Изменить</button></div>
     </div>
     <?php endforeach; ?>
     <div class="fs-empty-state" data-article-search-empty hidden><div class="fs-empty-title">Статья не найдена</div><div>Измените поисковый запрос.</div></div>
    </div>
    <?php else: ?>
    <div class="fs-empty-state"><div class="fs-empty-title">У ЦФУ пока нет статей</div><div><?= $active?'Добавьте новую статью или подключите существующую через «Состав статей».':'В архивном ЦФУ нет активных статей.' ?></div></div>
    <?php endif; ?>
   </section>
   <?php endforeach; ?>
  </main>
 </div>
 <?php endif; ?>
</div>

<div id="fs-cfu-create-modal" class="modal-overlay" role="dialog" aria-modal="true">
 <div class="modal modal-sm">
  <div class="modal-head"><span class="modal-title">Новый ЦФУ</span><button type="button" class="modal-close" data-close-modal="fs-cfu-create-modal">&times;</button></div>
  <form method="post" action="<?= app_url('/company/finance/settings/dds-categories/cfu/save') ?>">
   <?= csrfField() ?>
   <div class="modal-body"><div class="fs-modal-grid"><div class="field"><label class="field-label">Название <span class="field-required">*</span></label><input class="field-input" name="name" required placeholder="Например, Финансы"></div><div class="field"><label class="field-label">Порядок</label><input class="field-input" type="number" name="sort_order" value="100" min="0"></div></div></div>
   <div class="modal-foot"><button type="button" class="btn btn-ghost" data-close-modal="fs-cfu-create-modal">Отмена</button><button type="submit" class="btn btn-primary">Создать</button></div>
  </form>
 </div>
</div>

<div id="fs-cfu-settings-modal" class="modal-overlay" role="dialog" aria-modal="true">
 <div class="modal modal-sm">
  <div class="modal-head"><span class="modal-title">Настройки ЦФУ</span><button type="button" class="modal-close" data-close-modal="fs-cfu-settings-modal">&times;</button></div>
  <form id="fs-cfu-settings-form" method="post" action="<?= app_url('/company/finance/settings/dds-categories/cfu/save') ?>">
   <?= csrfField() ?><input type="hidden" name="id" id="fs-settings-id">
   <div class="modal-body"><div class="fs-modal-grid"><div class="field"><label class="field-label">Название <span class="field-required">*</span></label><input class="field-input" name="name" id="fs-settings-name" required></div><div class="field"><label class="field-label">Порядок</label><input class="field-input" type="number" name="sort_order" id="fs-settings-order" min="0" max="100000"></div></div></div>
  </form>
  <div class="modal-foot"><button type="button" class="btn btn-ghost fs-settings-danger" id="fs-settings-toggle"></button><button type="button" class="btn btn-ghost fs-delete-action" id="fs-settings-delete">Удалить ЦФУ</button><button type="button" class="btn btn-ghost" data-close-modal="fs-cfu-settings-modal">Отмена</button><button type="submit" class="btn btn-primary" form="fs-cfu-settings-form">Сохранить</button></div>
 </div>
</div>
<form method="post" action="<?= app_url('/company/finance/settings/dds-categories/cfu/toggle') ?>" id="fs-cfu-toggle-form" hidden><?= csrfField() ?><input type="hidden" name="id" id="fs-toggle-id"><input type="hidden" name="active" id="fs-toggle-active"></form>
<form method="post" action="<?= app_url('/company/finance/settings/dds-categories/cfu/delete') ?>" id="fs-cfu-delete-form" hidden><?= csrfField() ?><input type="hidden" name="id" id="fs-delete-id"></form>

<div id="fs-links-modal" class="modal-overlay" role="dialog" aria-modal="true">
 <div class="modal modal-md">
  <div class="modal-head"><span class="modal-title" id="fs-links-title">Состав статей</span><button type="button" class="modal-close" data-close-modal="fs-links-modal">&times;</button></div>
  <form method="post" action="<?= app_url('/company/finance/settings/dds-categories/links/save') ?>" id="fs-links-form">
   <?= csrfField() ?><input type="hidden" name="cash_flow_center_id" id="fs-links-center-id">
   <div class="modal-body">
    <div class="fs-links-tools"><input type="search" class="field-input" id="fs-links-search" placeholder="Найти статью"><span class="fs-selected-count" id="fs-link-selected-count"></span></div>
    <div class="fs-link-list" id="fs-link-list"></div>
   </div>
   <div class="modal-foot"><button type="button" class="btn btn-ghost" data-close-modal="fs-links-modal">Отмена</button><button type="submit" class="btn btn-primary">Сохранить состав</button></div>
  </form>
 </div>
</div>

<div id="dds-category-create-modal" class="modal-overlay" role="dialog" aria-modal="true"><div class="modal modal-md"><div class="modal-head"><span class="modal-title">Новая статья ДДС</span><button type="button" class="modal-close" data-close-modal="dds-category-create-modal">&times;</button></div><div class="fs-remote-form" id="dds-category-create-modal-body"></div></div></div>
<div id="dds-category-edit-modal" class="modal-overlay" role="dialog" aria-modal="true"><div class="modal modal-md"><div class="modal-head"><span class="modal-title">Редактирование статьи ДДС</span><button type="button" class="modal-close" data-close-modal="dds-category-edit-modal">&times;</button></div><div class="fs-remote-form" id="dds-category-edit-modal-body"></div></div></div>

<script>
document.addEventListener('DOMContentLoaded',function(){
 var structureData=<?= json_encode($structureJs, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;
 var directionLabels={INCOME:'Поступление',EXPENSE:'Расход',BOTH:'Поступление / расход'};

 function selectCenter(id){
  id=String(id);
  document.querySelectorAll('[data-cfu-nav]').forEach(function(btn){btn.classList.toggle('is-active',btn.dataset.cfuNav===id);});
  document.querySelectorAll('[data-cfu-pane]').forEach(function(pane){pane.hidden=pane.dataset.cfuPane!==id;});
 }
 document.querySelectorAll('[data-cfu-nav]').forEach(function(btn){btn.addEventListener('click',function(){selectCenter(this.dataset.cfuNav);});});

 var cfuSearch=document.getElementById('fs-cfu-search');
 if(cfuSearch){cfuSearch.addEventListener('input',function(){
  var q=this.value.trim().toLocaleLowerCase('ru-RU'),visible=[];
  document.querySelectorAll('[data-cfu-nav]').forEach(function(btn){var show=!q||(btn.dataset.cfuSearchName||'').indexOf(q)!==-1;btn.hidden=!show;if(show)visible.push(btn);});
  var empty=document.getElementById('fs-cfu-search-empty');if(empty)empty.hidden=visible.length>0;
  var current=document.querySelector('[data-cfu-nav].is-active');if(visible.length&&current&&current.hidden)selectCenter(visible[0].dataset.cfuNav);
 });}

 document.querySelectorAll('[data-article-search]').forEach(function(input){input.addEventListener('input',function(){
  var pane=document.querySelector('[data-cfu-pane="'+this.dataset.articleSearch+'"]');if(!pane)return;
  var q=this.value.trim().toLocaleLowerCase('ru-RU'),shown=0;
  pane.querySelectorAll('[data-article-row]').forEach(function(row){var show=!q||(row.dataset.articleName||'').indexOf(q)!==-1;row.hidden=!show;if(show)shown++;});
  var empty=pane.querySelector('[data-article-search-empty]');if(empty)empty.hidden=shown>0;
 });});

 function load(modalId,bodyId,url){
  var body=document.getElementById(bodyId);if(!body)return;
  body.innerHTML='<div class="empty-state compact"><p>Загрузка...</p></div>';
  window.openModal(modalId);
  fetch(window.getErpBasePath()+url,{credentials:'same-origin'}).then(function(r){if(!r.ok)throw new Error('HTTP '+r.status);return r.text();}).then(function(h){body.innerHTML=h;}).catch(function(){body.innerHTML='<div class="form-alert alert-error">Не удалось загрузить форму.</div>';});
 }
 document.querySelectorAll('[data-dds-create-cfu]').forEach(function(b){b.addEventListener('click',function(){load('dds-category-create-modal','dds-category-create-modal-body','/company/finance/settings/dds-categories/create?cfu_id='+this.dataset.ddsCreateCfu);});});
 document.querySelectorAll('[data-dds-edit]').forEach(function(b){b.addEventListener('click',function(){load('dds-category-edit-modal','dds-category-edit-modal-body','/company/finance/settings/dds-categories/edit?id='+this.dataset.ddsEdit);});});

 var settingsActive=true;
 document.querySelectorAll('[data-cfu-settings]').forEach(function(btn){btn.addEventListener('click',function(){
  document.getElementById('fs-settings-id').value=this.dataset.cfuSettings;
  document.getElementById('fs-settings-name').value=this.dataset.cfuName||'';
  document.getElementById('fs-settings-order').value=this.dataset.cfuOrder||100;
  settingsActive=this.dataset.cfuActive==='1';
  var toggle=document.getElementById('fs-settings-toggle');toggle.textContent=settingsActive?'Архивировать ЦФУ':'Восстановить ЦФУ';
  window.openModal('fs-cfu-settings-modal');
 });});
 var settingsToggle=document.getElementById('fs-settings-toggle');
 if(settingsToggle){settingsToggle.addEventListener('click',function(){
  var question=settingsActive?'Архивировать ЦФУ? Исторические операции сохранятся.':'Восстановить ЦФУ?';if(!confirm(question))return;
  document.getElementById('fs-toggle-id').value=document.getElementById('fs-settings-id').value;
  document.getElementById('fs-toggle-active').value=settingsActive?'0':'1';
  document.getElementById('fs-cfu-toggle-form').submit();
 });}
 var settingsDelete=document.getElementById('fs-settings-delete');
 if(settingsDelete){settingsDelete.addEventListener('click',function(){
  var name=document.getElementById('fs-settings-name').value||'этот ЦФУ';
  if(!confirm('Удалить ЦФУ «'+name+'» безвозвратно? Если он уже используется в операциях или правилах, ERP не позволит удалить его.'))return;
  document.getElementById('fs-delete-id').value=document.getElementById('fs-settings-id').value;
  document.getElementById('fs-cfu-delete-form').submit();
 });}

 var linksModalCenter=0,linksSearch=document.getElementById('fs-links-search');
 function updateSelectedCount(){var list=document.getElementById('fs-link-list');if(!list)return;var selected=list.querySelectorAll('input[type="checkbox"]:checked').length;document.getElementById('fs-link-selected-count').textContent='Выбрано: '+selected;}
 function filterLinks(){var list=document.getElementById('fs-link-list');if(!list)return;var q=(linksSearch.value||'').trim().toLocaleLowerCase('ru-RU');list.querySelectorAll('[data-fs-link-row]').forEach(function(row){row.hidden=!!q&&(row.dataset.searchText||'').indexOf(q)===-1;});}
 function buildLinks(centerId,centerName){
  linksModalCenter=Number(centerId||0);document.getElementById('fs-links-center-id').value=String(linksModalCenter);document.getElementById('fs-links-title').textContent='Состав статей · '+centerName;linksSearch.value='';
  var selected=(structureData.links[linksModalCenter]||structureData.links[String(linksModalCenter)]||[]).map(Number),list=document.getElementById('fs-link-list');list.innerHTML='';
  structureData.articles.forEach(function(article){
   var label=document.createElement('label');label.className='fs-link-row';label.dataset.fsLinkRow='1';label.dataset.searchText=(article.name+' '+(directionLabels[article.direction]||'')).toLocaleLowerCase('ru-RU');
   var checkbox=document.createElement('input');checkbox.type='checkbox';checkbox.name='dds_category_ids[]';checkbox.value=String(article.id);checkbox.checked=selected.indexOf(Number(article.id))!==-1;checkbox.addEventListener('change',updateSelectedCount);
   var name=document.createElement('span');name.className='fs-link-name';name.textContent=article.name;
   var direction=document.createElement('span');direction.className='fs-link-direction';direction.textContent=directionLabels[article.direction]||'—';
   label.appendChild(checkbox);label.appendChild(name);label.appendChild(direction);list.appendChild(label);
  });
  if(!structureData.articles.length){list.innerHTML='<div class="fs-empty-state"><div class="fs-empty-title">Нет доступных статей</div><div>Сначала создайте статью ДДС.</div></div>';}
  updateSelectedCount();window.openModal('fs-links-modal');
 }
 document.querySelectorAll('[data-fs-links]').forEach(function(btn){btn.addEventListener('click',function(){buildLinks(this.dataset.fsLinks,this.dataset.fsCenterName||'ЦФУ');});});
 if(linksSearch)linksSearch.addEventListener('input',filterLinks);
});
</script>
<?php endif; ?>
