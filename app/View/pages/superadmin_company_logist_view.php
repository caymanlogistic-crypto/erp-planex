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

<?php elseif (isset($dbError)): ?>

<div class="panel">
    <div class="panel-body">
        <div class="notice danger">
            <?= e($dbError) ?>
        </div>
        <div class="form-actions" style="margin-top:16px">
            <a href="/superadmin/companies" class="btn btn-ghost">← К реестру</a>
        </div>
    </div>
</div>

<?php elseif ($logist === null): ?>

<div class="panel">
    <div class="panel-body">
        <div class="notice warn">
            Логист не найден. <a href="/superadmin/companies/<?= $companyId ?>/users">← К пользователям</a>
        </div>
    </div>
</div>

<?php elseif ($passwordReset): ?>

<div class="page-head">
    <div>
        <h1>Пароль сброшен</h1>
        <p class="text-muted">Логист: <?= e($logist['full_name']) ?> · Компания: <?= e($company['name']) ?></p>
    </div>
    <div class="page-head-actions">
        <a href="/superadmin/companies/<?= $companyId ?>/users/logists/<?= $logistId ?>" class="btn btn-ghost">← К карточке логиста</a>
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
            <dd><code style="background:var(--warning-bg);padding:2px 6px;border-radius:2px"><?= e($newPassword) ?></code></dd>
            <dt>Роль</dt>
            <dd>Логист</dd>
        </dl>

        <div class="notice warn" style="margin-top:16px">
            Временный пароль показан только один раз. Сохраните его сейчас. Пароль не хранится в открытом виде и не может быть восстановлен.
        </div>
    </div>
</div>

<?php else: ?>

<?php if (($_GET['status_changed'] ?? '') === '1'): ?>
    <div class="notice success">Статус изменён.</div>
<?php endif; ?>

<div class="page-head">
    <div>
        <h1>Логист: <?= e($logist['full_name']) ?></h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?> (ID: <?= $companyId ?>)</p>
    </div>
    <div class="page-head-actions">
        <a href="/superadmin/companies/<?= $companyId ?>/users" class="btn btn-ghost">← К пользователям</a>
        <a href="/superadmin/companies/<?= $companyId ?>/users/logists/<?= $logistId ?>/edit" class="btn btn-primary">Редактировать</a>
    </div>
</div>

<div class="panel">
    <div class="panel-head">
        <h2>Основные данные</h2>
    </div>
    <div class="panel-body">
        <dl class="kv">
            <dt>ФИО</dt>
            <dd><?= e($logist['full_name']) ?></dd>
            <dt>Логин</dt>
            <dd><code><?= e($logist['login']) ?></code></dd>
            <dt>Email</dt>
            <dd><?= e($logist['email'] ?? '—') ?></dd>
            <dt>Телефон</dt>
            <dd><?= e($logist['phone'] ?? '—') ?></dd>
            <dt>Роль</dt>
            <dd>Логист</dd>
            <dt>Статус</dt>
            <dd><?= renderStatusBadge($logist['status']) ?></dd>
            <dt>Комментарий</dt>
            <dd><?= e($logist['comments'] ?? '—') ?></dd>
            <dt>Создан</dt>
            <dd><?= e($logist['created_at'] ?? '—') ?></dd>
            <dt>Обновлён</dt>
            <dd><?= e($logist['updated_at'] ?? '—') ?></dd>
        </dl>
    </div>
</div>

<div class="panel">
    <div class="panel-head">
        <h2>Созданные записи</h2>
    </div>
    <div class="panel-body">
        <dl class="kv">
            <dt>Клиенты</dt>
            <dd><?= (int)($counts['clients'] ?? 0) ?></dd>
            <dt>Подрядчики</dt>
            <dd><?= (int)($counts['contractors'] ?? 0) ?></dd>
            <dt>Водители</dt>
            <dd><?= (int)($counts['drivers'] ?? 0) ?></dd>
            <dt>Транспорт</dt>
            <dd><?= (int)($counts['vehicles'] ?? 0) ?></dd>
            <dt>Экипажи</dt>
            <dd><?= (int)($counts['crews'] ?? 0) ?></dd>
            <dt>Документы</dt>
            <dd><?= (int)($counts['documents'] ?? 0) ?></dd>
        </dl>
    </div>
</div>

<div class="panel">
    <div class="panel-head">
        <h2>Доступы</h2>
    </div>
    <div class="panel-body">
        <dl class="kv">
            <dt>Выдано доступов</dt>
            <dd><?= (int)($grantsCount ?? 0) ?></dd>
        </dl>
    </div>
</div>

<div class="panel">
    <div class="panel-head">
        <h2>Действия</h2>
    </div>
    <div class="panel-body">
        <div class="form-actions">
            <?php if ($logist['status'] !== 'active'): ?>
            <form method="post" action="/superadmin/companies/<?= $companyId ?>/users/logists/<?= $logistId ?>/activate" style="display:inline" onsubmit="return confirm('Активировать логиста?')">
                <button type="submit" class="btn btn-primary">Активировать</button>
            </form>
            <?php endif; ?>
            <?php if ($logist['status'] === 'active'): ?>
            <form method="post" action="/superadmin/companies/<?= $companyId ?>/users/logists/<?= $logistId ?>/block" style="display:inline" onsubmit="return confirm('Заблокировать логиста?')">
                <button type="submit" class="btn btn-danger">Заблокировать</button>
            </form>
            <?php endif; ?>
            <form method="post" action="/superadmin/companies/<?= $companyId ?>/users/logists/<?= $logistId ?>/archive" style="display:inline" onsubmit="return confirm('Архивировать логиста?')">
                <button type="submit" class="btn btn-danger">Архивировать</button>
            </form>
            <form method="post" action="/superadmin/companies/<?= $companyId ?>/users/logists/<?= $logistId ?>/reset-password" style="display:inline" onsubmit="return confirm('Сбросить пароль логиста? Текущий пароль будет заменён. Новый пароль будет показан только один раз.')">
                <button type="submit" class="btn btn-danger">Сбросить пароль</button>
            </form>
        </div>
    </div>
</div>

<?php endif; ?>
