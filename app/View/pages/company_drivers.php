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
        <a href="/company/drivers/create?company_id=<?= $companyId ?>" class="btn btn-primary">Создать водителя</a>
    </div>
</div>

<div class="panel">
    <div class="panel-body">
        <div class="empty-state">
            <p>Водители ещё не созданы.</p>
            <a href="/company/drivers/create?company_id=<?= $companyId ?>" class="btn btn-primary">Создать первого водителя</a>
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
        <a href="/company/drivers/create?company_id=<?= $companyId ?>" class="btn btn-primary">Создать водителя</a>
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
                        <th>Телефон</th>
                        <th>Дата выдачи ВУ</th>
                        <th>Дата окончания ВУ</th>
                        <th>Статус</th>
                        <th>Создан</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($drivers as $d): ?>
                    <tr>
                        <td class="col-mono"><?= $d['id'] ?></td>
                        <td><?= e($d['full_name']) ?></td>
                        <td class="col-mono"><?= e($d['phone']) ?></td>
                        <td class="col-mono"><?= e($d['license_issue_date'] ?? '—') ?></td>
                        <td class="col-mono"><?= e($d['license_expire_date'] ?? '—') ?></td>
                        <td>
                            <span class="badge<?= $d['status'] === 'active' ? ' badge-ok' : '' ?>">
                                <span class="dot"></span>
                                <?= $d['status'] === 'active' ? 'Активен' : 'Неактивен' ?>
                            </span>
                        </td>
                        <td class="col-muted"><?= e($d['created_at']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php endif; ?>
