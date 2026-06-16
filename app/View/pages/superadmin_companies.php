<?php

require_once __DIR__ . '/../components/status_badge.php';

?>
<div class="page-head">
    <div class="page-head-left">
        <span class="page-eyebrow">SUPERADMIN</span>
        <span class="page-title">Реестр компаний</span>
    </div>
    <div class="page-head-actions">
        <a href="/superadmin/companies/create" class="btn btn-primary">Создать экспедитора</a>
    </div>
</div>

<div class="page-content">

<?php if (isset($dbError)): ?>
    <div class="notice warn">
        <?= e($dbError) ?>
    </div>
<?php endif; ?>

<?php if (($_GET['status_changed'] ?? '') === '1'): ?>
    <div class="notice success">Статус изменён.</div>
<?php endif; ?>

<?php if (($_GET['deleted'] ?? '') !== ''): ?>
<div class="notice success">Компания ID <?= (int)$_GET['deleted'] ?> полностью удалена.</div>
<?php endif; ?>

<form method="get" action="/superadmin/companies" class="filters-bar">
    <input type="text" class="field-input filter-input-search" placeholder="Поиск по названию или ИНН" name="search" value="<?= e($search ?? '') ?>">
    <select class="field-select filter-input-narrow" name="status">
        <option value="">Все статусы</option>
        <option value="active" <?= ($filterStatus ?? '') === 'active' ? 'selected' : '' ?>>Активен</option>
        <option value="inactive" <?= ($filterStatus ?? '') === 'inactive' ? 'selected' : '' ?>>Неактивен</option>
        <option value="blocked" <?= ($filterStatus ?? '') === 'blocked' ? 'selected' : '' ?>>Заблокирован</option>
        <option value="archived" <?= ($filterStatus ?? '') === 'archived' ? 'selected' : '' ?>>Архивирован</option>
        <option value="provisioning" <?= ($filterStatus ?? '') === 'provisioning' ? 'selected' : '' ?>>Настройка</option>
        <option value="error" <?= ($filterStatus ?? '') === 'error' ? 'selected' : '' ?>>Ошибка</option>
    </select>

    <button type="submit" class="btn btn-toolbar">Применить</button>
    <a href="/superadmin/companies" class="btn btn-ghost">Сбросить</a>
</form>

<?php if (empty($companies)): ?>
    <div class="panel">
        <div class="panel-body">
            <div class="empty-state">
                <p class="empty-title">Нет компаний</p>
                <p class="empty-desc">Создайте первого экспедитора для начала работы системы.</p>
                <a href="/superadmin/companies/create" class="btn btn-primary">Создать экспедитора</a>
            </div>
        </div>
    </div>
<?php else: ?>
    <div class="panel">
        <div class="panel-head">
            <h3 class="panel-head-title">Компании</h3>
            <span class="badge"><?= count($companies) ?> записей</span>
        </div>
        <div class="tbl-wrap">
            <table class="tbl">
                <thead>
                    <tr>
                        <th>Компания</th>
                        <th class="col-tight">Статус</th>
                        <th>Руководитель</th>
                        <th class="col-tight col-num">Польз.</th>
                        <th class="col-tight">Создан</th>
                        <th class="col-tight"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($companies as $c): ?>
                    <tr>
                        <td class="cell-double">
                            <span class="cell-main"><?= e($c['name']) ?></span>
                            <span class="cell-sub">ИНН <?= e($c['inn']) ?> · ID <?= $c['id'] ?></span>
                        </td>
                        <td class="col-tight"><?= renderStatusBadge($c['status']) ?></td>
                        <td>
                            <?php if (!empty($c['owner_name'])): ?>
                                <?= e($c['owner_name']) ?>
                            <?php else: ?>
                                <span class="col-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="col-tight col-num"><?= (int)($c['user_count'] ?? 0) ?></td>
                        <td class="col-tight col-muted"><?= e(substr($c['created_at'] ?? '', 0, 10)) ?></td>
                        <td class="col-tight">
                            <div class="row-actions">
                                <a href="/superadmin/companies/<?= $c['id'] ?>" class="btn btn-ghost btn-sm">Открыть</a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

</div>
