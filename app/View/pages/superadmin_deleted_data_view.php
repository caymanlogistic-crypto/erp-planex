<?php
/**
 * @var array $record
 */
$snapshot = null;
if (!empty($record['snapshot_json'])) {
    $snapshot = json_decode($record['snapshot_json'], true);
}
?>
<div class="page-head">
    <div>
        <h1>Просмотр удалённой записи</h1>
        <p class="text-muted"><a href="/superadmin/deleted-data">← К списку удалённых данных</a></p>
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
    <div class="panel-head">Общая информация</div>
    <div class="panel-body">
        <table class="table">
            <tbody>
                <tr><td><strong>ID записи</strong></td><td><?= (int)$record['id'] ?></td></tr>
                <tr><td><strong>Компания</strong></td><td><?= e($record['company_name'] ?? '#' . $record['company_id']) ?> (ID: <?= (int)$record['company_id'] ?>)</td></tr>
                <tr><td><strong>Локальная БД</strong></td><td><?= e($record['local_db'] ?? '—') ?></td></tr>
                <tr><td><strong>Тип сущности</strong></td><td><?= e($record['entity_type']) ?></td></tr>
                <tr><td><strong>ID сущности</strong></td><td><?= (int)$record['entity_id'] ?></td></tr>
                <tr><td><strong>Таблица</strong></td><td><?= e($record['source_table']) ?></td></tr>
                <tr><td><strong>Название / ФИО</strong></td><td><?= e($record['display_name'] ?? '—') ?></td></tr>
                <tr><td><strong>Удалён</strong></td><td><?= e($record['deleted_at'] ?? '—') ?></td></tr>
                <tr><td><strong>Кем удалён</strong></td><td><?= e($record['deleted_by_name'] ?? '#' . $record['deleted_by_user_id']) ?></td></tr>
                <tr><td><strong>Роль удалившего</strong></td><td><?= e($record['deleted_by_role'] ?? '—') ?></td></tr>
                <tr><td><strong>Статус</strong></td>
                    <td>
                        <?php if ($record['status'] === 'restored'): ?>
                        <span class="badge badge-ok">Восстановлен</span>
                        <?php else: ?>
                        <span class="badge badge-warn">Удалён</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php if ($record['status'] === 'restored'): ?>
                <tr><td><strong>Восстановлен</strong></td><td><?= e($record['restored_at'] ?? '—') ?></td></tr>
                <tr><td><strong>Кем восстановлен</strong></td>
                    <td>SUPERADMIN (ID: <?= (int)$record['restored_by_user_id'] ?>, роль: <?= e($record['restored_by_role'] ?? '—') ?>)</td>
                </tr>
                <?php endif; ?>
                <?php if (!empty($record['documents_count'])): ?>
                <tr><td><strong>Документов</strong></td><td><?= (int)$record['documents_count'] ?></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($snapshot): ?>
<div class="panel">
    <div class="panel-head">Снимок данных (snapshot)</div>
    <div class="panel-body">
        <pre style="max-height:400px;overflow:auto;background:#f5f5f5;padding:12px;border-radius:6px;font-size:13px;line-height:1.5;"><?= e(json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
    </div>
</div>
<?php endif; ?>

<?php if ($record['status'] === 'archived'): ?>
<div class="form-actions">
    <form method="post" action="/superadmin/deleted-data/<?= (int)$record['id'] ?>/restore" style="display:inline;" onsubmit="return confirm('Восстановить запись? Она снова появится в рабочем списке компании.')">
        <button type="submit" class="btn btn-primary">Восстановить запись</button>
    </form>
</div>
<?php endif; ?>
