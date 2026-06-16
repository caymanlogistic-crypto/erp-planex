<?php if ($company === null): ?>

<div class="notice warn">
    Компания не найдена. Укажите корректный company_id.
</div>

<?php elseif ($company['status'] !== 'active'): ?>

<div class="page-head">
    <div>
        <h1>Пользователи</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?></p>
    </div>
</div>

<div class="notice warn">
    Компания находится в статусе «<?= e($company['status']) ?>». Создание пользователей недоступно.
</div>

<?php elseif (isset($dbError)): ?>

<div class="page-head">
    <div>
        <h1>Пользователи</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?></p>
    </div>
</div>

<div class="notice warn">
    <?= e($dbError) ?>
</div>

<?php elseif (empty($logists)): ?>

<div class="page-head">
    <div>
        <h1>Пользователи</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?></p>
    </div>
    <div class="page-head-actions">
        <a href="/company/logists/create" class="btn btn-primary">Создать пользователя</a>
    </div>
</div>

<div class="panel">
    <div class="panel-body">
        <div class="empty-state">
            <p>Пользователи ещё не созданы.</p>
            <a href="/company/logists/create" class="btn btn-primary">Создать первого пользователя</a>
        </div>
    </div>
</div>

<?php else: ?>

<div class="page-head">
    <div>
        <h1>Пользователи</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?></p>
    </div>
    <div class="page-head-actions">
        <a href="/company/logists/create" class="btn btn-primary">Создать пользователя</a>
    </div>
</div>

<div class="panel">
    <div class="panel-body">
        <div class="tbl-wrap">
            <table class="tbl">
                <thead>
                    <tr>
                        <th>Пользователь</th>
                        <th>Роль</th>
                        <th>Статус</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logists as $l): ?>
                    <tr>
                        <td class="cell-double">
                            <span class="cell-main"><?= e($l['full_name']) ?></span>
                            <span class="cell-sub">ID <?= $l['id'] ?> / <?= e($l['login']) ?> / создан <?= e(ui_date($l['created_at'] ?? null)) ?></span>
                        </td>
                        <td><?= ($l['role_code'] ?? 'logist') === 'logist' ? 'Логист' : e($l['role_code'] ?? '') ?></td>
                        <td>
                            <?php if ($l['status'] === 'active'): ?>
                            <span class="badge badge-ok"><span class="dot"></span>Активен</span>
                            <?php elseif ($l['status'] === 'blocked'): ?>
                            <span class="badge"><span class="dot"></span>Заблокирован</span>
                            <?php elseif ($l['status'] === 'archived'): ?>
                            <span class="badge badge-warn"><span class="dot"></span>Архив</span>
                            <?php else: ?>
                            <span class="badge"><span class="dot"></span><?= e($l['status']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="col-actions">
                            <div class="row-actions">
                                <a href="/company/logists/<?= $l['id'] ?>" class="btn btn-toolbar">Просмотр</a>
                                <a href="/company/logists/<?= $l['id'] ?>/edit" class="btn btn-toolbar">Редактировать</a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php endif; ?>
