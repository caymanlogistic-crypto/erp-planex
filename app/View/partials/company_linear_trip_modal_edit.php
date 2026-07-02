<?php

use App\Service\LinearRouteService;

$old = [
    'id' => $route['id'] ?? 0,
    'route_type' => $route['route_type'] ?? '',
    'client_id' => $route['client_id'] ?? '',
    'carrier_contractor_id' => $route['carrier_contractor_id'] ?? '',
    'route_executor_id' => $route['route_executor_id'] ?? '',
    'cargo_type_name' => $route['cargo_type_name'] ?? '',
    'planned_loading_date' => $route['planned_loading_date'] ?? '',
    'planned_unloading_date' => $route['planned_unloading_date'] ?? '',
    'actual_loading_date' => $route['actual_loading_date'] ?? '',
    'actual_unloading_date' => $route['actual_unloading_date'] ?? '',
    'comments' => $route['comments'] ?? '',
    'customer_payments' => $route['payments']['customer'] ?? [],
    'carrier_payments' => $route['payments']['carrier'] ?? [],
    'principal_rows' => [],
];

foreach (($route['principal_items'] ?? []) as $principal) {
    $principalId = (int) ($principal['id'] ?? 0);
    $old['principal_rows'][] = [
        'entity_key' => $principal['entity_key'] ?? '',
        'payments' => $route['payments']['principals'][$principalId] ?? [],
    ];
}

if (!empty($validationErrors)) {
    $old = array_replace_recursive($old, $_POST);
}

$routeFormMode = 'edit';
$routeFormId = 'linear-trip-edit-form';
$routeFormAction = '/company/trips/linear/' . (int) ($route['id'] ?? 0) . '/modal-edit';
$routeFormDomPrefix = 'linear-trip-edit-' . (int) ($route['id'] ?? 0);
$routeFormClass = 'linear-trip-panel';
$existingDocsByCode = $docsByCode;
?>
<div class="modal-body driver-modal-body">
  <?php require base_path('app/View/partials/company_linear_trip_create_form.php'); ?>
</div>

<div class="modal-foot is-spaced">
  <div class="modal-required-note"><span class="req">*</span> — обязательные поля</div>
  <div class="modal-foot-actions">
    <button type="button" class="btn btn-ghost" data-linear-trip-cancel-edit-btn>Отмена</button>
    <button type="submit" form="linear-trip-edit-form" class="btn btn-primary">Сохранить</button>
  </div>
</div>
