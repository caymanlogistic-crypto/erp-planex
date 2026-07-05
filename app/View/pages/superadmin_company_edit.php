<?php

require_once __DIR__ . '/../components/status_badge.php';

?>

<?php if ($company === null): ?>

<div class="panel">
    <div class="panel-body">
        <div class="notice warn">
            Компания не найдена. <a href="<?= app_url('/superadmin/companies') ?>">← К реестру</a>
        </div>
    </div>
</div>

<?php elseif (isset($dbError)): ?>

<div class="panel">
    <div class="panel-body">
        <div class="notice danger">
            <?= e($dbError) ?>
        </div>
        <div class="form-actions mt-4">
            <a href="<?= app_url('/superadmin/companies') ?>" class="btn btn-ghost">← К реестру</a>
        </div>
    </div>
</div>

<?php else: ?>

<div class="page-head">
    <div class="page-head-left">
        <span class="page-eyebrow">SUPERADMIN / <?= e($company['name']) ?></span>
        <span class="page-title">Редактировать компанию</span>
    </div>
    <div class="page-head-actions">
        <a href="/superadmin/companies/<?= $company['id'] ?>" class="btn btn-ghost">← К карточке</a>
    </div>
</div>

<div class="page-content">

<?php if (!empty($formError)): ?>
    <div class="notice warn"><?= e($formError) ?></div>
<?php endif; ?>

<?php
    $leEntityType = 'company';
    $leFormAction = app_url('/superadmin/companies/' . ((int) ($company['id'] ?? 0)) . '/edit');
    $leFormId = 'company-edit-form';
    $leIsModal = false;
    $leOld = $old ?? $company ?? [];
    $leErrors = $errors ?? [];
    $leFormError = $formError ?? null;
    $leSubmitLabel = 'Сохранить';
    $leShowContacts = false;
    $leShowBankDetails = true;
    $leShowDocuments = true;
    $leShowStatus = true;
    $leShowInlineActions = true;
    $leInnLookupUrl = app_url('/superadmin/requisites/lookup-by-inn');
    require base_path('app/View/partials/legal_entity_create_form.php');
?>

</div><!-- /.page-content -->

<?php endif; ?>
