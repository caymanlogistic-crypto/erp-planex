<?php
$category=$category??null;$formError=$formError??null;$isEdit=$category!==null;$cfuId=(int)($cfuId??0);
$action=$isEdit?app_url('/company/finance/settings/dds-categories/edit'):app_url('/company/finance/settings/dds-categories/create');
?>
<?php if($formError): ?><div class="form-alert alert-error"><?= e($formError) ?></div><?php endif; ?>
<form action="<?= $action ?>" method="post" class="dds-category-form">
 <?= csrfField() ?>
 <?php if($isEdit): ?><input type="hidden" name="id" value="<?= (int)$category['id'] ?>"><?php endif; ?>
 <?php if(!$isEdit&&$cfuId>0): ?><input type="hidden" name="cfu_id" value="<?= $cfuId ?>"><?php endif; ?>
 <div class="modal-body">
  <div class="section-title"><?= $isEdit?'Статья ДДС #'.(int)$category['id']:'Новая статья ДДС' ?></div>
  <div class="form-grid-3">
   <div class="field" style="grid-column:span 2"><label class="field-label">Название <span class="field-required">*</span></label><input type="text" name="name" class="field-input" placeholder="Например, Банковская комиссия" required value="<?= e($isEdit?$category['name']:'') ?>"></div>
   <div class="field"><label class="field-label">Направление <span class="field-required">*</span></label><select name="direction" class="field-select" required><option value="INCOME" <?= $isEdit&&$category['direction']==='INCOME'?'selected':'' ?>>Поступление</option><option value="EXPENSE" <?= (!$isEdit||$category['direction']==='EXPENSE')?'selected':'' ?>>Расход</option><option value="BOTH" <?= $isEdit&&$category['direction']==='BOTH'?'selected':'' ?>>Поступление / расход</option></select></div>
   <div class="field"><label class="field-label">Порядок</label><input type="number" name="sort_order" class="field-input" value="<?= (int)($isEdit?$category['sort_order']:100) ?>" min="0"></div>
  </div>
  <div class="field-msg" style="margin-top:8px">ID и технический код ERP назначает автоматически. Вводить их вручную не нужно.</div>
 </div>
 <div class="modal-foot"><button type="button" class="btn btn-ghost" data-close-modal="<?= $isEdit?'dds-category-edit-modal':'dds-category-create-modal' ?>">Отмена</button><button type="submit" class="btn btn-primary"><?= $isEdit?'Сохранить':'Создать' ?></button></div>
</form>
