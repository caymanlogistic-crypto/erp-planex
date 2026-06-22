<?php if ($company === null): ?>

<div class="notice warn">
    Компания не найдена. Укажите корректный company_id.
</div>

<?php elseif ($company['status'] !== 'active'): ?>

<div class="page-head">
    <div>
        <h1>Клиенты</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?></p>
    </div>
</div>

<div class="notice warn">
    Компания находится в статусе «<?= e($company['status']) ?>». Создание клиентов недоступно.
</div>

<?php elseif (isset($dbError)): ?>

<div class="page-head">
    <div>
        <h1>Клиенты</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?></p>
    </div>
</div>

<div class="notice warn">
    <?= e($dbError) ?>
</div>

<?php elseif (empty($clients)): ?>

<div class="page-head">
    <div>
        <h1>Клиенты</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?></p>
    </div>
    <div class="page-head-actions">
        <a href="/company/clients/create" class="btn btn-primary">Создать клиента</a>
    </div>
</div>

<div class="table-card table-card--toolbar-only">
    <div class="empty-state">
        <p class="empty-title">Клиенты ещё не созданы.</p>
        <a href="/company/clients/create" class="btn btn-primary">Создать первого клиента</a>
    </div>
</div>

<?php else: ?>

<div class="page-head">
    <div>
        <h1>Клиенты</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?></p>
    </div>
    <div class="page-head-actions">
        <a href="/company/clients/create" class="btn btn-primary">Создать клиента</a>
    </div>
</div>

<div class="table-card table-card--toolbar-only" data-erp-grid>
    <div class="table-toolbar">
        <div class="found-label">Найдено: <b><?= count($clients) ?></b> клиентов</div>
        <div class="toolbar-right">
            <input type="text" class="toolbar-search" placeholder="Поиск по таблице">
        </div>
    </div>
    <div class="table-scroll">
        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Название</th>
                    <th>ИНН</th>
                    <th>Статус</th>
                    <th>Создан</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($clients as $c): ?>
                <tr>
                    <td class="col-mono"><?= $c['id'] ?></td>
                    <td><?= e($c['name']) ?></td>
                    <td class="col-mono"><?= e($c['inn']) ?></td>
                    <td>
                        <?php if ($c['status'] === 'active'): ?>
                        <span class="badge badge-ok"><span class="dot"></span>Активен</span>
                        <?php elseif ($c['status'] === 'inactive'): ?>
                        <span class="badge"><span class="dot"></span>Неактивен</span>
                        <?php elseif ($c['status'] === 'archived'): ?>
                        <span class="badge badge-warn"><span class="dot"></span>Архив</span>
                        <?php else: ?>
                        <span class="badge"><span class="dot"></span><?= e($c['status']) ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="col-muted"><?= e($c['created_at']) ?></td>
                    <td class="col-actions">
                        <div class="row-actions">
                            <a href="/company/clients/<?= $c['id'] ?>" class="btn btn-toolbar">Просмотр</a>
                            <a href="/company/clients/<?= $c['id'] ?>/edit" class="btn btn-toolbar">Редактировать</a>
                            <a href="/company/documents?entity_type=client&entity_id=<?= $c['id'] ?>" class="btn btn-toolbar">Документы</a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php endif; ?>
