<?php
require_once __DIR__ . '/../components/status_badge.php';
?>

<?php if ($company === null): ?>

<div class="panel">
    <div class="panel-body">
        <div class="notice warn">
            Компания не найдена. <a href="/company/vehicle-sets">← К списку</a>
        </div>
    </div>
</div>

<?php elseif ($company['status'] !== 'active'): ?>

<div class="page-head">
    <div>
        <h1>Транспортный комплект</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?></p>
    </div>
</div>
<div class="notice warn">
    Компания находится в статусе «<?= e($company['status']) ?>».
</div>

<?php elseif (isset($dbError)): ?>

<div class="panel">
    <div class="panel-body">
        <div class="notice danger"><?= e($dbError) ?></div>
        <div class="form-actions mt-4">
            <a href="/company/vehicle-sets" class="btn btn-ghost">← К списку</a>
        </div>
    </div>
</div>

<?php elseif ($vehicleSet === null): ?>

<div class="page-head">
    <div>
        <h1>Комплект не найден</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?></p>
    </div>
    <div class="page-head-actions">
        <a href="/company/vehicle-sets" class="btn btn-ghost">← К списку</a>
    </div>
</div>
<div class="panel">
    <div class="panel-body">
        <div class="notice warn">
            Транспортный комплект с указанным ID не найден.
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
        <a href="/company/vehicle-sets" class="btn btn-ghost">← К списку</a>
    </div>
</div>
<div class="panel">
    <div class="panel-body">
        <div class="empty-state">
            <div class="empty-icon">🔒</div>
            <p class="empty-title">Доступ запрещён</p>
            <p class="empty-desc"><?= e($accessDenied) ?> Для получения доступа обратитесь к руководителю компании.</p>
            <div class="form-actions">
                <a href="/company/vehicle-sets" class="btn btn-ghost">← К списку</a>
                <a href="/company/dashboard" class="btn btn-primary">На главную</a>
            </div>
        </div>
    </div>
</div>

<?php else: ?>

<div class="page-head">
    <div>
        <div class="page-eyebrow">ТРАНСПОРТНЫЕ КОМПЛЕКТЫ / <?= e(mb_strtoupper($company['name'])) ?></div>
        <h1>Комплект #<?= $vehicleSet['id'] ?></h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?></p>
    </div>
    <div class="page-head-actions">
        <a href="/company/vehicle-sets/<?= $vehicleSet['id'] ?>/edit" class="btn btn-primary">Редактировать</a>
        <a href="/company/vehicle-sets" class="btn btn-ghost">← К списку</a>
        <a href="/company/documents?entity_type=vehicle_set&entity_id=<?= $vehicleSet['id'] ?>" class="btn btn-ghost">Документы</a>
    </div>
</div>

<div class="panel">
    <div class="panel-body">

        <div class="form-section">
            <h3 class="panel-head-title">Основные данные</h3>
            <dl class="kv">
                <dt>Тип комплекта</dt>
                <dd><?= e(ui_set_type($vehicleSet['set_type'] ?? null)) ?></dd>
                <dt>Основная единица</dt>
                <dd>
                    <?= e($vehicleSet['primary_plate'] ?? '—') ?>
                    <?php if (!empty($vehicleSet['primary_brand'])): ?>(<?= e($vehicleSet['primary_brand']) ?> <?= e($vehicleSet['primary_model'] ?? '') ?>)<?php endif; ?>
                    <?php if (!empty($vehicleSet['primary_vin'])): ?><br><small class="text-muted">VIN: <?= e($vehicleSet['primary_vin']) ?></small><?php endif; ?>
                </dd>
                <?php if (!empty($vehicleSet['secondary_plate'])): ?>
                <dt>Доп. единица</dt>
                <dd>
                    <?= e($vehicleSet['secondary_plate']) ?>
                    <?php if (!empty($vehicleSet['secondary_brand'])): ?>(<?= e($vehicleSet['secondary_brand']) ?> <?= e($vehicleSet['secondary_model'] ?? '') ?>)<?php endif; ?>
                    <?php if (!empty($vehicleSet['secondary_vin'])): ?><br><small class="text-muted">VIN: <?= e($vehicleSet['secondary_vin']) ?></small><?php endif; ?>
                </dd>
                <?php endif; ?>
                <dt>Статус</dt>
                <dd><?= renderStatusBadge($vehicleSet['status']) ?></dd>
                <dt>Комментарий</dt>
                <dd><?= e($vehicleSet['comments'] ?? '') ?: '—' ?></dd>
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
                <input type="hidden" name="entity_type" value="vehicle_set">
                <input type="hidden" name="entity_id" value="<?= $vehicleSet['id'] ?>">
                <input type="hidden" name="redirect" value="/company/vehicle-sets/<?= $vehicleSet['id'] ?>">
                <div class="field inline-field">
                    <select name="granted_to_user_id" class="field-select">
                        <option value="">— Выберите логиста —</option>
                        <?php foreach($logists as $l): ?>
                        <option value="<?= $l['id'] ?>"><?= e($l['full_name']) ?> (<?= e($l['login']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field inline-field">
                    <select name="access_level" class="field-select">
                        <option value="view">Просмотр</option>
                        <option value="edit">Редактирование</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-ghost">Дать доступ</button>
            </form>
        </div>
        <?php endif; ?>

        <div class="form-section">
            <h3 class="panel-head-title">Опасная зона</h3>
            <p class="text-muted" style="margin-bottom:8px;">Архивирование скроет запись из основных списков.</p>
            <form method="post" action="/company/vehicle-sets/<?= $vehicleSet['id'] ?>/archive" onsubmit="return confirm('Вы уверены? Запись будет перемещена в архив.')">
                <button type="submit" class="btn btn-danger">Архивировать</button>
            </form>
        </div>

    </div>
</div>

<?php endif; ?>
