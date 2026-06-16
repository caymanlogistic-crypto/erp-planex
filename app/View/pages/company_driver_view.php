<?php

require_once __DIR__ . '/../components/status_badge.php';

?>

<?php if ($company === null): ?>

<div class="panel">
    <div class="panel-body">
        <div class="notice warn">
            Компания не найдена. <a href="/company/drivers">← К списку</a>
        </div>
    </div>
</div>

<?php elseif ($company['status'] !== 'active'): ?>

<div class="page-head">
    <div>
        <h1>Водитель</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?></p>
    </div>
</div>

<div class="notice warn">
    Компания находится в статусе «<?= e($company['status']) ?>».
</div>

<?php elseif (isset($dbError)): ?>

<div class="panel">
    <div class="panel-body">
        <div class="notice danger">
            <?= e($dbError) ?>
        </div>
        <div class="form-actions mt-4">
            <a href="/company/drivers" class="btn btn-ghost">← К списку</a>
        </div>
    </div>
</div>

<?php elseif ($driver === null): ?>

<div class="page-head">
    <div>
        <h1>Водитель не найден</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?></p>
    </div>
    <div class="page-head-actions">
        <a href="/company/drivers" class="btn btn-ghost">← К списку</a>
    </div>
</div>

<div class="panel">
    <div class="panel-body">
        <div class="notice warn">
            Водитель с указанным ID не найден.
        </div>
    </div>
</div>

<?php elseif (isset($accessDenied)): ?>

<div class="page-head">
    <div>
        <h1>Доступ запрещён</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?></p>
    </div>
    <div class="page-head-actions">
        <a href="/company/drivers" class="btn btn-ghost">← К списку</a>
    </div>
</div>

<div class="panel">
    <div class="panel-body">
        <div class="notice warn">
            <?= e($accessDenied) ?>
        </div>
    </div>
</div>

<?php else: ?>

<div class="page-head">
    <div>
        <div class="page-eyebrow">ВОДИТЕЛИ / <?= e(mb_strtoupper($company['name'])) ?></div>
        <h1>Водитель: <?= e($driver['full_name']) ?></h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?></p>
    </div>
    <div class="page-head-actions">
        <a href="/company/drivers/<?= $driver['id'] ?>/edit" class="btn btn-primary">Редактировать</a>
        <a href="/company/drivers" class="btn btn-ghost">← К списку</a>
        <a href="/company/documents?entity_type=driver&entity_id=<?= $driver['id'] ?>" class="btn btn-ghost">Документы</a>
    </div>
</div>

<?php if (!empty($archiveError)): ?>
    <div class="notice warn"><?= e($archiveError) ?></div>
<?php endif; ?>

<div class="panel">
    <div class="panel-body">

        <div class="form-section">
            <h3 class="panel-head-title">Основные данные</h3>
            <dl class="kv">
                <dt>ФИО</dt>
                <dd><?= e($driver['full_name']) ?></dd>
                <dt>Статус</dt>
                <dd><?= renderStatusBadge($driver['status']) ?></dd>
                <dt>Комментарий</dt>
                <dd><?= e($driver['comments'] ?? '') ?: '—' ?></dd>
            </dl>
        </div>

        <div class="form-section">
            <h3 class="panel-head-title">Паспорт</h3>
            <dl class="kv">
                <dt>Серия и номер</dt>
                <dd><?= e($driver['passport_number'] ?? '') ?: '—' ?></dd>
                <dt>Кем выдан</dt>
                <dd><?= e($driver['passport_issued_by'] ?? '') ?: '—' ?></dd>
                <dt>Код подразделения</dt>
                <dd><?= e($driver['passport_department_code'] ?? '') ?: '—' ?></dd>
                <dt>Дата выдачи</dt>
                <dd><?= e($driver['passport_issue_date'] ?? '') ?: '—' ?></dd>
            </dl>
        </div>

        <div class="form-section">
            <h3 class="panel-head-title">Водительское удостоверение</h3>
            <dl class="kv">
                <dt>Номер</dt>
                <dd><?= e($driver['license_number'] ?? '') ?: '—' ?></dd>
                <dt>Дата выдачи</dt>
                <dd><?= e($driver['license_issue_date'] ?? '') ?: '—' ?></dd>
            </dl>
        </div>

        <div class="form-section">
            <h3 class="panel-head-title">СНИЛС</h3>
            <dl class="kv">
                <dt>Номер</dt>
                <dd><?= e($driver['snils'] ?? '') ?: '—' ?></dd>
            </dl>
        </div>

        <div class="form-section">
            <h3 class="panel-head-title">Телефоны</h3>

            <?php if (empty($phones)): ?>
                <p class="text-muted">Телефоны не добавлены.</p>
            <?php else: ?>
            <div class="tbl-wrap">
                <table class="tbl">
                    <thead>
                        <tr>
                            <th>Телефон</th>
                            <th>Основной</th>
                            <th>Комментарий</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($phones as $ph): ?>
                    <tr>
                        <td class="col-mono"><?= e($ph['phone'] ?? '—') ?></td>
                        <td><?= $ph['is_main'] ? '✓' : '—' ?></td>
                        <td class="col-muted col-truncate"><?= e($ph['comment'] ?? '') ?></td>
                        <td class="col-actions">
                            <button type="button" class="btn btn-toolbar" onclick="editPhone(<?= $ph['id'] ?>)">Редактировать</button>
                            <form method="post" action="/company/drivers/<?= $driver['id'] ?>/phones/<?= $ph['id'] ?>/delete" class="inline-form" onsubmit="return confirm('Удалить телефон?')">
                                <button type="submit" class="btn btn-toolbar text-danger">Удалить</button>
                            </form>
                            <?php if (!$ph['is_main']): ?>
                            <form method="post" action="/company/drivers/<?= $driver['id'] ?>/phones/<?= $ph['id'] ?>/set-main" class="inline-form">
                                <button type="submit" class="btn btn-toolbar">Сделать основным</button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

            <!-- Inline phone edit form -->
            <div id="phone-edit-form" class="hidden-subform" style="display:none">
                <h4 class="section-title">Редактировать телефон</h4>
                <form method="post" id="phone-edit-frm">
                    <input type="hidden" name="phone_edit_id" id="phone-edit-id" value="">
                    <div class="frm-row">
                        <div class="field">
                            <label class="field-label">Телефон</label>
                            <input type="text" name="phone" class="field-input" id="phone-edit-value">
                        </div>
                        <div class="field">
                            <label class="field-label">Комментарий</label>
                            <input type="text" name="comment" class="field-input" id="phone-edit-comment">
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Сохранить</button>
                        <button type="button" class="btn btn-ghost" onclick="document.getElementById('phone-edit-form').style.display='none'">Отмена</button>
                    </div>
                </form>
            </div>

            <!-- Add phone form -->
            <button type="button" class="btn btn-ghost btn-sm mt-4" onclick="document.getElementById('phone-add-form').style.display='block'">Добавить телефон</button>
            <div id="phone-add-form" class="hidden-subform" style="display:none">
                <h4 class="section-title">Добавить телефон</h4>
                <form method="post" action="/company/drivers/<?= $driver['id'] ?>/phones/create">
                    <div class="frm-row">
                        <div class="field">
                            <label class="field-label">Телефон</label>
                            <input type="text" name="phone" class="field-input">
                        </div>
                        <div class="field">
                            <label class="field-label">Комментарий</label>
                            <input type="text" name="comment" class="field-input">
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Добавить телефон</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="form-section">
            <h3 class="panel-head-title">Связанные блоки "Водитель+ТС"</h3>
            <?php if (empty($driverBlocks)): ?>
                <p class="text-muted">Нет связанных блоков.</p>
            <?php else: ?>
            <div class="tbl-wrap">
                <table class="tbl">
                    <thead>
                        <tr>
                            <th>ID блока</th>
                            <th>Тип комплекта</th>
                            <th>Основная ед.</th>
                            <th>Доп. ед.</th>
                            <th>Статус</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($driverBlocks as $db): ?>
                    <tr>
                        <td class="col-mono"><?= $db['id'] ?></td>
                        <td><?= e($db['set_type'] ?? '—') ?></td>
                        <td class="col-mono"><?= e($db['primary_plate'] ?? '—') ?></td>
                        <td class="col-mono"><?= e($db['secondary_plate'] ?? '—') ?></td>
                        <td><?= renderStatusBadge($db['status']) ?></td>
                        <td class="col-actions">
                            <a href="/company/driver-vehicle-blocks/<?= $db['id'] ?>" class="btn btn-toolbar">Просмотр</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

        <?php if (($_SESSION['role_code'] ?? '') === 'company_owner'): ?>
        <div class="form-section">
            <h3 class="panel-head-title">Доступ логистов</h3>
            <?php if (!empty($grants)): ?>
            <div class="tbl-wrap">
                <table class="tbl">
                    <thead><tr><th>Логист</th><th>Доступ</th><th>Выдан</th></tr></thead>
                    <tbody>
                    <?php foreach($grants as $g): ?>
                    <tr>
                        <td><?= e($g['logist_name']) ?></td>
                        <td><?= e(ui_access_level($g['access_level'] ?? null)) ?></td>
                        <td class="col-muted"><?= e(ui_date($g['created_at'] ?? null)) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <p class="text-muted">Доступ логистам не выдан.</p>
            <?php endif; ?>

            <form method="post" action="/company/access-grants/grant" class="grant-form">
                <input type="hidden" name="entity_type" value="driver">
                <input type="hidden" name="entity_id" value="<?= $driver['id'] ?>">
                <input type="hidden" name="redirect" value="/company/drivers/<?= $driver['id'] ?>">
                <div class="field inline-field">
                    <select name="granted_to_user_id" class="field-select">
                        <option value="">— Выберите логиста —</option>
                        <?php foreach($logists as $l): ?>
                        <option value="<?= $l['id'] ?>"><?= e($l['full_name']) ?> (<?= e($l['login']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-ghost">Дать доступ</button>
            </form>
        </div>
        <?php endif; ?>

        <div class="form-section">
            <h3 class="panel-head-title">Служебные данные</h3>
            <dl class="kv">
                <dt>Создал</dt>
                <dd><?= e(ui_actor($driver['created_by_role'] ?? null, $driver['created_by_user_id'] ?? null, $createdByUser ?? null)) ?></dd>
                <dt>Создан</dt>
                <dd><?= e(ui_date($driver['created_at'] ?? null)) ?></dd>
                <dt>Обновил</dt>
                <dd><?= !empty($driver['updated_by_user_id']) ? e(ui_actor($driver['updated_by_role'] ?? null, $driver['updated_by_user_id'] ?? null, $updatedByUser ?? null)) : '—' ?></dd>
                <dt>Обновлён</dt>
                <dd><?= e(ui_date($driver['updated_at'] ?? null)) ?></dd>
            </dl>
        </div>

        <div class="form-actions">
            <a href="/company/documents?entity_type=driver&entity_id=<?= $driver['id'] ?>" class="btn btn-ghost">Документы</a>
            <form method="post" action="/company/drivers/<?= $driver['id'] ?>/archive" class="inline-form" onsubmit="return confirm('Архивировать водителя?')">
                <button type="submit" class="btn btn-secondary">Архивировать</button>
            </form>
        </div>

    </div>
</div>

<script>
function editPhone(phoneId) {
    var form = document.getElementById('phone-edit-form');
    var frm = document.getElementById('phone-edit-frm');
    document.getElementById('phone-edit-id').value = phoneId;
    frm.action = '/company/drivers/<?= $driver['id'] ?>/phones/' + phoneId + '/edit';
    document.getElementById('phone-edit-value').value = '';
    document.getElementById('phone-edit-comment').value = '';
    form.style.display = 'block';
    form.scrollIntoView({behavior: 'smooth'});
}
</script>

<?php endif; ?>
