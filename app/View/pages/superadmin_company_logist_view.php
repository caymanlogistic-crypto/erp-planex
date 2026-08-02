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

<?php elseif ($logist === null): ?>

<div class="panel">
    <div class="panel-body">
        <div class="notice warn">
            Пользователь не найден. <a href="/superadmin/companies/<?= $companyId ?>/users">← К пользователям</a>
        </div>
    </div>
</div>

<?php elseif ($passwordReset): ?>

<div class="page-head">
    <div class="page-head-left">
        <h1 class="page-title">Пароль сброшен</h1>
        <div class="page-summary"><span>SUPERADMIN / <?= e($company['name']) ?></span></div>
    </div>
    <div class="page-head-actions">
        <a href="/superadmin/companies/<?= $companyId ?>/users/logists/<?= $logistId ?>" class="btn btn-ghost">← К карточке пользователя</a>
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
            <dd><?= e($logist['full_name']) ?></dd>
            <dt>Логин</dt>
            <dd><code><?= e($logist['login']) ?></code></dd>
            <dt>Новый временный пароль</dt>
            <dd><code class="code-hi"><?= e($newPassword) ?></code></dd>
            <dt>Роль</dt>
            <dd><?php $rc = $logist['role'] ?? 'logist'; echo e($rc === 'logist' ? 'Логист' : ($rc === 'senior_logist' ? 'Логист+' : $rc)) ?></dd>
        </dl>

        <div class="notice warn">
            Временный пароль показан только один раз. Сохраните его сейчас. Пароль не хранится в открытом виде и не может быть восстановлен.
        </div>
    </div>
</div>
</div>

<?php else: ?>

<div class="page-head">
    <div class="page-head-left">
        <h1 class="page-title"><?= e($logist['full_name']) ?></h1>
        <div class="page-summary"><span>SUPERADMIN / <?= e($company['name']) ?></span></div>
    </div>
    <div class="page-head-actions">
        <a href="/superadmin/companies/<?= $companyId ?>/users/logists/<?= $logistId ?>/edit" class="btn btn-primary">Редактировать</a>
        <a href="/superadmin/companies/<?= $companyId ?>/users" class="btn btn-ghost">← К пользователям</a>
    </div>
</div>

<div class="page-content">

<?php if (($_GET['status_changed'] ?? '') === '1'): ?>
    <div class="notice success">Статус изменён.</div>
<?php endif; ?>
<?php if (($_GET['error'] ?? '') === 'reset_failed'): ?>
    <div class="notice danger">Ошибка сброса пароля. Проверьте доступность БД компании и повторите попытку.</div>
<?php endif; ?>

<?php if (!empty($countsIncomplete)): ?>
    <div class="notice info">
        Часть счётчиков недоступна: локальная БД компании не содержит полей аудита автора для этих справочников. Карточка пользователя открыта, но аудит созданных записей ограничен.
    </div>
<?php endif; ?>

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
            <dd><?php $rc = $logist['role'] ?? 'logist'; echo e($rc === 'logist' ? 'Логист' : ($rc === 'senior_logist' ? 'Логист+' : $rc)) ?></dd>
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
        <span class="panel-head-title">Созданные записи</span>
    </div>
    <div class="panel-body">
        <dl class="kv">
            <dt>Клиенты</dt>
            <dd><?= array_key_exists('clients', $counts) && $counts['clients'] !== null ? (int)$counts['clients'] : '—' ?></dd>
            <dt>Подрядчики</dt>
            <dd><?= array_key_exists('contractors', $counts) && $counts['contractors'] !== null ? (int)$counts['contractors'] : '—' ?></dd>
            <dt>Водители</dt>
            <dd><?= array_key_exists('drivers', $counts) && $counts['drivers'] !== null ? (int)$counts['drivers'] : '—' ?></dd>
            <dt>Транспорт</dt>
            <dd><?= array_key_exists('vehicles', $counts) && $counts['vehicles'] !== null ? (int)$counts['vehicles'] : '—' ?></dd>
            <dt>Экипажи</dt>
            <dd><?= array_key_exists('crews', $counts) && $counts['crews'] !== null ? (int)$counts['crews'] : '—' ?></dd>
            <dt>Документы</dt>
            <dd><?= array_key_exists('documents', $counts) && $counts['documents'] !== null ? (int)$counts['documents'] : '—' ?></dd>
        </dl>
    </div>
</div>

<div class="panel">
    <div class="panel-head">
        <span class="panel-head-title">Доступы</span>
    </div>
    <div class="panel-body">
        <dl class="kv">
            <dt>Выдано доступов</dt>
            <dd><?= $grantsCount !== null ? (int)$grantsCount : '—' ?></dd>
        </dl>
    </div>
</div>

<div class="panel">
    <div class="panel-head">
        <span class="panel-head-title">Управление доступом</span>
    </div>
    <div class="panel-body">
        <div class="notice info">
            Сброс пароля заменит текущий пароль пользователя. Новый временный пароль будет показан только один раз.
        </div>
        <div class="form-actions">
            <?php if ($logist['status'] !== 'active'): ?>
            <form method="post" action="/superadmin/companies/<?= $companyId ?>/users/logists/<?= $logistId ?>/activate" onsubmit="return confirm('Активировать пользователя?')">
                <button type="submit" class="btn btn-secondary">Активировать</button>
            </form>
            <?php endif; ?>
            <form method="post" action="/superadmin/companies/<?= $companyId ?>/users/logists/<?= $logistId ?>/reset-password" onsubmit="return confirm('Сбросить пароль пользователя? Текущий пароль будет заменён. Новый пароль будет показан только один раз.')">
                <button type="submit" class="btn btn-secondary">Сбросить пароль</button>
            </form>
        </div>
    </div>
</div>

<div class="panel panel-danger">
    <div class="panel-head">
        <span class="panel-head-title">Опасная зона</span>
    </div>
    <div class="panel-body">
        <div class="notice danger">
            Действия в этом разделе изменяют статус пользователя и не могут быть отменены автоматически.
        </div>
        <div class="form-actions">
            <?php if ($logist['status'] === 'active'): ?>
            <form method="post" action="/superadmin/companies/<?= $companyId ?>/users/logists/<?= $logistId ?>/block" onsubmit="return confirm('Заблокировать пользователя?')">
                <button type="submit" class="btn btn-danger">Заблокировать</button>

            </form>
            <?php endif; ?>
            <?php if ($logist['status'] !== 'archived'): ?>
            <form method="post" action="/superadmin/companies/<?= $companyId ?>/users/logists/<?= $logistId ?>/archive" onsubmit="return confirm('Архивировать пользователя? Это действие скроет пользователя из списков и запретит вход.')">
                <button type="submit" class="btn btn-danger">Архивировать</button>
            </form>
            <?php endif; ?>
        </div>
    </div>
</div>

</div><!-- /.page-content -->

<?php endif; ?>
