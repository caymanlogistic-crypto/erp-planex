<?php if ($company === null): ?>

<div class="notice warn">
    Компания не найдена. Укажите корректный company_id.
</div>

<?php elseif ($company['status'] !== 'active'): ?>

<div class="page-head">
    <div>
        <h1>Транспорт</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?> (ID: <?= $company['id'] ?>)</p>
    </div>
</div>

<div class="notice warn">
    Компания находится в статусе «<?= e($company['status']) ?>». Просмотр транспорта недоступен.
</div>

<?php elseif (isset($dbError)): ?>

<div class="page-head">
    <div>
        <h1>Транспорт</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?> (ID: <?= $company['id'] ?>)</p>
    </div>
</div>

<div class="notice warn">
    <?= e($dbError) ?>
</div>

<?php elseif ($entityNotFound): ?>

<div class="page-head">
    <div>
        <h1>Транспорт не найден</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?> (ID: <?= $company['id'] ?>)</p>
    </div>
    <div class="page-head-actions">
        <a href="/company/vehicles" class="btn btn-ghost">← К списку</a>
    </div>
</div>

<div class="notice warn">
    Транспорт с ID <?= e((string)$vehicleId) ?> не найден в этой компании.
</div>

<?php else: ?>

<div class="page-head">
    <div>
        <h1>Транспорт: <?= e($vehicle['plate_number']) ?></h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?> (ID: <?= $company['id'] ?>)</p>
    </div>
    <div class="page-head-actions">
        <a href="/company/vehicles/<?= $vehicle['id'] ?>/edit" class="btn btn-primary">Редактировать</a>
        <a href="/company/vehicles" class="btn btn-ghost">← К списку</a>
        <a href="/company/documents?entity_type=vehicle_unit&entity_id=<?= $vehicle['id'] ?>" class="btn btn-toolbar">Документы</a>
    </div>
</div>

<div class="panel">
    <div class="panel-body">

        <div class="form-section">
            <h3 class="panel-head-title">Основные данные</h3>
            <dl class="kv">
                <dt>Госномер</dt>
                <dd><code><?= e($vehicle['plate_number']) ?></code></dd>
                <dt>Марка</dt>
                <dd><?= e($vehicle['brand'] ?? '') ?: '—' ?></dd>
                <dt>Модель</dt>
                <dd><?= e($vehicle['model'] ?? '') ?: '—' ?></dd>
                <dt>Тип единицы</dt>
                <dd><?= e($vehicle['unit_type'] ?? '') ?: '—' ?></dd>
                <dt>Статус</dt>
                <dd>
                    <?php if ($vehicle['status'] === 'active'): ?>
                        <span class="badge badge-ok"><span class="dot"></span>Активен</span>
                    <?php elseif ($vehicle['status'] === 'archived'): ?>
                        <span class="badge badge-warn"><span class="dot"></span>Архив</span>
                    <?php else: ?>
                        <span class="badge"><span class="dot"></span>Неактивен</span>
                    <?php endif; ?>
                </dd>
                <dt>Комментарий</dt>
                <dd><?= e($vehicle['comments'] ?? '') ?: '—' ?></dd>
            </dl>
        </div>

        <div class="form-section">
            <h3 class="panel-head-title">Технические данные</h3>
            <dl class="kv">
                <dt>VIN</dt>
                <dd><code><?= e($vehicle['vin'] ?? '') ?: '—' ?></code></dd>
                <dt>СТС</dt>
                <dd><?= e($vehicle['sts_number'] ?? '') ?: '—' ?></dd>
                <dt>Грузоподъёмность (т)</dt>
                <dd><?= $vehicle['capacity_tons'] !== null ? e((string)$vehicle['capacity_tons']) : '—' ?></dd>
                <dt>Объём кузова (м³)</dt>
                <dd><?= $vehicle['volume_m3'] !== null ? e((string)$vehicle['volume_m3']) : '—' ?></dd>
            </dl>
        </div>

        <div class="form-section">
            <h3 class="panel-head-title">Техническая информация</h3>
            <dl class="kv">
                <dt>Создан</dt>
                <dd><?= e($vehicle['created_at'] ?? '') ?></dd>
                <dt>Обновлён</dt>
                <dd><?= e($vehicle['updated_at'] ?? '') ?></dd>
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
                <input type="hidden" name="entity_type" value="vehicle_unit">
                <input type="hidden" name="entity_id" value="<?= $vehicle['id'] ?>">
                <input type="hidden" name="redirect" value="/company/vehicles/<?= $vehicle['id'] ?>">
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
            <a href="/company/vehicles/<?= $vehicle['id'] ?>/edit" class="btn btn-primary">Редактировать</a>
            <a href="/company/documents?entity_type=vehicle_unit&entity_id=<?= $vehicle['id'] ?>" class="btn btn-toolbar">Документы</a>
            <form method="post" action="/company/vehicles/<?= $vehicle['id'] ?>/archive" style="display:inline" onsubmit="return confirm('Архивировать транспорт?')">
                <button type="submit" class="btn btn-warn">Архивировать</button>
            </form>
        </div>

    </div>
</div>

<?php endif; ?>
