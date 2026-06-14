<?php if ($company === null): ?>
<div class="panel"><div class="panel-body"><div class="notice warn">Компания не найдена. <a href="/superadmin/companies">← К реестру</a></div></div></div>

<?php else: ?>

<div class="page-head">
    <div>
        <h1>Удаление компании</h1>
        <p class="text-muted">Полное удаление компании, локальной БД, пользователей и документов</p>
    </div>
    <div class="page-head-actions">
        <a href="/superadmin/companies/<?= $company['id'] ?>" class="btn btn-ghost">← К карточке</a>
    </div>
</div>

<?php if (!empty($confirmError)): ?>
<div class="notice danger" style="margin-bottom:12px"><?= e($confirmError) ?></div>
<?php endif; ?>

<div class="panel">
    <div class="panel-head"><h2>Данные для удаления</h2></div>
    <div class="panel-body">
        <dl class="kv">
            <dt>Название</dt><dd><?= e($preview['company_name']) ?></dd>
            <dt>ИНН</dt><dd><?= e($preview['company_inn']) ?></dd>
            <dt>ID</dt><dd><?= $preview['company_id'] ?></dd>
            <dt>Локальная БД</dt><dd><?= e($preview['db_identifier']) ?></dd>
            <dt>Storage</dt><dd><?= e($preview['storage_path']) ?></dd>
            <dt>Статус</dt><dd><?= e($preview['status']) ?></dd>
            <dt>Руководитель</dt><dd><?= $preview['owner'] ? e($preview['owner']['full_name']) : '—' ?></dd>
            <dt>Логистов</dt><dd><?= (int)$preview['logists_count'] ?></dd>
            <dt>Клиентов</dt><dd><?= (int)$preview['clients_count'] ?></dd>
            <dt>Подрядчиков</dt><dd><?= (int)$preview['contractors_count'] ?></dd>
            <dt>Водителей</dt><dd><?= (int)$preview['drivers_count'] ?></dd>
            <dt>Транспорта</dt><dd><?= (int)$preview['vehicles_count'] ?></dd>
            <dt>Экипажей</dt><dd><?= (int)$preview['crews_count'] ?></dd>
            <dt>Документов</dt><dd><?= (int)$preview['documents_count'] ?></dd>
            <dt>Размер storage</dt><dd><?= e($preview['storage_size']) ?></dd>
        </dl>
        <?php if (!empty($localDbError)): ?>
        <div class="notice warn"><?= e($localDbError) ?></div>
        <?php endif; ?>
    </div>
</div>

<div class="panel">
    <div class="panel-head"><h2>Предупреждение</h2></div>
    <div class="panel-body">
        <div class="notice danger" style="margin-bottom:16px">
            Это действие полностью удалит компанию, локальную базу данных, пользователей, документы и файлы. Восстановление возможно только из резервной копии, если она была создана.
        </div>

        <form method="post" action="/superadmin/companies/<?= $company['id'] ?>/delete">
            <div class="form-section">
                <p style="margin-bottom:8px">Для подтверждения введите:</p>
                <p class="col-mono" style="margin-bottom:8px;font-weight:700">DELETE COMPANY <?= $company['id'] ?></p>
                <input type="text" class="field-input" name="confirm_phrase" value="<?= e($confirmValue ?? '') ?>" placeholder="DELETE COMPANY <?= $company['id'] ?>" style="max-width:360px" autocomplete="off">

                <?php if (!empty($backupWarning)): ?>
                <div class="notice warn" style="margin-top:12px">
                    SQL дамп не создан: mysqldump недоступен или не настроен.<br>
                    Storage backup не создан. Локальная БД и файлы будут удалены без резервной копии.
                </div>
                <label style="display:block;margin-top:12px">
                    <input type="checkbox" name="skip_backup" value="1">
                    Я понимаю, что резервная копия не создана
                </label>
                <?php endif; ?>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-danger">Удалить компанию</button>
                <a href="/superadmin/companies/<?= $company['id'] ?>" class="btn btn-ghost">Отмена</a>
            </div>
        </form>
    </div>
</div>

<?php endif; ?>
