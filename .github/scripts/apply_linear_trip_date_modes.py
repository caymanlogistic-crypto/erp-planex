#!/usr/bin/env python3
from pathlib import Path


def replace_once(path: str, old: str, new: str) -> None:
    p = Path(path)
    text = p.read_text(encoding='utf-8')
    count = text.count(old)
    if count != 1:
        raise SystemExit(f'{path}: expected exactly one match, found {count}')
    p.write_text(text.replace(old, new, 1), encoding='utf-8')


# 1) Tenant schema: preserve four independent dates, but allow plan dates to be absent.
Path('database/migrations-local/062_allow_optional_linear_route_plan_dates.sql').write_text(
    """ALTER TABLE `linear_routes`\n"
    "    MODIFY COLUMN `planned_loading_date` DATE NULL,\n"
    "    MODIFY COLUMN `planned_unloading_date` DATE NULL;\n""",
    encoding='utf-8',
)

# 2) Create backend: accept and persist plan/fact independently.
replace_once(
    'app/Http/Controllers/Company/LinearTripActions/create_submit.php',
    """$cargoTypeName = LinearRouteService::normalizeCargoTypeName((string) ($_POST['cargo_type_name'] ?? ''));
$plannedLoadingDate = LinearRouteService::normalizeDate($_POST['planned_loading_date'] ?? '');
$plannedUnloadingDate = $plannedLoadingDate;
$comments = trim((string) ($_POST['comments'] ?? ''));""",
    """$cargoTypeName = LinearRouteService::normalizeCargoTypeName((string) ($_POST['cargo_type_name'] ?? ''));
$startDateKind = trim((string) ($_POST['start_date_kind'] ?? 'plan'));
$endDateKind = trim((string) ($_POST['end_date_kind'] ?? ''));
$plannedLoadingDate = LinearRouteService::normalizeDate($_POST['planned_loading_date'] ?? '');
$plannedUnloadingDate = LinearRouteService::normalizeDate($_POST['planned_unloading_date'] ?? '');
$actualLoadingDate = LinearRouteService::normalizeDate($_POST['actual_loading_date'] ?? '');
$actualUnloadingDate = LinearRouteService::normalizeDate($_POST['actual_unloading_date'] ?? '');
if ($endDateKind === '') {
    $plannedUnloadingDate = null;
    $actualUnloadingDate = null;
}
$comments = trim((string) ($_POST['comments'] ?? ''));""",
)
replace_once(
    'app/Http/Controllers/Company/LinearTripActions/create_submit.php',
    """if ($cargoTypeName === '') {
    $errors['cargo_type_name'] = 'Укажите тип груза.';
}
if ($plannedLoadingDate === null) {
    $errors['planned_loading_date'] = 'Укажите плановую дату загрузки.';
}

try {""",
    """if ($cargoTypeName === '') {
    $errors['cargo_type_name'] = 'Укажите перевозимый груз.';
}
if (!in_array($startDateKind, ['plan', 'fact'], true)) {
    $errors['start_date_kind'] = 'Выберите плановую или фактическую дату начала рейса.';
} elseif ($startDateKind === 'plan' && $plannedLoadingDate === null) {
    $errors['planned_loading_date'] = 'Укажите плановую дату начала рейса.';
} elseif ($startDateKind === 'fact' && $actualLoadingDate === null) {
    $errors['actual_loading_date'] = 'Укажите фактическую дату начала рейса.';
}
if (!in_array($endDateKind, ['', 'plan', 'fact'], true)) {
    $errors['end_date_kind'] = 'Выберите плановую или фактическую дату окончания рейса.';
} elseif ($endDateKind === 'plan' && $plannedUnloadingDate === null) {
    $errors['planned_unloading_date'] = 'Укажите плановую дату окончания рейса.';
} elseif ($endDateKind === 'fact' && $actualUnloadingDate === null) {
    $errors['actual_unloading_date'] = 'Укажите фактическую дату окончания рейса.';
}
if ($plannedLoadingDate !== null && $plannedUnloadingDate !== null && $plannedUnloadingDate < $plannedLoadingDate) {
    $errors['planned_unloading_date'] = 'Плановое окончание рейса не может быть раньше планового начала.';
}
if ($actualLoadingDate !== null && $actualUnloadingDate !== null && $actualUnloadingDate < $actualLoadingDate) {
    $errors['actual_unloading_date'] = 'Фактическое окончание рейса не может быть раньше фактического начала.';
}

try {""",
)
replace_once(
    'app/Http/Controllers/Company/LinearTripActions/create_submit.php',
    """            planned_loading_date,
            planned_unloading_date,
            status,""",
    """            planned_loading_date,
            planned_unloading_date,
            actual_loading_date,
            actual_unloading_date,
            status,""",
)
replace_once(
    'app/Http/Controllers/Company/LinearTripActions/create_submit.php',
    """            :planned_loading_date,
            :planned_unloading_date,
            'active',""",
    """            :planned_loading_date,
            :planned_unloading_date,
            :actual_loading_date,
            :actual_unloading_date,
            'active',""",
)
replace_once(
    'app/Http/Controllers/Company/LinearTripActions/create_submit.php',
    """        ':planned_loading_date' => $plannedLoadingDate,
        ':planned_unloading_date' => $plannedUnloadingDate,
        ':comments' => $comments !== '' ? $comments : null,""",
    """        ':planned_loading_date' => $plannedLoadingDate,
        ':planned_unloading_date' => $plannedUnloadingDate,
        ':actual_loading_date' => $actualLoadingDate,
        ':actual_unloading_date' => $actualUnloadingDate,
        ':comments' => $comments !== '' ? $comments : null,""",
)
replace_once(
    'app/Http/Controllers/Company/LinearTripActions/create_submit.php',
    """                'planned_loading_date' => $plannedLoadingDate,
                'planned_unloading_date' => $plannedUnloadingDate,
            ]""",
    """                'planned_loading_date' => $plannedLoadingDate,
                'planned_unloading_date' => $plannedUnloadingDate,
                'actual_loading_date' => $actualLoadingDate,
                'actual_unloading_date' => $actualUnloadingDate,
            ]""",
)

# 3) Edit backend: same four-state validation; end can be absent.
replace_once(
    'app/Service/LinearTripEditSaveService.php',
    """        $cargoTypeName = LinearRouteService::normalizeCargoTypeName((string) ($post['cargo_type_name'] ?? ''));
        $plannedLoadingDate = LinearRouteService::normalizeDate($post['planned_loading_date'] ?? '');
        $plannedUnloadingDate = LinearRouteService::normalizeDate($post['planned_unloading_date'] ?? '');
        $actualLoadingDate = LinearRouteService::normalizeDate($post['actual_loading_date'] ?? '');
        $actualUnloadingDate = LinearRouteService::normalizeDate($post['actual_unloading_date'] ?? '');
        $comments = trim((string) ($post['comments'] ?? ''));""",
    """        $cargoTypeName = LinearRouteService::normalizeCargoTypeName((string) ($post['cargo_type_name'] ?? ''));
        $startDateKind = trim((string) ($post['start_date_kind'] ?? 'plan'));
        $endDateKind = trim((string) ($post['end_date_kind'] ?? ''));
        $plannedLoadingDate = LinearRouteService::normalizeDate($post['planned_loading_date'] ?? '');
        $plannedUnloadingDate = LinearRouteService::normalizeDate($post['planned_unloading_date'] ?? '');
        $actualLoadingDate = LinearRouteService::normalizeDate($post['actual_loading_date'] ?? '');
        $actualUnloadingDate = LinearRouteService::normalizeDate($post['actual_unloading_date'] ?? '');
        if ($endDateKind === '') {
            $plannedUnloadingDate = null;
            $actualUnloadingDate = null;
        }
        $comments = trim((string) ($post['comments'] ?? ''));""",
)
replace_once(
    'app/Service/LinearTripEditSaveService.php',
    """        if ($routeExecutorId <= 0) $errors['route_executor_id'] = 'Выберите исполнителя рейса.';
        if ($cargoTypeName === '') $errors['cargo_type_name'] = 'Укажите тип груза.';
        if ($plannedLoadingDate === null) $errors['planned_loading_date'] = 'Укажите плановую дату загрузки.';
        if ($plannedLoadingDate !== null && $plannedUnloadingDate !== null && $plannedUnloadingDate < $plannedLoadingDate) {
            $errors['planned_unloading_date'] = 'Дата выгрузки не может быть раньше даты загрузки.';
        }
        if ($actualLoadingDate !== null && $actualUnloadingDate !== null && $actualUnloadingDate < $actualLoadingDate) {
            $errors['actual_unloading_date'] = 'Фактическая выгрузка не может быть раньше фактической загрузки.';
        }""",
    """        if ($routeExecutorId <= 0) $errors['route_executor_id'] = 'Выберите исполнителя рейса.';
        if ($cargoTypeName === '') $errors['cargo_type_name'] = 'Укажите перевозимый груз.';
        if (!in_array($startDateKind, ['plan', 'fact'], true)) {
            $errors['start_date_kind'] = 'Выберите плановую или фактическую дату начала рейса.';
        } elseif ($startDateKind === 'plan' && $plannedLoadingDate === null) {
            $errors['planned_loading_date'] = 'Укажите плановую дату начала рейса.';
        } elseif ($startDateKind === 'fact' && $actualLoadingDate === null) {
            $errors['actual_loading_date'] = 'Укажите фактическую дату начала рейса.';
        }
        if (!in_array($endDateKind, ['', 'plan', 'fact'], true)) {
            $errors['end_date_kind'] = 'Выберите плановую или фактическую дату окончания рейса.';
        } elseif ($endDateKind === 'plan' && $plannedUnloadingDate === null) {
            $errors['planned_unloading_date'] = 'Укажите плановую дату окончания рейса.';
        } elseif ($endDateKind === 'fact' && $actualUnloadingDate === null) {
            $errors['actual_unloading_date'] = 'Укажите фактическую дату окончания рейса.';
        }
        if ($plannedLoadingDate !== null && $plannedUnloadingDate !== null && $plannedUnloadingDate < $plannedLoadingDate) {
            $errors['planned_unloading_date'] = 'Плановое окончание рейса не может быть раньше планового начала.';
        }
        if ($actualLoadingDate !== null && $actualUnloadingDate !== null && $actualUnloadingDate < $actualLoadingDate) {
            $errors['actual_unloading_date'] = 'Фактическое окончание рейса не может быть раньше фактического начала.';
        }""",
)

# 4) Registry: actual has priority; plan is a gray fallback with an explicit note.
replace_once(
    'app/View/pages/company_linear_trips.php',
    """                        $actualLoading = trim((string) ($route['actual_loading_date'] ?? ''));
                        $loadingDate = $actualLoading !== '' ? $actualLoading : trim((string) ($route['planned_loading_date'] ?? ''));
                        $actualUnloading = trim((string) ($route['actual_unloading_date'] ?? ''));
""",
    """                        $plannedLoading = trim((string) ($route['planned_loading_date'] ?? ''));
                        $actualLoading = trim((string) ($route['actual_loading_date'] ?? ''));
                        $loadingDate = $actualLoading !== '' ? $actualLoading : $plannedLoading;
                        $loadingIsPlanned = $actualLoading === '' && $plannedLoading !== '';
                        $plannedUnloading = trim((string) ($route['planned_unloading_date'] ?? ''));
                        $actualUnloading = trim((string) ($route['actual_unloading_date'] ?? ''));
                        $unloadingDate = $actualUnloading !== '' ? $actualUnloading : $plannedUnloading;
                        $unloadingIsPlanned = $actualUnloading === '' && $plannedUnloading !== '';
""",
)
replace_once(
    'app/View/pages/company_linear_trips.php',
    """                            <td class="registry-date<?= $actualLoading === '' ? ' registry-date-planned' : '' ?>">
                                <?= e($loadingDate !== '' ? ui_date($loadingDate) : '—') ?>
                            </td>
                            <td class="registry-date"><?= e($actualUnloading !== '' ? ui_date($actualUnloading) : '—') ?></td>""",
    """                            <td class="registry-date<?= $loadingIsPlanned ? ' registry-date-planned' : '' ?>">
                                <span class="registry-date-value"><?= e($loadingDate !== '' ? ui_date($loadingDate) : '—') ?></span>
                                <?php if ($loadingIsPlanned): ?><span class="registry-date-note">(плановая)</span><?php endif; ?>
                            </td>
                            <td class="registry-date<?= $unloadingIsPlanned ? ' registry-date-planned' : '' ?>">
                                <span class="registry-date-value"><?= e($unloadingDate !== '' ? ui_date($unloadingDate) : '—') ?></span>
                                <?php if ($unloadingIsPlanned): ?><span class="registry-date-note">(плановая)</span><?php endif; ?>
                            </td>""",
)
replace_once(
    'app/View/pages/company_linear_trips.php',
    """<script src="<?= app_url('/assets/js/linear-trip-route-points.js') ?>?v=<?= filemtime(base_path('public/assets/js/linear-trip-route-points.js')) ?>"></script>""",
    """<script src="<?= app_url('/assets/js/linear-trip-route-points.js') ?>?v=<?= filemtime(base_path('public/assets/js/linear-trip-route-points.js')) ?>"></script>
<link rel="stylesheet" href="<?= app_url('/assets/css/linear-trip-date-mode.css') ?>?v=<?= filemtime(base_path('public/assets/css/linear-trip-date-mode.css')) ?>">
<script src="<?= app_url('/assets/js/linear-trip-date-mode.js') ?>?v=<?= filemtime(base_path('public/assets/js/linear-trip-date-mode.js')) ?>"></script>""",
)

# 5) View modal: same effective-date semantics + cargo.
replace_once(
    'app/View/partials/company_linear_trip_modal_view.php',
    """$customerTotal = $sumPayments($customerPayments);
$carrierTotal = $sumPayments($carrierPayments);
$margin = $customerTotal - $carrierTotal;
""",
    """$customerTotal = $sumPayments($customerPayments);
$carrierTotal = $sumPayments($carrierPayments);
$margin = $customerTotal - $carrierTotal;
$plannedLoading = trim((string) ($route['planned_loading_date'] ?? ''));
$actualLoading = trim((string) ($route['actual_loading_date'] ?? ''));
$displayLoading = $actualLoading !== '' ? $actualLoading : $plannedLoading;
$loadingIsPlanned = $actualLoading === '' && $plannedLoading !== '';
$plannedUnloading = trim((string) ($route['planned_unloading_date'] ?? ''));
$actualUnloading = trim((string) ($route['actual_unloading_date'] ?? ''));
$displayUnloading = $actualUnloading !== '' ? $actualUnloading : $plannedUnloading;
$unloadingIsPlanned = $actualUnloading === '' && $plannedUnloading !== '';
""",
)
replace_once(
    'app/View/partials/company_linear_trip_modal_view.php',
    """          <tr>
            <td>Плановая дата загрузки</td>
            <td><?= e(ui_date($route['planned_loading_date'] ?? null)) ?></td>
          </tr>
          <tr>
            <td>Загрузка</td>""",
    """          <tr>
            <td>Начало рейса</td>
            <td><span class="trip-view-date<?= $loadingIsPlanned ? ' is-planned' : '' ?>"><span class="trip-view-date-value"><?= e($displayLoading !== '' ? ui_date($displayLoading) : '—') ?></span><?php if ($loadingIsPlanned): ?><span class="trip-view-date-note">(плановая)</span><?php endif; ?></span></td>
          </tr>
          <tr>
            <td>Окончание рейса</td>
            <td><span class="trip-view-date<?= $unloadingIsPlanned ? ' is-planned' : '' ?>"><span class="trip-view-date-value"><?= e($displayUnloading !== '' ? ui_date($displayUnloading) : '—') ?></span><?php if ($unloadingIsPlanned): ?><span class="trip-view-date-note">(плановая)</span><?php endif; ?></span></td>
          </tr>
          <tr>
            <td>Перевозимый груз</td>
            <td><?= e(trim((string) ($route['cargo_type_name'] ?? '')) !== '' ? (string) $route['cargo_type_name'] : '—') ?></td>
          </tr>
          <tr>
            <td>Загрузка</td>""",
)

# 6) New UI stylesheet. It overrides the older P37 hide/layout rules without changing finance/doc geometry.
Path('public/assets/css/linear-trip-date-mode.css').write_text(r"""
/* Linear trip lifecycle dates: one visible date per edge, backed by independent plan/fact DB fields. */
[data-linear-trip-form] .linear-trip-date-row{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px;align-items:start;margin:0 0 6px}
[data-linear-trip-form] .linear-trip-date-control{min-width:0;margin:0}
[data-linear-trip-form] .linear-trip-date-shell{display:grid;grid-template-columns:minmax(0,1fr) auto;min-width:0}
[data-linear-trip-form] .linear-trip-date-shell>.field-input{min-width:0;border-top-right-radius:0!important;border-bottom-right-radius:0!important}
[data-linear-trip-form] .linear-trip-date-modes{display:flex;align-items:stretch}
[data-linear-trip-form] .linear-trip-date-modes .linear-trip-route-point-toggle{min-width:48px;border-left:0}
[data-linear-trip-form] .linear-trip-date-modes .linear-trip-route-point-toggle:last-child{border-radius:0 2px 2px 0}
[data-linear-trip-form] .linear-trip-primary-row.linear-trip-participant-row{grid-template-columns:minmax(0,1fr) minmax(0,1.45fr);gap:8px;margin-bottom:6px}
[data-linear-trip-form] .linear-trip-cargo-row{display:block;margin:0 0 5px}
[data-linear-trip-form] .linear-trip-cargo-row>.field{display:block!important;width:100%!important;max-width:none!important;flex:none!important}
[data-linear-trip-form] .linear-trip-cargo-row .field-input{width:100%}
[data-linear-trip-form] .linear-trip-date-backing{display:none!important}
[data-linear-trip-form] .field:has([name="cargo_type_name"]){display:block!important}
#linear-trip-edit-form .field:has([name="cargo_type_name"]){display:block!important}
.registry-date-value{display:block}
.registry-date-note{display:block;margin-top:2px;font-size:9px;font-weight:500;line-height:1.1;color:var(--text-faint,#80776d)}
.registry-date-planned .registry-date-value{color:var(--text-faint,#80776d);font-weight:500}
.trip-view-date{display:inline-flex;flex-direction:column;align-items:flex-start}
.trip-view-date.is-planned .trip-view-date-value{color:var(--text-faint,#80776d);font-weight:500}
.trip-view-date-note{margin-top:1px;font-size:9px;font-weight:500;color:var(--text-faint,#80776d)}
@media(max-width:680px){[data-linear-trip-form] .linear-trip-date-row,[data-linear-trip-form] .linear-trip-primary-row.linear-trip-participant-row{grid-template-columns:1fr}[data-linear-trip-form] .linear-trip-date-shell{grid-template-columns:minmax(0,1fr) auto}}
""".lstrip(), encoding='utf-8')

# 7) New lifecycle date controller. Loaded after the existing form enhancer, so it deliberately
#    unhides cargo and replaces four raw date fields with two plan/fact controls.
Path('public/assets/js/linear-trip-date-mode.js').write_text(r"""
(function () {
  'use strict';

  function isEditForm(form) {
    return !!(form && (form.id === 'linear-trip-edit-form' || /\/modal-edit(?:$|\?)/.test(String(form.action || ''))));
  }

  function value(input) {
    return input ? String(input.value || '').trim() : '';
  }

  function setActive(control, kind) {
    var hidden = control.querySelector('[data-trip-date-kind-value]');
    if (hidden) hidden.value = kind || '';
    control.querySelectorAll('[data-trip-date-kind]').forEach(function (button) {
      var active = button.getAttribute('data-trip-date-kind') === kind;
      button.classList.toggle('is-active', active);
      button.setAttribute('aria-pressed', active ? 'true' : 'false');
    });
  }

  function selectedKind(control) {
    var hidden = control.querySelector('[data-trip-date-kind-value]');
    return hidden ? String(hidden.value || '') : '';
  }

  function backingFor(control, kind) {
    var planName = control.getAttribute('data-plan-name');
    var factName = control.getAttribute('data-fact-name');
    var form = control.closest('form[data-linear-trip-form]');
    if (!form) return null;
    return form.querySelector('[name="' + (kind === 'fact' ? factName : planName) + '"]');
  }

  function syncVisibleToBacking(control) {
    var kind = selectedKind(control);
    if (!kind) return;
    var visible = control.querySelector('[data-trip-date-visible]');
    var backing = backingFor(control, kind);
    if (visible && backing) backing.value = visible.value || '';
  }

  function chooseKind(control, kind) {
    var edge = control.getAttribute('data-trip-date-control');
    var current = selectedKind(control);
    if (edge === 'end' && current === kind) {
      var form = control.closest('form[data-linear-trip-form]');
      var visible = control.querySelector('[data-trip-date-visible]');
      var plan = form ? form.querySelector('[name="planned_unloading_date"]') : null;
      var fact = form ? form.querySelector('[name="actual_unloading_date"]') : null;
      if (plan) plan.value = '';
      if (fact) fact.value = '';
      if (visible) visible.value = '';
      setActive(control, '');
      return;
    }
    syncVisibleToBacking(control);
    setActive(control, kind);
    var target = backingFor(control, kind);
    var visibleInput = control.querySelector('[data-trip-date-visible]');
    if (visibleInput) {
      visibleInput.value = value(target);
      visibleInput.focus();
    }
  }

  function createControl(form, edge, planInput, factInput, initialKind) {
    var control = document.createElement('div');
    control.className = 'field linear-trip-date-control';
    control.setAttribute('data-trip-date-control', edge);
    control.setAttribute('data-plan-name', planInput.name);
    control.setAttribute('data-fact-name', factInput.name);
    var label = edge === 'start' ? 'Начало рейса' : 'Окончание рейса';
    var required = edge === 'start' ? ' <span class="req">*</span>' : '';
    var hiddenName = edge === 'start' ? 'start_date_kind' : 'end_date_kind';
    control.innerHTML = '' +
      '<label class="field-label">' + label + required + '</label>' +
      '<div class="linear-trip-date-shell">' +
        '<input type="date" class="field-input" data-trip-date-visible' + (edge === 'start' ? ' required' : '') + '>' +
        '<div class="linear-trip-date-modes" role="group" aria-label="' + label + ': тип даты">' +
          '<button type="button" class="linear-trip-route-point-toggle" data-trip-date-kind="fact" aria-pressed="false">Факт</button>' +
          '<button type="button" class="linear-trip-route-point-toggle" data-trip-date-kind="plan" aria-pressed="false">План</button>' +
        '</div>' +
      '</div>' +
      '<input type="hidden" name="' + hiddenName + '" data-trip-date-kind-value>' +
      '<div class="field-msg" data-trip-date-message></div>';

    setActive(control, initialKind);
    var visible = control.querySelector('[data-trip-date-visible]');
    var initialBacking = initialKind ? backingFor(control, initialKind) : null;
    if (visible && initialBacking) visible.value = value(initialBacking);
    control.querySelectorAll('[data-trip-date-kind]').forEach(function (button) {
      button.addEventListener('click', function () { chooseKind(control, button.getAttribute('data-trip-date-kind')); });
    });
    if (visible) visible.addEventListener('input', function () { syncVisibleToBacking(control); });
    return control;
  }

  function unhideCargo(form) {
    var cargo = form.querySelector('input[name="cargo_type_name"]');
    if (!cargo) return null;
    var field = cargo.closest('.field');
    if (field) {
      field.hidden = false;
      field.classList.remove('is-hidden');
      field.style.removeProperty('display');
    }
    if (!isEditForm(form) && form.dataset.tripCargoNormalized !== '1' && value(cargo) === 'Не указан') {
      cargo.value = '';
    }
    form.dataset.tripCargoNormalized = '1';
    return field;
  }

  function initForm(form) {
    if (!form || !form.matches('form[data-linear-trip-form]')) return;
    var planStart = form.querySelector('[name="planned_loading_date"]');
    var planEnd = form.querySelector('[name="planned_unloading_date"]');
    var factStart = form.querySelector('[name="actual_loading_date"]');
    var factEnd = form.querySelector('[name="actual_unloading_date"]');
    var client = form.querySelector('[name="client_id"]');
    var executor = form.querySelector('[name="route_executor_id"]');
    var primaryRow = planStart ? planStart.closest('.linear-trip-primary-row') : null;
    if (!planStart || !planEnd || !factStart || !factEnd || !client || !executor || !primaryRow) return;

    if (form.dataset.tripDateModeReady === '1') {
      unhideCargo(form);
      return;
    }
    form.dataset.tripDateModeReady = '1';

    [planStart, planEnd, factStart, factEnd].forEach(function (input) {
      input.required = false;
      var field = input.closest('.field');
      if (field) {
        field.hidden = true;
        field.classList.add('is-hidden', 'linear-trip-date-backing');
      }
    });

    var edit = isEditForm(form);
    var startKind = value(factStart) ? 'fact' : (value(planStart) ? 'plan' : 'plan');
    var endKind = value(factEnd) ? 'fact' : (value(planEnd) ? 'plan' : '');
    if (!edit && !value(factStart) && !value(planStart)) startKind = 'plan';
    if (!edit && !value(factEnd) && !value(planEnd)) endKind = '';

    var dateRow = document.createElement('div');
    dateRow.className = 'field-row field-row-group linear-trip-date-row';
    dateRow.appendChild(createControl(form, 'start', planStart, factStart, startKind));
    dateRow.appendChild(createControl(form, 'end', planEnd, factEnd, endKind));
    primaryRow.parentNode.insertBefore(dateRow, primaryRow);

    primaryRow.classList.add('linear-trip-participant-row');
    var planStartField = planStart.closest('.field');
    if (planStartField && planStartField.parentNode === primaryRow) primaryRow.removeChild(planStartField);

    var cargoField = unhideCargo(form);
    if (cargoField) {
      var cargoRow = document.createElement('div');
      cargoRow.className = 'field-row field-row-group linear-trip-cargo-row';
      cargoRow.appendChild(cargoField);
      var agencyToggle = primaryRow.nextElementSibling && primaryRow.nextElementSibling.classList.contains('linear-trip-agency-toggle') ? primaryRow.nextElementSibling : null;
      if (agencyToggle) agencyToggle.parentNode.insertBefore(cargoRow, agencyToggle);
      else primaryRow.parentNode.insertBefore(cargoRow, primaryRow.nextSibling);
    }

    form.addEventListener('submit', function () {
      form.querySelectorAll('[data-trip-date-control]').forEach(syncVisibleToBacking);
      var endControl = form.querySelector('[data-trip-date-control="end"]');
      if (endControl && !selectedKind(endControl)) {
        planEnd.value = '';
        factEnd.value = '';
      }
      var cargo = form.querySelector('input[name="cargo_type_name"]');
      if (!edit && cargo && value(cargo) === 'Не указан') cargo.value = '';
    }, true);
  }

  function scan(root) {
    if (!root || !root.querySelectorAll) return;
    if (root.matches && root.matches('form[data-linear-trip-form]')) initForm(root);
    root.querySelectorAll('form[data-linear-trip-form]').forEach(initForm);
  }

  var observer = new MutationObserver(function (records) {
    records.forEach(function (record) {
      record.addedNodes.forEach(function (node) {
        if (node.nodeType === Node.ELEMENT_NODE) scan(node);
      });
    });
  });

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () {
      scan(document);
      observer.observe(document.body, {childList: true, subtree: true});
    }, {once: true});
  } else {
    scan(document);
    observer.observe(document.body, {childList: true, subtree: true});
  }
})();
""".lstrip(), encoding='utf-8')

print('Linear trip date-mode patch applied successfully.')
