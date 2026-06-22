<?php if ($company === null): ?>

<div class="notice warn">
    Компания не найдена. Укажите корректный company_id.
</div>

<?php elseif ($company['status'] !== 'active'): ?>

<div class="page-head">
    <div>
        <h1>Экипажи</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?></p>
    </div>
</div>

<div class="notice warn">
    Компания находится в статусе «<?= e($company['status']) ?>». Работа с экипажами недоступна.
</div>

<?php elseif (isset($dbError)): ?>

<div class="page-head">
    <div>
        <h1>Экипажи</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?></p>
    </div>
</div>

<div class="notice warn">
    <?= e($dbError) ?>
</div>

<?php elseif (empty($crews)): ?>
<?php $isLogist = ($_SESSION['role_code'] ?? '') === 'logist'; ?>

<div class="page-head">
    <div>
        <h1>Экипажи</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?></p>
    </div>
    <?php if (!$isLogist): ?>
    <div class="page-head-actions">
        <a href="/company/crews/create" class="btn btn-primary">Создать экипаж</a>
    </div>
    <?php endif; ?>
</div>

<div class="table-card table-card--toolbar-only">
    <?php if ($isLogist && !empty($hasGrantsButAllArchived)): ?>
    <div class="empty-state">
        <div class="empty-icon">📦</div>
        <p class="empty-title">Экипажи в архиве</p>
        <p class="empty-desc">Доступные вам экипажи заархивированы. Обратитесь к руководителю для восстановления записи или назначения доступа к другому экипажу.</p>
    </div>
    <?php elseif ($isLogist): ?>
    <div class="empty-state">
        <div class="empty-icon">🔒</div>
        <p class="empty-title">Нет доступа</p>
        <p class="empty-desc">У вас нет доступа к экипажам. Обратитесь к руководителю для получения доступа.</p>
    </div>
    <?php else: ?>
    <div class="empty-state">
        <p class="empty-title">Экипажи ещё не созданы.</p>
        <p class="empty-desc">Создайте экипаж, чтобы объединить подрядчика, водителя и транспортный комплект.</p>
        <a href="/company/crews/create" class="btn btn-primary">Создать первый экипаж</a>
    </div>
    <?php endif; ?>
</div>

<?php else: ?>

<div class="page-head">
    <div>
        <h1>Экипажи</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?></p>
    </div>
    <div class="page-head-actions">
        <a href="/company/crews/create" class="btn btn-primary">Создать экипаж</a>
    </div>
</div>

<div class="table-card table-card--toolbar-only" data-erp-grid>
    <div class="table-toolbar">
        <div class="found-label">Найдено: <b><?= count($crews) ?></b> экипажей</div>
        <div class="toolbar-right">
            <input type="text" class="toolbar-search" placeholder="Поиск по таблице">
        </div>
    </div>
    <div class="table-scroll">
        <table class="table">
            <thead>
                <tr>
                    <th>Экипаж</th>
                    <th>Подрядчик</th>
                    <th>Водитель</th>
                    <th>Транспортный комплект</th>
                    <th>Статус</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($crews as $c): ?>
                <tr>
                    <td class="cell-double">
                        <span class="cell-main">Экипаж #<?= $c['id'] ?></span>
                        <span class="cell-sub">Создан <?= e(ui_date($c['created_at'] ?? null)) ?></span>
                    </td>
                    <td><?= e($c['contractor_name'] ?? '—') ?></td>
                    <td class="cell-double">
                        <span class="cell-main"><?= e($c['driver_name'] ?? '—') ?></span>
                        <span class="cell-sub col-mono"><?= e($c['driver_phone'] ?? '—') ?></span>
                    </td>
                    <td class="cell-double">
                        <span class="cell-main col-mono"><?= e($c['plates'] ?? '—') ?></span>
                        <span class="cell-sub"><?= e($c['set_type'] ?? '—') ?></span>
                    </td>
                    <td>
                        <span class="badge<?= $c['status'] === 'active' ? ' badge-ok' : '' ?>">
                            <span class="dot"></span>
                            <?= $c['status'] === 'active' ? 'Активен' : 'Неактивен' ?>
                        </span>
                    </td>
                    <td class="col-actions">
                        <div class="row-actions">
                            <a href="/company/crews/<?= $c['id'] ?>" class="btn btn-toolbar">Просмотр</a>
                            <a href="/company/crews/<?= $c['id'] ?>/edit" class="btn btn-toolbar">Редактировать</a>
                            <a href="/company/documents?entity_type=crew&entity_id=<?= $c['id'] ?>" class="btn btn-toolbar">Документы</a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php endif; ?>
