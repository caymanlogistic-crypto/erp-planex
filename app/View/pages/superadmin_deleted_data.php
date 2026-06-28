<?php
/**
 * @var array $records
 * @var array $companies
 * @var array $entityTypes
 */
?>
<div class="page-head">
    <div>
        <h1>Удалённые данные</h1>
        <p class="text-muted">Просмотр и восстановление удалённых записей всех компаний</p>
    </div>
</div>

<?php if (!empty($_GET['error'])): ?>
<div class="notice warn">Ошибка: <?= e($_GET['error']) ?></div>
<?php endif; ?>
<?php if (!empty($_GET['restored'])): ?>
<div class="notice success">Запись успешно восстановлена.
    <?php if (!empty($_GET['redirect'])): ?>
    <a href="<?= e($_GET['redirect']) ?>">Перейти к записи в компании →</a>
    <?php endif; ?>
</div>
<?php endif; ?>

<div class="panel">
    <div class="panel-body" style="overflow-x:auto;">
        <?php if (empty($records)): ?>
        <div class="empty-state">
            <p class="text-muted">Нет удалённых записей.</p>
        </div>
        <?php else: ?>
        <table class="table">
            <thead>
                <tr>
                    <th>Дата удаления</th>
                    <th>Компания</th>
                    <th>Кто удалил</th>
                    <th>Роль</th>
                    <th>Тип сущности</th>
                    <th>Название / ФИО</th>
                    <th>Статус</th>
                    <th>Действия</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($records as $r): ?>
                <tr>
                    <td><?= e($r['deleted_at'] ?? '—') ?></td>
                    <td><?= e($r['company_name'] ?? '#' . $r['company_id']) ?></td>
                    <td><?= e($r['deleted_by_name'] ?? '#' . $r['deleted_by_user_id']) ?></td>
                    <td><?= e($r['deleted_by_role'] ?? '—') ?></td>
                    <td><span class="badge"><?= e($r['entity_type']) ?></span></td>
                    <td><?= e(mb_substr($r['display_name'] ?? '', 0, 80)) ?></td>
                    <td>
                        <?php if ($r['status'] === 'restored'): ?>
                        <span class="badge badge-ok">Восстановлен</span>
                        <?php else: ?>
                        <span class="badge badge-warn">Удалён</span>
                        <?php endif; ?>
                    </td>
                    <td class="table-actions">
                        <a href="/superadmin/deleted-data/<?= (int)$r['id'] ?>" class="btn btn-ghost btn-sm">Просмотр</a>
                        <?php if ($r['status'] === 'archived'): ?>
                        <form method="post" action="/superadmin/deleted-data/<?= (int)$r['id'] ?>/restore" style="display:inline;" onsubmit="return confirm('Восстановить запись? Она снова появится в рабочем списке компании.')">
                            <button type="submit" class="btn btn-primary btn-sm">Восстановить</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>
