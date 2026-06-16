<?php if ($company === null): ?>
<div class="panel"><div class="panel-body"><div class="notice warn">Компания не найдена. <a href="/superadmin/companies">← К реестру</a></div></div></div>

<?php else: ?>

<div class="page-head">
    <div class="page-head-left">
        <span class="page-eyebrow">SUPERADMIN / <?= e($company['name']) ?></span>
        <span class="page-title">Удаление компании</span>
    </div>
    <div class="page-head-actions">
        <a href="/superadmin/companies/<?= $company['id'] ?>" class="btn btn-ghost">← Отмена</a>
    </div>
</div>

<div class="page-content">

<?php if (!empty($confirmError)): ?>
<div class="notice danger"><?= e($confirmError) ?></div>
<?php endif; ?>

<div class="panel panel-danger">
    <div class="panel-head">
        <span class="panel-head-title">Процесс физического удаления</span>
        <span class="badge badge-danger">Необратимо</span>
    </div>
    <div class="panel-body">
        <div class="danger-flow">
            <div class="danger-step">
                <strong>1. Проверить объект</strong>
                <span>Сверьте компанию, ИНН, локальную БД, storage и количество связанных данных ниже.</span>
            </div>
            <div class="danger-step">
                <strong>2. Проверить резервную копию</strong>
                <span>Если резервная копия не создана, система потребует отдельное подтверждение риска.</span>
            </div>
            <div class="danger-step is-terminal">
                <strong>3. Подтвердить фразой</strong>
                <span>Удаление запускается только после точной фразы подтверждения. Отмена возвращает в карточку компании.</span>
            </div>
        </div>
    </div>
</div>

<div class="panel">
    <div class="panel-head">
        <span class="panel-head-title">Что будет удалено</span>
    </div>
    <div class="panel-body">
        <dl class="kv">
            <dt>Название</dt><dd><?= e($preview['company_name']) ?></dd>
            <dt>ИНН</dt><dd><?= e($preview['company_inn']) ?></dd>
            <dt>ID</dt><dd class="col-mono"><?= $preview['company_id'] ?></dd>
            <dt>Локальная БД</dt><dd class="col-mono"><?= e($preview['db_identifier']) ?></dd>
            <dt>Storage</dt><dd class="col-mono"><?= e($preview['storage_path']) ?></dd>
            <dt>Статус</dt><dd><?= e($preview['status']) ?></dd>
            <dt>Руководитель</dt><dd><?= $preview['owner'] ? e($preview['owner']['full_name']) : '—' ?></dd>
            <dt>Пользователей</dt><dd><?= (int)$preview['logists_count'] ?></dd>
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

<div class="panel panel-danger">
    <div class="panel-head">
        <span class="panel-head-title">Необратимое действие</span>
    </div>
    <div class="panel-body">
        <div class="notice danger">
            <strong>Внимание.</strong> Будут безвозвратно удалены: локальная база данных, storage-папка, все пользователи, документы и файлы компании. Восстановление возможно только из резервной копии.
        </div>

        <form method="post" action="/superadmin/companies/<?= $company['id'] ?>/delete">
            <div class="form-section">
                <label class="field-label">Для подтверждения введите точную фразу:</label>
                <code class="code-hi">DELETE COMPANY <?= $company['id'] ?></code>
                <input type="text" class="field-input field-confirm" name="confirm_phrase" value="<?= e($confirmValue ?? '') ?>" placeholder="DELETE COMPANY <?= $company['id'] ?>" autocomplete="off">

                <?php if (!empty($backupWarning)): ?>
                <div class="notice warn">
                    <?php if (!empty($backupDetails)): ?><?= e($backupDetails) ?><br><?php endif; ?>
                    Резервная копия не создана. Локальная БД и файлы будут удалены без возможности восстановления.
                </div>
                <label class="label-block">
                    <input type="checkbox" name="skip_backup" value="1">
                    Я понимаю, что резервная копия не создана
                </label>
                <?php endif; ?>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-danger">Удалить компанию навсегда</button>
         
                <a href="/superadmin/companies/<?= $company['id'] ?>" class="btn btn-ghost">← Отмена</a>
            </div>
        </form>
    </div>
</div>

</div><!-- /.page-content -->

<?php endif; ?>
