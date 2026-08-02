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
            'href' => $hasOwner ? app_url("/superadmin/companies/{$id}/owner") : app_url("/superadmin/companies/{$id}/create-owner"),
            'action' => $hasOwner ? 'Открыть' : 'Создать',
        ],
        [
            'label' => 'Локальная БД',
            'ok' => $hasLocalDb,
            'desc' => $hasLocalDb ? 'Рабочая база компании доступна.' : 'Данные пользователей, документов и справочников недоступны.',
            'href' => app_url("/superadmin/companies/{$id}"),
            'action' => 'Проверить',
        ],
        [
            'label' => 'Пользователи',
            'ok' => $hasUsers,
            'desc' => $hasUsers ? 'Есть рабочие пользователи компании.' : 'Можно создать обычных пользователей после руководителя.',
            'href' => app_url("/superadmin/companies/{$id}/users"),
            'action' => $hasUsers ? 'Открыть' : 'Создать',
        ],
        [
            'label' => 'Справочники',
            'ok' => $dirsTotal > 0,
            'desc' => $dirsTotal > 0 ? 'Справочники содержат рабочие записи.' : 'Пусто: это нормально для новой компании, но важно для запуска операций.',
            'href' => app_url("/superadmin/companies/{$id}/directories"),
            'action' => 'Аудит',
        ],
        [
            'label' => 'Документы',
            'ok' => $hasDocuments,
            'desc' => $hasDocuments ? 'Есть активные документы.' : 'Документы ещё не загружены или недоступны.',
            'href' => app_url("/superadmin/companies/{$id}/documents"),
            'action' => 'Открыть',
        ],
    ];
    $nextAction = !$hasOwner
        ? ['title' => 'Создать руководителя', 'desc' => 'Это главный блокер: руководитель получает первичный доступ к компании.', 'href' => app_url("/superadmin/companies/{$id}/create-owner"), 'class' => 'btn-primary']
        : (!$hasLocalDb
            ? ['title' => 'Проверить рабочую БД', 'desc' => 'Без локальной БД нельзя подтвердить пользователей, документы и справочники.', 'href' => app_url("/superadmin/companies/{$id}"), 'class' => 'btn-secondary']
            : (!$hasUsers
                ? ['title' => 'Создать пользователя', 'desc' => 'После руководителя можно выдать рабочий доступ сотруднику компании.', 'href' => app_url("/superadmin/companies/{$id}/users/logists/create"), 'class' => 'btn-primary']
                : ['title' => 'Проверить операционные данные', 'desc' => 'Компания готова к администрированию: проверьте справочники и документы.', 'href' => app_url("/superadmin/companies/{$id}/directories"), 'class' => 'btn-secondary']));
?>

<div class="page-head">
    <div class="page-head-left">
        <h1 class="page-title"><?= e($company['name']) ?></h1>
        <div class="page-summary"><span>ID <?= (int)$company['id'] ?> · <?= renderStatusBadge($company['status']) ?> · <?= $isOperational ? 'Компания готова к работе' : 'Требуется настройка' ?></span></div>
    </div>
    <div class="page-head-actions">
        <a href="<?= app_url('/superadmin/companies/' . $company['id'] . '/edit') ?>" class="btn btn-primary">Редактировать</a>
        <a href="<?= app_url('/superadmin/companies') ?>" class="btn btn-ghost">← К реестру</a>
    </div>
</div>

<div class="page-content">

<div class="company-detail-shell">

<?php if (!empty($_SESSION['company_edit_doc_warning'])): ?>
<div class="panel">
    <div class="panel-body">
        <div class="notice warn"><?= e($_SESSION['company_edit_doc_warning']) ?></div>
    </div>
</div>
<?php unset($_SESSION['company_edit_doc_warning']); ?>
<?php endif; ?>

<div class="section-nav">
    <a href="#overview" class="section-nav-item is-active">Обзор</a>
    <a href="#legal" class="section-nav-item">Юр. данные</a>
    <a href="#owner" class="section-nav-item">Руководитель</a>
    <a href="#users" class="section-nav-item">Пользователи</a>
    <a href="#tech" class="section-nav-item">Техническое</a>
    <a href="#documents" class="section-nav-item">Документы</a>
    <a href="#directories" class="section-nav-item">Справочники</a>
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
                        <a href="<?= e($item['href']) ?>" class="btn btn-ghost btn-sm"<?= (!$item['ok'] && str_contains($item['href'], '/create-owner')) ? ' data-owner-create-modal data-company-id="' . (int)$id . '"' : '' ?>><?= e($item['action']) ?></a>
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
            <a href="<?= e($nextAction['href']) ?>" class="btn <?= e($nextAction['class']) ?>"<?= str_contains($nextAction['href'], '/create-owner') ? ' data-owner-create-modal data-company-id="' . (int)$id . '"' : '' ?>><?= e($nextAction['title']) ?></a>
        </div>
    </div>
</div>

<div class="company-card-grid">

    <div class="company-card company-card--wide" id="legal">
        <div class="company-card-head">Юридические данные</div>
        <div class="company-card-body">
            <div class="cd-mini-grid">
                <div class="cd-mini-item cd-mini-item--wide">
                    <span class="cd-mini-label">Название</span>
                    <span class="cd-mini-value"><?= e($company['name']) ?></span>
                </div>
                <div class="cd-mini-item">
                    <span class="cd-mini-label">ИНН</span>
                    <span class="cd-mini-value"><?= e($company['inn'] ?? '') ?: '—' ?></span>
                </div>
                <div class="cd-mini-item">
                    <span class="cd-mini-label">КПП</span>
                    <span class="cd-mini-value"><?= e($company['kpp'] ?? '') ?: '—' ?></span>
                </div>
                <div class="cd-mini-item">
                    <span class="cd-mini-label">ОГРН</span>
                    <span class="cd-mini-value"><?= e($company['ogrn'] ?? '') ?: '—' ?></span>
                </div>
                <div class="cd-mini-item">
                    <span class="cd-mini-label">Статус</span>
                    <span class="cd-mini-value"><?= renderStatusBadge($company['status']) ?></span>
                </div>
                <div class="cd-mini-item">
                    <span class="cd-mini-label">Должность руководителя</span>
                    <span class="cd-mini-value"><?= e($company['director_position'] ?? '') ?: '—' ?></span>
                </div>
                <div class="cd-mini-item cd-mini-item--wide">
                    <span class="cd-mini-label">ФИО руководителя</span>
                    <span class="cd-mini-value"><?= e($company['director_full_name'] ?? '') ?: '—' ?></span>
                </div>
                <div class="cd-mini-item cd-mini-item--wide">
                    <span class="cd-mini-label">Юридический адрес</span>
                    <span class="cd-mini-value"><?= e($company['legal_address'] ?? '') ?: '—' ?></span>
                </div>
                <div class="cd-mini-item cd-mini-item--wide">
                    <span class="cd-mini-label">Фактический адрес</span>
                    <span class="cd-mini-value"><?= e($company['physical_address'] ?? '') ?: '—' ?></span>
                </div>
                <div class="cd-mini-item">
                    <span class="cd-mini-label">Расчётный счёт</span>
                    <span class="cd-mini-value cd-mini-value--mono"><?= e($company['bank_account'] ?? '') ?: '—' ?></span>
                </div>
                <div class="cd-mini-item">
                    <span class="cd-mini-label">БИК</span>
                    <span class="cd-mini-value cd-mini-value--mono"><?= e($company['bank_bik'] ?? '') ?: '—' ?></span>
                </div>
                <div class="cd-mini-item cd-mini-item--wide">
                    <span class="cd-mini-label">Банк</span>
                    <span class="cd-mini-value"><?= e($company['bank_name'] ?? '') ?: '—' ?></span>
                </div>
                <div class="cd-mini-item">
                    <span class="cd-mini-label">Корр. счёт</span>
                    <span class="cd-mini-value cd-mini-value--mono"><?= e($company['bank_corr_account'] ?? '') ?: '—' ?></span>
                </div>
                <div class="cd-mini-item cd-mini-item--wide">
                    <span class="cd-mini-label">Комментарий</span>
                    <span class="cd-mini-value"><?= e($company['comments'] ?? '') ?: '—' ?></span>
                </div>
            </div>
        </div>
    </div>

    <div class="company-card" id="owner" data-owner-id="<?= $owner ? (int)$company['id'] : '' ?>" data-erp-owner-card>
        <div class="company-card-head">
            <span>ERP-доступ руководителя</span>
            <?php if ($owner): ?>
            <a href="<?= app_url('/superadmin/companies/' . (int)$company['id'] . '/owner') ?>" class="btn btn-ghost btn-sm">Открыть</a>
            <?php else: ?>
            <a href="<?= app_url('/superadmin/companies/' . (int)$company['id'] . '/create-owner') ?>" class="btn btn-primary btn-sm" data-owner-create-modal data-company-id="<?= (int)$company['id'] ?>">Создать ERP-доступ</a>
            <?php endif; ?>
        </div>
        <div class="company-card-body">
            <?php if ($owner): ?>
            <div class="cd-mini-grid cd-mini-grid--cols-2">
                <div class="cd-mini-item cd-mini-item--wide">
                    <span class="cd-mini-label">ФИО</span>
                    <span class="cd-mini-value"><?= e($owner['full_name']) ?></span>
                </div>
                <div class="cd-mini-item cd-mini-item--wide">
                    <span class="cd-mini-label">Логин</span>
                    <span class="cd-mini-value cd-mini-value--mono"><?= e($owner['login']) ?></span>
                </div>
                <div class="cd-mini-item">
                    <span class="cd-mini-label">Статус</span>
                    <span class="cd-mini-value"><?= renderStatusBadge($owner['status']) ?></span>
                </div>
            </div>
            <?php else: ?>
            <div class="cd-notice">ERP-доступ не создан.</div>
            <?php endif; ?>
        </div>
    </div>

    <div class="company-card" id="users">
        <div class="company-card-head">
            <span>Пользователи компании</span>
            <a href="<?= app_url('/superadmin/companies/' . $id . '/users') ?>" class="btn btn-ghost btn-sm">Все →</a>
        </div>
        <div class="company-card-body">
            <div class="cd-stat-row">
                <div class="cd-stat-item">
                    <span class="cd-stat-num"><?= (int)$userStats['total'] ?></span>
                    <span class="cd-stat-label">Всего</span>
                </div>
                <div class="cd-stat-item">
                    <span class="cd-stat-num"><?= (int)$userStats['active'] ?></span>
                    <span class="cd-stat-label">Активных</span>
                </div>
                <div class="cd-stat-item">
                    <span class="cd-stat-num"><?= (int)$userStats['blocked'] ?></span>
                    <span class="cd-stat-label">Заблок.</span>
                </div>
                <div class="cd-stat-item">
                    <span class="cd-stat-num"><?= (int)$userStats['owner_count'] ?></span>
                    <span class="cd-stat-label">Рук-ль</span>
                </div>
                <div class="cd-stat-item">
                    <span class="cd-stat-num"><?= (int)$userStats['logist_count'] ?></span>
                    <span class="cd-stat-label">Пользователей</span>
                </div>
            </div>
        </div>
    </div>

    <div class="company-card" id="tech">
        <div class="company-card-head">Техническая готовность</div>
        <div class="company-card-body">
            <div class="cd-mini-grid">
                <div class="cd-mini-item">
                    <span class="cd-mini-label">Company ID</span>
                    <span class="cd-mini-value cd-mini-value--mono"><?= (int)$company['id'] ?></span>
                </div>
                <div class="cd-mini-item cd-mini-item--wide">
                    <span class="cd-mini-label">Локальная БД</span>
                    <span class="cd-mini-value cd-mini-value--mono"><?= e($company['db_identifier'] ?? '') ?: '—' ?></span>
                </div>
                <div class="cd-mini-item">
                    <span class="cd-mini-label">БД существует</span>
                    <span class="cd-mini-value"><span class="badge <?= $hasLocalDb ? 'badge-ok' : 'badge-danger' ?>"><?= $hasLocalDb ? 'YES' : 'NO' ?></span></span>
                </div>
                <div class="cd-mini-item">
                    <span class="cd-mini-label">Storage существует</span>
                    <span class="cd-mini-value"><span class="badge <?= !empty($storageExists) ? 'badge-ok' : 'badge-warning' ?>"><?= !empty($storageExists) ? 'YES' : 'NO' ?></span></span>
                </div>
                <div class="cd-mini-item">
                    <span class="cd-mini-label">Создана</span>
                    <span class="cd-mini-value"><?= e($company['created_at'] ?? '') ?></span>
                </div>
                <div class="cd-mini-item">
                    <span class="cd-mini-label">Обновлена</span>
                    <span class="cd-mini-value"><?= e($company['updated_at'] ?? '') ?></span>
                </div>
            </div>
        </div>
    </div>

    <div class="company-card company-card--wide" id="documents">
        <div class="company-card-head">
            <span>Документы компании</span>
            <a href="<?= app_url('/superadmin/companies/' . $id . '/documents') ?>" class="btn btn-ghost btn-sm">Все →</a>
        </div>
        <div class="company-card-body">
            <?php if (!empty($companyDocs)): ?>
            <div class="file-list">
                <?php foreach (array_slice($companyDocs, 0, 8) as $doc):
                    $ext = strtoupper(pathinfo($doc['original_name'] ?? '', PATHINFO_EXTENSION));
                    $badgeCls = 'file-type-badge';
                    $badgeTxt = 'FILE';
                    if ($ext === 'PDF') { $badgeCls .= ' is-pdf'; $badgeTxt = 'PDF'; }
                    elseif (in_array($ext, ['DOC','DOCX','RTF','ODT'], true)) { $badgeCls .= ' is-doc'; $badgeTxt = 'DOC'; }
                    elseif (in_array($ext, ['XLS','XLSX','CSV','ODS'], true)) { $badgeCls .= ' is-xls'; $badgeTxt = 'XLS'; }
                    elseif (in_array($ext, ['JPG','JPEG','PNG','WEBP','GIF','BMP','TIF','TIFF','HEIC','HEIF'], true)) { $badgeCls .= ' is-img'; $badgeTxt = 'IMG'; }
                    else { $badgeCls .= ' is-other'; $badgeTxt = $ext ?: 'FILE'; }
                    $typeLabel = $doc['type_name'] ?? $doc['document_type'] ?? '';
                ?>
                <div class="file-item file-item-predef document-file-row has-file has-existing-file driver-doc-view-item">
                    <div class="<?= $badgeCls ?>"><?= $badgeTxt ?></div>
                    <div class="file-info">
                        <div class="file-name"><?= e($typeLabel ?: $doc['original_name']) ?></div>
                        <div class="file-meta"><?= e($doc['original_name']) ?></div>
                    </div>
                    <a href="<?= app_url('/superadmin/companies/' . (int)$id . '/documents/' . (int)$doc['id'] . '/view') ?>" target="_blank" rel="noopener" class="btn btn-secondary file-action-btn js-doc-popup-window"><span>Просмотр</span></a>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="notice info">Документы компании не загружены.</div>
            <?php endif; ?>
            <div class="form-actions mt-2">
                <a href="<?= app_url('/superadmin/companies/' . $id . '/documents') ?>" class="btn btn-secondary btn-sm">Все документы (<?= (int)$docStats['active'] ?>)</a>
                <a href="<?= app_url('/superadmin/companies/' . $id . '/access-grants') ?>" class="btn btn-secondary btn-sm">Доступы (<?= (int)$accessStats['total'] ?>)</a>
            </div>
        </div>
    </div>

</div>

<div class="company-card" id="directories">
    <div class="company-card-head">
        <span>Справочники компании</span>
        <a href="<?= app_url('/superadmin/companies/' . $id . '/directories') ?>" class="btn btn-ghost btn-sm">Все →</a>
    </div>
    <div class="company-card-body">
        <div class="tbl-wrap">
            <table class="tbl cd-dirs-tbl">
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
                        <td class="col-tight"><a href="<?= app_url('/superadmin/companies/' . $id . '/clients') ?>" class="btn btn-ghost btn-sm">Открыть</a></td>
                    </tr>
                    <tr>
                        <td>Подрядчики</td>
                        <td class="col-num"><?= (int)$dirs['contractors_total'] ?></td>
                        <td class="col-num"><?= (int)$dirs['contractors_active'] ?></td>
                        <td class="col-num"><?= (int)$dirs['contractors_archived'] ?></td>
                        <td class="col-tight"><a href="<?= app_url('/superadmin/companies/' . $id . '/contractors') ?>" class="btn btn-ghost btn-sm">Открыть</a></td>
                    </tr>
                    <tr>
                        <td>Водители</td>
                        <td class="col-num"><?= (int)$dirs['drivers_total'] ?></td>
                        <td class="col-num"><?= (int)$dirs['drivers_active'] ?></td>
                        <td class="col-num"><?= (int)$dirs['drivers_archived'] ?></td>
                        <td class="col-tight"><a href="<?= app_url('/superadmin/companies/' . $id . '/drivers') ?>" class="btn btn-ghost btn-sm">Открыть</a></td>
                    </tr>
                    <tr>
                        <td>Транспортные единицы</td>
                        <td class="col-num"><?= (int)$dirs['vehicle_units_total'] ?></td>
                        <td class="col-num"><?= (int)$dirs['vehicle_units_active'] ?></td>
                        <td class="col-num"><?= (int)$dirs['vehicle_units_archived'] ?></td>
                        <td class="col-tight"><a href="<?= app_url('/superadmin/companies/' . $id . '/vehicles') ?>" class="btn btn-ghost btn-sm">Открыть</a></td>
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
                        <td class="col-tight"><a href="<?= app_url('/superadmin/companies/' . $id . '/crews') ?>" class="btn btn-ghost btn-sm">Открыть</a></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="company-action-section" id="status-actions">
    <div class="company-card-head">Управление статусом</div>
    <div class="company-action-body">
        <span class="cd-action-hint">Обычные действия меняют доступность компании, но не удаляют данные.</span>
        <div class="cd-action-buttons">
            <?php if ($company['status'] !== 'active'): ?>
            <form method="post" action="<?= app_url('/superadmin/companies/' . $id . '/activate') ?>" onsubmit="return confirm('Активировать компанию?')">
                <button type="submit" class="btn btn-primary btn-sm">Активировать</button>
            </form>
            <?php endif; ?>
            <?php if ($company['status'] === 'active'): ?>
            <form method="post" action="<?= app_url('/superadmin/companies/' . $id . '/deactivate') ?>" onsubmit="return confirm('Отключить компанию?')">
                <button type="submit" class="btn btn-secondary btn-sm">Отключить</button>
            </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="company-action-section company-action-section--danger" id="danger">
    <div class="company-card-head">Опасная зона</div>
    <div class="company-action-body">
        <div class="cd-danger-row">
            <div class="cd-danger-step">
                <strong>Ограничить доступ</strong>
                <span>Блокировка не удаляет данные, но пользователи не смогут войти.</span>
            </div>
            <div class="cd-danger-step">
                <strong>Архивировать</strong>
                <span>Компания сохраняется в системе как неактивная запись.</span>
            </div>
            <div class="cd-danger-step cd-danger-step--terminal">
                <strong>Физически удалить</strong>
                <span>Удаляет локальную БД, storage, пользователей, документы и справочники. Это отдельный подтверждаемый процесс.</span>
            </div>
        </div>
        <div class="cd-action-buttons">
            <?php if (in_array($company['status'], ['active', 'inactive'], true)): ?>
            <form method="post" action="<?= app_url('/superadmin/companies/' . $id . '/block') ?>" onsubmit="return confirm('Заблокировать компанию? Пользователи не смогут войти.')">
                <button type="submit" class="btn btn-danger btn-sm">Заблокировать</button>
            </form>
            <?php endif; ?>
            <form method="post" action="<?= app_url('/superadmin/companies/' . $id . '/archive') ?>" onsubmit="return confirm('Архивировать компанию? Все данные сохранятся.')">
                <button type="submit" class="btn btn-secondary btn-sm">Архивировать</button>
            </form>
            <a href="<?= app_url('/superadmin/companies/' . $id . '/delete') ?>" class="btn btn-danger btn-sm">Физическое удаление →</a>
        </div>
    </div>
</div>

</div><!-- /.company-detail-shell -->
</div><!-- /.page-content -->

<!-- Owner create modal -->
<div class="modal-overlay driver-view-overlay" id="sa-owner-create-modal" data-close-on-overlay="0" data-close-on-escape="0" data-reset-on-close="1">
  <div class="modal modal-lg driver-view-modal-inner">
    <div class="modal-head">
      <span class="modal-title">Создать руководителя</span>
      <button type="button" class="modal-close" data-owner-create-close>✕</button>
    </div>
    <div id="sa-owner-create-modal-content">
      <div class="modal-body">
        <div class="driver-modal-loading">...</div>
      </div>
    </div>
  </div>
</div>

<!-- Owner view modal -->
<div class="modal-overlay driver-view-overlay" id="sa-owner-view-modal" data-close-on-overlay="0" data-close-on-escape="0" data-reset-on-close="1">
  <div class="modal modal-lg user-view-modal-inner">
    <div class="modal-head">
      <span class="modal-title">Руководитель</span>
      <button type="button" class="modal-close" data-owner-view-close>✕</button>
    </div>
    <div class="modal-content"></div>
  </div>
</div>

<?php endif; ?>
