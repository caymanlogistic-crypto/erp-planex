<?php

function statusBadge(string $status): string
{
    $map = [
        'active'       => ['class' => 'badge-ok',    'label' => 'Активен'],
        'blocked'      => ['class' => '',             'label' => 'Заблокирован'],
        'archived'     => ['class' => 'badge-warn',  'label' => 'Архив'],
    ];

    $item = $map[$status] ?? ['class' => '', 'label' => $status];

    return '<span class="badge ' . $item['class'] . '"><span class="dot"></span>' . e($item['label']) . '</span>';
}

?>

<?php if ($company === null): ?>

<div class="notice warn">
    Компания не найдена. Укажите корректный company_id.
</div>

<?php elseif ($company['status'] !== 'active'): ?>

<div class="page-head">
    <div>
        <h1>Логисты</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?> (ID: <?= $company['id'] ?>)</p>
    </div>
    <div class="page-head-actions">
        <a href="/company/logists" class="btn btn-ghost">← К списку</a>
    </div>
</div>

<div class="notice warn">
    Компания находится в статусе «<?= e($company['status']) ?>».
</div>

<?php elseif ($logist === null): ?>

<div class="page-head">
    <div>
        <h1>Логист</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?></p>
    </div>
    <div class="page-head-actions">
        <a href="/company/logists" class="btn btn-ghost">← К списку</a>
    </div>
</div>

<div class="notice warn">
    Логист не найден.
</div>

<?php elseif (isset($dbError)): ?>

<div class="page-head">
    <div>
        <h1>Логист</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?></p>
    </div>
    <div class="page-head-actions">
        <a href="/company/logists" class="btn btn-ghost">← К списку</a>
    </div>
</div>

<div class="notice danger">
    <?= e($dbError) ?>
</div>

<?php elseif ($passwordReset): ?>

<div class="page-head">
    <div>
        <h1>Логист: <?= e($logist['full_name']) ?></h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?> (ID: <?= $company['id'] ?>)</p>
    </div>
    <div class="page-head-actions">
        <a href="/company/logists/<?= $logist['id'] ?>" class="btn btn-ghost">← К карточке логиста</a>
    </div>
</div>

<div class="panel">
    <div class="panel-body">
        <div class="notice success">
            Пароль успешно сброшен.
        </div>

        <dl class="kv" style="margin-top:16px">
            <dt>ФИО</dt>
            <dd><?= e($logist['full_name']) ?></dd>
            <dt>Логин</dt>
            <dd><code><?= e($logist['login']) ?></code></dd>
            <dt>Новый временный пароль</dt>
            <dd><code style="background:var(--warning-bg);padding:2px 6px;border-radius:3px"><?= e($newPassword) ?></code></dd>
        </dl>

        <div class="notice warn" style="margin-top:16px">
            Временный пароль показан только один раз. Сохраните его сейчас. Пароль не хранится в открытом виде и не может быть восстановлен.
        </div>

        <div class="form-actions" style="margin-top:16px">
            <a href="/company/logists/<?= $logist['id'] ?>" class="btn btn-ghost">← К карточке логиста</a>
        </div>
    </div>
</div>

<?php else: ?>

<div class="page-head">
    <div>
        <h1>Логист: <?= e($logist['full_name']) ?></h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?> (ID: <?= $company['id'] ?>)</p>
    </div>
    <div class="page-head-actions">
        <a href="/company/logists/<?= $logist['id'] ?>/edit" class="btn btn-primary">Редактировать</a>
        <a href="/company/logists" class="btn btn-ghost">← К списку</a>
    </div>
</div>

<div class="panel">
    <div class="panel-body">

        <div class="form-section">
            <h3 class="panel-head-title">Основные данные</h3>
            <dl class="kv">
                <dt>ФИО</dt>
                <dd><?= e($logist['full_name']) ?></dd>
                <dt>Логин</dt>
                <dd><code><?= e($logist['login']) ?></code></dd>
                <dt>Email</dt>
                <dd><?= e($logist['email'] ?? '') ?: '—' ?></dd>
                <dt>Телефон</dt>
                <dd><?= e($logist['phone'] ?? '') ?: '—' ?></dd>
                <dt>Роль</dt>
                <dd>Логист</dd>
                <dt>Статус</dt>
                <dd><?= statusBadge($logist['status']) ?></dd>
            </dl>
        </div>

        <div class="form-section">
            <h3 class="panel-head-title">Техническая информация</h3>
            <dl class="kv">
                <dt>Дата создания</dt>
                <dd><?= e($logist['created_at'] ?? '') ?></dd>
                <dt>Дата обновления</dt>
                <dd><?= e($logist['updated_at'] ?? '') ?></dd>
            </dl>
        </div>

        <div class="form-section">
            <h3 class="panel-head-title">Действия</h3>
            <form method="post" action="/company/logists/<?= $logist['id'] ?>/reset-password" style="margin-bottom:12px">
                <p class="text-muted" style="margin-bottom:8px">Вы уверены? Текущий пароль будет заменён. Новый пароль будет показан только один раз.</p>
                <button type="submit" class="btn btn-warn">Сбросить пароль</button>
            </form>
            <?php if ($logist['status'] !== 'archived'): ?>
            <form method="post" action="/company/logists/<?= $logist['id'] ?>/archive" onsubmit="return confirm('Вы уверены, что хотите архивировать логиста?')">
                <button type="submit" class="btn btn-warn">Архивировать</button>
            </form>
            <?php endif; ?>
        </div>

        <div class="form-actions" style="margin-top:16px">
            <a href="/company/logists/<?= $logist['id'] ?>/edit" class="btn btn-primary">Редактировать</a>
            <a href="/company/logists" class="btn btn-ghost">← К списку</a>
        </div>

    </div>
</div>

<?php endif; ?>
