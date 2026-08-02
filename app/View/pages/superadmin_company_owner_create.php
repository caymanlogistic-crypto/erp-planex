<?php if ($company === null): ?>

<div class="panel">
    <div class="panel-body">
        <div class="notice warn">
            Компания не найдена. <a href="<?= app_url('/superadmin/companies') ?>">← К реестру</a>
        </div>
    </div>
</div>

<?php elseif ($ownerExists): ?>

<div class="page-head">
    <div class="page-head-left">
        <h1 class="page-title">Создать Руководителя</h1>
        <div class="page-summary"><span>SUPERADMIN / <?= e($company['name']) ?></span></div>
    </div>
    <div class="page-head-actions">
        <a href="<?= app_url('/superadmin/companies') ?>" class="btn btn-ghost">← К реестру</a>
    </div>
</div>

<div class="page-content">
<div class="panel">
    <div class="panel-body">
        <div class="notice warn">
            Руководитель для этой компании уже создан: <strong><?= e($existingOwner['full_name']) ?></strong> (логин: <?= e($existingOwner['login']) ?>).
            Дублирование невозможно.
        </div>
        <div class="form-actions">
            <a href="<?= app_url('/superadmin/companies') ?>" class="btn btn-secondary">← К реестру компаний</a>
        </div>
    </div>
</div>
</div>

<?php elseif ($success): ?>

<div class="page-head">
    <div class="page-head-left">
        <h1 class="page-title">Руководитель создан</h1>
        <div class="page-summary"><span>SUPERADMIN / <?= e($company['name']) ?></span></div>
    </div>
    <div class="page-head-actions">
        <a href="<?= app_url('/superadmin/companies') ?>" class="btn btn-ghost">← К реестру</a>
    </div>
</div>

<div class="page-content">
<div class="panel">
    <div class="panel-body">
        <div class="notice success">
            Главный пользователь успешно создан. Ниже — данные для передачи Руководителю.
        </div>

        <dl class="kv">
            <dt>Компания</dt>
            <dd><?= e($company['name']) ?></dd>
            <dt>ФИО</dt>
            <dd><?= e($createdOwner['full_name']) ?></dd>
            <dt>Логин</dt>
            <dd><code><?= e($createdOwner['login']) ?></code></dd>
            <dt>Временный пароль</dt>
            <dd><code class="code-hi"><?= e($tempPassword) ?></code></dd>
            <dt>Роль</dt>
            <dd>Руководитель</dd>
        </dl>

        <div class="form-actions">
            <a href="<?= app_url('/superadmin/companies') ?>" class="btn btn-secondary">← К реестру компаний</a>
        </div>
    </div>
</div>
</div>

<?php else: ?>

<?php $generatedPassword = $generatedPassword ?? generatePassword(); ?>

<div class="page-head">
    <div class="page-head-left">
        <h1 class="page-title">Создать Руководителя</h1>
        <div class="page-summary"><span>SUPERADMIN / <?= e($company['name']) ?></span></div>
    </div>
    <div class="page-head-actions">
        <a href="<?= app_url('/superadmin/companies/' . $company['id']) ?>" class="btn btn-ghost">← К карточке</a>
    </div>
</div>

<div class="page-content">
<?php
    $isModal = false;
    require base_path('app/View/partials/superadmin_company_owner_create_form.php');
?>
</div>

<?php endif; ?>
