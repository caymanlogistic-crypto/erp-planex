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
            <h3 class="panel-head-title">Контакты</h3>
            <dl class="kv">
                <dt>Контактное лицо</dt>
                <dd><?= e($contractor['contact_person'] ?? '') ?: '—' ?></dd>
                <dt>Телефон</dt>
                <dd><?= e($contractor['contact_phone'] ?? '') ?: '—' ?></dd>
                <dt>Email</dt>
                <dd><?= e($contractor['contact_email'] ?? '') ?: '—' ?></dd>
            </dl>
        </div>

        <div class="form-section">
            <h3 class="panel-head-title">Техническая информация</h3>
            <dl class="kv">
                <dt>Создан</dt>
                <dd><?= e($contractor['created_at'] ?? '') ?></dd>
                <dt>Обновлён</dt>
                <dd><?= e($contractor['updated_at'] ?? '') ?></dd>
            </dl>
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

        <div class="form-actions">
            <a href="/company/contractors/<?= $contractor['id'] ?>/edit" class="btn btn-primary">Редактировать</a>
            <a href="/company/documents?entity_type=contractor&entity_id=<?= $contractor['id'] ?>" class="btn btn-ghost">Документы</a>
            <form method="post" action="/company/contractors/<?= $contractor['id'] ?>/archive" style="display:inline">
                <button type="submit" class="btn btn-warn">Архивировать</button>
            </form>
        </div>

    </div>
</div>

<?php endif; ?>
