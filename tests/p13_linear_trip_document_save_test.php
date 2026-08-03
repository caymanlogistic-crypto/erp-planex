<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$root = dirname(__DIR__);
$files = [
    'action' => 'app/Http/Controllers/Company/LinearTripActions/modal_edit_submit_p13.php',
    'wrapper' => 'app/Http/Controllers/Company/LinearTripActions/modal_edit_submit.php',
    'get' => 'app/Http/Controllers/Company/LinearTripActions/modal_edit_form.php',
    'form' => 'app/View/partials/company_linear_trip_create_form.php',
    'modal' => 'app/View/partials/company_linear_trip_modal_edit.php',
    'upload' => 'app/Service/LinearTripDocumentUploadService.php',
    'save' => 'app/Service/LinearTripEditSaveService.php',
    'token' => 'app/Service/LinearTripEditTokenService.php',
    'doc' => 'app/Service/DocumentService.php',
    'routes' => 'app/Http/Routes/company_linear_trips.php',
    'index' => 'public/index.php',
];
foreach ($files as $key => $path) $files[$key] = file_get_contents($root . '/' . $path);

$checks = [
    'Form has multipart encoding' => str_contains($files['form'], 'enctype="multipart/form-data"'),
    'Predefined file input exists' => str_contains($files['form'], 'type="file" name="<?= e($inputName) ?>"'),
    'Custom file input matches backend' => str_contains($files['form'], 'name="custom_doc_file[]"') && str_contains($files['upload'], "\$files['custom_doc_file']"),
    'POST route exists' => str_contains($files['routes'], "post('/company/trips/linear/{id}/modal-edit'"),
    'POST requires auth role' => str_contains($files['action'], "requireRole(['company_owner', 'senior_logist', 'logist'])"),
    'CSRF is globally verified' => str_contains($files['index'], 'verifyCsrfRequest();') && str_contains($files['index'], 'startCsrfFormInjection();'),
    'Company comes from session' => str_contains($files['action'], 'getSessionCompanyId()'),
    'Tenant DB is selected' => str_contains($files['action'], 'companyDatabaseConfig($config, $company)'),
    'Route permission is checked' => str_contains($files['action'], 'LinearRouteService::canEditRoute'),
    'Route is row-locked' => str_contains($files['save'], 'FOR UPDATE'),
    'GET runs no migrations' => !str_contains($files['get'], 'applyLocalMigrations'),
    'POST runs no migrations' => !str_contains($files['action'] . $files['save'] . $files['upload'], 'applyLocalMigrations'),
    'No controller DDL workaround' => !str_contains($files['action'] . $files['save'] . $files['upload'], 'ALTER TABLE'),
    'Entity type is linear_route' => str_contains($files['upload'], "ENTITY_TYPE = 'linear_route'"),
    'Shared whitelist includes route' => str_contains($files['doc'], "'linear_route'"),
    'Limit is 20 MB' => preg_match('/20\s*\*\s*1024\s*\*\s*1024/', $files['doc']) === 1,
    'Shared upload validation is used' => str_contains($files['upload'], 'DocumentService::validateUploadedFile'),
    'Stage precedes DB transaction' => strpos($files['save'], 'LinearTripDocumentUploadService::stage') < strpos($files['save'], '$pdo->beginTransaction()'),
    'Tenant path builder is used' => str_contains($files['upload'], 'DocumentService::buildRelativePath') && str_contains($files['upload'], 'DocumentService::buildStoredFilePath'),
    'Metadata is inserted' => str_contains($files['upload'], 'INSERT INTO documents'),
    'Old metadata retires after new insert' => strpos($files['upload'], '$insert->execute') < strpos($files['upload'], 'Linear route predefined document replaced'),
    'Rollback compensates final files' => str_contains($files['save'], 'cleanupFinalFiles($movedFinalFiles)'),
    'Staging is cleaned both paths' => substr_count($files['save'], 'cleanupStaged($stagedPlans)') >= 2,
    'Duplicate submit token is consumed' => str_contains($files['action'], 'LinearTripEditTokenService::consume') && str_contains($files['modal'], '_linear_trip_save_token'),
    'Frontend uses FormData' => str_contains($files['modal'], 'new FormData(form)'),
    'Frontend uses base-aware action' => str_contains($files['modal'], 'fetch(form.action'),
    'Frontend sends credentials' => str_contains($files['modal'], "credentials: 'same-origin'"),
    'Button locks while saving' => str_contains($files['modal'], "form.dataset.submitting === '1'") && str_contains($files['modal'], 'saveButton.disabled = isBusy'),
    'Exact server error is displayed' => str_contains($files['modal'], 'showError(data.message'),
    'Retry token is refreshed' => str_contains($files['modal'], 'data.retry_token') && str_contains($files['modal'], 'tokenInput.value'),
    'Selected file can be cleared' => str_contains($files['modal'], 'data-p13-clear-selected-file') && str_contains($files['modal'], "input.value = ''"),
    'Success reloads saved card' => str_contains($files['modal'], "ModalShell.get('linearTrip')") && str_contains($files['modal'], 'controller.loadView'),
    'Unknown exception is not exposed' => str_contains($files['action'], 'Изменения не применены.') && str_contains($files['action'], 'error_log(sprintf('),
    'Upload error is concrete and safe' => str_contains($files['action'], 'LinearTripDocumentUploadException') && str_contains($files['action'], "'message' => \$e->getMessage()"),
    'Legacy action delegates to P13' => str_contains($files['wrapper'], 'modal_edit_submit_p13.php'),
    'No hardcoded old ERP path' => !str_contains($files['modal'] . $files['action'], "'/erp/"),
];

$failed = 0;
echo "=== P13 Linear Trip Document Save ===\n";
foreach ($checks as $name => $ok) {
    echo ($ok ? 'PASS' : 'FAIL') . " - {$name}\n";
    if (!$ok) $failed++;
}
echo 'TOTAL=' . count($checks) . '; FAILED=' . $failed . "\n";
exit($failed === 0 ? 0 : 1);
