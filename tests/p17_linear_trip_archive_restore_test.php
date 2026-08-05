<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/Service/LinearRouteArchiveService.php';

use App\Service\LinearRouteArchiveService;

$failed = 0;
$check = static function (bool $condition, string $label) use (&$failed): void {
    if ($condition) {
        echo "PASS - {$label}\n";
        return;
    }
    $failed++;
    echo "FAIL - {$label}\n";
};

$normalized = LinearRouteArchiveService::normalizeRelatedIds([
    'linear_route_financial_terms' => [1, '2', 2, 0, -1, 'x'],
    'linear_route_payments' => ['7'],
    'linear_route_principals' => null,
    'documents' => [11, '12', 11],
    'unexpected_table' => [999],
]);

$check($normalized['linear_route_financial_terms'] === [1, 2], 'snapshot normalizes unique positive financial term IDs');
$check($normalized['linear_route_payments'] === [7], 'snapshot normalizes payment IDs');
$check($normalized['linear_route_principals'] === [], 'snapshot keeps absent principal list empty');
$check($normalized['documents'] === [11, 12], 'snapshot normalizes document IDs');
$check(!array_key_exists('unexpected_table', $normalized), 'snapshot rejects non-allowlisted tables');

$json = LinearRouteArchiveService::encodeSnapshot(
    ['id' => 55, 'client_id' => 10],
    $normalized
);
$decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
$check(($decoded['schema'] ?? null) === 'linear_route_archive_v1', 'snapshot has versioned schema');
$check(($decoded['route']['id'] ?? null) === 55, 'snapshot stores route facts');
$check(
    LinearRouteArchiveService::relatedIdsFromSnapshot($json) === $normalized,
    'snapshot round-trip preserves exact related ID allowlist'
);

$modalDelete = file_get_contents(__DIR__ . '/../app/Http/Controllers/Company/LinearTripActions/modal_delete.php');
$deletedData = file_get_contents(__DIR__ . '/../app/Http/Controllers/Superadmin/DeletedDataController.php');
$dependencies = file_get_contents(__DIR__ . '/../app/Support/entrypoint_dependencies.php');

$check(is_string($modalDelete) && str_contains($modalDelete, 'LinearRouteArchiveService::archive'), 'trip delete archives main and related rows through service');
$check(is_string($modalDelete) && str_contains($modalDelete, "'linear_route'"), 'trip delete records linear_route audit entity type');
$check(is_string($modalDelete) && str_contains($modalDelete, "'linear_routes'"), 'trip delete records linear_routes source table');
$check(is_string($modalDelete) && str_contains($modalDelete, 'AuditService::recordDeletion'), 'trip delete writes SUPERADMIN deleted-data audit');
$check(is_string($modalDelete) && str_contains($modalDelete, 'MutationErrorService::report'), 'trip delete logs unexpected errors with correlation ID');
$check(is_string($modalDelete) && !str_contains($modalDelete, "'Ошибка: ' . \$e->getMessage()"), 'trip delete does not expose exception text in JSON');

$check(is_string($deletedData) && str_contains($deletedData, 'LinearRouteArchiveService::restoreRelated'), 'SUPERADMIN restore restores related route rows');
$check(is_string($deletedData) && str_contains($deletedData, "'linear_route' => '/company/trips/linear'"), 'SUPERADMIN restore redirects restored route to trip registry');
$check(is_string($deletedData) && str_contains($deletedData, 'MutationErrorService::report'), 'SUPERADMIN restore uses protected unexpected-error logging');
$check(is_string($deletedData) && !str_contains($deletedData, "urlencode(\$e->getMessage())"), 'SUPERADMIN restore does not disclose exception text in URL');
$check(is_string($deletedData) && str_contains($deletedData, 'preg_replace'), 'source table is sanitized before dynamic SQL');

$check(is_string($dependencies) && str_contains($dependencies, 'LinearRouteArchiveService.php'), 'runtime dependency manifest registers archive service');

printf("FAILED=%d\n", $failed);
exit($failed === 0 ? 0 : 1);
