<?php

require_once __DIR__ . '/../components/status_badge.php';

?>

<?php if ($company === null): ?>

<div class="panel">
    <div class="panel-body">
        <div class="notice warn">
            Компания не найдена. <a href="/company/contractors">← К списку</a>
        </div>
    </div>
</div>

<?php elseif ($company['status'] !== 'active'): ?>

<div class="page-head">
    <div>
        <h1>Подрядчик</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?> (ID: <?= $company['id'] ?>)</p>
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
        <div class="form-actions" style="margin-top:16px">
            <a href="/company/contractors" class="btn btn-ghost">← К списку</a>
        </div>
    </div>
</div>

<?php elseif ($contractor === null): ?>

<div class="page-head">
    <div>
        <h1>Подрядчик не найден</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?> (ID: <?= $company['id'] ?>)</p>
    </div>
    <div class="page-head-actions">
        <a href="/company/contractors" class="btn btn-ghost">← К списку</a>
    </div>
</div>

<div class="panel">
    <div class="panel-body">
        <div class="notice warn">
            Подрядчик с указанным ID не найден.
        </div>
    </div>
</div>

<?php elseif (isset($accessDenied)): ?>

<div class="page-head">
    <div>
        <h1>Доступ запрещён</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?> (ID: <?= $company['id'] ?>)</p>
    </div>
    <div class="page-head-actions">
        <a href="/company/contractors" class="btn btn-ghost">← К списку</a>
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
        <h1>Подрядчик: <?= e($contractor['name']) ?></h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?></p>
    </div>
    <div class="page-head-actions">
        <a href="/company/contractors/<?= $contractor['id'] ?>/edit" class="btn btn-primary">Редактировать</a>
        <a href="/company/contractors" class="btn btn-ghost">← К списку</a>
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
                <dd><?= !empty($contractor['contractor_type']) ? e($contractor['contractor_type']) : '—' ?></dd>
                <dt>Статус</dt>
                <dd><?= renderStatusBadge($contractor['status']) ?></dd>
                <dt>Комментарий</dt>
                <dd><?= e($contractor['comments'] ?? '') ?: '—' ?></dd>
            </dl>
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

        <div class="form-section">
            <h3 class="panel-head-title">Банковские реквизиты</h3>
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
                            <th>Телефон</th>
                            <th>Email</th>
                            <th>Главный</th>
                            <th>Email для док.</th>
                            <th>Комментарий</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($contacts as $ct): ?>
                    <tr>
                        <td><?= e($ct['contact_person'] ?? '—') ?></td>
                        <td class="col-mono"><?= e($ct['phone'] ?? '—') ?></td>
                        <td><?= e($ct['email'] ?? '—') ?></td>
                        <td><?= $ct['is_primary'] ? '✓' : '—' ?></td>
                        <td><?= $ct['is_document_email'] ? '✓' : '—' ?></td>
                        <td class="col-muted" style="max-width:200px;overflow:hidden;text-overflow:ellipsis"><?= e($ct['comment'] ?? '') ?></td>
                        <td class="col-actions">
                            <button type="button" class="btn btn-toolbar" onclick="editContact(<?= $ct['id'] ?>)">Редактировать</button>
                            <form method="post" action="/company/contractors/<?= $contractor['id'] ?>/contacts/<?= $ct['id'] ?>/delete" style="display:inline" onsubmit="return confirm('Удалить контакт?')">
                                <button type="submit" class="btn btn-toolbar" style="color:var(--danger)">Удалить</button>
                            </form>
                            <?php if (!$ct['is_primary']): ?>
                            <form method="post" action="/company/contractors/<?= $contractor['id'] ?>/contacts/<?= $ct['id'] ?>/set-primary" style="display:inline">
                                <button type="submit" class="btn btn-toolbar">Сделать главным</button>
                            </form>
                            <?php endif; ?>
                            <?php if (!$ct['is_document_email'] && !empty($ct['email'])): ?>
                            <form method="post" action="/company/contractors/<?= $contractor['id'] ?>/contacts/<?= $ct['id'] ?>/set-document-email" style="display:inline">
                                <button type="submit" class="btn btn-toolbar">Email для док.</button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

            <!-- Inline contact edit form (hidden by default, shown via JS) -->
            <div id="contact-edit-form" style="display:none;margin-top:12px">
                <h4 class="panel-head-title" style="font-size:1rem">Редактировать контакт</h4>
                <form method="post" id="contact-edit-frm">
                    <input type="hidden" name="contact_edit_id" id="contact-edit-id" value="">
                    <div class="frm-row">
                        <div class="field">
                            <label class="field-label">Контактное лицо <span class="text-muted">(рекомендуемое)</span></label>
                            <input type="text" name="contact_person" class="field-input" id="contact-edit-person">
                        </div>
                        <div class="field">
                            <label class="field-label">Телефон <span class="text-muted">(рекомендуемое)</span></label>
                            <input type="text" name="phone" class="field-input" id="contact-edit-phone">
                        </div>
                    </div>
                    <div class="frm-row">
                        <div class="field">
                            <label class="field-label">Email <span class="text-muted">(рекомендуемое)</span></label>
                            <input type="email" name="email" class="field-input" id="contact-edit-email">
                        </div>
                        <div class="field">
                            <label class="field-label">Комментарий <span class="text-muted">(рекомендуемое)</span></label>
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
            <div style="margin-top:16px">
                <h4 class="panel-head-title" style="font-size:1rem">Добавить контакт</h4>
                <form method="post" action="/company/contractors/<?= $contractor['id'] ?>/contacts/create">
                    <div class="frm-row">
                        <div class="field">
                            <label class="field-label">Контактное лицо <span class="text-muted">(рекомендуемое)</span></label>
                            <input type="text" name="contact_person" class="field-input">
                        </div>
                        <div class="field">
                            <label class="field-label">Телефон <span class="text-muted">(рекомендуемое)</span></label>
                            <input type="text" name="phone" class="field-input">
                        </div>
                    </div>
                    <div class="frm-row">
                        <div class="field">
                            <label class="field-label">Email <span class="text-muted">(рекомендуемое)</span></label>
                            <input type="email" name="email" class="field-input">
                        </div>
                        <div class="field">
                            <label class="field-label">Комментарий <span class="text-muted">(рекомендуемое)</span></label>
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
                <p style="margin-bottom:8px">
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
            <div style="margin-top:16px">
                <h4 class="panel-head-title" style="font-size:1rem">Добавить запись</h4>
                <form method="post" action="/company/contractors/<?= $contractor['id'] ?>/tax-history/create">
                    <div class="frm-row">
                        <div class="field">
                            <label class="field-label">Система налогообложения <span class="text-muted">(рекомендуемое)</span></label>
                            <input type="text" name="tax_system" class="field-input" placeholder="Например: ОСНО, УСН 6%, УСН 15%">
                        </div>
                        <div class="field">
                            <label class="field-label">НДС <span class="text-muted">(рекомендуемое)</span></label>
                            <input type="text" name="vat_mode" class="field-input" placeholder="Например: 20%, Без НДС">
                        </div>
                    </div>
                    <div class="frm-row">
                        <div class="field">
                            <label class="field-label">Дата начала действия <span class="text-muted">(рекомендуемое)</span></label>
                            <input type="date" name="effective_from" class="field-input">
                        </div>
                        <div class="field">
                            <label class="field-label">Комментарий <span class="text-muted">(рекомендуемое)</span></label>
                            <input type="text" name="comment" class="field-input">
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Добавить запись</button>
                    </div>
                </form>
            </div>
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
                        <td><?= e($g['access_level']) ?></td>
                        <td class="col-muted"><?= e($g['created_at']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <p class="text-muted">Доступ логистам не выдан.</p>
            <?php endif; ?>

            <form method="post" action="/company/access-grants/grant" style="margin-top:12px">
                <input type="hidden" name="entity_type" value="contractor">
                <input type="hidden" name="entity_id" value="<?= $contractor['id'] ?>">
                <input type="hidden" name="redirect" value="/company/contractors/<?= $contractor['id'] ?>">
                <div class="field" style="display:inline-block;margin-right:8px">
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
                <dd><?= e($contractor['created_by_role'] ?? '—') ?> (ID: <?= e($contractor['created_by_user_id'] ?? '—') ?>)<?php if ($createdByUser): ?> — <?= e($createdByUser) ?><?php endif; ?></dd>
                <dt>Создан</dt>
                <dd><?= e($contractor['created_at'] ?? '—') ?></dd>
                <dt>Обновил</dt>
                <dd><?= !empty($contractor['updated_by_user_id']) ? (e($contractor['updated_by_role'] ?? '—') . ' (ID: ' . e($contractor['updated_by_user_id']) . ')' . ($updatedByUser ? ' — ' . e($updatedByUser) : '')) : '—' ?></dd>
                <dt>Обновлён</dt>
                <dd><?= e($contractor['updated_at'] ?? '—') ?></dd>
            </dl>
        </div>

        <div class="form-actions">
            <a href="/company/contractors/<?= $contractor['id'] ?>/edit" class="btn btn-primary">Редактировать</a>
            <a href="/company/documents?entity_type=contractor&entity_id=<?= $contractor['id'] ?>" class="btn btn-ghost">Документы</a>
            <form method="post" action="/company/contractors/<?= $contractor['id'] ?>/archive" style="display:inline" onsubmit="return confirm('Архивировать подрядчика?')">
                <button type="submit" class="btn btn-warn">Архивировать</button>
            </form>
        </div>

    </div>
</div>

<script>
// Inline contact edit toggle
function editContact(contactId) {
    // Find the contact row data (stored in data attributes or via AJAX)
    // For simplicity, we show a form that submits to the edit route
    var form = document.getElementById('contact-edit-form');
    var frm = document.getElementById('contact-edit-frm');
    document.getElementById('contact-edit-id').value = contactId;
    frm.action = '/company/contractors/<?= $contractor['id'] ?>/contacts/' + contactId + '/edit';
    // Clear fields - user fills in values
    document.getElementById('contact-edit-person').value = '';
    document.getElementById('contact-edit-phone').value = '';
    document.getElementById('contact-edit-email').value = '';
    document.getElementById('contact-edit-comment').value = '';
    form.style.display = 'block';
    form.scrollIntoView({behavior: 'smooth'});
}
</script>

<?php endif; ?>
