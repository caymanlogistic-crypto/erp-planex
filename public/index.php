<?php

/**
 * ERP PLANEX — Entry Point
 *
 * Минимальная техническая точка входа.
 * PDO-обёртка и роутер интегрированы.
 * Бизнес-маршруты, авторизация — не подключаются.
 */

session_start();

define('ERP_MAX_FILE_SIZE', 20 * 1024 * 1024);        // 20 MB per file
define('ERP_MAX_TOTAL_UPLOAD_SIZE', 80 * 1024 * 1024); // 80 MB per form submit

if (PHP_SAPI === 'cli-server') {
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $file = __DIR__ . $path;
    if ($path !== '/' && is_file($file)) {
        return false;
    }
}

$config = require_once __DIR__ . '/../bootstrap/app.php';

require_once base_path('app/Core/Database.php');
require_once base_path('app/Http/Router.php');
require_once base_path('app/Service/CompanyInnLookupService.php');
require_once base_path('app/Service/ContractorContactService.php');
require_once base_path('app/Service/ClientContactService.php');

require_once base_path('app/Support/legal_entity_document_upload.php');

require_once base_path('app/View/components/alert.php');
require_once base_path('app/View/components/button.php');
require_once base_path('app/View/components/contact_fields.php');
require_once base_path('app/View/components/empty_state.php');
require_once base_path('app/View/components/form_actions.php');
require_once base_path('app/View/components/input.php');
require_once base_path('app/View/components/page_header.php');
require_once base_path('app/View/components/status_badge.php');
require_once base_path('app/View/components/table.php');
require_once base_path('app/View/components/view_formatters.php');

function isAuthenticated(): bool
{
    return !empty($_SESSION['user_id']);
}

function requireRole(string|array $roles): void
{
    if (!isAuthenticated()) {
        header('Location: /login');
        exit;
    }
    $allowed = is_array($roles) ? $roles : [$roles];
    if (!in_array($_SESSION['role_code'] ?? '', $allowed, true)) {
        http_response_code(403);
        header('Content-Type: text/html; charset=utf-8');
        $pageTitle = 'Доступ запрещён';
        ob_start();
        require base_path('app/View/pages/error_403.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
        exit;
    }
}

function getSessionCompanyId(): ?int
{
    return isset($_SESSION['company_id']) ? (int)$_SESSION['company_id'] : null;
}

function jsonResponse(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}


function hasEntityAccess(PDO $pdo, string $entityType, int $entityId, int $userId, array $levels = ['view', 'edit']): bool
{
    if ($entityId <= 0 || $userId <= 0) {
        return false;
    }

    $placeholders = implode(',', array_fill(0, count($levels), '?'));
    $stmt = $pdo->prepare(
        "SELECT 1 FROM entity_access_grants
         WHERE entity_type = ?
           AND entity_id = ?
           AND granted_to_user_id = ?
           AND access_level IN ($placeholders)
           AND revoked_at IS NULL
         LIMIT 1"
    );
    $stmt->execute(array_merge([$entityType, $entityId, $userId], $levels));

    return (bool)$stmt->fetchColumn();
}

function hasRouteExecutorAccess(PDO $pdo, array $crew, int $userId, string $mode = 'view'): bool
{
    if ($userId <= 0) {
        return false;
    }

    if ((int)($crew['created_by_user_id'] ?? 0) === $userId) {
        return true;
    }

    $levels = $mode === 'edit' ? ['edit'] : ['view', 'edit'];
    $crewId = (int)($crew['id'] ?? $crew['crew_id'] ?? 0);
    if (hasEntityAccess($pdo, 'crew', $crewId, $userId, $levels)) {
        return true;
    }

    $contractorId = (int)($crew['contractor_id'] ?? 0);
    $driverVehicleBlockId = (int)($crew['driver_vehicle_block_id'] ?? 0);
    $driverId = (int)($crew['driver_id'] ?? 0);
    $vehicleSetId = (int)($crew['vehicle_set_id'] ?? 0);

    $hasContractor = ((int)($crew['contractor_created_by_user_id'] ?? 0) === $userId)
        || hasEntityAccess($pdo, 'contractor', $contractorId, $userId, $levels);

    $hasDriverVehicleBlock = ((int)($crew['dvb_created_by_user_id'] ?? 0) === $userId)
        || hasEntityAccess($pdo, 'driver_vehicle_block', $driverVehicleBlockId, $userId, $levels);

    $hasDriver = ((int)($crew['driver_created_by_user_id'] ?? 0) === $userId)
        || hasEntityAccess($pdo, 'driver', $driverId, $userId, $levels);

    $hasVehicleSet = ((int)($crew['vehicle_set_created_by_user_id'] ?? 0) === $userId)
        || hasEntityAccess($pdo, 'vehicle_set', $vehicleSetId, $userId, $levels);

    return $hasContractor && ($hasDriverVehicleBlock || ($hasDriver && $hasVehicleSet));
}

function requestJsonBody(): array
{
    $rawBody = file_get_contents('php://input');

    if (!is_string($rawBody) || trim($rawBody) === '') {
        return [];
    }

    $decoded = json_decode($rawBody, true);

    return is_array($decoded) ? $decoded : [];
}

function contractorFormDefaultContacts(): array
{
    return contactFieldsDefaultRows();
}

function clientFormDefaultContacts(): array
{
    return contactFieldsDefaultRows();
}

use App\Core\Database;
use App\Http\Router;
use App\Service\CompanyInnLookupService;
use App\Service\ContractorContactService;
use App\Service\ClientContactService;

$db     = new Database($config['database']);
$router = new Router();


// Route registration extracted from the legacy index monolith.
require_once base_path('app/Http/Routes/core.php');
require_once base_path('app/Http/Routes/superadmin.php');
require_once base_path('app/Http/Routes/company_logists.php');
require_once base_path('app/Http/Routes/company_clients.php');
require_once base_path('app/Http/Routes/company_contractors.php');
require_once base_path('app/Http/Routes/company_drivers.php');
require_once base_path('app/Http/Routes/company_vehicles.php');
require_once base_path('app/Http/Routes/company_crews_legacy.php');
require_once base_path('app/Http/Routes/company_vehicle_sets.php');
require_once base_path('app/Http/Routes/company_driver_vehicle_blocks_legacy.php');
require_once base_path('app/Http/Routes/company_documents.php');
require_once base_path('app/Http/Routes/auth.php');
require_once base_path('app/Http/Routes/company_dashboard.php');
require_once base_path('app/Http/Routes/company_contractor_assignments.php');
require_once base_path('app/Http/Routes/company_route_executors.php');
require_once base_path('app/Http/Routes/company_responsible_assignments.php');
require_once base_path('app/Http/Routes/superadmin_management.php');
require_once base_path('app/Http/Routes/superadmin_company_delete.php');

$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
