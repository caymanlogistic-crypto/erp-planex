<?php if ($company === null): ?>

<div class="panel">
    <div class="panel-body">
        <div class="notice warn">
            Компания не найдена. <a href="/superadmin/companies">← К реестру</a>
        </div>
    </div>
</div>

<?php elseif (isset($dbError)): ?>

<div class="panel">
    <div class="panel-body">
        <div class="notice danger">
            <?= e($dbError) ?>
        </div>
        <div class="form-actions" style="margin-top:16px">
            <a href="/superadmin/companies" class="btn btn-ghost">← К реестру</a>
        </div>
    </div>
</div>

<?php else: ?>

<div class="page-head">
    <div>
        <h1>Справочники компании: <?= e($company['name']) ?></h1>
        <p class="text-muted">ID: <?= $id ?> · Режим SUPERADMIN: просмотр</p>
    </div>
    <div class="page-head-actions">
        <a href="/superadmin/companies/<?= $id ?>" class="btn btn-ghost">← К карточке</a>
    </div>
</div>

<?php if (!empty($dbError)): ?>
    <div class="notice warn">
        Локальная БД компании недоступна. Данные справочников не могут быть загружены.
    </div>
<?php else: ?>
    <div class="panel">
        <div class="panel-head">
            <h3 class="panel-head-title">Справочники</h3>
        </div>
        <div class="tbl-wrap">
            <table class="tbl">
                <thead>
                    <tr>
                        <th>Справочник</th>
                        <th class="col-num">Всего</th>
                        <th class="col-num">Активных</th>
                        <th class="col-num">Архивированных</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Клиенты</td>
                        <td class="col-num"><?= (int)($dirs['clients']['total'] ?? 0) ?></td>
                        <td class="col-num"><?= (int)($dirs['clients']['active'] ?? 0) ?></td>
                        <td class="col-num"><?= (int)($dirs['clients']['archived'] ?? 0) ?></td>
                        <td class="col-actions"><a href="/superadmin/companies/<?= $id ?>/clients" class="btn btn-ghost" style="font-size:11px;padding:2px 8px">Открыть</a></td>
                    </tr>
                    <tr>
                        <td>Подрядчики</td>
                        <td class="col-num"><?= (int)($dirs['contractors']['total'] ?? 0) ?></td>
                        <td class="col-num"><?= (int)($dirs['contractors']['active'] ?? 0) ?></td>
                        <td class="col-num"><?= (int)($dirs['contractors']['archived'] ?? 0) ?></td>
                        <td class="col-actions"><a href="/superadmin/companies/<?= $id ?>/contractors" class="btn btn-ghost" style="font-size:11px;padding:2px 8px">Открыть</a></td>
                    </tr>
                    <tr>
                        <td>Водители</td>
                        <td class="col-num"><?= (int)($dirs['drivers']['total'] ?? 0) ?></td>
                        <td class="col-num"><?= (int)($dirs['drivers']['active'] ?? 0) ?></td>
                        <td class="col-num"><?= (int)($dirs['drivers']['archived'] ?? 0) ?></td>
                        <td class="col-actions"><a href="/superadmin/companies/<?= $id ?>/drivers" class="btn btn-ghost" style="font-size:11px;padding:2px 8px">Открыть</a></td>
                    </tr>
                    <tr>
                        <td>Транспорт</td>
                        <td class="col-num"><?= (int)($dirs['vehicles']['total'] ?? 0) ?></td>
                        <td class="col-num"><?= (int)($dirs['vehicles']['active'] ?? 0) ?></td>
                        <td class="col-num"><?= (int)($dirs['vehicles']['archived'] ?? 0) ?></td>
                        <td class="col-actions"><a href="/superadmin/companies/<?= $id ?>/vehicles" class="btn btn-ghost" style="font-size:11px;padding:2px 8px">Открыть</a></td>
                    </tr>
                    <tr>
                        <td>Экипажи</td>
                        <td class="col-num"><?= (int)($dirs['crews']['total'] ?? 0) ?></td>
                        <td class="col-num"><?= (int)($dirs['crews']['active'] ?? 0) ?></td>
                        <td class="col-num"><?= (int)($dirs['crews']['archived'] ?? 0) ?></td>
                        <td class="col-actions"><a href="/superadmin/companies/<?= $id ?>/crews" class="btn btn-ghost" style="font-size:11px;padding:2px 8px">Открыть</a></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php endif; ?>
