<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$files = [
    'create' => $root . '/app/Http/Controllers/Company/LinearTripActions/create_submit.php',
    'edit' => $root . '/app/Service/LinearTripEditSaveService.php',
    'registry' => $root . '/app/View/pages/company_linear_trips.php',
    'view' => $root . '/app/View/partials/company_linear_trip_modal_view.php',
    'js' => $root . '/public/assets/js/linear-trip-date-mode.js',
    'css' => $root . '/public/assets/css/linear-trip-date-mode.css',
    'migration' => $root . '/database/migrations-local/062_allow_optional_linear_route_plan_dates.sql',
];

foreach ($files as $name => $path) {
    if (!is_file($path)) {
        fwrite(STDERR, "Missing {$name}: {$path}\n");
        exit(1);
    }
}

$read = static fn (string $key): string => (string) file_get_contents($files[$key]);
$assertContains = static function (string $haystack, string $needle, string $label): void {
    if (!str_contains($haystack, $needle)) {
        fwrite(STDERR, "FAIL: {$label}\nMissing: {$needle}\n");
        exit(1);
    }
};

$create = $read('create');
$edit = $read('edit');
$registry = $read('registry');
$view = $read('view');
$js = $read('js');
$css = $read('css');
$migration = $read('migration');

foreach (['planned_loading_date', 'actual_loading_date', 'planned_unloading_date', 'actual_unloading_date'] as $column) {
    $assertContains($create, $column, "create persists {$column}");
    $assertContains($edit, $column, "edit persists {$column}");
}
$assertContains($create, "start_date_kind", 'create accepts start plan/fact mode');
$assertContains($create, "end_date_kind", 'create accepts optional end mode');
$assertContains($edit, "start_date_kind", 'edit accepts start plan/fact mode');
$assertContains($edit, "end_date_kind", 'edit accepts optional end mode');
$assertContains($migration, 'MODIFY COLUMN `planned_loading_date` DATE NULL', 'planned start can be absent');
$assertContains($migration, 'MODIFY COLUMN `planned_unloading_date` DATE NULL', 'planned end can be absent');
$assertContains($registry, '(плановая)', 'registry labels planned fallback dates');
$assertContains($registry, '$unloadingDate = $actualUnloading !== \'\' ? $actualUnloading : $plannedUnloading;', 'registry uses actual end before plan');
$assertContains($view, '<td>Начало рейса</td>', 'view has trip start');
$assertContains($view, '<td>Окончание рейса</td>', 'view has trip end');
$assertContains($view, '<td>Перевозимый груз</td>', 'view has cargo');
$assertContains($js, 'data-trip-date-kind="fact"', 'form has Fact toggle');
$assertContains($js, 'data-trip-date-kind="plan"', 'form has Plan toggle');
$assertContains($js, "startKind = 'plan'", 'new trip start defaults to plan');
$assertContains($js, "endKind = ''", 'new trip end defaults to no mode');
$assertContains($css, '.linear-trip-date-row', 'date controls share the first row');
$assertContains($css, '.linear-trip-participant-row', 'client/executor occupy next row');
$assertContains($css, '.linear-trip-cargo-row', 'cargo occupies its own full-width row');

fwrite(STDOUT, "linear_trip_date_mode_contract: PASS\n");
