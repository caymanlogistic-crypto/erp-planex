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
        <h1>Доступы компании: <?= e($company['name']) ?></h1>
        <p class="text-muted">ID: <?= $id ?> · Режим SUPERADMIN: просмотр</p>
    </div>
    <div class="page-head-actions">
        <a href="/superadmin/companies/<?= $id ?>" class="btn btn-ghost">← К карточке</a>
    </div>
</div>

<?php if (!empty($dbError)): ?>
    <div class="notice warn">
        Локальная БД компании недоступна. Данные доступов не могут быть загружены.
    </div>
<?php elseif (empty($grants)): ?>
    <div class="panel">
        <div class="panel-body">
            <div class="empty-state">
                <p class="text-muted">Нет выданных доступов</p>
                <p class="text-muted">В компании ещё не выданы доступы к записям.</p>
            </div>
        </div>
    </div>
<?php else: ?>
    <div class="panel">
        <div class="panel-head">
            <h3 class="panel-head-title">Доступы</h3>
            <span class="badge"><?= $totalCount ?> доступов</span>
        </div>
        <div class="tbl-wrap">
            <table class="tbl">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Тип сущности</th>
                        <th>ID сущности</th>
                        <th>Кому (ID)</th>
                        <th>Кому (имя)</th>
                        <th>Кем выдан (ID)</th>
                        <th>Кем выдан (имя)</th>
                        <th>Уровень</th>
                        <th>Создан</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($grants as $g): ?>
                    <tr>
                        <td class="col-mono"><?= $g['id'] ?></td>
                        <td><?= e($g['entity_type']) ?></td>
                        <td class="col-mono"><?= (int)$g['entity_id'] ?></td>
                        <td class="col-mono"><?= (int)$g['granted_to_user_id'] ?></td>
                        <td><?= e($g['granted_to_name'] ?? '—') ?></td>
                        <td class="col-mono"><?= (int)$g['granted_by_user_id'] ?></td>
                        <td><?= e($g['granted_by_name'] ?? '—') ?></td>
                        <td><?= e($g['access_level']) ?></td>
                        <td class="col-muted"><?= e($g['created_at']) ?></td>
                        <td class="col-actions">
                            <form method="post" action="/superadmin/companies/<?= $id ?>/access-grants/<?= $g['id'] ?>/revoke" style="display:inline" onsubmit="return confirm('Отозвать доступ?')">
                                <button type="submit" class="btn btn-danger" style="font-size:11px;padding:2px 8px">Отозвать</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php endif; ?>
