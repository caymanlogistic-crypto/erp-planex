<?php

function userStatusBadge(string $status): string
{
    $map = [
        'active'  => ['class' => 'badge-ok',    'label' => 'Активен'],
        'blocked' => ['class' => 'badge-danger', 'label' => 'Заблокирован'],
        'archived'=> ['class' => '',             'label' => 'Архивирован'],
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

<?php else: ?>

<div class="page-head">
    <div>
        <h1>Пользователи компании: <?= e($company['name']) ?></h1>
        <p class="text-muted">ID: <?= $company['id'] ?> · Руководитель + Логисты</p>
    </div>
    <div class="page-head-actions">
        <a href="/superadmin/companies/<?= $id ?>" class="btn btn-ghost">← К карточке</a>
    </div>
</div>

<?php if (!empty($localDbError)): ?>
    <div class="notice warn" style="margin-bottom:12px">
        Локальная БД компании недоступна. Отображаются только данные Руководителя.
    </div>
<?php endif; ?>

<?php if (empty($users)): ?>
    <div class="panel">
        <div class="panel-body">
            <div class="empty-state">
                <p class="text-muted">Нет пользователей</p>
                <p class="text-muted">В компании ещё не созданы пользователи.</p>
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
                        <th>ID</th>
                        <th>Тип</th>
                        <th>ФИО</th>
                        <th>Логин</th>
                        <th>Email</th>
                        <th>Телефон</th>
                        <th>Роль</th>
                        <th>Статус</th>
                        <th>Создан</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                    <tr>
                        <td class="col-mono"><?= $u['id'] ?></td>
                        <td>
                            <?php if ($u['type'] === 'owner'): ?>
                            <span class="badge" style="background:var(--accent-bg);color:var(--accent);border-color:var(--accent-line)"><span class="dot"></span>Руководитель</span>
                            <?php else: ?>
                            <span class="badge"><span class="dot"></span>Логист</span>
                            <?php endif; ?>
                        </td>
                        <td><?= e($u['full_name']) ?></td>
                        <td class="col-mono"><?= e($u['login']) ?></td>
                        <td><?= e($u['email'] ?? '—') ?></td>
                        <td class="col-mono"><?= e($u['phone'] ?? '—') ?></td>
                        <td><?= e($u['role_label']) ?></td>
                        <td><?= userStatusBadge($u['status']) ?></td>
                        <td class="col-muted"><?= e($u['created_at'] ?? '') ?></td>
                        <td class="col-actions">
                            <div class="row-actions">
                                <?php if ($u['type'] === 'owner'): ?>
                                <a href="/superadmin/companies/<?= $id ?>/owner" class="ra" title="Просмотр">V</a>
                                <a href="/superadmin/companies/<?= $id ?>/owner/edit" class="ra" title="Редактировать">E</a>
                                <form method="post" action="/superadmin/companies/<?= $id ?>/owner/reset-password" style="display:inline" onsubmit="return confirm('Сбросить пароль Руководителя?')">
                                    <button class="ra" title="Сбросить пароль">P</button>
                                </form>
                                <?php else: ?>
                                <a href="/superadmin/companies/<?= $id ?>/users/logists/<?= $u['id'] ?>" class="ra" title="Просмотр">V</a>
                                <a href="/superadmin/companies/<?= $id ?>/users/logists/<?= $u['id'] ?>/edit" class="ra" title="Редактировать">E</a>
                                <?php if ($u['status'] !== 'active'): ?>
                                <form method="post" action="/superadmin/companies/<?= $id ?>/users/logists/<?= $u['id'] ?>/activate" style="display:inline" onsubmit="return confirm('Активировать логиста?')">
                                    <button class="ra" title="Активировать">✓</button>
                                </form>
                                <?php endif; ?>
                                <?php if ($u['status'] === 'active'): ?>
                                <form method="post" action="/superadmin/companies/<?= $id ?>/users/logists/<?= $u['id'] ?>/block" style="display:inline" onsubmit="return confirm('Заблокировать логиста?')">
                                    <button class="ra" title="Заблокировать">⊗</button>
                                </form>
                                <?php endif; ?>
                                <form method="post" action="/superadmin/companies/<?= $id ?>/users/logists/<?= $u['id'] ?>/archive" style="display:inline" onsubmit="return confirm('Архивировать логиста?')">
                                    <button class="ra del" title="Архивировать">A</button>
                                </form>
                                <form method="post" action="/superadmin/companies/<?= $id ?>/users/logists/<?= $u['id'] ?>/reset-password" style="display:inline" onsubmit="return confirm('Сбросить пароль логиста?')">
                                    <button class="ra" title="Сбросить пароль">P</button>
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

<?php endif; ?>
