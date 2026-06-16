<?php if ($company === null): ?>

<div class="notice warn">
    Компания не найдена. Укажите корректный company_id.
</div>

<?php elseif ($company['status'] !== 'active'): ?>

<div class="page-head">
    <div>
        <h1>Водители</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?> (ID: <?= $company['id'] ?>)</p>
    </div>
</div>

<div class="notice warn">
    Компания находится в статусе «<?= e($company['status']) ?>». Работа с водителями недоступна.
</div>

<?php elseif (isset($dbError)): ?>

<div class="page-head">
    <div>
        <h1>Водители</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?> (ID: <?= $company['id'] ?>)</p>
    </div>
</div>

<div class="notice warn">
    <?= e($dbError) ?>
</div>

<?php elseif (empty($drivers)): ?>

<div class="page-head">
    <div>
        <h1>Водители</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?> (ID: <?= $company['id'] ?>)</p>
    </div>
    <div class="page-head-actions">
        <a href="/company/drivers/create" class="btn btn-primary">Создать водителя</a>
    </div>
</div>

<div class="panel">
    <div class="panel-body">
        <div class="empty-state">
            <p>Водители ещё не созданы.</p>
            <a href="/company/drivers/create" class="btn btn-primary">Создать первого водителя</a>
        </div>
    </div>
</div>

<?php else: ?>

<div class="page-head">
    <div>
        <h1>Водители</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?> (ID: <?= $company['id'] ?>)</p>
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
                        <th>ID</th>
                        <th>ФИО</th>
                        <th>Основной телефон</th>
                        <th>Номер ВУ</th>
                        <th>Паспорт</th>
                        <th>СНИЛС</th>
                        <?php if (($_SESSION['role_code'] ?? '') === 'company_owner'): ?>
                        <th>Владелец</th>
                        <?php endif; ?>
                        <th>Статус</th>
                        <th>Создан</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($drivers as $d): ?>
                    <tr>
                        <td class="col-mono"><?= $d['id'] ?></td>
                        <td><?= e($d['full_name']) ?></td>
                        <td class="col-mono"><?= e($d['main_phone'] ?? '—') ?></td>
                        <td class="col-mono"><?= e($d['license_number'] ?? '—') ?></td>
                        <td class="col-mono"><?= e($d['passport_number'] ?? '—') ?></td>
                        <td class="col-mono"><?= e($d['snils'] ?? '—') ?></td>
                        <?php if (($_SESSION['role_code'] ?? '') === 'company_owner'): ?>
                        <td class="col-muted"><?= e($d['created_by_role'] ?? '—') ?> #<?= e($d['created_by_user_id'] ?? '—') ?></td>
                        <?php endif; ?>
                        <td>
                            <span class="badge<?= $d['status'] === 'active' ? ' badge-ok' : '' ?>">
                                <span class="dot"></span>
                                <?= $d['status'] === 'active' ? 'Активен' : 'Неактивен' ?>
                            </span>
                        </td>
                        <td class="col-muted"><?= e($d['created_at']) ?></td>
                        <td class="col-actions">
                            <a href="/company/drivers/<?= $d['id'] ?>" class="btn btn-toolbar">Просмотр</a>
                            <a href="/company/drivers/<?= $d['id'] ?>/edit" class="btn btn-toolbar">Редактировать</a>
                            <a href="/company/documents?entity_type=driver&entity_id=<?= $d['id'] ?>" class="btn btn-toolbar">Документы</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php endif; ?>
