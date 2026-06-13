<?php if ($company === null): ?>

<div class="notice warn">
    Компания не найдена. Укажите корректный company_id.
</div>

<?php elseif ($company['status'] !== 'active'): ?>

<div class="page-head">
    <div>
        <h1>Логисты</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?> (ID: <?= $company['id'] ?>)</p>
    </div>
</div>

<div class="notice warn">
    Компания находится в статусе «<?= e($company['status']) ?>». Создание логистов недоступно.
</div>

<?php elseif (isset($dbError)): ?>

<div class="page-head">
    <div>
        <h1>Логисты</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?> (ID: <?= $company['id'] ?>)</p>
    </div>
</div>

<div class="notice warn">
    <?= e($dbError) ?>
</div>

<?php elseif (empty($logists)): ?>

<div class="page-head">
    <div>
        <h1>Логисты</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?> (ID: <?= $company['id'] ?>)</p>
    </div>
    <div class="page-head-actions">
        <a href="/company/logists/create?company_id=<?= $companyId ?>" class="btn btn-primary">Создать логиста</a>
    </div>
</div>

<div class="panel">
    <div class="panel-body">
        <div class="empty-state">
            <p>Логисты ещё не созданы.</p>
            <a href="/company/logists/create?company_id=<?= $companyId ?>" class="btn btn-primary">Создать первого логиста</a>
        </div>
    </div>
</div>

<?php else: ?>

<div class="page-head">
    <div>
        <h1>Логисты</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?> (ID: <?= $company['id'] ?>)</p>
    </div>
    <div class="page-head-actions">
        <a href="/company/logists/create?company_id=<?= $companyId ?>" class="btn btn-primary">Создать логиста</a>
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
                        <th>Логин</th>
                        <th>Роль</th>
                        <th>Статус</th>
                        <th>Создан</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logists as $l): ?>
                    <tr>
                        <td class="col-mono"><?= $l['id'] ?></td>
                        <td><?= e($l['full_name']) ?></td>
                        <td class="col-mono"><?= e($l['login']) ?></td>
                        <td>Логист</td>
                        <td>
                            <span class="badge<?= $l['status'] === 'active' ? ' badge-ok' : '' ?>">
                                <span class="dot"></span>
                                <?= $l['status'] === 'active' ? 'Активен' : e($l['status']) ?>
                            </span>
                        </td>
                        <td class="col-muted"><?= e($l['created_at']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php endif; ?>
