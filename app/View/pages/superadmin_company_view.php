<?php

require_once __DIR__ . '/../components/status_badge.php';

?>
<?php if ($company === null): ?>

<div class="panel">
    <div class="panel-body">
        <div class="notice warn">
            Компания не найдена. <a href="/superadmin/companies">← К реестру</a>
        </div>
    </div>
</div>

<?php elseif (isset($dbError)): ?>

<div class="panel">
    <div class="panel-body">
        <div class="notice danger">
            <?= e($dbError) ?>
        </div>
        <div class="form-actions">
            <a href="/superadmin/companies" class="btn btn-ghost">← К реестру</a>
        </div>
    </div>
</div>

<?php else: ?>

<div class="page-head">
    <div class="page-head-left">
        <span class="page-eyebrow">SUPERADMIN / Реестр компаний</span>
        <span class="page-title"><?= e($company['name']) ?></span>
    </div>
    <div class="page-head-actions">
        <a href="/superadmin/companies/<?= $company['id'] ?>/edit" class="btn btn-primary">Редактировать</a>
        <a href="/superadmin/companies" class="btn btn-ghost">← К реестру</a>
    </div>
</div>

<div class="page-content">

<!-- 2-column info grid -->
<div class="view-grid">

    <!-- Основные данные -->
    <div class="panel">
        <div class="panel-head">
            <span class="panel-head-title">Основные данные</span>
        </div>
        <div class="panel-body">
            <dl class="kv">
                <dt>Название</dt>
                <dd><?= e($company['name']) ?></dd>
                <dt>ИНН</dt>
                <dd><?= e($company['inn'] ?? '') ?></dd>
                <dt>КПП</dt>
                <dd><?= e($company['kpp'] ?? '') ?: '—' ?></dd>
                <dt>ОГРН</dt>
                <dd><?= e($company['ogrn'] ?? '') ?: '—' ?></dd>
                <dt>Статус</dt>
                <dd><?= renderStatusBadge($company['status']) ?></dd>
                <dt>Комментарий</dt>
                <dd><?= e($company['comments'] ?? '') ?: '—' ?></dd>
            </dl>
        </div>
    </div>

    <!-- Адреса и контакты -->
    <div class="panel">
        <div class="panel-head">
            <span class="panel-head-title">Адреса и контакты</span>
        </div>
        <div class="panel-body">
            <dl class="kv">
                <dt>Юридический адрес</dt>
                <dd><?= e($company['legal_address'] ?? '') ?: '—' ?></dd>
                <dt>Фактический адрес</dt>
                <dd><?= e($company['physical_address'] ?? '') ?: '—' ?></dd>
                <dt>Контактное лицо</dt>
                <dd><?= e($company['contact_person'] ?? '') ?: '—' ?></dd>
                <dt>Телефон</dt>
                <dd><?= e($company['contact_phone'] ?? '') ?: '—' ?></dd>
                <dt>Email</dt>
                <dd><?= e($company['contact_email'] ?? '') ?: '—' ?></dd>
            </dl>
        </div>
    </div>

    <!-- Руководитель -->
    <div class="panel">
        <div class="panel-head">
            <span class="panel-head-title">Руководитель</span>
            <?php if ($owner): ?>
            <a href="/superadmin/companies/<?= $company['id'] ?>/owner" class="btn btn-ghost btn-sm">Управлять</a>
            <?php else: ?>
            <a href="/superadmin/companies/<?= $company['id'] ?>/create-owner" class="btn btn-ghost btn-sm">Создать</a>
            <?php endif; ?>
        </div>
        <div class="panel-body">
            <?php if ($owner): ?>
            <dl class="kv">
                <dt>ФИО</dt>
                <dd><?= e($owner['full_name']) ?></dd>
                <dt>Должность</dt>
                <dd><?= e($owner['position'] ?? '') ?: '—' ?></dd>
                <dt>Логин</dt>
                <dd><code><?= e($owner['login']) ?></code></dd>
                <dt>Email</dt>
                <dd><?= e($owner['email'] ?? '') ?: '—' ?></dd>
                <dt>Телефон</dt>
                <dd><?= e($owner['phone'] ?? '') ?: '—' ?></dd>
                <dt>Статус</dt>
                <dd><?= renderStatusBadge($owner['status']) ?></dd>
            </dl>
            <?php else: ?>
            <div class="notice info">Руководитель не создан. <a href="/superadmin/companies/<?= $company['id'] ?>/create-owner">Создать</a></div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Пользователи компании -->
    <div class="panel">
        <div class="panel-head">
            <span class="panel-head-title">Пользователи компании</span>
            <a href="/superadmin/companies/<?= $id ?>/users" class="btn btn-ghost btn-sm">Все →</a>
        </div>
        <div class="panel-body">
            <dl class="kv">
                <dt>Всего</dt>
                <dd><?= $userStats['total'] ?></dd>
                <dt>Активных</dt>
                <dd><?= $userStats['active'] ?></dd>
                <dt>Заблокированных</dt>
                <dd><?= $userStats['blocked'] ?></dd>
                <dt>Руководитель</dt>
                <dd><?= $userStats['owner_count'] ?></dd>
                <dt>Пользователей</dt>
                <dd><?= $userStats['logist_count'] ?></dd>
            </dl>
        </div>
    </div>

    <!-- Техническая информация -->
    <div class="panel">
        <div class="panel-head">
            <span class="panel-head-title">Техническая информация</span>
        </div>
        <div class="panel-body">
            <dl class="kv">
                <dt>Company ID</dt>
                <dd><?= $company['id'] ?></dd>
                <dt>Локальная БД</dt>
                <dd><?= e($company['db_identifier'] ?? '') ?: '—' ?></dd>
                <dt>БД существует</dt>
                <dd><?= !empty($localDbExists) ? 'YES' : 'NO' ?></dd>
                <dt>Storage</dt>
                <dd><?= e($company['storage_path'] ?? '') ?: '—' ?></dd>
                <dt>Storage существует</dt>
                <dd><?= !empty($storageExists) ? 'YES' : 'NO' ?></dd>
                <dt>Provisioning</dt>
                <dd>
                    <?= e($company['status']) ?>
                    <?php if ($company['status'] === 'error' && !empty($company['error_message'])): ?>
                        <div class="notice warn mt-actions"><?= e($company['error_message']) ?></div>
                    <?php endif; ?>
                </dd>
                <dt>Создана</dt>
                <dd><?= e($company['created_at'] ?? '') ?></dd>
                <dt>Обновлена</dt>
                <dd><?= e($company['updated_at'] ?? '') ?></dd>
            </dl>
        </div>
    </div>

    <!-- Документы и доступы -->
    <div class="panel">
        <div class="panel-head">
            <span class="panel-head-title">Документы и доступы</span>
        </div>
        <div class="panel-body">
            <dl class="kv">
                <dt>Всего документов</dt>
                <dd><?= $docStats['total'] ?></dd>
                <dt>Активных документов</dt>
                <dd><?= $docStats['active'] ?></dd>
                <dt>Выданных доступов</dt>
                <dd><?= $accessStats['total'] ?></dd>
            </dl>
            <div class="form-actions">
                <a href="/superadmin/companies/<?= $id ?>/documents" class="btn btn-secondary btn-sm">Документы</a>
                <a href="/superadmin/companies/<?= $id ?>/access-grants" class="btn btn-secondary btn-sm">Доступы</a>
            </div>
        </div>
    </div>

</div><!-- /.view-grid -->

<!-- Справочники (full width) -->
<div class="panel">
    <div class="panel-head">
        <span class="panel-head-title">Справочники компании</span>
        <a href="/superadmin/companies/<?= $id ?>/directories" class="btn btn-ghost btn-sm">Все →</a>
    </div>
    <div class="panel-body">
        <div class="tbl-wrap">
            <table class="tbl">
                <thead>
                    <tr>
                        <th>Справочник</th>
                        <th class="col-num">Всего</th>
                        <th class="col-num">Активных</th>
                        <th class="col-num">Архив</th>
                        <th class="col-tight"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Клиенты</td>
                        <td class="col-num"><?= $dirs['clients_total'] ?></td>
                        <td class="col-num"><?= $dirs['clients_active'] ?></td>
                        <td class="col-num"><?= $dirs['clients_archived'] ?></td>
                        <td class="col-tight"><a href="/superadmin/companies/<?= $id ?>/clients" class="btn btn-ghost btn-sm">Открыть</a></td>
                    </tr>
                    <tr>
                        <td>Подрядчики</td>
                        <td class="col-num"><?= $dirs['contractors_total'] ?></td>
                        <td class="col-num"><?= $dirs['contractors_active'] ?></td>
                        <td class="col-num"><?= $dirs['contractors_archived'] ?></td>
                        <td class="col-tight"><a href="/superadmin/companies/<?= $id ?>/contractors" class="btn btn-ghost btn-sm">Открыть</a></td>
                    </tr>
                    <tr>
                        <td>Водители</td>
                        <td class="col-num"><?= $dirs['drivers_total'] ?></td>
                        <td class="col-num"><?= $dirs['drivers_active'] ?></td>
                        <td class="col-num"><?= $dirs['drivers_archived'] ?></td>
                        <td class="col-tight"><a href="/superadmin/companies/<?= $id ?>/drivers" class="btn btn-ghost btn-sm">Открыть</a></td>
                    </tr>
                    <tr>
                        <td>Транспорт</td>
                        <td class="col-num"><?= $dirs['vehicles_total'] ?></td>
                        <td class="col-num"><?= $dirs['vehicles_active'] ?></td>
                        <td class="col-num"><?= $dirs['vehicles_archived'] ?></td>
                        <td class="col-tight"><a href="/superadmin/companies/<?= $id ?>/vehicles" class="btn btn-ghost btn-sm">Открыть</a></td>
                    </tr>
                    <tr>
                        <td>Экипажи</td>
                        <td class="col-num"><?= $dirs['crews_total'] ?></td>
                        <td class="col-num"><?= $dirs['crews_active'] ?></td>
                        <td class="col-num"><?= $dirs['crews_archived'] ?></td>
                        <td class="col-tight"><a href="/superadmin/companies/<?= $id ?>/crews" class="btn btn-ghost btn-sm">Открыть</a></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Управление статусом -->
<div class="panel">
    <div class="panel-head">
        <span class="panel-head-title">Управление статусом</span>
    </div>
    <div class="panel-body">
        <div class="form-actions">
            <?php if ($company['status'] !== 'active'): ?>
            <form method="post" action="/superadmin/companies/<?= $id ?>/activate" onsubmit="return confirm('Активировать компанию?')">
                <button type="submit" class="btn btn-primary btn-sm">Активировать</button>
            </form>
            <?php endif; ?>
            <?php if ($company['status'] === 'active'): ?>
            <form method="post" action="/superadmin/companies/<?= $id ?>/deactivate" onsubmit="return confirm('Отключить компанию?')">
                <button type="submit" class="btn btn-secondary btn-sm">Отключить</button>
            </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Опасная зона -->
<div class="panel panel-danger">
    <div class="panel-head">
        <span class="panel-head-title">Опасная зона</span>
    </div>
    <div class="panel-body">
        <div class="notice danger">
            Действия в этом разделе изменяют статус компании и могут ограничить доступ пользователей.
        </div>
        <div class="form-actions">
            <?php if (in_array($company['status'], ['active', 'inactive'], true)): ?>
            <form method="post" action="/superadmin/companies/<?= $id ?>/block" onsubmit="return confirm('Заблокировать компанию? Пользователи не смогут войти.')">
                <button type="submit" class="btn btn-danger btn-sm">Заблокировать</button>
            </form>
            <?php endif; ?>
            <form method="post" action="/superadmin/companies/<?= $id ?>/archive" onsubmit="return confirm('Архивировать компанию? Все данные сохранятся.')">
                <button type="submit" class="btn btn-secondary btn-sm">Архивировать</button>
            </form>
        </div>
    </div>
</div>

<!-- Полное удаление -->
<div class="panel panel-danger">
    <div class="panel-head">
        <span class="panel-head-title">Полное удаление компании</span>
    </div>
    <div class="panel-body">
        <div class="notice danger">
            <strong>Необратимое действие.</strong> Будет удалена локальная база данных, storage-папка, все пользователи, документы и справочники компании. Восстановление возможно только из резервной копии.
        </div>
        <div class="form-actions">
            <a href="/superadmin/companies/<?= $id ?>/delete" class="btn btn-danger btn-sm">Перейти к удалению →</a>
        </div>
    </div>
</div>

</div><!-- /.page-content -->

<?php endif; ?>
