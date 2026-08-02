<?php

use App\Service\LinearRouteService;

$requestedRouteType = trim((string) ($_GET['route_type'] ?? ''));
if (!in_array($requestedRouteType, [LinearRouteService::ROUTE_TYPE_LINEAR, LinearRouteService::ROUTE_TYPE_AGENCY], true)) {
    $requestedRouteType = '';
}
$selectedRouteType = (string) ($old['route_type'] ?? $requestedRouteType);
$showCreateModal = (($_GET['show_create'] ?? '') === '1')
    || $selectedRouteType !== ''
    || !empty($validationErrors)
    || !empty($formError);
?>
<?php if ($company === null): ?>

<div class="notice warn">
    Компания не найдена. Укажите корректный `company_id`.
</div>

<?php elseif (($company['status'] ?? '') !== 'active'): ?>

<div class="page-head">
    <div class="page-head-left">
        <h1 class="page-title">Линейные рейсы</h1>
        <div class="page-summary"><span>Компания находится в неактивном статусе. Работа с рейсами временно недоступна.</span></div>
    </div>
</div>

<?php elseif ($dbError !== null): ?>

<div class="page-head">
    <div class="page-head-left">
        <h1 class="page-title">Линейные рейсы</h1>
        <div class="page-summary"><span>Новый модуль рейсов компании.</span></div>
    </div>
</div>

<div class="notice warn"><?= e($dbError) ?></div>

<?php else: ?>

<div class="page-head">
    <div class="page-head-left">
        <h1 class="page-title">Линейные рейсы</h1>
        <div class="page-summary"><span>Реестр линейных перевозок и агентских договоров по компании.</span></div>
    </div>
    <div class="page-head-actions">
        <a href="<?= app_url('/company/trips/linear?show_create=1') ?>" class="btn btn-primary" onclick="openModal('linear-trip-create-modal'); return false;">Создать рейс</a>
    </div>
</div>

<?php if ($formSuccess): ?><div class="notice success"><?= e($formSuccess) ?></div><?php endif; ?>
<?php if ($formError): ?><div class="notice warn"><?= e($formError) ?></div><?php endif; ?>

<?php if (empty($routes)): ?>
    <div class="panel">
        <div class="panel-body">
            <div class="empty-state">
                <p class="empty-title">Линейные рейсы ещё не созданы.</p>
                <p class="empty-desc">Создайте первый рейс через модальное окно: выберите тип, участников, исполнителя, груз, даты и финансовые условия.</p>
            </div>
        </div>
    </div>
<?php else: ?>
    <div class="table-card table-card--standard" data-erp-grid>
        <div class="table-toolbar">
            <div class="found-label">Найдено: <b><?= count($routes) ?></b> рейсов</div>
            <div class="toolbar-right">
                <div class="toolbar-sort">
                    <span class="toolbar-sort-label">Сортировка по:</span>
                    <select class="toolbar-select" data-erp-grid-sort>
                        <option value="date" selected>По дате добавления</option>
                        <option value="alpha">По алфавиту</option>
                    </select>
                </div>
                <input type="text" class="toolbar-search" placeholder="Поиск по таблице">
            </div>
        </div>
        <div class="table-scroll">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Тип</th>
                        <th>Заказчик</th>
                        <th>Перевозчик</th>
                        <th>Принципалы</th>
                        <th>Исполнитель рейса</th>
                        <th>Груз</th>
                        <th>Даты</th>
                        <th>Оплаты</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($routes as $route): ?>
                        <?php
                        $principalLabel = !empty($route['principal_summary']) ? implode(', ', $route['principal_summary']) : '—';
                        $executorLabel = trim((string) (($route['executor_contractor_name'] ?? '') . ' · ' . ($route['executor_driver_name'] ?? '')));
                        $executorPlates = trim((string) ($route['executor_primary_plate'] ?? ''));
                        if (!empty($route['executor_secondary_plate'])) {
                            $executorPlates .= ' + ' . $route['executor_secondary_plate'];
                        }

                        $moneyParts = [];
                        if ($isFinanceRealm) {
                            foreach (($route['payments']['customer'] ?? []) as $payment) {
                                $moneyParts[] = 'Заказчик: ' . LinearRouteService::formatAmount($payment['amount'] ?? null);
                            }
                            foreach (($route['payments']['carrier'] ?? []) as $payment) {
                                $moneyParts[] = 'Перевозчик: ' . LinearRouteService::formatAmount($payment['amount'] ?? null);
                            }
                            foreach (($route['principal_items'] ?? []) as $principal) {
                                $principalPayments = $route['payments']['principals'][(int) ($principal['id'] ?? 0)] ?? [];
                                foreach ($principalPayments as $payment) {
                                    $moneyParts[] = 'Принципал: ' . LinearRouteService::formatAmount($payment['amount'] ?? null);
                                }
                            }
                        }
                        if ($moneyParts === []) {
                            $moneyParts[] = '—';
                        }
                        ?>
                        <tr data-erp-sort-date="<?= (int) $route['id'] ?>" data-linear-route-id="<?= (int) $route['id'] ?>">
                            <td class="col-mono">#<?= (int) $route['id'] ?></td>
                            <td><?= e(LinearRouteService::routeTypeLabel((string) ($route['route_type'] ?? ''))) ?></td>
                            <td><?= e($route['client_name'] ?? '—') ?></td>
                            <td><?= e($route['carrier_name'] ?? '—') ?></td>
                            <td><?= e($principalLabel) ?></td>
                            <td class="cell-double">
                                <span class="cell-main"><?= e($executorLabel !== '' ? $executorLabel : '—') ?></span>
                                <span class="cell-sub"><?= e($executorPlates !== '' ? $executorPlates : '—') ?></span>
                            </td>
                            <td><?= e($route['cargo_type_name'] ?? '—') ?></td>
                            <td class="cell-double">
                                <span class="cell-main"><?= e(ui_date($route['planned_loading_date'] ?? null)) ?></span>
                                <?php if (LinearRouteService::shouldShowPlannedUnloading($route['planned_loading_date'] ?? null, $route['planned_unloading_date'] ?? null)): ?>
                                <span class="cell-sub"><?= e(ui_date($route['planned_unloading_date'] ?? null)) ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="cell-double">
                                <?php foreach ($moneyParts as $index => $moneyPart): ?>
                                    <?php if ($index === 0): ?>
                                        <span class="cell-main"><?= e($moneyPart) ?></span>
                                    <?php else: ?>
                                        <span class="cell-sub"><?= e($moneyPart) ?></span>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                                <?php if ($isFinanceRealm): ?>
                                <?php
                                $invCounts = $route['invoice_counts'] ?? [];
                                $invParts = [];
                                if (!empty($invCounts['customer'])) $invParts[] = 'Сч. заказчика: ' . (int) $invCounts['customer'];
                                if (!empty($invCounts['carrier'])) $invParts[] = 'Сч. перевозчика: ' . (int) $invCounts['carrier'];
                                if (!empty($invCounts['principal'])) $invParts[] = 'Сч. принципала: ' . (int) $invCounts['principal'];
                                if (!empty($invParts)): ?>
                                    <span class="cell-sub invoice-hint"><?= e(implode(' · ', $invParts)) ?></span>
                                <?php endif; ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="table-footer">
            <span class="footer-label">Показано <b class="footer-range">1–<?= count($routes) ?></b> из <b class="footer-total"><?= count($routes) ?></b></span>
        </div>
    </div>
<?php endif; ?>

<div class="modal-overlay<?= $showCreateModal ? ' is-open' : '' ?>" id="linear-trip-create-modal" data-close-on-overlay="0" data-close-on-escape="0" data-reset-on-close="0">
    <div class="modal modal-lg driver-create-modal">
        <div class="modal-head">
            <span class="modal-title">Создать рейс</span>
            <button type="button" class="modal-close" onclick="closeModal('linear-trip-create-modal')">×</button>
        </div>
        <div class="modal-body">
            <?php require base_path('app/View/partials/company_linear_trip_create_form.php'); ?>
        </div>
        <div class="modal-foot is-spaced">
            <div class="modal-required-note"><span class="req">*</span> — обязательные поля</div>
            <div class="modal-foot-actions">
                <button type="button" class="btn btn-ghost" onclick="closeModal('linear-trip-create-modal')">Отмена</button>
                <button type="submit" form="linear-trip-create-form" class="btn btn-primary">Создать рейс</button>
            </div>
        </div>
    </div>
</div>

<?php endif; ?>
