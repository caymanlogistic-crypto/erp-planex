<?php if ($company === null): ?>

<div class="notice warn">
    Компания не найдена. Укажите корректный company_id.
</div>

<?php elseif ($company['status'] !== 'active'): ?>

<div class="page-head">
    <div>
        <h1>Типы документов</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?></p>
    </div>
    <div class="page-head-actions">
        <a href="<?= app_url('/company/dashboard') ?>" class="btn btn-ghost">← На главную</a>
    </div>
</div>

<div class="notice warn">
    Компания находится в статусе «<?= e($company['status']) ?>». Просмотр типов документов недоступен.
</div>

<?php elseif (!empty($dbError)): ?>

<div class="page-head">
    <div>
        <h1>Типы документов</h1>
    </div>
</div>
<div class="notice warn">
    <?= e($dbError) ?>
</div>

<?php else: ?>

<div class="page-head">
    <div>
        <h1>Типы документов</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?></p>
    </div>
    <div class="page-head-actions">
        <a href="<?= app_url('/company/document-types/create') ?>" class="btn btn-primary">+ Создать тип</a>
    </div>
</div>

<?php if ($deleteError): ?>
<div class="notice warn"><?= e($deleteError) ?></div>
<?php endif; ?>

<?php if ($deleteSuccess): ?>
<div class="notice success">Тип документа удалён.</div>
<?php endif; ?>

<div class="panel">
    <div class="panel-body">

        <?php if (empty($types) && empty($filterEntity)): ?>
            <div class="empty-state">
                <p class="empty-title">Типы документов не найдены</p>
                <p class="empty-desc">Создайте типы документов для каталогизации.</p>
                <a href="<?= app_url('/company/document-types/create') ?>" class="btn btn-primary">Создать тип</a>
            </div>
        <?php else: ?>

            <?php if (!empty($entityTypes)): ?>
            <div class="table-toolbar">
                <div>
                    <a href="<?= app_url('/company/document-types') ?>" class="btn btn-ghost btn-sm<?= empty($filterEntity) ? ' btn-primary' : '' ?>">Все</a>
                    <?php foreach ($entityTypes as $et): ?>
                    <a href="/company/document-types?entity_type=<?= e($et) ?>" class="btn btn-ghost btn-sm<?= $filterEntity === $et ? ' btn-primary' : '' ?>"><?= e(ui_entity_type($et)) ?></a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if (empty($types)): ?>
                <div class="empty-state">
                    <p class="empty-title">Нет типов для выбранного фильтра</p>
                    <p class="empty-desc">Создайте новый тип документа или измените фильтр.</p>
                </div>
            <?php else: ?>
            <div class="table-scroll">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Название</th>
                            <th>Код</th>
                            <th>Тип сущности</th>
                            <th>Категория</th>
                            <th>Порядок</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($types as $t): ?>
                        <tr>
                            <td>
                                <strong><?= e($t['name']) ?></strong>
                                <?php if ($t['category'] === 'predefined'): ?>
                                    <span class="badge badge-neutral">Системный</span>
                                <?php endif; ?>
                            </td>
                            <td><code><?= e($t['code'] ?? '—') ?></code></td>
                            <td><?= e(ui_entity_type($t['entity_type'] ?? '') ?: 'Все') ?></td>
                            <td>
                                <?php if ($t['category'] === 'predefined'): ?>
                                    <span class="badge badge-ok">Предопределённый</span>
                                <?php else: ?>
                                    <span class="badge badge-neutral">Пользовательский</span>
                                <?php endif; ?>
                            </td>
                            <td><?= (int)$t['sort_order'] ?></td>
                            <td class="row-actions">
                                <a href="/company/document-types/<?= $t['id'] ?>/edit" class="btn btn-ghost btn-sm">Редактировать</a>
                                <?php if ($t['category'] !== 'predefined'): ?>
                                <form method="post" action="/company/document-types/<?= $t['id'] ?>/delete" style="display:inline;" onsubmit="return confirm('Удалить тип документа «<?= e($t['name']) ?>»?')">
                                    <button type="submit" class="btn btn-ghost btn-sm">Удалить</button>
                                </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        <?php endif; ?>

    </div>
</div>

<?php endif; ?>
