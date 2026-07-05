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
start_base_path_header_rewrite();
start_base_path_output_rewrite();

require_once base_path('app/Core/Database.php');
require_once base_path('app/Http/Router.php');
require_once base_path('app/Service/AccessControlService.php');
require_once base_path('app/Service/CompanyInnLookupService.php');
require_once base_path('app/Service/ContractorContactService.php');
require_once base_path('app/Service/ClientContactService.php');
require_once base_path('app/Service/ClientService.php');
require_once base_path('app/Service/ContractorService.php');
require_once base_path('app/Service/DriverService.php');
require_once base_path('app/Service/VehicleSetService.php');
require_once base_path('app/Service/SuperadminCompanyService.php');
require_once base_path('app/Service/DocumentService.php');
require_once base_path('app/Service/RouteExecutorService.php');
require_once base_path('app/Service/ResponsibleAssignmentService.php');
require_once base_path('app/Service/LocalMigrationService.php');
require_once base_path('app/Service/AuditService.php');
require_once base_path('app/Service/LinearRouteService.php');

require_once base_path('app/Support/core_runtime.php');
require_once base_path('app/Support/http_runtime.php');
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
require_once base_path('app/Http/Routes/legacy_redirects.php');
require_once base_path('app/Http/Routes/company_crews_legacy.php');
require_once base_path('app/Http/Routes/company_vehicle_sets.php');
require_once base_path('app/Http/Routes/company_driver_vehicle_blocks_legacy.php');
require_once base_path('app/Http/Routes/company_documents.php');
require_once base_path('app/Http/Routes/auth.php');
require_once base_path('app/Http/Routes/company_dashboard.php');
require_once base_path('app/Http/Routes/company_contractor_assignments.php');
require_once base_path('app/Http/Routes/company_route_executors.php');
require_once base_path('app/Http/Routes/company_responsible_assignments.php');
require_once base_path('app/Http/Routes/company_linear_trips.php');
require_once base_path('app/Http/Routes/superadmin_management.php');
require_once base_path('app/Http/Routes/superadmin_company_delete.php');
require_once base_path('app/Http/Routes/superadmin_deleted_data.php');

$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
