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

$paymentAmountCents = static function ($value): int {
    $clean = trim(str_replace(["\xc2\xa0", ' ', ','], ['', '', '.'], (string) $value));
    if (!preg_match('/^\d+(?:\.\d{1,2})?$/', $clean)) {
        return 0;
    }
    [$whole, $fraction] = array_pad(explode('.', $clean, 2), 2, '');
    $fraction = str_pad(substr($fraction, 0, 2), 2, '0');
    return ((int) $whole * 100) + (int) $fraction;
};

$formatCents = static function (int $cents): string {
    $rubles = intdiv(max(0, $cents), 100);
    return number_format($rubles, 0, ',', ' ') . ' ₽';
};

$paymentDetail = static function (array $payment) use ($paymentAmountCents, $formatCents): string {
    $parts = [
        $formatCents($paymentAmountCents($payment['amount'] ?? 0)),
        LinearRouteService::paymentMethodLabel($payment['payment_method'] ?? null),
        LinearRouteService::vatRateLabel($payment['vat_rate'] ?? null),
    ];

    $condition = LinearRouteService::conditionTypeLabel($payment['condition_type'] ?? null);
    if ($condition === '—') {
        $condition = trim((string) ($payment['payment_due_type'] ?? ''));
    }
    if ($condition !== '') {
        $parts[] = $condition;
    }

    $daysCount = isset($payment['days_count']) ? (int) $payment['days_count'] : (isset($payment['payment_due_days']) ? (int) $payment['payment_due_days'] : 0);
    if ($daysCount > 0) {
        $parts[] = $daysCount . ' ' . (($payment['days_kind'] ?? $payment['payment_due_days_kind'] ?? 'calendar') === 'working' ? 'РД' : 'БД');
    }

    return implode(' · ', array_values(array_filter($parts, static fn(string $part): bool => $part !== '' && $part !== '—')));
};
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
                <p class="empty-desc">Создайте первый рейс через модальное окно: выберите участников, исполнителя, маршрут и финансовые условия.</p>
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
            <table class="table linear-trip-registry">
                <thead>
                    <tr>
                        <th class="registry-id">ID</th>
                        <th class="registry-date">Дата загрузки</th>
                        <th class="registry-date">Дата выгрузки</th>
                        <th class="registry-client">Заказчик</th>
                        <th class="registry-executor">Исполнитель</th>
                        <th class="registry-route">Маршрут</th>
                        <th class="registry-cargo">Груз</th>
                        <th class="registry-rate">Ставка заказчика</th>
                        <th class="registry-rate">Ставка перевозчика</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($routes as $route): ?>
                        <?php
                        $actualLoading = trim((string) ($route['actual_loading_date'] ?? ''));
                        $loadingDate = $actualLoading !== '' ? $actualLoading : trim((string) ($route['planned_loading_date'] ?? ''));
                        $actualUnloading = trim((string) ($route['actual_unloading_date'] ?? ''));

                        $executorCarrier = trim((string) ($route['executor_contractor_name'] ?? ''));
                        if ($executorCarrier === '') {
                            $executorCarrier = trim((string) ($route['carrier_name'] ?? ''));
                        }
                        $executorDrivers = trim((string) ($route['registry_driver_names'] ?? $route['executor_driver_name'] ?? ''));
                        $executorVehicle = trim((string) ($route['registry_vehicle_label'] ?? ''));
                        if ($executorVehicle === '') {
                            $plateParts = array_values(array_filter([
                                trim((string) ($route['executor_primary_plate'] ?? '')),
                                trim((string) ($route['executor_secondary_plate'] ?? '')),
                            ], static fn(string $value): bool => $value !== ''));
                            $executorVehicle = implode(' + ', $plateParts);
                        }

                        $customerPayments = $isFinanceRealm ? ($route['payments']['customer'] ?? []) : [];
                        $carrierPayments = $isFinanceRealm ? ($route['payments']['carrier'] ?? []) : [];
                        $customerTotalCents = 0;
                        foreach ($customerPayments as $payment) {
                            $customerTotalCents += $paymentAmountCents($payment['amount'] ?? 0);
                        }
                        $carrierTotalCents = 0;
                        foreach ($carrierPayments as $payment) {
                            $carrierTotalCents += $paymentAmountCents($payment['amount'] ?? 0);
                        }
                        ?>
                        <tr data-erp-sort-date="<?= (int) $route['id'] ?>" data-linear-route-id="<?= (int) $route['id'] ?>">
                            <td class="col-mono registry-id">#<?= (int) $route['id'] ?></td>
                            <td class="registry-date<?= $actualLoading === '' ? ' registry-date-planned' : '' ?>">
                                <?= e($loadingDate !== '' ? ui_date($loadingDate) : '—') ?>
                            </td>
                            <td class="registry-date"><?= e($actualUnloading !== '' ? ui_date($actualUnloading) : '—') ?></td>
                            <td class="registry-client"><?= e($route['client_name'] ?? '—') ?></td>
                            <td class="registry-executor">
                                <span class="registry-executor-line"><?= e($executorCarrier !== '' ? $executorCarrier : '—') ?></span>
                                <?php if ($executorDrivers !== ''): ?>
                                    <span class="registry-executor-line registry-executor-secondary"><?= e($executorDrivers) ?></span>
                                <?php endif; ?>
                                <?php if ($executorVehicle !== ''): ?>
                                    <span class="registry-executor-line registry-executor-secondary"><?= e($executorVehicle) ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="registry-route">
                                <?php if (!empty($route['registry_points'])): ?>
                                    <?php foreach ($route['registry_points'] as $point): ?>
                                        <span class="registry-route-line">
                                            <?php if (!empty($point['is_loading'])): ?><span class="registry-route-tag">↘</span><?php endif; ?>
                                            <?php if (!empty($point['is_unloading'])): ?><span class="registry-route-tag">↗</span><?php endif; ?>
                                            <?= e($point['address_text'] ?? '—') ?>
                                        </span>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <span class="registry-empty">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="registry-cargo"><?= e(trim((string) ($route['cargo_type_name'] ?? '')) !== '' ? $route['cargo_type_name'] : '—') ?></td>
                            <td class="registry-rate">
                                <?php if ($isFinanceRealm && $customerPayments !== []): ?>
                                    <span class="registry-rate-total"><?= e($formatCents($customerTotalCents)) ?></span>
                                    <?php foreach ($customerPayments as $payment): ?>
                                        <span class="registry-payment-line"><?= e($paymentDetail($payment)) ?></span>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <span class="registry-empty">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="registry-rate">
                                <?php if ($isFinanceRealm && $carrierPayments !== []): ?>
                                    <span class="registry-rate-total"><?= e($formatCents($carrierTotalCents)) ?></span>
                                    <?php foreach ($carrierPayments as $payment): ?>
                                        <span class="registry-payment-line"><?= e($paymentDetail($payment)) ?></span>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <span class="registry-empty">—</span>
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
<link rel="stylesheet" href="<?= app_url('/assets/css/linear-trip-form-ux.css') ?>?v=<?= filemtime(base_path('public/assets/css/linear-trip-form-ux.css')) ?>">
<link rel="stylesheet" href="<?= app_url('/assets/css/linear-trip-registry.css') ?>?v=<?= filemtime(base_path('public/assets/css/linear-trip-registry.css')) ?>">
<script src="<?= app_url('/assets/js/linear-trip-executor-carrier.js') ?>?v=<?= filemtime(base_path('public/assets/js/linear-trip-executor-carrier.js')) ?>"></script>
<link rel="stylesheet" href="<?= app_url('/assets/css/linear-trip-route-points.css') ?>?v=<?= filemtime(base_path('public/assets/css/linear-trip-route-points.css')) ?>">
<script src="<?= app_url('/assets/js/linear-trip-route-points.js') ?>?v=<?= filemtime(base_path('public/assets/js/linear-trip-route-points.js')) ?>"></script>