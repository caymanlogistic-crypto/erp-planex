<?php

require_once __DIR__ . '/../components/status_badge.php';

?>

<?php if ($company === null): ?>

<div class="panel">
    <div class="panel-body">
        <div class="notice warn">
            Компания не найдена. <a href="<?= app_url('/company/contractors') ?>">← К списку</a>
        </div>
    </div>
</div>

<?php elseif ($company['status'] !== 'active'): ?>

<div class="page-head">
    <div class="page-head-left">
        <h1 class="page-title">Перевозчик</h1>
        <div class="page-summary"><span>Компания: <?= e($company['name']) ?></span></div>
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
            <a href="<?= app_url('/company/contractors') ?>" class="btn btn-ghost">← К списку</a>
        </div>
    </div>
</div>

<?php elseif ($contractor === null): ?>

<div class="page-head">
    <div class="page-head-left">
        <h1 class="page-title">Перевозчик не найден</h1>
        <div class="page-summary"><span>Компания: <?= e($company['name']) ?></span></div>
    </div>
    <div class="page-head-actions">
        <a href="<?= app_url('/company/contractors') ?>" class="btn btn-ghost">← К списку</a>
    </div>
</div>

<div class="panel">
    <div class="panel-body">
        <div class="notice warn">
            Перевозчик с указанным ID не найден.
        </div>
    </div>
</div>

<?php elseif (isset($accessDenied)): ?>

<div class="page-head">
    <div class="page-head-left">
        <h1 class="page-title">Доступ запрещён</h1>
        <div class="page-summary"><span>Компания: <?= e($company['name']) ?></span></div>
    </div>
    <div class="page-head-actions">
        <a href="<?= app_url('/company/contractors') ?>" class="btn btn-ghost">← К списку</a>
    </div>
</div>

<div class="panel">
    <div class="panel-body">
        <div class="empty-state">
            <div class="empty-icon">🔒</div>
            <p class="empty-title">Доступ запрещён</p>
            <p class="empty-desc"><?= e($accessDenied) ?> Для получения доступа обратитесь к руководителю компании.</p>
            <div class="form-actions">
                <a href="<?= app_url('/company/contractors') ?>" class="btn btn-ghost">← К списку перевозчиков</a>
                <a href="<?= app_url('/company/dashboard') ?>" class="btn btn-primary">На главную</a>
            </div>
        </div>
    </div>
</div>

<?php else: ?>

<div class="page-head">
    <div class="page-head-left">
        <h1 class="page-title">Перевозчик: <?= e($contractor['name']) ?></h1>
        <div class="page-summary"><span>Компания: <?= e($company['name']) ?></span></div>
    </div>
    <div class="page-head-actions">
        <a href="/company/contractors/<?= $contractor['id'] ?>/edit" class="btn btn-primary">Редактировать</a>
        <a href="<?= app_url('/company/contractors') ?>" class="btn btn-ghost">← К списку</a>
        <a href="/company/documents?entity_type=contractor&entity_id=<?= $contractor['id'] ?>" class="btn btn-ghost">Документы</a>
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
                <dt>Наименование</dt>
                <dd><?= e($contractor['name']) ?></dd>
                <dt>ИНН</dt>
                <dd><code><?= e($contractor['inn'] ?? '') ?></code></dd>
                <dt>КПП</dt>
                <dd><?= e($contractor['kpp'] ?? '') ?: '—' ?></dd>
                <dt>ОГРН</dt>
                <dd><?= e($contractor['ogrn'] ?? '') ?: '—' ?></dd>
                <dt>Тип</dt>
                <dd><?= e(ui_contractor_type($contractor['contractor_type'] ?? null)) ?></dd>
                <dt>Статус</dt>
                <dd><?= renderStatusBadge($contractor['status']) ?></dd>
                <dt>Комментарий</dt>
                <dd><?= e($contractor['comments'] ?? '') ?: '—' ?></dd>
            </dl>
        </div>

        <div class="form-section">
            <h3 class="panel-head-title">Контакты</h3>

            <?php if (empty($contacts)): ?>
                <p class="text-muted">Контакты не добавлены.</p>
            <?php else: ?>
            <div class="tbl-wrap">
                <table class="tbl">
                    <thead>
                        <tr>
                            <th>Контактное лицо</th>
                            <th>Связь</th>
                            <th>Назначение</th>
                            <th>Комментарий</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($contacts as $ct): ?>
                    <tr>
                        <td><?= e($ct['contact_person'] ?? '—') ?></td>
                        <td>
                            <div class="cell-main col-mono"><?= e($ct['phone'] ?? '—') ?></div>
                            <div class="cell-sub"><?= e($ct['email'] ?? '—') ?></div>
                        </td>
                        <td>
                            <div class="cell-main"><?= $ct['is_primary'] ? 'Главный контакт' : '—' ?></div>
                            <div class="cell-sub"><?= $ct['is_document_email'] ? 'Официальная рассылка' : '—' ?></div>
                        </td>
                        <td class="col-muted col-truncate"><?= e($ct['comment'] ?? '') ?></td>
                        <td class="col-actions">
                            <div class="row-actions">
                                <button type="button"
                                        class="btn btn-toolbar"
                                        onclick="editContact(<?= (int) $ct['id'] ?>, <?= json_encode($ct['contact_person'] ?? '', JSON_UNESCAPED_UNICODE) ?>, <?= json_encode($ct['phone'] ?? '', JSON_UNESCAPED_UNICODE) ?>, <?= json_encode($ct['email'] ?? '', JSON_UNESCAPED_UNICODE) ?>, <?= json_encode($ct['comment'] ?? '', JSON_UNESCAPED_UNICODE) ?>)">Редактировать</button>
                                <form method="post" action="/company/contractors/<?= $contractor['id'] ?>/contacts/<?= $ct['id'] ?>/delete" class="inline-form" onsubmit="return confirm('Контакт будет удалён. Подтвердить удаление?')">
                                    <button type="submit" class="btn btn-danger btn-sm">Удалить</button>
                                </form>
                            </div>
                            <?php if (!$ct['is_primary'] || (!$ct['is_document_email'] && !empty($ct['email']))): ?>
                            <div class="row-actions-sub">
                                <?php if (!$ct['is_primary']): ?>
                                <form method="post" action="/company/contractors/<?= $contractor['id'] ?>/contacts/<?= $ct['id'] ?>/set-primary">
                                    <button type="submit" class="btn btn-toolbar">Сделать главным</button>
                                </form>
                                <?php endif; ?>
                                <?php if (!$ct['is_document_email'] && !empty($ct['email'])): ?>
                                <form method="post" action="/company/contractors/<?= $contractor['id'] ?>/contacts/<?= $ct['id'] ?>/set-document-email">
                                    <button type="submit" class="btn btn-toolbar">Отметить для рассылки</button>
                                </form>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

            <!-- Inline contact edit form (hidden by default, shown via JS) -->
            <div id="contact-edit-form" class="hidden-subform" style="display:none">
                <h4 class="section-title">Редактировать контакт</h4>
                <form method="post" id="contact-edit-frm">
                    <input type="hidden" name="contact_edit_id" id="contact-edit-id" value="">
                    <div class="frm-row">
                        <div class="field">
                            <label class="field-label">Контактное лицо</label>
                            <input type="text" name="contact_person" class="field-input" id="contact-edit-person">
                        </div>
                        <div class="field">
                            <label class="field-label">Телефон</label>
                            <input type="text" name="phone" class="field-input" id="contact-edit-phone">
                        </div>
                    </div>
                    <div class="frm-row">
                        <div class="field">
                            <label class="field-label">Email</label>
                            <input type="email" name="email" class="field-input" id="contact-edit-email">
                        </div>
                        <div class="field">
                            <label class="field-label">Комментарий</label>
                            <input type="text" name="comment" class="field-input" id="contact-edit-comment">
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Сохранить</button>
                        <button type="button" class="btn btn-ghost" onclick="document.getElementById('contact-edit-form').style.display='none'">Отмена</button>
                    </div>
                </form>
            </div>

            <!-- Add contact form -->
            <button type="button" class="btn btn-ghost btn-sm mt-4" onclick="document.getElementById('contact-add-form').style.display='block'">Добавить контакт</button>
            <div id="contact-add-form" class="hidden-subform" style="display:none">
                <h4 class="section-title">Добавить контакт</h4>
                <form method="post" action="/company/contractors/<?= $contractor['id'] ?>/contacts/create">
                    <div class="frm-row">
                        <div class="field">
                            <label class="field-label">Контактное лицо</label>
                            <input type="text" name="contact_person" class="field-input">
                        </div>
                        <div class="field">
                            <label class="field-label">Телефон</label>
                            <input type="text" name="phone" class="field-input">
                        </div>
                    </div>
                    <div class="frm-row">
                        <div class="field">
                            <label class="field-label">Email</label>
                            <input type="email" name="email" class="field-input">
                        </div>
                        <div class="field">
                            <label class="field-label">Комментарий</label>
                            <input type="text" name="comment" class="field-input">
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Добавить контакт</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="form-section">
            <h3 class="panel-head-title">Адреса</h3>
            <dl class="kv">
                <dt>Юридический адрес</dt>
                <dd><?= e($contractor['legal_address'] ?? '') ?: '—' ?></dd>
                <dt>Фактический адрес</dt>
                <dd><?= e($contractor['physical_address'] ?? '') ?: '—' ?></dd>
            </dl>
        </div>

        <details class="panel-section mt-3">
            <summary class="section-title">Банковские реквизиты</summary>
            <dl class="kv">
                <dt>Расчётный счёт</dt>
                <dd><?= e($contractor['bank_account'] ?? '') ?: '—' ?></dd>
                <dt>Банк</dt>
                <dd><?= e($contractor['bank_name'] ?? '') ?: '—' ?></dd>
                <dt>БИК</dt>
                <dd><?= e($contractor['bank_bik'] ?? '') ?: '—' ?></dd>
                <dt>Корр. счёт</dt>
                <dd><?= e($contractor['bank_corr_account'] ?? '') ?: '—' ?></dd>
            </dl>
        </details>

        <div class="form-section">
            <h3 class="panel-head-title">История налогообложения</h3>

            <?php if (empty($taxHistory)): ?>
                <p class="text-muted">Данные о системе налогообложения отсутствуют.</p>
            <?php else: ?>
                <?php
                // Determine current tax system (last by effective_from)
                $activeTax = null;
                foreach ($taxHistory as $th) {
                    if (!empty($th['tax_system']) && $activeTax === null) {
                        $activeTax = $th['tax_system'];
                    }
                }
                ?>
                <p class="muted-copy">
                    <strong>Актуальная система:</strong>
                    <?= $activeTax ? e($activeTax) : '<span class="text-muted">Актуальная система не определена</span>' ?>
                </p>
                <div class="tbl-wrap">
                    <table class="tbl">
                        <thead>
                            <tr>
                                <th>Система налогообложения</th>
                                <th>НДС</th>
                                <th>Дата начала действия</th>
                                <th>Комментарий</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($taxHistory as $th): ?>
                        <tr>
                            <td><?= e($th['tax_system'] ?? '') ?: '<span class="text-muted">Система налогообложения не указана</span>' ?></td>
                            <td><?= e($th['vat_mode'] ?? '—') ?></td>
                            <td class="col-mono"><?= e($th['effective_from'] ?? '—') ?></td>
                            <td class="col-muted"><?= e($th['comment'] ?? '') ?></td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <!-- Add tax history form -->
            <div class="entity-subform">
                <h4 class="section-title">Добавить запись</h4>
                <form method="post" action="/company/contractors/<?= $contractor['id'] ?>/tax-history/create">
                    <div class="frm-row">
                        <div class="field">
                            <label class="field-label">Система налогообложения</label>
                            <input type="text" name="tax_system" class="field-input" placeholder="Например: ОСНО, УСН 6%, УСН 15%">
                        </div>
                        <div class="field">
                            <label class="field-label">НДС</label>
                            <input type="text" name="vat_mode" class="field-input" placeholder="Например: 20%, Без НДС">
                        </div>
                    </div>
                    <div class="frm-row">
                        <div class="field">
                            <label class="field-label">Дата начала действия</label>
                            <input type="date" name="effective_from" class="field-input">
                        </div>
                        <div class="field">
                            <label class="field-label">Комментарий</label>
                            <input type="text" name="comment" class="field-input">
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Добавить запись</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="form-section">
            <h3 class="panel-head-title">Водители+ТС</h3>

            <?php if (empty($crewBlocks)): ?>
                <p class="text-muted">Нет привязанных водителей и транспорта.</p>
            <?php else: ?>
            <div class="tbl-wrap">
                <table class="tbl">
                    <thead>
                        <tr>
                            <th>Водитель</th>
                            <th>Транспорт</th>
                            <th>Статус связки</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($crewBlocks as $cb): ?>
                    <tr>
                        <td class="cell-double">
                            <span class="cell-main">
                                <a href="/company/drivers/<?= $cb['driver_id'] ?>"><?= e($cb['driver_name']) ?></a>
                            </span>
                        </td>
                        <td class="cell-double">
                            <span class="cell-main">
                                <a href="/company/vehicle-sets/<?= $cb['vehicle_set_id'] ?>"><?= e($cb['vehicle_plate'] ?? '—') ?></a>
                            </span>
                            <?php if (!empty($cb['secondary_plate'])): ?>
                            <span class="cell-sub">+ <?= e($cb['secondary_plate']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge<?= $cb['block_status'] === 'active' ? ' badge-ok' : '' ?>">
                                <span class="dot"></span>
                                <?= $cb['block_status'] === 'active' ? 'Активен' : e(ucfirst($cb['block_status'] ?? '—')) ?>
                            </span>
                        </td>
                        <td class="col-actions">
                            <a href="/company/driver-vehicle-blocks/<?= $cb['block_id'] ?>" class="btn btn-toolbar">Просмотр</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

            <a href="/company/contractors/<?= $contractor['id'] ?>/add-crew" class="btn btn-primary mt-4">+ Водитель + Машина</a>
        </div>

        <?php if (($_SESSION['role_code'] ?? '') !== 'logist'): ?>
        <div class="form-section">
            <h3 class="panel-head-title">Служебные данные</h3>
            <dl class="kv">
                <dt>Создал</dt>
                <dd><?= e(ui_actor($contractor['created_by_role'] ?? null, $contractor['created_by_user_id'] ?? null, $createdByUser ?? null)) ?></dd>
                <dt>Создан</dt>
                <dd><?= e(ui_date($contractor['created_at'] ?? null)) ?></dd>
                <dt>Обновил</dt>
                <dd><?= !empty($contractor['updated_by_user_id']) ? e(ui_actor($contractor['updated_by_role'] ?? null, $contractor['updated_by_user_id'] ?? null, $updatedByUser ?? null)) : '—' ?></dd>
                <dt>Обновлён</dt>
                <dd><?= e(ui_date($contractor['updated_at'] ?? null)) ?></dd>
            </dl>
        </div>
        <?php endif; ?>

        <div class="form-section">
            <h3 class="panel-head-title">Удаление записи</h3>
            <p class="text-muted">Запись будет удалена из списка.</p>
            <form method="post" action="/company/contractors/<?= $contractor['id'] ?>/archive" onsubmit="return confirm('Удалить запись? Запись будет удалена из списка.')">
                <button type="submit" class="btn btn-danger">Удалить</button>
            </form>
        </div>

    </div>
</div>

<script>
// Inline contact edit toggle
function editContact(contactId, contactPerson, phone, email, comment) {
    var form = document.getElementById('contact-edit-form');
    var frm = document.getElementById('contact-edit-frm');
    document.getElementById('contact-edit-id').value = contactId;
    frm.action = '/company/contractors/<?= $contractor['id'] ?>/contacts/' + contactId + '/edit';
    document.getElementById('contact-edit-person').value = contactPerson || '';
    document.getElementById('contact-edit-phone').value = phone || '';
    document.getElementById('contact-edit-email').value = email || '';
    document.getElementById('contact-edit-comment').value = comment || '';
    form.style.display = 'block';
    form.scrollIntoView({behavior: 'smooth'});
}
</script>

<?php endif; ?>
