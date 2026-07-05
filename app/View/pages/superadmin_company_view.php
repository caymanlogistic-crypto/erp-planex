<?php

require_once __DIR__ . '/../components/status_badge.php';

?>
<?php if ($company === null): ?>

<div class="panel">
    <div class="panel-body">
        <div class="notice warn">
            Компания не найдена. <a href="<?= app_url('/superadmin/companies') ?>">← К реестру</a>
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
            <a href="<?= app_url('/superadmin/companies') ?>" class="btn btn-ghost">← К реестру</a>
        </div>
    </div>
</div>

<?php else: ?>
<?php
    $hasOwner = !empty($owner);
    $hasLocalDb = !empty($localDbExists);
    $hasUsers = (int)($userStats['logist_count'] ?? 0) > 0;
    $dirsTotal = (int)($dirs['clients_total'] ?? 0)
        + (int)($dirs['contractors_total'] ?? 0)
        + (int)($dirs['drivers_total'] ?? 0)
        + (int)($dirs['vehicle_units_total'] ?? 0)
        + (int)($dirs['vehicle_sets_total'] ?? 0)
        + (int)($dirs['driver_vehicle_blocks_total'] ?? 0)
        + (int)($dirs['crews_total'] ?? 0);
    $hasDocuments = (int)($docStats['active'] ?? 0) > 0;
    $isOperational = $company['status'] === 'active' && $hasOwner && $hasLocalDb;
    $readiness = [
        [
            'label' => 'Руководитель',
            'ok' => $hasOwner,
            'desc' => $hasOwner ? 'Есть ответственный за компанию.' : 'Критический шаг: без руководителя компания не готова к работе.',
            'href' => $hasOwner ? "/superadmin/companies/{$id}/owner" : "/superadmin/companies/{$id}/create-owner",
            'action' => $hasOwner ? 'Открыть' : 'Создать',
        ],
        [
            'label' => 'Локальная БД',
            'ok' => $hasLocalDb,
            'desc' => $hasLocalDb ? 'Рабочая база компании доступна.' : 'Данные пользователей, документов и справочников недоступны.',
            'href' => "/superadmin/companies/{$id}",
            'action' => 'Проверить',
        ],
        [
            'label' => 'Пользователи',
            'ok' => $hasUsers,
            'desc' => $hasUsers ? 'Есть рабочие пользователи компании.' : 'Можно создать обычных пользователей после руководителя.',
            'href' => "/superadmin/companies/{$id}/users",
            'action' => $hasUsers ? 'Открыть' : 'Создать',
        ],
        [
            'label' => 'Справочники',
            'ok' => $dirsTotal > 0,
            'desc' => $dirsTotal > 0 ? 'Справочники содержат рабочие записи.' : 'Пусто: это нормально для новой компании, но важно для запуска операций.',
            'href' => "/superadmin/companies/{$id}/directories",
            'action' => 'Аудит',
        ],
        [
            'label' => 'Документы',
            'ok' => $hasDocuments,
            'desc' => $hasDocuments ? 'Есть активные документы.' : 'Документы ещё не загружены или недоступны.',
            'href' => "/superadmin/companies/{$id}/documents",
            'action' => 'Открыть',
        ],
    ];
    $nextAction = !$hasOwner
        ? ['title' => 'Создать руководителя', 'desc' => 'Это главный блокер: руководитель получает первичный доступ к компании.', 'href' => "/superadmin/companies/{$id}/create-owner", 'class' => 'btn-primary']
        : (!$hasLocalDb
            ? ['title' => 'Проверить рабочую БД', 'desc' => 'Без локальной БД нельзя подтвердить пользователей, документы и справочники.', 'href' => "/superadmin/companies/{$id}", 'class' => 'btn-secondary']
            : (!$hasUsers
                ? ['title' => 'Создать пользователя', 'desc' => 'После руководителя можно выдать рабочий доступ сотруднику компании.', 'href' => "/superadmin/companies/{$id}/users/logists/create", 'class' => 'btn-primary']
                : ['title' => 'Проверить операционные данные', 'desc' => 'Компания готова к администрированию: проверьте справочники и документы.', 'href' => "/superadmin/companies/{$id}/directories", 'class' => 'btn-secondary']));
?>

<div class="page-head">
    <div class="page-head-left">
        <span class="page-eyebrow">SUPERADMIN / Реестр компаний</span>
        <span class="page-title"><?= e($company['name']) ?></span>
        <span class="page-summary">
            <b>ID <?= (int)$company['id'] ?></b>
            <span class="sep">·</span>
            <?= renderStatusBadge($company['status']) ?>
            <span class="sep">·</span>
            <span><?= $isOperational ? 'Компания готова к работе' : 'Требуется настройка' ?></span>
        </span>
    </div>
    <div class="page-head-actions">
        <a href="/superadmin/companies/<?= $company['id'] ?>/edit" class="btn btn-primary">Редактировать</a>
        <a href="<?= app_url('/superadmin/companies') ?>" class="btn btn-ghost">← К реестру</a>
    </div>
</div>

<div class="page-content">

<div class="section-nav">
    <a href="#overview" class="section-nav-item is-active">Обзор</a>
    <a href="#owner" class="section-nav-item">Руководитель</a>
    <a href="#users" class="section-nav-item">Пользователи</a>
    <a href="#directories" class="section-nav-item">Справочники</a>
    <a href="#documents" class="section-nav-item">Документы</a>
    <a href="#danger" class="section-nav-item is-danger">Опасная зона</a>
</div>

<div class="command-center" id="overview">
    <div class="command-main">
        <div class="panel">
            <div class="panel-head">
                <span class="panel-head-title">Готовность компании</span>
                <span class="badge <?= $isOperational ? 'badge-ok' : 'badge-warning' ?>"><?= $isOperational ? 'Рабочее состояние' : 'Есть блокеры' ?></span>
            </div>
            <div class="panel-body">
                <div class="readiness-list">
                    <?php foreach ($readiness as $item): ?>
                    <div class="readiness-item <?= $item['ok'] ? 'is-ok' : 'is-warn' ?>">
                        <div class="readiness-mark"></div>
                        <div class="readiness-copy">
                            <span class="readiness-title"><?= e($item['label']) ?></span>
                            <span class="readiness-desc"><?= e($item['desc']) ?></span>
                        </div>
                        <a href="<?= e($item['href']) ?>" class="btn btn-ghost btn-sm"><?= e($item['action']) ?></a>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    <div class="command-aside">
        <div class="next-action <?= !$hasOwner ? 'is-critical' : '' ?>">
            <span class="next-action-label">Следующее действие</span>
            <strong><?= e($nextAction['title']) ?></strong>
            <span><?= e($nextAction['desc']) ?></span>
            <a href="<?= e($nextAction['href']) ?>" class="btn <?= e($nextAction['class']) ?>"><?= e($nextAction['title']) ?></a>
        </div>
    </div>
</div>

<div class="view-grid">

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

    <div class="panel" id="owner">
        <div class="panel-head">
            <span class="panel-head-title">Руководитель</span>
            <?php if ($owner): ?>
            <a href="/superadmin/companies/<?= $company['id'] ?>/owner" class="btn btn-ghost btn-sm">ERP-доступ</a>
            <?php else: ?>
            <a href="/superadmin/companies/<?= $company['id'] ?>/create-owner" class="btn btn-primary btn-sm">Создать ERP-доступ</a>
            <?php endif; ?>
        </div>
        <div class="panel-body">
            <dl class="kv">
                <dt>Должность</dt>
                <dd><?= e($company['director_position'] ?? '') ?: '—' ?></dd>
                <dt>ФИО руководителя</dt>
                <dd><?= e($company['director_full_name'] ?? '') ?: '—' ?></dd>
                <?php if ($owner): ?>
                <dt>Статус ERP-доступа</dt>
                <dd><?= renderStatusBadge($owner['status']) ?></dd>
                <?php endif; ?>
            </dl>
            <?php if (!$owner): ?>
            <div class="notice info mt-2">ERP-доступ для руководителя не создан. Это можно сделать отдельно.</div>
            <?php endif; ?>
        </div>
    </div>

    <div class="panel" id="users">
        <div class="panel-head">
            <span class="panel-head-title">Пользователи компании</span>
            <a href="/superadmin/companies/<?= $id ?>/users" class="btn btn-ghost btn-sm">Все →</a>
        </div>
        <div class="panel-body">
            <dl class="kv">
                <dt>Всего</dt>
                <dd><?= (int)$userStats['total'] ?></dd>
                <dt>Активных</dt>
                <dd><?= (int)$userStats['active'] ?></dd>
                <dt>Заблокированных</dt>
                <dd><?= (int)$userStats['blocked'] ?></dd>
                <dt>Руководитель</dt>
                <dd><?= (int)$userStats['owner_count'] ?></dd>
                <dt>Пользователей</dt>
                <dd><?= (int)$userStats['logist_count'] ?></dd>
            </dl>
        </div>
    </div>

    <div class="panel">
        <div class="panel-head">
            <span class="panel-head-title">Техническая готовность</span>
        </div>
        <div class="panel-body">
            <dl class="kv">
                <dt>Company ID</dt>
                <dd><?= (int)$company['id'] ?></dd>
                <dt>Локальная БД</dt>
                <dd><?= e($company['db_identifier'] ?? '') ?: '—' ?></dd>
                <dt>БД существует</dt>
                <dd><span class="badge <?= $hasLocalDb ? 'badge-ok' : 'badge-danger' ?>"><?= $hasLocalDb ? 'YES' : 'NO' ?></span></dd>
                <dt>Storage</dt>
                <dd><?= e($company['storage_path'] ?? '') ?: '—' ?></dd>
                <dt>Storage существует</dt>
                <dd><span class="badge <?= !empty($storageExists) ? 'badge-ok' : 'badge-warning' ?>"><?= !empty($storageExists) ? 'YES' : 'NO' ?></span></dd>
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

    <div class="panel" id="documents">
        <div class="panel-head">
            <span class="panel-head-title">Документы и доступы</span>
        </div>
        <div class="panel-body">
            <dl class="kv">
                <dt>Всего документов</dt>
                <dd><?= (int)$docStats['total'] ?></dd>
                <dt>Активных документов</dt>
                <dd><?= (int)$docStats['active'] ?></dd>
                <dt>Выданных доступов</dt>
                <dd><?= (int)$accessStats['total'] ?></dd>
            </dl>
            <div class="form-actions">
                <a href="/superadmin/companies/<?= $id ?>/documents" class="btn btn-secondary btn-sm">Документы</a>
                <a href="/superadmin/companies/<?= $id ?>/access-grants" class="btn btn-secondary btn-sm">Доступы</a>
            </div>
        </div>
    </div>

</div>

<div class="panel" id="directories">
    <div class="panel-head">
        <span class="panel-head-title">Справочники компании</span>
        <a href="/superadmin/companies/<?= $id ?>/directories" class="btn btn-ghost btn-sm">Все →</a>
    </div>
    <div class="panel-body">
        <div class="notice info">
            Справочники показывают не просто количество записей, а операционную наполненность компании: клиенты, подрядчики, водители, транспорт и экипажи.
        </div>
        <div class="tbl-wrap mt-actions">
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
                        <td class="col-num"><?= (int)$dirs['clients_total'] ?></td>
                        <td class="col-num"><?= (int)$dirs['clients_active'] ?></td>
                        <td class="col-num"><?= (int)$dirs['clients_archived'] ?></td>
                        <td class="col-tight"><a href="/superadmin/companies/<?= $id ?>/clients" class="btn btn-ghost btn-sm">Открыть</a></td>
                    </tr>
                    <tr>
                        <td>Подрядчики</td>
                        <td class="col-num"><?= (int)$dirs['contractors_total'] ?></td>
                        <td class="col-num"><?= (int)$dirs['contractors_active'] ?></td>
                        <td class="col-num"><?= (int)$dirs['contractors_archived'] ?></td>
                        <td class="col-tight"><a href="/superadmin/companies/<?= $id ?>/contractors" class="btn btn-ghost btn-sm">Открыть</a></td>
                    </tr>
                    <tr>
                        <td>Водители</td>
                        <td class="col-num"><?= (int)$dirs['drivers_total'] ?></td>
                        <td class="col-num"><?= (int)$dirs['drivers_active'] ?></td>
                        <td class="col-num"><?= (int)$dirs['drivers_archived'] ?></td>
                        <td class="col-tight"><a href="/superadmin/companies/<?= $id ?>/drivers" class="btn btn-ghost btn-sm">Открыть</a></td>
                    </tr>
                    <tr>
                        <td>Транспортные единицы</td>
                        <td class="col-num"><?= (int)$dirs['vehicle_units_total'] ?></td>
                        <td class="col-num"><?= (int)$dirs['vehicle_units_active'] ?></td>
                        <td class="col-num"><?= (int)$dirs['vehicle_units_archived'] ?></td>
                        <td class="col-tight"><a href="/superadmin/companies/<?= $id ?>/vehicles" class="btn btn-ghost btn-sm">Открыть</a></td>
                    </tr>
                    <tr>
                        <td>Транспортные комплекты</td>
                        <td class="col-num"><?= (int)$dirs['vehicle_sets_total'] ?></td>
                        <td class="col-num"><?= (int)$dirs['vehicle_sets_active'] ?></td>
                        <td class="col-num"><?= (int)$dirs['vehicle_sets_archived'] ?></td>
                        <td class="col-tight"><span class="text-muted">—</span></td>
                    </tr>
                    <tr>
                        <td>Блоки водитель+ТС</td>
                        <td class="col-num"><?= (int)$dirs['driver_vehicle_blocks_total'] ?></td>
                        <td class="col-num"><?= (int)$dirs['driver_vehicle_blocks_active'] ?></td>
                        <td class="col-num"><?= (int)$dirs['driver_vehicle_blocks_archived'] ?></td>
                        <td class="col-tight"><span class="text-muted">—</span></td>
                    </tr>
                    <tr>
                        <td>Экипажи</td>
                        <td class="col-num"><?= (int)$dirs['crews_total'] ?></td>
                        <td class="col-num"><?= (int)$dirs['crews_active'] ?></td>
                        <td class="col-num"><?= (int)$dirs['crews_archived'] ?></td>
                        <td class="col-tight"><a href="/superadmin/companies/<?= $id ?>/crews" class="btn btn-ghost btn-sm">Открыть</a></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="panel" id="status-actions">
    <div class="panel-head">
        <span class="panel-head-title">Управление статусом</span>
    </div>
    <div class="panel-body">
        <div class="notice info">
            Обычные действия меняют доступность компании, но не удаляют данные.
        </div>
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

<div class="panel panel-danger" id="danger">
    <div class="panel-head">
        <span class="panel-head-title">Опасная зона</span>
    </div>
    <div class="panel-body">
        <div class="danger-flow">
            <div class="danger-step">
                <strong>Ограничить доступ</strong>
                <span>Блокировка не удаляет данные, но пользователи не смогут войти.</span>
            </div>
            <div class="danger-step">
                <strong>Архивировать</strong>
                <span>Компания сохраняется в системе как неактивная запись.</span>
            </div>
            <div class="danger-step is-terminal">
                <strong>Физически удалить</strong>
                <span>Удаляет локальную БД, storage, пользователей, документы и справочники. Это отдельный подтверждаемый процесс.</span>
            </div>
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
            <a href="/superadmin/companies/<?= $id ?>/delete" class="btn btn-danger btn-sm">Физическое удаление →</a>
        </div>
    </div>
</div>

</div><!-- /.page-content -->

<?php endif; ?>
