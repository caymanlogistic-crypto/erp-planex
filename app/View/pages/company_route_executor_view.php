<?php if ($company === null): ?>

<div class="notice warn">
    Компания не найдена. Укажите корректный company_id.
</div>

<?php elseif ($company['status'] !== 'active'): ?>

<div class="page-head">
    <div>
        <h1>Исполнитель рейса</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?></p>
    </div>
</div>

<div class="notice warn">
    Компания находится в статусе «<?= e($company['status']) ?>». Просмотр исполнителя рейса недоступен.
</div>

<?php elseif (isset($dbError)): ?>

<div class="page-head">
    <div>
        <h1>Исполнитель рейса</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?></p>
    </div>
</div>

<div class="notice warn">
    <?= e($dbError) ?>
</div>

<?php elseif ($entityNotFound): ?>

<div class="page-head">
    <div>
        <h1>Исполнитель рейса не найден</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?></p>
    </div>
    <div class="page-head-actions">
        <a href="/company/route-executors" class="btn btn-ghost">← К списку</a>
    </div>
</div>

<div class="notice warn">
    Исполнитель рейса с ID <?= e((string)$crewId) ?> не найден в этой компании.
</div>

<?php elseif (isset($accessDenied)): ?>

<div class="page-head">
    <div>
        <h1>Доступ запрещён</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?></p>
    </div>
    <div class="page-head-actions">
        <a href="/company/route-executors" class="btn btn-ghost">← К списку</a>
    </div>
</div>

<div class="panel">
    <div class="panel-body">
        <div class="empty-state">
            <p class="empty-title">Доступ запрещён</p>
            <p class="empty-desc"><?= e($accessDenied) ?> Для получения доступа обратитесь к руководителю компании.</p>
            <div class="form-actions">
                <a href="/company/route-executors" class="btn btn-ghost">← К списку исполнителей рейса</a>
                <a href="/company/dashboard" class="btn btn-primary">На главную</a>
            </div>
        </div>
    </div>
</div>

<?php else: ?>

<div class="page-head">
    <div>
        <div class="page-eyebrow">ИСПОЛНИТЕЛИ РЕЙСА / <?= e(mb_strtoupper($company['name'])) ?></div>
        <h1>Исполнитель рейса #<?= $crew['id'] ?></h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?></p>
    </div>
    <div class="page-head-actions">
        <a href="/company/route-executors/<?= $crew['id'] ?>/edit" class="btn btn-primary">Редактировать</a>
        <a href="/company/route-executors" class="btn btn-ghost">← К списку</a>
    </div>
</div>

<div class="panel">
    <div class="panel-body">

        <div class="form-section">
            <h3 class="panel-head-title">Подрядчик</h3>
            <dl class="kv">
                <dt>Название</dt>
                <dd>
                    <?php if (!empty($crew['contractor_id'])): ?>
                        <a href="/company/contractors/<?= $crew['contractor_id'] ?>"><?= e($crew['contractor_name'] ?? '') ?: '—' ?></a>
                    <?php else: ?>
                        —
                    <?php endif; ?>
                </dd>
                <dt>ИНН</dt>
                <dd><?= e($crew['contractor_inn'] ?? '') ?: '—' ?></dd>
            </dl>
        </div>

        <div class="form-section">
            <h3 class="panel-head-title">Водитель</h3>
            <dl class="kv">
                <dt>ФИО</dt>
                <dd>
                    <?php if (!empty($crew['driver_id'])): ?>
                        <a href="/company/drivers/<?= $crew['driver_id'] ?>"><?= e($crew['driver_name'] ?? '') ?: '—' ?></a>
                    <?php else: ?>
                        —
                    <?php endif; ?>
                </dd>
                <dt>Телефон</dt>
                <dd><?= e($crew['driver_phone'] ?? '') ?: '—' ?></dd>
            </dl>
        </div>

        <div class="form-section">
            <h3 class="panel-head-title">Транспорт (ТС)</h3>
            <dl class="kv">
                <dt>Тип комплекта</dt>
                <dd><?= e(ui_set_type($crew['set_type'] ?? null)) ?: '—' ?></dd>
                <dt>Госномер</dt>
                <dd>
                    <?php if (!empty($crew['vehicle_set_id'])): ?>
                        <a href="/company/vehicle-sets/<?= $crew['vehicle_set_id'] ?>">
                            <code><?= e($crew['primary_plate'] ?? '—') ?></code>
                            <?php if (!empty($crew['secondary_plate'])): ?>
                                + <code><?= e($crew['secondary_plate']) ?></code>
                            <?php endif; ?>
                        </a>
                    <?php else: ?>
                        —
                    <?php endif; ?>
                </dd>
            </dl>
        </div>

        <div class="form-section">
            <h3 class="panel-head-title">Статус и ответственный</h3>
            <dl class="kv">
                <dt>Ответственный логист</dt>
                <dd><?= e($crew['created_by_name'] ?? '') ?: '—' ?></dd>
                <dt>Статус</dt>
                <dd>
                    <?php if ($crew['status'] === 'active'): ?>
                        <span class="badge badge-ok"><span class="dot"></span>Активен</span>
                    <?php elseif ($crew['status'] === 'archived'): ?>

                    <?php else: ?>
                        <span class="badge"><span class="dot"></span>Неактивен</span>
                    <?php endif; ?>
                </dd>
                <dt>Комментарий</dt>
                <dd><?= e($crew['comments'] ?? '') ?: '—' ?></dd>
            </dl>
        </div>

        <?php if (($_SESSION['role_code'] ?? '') !== 'logist'): ?>
        <div class="form-section">
            <h3 class="panel-head-title">Техническая информация</h3>
            <dl class="kv">
                <dt>Создан</dt>
                <dd><?= e(ui_date($crew['created_at'] ?? null)) ?></dd>
                <dt>Обновлён</dt>
                <dd><?= e(ui_date($crew['updated_at'] ?? null)) ?></dd>
            </dl>
        </div>
        <?php endif; ?>

        <div class="form-section">
            <h3 class="panel-head-title">Действия</h3>
            <div class="form-actions">
                <a href="/company/route-executors/<?= $crew['id'] ?>/edit" class="btn btn-primary">Редактировать</a>
                <?php if (($crew['status'] ?? '') !== 'archived'): ?>
                <form method="post" action="/company/route-executors/<?= $crew['id'] ?>/archive" style="display:inline;" onsubmit="return confirm('Удалить запись? Запись будет удалена из списка.')">
                    <button type="submit" class="btn btn-danger">Удалить</button>
                </form>
                <?php else: ?>
                    <p class="text-muted">Исполнитель рейса уже удалён.</p>
                <?php endif; ?>
                <a href="/company/route-executors" class="btn btn-ghost">← К списку</a>
            </div>
        </div>

    </div>
</div>

<?php endif; ?>
