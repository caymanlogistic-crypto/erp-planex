<?php if ($company === null): ?>

<div class="notice warn">
    Компания не найдена. Укажите корректный company_id.
</div>

<?php elseif ($company['status'] !== 'active'): ?>

<div class="page-head">
    <div class="page-head-left">
        <h1 class="page-title">Список водителей</h1>
        <div class="page-summary"><span>Реестр водителей транспортных средств · Управление доступами и документами</span></div>
    </div>
</div>

<div class="notice warn">
    Компания находится в статусе «<?= e($company['status']) ?>». Работа с водителями недоступна.
</div>

<?php elseif (isset($dbError)): ?>

<div class="page-head">
    <div class="page-head-left">
        <h1 class="page-title">Список водителей</h1>
        <div class="page-summary"><span>Реестр водителей транспортных средств · Управление доступами и документами</span></div>
    </div>
</div>

<div class="notice warn">
    <?= e($dbError) ?>
</div>

<?php elseif (empty($drivers)): ?>
<?php $isLogist = ($_SESSION['role_code'] ?? '') === 'logist'; ?>

<div class="page-head">
    <div class="page-head-left">
        <h1 class="page-title">Список водителей</h1>
        <div class="page-summary"><span>Реестр водителей транспортных средств · Управление доступами и документами</span></div>
    </div>
    <div class="page-head-actions">
        <a href="/company/drivers/create" class="btn btn-primary">Создать водителя</a>
    </div>
</div>

<div class="table-card table-card--toolbar-only">
    <div class="empty-state">
        <p class="empty-title">Нет доступных водителей</p>
        <p class="empty-desc">У вас пока нет созданных водителей, либо руководитель ещё не выдал вам доступ к существующим.</p>
    </div>
</div>

<?php else: ?>

<div class="page-head">
    <div class="page-head-left">
        <h1 class="page-title">Список водителей</h1>
        <div class="page-summary"><span>Реестр водителей транспортных средств · Управление доступами и документами</span></div>
    </div>
    <div class="page-head-actions">
        <a href="/company/drivers/create" class="btn btn-primary">Создать водителя</a>
    </div>
</div>

<div class="table-card table-card--toolbar-only" data-erp-grid>
    <div class="table-toolbar">
        <div class="found-label">Найдено: <b><?= count($drivers) ?></b> водителей</div>
        <div class="toolbar-right">
            <input type="text" class="toolbar-search" placeholder="Поиск по таблице">
        </div>
    </div>
    <div class="table-scroll">
        <table class="table">
            <thead>
                <tr>
                    <th>Водитель</th>
                    <th>Контакты</th>
                    <th>Документы</th>
                    <?php if (($_SESSION['role_code'] ?? '') === 'company_owner'): ?>
                    <th>Создал</th>
                    <?php endif; ?>
                    <th>Статус</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($drivers as $d): ?>
                <tr>
                    <td class="cell-double">
                        <span class="cell-main"><?= e($d['full_name']) ?></span>
                    </td>
                    <td class="cell-double">
                        <span class="cell-main col-mono"><?= e($d['main_phone'] ?? '—') ?></span>
                        <span class="cell-sub">Основной телефон</span>
                    </td>
                    <td class="cell-double">
                        <?php if (!empty($d['license_number'])): ?>
                            <span class="cell-main">ВУ <?= e($d['license_number']) ?></span>
                            <span class="cell-sub">Водительское удостоверение</span>
                        <?php else: ?>
                            <span class="cell-main">ВУ: нет</span>
                            <span class="cell-sub">Документы доступны в карточке водителя</span>
                        <?php endif; ?>
                    </td>
                    <?php if (($_SESSION['role_code'] ?? '') === 'company_owner'): ?>
                    <td class="col-muted"><?= e(ui_actor($d['created_by_role'] ?? null, $d['created_by_user_id'] ?? null, $d['created_by_name'] ?? null)) ?></td>
                    <?php endif; ?>
                    <td>
                        <span class="badge<?= $d['status'] === 'active' ? ' badge-ok' : '' ?>">
                            <span class="dot"></span>
                            <?= $d['status'] === 'active' ? 'Активен' : 'Неактивен' ?>
                        </span>
                    </td>
                    <td class="col-actions">
                        <div class="row-actions">
                            <a href="/company/drivers/<?= $d['id'] ?>" class="btn btn-toolbar">Просмотр</a>
                            <a href="/company/drivers/<?= $d['id'] ?>/edit" class="btn btn-toolbar">Редактировать</a>
                            <a href="/company/documents?entity_type=driver&entity_id=<?= $d['id'] ?>" class="btn btn-toolbar">Документы</a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php endif; ?>
