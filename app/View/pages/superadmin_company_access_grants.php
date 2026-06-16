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
        <span class="page-title">Доступы компании</span>
    </div>
    <div class="page-head-actions">
        <a href="/superadmin/companies/<?= $id ?>" class="btn btn-ghost">← К карточке</a>
    </div>
</div>

<div class="page-content">

<?php if (!empty($dbError)): ?>
    <div class="notice warn">
        Локальная БД компании недоступна. Данные доступов не могут быть загружены.
    </div>
<?php elseif (empty($grants)): ?>
    <div class="panel">
        <div class="panel-body">
            <div class="empty-state empty-state-left">
                <p class="empty-title">Нет выданных доступов</p>
                <p class="empty-desc">Доступы появляются, когда пользователям выдают права на конкретные записи. Если пользователей или справочников нет, это нормальное производное состояние.</p>
                <a href="/superadmin/companies/<?= $id ?>/users" class="btn btn-secondary">Проверить пользователей</a>
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
                        <th>Объект</th>
                        <th>Кому</th>
                        <th>Кем выдан</th>
                        <th class="col-tight">Уровень</th>
                        <th class="col-tight">Создан</th>
                        <th class="col-tight"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($grants as $g): ?>
                    <tr>
                        <td class="cell-double">
                            <span class="cell-main"><?= e($g['entity_type']) ?></span>
                            <span class="cell-sub">ID <?= (int)$g['entity_id'] ?> · #<?= $g['id'] ?></span>
                        </td>
                        <td><?= e($g['granted_to_name'] ?? '—') ?></td>
                        <td><?= e($g['granted_by_name'] ?? '—') ?></td>
                        <td class="col-tight col-mono"><?= e($g['access_level']) ?></td>
                        <td class="col-tight col-muted"><?= e(substr($g['created_at'] ?? '', 0, 10)) ?></td>
                        <td class="col-tight col-actions">
                            <div class="row-actions">
                                <form method="post" action="/superadmin/companies/<?= $id ?>/access-grants/<?= $g['id'] ?>/revoke" onsubmit="return confirm('Отозвать доступ?')">
                                    <button type="submit" class="btn btn-danger btn-sm">Отозвать</button>
                                </form>
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
