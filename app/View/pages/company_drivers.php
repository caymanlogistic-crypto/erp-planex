<?php if ($company === null): ?>

<div class="notice warn">
    Компания не найдена. Укажите корректный company_id.
</div>

<?php elseif ($company['status'] !== 'active'): ?>

<div class="page-head">
    <div class="page-head-left">
        <span class="page-eyebrow">КОМПАНИЯ / <?= e($company['name']) ?></span>
        <span class="page-title">Водители</span>
    </div>
</div>

<div class="notice warn">
    Компания находится в статусе «<?= e($company['status']) ?>». Работа с водителями недоступна.
</div>

<?php elseif (isset($dbError)): ?>

<div class="page-head">
    <div class="page-head-left">
        <span class="page-eyebrow">КОМПАНИЯ / <?= e($company['name']) ?></span>
        <span class="page-title">Водители</span>
    </div>
</div>

<div class="notice warn">
    <?= e($dbError) ?>
</div>

<?php elseif (empty($drivers)): ?>
<?php $isLogist = ($_SESSION['role_code'] ?? '') === 'logist'; ?>

<div class="page-head">
    <div class="page-head-left">
        <span class="page-eyebrow">КОМПАНИЯ / <?= e($company['name']) ?></span>
        <span class="page-title">Водители</span>
    </div>
    <?php if (!$isLogist): ?>
    <div class="page-head-actions">
        <a href="/company/drivers/create" class="btn btn-primary">Создать водителя</a>
    </div>
    <?php endif; ?>
</div>

<div class="panel">
    <div class="panel-body">
        <?php if ($isLogist): ?>
        <div class="empty-state">
            <div class="empty-icon">🔒</div>
            <p class="empty-title">Нет доступа</p>
            <p class="empty-desc">У вас нет доступа к водителям. Обратитесь к руководителю для получения доступа.</p>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <p class="empty-title">Водители ещё не созданы.</p>
            <p class="empty-desc">Добавьте водителя, затем привяжите телефоны, документы и блоки «Водитель + ТС».</p>
            <a href="/company/drivers/create" class="btn btn-primary">Создать первого водителя</a>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php else: ?>

<div class="page-head">
    <div class="page-head-left">
        <span class="page-eyebrow">КОМПАНИЯ / <?= e($company['name']) ?></span>
        <span class="page-title">Водители</span>
    </div>
    <div class="page-head-actions">
        <a href="/company/drivers/create" class="btn btn-primary">Создать водителя</a>
    </div>
</div>

<div class="panel">
    <div class="panel-body">
        <div class="tbl-wrap">
            <table class="tbl">
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
</div>

<?php endif; ?>
