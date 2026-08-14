<?php
$directionLabel = static fn(string $d): string => match ($d) {
    'INCOME' => 'Поступление',
    'EXPENSE' => 'Расход',
    'BOTH' => 'Поступление / расход',
    default => '—',
};
$activeCategories = array_values(array_filter($categories ?? [], static fn(array $c): bool => !empty($c['is_active'])));
?>
<style>
.finance-structure{display:grid;gap:10px}.finance-structure-help{padding:10px 12px;background:var(--surface-muted);border:1px solid var(--line-soft);font-size:11px;color:var(--text-muted)}.fs-card{background:var(--surface-strong);border:1px solid var(--line-soft)}.fs-head{display:grid;grid-template-columns:minmax(240px,1fr) 110px auto;gap:8px;align-items:end;padding:10px 12px;background:var(--surface-form);border-bottom:1px solid var(--line-soft)}.fs-title{font-size:13px;font-weight:700}.fs-meta{font-size:10px;color:var(--text-faint);margin-top:2px}.fs-actions{display:flex;gap:6px;justify-content:flex-end}.fs-body{padding:4px 12px 10px}.fs-article{display:grid;grid-template-columns:minmax(0,1fr) 140px auto;gap:10px;align-items:center;min-height:34px;border-bottom:1px solid var(--line-hair)}.fs-article:last-child{border-bottom:0}.fs-article-name{font-weight:600}.fs-article-meta{font-size:10px;color:var(--text-faint)}.fs-empty{padding:12px 0;color:var(--text-faint);font-size:11px}.fs-footer{display:flex;gap:8px;align-items:center;padding-top:8px}.fs-links{margin-top:8px;border-top:1px solid var(--line-hair);padding-top:8px}.fs-links summary{cursor:pointer;font-size:11px;font-weight:700;color:var(--text-muted)}.fs-link-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:5px 12px;padding:8px 0}.fs-link-item{display:flex;align-items:center;gap:6px;font-size:11px}.fs-link-item input{margin:0;accent-color:var(--accent)}.fs-archived{opacity:.62}.fs-modal-grid{display:grid;grid-template-columns:1fr 120px;gap:10px}.fs-code-note{font-size:10px;color:var(--text-faint);margin-top:6px}@media(max-width:1200px){.fs-link-grid{grid-template-columns:repeat(2,minmax(0,1fr));}}
</style>
<?php if ($company === null): ?>
<div class="notice warn">Компания не найдена.</div>
<?php elseif (($company['status'] ?? '') !== 'active'): ?>
<div class="page-head"><div class="page-head-left"><h1 class="page-title">Финансовая структура</h1><div class="page-summary"><span>Компания находится в неактивном статусе.</span></div></div></div>
<?php elseif ($dbError !== null): ?>
<div class="page-head"><div class="page-head-left"><h1 class="page-title">Финансовая структура</h1></div></div><div class="notice warn"><?= e($dbError) ?></div>
<?php else: ?>
<div class="page-head">
 <div class="page-head-left"><h1 class="page-title">Финансовая структура</h1><div class="page-summary"><span>Настройте дерево ЦФУ → статьи ДДС. В банковской операции будут доступны только статьи выбранного ЦФУ.</span></div></div>
 <div class="page-head-right"><button type="button" class="btn btn-primary btn--toolbar" data-open-modal="fs-cfu-create-modal">+ ЦФУ</button></div>
</div>
<?php if (!empty($successFlash)): ?><div class="notice success"><?= e($successFlash) ?></div><?php endif; ?>
<?php if (!empty($errorFlash)): ?><div class="notice warn"><?= e($errorFlash) ?></div><?php endif; ?>
<div class="finance-structure-help"><strong>Как это работает:</strong> ЦФУ задаёт направление ответственности, статья ДДС — экономический смысл платежа. Технические коды статей скрыты: ERP использует внутренние ID автоматически.</div>

<div class="finance-structure">
<?php if (empty($structure)): ?>
 <div class="panel"><div class="panel-body"><div class="empty-state"><p class="empty-title">ЦФУ ещё не созданы.</p><p class="empty-desc">Создайте первый ЦФУ, затем добавьте к нему статьи ДДС.</p></div></div></div>
<?php endif; ?>
<?php foreach ($structure as $center):
 $centerId=(int)$center['id'];$linked=array_values(array_filter($center['articles']??[],static fn(array $a):bool=>!empty($a['is_active'])));$linkedIds=array_map(static fn(array $a):int=>(int)$a['dds_category_id'],$linked);$active=!empty($center['is_active']); ?>
 <section class="fs-card<?= $active?'':' fs-archived' ?>" data-cfu-id="<?= $centerId ?>">
  <form method="post" action="<?= app_url('/company/finance/settings/dds-categories/cfu/save') ?>" class="fs-head">
   <?= csrfField() ?><input type="hidden" name="id" value="<?= $centerId ?>">
   <div><label class="field-label">ЦФУ</label><input class="field-input" name="name" value="<?= e($center['name']) ?>" required><div class="fs-meta">ID <?= $centerId ?><?= $active?'':' · архив' ?></div></div>
   <div><label class="field-label">Порядок</label><input class="field-input" type="number" name="sort_order" min="0" max="100000" value="<?= (int)($center['sort_order']??100) ?>"></div>
   <div class="fs-actions"><button class="btn btn-ghost" type="submit">Сохранить</button></div>
  </form>
  <div class="fs-body">
   <?php if (empty($linked)): ?><div class="fs-empty">Для этого ЦФУ статьи пока не назначены.</div><?php endif; ?>
   <?php foreach ($linked as $article): ?>
    <div class="fs-article"><div><div class="fs-article-name"><?= e($article['name']) ?></div><div class="fs-article-meta">Статья #<?= (int)$article['dds_category_id'] ?></div></div><div><?= e($directionLabel((string)$article['direction'])) ?></div><button type="button" class="btn btn-ghost btn-sm" data-dds-edit="<?= (int)$article['dds_category_id'] ?>">Изменить</button></div>
   <?php endforeach; ?>
   <?php if ($active): ?>
   <div class="fs-footer"><button type="button" class="btn btn-ghost btn-sm" data-dds-create-cfu="<?= $centerId ?>">+ Новая статья</button>
    <form method="post" action="<?= app_url('/company/finance/settings/dds-categories/cfu/toggle') ?>" data-confirm="Архивировать ЦФУ? Исторические операции сохранятся."><?= csrfField() ?><input type="hidden" name="id" value="<?= $centerId ?>"><input type="hidden" name="active" value="0"><button class="btn btn-ghost btn-sm" type="submit">Архивировать ЦФУ</button></form>
   </div>
   <details class="fs-links"><summary>Настроить состав статей</summary><form method="post" action="<?= app_url('/company/finance/settings/dds-categories/links/save') ?>"><?= csrfField() ?><input type="hidden" name="cash_flow_center_id" value="<?= $centerId ?>"><div class="fs-link-grid"><?php foreach($activeCategories as $cat): $cid=(int)$cat['id']; ?><label class="fs-link-item"><input type="checkbox" name="dds_category_ids[]" value="<?= $cid ?>" <?= in_array($cid,$linkedIds,true)?'checked':'' ?>><span><?= e($cat['name']) ?></span></label><?php endforeach; ?></div><button class="btn btn-primary btn-sm" type="submit">Сохранить состав</button></form></details>
   <?php else: ?>
   <div class="fs-footer"><form method="post" action="<?= app_url('/company/finance/settings/dds-categories/cfu/toggle') ?>"><?= csrfField() ?><input type="hidden" name="id" value="<?= $centerId ?>"><input type="hidden" name="active" value="1"><button class="btn btn-ghost btn-sm" type="submit">Восстановить ЦФУ</button></form></div>
   <?php endif; ?>
  </div>
 </section>
<?php endforeach; ?>
</div>

<div id="fs-cfu-create-modal" class="modal-overlay" role="dialog" aria-modal="true"><div class="modal modal-sm"><div class="modal-head"><span class="modal-title">Новый ЦФУ</span><button type="button" class="modal-close" data-close-modal="fs-cfu-create-modal">&times;</button></div><form method="post" action="<?= app_url('/company/finance/settings/dds-categories/cfu/save') ?>"><?= csrfField() ?><div class="modal-body"><div class="fs-modal-grid"><div class="field"><label class="field-label">Название <span class="field-required">*</span></label><input class="field-input" name="name" required placeholder="Например, Финансы"></div><div class="field"><label class="field-label">Порядок</label><input class="field-input" type="number" name="sort_order" value="100" min="0"></div></div></div><div class="modal-foot"><button type="button" class="btn btn-ghost" data-close-modal="fs-cfu-create-modal">Отмена</button><button type="submit" class="btn btn-primary">Создать</button></div></form></div></div>
<div id="dds-category-create-modal" class="modal-overlay" role="dialog" aria-modal="true"><div class="modal modal-md"><div class="modal-head"><span class="modal-title">Новая статья ДДС</span><button type="button" class="modal-close" data-close-modal="dds-category-create-modal">&times;</button></div><div class="modal-body" id="dds-category-create-modal-body"></div></div></div>
<div id="dds-category-edit-modal" class="modal-overlay" role="dialog" aria-modal="true"><div class="modal modal-md"><div class="modal-head"><span class="modal-title">Редактирование статьи ДДС</span><button type="button" class="modal-close" data-close-modal="dds-category-edit-modal">&times;</button></div><div class="modal-body" id="dds-category-edit-modal-body"></div></div></div>
<script>
document.addEventListener('DOMContentLoaded',function(){
 function load(modalId,bodyId,url){var body=document.getElementById(bodyId);if(!body)return;body.innerHTML='<div class="empty-state compact"><p>Загрузка...</p></div>';window.openModal(modalId);fetch(window.getErpBasePath()+url).then(function(r){return r.text();}).then(function(h){body.innerHTML=h;}).catch(function(){body.innerHTML='<div class="form-alert alert-error">Не удалось загрузить форму.</div>';});}
 document.querySelectorAll('[data-dds-create-cfu]').forEach(function(b){b.addEventListener('click',function(){load('dds-category-create-modal','dds-category-create-modal-body','/company/finance/settings/dds-categories/create?cfu_id='+this.dataset.ddsCreateCfu);});});
 document.querySelectorAll('[data-dds-edit]').forEach(function(b){b.addEventListener('click',function(){load('dds-category-edit-modal','dds-category-edit-modal-body','/company/finance/settings/dds-categories/edit?id='+this.dataset.ddsEdit);});});
 document.querySelectorAll('[data-confirm]').forEach(function(f){f.addEventListener('submit',function(e){if(!confirm(this.dataset.confirm))e.preventDefault();});});
});
</script>
<?php endif; ?>
