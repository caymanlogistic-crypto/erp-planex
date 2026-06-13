<?php

function statusBadge(string $status): string
{
    $map = [
        'active'       => ['class' => 'badge-ok',    'label' => 'Активен'],
        'provisioning' => ['class' => 'badge-warn',  'label' => 'Настройка'],
        'error'        => ['class' => 'badge-danger','label' => 'Ошибка'],
        'inactive'     => ['class' => '',             'label' => 'Неактивен'],
        'blocked'      => ['class' => '',             'label' => 'Заблокирован'],
        'suspended'    => ['class' => 'badge-warn',  'label' => 'Приостановлен'],
    ];

    $item = $map[$status] ?? ['class' => '', 'label' => $status];

    return '<span class="badge ' . $item['class'] . '"><span class="dot"></span>' . e($item['label']) . '</span>';
}

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

<div class="panel">
    <div class="panel-body">
        <div class="notice warn">
            Руководитель не создан. <a href="/superadmin/companies/<?= $company['id'] ?>/create-owner">Создать Руководителя</a>
        </div>
    </div>
</div>

<?php elseif (isset($dbError)): ?>

<div class="panel">
    <div class="panel-body">
        <div class="notice danger">
            <?= e($dbError) ?>
        </div>
        <div class="form-actions" style="margin-top:16px">
            <a href="/superadmin/companies/<?= $company['id'] ?>" class="btn btn-ghost">← К карточке компании</a>
        </div>
    </div>
</div>

<?php elseif ($passwordReset): ?>

<div class="page-head">
    <div>
        <h1>Руководитель: <?= e($owner['full_name']) ?></h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?> (ID: <?= $company['id'] ?>)</p>
    </div>
    <div class="page-head-actions">
        <a href="/superadmin/companies/<?= $company['id'] ?>/owner" class="btn btn-ghost">← К карточке Руководителя</a>
    </div>
</div>

<div class="panel">
    <div class="panel-body">
        <div class="notice success">
            Пароль успешно сброшен.
        </div>

        <dl class="kv" style="margin-top:16px">
            <dt>ФИО</dt>
            <dd><?= e($owner['full_name']) ?></dd>
            <dt>Логин</dt>
            <dd><code><?= e($owner['login']) ?></code></dd>
            <dt>Новый временный пароль</dt>
            <dd><code style="background:var(--warning-bg);padding:2px 6px;border-radius:3px"><?= e($newPassword) ?></code></dd>
        </dl>

        <div class="notice warn" style="margin-top:16px">
            Временный пароль показан только один раз. Сохраните его сейчас. Пароль не хранится в открытом виде и не может быть восстановлен.
        </div>

        <div class="form-actions" style="margin-top:16px">
            <a href="/superadmin/companies/<?= $company['id'] ?>/owner" class="btn btn-ghost">← К карточке Руководителя</a>
        </div>
    </div>
</div>

<?php else: ?>

<div class="page-head">
    <div>
        <h1>Руководитель: <?= e($owner['full_name']) ?></h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?> (ID: <?= $company['id'] ?>)</p>
    </div>
    <div class="page-head-actions">
        <a href="/superadmin/companies/<?= $company['id'] ?>/owner/edit" class="btn btn-primary">Редактировать</a>
        <a href="/superadmin/companies/<?= $company['id'] ?>" class="btn btn-ghost">← К карточке компании</a>
    </div>
</div>

<div class="panel">
    <div class="panel-body">

        <div class="form-section">
            <h3 class="panel-head-title">Основные данные</h3>
            <dl class="kv">
                <dt>ФИО</dt>
                <dd><?= e($owner['full_name']) ?></dd>
                <dt>Логин</dt>
                <dd><code><?= e($owner['login']) ?></code></dd>
                <dt>Email</dt>
                <dd><?= e($owner['email'] ?? '') ?: '—' ?></dd>
                <dt>Телефон</dt>
                <dd><?= e($owner['phone'] ?? '') ?: '—' ?></dd>
                <dt>Роль</dt>
                <dd>Руководитель</dd>
                <dt>Статус</dt>
                <dd><?= statusBadge($owner['status']) ?></dd>
                <dt>Комментарий</dt>
                <dd><?= e($owner['comments'] ?? '') ?: '—' ?></dd>
                <dt>Создан</dt>
                <dd><?= e($owner['created_at'] ?? '') ?></dd>
                <dt>Обновлён</dt>
                <dd><?= e($owner['updated_at'] ?? '') ?></dd>
            </dl>
        </div>

        <div class="form-section">
            <h3 class="panel-head-title">Действия</h3>
            <form method="post" action="/superadmin/companies/<?= $company['id'] ?>/owner/reset-password">
                <p class="text-muted" style="margin-bottom:12px">Вы уверены? Текущий пароль будет заменён. Новый пароль будет показан только один раз.</p>
                <button type="submit" class="btn btn-danger">Сбросить пароль</button>
            </form>
        </div>

    </div>
</div>

<?php endif; ?>
