<?php if ($company === null): ?>

<div class="notice warn">
    Компания не найдена. Укажите корректный company_id.
</div>

<?php elseif ($company['status'] !== 'active'): ?>

<div class="page-head">
    <div>
        <h1>Подрядчики</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?> (ID: <?= $company['id'] ?>)</p>
    </div>
</div>

<div class="notice warn">
    Компания находится в статусе «<?= e($company['status']) ?>». Создание подрядчиков недоступно.
</div>

<?php elseif (isset($dbError)): ?>

<div class="page-head">
    <div>
        <h1>Подрядчики</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?> (ID: <?= $company['id'] ?>)</p>
    </div>
</div>

<div class="notice warn">
    <?= e($dbError) ?>
</div>

<?php elseif (empty($contractors)): ?>

<div class="page-head">
    <div>
        <h1>Подрядчики</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?> (ID: <?= $company['id'] ?>)</p>
    </div>
    <div class="page-head-actions">
        <a href="/company/contractors/create" class="btn btn-primary">Создать подрядчика</a>
    </div>
</div>

<div class="panel">
    <div class="panel-body">
        <div class="empty-state">
            <p>Подрядчики ещё не созданы.</p>
            <a href="/company/contractors/create" class="btn btn-primary">Создать первого подрядчика</a>
        </div>
    </div>
</div>

<?php else: ?>

<div class="page-head">
    <div>
        <h1>Подрядчики</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?> (ID: <?= $company['id'] ?>)</p>
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
                        <th>ID</th>
                        <th>Название</th>
                        <th>ИНН</th>
                        <th>Тип</th>
                        <th>КПП</th>
                        <th>Главный контакт</th>
                        <th>Email для док.</th>
                        <?php if (($_SESSION['role_code'] ?? '') === 'company_owner'): ?>
                        <th>Владелец</th>
                        <?php endif; ?>
                        <th>Статус</th>
                        <th>Создан</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($contractors as $c): ?>
                    <tr>
                        <td class="col-mono"><?= $c['id'] ?></td>
                        <td><?= e($c['name']) ?></td>
                        <td class="col-mono"><?= e($c['inn']) ?></td>
                        <td><?= !empty($c['contractor_type']) ? e($c['contractor_type']) : '—' ?></td>
                        <td class="col-mono"><?= e($c['kpp'] ?? '') ?: '—' ?></td>
                        <td><?= e($c['primary_contact_person'] ?? '') ?: '—' ?><?php if (!empty($c['primary_contact_phone'])): ?><br><small class="text-muted"><?= e($c['primary_contact_phone']) ?></small><?php endif; ?></td>
                        <td><?= e($c['doc_email'] ?? '') ?: '—' ?></td>
                        <?php if (($_SESSION['role_code'] ?? '') === 'company_owner'): ?>
                        <td class="col-muted"><?= e($c['created_by_role'] ?? '—') ?> #<?= e($c['created_by_user_id'] ?? '—') ?></td>
                        <?php endif; ?>
                        <td>
                            <span class="badge<?= $c['status'] === 'active' ? ' badge-ok' : '' ?>">
                                <span class="dot"></span>
                                <?= $c['status'] === 'active' ? 'Активен' : 'Неактивен' ?>
                            </span>
                        </td>
                        <td class="col-muted"><?= e($c['created_at']) ?></td>
                        <td class="col-actions">
                            <a href="/company/contractors/<?= $c['id'] ?>" class="btn btn-toolbar">Просмотр</a>
                            <a href="/company/contractors/<?= $c['id'] ?>/edit" class="btn btn-toolbar">Редактировать</a>
                            <a href="/company/documents?entity_type=contractor&entity_id=<?= $c['id'] ?>" class="btn btn-toolbar">Документы</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php endif; ?>
