<?php if ($company === null): ?>

<div class="notice warn">
    Компания не найдена. Укажите корректный company_id.
</div>

<?php elseif ($company['status'] !== 'active'): ?>

<div class="page-head">
    <div class="page-head-left">
        <h1 class="page-title">Водители и транспортное средство</h1>
        <div class="page-summary"><span>Связки водителя и транспортного средства · Экипажи для рейсов</span></div>
    </div>
</div>

<div class="notice warn">
    Компания находится в статусе «<?= e($company['status']) ?>». Работа со связками недоступна.
</div>

<?php elseif (isset($dbError)): ?>

<div class="page-head">
    <div class="page-head-left">
        <h1 class="page-title">Водители и транспортное средство</h1>
        <div class="page-summary"><span>Связки водителя и транспортного средства · Экипажи для рейсов</span></div>
    </div>
</div>

<div class="notice warn">
    <?= e($dbError) ?>
</div>

<?php elseif (empty($blocks)): ?>
<?php $isLogist = ($_SESSION['role_code'] ?? '') === 'logist'; ?>

<div class="page-head">
    <div class="page-head-left">
        <h1 class="page-title">Водители и транспортное средство</h1>
        <div class="page-summary"><span>Связки водителя и транспортного средства · Экипажи для рейсов</span></div>
    </div>
    <div class="page-head-actions">
        <a href="/company/driver-vehicle-blocks/create" class="btn btn-primary">Создать связку Водитель + Машина</a>
    </div>
</div>

<div class="table-card table-card--toolbar-only">
    <div class="empty-state">
        <p class="empty-title">Нет доступных связок</p>
        <p class="empty-desc">У вас пока нет созданных связок Водители+ТС, либо руководитель ещё не выдал вам доступ к существующим.</p>
    </div>
</div>

<?php else: ?>

<div class="page-head">
    <div class="page-head-left">
        <h1 class="page-title">Водители и транспортное средство</h1>
        <div class="page-summary"><span>Связки водителя и транспортного средства · Экипажи для рейсов</span></div>
    </div>
    <div class="page-head-actions">
        <a href="/company/driver-vehicle-blocks/create" class="btn btn-primary">Создать связку Водитель + Машина</a>
    </div>
</div>

<div class="table-card table-card--standard" data-erp-grid>
    <div class="table-toolbar">
        <div class="found-label">Найдено: <b><?= count($blocks) ?></b> связок</div>
        <div class="toolbar-right">
            <div class="toolbar-sort">
                <span class="toolbar-sort-label">Сортировка по:</span>
                <select class="toolbar-select" data-erp-grid-sort>
                    <option value="date" selected>По дате добавления</option>
                    <option value="alpha">По алфавиту</option>
                </select>
            </div>
            <input type="text" class="toolbar-search" placeholder="Поиск по таблице">
        </div>
    </div>
    <div class="table-scroll">
        <table class="table">
            <thead>
                <tr>
                    <th>Связка</th>
                    <th>Водитель</th>
                    <th>Транспорт</th>
                    <th>Статус</th>
                    <?php if (($_SESSION['role_code'] ?? '') === 'company_owner'): ?>
                    <th>Создал</th>
                    <?php endif; ?>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($blocks as $b): ?>
                <tr data-erp-sort-date="<?= $b['id'] ?>">
                    <td class="cell-double">
                        <span class="cell-main">Водители+ТС</span>
                    </td>
                    <td class="cell-double">
                        <span class="cell-main"><?= e($b['driver_name'] ?? '—') ?></span>
                        <span class="cell-sub col-mono"><?= e($b['driver_phone'] ?? '—') ?></span>
                    </td>
                    <td class="cell-double">
                        <span class="cell-main col-mono"><?= e($b['plates'] ?? '—') ?></span>
                        <span class="cell-sub"><?= e(ui_set_type($b['set_type'] ?? null)) ?></span>
                    </td>
                    <td>
                        <span class="badge<?= $b['status'] === 'active' ? ' badge-ok' : '' ?>">
                            <span class="dot"></span>
                            <?= $b['status'] === 'active' ? 'Активен' : 'Неактивен' ?>
                        </span>
                    </td>
                    <?php if (($_SESSION['role_code'] ?? '') === 'company_owner'): ?>
                    <td class="col-muted"><?= e(ui_actor($b['created_by_role'] ?? null, $b['created_by_user_id'] ?? null, $b['created_by_name'] ?? null)) ?></td>
                    <?php endif; ?>
                    <td class="col-actions">
                        <div class="row-actions">
                            <a href="/company/driver-vehicle-blocks/<?= $b['id'] ?>" class="btn btn-toolbar">Просмотр</a>
                            <a href="/company/driver-vehicle-blocks/<?= $b['id'] ?>/edit" class="btn btn-toolbar">Редактировать</a>
                            <a href="/company/documents?entity_type=driver_vehicle_block&entity_id=<?= $b['id'] ?>" class="btn btn-toolbar">Документы</a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="table-footer">
        <span class="footer-label">Показано <b class="footer-range">1–<?= count($blocks) ?></b> из <b class="footer-total"><?= count($blocks) ?></b></span>
    </div>
</div>

<?php endif; ?>
