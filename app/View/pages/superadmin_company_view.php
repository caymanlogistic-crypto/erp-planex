<?php

function statusBadge(string $status): string
{
    $map = [
        'active'       => ['class' => 'badge-ok',    'label' => 'Активен'],
        'provisioning' => ['class' => 'badge-warn',  'label' => 'Настройка'],
        'error'        => ['class' => 'badge-danger','label' => 'Ошибка'],
        'inactive'     => ['class' => '',             'label' => 'Неактивен'],
        'suspended'    => ['class' => 'badge-warn',  'label' => 'Приостановлен'],
        'blocked'      => ['class' => 'badge-danger', 'label' => 'Заблокирован'],
        'archived'     => ['class' => '',             'label' => 'Архивирован'],
    ];

    $item = $map[$status] ?? ['class' => '', 'label' => $status];

    return '<span class="badge ' . $item['class'] . '"><span class="dot"></span>' . e($item['label']) . '</span>';
}

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
        <div class="form-actions" style="margin-top:16px">
            <a href="/superadmin/companies" class="btn btn-ghost">← К реестру</a>
        </div>
    </div>
</div>

<?php else: ?>

<div class="page-head">
    <div>
        <h1>Компания: <?= e($company['name']) ?></h1>
        <p class="text-muted">ID: <?= $company['id'] ?> · Статус: <?= statusBadge($company['status']) ?></p>
    </div>
    <div class="page-head-actions">
        <a href="/superadmin/companies/<?= $company['id'] ?>/edit" class="btn btn-primary">Редактировать</a>
        <a href="/superadmin/companies" class="btn btn-ghost">← К реестру</a>
    </div>
</div>

<div class="panel">
    <div class="panel-body">

        <div class="form-section">
            <h3 class="panel-head-title">Основные данные</h3>
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
                <dd><?= statusBadge($company['status']) ?></dd>
                <dt>Комментарий</dt>
                <dd><?= e($company['comments'] ?? '') ?: '—' ?></dd>
            </dl>
        </div>

        <div class="form-section">
            <h3 class="panel-head-title">Адреса</h3>
            <dl class="kv">
                <dt>Юридический адрес</dt>
                <dd><?= e($company['legal_address'] ?? '') ?: '—' ?></dd>
                <dt>Фактический адрес</dt>
                <dd><?= e($company['physical_address'] ?? '') ?: '—' ?></dd>
            </dl>
        </div>

        <div class="form-section">
            <h3 class="panel-head-title">Контакты</h3>
            <dl class="kv">
                <dt>Контактное лицо</dt>
                <dd><?= e($company['contact_person'] ?? '') ?: '—' ?></dd>
                <dt>Телефон</dt>
                <dd><?= e($company['contact_phone'] ?? '') ?: '—' ?></dd>
                <dt>Email</dt>
                <dd><?= e($company['contact_email'] ?? '') ?: '—' ?></dd>
            </dl>
        </div>

        <div class="form-section">
            <h3 class="panel-head-title">Техническая информация</h3>
            <dl class="kv">
                <dt>Company ID</dt>
                <dd><?= $company['id'] ?></dd>
                <dt>Локальная БД</dt>
                <dd><?= e($company['db_identifier'] ?? '') ?: '—' ?></dd>
                <dt>Локальная БД существует</dt>
                <dd><?= !empty($localDbExists) ? 'YES' : 'NO' ?></dd>
                <dt>Storage</dt>
                <dd><?= e($company['storage_path'] ?? '') ?: '—' ?></dd>
                <dt>Storage существует</dt>
                <dd><?= !empty($storageExists) ? 'YES' : 'NO' ?></dd>
                <dt>Provisioning</dt>
                <dd>
                    <?= e($company['status']) ?>
                    <?php if ($company['status'] === 'error' && !empty($company['error_message'])): ?>
                        <div class="notice warn" style="margin-top:8px"><?= e($company['error_message']) ?></div>
                    <?php endif; ?>
                </dd>
                <dt>Создана</dt>
                <dd><?= e($company['created_at'] ?? '') ?></dd>
                <dt>Обновлена</dt>
                <dd><?= e($company['updated_at'] ?? '') ?></dd>
            </dl>
        </div>

        <div class="form-section">
            <h3 class="panel-head-title">Руководитель</h3>
            <?php if ($owner): ?>
            <dl class="kv">
                <dt>ФИО</dt>
                <dd><?= e($owner['full_name']) ?></dd>
                <dt>Логин</dt>
                <dd><code><?= e($owner['login']) ?></code></dd>
                <dt>Email</dt>
                <dd><?= e($owner['email'] ?? '') ?: '—' ?></dd>
                <dt>Телефон</dt>
                <dd><?= e($owner['phone'] ?? '') ?: '—' ?></dd>
                <dt>Статус</dt>
                <dd><?= statusBadge($owner['status']) ?></dd>
            </dl>
            <div class="form-actions">
                <a href="/superadmin/companies/<?= $company['id'] ?>/owner" class="btn btn-ghost">Управлять Руководителем</a>
            </div>
            <?php else: ?>
            <p class="text-muted">Руководитель не создан. <a href="/superadmin/companies/<?= $company['id'] ?>/create-owner">Создать</a></p>
            <?php endif; ?>
        </div>

    </div>
</div>

<!-- Section 6: Users -->
<div class="panel">
    <div class="panel-head">
        <h2>Пользователи компании</h2>
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
            <dt>Логистов</dt>
            <dd><?= $userStats['logist_count'] ?></dd>
        </dl>
        <div class="form-actions">
            <a href="/superadmin/companies/<?= $id ?>/users" class="btn btn-secondary">Все пользователи</a>
        </div>
    </div>
</div>

<!-- Section 7: Directories -->
<div class="panel">
    <div class="panel-head">
        <h2>Справочники компании</h2>
    </div>
    <div class="panel-body">
        <div class="tbl-wrap">
            <table class="tbl">
                <thead>
                    <tr>
                        <th>Справочник</th>
                        <th>Всего</th>
                        <th>Активных</th>
                        <th>Архивированных</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Клиенты</td>
                        <td class="col-num"><?= $dirs['clients_total'] ?></td>
                        <td class="col-num"><?= $dirs['clients_active'] ?></td>
                        <td class="col-num"><?= $dirs['clients_archived'] ?></td>
                        <td><a href="/superadmin/companies/<?= $id ?>/clients" class="btn btn-ghost" style="font-size:11px;padding:2px 8px">Открыть</a></td>
                    </tr>
                    <tr>
                        <td>Подрядчики</td>
                        <td class="col-num"><?= $dirs['contractors_total'] ?></td>
                        <td class="col-num"><?= $dirs['contractors_active'] ?></td>
                        <td class="col-num"><?= $dirs['contractors_archived'] ?></td>
                        <td><a href="/superadmin/companies/<?= $id ?>/contractors" class="btn btn-ghost" style="font-size:11px;padding:2px 8px">Открыть</a></td>
                    </tr>
                    <tr>
                        <td>Водители</td>
                        <td class="col-num"><?= $dirs['drivers_total'] ?></td>
                        <td class="col-num"><?= $dirs['drivers_active'] ?></td>
                        <td class="col-num"><?= $dirs['drivers_archived'] ?></td>
                        <td><a href="/superadmin/companies/<?= $id ?>/drivers" class="btn btn-ghost" style="font-size:11px;padding:2px 8px">Открыть</a></td>
                    </tr>
                    <tr>
                        <td>Транспорт</td>
                        <td class="col-num"><?= $dirs['vehicles_total'] ?></td>
                        <td class="col-num"><?= $dirs['vehicles_active'] ?></td>
                        <td class="col-num"><?= $dirs['vehicles_archived'] ?></td>
                        <td><a href="/superadmin/companies/<?= $id ?>/vehicles" class="btn btn-ghost" style="font-size:11px;padding:2px 8px">Открыть</a></td>
                    </tr>
                    <tr>
                        <td>Экипажи</td>
                        <td class="col-num"><?= $dirs['crews_total'] ?></td>
                        <td class="col-num"><?= $dirs['crews_active'] ?></td>
                        <td class="col-num"><?= $dirs['crews_archived'] ?></td>
                        <td><a href="/superadmin/companies/<?= $id ?>/crews" class="btn btn-ghost" style="font-size:11px;padding:2px 8px">Открыть</a></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="form-actions">
            <a href="/superadmin/companies/<?= $id ?>/directories" class="btn btn-secondary">Все справочники</a>
        </div>
    </div>
</div>

<!-- Section 8: Documents -->
<div class="panel">
    <div class="panel-head">
        <h2>Документы компании</h2>
    </div>
    <div class="panel-body">
        <dl class="kv">
            <dt>Всего документов</dt>
            <dd><?= $docStats['total'] ?></dd>
            <dt>Активных (не archived)</dt>
            <dd><?= $docStats['active'] ?></dd>
        </dl>
        <div class="form-actions">
            <a href="/superadmin/companies/<?= $id ?>/documents" class="btn btn-secondary">Все документы</a>
        </div>
    </div>
</div>

<!-- Section 9: Access grants -->
<div class="panel">
    <div class="panel-head">
        <h2>Доступы</h2>
    </div>
    <div class="panel-body">
        <dl class="kv">
            <dt>Выданных доступов</dt>
            <dd><?= $accessStats['total'] ?></dd>
        </dl>
        <div class="form-actions">
            <a href="/superadmin/companies/<?= $id ?>/access-grants" class="btn btn-secondary">Все доступы</a>
        </div>
    </div>
</div>

<!-- Section 10: Actions -->
<div class="panel">
    <div class="panel-head">
        <h2>Действия</h2>
    </div>
    <div class="panel-body">
        <div class="form-actions">
            <?php if ($company['status'] !== 'active'): ?>
            <form method="post" action="/superadmin/companies/<?= $id ?>/activate" style="display:inline" onsubmit="return confirm('Активировать компанию?')">
                <button type="submit" class="btn btn-primary">Активировать</button>
            </form>
            <?php endif; ?>
            <?php if ($company['status'] === 'active'): ?>
            <form method="post" action="/superadmin/companies/<?= $id ?>/block" style="display:inline" onsubmit="return confirm('Заблокировать компанию?')">
                <button type="submit" class="btn btn-danger">Заблокировать</button>
            </form>
            <?php endif; ?>
            <form method="post" action="/superadmin/companies/<?= $id ?>/archive" style="display:inline" onsubmit="return confirm('Архивировать компанию? Все данные сохранятся.')">
                <button type="submit" class="btn btn-danger">Архивировать</button>
            </form>
            <?php if ($company['status'] === 'active'): ?>
            <form method="post" action="/superadmin/companies/<?= $id ?>/deactivate" style="display:inline" onsubmit="return confirm('Отключить компанию?')">
                <button type="submit" class="btn btn-danger">Отключить</button>
            </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php endif; ?>
