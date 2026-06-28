<?php if ($company === null): ?>

<div class="notice warn">
    Компания не найдена. Укажите корректный company_id.
</div>

<?php elseif ($company['status'] !== 'active'): ?>

<div class="page-head">
    <div class="page-head-left">
        <h1 class="page-title">Исполнители рейса</h1>
        <div class="page-summary"><span>Исполнитель рейса: подрядчик + водитель + транспорт</span></div>
    </div>
</div>

<div class="notice warn">
    Компания находится в статусе «<?= e($company['status']) ?>». Работа с исполнителями рейса недоступна.
</div>

<?php elseif (isset($dbError)): ?>

<div class="page-head">
    <div class="page-head-left">
        <h1 class="page-title">Исполнители рейса</h1>
        <div class="page-summary"><span>Исполнитель рейса: подрядчик + водитель + транспорт</span></div>
    </div>
</div>

<div class="notice warn">
    <?= e($dbError) ?>
</div>

<?php elseif (empty($executors)): ?>
<?php $isLogist = ($_SESSION['role_code'] ?? '') === 'logist'; ?>

<div class="page-head">
    <div class="page-head-left">
        <h1 class="page-title">Исполнители рейса</h1>
        <div class="page-summary"><span>Исполнитель рейса: подрядчик + водитель + транспорт</span></div>
    </div>
    <div class="page-head-actions">
        <a href="/company/route-executors/create" class="btn btn-primary">Создать исполнителя рейса</a>
    </div>
</div>

<div class="panel">
    <?php if ($isLogist && !empty($hasGrantsButAllArchived)): ?>
    <div class="empty-state">
        <div class="empty-icon">📦</div>
        <p class="empty-title">Нет доступных исполнителей рейса</p>
        <p class="empty-desc">У вас пока нет созданных исполнителей рейса, либо руководитель ещё не выдал вам доступ к существующим.</p>
    </div>
    <?php elseif ($isLogist): ?>
    <div class="empty-state">
        <p class="empty-title">Исполнители рейса ещё не созданы или пока не доступны.</p>
        <p class="empty-desc">Можно создать исполнителя рейса из доступных вам подрядчиков, водителей и транспорта. Если нужных данных нет в списках, запросите доступ у руководителя.</p>
        <a href="/company/route-executors/create" class="btn btn-primary">Создать исполнителя рейса</a>
    </div>
    <?php else: ?>
    <div class="empty-state">
        <p class="empty-title">Исполнители рейса ещё не созданы.</p>
        <p class="empty-desc">Создайте исполнителя рейса: выберите подрядчика, водителя и транспорт — система автоматически создаст связку.</p>
        <a href="/company/route-executors/create" class="btn btn-primary">Создать первого исполнителя рейса</a>
    </div>
    <?php endif; ?>
</div>

<?php else: ?>

<div class="page-head">
    <div class="page-head-left">
        <h1 class="page-title">Исполнители рейса</h1>
        <div class="page-summary"><span>Исполнитель рейса: подрядчик + водитель + транспорт</span></div>
    </div>
    <div class="page-head-actions">
        <a href="/company/route-executors/create" class="btn btn-primary">Создать исполнителя рейса</a>
    </div>
</div>

<div class="table-card table-card--standard" data-erp-grid>
    <div class="table-toolbar">
        <div class="found-label">Найдено: <b><?= count($executors) ?></b> исполнителей</div>
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
                    <th>Подрядчик</th>
                    <th>Водитель</th>
                    <th>Телефон</th>
                    <th>ТС</th>
                    <th>Госномер</th>
                    <th>Ответственный логист</th>
                    <th>Статус</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($executors as $e): ?>
                <tr data-erp-sort-date="<?= $e['crew_id'] ?>">
                    <td>
                        <a href="/company/contractors/<?= $e['contractor_id'] ?>" class="cell-link"><?= e($e['contractor_name'] ?? '—') ?></a>
                    </td>
                    <td><?= e($e['driver_name'] ?? '—') ?></td>
                    <td class="col-mono"><?= e($e['driver_phone'] ?? '—') ?></td>
                    <td class="cell-double">
                        <span class="cell-main"><?= e(ui_set_type($e['set_type'] ?? null)) ?></span>
                        <span class="cell-sub"><?= e($e['plates'] ?? '—') ?></span>
                    </td>
                    <td class="col-mono"><?= e($e['primary_plate'] ?? '—') ?></td>
                    <td><?= e($e['created_by_name'] ?? '—') ?></td>
                    <td>
                        <span class="badge<?= $e['crew_status'] === 'active' ? ' badge-ok' : '' ?>">
                            <span class="dot"></span>
                            <?= $e['crew_status'] === 'active' ? 'Активен' : 'Неактивен' ?>
                        </span>
                    </td>
                    <td class="col-actions">
                        <div class="row-actions">
                            <a href="/company/route-executors/<?= $e['crew_id'] ?>" class="btn btn-toolbar">Открыть</a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="table-footer">
        <span class="footer-label">Показано <b class="footer-range">1–<?= count($executors) ?></b> из <b class="footer-total"><?= count($executors) ?></b></span>
    </div>
</div>

<?php endif; ?>
