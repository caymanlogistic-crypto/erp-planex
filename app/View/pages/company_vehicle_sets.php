<?php if ($company === null): ?>

<div class="notice warn">
    Компания не найдена. Укажите корректный company_id.
</div>

<?php elseif ($company['status'] !== 'active'): ?>

<div class="page-head">
    <div class="page-head-left">
        <h1 class="page-title">Список транспорта</h1>
        <div class="page-summary"><span>Тягачи и полуприцепы · Транспортные средства перевозчиков</span></div>
    </div>
</div>

<div class="notice warn">
    Компания находится в статусе «<?= e($company['status']) ?>». Работа с транспортом недоступна.
</div>

<?php elseif (isset($dbError)): ?>

<div class="page-head">
    <div class="page-head-left">
        <h1 class="page-title">Список транспорта</h1>
        <div class="page-summary"><span>Тягачи и полуприцепы · Транспортные средства перевозчиков</span></div>
    </div>
</div>

<div class="notice warn">
    <?= e($dbError) ?>
</div>

<?php elseif (empty($vehicleSets)): ?>
<?php $isLogist = ($_SESSION['role_code'] ?? '') === 'logist'; ?>

<div class="page-head">
    <div class="page-head-left">
        <h1 class="page-title">Список транспорта</h1>
        <div class="page-summary"><span>Тягачи и полуприцепы · Транспортные средства перевозчиков</span></div>
    </div>
    <div class="page-head-actions">
        <a href="/company/vehicle-sets/create" class="btn btn-primary">Создать транспорт</a>
    </div>
</div>

<div class="table-card table-card--toolbar-only">
    <div class="empty-state">
        <p class="empty-title">Транспорт ещё не создан.</p>
        <p class="empty-desc">Создайте транспорт, чтобы объединить тягач и полуприцеп для экипажа.</p>
    </div>
</div>

<?php else: ?>

<div class="page-head">
    <div class="page-head-left">
        <h1 class="page-title">Список транспорта</h1>
        <div class="page-summary"><span>Тягачи и полуприцепы · Транспортные средства перевозчиков</span></div>
    </div>
    <div class="page-head-actions">
        <a href="/company/vehicle-sets/create" class="btn btn-primary">Создать транспорт</a>
    </div>
</div>

<div class="table-card table-card--standard" data-erp-grid>
    <div class="table-toolbar">
        <div class="found-label">Найдено: <b><?= count($vehicleSets) ?></b> транспорта</div>
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
                    <th>Транспорт</th>
                    <th>Состав</th>
                    <th>Статус</th>
                    <?php if (($_SESSION['role_code'] ?? '') === 'company_owner'): ?>
                    <th>Создал</th>
                    <?php endif; ?>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($vehicleSets as $vs): ?>
                <tr data-erp-sort-date="<?= $vs['id'] ?>">
                    <td class="cell-double">
                        <span class="cell-main"><?= e($vs['set_type'] ?? 'Транспорт') ?></span>
                    </td>
                    <td class="cell-double">
                        <span class="cell-main col-mono"><?= e($vs['primary_plate'] ?? '—') ?><?= !empty($vs['secondary_plate']) ? ' + ' . e($vs['secondary_plate']) : '' ?></span>
                        <span class="cell-sub"><?= e($vs['primary_brand'] ?? '') ?><?= !empty($vs['secondary_brand']) ? ' / ' . e($vs['secondary_brand']) : '' ?></span>
                    </td>
                    <td>
                        <span class="badge<?= $vs['status'] === 'active' ? ' badge-ok' : ($vs['status'] === 'archived' ? ' badge-warn' : '') ?>">
                            <span class="dot"></span>
                            <?= $vs['status'] === 'active' ? 'Активен' : ($vs['status'] === 'archived' ? 'Архив' : 'Неактивен') ?>
                        </span>
                    </td>
                    <?php if (($_SESSION['role_code'] ?? '') === 'company_owner'): ?>
                    <td class="col-muted"><?= e(ui_actor($vs['created_by_role'] ?? null, $vs['created_by_user_id'] ?? null, $vs['created_by_name'] ?? null)) ?></td>
                    <?php endif; ?>
                    <td class="col-actions">
                        <div class="row-actions">
                            <a href="/company/vehicle-sets/<?= $vs['id'] ?>" class="btn btn-toolbar">Просмотр</a>
                            <a href="/company/vehicle-sets/<?= $vs['id'] ?>/edit" class="btn btn-toolbar">Редактировать</a>
                            <a href="/company/documents?entity_type=vehicle_set&entity_id=<?= $vs['id'] ?>" class="btn btn-toolbar">Документы</a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="table-footer">
        <span class="footer-label">Показано <b class="footer-range">1–<?= count($vehicleSets) ?></b> из <b class="footer-total"><?= count($vehicleSets) ?></b></span>
    </div>
</div>

<?php endif; ?>
