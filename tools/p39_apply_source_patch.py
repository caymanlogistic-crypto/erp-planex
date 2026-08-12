from pathlib import Path


def replace_once(path, old, new):
    p = Path(path)
    text = p.read_text(encoding='utf-8')
    if old not in text:
        raise SystemExit(f'anchor missing in {path}: {old[:100]!r}')
    text = text.replace(old, new, 1)
    p.write_text(text, encoding='utf-8')


# Create: validate and persist route points in the same transaction as the route.
replace_once(
    'app/Http/Controllers/Company/LinearTripActions/create_submit.php',
    "$comments = trim((string) ($_POST['comments'] ?? ''));\n\n$isFinanceRealm =",
    "$comments = trim((string) ($_POST['comments'] ?? ''));\n$routePoints = \\App\\Service\\LinearRoutePointService::normalizeSubmitted($_POST);\n\\App\\Service\\LinearRoutePointService::validate($routePoints, $errors);\n\n$isFinanceRealm ="
)
replace_once(
    'app/Http/Controllers/Company/LinearTripActions/create_submit.php',
    "$linearRouteId = (int) $localPdo->lastInsertId();\n\n    $storedPrincipals = [];",
    "$linearRouteId = (int) $localPdo->lastInsertId();\n    \\App\\Service\\LinearRoutePointService::store($localPdo, $linearRouteId, $routePoints, $userId, $roleCode);\n\n    $storedPrincipals = [];"
)

# Edit: validate and persist points in the existing transactional save.
replace_once(
    'app/Service/LinearTripEditSaveService.php',
    "$comments = trim((string) ($post['comments'] ?? ''));\n\n        if (!in_array($routeType,",
    "$comments = trim((string) ($post['comments'] ?? ''));\n        $routePoints = LinearRoutePointService::normalizeSubmitted($post);\n        LinearRoutePointService::validate($routePoints, $errors);\n\n        if (!in_array($routeType,"
)
replace_once(
    'app/Service/LinearTripEditSaveService.php',
    "            if ($update->rowCount() === 0 && self::lockRoute($pdo, $routeId) === null) {\n                throw new RuntimeException('Route update failed.');\n            }\n\n            if ($isFinanceRealm) {",
    "            if ($update->rowCount() === 0 && self::lockRoute($pdo, $routeId) === null) {\n                throw new RuntimeException('Route update failed.');\n            }\n\n            LinearRoutePointService::store($pdo, $routeId, $routePoints, $userId, $roleCode);\n\n            if ($isFinanceRealm) {"
)

# Edit/view loaders: read route points without mutating GET requests.
replace_once(
    'app/Http/Controllers/Company/LinearTripActions/modal_edit_form.php',
    "    if (!LinearRouteService::canEditRoute($route, $localPdo, $sessionUser)) {",
    "    $route['route_points'] = \\App\\Service\\LinearRoutePointService::fetch($localPdo, $routeId);\n    if (!LinearRouteService::canEditRoute($route, $localPdo, $sessionUser)) {"
)
replace_once(
    'app/Http/Controllers/Company/LinearTripActions/modal_view.php',
    "    if (!LinearRouteService::canViewRoute($route, $localPdo, $sessionUser)) {",
    "    $route['route_points'] = \\App\\Service\\LinearRoutePointService::fetch($localPdo, (int) $id);\n    if (!LinearRouteService::canViewRoute($route, $localPdo, $sessionUser)) {"
)

# Edit form old-data map.
replace_once(
    'app/View/partials/company_linear_trip_modal_edit.php',
    "    'comments' => $route['comments'] ?? '',\n    'customer_payments' =>",
    "    'comments' => $route['comments'] ?? '',\n    'route_points' => $route['route_points'] ?? ['loading' => [''], 'unloading' => ['']],\n    'customer_payments' =>"
)

# Shared create/edit form: initialize and render repeatable point groups.
replace_once(
    'app/View/partials/company_linear_trip_create_form.php',
    "$documentDefinitions = LinearRouteService::routeDocumentDefinitions($currentRouteType ?: LinearRouteService::ROUTE_TYPE_AGENCY);",
    "$routePointRows = (array) ($old['route_points'] ?? []);\n$routePointRows['loading'] = array_values((array) ($routePointRows['loading'] ?? []));\n$routePointRows['unloading'] = array_values((array) ($routePointRows['unloading'] ?? []));\nif ($routePointRows['loading'] === []) $routePointRows['loading'] = [''];\nif ($routePointRows['unloading'] === []) $routePointRows['unloading'] = [''];\n\n$documentDefinitions = LinearRouteService::routeDocumentDefinitions($currentRouteType ?: LinearRouteService::ROUTE_TYPE_AGENCY);"
)
points_html = r'''
            <div class="linear-trip-route-points-wrap">
                <?php foreach (['loading' => 'Загрузка', 'unloading' => 'Выгрузка'] as $pointType => $pointTitle): ?>
                <div class="linear-trip-route-point-group" data-route-point-group="<?= e($pointType) ?>">
                    <div class="linear-trip-route-point-head">
                        <div class="linear-trip-route-point-title"><?= e($pointTitle) ?> <span class="req">*</span></div>
                        <button type="button" class="linear-trip-route-point-add" data-add-route-point>+ Добавить</button>
                    </div>
                    <div data-route-point-list>
                        <?php foreach ($routePointRows[$pointType] as $pointIndex => $pointAddress): ?>
                        <div class="linear-trip-route-point-row" data-route-point-row>
                            <div class="linear-trip-route-point-index" data-route-point-number><?= $pointIndex + 1 ?></div>
                            <input type="text" name="route_points[<?= e($pointType) ?>][]" value="<?= e((string) $pointAddress) ?>" class="field-input" data-route-point-input autocomplete="off" placeholder="<?= $pointType === 'loading' ? 'Адрес или место загрузки' : 'Адрес или место выгрузки' ?>">
                            <button type="button" class="linear-trip-route-point-remove<?= count($routePointRows[$pointType]) === 1 ? ' is-hidden' : '' ?>" data-remove-route-point aria-label="Удалить точку">×</button>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="linear-trip-route-point-msg"><?= e($errorOf('route_points.' . $pointType . '.0')) ?></div>
                </div>
                <?php endforeach; ?>
            </div>

'''
replace_once(
    'app/View/partials/company_linear_trip_create_form.php',
    "            <?php if ($showActualDates): ?>",
    points_html + "            <?php if ($showActualDates): ?>"
)

# Load P39 assets on the linear trips page.
replace_once(
    'app/View/pages/company_linear_trips.php',
    "<script src=\"<?= app_url('/assets/js/linear-trip-executor-carrier.js') ?>?v=<?= filemtime(base_path('public/assets/js/linear-trip-executor-carrier.js')) ?>\"></script>",
    "<script src=\"<?= app_url('/assets/js/linear-trip-executor-carrier.js') ?>?v=<?= filemtime(base_path('public/assets/js/linear-trip-executor-carrier.js')) ?>\"></script>\n<link rel=\"stylesheet\" href=\"<?= app_url('/assets/css/linear-trip-route-points.css') ?>?v=<?= filemtime(base_path('public/assets/css/linear-trip-route-points.css')) ?>\">\n<script src=\"<?= app_url('/assets/js/linear-trip-route-points.js') ?>?v=<?= filemtime(base_path('public/assets/js/linear-trip-route-points.js')) ?>\"></script>"
)

print('P39 source patch applied')
