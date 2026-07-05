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
<?php
    $ownerUser = null;
    $regularUsers = [];
    foreach ($users as $user) {
        if (($user['type'] ?? '') === 'owner') {
            $ownerUser = $user;
        } else {
            $regularUsers[] = $user;
        }
    }
?>

<div class="page-head">
    <div class="page-head-left">
        <span class="page-eyebrow">SUPERADMIN / <?= e($company['name']) ?></span>
        <span class="page-title">Пользователи компании</span>
        <span class="page-summary">
            <b><?= (int)$totalCount ?> всего</b>
            <span class="sep">·</span>
            <span><?= $ownerUser ? 'Руководитель назначен' : 'Нет руководителя' ?></span>
        </span>
    </div>
    <div class="page-head-actions">
        <a href="/superadmin/companies/<?= $id ?>/users/logists/create" class="btn btn-primary">Создать пользователя</a>
        <a href="/superadmin/companies/<?= $id ?>" class="btn btn-ghost">← К карточке</a>
    </div>
</div>

<div class="page-content">

<?php if (!empty($localDbError)): ?>
    <div class="notice warn mb-3">
        Локальная БД компании недоступна. Отображаются только данные руководителя из центрального реестра; рабочие пользователи могут быть неполными.
    </div>
<?php endif; ?>

<div class="panel">
    <div class="panel-head">
        <h3 class="panel-head-title">Руководитель</h3>
        <?php if ($ownerUser): ?>
            <span class="badge badge-accent"><span class="dot dot-accent"></span>Главный доступ</span>
        <?php else: ?>
            <span class="badge badge-danger">Не создан</span>
        <?php endif; ?>
    </div>
    <div class="panel-body">
        <?php if ($ownerUser): ?>
        <div class="subject-card">
            <div class="subject-main">
                <strong><?= e($ownerUser['full_name']) ?></strong>
                <span><code><?= e($ownerUser['login']) ?></code> · <?= e($ownerUser['email'] ?? '—') ?> · <?= e($ownerUser['phone'] ?? '—') ?></span>
                <span><?= renderStatusBadge($ownerUser['status']) ?></span>
            </div>
            <div class="subject-actions">
                <a href="/superadmin/companies/<?= $id ?>/owner" class="btn btn-secondary btn-sm">Карточка</a>
                <a href="/superadmin/companies/<?= $id ?>/owner/edit" class="btn btn-ghost btn-sm">Редактировать</a>
            </div>
        </div>
        <div class="notice info mt-actions">
            Руководитель управляется отдельно от обычных пользователей: это первичный доступ компании и главный контакт для восстановления управляемости.
        </div>
        <div class="risk-actions">
            <form method="post" action="/superadmin/companies/<?= $id ?>/owner/reset-password" onsubmit="return confirm('Сбросить пароль Руководителя?')">
                <button type="submit" class="btn btn-secondary btn-sm">Сбросить пароль</button>
            </form>
            <?php if ($ownerUser['status'] !== 'active'): ?>
            <form method="post" action="/superadmin/companies/<?= $id ?>/users/owner/<?= $ownerUser['id'] ?>/activate" onsubmit="return confirm('Активировать руководителя?')">
                <button type="submit" class="btn btn-ghost btn-sm">Активировать</button>
            </form>
            <?php endif; ?>
            <?php if ($ownerUser['status'] === 'active'): ?>
            <form method="post" action="/superadmin/companies/<?= $id ?>/users/owner/<?= $ownerUser['id'] ?>/block" onsubmit="return confirm('Заблокировать руководителя?')">
                <button type="submit" class="btn btn-secondary btn-sm">Заблокировать</button>
            </form>
            <?php endif; ?>
            <form method="post" action="/superadmin/companies/<?= $id ?>/users/owner/<?= $ownerUser['id'] ?>/archive" onsubmit="return confirm('Архивировать руководителя?')">
                <button type="submit" class="btn btn-danger btn-sm">Архивировать</button>
            </form>
        </div>
        <?php else: ?>
        <div class="empty-state empty-state-left">
            <p class="empty-title">Руководитель не создан</p>
            <p class="empty-desc">Это не обычная пустая таблица, а блокер готовности компании. Создайте руководителя, чтобы у компании появился первичный доступ и ответственный контакт.</p>
            <a href="/superadmin/companies/<?= $id ?>/create-owner" class="btn btn-primary">Создать руководителя</a>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="panel">
    <div class="panel-head">
        <h3 class="panel-head-title">Обычные пользователи</h3>
        <span class="badge"><?= count($regularUsers) ?> пользователей</span>
    </div>

    <?php if (empty($regularUsers)): ?>
    <div class="panel-body">
        <div class="empty-state empty-state-left">
            <p class="empty-title">Рабочие пользователи не созданы</p>
            <p class="empty-desc">Для новой компании это нормальный следующий шаг после руководителя. Пользователь получает рабочий доступ, но не заменяет руководителя.</p>
            <a href="/superadmin/companies/<?= $id ?>/users/logists/create" class="btn btn-secondary">Создать пользователя</a>
        </div>
    </div>
    <?php else: ?>
    <div class="tbl-wrap">
        <table class="tbl">
            <thead>
                <tr>
                    <th>Пользователь</th>
                    <th>Email / Телефон</th>
                    <th class="col-tight">Статус</th>
                    <th class="col-tight">Создан</th>
                    <th class="col-tight">Основные действия</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($regularUsers as $u): ?>
                <tr>
                    <td class="cell-double">
                        <span class="cell-main">
                            <?= e($u['full_name']) ?>
                            <span class="badge">Логист</span>
                        </span>
                        <span class="cell-sub"><?= e($u['login']) ?></span>
                    </td>
                    <td class="cell-double">
                        <span class="cell-main"><?= e($u['email'] ?? '—') ?></span>
                        <span class="cell-sub"><?= e($u['phone'] ?? '—') ?></span>
                    </td>
                    <td class="col-tight"><?= renderStatusBadge($u['status']) ?></td>
                    <td class="col-tight col-muted"><?= e(substr($u['created_at'] ?? '', 0, 10)) ?></td>
                    <td class="col-tight col-actions">
                        <div class="row-actions">
                            <a href="/superadmin/companies/<?= $id ?>/users/logists/<?= $u['id'] ?>" class="btn btn-secondary btn-sm">Карточка</a>
                            <a href="/superadmin/companies/<?= $id ?>/users/logists/<?= $u['id'] ?>/edit" class="btn btn-ghost btn-sm">Редактировать</a>
                            <form method="post" action="/superadmin/companies/<?= $id ?>/users/logists/<?= $u['id'] ?>/reset-password" onsubmit="return confirm('Сбросить пароль пользователя?')">
                                <button type="submit" class="btn btn-ghost btn-sm">Пароль</button>
                            </form>
                        </div>
                    </td>
                    <!-- DESIGN_TODO: кнопки Блок/Архив перенесены в карточку пользователя; ранее были здесь в колонке «Риск» -->
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

</div><!-- /.page-content -->

<?php endif; ?>
