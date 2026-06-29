<?php

require_once __DIR__ . '/../components/status_badge.php';

?>

<?php if ($company === null): ?>

<div class="notice warn">
    Компания не найдена. Укажите корректный company_id.
</div>

<?php elseif ($company['status'] !== 'active'): ?>

<div class="page-head">
    <div>
        <h1>Клиенты</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?></p>
    </div>
    <div class="page-head-actions">
        <a href="/company/clients" class="btn btn-ghost">← К списку</a>
    </div>
</div>

<div class="notice warn">
    Компания находится в статусе «<?= e($company['status']) ?>».
</div>

<?php elseif ($client === null): ?>

<div class="page-head">
    <div>
        <h1>Клиент</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?></p>
    </div>
    <div class="page-head-actions">
        <a href="/company/clients" class="btn btn-ghost">← К списку</a>
    </div>
</div>

<div class="notice warn">
    Клиент не найден.
</div>

<?php elseif (isset($dbError)): ?>

<div class="page-head">
    <div>
        <h1>Клиент</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?></p>
    </div>
    <div class="page-head-actions">
        <a href="/company/clients" class="btn btn-ghost">← К списку</a>
    </div>
</div>

<div class="notice danger">
    <?= e($dbError) ?>
</div>

<?php else: ?>

<div class="page-head">
    <div>
        <h1>Клиент: <?= e($client['name']) ?></h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?></p>
    </div>
    <div class="page-head-actions">
        <a href="/company/clients/<?= $client['id'] ?>/edit" class="btn btn-primary">Редактировать</a>
        <a href="/company/documents?entity_type=client&entity_id=<?= $client['id'] ?>" class="btn btn-ghost">Документы</a>
        <a href="/company/clients" class="btn btn-ghost">← К списку</a>
    </div>
</div>

<div class="panel">
    <div class="panel-body">

        <div class="form-section">
            <h3 class="panel-head-title">Основные данные</h3>
            <dl class="kv">
                <dt>Наименование</dt>
                <dd><?= e($client['name']) ?></dd>
                <dt>ИНН</dt>
                <dd><code><?= e($client['inn']) ?></code></dd>
                <dt>Тип</dt>
                <dd><?= e(ui_contractor_type($client['entity_type'] ?? null)) ?></dd>
                <dt>КПП</dt>
                <dd><?= e($client['kpp'] ?? '') ?: '—' ?></dd>
                <dt>ОГРН</dt>
                <dd><?= e($client['ogrn'] ?? '') ?: '—' ?></dd>
                <dt>Руководитель</dt>
                <dd><?= e($client['director_full_name'] ?? '') ?: '—' ?></dd>
                <dt>Должность руководителя</dt>
                <dd><?= e($client['director_position'] ?? '') ?: '—' ?></dd>
                <dt>Статус</dt>
                <dd><?= renderStatusBadge($client['status']) ?></dd>
                <dt>Комментарий</dt>
                <dd><?= e($client['comments'] ?? '') ?: '—' ?></dd>
            </dl>
        </div>

        <div class="form-section">
            <h3 class="panel-head-title">Адреса</h3>
            <dl class="kv">
                <dt>Юридический адрес</dt>
                <dd><?= e($client['legal_address'] ?? '') ?: '—' ?></dd>
                <dt>Фактический адрес</dt>
                <dd><?= e($client['physical_address'] ?? '') ?: '—' ?></dd>
            </dl>
        </div>

        <?php if (!empty($client['bank_account']) || !empty($client['bank_name']) || !empty($client['bank_bik']) || !empty($client['bank_corr_account'])): ?>
        <div class="form-section">
            <h3 class="panel-head-title">Банковские реквизиты</h3>
            <dl class="kv">
                <?php if (!empty($client['bank_account'])): ?>
                <dt>Расчётный счёт</dt>
                <dd class="mono"><?= e($client['bank_account']) ?></dd>
                <?php endif; ?>
                <?php if (!empty($client['bank_name'])): ?>
                <dt>Банк</dt>
                <dd><?= e($client['bank_name']) ?></dd>
                <?php endif; ?>
                <?php if (!empty($client['bank_bik'])): ?>
                <dt>БИК</dt>
                <dd class="mono"><?= e($client['bank_bik']) ?></dd>
                <?php endif; ?>
                <?php if (!empty($client['bank_corr_account'])): ?>
                <dt>Корр. счёт</dt>
                <dd class="mono"><?= e($client['bank_corr_account']) ?></dd>
                <?php endif; ?>
            </dl>
        </div>
        <?php endif; ?>

        <div class="form-section">
            <h3 class="panel-head-title">Контакты</h3>
            <?php if (!empty($contacts)): ?>
            <div class="tbl-wrap">
                <table class="tbl">
                    <thead>
                        <tr>
                            <th>Контактное лицо</th>
                            <th>Телефон</th>
                            <th>Email</th>
                            <th>Комментарий</th>
                            <th>Осн.</th>
                            <th>Док.</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($contacts as $ct): ?>
                        <tr>
                            <td><?= e($ct['contact_person'] ?? '—') ?></td>
                            <td><?= e($ct['phone'] ?? '—') ?></td>
                            <td><?= e($ct['email'] ?? '—') ?></td>
                            <td class="col-muted"><?= e($ct['comment'] ?? '') ?: '—' ?></td>
                            <td><?= !empty($ct['is_primary']) ? '✓' : '' ?></td>
                            <td><?= !empty($ct['is_document_email']) ? '✓' : '' ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <p class="text-muted">Контакты не указаны.</p>
            <?php endif; ?>
        </div>

        <?php if (($_SESSION['role_code'] ?? '') !== 'logist'): ?>
        <div class="form-section">
            <h3 class="panel-head-title">Техническая информация</h3>
            <dl class="kv">
                <dt>Дата создания</dt>
                <dd><?= e($client['created_at'] ?? '') ?></dd>
                <dt>Дата обновления</dt>
                <dd><?= e($client['updated_at'] ?? '') ?></dd>
            </dl>
        </div>
        <?php endif; ?>

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
                <input type="hidden" name="entity_type" value="client">
                <input type="hidden" name="entity_id" value="<?= $client['id'] ?>">
                <input type="hidden" name="redirect" value="/company/clients/<?= $client['id'] ?>">
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
            <h3 class="panel-head-title">Опасная зона</h3>
            <?php if ($client['status'] !== 'archived'): ?>
                <p class="text-muted hint-before-action">Запись будет удалена из списка.</p>
                <form method="post" action="/company/clients/<?= $client['id'] ?>/archive">
                    <button type="button" class="btn btn-danger" onclick="window.confirmDeleteForm(this)">Удалить</button>
                </form>
            <?php else: ?>
                <p class="text-muted">Клиент уже удалён.</p>
            <?php endif; ?>
        </div>

    </div>
</div>

<?php endif; ?>
<?php require base_path('app/View/partials/delete_confirm_modal.php'); ?>
