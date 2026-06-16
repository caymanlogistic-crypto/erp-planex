<?php if ($company === null): ?>

<div class="notice warn">
    Компания не найдена. Укажите корректный company_id.
</div>

<?php elseif ($company['status'] !== 'active'): ?>

<div class="page-head">
    <div class="page-head-left">
        <span class="page-eyebrow">КОМПАНИЯ / <?= e($company['name']) ?></span>
        <span class="page-title">Подрядчики</span>
    </div>
</div>

<div class="notice warn">
    Компания находится в статусе «<?= e($company['status']) ?>». Создание подрядчиков недоступно.
</div>

<?php elseif (isset($dbError)): ?>

<div class="page-head">
    <div class="page-head-left">
        <span class="page-eyebrow">КОМПАНИЯ / <?= e($company['name']) ?></span>
        <span class="page-title">Подрядчики</span>
    </div>
</div>

<div class="notice warn">
    <?= e($dbError) ?>
</div>

<?php elseif (empty($contractors)): ?>

<div class="page-head">
    <div class="page-head-left">
        <span class="page-eyebrow">КОМПАНИЯ / <?= e($company['name']) ?></span>
        <span class="page-title">Подрядчики</span>
    </div>
    <div class="page-head-actions">
        <a href="/company/contractors/create" class="btn btn-primary">Создать подрядчика</a>
    </div>
</div>

<div class="panel">
    <div class="panel-body">
        <div class="empty-state">
            <p class="empty-title">Подрядчики ещё не созданы.</p>
            <p class="empty-desc">Добавьте первого подрядчика, чтобы вести контакты, документы, налоговую историю и экипажи.</p>
            <a href="/company/contractors/create" class="btn btn-primary">Создать первого подрядчика</a>
        </div>
    </div>
</div>

<?php else: ?>

<div class="page-head">
    <div class="page-head-left">
        <span class="page-eyebrow">КОМПАНИЯ / <?= e($company['name']) ?></span>
        <span class="page-title">Подрядчики</span>
    </div>
    <div class="page-head-actions">
        <a href="/company/contractors/create" class="btn btn-primary">Создать подрядчика</a>
    </div>
</div>

<div class="panel">
    <div class="panel-body">
        <div class="tbl-wrap">
            <table class="tbl">
                <thead>
                    <tr>
                        <th>Подрядчик</th>
                        <th>Реквизиты</th>
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
                    <tr>
                        <td class="cell-double">
                            <span class="cell-main"><?= e($c['name']) ?></span>
                            <span class="cell-sub">ID <?= $c['id'] ?><?= !empty($c['contractor_type']) ? ' / ' . e($c['contractor_type']) : '' ?></span>
                        </td>
                        <td class="cell-double">
                            <span class="cell-main col-mono"><?= e($c['inn']) ?></span>
                            <span class="cell-sub">КПП <?= e($c['kpp'] ?? '') ?: '—' ?></span>
                        </td>
                        <td class="cell-double">
                            <span class="cell-main"><?= e($c['primary_contact_person'] ?? '') ?: '—' ?></span>
                            <span class="cell-sub"><?= e($c['primary_contact_phone'] ?? '') ?: e($c['doc_email'] ?? '') ?: 'Контакт не указан' ?></span>
                        </td>
                        <?php if (($_SESSION['role_code'] ?? '') === 'company_owner'): ?>
                        <td class="col-muted"><?= e($c['created_by_role'] ?? '—') ?> #<?= e($c['created_by_user_id'] ?? '—') ?></td>
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
    </div>
</div>

<?php endif; ?>
