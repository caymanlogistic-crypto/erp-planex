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
        <div class="form-actions mt-4">
            <a href="/superadmin/companies" class="btn btn-ghost">← К реестру</a>
        </div>
    </div>
</div>

<?php else: ?>

<div class="page-head">
    <div class="page-head-left">
        <span class="page-eyebrow">SUPERADMIN / <?= e($company['name']) ?></span>
        <span class="page-title">Пользователи компании</span>
    </div>
    <div class="page-head-actions">
        <a href="/superadmin/companies/<?= $id ?>/users/logists/create" class="btn btn-primary">Создать пользователя</a>
        <a href="/superadmin/companies/<?= $id ?>" class="btn btn-ghost">← К карточке</a>
    </div>
</div>

<div class="page-content">

<?php if (!empty($localDbError)): ?>
    <div class="notice warn mb-3">
        Локальная БД компании недоступна. Отображаются только данные Руководителя.
    </div>
<?php endif; ?>

<?php if (empty($users)): ?>
    <div class="panel">
        <div class="panel-body">
            <div class="empty-state">
                <p class="empty-title">Нет пользователей</p>
                <p class="empty-desc">В компании ещё не созданы пользователи.</p>
            </div>
        </div>
    </div>
<?php else: ?>
    <div class="panel">
        <div class="panel-head">
            <h3 class="panel-head-title">Пользователи</h3>
            <span class="badge"><?= $totalCount ?> пользователей</span>
        </div>
        <div class="tbl-wrap">
            <table class="tbl">
                <thead>
                    <tr>
                        <th>Пользователь</th>
                        <th>Email / Телефон</th>
                        <th class="col-tight">Статус</th>
                        <th class="col-tight">Создан</th>
                        <th class="col-tight"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                    <tr>
                        <td class="cell-double">
                            <span class="cell-main">
                                <?= e($u['full_name']) ?>
                                <?php if ($u['type'] === 'owner'): ?>
                                <span class="badge badge-accent"><span class="dot dot-accent"></span>Руководитель</span>
                                <?php else: ?>
                                <span class="badge">Пользователь</span>
                                <?php endif; ?>
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
                                <?php if ($u['type'] === 'owner'): ?>
                                <a href="/superadmin/companies/<?= $id ?>/owner" class="btn btn-ghost btn-sm">Карточка</a>
                                <a href="/superadmin/companies/<?= $id ?>/owner/edit" class="btn btn-ghost btn-sm">Редактировать</a>
                                <span class="action-sep"></span>
                                <form method="post" action="/superadmin/companies/<?= $id ?>/owner/reset-password" onsubmit="return confirm('Сбросить пароль Руководителя?')">
                                    <button type="submit" class="btn btn-secondary btn-sm">Сбросить пароль</button>
                                </form>
                                <?php if ($u['status'] !== 'active'): ?>
                                <form method="post" action="/superadmin/companies/<?= $id ?>/users/owner/<?= $u['id'] ?>/activate" onsubmit="return confirm('Активировать руководителя?')">
                                    <button type="submit" class="btn btn-ghost btn-sm">Активировать</button>
                                </form>
                                <?php endif; ?>
                                <?php if ($u['status'] === 'active'): ?>
                                <form method="post" action="/superadmin/companies/<?= $id ?>/users/owner/<?= $u['id'] ?>/block" onsubmit="return confirm('Заблокировать руководителя?')">
                                    <button type="submit" class="btn btn-secondary btn-sm">Заблокировать</button>
                                </form>
                                <?php endif; ?>
                                <form method="post" action="/superadmin/companies/<?= $id ?>/users/owner/<?= $u['id'] ?>/archive" onsubmit="return confirm('Архивировать руководителя?')">
                                    <button type="submit" class="btn btn-danger btn-sm">Архивировать</button>
                                </form>
                                <?php else: ?>
                                <a href="/superadmin/companies/<?= $id ?>/users/logists/<?= $u['id'] ?>" class="btn btn-ghost btn-sm">Карточка</a>
                                <a href="/superadmin/companies/<?= $id ?>/users/logists/<?= $u['id'] ?>/edit" class="btn btn-ghost btn-sm">Редактировать</a>
                                <span class="action-sep"></span>
                                <form method="post" action="/superadmin/companies/<?= $id ?>/users/logists/<?= $u['id'] ?>/reset-password" onsubmit="return confirm('Сбросить пароль пользователя?')">
                                    <button type="submit" class="btn btn-secondary btn-sm">Сбросить пароль</button>
                                </form>
                                <?php if ($u['status'] !== 'active'): ?>
                                <form method="post" action="/superadmin/companies/<?= $id ?>/users/logists/<?= $u['id'] ?>/activate" onsubmit="return confirm('Активировать пользователя?')">
                                    <button type="submit" class="btn btn-ghost btn-sm">Активировать</button>
                                </form>
                                <?php endif; ?>
                                <?php if ($u['status'] === 'active'): ?>
                                <form method="post" action="/superadmin/companies/<?= $id ?>/users/logists/<?= $u['id'] ?>/block" onsubmit="return confirm('Заблокировать пользователя?')">
                                    <button type="submit" class="btn btn-secondary btn-sm">Заблокировать</button>
                                </form>
                                <?php endif; ?>
                                <form method="post" action="/superadmin/companies/<?= $id ?>/users/logists/<?= $u['id'] ?>/archive" onsubmit="return confirm('Архивировать пользователя?')">
                                    <button type="submit" class="btn btn-danger btn-sm">Архивировать</button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

</div><!-- /.page-content -->

<?php endif; ?>
