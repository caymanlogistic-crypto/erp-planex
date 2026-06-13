<?php

function statusBadge(string $status): string
{
    $map = [
        'active'       => ['class' => 'badge-ok',    'label' => 'Активен'],
        'provisioning' => ['class' => 'badge-warn',  'label' => 'Настройка'],
        'error'        => ['class' => 'badge-danger','label' => 'Ошибка'],
        'inactive'     => ['class' => '',             'label' => 'Неактивен'],
        'suspended'    => ['class' => 'badge-warn',  'label' => 'Приостановлен'],
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
                <dt>Локальная БД</dt>
                <dd><?= e($company['db_identifier'] ?? '') ?: '—' ?></dd>
                <dt>Storage</dt>
                <dd><?= e($company['storage_path'] ?? '') ?: '—' ?></dd>
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

<?php endif; ?>
