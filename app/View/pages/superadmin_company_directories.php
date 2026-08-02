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
        <div class="form-actions mt-4">
            <a href="<?= app_url('/superadmin/companies') ?>" class="btn btn-ghost">← К реестру</a>
        </div>
    </div>
</div>

<?php else: ?>

<div class="page-head">
    <div class="page-head-left">
        <h1 class="page-title">Справочники компании</h1>
        <div class="page-summary"><span>SUPERADMIN / <?= e($company['name']) ?></span></div>
    </div>
    <div class="page-head-actions">
        <a href="/superadmin/companies/<?= $id ?>" class="btn btn-ghost">← К карточке</a>
    </div>
</div>

<div class="page-content">

<?php if (!empty($dbError)): ?>
    <div class="notice warn">
        Локальная БД компании недоступна. Данные справочников не могут быть загружены.
    </div>
<?php else: ?>
    <div class="panel">
        <div class="panel-head">
            <h3 class="panel-head-title">Справочники</h3>
        </div>
        <div class="panel-body">
            <div class="notice info">
                Это аудит наполненности компании. Нулевые значения допустимы для новой компании, но для рабочей компании они означают, что владелец ещё не заполнил операционные данные.
            </div>
        </div>
        <div class="tbl-wrap">
            <table class="tbl">
                <thead>
                    <tr>
                        <th>Справочник</th>
                        <th class="col-num">Всего</th>
                        <th class="col-num">Активных</th>
                        <th class="col-num">Архивированных</th>
                        <th class="col-tight">Состояние</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Клиенты</td>
                        <td class="col-num"><?= (int)($dirs['clients']['total'] ?? 0) ?></td>
                        <td class="col-num"><?= (int)($dirs['clients']['active'] ?? 0) ?></td>
                        <td class="col-num"><?= (int)($dirs['clients']['archived'] ?? 0) ?></td>
                        <td class="col-tight"><span class="badge <?= (int)($dirs['clients']['active'] ?? 0) > 0 ? 'badge-ok' : 'badge-warning' ?>"><?= (int)($dirs['clients']['active'] ?? 0) > 0 ? 'Заполнен' : 'Пусто' ?></span></td>
                        <td class="col-actions"><a href="/superadmin/companies/<?= $id ?>/clients" class="btn btn-ghost btn-sm">Открыть</a></td>
                    </tr>
                    <tr>
                        <td>Подрядчики</td>
                        <td class="col-num"><?= (int)($dirs['contractors']['total'] ?? 0) ?></td>
                        <td class="col-num"><?= (int)($dirs['contractors']['active'] ?? 0) ?></td>
                        <td class="col-num"><?= (int)($dirs['contractors']['archived'] ?? 0) ?></td>
                        <td class="col-tight"><span class="badge <?= (int)($dirs['contractors']['active'] ?? 0) > 0 ? 'badge-ok' : 'badge-warning' ?>"><?= (int)($dirs['contractors']['active'] ?? 0) > 0 ? 'Заполнен' : 'Пусто' ?></span></td>
                        <td class="col-actions"><a href="/superadmin/companies/<?= $id ?>/contractors" class="btn btn-ghost btn-sm">Открыть</a></td>
                    </tr>
                    <tr>
                        <td>Водители</td>
                        <td class="col-num"><?= (int)($dirs['drivers']['total'] ?? 0) ?></td>
                        <td class="col-num"><?= (int)($dirs['drivers']['active'] ?? 0) ?></td>
                        <td class="col-num"><?= (int)($dirs['drivers']['archived'] ?? 0) ?></td>
                        <td class="col-tight"><span class="badge <?= (int)($dirs['drivers']['active'] ?? 0) > 0 ? 'badge-ok' : 'badge-warning' ?>"><?= (int)($dirs['drivers']['active'] ?? 0) > 0 ? 'Заполнен' : 'Пусто' ?></span></td>
                        <td class="col-actions"><a href="/superadmin/companies/<?= $id ?>/drivers" class="btn btn-ghost btn-sm">Открыть</a></td>
                    </tr>
                    <tr>
                        <td>Транспортные единицы</td>
                        <td class="col-num"><?= (int)($dirs['vehicle_units']['total'] ?? 0) ?></td>
                        <td class="col-num"><?= (int)($dirs['vehicle_units']['active'] ?? 0) ?></td>
                        <td class="col-num"><?= (int)($dirs['vehicle_units']['archived'] ?? 0) ?></td>
                        <td class="col-tight"><span class="badge <?= (int)($dirs['vehicle_units']['active'] ?? 0) > 0 ? 'badge-ok' : 'badge-warning' ?>"><?= (int)($dirs['vehicle_units']['active'] ?? 0) > 0 ? 'Заполнен' : 'Пусто' ?></span></td>
                        <td class="col-actions"><a href="/superadmin/companies/<?= $id ?>/vehicles" class="btn btn-ghost btn-sm">Открыть</a></td>
                    </tr>
                    <tr>
                        <td>Транспортные комплекты</td>
                        <td class="col-num"><?= (int)($dirs['vehicle_sets']['total'] ?? 0) ?></td>
                        <td class="col-num"><?= (int)($dirs['vehicle_sets']['active'] ?? 0) ?></td>
                        <td class="col-num"><?= (int)($dirs['vehicle_sets']['archived'] ?? 0) ?></td>
                        <td class="col-tight"><span class="badge <?= (int)($dirs['vehicle_sets']['active'] ?? 0) > 0 ? 'badge-ok' : 'badge-warning' ?>"><?= (int)($dirs['vehicle_sets']['active'] ?? 0) > 0 ? 'Заполнен' : 'Пусто' ?></span></td>
                        <td class="col-actions"><span class="text-muted">—</span></td>
                    </tr>
                    <tr>
                        <td>Блоки водитель+ТС</td>
                        <td class="col-num"><?= (int)($dirs['driver_vehicle_blocks']['total'] ?? 0) ?></td>
                        <td class="col-num"><?= (int)($dirs['driver_vehicle_blocks']['active'] ?? 0) ?></td>
                        <td class="col-num"><?= (int)($dirs['driver_vehicle_blocks']['archived'] ?? 0) ?></td>
                        <td class="col-tight"><span class="badge <?= (int)($dirs['driver_vehicle_blocks']['active'] ?? 0) > 0 ? 'badge-ok' : 'badge-warning' ?>"><?= (int)($dirs['driver_vehicle_blocks']['active'] ?? 0) > 0 ? 'Заполнен' : 'Пусто' ?></span></td>
                        <td class="col-actions"><span class="text-muted">—</span></td>
                    </tr>
                    <tr>
                        <td>Экипажи</td>
                        <td class="col-num"><?= (int)($dirs['crews']['total'] ?? 0) ?></td>
                        <td class="col-num"><?= (int)($dirs['crews']['active'] ?? 0) ?></td>
                        <td class="col-num"><?= (int)($dirs['crews']['archived'] ?? 0) ?></td>
                        <td class="col-tight"><span class="badge <?= (int)($dirs['crews']['active'] ?? 0) > 0 ? 'badge-ok' : 'badge-warning' ?>"><?= (int)($dirs['crews']['active'] ?? 0) > 0 ? 'Заполнен' : 'Пусто' ?></span></td>
                        <td class="col-actions"><a href="/superadmin/companies/<?= $id ?>/crews" class="btn btn-ghost btn-sm">Открыть</a></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

</div><!-- /.page-content -->

<?php endif; ?>
<?php endif; ?>
