<?php

require_once __DIR__ . '/../components/status_badge.php';

?>

<?php if ($company === null): ?>

<div class="panel">
    <div class="panel-body">
        <div class="notice warn">
            Компания не найдена. <a href="/superadmin/companies">← К реестру</a>
        </div>
    </div>
</div>

<?php elseif ($owner === null): ?>

<div class="page-head">
    <div class="page-head-left">
        <span class="page-eyebrow">SUPERADMIN / <?= e($company['name']) ?></span>
        <span class="page-title">Руководитель не создан</span>
        <span class="page-summary"><b>Критический шаг</b><span class="sep">·</span>Компания не готова к работе</span>
    </div>
    <div class="page-head-actions">
        <a href="/superadmin/companies/<?= $company['id'] ?>/create-owner" class="btn btn-primary">Создать руководителя</a>
        <a href="/superadmin/companies/<?= $company['id'] ?>" class="btn btn-ghost">← К карточке компании</a>
    </div>
</div>

<div class="page-content">
    <div class="panel">
        <div class="panel-head">
            <span class="panel-head-title">Первичный доступ компании</span>
            <span class="badge badge-danger">Блокер</span>
        </div>
        <div class="panel-body">
            <div class="empty-state empty-state-left">
                <p class="empty-title">У компании нет руководителя</p>
                <p class="empty-desc">Это не техническая заглушка: без руководителя у компании нет главного ответственного пользователя, которому можно передать первичный доступ и временный пароль.</p>
                <a href="/superadmin/companies/<?= $company['id'] ?>/create-owner" class="btn btn-primary">Создать руководителя</a>
            </div>
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
            <a href="/superadmin/companies/<?= $company['id'] ?>" class="btn btn-ghost">← К карточке компании</a>
        </div>
    </div>
</div>

<?php elseif ($passwordReset): ?>

<div class="page-head">
    <div class="page-head-left">
        <span class="page-eyebrow">SUPERADMIN / <?= e($company['name']) ?></span>
        <span class="page-title"><?= e($owner['full_name']) ?></span>
    </div>
    <div class="page-head-actions">
        <a href="/superadmin/companies/<?= $company['id'] ?>/owner" class="btn btn-ghost">← К карточке</a>
    </div>
</div>

<div class="page-content">
<div class="panel">
    <div class="panel-body">
        <div class="notice success">
            Пароль успешно сброшен.
        </div>

        <dl class="kv">
            <dt>ФИО</dt>
            <dd><?= e($owner['full_name']) ?></dd>
            <dt>Логин</dt>
            <dd><code><?= e($owner['login']) ?></code></dd>
            <dt>Новый временный пароль</dt>
            <dd><code class="code-hi"><?= e($newPassword) ?></code></dd>
        </dl>

        <div class="notice warn">
            Временный пароль показан только один раз. Сохраните его сейчас. Пароль не хранится в открытом виде и не может быть восстановлен.
        </div>

        <div class="form-actions">
            <a href="/superadmin/companies/<?= $company['id'] ?>/owner" class="btn btn-ghost">← К карточке Руководителя</a>
        </div>
    </div>
</div>
</div>

<?php else: ?>

<div class="page-head">
    <div class="page-head-left">
        <span class="page-eyebrow">SUPERADMIN / <?= e($company['name']) ?></span>
        <span class="page-title"><?= e($owner['full_name']) ?></span>
    </div>
    <div class="page-head-actions">
        <a href="/superadmin/companies/<?= $company['id'] ?>/owner/edit" class="btn btn-primary">Редактировать</a>
        <a href="/superadmin/companies/<?= $company['id'] ?>" class="btn btn-ghost">← К карточке компании</a>
    </div>
</div>

<div class="page-content">

<?php if (($_GET['success'] ?? '') === '1'): ?>
    <div class="notice success">Данные сохранены.</div>
<?php endif; ?>

<div class="panel">
    <div class="panel-head">
        <span class="panel-head-title">Основные данные</span>
    </div>
    <div class="panel-body">
        <dl class="kv">
            <dt>ФИО</dt>
            <dd><?= e($owner['full_name']) ?></dd>
            <dt>Логин</dt>
            <dd><code><?= e($owner['login']) ?></code></dd>
            <dt>Должность</dt>
            <dd><?= e($owner['position'] ?? '') ?: '—' ?></dd>
            <dt>Email</dt>
            <dd><?= e($owner['email'] ?? '') ?: '—' ?></dd>
            <dt>Телефон</dt>
            <dd><?= e($owner['phone'] ?? '') ?: '—' ?></dd>
            <dt>Роль</dt>
            <dd>Руководитель</dd>
            <dt>Статус</dt>
            <dd><?= renderStatusBadge($owner['status']) ?></dd>
            <dt>Комментарий</dt>
            <dd><?= e($owner['comments'] ?? '') ?: '—' ?></dd>
            <dt>Создан</dt>
            <dd><?= e($owner['created_at'] ?? '') ?></dd>
            <dt>Обновлён</dt>
            <dd><?= e($owner['updated_at'] ?? '') ?></dd>
        </dl>
    </div>
</div>

<div class="panel">
    <div class="panel-head">
        <span class="panel-head-title">Управление доступом</span>
    </div>
    <div class="panel-body">
        <div class="notice info">
            Сброс пароля заменит текущий пароль Руководителя. Новый временный пароль будет показан только один раз.
        </div>
        <form method="post" action="/superadmin/companies/<?= $company['id'] ?>/owner/reset-password" onsubmit="return confirm('Вы уверены? Руководитель потеряет текущий пароль. Новый пароль будет показан однократно.')">
            <div class="form-actions">
                <button type="submit" class="btn btn-danger">Сбросить пароль</button>
            </div>
        </form>
    </div>
</div>

</div><!-- /.page-content -->

<?php endif; ?>
