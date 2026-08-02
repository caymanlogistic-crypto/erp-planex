<?php
/**
 * @var array $records
 * @var array $companies
 * @var array $entityTypes
 */
?>
<div class="page-head">
    <div class="page-head-left">
        <h1 class="page-title">Удалённые данные</h1>
        <div class="page-summary"><span>Просмотр и восстановление удалённых записей всех компаний</span></div>
    </div>
</div>

<?php if (!empty($_GET['error'])): ?>
<?php $errorMsg = $_GET['error'] === 'company_deletion_final' ? 'Восстановление компании невозможно — компания была безвозвратно удалена.' : e($_GET['error']); ?>
<div class="notice warn">Ошибка: <?= $errorMsg ?></div>
<?php endif; ?>
<?php if (!empty($_GET['restored'])): ?>
<div class="notice success">Запись успешно восстановлена.
    <?php if (!empty($_GET['redirect'])): ?>
    <a href="<?= e($_GET['redirect']) ?>">Перейти к записи в компании →</a>
    <?php endif; ?>
</div>
<?php endif; ?>

<div class="panel">
    <div class="panel-body">
        <?php if (empty($records)): ?>
        <div class="empty-state">
            <p class="empty-title">Нет удалённых записей</p>
            <p class="empty-desc">Удалённые данные всех компаний отображаются здесь для просмотра и восстановления.</p>
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
                        <?php if ($r['status'] === 'archived' && $r['entity_type'] !== 'company'): ?>
                        <form method="post" action="/superadmin/deleted-data/<?= (int)$r['id'] ?>/restore" class="inline-form" onsubmit="return confirm('Восстановить запись? Она снова появится в рабочем списке компании.')">
                            <button type="submit" class="btn btn-primary btn-sm">Восстановить</button>
                        </form>
                        <?php endif; ?>
                        <?php if ($r['entity_type'] === 'company'): ?>
                        <span class="text-muted">Безвозвратно удалена</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>
