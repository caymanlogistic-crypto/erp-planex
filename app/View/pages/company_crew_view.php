<?php if ($company === null): ?>

<div class="notice warn">
    Компания не найдена. Укажите корректный company_id.
</div>

<?php elseif ($company['status'] !== 'active'): ?>

<div class="page-head">
    <div>
        <h1>Экипаж</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?></p>
    </div>
</div>

<div class="notice warn">
    Компания находится в статусе «<?= e($company['status']) ?>». Просмотр экипажа недоступен.
</div>

<?php elseif (isset($dbError)): ?>

<div class="page-head">
    <div>
        <h1>Экипаж</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?></p>
    </div>
</div>

<div class="notice warn">
    <?= e($dbError) ?>
</div>

<?php elseif ($entityNotFound): ?>

<div class="page-head">
    <div>
        <h1>Экипаж не найден</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?></p>
    </div>
    <div class="page-head-actions">
        <a href="/company/crews" class="btn btn-ghost">← К списку</a>
    </div>
</div>

<div class="notice warn">
    Экипаж с ID <?= e((string)$crewId) ?> не найден в этой компании.
</div>

<?php elseif (isset($accessDenied)): ?>

<div class="page-head">
    <div>
        <h1>Доступ запрещён</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?></p>
    </div>
    <div class="page-head-actions">
        <a href="/company/crews" class="btn btn-ghost">← К списку</a>
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
        <div class="page-eyebrow">ЭКИПАЖИ / <?= e(mb_strtoupper($company['name'])) ?></div>
        <h1>Экипаж #<?= $crew['id'] ?></h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?></p>
    </div>
    <div class="page-head-actions">
        <a href="/company/crews/<?= $crew['id'] ?>/edit" class="btn btn-primary">Редактировать</a>
        <a href="/company/crews" class="btn btn-ghost">← К списку</a>
        <a href="/company/documents?entity_type=crew&entity_id=<?= $crew['id'] ?>" class="btn btn-ghost">Документы</a>
    </div>
</div>

<div class="panel">
    <div class="panel-body">

        <div class="form-section">
            <h3 class="panel-head-title">Состав экипажа</h3>
            <dl class="kv">
                <dt>Подрядчик</dt>
                <dd>
                    <?php if (!empty($crew['contractor_id'])): ?>
                        <a href="/company/contractors/<?= $crew['contractor_id'] ?>"><?= e($crew['contractor_name'] ?? '') ?: '—' ?></a>
                        <?php if (!empty($crew['contractor_inn'])): ?><br><small class="text-muted">ИНН: <?= e($crew['contractor_inn']) ?></small><?php endif; ?>
                    <?php else: ?>
                        —
                    <?php endif; ?>
                </dd>
                <dt>Блок "Водитель + ТС"</dt>
                <dd>
                    <?php if (!empty($crew['driver_vehicle_block_id'])): ?>
                        <a href="/company/driver-vehicle-blocks/<?= $crew['driver_vehicle_block_id'] ?>">Блок #<?= $crew['driver_vehicle_block_id'] ?></a>
                    <?php else: ?>
                        —
                    <?php endif; ?>
                </dd>
                <dt>Водитель</dt>
                <dd>
                    <?= e($crew['driver_name'] ?? '') ?: '—' ?>
                    <?php if (!empty($crew['driver_phone'])): ?><br><small class="text-muted">Тел: <?= e($crew['driver_phone']) ?></small><?php endif; ?>
                </dd>
                <dt>Транспортный комплект</dt>
                <dd>
                    <?= e($crew['set_type'] ?? '—') ?>
                </dd>
                <dt>Транспортные единицы</dt>
                <dd>
                    <code><?= e($crew['primary_plate'] ?? '—') ?></code>
                    <?php if (!empty($crew['secondary_plate'])): ?>
                        + <code><?= e($crew['secondary_plate']) ?></code>
                    <?php endif; ?>
                </dd>
                <dt>Статус</dt>
                <dd>
                    <?php if ($crew['status'] === 'active'): ?>
                        <span class="badge badge-ok"><span class="dot"></span>Активен</span>
                    <?php elseif ($crew['status'] === 'archived'): ?>
                        <span class="badge badge-warn"><span class="dot"></span>Архив</span>
                    <?php else: ?>
                        <span class="badge"><span class="dot"></span>Неактивен</span>
                    <?php endif; ?>
                </dd>
                <dt>Комментарий</dt>
                <dd><?= e($crew['comments'] ?? '') ?: '—' ?></dd>
            </dl>
        </div>

        <div class="form-section">
            <h3 class="panel-head-title">Техническая информация</h3>
            <dl class="kv">
                <dt>Создан</dt>
                <dd><?= e(ui_date($crew['created_at'] ?? null)) ?></dd>
                <dt>Обновлён</dt>
                <dd><?= e(ui_date($crew['updated_at'] ?? null)) ?></dd>
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
                <input type="hidden" name="entity_type" value="crew">
                <input type="hidden" name="entity_id" value="<?= $crew['id'] ?>">
                <input type="hidden" name="redirect" value="/company/crews/<?= $crew['id'] ?>">
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

        <div class="form-actions">
            <a href="/company/documents?entity_type=crew&entity_id=<?= $crew['id'] ?>" class="btn btn-ghost">Документы</a>
            <?php if (($crew['status'] ?? '') === 'archived'): ?>
                <span class="badge badge-warn"><span class="dot"></span>В архиве</span>
            <?php else: ?>
            <form method="post" action="/company/crews/<?= $crew['id'] ?>/archive" class="inline-form" onsubmit="return confirm('Архивировать экипаж?')">
                <button type="submit" class="btn btn-secondary">Архивировать</button>
            </form>
            <?php endif; ?>
        </div>

    </div>
</div>

<?php endif; ?>
