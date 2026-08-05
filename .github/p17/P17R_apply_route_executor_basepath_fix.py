from pathlib import Path


def replace_once(path: Path, before: str, after: str, label: str) -> None:
    text = path.read_text(encoding='utf-8')
    if text.count(before) != 1:
        raise RuntimeError(f'{label}: expected one anchor in {path}, got {text.count(before)}')
    path.write_text(text.replace(before, after), encoding='utf-8', newline='\n')


view = Path('app/View/pages/company_route_executor_view.php')
replacements = [
    (
        '<a href="/company/route-executors/<?= $crew[\'id\'] ?>/edit" class="btn btn-primary">Редактировать</a>',
        '<a href="<?= app_url(\'/company/route-executors/\' . (int) $crew[\'id\'] . \'/edit\') ?>" class="btn btn-primary">Редактировать</a>',
        'header edit link',
    ),
    (
        '<a href="/company/contractors/<?= $crew[\'contractor_id\'] ?>">',
        '<a href="<?= app_url(\'/company/contractors/\' . (int) $crew[\'contractor_id\']) ?>">',
        'contractor link',
    ),
    (
        '<a href="/company/drivers/<?= $crew[\'driver_id\'] ?>">',
        '<a href="<?= app_url(\'/company/drivers/\' . (int) $crew[\'driver_id\']) ?>">',
        'driver link',
    ),
    (
        '<a href="/company/vehicle-sets/<?= $crew[\'vehicle_set_id\'] ?>">',
        '<a href="<?= app_url(\'/company/vehicle-sets/\' . (int) $crew[\'vehicle_set_id\']) ?>">',
        'vehicle set link',
    ),
    (
        '<form method="post" action="/company/route-executors/<?= $crew[\'id\'] ?>/archive" class="inline-form">',
        '<form method="post" action="<?= app_url(\'/company/route-executors/\' . (int) $crew[\'id\'] . \'/archive\') ?>" class="inline-form">',
        'archive action',
    ),
]
# The edit link occurs twice intentionally; replace both in one guarded operation.
text = view.read_text(encoding='utf-8')
edit_before = replacements[0][0]
if text.count(edit_before) != 2:
    raise RuntimeError(f'edit links: expected two anchors, got {text.count(edit_before)}')
text = text.replace(edit_before, replacements[0][1])
view.write_text(text, encoding='utf-8', newline='\n')
for before, after, label in replacements[1:]:
    replace_once(view, before, after, label)

controller = Path('app/Http/Controllers/Company/RouteExecutorActions/archive.php')
text = controller.read_text(encoding='utf-8')
old_redirect = "header('Location: /company/route-executors');"
count = text.count(old_redirect)
if count != 5:
    raise RuntimeError(f'archive redirects: expected five anchors, got {count}')
text = text.replace(old_redirect, "header('Location: ' . app_url('/company/route-executors'));")
controller.write_text(text, encoding='utf-8', newline='\n')

Path('tests/p17_route_executor_basepath_test.php').write_text(r'''<?php

$view = file_get_contents(__DIR__ . '/../app/View/pages/company_route_executor_view.php');
$controller = file_get_contents(__DIR__ . '/../app/Http/Controllers/Company/RouteExecutorActions/archive.php');
$failed = 0;
function checkP17Executor(string $name, bool $condition): void
{
    global $failed;
    echo ($condition ? 'PASS' : 'FAIL') . ' - ' . $name . PHP_EOL;
    if (!$condition) $failed++;
}

checkP17Executor('executor view has no root-only company links', !str_contains($view, 'href="/company/'));
checkP17Executor('executor archive form uses app_url', str_contains($view, "app_url('/company/route-executors/"));
checkP17Executor('executor edit links use app_url', substr_count($view, "app_url('/company/route-executors/") >= 3);
checkP17Executor('executor related entity links use app_url', str_contains($view, "app_url('/company/contractors/") && str_contains($view, "app_url('/company/drivers/") && str_contains($view, "app_url('/company/vehicle-sets/"));
checkP17Executor('archive redirects preserve ERP base path', !str_contains($controller, "Location: /company/route-executors") && substr_count($controller, "app_url('/company/route-executors')") === 5);

echo 'FAILED=' . $failed . PHP_EOL;
exit($failed === 0 ? 0 : 1);
''', encoding='utf-8', newline='\n')

print('P17R_EXECUTOR_BASEPATH_PATCH=APPLIED')
