<?php if ($company === null): ?>

<div class="notice warn">
    Компания не найдена. Укажите корректный company_id.
</div>

<?php elseif ($company['status'] !== 'active'): ?>

<div class="page-head">
    <div class="page-head-left">
        <h1 class="page-title">Список перевозчиков</h1>
        <div class="page-summary"><span>Компании-перевозчики и ИП · Управление договорами и документами</span></div>
    </div>
</div>

<div class="notice warn">
    Компания находится в статусе «<?= e($company['status']) ?>». Создание перевозчиков недоступно.
</div>

<?php elseif (isset($dbError)): ?>

<div class="page-head">
    <div class="page-head-left">
        <h1 class="page-title">Список перевозчиков</h1>
        <div class="page-summary"><span>Компании-перевозчики и ИП · Управление договорами и документами</span></div>
    </div>
</div>

<div class="notice warn">
    <?= e($dbError) ?>
</div>

<?php elseif (empty($contractors)): ?>
<?php $isLogist = ($_SESSION['role_code'] ?? '') === 'logist'; ?>

<div class="page-head">
    <div class="page-head-left">
        <h1 class="page-title">Список перевозчиков</h1>
        <div class="page-summary"><span>Компании-перевозчики и ИП · Управление договорами и документами</span></div>
    </div>
    <div class="page-head-actions">
        <a href="/company/contractors/create" class="btn btn-primary">Создать перевозчика</a>
    </div>
</div>

<div class="table-card table-card--toolbar-only">
    <div class="empty-state">
        <p class="empty-title">Нет доступных перевозчиков</p>
        <p class="empty-desc">У вас пока нет созданных перевозчиков, либо руководитель ещё не выдал вам доступ к существующим.</p>
    </div>
</div>

<?php else: ?>
<?php $isLogist = ($_SESSION['role_code'] ?? '') === 'logist'; ?>

<div class="page-head">
    <div class="page-head-left">
        <h1 class="page-title">Список перевозчиков</h1>
        <div class="page-summary"><span>Компании-перевозчики и ИП · Управление договорами и документами</span></div>
    </div>
    <div class="page-head-actions">
        <a href="/company/contractors/create" class="btn btn-primary">Создать перевозчика</a>
        <a href="/company/contractors/create-full" class="btn btn-primary">Создать перевозчика + Водителя + Транспорт</a>
    </div>
</div>

<div class="table-card table-card--standard" data-erp-grid>
    <div class="table-toolbar">
        <div class="found-label">Найдено: <b><?= count($contractors) ?></b> перевозчиков</div>
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
                    <th>Перевозчик</th>
                    <th>Контакт</th>
                    <?php if (($_SESSION['role_code'] ?? '') === 'company_owner'): ?>
                    <th>Создал</th>
                    <?php endif; ?>
                    <th>Статус</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($contractors as $c): ?>
                <tr data-erp-sort-date="<?= $c['id'] ?>">
                    <td class="cell-double">
                        <span class="cell-main"><?= e($c['name']) ?></span>
                        <span class="cell-sub">ИНН <?= e($c['inn']) ?> · <?= !empty($c['contractor_type']) ? e(ui_contractor_type($c['contractor_type'])) : '—' ?></span>
                    </td>
                    <td class="cell-double">
                        <span class="cell-main"><?= e($c['primary_contact_person'] ?? '') ?: '—' ?></span>
                        <span class="cell-sub"><?= e($c['primary_contact_phone'] ?? '') ?: e($c['doc_email'] ?? '') ?: 'Контакт не указан' ?></span>
                    </td>
                    <?php if (($_SESSION['role_code'] ?? '') === 'company_owner'): ?>
                    <td class="col-muted"><?= e(ui_actor($c['created_by_role'] ?? null, $c['created_by_user_id'] ?? null, $c['created_by_name'] ?? null)) ?></td>
                    <?php endif; ?>
                    <td>
                        <span class="badge<?= $c['status'] === 'active' ? ' badge-ok' : '' ?>">
                            <span class="dot"></span>
                            <?= $c['status'] === 'active' ? 'Активен' : 'Неактивен' ?>
                        </span>
                    </td>
                    <td class="col-actions">
                        <div class="row-actions">
                            <a href="/company/contractors/<?= $c['id'] ?>" class="btn btn-toolbar">Просмотр</a>
                            <a href="/company/contractors/<?= $c['id'] ?>/edit" class="btn btn-toolbar">Редактировать</a>
                            <a href="/company/documents?entity_type=contractor&entity_id=<?= $c['id'] ?>" class="btn btn-toolbar">Документы</a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="table-footer">
        <span class="footer-label">Показано <b class="footer-range">1–<?= count($contractors) ?></b> из <b class="footer-total"><?= count($contractors) ?></b></span>
    </div>
</div>

<?php endif; ?>
