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

require_once base_path('app/View/components/alert.php');
require_once base_path('app/View/components/button.php');
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
    return [[
        'contact_person' => '',
        'phone' => '',
        'email' => '',
        'comment' => '',
        'is_primary' => '1',
        'is_document_email' => '0',
    ]];
}

use App\Core\Database;
use App\Http\Router;
use App\Service\CompanyInnLookupService;
use App\Service\ContractorContactService;
use App\Service\ClientContactService;

$db     = new Database($config['database']);
$router = new Router();

$router->get('/favicon.ico', function () {
    http_response_code(204);
    exit;
});

function generatePassword(int $length = 10): string
{
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $password;
}

/**
 * Build passport number from series and number.
 * If series is provided, concatenates "series number" into passport_number DB column.
 * DESIGN_TODO: add separate passport_series column to drivers table.
 */
function buildPassportNumber(?string $series, ?string $number): ?string
{
    $series = trim($series ?? '');
    $number = trim($number ?? '');

    if ($series === '' && $number === '') {
        return null;
    }

    if ($series !== '') {
        return $series . ' ' . $number;
    }

    return $number;
}

function vehicleSetTypeRules(): array
{
    return [
        'single' => [
            'label' => 'Одиночка',
            'primary_label' => 'Транспортная единица',
            'secondary_label' => null,
            'primary_unit_types' => ['single', 'truck'],
            'secondary_unit_types' => [],
            'secondary_required' => false,
            'primary_docs_title' => 'Документы транспортной единицы',
            'secondary_docs_title' => '',
            'units' => [
                'primary' => [
                    'label' => 'Транспортная единица',
                    'unit_type' => 'single',
                    'docs_title' => 'Документы транспортной единицы',
                    'show_capacity' => true,
                    'show_volume' => true,
                ],
            ],
        ],
        'coupling' => [
            'label' => 'Сцепка',
            'primary_label' => 'Тягач',
            'secondary_label' => 'Полуприцеп',
            'primary_unit_types' => ['tractor'],
            'secondary_unit_types' => ['semi_trailer'],
            'secondary_required' => true,
            'primary_docs_title' => 'Документы тягача',
            'secondary_docs_title' => 'Документы полуприцепа',
            'units' => [
                'primary' => [
                    'label' => 'Тягач',
                    'unit_type' => 'tractor',
                    'docs_title' => 'Документы тягача',
                    'show_capacity' => false,
                    'show_volume' => false,
                ],
                'secondary' => [
                    'label' => 'Полуприцеп',
                    'unit_type' => 'semi_trailer',
                    'docs_title' => 'Документы полуприцепа',
                    'show_capacity' => true,
                    'show_volume' => true,
                ],
            ],
        ],
        'road_train' => [
            'label' => 'Автопоезд',
            'primary_label' => 'Первая часть',
            'secondary_label' => 'Прицеп',
            'primary_unit_types' => ['truck'],
            'secondary_unit_types' => ['trailer'],
            'secondary_required' => true,
            'primary_docs_title' => 'Документы первой части',
            'secondary_docs_title' => 'Документы прицепа',
            'units' => [
                'primary' => [
                    'label' => 'Первая часть',
                    'unit_type' => 'truck',
                    'docs_title' => 'Документы первой части',
                    'show_capacity' => true,
                    'show_volume' => true,
                ],
                'secondary' => [
                    'label' => 'Прицеп',
                    'unit_type' => 'trailer',
                    'docs_title' => 'Документы прицепа',
                    'show_capacity' => true,
                    'show_volume' => true,
                ],
            ],
        ],
    ];
}

function vehicleSetPredefinedDocumentSections(): array
{
    return [
        'primary' => [
            ['input_code' => 'primary_sts', 'doc_code' => 'sts', 'badge' => '-', 'name' => 'СТС'],
            ['input_code' => 'primary_diagnostic_card', 'doc_code' => 'diagnostic_card', 'badge' => '-', 'name' => 'Диагностическая карта'],
            ['input_code' => 'primary_photo', 'doc_code' => 'photo', 'badge' => '-', 'name' => 'Фотография'],
        ],
        'secondary' => [
            ['input_code' => 'secondary_sts', 'doc_code' => 'sts', 'badge' => '-', 'name' => 'СТС'],
            ['input_code' => 'secondary_diagnostic_card', 'doc_code' => 'diagnostic_card', 'badge' => '-', 'name' => 'Диагностическая карта'],
            ['input_code' => 'secondary_photo', 'doc_code' => 'photo', 'badge' => '-', 'name' => 'Фотография'],
        ],
    ];
}

function vehicleSetDocumentUploadMap(string $setType): array
{
    $sections = vehicleSetPredefinedDocumentSections();
    $map = [];

    foreach ($sections['primary'] as $doc) {
        $map[$doc['input_code']] = $doc + ['entity_role' => 'primary'];
    }

    if (in_array($setType, ['coupling', 'road_train'], true)) {
        foreach ($sections['secondary'] as $doc) {
            $map[$doc['input_code']] = $doc + ['entity_role' => 'secondary'];
        }
    }

    return $map;
}

function vehicleSetUnitTypeLabelList(array $unitTypes): string
{
    $labels = array_values(array_filter(array_map(static function ($type) {
        return ui_unit_type((string) $type);
    }, $unitTypes)));

    return implode(', ', $labels);
}

function vehicleSetVisibleUnitRoles(string $setType): array
{
    $rules = vehicleSetTypeRules();
    if (!isset($rules[$setType]['units']) || !is_array($rules[$setType]['units'])) {
        return [];
    }

    return array_keys($rules[$setType]['units']);
}

function validateVehicleSetSelection(PDO $localPdo, string $setType, string $primaryId, string $secondaryId): array
{
    $rules = vehicleSetTypeRules();
    $errors = [];
    $units = [];

    if ($setType === '' || !isset($rules[$setType])) {
        $errors['set_type'] = 'Выберите тип комплекта';
        return ['errors' => $errors, 'units' => $units];
    }

    $rule = $rules[$setType];

    if ($primaryId === '') {
        $errors['primary_vehicle_unit_id'] = 'Выберите основную транспортную единицу';
    }

    if (!empty($rule['secondary_required']) && $secondaryId === '') {
        $errors['secondary_vehicle_unit_id'] = 'Выберите дополнительную транспортную единицу';
    }

    $ids = [];
    if ($primaryId !== '' && ctype_digit($primaryId)) {
        $ids[] = (int) $primaryId;
    }
    if ($secondaryId !== '' && ctype_digit($secondaryId)) {
        $ids[] = (int) $secondaryId;
    }

    if (!empty($ids)) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $localPdo->prepare("SELECT * FROM vehicle_units WHERE id IN ($placeholders)");
        $stmt->execute($ids);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $unit) {
            $units[(int) $unit['id']] = $unit;
        }
    }

    if ($primaryId !== '') {
        $primaryInt = ctype_digit($primaryId) ? (int) $primaryId : 0;
        $primaryUnit = $units[$primaryInt] ?? null;
        if (!$primaryUnit) {
            $errors['primary_vehicle_unit_id'] = 'Основная транспортная единица не найдена';
        } elseif (($primaryUnit['status'] ?? '') !== 'active') {
            $errors['primary_vehicle_unit_id'] = 'Основная транспортная единица неактивна';
        } elseif (!in_array((string) ($primaryUnit['unit_type'] ?? ''), $rule['primary_unit_types'], true)) {
            $errors['primary_vehicle_unit_id'] = 'Для выбранного типа комплекта допустимы только: ' . vehicleSetUnitTypeLabelList($rule['primary_unit_types']);
        }
    }

    if ($secondaryId !== '') {
        $secondaryInt = ctype_digit($secondaryId) ? (int) $secondaryId : 0;
        $secondaryUnit = $units[$secondaryInt] ?? null;
        if (!$secondaryUnit) {
            $errors['secondary_vehicle_unit_id'] = 'Дополнительная транспортная единица не найдена';
        } elseif (($secondaryUnit['status'] ?? '') !== 'active') {
            $errors['secondary_vehicle_unit_id'] = 'Дополнительная транспортная единица неактивна';
        } elseif (!in_array((string) ($secondaryUnit['unit_type'] ?? ''), $rule['secondary_unit_types'], true)) {
            $errors['secondary_vehicle_unit_id'] = 'Для выбранного типа комплекта допустимы только: ' . vehicleSetUnitTypeLabelList($rule['secondary_unit_types']);
        }
    }

    if ($primaryId !== '' && $secondaryId !== '' && ctype_digit($primaryId) && ctype_digit($secondaryId) && (int) $primaryId === (int) $secondaryId) {
        $errors['secondary_vehicle_unit_id'] = 'Основная и дополнительная единицы должны отличаться';
    }

    return ['errors' => $errors, 'units' => $units];
}

function ensureDocumentTypeRecord(PDO $localPdo, string $name, string $code, string $entityType, string $category = 'predefined'): ?int
{
    $name = trim($name);
    if ($name === '') {
        return null;
    }

    try {
        $insert = $localPdo->prepare(
            'INSERT IGNORE INTO document_types (name, code, entity_type, category, created_by_user_id, created_by_role)
             VALUES (:name, :code, :entity_type, :category, :user_id, :role)'
        );
        $insert->execute([
            ':name' => $name,
            ':code' => $code !== '' ? $code : null,
            ':entity_type' => $entityType !== '' ? $entityType : null,
            ':category' => $category,
            ':user_id' => (int) ($_SESSION['user_id'] ?? 0),
            ':role' => (string) ($_SESSION['role_code'] ?? 'system'),
        ]);
    } catch (\Throwable $e) {
    }

    $lookup = $localPdo->prepare(
        'SELECT id
           FROM document_types
          WHERE name = :name
            AND ((entity_type = :entity_type_value) OR (entity_type IS NULL AND :entity_type_null IS NULL))
          ORDER BY id DESC
          LIMIT 1'
    );
    $lookup->execute([
        ':name' => $name,
        ':entity_type_value' => $entityType !== '' ? $entityType : null,
        ':entity_type_null' => $entityType !== '' ? $entityType : null,
    ]);

    $id = $lookup->fetchColumn();
    return $id !== false ? (int) $id : null;
}

function applyLocalMigrations(\PDO $localPdo): void
{
    for ($i = 1; $i <= 38; $i++) {
        $pattern = base_path('database/migrations-local/' . sprintf('%03d', $i) . '_*.sql');
        $files = glob($pattern);
        if (!$files) {
            continue;
        }
        $file = $files[0];
        $fileName = basename($file);
        try {
            $sql = file_get_contents($file);
            if ($sql !== false && trim($sql) !== '') {
                $localPdo->exec($sql);
            }
        } catch (\Exception $e) {
            error_log('Local migration ' . $fileName . ': ' . $e->getMessage());
        }
    }
}

function formatFileSize(int $bytes): string
{
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' МБ';
    }
    if ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' КБ';
    }
    return $bytes . ' Б';
}

/**
 * Validate total uploaded file size across all file inputs in $_FILES.
 * Returns an error message string if total > ERP_MAX_TOTAL_UPLOAD_SIZE, or empty string if OK.
 */
function validateTotalUploadSize(): string
{
    $total = 0;
    $processFileArray = function (array $arr) use (&$total, &$processFileArray): void {
        if (isset($arr['size'])) {
            if (is_array($arr['size'])) {
                foreach ($arr['size'] as $size) {
                    if (is_array($size)) {
                        $processFileArray(['size' => $size]);
                    } elseif (is_numeric($size)) {
                        $total += (int) $size;
                    }
                }
            } elseif (is_numeric($arr['size'])) {
                $total += (int) $arr['size'];
            }
        } else {
            foreach ($arr as $val) {
                if (is_array($val)) {
                    $processFileArray($val);
                }
            }
        }
    };
    $processFileArray($_FILES);

    if ($total > ERP_MAX_TOTAL_UPLOAD_SIZE) {
        return 'Общий размер файлов превышает 80 МБ. Уменьшите количество файлов.';
    }
    return '';
}

/**
 * Check if PHP truncated the POST body (post_max_size exceeded).
 */
function isPostTruncated(): bool
{
    return ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0
        && empty($_POST)
        && empty($_FILES)
        && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
}

$router->get('/', function () use ($config) {
    if (!isAuthenticated()) {
        header('Location: /login');
        exit;
    }

    $role = $_SESSION['role_code'] ?? '';
    if ($role === 'superadmin') {
        header('Location: /superadmin/companies');
        exit;
    }

    header('Location: /company/dashboard');
    exit;
});

$router->get('/dev/ui-foundation', function () use ($config) {
    $pageTitle = 'UI foundation';

    ob_start();
    require base_path('app/View/pages/ui_demo.php');
    $content = ob_get_clean();

    require base_path('app/View/layouts/main.php');
});

$router->get('/test', function () {
    header('Content-Type: text/plain');
    echo 'ERP PLANEX core is running';
});

$router->get('/test-db', function () use ($db) {
    header('Content-Type: text/plain');
    try {
        $db->connection();
        echo 'DB connection OK';
    } catch (\Exception $e) {
        if (isset($localPdo) && $localPdo instanceof \PDO && $localPdo->inTransaction()) {
            $localPdo->rollBack();
        }
        if (isset($localPdo) && $localPdo instanceof \PDO && $localPdo->inTransaction()) {
            $localPdo->rollBack();
        }
        echo 'DB connection FAILED';
    }
});

// ============================================================
// SUPERADMIN: Companies list
// ============================================================

$router->get('/superadmin/companies', function () use ($config, $db) {
    requireRole('superadmin');
    $pageTitle = 'Реестр компаний';
    $pageContext = 'Реестр компаний';

    $search = trim($_GET['search'] ?? '');
    $filterStatus = trim($_GET['status'] ?? '');

    try {
        $pdo = $db->connection();

        $sql = 'SELECT c.*,
                       cu.full_name as owner_name,
                       (SELECT COUNT(*) FROM company_users WHERE company_id = c.id) as user_count
                FROM companies c
                LEFT JOIN company_users cu ON c.id = cu.company_id AND cu.role = :owner_role';

        $conditions = [];
        $params = [':owner_role' => 'company_owner'];

        if ($search !== '') {
            $conditions[] = '(c.name LIKE :search OR c.inn LIKE :search2)';
            $params[':search'] = '%' . $search . '%';
            $params[':search2'] = '%' . $search . '%';
        }

        if ($filterStatus !== '') {
            $conditions[] = 'c.status = :status';
            $params[':status'] = $filterStatus;
        }

        if (!empty($conditions)) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }

        $sql .= ' ORDER BY c.created_at DESC';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $companies = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $dbError = null;
    } catch (\Exception $e) {
        $companies = [];
        $dbError = 'Не удалось загрузить список компаний. Попробуйте позже.';
    }

    ob_start();
    require base_path('app/View/pages/superadmin_companies.php');
    $content = ob_get_clean();

    require base_path('app/View/layouts/main.php');
});

// ============================================================
// SUPERADMIN: Company create
// ============================================================

$router->get('/superadmin/companies/create', function () use ($config) {
    requireRole('superadmin');
    $pageTitle = 'Создать экспедитора';
    $pageContext = 'Реестр компаний';
    $errors = [];
    $old = [];
    $formError = null;

    ob_start();
    require base_path('app/View/pages/superadmin_companies_create.php');
    $content = ob_get_clean();

    require base_path('app/View/layouts/main.php');
});

$router->post('/superadmin/companies/create', function () use ($config, $db) {
    requireRole('superadmin');
    $pageTitle = 'Создать экспедитора';
    $pageContext = 'Реестр компаний';
    $errors = [];
    $old = $_POST;
    $formError = null;

    $name = trim($_POST['name'] ?? '');
    $inn  = trim($_POST['inn'] ?? '');

    if ($name === '') {
        $errors['name'] = 'Обязательное поле';
    }

    if ($inn === '') {
        $errors['inn'] = 'Обязательное поле';
    }

    if (!empty($errors)) {
        ob_start();
        require base_path('app/View/pages/superadmin_companies_create.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
        return;
    }

    try {
        $pdo = $db->connection();

        $key = 'company_' . uniqid();

        $insert = $pdo->prepare(
            'INSERT INTO companies (`key`, name, inn, kpp, ogrn, legal_address, physical_address,
                director_position, director_full_name, comments, status)
             VALUES (:key, :name, :inn, :kpp, :ogrn, :legal_address, :physical_address,
                :director_position, :director_full_name, :comments, :status)'
        );

        $insert->execute([
            ':key'              => $key,
            ':name'             => $name,
            ':inn'              => $inn,
            ':kpp'              => $_POST['kpp'] ?? null,
            ':ogrn'             => $_POST['ogrn'] ?? null,
            ':legal_address'    => $_POST['legal_address'] ?? null,
            ':physical_address' => $_POST['physical_address'] ?? null,
            ':director_position' => $_POST['director_position'] ?? null,
            ':director_full_name' => $_POST['director_full_name'] ?? null,
            ':comments'         => $_POST['comments'] ?? null,
            ':status'           => 'provisioning',
        ]);

        $companyId = (int) $pdo->lastInsertId();
        $dbName = 'erp_company_' . $companyId;
        $storageDir = storage_path('companies/' . $companyId);

        $newKey = 'company_' . $companyId;

        try {
            $dbConfig = $config['database'];
            $dbConfig['database'] = '';
            $sysDb = new \App\Core\Database($dbConfig);
            $sysPdo = $sysDb->connection();
            $sysPdo->exec('CREATE DATABASE IF NOT EXISTS `' . $dbName . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        } catch (\Exception $e) {
            $pdo->prepare('UPDATE companies SET status = :status, error_message = :msg WHERE id = :id')
                ->execute([
                    ':status' => 'error',
                    ':msg'    => 'Не удалось создать базу данных: ' . $e->getMessage(),
                    ':id'     => $companyId,
                ]);

            $formError = 'Ошибка создания инфраструктуры. База данных не создана. Запись сохранена со статусом "Ошибка".';

            ob_start();
            require base_path('app/View/pages/superadmin_companies_create.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        try {
            if (!is_dir($storageDir)) {
                mkdir($storageDir, 0755, true);
            }
        } catch (\Exception $e) {
            $pdo->prepare('UPDATE companies SET status = :status, error_message = :msg, db_identifier = :db WHERE id = :id')
                ->execute([
                    ':status' => 'error',
                    ':msg'    => 'БД создана, но не удалось создать storage-папку: ' . $e->getMessage(),
                    ':db'     => $dbName,
                    ':id'     => $companyId,
                ]);

            $formError = 'База данных создана, но не удалось создать storage-папку. Запись сохранена со статусом "Ошибка".';

            ob_start();
            require base_path('app/View/pages/superadmin_companies_create.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $pdo->prepare('UPDATE companies SET status = :status, db_identifier = :db, storage_path = :storage, `key` = :new_key WHERE id = :id')
            ->execute([
                ':status'  => 'active',
                ':db'      => $dbName,
                ':storage' => 'storage/companies/' . $companyId . '/',
                ':new_key' => $newKey,
                ':id'      => $companyId,
            ]);

        header('Location: /superadmin/companies');
        exit;
    } catch (\Exception $e) {
        $formError = 'Не удалось создать экспедитора: ' . $e->getMessage();

        ob_start();
        require base_path('app/View/pages/superadmin_companies_create.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
    }
});

$router->get('/superadmin/companies/{id}', function ($id) use ($config, $db) {
    requireRole('superadmin');
    $pageTitle = 'Карточка компании';
    $pageContext = 'Реестр компаний';

    try {
        $pdo = $db->connection();

        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([(int) $id]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company) {
            $company = null;
            $owner = null;
            $dbError = null;

            ob_start();
            require base_path('app/View/pages/superadmin_company_view.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $pageTitle = 'Компания: ' . $company['name'];

        $ownerStmt = $pdo->prepare(
            "SELECT * FROM company_users WHERE company_id = ? AND role = 'company_owner'"
        );
        $ownerStmt->execute([(int) $id]);
        $owner = $ownerStmt->fetch(PDO::FETCH_ASSOC) ?: null;

        $userStats = ['total' => 0, 'active' => 0, 'blocked' => 0, 'owner_count' => 0, 'logist_count' => 0];
        $ownerCountStmt = $pdo->prepare(
            "SELECT COUNT(*) as owner_total,
                    SUM(CASE WHEN status='active' THEN 1 ELSE 0 END) as owner_active,
                    SUM(CASE WHEN status='blocked' THEN 1 ELSE 0 END) as owner_blocked
             FROM company_users WHERE company_id = ?"
        );
        $ownerCountStmt->execute([(int)$id]);
        $ownerCounts = $ownerCountStmt->fetch(PDO::FETCH_ASSOC);
        $userStats['owner_count'] = (int)($ownerCounts['owner_total'] ?? 0);
        $userStats['total'] += $userStats['owner_count'];
        $userStats['active'] += (int)($ownerCounts['owner_active'] ?? 0);
        $userStats['blocked'] += (int)($ownerCounts['owner_blocked'] ?? 0);

        $dirs = [
            'clients_total' => 0, 'clients_active' => 0, 'clients_archived' => 0,
            'contractors_total' => 0, 'contractors_active' => 0, 'contractors_archived' => 0,
            'drivers_total' => 0, 'drivers_active' => 0, 'drivers_archived' => 0,
            'vehicle_units_total' => 0, 'vehicle_units_active' => 0, 'vehicle_units_archived' => 0,
            'vehicle_sets_total' => 0, 'vehicle_sets_active' => 0, 'vehicle_sets_archived' => 0,
            'driver_vehicle_blocks_total' => 0, 'driver_vehicle_blocks_active' => 0, 'driver_vehicle_blocks_archived' => 0,
            'crews_total' => 0, 'crews_active' => 0, 'crews_archived' => 0,
        ];
        $docStats = ['total' => 0, 'active' => 0];
        $accessStats = ['total' => 0];
        $localDbExists = false;
        $storageExists = false;

        if (!empty($company['db_identifier'])) {
            try {
                $localDbConfig = $config['database'];
                $localDbConfig['database'] = $company['db_identifier'];
                $localDb = new \App\Core\Database($localDbConfig);
                $localPdo = $localDb->connection();
                applyLocalMigrations($localPdo);
                $localDbExists = true;

                $logistCountStmt = $localPdo->prepare(
                    "SELECT COUNT(*) as logist_total,
                            SUM(CASE WHEN status='active' THEN 1 ELSE 0 END) as logist_active,
                            SUM(CASE WHEN status='blocked' THEN 1 ELSE 0 END) as logist_blocked
                     FROM users WHERE role_code IN ('logist', 'senior_logist')"
                );
                $logistCountStmt->execute();
                $logistCounts = $logistCountStmt->fetch(PDO::FETCH_ASSOC);
                $userStats['logist_count'] = (int)($logistCounts['logist_total'] ?? 0);
                $userStats['total'] += $userStats['logist_count'];
                $userStats['active'] += (int)($logistCounts['logist_active'] ?? 0);
                $userStats['blocked'] += (int)($logistCounts['logist_blocked'] ?? 0);

                $dirTables = ['clients', 'contractors', 'drivers', 'vehicle_units', 'vehicle_sets', 'driver_vehicle_blocks', 'crews'];
                foreach ($dirTables as $table) {
                    $dirStmt = $localPdo->prepare(
                        "SELECT COUNT(*) as total,
                                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
                                SUM(CASE WHEN status = 'archived' THEN 1 ELSE 0 END) as archived
                         FROM `{$table}`"
                    );
                    $dirStmt->execute();
                    $row = $dirStmt->fetch(PDO::FETCH_ASSOC);
                    $dirs[$table . '_total'] = (int)($row['total'] ?? 0);
                    $dirs[$table . '_active'] = (int)($row['active'] ?? 0);
                    $dirs[$table . '_archived'] = (int)($row['archived'] ?? 0);
                }

                $docCountStmt = $localPdo->prepare(
                    "SELECT COUNT(*) as total,
                            SUM(CASE WHEN deleted_at IS NULL THEN 1 ELSE 0 END) as active
                     FROM documents"
                );
                $docCountStmt->execute();
                $docRow = $docCountStmt->fetch(PDO::FETCH_ASSOC);
                $docStats['total'] = (int)($docRow['total'] ?? 0);
                $docStats['active'] = (int)($docRow['active'] ?? 0);

                $accessCountStmt = $localPdo->prepare("SELECT COUNT(*) as total FROM entity_access_grants");
                $accessCountStmt->execute();
                $accessStats['total'] = (int)$accessCountStmt->fetchColumn();
            } catch (\Exception $e) {
            }
        }

        if (!empty($company['storage_path'])) {
            $storageExists = is_dir($company['storage_path']);
        }

        $dbError = null;

        ob_start();
        require base_path('app/View/pages/superadmin_company_view.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
    } catch (\Exception $e) {
        $company = null;
        $owner = null;
        $dbError = 'Не удалось загрузить компанию: ' . $e->getMessage();

        ob_start();
        require base_path('app/View/pages/superadmin_company_view.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
    }
});

$router->get('/superadmin/companies/{id}/edit', function ($id) use ($config, $db) {
    requireRole('superadmin');
    $pageTitle = 'Редактировать компанию';
    $pageContext = 'Реестр компаний';

    try {
        $pdo = $db->connection();

        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([(int) $id]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company) {
            $company = null;
            $errors = [];
            $old = [];
            $formError = 'Компания не найдена';
            $owner = null;

            ob_start();
            require base_path('app/View/pages/superadmin_company_edit.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $errors = [];
        $old = $company;
        $formError = null;

        $owner = null; // Director now stored in companies table, not company_users

        ob_start();
        require base_path('app/View/pages/superadmin_company_edit.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
    } catch (\Exception $e) {
        $company = null;
        $contacts = [];
        $errors = [];
        $old = ['contacts' => contractorFormDefaultContacts()];
        $formError = 'Не удалось загрузить компанию: ' . $e->getMessage();

        ob_start();
        require base_path('app/View/pages/superadmin_company_edit.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
    }
});

$router->post('/superadmin/companies/{id}/edit', function ($id) use ($config, $db) {
    requireRole('superadmin');
    $pageTitle = 'Редактировать компанию';
    $pageContext = 'Реестр компаний';

    try {
        $pdo = $db->connection();

        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([(int) $id]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company) {
            $company = null;
            $errors = [];
            $old = $_POST;
            $formError = 'Компания не найдена';
            $owner = null;

            ob_start();
            require base_path('app/View/pages/superadmin_company_edit.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $errors = [];
        $old = $_POST;
        $formError = null;
        $contactPayload = ContractorContactService::normalizeSubmittedContacts($_POST['contacts'] ?? []);
        $submittedContacts = $contactPayload['contacts'];
        if (!empty($contactPayload['errors'])) {
            $errors['contacts'] = $contactPayload['errors'];
        }

        $owner = null;

        $name = trim($_POST['name'] ?? '');
        $inn  = trim($_POST['inn'] ?? '');

        if ($name === '') {
            $errors['name'] = 'Обязательное поле';
        }

        if ($inn === '') {
            $errors['inn'] = 'Обязательное поле';
        } else {
            $dupStmt = $pdo->prepare('SELECT COUNT(*) FROM companies WHERE inn = ? AND id != ?');
            $dupStmt->execute([$inn, (int) $id]);
            if ($dupStmt->fetchColumn() > 0) {
                $errors['inn'] = 'ИНН уже используется';
            }
        }

        if (!empty($errors)) {
            ob_start();
            require base_path('app/View/pages/superadmin_company_edit.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $update = $pdo->prepare(
            'UPDATE companies SET
                name = :name,
                inn = :inn,
                kpp = :kpp,
                ogrn = :ogrn,
                legal_address = :legal_address,
                physical_address = :physical_address,
                director_position = :director_position,
                director_full_name = :director_full_name,
                status = :status,
                comments = :comments
             WHERE id = :id'
        );

        $update->execute([
            ':name'             => $name,
            ':inn'              => $inn,
            ':kpp'              => $_POST['kpp'] ?? null,
            ':ogrn'             => $_POST['ogrn'] ?? null,
            ':legal_address'    => $_POST['legal_address'] ?? null,
            ':physical_address' => $_POST['physical_address'] ?? null,
            ':director_position' => $_POST['director_position'] ?? null,
            ':director_full_name' => $_POST['director_full_name'] ?? null,
            ':status'           => $_POST['status'] ?? $company['status'],
            ':comments'         => $_POST['comments'] ?? null,
            ':id'               => (int) $id,
        ]);

        header('Location: /superadmin/companies/' . $id);
        exit;
    } catch (\Exception $e) {
        $company = $company ?? null;
        $owner = $owner ?? null;
        $errors = [];
        $old = $_POST;
        $formError = 'Ошибка сохранения: ' . $e->getMessage();

        ob_start();
        require base_path('app/View/pages/superadmin_company_edit.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
    }
});

// ============================================================
// SUPERADMIN: Create company owner
// ============================================================

$router->get('/superadmin/companies/{id}/create-owner', function ($id) use ($config, $db) {
    requireRole('superadmin');
    $pageTitle = 'Создать Руководителя';
    $pageContext = 'Реестр компаний';

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([(int) $id]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        $ownerExists = false;
        $existingOwner = null;
        if ($company) {
            $ownerStmt = $pdo->prepare("SELECT * FROM company_users WHERE company_id = ? AND role = 'company_owner' LIMIT 1");
            $ownerStmt->execute([(int) $id]);
            $existingOwner = $ownerStmt->fetch(PDO::FETCH_ASSOC) ?: null;
            $ownerExists = $existingOwner !== null;
        }

        $success = false;
        $errors = [];
        $old = [];
        $formError = null;
        $generatedPassword = generatePassword();
        $createdOwner = null;
        $tempPassword = null;
    } catch (\Exception $e) {
        $company = null;
        $ownerExists = false;
        $existingOwner = null;
        $success = false;
        $errors = [];
        $old = [];
        $formError = 'Ошибка загрузки данных: ' . $e->getMessage();
        $generatedPassword = null;
        $createdOwner = null;
        $tempPassword = null;
    }

    ob_start();
    require base_path('app/View/pages/superadmin_company_owner_create.php');
    $content = ob_get_clean();
    require base_path('app/View/layouts/main.php');
});

$router->post('/superadmin/companies/{id}/create-owner', function ($id) use ($config, $db) {
    requireRole('superadmin');
    $pageTitle = 'Создать Руководителя';
    $pageContext = 'Реестр компаний';

    $errors = [];
    $old = $_POST;
    $formError = null;
    $success = false;
    $generatedPassword = null;
    $createdOwner = null;
    $tempPassword = null;
    $ownerExists = false;
    $existingOwner = null;

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([(int) $id]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        if (!$company) {
            ob_start();
            require base_path('app/View/pages/superadmin_company_owner_create.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $ownerStmt = $pdo->prepare("SELECT * FROM company_users WHERE company_id = ? AND role = 'company_owner' LIMIT 1");
        $ownerStmt->execute([(int) $id]);
        $existingOwner = $ownerStmt->fetch(PDO::FETCH_ASSOC) ?: null;
        $ownerExists = $existingOwner !== null;

        if ($ownerExists) {
            ob_start();
            require base_path('app/View/pages/superadmin_company_owner_create.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $fullName = trim($_POST['full_name'] ?? '');
        $login = trim($_POST['login'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $position = trim($_POST['position'] ?? '');
        $comments = trim($_POST['comments'] ?? '');

        if ($fullName === '') {
            $errors['full_name'] = 'Обязательное поле';
        }

        if ($login === '') {
            $errors['login'] = 'Обязательное поле';
        } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $login)) {
            $errors['login'] = 'Только латинские буквы, цифры и подчёркивание';
        } else {
            $dupStmt = $pdo->prepare('SELECT COUNT(*) FROM company_users WHERE login = ?');
            $dupStmt->execute([$login]);
            if ((int) $dupStmt->fetchColumn() > 0) {
                $errors['login'] = 'Такой логин уже используется';
            }
        }

        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Некорректный email';
        }

        if ($password === '') {
            $password = generatePassword();
        }

        if (!empty($errors)) {
            $generatedPassword = generatePassword();
            ob_start();
            require base_path('app/View/pages/superadmin_company_owner_create.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $insert = $pdo->prepare(
            'INSERT INTO company_users (company_id, full_name, login, email, phone, position, password_hash, role, status, comments)
             VALUES (:company_id, :full_name, :login, :email, :phone, :position, :password_hash, :role, :status, :comments)'
        );
        $insert->execute([
            ':company_id'    => (int) $id,
            ':full_name'     => $fullName,
            ':login'         => $login,
            ':email'         => $email !== '' ? $email : null,
            ':phone'         => $phone !== '' ? $phone : null,
            ':position'      => $position !== '' ? $position : null,
            ':password_hash' => password_hash($password, PASSWORD_BCRYPT),
            ':role'          => 'company_owner',
            ':status'        => 'active',
            ':comments'      => $comments !== '' ? $comments : null,
        ]);

        $createdOwner = [
            'full_name' => $fullName,
            'login'     => $login,
        ];
        $tempPassword = $password;
        $success = true;
    } catch (\Exception $e) {
        $company = $company ?? null;
        $formError = 'Ошибка создания Руководителя: ' . $e->getMessage();
    }

    ob_start();
    require base_path('app/View/pages/superadmin_company_owner_create.php');
    $content = ob_get_clean();
    require base_path('app/View/layouts/main.php');
});

$router->get('/superadmin/companies/{id}/owner', function ($id) use ($config, $db) {
    requireRole('superadmin');
    $pageTitle = 'Руководитель';
    $pageContext = 'Реестр компаний';

    try {
        $pdo = $db->connection();

        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([(int) $id]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company) {
            $company = null;
            $owner = null;
            $dbError = null;
            $passwordReset = false;
            $newPassword = null;

            ob_start();
            require base_path('app/View/pages/superadmin_company_owner_view.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $ownerStmt = $pdo->prepare(
            "SELECT * FROM company_users WHERE company_id = ? AND role = 'company_owner'"
        );
        $ownerStmt->execute([(int) $id]);
        $owner = $ownerStmt->fetch(PDO::FETCH_ASSOC) ?: null;

        $pageTitle = $owner ? 'Руководитель: ' . $owner['full_name'] : 'Руководитель';
        $dbError = null;
        $passwordReset = false;
        $newPassword = null;

        ob_start();
        require base_path('app/View/pages/superadmin_company_owner_view.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
    } catch (\Exception $e) {
        $company = null;
        $owner = null;
        $dbError = 'Не удалось загрузить данные: ' . $e->getMessage();
        $passwordReset = false;
        $newPassword = null;

        ob_start();
        require base_path('app/View/pages/superadmin_company_owner_view.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
    }
});

$router->get('/superadmin/companies/{id}/owner/edit', function ($id) use ($config, $db) {
    requireRole('superadmin');
    $pageTitle = 'Редактировать Руководителя';
    $pageContext = 'Реестр компаний';

    try {
        $pdo = $db->connection();

        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([(int) $id]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company) {
            $company = null;
            $owner = null;
            $errors = [];
            $old = [];
            $formError = 'Компания не найдена';

            ob_start();
            require base_path('app/View/pages/superadmin_company_owner_edit.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $ownerStmt = $pdo->prepare(
            "SELECT * FROM company_users WHERE company_id = ? AND role = 'company_owner'"
        );
        $ownerStmt->execute([(int) $id]);
        $owner = $ownerStmt->fetch(PDO::FETCH_ASSOC) ?: null;

        if (!$owner) {
            $errors = [];
            $old = [];
            $formError = 'Руководитель не создан';

            ob_start();
            require base_path('app/View/pages/superadmin_company_owner_edit.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $errors = [];
        $old = $owner;
        $formError = null;

        ob_start();
        require base_path('app/View/pages/superadmin_company_owner_edit.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
    } catch (\Exception $e) {
        $company = null;
        $owner = null;
        $errors = [];
        $old = [];
        $formError = 'Не удалось загрузить данные: ' . $e->getMessage();

        ob_start();
        require base_path('app/View/pages/superadmin_company_owner_edit.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
    }
});

$router->post('/superadmin/companies/{id}/owner/edit', function ($id) use ($config, $db) {
    requireRole('superadmin');
    $pageTitle = 'Редактировать Руководителя';
    $pageContext = 'Реестр компаний';

    try {
        $pdo = $db->connection();

        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([(int) $id]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company) {
            $company = null;
            $owner = null;
            $errors = [];
            $old = $_POST;
            $formError = 'Компания не найдена';

            ob_start();
            require base_path('app/View/pages/superadmin_company_owner_edit.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $ownerStmt = $pdo->prepare(
            "SELECT * FROM company_users WHERE company_id = ? AND role = 'company_owner'"
        );
        $ownerStmt->execute([(int) $id]);
        $owner = $ownerStmt->fetch(PDO::FETCH_ASSOC) ?: null;

        if (!$owner) {
            $errors = [];
            $old = $_POST;
            $formError = 'Руководитель не создан';

            ob_start();
            require base_path('app/View/pages/superadmin_company_owner_edit.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $errors = [];
        $old = $_POST;
        $formError = null;

        // Prevent self-deactivation
        if ((int)$owner['id'] === (int)$_SESSION['user_id']) {
            $newStatus = $_POST['status'] ?? $owner['status'];
            if ($newStatus !== 'active') {
                $errors['status'] = 'Нельзя деактивировать самого себя';
            }
        }

        // Prevent deactivating last active owner
        $newStatus = $_POST['status'] ?? $owner['status'];
        if ($newStatus !== 'active' && $owner['status'] === 'active') {
            $activeOwnerCount = $pdo->prepare(
                "SELECT COUNT(*) FROM company_users WHERE company_id = ? AND role = 'company_owner' AND status = 'active' AND id != ?"
            );
            $activeOwnerCount->execute([(int)$id, (int)$owner['id']]);
            if ((int)$activeOwnerCount->fetchColumn() === 0) {
                $errors['status'] = 'Нельзя деактивировать последнего активного Руководителя';
            }
        }

        $fullName = trim($_POST['full_name'] ?? '');
        $login = trim($_POST['login'] ?? '');

        if ($fullName === '') {
            $errors['full_name'] = 'Обязательное поле';
        }

        if ($login === '') {
            $errors['login'] = 'Обязательное поле';
        } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $login)) {
            $errors['login'] = 'Только латинские буквы, цифры и подчёркивание';
        } else {
            $dupStmt = $pdo->prepare('SELECT COUNT(*) FROM company_users WHERE login = ? AND id != ?');
            $dupStmt->execute([$login, (int) $owner['id']]);
            if ($dupStmt->fetchColumn() > 0) {
                $errors['login'] = 'Логин уже используется';
            }
        }

        $email = trim($_POST['email'] ?? '');
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Некорректный email';
        }

        if (!empty($errors)) {
            ob_start();
            require base_path('app/View/pages/superadmin_company_owner_edit.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $update = $pdo->prepare(
            'UPDATE company_users SET
                full_name = :full_name,
                login = :login,
                email = :email,
                phone = :phone,
                position = :position,
                status = :status,
                comments = :comments
             WHERE id = :id'
        );

        $update->execute([
            ':full_name' => $fullName,
            ':login'     => $login,
            ':email'     => $email !== '' ? $email : null,
            ':phone'     => trim($_POST['phone'] ?? '') ?: null,
            ':position'  => trim($_POST['position'] ?? '') ?: null,
            ':status'    => $_POST['status'] ?? $owner['status'],
            ':comments'  => trim($_POST['comments'] ?? '') ?: null,
            ':id'        => (int) $owner['id'],
        ]);

        header('Location: /superadmin/companies/' . $id . '/owner?success=1');
        exit;
    } catch (\Exception $e) {
        $company = $company ?? null;
        $owner = $owner ?? null;
        $errors = [];
        $old = $_POST;
        $formError = 'Ошибка сохранения: ' . $e->getMessage();

        ob_start();
        require base_path('app/View/pages/superadmin_company_owner_edit.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
    }
});

$router->post('/superadmin/companies/{id}/owner/reset-password', function ($id) use ($config, $db) {
    requireRole('superadmin');
    $pageTitle = 'Руководитель';
    $pageContext = 'Реестр компаний';

    try {
        $pdo = $db->connection();

        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([(int) $id]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company) {
            $company = null;
            $owner = null;
            $dbError = 'Компания не найдена';
            $passwordReset = false;
            $newPassword = null;

            ob_start();
            require base_path('app/View/pages/superadmin_company_owner_view.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $ownerStmt = $pdo->prepare(
            "SELECT * FROM company_users WHERE company_id = ? AND role = 'company_owner'"
        );
        $ownerStmt->execute([(int) $id]);
        $owner = $ownerStmt->fetch(PDO::FETCH_ASSOC) ?: null;

        if (!$owner) {
            $dbError = null;
            $passwordReset = false;
            $newPassword = null;

            ob_start();
            require base_path('app/View/pages/superadmin_company_owner_view.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $newPassword = generatePassword(10);
        $passwordHash = password_hash($newPassword, PASSWORD_BCRYPT);

        $update = $pdo->prepare('UPDATE company_users SET password_hash = ? WHERE id = ?');
        $update->execute([$passwordHash, (int) $owner['id']]);

        $passwordReset = true;
        $dbError = null;

        ob_start();
        require base_path('app/View/pages/superadmin_company_owner_view.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
    } catch (\Exception $e) {
        $company = $company ?? null;
        $owner = $owner ?? null;
        $dbError = 'Ошибка сброса пароля: ' . $e->getMessage();
        $passwordReset = false;
        $newPassword = null;

        ob_start();
        require base_path('app/View/pages/superadmin_company_owner_view.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
    }
});

$router->get('/company/logists', function () use ($config, $db) {
    requireRole('company_owner');
    $pageTitle = 'Пользователи';
    $pageContext = 'Пользователи › Компания';

    $companyId = (int)(getSessionCompanyId() ?? 0);

    if ($companyId <= 0) {
        $company = null;
        $logists = [];
        $dbError = null;

        ob_start();
        require base_path('app/View/pages/company_logists.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
        return;
    }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company) {
            $company = null;
            $logists = [];
            $dbError = null;

            ob_start();
            require base_path('app/View/pages/company_logists.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $pageContext = 'Пользователи › Компания: ' . $company['name'];

        if ($company['status'] !== 'active') {
            $logists = [];
            $dbError = null;

            ob_start();
            require base_path('app/View/pages/company_logists.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $dbIdentifier = $company['db_identifier'];

        // Создать локальную БД, если не существует
        try {
            $tempPdo = new PDO(
                sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $config['database']['host'] ?? '127.0.0.1', $config['database']['port'] ?? '3306'),
                $config['database']['username'] ?? 'root',
                $config['database']['password'] ?? ''
            );
            $tempPdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbIdentifier}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        } catch (\Exception $e) {
            // Игнорируем ошибку создания БД — основное подключение поймает проблему
        }
        // Release tempPdo to avoid any connection state interference
        $tempPdo = null;

        $localDbConfig = $config['database'];
        $localDbConfig['database'] = $dbIdentifier;
        $localDb = new \App\Core\Database($localDbConfig);
        $localPdo = $localDb->connection();
        applyLocalMigrations($localPdo);

        try {
            $localPdo->query("SELECT 1 FROM users LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $migrationSql = file_get_contents(base_path('database/migrations-local/001_create_company_users.sql'));
            $localPdo->exec($migrationSql);
        }

        try {
            $localPdo->query("SELECT created_by_user_id FROM users LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $localPdo->exec("ALTER TABLE users ADD COLUMN created_by_user_id INT UNSIGNED DEFAULT NULL, ADD COLUMN created_by_role VARCHAR(20) DEFAULT NULL");
        }

        try {
            $localPdo->query("SELECT 1 FROM entity_access_grants LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $migrationSql = file_get_contents(base_path('database/migrations-local/009_create_entity_access_grants.sql'));
            $localPdo->exec($migrationSql);
        }

        try {
            $localPdo->query("SELECT position FROM users LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $migrationSql = file_get_contents(base_path('database/migrations-local/010_add_position_to_users.sql'));
            if ($migrationSql !== false) {
                $localPdo->exec($migrationSql);
            }
        }

        $isLogist = ($_SESSION['role_code'] ?? '') === 'logist';
        if ($isLogist) {
            $userId = (int)$_SESSION['user_id'];
            $logistStmt = $localPdo->prepare(
                "SELECT * FROM users WHERE (created_by_user_id = ? OR id IN (SELECT entity_id FROM entity_access_grants WHERE entity_type = 'logist' AND granted_to_user_id = ? AND access_level = 'view')) ORDER BY created_at DESC"
            );
            $logistStmt->execute([$userId, $userId]);
            $logists = $logistStmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $logistStmt = $localPdo->query("SELECT * FROM users ORDER BY created_at DESC");
            $logists = $logistStmt->fetchAll(PDO::FETCH_ASSOC);
        }
        $dbError = null;
    } catch (\Exception $e) {
        $company = $company ?? null;
        $logists = [];
        $dbError = 'Не удалось подключиться к базе данных компании. Проверьте, что локальная БД создана.';
    }

    ob_start();
    require base_path('app/View/pages/company_logists.php');
    $content = ob_get_clean();
    require base_path('app/View/layouts/main.php');
});

$router->get('/company/logists/create', function () use ($config, $db) {
    requireRole('company_owner');
    $pageTitle = 'Создать пользователя';
    $pageContext = 'Пользователи › Компания';

    $companyId = (int)(getSessionCompanyId() ?? 0);

    if ($companyId <= 0) {
        $company = null;
        $success = false;
        $errors = [];
        $old = ['contacts' => contractorFormDefaultContacts()];
        $formError = null;
        $generatedPassword = null;
        $createdLogist = null;
        $tempPassword = null;

        ob_start();
        require base_path('app/View/pages/company_logists_create.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
        return;
    }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company) {
            $company = null;
            $success = false;
            $errors = [];
            $old = [];
            $formError = null;
            $generatedPassword = null;
            $createdLogist = null;
            $tempPassword = null;

            ob_start();
            require base_path('app/View/pages/company_logists_create.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $pageContext = 'Пользователи › Компания: ' . $company['name'];

        $success = false;
        $errors = [];
        $old = [];
        $formError = null;
        $generatedPassword = generatePassword();
        $createdLogist = null;
        $tempPassword = null;
    } catch (\Exception $e) {
        $company = null;
        $success = false;
        $errors = [];
        $old = ['contacts' => contractorFormDefaultContacts()];
        $formError = 'Ошибка загрузки данных: ' . $e->getMessage();
        $generatedPassword = null;
        $createdLogist = null;
        $tempPassword = null;
    }

    ob_start();
    require base_path('app/View/pages/company_logists_create.php');
    $content = ob_get_clean();
    require base_path('app/View/layouts/main.php');
});

$router->post('/company/logists/create', function () use ($config, $db) {
    requireRole('company_owner');
    $pageTitle = 'Создать пользователя';
    $pageContext = 'Пользователи › Компания';

    $companyId = (int)(getSessionCompanyId() ?? 0);
    $errors = [];
    $old = $_POST;
    $formError = null;
    $success = false;
    $generatedPassword = null;
    $createdLogist = null;
    $tempPassword = null;

    if ($companyId <= 0) {
        $company = null;
        $formError = 'Компания не найдена';

        ob_start();
        require base_path('app/View/pages/company_logists_create.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
        return;
    }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company) {
            $company = null;
            $formError = 'Компания не найдена';

            ob_start();
            require base_path('app/View/pages/company_logists_create.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $pageContext = 'Пользователи › Компания: ' . $company['name'];

        if ($company['status'] !== 'active') {
            $formError = 'Создание пользователей недоступно';

            ob_start();
            require base_path('app/View/pages/company_logists_create.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $dbIdentifier = $company['db_identifier'];

        // Создать локальную БД, если не существует
        try {
            $tempPdo = new PDO(
                sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $config['database']['host'] ?? '127.0.0.1', $config['database']['port'] ?? '3306'),
                $config['database']['username'] ?? 'root',
                $config['database']['password'] ?? ''
            );
            $tempPdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbIdentifier}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        } catch (\Exception $e) {
            // Игнорируем ошибку создания БД — основное подключение поймает проблему
        }
        // Release tempPdo to avoid any connection state interference
        $tempPdo = null;

        $localDbConfig = $config['database'];
        $localDbConfig['database'] = $dbIdentifier;
        $localDb = new \App\Core\Database($localDbConfig);
        $localPdo = $localDb->connection();
        applyLocalMigrations($localPdo);

        try {
            $localPdo->query("SELECT 1 FROM users LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $migrationSql = file_get_contents(base_path('database/migrations-local/001_create_company_users.sql'));
            $localPdo->exec($migrationSql);
        }

        $fullName = trim($_POST['full_name'] ?? '');
        $login = trim($_POST['login'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $roleCode = trim($_POST['role_code'] ?? 'logist');

        if ($fullName === '') {
            $errors['full_name'] = 'Обязательное поле';
        }

        if ($login === '') {
            $errors['login'] = 'Обязательное поле';
        } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $login)) {
            $errors['login'] = 'Только латинские буквы, цифры и подчёркивание';
        }

        if (empty($errors['login'])) {
            $checkStmt = $localPdo->prepare('SELECT COUNT(*) FROM users WHERE login = ?');
            $checkStmt->execute([$login]);
            if ($checkStmt->fetchColumn() > 0) {
                $errors['login'] = 'Логин уже используется в этой компании';
            }
        }

        if ($password === '') {
            $password = generatePassword();
        }

        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Некорректный email';
        }

        // FR19: Validate role against whitelist
        $allowedRoles = ['logist', 'senior_logist'];
        if (!in_array($roleCode, $allowedRoles, true)) {
            $errors['role_code'] = 'Недопустимая роль';
        }

        if (!empty($errors)) {
            $generatedPassword = generatePassword();

            ob_start();
            require base_path('app/View/pages/company_logists_create.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $passwordHash = password_hash($password, PASSWORD_BCRYPT);

        $insert = $localPdo->prepare(
            'INSERT INTO users (full_name, login, email, phone, password_hash, role_code, status, created_by_user_id, created_by_role)
             VALUES (:full_name, :login, :email, :phone, :password_hash, :role_code, :status, :created_by_user_id, :created_by_role)'
        );
        $insert->execute([
            ':full_name'          => $fullName,
            ':login'              => $login,
            ':email'              => $email !== '' ? $email : null,
            ':phone'              => $phone !== '' ? $phone : null,
            ':password_hash'      => $passwordHash,
            ':role_code'          => $roleCode,
            ':status'             => 'active',
            ':created_by_user_id' => (int)$_SESSION['user_id'],
            ':created_by_role'    => $_SESSION['role_code'],
        ]);

        $createdLogist = [
            'full_name' => $fullName,
            'login'     => $login,
            'role_code' => $roleCode,
        ];
        $tempPassword = $password;
        $success = true;
    } catch (\Exception $e) {
        $company = $company ?? null;
        $formError = 'Ошибка создания пользователя: ' . $e->getMessage();
    }

    ob_start();
    require base_path('app/View/pages/company_logists_create.php');
    $content = ob_get_clean();
    require base_path('app/View/layouts/main.php');
});

$router->get('/company/logists/{id}', function ($id) use ($config, $db) {
    requireRole('company_owner');

    $companyId = (int)(getSessionCompanyId() ?? 0);

    if ($companyId <= 0) {
        $company = null;
        $logist = null;
        $dbError = null;
        $passwordReset = false;
        $newPassword = null;

        ob_start();
        require base_path('app/View/pages/company_logist_view.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
        return;
    }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company) {
            $company = null;
            $logist = null;
            $dbError = null;
            $passwordReset = false;
            $newPassword = null;

            ob_start();
            require base_path('app/View/pages/company_logist_view.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $pageTitle = 'Пользователь';
        $pageContext = 'Пользователи › Компания: ' . $company['name'];

        if ($company['status'] !== 'active') {
            $logist = null;
            $dbError = null;
            $passwordReset = false;
            $newPassword = null;

            ob_start();
            require base_path('app/View/pages/company_logist_view.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $dbIdentifier = $company['db_identifier'];

        // Создать локальную БД, если не существует
        try {
            $tempPdo = new PDO(
                sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $config['database']['host'] ?? '127.0.0.1', $config['database']['port'] ?? '3306'),
                $config['database']['username'] ?? 'root',
                $config['database']['password'] ?? ''
            );
            $tempPdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbIdentifier}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        } catch (\Exception $e) {
            // Игнорируем ошибку создания БД — основное подключение поймает проблему
        }
        // Release tempPdo to avoid any connection state interference
        $tempPdo = null;

        $localDbConfig = $config['database'];
        $localDbConfig['database'] = $dbIdentifier;
        $localDb = new \App\Core\Database($localDbConfig);
        $localPdo = $localDb->connection();
                applyLocalMigrations($localPdo);

        try {
            $localPdo->query("SELECT 1 FROM users LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $migrationSql = file_get_contents(base_path('database/migrations-local/001_create_company_users.sql'));
            $localPdo->exec($migrationSql);
        }

        $logistStmt = $localPdo->prepare("SELECT * FROM users WHERE id = ?");
        $logistStmt->execute([(int) $id]);
        $logist = $logistStmt->fetch(PDO::FETCH_ASSOC) ?: null;

        $pageTitle = $logist ? 'Пользователь: ' . $logist['full_name'] : 'Пользователь';
        $dbError = null;
        $passwordReset = false;
        $newPassword = null;

        ob_start();
        require base_path('app/View/pages/company_logist_view.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
    } catch (\Exception $e) {
        $company = $company ?? null;
        $logist = null;
        $dbError = 'Не удалось загрузить данные: ' . $e->getMessage();
        $passwordReset = false;
        $newPassword = null;

        ob_start();
        require base_path('app/View/pages/company_logist_view.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
    }
});

$router->get('/company/logists/{id}/edit', function ($id) use ($config, $db) {
    requireRole('company_owner');

    $companyId = (int)(getSessionCompanyId() ?? 0);

    if ($companyId <= 0) {
        $company = null;
        $logist = null;
        $errors = [];
        $old = [];
        $formError = null;

        ob_start();
        require base_path('app/View/pages/company_logist_edit.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
        return;
    }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company) {
            $company = null;
            $logist = null;
            $errors = [];
            $old = [];
            $formError = null;

            ob_start();
            require base_path('app/View/pages/company_logist_edit.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $pageTitle = 'Редактировать пользователя';
        $pageContext = 'Пользователи › Компания: ' . $company['name'];

        if ($company['status'] !== 'active') {
            $logist = null;
            $errors = [];
            $old = [];
            $formError = null;

            ob_start();
            require base_path('app/View/pages/company_logist_edit.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $dbIdentifier = $company['db_identifier'];

        // Создать локальную БД, если не существует
        try {
            $tempPdo = new PDO(
                sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $config['database']['host'] ?? '127.0.0.1', $config['database']['port'] ?? '3306'),
                $config['database']['username'] ?? 'root',
                $config['database']['password'] ?? ''
            );
            $tempPdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbIdentifier}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        } catch (\Exception $e) {
            // Игнорируем ошибку создания БД — основное подключение поймает проблему
        }
        // Release tempPdo to avoid any connection state interference
        $tempPdo = null;

        $localDbConfig = $config['database'];
        $localDbConfig['database'] = $dbIdentifier;
        $localDb = new \App\Core\Database($localDbConfig);
        $localPdo = $localDb->connection();
                applyLocalMigrations($localPdo);

        try {
            $localPdo->query("SELECT 1 FROM users LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $migrationSql = file_get_contents(base_path('database/migrations-local/001_create_company_users.sql'));
            $localPdo->exec($migrationSql);
        }

        $logistStmt = $localPdo->prepare("SELECT * FROM users WHERE id = ?");
        $logistStmt->execute([(int) $id]);
        $logist = $logistStmt->fetch(PDO::FETCH_ASSOC) ?: null;

        $errors = [];
        $old = $logist ?: [];
        $formError = null;

        ob_start();
        require base_path('app/View/pages/company_logist_edit.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
    } catch (\Exception $e) {
        $company = $company ?? null;
        $logist = null;
        $errors = [];
        $old = [];
        $formError = 'Не удалось загрузить данные: ' . $e->getMessage();

        ob_start();
        require base_path('app/View/pages/company_logist_edit.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
    }
});

$router->post('/company/logists/{id}/edit', function ($id) use ($config, $db) {
    requireRole('company_owner');

    $companyId = (int)(getSessionCompanyId() ?? 0);

    if ($companyId <= 0) {
        $company = null;
        $logist = null;
        $errors = [];
        $old = $_POST;
        $formError = 'Компания не найдена';

        ob_start();
        require base_path('app/View/pages/company_logist_edit.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
        return;
    }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company) {
            $company = null;
            $logist = null;
            $errors = [];
            $old = $_POST;
            $formError = 'Компания не найдена';

            ob_start();
            require base_path('app/View/pages/company_logist_edit.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $pageTitle = 'Редактировать пользователя';
        $pageContext = 'Пользователи › Компания: ' . $company['name'];

        if ($company['status'] !== 'active') {
            $logist = null;
            $errors = [];
            $old = $_POST;
            $formError = 'Редактирование пользователей недоступно';

            ob_start();
            require base_path('app/View/pages/company_logist_edit.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $dbIdentifier = $company['db_identifier'];

        // Создать локальную БД, если не существует
        try {
            $tempPdo = new PDO(
                sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $config['database']['host'] ?? '127.0.0.1', $config['database']['port'] ?? '3306'),
                $config['database']['username'] ?? 'root',
                $config['database']['password'] ?? ''
            );
            $tempPdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbIdentifier}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        } catch (\Exception $e) {
            // Игнорируем ошибку создания БД — основное подключение поймает проблему
        }
        // Release tempPdo to avoid any connection state interference
        $tempPdo = null;

        $localDbConfig = $config['database'];
        $localDbConfig['database'] = $dbIdentifier;
        $localDb = new \App\Core\Database($localDbConfig);
        $localPdo = $localDb->connection();
                applyLocalMigrations($localPdo);

        try {
            $localPdo->query("SELECT 1 FROM users LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $migrationSql = file_get_contents(base_path('database/migrations-local/001_create_company_users.sql'));
            $localPdo->exec($migrationSql);
        }

        $logistStmt = $localPdo->prepare("SELECT * FROM users WHERE id = ?");
        $logistStmt->execute([(int) $id]);
        $logist = $logistStmt->fetch(PDO::FETCH_ASSOC) ?: null;

        if (!$logist) {
            $errors = [];
            $old = $_POST;
            $formError = 'Пользователь не найден';

            ob_start();
            require base_path('app/View/pages/company_logist_edit.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $errors = [];
        $old = $_POST;
        $formError = null;

        $fullName = trim($_POST['full_name'] ?? '');
        $login = trim($_POST['login'] ?? '');
        $roleCode = trim($_POST['role_code'] ?? $logist['role_code']);

        if ($fullName === '') {
            $errors['full_name'] = 'Обязательное поле';
        }

        if ($login === '') {
            $errors['login'] = 'Обязательное поле';
        } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $login)) {
            $errors['login'] = 'Только латинские буквы, цифры и подчёркивание';
        } else {
            $dupStmt = $localPdo->prepare("SELECT COUNT(*) FROM users WHERE login = ? AND id != ?");
            $dupStmt->execute([$login, (int) $id]);
            if ($dupStmt->fetchColumn() > 0) {
                $errors['login'] = 'Логин уже используется в этой компании';
            }
        }

        // FR21: Validate role
        $allowedRoles = ['logist', 'senior_logist'];
        if (!in_array($roleCode, $allowedRoles, true)) {
            $errors['role_code'] = 'Недопустимая роль';
        }

        $email = trim($_POST['email'] ?? '');
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Некорректный email';
        }

        if (!empty($errors)) {
            ob_start();
            require base_path('app/View/pages/company_logist_edit.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $update = $localPdo->prepare(
            "UPDATE users SET
                full_name = :full_name,
                login = :login,
                email = :email,
                phone = :phone,
                role_code = :role_code,
                status = :status
             WHERE id = :id"
        );

        $update->execute([
            ':full_name' => $fullName,
            ':login'     => $login,
            ':email'     => $email !== '' ? $email : null,
            ':phone'     => trim($_POST['phone'] ?? '') ?: null,
            ':role_code' => $roleCode,
            ':status'    => $_POST['status'] ?? $logist['status'],
            ':id'        => (int) $id,
        ]);

        header('Location: /company/logists/' . $id);
        exit;
    } catch (\Exception $e) {
        $company = $company ?? null;
        $logist = $logist ?? null;
        $errors = [];
        $old = $_POST;
        $formError = 'Ошибка сохранения: ' . $e->getMessage();

        ob_start();
        require base_path('app/View/pages/company_logist_edit.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
    }
});

$router->post('/company/logists/{id}/reset-password', function ($id) use ($config, $db) {
    requireRole('company_owner');

    $companyId = (int)(getSessionCompanyId() ?? 0);

    if ($companyId <= 0) {
        $company = null;
        $logist = null;
        $dbError = 'Компания не найдена';
        $passwordReset = false;
        $newPassword = null;

        ob_start();
        require base_path('app/View/pages/company_logist_view.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
        return;
    }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company) {
            $company = null;
            $logist = null;
            $dbError = 'Компания не найдена';
            $passwordReset = false;
            $newPassword = null;

            ob_start();
            require base_path('app/View/pages/company_logist_view.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $pageTitle = 'Пользователь';
        $pageContext = 'Пользователи › Компания: ' . $company['name'];

        if ($company['status'] !== 'active') {
            $logist = null;
            $dbError = null;
            $passwordReset = false;
            $newPassword = null;

            ob_start();
            require base_path('app/View/pages/company_logist_view.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $dbIdentifier = $company['db_identifier'];

        // Создать локальную БД, если не существует
        try {
            $tempPdo = new PDO(
                sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $config['database']['host'] ?? '127.0.0.1', $config['database']['port'] ?? '3306'),
                $config['database']['username'] ?? 'root',
                $config['database']['password'] ?? ''
            );
            $tempPdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbIdentifier}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        } catch (\Exception $e) {
            // Игнорируем ошибку создания БД — основное подключение поймает проблему
        }
        // Release tempPdo to avoid any connection state interference
        $tempPdo = null;

        $localDbConfig = $config['database'];
        $localDbConfig['database'] = $dbIdentifier;
        $localDb = new \App\Core\Database($localDbConfig);
        $localPdo = $localDb->connection();
                applyLocalMigrations($localPdo);

        try {
            $localPdo->query("SELECT 1 FROM users LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $migrationSql = file_get_contents(base_path('database/migrations-local/001_create_company_users.sql'));
            $localPdo->exec($migrationSql);
        }

        $logistStmt = $localPdo->prepare("SELECT * FROM users WHERE id = ?");
        $logistStmt->execute([(int) $id]);
        $logist = $logistStmt->fetch(PDO::FETCH_ASSOC) ?: null;

        if (!$logist) {
            $pageTitle = $logist ? 'Пользователь: ' . $logist['full_name'] : 'Пользователь';
            $dbError = null;
            $passwordReset = false;
            $newPassword = null;

            ob_start();
            require base_path('app/View/pages/company_logist_view.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $pageTitle = 'Пользователь: ' . $logist['full_name'];

        $newPassword = generatePassword(10);
        $passwordHash = password_hash($newPassword, PASSWORD_BCRYPT);

        $update = $localPdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        $update->execute([$passwordHash, (int) $logist['id']]);

        $passwordReset = true;
        $dbError = null;

        ob_start();
        require base_path('app/View/pages/company_logist_view.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
    } catch (\Exception $e) {
        $company = $company ?? null;
        $logist = $logist ?? null;
        $dbError = 'Ошибка сброса пароля: ' . $e->getMessage();
        $passwordReset = false;
        $newPassword = null;

        ob_start();
        require base_path('app/View/pages/company_logist_view.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
    }
});

$router->post('/company/logists/{id}/archive', function ($id) use ($config, $db) {
    requireRole('company_owner');

    $companyId = (int)(getSessionCompanyId() ?? 0);

    if ($companyId <= 0) {
        header('Location: /company/logists');
        exit;
    }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company || $company['status'] !== 'active') {
            header('Location: /company/logists');
            exit;
        }

        $dbIdentifier = $company['db_identifier'];

        // Создать локальную БД, если не существует
        try {
            $tempPdo = new PDO(
                sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $config['database']['host'] ?? '127.0.0.1', $config['database']['port'] ?? '3306'),
                $config['database']['username'] ?? 'root',
                $config['database']['password'] ?? ''
            );
            $tempPdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbIdentifier}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        } catch (\Exception $e) {
            // Игнорируем ошибку создания БД — основное подключение поймает проблему
        }
        // Release tempPdo to avoid any connection state interference
        $tempPdo = null;

        $localDbConfig = $config['database'];
        $localDbConfig['database'] = $dbIdentifier;
        $localDb = new \App\Core\Database($localDbConfig);
        $localPdo = $localDb->connection();
                applyLocalMigrations($localPdo);

        try {
            $localPdo->query("SELECT 1 FROM users LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $migrationSql = file_get_contents(base_path('database/migrations-local/001_create_company_users.sql'));
            $localPdo->exec($migrationSql);
        }

        $update = $localPdo->prepare("UPDATE users SET status = 'archived' WHERE id = ?");
        $update->execute([(int) $id]);

        header('Location: /company/logists');
        exit;
    } catch (\Exception $e) {
        header('Location: /company/logists');
        exit;
    }
});

$router->get('/company/clients', function () use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);
    $pageTitle = 'Клиенты';
    $pageContext = 'Клиенты › Компания';

    $companyId = (int)(getSessionCompanyId() ?? 0);

    if ($companyId <= 0) {
        $company = null;
        $clients = [];
        $dbError = null;

        ob_start();
        require base_path('app/View/pages/company_clients.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
        return;
    }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company) {
            $company = null;
            $clients = [];
            $dbError = null;

            ob_start();
            require base_path('app/View/pages/company_clients.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $pageContext = 'Клиенты › Компания: ' . $company['name'];

        if ($company['status'] !== 'active') {
            $clients = [];
            $dbError = null;

            ob_start();
            require base_path('app/View/pages/company_clients.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $dbIdentifier = $company['db_identifier'];
        $localDbConfig = $config['database'];
        $localDbConfig['database'] = $dbIdentifier;
        $localDb = new \App\Core\Database($localDbConfig);
        $localPdo = $localDb->connection();
                applyLocalMigrations($localPdo);

        try {
            $localPdo->query("SELECT 1 FROM clients LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $migrationSql = file_get_contents(base_path('database/migrations-local/002_create_company_clients.sql'));
            $localPdo->exec($migrationSql);
        }

        try {
            $localPdo->query("SELECT created_by_user_id FROM clients LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $localPdo->exec("ALTER TABLE clients ADD COLUMN created_by_user_id INT UNSIGNED DEFAULT NULL, ADD COLUMN created_by_role VARCHAR(20) DEFAULT NULL");
        }

        try {
            $localPdo->query("SELECT 1 FROM entity_access_grants LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $migrationSql = file_get_contents(base_path('database/migrations-local/009_create_entity_access_grants.sql'));
            $localPdo->exec($migrationSql);
        }

        $isLogist = ($_SESSION['role_code'] ?? '') === 'logist';
        if ($isLogist) {
            $userId = (int)$_SESSION['user_id'];
            $clientStmt = $localPdo->prepare(
                "SELECT * FROM clients WHERE (created_by_user_id = ? OR id IN (SELECT entity_id FROM entity_access_grants WHERE entity_type = 'client' AND granted_to_user_id = ? AND access_level = 'view')) ORDER BY created_at DESC"
            );
            $clientStmt->execute([$userId, $userId]);
            $clients = $clientStmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $clientStmt = $localPdo->query("SELECT * FROM clients ORDER BY created_at DESC");
            $clients = $clientStmt->fetchAll(PDO::FETCH_ASSOC);
        }
        $dbError = null;
    } catch (\Exception $e) {
        $company = $company ?? null;
        $clients = [];
        $dbError = 'Не удалось подключиться к базе данных компании.';
    }

    ob_start();
    require base_path('app/View/pages/company_clients.php');
    $content = ob_get_clean();
    require base_path('app/View/layouts/main.php');
});

$router->get('/company/clients/create', function () use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);
    $pageTitle = 'Создать клиента';
    $pageContext = 'Клиенты › Компания';
    $isModalRequest = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest';

    $companyId = (int)(getSessionCompanyId() ?? 0);

    if ($companyId <= 0) {
        $company = null;
        $success = false;
        $errors = [];
        $old = [];
        $formError = null;
        $createdClient = null;

        ob_start();
        require base_path('app/View/pages/company_clients_create.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
        return;
    }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company) {
            $company = null;
            $success = false;
            $errors = [];
            $old = [];
            $formError = null;
            $createdClient = null;

            ob_start();
            require base_path('app/View/pages/company_clients_create.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $pageContext = 'Клиенты › Компания: ' . $company['name'];

        $success = false;
        $errors = [];
        $old = [];
        $formError = null;
        $createdClient = null;
        $docTypes = [];

        if ($company['status'] === 'active') {
            try {
                $dbIdentifier = $company['db_identifier'];
                $localDbConfig = $config['database']; $localDbConfig['database'] = $dbIdentifier;
                $localDb = new \App\Core\Database($localDbConfig); $localPdo = $localDb->connection();
                applyLocalMigrations($localPdo);

                try { $localPdo->query("SELECT 1 FROM document_types LIMIT 1")->fetch(); }
                catch (\Exception $e) {
                    $localPdo->exec(file_get_contents(base_path('database/migrations-local/024_create_document_types.sql')));
                    $localPdo->exec(file_get_contents(base_path('database/migrations-local/025_add_document_type_id.sql')));
                }

                $docTypes = $localPdo->query("SELECT id, name, code, entity_type FROM document_types WHERE entity_type = 'client' OR entity_type IS NULL ORDER BY sort_order, name")->fetchAll(PDO::FETCH_ASSOC);
            } catch (\Exception $e) {
                $docTypes = [];
            }
        }
    } catch (\Exception $e) {
        $company = null;
        $success = false;
        $errors = [];
        $old = [];
        $formError = 'Ошибка загрузки данных: ' . $e->getMessage();
        $createdClient = null;
    }

    if ($isModalRequest) {
        header('Content-Type: text/html; charset=utf-8');
        $leEntityType = 'client';
        $leFormAction = '/company/clients/create';
        $leFormId = 'le-create-form';
        $leOld = $old ?? [];
        $leErrors = $errors ?? [];
        $leFormError = $formError;
        $leDocTypes = $docTypes ?? [];
        $leContactValues = $contactValues ?? [];
        $leContactErrors = [];
        ob_start();
        require base_path('app/View/partials/legal_entity_create_form.php');
        echo ob_get_clean();
        exit;
    }

    ob_start();
    require base_path('app/View/pages/company_clients_create.php');
    $content = ob_get_clean();
    require base_path('app/View/layouts/main.php');
});

$router->post('/company/clients/create', function () use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);
    $pageTitle = 'Создать клиента';
    $pageContext = 'Клиенты › Компания';
    $isModalRequest = !empty($_POST['is_modal']);

    $companyId = (int)(getSessionCompanyId() ?? 0);
    $errors = [];
    $old = $_POST;
    $formError = null;
    $success = false;
    $createdClient = null;
    $docErrors = [];
    $uploadedDocs = [];

    if ($companyId <= 0) {
        $company = null;
        $formError = 'Компания не найдена';

        ob_start();
        require base_path('app/View/pages/company_clients_create.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
        return;
    }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company) {
            $company = null;
            $formError = 'Компания не найдена';

            ob_start();
            require base_path('app/View/pages/company_clients_create.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $pageContext = 'Клиенты › Компания: ' . $company['name'];

        if ($company['status'] !== 'active') {
            $formError = 'Создание клиентов недоступно';

            ob_start();
            require base_path('app/View/pages/company_clients_create.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $dbIdentifier = $company['db_identifier'];
        $localDbConfig = $config['database'];
        $localDbConfig['database'] = $dbIdentifier;
        $localDb = new \App\Core\Database($localDbConfig);
        $localPdo = $localDb->connection();
                applyLocalMigrations($localPdo);

        try {
            $localPdo->query("SELECT 1 FROM clients LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $migrationSql = file_get_contents(base_path('database/migrations-local/002_create_company_clients.sql'));
            $localPdo->exec($migrationSql);
        }

        if (isPostTruncated()) {
            $formError = 'Общий размер отправки превышает серверный лимит. Для ERP требуется настройка post_max_size не менее 100M. Уменьшите количество файлов или обратитесь к администратору.';
            ob_start();
            require base_path('app/View/pages/company_clients_create.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $name = trim($_POST['name'] ?? '');
        $inn = trim($_POST['inn'] ?? '');
        $kpp = trim($_POST['kpp'] ?? '');
        $ogrn = trim($_POST['ogrn'] ?? '');
        $legalAddress = trim($_POST['legal_address'] ?? '');
        $physicalAddress = trim($_POST['physical_address'] ?? '');
        $comments = trim($_POST['comments'] ?? '');
        $directorFullName = trim($_POST['director_full_name'] ?? '');
        $directorPosition = trim($_POST['director_position'] ?? '');

        $contactPayload = ClientContactService::normalizeSubmittedContacts($_POST['contacts'] ?? []);
        $submittedContacts = $contactPayload['contacts'];
        if (!empty($contactPayload['errors'])) {
            $errors['contacts'] = $contactPayload['errors'];
        }

        if ($name === '') {
            $errors['name'] = 'Обязательное поле';
        }

        // Валидация ИНН
        if ($inn === '') {
            $errors['inn'] = 'Укажите ИНН';
        } elseif (!preg_match('/^\d+$/', $inn) || (strlen($inn) !== 10 && strlen($inn) !== 12)) {
            $errors['inn'] = 'ИНН должен содержать 10 или 12 цифр';
        }

        // Валидация КПП (если заполнен)
        if ($kpp !== '' && !preg_match('/^\d{9}$/', $kpp)) {
            $errors['kpp'] = 'КПП должен содержать 9 цифр';
        }

        // Валидация ОГРН (если заполнен)
        if ($ogrn !== '' && (!preg_match('/^\d+$/', $ogrn) || (strlen($ogrn) !== 13 && strlen($ogrn) !== 15))) {
            $errors['ogrn'] = 'ОГРН/ОГРНИП должен содержать 13 или 15 цифр';
        }

        // Валидация банковских полей (если заполнены)
        if (($_POST['bank_account'] ?? '') !== '' && !preg_match('/^\d{20}$/', $_POST['bank_account'])) {
            $errors['bank_account'] = 'Расчётный счёт должен содержать 20 цифр';
        }
        if (($_POST['bank_bik'] ?? '') !== '' && !preg_match('/^\d{9}$/', $_POST['bank_bik'])) {
            $errors['bank_bik'] = 'БИК должен содержать 9 цифр';
        }
        if (($_POST['bank_corr_account'] ?? '') !== '' && !preg_match('/^\d{20}$/', $_POST['bank_corr_account'])) {
            $errors['bank_corr_account'] = 'Корр. счёт должен содержать 20 цифр';
        }

        if ($inn !== '') {
            $checkStmt = $localPdo->prepare('SELECT COUNT(*) FROM clients WHERE inn = ?');
            $checkStmt->execute([$inn]);
            if ($checkStmt->fetchColumn() > 0) {
                $errors['inn'] = 'ИНН уже используется в этой компании';
            }
        }

        if (!empty($errors)) {
            ob_start();
            require base_path('app/View/pages/company_clients_create.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        // Check total upload size before INSERT
        $totalSizeError = validateTotalUploadSize();
        if ($totalSizeError !== '') {
            $formError = $totalSizeError;
            ob_start();
            require base_path('app/View/pages/company_clients_create.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        // Pre-validate custom document titles (before INSERT)
        if (!empty($_FILES['custom_doc_file']['name']) && is_array($_FILES['custom_doc_file']['name'])) {
            foreach ($_FILES['custom_doc_file']['name'] as $idx => $origName) {
                $fe = $_FILES['custom_doc_file']['error'][$idx] ?? UPLOAD_ERR_NO_FILE;
                if ($fe !== UPLOAD_ERR_OK || trim((string)$origName) === '') {
                    continue;
                }
                $customTypeNew = trim($_POST['custom_doc_type_new'][$idx] ?? '');
                $customTypeSelect = trim($_POST['custom_doc_type'][$idx] ?? '');
                if ($customTypeNew === '' && $customTypeSelect === '') {
                    $formError = 'Введите название документа';
                    ob_start();
                    require base_path('app/View/pages/company_clients_create.php');
                    $content = ob_get_clean();
                    require base_path('app/View/layouts/main.php');
                    return;
                }
            }
        }

        $insert = $localPdo->prepare(
            'INSERT INTO clients (name, inn, kpp, ogrn, entity_type, legal_address, physical_address,
             bank_account, bank_name, bank_bik, bank_corr_account,
             director_full_name, director_position,
             status, comments, created_by_user_id, created_by_role,
             updated_by_user_id, updated_by_role)
             VALUES (:name, :inn, :kpp, :ogrn, :entity_type, :legal_address, :physical_address,
             :bank_account, :bank_name, :bank_bik, :bank_corr_account,
             :director_full_name, :director_position,
             :status, :comments, :created_by_user_id, :created_by_role,
             :updated_by_user_id, :updated_by_role)'
        );
        $insert->execute([
            ':name'               => $name,
            ':inn'                => $inn,
            ':kpp'                => $kpp !== '' ? $kpp : null,
            ':ogrn'               => $ogrn !== '' ? $ogrn : null,
            ':entity_type'        => ($_POST['entity_type'] ?? '') !== '' ? $_POST['entity_type'] : null,
            ':legal_address'      => $legalAddress !== '' ? $legalAddress : null,
            ':physical_address'   => $physicalAddress !== '' ? $physicalAddress : null,
            ':bank_account'       => ($_POST['bank_account'] ?? '') !== '' ? $_POST['bank_account'] : null,
            ':bank_name'          => ($_POST['bank_name'] ?? '') !== '' ? $_POST['bank_name'] : null,
            ':bank_bik'           => ($_POST['bank_bik'] ?? '') !== '' ? $_POST['bank_bik'] : null,
            ':bank_corr_account'  => ($_POST['bank_corr_account'] ?? '') !== '' ? $_POST['bank_corr_account'] : null,
            ':director_full_name' => $directorFullName !== '' ? $directorFullName : null,
            ':director_position'  => $directorPosition !== '' ? $directorPosition : null,
            ':status'             => 'active',
            ':comments'           => $comments !== '' ? $comments : null,
            ':created_by_user_id' => (int)$_SESSION['user_id'],
            ':created_by_role'    => $_SESSION['role_code'],
            ':updated_by_user_id' => (int)$_SESSION['user_id'],
            ':updated_by_role'    => $_SESSION['role_code'],
        ]);

        $newClientId = (int)$localPdo->lastInsertId();
        ClientContactService::replaceForClient(
            $localPdo,
            $newClientId,
            $submittedContacts,
            (int) $_SESSION['user_id'],
            (string) ($_SESSION['role_code'] ?? '')
        );

        $createdClient = [
            'id'          => $newClientId,
            'name'        => $name,
            'inn'         => $inn,
            'entity_type' => ($_POST['entity_type'] ?? '') !== '' ? $_POST['entity_type'] : null,
        ];

        // -- Process document uploads during creation --
        $docErrors = []; $uploadedDocs = []; $entityType = 'client';
        try { $localPdo->query("SELECT 1 FROM documents LIMIT 1")->fetch(); } catch (\Exception $e) { $localPdo->exec(file_get_contents(base_path('database/migrations-local/007_create_company_documents.sql'))); }
        try { $localPdo->query("SELECT 1 FROM document_types LIMIT 1")->fetch(); } catch (\Exception $e) { $localPdo->exec(file_get_contents(base_path('database/migrations-local/024_create_document_types.sql'))); $localPdo->exec(file_get_contents(base_path('database/migrations-local/025_add_document_type_id.sql'))); }
        $allowedExt = ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'doc', 'docx', 'xls', 'xlsx']; $maxSize = 20 * 1024 * 1024;
        if (!empty($_FILES['predef_doc']['name']) && is_array($_FILES['predef_doc']['name'])) {
            foreach ($_FILES['predef_doc']['name'] as $code => $origName) {
                $fe = $_FILES['predef_doc']['error'][$code] ?? UPLOAD_ERR_NO_FILE; if ($fe !== UPLOAD_ERR_OK || trim((string)$origName) === '') continue;
                $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION)); $fs = $_FILES['predef_doc']['size'][$code];
                if (!in_array($ext, $allowedExt, true)) { $docErrors[] = 'Предопределённый документ «' . ($_POST['predef_doc_type'][$code] ?? $code) . '»: недопустимый формат'; continue; }
                if ($fs > $maxSize) { $docErrors[] = 'Предопределённый документ «' . ($_POST['predef_doc_type'][$code] ?? $code) . '»: размер > 20 МБ'; continue; }
                if (strpos($origName, '../') !== false || strpos($origName, '..\\') !== false || strpos($origName, '/') !== false || strpos($origName, '\\') !== false) { $docErrors[] = 'Предопределённый документ «' . ($_POST['predef_doc_type'][$code] ?? $code) . '»: недопустимое имя'; continue; }
                try {
                    $storedName = uniqid('doc_', true) . '.' . $ext; $relativeDir = 'companies/' . $companyId . '/documents/client/' . $newClientId; $absoluteDir = storage_path($relativeDir);
                    if (!is_dir($absoluteDir)) mkdir($absoluteDir, 0755, true);
                    if (!move_uploaded_file($_FILES['predef_doc']['tmp_name'][$code], $absoluteDir . DIRECTORY_SEPARATOR . $storedName)) { $docErrors[] = 'Предопределённый документ «' . ($_POST['predef_doc_type'][$code] ?? $code) . '»: не удалось сохранить'; continue; }
                    $docTypeName = $_POST['predef_doc_type'][$code] ?? ''; $mime = $_FILES['predef_doc']['type'][$code]; $dtId = null;
                    if ($docTypeName !== '') { $dts = $localPdo->prepare("SELECT id FROM document_types WHERE name = ? AND entity_type = ? LIMIT 1"); $dts->execute([$docTypeName, $entityType]); $dtId = $dts->fetchColumn() ?: null; }
                    $ins = $localPdo->prepare('INSERT INTO documents (entity_type, entity_id, document_type, document_type_id, original_name, stored_name, relative_path, mime_type, file_size, status, uploaded_by_user_id, uploaded_by_role, created_by_user_id, created_by_role) VALUES (:et, :eid, :dtype, :dtid, :oname, :sname, :rpath, :mime, :fsize, :status, :uid, :role, :cuid, :crole)');
                    $ins->execute([':et' => $entityType, ':eid' => $newClientId, ':dtype' => $docTypeName ?: null, ':dtid' => $dtId, ':oname' => $origName, ':sname' => $storedName, ':rpath' => $relativeDir . '/' . $storedName, ':mime' => $mime, ':fsize' => $fs, ':status' => 'uploaded', ':uid' => (int)$_SESSION['user_id'], ':role' => $_SESSION['role_code'], ':cuid' => (int)$_SESSION['user_id'], ':crole' => $_SESSION['role_code']]);
                    $uploadedDocs[] = $docTypeName . ' (' . $origName . ')';
                } catch (\Exception $ex) { $docErrors[] = 'Предопределённый документ «' . ($_POST['predef_doc_type'][$code] ?? $code) . '»: ошибка сохранения'; }
            }
        }
        if (!empty($_FILES['custom_doc_file']['name']) && is_array($_FILES['custom_doc_file']['name'])) {
            foreach ($_FILES['custom_doc_file']['name'] as $idx => $origName) {
                $fe = $_FILES['custom_doc_file']['error'][$idx] ?? UPLOAD_ERR_NO_FILE; if ($fe !== UPLOAD_ERR_OK || trim((string)$origName) === '') continue;
                $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION)); $fs = $_FILES['custom_doc_file']['size'][$idx];
                if (!in_array($ext, $allowedExt, true)) { $docErrors[] = 'Произвольный документ #' . ($idx + 1) . ': недопустимый формат'; continue; }
                if ($fs > $maxSize) { $docErrors[] = 'Произвольный документ #' . ($idx + 1) . ': размер > 20 МБ'; continue; }
                if (strpos($origName, '../') !== false || strpos($origName, '..\\') !== false || strpos($origName, '/') !== false || strpos($origName, '\\') !== false) { $docErrors[] = 'Произвольный документ #' . ($idx + 1) . ': недопустимое имя'; continue; }
                $customTypeNew = trim($_POST['custom_doc_type_new'][$idx] ?? ''); $customTypeSelect = trim($_POST['custom_doc_type'][$idx] ?? ''); $docTypeName = $customTypeNew !== '' ? $customTypeNew : $customTypeSelect; $dtId = null;
                if ($docTypeName === '') { $docErrors[] = 'Произвольный документ #' . ($idx + 1) . ': введите название документа'; continue; }
                if ($customTypeNew !== '') { try { $idts = $localPdo->prepare("INSERT IGNORE INTO document_types (name, entity_type, category, created_by_user_id, created_by_role) VALUES (:name, :et, 'custom', :uid, :role)"); $idts->execute([':name' => $customTypeNew, ':et' => $entityType, ':uid' => (int)$_SESSION['user_id'], ':role' => $_SESSION['role_code']]); $dtId = $localPdo->lastInsertId(); if (!$dtId) { $g = $localPdo->prepare("SELECT id FROM document_types WHERE name = ? AND entity_type = ? LIMIT 1"); $g->execute([$customTypeNew, $entityType]); $dtId = $g->fetchColumn() ?: null; } } catch (\Exception $ex) { $docErrors[] = 'Произвольный документ #' . ($idx + 1) . ': ошибка создания типа документа'; } }
                elseif ($customTypeSelect !== '') { $g = $localPdo->prepare("SELECT id FROM document_types WHERE name = ? AND entity_type = ? LIMIT 1"); $g->execute([$customTypeSelect, $entityType]); $dtId = $g->fetchColumn() ?: null; }
                try {
                    $storedName = uniqid('doc_', true) . '.' . $ext; $relativeDir = 'companies/' . $companyId . '/documents/client/' . $newClientId; $absoluteDir = storage_path($relativeDir);
                    if (!is_dir($absoluteDir)) mkdir($absoluteDir, 0755, true);
                    if (!move_uploaded_file($_FILES['custom_doc_file']['tmp_name'][$idx], $absoluteDir . DIRECTORY_SEPARATOR . $storedName)) { $docErrors[] = 'Произвольный документ #' . ($idx + 1) . ': не удалось сохранить'; continue; }
                    $mime = $_FILES['custom_doc_file']['type'][$idx];
                    $ins = $localPdo->prepare('INSERT INTO documents (entity_type, entity_id, document_type, document_type_id, original_name, stored_name, relative_path, mime_type, file_size, status, uploaded_by_user_id, uploaded_by_role, created_by_user_id, created_by_role) VALUES (:et, :eid, :dtype, :dtid, :oname, :sname, :rpath, :mime, :fsize, :status, :uid, :role, :cuid, :crole)');
                    $ins->execute([':et' => $entityType, ':eid' => $newClientId, ':dtype' => $docTypeName ?: null, ':dtid' => $dtId, ':oname' => $origName, ':sname' => $storedName, ':rpath' => $relativeDir . '/' . $storedName, ':mime' => $mime, ':fsize' => $fs, ':status' => 'uploaded', ':uid' => (int)$_SESSION['user_id'], ':role' => $_SESSION['role_code'], ':cuid' => (int)$_SESSION['user_id'], ':crole' => $_SESSION['role_code']]);
                    $uploadedDocs[] = $docTypeName . ' (' . $origName . ')';
                } catch (\Exception $ex) { $docErrors[] = 'Произвольный документ #' . ($idx + 1) . ': ошибка сохранения'; }
            }
        }

        $success = true;
    } catch (\Exception $e) {
        $company = $company ?? null;
        $formError = 'Ошибка создания клиента: ' . $e->getMessage();
    }

    if ($isModalRequest) {
        header('Content-Type: text/html; charset=utf-8');
        if ($success) {
            echo '<div data-le-create-success="1"></div>';
        } else {
            if (empty($docTypes) && !empty($localPdo)) {
                try { $docTypes = $localPdo->query("SELECT id, name, code, entity_type FROM document_types WHERE entity_type = 'client' OR entity_type IS NULL ORDER BY sort_order, name")->fetchAll(PDO::FETCH_ASSOC); } catch (\Exception $e) { $docTypes = []; }
            }
            $leEntityType = 'client';
            $leFormAction = '/company/clients/create';
            $leFormId = 'le-create-form';
            $leOld = $old;
            $leErrors = $errors;
            $leFormError = $formError;
            $leDocTypes = $docTypes ?? [];
            $leContactValues = $submittedContacts ?? ($old['contacts'] ?? []);
            $leContactErrors = $errors['contacts'] ?? [];
            ob_start();
            require base_path('app/View/partials/legal_entity_create_form.php');
            echo ob_get_clean();
        }
        exit;
    }

    ob_start();
    require base_path('app/View/pages/company_clients_create.php');
    $content = ob_get_clean();
    require base_path('app/View/layouts/main.php');
});

$router->get('/company/clients/{id}', function ($id) use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);

    $companyId = (int)(getSessionCompanyId() ?? 0);
    $grants = [];
    $logists = [];

    if ($companyId <= 0) {
        $company = null;
        $client = null;
        $dbError = null;

        ob_start();
        require base_path('app/View/pages/company_client_view.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
        return;
    }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company) {
            $company = null;
            $client = null;
            $dbError = null;

            ob_start();
            require base_path('app/View/pages/company_client_view.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $pageTitle = 'Клиент';
        $pageContext = 'Клиенты › Компания: ' . $company['name'];

        if ($company['status'] !== 'active') {
            $client = null;
            $dbError = null;

            ob_start();
            require base_path('app/View/pages/company_client_view.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $dbIdentifier = $company['db_identifier'];
        $localDbConfig = $config['database'];
        $localDbConfig['database'] = $dbIdentifier;
        $localDb = new \App\Core\Database($localDbConfig);
        $localPdo = $localDb->connection();
                applyLocalMigrations($localPdo);

        try {
            $localPdo->query("SELECT 1 FROM clients LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $migrationSql = file_get_contents(base_path('database/migrations-local/002_create_company_clients.sql'));
            $localPdo->exec($migrationSql);
        }

        try {
            $localPdo->query("SELECT created_by_user_id FROM clients LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $localPdo->exec("ALTER TABLE clients ADD COLUMN created_by_user_id INT UNSIGNED DEFAULT NULL, ADD COLUMN created_by_role VARCHAR(20) DEFAULT NULL");
        }

        try {
            $localPdo->query("SELECT 1 FROM entity_access_grants LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $migrationSql = file_get_contents(base_path('database/migrations-local/009_create_entity_access_grants.sql'));
            $localPdo->exec($migrationSql);
        }

        $clientStmt = $localPdo->prepare('SELECT * FROM clients WHERE id = ?');
        $clientStmt->execute([(int) $id]);
        $client = $clientStmt->fetch(PDO::FETCH_ASSOC) ?: null;

        $contacts = [];
        if ($client) {
            try {
                $contacts = ClientContactService::loadByClientId($localPdo, (int) $client['id']);
            } catch (\Exception $e) {
                $contacts = [];
            }
        }

        $pageTitle = $client ? 'Клиент: ' . $client['name'] : 'Клиент';

        $grants = [];
        $logists = [];
        if (($_SESSION['role_code'] ?? '') === 'company_owner') {
            $grantsStmt = $localPdo->prepare(
                "SELECT g.*, u.full_name AS logist_name
                 FROM entity_access_grants g
                 LEFT JOIN users u ON g.granted_to_user_id = u.id
                 WHERE g.entity_type = ? AND g.entity_id = ?"
            );
            $grantsStmt->execute(['client', (int)$id]);
            $grants = $grantsStmt->fetchAll(PDO::FETCH_ASSOC);

            $logists = $localPdo->query("SELECT id, full_name, login FROM users WHERE role_code IN ('logist', 'senior_logist') AND status='active' ORDER BY full_name")->fetchAll(PDO::FETCH_ASSOC);
        }

        $dbError = null;

        ob_start();
        require base_path('app/View/pages/company_client_view.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
    } catch (\Exception $e) {
        $company = $company ?? null;
        $client = null;
        $grants = [];
        $logists = [];
        $dbError = 'Не удалось загрузить данные: ' . $e->getMessage();

        ob_start();
        require base_path('app/View/pages/company_client_view.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
    }
});

$router->get('/company/clients/{id}/edit', function ($id) use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);

    $companyId = (int)(getSessionCompanyId() ?? 0);

    if ($companyId <= 0) {
        $company = null;
        $client = null;
        $errors = [];
        $old = [];
        $formError = null;

        ob_start();
        require base_path('app/View/pages/company_client_edit.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
        return;
    }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company) {
            $company = null;
            $client = null;
            $errors = [];
            $old = [];
            $formError = null;

            ob_start();
            require base_path('app/View/pages/company_client_edit.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $pageTitle = 'Редактировать клиента';
        $pageContext = 'Клиенты › Компания: ' . $company['name'];

        if ($company['status'] !== 'active') {
            $client = null;
            $errors = [];
            $old = [];
            $formError = null;

            ob_start();
            require base_path('app/View/pages/company_client_edit.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $dbIdentifier = $company['db_identifier'];
        $localDbConfig = $config['database'];
        $localDbConfig['database'] = $dbIdentifier;
        $localDb = new \App\Core\Database($localDbConfig);
        $localPdo = $localDb->connection();
                applyLocalMigrations($localPdo);

        try {
            $localPdo->query("SELECT 1 FROM clients LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $migrationSql = file_get_contents(base_path('database/migrations-local/002_create_company_clients.sql'));
            $localPdo->exec($migrationSql);
        }

        $clientStmt = $localPdo->prepare('SELECT * FROM clients WHERE id = ?');
        $clientStmt->execute([(int) $id]);
        $client = $clientStmt->fetch(PDO::FETCH_ASSOC) ?: null;

        $contacts = [];
        if ($client) {
            try {
                $contacts = ClientContactService::loadByClientId($localPdo, (int) $client['id']);
            } catch (\Exception $e) {
                $contacts = [];
            }
        }

        $errors = [];
        $old = $client ?: [];
        $formError = null;

        ob_start();
        require base_path('app/View/pages/company_client_edit.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
    } catch (\Exception $e) {
        $company = $company ?? null;
        $client = null;
        $errors = [];
        $old = [];
        $formError = 'Не удалось загрузить данные: ' . $e->getMessage();

        ob_start();
        require base_path('app/View/pages/company_client_edit.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
    }
});

$router->post('/company/clients/{id}/edit', function ($id) use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);

    $companyId = (int)(getSessionCompanyId() ?? 0);

    if ($companyId <= 0) {
        $company = null;
        $client = null;
        $errors = [];
        $old = $_POST;
        $formError = 'Компания не найдена';

        ob_start();
        require base_path('app/View/pages/company_client_edit.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
        return;
    }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company) {
            $company = null;
            $client = null;
            $errors = [];
            $old = $_POST;
            $formError = 'Компания не найдена';

            ob_start();
            require base_path('app/View/pages/company_client_edit.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $pageTitle = 'Редактировать клиента';
        $pageContext = 'Клиенты › Компания: ' . $company['name'];

        if ($company['status'] !== 'active') {
            $client = null;
            $errors = [];
            $old = $_POST;
            $formError = 'Редактирование клиентов недоступно';

            ob_start();
            require base_path('app/View/pages/company_client_edit.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $dbIdentifier = $company['db_identifier'];
        $localDbConfig = $config['database'];
        $localDbConfig['database'] = $dbIdentifier;
        $localDb = new \App\Core\Database($localDbConfig);
        $localPdo = $localDb->connection();
                applyLocalMigrations($localPdo);

        try {
            $localPdo->query("SELECT 1 FROM clients LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $migrationSql = file_get_contents(base_path('database/migrations-local/002_create_company_clients.sql'));
            $localPdo->exec($migrationSql);
        }

        $clientStmt = $localPdo->prepare('SELECT * FROM clients WHERE id = ?');
        $clientStmt->execute([(int) $id]);
        $client = $clientStmt->fetch(PDO::FETCH_ASSOC) ?: null;

        if (!$client) {
            $errors = [];
            $old = $_POST;
            $formError = null;

            ob_start();
            require base_path('app/View/pages/company_client_edit.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $errors = [];
        $old = $_POST;
        $formError = null;

        $name = trim($_POST['name'] ?? '');
        $inn = trim($_POST['inn'] ?? '');

        if ($name === '') {
            $errors['name'] = 'Обязательное поле';
        }

        if ($inn === '') {
            $errors['inn'] = 'Обязательное поле';
        } else {
            $dupStmt = $localPdo->prepare('SELECT COUNT(*) FROM clients WHERE inn = ? AND id != ?');
            $dupStmt->execute([$inn, (int) $id]);
            if ($dupStmt->fetchColumn() > 0) {
                $errors['inn'] = 'ИНН уже используется в этой компании';
            }
        }

        if (!empty($errors)) {
            ob_start();
            require base_path('app/View/pages/company_client_edit.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $kpp = trim($_POST['kpp'] ?? '');
        $ogrn = trim($_POST['ogrn'] ?? '');
        $legalAddress = trim($_POST['legal_address'] ?? '');
        $physicalAddress = trim($_POST['physical_address'] ?? '');
        $comments = trim($_POST['comments'] ?? '');

        $update = $localPdo->prepare(
            'UPDATE clients SET
                name = :name,
                inn = :inn,
                kpp = :kpp,
                ogrn = :ogrn,
                legal_address = :legal_address,
                physical_address = :physical_address,
                status = :status,
                comments = :comments,
                updated_by_user_id = :updated_by_user_id,
                updated_by_role = :updated_by_role
             WHERE id = :id'
        );

        $update->execute([
            ':name'             => $name,
            ':inn'              => $inn,
            ':kpp'              => $kpp !== '' ? $kpp : null,
            ':ogrn'             => $ogrn !== '' ? $ogrn : null,
            ':legal_address'    => $legalAddress !== '' ? $legalAddress : null,
            ':physical_address' => $physicalAddress !== '' ? $physicalAddress : null,
            ':status'           => $_POST['status'] ?? $client['status'],
            ':comments'         => $comments !== '' ? $comments : null,
            ':updated_by_user_id' => (int)$_SESSION['user_id'],
            ':updated_by_role'   => $_SESSION['role_code'],
            ':id'               => (int) $id,
        ]);

        header('Location: /company/clients/' . $id);
        exit;
    } catch (\Exception $e) {
        $company = $company ?? null;
        $client = $client ?? null;
        $errors = [];
        $old = $_POST;
        $formError = 'Ошибка сохранения: ' . $e->getMessage();

        ob_start();
        require base_path('app/View/pages/company_client_edit.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
    }
});

$router->post('/company/clients/{id}/archive', function ($id) use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);

    $companyId = (int)(getSessionCompanyId() ?? 0);

    if ($companyId <= 0) {
        header('Location: /company/clients');
        exit;
    }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company || $company['status'] !== 'active') {
            header('Location: /company/clients');
            exit;
        }

        $dbIdentifier = $company['db_identifier'];
        $localDbConfig = $config['database'];
        $localDbConfig['database'] = $dbIdentifier;
        $localDb = new \App\Core\Database($localDbConfig);
        $localPdo = $localDb->connection();
                applyLocalMigrations($localPdo);

        try {
            $localPdo->query("SELECT 1 FROM clients LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $migrationSql = file_get_contents(base_path('database/migrations-local/002_create_company_clients.sql'));
            $localPdo->exec($migrationSql);
        }

        $update = $localPdo->prepare("UPDATE clients SET status = 'archived' WHERE id = ?");
        $update->execute([(int) $id]);

        header('Location: /company/clients');
        exit;
    } catch (\Exception $e) {
        header('Location: /company/clients');
        exit;
    }
});

$router->get('/company/contractors', function () use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);
    $pageTitle = 'Перевозчики';
    $pageContext = 'Перевозчики › Компания';

    $companyId = (int)(getSessionCompanyId() ?? 0);

    if ($companyId <= 0) {
        $company = null;
        $contractors = [];
        $dbError = null;

        ob_start();
        require base_path('app/View/pages/company_contractors.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
        return;
    }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company) {
            $company = null;
            $contractors = [];
            $dbError = null;

            ob_start();
            require base_path('app/View/pages/company_contractors.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $pageContext = 'Перевозчики › Компания: ' . $company['name'];
        $topbarCrumbs = [
            ['label' => mb_strtoupper($company['name']), 'url' => '/company/dashboard'],
            ['label' => 'Подрядчики', 'url' => null],
            ['label' => 'Список перевозчиков', 'url' => null],
        ];

        if ($company['status'] !== 'active') {
            $contractors = [];
            $dbError = null;

            ob_start();
            require base_path('app/View/pages/company_contractors.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $dbIdentifier = $company['db_identifier'];
        $localDbConfig = $config['database'];
        $localDbConfig['database'] = $dbIdentifier;
        $localDb = new \App\Core\Database($localDbConfig);
        $localPdo = $localDb->connection();
                applyLocalMigrations($localPdo);

        try {
            $localPdo->query("SELECT 1 FROM contractors LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $migrationSql = file_get_contents(base_path('database/migrations-local/003_create_company_contractors.sql'));
            $localPdo->exec($migrationSql);
        }

        try {
            $localPdo->query("SELECT created_by_user_id FROM contractors LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $localPdo->exec("ALTER TABLE contractors ADD COLUMN created_by_user_id INT UNSIGNED DEFAULT NULL, ADD COLUMN created_by_role VARCHAR(20) DEFAULT NULL");
        }

        try {
            $localPdo->query("SELECT 1 FROM entity_access_grants LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $migrationSql = file_get_contents(base_path('database/migrations-local/009_create_entity_access_grants.sql'));
            $localPdo->exec($migrationSql);
        }

        $isLogist = ($_SESSION['role_code'] ?? '') === 'logist';
        if ($isLogist) {
            $userId = (int)$_SESSION['user_id'];
            $contractorStmt = $localPdo->prepare(
                "SELECT c.*,
                        " . ContractorContactService::buildPrimaryContactSubquery('contact_person') . " AS primary_contact_person,
                        " . ContractorContactService::buildPrimaryContactSubquery('phone') . " AS primary_contact_phone,
                        " . ContractorContactService::buildDocumentEmailSubquery() . " AS doc_email
                 FROM contractors c
                 WHERE (c.created_by_user_id = ? OR c.id IN (SELECT entity_id FROM entity_access_grants WHERE entity_type = 'contractor' AND granted_to_user_id = ? AND access_level = 'view'))
                 ORDER BY c.created_at DESC"
            );
            $contractorStmt->execute([$userId, $userId]);
            $contractors = $contractorStmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $contractorStmt = $localPdo->query(
                "SELECT c.*,
                        " . ContractorContactService::buildPrimaryContactSubquery('contact_person') . " AS primary_contact_person,
                        " . ContractorContactService::buildPrimaryContactSubquery('phone') . " AS primary_contact_phone,
                        " . ContractorContactService::buildDocumentEmailSubquery() . " AS doc_email
                 FROM contractors c
                 ORDER BY c.created_at DESC"
            );
            $contractors = $contractorStmt->fetchAll(PDO::FETCH_ASSOC);
        }
        $dbError = null;
    } catch (\Exception $e) {
        $company = $company ?? null;
        $contractors = [];
        $dbError = 'Не удалось подключиться к базе данных компании.';
    }

    ob_start();
    require base_path('app/View/pages/company_contractors.php');
    $content = ob_get_clean();
    require base_path('app/View/layouts/main.php');
});

$router->get('/company/contractors/create', function () use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);
    $pageTitle = 'Создать перевозчика';
    $pageContext = 'Перевозчики › Компания';
    $isModalRequest = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest';

    $companyId = (int)(getSessionCompanyId() ?? 0);

    if ($companyId <= 0) {
        $company = null;
        $success = false;
        $errors = [];
        $old = [];
        $formError = null;
        $createdContractor = null;

        ob_start();
        require base_path('app/View/pages/company_contractors_create.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
        return;
    }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company) {
            $company = null;
            $success = false;
            $errors = [];
            $old = [];
            $formError = null;
            $createdContractor = null;

            ob_start();
            require base_path('app/View/pages/company_contractors_create.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $pageContext = 'Перевозчики › Компания: ' . $company['name'];

        $success = false;
        $errors = [];
        $old = [];
        $formError = null;
        $createdContractor = null;
        $docTypes = [];

        if ($company['status'] === 'active') {
            try {
                $dbIdentifier = $company['db_identifier'];
                $localDbConfig = $config['database']; $localDbConfig['database'] = $dbIdentifier;
                $localDb = new \App\Core\Database($localDbConfig); $localPdo = $localDb->connection();
                applyLocalMigrations($localPdo);

                try { $localPdo->query("SELECT 1 FROM document_types LIMIT 1")->fetch(); }
                catch (\Exception $e) {
                    $localPdo->exec(file_get_contents(base_path('database/migrations-local/024_create_document_types.sql')));
                    $localPdo->exec(file_get_contents(base_path('database/migrations-local/025_add_document_type_id.sql')));
                }

                $docTypes = $localPdo->query("SELECT id, name, code, entity_type FROM document_types WHERE entity_type = 'contractor' OR entity_type IS NULL ORDER BY sort_order, name")->fetchAll(PDO::FETCH_ASSOC);
            } catch (\Exception $e) {
                $docTypes = [];
            }
        }
    } catch (\Exception $e) {
        $company = null;
        $success = false;
        $errors = [];
        $old = [];
        $formError = 'Ошибка загрузки данных: ' . $e->getMessage();
        $createdContractor = null;
    }

    if ($isModalRequest) {
        header('Content-Type: text/html; charset=utf-8');
        $leEntityType = 'contractor';
        $leFormAction = '/company/contractors/create';
        $leFormId = 'le-create-form';
        $leOld = $old ?? [];
        $leErrors = $errors ?? [];
        $leFormError = $formError;
        $leDocTypes = $docTypes ?? [];
        $leContactValues = $contactValues ?? [];
        $leContactErrors = [];
        ob_start();
        require base_path('app/View/partials/legal_entity_create_form.php');
        echo ob_get_clean();
        exit;
    }

    ob_start();
    require base_path('app/View/pages/company_contractors_create.php');
    $content = ob_get_clean();
    require base_path('app/View/layouts/main.php');
});

$router->post('/company/requisites/lookup-by-inn', function () use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);

    $companyId = (int)(getSessionCompanyId() ?? 0);
    if ($companyId <= 0) {
        jsonResponse([
            'ok' => false,
            'message' => 'Не удалось получить данные. Заполните реквизиты вручную.',
        ], 200);
        return;
    }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company || ($company['status'] ?? '') !== 'active') {
            jsonResponse([
                'ok' => false,
                'message' => 'Не удалось получить данные. Заполните реквизиты вручную.',
            ], 200);
            return;
        }

        $payload = requestJsonBody();
        $inn = (string)($payload['inn'] ?? $_POST['inn'] ?? '');

        $lookupService = new CompanyInnLookupService(
            (string)env('DADATA_API_KEY', ''),
            (string)env('DADATA_API_URL', 'https://suggestions.dadata.ru/suggestions/api/4_1/rs/findById/party'),
            (int)env('DADATA_API_TIMEOUT', 5),
            (string)env('DADATA_CAINFO', '')
        );

        $result = $lookupService->lookup($inn);
        jsonResponse($result, 200);
    } catch (\Throwable $e) {
        jsonResponse([
            'ok' => false,
            'message' => 'Не удалось получить данные. Заполните реквизиты вручную.',
        ], 200);
    }
});

$router->get('/company/contractors/create-full', function () use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);
    $pageTitle = 'Создать перевозчика + Водителя + Транспорт';
    $pageContext = 'Перевозчики › Компания';

    $companyId = (int)(getSessionCompanyId() ?? 0);
    $contractors = [];
    $drivers = [];
    $vehicleSets = [];

    if ($companyId <= 0) {
        $company = null;
        $success = false;
        $errors = [];
        $old = [];
        $formError = null;

        ob_start();
        require base_path('app/View/pages/company_contractors_create_full.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
        return;
    }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company) {
            $company = null;
            $success = false;
            $errors = [];
            $old = [];
            $formError = null;

            ob_start();
            require base_path('app/View/pages/company_contractors_create_full.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $pageContext = 'Перевозчики › Компания: ' . $company['name'];

        // Load existing entities from local database
        $dbIdentifier = $company['db_identifier'];
        $localDbConfig = $config['database'];
        $localDbConfig['database'] = $dbIdentifier;
        $localDb = new \App\Core\Database($localDbConfig);
        $localPdo = $localDb->connection();
        applyLocalMigrations($localPdo);

        // Load contractors
        try {
            $contractorsStmt = $localPdo->query(
                'SELECT id, name, inn FROM contractors WHERE status IN (\'active\', \'archived\') ORDER BY name'
            );
            $contractors = $contractorsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Exception $e) {
            $contractors = [];
        }

        // Load drivers
        try {
            $driversStmt = $localPdo->query(
                'SELECT id, full_name, phone FROM drivers WHERE status IN (\'active\', \'archived\') ORDER BY full_name'
            );
            $drivers = $driversStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Exception $e) {
            $drivers = [];
        }

        // Load vehicle sets with plate numbers
        try {
            $vehicleSetsStmt = $localPdo->query(
                'SELECT vs.id, vs.set_type,
                        vu1.plate_number AS primary_plate,
                        vu2.plate_number AS secondary_plate
                   FROM vehicle_sets vs
                   JOIN vehicle_units vu1 ON vs.primary_vehicle_unit_id = vu1.id
                   LEFT JOIN vehicle_units vu2 ON vs.secondary_vehicle_unit_id = vu2.id
                  WHERE vs.status IN (\'active\', \'archived\')
                  ORDER BY vs.id DESC'
            );
            $vehicleSets = $vehicleSetsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Exception $e) {
            $vehicleSets = [];
        }

        $success = false;
        $errors = [];
        $old = [];
        $formError = null;
    } catch (\Exception $e) {
        $company = null;
        $success = false;
        $errors = [];
        $old = [];
        $formError = 'Ошибка загрузки данных: ' . $e->getMessage();
    }

    ob_start();
    require base_path('app/View/pages/company_contractors_create_full.php');
    $content = ob_get_clean();
    require base_path('app/View/layouts/main.php');
});

$router->post('/company/contractors/create', function () use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);
    $pageTitle = 'Создать перевозчика';
    $pageContext = 'Перевозчики › Компания';
    $isModalRequest = !empty($_POST['is_modal']);

    $companyId = (int)(getSessionCompanyId() ?? 0);
    $errors = [];
    $old = $_POST;
    $formError = null;
    $success = false;
    $createdContractor = null;
    $docErrors = [];
    $uploadedDocs = [];

    if ($companyId <= 0) {
        $company = null;
        $formError = 'Компания не найдена';

        ob_start();
        require base_path('app/View/pages/company_contractors_create.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
        return;
    }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company) {
            $company = null;
            $formError = 'Компания не найдена';

            ob_start();
            require base_path('app/View/pages/company_contractors_create.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $pageContext = 'Перевозчики › Компания: ' . $company['name'];

        if ($company['status'] !== 'active') {
            $formError = 'Создание перевозчиков недоступно';

            ob_start();
            require base_path('app/View/pages/company_contractors_create.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $dbIdentifier = $company['db_identifier'];
        $localDbConfig = $config['database'];
        $localDbConfig['database'] = $dbIdentifier;
        $localDb = new \App\Core\Database($localDbConfig);
        $localPdo = $localDb->connection();
                applyLocalMigrations($localPdo);

        try {
            $localPdo->query("SELECT 1 FROM contractors LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $migrationSql = file_get_contents(base_path('database/migrations-local/003_create_company_contractors.sql'));
            $localPdo->exec($migrationSql);
        }

        if (isPostTruncated()) {
            $formError = 'Общий размер отправки превышает серверный лимит. Для ERP требуется настройка post_max_size не менее 100M. Уменьшите количество файлов или обратитесь к администратору.';
            ob_start();
            require base_path('app/View/pages/company_contractors_create.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $name = trim($_POST['name'] ?? '');
        $inn = trim($_POST['inn'] ?? '');
        $kpp = trim($_POST['kpp'] ?? '');
        $ogrn = trim($_POST['ogrn'] ?? '');
        $legalAddress = trim($_POST['legal_address'] ?? '');
        $physicalAddress = trim($_POST['physical_address'] ?? '');
        $comments = trim($_POST['comments'] ?? '');
        $directorFullName = trim($_POST['director_full_name'] ?? '');
        $directorPosition = trim($_POST['director_position'] ?? '');

        $contactPayload = ContractorContactService::normalizeSubmittedContacts($_POST['contacts'] ?? []);
        $submittedContacts = $contactPayload['contacts'];
        if (!empty($contactPayload['errors'])) {
            $errors['contacts'] = $contactPayload['errors'];
        }

        if ($name === '') {
            $errors['name'] = 'Обязательное поле';
        }

        // Валидация ИНН
        if ($inn === '') {
            $errors['inn'] = 'Укажите ИНН';
        } elseif (!preg_match('/^\d+$/', $inn) || (strlen($inn) !== 10 && strlen($inn) !== 12)) {
            $errors['inn'] = 'ИНН должен содержать 10 или 12 цифр';
        }

        // Валидация КПП (если заполнен)
        if ($kpp !== '' && !preg_match('/^\d{9}$/', $kpp)) {
            $errors['kpp'] = 'КПП должен содержать 9 цифр';
        }

        // Валидация ОГРН (если заполнен)
        if ($ogrn !== '' && (!preg_match('/^\d+$/', $ogrn) || (strlen($ogrn) !== 13 && strlen($ogrn) !== 15))) {
            $errors['ogrn'] = 'ОГРН/ОГРНИП должен содержать 13 или 15 цифр';
        }

        // Валидация банковских полей (если заполнены)
        if (($_POST['bank_account'] ?? '') !== '' && !preg_match('/^\d{20}$/', $_POST['bank_account'])) {
            $errors['bank_account'] = 'Расчётный счёт должен содержать 20 цифр';
        }
        if (($_POST['bank_bik'] ?? '') !== '' && !preg_match('/^\d{9}$/', $_POST['bank_bik'])) {
            $errors['bank_bik'] = 'БИК должен содержать 9 цифр';
        }
        if (($_POST['bank_corr_account'] ?? '') !== '' && !preg_match('/^\d{20}$/', $_POST['bank_corr_account'])) {
            $errors['bank_corr_account'] = 'Корр. счёт должен содержать 20 цифр';
        }

        if ($inn !== '') {
            $checkStmt = $localPdo->prepare('SELECT COUNT(*) FROM contractors WHERE inn = ?');
            $checkStmt->execute([$inn]);
            if ($checkStmt->fetchColumn() > 0) {
                $errors['inn'] = 'ИНН уже используется в этой компании';
            }
        }

        if (!empty($errors)) {
            ob_start();
            require base_path('app/View/pages/company_contractors_create.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        // Check total upload size before INSERT
        $totalSizeError = validateTotalUploadSize();
        if ($totalSizeError !== '') {
            $formError = $totalSizeError;
            ob_start();
            require base_path('app/View/pages/company_contractors_create.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $insert = $localPdo->prepare(
            'INSERT INTO contractors (name, inn, kpp, ogrn, contractor_type, legal_address, physical_address,
             bank_account, bank_name, bank_bik, bank_corr_account,
             director_full_name, director_position,
             status, comments, created_by_user_id, created_by_role)
             VALUES (:name, :inn, :kpp, :ogrn, :contractor_type, :legal_address, :physical_address,
             :bank_account, :bank_name, :bank_bik, :bank_corr_account,
             :director_full_name, :director_position,
             :status, :comments, :created_by_user_id, :created_by_role)'
        );
        $insert->execute([
            ':name'               => $name,
            ':inn'                => $inn,
            ':kpp'                => $kpp !== '' ? $kpp : null,
            ':ogrn'               => $ogrn !== '' ? $ogrn : null,
            ':contractor_type'    => ($_POST['contractor_type'] ?? '') !== '' ? $_POST['contractor_type'] : null,
            ':legal_address'      => $legalAddress !== '' ? $legalAddress : null,
            ':physical_address'   => $physicalAddress !== '' ? $physicalAddress : null,
            ':bank_account'       => ($_POST['bank_account'] ?? '') !== '' ? $_POST['bank_account'] : null,
            ':bank_name'          => ($_POST['bank_name'] ?? '') !== '' ? $_POST['bank_name'] : null,
            ':bank_bik'           => ($_POST['bank_bik'] ?? '') !== '' ? $_POST['bank_bik'] : null,
            ':bank_corr_account'  => ($_POST['bank_corr_account'] ?? '') !== '' ? $_POST['bank_corr_account'] : null,
            ':director_full_name' => $directorFullName !== '' ? $directorFullName : null,
            ':director_position'  => $directorPosition !== '' ? $directorPosition : null,
            ':status'             => 'active',
            ':comments'           => $comments !== '' ? $comments : null,
            ':created_by_user_id' => (int)$_SESSION['user_id'],
            ':created_by_role'    => $_SESSION['role_code'],
        ]);

        $newContractorId = (int)$localPdo->lastInsertId();
        ContractorContactService::replaceForContractor(
            $localPdo,
            $newContractorId,
            $submittedContacts,
            (int) $_SESSION['user_id'],
            (string) ($_SESSION['role_code'] ?? '')
        );

        $createdContractor = [
            'id'              => $newContractorId,
            'name'            => $name,
            'inn'             => $inn,
            'contractor_type' => ($_POST['contractor_type'] ?? '') !== '' ? $_POST['contractor_type'] : null,
        ];

        // -- Process document uploads during creation --
        $docErrors = []; $uploadedDocs = []; $entityType = 'contractor';
        try { $localPdo->query("SELECT 1 FROM documents LIMIT 1")->fetch(); } catch (\Exception $e) { $localPdo->exec(file_get_contents(base_path('database/migrations-local/007_create_company_documents.sql'))); }
        try { $localPdo->query("SELECT 1 FROM document_types LIMIT 1")->fetch(); } catch (\Exception $e) { $localPdo->exec(file_get_contents(base_path('database/migrations-local/024_create_document_types.sql'))); $localPdo->exec(file_get_contents(base_path('database/migrations-local/025_add_document_type_id.sql'))); }
        $allowedExt = ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'doc', 'docx', 'xls', 'xlsx']; $maxSize = 20 * 1024 * 1024;
        if (!empty($_FILES['predef_doc']['name']) && is_array($_FILES['predef_doc']['name'])) {
            foreach ($_FILES['predef_doc']['name'] as $code => $origName) {
                $fe = $_FILES['predef_doc']['error'][$code] ?? UPLOAD_ERR_NO_FILE; if ($fe !== UPLOAD_ERR_OK || trim((string)$origName) === '') continue;
                $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION)); $fs = $_FILES['predef_doc']['size'][$code];
                if (!in_array($ext, $allowedExt, true)) { $docErrors[] = 'Предопределённый документ «' . ($_POST['predef_doc_type'][$code] ?? $code) . '»: недопустимый формат'; continue; }
                if ($fs > $maxSize) { $docErrors[] = 'Предопределённый документ «' . ($_POST['predef_doc_type'][$code] ?? $code) . '»: размер > 20 МБ'; continue; }
                if (strpos($origName, '../') !== false || strpos($origName, '..\\') !== false || strpos($origName, '/') !== false || strpos($origName, '\\') !== false) { $docErrors[] = 'Предопределённый документ «' . ($_POST['predef_doc_type'][$code] ?? $code) . '»: недопустимое имя'; continue; }
                try {
                    $storedName = uniqid('doc_', true) . '.' . $ext; $relativeDir = 'companies/' . $companyId . '/documents/' . $entityType . '/' . $newContractorId; $absoluteDir = storage_path($relativeDir);
                    if (!is_dir($absoluteDir)) mkdir($absoluteDir, 0755, true);
                    if (!move_uploaded_file($_FILES['predef_doc']['tmp_name'][$code], $absoluteDir . DIRECTORY_SEPARATOR . $storedName)) { $docErrors[] = 'Предопределённый документ «' . ($_POST['predef_doc_type'][$code] ?? $code) . '»: не удалось сохранить'; continue; }
                    $docTypeName = $_POST['predef_doc_type'][$code] ?? ''; $mime = $_FILES['predef_doc']['type'][$code]; $dtId = null;
                    if ($docTypeName !== '') { $dts = $localPdo->prepare("SELECT id FROM document_types WHERE name = ? AND entity_type = ? LIMIT 1"); $dts->execute([$docTypeName, $entityType]); $dtId = $dts->fetchColumn() ?: null; }
                    $ins = $localPdo->prepare('INSERT INTO documents (entity_type, entity_id, document_type, document_type_id, original_name, stored_name, relative_path, mime_type, file_size, status, uploaded_by_user_id, uploaded_by_role, created_by_user_id, created_by_role) VALUES (:et, :eid, :dtype, :dtid, :oname, :sname, :rpath, :mime, :fsize, :status, :uid, :role, :cuid, :crole)');
                    $ins->execute([':et' => $entityType, ':eid' => $newContractorId, ':dtype' => $docTypeName ?: null, ':dtid' => $dtId, ':oname' => $origName, ':sname' => $storedName, ':rpath' => $relativeDir . '/' . $storedName, ':mime' => $mime, ':fsize' => $fs, ':status' => 'uploaded', ':uid' => (int)$_SESSION['user_id'], ':role' => $_SESSION['role_code'], ':cuid' => (int)$_SESSION['user_id'], ':crole' => $_SESSION['role_code']]);
                    $uploadedDocs[] = $docTypeName . ' (' . $origName . ')';
                } catch (\Exception $ex) { $docErrors[] = 'Предопределённый документ «' . ($_POST['predef_doc_type'][$code] ?? $code) . '»: ошибка сохранения'; }
            }
        }
        if (!empty($_FILES['custom_doc_file']['name']) && is_array($_FILES['custom_doc_file']['name'])) {
            foreach ($_FILES['custom_doc_file']['name'] as $idx => $origName) {
                $fe = $_FILES['custom_doc_file']['error'][$idx] ?? UPLOAD_ERR_NO_FILE; if ($fe !== UPLOAD_ERR_OK || trim((string)$origName) === '') continue;
                $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION)); $fs = $_FILES['custom_doc_file']['size'][$idx];
                if (!in_array($ext, $allowedExt, true)) { $docErrors[] = 'Произвольный документ #' . ($idx + 1) . ': недопустимый формат'; continue; }
                if ($fs > $maxSize) { $docErrors[] = 'Произвольный документ #' . ($idx + 1) . ': размер > 20 МБ'; continue; }
                if (strpos($origName, '../') !== false || strpos($origName, '..\\') !== false || strpos($origName, '/') !== false || strpos($origName, '\\') !== false) { $docErrors[] = 'Произвольный документ #' . ($idx + 1) . ': недопустимое имя'; continue; }
                $customTypeNew = trim($_POST['custom_doc_type_new'][$idx] ?? ''); $customTypeSelect = trim($_POST['custom_doc_type'][$idx] ?? ''); $docTypeName = $customTypeNew !== '' ? $customTypeNew : $customTypeSelect; $dtId = null;
                if ($customTypeNew !== '') { try { $idts = $localPdo->prepare("INSERT IGNORE INTO document_types (name, entity_type, category, created_by_user_id, created_by_role) VALUES (:name, :et, 'custom', :uid, :role)"); $idts->execute([':name' => $customTypeNew, ':et' => $entityType, ':uid' => (int)$_SESSION['user_id'], ':role' => $_SESSION['role_code']]); $dtId = $localPdo->lastInsertId(); if (!$dtId) { $g = $localPdo->prepare("SELECT id FROM document_types WHERE name = ? AND entity_type = ? LIMIT 1"); $g->execute([$customTypeNew, $entityType]); $dtId = $g->fetchColumn() ?: null; } } catch (\Exception $ex) { $docErrors[] = 'Произвольный документ #' . ($idx + 1) . ': ошибка создания типа документа'; } }
                elseif ($customTypeSelect !== '') { $g = $localPdo->prepare("SELECT id FROM document_types WHERE name = ? AND entity_type = ? LIMIT 1"); $g->execute([$customTypeSelect, $entityType]); $dtId = $g->fetchColumn() ?: null; }
                try {
                    $storedName = uniqid('doc_', true) . '.' . $ext; $relativeDir = 'companies/' . $companyId . '/documents/' . $entityType . '/' . $newContractorId; $absoluteDir = storage_path($relativeDir);
                    if (!is_dir($absoluteDir)) mkdir($absoluteDir, 0755, true);
                    if (!move_uploaded_file($_FILES['custom_doc_file']['tmp_name'][$idx], $absoluteDir . DIRECTORY_SEPARATOR . $storedName)) { $docErrors[] = 'Произвольный документ #' . ($idx + 1) . ': не удалось сохранить'; continue; }
                    $mime = $_FILES['custom_doc_file']['type'][$idx];
                    $ins = $localPdo->prepare('INSERT INTO documents (entity_type, entity_id, document_type, document_type_id, original_name, stored_name, relative_path, mime_type, file_size, status, uploaded_by_user_id, uploaded_by_role, created_by_user_id, created_by_role) VALUES (:et, :eid, :dtype, :dtid, :oname, :sname, :rpath, :mime, :fsize, :status, :uid, :role, :cuid, :crole)');
                    $ins->execute([':et' => $entityType, ':eid' => $newContractorId, ':dtype' => $docTypeName ?: null, ':dtid' => $dtId, ':oname' => $origName, ':sname' => $storedName, ':rpath' => $relativeDir . '/' . $storedName, ':mime' => $mime, ':fsize' => $fs, ':status' => 'uploaded', ':uid' => (int)$_SESSION['user_id'], ':role' => $_SESSION['role_code'], ':cuid' => (int)$_SESSION['user_id'], ':crole' => $_SESSION['role_code']]);
                    $uploadedDocs[] = $docTypeName . ' (' . $origName . ')';
                } catch (\Exception $ex) { $docErrors[] = 'Произвольный документ #' . ($idx + 1) . ': ошибка сохранения'; }
            }
        }

        $success = true;
    } catch (\Exception $e) {
        $company = $company ?? null;
        $formError = 'Ошибка создания перевозчика: ' . $e->getMessage();
    }

    if ($isModalRequest) {
        header('Content-Type: text/html; charset=utf-8');
        if ($success) {
            echo '<div data-le-create-success="1"></div>';
        } else {
            if (empty($docTypes) && !empty($localPdo)) {
                try { $docTypes = $localPdo->query("SELECT id, name, code, entity_type FROM document_types WHERE entity_type = 'contractor' OR entity_type IS NULL ORDER BY sort_order, name")->fetchAll(PDO::FETCH_ASSOC); } catch (\Exception $e) { $docTypes = []; }
            }
            $leEntityType = 'contractor';
            $leFormAction = '/company/contractors/create';
            $leFormId = 'le-create-form';
            $leOld = $old;
            $leErrors = $errors;
            $leFormError = $formError;
            $leDocTypes = $docTypes ?? [];
            $leContactValues = $submittedContacts ?? ($old['contacts'] ?? []);
            $leContactErrors = $errors['contacts'] ?? [];
            ob_start();
            require base_path('app/View/partials/legal_entity_create_form.php');
            echo ob_get_clean();
        }
        exit;
    }

    ob_start();
    require base_path('app/View/pages/company_contractors_create.php');
    $content = ob_get_clean();
    require base_path('app/View/layouts/main.php');
});

$router->post('/company/contractors/create-full', function () use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);
    $pageTitle = 'Создать перевозчика + Водителя + Транспорт';
    $pageContext = 'Перевозчики › Компания';

    $companyId = (int)(getSessionCompanyId() ?? 0);
    $errors = [];
    $old = $_POST;
    $formError = null;
    $success = false;
    $createdContractor = null;
    $createdDriver = null;
    $createdVehicleSet = null;
    $createdBlock = null;
    $createdCrew = null;
    $contractors = [];
    $drivers = [];
    $vehicleSets = [];

    if ($companyId <= 0) {
        $company = null;
        $formError = 'Компания не найдена';

        ob_start();
        require base_path('app/View/pages/company_contractors_create_full.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
        return;
    }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company) {
            $company = null;
            $formError = 'Компания не найдена';

            ob_start();
            require base_path('app/View/pages/company_contractors_create_full.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $pageContext = 'Перевозчики › Компания: ' . $company['name'];

        if ($company['status'] !== 'active') {
            $formError = 'Создание недоступно';

            ob_start();
            require base_path('app/View/pages/company_contractors_create_full.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $dbIdentifier = $company['db_identifier'];
        $localDbConfig = $config['database'];
        $localDbConfig['database'] = $dbIdentifier;
        $localDb = new \App\Core\Database($localDbConfig);
        $localPdo = $localDb->connection();
        applyLocalMigrations($localPdo);

        // Ensure all required tables exist
        $requiredTables = [
            'contractors' => 'database/migrations-local/003_create_company_contractors.sql',
            'drivers'     => 'database/migrations-local/004_create_company_drivers.sql',
            'vehicle_units' => 'database/migrations-local/005_create_company_vehicles.sql',
            'vehicle_sets' => 'database/migrations-local/017_create_vehicle_sets.sql',
            'driver_vehicle_blocks' => 'database/migrations-local/018_create_driver_vehicle_blocks.sql',
            'crews'       => 'database/migrations-local/006_create_company_crews.sql',
        ];

        foreach ($requiredTables as $table => $migrationFile) {
            try {
                $localPdo->query("SELECT 1 FROM `$table` LIMIT 1")->fetch();
            } catch (\Exception $e) {
                $migrationSql = file_get_contents(base_path($migrationFile));
                if ($migrationSql !== false && trim($migrationSql) !== '') {
                    $localPdo->exec($migrationSql);
                }
            }
        }

        // Handle vehicles -> vehicle_units rename if needed
        try {
            $localPdo->query("SELECT 1 FROM vehicle_units LIMIT 1")->fetch();
        } catch (\Exception $e) {
            try {
                $localPdo->exec("RENAME TABLE vehicles TO vehicle_units");
            } catch (\Exception $renameEx) {
                // Table may already be vehicle_units or vehicles may not exist
            }
        }

        // --- Determine modes ---
        $contractorMode = trim($_POST['contractor_mode'] ?? 'existing');
        $driverMode = trim($_POST['driver_mode'] ?? 'existing');
        $vehicleMode = trim($_POST['vehicle_mode'] ?? 'existing');

        // --- Load existing entities for view (dropdowns + success display) ---
        $contractors = [];
        $drivers = [];
        $vehicleSets = [];
        try {
            $contractorsStmt = $localPdo->query(
                'SELECT id, name, inn FROM contractors WHERE status IN (\'active\', \'archived\') ORDER BY name'
            );
            $contractors = $contractorsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Exception $e) {}
        try {
            $driversStmt = $localPdo->query(
                'SELECT id, full_name, phone FROM drivers WHERE status IN (\'active\', \'archived\') ORDER BY full_name'
            );
            $drivers = $driversStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Exception $e) {}
        try {
            $vehicleSetsStmt = $localPdo->query(
                'SELECT vs.id, vs.set_type,
                        vu1.plate_number AS primary_plate,
                        vu2.plate_number AS secondary_plate
                   FROM vehicle_sets vs
                   JOIN vehicle_units vu1 ON vs.primary_vehicle_unit_id = vu1.id
                   LEFT JOIN vehicle_units vu2 ON vs.secondary_vehicle_unit_id = vu2.id
                  WHERE vs.status IN (\'active\', \'archived\')
                  ORDER BY vs.id DESC'
            );
            $vehicleSets = $vehicleSetsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Exception $e) {}

        // --- Validation ---
        $name = trim($_POST['name'] ?? '');
        $inn = trim($_POST['inn'] ?? '');
        $kpp = trim($_POST['kpp'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $contractorIdExisting = trim($_POST['contractor_id'] ?? '');

        $driverFullName = trim($_POST['driver_full_name'] ?? '');
        $driverPhone = trim($_POST['driver_phone'] ?? '');
        $driverIdExisting = trim($_POST['driver_id'] ?? '');

        $plateNumber = trim($_POST['plate_number'] ?? '');
        $brand = trim($_POST['brand'] ?? '');
        $model = trim($_POST['model'] ?? '');
        $setType = trim($_POST['set_type'] ?? 'single');
        $secondaryPlateNumber = trim($_POST['secondary_plate_number'] ?? '');
        $vehicleSetIdExisting = trim($_POST['vehicle_set_id'] ?? '');

        // Contractor validation
        if ($contractorMode === 'existing') {
            if ($contractorIdExisting === '') {
                $errors['contractor_id'] = 'Выберите перевозчика';
            } else {
                // Verify contractor exists
                $checkStmt = $localPdo->prepare('SELECT id, name, inn FROM contractors WHERE id = ?');
                $checkStmt->execute([(int)$contractorIdExisting]);
                $existingContractorRow = $checkStmt->fetch(PDO::FETCH_ASSOC);
                if (!$existingContractorRow) {
                    $errors['contractor_id'] = 'Перевозчик не найден';
                }
            }
        } else {
            if ($name === '') {
                $errors['name'] = 'Обязательное поле';
            }
            if ($inn === '') {
                $errors['inn'] = 'Обязательное поле';
            } else {
                $checkStmt = $localPdo->prepare('SELECT COUNT(*) FROM contractors WHERE inn = ?');
                $checkStmt->execute([$inn]);
                if ($checkStmt->fetchColumn() > 0) {
                    $errors['inn'] = 'ИНН уже используется в этой компании';
                }
            }
        }

        // Driver validation
        if ($driverMode === 'existing') {
            if ($driverIdExisting === '') {
                $errors['driver_id'] = 'Выберите водителя';
            } else {
                $checkStmt = $localPdo->prepare('SELECT id, full_name FROM drivers WHERE id = ?');
                $checkStmt->execute([(int)$driverIdExisting]);
                if (!$checkStmt->fetch(PDO::FETCH_ASSOC)) {
                    $errors['driver_id'] = 'Водитель не найден';
                }
            }
        } else {
            if ($driverFullName === '') {
                $errors['driver_full_name'] = 'Обязательное поле';
            }
        }

        // Vehicle validation
        if ($vehicleMode === 'existing') {
            if ($vehicleSetIdExisting === '') {
                $errors['vehicle_set_id'] = 'Выберите транспорт';
            } else {
                $checkStmt = $localPdo->prepare('SELECT id FROM vehicle_sets WHERE id = ?');
                $checkStmt->execute([(int)$vehicleSetIdExisting]);
                if (!$checkStmt->fetch(PDO::FETCH_ASSOC)) {
                    $errors['vehicle_set_id'] = 'Транспорт не найден';
                }
            }
        } else {
            if ($plateNumber === '') {
                $errors['plate_number'] = 'Обязательное поле';
            } else {
                $checkStmt = $localPdo->prepare('SELECT COUNT(*) FROM vehicle_units WHERE plate_number = ?');
                $checkStmt->execute([$plateNumber]);
                if ($checkStmt->fetchColumn() > 0) {
                    $errors['plate_number'] = 'Госномер уже используется в этой компании';
                }
            }
            if (($setType === 'coupling' || $setType === 'road_train') && $secondaryPlateNumber === '') {
                $errors['secondary_plate_number'] = 'Обязательно для сцепки и автопоезда';
            }
            if ($secondaryPlateNumber !== '' && empty($errors['plate_number'])) {
                $checkStmt = $localPdo->prepare('SELECT COUNT(*) FROM vehicle_units WHERE plate_number = ?');
                $checkStmt->execute([$secondaryPlateNumber]);
                if ($checkStmt->fetchColumn() > 0) {
                    $errors['secondary_plate_number'] = 'Госномер уже используется в этой компании';
                }
            }
        }

        if (!empty($errors)) {
            ob_start();
            require base_path('app/View/pages/company_contractors_create_full.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        // --- Transactional creation ---
        $localPdo->beginTransaction();
        try {
            $uid = (int)$_SESSION['user_id'];
            $role = $_SESSION['role_code'];

            // 1. Contractor — use existing or create new
            $contractorId = null;
            $contractorNameForSuccess = '';
            $contractorInnForSuccess = '';
            if ($contractorMode === 'existing') {
                $contractorId = (int)$contractorIdExisting;
                $contractorNameForSuccess = $existingContractorRow['name'] ?? '';
                $contractorInnForSuccess = $existingContractorRow['inn'] ?? '';
            } else {
                $insertContractor = $localPdo->prepare(
                    'INSERT INTO contractors (name, inn, kpp, status, created_by_user_id, created_by_role)
                     VALUES (:name, :inn, :kpp, :status, :uid, :role)'
                );
                $insertContractor->execute([
                    ':name'   => $name,
                    ':inn'    => $inn,
                    ':kpp'    => $kpp !== '' ? $kpp : null,
                    ':status' => 'active',
                    ':uid'    => $uid,
                    ':role'   => $role,
                ]);
                $contractorId = (int)$localPdo->lastInsertId();
                ContractorContactService::replaceForContractor(
                    $localPdo,
                    $contractorId,
                    $phone !== '' ? [[
                        'contact_person'      => null,
                        'phone'               => $phone,
                        'email'               => null,
                        'comment'             => null,
                        'is_primary'          => 1,
                        'is_document_email'   => 0,
                    ]] : [],
                    $uid,
                    (string) ($role ?? '')
                );
                $contractorNameForSuccess = $name;
                $contractorInnForSuccess = $inn;
            }

            // 2. Driver — use existing or create new
            $driverId = null;
            $driverNameForSuccess = '';
            if ($driverMode === 'existing') {
                $driverId = (int)$driverIdExisting;
                // Fetch name for success display
                $drvStmt = $localPdo->prepare('SELECT full_name FROM drivers WHERE id = ?');
                $drvStmt->execute([$driverId]);
                $drvRow = $drvStmt->fetch(PDO::FETCH_ASSOC);
                $driverNameForSuccess = $drvRow['full_name'] ?? '';
            } else {
                $insertDriver = $localPdo->prepare(
                    'INSERT INTO drivers (full_name, phone, status, created_by_user_id, created_by_role)
                     VALUES (:full_name, :phone, :status, :uid, :role)'
                );
                $insertDriver->execute([
                    ':full_name' => $driverFullName,
                    ':phone'     => $driverPhone !== '' ? $driverPhone : '',
                    ':status'    => 'active',
                    ':uid'       => $uid,
                    ':role'      => $role,
                ]);
                $driverId = (int)$localPdo->lastInsertId();
                $driverNameForSuccess = $driverFullName;
            }

            // 3. Vehicle — use existing or create new
            $vehicleSetId = null;
            $vehiclePrimaryPlateForSuccess = '';
            $vehicleSecondaryPlateForSuccess = null;
            if ($vehicleMode === 'existing') {
                $vehicleSetId = (int)$vehicleSetIdExisting;
                // Fetch plate numbers for success display
                $vsStmt = $localPdo->prepare(
                    'SELECT vu1.plate_number AS primary_plate, vu2.plate_number AS secondary_plate
                       FROM vehicle_sets vs
                       JOIN vehicle_units vu1 ON vs.primary_vehicle_unit_id = vu1.id
                       LEFT JOIN vehicle_units vu2 ON vs.secondary_vehicle_unit_id = vu2.id
                      WHERE vs.id = ?'
                );
                $vsStmt->execute([$vehicleSetId]);
                $vsRow = $vsStmt->fetch(PDO::FETCH_ASSOC);
                $vehiclePrimaryPlateForSuccess = $vsRow['primary_plate'] ?? '';
                $vehicleSecondaryPlateForSuccess = !empty($vsRow['secondary_plate']) ? $vsRow['secondary_plate'] : null;
            } else {
                // Create primary vehicle_unit
                $insertPrimaryUnit = $localPdo->prepare(
                    'INSERT INTO vehicle_units (plate_number, brand, model, unit_type, status, created_by_user_id, created_by_role)
                     VALUES (:plate_number, :brand, :model, :unit_type, :status, :uid, :role)'
                );
                $insertPrimaryUnit->execute([
                    ':plate_number' => $plateNumber,
                    ':brand'        => $brand !== '' ? $brand : null,
                    ':model'        => $model !== '' ? $model : null,
                    ':unit_type'    => 'tractor',
                    ':status'       => 'active',
                    ':uid'          => $uid,
                    ':role'         => $role,
                ]);
                $primaryUnitId = (int)$localPdo->lastInsertId();

                // Create secondary vehicle_unit if needed
                $secondaryUnitId = null;
                if ($secondaryPlateNumber !== '') {
                    $insertSecondaryUnit = $localPdo->prepare(
                        'INSERT INTO vehicle_units (plate_number, unit_type, status, created_by_user_id, created_by_role)
                         VALUES (:plate_number, :unit_type, :status, :uid, :role)'
                    );
                    $insertSecondaryUnit->execute([
                        ':plate_number' => $secondaryPlateNumber,
                        ':unit_type'    => 'trailer',
                        ':status'       => 'active',
                        ':uid'          => $uid,
                        ':role'         => $role,
                    ]);
                    $secondaryUnitId = (int)$localPdo->lastInsertId();
                }

                // Create vehicle_set
                $insertVehicleSet = $localPdo->prepare(
                    'INSERT INTO vehicle_sets (set_type, primary_vehicle_unit_id, secondary_vehicle_unit_id, status, created_by_user_id, created_by_role)
                     VALUES (:set_type, :primary_id, :secondary_id, :status, :uid, :role)'
                );
                $insertVehicleSet->execute([
                    ':set_type'     => $setType,
                    ':primary_id'   => $primaryUnitId,
                    ':secondary_id' => $secondaryUnitId,
                    ':status'       => 'active',
                    ':uid'          => $uid,
                    ':role'         => $role,
                ]);
                $vehicleSetId = (int)$localPdo->lastInsertId();
                $vehiclePrimaryPlateForSuccess = $plateNumber;
                $vehicleSecondaryPlateForSuccess = $secondaryPlateNumber !== '' ? $secondaryPlateNumber : null;
            }

            // 4. Check for existing driver_vehicle_block
            $existingBlock = $localPdo->prepare('SELECT id FROM driver_vehicle_blocks WHERE driver_id = ? AND vehicle_set_id = ?');
            $existingBlock->execute([$driverId, $vehicleSetId]);
            $blockRow = $existingBlock->fetch(PDO::FETCH_ASSOC);
            if ($blockRow) {
                $blockId = (int)$blockRow['id'];
            } else {
                $insertBlock = $localPdo->prepare(
                    'INSERT INTO driver_vehicle_blocks (driver_id, vehicle_set_id, status, created_by_user_id, created_by_role)
                     VALUES (:driver_id, :vehicle_set_id, :status, :uid, :role)'
                );
                $insertBlock->execute([
                    ':driver_id'       => $driverId,
                    ':vehicle_set_id'  => $vehicleSetId,
                    ':status'          => 'active',
                    ':uid'             => $uid,
                    ':role'            => $role,
                ]);
                $blockId = (int)$localPdo->lastInsertId();
            }

            // 5. Create crew (with duplicate check)
            $existingCrew = $localPdo->prepare('SELECT id FROM crews WHERE contractor_id = ? AND driver_vehicle_block_id = ?');
            $existingCrew->execute([$contractorId, $blockId]);
            if (!$existingCrew->fetch()) {
                $insertCrew = $localPdo->prepare(
                    'INSERT INTO crews (contractor_id, driver_vehicle_block_id, status, created_by_user_id, created_by_role)
                     VALUES (:contractor_id, :driver_vehicle_block_id, :status, :uid, :role)'
                );
                $insertCrew->execute([
                    ':contractor_id'           => $contractorId,
                    ':driver_vehicle_block_id' => $blockId,
                    ':status'                  => 'active',
                    ':uid'                     => $uid,
                    ':role'                    => $role,
                ]);
            }

            // Capture crew ID for success display
            $crewFetch = $localPdo->prepare('SELECT id FROM crews WHERE contractor_id = ? AND driver_vehicle_block_id = ?');
            $crewFetch->execute([$contractorId, $blockId]);
            $crewRow = $crewFetch->fetch(PDO::FETCH_ASSOC);
            $crewId = $crewRow ? (int)$crewRow['id'] : 0;

            $localPdo->commit();

            $createdContractor = ['id' => $contractorId, 'name' => $contractorNameForSuccess, 'inn' => $contractorInnForSuccess];
            $createdDriver = ['id' => $driverId, 'full_name' => $driverNameForSuccess];
            $createdVehicleSet = [
                'id'              => $vehicleSetId,
                'primary_plate'   => $vehiclePrimaryPlateForSuccess,
                'secondary_plate' => $vehicleSecondaryPlateForSuccess,
            ];
            $createdBlock = ['id' => $blockId];
            $createdCrew = ['id' => $crewId];
            $success = true;
        } catch (\Exception $e) {
            $localPdo->rollBack();
            throw $e;
        }
    } catch (\Exception $e) {
        $company = $company ?? null;
        $formError = 'Ошибка создания: ' . $e->getMessage();
    }

    // Re-load entity lists for view in case of error (if not already loaded)
    if (!isset($contractors) || !is_array($contractors)) { $contractors = []; }
    if (!isset($drivers) || !is_array($drivers)) { $drivers = []; }
    if (!isset($vehicleSets) || !is_array($vehicleSets)) { $vehicleSets = []; }

    ob_start();
    require base_path('app/View/pages/company_contractors_create_full.php');
    $content = ob_get_clean();
    require base_path('app/View/layouts/main.php');
});

// ====================================================================
// MASTER-FLOW: Add crew to existing contractor
// ====================================================================
$router->get('/company/contractors/{id}/add-crew', function ($id) use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);
    $pageTitle = 'Добавить экипаж перевозчику';
    $pageContext = 'Перевозчики › Компания';

    $companyId = (int)(getSessionCompanyId() ?? 0);
    $contractorId = (int)$id;
    $drivers = [];
    $vehicleSets = [];
    $driverVehicleBlocks = [];

    if ($companyId <= 0) {
        $company = null;
        $contractor = null;
        $success = false;
        $errors = [];
        $old = [];
        $formError = null;
        $dbError = null;

        ob_start();
        require base_path('app/View/pages/company_contractor_add_crew.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
        return;
    }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company) {
            $company = null;
            $contractor = null;
            $success = false;
            $errors = [];
            $old = [];
            $formError = null;
            $dbError = null;

            ob_start();
            require base_path('app/View/pages/company_contractor_add_crew.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $pageContext = 'Перевозчики › Компания: ' . $company['name'];

        if ($company['status'] !== 'active') {
            $contractor = null;
            $success = false;
            $errors = [];
            $old = [];
            $formError = null;
            $dbError = null;

            ob_start();
            require base_path('app/View/pages/company_contractor_add_crew.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $dbIdentifier = $company['db_identifier'];
        $localDbConfig = $config['database'];
        $localDbConfig['database'] = $dbIdentifier;
        $localDb = new \App\Core\Database($localDbConfig);
        $localPdo = $localDb->connection();
        applyLocalMigrations($localPdo);

        // Ensure required tables
        try { $localPdo->query("SELECT 1 FROM contractors LIMIT 1")->fetch(); } catch (\Exception $e) { $localPdo->exec(file_get_contents(base_path('database/migrations-local/003_create_company_contractors.sql'))); }
        try { $localPdo->query("SELECT 1 FROM drivers LIMIT 1")->fetch(); } catch (\Exception $e) { $localPdo->exec(file_get_contents(base_path('database/migrations-local/004_create_company_drivers.sql'))); }
        try { $localPdo->query("SELECT 1 FROM vehicle_sets LIMIT 1")->fetch(); } catch (\Exception $e) { $localPdo->exec(file_get_contents(base_path('database/migrations-local/017_create_vehicle_sets.sql'))); }
        try { $localPdo->query("SELECT 1 FROM driver_vehicle_blocks LIMIT 1")->fetch(); } catch (\Exception $e) { $localPdo->exec(file_get_contents(base_path('database/migrations-local/018_create_driver_vehicle_blocks.sql'))); }
        try { $localPdo->query("SELECT 1 FROM crews LIMIT 1")->fetch(); } catch (\Exception $e) { $localPdo->exec(file_get_contents(base_path('database/migrations-local/006_create_company_crews.sql'))); }

        // Load contractor
        $contractorStmt = $localPdo->prepare('SELECT * FROM contractors WHERE id = ?');
        $contractorStmt->execute([$contractorId]);
        $contractor = $contractorStmt->fetch(PDO::FETCH_ASSOC) ?: null;

        if (!$contractor) {
            $dbError = null;
            $success = false;
            $errors = [];
            $old = [];
            $formError = null;

            ob_start();
            require base_path('app/View/pages/company_contractor_add_crew.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        // Load drivers
        $isLogist = ($_SESSION['role_code'] ?? '') === 'logist';
        $userId = (int)$_SESSION['user_id'];

        try {
            if ($isLogist) {
                $driversStmt = $localPdo->prepare(
                    "SELECT id, full_name, phone FROM drivers WHERE status IN ('active', 'archived') AND (created_by_user_id = ? OR id IN (SELECT entity_id FROM entity_access_grants WHERE entity_type = 'driver' AND granted_to_user_id = ? AND access_level IN ('view','edit') AND revoked_at IS NULL)) ORDER BY full_name"
                );
                $driversStmt->execute([$userId, $userId]);
                $drivers = $driversStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            } else {
                $driversStmt = $localPdo->query(
                    "SELECT id, full_name, phone FROM drivers WHERE status IN ('active', 'archived') ORDER BY full_name"
                );
                $drivers = $driversStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            }
        } catch (\Exception $e) {
            $drivers = [];
        }

        // Load vehicle sets
        try {
            if ($isLogist) {
                $vehicleSetsStmt = $localPdo->prepare(
                    "SELECT vs.id, vs.set_type,
                            vu1.plate_number AS primary_plate,
                            vu2.plate_number AS secondary_plate
                       FROM vehicle_sets vs
                       JOIN vehicle_units vu1 ON vs.primary_vehicle_unit_id = vu1.id
                       LEFT JOIN vehicle_units vu2 ON vs.secondary_vehicle_unit_id = vu2.id
                      WHERE vs.status IN ('active', 'archived')
                        AND (vs.created_by_user_id = ? OR vs.id IN (SELECT entity_id FROM entity_access_grants WHERE entity_type = 'vehicle_set' AND granted_to_user_id = ? AND access_level IN ('view','edit') AND revoked_at IS NULL))
                      ORDER BY vs.id DESC"
                );
                $vehicleSetsStmt->execute([$userId, $userId]);
                $vehicleSets = $vehicleSetsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            } else {
                $vehicleSetsStmt = $localPdo->query(
                    "SELECT vs.id, vs.set_type,
                            vu1.plate_number AS primary_plate,
                            vu2.plate_number AS secondary_plate
                       FROM vehicle_sets vs
                       JOIN vehicle_units vu1 ON vs.primary_vehicle_unit_id = vu1.id
                       LEFT JOIN vehicle_units vu2 ON vs.secondary_vehicle_unit_id = vu2.id
                      WHERE vs.status IN ('active', 'archived')
                      ORDER BY vs.id DESC"
                );
                $vehicleSets = $vehicleSetsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            }
        } catch (\Exception $e) {
            $vehicleSets = [];
        }

        // Load driver-vehicle blocks not already crewed with this contractor
        try {
            if ($isLogist) {
                $dvbStmt = $localPdo->prepare(
                    "SELECT dvb.id, d.full_name AS driver_name, d.id AS driver_id,
                            vs.id AS vehicle_set_id, vs.set_type,
                            vu1.plate_number AS primary_plate,
                            vu2.plate_number AS secondary_plate
                       FROM driver_vehicle_blocks dvb
                       JOIN drivers d ON dvb.driver_id = d.id
                       JOIN vehicle_sets vs ON dvb.vehicle_set_id = vs.id
                       JOIN vehicle_units vu1 ON vs.primary_vehicle_unit_id = vu1.id
                       LEFT JOIN vehicle_units vu2 ON vs.secondary_vehicle_unit_id = vu2.id
                      WHERE dvb.status = 'active'
                        AND dvb.id NOT IN (
                            SELECT driver_vehicle_block_id FROM crews
                             WHERE contractor_id = ? AND status != 'archived'
                        )
                        AND (dvb.created_by_user_id = ? OR dvb.id IN (SELECT entity_id FROM entity_access_grants WHERE entity_type = 'driver_vehicle_block' AND granted_to_user_id = ? AND access_level IN ('view','edit') AND revoked_at IS NULL))
                      ORDER BY d.full_name"
                );
                $dvbStmt->execute([$contractorId, $userId, $userId]);
                $driverVehicleBlocks = $dvbStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            } else {
                $dvbStmt = $localPdo->prepare(
                    "SELECT dvb.id, d.full_name AS driver_name, d.id AS driver_id,
                            vs.id AS vehicle_set_id, vs.set_type,
                            vu1.plate_number AS primary_plate,
                            vu2.plate_number AS secondary_plate
                       FROM driver_vehicle_blocks dvb
                       JOIN drivers d ON dvb.driver_id = d.id
                       JOIN vehicle_sets vs ON dvb.vehicle_set_id = vs.id
                       JOIN vehicle_units vu1 ON vs.primary_vehicle_unit_id = vu1.id
                       LEFT JOIN vehicle_units vu2 ON vs.secondary_vehicle_unit_id = vu2.id
                      WHERE dvb.status = 'active'
                        AND dvb.id NOT IN (
                            SELECT driver_vehicle_block_id FROM crews
                             WHERE contractor_id = ? AND status != 'archived'
                        )
                      ORDER BY d.full_name"
                );
                $dvbStmt->execute([$contractorId]);
                $driverVehicleBlocks = $dvbStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            }
        } catch (\Exception $e) {
            $driverVehicleBlocks = [];
        }

        $success = false;
        $errors = [];
        $old = [];
        $formError = null;
        $dbError = null;
    } catch (\Exception $e) {
        $company = null;
        $contractor = null;
        $success = false;
        $errors = [];
        $old = [];
        $formError = 'Ошибка загрузки данных: ' . $e->getMessage();
        $dbError = null;
    }

    ob_start();
    require base_path('app/View/pages/company_contractor_add_crew.php');
    $content = ob_get_clean();
    require base_path('app/View/layouts/main.php');
});

$router->post('/company/contractors/{id}/add-crew', function ($id) use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);
    $pageTitle = 'Добавить экипаж перевозчику';
    $pageContext = 'Перевозчики › Компания';

    $companyId = (int)(getSessionCompanyId() ?? 0);
    $contractorId = (int)$id;
    $errors = [];
    $old = $_POST;
    $formError = null;
    $success = false;
    $createdDriver = null;
    $createdVehicleSet = null;
    $createdBlock = null;
    $createdCrew = null;
    $drivers = [];
    $vehicleSets = [];
    $driverVehicleBlocks = [];

    if ($companyId <= 0) {
        $company = null;
        $contractor = null;
        $formError = 'Компания не найдена';
        $dbError = null;

        ob_start();
        require base_path('app/View/pages/company_contractor_add_crew.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
        return;
    }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company) {
            $company = null;
            $contractor = null;
            $formError = 'Компания не найдена';
            $dbError = null;

            ob_start();
            require base_path('app/View/pages/company_contractor_add_crew.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $pageContext = 'Перевозчики › Компания: ' . $company['name'];

        if ($company['status'] !== 'active') {
            $contractor = null;
            $formError = 'Создание недоступно';
            $dbError = null;

            ob_start();
            require base_path('app/View/pages/company_contractor_add_crew.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $dbIdentifier = $company['db_identifier'];
        $localDbConfig = $config['database'];
        $localDbConfig['database'] = $dbIdentifier;
        $localDb = new \App\Core\Database($localDbConfig);
        $localPdo = $localDb->connection();
        applyLocalMigrations($localPdo);

        // Ensure required tables
        $requiredTables = [
            'contractors' => 'database/migrations-local/003_create_company_contractors.sql',
            'drivers'     => 'database/migrations-local/004_create_company_drivers.sql',
            'vehicle_units' => 'database/migrations-local/005_create_company_vehicles.sql',
            'vehicle_sets' => 'database/migrations-local/017_create_vehicle_sets.sql',
            'driver_vehicle_blocks' => 'database/migrations-local/018_create_driver_vehicle_blocks.sql',
            'crews'       => 'database/migrations-local/006_create_company_crews.sql',
        ];
        foreach ($requiredTables as $table => $migrationFile) {
            try {
                $localPdo->query("SELECT 1 FROM `$table` LIMIT 1")->fetch();
            } catch (\Exception $e) {
                $migrationSql = file_get_contents(base_path($migrationFile));
                if ($migrationSql !== false && trim($migrationSql) !== '') {
                    $localPdo->exec($migrationSql);
                }
            }
        }

        // Handle vehicles -> vehicle_units rename if needed
        try {
            $localPdo->query("SELECT 1 FROM vehicle_units LIMIT 1")->fetch();
        } catch (\Exception $e) {
            try { $localPdo->exec("RENAME TABLE vehicles TO vehicle_units"); } catch (\Exception $renameEx) {}
        }

        // Load contractor
        $contractorStmt = $localPdo->prepare('SELECT * FROM contractors WHERE id = ?');
        $contractorStmt->execute([$contractorId]);
        $contractor = $contractorStmt->fetch(PDO::FETCH_ASSOC) ?: null;

        if (!$contractor) {
            $dbError = null;
            $formError = 'Перевозчик не найден';

            ob_start();
            require base_path('app/View/pages/company_contractor_add_crew.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        // Load entity lists for dropdowns and error re-display
        $isLogist = ($_SESSION['role_code'] ?? '') === 'logist';
        $userId = (int)$_SESSION['user_id'];

        try {
            if ($isLogist) {
                $driversStmt = $localPdo->prepare(
                    "SELECT id, full_name, phone FROM drivers WHERE status IN ('active', 'archived') AND (created_by_user_id = ? OR id IN (SELECT entity_id FROM entity_access_grants WHERE entity_type = 'driver' AND granted_to_user_id = ? AND access_level IN ('view','edit') AND revoked_at IS NULL)) ORDER BY full_name"
                );
                $driversStmt->execute([$userId, $userId]);
                $drivers = $driversStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            } else {
                $driversStmt = $localPdo->query("SELECT id, full_name, phone FROM drivers WHERE status IN ('active', 'archived') ORDER BY full_name");
                $drivers = $driversStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            }
        } catch (\Exception $e) {}
        try {
            if ($isLogist) {
                $vehicleSetsStmt = $localPdo->prepare(
                    "SELECT vs.id, vs.set_type, vu1.plate_number AS primary_plate, vu2.plate_number AS secondary_plate FROM vehicle_sets vs JOIN vehicle_units vu1 ON vs.primary_vehicle_unit_id = vu1.id LEFT JOIN vehicle_units vu2 ON vs.secondary_vehicle_unit_id = vu2.id WHERE vs.status IN ('active', 'archived') AND (vs.created_by_user_id = ? OR vs.id IN (SELECT entity_id FROM entity_access_grants WHERE entity_type = 'vehicle_set' AND granted_to_user_id = ? AND access_level IN ('view','edit') AND revoked_at IS NULL)) ORDER BY vs.id DESC"
                );
                $vehicleSetsStmt->execute([$userId, $userId]);
                $vehicleSets = $vehicleSetsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            } else {
                $vehicleSetsStmt = $localPdo->query("SELECT vs.id, vs.set_type, vu1.plate_number AS primary_plate, vu2.plate_number AS secondary_plate FROM vehicle_sets vs JOIN vehicle_units vu1 ON vs.primary_vehicle_unit_id = vu1.id LEFT JOIN vehicle_units vu2 ON vs.secondary_vehicle_unit_id = vu2.id WHERE vs.status IN ('active', 'archived') ORDER BY vs.id DESC");
                $vehicleSets = $vehicleSetsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            }
        } catch (\Exception $e) {}

        // --- Determine modes ---
        $blockMode = trim($_POST['block_mode'] ?? 'existing');

        if ($blockMode === 'new') {
            $driverMode = trim($_POST['driver_mode'] ?? 'existing');
            $vehicleMode = trim($_POST['vehicle_mode'] ?? 'existing');
        } else {
            $driverMode = '';
            $vehicleMode = '';
        }

        // --- Validation ---
        if ($blockMode === 'existing') {
            // Validate existing driver_vehicle_block
            $blockIdExisting = trim($_POST['block_id'] ?? '');
            if ($blockIdExisting === '') {
                $errors['block_id'] = 'Выберите связку Водитель+ТС';
            } else {
                $checkStmt = $localPdo->prepare(
                    'SELECT dvb.id, d.full_name AS driver_name, d.id AS driver_id,
                            vs.id AS vehicle_set_id,
                            vu1.plate_number AS primary_plate,
                            vu2.plate_number AS secondary_plate
                       FROM driver_vehicle_blocks dvb
                       JOIN drivers d ON dvb.driver_id = d.id
                       JOIN vehicle_sets vs ON dvb.vehicle_set_id = vs.id
                       JOIN vehicle_units vu1 ON vs.primary_vehicle_unit_id = vu1.id
                       LEFT JOIN vehicle_units vu2 ON vs.secondary_vehicle_unit_id = vu2.id
                      WHERE dvb.id = ? AND dvb.status = \'active\''
                );
                $checkStmt->execute([(int)$blockIdExisting]);
                $existingBlockRow = $checkStmt->fetch(PDO::FETCH_ASSOC);
                if (!$existingBlockRow) {
                    $errors['block_id'] = 'Связка Водитель+ТС не найдена или неактивна';
                } elseif ($isLogist) {
                    // Backend validation: logist can only use their own blocks
                    $bOwnerCheck = $localPdo->prepare("SELECT created_by_user_id FROM driver_vehicle_blocks WHERE id = ?");
                    $bOwnerCheck->execute([(int)$blockIdExisting]);
                    $bOwner = $bOwnerCheck->fetchColumn();
                    if ($bOwner !== false && (int)$bOwner !== $userId) {
                        $bGrantCheck = $localPdo->prepare("SELECT COUNT(*) FROM entity_access_grants WHERE entity_type = 'driver_vehicle_block' AND entity_id = ? AND granted_to_user_id = ? AND access_level IN ('view','edit') AND revoked_at IS NULL");
                        $bGrantCheck->execute([(int)$blockIdExisting, $userId]);
                        if ($bGrantCheck->fetchColumn() == 0) {
                            $errors['block_id'] = 'Связка недоступна.';
                        }
                    }
                }
            }
        } else {
            $driverFullName = trim($_POST['driver_full_name'] ?? '');
            $driverPhone = trim($_POST['driver_phone'] ?? '');
            $driverIdExisting = trim($_POST['driver_id'] ?? '');

            $plateNumber = trim($_POST['plate_number'] ?? '');
            $brand = trim($_POST['brand'] ?? '');
            $model = trim($_POST['model'] ?? '');
            $setType = trim($_POST['set_type'] ?? 'single');
            $secondaryPlateNumber = trim($_POST['secondary_plate_number'] ?? '');
            $vehicleSetIdExisting = trim($_POST['vehicle_set_id'] ?? '');

            // Driver validation
            if ($driverMode === 'existing') {
                if ($driverIdExisting === '') {
                    $errors['driver_id'] = 'Выберите водителя';
                } else {
                    $checkStmt = $localPdo->prepare('SELECT id, full_name FROM drivers WHERE id = ?');
                    $checkStmt->execute([(int)$driverIdExisting]);
                    if (!$checkStmt->fetch(PDO::FETCH_ASSOC)) {
                        $errors['driver_id'] = 'Водитель не найден';
                    }
                }
            } else {
                if ($driverFullName === '') {
                    $errors['driver_full_name'] = 'Обязательное поле';
                }
            }

            // Vehicle validation
            if ($vehicleMode === 'existing') {
                if ($vehicleSetIdExisting === '') {
                    $errors['vehicle_set_id'] = 'Выберите транспорт';
                } else {
                    $checkStmt = $localPdo->prepare('SELECT id FROM vehicle_sets WHERE id = ?');
                    $checkStmt->execute([(int)$vehicleSetIdExisting]);
                    if (!$checkStmt->fetch(PDO::FETCH_ASSOC)) {
                        $errors['vehicle_set_id'] = 'Транспорт не найден';
                    }
                }
            } else {
                if ($plateNumber === '') {
                    $errors['plate_number'] = 'Обязательное поле';
                } else {
                    $checkStmt = $localPdo->prepare('SELECT COUNT(*) FROM vehicle_units WHERE plate_number = ?');
                    $checkStmt->execute([$plateNumber]);
                    if ($checkStmt->fetchColumn() > 0) {
                        $errors['plate_number'] = 'Госномер уже используется в этой компании';
                    }
                }
                if (($setType === 'coupling' || $setType === 'road_train') && $secondaryPlateNumber === '') {
                    $errors['secondary_plate_number'] = 'Обязательно для сцепки и автопоезда';
                }
                if ($secondaryPlateNumber !== '' && empty($errors['plate_number'])) {
                    $checkStmt = $localPdo->prepare('SELECT COUNT(*) FROM vehicle_units WHERE plate_number = ?');
                    $checkStmt->execute([$secondaryPlateNumber]);
                    if ($checkStmt->fetchColumn() > 0) {
                        $errors['secondary_plate_number'] = 'Госномер уже используется в этой компании';
                    }
                }
            }
        }

        if (!empty($errors)) {
            // Re-load entity lists for error re-display
            try {
                $dvbStmt = $localPdo->prepare(
                    "SELECT dvb.id, d.full_name AS driver_name, d.id AS driver_id,
                            vs.id AS vehicle_set_id, vs.set_type,
                            vu1.plate_number AS primary_plate,
                            vu2.plate_number AS secondary_plate
                       FROM driver_vehicle_blocks dvb
                       JOIN drivers d ON dvb.driver_id = d.id
                       JOIN vehicle_sets vs ON dvb.vehicle_set_id = vs.id
                       JOIN vehicle_units vu1 ON vs.primary_vehicle_unit_id = vu1.id
                       LEFT JOIN vehicle_units vu2 ON vs.secondary_vehicle_unit_id = vu2.id
                      WHERE dvb.status = 'active'
                        AND dvb.id NOT IN (
                            SELECT driver_vehicle_block_id FROM crews
                             WHERE contractor_id = ? AND status != 'archived'
                        )
                      ORDER BY d.full_name"
                );
                $dvbStmt->execute([$contractorId]);
                $driverVehicleBlocks = $dvbStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            } catch (\Exception $e) {}
            $dbError = null;
            ob_start();
            require base_path('app/View/pages/company_contractor_add_crew.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        // --- Transactional creation ---
        $localPdo->beginTransaction();
        try {
            $uid = (int)$_SESSION['user_id'];
            $role = $_SESSION['role_code'];

            if ($blockMode === 'existing') {
                // Use existing driver_vehicle_block — just create crew
                $blockId = (int)$blockIdExisting;
                $driverNameForSuccess = $existingBlockRow['driver_name'] ?? '';
                $driverId = (int)($existingBlockRow['driver_id'] ?? 0);
                $vehicleSetId = (int)($existingBlockRow['vehicle_set_id'] ?? 0);
                $vehiclePrimaryPlateForSuccess = $existingBlockRow['primary_plate'] ?? '';
                $vehicleSecondaryPlateForSuccess = (!empty($existingBlockRow['secondary_plate']) ? $existingBlockRow['secondary_plate'] : null);
            } else {
                // 1. Driver — use existing or create new
                $driverId = null;
                $driverNameForSuccess = '';
                if ($driverMode === 'existing') {
                    $driverId = (int)$driverIdExisting;
                    $drvStmt = $localPdo->prepare('SELECT full_name FROM drivers WHERE id = ?');
                    $drvStmt->execute([$driverId]);
                    $drvRow = $drvStmt->fetch(PDO::FETCH_ASSOC);
                    $driverNameForSuccess = $drvRow['full_name'] ?? '';
                } else {
                    $insertDriver = $localPdo->prepare(
                        'INSERT INTO drivers (full_name, phone, status, created_by_user_id, created_by_role)
                         VALUES (:full_name, :phone, :status, :uid, :role)'
                    );
                    $insertDriver->execute([
                        ':full_name' => $driverFullName,
                        ':phone'     => $driverPhone !== '' ? $driverPhone : '',
                        ':status'    => 'active',
                        ':uid'       => $uid,
                        ':role'      => $role,
                    ]);
                    $driverId = (int)$localPdo->lastInsertId();
                    $driverNameForSuccess = $driverFullName;
                }

                // 2. Vehicle — use existing or create new
                $vehicleSetId = null;
                $vehiclePrimaryPlateForSuccess = '';
                $vehicleSecondaryPlateForSuccess = null;
                if ($vehicleMode === 'existing') {
                    $vehicleSetId = (int)$vehicleSetIdExisting;
                    $vsStmt = $localPdo->prepare(
                        'SELECT vu1.plate_number AS primary_plate, vu2.plate_number AS secondary_plate
                           FROM vehicle_sets vs
                           JOIN vehicle_units vu1 ON vs.primary_vehicle_unit_id = vu1.id
                           LEFT JOIN vehicle_units vu2 ON vs.secondary_vehicle_unit_id = vu2.id
                          WHERE vs.id = ?'
                    );
                    $vsStmt->execute([$vehicleSetId]);
                    $vsRow = $vsStmt->fetch(PDO::FETCH_ASSOC);
                    $vehiclePrimaryPlateForSuccess = $vsRow['primary_plate'] ?? '';
                    $vehicleSecondaryPlateForSuccess = !empty($vsRow['secondary_plate']) ? $vsRow['secondary_plate'] : null;
                } else {
                    $insertPrimaryUnit = $localPdo->prepare(
                        'INSERT INTO vehicle_units (plate_number, brand, model, unit_type, status, created_by_user_id, created_by_role)
                         VALUES (:plate_number, :brand, :model, :unit_type, :status, :uid, :role)'
                    );
                    $insertPrimaryUnit->execute([
                        ':plate_number' => $plateNumber,
                        ':brand'        => $brand !== '' ? $brand : null,
                        ':model'        => $model !== '' ? $model : null,
                        ':unit_type'    => 'tractor',
                        ':status'       => 'active',
                        ':uid'          => $uid,
                        ':role'         => $role,
                    ]);
                    $primaryUnitId = (int)$localPdo->lastInsertId();

                    $secondaryUnitId = null;
                    if ($secondaryPlateNumber !== '') {
                        $insertSecondaryUnit = $localPdo->prepare(
                            'INSERT INTO vehicle_units (plate_number, unit_type, status, created_by_user_id, created_by_role)
                             VALUES (:plate_number, :unit_type, :status, :uid, :role)'
                        );
                        $insertSecondaryUnit->execute([
                            ':plate_number' => $secondaryPlateNumber,
                            ':unit_type'    => 'trailer',
                            ':status'       => 'active',
                            ':uid'          => $uid,
                            ':role'         => $role,
                        ]);
                        $secondaryUnitId = (int)$localPdo->lastInsertId();
                    }

                    $insertVehicleSet = $localPdo->prepare(
                        'INSERT INTO vehicle_sets (set_type, primary_vehicle_unit_id, secondary_vehicle_unit_id, status, created_by_user_id, created_by_role)
                         VALUES (:set_type, :primary_id, :secondary_id, :status, :uid, :role)'
                    );
                    $insertVehicleSet->execute([
                        ':set_type'     => $setType,
                        ':primary_id'   => $primaryUnitId,
                        ':secondary_id' => $secondaryUnitId,
                        ':status'       => 'active',
                        ':uid'          => $uid,
                        ':role'         => $role,
                    ]);
                    $vehicleSetId = (int)$localPdo->lastInsertId();
                    $vehiclePrimaryPlateForSuccess = $plateNumber;
                    $vehicleSecondaryPlateForSuccess = $secondaryPlateNumber !== '' ? $secondaryPlateNumber : null;
                }

                // 3. Find or create driver_vehicle_block
                $existingBlock = $localPdo->prepare('SELECT id FROM driver_vehicle_blocks WHERE driver_id = ? AND vehicle_set_id = ?');
                $existingBlock->execute([$driverId, $vehicleSetId]);
                $blockRow = $existingBlock->fetch(PDO::FETCH_ASSOC);
                if ($blockRow) {
                    $blockId = (int)$blockRow['id'];
                } else {
                    $insertBlock = $localPdo->prepare(
                        'INSERT INTO driver_vehicle_blocks (driver_id, vehicle_set_id, status, created_by_user_id, created_by_role)
                         VALUES (:driver_id, :vehicle_set_id, :status, :uid, :role)'
                    );
                    $insertBlock->execute([
                        ':driver_id'       => $driverId,
                        ':vehicle_set_id'  => $vehicleSetId,
                        ':status'          => 'active',
                        ':uid'             => $uid,
                        ':role'            => $role,
                    ]);
                    $blockId = (int)$localPdo->lastInsertId();
                }
            }

            // 4. Create crew (with duplicate check)
            $existingCrew = $localPdo->prepare('SELECT id FROM crews WHERE contractor_id = ? AND driver_vehicle_block_id = ?');
            $existingCrew->execute([$contractorId, $blockId]);
            $crewRow = $existingCrew->fetch(PDO::FETCH_ASSOC);
            if ($crewRow) {
                $crewId = (int)$crewRow['id'];
            } else {
                $insertCrew = $localPdo->prepare(
                    'INSERT INTO crews (contractor_id, driver_vehicle_block_id, status, created_by_user_id, created_by_role)
                     VALUES (:contractor_id, :driver_vehicle_block_id, :status, :uid, :role)'
                );
                $insertCrew->execute([
                    ':contractor_id'           => $contractorId,
                    ':driver_vehicle_block_id' => $blockId,
                    ':status'                  => 'active',
                    ':uid'                     => $uid,
                    ':role'                    => $role,
                ]);
                $crewId = (int)$localPdo->lastInsertId();
            }

            $localPdo->commit();

            $createdDriver = ['id' => $driverId, 'full_name' => $driverNameForSuccess];
            $createdVehicleSet = [
                'id'              => $vehicleSetId,
                'primary_plate'   => $vehiclePrimaryPlateForSuccess,
                'secondary_plate' => $vehicleSecondaryPlateForSuccess,
            ];
            $createdBlock = ['id' => $blockId];
            $createdCrew = ['id' => $crewId];
            $success = true;
            $dbError = null;
        } catch (\Exception $e) {
            $localPdo->rollBack();
            throw $e;
        }
    } catch (\Exception $e) {
        $company = $company ?? null;
        $contractor = $contractor ?? null;
        $formError = 'Ошибка создания: ' . $e->getMessage();
        $dbError = null;
    }

    // Re-load entity lists for view in case of error
    if (!isset($drivers) || !is_array($drivers)) { $drivers = []; }
    if (!isset($vehicleSets) || !is_array($vehicleSets)) { $vehicleSets = []; }

    ob_start();
    require base_path('app/View/pages/company_contractor_add_crew.php');
    $content = ob_get_clean();
    require base_path('app/View/layouts/main.php');
});

$router->get('/company/contractors/{id}', function ($id) use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);
    $pageTitle = 'Перевозчик';
    $pageContext = 'Перевозчики › Компания';

    $companyId = (int)(getSessionCompanyId() ?? 0);
    $archiveError = null;
    $grants = [];
    $logists = [];
    $crewBlocks = [];

    if ($companyId <= 0) {
        $company = null;
        $contractor = null;
        $dbError = null;

        ob_start();
        require base_path('app/View/pages/company_contractor_view.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
        return;
    }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company) {
            $company = null;
            $contractor = null;
            $dbError = null;

            ob_start();
            require base_path('app/View/pages/company_contractor_view.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $pageContext = 'Перевозчики › Компания: ' . $company['name'];

        if ($company['status'] !== 'active') {
            $contractor = null;
            $dbError = null;

            ob_start();
            require base_path('app/View/pages/company_contractor_view.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $dbIdentifier = $company['db_identifier'];
        $localDbConfig = $config['database'];
        $localDbConfig['database'] = $dbIdentifier;
        $localDb = new \App\Core\Database($localDbConfig);
        $localPdo = $localDb->connection();
                applyLocalMigrations($localPdo);

        try {
            $localPdo->query("SELECT 1 FROM contractors LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $migrationSql = file_get_contents(base_path('database/migrations-local/003_create_company_contractors.sql'));
            $localPdo->exec($migrationSql);
        }

        try {
            $localPdo->query("SELECT created_by_user_id FROM contractors LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $localPdo->exec("ALTER TABLE contractors ADD COLUMN created_by_user_id INT UNSIGNED DEFAULT NULL, ADD COLUMN created_by_role VARCHAR(20) DEFAULT NULL");
        }

        try {
            $localPdo->query("SELECT 1 FROM entity_access_grants LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $migrationSql = file_get_contents(base_path('database/migrations-local/009_create_entity_access_grants.sql'));
            $localPdo->exec($migrationSql);
        }

        $contractorStmt = $localPdo->prepare('SELECT * FROM contractors WHERE id = ?');
        $contractorStmt->execute([(int) $id]);
        $contractor = $contractorStmt->fetch(PDO::FETCH_ASSOC) ?: null;

        // Role-based access check
        $accessDenied = null;
        $createdByUser = null;
        $updatedByUser = null;
        if ($contractor) {
            $isLogist = ($_SESSION['role_code'] ?? '') === 'logist';
            if ($isLogist) {
                $userId = (int)$_SESSION['user_id'];
                $hasGrant = false;
                $grantCheck = $localPdo->prepare("SELECT access_level FROM entity_access_grants WHERE entity_type = 'contractor' AND entity_id = ? AND granted_to_user_id = ? AND revoked_at IS NULL LIMIT 1");
                $grantCheck->execute([(int)$id, $userId]);
                $grantRow = $grantCheck->fetch(PDO::FETCH_ASSOC);
                $hasGrant = ($grantRow && in_array($grantRow['access_level'], ['view', 'edit']));
                if ((int)$contractor['created_by_user_id'] !== $userId && !$hasGrant) {
                    $accessDenied = 'У вас нет доступа к этой записи.';
                }
            }
            // Load created/updated by user names
            $createdByUser = $localPdo->prepare("SELECT full_name FROM users WHERE id = ?");
            $createdByUser->execute([(int)$contractor['created_by_user_id']]);
            $createdByUser = $createdByUser->fetchColumn() ?: null;
            if (!empty($contractor['updated_by_user_id'])) {
                $updatedByUser = $localPdo->prepare("SELECT full_name FROM users WHERE id = ?");
                $updatedByUser->execute([(int)$contractor['updated_by_user_id']]);
                $updatedByUser = $updatedByUser->fetchColumn() ?: null;
            }
        }

        if ($contractor && !$accessDenied) {
            $pageTitle = 'Перевозчик: ' . $contractor['name'];
        }

        $contacts = [];
        if ($contractor && !$accessDenied) {
            $contacts = ContractorContactService::loadByContractorId($localPdo, (int) $id);
        }

        // Load tax history
        $taxHistory = [];
        if ($contractor && !$accessDenied) {
            $taxHistory = $localPdo->prepare("SELECT * FROM contractor_tax_history WHERE contractor_id = ? ORDER BY effective_from DESC, id DESC");
            $taxHistory->execute([(int)$id]);
            $taxHistory = $taxHistory->fetchAll(PDO::FETCH_ASSOC);
        }

        // Load crew blocks (Водители+ТС)
        if ($contractor && !$accessDenied) {
            try {
                $crewBlockStmt = $localPdo->prepare(
                    "SELECT c.id AS crew_id, dvb.id AS block_id, dvb.status AS block_status,
                            d.full_name AS driver_name, d.id AS driver_id,
                            vs.id AS vehicle_set_id,
                            vs.primary_vehicle_unit_id, vs.secondary_vehicle_unit_id
                     FROM crews c
                     JOIN driver_vehicle_blocks dvb ON c.driver_vehicle_block_id = dvb.id
                     JOIN drivers d ON dvb.driver_id = d.id
                     JOIN vehicle_sets vs ON dvb.vehicle_set_id = vs.id
                     WHERE c.contractor_id = ? AND c.status != 'archived'
                     ORDER BY d.full_name"
                );
                $crewBlockStmt->execute([(int)$id]);
                $crewBlocks = $crewBlockStmt->fetchAll(PDO::FETCH_ASSOC);

                // Load vehicle plates
                if (!empty($crewBlocks)) {
                    $vehicleUnitIds = [];
                    foreach ($crewBlocks as $cb) {
                        if (!empty($cb['primary_vehicle_unit_id'])) {
                            $vehicleUnitIds[] = (int)$cb['primary_vehicle_unit_id'];
                        }
                        if (!empty($cb['secondary_vehicle_unit_id'])) {
                            $vehicleUnitIds[] = (int)$cb['secondary_vehicle_unit_id'];
                        }
                    }
                    $vehicleUnitIds = array_unique($vehicleUnitIds);
                    $vehicleUnitIds = array_values($vehicleUnitIds);

                    $plateMap = [];
                    if (!empty($vehicleUnitIds)) {
                        $placeholders = implode(',', array_fill(0, count($vehicleUnitIds), '?'));
                        $plateStmt = $localPdo->prepare("SELECT id, plate_number FROM vehicle_units WHERE id IN ($placeholders)");
                        $plateStmt->execute($vehicleUnitIds);
                        while ($row = $plateStmt->fetch(PDO::FETCH_ASSOC)) {
                            $plateMap[$row['id']] = $row['plate_number'];
                        }
                    }

                    // Enrich crewBlocks with plate info
                    foreach ($crewBlocks as &$cb) {
                        $primaryPlate = $plateMap[$cb['primary_vehicle_unit_id']] ?? null;
                        $secondaryPlate = !empty($cb['secondary_vehicle_unit_id'])
                            ? ($plateMap[$cb['secondary_vehicle_unit_id']] ?? null)
                            : null;

                        $cb['vehicle_plate'] = $primaryPlate ?: 'ТС #' . $cb['vehicle_set_id'];
                        $cb['secondary_plate'] = $secondaryPlate;
                    }
                    unset($cb);
                }
            } catch (\Exception $e) {
                $crewBlocks = [];
            }
        }

        $grants = [];
        $logists = [];
        $dbError = null;
    } catch (\Exception $e) {
        $company = $company ?? null;
        $contractor = null;
        $grants = [];
        $logists = [];
        $crewBlocks = [];
        $dbError = 'Не удалось загрузить перевозчика: ' . $e->getMessage();
    }

    ob_start();
    require base_path('app/View/pages/company_contractor_view.php');
    $content = ob_get_clean();
    require base_path('app/View/layouts/main.php');
});

$router->get('/company/contractors/{id}/edit', function ($id) use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);
    $pageTitle = 'Редактировать перевозчика';
    $pageContext = 'Перевозчики › Компания';

    $companyId = (int)(getSessionCompanyId() ?? 0);

    if ($companyId <= 0) {
        $company = null;
        $contractor = null;
        $contacts = [];
        $errors = [];
        $old = ['contacts' => contractorFormDefaultContacts()];
        $formError = null;

        ob_start();
        require base_path('app/View/pages/company_contractor_edit.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
        return;
    }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company) {
            $company = null;
            $contractor = null;
            $contacts = [];
            $errors = [];
            $old = ['contacts' => contractorFormDefaultContacts()];
            $formError = null;

            ob_start();
            require base_path('app/View/pages/company_contractor_edit.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $pageContext = 'Перевозчики › Компания: ' . $company['name'];

        if ($company['status'] !== 'active') {
            $contractor = null;
            $contacts = [];
            $errors = [];
            $old = ['contacts' => contractorFormDefaultContacts()];
            $formError = null;

            ob_start();
            require base_path('app/View/pages/company_contractor_edit.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $dbIdentifier = $company['db_identifier'];
        $localDbConfig = $config['database'];
        $localDbConfig['database'] = $dbIdentifier;
        $localDb = new \App\Core\Database($localDbConfig);
        $localPdo = $localDb->connection();
                applyLocalMigrations($localPdo);

        try {
            $localPdo->query("SELECT 1 FROM contractors LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $migrationSql = file_get_contents(base_path('database/migrations-local/003_create_company_contractors.sql'));
            $localPdo->exec($migrationSql);
        }

        $contractorStmt = $localPdo->prepare('SELECT * FROM contractors WHERE id = ?');
        $contractorStmt->execute([(int) $id]);
        $contractor = $contractorStmt->fetch(PDO::FETCH_ASSOC) ?: null;

        if (!$contractor) {
            $contractor = null;
            $contacts = [];
            $errors = [];
            $old = ['contacts' => contractorFormDefaultContacts()];
            $formError = null;

            ob_start();
            require base_path('app/View/pages/company_contractor_edit.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $contacts = ContractorContactService::loadByContractorId($localPdo, (int) $id);
        $errors = [];
        $old = $contractor;
        $old['contacts'] = $contacts !== [] ? $contacts : contractorFormDefaultContacts();
        $formError = null;

        ob_start();
        require base_path('app/View/pages/company_contractor_edit.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
    } catch (\Exception $e) {
        $company = $company ?? null;
        $contractor = null;
        $errors = [];
        $old = [];
        $formError = 'Не удалось загрузить перевозчика: ' . $e->getMessage();

        ob_start();
        require base_path('app/View/pages/company_contractor_edit.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
    }
});

$router->post('/company/contractors/{id}/edit', function ($id) use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);
    $pageTitle = 'Редактировать перевозчика';
    $pageContext = 'Перевозчики › Компания';

    $companyId = (int)(getSessionCompanyId() ?? 0);

    if ($companyId <= 0) {
        $company = null;
        $contractor = null;
        $errors = [];
        $old = $_POST;
        $formError = 'Компания не найдена';

        ob_start();
        require base_path('app/View/pages/company_contractor_edit.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
        return;
    }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company) {
            $company = null;
            $contractor = null;
            $errors = [];
            $old = $_POST;
            $formError = 'Компания не найдена';

            ob_start();
            require base_path('app/View/pages/company_contractor_edit.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $pageContext = 'Перевозчики › Компания: ' . $company['name'];

        if ($company['status'] !== 'active') {
            $contractor = null;
            $errors = [];
            $old = $_POST;
            $formError = 'Редактирование перевозчиков недоступно';

            ob_start();
            require base_path('app/View/pages/company_contractor_edit.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $dbIdentifier = $company['db_identifier'];
        $localDbConfig = $config['database'];
        $localDbConfig['database'] = $dbIdentifier;
        $localDb = new \App\Core\Database($localDbConfig);
        $localPdo = $localDb->connection();
                applyLocalMigrations($localPdo);

        try {
            $localPdo->query("SELECT 1 FROM contractors LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $migrationSql = file_get_contents(base_path('database/migrations-local/003_create_company_contractors.sql'));
            $localPdo->exec($migrationSql);
        }

        $contractorStmt = $localPdo->prepare('SELECT * FROM contractors WHERE id = ?');
        $contractorStmt->execute([(int) $id]);
        $contractor = $contractorStmt->fetch(PDO::FETCH_ASSOC) ?: null;

        if (!$contractor) {
            $contractor = null;
            $errors = [];
            $old = $_POST;
            $formError = 'Перевозчик не найден';

            ob_start();
            require base_path('app/View/pages/company_contractor_edit.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        // Access check for logist
        $isLogist = ($_SESSION['role_code'] ?? '') === 'logist';
        if ($isLogist) {
            $userId = (int)$_SESSION['user_id'];
            $hasGrantEdit = false;
            $gc = $localPdo->prepare("SELECT access_level FROM entity_access_grants WHERE entity_type = 'contractor' AND entity_id = ? AND granted_to_user_id = ? AND revoked_at IS NULL LIMIT 1");
            $gc->execute([(int)$id, $userId]);
            $gr = $gc->fetch(PDO::FETCH_ASSOC);
            $hasGrantEdit = ($gr && $gr['access_level'] === 'edit');
            if ((int)$contractor['created_by_user_id'] !== $userId && !$hasGrantEdit) {
                $errors = []; $old = $contractor;
                $formError = (int)$contractor['created_by_user_id'] !== $userId ? 'У вас есть доступ на просмотр, но нет права редактировать эту запись.' : 'У вас нет доступа к этой записи.';
                ob_start();
                require base_path('app/View/pages/company_contractor_edit.php');
                $content = ob_get_clean();
                require base_path('app/View/layouts/main.php');
                return;
            }
        }

        $errors = [];
        $old = $_POST;
        $formError = null;

        $contactPayload = ContractorContactService::normalizeSubmittedContacts($_POST['contacts'] ?? []);
        $submittedContacts = $contactPayload['contacts'];
        if (!empty($contactPayload['errors'])) {
            $errors['contacts'] = $contactPayload['errors'];
        }

        $name = trim($_POST['name'] ?? '');
        $inn  = trim($_POST['inn'] ?? '');

        if ($name === '') {
            $errors['name'] = 'Обязательное поле';
        }

        if ($inn === '') {
            $errors['inn'] = 'Обязательное поле';
        } else {
            $dupStmt = $localPdo->prepare('SELECT COUNT(*) FROM contractors WHERE inn = ? AND id != ?');
            $dupStmt->execute([$inn, (int) $id]);
            if ($dupStmt->fetchColumn() > 0) {
                $errors['inn'] = 'ИНН уже используется';
            }
        }

        if (!empty($errors)) {
            ob_start();
            require base_path('app/View/pages/company_contractor_edit.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $update = $localPdo->prepare(
            'UPDATE contractors SET
                name = :name,
                inn = :inn,
                kpp = :kpp,
                ogrn = :ogrn,
                contractor_type = :contractor_type,
                legal_address = :legal_address,
                physical_address = :physical_address,
                bank_account = :bank_account,
                bank_name = :bank_name,
                bank_bik = :bank_bik,
                bank_corr_account = :bank_corr_account,
                status = :status,
                comments = :comments,
                updated_by_user_id = :updated_by_user_id,
                updated_by_role = :updated_by_role
             WHERE id = :id'
        );

        $update->execute([
            ':name'             => $name,
            ':inn'              => $inn,
            ':kpp'              => $_POST['kpp'] ?? null,
            ':ogrn'             => $_POST['ogrn'] ?? null,
            ':contractor_type'  => $_POST['contractor_type'] ?? null,
            ':legal_address'    => $_POST['legal_address'] ?? null,
            ':physical_address' => $_POST['physical_address'] ?? null,
            ':bank_account'     => $_POST['bank_account'] ?? null,
            ':bank_name'        => $_POST['bank_name'] ?? null,
            ':bank_bik'         => $_POST['bank_bik'] ?? null,
            ':bank_corr_account'=> $_POST['bank_corr_account'] ?? null,
            ':status'           => $_POST['status'] ?? $contractor['status'],
            ':comments'         => $_POST['comments'] ?? null,
            ':updated_by_user_id' => (int)$_SESSION['user_id'],
            ':updated_by_role'  => $_SESSION['role_code'] ?? null,
            ':id'               => (int) $id,
        ]);

        ContractorContactService::replaceForContractor(
            $localPdo,
            (int) $id,
            $submittedContacts,
            (int) $_SESSION['user_id'],
            (string) ($_SESSION['role_code'] ?? '')
        );

        header('Location: /company/contractors/' . $id);
        exit;
    } catch (\Exception $e) {
        $company = $company ?? null;
        $contractor = $contractor ?? null;
        $errors = [];
        $old = $_POST;
        $formError = 'Ошибка сохранения: ' . $e->getMessage();

        ob_start();
        require base_path('app/View/pages/company_contractor_edit.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
    }
});

$router->post('/company/contractors/{id}/archive', function ($id) use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);
    $pageTitle = 'Перевозчик';
    $pageContext = 'Перевозчики › Компания';

    $companyId = (int)(getSessionCompanyId() ?? 0);
    $archiveError = null;

    if ($companyId <= 0) {
        $company = null;
        $contractor = null;
        $dbError = null;

        ob_start();
        require base_path('app/View/pages/company_contractor_view.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
        return;
    }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company) {
            $company = null;
            $contractor = null;
            $dbError = null;

            ob_start();
            require base_path('app/View/pages/company_contractor_view.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $pageContext = 'Перевозчики › Компания: ' . $company['name'];

        if ($company['status'] !== 'active') {
            $contractor = null;
            $dbError = null;

            ob_start();
            require base_path('app/View/pages/company_contractor_view.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $dbIdentifier = $company['db_identifier'];
        $localDbConfig = $config['database'];
        $localDbConfig['database'] = $dbIdentifier;
        $localDb = new \App\Core\Database($localDbConfig);
        $localPdo = $localDb->connection();
                applyLocalMigrations($localPdo);

        try {
            $localPdo->query("SELECT 1 FROM contractors LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $migrationSql = file_get_contents(base_path('database/migrations-local/003_create_company_contractors.sql'));
            $localPdo->exec($migrationSql);
        }

        $contractorStmt = $localPdo->prepare('SELECT * FROM contractors WHERE id = ?');
        $contractorStmt->execute([(int) $id]);
        $contractor = $contractorStmt->fetch(PDO::FETCH_ASSOC) ?: null;

        if (!$contractor) {
            $contractor = null;
            $dbError = null;

            ob_start();
            require base_path('app/View/pages/company_contractor_view.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        // Archive access check: logist can only archive own records
        $isLogist = ($_SESSION['role_code'] ?? '') === 'logist';
        if ($isLogist) {
            $userId = (int)$_SESSION['user_id'];
            if ((int)$contractor['created_by_user_id'] !== $userId) {
                $archiveError = 'Логист может архивировать только записи, созданные им самим.';
                $dbError = null;
                ob_start();
                require base_path('app/View/pages/company_contractor_view.php');
                $content = ob_get_clean();
                require base_path('app/View/layouts/main.php');
                return;
            }
        }

        $pageTitle = 'Перевозчик: ' . $contractor['name']; // archive handler

        try {
            $localPdo->query("SELECT 1 FROM crews LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $migrationSql = file_get_contents(base_path('database/migrations-local/006_create_company_crews.sql'));
            $localPdo->exec($migrationSql);
        }

        $crewCheck = $localPdo->prepare('SELECT COUNT(*) FROM crews WHERE contractor_id = ?');
        $crewCheck->execute([(int) $id]);
        if ($crewCheck->fetchColumn() > 0) {
            $archiveError = 'Перевозчик участвует в экипажах. Сначала удалите экипажи.';
            $dbError = null;

            ob_start();
            require base_path('app/View/pages/company_contractor_view.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $update = $localPdo->prepare("UPDATE contractors SET status = 'archived' WHERE id = ?");
        $update->execute([(int) $id]);

        header('Location: /company/contractors');
        exit;
    } catch (\Exception $e) {
        $company = $company ?? null;
        $contractor = null;
        $dbError = 'Ошибка архивирования: ' . $e->getMessage();

        ob_start();
        require base_path('app/View/pages/company_contractor_view.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
    }
});

// --- Contractor Contacts CRUD ---

$router->post('/company/contractors/{contractor_id}/contacts/create', function ($contractor_id) use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);
    $contractor_id = (int)$contractor_id;
    $companyId = (int)(getSessionCompanyId() ?? 0);
    $redirect = '/company/contractors/' . $contractor_id;

    if ($companyId <= 0) { header('Location: ' . $redirect); exit; }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$company || $company['status'] !== 'active') { header('Location: ' . $redirect); exit; }

        $dbIdentifier = $company['db_identifier'];
        $localDbConfig = $config['database']; $localDbConfig['database'] = $dbIdentifier;
        $localDb = new \App\Core\Database($localDbConfig); $localPdo = $localDb->connection();
        applyLocalMigrations($localPdo);

        $cStmt = $localPdo->prepare('SELECT * FROM contractors WHERE id = ?');
        $cStmt->execute([$contractor_id]);
        $contractor = $cStmt->fetch(PDO::FETCH_ASSOC);
        if (!$contractor) { header('Location: /company/contractors'); exit; }

        // Access check
        $isLogist = ($_SESSION['role_code'] ?? '') === 'logist';
        if ($isLogist) {
            $userId = (int)$_SESSION['user_id'];
            $hasGrant = false;
            $gc = $localPdo->prepare("SELECT access_level FROM entity_access_grants WHERE entity_type = 'contractor' AND entity_id = ? AND granted_to_user_id = ? AND revoked_at IS NULL LIMIT 1");
            $gc->execute([$contractor_id, $userId]);
            $gr = $gc->fetch(PDO::FETCH_ASSOC);
            $hasGrant = ($gr && $gr['access_level'] === 'edit');
            if ((int)$contractor['created_by_user_id'] !== $userId && !$hasGrant) { header('Location: ' . $redirect); exit; }
        }

        $contactPerson = trim($_POST['contact_person'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $comment = trim($_POST['comment'] ?? '');

        // Check if this is the first contact
        $cntStmt = $localPdo->prepare('SELECT COUNT(*) FROM contractor_contacts WHERE contractor_id = ?');
        $cntStmt->execute([$contractor_id]);
        $isFirst = ($cntStmt->fetchColumn() == 0);

        $isPrimary = $isFirst ? 1 : 0;
        $isDocEmail = ($isFirst && !empty($email)) ? 1 : 0;

        $insert = $localPdo->prepare(
            'INSERT INTO contractor_contacts (contractor_id, contact_person, phone, email, is_primary, is_document_email, comment, created_by_user_id, created_by_role)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $insert->execute([$contractor_id, $contactPerson ?: null, $phone ?: null, $email ?: null, $isPrimary, $isDocEmail, $comment ?: null, (int)$_SESSION['user_id'], $_SESSION['role_code'] ?? null]);
    } catch (\Exception $e) {}

    header('Location: ' . $redirect);
    exit;
});

$router->post('/company/contractors/{contractor_id}/contacts/{contact_id}/edit', function ($contractor_id, $contact_id) use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);
    $contractor_id = (int)$contractor_id; $contact_id = (int)$contact_id;
    $companyId = (int)(getSessionCompanyId() ?? 0);
    $redirect = '/company/contractors/' . $contractor_id;

    if ($companyId <= 0) { header('Location: ' . $redirect); exit; }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$company || $company['status'] !== 'active') { header('Location: ' . $redirect); exit; }

        $dbIdentifier = $company['db_identifier'];
        $localDbConfig = $config['database']; $localDbConfig['database'] = $dbIdentifier;
        $localDb = new \App\Core\Database($localDbConfig); $localPdo = $localDb->connection();
        applyLocalMigrations($localPdo);

        $cStmt = $localPdo->prepare('SELECT * FROM contractors WHERE id = ?');
        $cStmt->execute([$contractor_id]);
        $contractor = $cStmt->fetch(PDO::FETCH_ASSOC);
        if (!$contractor) { header('Location: /company/contractors'); exit; }

        // Access check
        $isLogist = ($_SESSION['role_code'] ?? '') === 'logist';
        if ($isLogist) {
            $userId = (int)$_SESSION['user_id'];
            $hasGrant = false;
            $gc = $localPdo->prepare("SELECT access_level FROM entity_access_grants WHERE entity_type = 'contractor' AND entity_id = ? AND granted_to_user_id = ? AND revoked_at IS NULL LIMIT 1");
            $gc->execute([$contractor_id, $userId]);
            $gr = $gc->fetch(PDO::FETCH_ASSOC);
            $hasGrant = ($gr && $gr['access_level'] === 'edit');
            if ((int)$contractor['created_by_user_id'] !== $userId && !$hasGrant) { header('Location: ' . $redirect); exit; }
        }

        $contactPerson = trim($_POST['contact_person'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $comment = trim($_POST['comment'] ?? '');

        $update = $localPdo->prepare(
            'UPDATE contractor_contacts SET contact_person = ?, phone = ?, email = ?, comment = ?, updated_by_user_id = ?, updated_by_role = ? WHERE id = ? AND contractor_id = ?'
        );
        $update->execute([$contactPerson ?: null, $phone ?: null, $email ?: null, $comment ?: null, (int)$_SESSION['user_id'], $_SESSION['role_code'] ?? null, $contact_id, $contractor_id]);
    } catch (\Exception $e) {}

    header('Location: ' . $redirect);
    exit;
});

$router->post('/company/contractors/{contractor_id}/contacts/{contact_id}/delete', function ($contractor_id, $contact_id) use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);
    $contractor_id = (int)$contractor_id; $contact_id = (int)$contact_id;
    $companyId = (int)(getSessionCompanyId() ?? 0);
    $redirect = '/company/contractors/' . $contractor_id;

    if ($companyId <= 0) { header('Location: ' . $redirect); exit; }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$company || $company['status'] !== 'active') { header('Location: ' . $redirect); exit; }

        $dbIdentifier = $company['db_identifier'];
        $localDbConfig = $config['database']; $localDbConfig['database'] = $dbIdentifier;
        $localDb = new \App\Core\Database($localDbConfig); $localPdo = $localDb->connection();
        applyLocalMigrations($localPdo);

        $cStmt = $localPdo->prepare('SELECT * FROM contractors WHERE id = ?');
        $cStmt->execute([$contractor_id]);
        $contractor = $cStmt->fetch(PDO::FETCH_ASSOC);
        if (!$contractor) { header('Location: /company/contractors'); exit; }

        // Access check: logist view — deny, logist edit — allow if can edit, company_owner — allow
        $isLogist = ($_SESSION['role_code'] ?? '') === 'logist';
        if ($isLogist) {
            $userId = (int)$_SESSION['user_id'];
            $hasGrant = false;
            $gc = $localPdo->prepare("SELECT access_level FROM entity_access_grants WHERE entity_type = 'contractor' AND entity_id = ? AND granted_to_user_id = ? AND revoked_at IS NULL LIMIT 1");
            $gc->execute([$contractor_id, $userId]);
            $gr = $gc->fetch(PDO::FETCH_ASSOC);
            $hasGrant = ($gr && $gr['access_level'] === 'edit');
            if ((int)$contractor['created_by_user_id'] !== $userId && !$hasGrant) { header('Location: ' . $redirect); exit; }
        }

        // Check if deleting primary contact
        $contactStmt = $localPdo->prepare('SELECT is_primary FROM contractor_contacts WHERE id = ? AND contractor_id = ?');
        $contactStmt->execute([$contact_id, $contractor_id]);
        $contact = $contactStmt->fetch(PDO::FETCH_ASSOC);

        // Delete
        $delStmt = $localPdo->prepare('DELETE FROM contractor_contacts WHERE id = ? AND contractor_id = ?');
        $delStmt->execute([$contact_id, $contractor_id]);

        // If deleted primary, set first remaining as primary
        if ($contact && $contact['is_primary']) {
            $first = $localPdo->prepare('SELECT id FROM contractor_contacts WHERE contractor_id = ? ORDER BY id ASC LIMIT 1');
            $first->execute([$contractor_id]);
            $firstRow = $first->fetch(PDO::FETCH_ASSOC);
            if ($firstRow) {
                $localPdo->prepare('UPDATE contractor_contacts SET is_primary = 1 WHERE id = ?')->execute([$firstRow['id']]);
            }
        }
    } catch (\Exception $e) {}

    header('Location: ' . $redirect);
    exit;
});

$router->post('/company/contractors/{contractor_id}/contacts/{contact_id}/set-primary', function ($contractor_id, $contact_id) use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);
    $contractor_id = (int)$contractor_id; $contact_id = (int)$contact_id;
    $companyId = (int)(getSessionCompanyId() ?? 0);
    $redirect = '/company/contractors/' . $contractor_id;

    if ($companyId <= 0) { header('Location: ' . $redirect); exit; }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$company || $company['status'] !== 'active') { header('Location: ' . $redirect); exit; }

        $dbIdentifier = $company['db_identifier'];
        $localDbConfig = $config['database']; $localDbConfig['database'] = $dbIdentifier;
        $localDb = new \App\Core\Database($localDbConfig); $localPdo = $localDb->connection();
        applyLocalMigrations($localPdo);

        $cStmt = $localPdo->prepare('SELECT * FROM contractors WHERE id = ?');
        $cStmt->execute([$contractor_id]);
        $contractor = $cStmt->fetch(PDO::FETCH_ASSOC);
        if (!$contractor) { header('Location: /company/contractors'); exit; }

        // Access check
        $isLogist = ($_SESSION['role_code'] ?? '') === 'logist';
        if ($isLogist) {
            $userId = (int)$_SESSION['user_id'];
            $hasGrant = false;
            $gc = $localPdo->prepare("SELECT access_level FROM entity_access_grants WHERE entity_type = 'contractor' AND entity_id = ? AND granted_to_user_id = ? AND revoked_at IS NULL LIMIT 1");
            $gc->execute([$contractor_id, $userId]);
            $gr = $gc->fetch(PDO::FETCH_ASSOC);
            $hasGrant = ($gr && $gr['access_level'] === 'edit');
            if ((int)$contractor['created_by_user_id'] !== $userId && !$hasGrant) { header('Location: ' . $redirect); exit; }
        }

        $localPdo->prepare('UPDATE contractor_contacts SET is_primary = 0 WHERE contractor_id = ?')->execute([$contractor_id]);
        $localPdo->prepare('UPDATE contractor_contacts SET is_primary = 1 WHERE id = ? AND contractor_id = ?')->execute([$contact_id, $contractor_id]);
    } catch (\Exception $e) {}

    header('Location: ' . $redirect);
    exit;
});

$router->post('/company/contractors/{contractor_id}/contacts/{contact_id}/set-document-email', function ($contractor_id, $contact_id) use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);
    $contractor_id = (int)$contractor_id; $contact_id = (int)$contact_id;
    $companyId = (int)(getSessionCompanyId() ?? 0);
    $redirect = '/company/contractors/' . $contractor_id;

    if ($companyId <= 0) { header('Location: ' . $redirect); exit; }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$company || $company['status'] !== 'active') { header('Location: ' . $redirect); exit; }

        $dbIdentifier = $company['db_identifier'];
        $localDbConfig = $config['database']; $localDbConfig['database'] = $dbIdentifier;
        $localDb = new \App\Core\Database($localDbConfig); $localPdo = $localDb->connection();
        applyLocalMigrations($localPdo);

        $cStmt = $localPdo->prepare('SELECT * FROM contractors WHERE id = ?');
        $cStmt->execute([$contractor_id]);
        $contractor = $cStmt->fetch(PDO::FETCH_ASSOC);
        if (!$contractor) { header('Location: /company/contractors'); exit; }

        // Access check
        $isLogist = ($_SESSION['role_code'] ?? '') === 'logist';
        if ($isLogist) {
            $userId = (int)$_SESSION['user_id'];
            $hasGrant = false;
            $gc = $localPdo->prepare("SELECT access_level FROM entity_access_grants WHERE entity_type = 'contractor' AND entity_id = ? AND granted_to_user_id = ? AND revoked_at IS NULL LIMIT 1");
            $gc->execute([$contractor_id, $userId]);
            $gr = $gc->fetch(PDO::FETCH_ASSOC);
            $hasGrant = ($gr && $gr['access_level'] === 'edit');
            if ((int)$contractor['created_by_user_id'] !== $userId && !$hasGrant) { header('Location: ' . $redirect); exit; }
        }

        $localPdo->prepare('UPDATE contractor_contacts SET is_document_email = 1 WHERE id = ? AND contractor_id = ?')->execute([$contact_id, $contractor_id]);
    } catch (\Exception $e) {}

    header('Location: ' . $redirect);
    exit;
});

// --- Contractor Tax History ---

$router->post('/company/contractors/{contractor_id}/tax-history/create', function ($contractor_id) use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);
    $contractor_id = (int)$contractor_id;
    $companyId = (int)(getSessionCompanyId() ?? 0);
    $redirect = '/company/contractors/' . $contractor_id;

    if ($companyId <= 0) { header('Location: ' . $redirect); exit; }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$company || $company['status'] !== 'active') { header('Location: ' . $redirect); exit; }

        $dbIdentifier = $company['db_identifier'];
        $localDbConfig = $config['database']; $localDbConfig['database'] = $dbIdentifier;
        $localDb = new \App\Core\Database($localDbConfig); $localPdo = $localDb->connection();
        applyLocalMigrations($localPdo);

        $cStmt = $localPdo->prepare('SELECT * FROM contractors WHERE id = ?');
        $cStmt->execute([$contractor_id]);
        $contractor = $cStmt->fetch(PDO::FETCH_ASSOC);
        if (!$contractor) { header('Location: /company/contractors'); exit; }

        // Access check
        $isLogist = ($_SESSION['role_code'] ?? '') === 'logist';
        if ($isLogist) {
            $userId = (int)$_SESSION['user_id'];
            $hasGrant = false;
            $gc = $localPdo->prepare("SELECT access_level FROM entity_access_grants WHERE entity_type = 'contractor' AND entity_id = ? AND granted_to_user_id = ? AND revoked_at IS NULL LIMIT 1");
            $gc->execute([$contractor_id, $userId]);
            $gr = $gc->fetch(PDO::FETCH_ASSOC);
            $hasGrant = ($gr && $gr['access_level'] === 'edit');
            if ((int)$contractor['created_by_user_id'] !== $userId && !$hasGrant) { header('Location: ' . $redirect); exit; }
        }

        $taxSystem = trim($_POST['tax_system'] ?? '');
        $vatMode = trim($_POST['vat_mode'] ?? '');
        $effectiveFrom = trim($_POST['effective_from'] ?? '');
        $comment = trim($_POST['comment'] ?? '');

        $insert = $localPdo->prepare(
            'INSERT INTO contractor_tax_history (contractor_id, tax_system, vat_mode, effective_from, comment, created_by_user_id, created_by_role)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $insert->execute([$contractor_id, $taxSystem ?: null, $vatMode ?: null, $effectiveFrom ?: null, $comment ?: null, (int)$_SESSION['user_id'], $_SESSION['role_code'] ?? null]);
    } catch (\Exception $e) {}

    header('Location: ' . $redirect);
    exit;
});

$router->get('/company/drivers', function () use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);
    $pageTitle = 'Водители';
    $pageContext = 'Водители › Компания';

    $companyId = (int)(getSessionCompanyId() ?? 0);

    if ($companyId <= 0) {
        $company = null;
        $drivers = [];
        $dbError = null;

        ob_start();
        require base_path('app/View/pages/company_drivers.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
        return;
    }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company) {
            $company = null;
            $drivers = [];
            $dbError = null;

            ob_start();
            require base_path('app/View/pages/company_drivers.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $pageContext = 'Водители › Компания: ' . $company['name'];
        $topbarCrumbs = [
            ['label' => mb_strtoupper($company['name']), 'url' => '/company/dashboard'],
            ['label' => 'Подрядчики', 'url' => null],
            ['label' => 'Список водителей', 'url' => null],
        ];

        if ($company['status'] !== 'active') {
            $drivers = [];
            $dbError = null;

            ob_start();
            require base_path('app/View/pages/company_drivers.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $dbIdentifier = $company['db_identifier'];
        $localDbConfig = $config['database'];
        $localDbConfig['database'] = $dbIdentifier;
        $localDb = new \App\Core\Database($localDbConfig);
        $localPdo = $localDb->connection();
                applyLocalMigrations($localPdo);

        try {
            $localPdo->query("SELECT 1 FROM drivers LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $migrationSql = file_get_contents(base_path('database/migrations-local/004_create_company_drivers.sql'));
            $localPdo->exec($migrationSql);
        }

        try {
            $localPdo->query("SELECT created_by_user_id FROM drivers LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $localPdo->exec("ALTER TABLE drivers ADD COLUMN created_by_user_id INT UNSIGNED DEFAULT NULL, ADD COLUMN created_by_role VARCHAR(20) DEFAULT NULL");
        }

        try {
            $localPdo->query("SELECT 1 FROM entity_access_grants LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $migrationSql = file_get_contents(base_path('database/migrations-local/009_create_entity_access_grants.sql'));
            $localPdo->exec($migrationSql);
        }

        try {
            $docTypes = $localPdo->query("SELECT id, name, code, entity_type FROM document_types WHERE entity_type = 'driver' OR entity_type IS NULL ORDER BY sort_order, name")->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            $docTypes = [];
        }

        $isLogist = ($_SESSION['role_code'] ?? '') === 'logist';
        if ($isLogist) {
            $userId = (int)$_SESSION['user_id'];
            $driverStmt = $localPdo->prepare(
                "SELECT d.*, COALESCE(dp.phone, d.phone) AS main_phone,
                        (SELECT COUNT(*) FROM driver_phones WHERE driver_id = d.id AND is_main = 0) AS extra_phones_count,
                        (SELECT COUNT(*) FROM documents doc WHERE doc.entity_type = 'driver' AND doc.entity_id = d.id AND doc.deleted_at IS NULL) AS files_count
                 FROM drivers d
                 LEFT JOIN driver_phones dp ON d.id = dp.driver_id AND dp.is_main = 1
                 WHERE (d.created_by_user_id = ? OR d.id IN (SELECT entity_id FROM entity_access_grants WHERE entity_type = 'driver' AND granted_to_user_id = ? AND access_level = 'view'))
                 ORDER BY d.created_at DESC"
            );
            $driverStmt->execute([$userId, $userId]);
            $drivers = $driverStmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $driverStmt = $localPdo->query(
                "SELECT d.*, COALESCE(dp.phone, d.phone) AS main_phone,
                        (SELECT COUNT(*) FROM driver_phones WHERE driver_id = d.id AND is_main = 0) AS extra_phones_count,
                        (SELECT COUNT(*) FROM documents doc WHERE doc.entity_type = 'driver' AND doc.entity_id = d.id AND doc.deleted_at IS NULL) AS files_count
                 FROM drivers d
                 LEFT JOIN driver_phones dp ON d.id = dp.driver_id AND dp.is_main = 1
                 ORDER BY d.created_at DESC"
            );
            $drivers = $driverStmt->fetchAll(PDO::FETCH_ASSOC);
        }

        $dbError = null;
    } catch (\Exception $e) {
        $company = $company ?? null;
        $drivers = [];
        $dbError = 'Не удалось подключиться к базе данных компании.';
    }

    ob_start();
    require base_path('app/View/pages/company_drivers.php');
    $content = ob_get_clean();
    require base_path('app/View/layouts/main.php');
});

$router->get('/company/drivers/create', function () use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);
    $pageTitle = 'Создать водителя';
    $pageContext = 'Водители › Компания';

    $companyId = (int)(getSessionCompanyId() ?? 0);

    if ($companyId <= 0) {
        $company = null;
        $success = false;
        $errors = [];
        $old = [];
        $formError = null;
        $createdDriver = null;

        ob_start();
        require base_path('app/View/pages/company_drivers_create.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
        return;
    }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company) {
            $company = null;
            $success = false;
            $errors = [];
            $old = [];
            $formError = null;
            $createdDriver = null;

            ob_start();
            require base_path('app/View/pages/company_drivers_create.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $topbarCrumbs = [
            ['label' => mb_strtoupper($company['name']), 'url' => '/company/dashboard'],
            ['label' => 'Подрядчики', 'url' => null],
            ['label' => 'Водители', 'url' => '/company/drivers'],
            ['label' => 'Создать водителя', 'url' => null],
        ];

        $success = false;
        $errors = [];
        $old = [];
        $formError = null;
        $createdDriver = null;
        $docTypes = [];

        if ($company['status'] === 'active') {
            try {
                $dbIdentifier = $company['db_identifier'];
                $localDbConfig = $config['database']; $localDbConfig['database'] = $dbIdentifier;
                $localDb = new \App\Core\Database($localDbConfig); $localPdo = $localDb->connection();
                applyLocalMigrations($localPdo);

                try { $localPdo->query("SELECT 1 FROM document_types LIMIT 1")->fetch(); }
                catch (\Exception $e) {
                    $localPdo->exec(file_get_contents(base_path('database/migrations-local/024_create_document_types.sql')));
                    $localPdo->exec(file_get_contents(base_path('database/migrations-local/025_add_document_type_id.sql')));
                }

                $docTypes = $localPdo->query("SELECT id, name, code, entity_type FROM document_types WHERE entity_type = 'driver' OR entity_type IS NULL ORDER BY sort_order, name")->fetchAll(PDO::FETCH_ASSOC);
            } catch (\Exception $e) {
                $docTypes = [];
            }
        }
    } catch (\Exception $e) {
        $company = null;
        $success = false;
        $errors = [];
        $old = [];
        $formError = 'Ошибка загрузки данных: ' . $e->getMessage();
        $createdDriver = null;
    }

    ob_start();
    require base_path('app/View/pages/company_drivers_create.php');
    $content = ob_get_clean();
    require base_path('app/View/layouts/main.php');
});

$router->post('/company/drivers/create', function () use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);
    require_once base_path('app/Support/driver_create_handler.php');
    handleCompanyDriverCreate($config, $db, 'page');
    return;
    $pageTitle = 'Создать водителя';
    $pageContext = 'Водители › Компания';

    $companyId = (int)(getSessionCompanyId() ?? 0);
    $errors = [];
    $old = $_POST;
    $formError = null;
    $success = false;
    $createdDriver = null;
    $docErrors = [];
    $uploadedDocs = [];

    if ($companyId <= 0) {
        $company = null;
        $formError = 'Компания не найдена';

        ob_start();
        require base_path('app/View/pages/company_drivers_create.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
        return;
    }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company) {
            $company = null;
            $formError = 'Компания не найдена';

            ob_start();
            require base_path('app/View/pages/company_drivers_create.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $topbarCrumbs = [
            ['label' => mb_strtoupper($company['name']), 'url' => '/company/dashboard'],
            ['label' => 'Подрядчики', 'url' => null],
            ['label' => 'Водители', 'url' => '/company/drivers'],
            ['label' => 'Создать водителя', 'url' => null],
        ];

        if ($company['status'] !== 'active') {
            $formError = 'Создание водителей недоступно';

            ob_start();
            require base_path('app/View/pages/company_drivers_create.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $dbIdentifier = $company['db_identifier'];
        $localDbConfig = $config['database'];
        $localDbConfig['database'] = $dbIdentifier;
        $localDb = new \App\Core\Database($localDbConfig);
        $localPdo = $localDb->connection();
                applyLocalMigrations($localPdo);

        try {
            $localPdo->query("SELECT 1 FROM drivers LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $migrationSql = file_get_contents(base_path('database/migrations-local/004_create_company_drivers.sql'));
            $localPdo->exec($migrationSql);
            applyLocalMigrations($localPdo);
        }

        $normalizeSpaces = static function ($value): string {
            return preg_replace('/\s+/u', ' ', trim((string)$value)) ?? '';
        };
        $normalizeDateInput = static function ($value) use ($normalizeSpaces): ?string {
            $value = $normalizeSpaces($value);
            if ($value === '') {
                return null;
            }

            $value = preg_replace('/\s*(г(?:\.|ода?|од)?)\s*$/ui', '', $value) ?? $value;
            $value = $normalizeSpaces($value);

            if (preg_match('/^(\d{8})$/', $value, $m)) {
                $day = (int)substr($m[1], 0, 2);
                $month = (int)substr($m[1], 2, 2);
                $year = (int)substr($m[1], 4, 4);
            } elseif (preg_match('/^(\d{4})[.\/-](\d{1,2})[.\/-](\d{1,2})$/', $value, $m)) {
                $year = (int)$m[1];
                $month = (int)$m[2];
                $day = (int)$m[3];
            } elseif (preg_match('/^(\d{1,2})[\s.\/-](\d{1,2})[\s.\/-](\d{2}|\d{4})$/', $value, $m)) {
                $day = (int)$m[1];
                $month = (int)$m[2];
                $year = (int)$m[3];
                if ($year < 100) {
                    $year = $year <= 49 ? 2000 + $year : 1900 + $year;
                }
            } else {
                return false;
            }

            if (!checkdate($month, $day, $year)) {
                return false;
            }

            return sprintf('%04d-%02d-%02d', $year, $month, $day);
        };
        $normalizePhone = static function ($value): ?string {
            $digits = preg_replace('/\D+/', '', (string)$value) ?? '';
            if (strlen($digits) === 11 && $digits[0] === '8') {
                $digits = '7' . substr($digits, 1);
            } elseif (strlen($digits) === 10) {
                $digits = '7' . $digits;
            }
            if (strlen($digits) !== 11 || $digits[0] !== '7') {
                return null;
            }

            return sprintf(
                '+7 %s %s-%s-%s',
                substr($digits, 1, 3),
                substr($digits, 4, 3),
                substr($digits, 7, 2),
                substr($digits, 9, 2)
            );
        };
        $normalizeTenDigits = static function ($value): ?string {
            $digits = preg_replace('/\D+/', '', (string)$value) ?? '';
            if (strlen($digits) !== 10) {
                return null;
            }

            return substr($digits, 0, 4) . ' ' . substr($digits, 4, 6);
        };
        $normalizeDepartmentCode = static function ($value): ?string {
            $digits = preg_replace('/\D+/', '', (string)$value) ?? '';
            if (strlen($digits) !== 6) {
                return null;
            }

            return substr($digits, 0, 3) . '-' . substr($digits, 3, 3);
        };
        $normalizeSnils = static function ($value): ?string {
            $digits = preg_replace('/\D+/', '', (string)$value) ?? '';
            if (strlen($digits) !== 11) {
                return null;
            }

            return substr($digits, 0, 3) . '-' . substr($digits, 3, 3) . '-' . substr($digits, 6, 3) . ' ' . substr($digits, 9, 2);
        };
        $normalizeFullName = static function ($value) use ($normalizeSpaces): ?string {
            $value = $normalizeSpaces($value);
            if ($value === '') {
                return null;
            }
            $parts = preg_split('/\s+/u', $value) ?: [];
            if (count($parts) !== 3) {
                return false;
            }

            $parts = array_map(static function ($part) {
                $part = mb_strtolower($part, 'UTF-8');
                return mb_strtoupper(mb_substr($part, 0, 1, 'UTF-8'), 'UTF-8') . mb_substr($part, 1, null, 'UTF-8');
            }, $parts);

            return implode(' ', $parts);
        };

        if (isPostTruncated()) {
            $formError = 'Общий размер отправки превышает серверный лимит. Для ERP требуется настройка post_max_size не менее 100M. Уменьшите количество файлов или обратитесь к администратору.';
            ob_start();
            require base_path('app/View/pages/company_drivers_create.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $fullName = $normalizeFullName($_POST['full_name'] ?? '');
        $phoneRaw = trim((string)($_POST['phone'] ?? ''));
        $phone = $normalizePhone($phoneRaw);
        $licenseNumberRaw = trim((string)($_POST['license_number'] ?? ''));
        $licenseNumber = $licenseNumberRaw !== '' ? $normalizeTenDigits($licenseNumberRaw) : null;
        $licenseCategory = trim((string)($_POST['license_category'] ?? ''));
        $licenseIssueDateRaw = trim((string)($_POST['license_issue_date'] ?? ''));
        $licenseIssueDate = $licenseIssueDateRaw !== '' ? $normalizeDateInput($licenseIssueDateRaw) : null;
        $licenseExpireDate = trim((string)($_POST['license_expire_date'] ?? ''));
        $comments = $normalizeSpaces($_POST['comments'] ?? '');
        $passportNumberRaw = trim((string)($_POST['passport_number'] ?? ''));
        $passportNumber = $passportNumberRaw !== '' ? $normalizeTenDigits($passportNumberRaw) : null;
        $passportIssuedBy = $normalizeSpaces($_POST['passport_issued_by'] ?? '');
        $passportDepartmentCodeRaw = trim((string)($_POST['passport_department_code'] ?? ''));
        $passportDepartmentCode = $passportDepartmentCodeRaw !== '' ? $normalizeDepartmentCode($passportDepartmentCodeRaw) : null;
        $passportIssueDateRaw = trim((string)($_POST['passport_issue_date'] ?? ''));
        $passportIssueDate = $passportIssueDateRaw !== '' ? $normalizeDateInput($passportIssueDateRaw) : null;
        $snilsRaw = trim((string)($_POST['snils'] ?? ''));
        $snils = $snilsRaw !== '' ? $normalizeSnils($snilsRaw) : null;
        $email = strtolower(trim((string)($_POST['email'] ?? '')));

        $old['full_name'] = is_string($fullName) ? $fullName : trim((string)($_POST['full_name'] ?? ''));
        $old['phone'] = $phone ?? $phoneRaw;
        $old['license_number'] = $licenseNumber ?? $licenseNumberRaw;
        $old['license_issue_date'] = is_string($licenseIssueDate) ? date('d.m.Y', strtotime($licenseIssueDate)) : $licenseIssueDateRaw;
        $old['comments'] = $comments;
        $old['passport_number'] = $passportNumber ?? $passportNumberRaw;
        $old['passport_issued_by'] = $passportIssuedBy;
        $old['passport_department_code'] = $passportDepartmentCode ?? $passportDepartmentCodeRaw;
        $old['passport_issue_date'] = is_string($passportIssueDate) ? date('d.m.Y', strtotime($passportIssueDate)) : $passportIssueDateRaw;
        $old['snils'] = $snils ?? $snilsRaw;
        $old['email'] = $email;

        if ($fullName === null) {
            $errors['full_name'] = 'ФИО: 3 слова';
        } elseif ($fullName === false) {
            $errors['full_name'] = 'ФИО: 3 слова';
        }

        if ($phoneRaw !== '' && $phone === null) {
            $errors['phone'] = 'Неверный формат';
        }

        if ($licenseNumberRaw !== '' && $licenseNumber === null) {
            $errors['license_number'] = 'Нужно 10 цифр';
        }

        if ($licenseIssueDateRaw !== '' && $licenseIssueDate === false) {
            $errors['license_issue_date'] = 'Неверная дата';
        }

        if ($passportNumberRaw !== '' && $passportNumber === null) {
            $errors['passport_number'] = 'Нужно 10 цифр';
        }

        if ($passportDepartmentCodeRaw !== '' && $passportDepartmentCode === null) {
            $errors['passport_department_code'] = 'Формат 000-000';
        }

        if ($passportIssueDateRaw !== '' && $passportIssueDate === false) {
            $errors['passport_issue_date'] = 'Неверная дата';
        }

        if ($snilsRaw !== '' && $snils === null) {
            $errors['snils'] = 'Нужно 11 цифр';
        }

        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Неверный email';
        }

        if (!empty($errors)) {
            ob_start();
            require base_path('app/View/pages/company_drivers_create.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        // Check total upload size before INSERT
        $totalSizeError = validateTotalUploadSize();
        if ($totalSizeError !== '') {
            $formError = $totalSizeError;
            ob_start();
            require base_path('app/View/pages/company_drivers_create.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $insert = $localPdo->prepare(
            'INSERT INTO drivers (full_name, phone, email, license_number, license_category,
             license_issue_date, license_expire_date, passport_number, passport_issued_by,
             passport_department_code, passport_issue_date, snils, status, comments, created_by_user_id, created_by_role)
             VALUES (:full_name, :phone, :email, :license_number, :license_category,
             :license_issue_date, :license_expire_date, :passport_number, :passport_issued_by,
             :passport_department_code, :passport_issue_date, :snils, :status, :comments, :created_by_user_id, :created_by_role)'
        );
        $insert->execute([
            ':full_name'              => $fullName,
            ':phone'                  => $phone ?? '',
            ':email'                  => $email !== '' ? $email : null,
            ':license_number'         => $licenseNumber,
            ':license_category'       => $licenseCategory !== '' ? $licenseCategory : null,
            ':license_issue_date'     => $licenseIssueDate ?: null,
            ':license_expire_date'    => $licenseExpireDate !== '' ? $licenseExpireDate : null,
            ':passport_number'        => $passportNumber,
            ':passport_issued_by'     => $passportIssuedBy !== '' ? $passportIssuedBy : null,
            ':passport_department_code' => $passportDepartmentCode,
            ':passport_issue_date'    => $passportIssueDate ?: null,
            ':snils'                  => $snils,
            ':status'                 => 'active',
            ':comments'               => $comments !== '' ? $comments : null,
            ':created_by_user_id'     => (int)$_SESSION['user_id'],
            ':created_by_role'        => $_SESSION['role_code'],
        ]);

        $createdDriver = [
            'id'                     => $localPdo->lastInsertId(),
            'full_name'              => $fullName,
            'phone'                  => $phone,
            'email'                  => $email !== '' ? $email : null,
            'passport_number'        => $passportNumber,
            'passport_issued_by'     => $passportIssuedBy !== '' ? $passportIssuedBy : null,
            'passport_issue_date'    => $passportIssueDate ? date('d.m.Y', strtotime($passportIssueDate)) : null,
            'snils'                  => $snils,
            'license_number'         => $licenseNumber,
            'license_category'       => $licenseCategory !== '' ? $licenseCategory : null,
        ];
        $newDriverId = (int)$localPdo->lastInsertId();
        $entityType = 'driver';

        // -- Process extra phones during creation --
        $extraPhones = $_POST['extra_phones'] ?? [];
        if (is_array($extraPhones)) {
            try { $localPdo->query("SELECT 1 FROM driver_phones LIMIT 1")->fetch(); }
            catch (\Exception $e) { $localPdo->exec(file_get_contents(base_path('database/migrations-local/015_create_driver_phones.sql'))); }
            $phoneComments = $_POST['extra_phone_comments'] ?? [];
            $phoneIns = $localPdo->prepare('INSERT INTO driver_phones (driver_id, phone, is_main, comment, created_by_user_id, created_by_role) VALUES (:did, :phone, 0, :comment, :uid, :role)');
            foreach ($extraPhones as $pIdx => $extraPhone) {
                $extraPhone = $normalizePhone($extraPhone ?? '');
                if ($extraPhone === '') continue;
                $phoneIns->execute([
                    ':did'     => $newDriverId,
                    ':phone'   => $extraPhone,
                    ':comment' => isset($phoneComments[$pIdx]) ? $normalizeSpaces($phoneComments[$pIdx]) : null,
                    ':uid'     => (int)$_SESSION['user_id'],
                    ':role'    => $_SESSION['role_code'],
                ]);
            }
            // Load back for success display
            $extraPhonesSaved = $localPdo->prepare("SELECT phone, comment FROM driver_phones WHERE driver_id = ? AND is_main = 0 ORDER BY id ASC");
            $extraPhonesSaved->execute([$newDriverId]);
            $createdDriver['extra_phones'] = $extraPhonesSaved->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $createdDriver['extra_phones'] = [];
        }

        // -- Process document uploads during creation --
        $docErrors = [];
        $uploadedDocs = [];

        try { $localPdo->query("SELECT 1 FROM documents LIMIT 1")->fetch(); }
        catch (\Exception $e) { $localPdo->exec(file_get_contents(base_path('database/migrations-local/007_create_company_documents.sql'))); }
        try { $localPdo->query("SELECT 1 FROM document_types LIMIT 1")->fetch(); }
        catch (\Exception $e) {
            $localPdo->exec(file_get_contents(base_path('database/migrations-local/024_create_document_types.sql')));
            $localPdo->exec(file_get_contents(base_path('database/migrations-local/025_add_document_type_id.sql')));
        }
        try { $localPdo->query("SELECT created_by_user_id FROM documents LIMIT 1")->fetch(); }
        catch (\Exception $e) { $localPdo->exec("ALTER TABLE documents ADD COLUMN created_by_user_id INT UNSIGNED DEFAULT NULL, ADD COLUMN created_by_role VARCHAR(20) DEFAULT NULL"); }

        $allowedExt = ['pdf', 'doc', 'docx', 'rtf', 'odt', 'xls', 'xlsx', 'csv', 'ods', 'jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp', 'tif', 'tiff', 'heic', 'heif', 'txt'];
        $maxSize = 20 * 1024 * 1024;

        // Predefined docs (supports multiple files per type via multiple attribute)
        if (!empty($_FILES['predef_doc']['name']) && is_array($_FILES['predef_doc']['name'])) {
            foreach ($_FILES['predef_doc']['name'] as $code => $nameValue) {
                $docTypeName = $_POST['predef_doc_type'][$code] ?? '';
                // Normalize to array of files (single or multiple)
                if (is_array($nameValue)) {
                    $names = $nameValue;
                    $tmpNames = $_FILES['predef_doc']['tmp_name'][$code] ?? [];
                    $errors = $_FILES['predef_doc']['error'][$code] ?? [];
                    $sizes = $_FILES['predef_doc']['size'][$code] ?? [];
                    $types = $_FILES['predef_doc']['type'][$code] ?? [];
                } else {
                    $names = [$nameValue];
                    $tmpNames = [$_FILES['predef_doc']['tmp_name'][$code] ?? ''];
                    $errors = [$_FILES['predef_doc']['error'][$code] ?? UPLOAD_ERR_NO_FILE];
                    $sizes = [$_FILES['predef_doc']['size'][$code] ?? 0];
                    $types = [$_FILES['predef_doc']['type'][$code] ?? ''];
                }
                foreach ($names as $fileIdx => $origName) {
                    $fe = $errors[$fileIdx] ?? UPLOAD_ERR_NO_FILE;
                    if ($fe !== UPLOAD_ERR_OK || trim((string)$origName) === '') continue;
                    $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
                    $fs = $sizes[$fileIdx] ?? 0;
                    if (!in_array($ext, $allowedExt, true)) { $docErrors[] = 'Предопределённый документ «' . $docTypeName . '»: недопустимый формат'; continue; }
                    if ($fs > $maxSize) { $docErrors[] = 'Предопределённый документ «' . $docTypeName . '»: размер > 20 МБ'; continue; }
                    if (strpos($origName, '../') !== false || strpos($origName, '..\\') !== false || strpos($origName, '/') !== false || strpos($origName, '\\') !== false) { $docErrors[] = 'Предопределённый документ «' . $docTypeName . '»: недопустимое имя'; continue; }
                    try {
                        $storedName = uniqid('doc_', true) . '.' . $ext;
                        $relativeDir = 'companies/' . $companyId . '/documents/' . $entityType . '/' . $newDriverId;
                        $absoluteDir = storage_path($relativeDir);
                        if (!is_dir($absoluteDir) && !mkdir($absoluteDir, 0755, true)) { $docErrors[] = 'Предопределённый документ «' . $docTypeName . '»: не удалось создать директорию'; continue; }
                        $destPath = $absoluteDir . DIRECTORY_SEPARATOR . $storedName;
                        $tmpSrc = $tmpNames[$fileIdx] ?? '';
                        if ($tmpSrc === '' || !move_uploaded_file($tmpSrc, $destPath)) { $docErrors[] = 'Предопределённый документ «' . $docTypeName . '»: не удалось сохранить'; continue; }
                        $mime = $types[$fileIdx] ?? '';
                        $dtId = null;
                    if ($docTypeName !== '') { $dts = $localPdo->prepare("SELECT id FROM document_types WHERE name = ? AND entity_type = ? LIMIT 1"); $dts->execute([$docTypeName, $entityType]); $dtId = $dts->fetchColumn() ?: null; }
                        $ins = $localPdo->prepare('INSERT INTO documents (entity_type, entity_id, document_type, document_type_id, original_name, stored_name, relative_path, mime_type, file_size, status, uploaded_by_user_id, uploaded_by_role, created_by_user_id, created_by_role) VALUES (:et, :eid, :dtype, :dtid, :oname, :sname, :rpath, :mime, :fsize, :status, :uid, :role, :cuid, :crole)');
                        $ins->execute([':et' => $entityType, ':eid' => $newDriverId, ':dtype' => $docTypeName ?: null, ':dtid' => $dtId, ':oname' => $origName, ':sname' => $storedName, ':rpath' => $relativeDir . '/' . $storedName, ':mime' => $mime, ':fsize' => $fs, ':status' => 'uploaded', ':uid' => (int)$_SESSION['user_id'], ':role' => $_SESSION['role_code'], ':cuid' => (int)$_SESSION['user_id'], ':crole' => $_SESSION['role_code']]);
                        $uploadedDocs[] = $docTypeName . ' (' . $origName . ')';
                    } catch (\Exception $ex) { $docErrors[] = 'Предопределённый документ «' . $docTypeName . '»: ошибка сохранения (' . $ex->getMessage() . ')'; }
                }
            }
        }

        // Custom docs
        if (!empty($_FILES['custom_doc_file']['name']) && is_array($_FILES['custom_doc_file']['name'])) {
            foreach ($_FILES['custom_doc_file']['name'] as $idx => $origName) {
                $fe = $_FILES['custom_doc_file']['error'][$idx] ?? UPLOAD_ERR_NO_FILE;
                if ($fe !== UPLOAD_ERR_OK || trim((string)$origName) === '') continue;
                $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
                $fs = $_FILES['custom_doc_file']['size'][$idx];
                if (!in_array($ext, $allowedExt, true)) { $docErrors[] = 'Произвольный документ #' . ($idx + 1) . ': недопустимый формат'; continue; }
                if ($fs > $maxSize) { $docErrors[] = 'Произвольный документ #' . ($idx + 1) . ': размер > 20 МБ'; continue; }
                if (strpos($origName, '../') !== false || strpos($origName, '..\\') !== false || strpos($origName, '/') !== false || strpos($origName, '\\') !== false) { $docErrors[] = 'Произвольный документ #' . ($idx + 1) . ': недопустимое имя'; continue; }
                $customType = $normalizeSpaces($_POST['custom_doc_type'][$idx] ?? '');
                if ($customType === '') {
                    $docErrors[] = 'Произвольный документ #' . ($idx + 1) . ': укажите тип документа';
                    continue;
                }
                // Normalize: capitalise first letter, lowercase rest (Russian-aware)
                $first = mb_substr($customType, 0, 1, 'UTF-8');
                $rest  = mb_substr($customType, 1, null, 'UTF-8');
                $docTypeName = mb_strtoupper($first, 'UTF-8') . mb_strtolower($rest, 'UTF-8');
                $dtId = null;
                // Auto-create document_type if new (INSERT IGNORE + fallback SELECT)
                try {
                    $idts = $localPdo->prepare("INSERT IGNORE INTO document_types (name, entity_type, category, created_by_user_id, created_by_role) VALUES (:name, :et, 'custom', :uid, :role)");
                    $idts->execute([':name' => $docTypeName, ':et' => $entityType, ':uid' => (int)$_SESSION['user_id'], ':role' => $_SESSION['role_code']]);
                    $dtId = $localPdo->lastInsertId();
                    if (!$dtId) {
                        $g = $localPdo->prepare("SELECT id FROM document_types WHERE name = ? AND entity_type = ? LIMIT 1");
                        $g->execute([$docTypeName, $entityType]);
                        $dtId = $g->fetchColumn() ?: null;
                    }
                } catch (\Exception $ex) {}
                try {
                    $storedName = uniqid('doc_', true) . '.' . $ext;
                    $relativeDir = 'companies/' . $companyId . '/documents/' . $entityType . '/' . $newDriverId;
                    $absoluteDir = storage_path($relativeDir);
                    if (!is_dir($absoluteDir) && !mkdir($absoluteDir, 0755, true)) { $docErrors[] = 'Произвольный документ #' . ($idx + 1) . ': не удалось создать директорию'; continue; }
                    $destPath = $absoluteDir . DIRECTORY_SEPARATOR . $storedName;
                    if (!move_uploaded_file($_FILES['custom_doc_file']['tmp_name'][$idx], $destPath)) { $docErrors[] = 'Произвольный документ #' . ($idx + 1) . ': не удалось сохранить'; continue; }
                    $mime = $_FILES['custom_doc_file']['type'][$idx];
                    $ins = $localPdo->prepare('INSERT INTO documents (entity_type, entity_id, document_type, document_type_id, original_name, stored_name, relative_path, mime_type, file_size, status, uploaded_by_user_id, uploaded_by_role, created_by_user_id, created_by_role) VALUES (:et, :eid, :dtype, :dtid, :oname, :sname, :rpath, :mime, :fsize, :status, :uid, :role, :cuid, :crole)');
                    $ins->execute([':et' => $entityType, ':eid' => $newDriverId, ':dtype' => $docTypeName ?: null, ':dtid' => $dtId, ':oname' => $origName, ':sname' => $storedName, ':rpath' => $relativeDir . '/' . $storedName, ':mime' => $mime, ':fsize' => $fs, ':status' => 'uploaded', ':uid' => (int)$_SESSION['user_id'], ':role' => $_SESSION['role_code'], ':cuid' => (int)$_SESSION['user_id'], ':crole' => $_SESSION['role_code']]);
                    $uploadedDocs[] = $docTypeName . ' (' . $origName . ')';
                } catch (\Exception $ex) { $docErrors[] = 'Произвольный документ #' . ($idx + 1) . ': ошибка сохранения (' . $ex->getMessage() . ')'; }
            }
        }

        $success = true;
    } catch (\PDOException $e) {
        $company = $company ?? null;
        $formError = 'Ошибка создания водителя. Проверьте заполнение формы и попробуйте ещё раз.';
    } catch (\Exception $e) {
        $company = $company ?? null;
        $formError = 'Ошибка создания водителя. Проверьте заполнение формы и попробуйте ещё раз.';
    }

    ob_start();
    require base_path('app/View/pages/company_drivers_create.php');
    $content = ob_get_clean();
    require base_path('app/View/layouts/main.php');
});

$router->post('/company/drivers/modal-create', function () use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);
    require_once base_path('app/Support/driver_create_handler.php');
    handleCompanyDriverCreate($config, $db, 'modal');
});

$router->get('/company/drivers/{id}', function ($id) use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);
    $pageTitle = 'Водитель';
    $pageContext = 'Водители › Компания';

    $companyId = (int)(getSessionCompanyId() ?? 0);
    $archiveError = null;
    $grants = [];
    $logists = [];

    if ($companyId <= 0) {
        $company = null;
        $driver = null;
        $dbError = null;

        ob_start();
        require base_path('app/View/pages/company_driver_view.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
        return;
    }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company) {
            $company = null;
            $driver = null;
            $dbError = null;

            ob_start();
            require base_path('app/View/pages/company_driver_view.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $pageContext = 'Водители › Компания: ' . $company['name'];

        if ($company['status'] !== 'active') {
            $driver = null;
            $dbError = null;

            ob_start();
            require base_path('app/View/pages/company_driver_view.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $dbIdentifier = $company['db_identifier'];
        $localDbConfig = $config['database'];
        $localDbConfig['database'] = $dbIdentifier;
        $localDb = new \App\Core\Database($localDbConfig);
        $localPdo = $localDb->connection();
                applyLocalMigrations($localPdo);

        try {
            $localPdo->query("SELECT 1 FROM drivers LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $migrationSql = file_get_contents(base_path('database/migrations-local/004_create_company_drivers.sql'));
            $localPdo->exec($migrationSql);
        }

        try {
            $localPdo->query("SELECT created_by_user_id FROM drivers LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $localPdo->exec("ALTER TABLE drivers ADD COLUMN created_by_user_id INT UNSIGNED DEFAULT NULL, ADD COLUMN created_by_role VARCHAR(20) DEFAULT NULL");
        }

        try {
            $localPdo->query("SELECT 1 FROM entity_access_grants LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $migrationSql = file_get_contents(base_path('database/migrations-local/009_create_entity_access_grants.sql'));
            $localPdo->exec($migrationSql);
        }

        $driverStmt = $localPdo->prepare('SELECT * FROM drivers WHERE id = ?');
        $driverStmt->execute([(int) $id]);
        $driver = $driverStmt->fetch(PDO::FETCH_ASSOC) ?: null;

        // Role-based access check
        $accessDenied = null;
        $createdByUser = null;
        $updatedByUser = null;
        if ($driver) {
            $isLogist = ($_SESSION['role_code'] ?? '') === 'logist';
            if ($isLogist) {
                $userId = (int)$_SESSION['user_id'];
                $hasGrant = false;
                $grantCheck = $localPdo->prepare("SELECT access_level FROM entity_access_grants WHERE entity_type = 'driver' AND entity_id = ? AND granted_to_user_id = ? AND revoked_at IS NULL LIMIT 1");
                $grantCheck->execute([(int)$id, $userId]);
                $grantRow = $grantCheck->fetch(PDO::FETCH_ASSOC);
                $hasGrant = ($grantRow && in_array($grantRow['access_level'], ['view', 'edit']));
                if ((int)$driver['created_by_user_id'] !== $userId && !$hasGrant) {
                    $accessDenied = 'У вас нет доступа к этой записи.';
                }
            }
            // Load created/updated by user names
            $createdByUser = $localPdo->prepare("SELECT full_name FROM users WHERE id = ?");
            $createdByUser->execute([(int)$driver['created_by_user_id']]);
            $createdByUser = $createdByUser->fetchColumn() ?: null;
            if (!empty($driver['updated_by_user_id'])) {
                $updatedByUser = $localPdo->prepare("SELECT full_name FROM users WHERE id = ?");
                $updatedByUser->execute([(int)$driver['updated_by_user_id']]);
                $updatedByUser = $updatedByUser->fetchColumn() ?: null;
            }
        }

        if ($driver && !$accessDenied) {
            $pageTitle = 'Водитель: ' . $driver['full_name'];
        }

        // Load phones
        $phones = [];
        if ($driver && !$accessDenied) {
            $phones = $localPdo->prepare("SELECT * FROM driver_phones WHERE driver_id = ? ORDER BY is_main DESC, id ASC");
            $phones->execute([(int)$id]);
            $phones = $phones->fetchAll(PDO::FETCH_ASSOC);
        }

        // Load related driver_vehicle_blocks
        $driverBlocks = [];
        if ($driver && !$accessDenied) {
            try {
                $driverBlocks = $localPdo->prepare(
                    "SELECT dvb.id, dvb.status, dvb.driver_id, dvb.vehicle_set_id,
                     vs.set_type, vu1.plate_number AS primary_plate, vu1.brand AS primary_brand, vu1.model AS primary_model,
                     vu2.plate_number AS secondary_plate, vu2.brand AS secondary_brand, vu2.model AS secondary_model
                     FROM driver_vehicle_blocks dvb
                     JOIN vehicle_sets vs ON dvb.vehicle_set_id = vs.id
                     LEFT JOIN vehicle_units vu1 ON vs.primary_vehicle_unit_id = vu1.id
                     LEFT JOIN vehicle_units vu2 ON vs.secondary_vehicle_unit_id = vu2.id
                     WHERE dvb.driver_id = ? AND dvb.status != 'archived' ORDER BY dvb.id DESC"
                );
                $driverBlocks->execute([(int)$id]);
                $driverBlocks = $driverBlocks->fetchAll(PDO::FETCH_ASSOC);
            } catch (\Exception $e) {
                $driverBlocks = [];
            }
        }

        $grants = [];
        $logists = [];
        if (($_SESSION['role_code'] ?? '') === 'company_owner') {
            $grantsStmt = $localPdo->prepare(
                "SELECT g.*, u.full_name AS logist_name
                 FROM entity_access_grants g
                 LEFT JOIN users u ON g.granted_to_user_id = u.id
                 WHERE g.entity_type = ? AND g.entity_id = ?"
            );
            $grantsStmt->execute(['driver', (int)$id]);
            $grants = $grantsStmt->fetchAll(PDO::FETCH_ASSOC);

            $logists = $localPdo->query("SELECT id, full_name, login FROM users WHERE role_code IN ('logist', 'senior_logist') AND status='active' ORDER BY full_name")->fetchAll(PDO::FETCH_ASSOC);
        }

        $dbError = null;
    } catch (\Exception $e) {
        $company = $company ?? null;
        $driver = null;
        $grants = [];
        $logists = [];
        $phones = [];
        $driverBlocks = [];
        $dbError = 'Не удалось загрузить водителя: ' . $e->getMessage();
    }

    ob_start();
    require base_path('app/View/pages/company_driver_view.php');
    $content = ob_get_clean();
    require base_path('app/View/layouts/main.php');
});

$router->get('/company/drivers/{id}/edit', function ($id) use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);
    $pageTitle = 'Редактировать водителя';
    $pageContext = 'Водители › Компания';

    $companyId = (int)(getSessionCompanyId() ?? 0);

    if ($companyId <= 0) {
        $company = null;
        $driver = null;
        $errors = [];
        $old = [];
        $formError = null;

        ob_start();
        require base_path('app/View/pages/company_driver_edit.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
        return;
    }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company) {
            $company = null;
            $driver = null;
            $errors = [];
            $old = [];
            $formError = null;

            ob_start();
            require base_path('app/View/pages/company_driver_edit.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $pageContext = 'Водители › Компания: ' . $company['name'];

        if ($company['status'] !== 'active') {
            $driver = null;
            $errors = [];
            $old = [];
            $formError = null;

            ob_start();
            require base_path('app/View/pages/company_driver_edit.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $dbIdentifier = $company['db_identifier'];
        $localDbConfig = $config['database'];
        $localDbConfig['database'] = $dbIdentifier;
        $localDb = new \App\Core\Database($localDbConfig);
        $localPdo = $localDb->connection();
                applyLocalMigrations($localPdo);

        try {
            $localPdo->query("SELECT 1 FROM drivers LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $migrationSql = file_get_contents(base_path('database/migrations-local/004_create_company_drivers.sql'));
            $localPdo->exec($migrationSql);
        }

        $driverStmt = $localPdo->prepare('SELECT * FROM drivers WHERE id = ?');
        $driverStmt->execute([(int) $id]);
        $driver = $driverStmt->fetch(PDO::FETCH_ASSOC) ?: null;

        if (!$driver) {
            $driver = null;
            $errors = [];
            $old = [];
            $formError = null;

            ob_start();
            require base_path('app/View/pages/company_driver_edit.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $errors = [];
        $old = $driver;
        $formError = null;

        ob_start();
        require base_path('app/View/pages/company_driver_edit.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
    } catch (\Exception $e) {
        $company = $company ?? null;
        $driver = null;
        $errors = [];
        $old = [];
        $formError = 'Не удалось загрузить водителя: ' . $e->getMessage();

        ob_start();
        require base_path('app/View/pages/company_driver_edit.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
    }
});

$router->post('/company/drivers/{id}/edit', function ($id) use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);
    $pageTitle = 'Редактировать водителя';
    $pageContext = 'Водители › Компания';

    $companyId = (int)(getSessionCompanyId() ?? 0);

    if ($companyId <= 0) {
        $company = null;
        $driver = null;
        $errors = [];
        $old = $_POST;
        $formError = 'Компания не найдена';

        ob_start();
        require base_path('app/View/pages/company_driver_edit.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
        return;
    }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company) {
            $company = null;
            $driver = null;
            $errors = [];
            $old = $_POST;
            $formError = 'Компания не найдена';

            ob_start();
            require base_path('app/View/pages/company_driver_edit.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $pageContext = 'Водители › Компания: ' . $company['name'];

        if ($company['status'] !== 'active') {
            $driver = null;
            $errors = [];
            $old = $_POST;
            $formError = 'Редактирование водителей недоступно';

            ob_start();
            require base_path('app/View/pages/company_driver_edit.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $dbIdentifier = $company['db_identifier'];
        $localDbConfig = $config['database'];
        $localDbConfig['database'] = $dbIdentifier;
        $localDb = new \App\Core\Database($localDbConfig);
        $localPdo = $localDb->connection();
                applyLocalMigrations($localPdo);

        try {
            $localPdo->query("SELECT 1 FROM drivers LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $migrationSql = file_get_contents(base_path('database/migrations-local/004_create_company_drivers.sql'));
            $localPdo->exec($migrationSql);
        }

        $driverStmt = $localPdo->prepare('SELECT * FROM drivers WHERE id = ?');
        $driverStmt->execute([(int) $id]);
        $driver = $driverStmt->fetch(PDO::FETCH_ASSOC) ?: null;

        if (!$driver) {
            $driver = null;
            $errors = [];
            $old = $_POST;
            $formError = 'Водитель не найден';

            ob_start();
            require base_path('app/View/pages/company_driver_edit.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        // Access check for logist
        $isLogist = ($_SESSION['role_code'] ?? '') === 'logist';
        if ($isLogist) {
            $userId = (int)$_SESSION['user_id'];
            $hasGrantEdit = false;
            $gc = $localPdo->prepare("SELECT access_level FROM entity_access_grants WHERE entity_type = 'driver' AND entity_id = ? AND granted_to_user_id = ? AND revoked_at IS NULL LIMIT 1");
            $gc->execute([(int)$id, $userId]);
            $gr = $gc->fetch(PDO::FETCH_ASSOC);
            $hasGrantEdit = ($gr && $gr['access_level'] === 'edit');
            if ((int)$driver['created_by_user_id'] !== $userId && !$hasGrantEdit) {
                $errors = []; $old = $driver;
                $formError = (int)$driver['created_by_user_id'] !== $userId ? 'У вас есть доступ на просмотр, но нет права редактировать эту запись.' : 'У вас нет доступа к этой записи.';
                ob_start();
                require base_path('app/View/pages/company_driver_edit.php');
                $content = ob_get_clean();
                require base_path('app/View/layouts/main.php');
                return;
            }
        }

        $errors = [];
        $old = $_POST;
        $formError = null;

        $fullName = trim($_POST['full_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');

        if ($fullName === '') {
            $errors['full_name'] = 'Обязательное поле';
        }

        // Phone is optional — no uniqueness check (business rule: phone is not a unique identifier)

        if (!empty($errors)) {
            ob_start();
            require base_path('app/View/pages/company_driver_edit.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $update = $localPdo->prepare(
            'UPDATE drivers SET
                full_name = :full_name,
                phone = :phone,
                license_number = :license_number,
                license_category = :license_category,
                license_issue_date = :license_issue_date,
                license_expire_date = :license_expire_date,
                passport_number = :passport_number,
                passport_issued_by = :passport_issued_by,
                passport_department_code = :passport_department_code,
                passport_issue_date = :passport_issue_date,
                snils = :snils,
                status = :status,
                comments = :comments,
                updated_by_user_id = :updated_by_user_id,
                updated_by_role = :updated_by_role
             WHERE id = :id'
        );

        $update->execute([
            ':full_name'              => $fullName,
            ':phone'                  => $phone,
            ':license_number'         => $_POST['license_number'] ?? null,
            ':license_category'       => $_POST['license_category'] ?? null,
            ':license_issue_date'     => $_POST['license_issue_date'] ?? null,
            ':license_expire_date'    => $_POST['license_expire_date'] ?? null,
            ':passport_number'        => buildPassportNumber($_POST['passport_series'] ?? null, $_POST['passport_number'] ?? null),
            ':passport_issued_by'     => $_POST['passport_issued_by'] ?? null,
            ':passport_department_code' => $_POST['passport_department_code'] ?? null,
            ':passport_issue_date'    => $_POST['passport_issue_date'] ?? null,
            ':snils'                  => $_POST['snils'] ?? null,
            ':status'                 => $_POST['status'] ?? $driver['status'],
            ':comments'               => $_POST['comments'] ?? null,
            ':updated_by_user_id'     => (int)$_SESSION['user_id'],
            ':updated_by_role'        => $_POST['role_code'] ?? null,
            ':id'                     => (int) $id,
        ]);

        header('Location: /company/drivers/' . $id);
        exit;
    } catch (\Exception $e) {
        $company = $company ?? null;
        $driver = $driver ?? null;
        $errors = [];
        $old = $_POST;
        $formError = 'Ошибка сохранения: ' . $e->getMessage();

        ob_start();
        require base_path('app/View/pages/company_driver_edit.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
    }
});

$router->post('/company/drivers/{id}/archive', function ($id) use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);
    $pageTitle = 'Водитель';
    $pageContext = 'Водители › Компания';

    $companyId = (int)(getSessionCompanyId() ?? 0);
    $archiveError = null;

    if ($companyId <= 0) {
        $company = null;
        $driver = null;
        $dbError = null;

        ob_start();
        require base_path('app/View/pages/company_driver_view.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
        return;
    }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company) {
            $company = null;
            $driver = null;
            $dbError = null;

            ob_start();
            require base_path('app/View/pages/company_driver_view.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $pageContext = 'Водители › Компания: ' . $company['name'];

        if ($company['status'] !== 'active') {
            $driver = null;
            $dbError = null;

            ob_start();
            require base_path('app/View/pages/company_driver_view.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $dbIdentifier = $company['db_identifier'];
        $localDbConfig = $config['database'];
        $localDbConfig['database'] = $dbIdentifier;
        $localDb = new \App\Core\Database($localDbConfig);
        $localPdo = $localDb->connection();
                applyLocalMigrations($localPdo);

        try {
            $localPdo->query("SELECT 1 FROM drivers LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $migrationSql = file_get_contents(base_path('database/migrations-local/004_create_company_drivers.sql'));
            $localPdo->exec($migrationSql);
        }

        $driverStmt = $localPdo->prepare('SELECT * FROM drivers WHERE id = ?');
        $driverStmt->execute([(int) $id]);
        $driver = $driverStmt->fetch(PDO::FETCH_ASSOC) ?: null;

        if (!$driver) {
            $driver = null;
            $dbError = null;

            ob_start();
            require base_path('app/View/pages/company_driver_view.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        // Archive access check: logist can only archive own records
        $isLogist = ($_SESSION['role_code'] ?? '') === 'logist';
        if ($isLogist) {
            $userId = (int)$_SESSION['user_id'];
            if ((int)$driver['created_by_user_id'] !== $userId) {
                $archiveError = 'Логист может архивировать только записи, созданные им самим.';
                $dbError = null;
                ob_start();
                require base_path('app/View/pages/company_driver_view.php');
                $content = ob_get_clean();
                require base_path('app/View/layouts/main.php');
                return;
            }
        }

        $pageTitle = 'Водитель: ' . $driver['full_name'];

        try {
            $localPdo->query("SELECT 1 FROM crews LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $migrationSql = file_get_contents(base_path('database/migrations-local/006_create_company_crews.sql'));
            $localPdo->exec($migrationSql);
        }

        try {
            $localPdo->query("SELECT 1 FROM driver_vehicle_blocks LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $migrationSql = file_get_contents(base_path('database/migrations-local/018_create_driver_vehicle_blocks.sql'));
            $localPdo->exec($migrationSql);
        }

        $crewCheck = $localPdo->prepare(
            'SELECT COUNT(*) FROM crews c
             JOIN driver_vehicle_blocks dvb ON dvb.id = c.driver_vehicle_block_id
             WHERE dvb.driver_id = ?'
        );
        $crewCheck->execute([(int) $id]);
        if ($crewCheck->fetchColumn() > 0) {
            $archiveError = 'Водитель участвует в экипажах. Сначала удалите экипажи.';
            $dbError = null;

            ob_start();
            require base_path('app/View/pages/company_driver_view.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $update = $localPdo->prepare("UPDATE drivers SET status = 'archived' WHERE id = ?");
        $update->execute([(int) $id]);

        header('Location: /company/drivers');
        exit;
    } catch (\Exception $e) {
        $company = $company ?? null;
        $driver = null;
        $dbError = 'Ошибка архивирования: ' . $e->getMessage();

        ob_start();
        require base_path('app/View/pages/company_driver_view.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
    }
});

// --- Driver Modal View (dblclick from list) ---

$router->get('/company/drivers/{id}/modal-view', function ($id) use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);

    $companyId = (int)(getSessionCompanyId() ?? 0);

    if ($companyId <= 0) {
        http_response_code(404);
        echo '<div class="notice warn">Компания не найдена.</div>';
        exit;
    }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company || $company['status'] !== 'active') {
            http_response_code(404);
            echo '<div class="notice warn">Компания не найдена или не активна.</div>';
            exit;
        }

        $dbIdentifier = $company['db_identifier'];
        $localDbConfig = $config['database'];
        $localDbConfig['database'] = $dbIdentifier;
        $localDb = new \App\Core\Database($localDbConfig);
        $localPdo = $localDb->connection();
        applyLocalMigrations($localPdo);

        try {
            $localPdo->query("SELECT 1 FROM drivers LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $migrationSql = file_get_contents(base_path('database/migrations-local/004_create_company_drivers.sql'));
            $localPdo->exec($migrationSql);
        }

        try {
            $localPdo->query("SELECT created_by_user_id FROM drivers LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $localPdo->exec("ALTER TABLE drivers ADD COLUMN created_by_user_id INT UNSIGNED DEFAULT NULL, ADD COLUMN created_by_role VARCHAR(20) DEFAULT NULL");
        }

        try {
            $localPdo->query("SELECT 1 FROM entity_access_grants LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $migrationSql = file_get_contents(base_path('database/migrations-local/009_create_entity_access_grants.sql'));
            $localPdo->exec($migrationSql);
        }

        $driverStmt = $localPdo->prepare('SELECT * FROM drivers WHERE id = ?');
        $driverStmt->execute([(int) $id]);
        $driver = $driverStmt->fetch(PDO::FETCH_ASSOC) ?: null;

        if (!$driver) {
            http_response_code(404);
            echo '<div class="notice warn">Водитель не найден.</div>';
            exit;
        }

        // Access check
        $isLogist = ($_SESSION['role_code'] ?? '') === 'logist';
        if ($isLogist) {
            $userId = (int)$_SESSION['user_id'];
            $hasGrant = false;
            $grantCheck = $localPdo->prepare("SELECT access_level FROM entity_access_grants WHERE entity_type = 'driver' AND entity_id = ? AND granted_to_user_id = ? AND revoked_at IS NULL LIMIT 1");
            $grantCheck->execute([(int)$id, $userId]);
            $grantRow = $grantCheck->fetch(PDO::FETCH_ASSOC);
            $hasGrant = ($grantRow && in_array($grantRow['access_level'], ['view', 'edit']));
            if ((int)$driver['created_by_user_id'] !== $userId && !$hasGrant) {
                http_response_code(403);
                echo '<div class="notice warn">У вас нет доступа к этой записи.</div>';
                exit;
            }
        }

        // Load phones
        $phones = $localPdo->prepare("SELECT * FROM driver_phones WHERE driver_id = ? ORDER BY is_main DESC, id ASC");
        $phones->execute([(int)$id]);
        $phones = $phones->fetchAll(PDO::FETCH_ASSOC);

        $mainPhone = null;
        foreach ($phones as $ph) {
            if ($ph['is_main'] && !empty($ph['phone'])) {
                $mainPhone = $ph['phone'];
                break;
            }
        }
        if ($mainPhone === null && !empty($phones)) {
            $mainPhone = $phones[0]['phone'] ?? null;
        }
        // Fallback to driver.phone
        if ($mainPhone === null && !empty($driver['phone'])) {
            $mainPhone = $driver['phone'];
        }

        // Load documents
        $docStmt = $localPdo->prepare(
            "SELECT id, entity_id, document_type, original_name, mime_type, stored_name, file_size
             FROM documents
             WHERE entity_type = 'driver'
               AND entity_id = ?
               AND deleted_at IS NULL
             ORDER BY id"
        );
        $docStmt->execute([(int)$id]);
        $allDocs = $docStmt->fetchAll(PDO::FETCH_ASSOC);

        $docsByType = ['passport' => [], 'license' => [], 'snils' => [], 'other' => []];
        foreach ($allDocs as $doc) {
            $dt = mb_strtolower($doc['document_type'] ?? '');
            if (strpos($dt, 'паспорт') !== false) {
                $docsByType['passport'][] = $doc;
            } elseif (strpos($dt, 'водительск') !== false || strpos($dt, 'ву') !== false) {
                $docsByType['license'][] = $doc;
            } elseif (strpos($dt, 'снилс') !== false) {
                $docsByType['snils'][] = $doc;
            } else {
                $docsByType['other'][] = $doc;
            }
        }

        $zipAvailable = class_exists('ZipArchive');

        // Permissions
        $canEdit = false;
        $canDelete = false;
        $role = $_SESSION['role_code'] ?? '';
        if ($role === 'company_owner' || $role === 'senior_logist') {
            $canEdit = true;
            $canDelete = true;
        } elseif ($role === 'logist') {
            $userId = (int)$_SESSION['user_id'];
            if ((int)$driver['created_by_user_id'] === $userId) {
                $canEdit = true;
                $canDelete = true;
            } else {
                $gc = $localPdo->prepare("SELECT access_level FROM entity_access_grants WHERE entity_type = 'driver' AND entity_id = ? AND granted_to_user_id = ? AND revoked_at IS NULL LIMIT 1");
                $gc->execute([(int)$id, $userId]);
                $gr = $gc->fetch(PDO::FETCH_ASSOC);
                if ($gr && $gr['access_level'] === 'edit') {
                    $canEdit = true;
                }
            }
        }

        header('Content-Type: text/html; charset=utf-8');
        require base_path('app/View/partials/company_driver_modal_view.php');
        exit;

    } catch (\Exception $e) {
        http_response_code(500);
        echo '<div class="notice warn">Ошибка загрузки: ' . e($e->getMessage()) . '</div>';
        exit;
    }
});

// --- Driver Modal Edit (GET) ---

$router->get('/company/drivers/{id}/modal-edit', function ($id) use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);

    $companyId = (int)(getSessionCompanyId() ?? 0);

    if ($companyId <= 0) {
        http_response_code(404);
        echo '<div class="notice warn">Компания не найдена.</div>';
        exit;
    }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company || $company['status'] !== 'active') {
            http_response_code(404);
            echo '<div class="notice warn">Компания не найдена или не активна.</div>';
            exit;
        }

        $dbIdentifier = $company['db_identifier'];
        $localDbConfig = $config['database'];
        $localDbConfig['database'] = $dbIdentifier;
        $localDb = new \App\Core\Database($localDbConfig);
        $localPdo = $localDb->connection();
        applyLocalMigrations($localPdo);

        try {
            $localPdo->query("SELECT 1 FROM drivers LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $migrationSql = file_get_contents(base_path('database/migrations-local/004_create_company_drivers.sql'));
            $localPdo->exec($migrationSql);
        }

        try {
            $localPdo->query("SELECT created_by_user_id FROM drivers LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $localPdo->exec("ALTER TABLE drivers ADD COLUMN created_by_user_id INT UNSIGNED DEFAULT NULL, ADD COLUMN created_by_role VARCHAR(20) DEFAULT NULL");
        }

        try {
            $localPdo->query("SELECT 1 FROM entity_access_grants LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $migrationSql = file_get_contents(base_path('database/migrations-local/009_create_entity_access_grants.sql'));
            $localPdo->exec($migrationSql);
        }

        $driverStmt = $localPdo->prepare('SELECT * FROM drivers WHERE id = ?');
        $driverStmt->execute([(int) $id]);
        $driver = $driverStmt->fetch(PDO::FETCH_ASSOC) ?: null;

        if (!$driver) {
            http_response_code(404);
            echo '<div class="notice warn">Водитель не найден.</div>';
            exit;
        }

        // Edit permission check
        $role = $_SESSION['role_code'] ?? '';
        $canEdit = false;
        $canDelete = false;
        if ($role === 'company_owner' || $role === 'senior_logist') {
            $canEdit = true;
            $canDelete = true;
        } elseif ($role === 'logist') {
            $userId = (int)$_SESSION['user_id'];
            if ((int)$driver['created_by_user_id'] === $userId) {
                $canEdit = true;
                $canDelete = true;
            } else {
                $gc = $localPdo->prepare("SELECT access_level FROM entity_access_grants WHERE entity_type = 'driver' AND entity_id = ? AND granted_to_user_id = ? AND revoked_at IS NULL LIMIT 1");
                $gc->execute([(int)$id, $userId]);
                $gr = $gc->fetch(PDO::FETCH_ASSOC);
                if ($gr && $gr['access_level'] === 'edit') {
                    $canEdit = true;
                }
            }
        }

        if (!$canEdit) {
            http_response_code(403);
            echo '<div class="notice warn">У вас нет права редактировать эту запись.</div>';
            exit;
        }

        $old = $driver;
        $errors = [];
        $formError = null;

        // Load phones
        $phones = $localPdo->prepare("SELECT * FROM driver_phones WHERE driver_id = ? ORDER BY is_main DESC, id ASC");
        $phones->execute([(int)$id]);
        $phones = $phones->fetchAll(PDO::FETCH_ASSOC);

        // Load documents
        $docStmt = $localPdo->prepare(
            "SELECT id, entity_id, document_type, original_name, mime_type, stored_name, file_size
             FROM documents
             WHERE entity_type = 'driver'
               AND entity_id = ?
               AND deleted_at IS NULL
             ORDER BY id"
        );
        $docStmt->execute([(int)$id]);
        $allDocs = $docStmt->fetchAll(PDO::FETCH_ASSOC);

        $docsByType = ['passport' => [], 'license' => [], 'snils' => [], 'other' => []];
        foreach ($allDocs as $doc) {
            $dt = mb_strtolower($doc['document_type'] ?? '');
            if (strpos($dt, 'паспорт') !== false) {
                $docsByType['passport'][] = $doc;
            } elseif (strpos($dt, 'водительск') !== false || strpos($dt, 'ву') !== false) {
                $docsByType['license'][] = $doc;
            } elseif (strpos($dt, 'снилс') !== false) {
                $docsByType['snils'][] = $doc;
            } else {
                $docsByType['other'][] = $doc;
            }
        }

        // Load docTypes (for create form JS custom doc type suggestions)
        try {
            $docTypes = $localPdo->query("SELECT id, name, code, entity_type FROM document_types WHERE entity_type = 'driver' OR entity_type IS NULL ORDER BY sort_order, name")->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            $docTypes = [];
        }

        header('Content-Type: text/html; charset=utf-8');
        require base_path('app/View/partials/company_driver_modal_edit.php');
        exit;

    } catch (\Exception $e) {
        http_response_code(500);
        echo '<div class="notice warn">Ошибка загрузки: ' . e($e->getMessage()) . '</div>';
        exit;
    }
});

// --- Driver Modal Edit (POST) ---

$router->post('/company/drivers/{id}/modal-edit', function ($id) use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);

    $companyId = (int)(getSessionCompanyId() ?? 0);

    if ($companyId <= 0) {
        http_response_code(404);
        echo '<div class="notice warn">Компания не найдена.</div>';
        exit;
    }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company || $company['status'] !== 'active') {
            http_response_code(404);
            echo '<div class="notice warn">Компания не найдена или не активна.</div>';
            exit;
        }

        $dbIdentifier = $company['db_identifier'];
        $localDbConfig = $config['database'];
        $localDbConfig['database'] = $dbIdentifier;
        $localDb = new \App\Core\Database($localDbConfig);
        $localPdo = $localDb->connection();
        applyLocalMigrations($localPdo);

        try {
            $localPdo->query("SELECT 1 FROM drivers LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $migrationSql = file_get_contents(base_path('database/migrations-local/004_create_company_drivers.sql'));
            $localPdo->exec($migrationSql);
        }

        try {
            $localPdo->query("SELECT created_by_user_id FROM drivers LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $localPdo->exec("ALTER TABLE drivers ADD COLUMN created_by_user_id INT UNSIGNED DEFAULT NULL, ADD COLUMN created_by_role VARCHAR(20) DEFAULT NULL");
        }

        try {
            $localPdo->query("SELECT 1 FROM entity_access_grants LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $migrationSql = file_get_contents(base_path('database/migrations-local/009_create_entity_access_grants.sql'));
            $localPdo->exec($migrationSql);
        }

        try {
            $localPdo->query("SELECT 1 FROM driver_phones LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $localPdo->exec(file_get_contents(base_path('database/migrations-local/015_create_driver_phones.sql')));
        }

        $driverStmt = $localPdo->prepare('SELECT * FROM drivers WHERE id = ?');
        $driverStmt->execute([(int) $id]);
        $driver = $driverStmt->fetch(PDO::FETCH_ASSOC) ?: null;

        if (!$driver) {
            http_response_code(404);
            echo '<div class="notice warn">Водитель не найден.</div>';
            exit;
        }

        // Edit permission check
        $role = $_SESSION['role_code'] ?? '';
        $canEdit = false;
        $canDelete = false;
        if ($role === 'company_owner' || $role === 'senior_logist') {
            $canEdit = true;
            $canDelete = true;
        } elseif ($role === 'logist') {
            $userId = (int)$_SESSION['user_id'];
            if ((int)$driver['created_by_user_id'] === $userId) {
                $canEdit = true;
                $canDelete = true;
            } else {
                $gc = $localPdo->prepare("SELECT access_level FROM entity_access_grants WHERE entity_type = 'driver' AND entity_id = ? AND granted_to_user_id = ? AND revoked_at IS NULL LIMIT 1");
                $gc->execute([(int)$id, $userId]);
                $gr = $gc->fetch(PDO::FETCH_ASSOC);
                if ($gr && $gr['access_level'] === 'edit') {
                    $canEdit = true;
                }
            }
        }

        if (!$canEdit) {
            http_response_code(403);
            echo '<div class="notice warn">У вас нет права редактировать эту запись.</div>';
            exit;
        }

        // Load existing phones and docs (needed for re-render on validation errors)
        $phones = $localPdo->prepare("SELECT * FROM driver_phones WHERE driver_id = ? ORDER BY is_main DESC, id ASC");
        $phones->execute([(int)$id]);
        $phones = $phones->fetchAll(PDO::FETCH_ASSOC);

        $docStmt = $localPdo->prepare(
            "SELECT id, entity_id, document_type, original_name, mime_type, stored_name, file_size
             FROM documents
             WHERE entity_type = 'driver'
               AND entity_id = ?
               AND deleted_at IS NULL
             ORDER BY id"
        );
        $docStmt->execute([(int)$id]);
        $allDocs = $docStmt->fetchAll(PDO::FETCH_ASSOC);

        $docsByType = ['passport' => [], 'license' => [], 'snils' => [], 'other' => []];
        foreach ($allDocs as $doc) {
            $dt = mb_strtolower($doc['document_type'] ?? '');
            if (strpos($dt, 'паспорт') !== false) {
                $docsByType['passport'][] = $doc;
            } elseif (strpos($dt, 'водительск') !== false || strpos($dt, 'ву') !== false) {
                $docsByType['license'][] = $doc;
            } elseif (strpos($dt, 'снилс') !== false) {
                $docsByType['snils'][] = $doc;
            } else {
                $docsByType['other'][] = $doc;
            }
        }

        try {
            $docTypes = $localPdo->query("SELECT id, name, code, entity_type FROM document_types WHERE entity_type = 'driver' OR entity_type IS NULL ORDER BY sort_order, name")->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            $docTypes = [];
        }

        // --- Validation ---
        $errors = [];
        $old = $_POST;

        // full_name: required, 3 words, title case
        $fullName = trim($_POST['full_name'] ?? '');
        if ($fullName === '') {
            $errors['full_name'] = 'Обязательное поле';
        } else {
            $fullName = preg_replace('/\s+/', ' ', $fullName);
            $parts = explode(' ', $fullName);
            if (count($parts) !== 3) {
                $errors['full_name'] = 'ФИО должно состоять из трёх слов';
            } else {
                $titleCase = function ($w) {
                    return mb_strtoupper(mb_substr($w, 0, 1)) . mb_strtolower(mb_substr($w, 1));
                };
                $fullName = implode(' ', array_map($titleCase, $parts));
            }
        }

        // phone: optional, format +7 XXX XXX-XX-XX
        $phone = trim($_POST['phone'] ?? '');
        if ($phone !== '') {
            $digits = preg_replace('/\D/', '', $phone);
            if (strlen($digits) === 11 && $digits[0] === '8') $digits = '7' . substr($digits, 1);
            if (strlen($digits) === 10) $digits = '7' . $digits;
            if (strlen($digits) === 11 && $digits[0] === '7') {
                $phone = '+7 ' . substr($digits, 1, 3) . ' ' . substr($digits, 4, 3) . '-' . substr($digits, 7, 2) . '-' . substr($digits, 9, 2);
            } else {
                $phone = $_POST['phone']; // keep as-is, not critical
            }
        }

        // email: optional, format validation
        $email = trim($_POST['email'] ?? '');
        if ($email !== '') {
            $email = mb_strtolower($email);
            if (!preg_match('/^[^\s@]+@[^\s@]+\.[^\s@]+$/', $email)) {
                $errors['email'] = 'Неверный формат email';
            }
        }

        // passport_number: optional, 10 digits → "0000 000000"
        $passportNumber = trim($_POST['passport_number'] ?? '');
        if ($passportNumber !== '') {
            $digits = preg_replace('/\D/', '', $passportNumber);
            if (strlen($digits) === 10) {
                $passportNumber = substr($digits, 0, 4) . ' ' . substr($digits, 4);
            }
        }

        // passport_department_code: optional, 6 digits → "000-000"
        $passportDeptCode = trim($_POST['passport_department_code'] ?? '');
        if ($passportDeptCode !== '') {
            $digits = preg_replace('/\D/', '', $passportDeptCode);
            if (strlen($digits) === 6) {
                $passportDeptCode = substr($digits, 0, 3) . '-' . substr($digits, 3);
            }
        }

        $passportIssuedBy = trim($_POST['passport_issued_by'] ?? '');
        $passportIssueDate = trim($_POST['passport_issue_date'] ?? '');

        // license_number: optional, 10 digits
        $licenseNumber = trim($_POST['license_number'] ?? '');
        if ($licenseNumber !== '') {
            $digits = preg_replace('/\D/', '', $licenseNumber);
            if (strlen($digits) === 10) {
                $licenseNumber = substr($digits, 0, 4) . ' ' . substr($digits, 4);
            }
        }

        $licenseIssueDate = trim($_POST['license_issue_date'] ?? '');

        // snils: optional, 11 digits → "000-000-000 00"
        $snils = trim($_POST['snils'] ?? '');
        if ($snils !== '') {
            $digits = preg_replace('/\D/', '', $snils);
            if (strlen($digits) === 11) {
                $snils = substr($digits, 0, 3) . '-' . substr($digits, 3, 3) . '-' . substr($digits, 6, 3) . ' ' . substr($digits, 9);
            }
        }

        $comments = trim($_POST['comments'] ?? '');

        if (!empty($errors)) {
            header('Content-Type: text/html; charset=utf-8');
            require base_path('app/View/partials/company_driver_modal_edit.php');
            exit;
        }

        // UPDATE
        $update = $localPdo->prepare(
            'UPDATE drivers SET
                full_name = :full_name,
                phone = :phone,
                email = :email,
                passport_number = :passport_number,
                passport_department_code = :passport_department_code,
                passport_issued_by = :passport_issued_by,
                passport_issue_date = :passport_issue_date,
                license_number = :license_number,
                license_issue_date = :license_issue_date,
                snils = :snils,
                comments = :comments,
                updated_by_user_id = :updated_by_user_id,
                updated_by_role = :updated_by_role
             WHERE id = :id'
        );

        $update->execute([
            ':full_name'                => $fullName,
            ':phone'                    => $phone ?: null,
            ':email'                    => $email ?: null,
            ':passport_number'          => $passportNumber ?: null,
            ':passport_department_code' => $passportDeptCode ?: null,
            ':passport_issued_by'       => $passportIssuedBy ?: null,
            ':passport_issue_date'      => $passportIssueDate ?: null,
            ':license_number'           => $licenseNumber ?: null,
            ':license_issue_date'       => $licenseIssueDate ?: null,
            ':snils'                    => $snils ?: null,
            ':comments'                 => $comments ?: null,
            ':updated_by_user_id'       => (int)$_SESSION['user_id'],
            ':updated_by_role'          => $_SESSION['role_code'] ?? null,
            ':id'                       => (int) $id,
        ]);

        // Replace driver_phones: delete existing extra phones, insert new ones
        // Keep the main phone as-is (first in list or explicitly marked)
        $localPdo->prepare("DELETE FROM driver_phones WHERE driver_id = ? AND is_main = 0")->execute([(int)$id]);

        $extraPhones = $_POST['extra_phones'] ?? [];
        $extraComments = $_POST['extra_phone_comments'] ?? [];
        $insertPhone = $localPdo->prepare(
            "INSERT INTO driver_phones (driver_id, phone, is_main, comment, created_by_user_id, created_by_role)
             VALUES (?, ?, 0, ?, ?, ?)"
        );
        $userId = (int)$_SESSION['user_id'];
        $userRole = $_SESSION['role_code'] ?? null;
        foreach ($extraPhones as $idx => $ep) {
            $ep = trim($ep);
            if ($ep === '') continue;
            $comment = trim($extraComments[$idx] ?? '');
            $insertPhone->execute([(int)$id, $ep, $comment, $userId, $userRole]);
        }

        // Update main phone if provided (update the is_main=1 record, or update drivers.phone)
        $mainPhoneCheck = $localPdo->prepare("SELECT id FROM driver_phones WHERE driver_id = ? AND is_main = 1 LIMIT 1");
        $mainPhoneCheck->execute([(int)$id]);
        $mainPhoneRow = $mainPhoneCheck->fetch(PDO::FETCH_ASSOC);
        if ($mainPhoneRow && !empty($phone)) {
            $localPdo->prepare("UPDATE driver_phones SET phone = ? WHERE id = ?")->execute([$phone, $mainPhoneRow['id']]);
        } elseif (!empty($phone)) {
            // No main phone record — create one
            $localPdo->prepare(
                "INSERT INTO driver_phones (driver_id, phone, is_main, comment, created_by_user_id, created_by_role)
                 VALUES (?, ?, 1, '', ?, ?)"
            )->execute([(int)$id, $phone, $userId, $userRole]);
        }

        // --- Document handling ---
        $allowedExts = ['pdf','doc','docx','rtf','odt','xls','xlsx','csv','ods','jpg','jpeg','png','webp','gif','bmp','tif','tiff','heic','heif','txt'];
        $maxFileSizeDoc = 20 * 1024 * 1024;
        $storageBase = 'companies/' . $companyId . '/documents/driver/' . (int)$id;

        // Map predef doc codes to document_type names
        $predefDocTypeNames = [
            'passport'       => 'Паспорт',
            'driver_license' => 'Водительское удостоверение',
            'snils'          => 'СНИЛС',
        ];

        // 1. Delete specific existing documents
        $deleteExistingDocs = $_POST['delete_existing_doc'] ?? [];
        foreach ($deleteExistingDocs as $docId => $val) {
            if ($val !== '1') {
                continue;
            }
            $localPdo->prepare(
                "UPDATE documents
                    SET deleted_at = NOW(),
                        deleted_by_user_id = ?,
                        delete_comment = 'Archived via modal edit'
                  WHERE id = ?
                    AND entity_type = 'driver'
                    AND entity_id = ?
                    AND deleted_at IS NULL"
            )->execute([(int)$_SESSION['user_id'], (int)$docId, (int)$id]);
        }

        // 2. Replace specific existing documents
        if (!empty($_FILES['existing_doc_file']['name']) && is_array($_FILES['existing_doc_file']['name'])) {
            foreach ($_FILES['existing_doc_file']['name'] as $docId => $origName) {
                $uploadError = $_FILES['existing_doc_file']['error'][$docId] ?? UPLOAD_ERR_NO_FILE;
                if ($uploadError !== UPLOAD_ERR_OK || trim((string)$origName) === '') {
                    continue;
                }

                $docStmt = $localPdo->prepare(
                    "SELECT id, document_type
                       FROM documents
                      WHERE id = ?
                        AND entity_type = 'driver'
                        AND entity_id = ?
                        AND deleted_at IS NULL
                      LIMIT 1"
                );
                $docStmt->execute([(int)$docId, (int)$id]);
                $existingDoc = $docStmt->fetch(PDO::FETCH_ASSOC);
                if (!$existingDoc) {
                    continue;
                }

                $size = (int)($_FILES['existing_doc_file']['size'][$docId] ?? 0);
                if ($size > $maxFileSizeDoc) {
                    continue;
                }

                $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
                if (!in_array($ext, $allowedExts, true)) {
                    continue;
                }

                $tmp = $_FILES['existing_doc_file']['tmp_name'][$docId] ?? '';
                if ($tmp === '') {
                    continue;
                }

                $localPdo->prepare(
                    "UPDATE documents
                        SET deleted_at = NOW(),
                            deleted_by_user_id = ?,
                            delete_comment = 'Replaced via modal edit'
                      WHERE id = ?
                        AND entity_type = 'driver'
                        AND entity_id = ?
                        AND deleted_at IS NULL"
                )->execute([(int)$_SESSION['user_id'], (int)$docId, (int)$id]);

                $storedName = uniqid('doc_', true) . '.' . $ext;
                $absoluteDir = storage_path($storageBase);
                if (!is_dir($absoluteDir)) {
                    mkdir($absoluteDir, 0755, true);
                }
                move_uploaded_file($tmp, $absoluteDir . '/' . $storedName);

                $localPdo->prepare(
                    "INSERT INTO documents (entity_type, entity_id, document_type, original_name, stored_name, relative_path, mime_type, file_size, status, uploaded_by_user_id, uploaded_by_role)
                     VALUES ('driver', ?, ?, ?, ?, ?, ?, ?, 'uploaded', ?, ?)"
                )->execute([
                    (int)$id,
                    $existingDoc['document_type'] ?? null,
                    $origName,
                    $storedName,
                    $storageBase . '/' . $storedName,
                    mime_content_type($absoluteDir . '/' . $storedName) ?: 'application/octet-stream',
                    $size,
                    (int)$_SESSION['user_id'],
                    $_SESSION['role_code'] ?? null
                ]);
            }
        }

        // 2b. Legacy grouped predef replacement fallback
        if (!empty($_FILES['predef_doc']['name'])) {
            foreach ($_FILES['predef_doc']['name'] as $code => $names) {
                if (!is_array($names)) $names = [$names];
                $typeName = $predefDocTypeNames[$code] ?? null;
                if (!$typeName) continue;
                $hasNewFile = false;
                foreach ($names as $n) { if (!empty($n)) { $hasNewFile = true; break; } }
                if (!$hasNewFile) continue;

                $localPdo->prepare(
                    "UPDATE documents SET deleted_at = NOW(), deleted_by_user_id = ?, delete_comment = 'Replaced via modal edit'
                     WHERE entity_type = 'driver' AND entity_id = ? AND document_type = ? AND deleted_at IS NULL"
                )->execute([(int)$_SESSION['user_id'], (int)$id, $typeName]);

                $tmpNames = $_FILES['predef_doc']['tmp_name'][$code];
                $origNames = $_FILES['predef_doc']['name'][$code];
                $sizes = $_FILES['predef_doc']['size'][$code];
                $uploadErrors = $_FILES['predef_doc']['error'][$code];
                if (!is_array($tmpNames)) {
                    $tmpNames = [$tmpNames]; $origNames = [$origNames]; $sizes = [$sizes]; $uploadErrors = [$uploadErrors];
                }
                foreach ($tmpNames as $i => $tmp) {
                    if ($uploadErrors[$i] !== UPLOAD_ERR_OK) continue;
                    if ($sizes[$i] > $maxFileSizeDoc) continue;
                    $orig = $origNames[$i];
                    $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
                    if (!in_array($ext, $allowedExts)) continue;
                    $storedName = uniqid('doc_', true) . '.' . $ext;
                    $absoluteDir = storage_path($storageBase);
                    if (!is_dir($absoluteDir)) mkdir($absoluteDir, 0755, true);
                    move_uploaded_file($tmp, $absoluteDir . '/' . $storedName);
                    $localPdo->prepare(
                        "INSERT INTO documents (entity_type, entity_id, document_type, original_name, stored_name, relative_path, mime_type, file_size, status, uploaded_by_user_id, uploaded_by_role)
                         VALUES ('driver', ?, ?, ?, ?, ?, ?, ?, 'uploaded', ?, ?)"
                    )->execute([
                        (int)$id, $typeName, $orig, $storedName,
                        $storageBase . '/' . $storedName,
                        mime_content_type($absoluteDir . '/' . $storedName) ?: 'application/octet-stream',
                        $sizes[$i], (int)$_SESSION['user_id'], $_SESSION['role_code'] ?? null
                    ]);
                }
            }
        }

        // 3. Process custom_doc_file uploads
        if (!empty($_FILES['custom_doc_file']['name'])) {
            $customTypes = $_POST['custom_doc_type'] ?? [];
            $tmpNames = $_FILES['custom_doc_file']['tmp_name'];
            $origNames = $_FILES['custom_doc_file']['name'];
            $sizes = $_FILES['custom_doc_file']['size'];
            $uploadErrors = $_FILES['custom_doc_file']['error'];
            if (!is_array($tmpNames)) {
                $tmpNames = [$tmpNames]; $origNames = [$origNames]; $sizes = [$sizes]; $uploadErrors = [$uploadErrors];
            }
            foreach ($tmpNames as $i => $tmp) {
                if ($uploadErrors[$i] !== UPLOAD_ERR_OK) continue;
                if ($sizes[$i] > $maxFileSizeDoc) continue;
                $orig = $origNames[$i];
                $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
                if (!in_array($ext, $allowedExts)) continue;
                $typeName = trim($customTypes[$i] ?? '');
                if ($typeName === '') continue;
                $storedName = uniqid('doc_', true) . '.' . $ext;
                $absoluteDir = storage_path($storageBase);
                if (!is_dir($absoluteDir)) mkdir($absoluteDir, 0755, true);
                move_uploaded_file($tmp, $absoluteDir . '/' . $storedName);
                $localPdo->prepare(
                    "INSERT INTO documents (entity_type, entity_id, document_type, original_name, stored_name, relative_path, mime_type, file_size, status, uploaded_by_user_id, uploaded_by_role)
                     VALUES ('driver', ?, ?, ?, ?, ?, ?, ?, 'uploaded', ?, ?)"
                )->execute([
                    (int)$id, $typeName, $orig, $storedName,
                    $storageBase . '/' . $storedName,
                    mime_content_type($absoluteDir . '/' . $storedName) ?: 'application/octet-stream',
                    $sizes[$i], (int)$_SESSION['user_id'], $_SESSION['role_code'] ?? null
                ]);
            }
        }

        // Reload driver, phones, documents for view
        $driverStmt = $localPdo->prepare('SELECT * FROM drivers WHERE id = ?');
        $driverStmt->execute([(int) $id]);
        $driver = $driverStmt->fetch(PDO::FETCH_ASSOC);

        $phones = $localPdo->prepare("SELECT * FROM driver_phones WHERE driver_id = ? ORDER BY is_main DESC, id ASC");
        $phones->execute([(int)$id]);
        $phones = $phones->fetchAll(PDO::FETCH_ASSOC);

        $mainPhone = null;
        foreach ($phones as $ph) {
            if ($ph['is_main'] && !empty($ph['phone'])) {
                $mainPhone = $ph['phone'];
                break;
            }
        }
        if ($mainPhone === null && !empty($phones)) {
            $mainPhone = $phones[0]['phone'] ?? null;
        }
        if ($mainPhone === null && !empty($driver['phone'])) {
            $mainPhone = $driver['phone'];
        }

        $docStmt = $localPdo->prepare(
            "SELECT id, entity_id, document_type, original_name, mime_type, stored_name, file_size
             FROM documents
             WHERE entity_type = 'driver'
               AND entity_id = ?
               AND deleted_at IS NULL
             ORDER BY id"
        );
        $docStmt->execute([(int)$id]);
        $allDocs = $docStmt->fetchAll(PDO::FETCH_ASSOC);

        $docsByType = ['passport' => [], 'license' => [], 'snils' => [], 'other' => []];
        foreach ($allDocs as $doc) {
            $dt = mb_strtolower($doc['document_type'] ?? '');
            if (strpos($dt, 'паспорт') !== false) {
                $docsByType['passport'][] = $doc;
            } elseif (strpos($dt, 'водительск') !== false || strpos($dt, 'ву') !== false) {
                $docsByType['license'][] = $doc;
            } elseif (strpos($dt, 'снилс') !== false) {
                $docsByType['snils'][] = $doc;
            } else {
                $docsByType['other'][] = $doc;
            }
        }

        $zipAvailable = class_exists('ZipArchive');

        header('Content-Type: text/html; charset=utf-8');
        require base_path('app/View/partials/company_driver_modal_view.php');
        exit;

    } catch (\Exception $e) {
        http_response_code(500);
        echo '<div class="notice warn">Ошибка сохранения: ' . e($e->getMessage()) . '</div>';
        exit;
    }
});

// --- Driver Modal Delete (POST) ---

$router->post('/company/drivers/{id}/modal-delete', function ($id) use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);

    header('Content-Type: application/json; charset=utf-8');

    $companyId = (int)(getSessionCompanyId() ?? 0);

    if ($companyId <= 0) {
        echo json_encode(['success' => false, 'error' => 'Компания не найдена.']);
        exit;
    }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company || $company['status'] !== 'active') {
            echo json_encode(['success' => false, 'error' => 'Компания не найдена или не активна.']);
            exit;
        }

        $dbIdentifier = $company['db_identifier'];
        $localDbConfig = $config['database'];
        $localDbConfig['database'] = $dbIdentifier;
        $localDb = new \App\Core\Database($localDbConfig);
        $localPdo = $localDb->connection();
        applyLocalMigrations($localPdo);

        $driverStmt = $localPdo->prepare('SELECT * FROM drivers WHERE id = ?');
        $driverStmt->execute([(int) $id]);
        $driver = $driverStmt->fetch(PDO::FETCH_ASSOC) ?: null;

        if (!$driver) {
            echo json_encode(['success' => false, 'error' => 'Водитель не найден.']);
            exit;
        }

        // Delete permission: company_owner always, logist only created_by
        $role = $_SESSION['role_code'] ?? '';
        if ($role === 'logist') {
            $userId = (int)$_SESSION['user_id'];
            if ((int)$driver['created_by_user_id'] !== $userId) {
                echo json_encode(['success' => false, 'error' => 'У вас нет права архивировать эту запись.']);
                exit;
            }
        }

        // Check crews via driver_vehicle_blocks
        try {
            $localPdo->query("SELECT 1 FROM crews LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $migrationSql = file_get_contents(base_path('database/migrations-local/006_create_company_crews.sql'));
            $localPdo->exec($migrationSql);
        }

        try {
            $localPdo->query("SELECT 1 FROM driver_vehicle_blocks LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $migrationSql = file_get_contents(base_path('database/migrations-local/018_create_driver_vehicle_blocks.sql'));
            $localPdo->exec($migrationSql);
        }

        $crewCheck = $localPdo->prepare(
            'SELECT COUNT(*) FROM crews c
             JOIN driver_vehicle_blocks dvb ON dvb.id = c.driver_vehicle_block_id
             WHERE dvb.driver_id = ?'
        );
        $crewCheck->execute([(int) $id]);
        if ($crewCheck->fetchColumn() > 0) {
            echo json_encode(['success' => false, 'error' => 'Водитель участвует в экипажах. Сначала удалите экипажи.']);
            exit;
        }

        $driverId = (int) $id;
        $storageBase = storage_path('companies/' . $companyId . '/documents/');
        $driverDocDir = $storageBase . 'driver/' . $driverId;

        $docsStmt = $localPdo->prepare(
            "SELECT stored_name
             FROM documents
             WHERE entity_type = 'driver' AND entity_id = ?"
        );
        $docsStmt->execute([$driverId]);
        $driverDocs = $docsStmt->fetchAll(PDO::FETCH_ASSOC);

        $filePathsToDelete = [];
        $realBase = realpath($storageBase);
        foreach ($driverDocs as $doc) {
            $storedName = trim((string) ($doc['stored_name'] ?? ''));
            if ($storedName === '') {
                continue;
            }

            $filePath = $driverDocDir . DIRECTORY_SEPARATOR . $storedName;
            $realFile = realpath($filePath);
            if ($realBase !== false && $realFile !== false && str_starts_with($realFile, $realBase)) {
                $filePathsToDelete[] = $realFile;
            }
        }

        $localPdo->beginTransaction();
        $localPdo->prepare("DELETE FROM entity_access_grants WHERE entity_type = 'driver' AND entity_id = ?")->execute([$driverId]);
        $localPdo->prepare("DELETE FROM documents WHERE entity_type = 'driver' AND entity_id = ?")->execute([$driverId]);
        $localPdo->prepare("DELETE FROM driver_phones WHERE driver_id = ?")->execute([$driverId]);
        $localPdo->prepare("DELETE FROM drivers WHERE id = ?")->execute([$driverId]);
        $localPdo->commit();

        foreach (array_unique($filePathsToDelete) as $storedPath) {
            if (is_string($storedPath) && $storedPath !== '' && is_file($storedPath)) {
                @unlink($storedPath);
            }
        }

        if (is_dir($driverDocDir)) {
            $items = @scandir($driverDocDir) ?: [];
            foreach ($items as $item) {
                if ($item === '.' || $item === '..') {
                    continue;
                }
                $path = $driverDocDir . DIRECTORY_SEPARATOR . $item;
                if (is_file($path)) {
                    @unlink($path);
                }
            }
            @rmdir($driverDocDir);
        }

        echo json_encode(['success' => true]);
        exit;

    } catch (\Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Ошибка: ' . $e->getMessage()]);
        exit;
    }
});

// --- Driver Phones CRUD ---

$router->post('/company/drivers/{driver_id}/phones/create', function ($driver_id) use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);
    $driver_id = (int)$driver_id;
    $companyId = (int)(getSessionCompanyId() ?? 0);
    $redirect = '/company/drivers/' . $driver_id;

    if ($companyId <= 0) { header('Location: ' . $redirect); exit; }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$company || $company['status'] !== 'active') { header('Location: ' . $redirect); exit; }

        $dbIdentifier = $company['db_identifier'];
        $localDbConfig = $config['database']; $localDbConfig['database'] = $dbIdentifier;
        $localDb = new \App\Core\Database($localDbConfig); $localPdo = $localDb->connection();
        applyLocalMigrations($localPdo);

        $dStmt = $localPdo->prepare('SELECT * FROM drivers WHERE id = ?');
        $dStmt->execute([$driver_id]);
        $driver = $dStmt->fetch(PDO::FETCH_ASSOC);
        if (!$driver) { header('Location: /company/drivers'); exit; }

        // Access check
        $isLogist = ($_SESSION['role_code'] ?? '') === 'logist';
        if ($isLogist) {
            $userId = (int)$_SESSION['user_id'];
            $hasGrant = false;
            $gc = $localPdo->prepare("SELECT access_level FROM entity_access_grants WHERE entity_type = 'driver' AND entity_id = ? AND granted_to_user_id = ? AND revoked_at IS NULL LIMIT 1");
            $gc->execute([$driver_id, $userId]);
            $gr = $gc->fetch(PDO::FETCH_ASSOC);
            $hasGrant = ($gr && $gr['access_level'] === 'edit');
            if ((int)$driver['created_by_user_id'] !== $userId && !$hasGrant) { header('Location: ' . $redirect); exit; }
        }

        $phone = trim($_POST['phone'] ?? '');
        $comment = trim($_POST['comment'] ?? '');

        $cntStmt = $localPdo->prepare('SELECT COUNT(*) FROM driver_phones WHERE driver_id = ?');
        $cntStmt->execute([$driver_id]);
        $isFirst = ($cntStmt->fetchColumn() == 0);
        $isMain = $isFirst ? 1 : 0;

        $insert = $localPdo->prepare(
            'INSERT INTO driver_phones (driver_id, phone, is_main, comment, created_by_user_id, created_by_role) VALUES (?, ?, ?, ?, ?, ?)'
        );
        $insert->execute([$driver_id, $phone ?: null, $isMain, $comment ?: null, (int)$_SESSION['user_id'], $_SESSION['role_code'] ?? null]);
    } catch (\Exception $e) {}

    header('Location: ' . $redirect);
    exit;
});

$router->post('/company/drivers/{driver_id}/phones/{phone_id}/edit', function ($driver_id, $phone_id) use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);
    $driver_id = (int)$driver_id; $phone_id = (int)$phone_id;
    $companyId = (int)(getSessionCompanyId() ?? 0);
    $redirect = '/company/drivers/' . $driver_id;

    if ($companyId <= 0) { header('Location: ' . $redirect); exit; }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$company || $company['status'] !== 'active') { header('Location: ' . $redirect); exit; }

        $dbIdentifier = $company['db_identifier'];
        $localDbConfig = $config['database']; $localDbConfig['database'] = $dbIdentifier;
        $localDb = new \App\Core\Database($localDbConfig); $localPdo = $localDb->connection();
        applyLocalMigrations($localPdo);

        $dStmt = $localPdo->prepare('SELECT * FROM drivers WHERE id = ?');
        $dStmt->execute([$driver_id]);
        $driver = $dStmt->fetch(PDO::FETCH_ASSOC);
        if (!$driver) { header('Location: /company/drivers'); exit; }

        // Access check
        $isLogist = ($_SESSION['role_code'] ?? '') === 'logist';
        if ($isLogist) {
            $userId = (int)$_SESSION['user_id'];
            $hasGrant = false;
            $gc = $localPdo->prepare("SELECT access_level FROM entity_access_grants WHERE entity_type = 'driver' AND entity_id = ? AND granted_to_user_id = ? AND revoked_at IS NULL LIMIT 1");
            $gc->execute([$driver_id, $userId]);
            $gr = $gc->fetch(PDO::FETCH_ASSOC);
            $hasGrant = ($gr && $gr['access_level'] === 'edit');
            if ((int)$driver['created_by_user_id'] !== $userId && !$hasGrant) { header('Location: ' . $redirect); exit; }
        }

        $phone = trim($_POST['phone'] ?? '');
        $comment = trim($_POST['comment'] ?? '');

        $update = $localPdo->prepare(
            'UPDATE driver_phones SET phone = ?, comment = ?, updated_by_user_id = ?, updated_by_role = ? WHERE id = ? AND driver_id = ?'
        );
        $update->execute([$phone ?: null, $comment ?: null, (int)$_SESSION['user_id'], $_SESSION['role_code'] ?? null, $phone_id, $driver_id]);
    } catch (\Exception $e) {}

    header('Location: ' . $redirect);
    exit;
});

$router->post('/company/drivers/{driver_id}/phones/{phone_id}/delete', function ($driver_id, $phone_id) use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);
    $driver_id = (int)$driver_id; $phone_id = (int)$phone_id;
    $companyId = (int)(getSessionCompanyId() ?? 0);
    $redirect = '/company/drivers/' . $driver_id;

    if ($companyId <= 0) { header('Location: ' . $redirect); exit; }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$company || $company['status'] !== 'active') { header('Location: ' . $redirect); exit; }

        $dbIdentifier = $company['db_identifier'];
        $localDbConfig = $config['database']; $localDbConfig['database'] = $dbIdentifier;
        $localDb = new \App\Core\Database($localDbConfig); $localPdo = $localDb->connection();
        applyLocalMigrations($localPdo);

        $dStmt = $localPdo->prepare('SELECT * FROM drivers WHERE id = ?');
        $dStmt->execute([$driver_id]);
        $driver = $dStmt->fetch(PDO::FETCH_ASSOC);
        if (!$driver) { header('Location: /company/drivers'); exit; }

        // Access check
        $isLogist = ($_SESSION['role_code'] ?? '') === 'logist';
        if ($isLogist) {
            $userId = (int)$_SESSION['user_id'];
            $hasGrant = false;
            $gc = $localPdo->prepare("SELECT access_level FROM entity_access_grants WHERE entity_type = 'driver' AND entity_id = ? AND granted_to_user_id = ? AND revoked_at IS NULL LIMIT 1");
            $gc->execute([$driver_id, $userId]);
            $gr = $gc->fetch(PDO::FETCH_ASSOC);
            $hasGrant = ($gr && $gr['access_level'] === 'edit');
            if ((int)$driver['created_by_user_id'] !== $userId && !$hasGrant) { header('Location: ' . $redirect); exit; }
        }

        $phoneStmt = $localPdo->prepare('SELECT is_main FROM driver_phones WHERE id = ? AND driver_id = ?');
        $phoneStmt->execute([$phone_id, $driver_id]);
        $phoneData = $phoneStmt->fetch(PDO::FETCH_ASSOC);

        $delStmt = $localPdo->prepare('DELETE FROM driver_phones WHERE id = ? AND driver_id = ?');
        $delStmt->execute([$phone_id, $driver_id]);

        if ($phoneData && $phoneData['is_main']) {
            $first = $localPdo->prepare('SELECT id FROM driver_phones WHERE driver_id = ? ORDER BY id ASC LIMIT 1');
            $first->execute([$driver_id]);
            $firstRow = $first->fetch(PDO::FETCH_ASSOC);
            if ($firstRow) {
                $localPdo->prepare('UPDATE driver_phones SET is_main = 1 WHERE id = ?')->execute([$firstRow['id']]);
            }
        }
    } catch (\Exception $e) {}

    header('Location: ' . $redirect);
    exit;
});

$router->post('/company/drivers/{driver_id}/phones/{phone_id}/set-main', function ($driver_id, $phone_id) use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);
    $driver_id = (int)$driver_id; $phone_id = (int)$phone_id;
    $companyId = (int)(getSessionCompanyId() ?? 0);
    $redirect = '/company/drivers/' . $driver_id;

    if ($companyId <= 0) { header('Location: ' . $redirect); exit; }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$company || $company['status'] !== 'active') { header('Location: ' . $redirect); exit; }

        $dbIdentifier = $company['db_identifier'];
        $localDbConfig = $config['database']; $localDbConfig['database'] = $dbIdentifier;
        $localDb = new \App\Core\Database($localDbConfig); $localPdo = $localDb->connection();
        applyLocalMigrations($localPdo);

        $dStmt = $localPdo->prepare('SELECT * FROM drivers WHERE id = ?');
        $dStmt->execute([$driver_id]);
        $driver = $dStmt->fetch(PDO::FETCH_ASSOC);
        if (!$driver) { header('Location: /company/drivers'); exit; }

        // Access check
        $isLogist = ($_SESSION['role_code'] ?? '') === 'logist';
        if ($isLogist) {
            $userId = (int)$_SESSION['user_id'];
            $hasGrant = false;
            $gc = $localPdo->prepare("SELECT access_level FROM entity_access_grants WHERE entity_type = 'driver' AND entity_id = ? AND granted_to_user_id = ? AND revoked_at IS NULL LIMIT 1");
            $gc->execute([$driver_id, $userId]);
            $gr = $gc->fetch(PDO::FETCH_ASSOC);
            $hasGrant = ($gr && $gr['access_level'] === 'edit');
            if ((int)$driver['created_by_user_id'] !== $userId && !$hasGrant) { header('Location: ' . $redirect); exit; }
        }

        $localPdo->prepare('UPDATE driver_phones SET is_main = 0 WHERE driver_id = ?')->execute([$driver_id]);
        $localPdo->prepare('UPDATE driver_phones SET is_main = 1 WHERE id = ? AND driver_id = ?')->execute([$phone_id, $driver_id]);
    } catch (\Exception $e) {}

    header('Location: ' . $redirect);
    exit;
});

$router->get('/company/vehicles', function () use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);
    $pageTitle = 'Транспортные единицы';
    $pageContext = 'Транспортные единицы › Компания';

    $companyId = (int)(getSessionCompanyId() ?? 0);

    if ($companyId <= 0) {
        $company = null;
        $vehicles = [];
        $dbError = null;

        ob_start();
        require base_path('app/View/pages/company_vehicles.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
        return;
    }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company) {
            $company = null;
            $vehicles = [];
            $dbError = null;

            ob_start();
            require base_path('app/View/pages/company_vehicles.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $pageContext = 'Транспортные единицы › Компания: ' . $company['name'];

        if ($company['status'] !== 'active') {
            $vehicles = [];
            $dbError = null;

            ob_start();
            require base_path('app/View/pages/company_vehicles.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $dbIdentifier = $company['db_identifier'];
        $localDbConfig = $config['database'];
        $localDbConfig['database'] = $dbIdentifier;
        $localDb = new \App\Core\Database($localDbConfig);
        $localPdo = $localDb->connection();
                applyLocalMigrations($localPdo);

        try {
            $localPdo->query("SELECT 1 FROM vehicle_units LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $migrationSql = file_get_contents(base_path('database/migrations-local/005_create_company_vehicles.sql'));
            if ($migrationSql !== false) {
                $localPdo->exec($migrationSql);
            }
            try {
                $localPdo->exec("RENAME TABLE vehicles TO vehicle_units");
            } catch (\Exception $renameEx) {
                // Table may already be vehicle_units or vehicles may not exist
            }
        }

        try {
            $localPdo->query("SELECT created_by_user_id FROM vehicle_units LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $localPdo->exec("ALTER TABLE vehicle_units ADD COLUMN created_by_user_id INT UNSIGNED DEFAULT NULL, ADD COLUMN created_by_role VARCHAR(20) DEFAULT NULL");
        }

        try {
            $localPdo->query("SELECT 1 FROM entity_access_grants LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $migrationSql = file_get_contents(base_path('database/migrations-local/009_create_entity_access_grants.sql'));
            $localPdo->exec($migrationSql);
        }

        $isLogist = ($_SESSION['role_code'] ?? '') === 'logist';
        if ($isLogist) {
            $userId = (int)$_SESSION['user_id'];
            $vehicleStmt = $localPdo->prepare(
                "SELECT * FROM vehicle_units WHERE (created_by_user_id = ? OR id IN (SELECT entity_id FROM entity_access_grants WHERE entity_type = 'vehicle_unit' AND granted_to_user_id = ? AND access_level = 'view')) ORDER BY created_at DESC"
            );
            $vehicleStmt->execute([$userId, $userId]);
            $vehicles = $vehicleStmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $vehicleStmt = $localPdo->query("SELECT * FROM vehicle_units ORDER BY created_at DESC");
            $vehicles = $vehicleStmt->fetchAll(PDO::FETCH_ASSOC);
        }
        $dbError = null;
    } catch (\Exception $e) {
        $company = $company ?? null;
        $vehicles = [];
        $dbError = 'Не удалось подключиться к базе данных компании.';
    }

    ob_start();
    require base_path('app/View/pages/company_vehicles.php');
    $content = ob_get_clean();
    require base_path('app/View/layouts/main.php');
});

$router->get('/company/vehicles/create', function () use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);
    $pageTitle = 'Добавить транспортную единицу';
    $pageContext = 'Транспортные единицы › Компания';

    $companyId = (int)(getSessionCompanyId() ?? 0);

    if ($companyId <= 0) {
        $company = null;
        $success = false;
        $errors = [];
        $old = [];
        $formError = null;
        $createdVehicle = null;

        ob_start();
        require base_path('app/View/pages/company_vehicles_create.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
        return;
    }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company) {
            $company = null;
            $success = false;
            $errors = [];
            $old = [];
            $formError = null;
            $createdVehicle = null;

            ob_start();
            require base_path('app/View/pages/company_vehicles_create.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $pageContext = 'Транспортные единицы › Компания: ' . $company['name'];

        $success = false;
        $errors = [];
        $old = [];
        $formError = null;
        $createdVehicle = null;
    } catch (\Exception $e) {
        $company = null;
        $success = false;
        $errors = [];
        $old = [];
        $formError = 'Ошибка загрузки данных: ' . $e->getMessage();
        $createdVehicle = null;
    }

    ob_start();
    require base_path('app/View/pages/company_vehicles_create.php');
    $content = ob_get_clean();
    require base_path('app/View/layouts/main.php');
});

$router->post('/company/vehicles/create', function () use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);
    $pageTitle = 'Добавить транспортную единицу';
    $pageContext = 'Транспортные единицы › Компания';

    $companyId = (int)(getSessionCompanyId() ?? 0);
    $errors = [];
    $old = $_POST;
    $formError = null;
    $success = false;
    $createdVehicle = null;

    if ($companyId <= 0) {
        $company = null;
        $formError = 'Компания не найдена';

        ob_start();
        require base_path('app/View/pages/company_vehicles_create.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
        return;
    }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company) {
            $company = null;
            $formError = 'Компания не найдена';

            ob_start();
            require base_path('app/View/pages/company_vehicles_create.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $pageContext = 'Транспортные единицы › Компания: ' . $company['name'];

        if ($company['status'] !== 'active') {
            $formError = 'Добавление транспорта недоступно';

            ob_start();
            require base_path('app/View/pages/company_vehicles_create.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $dbIdentifier = $company['db_identifier'];
        $localDbConfig = $config['database'];
        $localDbConfig['database'] = $dbIdentifier;
        $localDb = new \App\Core\Database($localDbConfig);
        $localPdo = $localDb->connection();
                applyLocalMigrations($localPdo);

        try {
            $localPdo->query("SELECT 1 FROM vehicle_units LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $migrationSql = file_get_contents(base_path('database/migrations-local/005_create_company_vehicles.sql'));
            if ($migrationSql !== false) {
                $localPdo->exec($migrationSql);
            }
            try {
                $localPdo->exec("RENAME TABLE vehicles TO vehicle_units");
            } catch (\Exception $renameEx) {
                // Table may already be vehicle_units or vehicles may not exist
            }
        }

        $plateNumber = trim($_POST['plate_number'] ?? '');
        $unitType = trim($_POST['unit_type'] ?? '');

        if ($plateNumber === '') {
            $errors['plate_number'] = 'Обязательное поле';
        }
        if ($unitType === '') {
            $errors['unit_type'] = 'Укажите тип транспортной единицы';
        }

        if (empty($errors['plate_number'])) {
            $checkStmt = $localPdo->prepare('SELECT COUNT(*) FROM vehicle_units WHERE plate_number = ?');
            $checkStmt->execute([$plateNumber]);
            if ($checkStmt->fetchColumn() > 0) {
                $errors['plate_number'] = 'Госномер уже используется в этой компании';
            }
        }

        if (!empty($errors)) {
            ob_start();
            require base_path('app/View/pages/company_vehicles_create.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $brand = trim($_POST['brand'] ?? '');
        $model = trim($_POST['model'] ?? '');
        $vin = trim($_POST['vin'] ?? '');
        $stsNumber = trim($_POST['sts_number'] ?? '');
        $ptsNumber = trim($_POST['pts_number'] ?? '');
        $capacityTons = trim($_POST['capacity_tons'] ?? '');
        $volumeM3 = trim($_POST['volume_m3'] ?? '');
        $comments = trim($_POST['comments'] ?? '');

        $insert = $localPdo->prepare(
            'INSERT INTO vehicle_units (plate_number, brand, model, unit_type, vin,
             sts_number, pts_number, capacity_tons, volume_m3, status, comments, created_by_user_id, created_by_role)
             VALUES (:plate_number, :brand, :model, :unit_type, :vin,
             :sts_number, :pts_number, :capacity_tons, :volume_m3, :status, :comments, :created_by_user_id, :created_by_role)'
        );
        $insert->execute([
            ':plate_number'       => $plateNumber,
            ':brand'              => $brand !== '' ? $brand : null,
            ':model'              => $model !== '' ? $model : null,
            ':unit_type'          => $unitType,
            ':vin'                => $vin !== '' ? $vin : null,
            ':sts_number'         => $stsNumber !== '' ? $stsNumber : null,
            ':pts_number'         => $ptsNumber !== '' ? $ptsNumber : null,
            ':capacity_tons'      => $capacityTons !== '' ? $capacityTons : null,
            ':volume_m3'          => $volumeM3 !== '' ? $volumeM3 : null,
            ':status'             => 'active',
            ':comments'           => $comments !== '' ? $comments : null,
            ':created_by_user_id' => (int)$_SESSION['user_id'],
            ':created_by_role'    => $_SESSION['role_code'],
        ]);

        $lastId = $localPdo->lastInsertId();
        $selectStmt = $localPdo->prepare('SELECT * FROM vehicle_units WHERE id = ?');
        $selectStmt->execute([$lastId]);
        $createdVehicle = $selectStmt->fetch(PDO::FETCH_ASSOC);
        $success = true;
    } catch (\Exception $e) {
        $company = $company ?? null;
        $formError = 'Ошибка добавления транспорта: ' . $e->getMessage();
    }

    ob_start();
    require base_path('app/View/pages/company_vehicles_create.php');
    $content = ob_get_clean();
    require base_path('app/View/layouts/main.php');
});

// --- Vehicle View ---
$router->get('/company/vehicles/{id}', function ($vehicleId) use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);
    $vehicleId = (int)$vehicleId;
    $pageTitle = 'Транспортные единицы';
    $pageContext = 'Транспортные единицы › Компания';
    $entityNotFound = false;
    $grants = [];
    $logists = [];

    $companyId = (int)(getSessionCompanyId() ?? 0);

    if ($companyId <= 0) {
        $company = null;
        $vehicle = null;
        $dbError = null;

        ob_start();
        require base_path('app/View/pages/company_vehicle_view.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
        return;
    }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company) {
            $company = null;
            $vehicle = null;
            $dbError = null;

            ob_start();
            require base_path('app/View/pages/company_vehicle_view.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $pageContext = 'Транспортные единицы › Компания: ' . $company['name'];

        if ($company['status'] !== 'active') {
            $vehicle = null;
            $dbError = null;

            ob_start();
            require base_path('app/View/pages/company_vehicle_view.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $dbIdentifier = $company['db_identifier'];
        $localDbConfig = $config['database'];
        $localDbConfig['database'] = $dbIdentifier;
        $localDb = new \App\Core\Database($localDbConfig);
        $localPdo = $localDb->connection();
                applyLocalMigrations($localPdo);

        try {
            $localPdo->query("SELECT 1 FROM vehicle_units LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $migrationSql = file_get_contents(base_path('database/migrations-local/005_create_company_vehicles.sql'));
            if ($migrationSql !== false) {
                $localPdo->exec($migrationSql);
            }
            try {
                $localPdo->exec("RENAME TABLE vehicles TO vehicle_units");
            } catch (\Exception $renameEx) {
                // Table may already be vehicle_units or vehicles may not exist
            }
        }

        try {
            $localPdo->query("SELECT created_by_user_id FROM vehicle_units LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $localPdo->exec("ALTER TABLE vehicle_units ADD COLUMN created_by_user_id INT UNSIGNED DEFAULT NULL, ADD COLUMN created_by_role VARCHAR(20) DEFAULT NULL");
        }

        try {
            $localPdo->query("SELECT 1 FROM entity_access_grants LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $migrationSql = file_get_contents(base_path('database/migrations-local/009_create_entity_access_grants.sql'));
            $localPdo->exec($migrationSql);
        }

        $vStmt = $localPdo->prepare('SELECT * FROM vehicle_units WHERE id = ?');
        $vStmt->execute([$vehicleId]);
        $vehicle = $vStmt->fetch(PDO::FETCH_ASSOC);

        if (!$vehicle) {
            $entityNotFound = true;
        }

        $accessDenied = null;
        $relatedSets = [];
        if ($vehicle) {
            // Access check for logist
            $isLogist = ($_SESSION['role_code'] ?? '') === 'logist';
            if ($isLogist) {
                $userId = (int)$_SESSION['user_id'];
                $hasGrant = false;
                $gc = $localPdo->prepare("SELECT access_level FROM entity_access_grants WHERE entity_type = 'vehicle_unit' AND entity_id = ? AND granted_to_user_id = ? AND revoked_at IS NULL LIMIT 1");
                $gc->execute([$vehicleId, $userId]);
                $gr = $gc->fetch(PDO::FETCH_ASSOC);
                $hasGrant = ($gr && in_array($gr['access_level'], ['view', 'edit']));
                if ((int)$vehicle['created_by_user_id'] !== $userId && !$hasGrant) {
                    $accessDenied = 'У вас нет доступа к этой записи.';
                }
            }
            // Load related vehicle sets
            if (!$accessDenied) {
                $rsStmt = $localPdo->prepare(
                    "SELECT vs.*, vu1.plate_number AS primary_plate, vu1.brand AS primary_brand,
                     vu2.plate_number AS secondary_plate, vu2.brand AS secondary_brand
                     FROM vehicle_sets vs
                     LEFT JOIN vehicle_units vu1 ON vs.primary_vehicle_unit_id = vu1.id
                     LEFT JOIN vehicle_units vu2 ON vs.secondary_vehicle_unit_id = vu2.id
                     WHERE vs.primary_vehicle_unit_id = ? OR vs.secondary_vehicle_unit_id = ?
                     ORDER BY vs.id DESC"
                );
                $rsStmt->execute([$vehicleId, $vehicleId]);
                $relatedSets = $rsStmt->fetchAll(PDO::FETCH_ASSOC);
            }
        }

        $pageTitle = $vehicle ? 'Транспортная единица: ' . $vehicle['plate_number'] : 'Транспортная единица';

        $grants = [];
        $logists = [];
        if (($_SESSION['role_code'] ?? '') === 'company_owner') {
            $grantsStmt = $localPdo->prepare(
                "SELECT g.*, u.full_name AS logist_name
                 FROM entity_access_grants g
                 LEFT JOIN users u ON g.granted_to_user_id = u.id
                 WHERE g.entity_type = ? AND g.entity_id = ?"
            );
            $grantsStmt->execute(['vehicle_unit', $vehicleId]);
            $grants = $grantsStmt->fetchAll(PDO::FETCH_ASSOC);

            $logists = $localPdo->query("SELECT id, full_name, login FROM users WHERE role_code IN ('logist', 'senior_logist') AND status='active' ORDER BY full_name")->fetchAll(PDO::FETCH_ASSOC);
        }

        $dbError = null;
    } catch (\Exception $e) {
        $company = $company ?? null;
        $vehicle = null;
        $grants = [];
        $logists = [];
        $relatedSets = [];
        $dbError = 'Не удалось подключиться к базе данных компании.';
    }

    ob_start();
    require base_path('app/View/pages/company_vehicle_view.php');
    $content = ob_get_clean();
    require base_path('app/View/layouts/main.php');
});

// --- Vehicle Edit (form) ---
$router->get('/company/vehicles/{id}/edit', function ($vehicleId) use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);
    $vehicleId = (int)$vehicleId;
    $pageTitle = 'Редактировать транспортную единицу';
    $pageContext = 'Транспортные единицы › Компания';
    $entityNotFound = false;

    $companyId = (int)(getSessionCompanyId() ?? 0);
    $success = false;
    $formError = null;
    $errors = [];

    if ($companyId <= 0) {
        $company = null;
        $vehicle = null;
        $old = [];
        $dbError = null;

        ob_start();
        require base_path('app/View/pages/company_vehicle_edit.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
        return;
    }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company) {
            $company = null;
            $vehicle = null;
            $old = [];
            $dbError = null;

            ob_start();
            require base_path('app/View/pages/company_vehicle_edit.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $pageContext = 'Транспортные единицы › Компания: ' . $company['name'];

        if ($company['status'] !== 'active') {
            $vehicle = null;
            $old = [];
            $dbError = null;

            ob_start();
            require base_path('app/View/pages/company_vehicle_edit.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $dbIdentifier = $company['db_identifier'];
        $localDbConfig = $config['database'];
        $localDbConfig['database'] = $dbIdentifier;
        $localDb = new \App\Core\Database($localDbConfig);
        $localPdo = $localDb->connection();
                applyLocalMigrations($localPdo);

        try {
            $localPdo->query("SELECT 1 FROM vehicle_units LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $migrationSql = file_get_contents(base_path('database/migrations-local/005_create_company_vehicles.sql'));
            if ($migrationSql !== false) {
                $localPdo->exec($migrationSql);
            }
            try {
                $localPdo->exec("RENAME TABLE vehicles TO vehicle_units");
            } catch (\Exception $renameEx) {
                // Table may already be vehicle_units or vehicles may not exist
            }
        }

        $vStmt = $localPdo->prepare('SELECT * FROM vehicle_units WHERE id = ?');
        $vStmt->execute([$vehicleId]);
        $vehicle = $vStmt->fetch(PDO::FETCH_ASSOC);

        if (!$vehicle) {
            $entityNotFound = true;
            $old = [];
        } else {
            $old = $vehicle;
        }

        $dbError = null;
    } catch (\Exception $e) {
        $company = $company ?? null;
        $vehicle = null;
        $old = [];
        $dbError = 'Не удалось подключиться к базе данных компании.';
    }

    ob_start();
    require base_path('app/View/pages/company_vehicle_edit.php');
    $content = ob_get_clean();
    require base_path('app/View/layouts/main.php');
});

// --- Vehicle Edit (handle) ---
$router->post('/company/vehicles/{id}/edit', function ($vehicleId) use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);
    $vehicleId = (int)$vehicleId;
    $pageTitle = 'Редактировать транспортную единицу';
    $pageContext = 'Транспортные единицы › Компания';
    $entityNotFound = false;

    $companyId = (int)(getSessionCompanyId() ?? 0);
    $errors = [];
    $old = $_POST;
    $formError = null;
    $success = false;

    if ($companyId <= 0) {
        $company = null;
        $vehicle = null;
        $dbError = null;
        $formError = 'Компания не найдена';

        ob_start();
        require base_path('app/View/pages/company_vehicle_edit.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
        return;
    }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company) {
            $company = null;
            $vehicle = null;
            $dbError = null;
            $formError = 'Компания не найдена';

            ob_start();
            require base_path('app/View/pages/company_vehicle_edit.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $pageContext = 'Транспортные единицы › Компания: ' . $company['name'];

        if ($company['status'] !== 'active') {
            $vehicle = null;
            $dbError = null;
            $formError = 'Редактирование транспорта недоступно';

            ob_start();
            require base_path('app/View/pages/company_vehicle_edit.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $dbIdentifier = $company['db_identifier'];
        $localDbConfig = $config['database'];
        $localDbConfig['database'] = $dbIdentifier;
        $localDb = new \App\Core\Database($localDbConfig);
        $localPdo = $localDb->connection();
                applyLocalMigrations($localPdo);

        try {
            $localPdo->query("SELECT 1 FROM vehicle_units LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $migrationSql = file_get_contents(base_path('database/migrations-local/005_create_company_vehicles.sql'));
            if ($migrationSql !== false) {
                $localPdo->exec($migrationSql);
            }
            try {
                $localPdo->exec("RENAME TABLE vehicles TO vehicle_units");
            } catch (\Exception $renameEx) {
                // Table may already be vehicle_units or vehicles may not exist
            }
        }

        $vStmt = $localPdo->prepare('SELECT * FROM vehicle_units WHERE id = ?');
        $vStmt->execute([$vehicleId]);
        $vehicle = $vStmt->fetch(PDO::FETCH_ASSOC);

        if (!$vehicle) {
            $entityNotFound = true;
            $dbError = null;

            ob_start();
            require base_path('app/View/pages/company_vehicle_edit.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $plateNumber = trim($_POST['plate_number'] ?? '');
        $unitType = trim($_POST['unit_type'] ?? '');

        if ($plateNumber === '') {
            $errors['plate_number'] = 'Обязательное поле';
        }
        if ($unitType === '') {
            $errors['unit_type'] = 'Укажите тип транспортной единицы';
        }

        if (empty($errors['plate_number'])) {
            $checkStmt = $localPdo->prepare('SELECT COUNT(*) FROM vehicle_units WHERE plate_number = ? AND id != ?');
            $checkStmt->execute([$plateNumber, $vehicleId]);
            if ($checkStmt->fetchColumn() > 0) {
                $errors['plate_number'] = 'Госномер уже используется в этой компании';
            }
        }

        if (!empty($errors)) {
            ob_start();
            require base_path('app/View/pages/company_vehicle_edit.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $brand = trim($_POST['brand'] ?? '');
        $model = trim($_POST['model'] ?? '');
        $vin = trim($_POST['vin'] ?? '');
        $stsNumber = trim($_POST['sts_number'] ?? '');
        $ptsNumber = trim($_POST['pts_number'] ?? '');
        $capacityTons = trim($_POST['capacity_tons'] ?? '');
        $volumeM3 = trim($_POST['volume_m3'] ?? '');
        $status = trim($_POST['status'] ?? 'active');
        $comments = trim($_POST['comments'] ?? '');

        $update = $localPdo->prepare(
            'UPDATE vehicle_units SET plate_number = :plate_number, brand = :brand, model = :model,
             unit_type = :unit_type, vin = :vin, sts_number = :sts_number,
             pts_number = :pts_number, capacity_tons = :capacity_tons, volume_m3 = :volume_m3,
             status = :status, comments = :comments
             WHERE id = :id'
        );
        $update->execute([
            ':plate_number'  => $plateNumber,
            ':brand'         => $brand !== '' ? $brand : null,
            ':model'         => $model !== '' ? $model : null,
            ':unit_type'     => $unitType,
            ':vin'           => $vin !== '' ? $vin : null,
            ':sts_number'    => $stsNumber !== '' ? $stsNumber : null,
            ':pts_number'    => $ptsNumber !== '' ? $ptsNumber : null,
            ':capacity_tons' => $capacityTons !== '' ? $capacityTons : null,
            ':volume_m3'     => $volumeM3 !== '' ? $volumeM3 : null,
            ':status'        => $status,
            ':comments'      => $comments !== '' ? $comments : null,
            ':id'            => $vehicleId,
        ]);

        $vStmt = $localPdo->prepare('SELECT * FROM vehicle_units WHERE id = ?');
        $vStmt->execute([$vehicleId]);
        $vehicle = $vStmt->fetch(PDO::FETCH_ASSOC);
        $success = true;
        $dbError = null;
    } catch (\Exception $e) {
        $company = $company ?? null;
        $vehicle = $vehicle ?? null;
        $dbError = null;
        $formError = 'Ошибка обновления транспорта: ' . $e->getMessage();
    }

    ob_start();
    require base_path('app/View/pages/company_vehicle_edit.php');
    $content = ob_get_clean();
    require base_path('app/View/layouts/main.php');
});

// --- Vehicle Archive ---
$router->post('/company/vehicles/{id}/archive', function ($vehicleId) use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);
    $vehicleId = (int)$vehicleId;

    $companyId = (int)(getSessionCompanyId() ?? 0);

    if ($companyId <= 0) {
        header('Location: /company/vehicles');
        exit;
    }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company || $company['status'] !== 'active') {
            header('Location: /company/vehicles');
            exit;
        }

        $dbIdentifier = $company['db_identifier'];
        $localDbConfig = $config['database'];
        $localDbConfig['database'] = $dbIdentifier;
        $localDb = new \App\Core\Database($localDbConfig);
        $localPdo = $localDb->connection();
                applyLocalMigrations($localPdo);

        try {
            $localPdo->query("SELECT 1 FROM vehicle_units LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $migrationSql = file_get_contents(base_path('database/migrations-local/005_create_company_vehicles.sql'));
            if ($migrationSql !== false) {
                $localPdo->exec($migrationSql);
            }
            try {
                $localPdo->exec("RENAME TABLE vehicles TO vehicle_units");
            } catch (\Exception $renameEx) {
                // Table may already be vehicle_units or vehicles may not exist
            }
        }

        try {
            $localPdo->query("SELECT 1 FROM crews LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $migrationSql = file_get_contents(base_path('database/migrations-local/006_create_company_crews.sql'));
            $localPdo->exec($migrationSql);
        }

        $crewCheck = $localPdo->prepare('SELECT COUNT(*) FROM crews WHERE vehicle_id = ? AND status = ?');
        $crewCheck->execute([$vehicleId, 'active']);
        if ($crewCheck->fetchColumn() > 0) {
            $pageTitle = 'Невозможно архивировать';
            $pageContext = 'Транспортные единицы › Компания';
            $companyError = false;
            $company = $company;
            $message = 'Транспорт используется в активных экипажах. Сначала удалите транспорт из всех экипажей.';

            ob_start();
            echo '<div class="page-head"><div><h1>' . e($pageTitle) . '</h1></div></div>';
            echo '<div class="notice warn">' . e($message) . '</div>';
            echo '<div class="form-actions"><a href="/company/vehicles/' . $vehicleId . '" class="btn btn-ghost">< К просмотру</a></div>';
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $update = $localPdo->prepare("UPDATE vehicle_units SET status = 'archived' WHERE id = ?");
        $update->execute([$vehicleId]);

        header('Location: /company/vehicles/' . $vehicleId);
        exit;
    } catch (\Exception $e) {
        header('Location: /company/vehicles');
        exit;
    }
});

$router->get('/company/crews', function () use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);
    header('Location: /company/route-executors', true, 302);
    exit;

    requireRole(['company_owner', 'senior_logist', 'logist']);
    $pageTitle = 'Экипажи';
    $pageContext = 'Экипажи › Компания';

    $companyId = (int)(getSessionCompanyId() ?? 0);

    if ($companyId <= 0) {
        $company = null;
        $crews = [];
        $dbError = null;

        ob_start();
        require base_path('app/View/pages/company_crews.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
        return;
    }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company) {
            $company = null;
            $crews = [];
            $dbError = null;

            ob_start();
            require base_path('app/View/pages/company_crews.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $pageContext = 'Экипажи › Компания: ' . $company['name'];

        if ($company['status'] !== 'active') {
            $crews = [];
            $dbError = null;

            ob_start();
            require base_path('app/View/pages/company_crews.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $dbIdentifier = $company['db_identifier'];
        $localDbConfig = $config['database'];
        $localDbConfig['database'] = $dbIdentifier;
        $localDb = new \App\Core\Database($localDbConfig);
        $localPdo = $localDb->connection();
                applyLocalMigrations($localPdo);

        try {
            $localPdo->query("SELECT 1 FROM crews LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $migrationSql = file_get_contents(base_path('database/migrations-local/006_create_company_crews.sql'));
            $localPdo->exec($migrationSql);
        }

        try {
            $localPdo->query("SELECT created_by_user_id FROM crews LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $localPdo->exec("ALTER TABLE crews ADD COLUMN created_by_user_id INT UNSIGNED DEFAULT NULL, ADD COLUMN created_by_role VARCHAR(20) DEFAULT NULL");
        }

        try {
            $localPdo->query("SELECT 1 FROM entity_access_grants LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $migrationSql = file_get_contents(base_path('database/migrations-local/009_create_entity_access_grants.sql'));
            $localPdo->exec($migrationSql);
        }

        $isLogist = ($_SESSION['role_code'] ?? '') === 'logist';
        $hasGrantsButAllArchived = false;
        if ($isLogist) {
            $userId = (int)$_SESSION['user_id'];
            $crewStmt = $localPdo->prepare(
                "SELECT c.*,
                        ct.name AS contractor_name,
                        d.full_name AS driver_name, d.phone AS driver_phone,
                        vs.set_type,
                        CONCAT(vu1.plate_number, IFNULL(CONCAT(' + ', vu2.plate_number), '')) AS plates,
                        vu1.plate_number AS primary_plate
                 FROM crews c
                 JOIN contractors ct ON c.contractor_id = ct.id
                 JOIN driver_vehicle_blocks dvb ON c.driver_vehicle_block_id = dvb.id
                 JOIN drivers d ON dvb.driver_id = d.id
                 JOIN vehicle_sets vs ON dvb.vehicle_set_id = vs.id
                 LEFT JOIN vehicle_units vu1 ON vs.primary_vehicle_unit_id = vu1.id
                 LEFT JOIN vehicle_units vu2 ON vs.secondary_vehicle_unit_id = vu2.id
                 WHERE (c.created_by_user_id = ? OR c.id IN (SELECT entity_id FROM entity_access_grants WHERE entity_type = 'crew' AND granted_to_user_id = ? AND access_level = 'view'))
                 ORDER BY c.created_at DESC"
            );
            $crewStmt->execute([$userId, $userId]);
            $crews = $crewStmt->fetchAll(PDO::FETCH_ASSOC);

            // TASK-021: detect if logist has grants but all granted crews are archived
            if (empty($crews)) {
                $grantCountStmt = $localPdo->prepare(
                    "SELECT COUNT(*) FROM entity_access_grants WHERE entity_type = 'crew' AND granted_to_user_id = ? AND revoked_at IS NULL"
                );
                $grantCountStmt->execute([$userId]);
                $hasGrantsButAllArchived = ((int)$grantCountStmt->fetchColumn() > 0);
            }
        } else {
            $crewStmt = $localPdo->query(
                "SELECT c.*,
                        ct.name AS contractor_name,
                        d.full_name AS driver_name, d.phone AS driver_phone,
                        vs.set_type,
                        CONCAT(vu1.plate_number, IFNULL(CONCAT(' + ', vu2.plate_number), '')) AS plates,
                        vu1.plate_number AS primary_plate
                 FROM crews c
                 JOIN contractors ct ON c.contractor_id = ct.id
                 JOIN driver_vehicle_blocks dvb ON c.driver_vehicle_block_id = dvb.id
                 JOIN drivers d ON dvb.driver_id = d.id
                 JOIN vehicle_sets vs ON dvb.vehicle_set_id = vs.id
                 LEFT JOIN vehicle_units vu1 ON vs.primary_vehicle_unit_id = vu1.id
                 LEFT JOIN vehicle_units vu2 ON vs.secondary_vehicle_unit_id = vu2.id
                 ORDER BY c.created_at DESC"
            );
            $crews = $crewStmt->fetchAll(PDO::FETCH_ASSOC);
        }
        $dbError = null;
    } catch (\Exception $e) {
        $company = $company ?? null;
        $crews = [];
        $hasGrantsButAllArchived = false;
        $dbError = 'Не удалось подключиться к базе данных компании.';
    }

    ob_start();
    require base_path('app/View/pages/company_crews.php');
    $content = ob_get_clean();
    require base_path('app/View/layouts/main.php');
});

$router->get('/company/crews/create', function () use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);
    header('Location: /company/route-executors/create', true, 302);
    exit;

    requireRole(['company_owner', 'senior_logist', 'logist']);
    $pageTitle = 'Создать экипаж';
    $pageContext = 'Экипажи › Компания';

    $companyId = (int)(getSessionCompanyId() ?? 0);

    if ($companyId <= 0) {
        $company = null;
        $success = false; $errors = []; $old = []; $formError = null; $createdCrew = null;
        $blockingNotices = []; $contractors = []; $driverVehicleBlocks = [];
        ob_start(); require base_path('app/View/pages/company_crews_create.php');
        $content = ob_get_clean(); require base_path('app/View/layouts/main.php');
        return;
    }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company) {
            $company = null;
            $success = false; $errors = []; $old = []; $formError = null; $createdCrew = null;
            $blockingNotices = []; $contractors = []; $driverVehicleBlocks = [];
            ob_start(); require base_path('app/View/pages/company_crews_create.php');
            $content = ob_get_clean(); require base_path('app/View/layouts/main.php');
            return;
        }

        $pageContext = 'Экипажи › Компания: ' . $company['name'];

        $success = false; $errors = []; $old = []; $formError = null; $createdCrew = null;

        if ($company['status'] !== 'active') {
            $blockingNotices = []; $contractors = []; $driverVehicleBlocks = [];
        } else {
            $dbIdentifier = $company['db_identifier'];
            $localDbConfig = $config['database']; $localDbConfig['database'] = $dbIdentifier;
            $localDb = new \App\Core\Database($localDbConfig); $localPdo = $localDb->connection();
            applyLocalMigrations($localPdo);

            try { $localPdo->query("SELECT 1 FROM crews LIMIT 1")->fetch(); }
            catch (\Exception $e) { $localPdo->exec(file_get_contents(base_path('database/migrations-local/006_create_company_crews.sql'))); }

            $isLogist = ($_SESSION['role_code'] ?? '') === 'logist';
            $userId = (int)$_SESSION['user_id'];

            if ($isLogist) {
                $cStmt = $localPdo->prepare("SELECT id, name, inn FROM contractors WHERE status = 'active' AND (created_by_user_id = ? OR id IN (SELECT entity_id FROM entity_access_grants WHERE entity_type = 'contractor' AND granted_to_user_id = ? AND access_level IN ('view','edit') AND revoked_at IS NULL)) ORDER BY name");
                $cStmt->execute([$userId, $userId]);
                $contractors = $cStmt->fetchAll(PDO::FETCH_ASSOC);
            } else {
                $contractors = $localPdo->query("SELECT id, name, inn FROM contractors WHERE status = 'active' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
            }

            if ($isLogist) {
                $dvbStmt = $localPdo->prepare("SELECT dvb.id, d.full_name AS driver_name, d.phone AS driver_phone, vs.set_type, vu1.plate_number AS primary_plate, vu2.plate_number AS secondary_plate FROM driver_vehicle_blocks dvb JOIN drivers d ON dvb.driver_id = d.id JOIN vehicle_sets vs ON dvb.vehicle_set_id = vs.id LEFT JOIN vehicle_units vu1 ON vs.primary_vehicle_unit_id = vu1.id LEFT JOIN vehicle_units vu2 ON vs.secondary_vehicle_unit_id = vu2.id WHERE dvb.status = 'active' AND (dvb.created_by_user_id = ? OR dvb.id IN (SELECT entity_id FROM entity_access_grants WHERE entity_type = 'driver_vehicle_block' AND granted_to_user_id = ? AND access_level IN ('view','edit') AND revoked_at IS NULL)) ORDER BY d.full_name");
                $dvbStmt->execute([$userId, $userId]);
                $driverVehicleBlocks = $dvbStmt->fetchAll(PDO::FETCH_ASSOC);
            } else {
                $driverVehicleBlocks = $localPdo->query("SELECT dvb.id, d.full_name AS driver_name, d.phone AS driver_phone, vs.set_type, vu1.plate_number AS primary_plate, vu2.plate_number AS secondary_plate FROM driver_vehicle_blocks dvb JOIN drivers d ON dvb.driver_id = d.id JOIN vehicle_sets vs ON dvb.vehicle_set_id = vs.id LEFT JOIN vehicle_units vu1 ON vs.primary_vehicle_unit_id = vu1.id LEFT JOIN vehicle_units vu2 ON vs.secondary_vehicle_unit_id = vu2.id WHERE dvb.status = 'active' ORDER BY d.full_name")->fetchAll(PDO::FETCH_ASSOC);
            }

            $blockingNotices = [];
            if (empty($contractors)) {
                $blockingNotices[] = ['message' => 'Сначала создайте подрядчика.', 'link' => '/company/contractors/create', 'action' => 'Создать подрядчика'];
            }
            if (empty($driverVehicleBlocks)) {
                $blockingNotices[] = ['message' => 'Сначала создайте связку.', 'link' => '/company/driver-vehicle-blocks/create', 'action' => 'Создать связку'];
            }
        }
    } catch (\Exception $e) {
        $company = null;
        $success = false; $errors = []; $old = []; $formError = 'Ошибка загрузки данных: ' . $e->getMessage();
        $createdCrew = null; $blockingNotices = []; $contractors = []; $driverVehicleBlocks = [];
    }

    ob_start();
    require base_path('app/View/pages/company_crews_create.php');
    $content = ob_get_clean();
    require base_path('app/View/layouts/main.php');
});

$router->post('/company/crews/create', function () use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);
    $pageTitle = 'Создать экипаж';
    $pageContext = 'Экипажи › Компания';

    $companyId = (int)(getSessionCompanyId() ?? 0);
    $errors = [];
    $old = $_POST;
    $formError = null;
    $success = false;
    $createdCrew = null;
    $blockingNotices = [];
    $contractors = [];
    $driverVehicleBlocks = [];

    if ($companyId <= 0) {
        $company = null;
        $formError = 'Компания не найдена';
        ob_start(); require base_path('app/View/pages/company_crews_create.php');
        $content = ob_get_clean(); require base_path('app/View/layouts/main.php');
        return;
    }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company) {
            $company = null;
            $formError = 'Компания не найдена';
            ob_start(); require base_path('app/View/pages/company_crews_create.php');
            $content = ob_get_clean(); require base_path('app/View/layouts/main.php');
            return;
        }

        $pageContext = 'Экипажи › Компания: ' . $company['name'];

        if ($company['status'] !== 'active') {
            $formError = 'Создание экипажа недоступно';
            ob_start(); require base_path('app/View/pages/company_crews_create.php');
            $content = ob_get_clean(); require base_path('app/View/layouts/main.php');
            return;
        }

        $dbIdentifier = $company['db_identifier'];
        $localDbConfig = $config['database'];
        $localDbConfig['database'] = $dbIdentifier;
        $localDb = new \App\Core\Database($localDbConfig);
        $localPdo = $localDb->connection();
        applyLocalMigrations($localPdo);

        try {
            $localPdo->query("SELECT 1 FROM crews LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $migrationSql = file_get_contents(base_path('database/migrations-local/006_create_company_crews.sql'));
            $localPdo->exec($migrationSql);
        }

        $isLogist = ($_SESSION['role_code'] ?? '') === 'logist';
        $userId = (int)$_SESSION['user_id'];

        if ($isLogist) {
            $cStmt = $localPdo->prepare("SELECT id, name, inn FROM contractors WHERE status = 'active' AND (created_by_user_id = ? OR id IN (SELECT entity_id FROM entity_access_grants WHERE entity_type = 'contractor' AND granted_to_user_id = ? AND access_level IN ('view','edit') AND revoked_at IS NULL)) ORDER BY name");
            $cStmt->execute([$userId, $userId]);
            $contractors = $cStmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $contractors = $localPdo->query("SELECT id, name, inn FROM contractors WHERE status = 'active' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
        }

        if ($isLogist) {
            $dvbStmt = $localPdo->prepare("SELECT dvb.id, d.full_name AS driver_name, d.phone AS driver_phone, vs.set_type, vu1.plate_number AS primary_plate, vu2.plate_number AS secondary_plate FROM driver_vehicle_blocks dvb JOIN drivers d ON dvb.driver_id = d.id JOIN vehicle_sets vs ON dvb.vehicle_set_id = vs.id LEFT JOIN vehicle_units vu1 ON vs.primary_vehicle_unit_id = vu1.id LEFT JOIN vehicle_units vu2 ON vs.secondary_vehicle_unit_id = vu2.id WHERE dvb.status = 'active' AND (dvb.created_by_user_id = ? OR dvb.id IN (SELECT entity_id FROM entity_access_grants WHERE entity_type = 'driver_vehicle_block' AND granted_to_user_id = ? AND access_level IN ('view','edit') AND revoked_at IS NULL)) ORDER BY d.full_name");
            $dvbStmt->execute([$userId, $userId]);
            $driverVehicleBlocks = $dvbStmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $driverVehicleBlocks = $localPdo->query("SELECT dvb.id, d.full_name AS driver_name, d.phone AS driver_phone, vs.set_type, vu1.plate_number AS primary_plate, vu2.plate_number AS secondary_plate FROM driver_vehicle_blocks dvb JOIN drivers d ON dvb.driver_id = d.id JOIN vehicle_sets vs ON dvb.vehicle_set_id = vs.id LEFT JOIN vehicle_units vu1 ON vs.primary_vehicle_unit_id = vu1.id LEFT JOIN vehicle_units vu2 ON vs.secondary_vehicle_unit_id = vu2.id WHERE dvb.status = 'active' ORDER BY d.full_name")->fetchAll(PDO::FETCH_ASSOC);
        }

        $blockingNotices = [];
        if (empty($contractors)) {
            $blockingNotices[] = ['message' => 'Сначала создайте подрядчика.', 'link' => '/company/contractors/create', 'action' => 'Создать подрядчика'];
        }
        if (empty($driverVehicleBlocks)) {
            $blockingNotices[] = ['message' => 'Сначала создайте связку «Водители+ТС».', 'link' => '/company/driver-vehicle-blocks/create', 'action' => 'Создать связку'];
        }

        $contractorId = trim($_POST['contractor_id'] ?? '');
        $driverVehicleBlockId = trim($_POST['driver_vehicle_block_id'] ?? '');
        $status = trim($_POST['status'] ?? 'active');
        $comments = trim($_POST['comments'] ?? '');

        if ($contractorId === '') {
            $errors['contractor_id'] = 'Обязательное поле';
        }
        if ($driverVehicleBlockId === '') {
            $errors['driver_vehicle_block_id'] = 'Обязательное поле';
        }

        // Backend validation: logist can only use their own contractors and blocks
        if ($contractorId !== '' && $isLogist) {
            $cCheck = $localPdo->prepare("SELECT created_by_user_id FROM contractors WHERE id = ?");
            $cCheck->execute([(int)$contractorId]);
            $cOwner = $cCheck->fetchColumn();
            if ($cOwner === false) {
                $errors['contractor_id'] = 'Перевозчик не найден.';
            } elseif ((int)$cOwner !== $userId) {
                $cGrant = $localPdo->prepare("SELECT COUNT(*) FROM entity_access_grants WHERE entity_type = 'contractor' AND entity_id = ? AND granted_to_user_id = ? AND access_level IN ('view','edit') AND revoked_at IS NULL");
                $cGrant->execute([(int)$contractorId, $userId]);
                if ($cGrant->fetchColumn() == 0) {
                    $errors['contractor_id'] = 'Перевозчик недоступен.';
                }
            }
        }

        if ($driverVehicleBlockId !== '' && $isLogist) {
            $bCheck = $localPdo->prepare("SELECT created_by_user_id FROM driver_vehicle_blocks WHERE id = ?");
            $bCheck->execute([(int)$driverVehicleBlockId]);
            $bOwner = $bCheck->fetchColumn();
            if ($bOwner === false) {
                $errors['driver_vehicle_block_id'] = 'Связка не найдена.';
            } elseif ((int)$bOwner !== $userId) {
                $bGrant = $localPdo->prepare("SELECT COUNT(*) FROM entity_access_grants WHERE entity_type = 'driver_vehicle_block' AND entity_id = ? AND granted_to_user_id = ? AND access_level IN ('view','edit') AND revoked_at IS NULL");
                $bGrant->execute([(int)$driverVehicleBlockId, $userId]);
                if ($bGrant->fetchColumn() == 0) {
                    $errors['driver_vehicle_block_id'] = 'Связка недоступна.';
                }
            }
        }

        if ($contractorId !== '' && $driverVehicleBlockId !== '') {
            $dupStmt = $localPdo->prepare('SELECT COUNT(*) FROM crews WHERE contractor_id = ? AND driver_vehicle_block_id = ?');
            $dupStmt->execute([(int)$contractorId, (int)$driverVehicleBlockId]);
            if ($dupStmt->fetchColumn() > 0) {
                $errors['driver_vehicle_block_id'] = 'Такой экипаж уже существует.';
            }
        }

        if (!empty($errors)) {
            ob_start(); require base_path('app/View/pages/company_crews_create.php');
            $content = ob_get_clean(); require base_path('app/View/layouts/main.php');
            return;
        }

        $insert = $localPdo->prepare(
            'INSERT INTO crews (contractor_id, driver_vehicle_block_id, status, comments, created_by_user_id, created_by_role)
             VALUES (:contractor_id, :driver_vehicle_block_id, :status, :comments, :uid, :role)'
        );
        $insert->execute([
            ':contractor_id' => (int)$contractorId,
            ':driver_vehicle_block_id' => (int)$driverVehicleBlockId,
            ':status' => $status,
            ':comments' => $comments !== '' ? $comments : null,
            ':uid' => (int)$_SESSION['user_id'],
            ':role' => $_SESSION['role_code'],
        ]);

        $newId = (int)$localPdo->lastInsertId();
        $ctrName = $localPdo->prepare("SELECT name FROM contractors WHERE id = ?");
        $ctrName->execute([(int)$contractorId]);
        $createdCrew = [
            'id' => $newId,
            'contractor_name' => $ctrName->fetchColumn() ?: '',
        ];
        $success = true;
    } catch (\Exception $e) {
        $company = $company ?? null;
        $formError = 'Ошибка создания экипажа: ' . $e->getMessage();
    }

    ob_start();
    require base_path('app/View/pages/company_crews_create.php');
    $content = ob_get_clean();
    require base_path('app/View/layouts/main.php');
});

// --- Crew View ---
$router->get('/company/crews/{id}', function ($crewId) use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);
    $crewId = (int)$crewId;
    $pageTitle = 'Экипаж';
    $pageContext = 'Экипажи › Компания';
    $entityNotFound = false;
    $grants = [];
    $logists = [];

    $companyId = (int)(getSessionCompanyId() ?? 0);

    if ($companyId <= 0) {
        $company = null;
        $crew = null;
        $dbError = null;

        ob_start();
        require base_path('app/View/pages/company_crew_view.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
        return;
    }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company) {
            $company = null;
            $crew = null;
            $dbError = null;

            ob_start();
            require base_path('app/View/pages/company_crew_view.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $pageContext = 'Экипажи › Компания: ' . $company['name'];

        if ($company['status'] !== 'active') {
            $crew = null;
            $dbError = null;

            ob_start();
            require base_path('app/View/pages/company_crew_view.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $dbIdentifier = $company['db_identifier'];
        $localDbConfig = $config['database'];
        $localDbConfig['database'] = $dbIdentifier;
        $localDb = new \App\Core\Database($localDbConfig);
        $localPdo = $localDb->connection();
                applyLocalMigrations($localPdo);

        try {
            $localPdo->query("SELECT 1 FROM crews LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $migrationSql = file_get_contents(base_path('database/migrations-local/006_create_company_crews.sql'));
            $localPdo->exec($migrationSql);
        }

        try {
            $localPdo->query("SELECT created_by_user_id FROM crews LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $localPdo->exec("ALTER TABLE crews ADD COLUMN created_by_user_id INT UNSIGNED DEFAULT NULL, ADD COLUMN created_by_role VARCHAR(20) DEFAULT NULL");
        }

        try {
            $localPdo->query("SELECT 1 FROM entity_access_grants LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $migrationSql = file_get_contents(base_path('database/migrations-local/009_create_entity_access_grants.sql'));
            $localPdo->exec($migrationSql);
        }

        $cStmt = $localPdo->prepare(
            "SELECT c.*,
                    ct.name AS contractor_name, ct.inn AS contractor_inn,
                    d.full_name AS driver_name, d.phone AS driver_phone,
                    dvb.id AS driver_vehicle_block_id,
                    vs.set_type, vs.id AS vehicle_set_id,
                    vu1.plate_number AS primary_plate, vu1.brand AS primary_brand, vu1.model AS primary_model,
                    vu2.plate_number AS secondary_plate, vu2.brand AS secondary_brand, vu2.model AS secondary_model
             FROM crews c
             JOIN contractors ct ON c.contractor_id = ct.id
             JOIN driver_vehicle_blocks dvb ON c.driver_vehicle_block_id = dvb.id
             JOIN drivers d ON dvb.driver_id = d.id
             JOIN vehicle_sets vs ON dvb.vehicle_set_id = vs.id
             LEFT JOIN vehicle_units vu1 ON vs.primary_vehicle_unit_id = vu1.id
             LEFT JOIN vehicle_units vu2 ON vs.secondary_vehicle_unit_id = vu2.id
             WHERE c.id = ?"
        );
        $cStmt->execute([$crewId]);
        $crew = $cStmt->fetch(PDO::FETCH_ASSOC);

        if (!$crew) {
            $entityNotFound = true;
        }

        // Access check for logist
        $accessDenied = null;
        if ($crew) {
            $isLogist = ($_SESSION['role_code'] ?? '') === 'logist';
            if ($isLogist) {
                $userId = (int)$_SESSION['user_id'];
                if (!hasRouteExecutorAccess($localPdo, $crew, $userId, 'view')) {
                    $accessDenied = 'У вас нет доступа к этой записи.';
                }
            }
        }

        $pageTitle = ($crew && !$accessDenied) ? 'Экипаж #' . $crew['id'] : 'Экипаж';

        $grants = [];
        $logists = [];
        if (($_SESSION['role_code'] ?? '') === 'company_owner') {
            $grantsStmt = $localPdo->prepare(
                "SELECT g.*, u.full_name AS logist_name
                 FROM entity_access_grants g
                 LEFT JOIN users u ON g.granted_to_user_id = u.id
                 WHERE g.entity_type = ? AND g.entity_id = ?"
            );
            $grantsStmt->execute(['crew', $crewId]);
            $grants = $grantsStmt->fetchAll(PDO::FETCH_ASSOC);

            $logists = $localPdo->query("SELECT id, full_name, login FROM users WHERE role_code IN ('logist', 'senior_logist') AND status='active' ORDER BY full_name")->fetchAll(PDO::FETCH_ASSOC);
        }

        $dbError = null;
    } catch (\Exception $e) {
        $company = $company ?? null;
        $crew = null;
        $grants = [];
        $logists = [];
        $dbError = 'Не удалось подключиться к базе данных компании.';
    }

    ob_start();
    require base_path('app/View/pages/company_crew_view.php');
    $content = ob_get_clean();
    require base_path('app/View/layouts/main.php');
});

// --- Crew Edit (form) ---
$router->get('/company/crews/{id}/edit', function ($crewId) use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);
    $crewId = (int)$crewId;
    $pageTitle = 'Редактировать экипаж';
    $pageContext = 'Экипажи › Компания';
    $entityNotFound = false;

    $companyId = (int)(getSessionCompanyId() ?? 0);
    $success = false;
    $formError = null;
    $errors = [];

    if ($companyId <= 0) {
        $company = null;
        $crew = null;
        $old = [];
        $dbError = null;
        $contractors = [];
        $driverVehicleBlocks = [];
        $blockingNotices = [];

        ob_start();
        require base_path('app/View/pages/company_crew_edit.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
        return;
    }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company) {
            $company = null;
            $crew = null;
            $old = [];
            $dbError = null;
            $contractors = [];
            $driverVehicleBlocks = [];
            $blockingNotices = [];

            ob_start();
            require base_path('app/View/pages/company_crew_edit.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $pageContext = 'Экипажи › Компания: ' . $company['name'];

        if ($company['status'] !== 'active') {
            $crew = null;
            $old = [];
            $dbError = null;
            $contractors = [];
            $driverVehicleBlocks = [];
            $blockingNotices = [];

            ob_start();
            require base_path('app/View/pages/company_crew_edit.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $dbIdentifier = $company['db_identifier'];
        $localDbConfig = $config['database'];
        $localDbConfig['database'] = $dbIdentifier;
        $localDb = new \App\Core\Database($localDbConfig);
        $localPdo = $localDb->connection();
                applyLocalMigrations($localPdo);

        try {
            $localPdo->query("SELECT 1 FROM crews LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $migrationSql = file_get_contents(base_path('database/migrations-local/006_create_company_crews.sql'));
            $localPdo->exec($migrationSql);
        }

        $cStmt = $localPdo->prepare('SELECT * FROM crews WHERE id = ?');
        $cStmt->execute([$crewId]);
        $crew = $cStmt->fetch(PDO::FETCH_ASSOC);

        if (!$crew) {
            $entityNotFound = true;
            $old = [];
            $contractors = [];
            $driverVehicleBlocks = [];
            $blockingNotices = [];
        } else {
            // --- PERMISSION CHECK (view or edit for GET) ---
            $currentUserId = (int)($_SESSION['user_id'] ?? 0);
            $currentRole = $_SESSION['role_code'] ?? '';

            if ($currentRole !== 'company_owner' && $currentRole !== 'senior_logist') {
                $isCreator = ($crew['created_by_user_id'] ?? 0) === $currentUserId;

                $grantStmt = $localPdo->prepare(
                    "SELECT 1 FROM entity_access_grants
                     WHERE entity_type = 'crew' AND entity_id = ?
                     AND granted_to_user_id = ? AND access_level = 'edit'
                     AND (revoked_at IS NULL)"
                );
                $grantStmt->execute([$crewId, $currentUserId]);
                $hasGrant = (bool)$grantStmt->fetchColumn();

                if (!$isCreator && !$hasGrant) {
                    http_response_code(403);
                    header('Content-Type: text/plain');
                    echo '403 Forbidden';
                    exit;
                }
            }

            $old = $crew;

            $isLogist = ($_SESSION['role_code'] ?? '') === 'logist';
            $userId = (int)$_SESSION['user_id'];

            if ($isLogist) {
                $cStmt = $localPdo->prepare("SELECT id, name, inn FROM contractors WHERE status = 'active' AND (created_by_user_id = ? OR id IN (SELECT entity_id FROM entity_access_grants WHERE entity_type = 'contractor' AND granted_to_user_id = ? AND access_level IN ('view','edit') AND revoked_at IS NULL)) ORDER BY name");
                $cStmt->execute([$userId, $userId]);
                $contractors = $cStmt->fetchAll(PDO::FETCH_ASSOC);
            } else {
                $contractors = $localPdo->query("SELECT id, name, inn FROM contractors WHERE status = 'active' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
            }

            $currentBlockId = (int)($crew['driver_vehicle_block_id'] ?? 0);
            if ($isLogist) {
                $dvbStmt = $localPdo->prepare(
                    "SELECT dvb.id, d.full_name AS driver_name, d.phone AS driver_phone, vs.set_type,
                     vu1.plate_number AS primary_plate, vu2.plate_number AS secondary_plate
                     FROM driver_vehicle_blocks dvb
                     JOIN drivers d ON dvb.driver_id = d.id
                     JOIN vehicle_sets vs ON dvb.vehicle_set_id = vs.id
                     LEFT JOIN vehicle_units vu1 ON vs.primary_vehicle_unit_id = vu1.id
                     LEFT JOIN vehicle_units vu2 ON vs.secondary_vehicle_unit_id = vu2.id
                     WHERE (dvb.status = 'active' OR dvb.id = ?)
                       AND (dvb.created_by_user_id = ? OR dvb.id IN (SELECT entity_id FROM entity_access_grants WHERE entity_type = 'driver_vehicle_block' AND granted_to_user_id = ? AND access_level IN ('view','edit') AND revoked_at IS NULL))
                     ORDER BY d.full_name"
                );
                $dvbStmt->execute([$currentBlockId, $userId, $userId]);
                $driverVehicleBlocks = $dvbStmt->fetchAll(PDO::FETCH_ASSOC);
            } else {
                $driverVehicleBlocks = $localPdo->query(
                    "SELECT dvb.id, d.full_name AS driver_name, d.phone AS driver_phone, vs.set_type,
                     vu1.plate_number AS primary_plate, vu2.plate_number AS secondary_plate
                     FROM driver_vehicle_blocks dvb
                     JOIN drivers d ON dvb.driver_id = d.id
                     JOIN vehicle_sets vs ON dvb.vehicle_set_id = vs.id
                     LEFT JOIN vehicle_units vu1 ON vs.primary_vehicle_unit_id = vu1.id
                     LEFT JOIN vehicle_units vu2 ON vs.secondary_vehicle_unit_id = vu2.id
                     WHERE dvb.status = 'active' OR dvb.id = {$currentBlockId}
                     ORDER BY d.full_name"
                )->fetchAll(PDO::FETCH_ASSOC);
            }

            $blockingNotices = [];
            if (empty($contractors)) {
                $blockingNotices[] = [
                    'message' => 'Нет активных подрядчиков.',
                    'link' => '/company/contractors/create',
                    'action' => 'Создать подрядчика',
                ];
            }
            if (empty($driverVehicleBlocks)) {
                $blockingNotices[] = [
                    'message' => 'Нет активных связок.',
                    'link' => '/company/driver-vehicle-blocks/create',
                    'action' => 'Создать связку',
                ];
            }
        }

        $dbError = null;
    } catch (\Exception $e) {
        $company = $company ?? null;
        $crew = null;
        $old = [];
        $dbError = 'Не удалось подключиться к базе данных компании.';
        $contractors = [];
        $driverVehicleBlocks = [];
        $blockingNotices = [];
    }

    ob_start();
    require base_path('app/View/pages/company_crew_edit.php');
    $content = ob_get_clean();
    require base_path('app/View/layouts/main.php');
});

// --- Crew Edit (handle) ---
$router->post('/company/crews/{id}/edit', function ($crewId) use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);
    $crewId = (int)$crewId;
    $pageTitle = 'Редактировать экипаж';
    $pageContext = 'Экипажи › Компания';
    $entityNotFound = false;

    $companyId = (int)(getSessionCompanyId() ?? 0);
    $errors = [];
    $old = $_POST;
    $formError = null;
    $success = false;

    if ($companyId <= 0) {
        $company = null;
        $crew = null;
        $dbError = null;
        $contractors = [];
        $driverVehicleBlocks = [];
        $blockingNotices = [];
        $formError = 'Компания не найдена';

        ob_start();
        require base_path('app/View/pages/company_crew_edit.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
        return;
    }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company) {
            $company = null;
            $crew = null;
            $dbError = null;
            $contractors = [];
            $driverVehicleBlocks = [];
            $blockingNotices = [];
            $formError = 'Компания не найдена';

            ob_start();
            require base_path('app/View/pages/company_crew_edit.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $pageContext = 'Экипажи › Компания: ' . $company['name'];

        if ($company['status'] !== 'active') {
            $crew = null;
            $dbError = null;
            $contractors = [];
            $driverVehicleBlocks = [];
            $blockingNotices = [];
            $formError = 'Редактирование экипажа недоступно';

            ob_start();
            require base_path('app/View/pages/company_crew_edit.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $dbIdentifier = $company['db_identifier'];
        $localDbConfig = $config['database'];
        $localDbConfig['database'] = $dbIdentifier;
        $localDb = new \App\Core\Database($localDbConfig);
        $localPdo = $localDb->connection();
                applyLocalMigrations($localPdo);

        try {
            $localPdo->query("SELECT 1 FROM crews LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $migrationSql = file_get_contents(base_path('database/migrations-local/006_create_company_crews.sql'));
            $localPdo->exec($migrationSql);
        }

        $isLogist = ($_SESSION['role_code'] ?? '') === 'logist';
        $userId = (int)$_SESSION['user_id'];

        if ($isLogist) {
            $cStmt = $localPdo->prepare("SELECT id, name, inn FROM contractors WHERE status = 'active' AND (created_by_user_id = ? OR id IN (SELECT entity_id FROM entity_access_grants WHERE entity_type = 'contractor' AND granted_to_user_id = ? AND access_level IN ('view','edit') AND revoked_at IS NULL)) ORDER BY name");
            $cStmt->execute([$userId, $userId]);
            $contractors = $cStmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $contractors = $localPdo->query("SELECT id, name, inn FROM contractors WHERE status = 'active' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
        }

        $cStmt = $localPdo->prepare('SELECT * FROM crews WHERE id = ?');
        $cStmt->execute([$crewId]);
        $crew = $cStmt->fetch(PDO::FETCH_ASSOC);

        $currentBlockId = $crew ? (int)($crew['driver_vehicle_block_id'] ?? 0) : 0;
        if ($isLogist) {
            $dvbStmt = $localPdo->prepare(
                "SELECT dvb.id, d.full_name AS driver_name, d.phone AS driver_phone, vs.set_type,
                 vu1.plate_number AS primary_plate, vu2.plate_number AS secondary_plate
                 FROM driver_vehicle_blocks dvb
                 JOIN drivers d ON dvb.driver_id = d.id
                 JOIN vehicle_sets vs ON dvb.vehicle_set_id = vs.id
                 LEFT JOIN vehicle_units vu1 ON vs.primary_vehicle_unit_id = vu1.id
                 LEFT JOIN vehicle_units vu2 ON vs.secondary_vehicle_unit_id = vu2.id
                 WHERE (dvb.status = 'active'" . ($currentBlockId > 0 ? " OR dvb.id = ?" : "") . ")
                   AND (dvb.created_by_user_id = ? OR dvb.id IN (SELECT entity_id FROM entity_access_grants WHERE entity_type = 'driver_vehicle_block' AND granted_to_user_id = ? AND access_level IN ('view','edit') AND revoked_at IS NULL))
                 ORDER BY d.full_name"
            );
            $params = $currentBlockId > 0 ? [$currentBlockId, $userId, $userId] : [$userId, $userId];
            $dvbStmt->execute($params);
            $driverVehicleBlocks = $dvbStmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $driverVehicleBlocks = $localPdo->query(
                "SELECT dvb.id, d.full_name AS driver_name, d.phone AS driver_phone, vs.set_type,
                 vu1.plate_number AS primary_plate, vu2.plate_number AS secondary_plate
                 FROM driver_vehicle_blocks dvb
                 JOIN drivers d ON dvb.driver_id = d.id
                 JOIN vehicle_sets vs ON dvb.vehicle_set_id = vs.id
                 LEFT JOIN vehicle_units vu1 ON vs.primary_vehicle_unit_id = vu1.id
                 LEFT JOIN vehicle_units vu2 ON vs.secondary_vehicle_unit_id = vu2.id
                 WHERE dvb.status = 'active' " . ($currentBlockId > 0 ? "OR dvb.id = " . $currentBlockId : "") . "
                 ORDER BY d.full_name"
            )->fetchAll(PDO::FETCH_ASSOC);
        }

        $blockingNotices = [];
        if (empty($contractors)) {
            $blockingNotices[] = [
                'message' => 'Нет активных подрядчиков.',
                'link' => '/company/contractors/create',
                'action' => 'Создать подрядчика',
            ];
        }
        if (empty($driverVehicleBlocks)) {
            $blockingNotices[] = [
                'message' => 'Нет активных связок «Водители+ТС».',
                'link' => '/company/driver-vehicle-blocks/create',
                'action' => 'Создать блок',
            ];
        }

        if (!empty($blockingNotices)) {
            $dbError = null;

            ob_start();
            require base_path('app/View/pages/company_crew_edit.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        if (!$crew) {
            $entityNotFound = true;
            $dbError = null;

            ob_start();
            require base_path('app/View/pages/company_crew_edit.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        // --- PERMISSION CHECK (edit required for POST) ---
        $currentUserId = (int)($_SESSION['user_id'] ?? 0);
        $currentRole = $_SESSION['role_code'] ?? '';

        if ($currentRole !== 'company_owner' && $currentRole !== 'senior_logist') {
            $isCreator = ($crew['created_by_user_id'] ?? 0) === $currentUserId;

            $grantStmt = $localPdo->prepare(
                "SELECT 1 FROM entity_access_grants
                 WHERE entity_type = 'crew' AND entity_id = ?
                 AND granted_to_user_id = ? AND access_level = 'edit'
                 AND (revoked_at IS NULL)"
            );
            $grantStmt->execute([$crewId, $currentUserId]);
            $hasEditGrant = (bool)$grantStmt->fetchColumn();

            if (!$isCreator && !$hasEditGrant) {
                http_response_code(403);
                header('Content-Type: text/plain');
                echo '403 Forbidden';
                exit;
            }
        }

        $contractorId = trim($_POST['contractor_id'] ?? '');
        $driverVehicleBlockId = trim($_POST['driver_vehicle_block_id'] ?? '');

        if ($contractorId === '') {
            $errors['contractor_id'] = 'Выберите подрядчика';
        } else {
            $checkStmt = $localPdo->prepare('SELECT COUNT(*) FROM contractors WHERE id = ? AND status = ?');
            $checkStmt->execute([$contractorId, 'active']);
            if ($checkStmt->fetchColumn() == 0) {
                $errors['contractor_id'] = 'Подрядчик не найден';
            }
        }

        if ($driverVehicleBlockId === '') {
            $errors['driver_vehicle_block_id'] = 'Выберите связку «Водители+ТС»';
        } else {
            $checkDvbStmt = $localPdo->prepare('SELECT COUNT(*) FROM driver_vehicle_blocks WHERE id = ?');
            $checkDvbStmt->execute([(int)$driverVehicleBlockId]);
            if ($checkDvbStmt->fetchColumn() == 0) {
                $errors['driver_vehicle_block_id'] = 'Связка «Водители+ТС» не найдена';
            }
        }

        // Backend access validation for logist
        if (empty($errors) && $isLogist) {
            $cCheck = $localPdo->prepare("SELECT created_by_user_id FROM contractors WHERE id = ?");
            $cCheck->execute([(int)$contractorId]);
            $cOwner = $cCheck->fetchColumn();
            if ($cOwner !== false && (int)$cOwner !== $userId) {
                $cGrant = $localPdo->prepare("SELECT COUNT(*) FROM entity_access_grants WHERE entity_type = 'contractor' AND entity_id = ? AND granted_to_user_id = ? AND access_level IN ('view','edit') AND revoked_at IS NULL");
                $cGrant->execute([(int)$contractorId, $userId]);
                if ($cGrant->fetchColumn() == 0) {
                    $errors['contractor_id'] = 'Перевозчик недоступен.';
                }
            }

            $bCheck = $localPdo->prepare("SELECT created_by_user_id FROM driver_vehicle_blocks WHERE id = ?");
            $bCheck->execute([(int)$driverVehicleBlockId]);
            $bOwner = $bCheck->fetchColumn();
            if ($bOwner !== false && (int)$bOwner !== $userId) {
                $bGrant = $localPdo->prepare("SELECT COUNT(*) FROM entity_access_grants WHERE entity_type = 'driver_vehicle_block' AND entity_id = ? AND granted_to_user_id = ? AND access_level IN ('view','edit') AND revoked_at IS NULL");
                $bGrant->execute([(int)$driverVehicleBlockId, $userId]);
                if ($bGrant->fetchColumn() == 0) {
                    $errors['driver_vehicle_block_id'] = 'Связка недоступна.';
                }
            }
        }

        if (empty($errors)) {
            $dupStmt = $localPdo->prepare(
                'SELECT COUNT(*) FROM crews WHERE contractor_id = ? AND driver_vehicle_block_id = ? AND id != ?'
            );
            $dupStmt->execute([$contractorId, $driverVehicleBlockId, $crewId]);
            if ($dupStmt->fetchColumn() > 0) {
                $formError = 'Такой экипаж уже существует в этой компании';
            }
        }

        if (!empty($errors) || $formError) {
            ob_start();
            require base_path('app/View/pages/company_crew_edit.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $status = trim($_POST['status'] ?? 'active');
        $comments = trim($_POST['comments'] ?? '');

        $update = $localPdo->prepare(
            'UPDATE crews SET contractor_id = :contractor_id, driver_vehicle_block_id = :driver_vehicle_block_id,
             status = :status, comments = :comments,
             updated_by_user_id = :uid, updated_by_role = :role
             WHERE id = :id'
        );
        $update->execute([
            ':contractor_id' => $contractorId,
            ':driver_vehicle_block_id' => $driverVehicleBlockId,
            ':status'        => $status,
            ':comments'      => $comments !== '' ? $comments : null,
            ':uid'           => (int)$_SESSION['user_id'],
            ':role'          => $_SESSION['role_code'],
            ':id'            => $crewId,
        ]);

        $cStmt = $localPdo->prepare(
            "SELECT c.*,
                    ct.name AS contractor_name,
                    vu1.plate_number,
                    d.full_name AS driver_name
             FROM crews c
             JOIN contractors ct ON c.contractor_id = ct.id
             JOIN driver_vehicle_blocks dvb ON c.driver_vehicle_block_id = dvb.id
             JOIN drivers d ON dvb.driver_id = d.id
             JOIN vehicle_sets vs ON dvb.vehicle_set_id = vs.id
             LEFT JOIN vehicle_units vu1 ON vs.primary_vehicle_unit_id = vu1.id
             WHERE c.id = ?"
        );
        $cStmt->execute([$crewId]);
        $crew = $cStmt->fetch(PDO::FETCH_ASSOC);
        $success = true;
        $dbError = null;
    } catch (\Exception $e) {
        $company = $company ?? null;
        $crew = $crew ?? null;
        $dbError = null;
        $contractors = $contractors ?? [];
        $driverVehicleBlocks = $driverVehicleBlocks ?? [];
        $blockingNotices = $blockingNotices ?? [];
        $formError = 'Ошибка обновления экипажа: ' . $e->getMessage();
    }

    ob_start();
    require base_path('app/View/pages/company_crew_edit.php');
    $content = ob_get_clean();
    require base_path('app/View/layouts/main.php');
});

// --- Crew Archive ---
$router->post('/company/crews/{id}/archive', function ($crewId) use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);
    $crewId = (int)$crewId;

    $companyId = (int)(getSessionCompanyId() ?? 0);

    if ($companyId <= 0) {
        header('Location: /company/crews');
        exit;
    }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company || $company['status'] !== 'active') {
            header('Location: /company/crews');
            exit;
        }

        $dbIdentifier = $company['db_identifier'];
        $localDbConfig = $config['database'];
        $localDbConfig['database'] = $dbIdentifier;
        $localDb = new \App\Core\Database($localDbConfig);
        $localPdo = $localDb->connection();
                applyLocalMigrations($localPdo);

        try {
            $localPdo->query("SELECT 1 FROM crews LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $migrationSql = file_get_contents(base_path('database/migrations-local/006_create_company_crews.sql'));
            $localPdo->exec($migrationSql);
        }

        // --- PERMISSION CHECK ---
        $currentUserId = (int)($_SESSION['user_id'] ?? 0);
        $currentRole = $_SESSION['role_code'] ?? '';

        $crewStmt = $localPdo->prepare('SELECT * FROM crews WHERE id = ?');
        $crewStmt->execute([$crewId]);
        $crew = $crewStmt->fetch(PDO::FETCH_ASSOC);

        if (!$crew) {
            header('Location: /company/crews');
            exit;
        }

        if ($currentRole !== 'company_owner' && $currentRole !== 'senior_logist') {
            $isCreator = ($crew['created_by_user_id'] ?? 0) === $currentUserId;

            $grantStmt = $localPdo->prepare(
                "SELECT 1 FROM entity_access_grants
                 WHERE entity_type = 'crew' AND entity_id = ?
                 AND granted_to_user_id = ? AND access_level = 'edit'
                 AND (revoked_at IS NULL)"
            );
            $grantStmt->execute([$crewId, $currentUserId]);
            $hasEditGrant = (bool)$grantStmt->fetchColumn();

            if (!$isCreator && !$hasEditGrant) {
                http_response_code(403);
                header('Content-Type: text/plain');
                echo '403 Forbidden';
                exit;
            }
        }

        $update = $localPdo->prepare("UPDATE crews SET status = 'archived' WHERE id = ?");
        $update->execute([$crewId]);

        header('Location: /company/crews');
        exit;
    } catch (\Exception $e) {
        header('Location: /company/crews');
        exit;
    }
});

// ---- VEHICLE SETS (Транспорт) ----

$router->get('/company/vehicle-sets', function () use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);
    $pageTitle = 'Транспорт';
    $pageContext = 'Транспорт › Компания';

    $companyId = (int)(getSessionCompanyId() ?? 0);

    if ($companyId <= 0) {
        $company = null; $vehicleSets = []; $dbError = null;
        ob_start(); require base_path('app/View/pages/company_vehicle_sets.php');
        $content = ob_get_clean(); require base_path('app/View/layouts/main.php');
        return;
    }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$company) {
            $company = null; $vehicleSets = []; $dbError = null;
            ob_start(); require base_path('app/View/pages/company_vehicle_sets.php');
            $content = ob_get_clean(); require base_path('app/View/layouts/main.php');
            return;
        }
        $pageContext = 'Транспорт › Компания: ' . $company['name'];
        $topbarCrumbs = [
            ['label' => mb_strtoupper($company['name']), 'url' => '/company/dashboard'],
            ['label' => 'Подрядчики', 'url' => null],
            ['label' => 'Список транспорта', 'url' => null],
        ];
        if ($company['status'] !== 'active') {
            $vehicleSets = []; $dbError = null;
            ob_start(); require base_path('app/View/pages/company_vehicle_sets.php');
            $content = ob_get_clean(); require base_path('app/View/layouts/main.php');
            return;
        }

        $dbIdentifier = $company['db_identifier'];
        $localDbConfig = $config['database']; $localDbConfig['database'] = $dbIdentifier;
        $localDb = new \App\Core\Database($localDbConfig); $localPdo = $localDb->connection();
        applyLocalMigrations($localPdo);

        try { $localPdo->query("SELECT 1 FROM vehicle_sets LIMIT 1")->fetch(); }
        catch (\Exception $e) {
            $migrationSql = file_get_contents(base_path('database/migrations-local/017_create_vehicle_sets.sql'));
            $localPdo->exec($migrationSql);
        }

        $isLogist = ($_SESSION['role_code'] ?? '') === 'logist';
        if ($isLogist) {
            $userId = (int)$_SESSION['user_id'];
            $stmt = $localPdo->prepare(
                "SELECT vs.*,
                 vu1.plate_number AS primary_plate,
                 vu1.brand AS primary_brand,
                 vu1.model AS primary_model,
                 vu1.volume_m3 AS primary_volume_m3,
                 vu1.capacity_tons AS primary_capacity_tons,
                 vu1.diagnostic_card_number AS primary_diagnostic_card_number,
                 vu2.plate_number AS secondary_plate,
                 vu2.brand AS secondary_brand,
                 vu2.model AS secondary_model,
                 vu2.volume_m3 AS secondary_volume_m3,
                 vu2.capacity_tons AS secondary_capacity_tons,
                 vu2.diagnostic_card_number AS secondary_diagnostic_card_number,
                 EXISTS (SELECT 1 FROM documents d INNER JOIN document_types dt ON dt.id = d.document_type_id WHERE d.entity_type = 'vehicle_unit' AND d.entity_id = vu1.id AND d.deleted_at IS NULL AND dt.code = 'sts') AS has_sts,
                 EXISTS (SELECT 1 FROM documents d INNER JOIN document_types dt ON dt.id = d.document_type_id WHERE d.entity_type = 'vehicle_unit' AND d.entity_id = vu1.id AND d.deleted_at IS NULL AND dt.code = 'diagnostic_card') AS has_diagnostic_card_doc,
                 EXISTS (SELECT 1 FROM documents d WHERE d.entity_type = 'vehicle_unit' AND d.entity_id = vu1.id AND d.deleted_at IS NULL AND d.mime_type LIKE 'image/%') AS has_photo,
                 EXISTS (SELECT 1 FROM documents d INNER JOIN document_types dt ON dt.id = d.document_type_id WHERE d.entity_type = 'vehicle_unit' AND d.entity_id = vu2.id AND d.deleted_at IS NULL AND dt.code = 'sts') AS has_sts_sec,
                 EXISTS (SELECT 1 FROM documents d INNER JOIN document_types dt ON dt.id = d.document_type_id WHERE d.entity_type = 'vehicle_unit' AND d.entity_id = vu2.id AND d.deleted_at IS NULL AND dt.code = 'diagnostic_card') AS has_diagnostic_card_doc_sec,
                 EXISTS (SELECT 1 FROM documents d WHERE d.entity_type = 'vehicle_unit' AND d.entity_id = vu2.id AND d.deleted_at IS NULL AND d.mime_type LIKE 'image/%') AS has_photo_sec
                 FROM vehicle_sets vs
                 LEFT JOIN vehicle_units vu1 ON vs.primary_vehicle_unit_id = vu1.id
                 LEFT JOIN vehicle_units vu2 ON vs.secondary_vehicle_unit_id = vu2.id
                 WHERE (vs.created_by_user_id = ? OR vs.id IN (SELECT entity_id FROM entity_access_grants WHERE entity_type = 'vehicle_set' AND granted_to_user_id = ? AND access_level IN ('view','edit') AND revoked_at IS NULL))
                 ORDER BY vs.created_at DESC"
            );
            $stmt->execute([$userId, $userId]);
            $vehicleSets = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $stmt = $localPdo->query(
                "SELECT vs.*,
                 vu1.plate_number AS primary_plate,
                 vu1.brand AS primary_brand,
                 vu1.model AS primary_model,
                 vu1.volume_m3 AS primary_volume_m3,
                 vu1.capacity_tons AS primary_capacity_tons,
                 vu1.diagnostic_card_number AS primary_diagnostic_card_number,
                 vu2.plate_number AS secondary_plate,
                 vu2.brand AS secondary_brand,
                 vu2.model AS secondary_model,
                 vu2.volume_m3 AS secondary_volume_m3,
                 vu2.capacity_tons AS secondary_capacity_tons,
                 vu2.diagnostic_card_number AS secondary_diagnostic_card_number,
                 EXISTS (SELECT 1 FROM documents d INNER JOIN document_types dt ON dt.id = d.document_type_id WHERE d.entity_type = 'vehicle_unit' AND d.entity_id = vu1.id AND d.deleted_at IS NULL AND dt.code = 'sts') AS has_sts,
                 EXISTS (SELECT 1 FROM documents d INNER JOIN document_types dt ON dt.id = d.document_type_id WHERE d.entity_type = 'vehicle_unit' AND d.entity_id = vu1.id AND d.deleted_at IS NULL AND dt.code = 'diagnostic_card') AS has_diagnostic_card_doc,
                 EXISTS (SELECT 1 FROM documents d WHERE d.entity_type = 'vehicle_unit' AND d.entity_id = vu1.id AND d.deleted_at IS NULL AND d.mime_type LIKE 'image/%') AS has_photo,
                 EXISTS (SELECT 1 FROM documents d INNER JOIN document_types dt ON dt.id = d.document_type_id WHERE d.entity_type = 'vehicle_unit' AND d.entity_id = vu2.id AND d.deleted_at IS NULL AND dt.code = 'sts') AS has_sts_sec,
                 EXISTS (SELECT 1 FROM documents d INNER JOIN document_types dt ON dt.id = d.document_type_id WHERE d.entity_type = 'vehicle_unit' AND d.entity_id = vu2.id AND d.deleted_at IS NULL AND dt.code = 'diagnostic_card') AS has_diagnostic_card_doc_sec,
                 EXISTS (SELECT 1 FROM documents d WHERE d.entity_type = 'vehicle_unit' AND d.entity_id = vu2.id AND d.deleted_at IS NULL AND d.mime_type LIKE 'image/%') AS has_photo_sec
                 FROM vehicle_sets vs
                 LEFT JOIN vehicle_units vu1 ON vs.primary_vehicle_unit_id = vu1.id
                 LEFT JOIN vehicle_units vu2 ON vs.secondary_vehicle_unit_id = vu2.id
                 ORDER BY vs.created_at DESC"
            );
            $vehicleSets = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        $dbError = null;
    } catch (\Exception $e) {
        $company = $company ?? null; $vehicleSets = [];
        $dbError = 'Не удалось подключиться к базе данных компании.';
    }

    ob_start(); require base_path('app/View/pages/company_vehicle_sets.php');
    $content = ob_get_clean(); require base_path('app/View/layouts/main.php');
});

$router->get('/company/vehicle-sets/create', function () use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);
    $pageTitle = 'Создать транспорт';
    $pageContext = 'Транспорт > Компания';

    $companyId = (int) (getSessionCompanyId() ?? 0);
    $company = null;
    $success = false;
    $errors = [];
    $old = [];
    $formError = null;
    $createdVehicleSet = null;
    $docErrors = [];
    $uploadedDocs = [];
    $docTypes = [];

    if ($companyId <= 0) {
        ob_start(); require base_path('app/View/pages/company_vehicle_sets_create.php');
        $content = ob_get_clean(); require base_path('app/View/layouts/main.php');
        return;
    }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$company) {
            $company = null;
            goto renderCreateVehicleSet;
        }

        $pageContext = 'Транспорт > Компания: ' . $company['name'];

        $localDbConfig = $config['database'];
        $localDbConfig['database'] = $company['db_identifier'];
        $localDb = new \App\Core\Database($localDbConfig);
        $localPdo = $localDb->connection();
        applyLocalMigrations($localPdo);

        try {
            $localPdo->query('SELECT 1 FROM document_types LIMIT 1')->fetch();
        } catch (\Exception $e) {
            $localPdo->exec(file_get_contents(base_path('database/migrations-local/024_create_document_types.sql')));
            $localPdo->exec(file_get_contents(base_path('database/migrations-local/025_add_document_type_id.sql')));
        }

        $docTypes = $localPdo->query("SELECT id, name, code, entity_type FROM document_types WHERE entity_type = 'vehicle_unit' OR entity_type IS NULL ORDER BY sort_order, name")->fetchAll(PDO::FETCH_ASSOC);
    } catch (\Exception $e) {
        $company = null;
        $formError = 'Ошибка: ' . $e->getMessage();
    }

    renderCreateVehicleSet:
    ob_start(); require base_path('app/View/pages/company_vehicle_sets_create.php');
    $content = ob_get_clean(); require base_path('app/View/layouts/main.php');
});

$router->post('/company/vehicle-sets/create', function () use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);
    $pageTitle = 'Создать транспорт';
    $pageContext = 'Транспорт > Компания';

    $companyId = (int) (getSessionCompanyId() ?? 0);
    $errors = [];
    $old = $_POST;
    $formError = null;
    $success = false;
    $createdVehicleSet = null;
    $docErrors = [];
    $uploadedDocs = [];
    $docTypes = [];
    $company = null;

    if ($companyId <= 0) {
        $formError = 'Компания не найдена';
        ob_start(); require base_path('app/View/pages/company_vehicle_sets_create.php');
        $content = ob_get_clean(); require base_path('app/View/layouts/main.php');
        return;
    }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$company) {
            $company = null;
            $formError = 'Компания не найдена';
            goto renderCreateVehicleSetPost;
        }

        $pageContext = 'Транспорт > Компания: ' . $company['name'];
        if (($company['status'] ?? '') !== 'active') {
            $formError = 'Компания недоступна';
            goto renderCreateVehicleSetPost;
        }

        $localDbConfig = $config['database'];
        $localDbConfig['database'] = $company['db_identifier'];
        $localDb = new \App\Core\Database($localDbConfig);
        $localPdo = $localDb->connection();
        applyLocalMigrations($localPdo);

        try {
            $localPdo->query('SELECT 1 FROM document_types LIMIT 1')->fetch();
        } catch (\Exception $e) {
            $localPdo->exec(file_get_contents(base_path('database/migrations-local/024_create_document_types.sql')));
            $localPdo->exec(file_get_contents(base_path('database/migrations-local/025_add_document_type_id.sql')));
        }
        $docTypes = $localPdo->query("SELECT id, name, code, entity_type FROM document_types WHERE entity_type = 'vehicle_unit' OR entity_type IS NULL ORDER BY sort_order, name")->fetchAll(PDO::FETCH_ASSOC);

        if (isPostTruncated()) {
            $formError = 'Общий размер отправки превышает серверный лимит. Для ERP требуется настройка post_max_size не менее 100M. Уменьшите количество файлов или обратитесь к администратору.';
            goto renderCreateVehicleSetPost;
        }

        $rules = vehicleSetTypeRules();
        $docSections = vehicleSetPredefinedDocumentSections();
        $setType = trim((string) ($_POST['set_type'] ?? ''));
        $status = trim((string) ($_POST['status'] ?? 'active'));
        $comments = trim((string) ($_POST['comments'] ?? ''));
        $unitsInput = isset($_POST['units']) && is_array($_POST['units']) ? $_POST['units'] : [];
        $activeRoles = vehicleSetVisibleUnitRoles($setType);
        $unitPayload = [];
        $createdUnits = [];
        $createdUnitIds = [];
        $storedPaths = [];

        if ($setType === '' || !isset($rules[$setType])) {
            $errors['set_type'] = 'Выберите тип комплекта';
        }
        if (!in_array($status, ['active', 'inactive'], true)) {
            $status = 'active';
        }

        foreach ($activeRoles as $role) {
            $unitRule = $rules[$setType]['units'][$role] ?? [];
            $source = isset($unitsInput[$role]) && is_array($unitsInput[$role]) ? $unitsInput[$role] : [];
            $label = (string) ($unitRule['label'] ?? 'Транспортная единица');
            $showCapacity = !empty($unitRule['show_capacity']);
            $showVolume = !empty($unitRule['show_volume']);
            $diagnosticCardDate = trim((string) ($source['diagnostic_card_date'] ?? ''));
            $payload = [
                'label' => $label,
                'unit_type' => (string) ($unitRule['unit_type'] ?? ''),
                'plate_number' => trim((string) ($source['plate_number'] ?? '')),
                'brand' => trim((string) ($source['brand'] ?? '')),
                'model' => trim((string) ($source['model'] ?? '')),
                'vin' => trim((string) ($source['vin'] ?? '')),
                'capacity_tons' => trim((string) ($source['capacity_tons'] ?? '')),
                'volume_m3' => trim((string) ($source['volume_m3'] ?? '')),
                'diagnostic_card_number' => trim((string) ($source['diagnostic_card_number'] ?? '')),
                'diagnostic_card_date' => $diagnosticCardDate,
                'show_capacity' => $showCapacity,
                'show_volume' => $showVolume,
            ];
            $unitPayload[$role] = $payload;

            if ($payload['plate_number'] === '') {
                $errors['units'][$role]['plate_number'] = 'Укажите госномер';
            }
            if ($payload['vin'] !== '' && !preg_match('/^[A-HJ-NPR-Z0-9]{17}$/', strtoupper($payload['vin']))) {
                $errors['units'][$role]['vin'] = 'Формат VIN: 17 символов';
            }
            if ($payload['diagnostic_card_number'] !== '' && !ctype_digit($payload['diagnostic_card_number'])) {
                $errors['units'][$role]['diagnostic_card_number'] = 'Только цифры';
            }
            if ($diagnosticCardDate !== '') {
                $dateCheck = \DateTime::createFromFormat('Y-m-d', $diagnosticCardDate);
                if (!$dateCheck || $dateCheck->format('Y-m-d') !== $diagnosticCardDate) {
                    $errors['units'][$role]['diagnostic_card_date'] = 'Некорректная дата';
                }
            }
            if ($showCapacity) {
                if ($payload['capacity_tons'] !== '' && (!is_numeric($payload['capacity_tons']) || (float) $payload['capacity_tons'] <= 0)) {
                    $errors['units'][$role]['capacity_tons'] = 'Введите число';
                }
            } else {
                $payload['capacity_tons'] = '';
                $unitPayload[$role]['capacity_tons'] = '';
            }
            if ($showVolume) {
                if ($payload['volume_m3'] !== '' && (!is_numeric($payload['volume_m3']) || (float) $payload['volume_m3'] <= 0)) {
                    $errors['units'][$role]['volume_m3'] = 'Введите число';
                }
            } else {
                $payload['volume_m3'] = '';
                $unitPayload[$role]['volume_m3'] = '';
            }
        }

        $allowedExt = ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'doc', 'docx', 'rtf', 'odt', 'xls', 'xlsx', 'csv', 'ods', 'gif', 'bmp', 'tif', 'tiff', 'heic', 'heif'];
        $maxSize = 20 * 1024 * 1024;

        $normalizeNamedFiles = static function (array $fileBag, string $role, string $docCode): array {
            $names = $fileBag['name'][$role][$docCode] ?? [];
            $tmpNames = $fileBag['tmp_name'][$role][$docCode] ?? [];
            $errors = $fileBag['error'][$role][$docCode] ?? [];
            $sizes = $fileBag['size'][$role][$docCode] ?? [];
            $types = $fileBag['type'][$role][$docCode] ?? [];
            if (!is_array($names)) {
                $names = [$names];
                $tmpNames = [$tmpNames];
                $errors = [$errors];
                $sizes = [$sizes];
                $types = [$types];
            }
            $items = [];
            foreach ($names as $idx => $origName) {
                $items[] = [
                    'name' => trim((string) $origName),
                    'tmp_name' => (string) ($tmpNames[$idx] ?? ''),
                    'error' => (int) ($errors[$idx] ?? UPLOAD_ERR_NO_FILE),
                    'size' => (int) ($sizes[$idx] ?? 0),
                    'type' => (string) ($types[$idx] ?? ''),
                ];
            }
            return $items;
        };

        $normalizeRoleFiles = static function (array $fileBag, string $role): array {
            $names = $fileBag['name'][$role] ?? [];
            $tmpNames = $fileBag['tmp_name'][$role] ?? [];
            $errors = $fileBag['error'][$role] ?? [];
            $sizes = $fileBag['size'][$role] ?? [];
            $types = $fileBag['type'][$role] ?? [];
            if (!is_array($names)) {
                $names = [$names];
                $tmpNames = [$tmpNames];
                $errors = [$errors];
                $sizes = [$sizes];
                $types = [$types];
            }
            $items = [];
            foreach ($names as $idx => $origName) {
                $items[] = [
                    'name' => trim((string) $origName),
                    'tmp_name' => (string) ($tmpNames[$idx] ?? ''),
                    'error' => (int) ($errors[$idx] ?? UPLOAD_ERR_NO_FILE),
                    'size' => (int) ($sizes[$idx] ?? 0),
                    'type' => (string) ($types[$idx] ?? ''),
                ];
            }
            return $items;
        };

        foreach ($activeRoles as $role) {
            $docs = $docSections[$role] ?? [];
            $unitLabel = $unitPayload[$role]['label'] ?? 'Транспортная единица';
            foreach ($docs as $docMeta) {
                $files = $normalizeNamedFiles($_FILES['predef_doc'] ?? [], $role, $docMeta['doc_code']);
                foreach ($files as $file) {
                    if ($file['error'] === UPLOAD_ERR_NO_FILE || $file['name'] === '') {
                        continue;
                    }
                    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                    if (!in_array($ext, $allowedExt, true)) {
                        $docErrors[] = $unitLabel . ': Документ <' . $docMeta['name'] . '> имеет недопустимый формат';
                    }
                    if ($file['size'] > $maxSize) {
                        $docErrors[] = $unitLabel . ': Документ <' . $docMeta['name'] . '> превышает 20 МБ';
                    }
                    if (strpos($file['name'], '../') !== false || strpos($file['name'], '..\\') !== false || strpos($file['name'], '/') !== false || strpos($file['name'], '\\') !== false) {
                        $docErrors[] = $unitLabel . ': Документ <' . $docMeta['name'] . '> имеет недопустимое имя';
                    }
                }
            }

            $customSelected = $_POST['custom_doc_type'][$role] ?? [];
            $customNew = $_POST['custom_doc_type_new'][$role] ?? [];
            $customFiles = $normalizeRoleFiles($_FILES['custom_doc_file'] ?? [], $role);
            $rowCount = max(count($customFiles), is_array($customSelected) ? count($customSelected) : 0, is_array($customNew) ? count($customNew) : 0);
            for ($idx = 0; $idx < $rowCount; $idx++) {
                $file = $customFiles[$idx] ?? ['name' => '', 'error' => UPLOAD_ERR_NO_FILE, 'size' => 0, 'tmp_name' => '', 'type' => ''];
                $selectedType = trim((string) ((is_array($customSelected) ? ($customSelected[$idx] ?? '') : '')));
                $newType = trim((string) ((is_array($customNew) ? ($customNew[$idx] ?? '') : '')));
                $hasFile = $file['error'] !== UPLOAD_ERR_NO_FILE && $file['name'] !== '';
                $hasType = $selectedType !== '' || $newType !== '';
                if ($hasFile && !$hasType) {
                    $docErrors[] = $unitLabel . ': Укажите название произвольного документа #' . ($idx + 1);
                }
                if ($hasFile) {
                    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                    if (!in_array($ext, $allowedExt, true)) {
                        $docErrors[] = $unitLabel . ': Произвольный документ #' . ($idx + 1) . ' имеет недопустимый формат';
                    }
                    if ($file['size'] > $maxSize) {
                        $docErrors[] = $unitLabel . ': Произвольный документ #' . ($idx + 1) . ' превышает 20 МБ';
                    }
                    if (strpos($file['name'], '../') !== false || strpos($file['name'], '..\\') !== false || strpos($file['name'], '/') !== false || strpos($file['name'], '\\') !== false) {
                        $docErrors[] = $unitLabel . ': Произвольный документ #' . ($idx + 1) . ' имеет недопустимое имя';
                    }
                }
            }
        }

        if (!empty($errors) || !empty($docErrors)) {
            goto renderCreateVehicleSetPost;
        }

        // Check total upload size before transaction
        $totalSizeError = validateTotalUploadSize();
        if ($totalSizeError !== '') {
            $formError = $totalSizeError;
            goto renderCreateVehicleSetPost;
        }

        $storeUpload = static function (PDO $localPdo, int $companyId, string $entityType, int $entityId, string $docTypeName, string $docCode, array $file, string $category, array &$storedPaths) {
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $storedName = uniqid('doc_', true) . '.' . $ext;
            $relativeDir = 'companies/' . $companyId . '/documents/' . $entityType . '/' . $entityId;
            $absoluteDir = storage_path($relativeDir);
            if (!is_dir($absoluteDir) && !mkdir($absoluteDir, 0755, true)) {
                throw new RuntimeException('mkdir_failed');
            }
            $absolutePath = $absoluteDir . DIRECTORY_SEPARATOR . $storedName;
            if (!move_uploaded_file($file['tmp_name'], $absolutePath)) {
                throw new RuntimeException('move_failed');
            }
            $storedPaths[] = $absolutePath;
            $documentTypeId = ensureDocumentTypeRecord($localPdo, $docTypeName, $docCode, $entityType, $category);
            $insertDocument = $localPdo->prepare(
                'INSERT INTO documents (entity_type, entity_id, document_type, document_type_id, original_name, stored_name, relative_path, mime_type, file_size, status, uploaded_by_user_id, uploaded_by_role, created_by_user_id, created_by_role)
                 VALUES (:et, :eid, :dtype, :dtid, :oname, :sname, :rpath, :mime, :fsize, :status, :uid, :role, :cuid, :crole)'
            );
            $insertDocument->execute([
                ':et' => $entityType,
                ':eid' => $entityId,
                ':dtype' => $docTypeName,
                ':dtid' => $documentTypeId,
                ':oname' => $file['name'],
                ':sname' => $storedName,
                ':rpath' => $relativeDir . '/' . $storedName,
                ':mime' => $file['type'],
                ':fsize' => $file['size'],
                ':status' => 'uploaded',
                ':uid' => (int) $_SESSION['user_id'],
                ':role' => $_SESSION['role_code'],
                ':cuid' => (int) $_SESSION['user_id'],
                ':crole' => $_SESSION['role_code'],
            ]);
        };

        $localPdo->beginTransaction();
        try {
            $insertUnit = $localPdo->prepare(
                'INSERT INTO vehicle_units (plate_number, brand, model, unit_type, vin, capacity_tons, volume_m3, diagnostic_card_number, diagnostic_card_date, status, comments, created_by_user_id, created_by_role)
                 VALUES (:plate_number, :brand, :model, :unit_type, :vin, :capacity_tons, :volume_m3, :diagnostic_card_number, :diagnostic_card_date, :status, :comments, :created_by_user_id, :created_by_role)'
            );

            foreach ($activeRoles as $role) {
                $payload = $unitPayload[$role];
                $insertUnit->execute([
                    ':plate_number' => $payload['plate_number'],
                    ':brand' => $payload['brand'],
                    ':model' => $payload['model'],
                    ':unit_type' => $payload['unit_type'],
                    ':vin' => $payload['vin'],
                    ':capacity_tons' => ($payload['show_capacity'] && $payload['capacity_tons'] !== '') ? (float) $payload['capacity_tons'] : null,
                    ':volume_m3' => ($payload['show_volume'] && $payload['volume_m3'] !== '') ? (float) $payload['volume_m3'] : null,
                    ':diagnostic_card_number' => $payload['diagnostic_card_number'] !== '' ? $payload['diagnostic_card_number'] : null,
                    ':diagnostic_card_date' => $payload['diagnostic_card_date'] !== '' ? $payload['diagnostic_card_date'] : null,
                    ':status' => $status,
                    ':comments' => null,
                    ':created_by_user_id' => (int) $_SESSION['user_id'],
                    ':created_by_role' => $_SESSION['role_code'],
                ]);
                $unitId = (int) $localPdo->lastInsertId();
                $createdUnitIds[$role] = $unitId;
                $createdUnits[$role] = $payload + ['id' => $unitId];
            }

            $insertSet = $localPdo->prepare(
                'INSERT INTO vehicle_sets (set_type, primary_vehicle_unit_id, secondary_vehicle_unit_id, status, comments, created_by_user_id, created_by_role)
                 VALUES (:set_type, :primary_id, :secondary_id, :status, :comments, :uid, :role)'
            );
            $insertSet->execute([
                ':set_type' => $setType,
                ':primary_id' => (int) ($createdUnitIds['primary'] ?? 0),
                ':secondary_id' => isset($createdUnitIds['secondary']) ? (int) $createdUnitIds['secondary'] : null,
                ':status' => $status,
                ':comments' => $comments !== '' ? $comments : null,
                ':uid' => (int) $_SESSION['user_id'],
                ':role' => $_SESSION['role_code'],
            ]);
            $newSetId = (int) $localPdo->lastInsertId();

            foreach ($activeRoles as $role) {
                $docs = $docSections[$role] ?? [];
                foreach ($docs as $docMeta) {
                    $files = $normalizeNamedFiles($_FILES['predef_doc'] ?? [], $role, $docMeta['doc_code']);
                    foreach ($files as $file) {
                        if ($file['error'] === UPLOAD_ERR_NO_FILE || $file['name'] === '') {
                            continue;
                        }
                        $storeUpload($localPdo, $companyId, 'vehicle_unit', (int) $createdUnitIds[$role], $docMeta['name'], $docMeta['doc_code'], $file, 'predefined', $storedPaths);
                        $uploadedDocs[] = ($unitPayload[$role]['label'] ?? 'Транспортная единица') . ': ' . $docMeta['name'] . ' (' . $file['name'] . ')';
                    }
                }

                $customSelected = $_POST['custom_doc_type'][$role] ?? [];
                $customNew = $_POST['custom_doc_type_new'][$role] ?? [];
                $customFiles = $normalizeRoleFiles($_FILES['custom_doc_file'] ?? [], $role);
                $rowCount = max(count($customFiles), is_array($customSelected) ? count($customSelected) : 0, is_array($customNew) ? count($customNew) : 0);
                for ($idx = 0; $idx < $rowCount; $idx++) {
                    $file = $customFiles[$idx] ?? ['name' => '', 'error' => UPLOAD_ERR_NO_FILE, 'size' => 0, 'tmp_name' => '', 'type' => ''];
                    if ($file['error'] === UPLOAD_ERR_NO_FILE || $file['name'] === '') {
                        continue;
                    }
                    $selectedType = trim((string) ((is_array($customSelected) ? ($customSelected[$idx] ?? '') : '')));
                    $newType = trim((string) ((is_array($customNew) ? ($customNew[$idx] ?? '') : '')));
                    $docTypeName = $newType !== '' ? $newType : $selectedType;
                    $storeUpload($localPdo, $companyId, 'vehicle_unit', (int) $createdUnitIds[$role], $docTypeName, '', $file, 'custom', $storedPaths);
                    $uploadedDocs[] = ($unitPayload[$role]['label'] ?? 'Транспортная единица') . ': ' . $docTypeName . ' (' . $file['name'] . ')';
                }
            }

            $localPdo->commit();

            $createdVehicleSet = [
                'id' => $newSetId,
                'set_type' => $setType,
                'status' => $status,
                'comments' => $comments,
                'units' => $createdUnits,
                'primary_plate' => $createdUnits['primary']['plate_number'] ?? '',
                'secondary_plate' => $createdUnits['secondary']['plate_number'] ?? null,
                'primary_vehicle_unit_id' => $createdUnitIds['primary'] ?? null,
                'secondary_vehicle_unit_id' => $createdUnitIds['secondary'] ?? null,
            ];
            $success = true;
        } catch (\Throwable $e) {
            if ($localPdo->inTransaction()) {
                $localPdo->rollBack();
            }
            foreach ($storedPaths as $storedPath) {
                if (is_string($storedPath) && $storedPath !== '' && is_file($storedPath)) {
                    @unlink($storedPath);
                }
            }
            throw $e;
        }
    } catch (\Exception $e) {
        $company = $company ?? null;
        $formError = 'Ошибка: ' . $e->getMessage();
    }

    renderCreateVehicleSetPost:
    ob_start(); require base_path('app/View/pages/company_vehicle_sets_create.php');
    $content = ob_get_clean(); require base_path('app/View/layouts/main.php');
});

// --- Vehicle Set Modal View ---

$router->get('/company/vehicle-sets/{id}/modal-view', function ($id) use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);

    $companyId = (int) (getSessionCompanyId() ?? 0);
    if ($companyId <= 0) {
        http_response_code(404);
        echo '<div class="notice warn">Компания не найдена.</div>';
        exit;
    }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company || ($company['status'] ?? '') !== 'active') {
            http_response_code(404);
            echo '<div class="notice warn">Компания не найдена или неактивна.</div>';
            exit;
        }

        $localDbConfig = $config['database'];
        $localDbConfig['database'] = $company['db_identifier'];
        $localDb = new \App\Core\Database($localDbConfig);
        $localPdo = $localDb->connection();
        applyLocalMigrations($localPdo);

        $vehicleSetStmt = $localPdo->prepare('SELECT * FROM vehicle_sets WHERE id = ?');
        $vehicleSetStmt->execute([(int) $id]);
        $vehicleSet = $vehicleSetStmt->fetch(PDO::FETCH_ASSOC) ?: null;

        if (!$vehicleSet) {
            http_response_code(404);
            echo '<div class="notice warn">Транспорт не найден.</div>';
            exit;
        }

        $grantAccessLevel = null;
        $roleCode = $_SESSION['role_code'] ?? '';
        if ($roleCode === 'logist') {
            $userId = (int) ($_SESSION['user_id'] ?? 0);
            $grantCheck = $localPdo->prepare("SELECT access_level FROM entity_access_grants WHERE entity_type = 'vehicle_set' AND entity_id = ? AND granted_to_user_id = ? AND revoked_at IS NULL LIMIT 1");
            $grantCheck->execute([(int) $id, $userId]);
            $grantRow = $grantCheck->fetch(PDO::FETCH_ASSOC);
            $grantAccessLevel = $grantRow['access_level'] ?? null;
            $hasGrant = in_array($grantAccessLevel, ['view', 'edit'], true);
            if ((int) ($vehicleSet['created_by_user_id'] ?? 0) !== $userId && !$hasGrant) {
                http_response_code(403);
                echo '<div class="notice warn">У вас нет доступа к этой записи.</div>';
                exit;
            }
        }

        $unitIds = array_values(array_filter([
            (int) ($vehicleSet['primary_vehicle_unit_id'] ?? 0),
            (int) ($vehicleSet['secondary_vehicle_unit_id'] ?? 0),
        ]));

        $unitsByRole = ['primary' => [], 'secondary' => []];
        if (!empty($unitIds)) {
            $placeholders = implode(',', array_fill(0, count($unitIds), '?'));
            $unitsStmt = $localPdo->prepare("SELECT * FROM vehicle_units WHERE id IN ($placeholders)");
            $unitsStmt->execute($unitIds);
            foreach ($unitsStmt->fetchAll(PDO::FETCH_ASSOC) as $unit) {
                $unitId = (int) ($unit['id'] ?? 0);
                if ($unitId === (int) ($vehicleSet['primary_vehicle_unit_id'] ?? 0)) {
                    $unitsByRole['primary'] = $unit;
                } elseif ($unitId === (int) ($vehicleSet['secondary_vehicle_unit_id'] ?? 0)) {
                    $unitsByRole['secondary'] = $unit;
                }
            }
        }

        $docsByRole = ['primary' => [], 'secondary' => []];
        if (!empty($unitIds)) {
            $placeholders = implode(',', array_fill(0, count($unitIds), '?'));
            $docsStmt = $localPdo->prepare(
                "SELECT id, entity_id, document_type, original_name, mime_type, stored_name, file_size
                   FROM documents
                  WHERE entity_type = 'vehicle_unit'
                    AND entity_id IN ($placeholders)
                    AND deleted_at IS NULL
                  ORDER BY id"
            );
            $docsStmt->execute($unitIds);
            foreach ($docsStmt->fetchAll(PDO::FETCH_ASSOC) as $doc) {
                $entityId = (int) ($doc['entity_id'] ?? 0);
                if ($entityId === (int) ($vehicleSet['primary_vehicle_unit_id'] ?? 0)) {
                    $docsByRole['primary'][] = $doc;
                } elseif ($entityId === (int) ($vehicleSet['secondary_vehicle_unit_id'] ?? 0)) {
                    $docsByRole['secondary'][] = $doc;
                }
            }
        }

        $rules = vehicleSetTypeRules();
        $unitTitles = [
            'primary' => $rules[$vehicleSet['set_type'] ?? '']['units']['primary']['label'] ?? 'Основная единица',
            'secondary' => $rules[$vehicleSet['set_type'] ?? '']['units']['secondary']['label'] ?? 'Доп. единица',
        ];

        $canEdit = false;
        $canDelete = false;
        if ($roleCode === 'company_owner' || $roleCode === 'senior_logist') {
            $canEdit = true;
            $canDelete = true;
        } elseif ($roleCode === 'logist') {
            $userId = (int) ($_SESSION['user_id'] ?? 0);
            if ((int) ($vehicleSet['created_by_user_id'] ?? 0) === $userId) {
                $canEdit = true;
                $canDelete = true;
            } elseif ($grantAccessLevel === 'edit') {
                $canEdit = true;
            }
        }

        header('Content-Type: text/html; charset=utf-8');
        require base_path('app/View/partials/company_vehicle_set_modal_view.php');
        exit;
    } catch (\Throwable $e) {
        http_response_code(500);
        echo '<div class="notice warn">Ошибка загрузки: ' . e($e->getMessage()) . '</div>';
        exit;
    }
});

// --- Vehicle Set Modal Edit (GET) ---

$router->get('/company/vehicle-sets/{id}/modal-edit', function ($id) use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);

    $companyId = (int) (getSessionCompanyId() ?? 0);
    if ($companyId <= 0) {
        http_response_code(404);
        echo '<div class="notice warn">Компания не найдена.</div>';
        exit;
    }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company || ($company['status'] ?? '') !== 'active') {
            http_response_code(404);
            echo '<div class="notice warn">Компания не найдена или неактивна.</div>';
            exit;
        }

        $localDbConfig = $config['database'];
        $localDbConfig['database'] = $company['db_identifier'];
        $localDb = new \App\Core\Database($localDbConfig);
        $localPdo = $localDb->connection();
        applyLocalMigrations($localPdo);

        $vehicleSetStmt = $localPdo->prepare('SELECT * FROM vehicle_sets WHERE id = ?');
        $vehicleSetStmt->execute([(int) $id]);
        $vehicleSet = $vehicleSetStmt->fetch(PDO::FETCH_ASSOC) ?: null;

        if (!$vehicleSet) {
            http_response_code(404);
            echo '<div class="notice warn">Транспорт не найден.</div>';
            exit;
        }

        $roleCode = $_SESSION['role_code'] ?? '';
        $grantAccessLevel = null;
        $canEdit = false;
        if ($roleCode === 'company_owner' || $roleCode === 'senior_logist') {
            $canEdit = true;
        } elseif ($roleCode === 'logist') {
            $userId = (int) ($_SESSION['user_id'] ?? 0);
            if ((int) ($vehicleSet['created_by_user_id'] ?? 0) === $userId) {
                $canEdit = true;
            } else {
                $grantCheck = $localPdo->prepare("SELECT access_level FROM entity_access_grants WHERE entity_type = 'vehicle_set' AND entity_id = ? AND granted_to_user_id = ? AND revoked_at IS NULL LIMIT 1");
                $grantCheck->execute([(int) $id, $userId]);
                $grantRow = $grantCheck->fetch(PDO::FETCH_ASSOC);
                $grantAccessLevel = $grantRow['access_level'] ?? null;
                $canEdit = $grantAccessLevel === 'edit';
            }
        }

        if (!$canEdit) {
            http_response_code(403);
            echo '<div class="notice warn">У вас нет права редактировать эту запись.</div>';
            exit;
        }

        $unitIds = array_values(array_filter([
            (int) ($vehicleSet['primary_vehicle_unit_id'] ?? 0),
            (int) ($vehicleSet['secondary_vehicle_unit_id'] ?? 0),
        ]));

        $unitsByRole = ['primary' => [], 'secondary' => []];
        if (!empty($unitIds)) {
            $placeholders = implode(',', array_fill(0, count($unitIds), '?'));
            $unitsStmt = $localPdo->prepare("SELECT * FROM vehicle_units WHERE id IN ($placeholders)");
            $unitsStmt->execute($unitIds);
            foreach ($unitsStmt->fetchAll(PDO::FETCH_ASSOC) as $unit) {
                $unitId = (int) ($unit['id'] ?? 0);
                if ($unitId === (int) ($vehicleSet['primary_vehicle_unit_id'] ?? 0)) {
                    $unitsByRole['primary'] = $unit;
                } elseif ($unitId === (int) ($vehicleSet['secondary_vehicle_unit_id'] ?? 0)) {
                    $unitsByRole['secondary'] = $unit;
                }
            }
        }

        $docsByRole = ['primary' => [], 'secondary' => []];
        if (!empty($unitIds)) {
            $placeholders = implode(',', array_fill(0, count($unitIds), '?'));
            $docsStmt = $localPdo->prepare(
                "SELECT id, entity_id, document_type, original_name, mime_type, stored_name, file_size
                   FROM documents
                  WHERE entity_type = 'vehicle_unit'
                    AND entity_id IN ($placeholders)
                    AND deleted_at IS NULL
                  ORDER BY id"
            );
            $docsStmt->execute($unitIds);
            foreach ($docsStmt->fetchAll(PDO::FETCH_ASSOC) as $doc) {
                $entityId = (int) ($doc['entity_id'] ?? 0);
                if ($entityId === (int) ($vehicleSet['primary_vehicle_unit_id'] ?? 0)) {
                    $docsByRole['primary'][] = $doc;
                } elseif ($entityId === (int) ($vehicleSet['secondary_vehicle_unit_id'] ?? 0)) {
                    $docsByRole['secondary'][] = $doc;
                }
            }
        }

        $old = [
            'set_type' => $vehicleSet['set_type'] ?? 'single',
            'status' => $vehicleSet['status'] ?? 'active',
            'comments' => $vehicleSet['comments'] ?? '',
            'units' => [
                'primary' => $unitsByRole['primary'] ?? [],
                'secondary' => $unitsByRole['secondary'] ?? [],
            ],
        ];
        $errors = [];
        $formError = null;

        header('Content-Type: text/html; charset=utf-8');
        require base_path('app/View/partials/company_vehicle_set_modal_edit.php');
        exit;
    } catch (\Throwable $e) {
        http_response_code(500);
        echo '<div class="notice warn">Ошибка загрузки: ' . e($e->getMessage()) . '</div>';
        exit;
    }
});

// --- Vehicle Set Modal Edit (POST) ---

$router->post('/company/vehicle-sets/{id}/modal-edit', function ($id) use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);

    $companyId = (int) (getSessionCompanyId() ?? 0);
    if ($companyId <= 0) {
        http_response_code(404);
        echo '<div class="notice warn">Компания не найдена.</div>';
        exit;
    }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company || ($company['status'] ?? '') !== 'active') {
            http_response_code(404);
            echo '<div class="notice warn">Компания не найдена или неактивна.</div>';
            exit;
        }

        $localDbConfig = $config['database'];
        $localDbConfig['database'] = $company['db_identifier'];
        $localDb = new \App\Core\Database($localDbConfig);
        $localPdo = $localDb->connection();
        applyLocalMigrations($localPdo);

        $vehicleSetStmt = $localPdo->prepare('SELECT * FROM vehicle_sets WHERE id = ?');
        $vehicleSetStmt->execute([(int) $id]);
        $vehicleSet = $vehicleSetStmt->fetch(PDO::FETCH_ASSOC) ?: null;

        if (!$vehicleSet) {
            http_response_code(404);
            echo '<div class="notice warn">Транспорт не найден.</div>';
            exit;
        }

        $roleCode = $_SESSION['role_code'] ?? '';
        $canEdit = false;
        if ($roleCode === 'company_owner' || $roleCode === 'senior_logist') {
            $canEdit = true;
        } elseif ($roleCode === 'logist') {
            $userId = (int) ($_SESSION['user_id'] ?? 0);
            if ((int) ($vehicleSet['created_by_user_id'] ?? 0) === $userId) {
                $canEdit = true;
            } else {
                $grantCheck = $localPdo->prepare("SELECT access_level FROM entity_access_grants WHERE entity_type = 'vehicle_set' AND entity_id = ? AND granted_to_user_id = ? AND revoked_at IS NULL LIMIT 1");
                $grantCheck->execute([(int) $id, $userId]);
                $grantRow = $grantCheck->fetch(PDO::FETCH_ASSOC);
                $canEdit = ($grantRow['access_level'] ?? null) === 'edit';
            }
        }

        if (!$canEdit) {
            http_response_code(403);
            echo '<div class="notice warn">У вас нет права редактировать эту запись.</div>';
            exit;
        }

        $currentUnitIds = [
            'primary' => (int) ($vehicleSet['primary_vehicle_unit_id'] ?? 0),
            'secondary' => (int) ($vehicleSet['secondary_vehicle_unit_id'] ?? 0),
        ];

        $unitsByRole = ['primary' => [], 'secondary' => []];
        $loadedUnitIds = array_values(array_filter($currentUnitIds));
        if (!empty($loadedUnitIds)) {
            $placeholders = implode(',', array_fill(0, count($loadedUnitIds), '?'));
            $unitsStmt = $localPdo->prepare("SELECT * FROM vehicle_units WHERE id IN ($placeholders)");
            $unitsStmt->execute($loadedUnitIds);
            foreach ($unitsStmt->fetchAll(PDO::FETCH_ASSOC) as $unit) {
                $unitId = (int) ($unit['id'] ?? 0);
                if ($unitId === $currentUnitIds['primary']) {
                    $unitsByRole['primary'] = $unit;
                } elseif ($unitId === $currentUnitIds['secondary']) {
                    $unitsByRole['secondary'] = $unit;
                }
            }
        }

        $docsByRole = ['primary' => [], 'secondary' => []];
        if (!empty($loadedUnitIds)) {
            $placeholders = implode(',', array_fill(0, count($loadedUnitIds), '?'));
            $docsStmt = $localPdo->prepare(
                "SELECT id, entity_id, document_type, original_name, mime_type, stored_name, file_size
                   FROM documents
                  WHERE entity_type = 'vehicle_unit'
                    AND entity_id IN ($placeholders)
                    AND deleted_at IS NULL
                  ORDER BY id"
            );
            $docsStmt->execute($loadedUnitIds);
            foreach ($docsStmt->fetchAll(PDO::FETCH_ASSOC) as $doc) {
                $entityId = (int) ($doc['entity_id'] ?? 0);
                if ($entityId === $currentUnitIds['primary']) {
                    $docsByRole['primary'][] = $doc;
                } elseif ($entityId === $currentUnitIds['secondary']) {
                    $docsByRole['secondary'][] = $doc;
                }
            }
        }

        $rules = vehicleSetTypeRules();
        $allowedExt = ['pdf', 'doc', 'docx', 'rtf', 'odt', 'xls', 'xlsx', 'csv', 'ods', 'jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp', 'tif', 'tiff', 'heic', 'heif'];
        $maxFileSize = 20 * 1024 * 1024;

        $normalizeDate = static function (?string $value): ?string {
            $value = trim((string) $value);
            if ($value === '') {
                return '';
            }
            $value = preg_replace('/[^0-9.\-]/u', '', $value);
            if (preg_match('/^(\d{2})\.(\d{2})\.(\d{4})$/', $value, $m)) {
                return $m[3] . '-' . $m[2] . '-' . $m[1];
            }
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                return $value;
            }
            return null;
        };

        $normalizeRoleFiles = static function (array $fileBag, string $role): array {
            $names = $fileBag['name'][$role] ?? [];
            $tmpNames = $fileBag['tmp_name'][$role] ?? [];
            $errors = $fileBag['error'][$role] ?? [];
            $sizes = $fileBag['size'][$role] ?? [];
            $types = $fileBag['type'][$role] ?? [];
            if (!is_array($names)) {
                $names = [$names];
                $tmpNames = [$tmpNames];
                $errors = [$errors];
                $sizes = [$sizes];
                $types = [$types];
            }
            $items = [];
            foreach ($names as $idx => $origName) {
                $items[] = [
                    'name' => trim((string) $origName),
                    'tmp_name' => (string) ($tmpNames[$idx] ?? ''),
                    'error' => (int) ($errors[$idx] ?? UPLOAD_ERR_NO_FILE),
                    'size' => (int) ($sizes[$idx] ?? 0),
                    'type' => (string) ($types[$idx] ?? ''),
                ];
            }
            return $items;
        };

        $lockedSetType = (string) ($vehicleSet['set_type'] ?? 'single');
        $old = [
            'set_type' => $lockedSetType,
            'status' => trim((string) ($_POST['status'] ?? ($vehicleSet['status'] ?? 'active'))),
            'comments' => trim((string) ($_POST['comments'] ?? ($vehicleSet['comments'] ?? ''))),
            'units' => [],
            'custom_doc_type' => $_POST['custom_doc_type'] ?? [],
        ];
        $errors = [];
        $formError = null;

        $setType = isset($rules[$lockedSetType]) ? $lockedSetType : 'single';
        $old['set_type'] = $setType;

        if (!in_array($old['status'], ['active', 'inactive'], true)) {
            $old['status'] = 'active';
        }

        $activeRoles = isset($rules[$setType]['units']) && is_array($rules[$setType]['units'])
            ? array_keys($rules[$setType]['units'])
            : ['primary'];
        $updatedUnitPayload = [];

        foreach ($activeRoles as $role) {
            $roleRule = $rules[$setType]['units'][$role] ?? [];
            $posted = isset($_POST['units'][$role]) && is_array($_POST['units'][$role]) ? $_POST['units'][$role] : [];
            $plate = mb_strtoupper(trim((string) ($posted['plate_number'] ?? '')));
            $brand = trim((string) ($posted['brand'] ?? ''));
            $model = trim((string) ($posted['model'] ?? ''));
            $vin = mb_strtoupper(trim((string) ($posted['vin'] ?? '')));
            $capacity = trim((string) ($posted['capacity_tons'] ?? ''));
            $volume = trim((string) ($posted['volume_m3'] ?? ''));
            $diagNumber = trim((string) ($posted['diagnostic_card_number'] ?? ''));
            $diagDateRaw = trim((string) ($posted['diagnostic_card_date'] ?? ''));
            $diagDate = $normalizeDate($diagDateRaw);

            $old['units'][$role] = [
                'plate_number' => $plate,
                'brand' => $brand,
                'model' => $model,
                'vin' => $vin,
                'capacity_tons' => $capacity,
                'volume_m3' => $volume,
                'diagnostic_card_number' => $diagNumber,
                'diagnostic_card_date' => $diagDateRaw,
            ];

            if ($plate === '') {
                $errors['units'][$role]['plate_number'] = 'Обязательное поле';
            }
            if ($vin !== '' && !preg_match('/^[A-HJ-NPR-Z0-9]{17}$/', $vin)) {
                $errors['units'][$role]['vin'] = 'Формат VIN: 17 символов';
            }
            if ($diagNumber !== '' && !ctype_digit($diagNumber)) {
                $errors['units'][$role]['diagnostic_card_number'] = 'Только цифры';
            }
            if ($diagDateRaw !== '' && $diagDate === null) {
                $errors['units'][$role]['diagnostic_card_date'] = 'Некорректная дата';
            }
            if (!empty($roleRule['show_capacity']) && $capacity !== '' && (!is_numeric($capacity) || (float) $capacity <= 0)) {
                $errors['units'][$role]['capacity_tons'] = 'Введите число';
            }
            if (!empty($roleRule['show_volume']) && $volume !== '' && (!is_numeric($volume) || (float) $volume <= 0)) {
                $errors['units'][$role]['volume_m3'] = 'Введите число';
            }

            $updatedUnitPayload[$role] = [
                'plate_number' => $plate,
                'brand' => $brand,
                'model' => $model,
                'unit_type' => (string) ($roleRule['unit_type'] ?? ''),
                'vin' => $vin,
                'capacity_tons' => !empty($roleRule['show_capacity']) && $capacity !== '' ? (float) $capacity : null,
                'volume_m3' => !empty($roleRule['show_volume']) && $volume !== '' ? (float) $volume : null,
                'diagnostic_card_number' => $diagNumber !== '' ? $diagNumber : null,
                'diagnostic_card_date' => $diagDate !== '' ? $diagDate : null,
            ];
        }

        if (!empty($_FILES['existing_doc_file']['name']) && is_array($_FILES['existing_doc_file']['name'])) {
            foreach ($_FILES['existing_doc_file']['name'] as $docId => $origName) {
                $uploadError = (int) ($_FILES['existing_doc_file']['error'][$docId] ?? UPLOAD_ERR_NO_FILE);
                if ($uploadError !== UPLOAD_ERR_OK || trim((string) $origName) === '') {
                    continue;
                }
                $size = (int) ($_FILES['existing_doc_file']['size'][$docId] ?? 0);
                if ($size > $maxFileSize) {
                    $formError = 'Один из файлов превышает 20 МБ.';
                }
                $ext = strtolower(pathinfo((string) $origName, PATHINFO_EXTENSION));
                if (!in_array($ext, $allowedExt, true)) {
                    $formError = 'Один из файлов имеет недопустимый формат.';
                }
            }
        }

        foreach ($activeRoles as $role) {
            $customFiles = $normalizeRoleFiles($_FILES['custom_doc_file'] ?? [], $role);
            $customTitles = $_POST['custom_doc_type'][$role] ?? [];
            $rowCount = max(count($customFiles), is_array($customTitles) ? count($customTitles) : 0);
            for ($i = 0; $i < $rowCount; $i++) {
                $file = $customFiles[$i] ?? ['name' => '', 'error' => UPLOAD_ERR_NO_FILE, 'size' => 0];
                $title = trim((string) ((is_array($customTitles) ? ($customTitles[$i] ?? '') : '')));
                $hasFile = ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK && ($file['name'] ?? '') !== '';
                if ($hasFile && $title === '') {
                    $formError = 'Введите название для добавляемого документа.';
                }
                if ($hasFile) {
                    $ext = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
                    if (!in_array($ext, $allowedExt, true)) {
                        $formError = 'Один из файлов имеет недопустимый формат.';
                    }
                    if ((int) ($file['size'] ?? 0) > $maxFileSize) {
                        $formError = 'Один из файлов превышает 20 МБ.';
                    }
                }
            }
        }

        if (isPostTruncated()) {
            $formError = 'Общий размер отправки превышает серверный лимит.';
        }
        $totalSizeError = validateTotalUploadSize();
        if ($totalSizeError !== '') {
            $formError = $totalSizeError;
        }

        if (!empty($errors) || $formError !== null) {
            header('Content-Type: text/html; charset=utf-8');
            require base_path('app/View/partials/company_vehicle_set_modal_edit.php');
            exit;
        }

        $storedPaths = [];
        $filePathsToDelete = [];
        $dirPathsToClean = [];
        $companyIdForPath = $companyId;
        $storeUpload = static function (PDO $localPdo, int $companyId, string $entityType, int $entityId, string $docTypeName, string $docCode, array $file, string $category, array &$storedPaths) {
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $storedName = uniqid('doc_', true) . '.' . $ext;
            $relativeDir = 'companies/' . $companyId . '/documents/' . $entityType . '/' . $entityId;
            $absoluteDir = storage_path($relativeDir);
            if (!is_dir($absoluteDir) && !mkdir($absoluteDir, 0755, true)) {
                throw new RuntimeException('mkdir_failed');
            }
            $absolutePath = $absoluteDir . DIRECTORY_SEPARATOR . $storedName;
            if (!move_uploaded_file($file['tmp_name'], $absolutePath)) {
                throw new RuntimeException('move_failed');
            }
            $storedPaths[] = $absolutePath;
            $documentTypeId = ensureDocumentTypeRecord($localPdo, $docTypeName, $docCode, $entityType, $category);
            $insertDocument = $localPdo->prepare(
                'INSERT INTO documents (entity_type, entity_id, document_type, document_type_id, original_name, stored_name, relative_path, mime_type, file_size, status, uploaded_by_user_id, uploaded_by_role, created_by_user_id, created_by_role)
                 VALUES (:et, :eid, :dtype, :dtid, :oname, :sname, :rpath, :mime, :fsize, :status, :uid, :role, :cuid, :crole)'
            );
            $insertDocument->execute([
                ':et' => $entityType,
                ':eid' => $entityId,
                ':dtype' => $docTypeName,
                ':dtid' => $documentTypeId,
                ':oname' => $file['name'],
                ':sname' => $storedName,
                ':rpath' => $relativeDir . '/' . $storedName,
                ':mime' => $file['type'] ?: 'application/octet-stream',
                ':fsize' => $file['size'],
                ':status' => 'uploaded',
                ':uid' => (int) ($_SESSION['user_id'] ?? 0),
                ':role' => $_SESSION['role_code'] ?? null,
                ':cuid' => (int) ($_SESSION['user_id'] ?? 0),
                ':crole' => $_SESSION['role_code'] ?? null,
            ]);
        };

        $collectDocFilesForDocId = static function (PDO $localPdo, int $docId, array &$filePaths): void {
            $docStmt = $localPdo->prepare("SELECT relative_path FROM documents WHERE id = ?");
            $docStmt->execute([$docId]);
            $relative = trim((string) ($docStmt->fetchColumn() ?: ''));
            if ($relative !== '') {
                $absolute = storage_path($relative);
                if (is_file($absolute)) {
                    $filePaths[] = $absolute;
                }
            }
        };

        $updatedUnitIds = $currentUnitIds;

        $localPdo->beginTransaction();
        try {
            $insertUnit = $localPdo->prepare(
                'INSERT INTO vehicle_units (plate_number, brand, model, unit_type, vin, capacity_tons, volume_m3, diagnostic_card_number, diagnostic_card_date, status, comments, created_by_user_id, created_by_role)
                 VALUES (:plate_number, :brand, :model, :unit_type, :vin, :capacity_tons, :volume_m3, :diagnostic_card_number, :diagnostic_card_date, :status, :comments, :created_by_user_id, :created_by_role)'
            );
            $updateUnit = $localPdo->prepare(
                'UPDATE vehicle_units
                    SET plate_number = :plate_number,
                        brand = :brand,
                        model = :model,
                        unit_type = :unit_type,
                        vin = :vin,
                        capacity_tons = :capacity_tons,
                        volume_m3 = :volume_m3,
                        diagnostic_card_number = :diagnostic_card_number,
                        diagnostic_card_date = :diagnostic_card_date,
                        status = :status,
                        updated_by_user_id = :updated_by_user_id,
                        updated_by_role = :updated_by_role
                  WHERE id = :id'
            );

            foreach ($activeRoles as $role) {
                $payload = $updatedUnitPayload[$role];
                if (!empty($updatedUnitIds[$role])) {
                    $updateUnit->execute([
                        ':plate_number' => $payload['plate_number'],
                        ':brand' => $payload['brand'],
                        ':model' => $payload['model'],
                        ':unit_type' => $payload['unit_type'],
                        ':vin' => $payload['vin'],
                        ':capacity_tons' => $payload['capacity_tons'],
                        ':volume_m3' => $payload['volume_m3'],
                        ':diagnostic_card_number' => $payload['diagnostic_card_number'],
                        ':diagnostic_card_date' => $payload['diagnostic_card_date'],
                        ':status' => $old['status'],
                        ':updated_by_user_id' => (int) ($_SESSION['user_id'] ?? 0),
                        ':updated_by_role' => $_SESSION['role_code'] ?? null,
                        ':id' => (int) $updatedUnitIds[$role],
                    ]);
                } else {
                    $insertUnit->execute([
                        ':plate_number' => $payload['plate_number'],
                        ':brand' => $payload['brand'],
                        ':model' => $payload['model'],
                        ':unit_type' => $payload['unit_type'],
                        ':vin' => $payload['vin'],
                        ':capacity_tons' => $payload['capacity_tons'],
                        ':volume_m3' => $payload['volume_m3'],
                        ':diagnostic_card_number' => $payload['diagnostic_card_number'],
                        ':diagnostic_card_date' => $payload['diagnostic_card_date'],
                        ':status' => $old['status'],
                        ':comments' => null,
                        ':created_by_user_id' => (int) ($_SESSION['user_id'] ?? 0),
                        ':created_by_role' => $_SESSION['role_code'] ?? null,
                    ]);
                    $updatedUnitIds[$role] = (int) $localPdo->lastInsertId();
                }
            }

            foreach (['primary', 'secondary'] as $role) {
                if (in_array($role, $activeRoles, true)) {
                    continue;
                }
                $removeId = (int) ($currentUnitIds[$role] ?? 0);
                if ($removeId <= 0) {
                    continue;
                }

                $docsStmt = $localPdo->prepare("SELECT relative_path FROM documents WHERE entity_type = 'vehicle_unit' AND entity_id = ?");
                $docsStmt->execute([$removeId]);
                foreach ($docsStmt->fetchAll(PDO::FETCH_ASSOC) as $doc) {
                    $relative = trim((string) ($doc['relative_path'] ?? ''));
                    if ($relative !== '') {
                        $absolute = storage_path($relative);
                        if (is_file($absolute)) {
                            $filePathsToDelete[] = $absolute;
                        }
                    }
                }
                $dirPathsToClean[] = storage_path('companies/' . $companyIdForPath . '/documents/vehicle_unit/' . $removeId);

                $localPdo->prepare("DELETE FROM entity_access_grants WHERE entity_type = 'vehicle_unit' AND entity_id = ?")->execute([$removeId]);
                $localPdo->prepare("DELETE FROM documents WHERE entity_type = 'vehicle_unit' AND entity_id = ?")->execute([$removeId]);
                $localPdo->prepare("DELETE FROM vehicle_units WHERE id = ?")->execute([$removeId]);
                $updatedUnitIds[$role] = 0;
            }

            $updateSet = $localPdo->prepare(
                'UPDATE vehicle_sets
                    SET set_type = :set_type,
                        primary_vehicle_unit_id = :primary_id,
                        secondary_vehicle_unit_id = :secondary_id,
                        status = :status,
                        comments = :comments,
                        updated_by_user_id = :updated_by_user_id,
                        updated_by_role = :updated_by_role
                  WHERE id = :id'
            );
            $updateSet->execute([
                ':set_type' => $setType,
                ':primary_id' => (int) ($updatedUnitIds['primary'] ?? 0),
                ':secondary_id' => !empty($updatedUnitIds['secondary']) ? (int) $updatedUnitIds['secondary'] : null,
                ':status' => $old['status'],
                ':comments' => $old['comments'] !== '' ? $old['comments'] : null,
                ':updated_by_user_id' => (int) ($_SESSION['user_id'] ?? 0),
                ':updated_by_role' => $_SESSION['role_code'] ?? null,
                ':id' => (int) $id,
            ]);

            $deleteExistingDocs = $_POST['delete_existing_doc'] ?? [];
            $replaceDocIds = [];
            if (!empty($_FILES['existing_doc_file']['name']) && is_array($_FILES['existing_doc_file']['name'])) {
                foreach ($_FILES['existing_doc_file']['name'] as $docId => $origName) {
                    $uploadError = (int) ($_FILES['existing_doc_file']['error'][$docId] ?? UPLOAD_ERR_NO_FILE);
                    if ($uploadError === UPLOAD_ERR_OK && trim((string) $origName) !== '') {
                        $replaceDocIds[] = (int) $docId;
                    }
                }
            }

            foreach ($deleteExistingDocs as $docId => $flag) {
                $docId = (int) $docId;
                if ($flag !== '1' || in_array($docId, $replaceDocIds, true)) {
                    continue;
                }
                $collectDocFilesForDocId($localPdo, $docId, $filePathsToDelete);
                $localPdo->prepare("DELETE FROM documents WHERE id = ? AND entity_type = 'vehicle_unit'")->execute([$docId]);
            }

            foreach ($replaceDocIds as $docId) {
                $docStmt = $localPdo->prepare("SELECT entity_id, document_type FROM documents WHERE id = ? AND entity_type = 'vehicle_unit' LIMIT 1");
                $docStmt->execute([$docId]);
                $existingDoc = $docStmt->fetch(PDO::FETCH_ASSOC);
                if (!$existingDoc) {
                    continue;
                }

                $collectDocFilesForDocId($localPdo, $docId, $filePathsToDelete);
                $localPdo->prepare("DELETE FROM documents WHERE id = ? AND entity_type = 'vehicle_unit'")->execute([$docId]);

                $file = [
                    'name' => (string) ($_FILES['existing_doc_file']['name'][$docId] ?? ''),
                    'tmp_name' => (string) ($_FILES['existing_doc_file']['tmp_name'][$docId] ?? ''),
                    'size' => (int) ($_FILES['existing_doc_file']['size'][$docId] ?? 0),
                    'type' => (string) ($_FILES['existing_doc_file']['type'][$docId] ?? 'application/octet-stream'),
                ];
                $storeUpload(
                    $localPdo,
                    $companyIdForPath,
                    'vehicle_unit',
                    (int) ($existingDoc['entity_id'] ?? 0),
                    (string) ($existingDoc['document_type'] ?? 'Документ'),
                    '',
                    $file,
                    'custom',
                    $storedPaths
                );
            }

            foreach ($activeRoles as $role) {
                $unitId = (int) ($updatedUnitIds[$role] ?? 0);
                if ($unitId <= 0) {
                    continue;
                }
                $customTitles = $_POST['custom_doc_type'][$role] ?? [];
                $customFiles = $normalizeRoleFiles($_FILES['custom_doc_file'] ?? [], $role);
                $rowCount = max(count($customFiles), is_array($customTitles) ? count($customTitles) : 0);
                for ($i = 0; $i < $rowCount; $i++) {
                    $file = $customFiles[$i] ?? ['name' => '', 'error' => UPLOAD_ERR_NO_FILE, 'size' => 0, 'tmp_name' => '', 'type' => ''];
                    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || ($file['name'] ?? '') === '') {
                        continue;
                    }
                    $docTypeName = trim((string) ((is_array($customTitles) ? ($customTitles[$i] ?? '') : '')));
                    if ($docTypeName === '') {
                        continue;
                    }
                    $storeUpload($localPdo, $companyIdForPath, 'vehicle_unit', $unitId, $docTypeName, '', $file, 'custom', $storedPaths);
                }
            }

            $localPdo->commit();
        } catch (\Throwable $e) {
            if ($localPdo->inTransaction()) {
                $localPdo->rollBack();
            }
            foreach ($storedPaths as $storedPath) {
                if (is_string($storedPath) && $storedPath !== '' && is_file($storedPath)) {
                    @unlink($storedPath);
                }
            }
            throw $e;
        }

        foreach (array_unique($filePathsToDelete) as $filePath) {
            if (is_string($filePath) && $filePath !== '' && is_file($filePath)) {
                @unlink($filePath);
            }
        }
        foreach (array_unique($dirPathsToClean) as $dirPath) {
            if (!is_dir($dirPath)) {
                continue;
            }
            $items = @scandir($dirPath) ?: [];
            foreach ($items as $item) {
                if ($item === '.' || $item === '..') {
                    continue;
                }
                $path = $dirPath . DIRECTORY_SEPARATOR . $item;
                if (is_file($path)) {
                    @unlink($path);
                }
            }
            @rmdir($dirPath);
        }

        $vehicleSetStmt = $localPdo->prepare(
            'SELECT vs.*, vu1.plate_number AS primary_plate, vu2.plate_number AS secondary_plate
               FROM vehicle_sets vs
               LEFT JOIN vehicle_units vu1 ON vs.primary_vehicle_unit_id = vu1.id
               LEFT JOIN vehicle_units vu2 ON vs.secondary_vehicle_unit_id = vu2.id
              WHERE vs.id = ?'
        );
        $vehicleSetStmt->execute([(int) $id]);
        $vehicleSet = $vehicleSetStmt->fetch(PDO::FETCH_ASSOC) ?: $vehicleSet;

        $currentUnitIds = [
            'primary' => (int) ($vehicleSet['primary_vehicle_unit_id'] ?? 0),
            'secondary' => (int) ($vehicleSet['secondary_vehicle_unit_id'] ?? 0),
        ];
        $unitIds = array_values(array_filter($currentUnitIds));
        $unitsByRole = ['primary' => [], 'secondary' => []];
        if (!empty($unitIds)) {
            $placeholders = implode(',', array_fill(0, count($unitIds), '?'));
            $unitsStmt = $localPdo->prepare("SELECT * FROM vehicle_units WHERE id IN ($placeholders)");
            $unitsStmt->execute($unitIds);
            foreach ($unitsStmt->fetchAll(PDO::FETCH_ASSOC) as $unit) {
                $unitId = (int) ($unit['id'] ?? 0);
                if ($unitId === $currentUnitIds['primary']) {
                    $unitsByRole['primary'] = $unit;
                } elseif ($unitId === $currentUnitIds['secondary']) {
                    $unitsByRole['secondary'] = $unit;
                }
            }
        }

        $docsByRole = ['primary' => [], 'secondary' => []];
        if (!empty($unitIds)) {
            $placeholders = implode(',', array_fill(0, count($unitIds), '?'));
            $docsStmt = $localPdo->prepare(
                "SELECT id, entity_id, document_type, original_name, mime_type, stored_name, file_size
                   FROM documents
                  WHERE entity_type = 'vehicle_unit'
                    AND entity_id IN ($placeholders)
                    AND deleted_at IS NULL
                  ORDER BY id"
            );
            $docsStmt->execute($unitIds);
            foreach ($docsStmt->fetchAll(PDO::FETCH_ASSOC) as $doc) {
                $entityId = (int) ($doc['entity_id'] ?? 0);
                if ($entityId === $currentUnitIds['primary']) {
                    $docsByRole['primary'][] = $doc;
                } elseif ($entityId === $currentUnitIds['secondary']) {
                    $docsByRole['secondary'][] = $doc;
                }
            }
        }

        $unitTitles = [
            'primary' => $rules[$vehicleSet['set_type'] ?? '']['units']['primary']['label'] ?? 'Основная единица',
            'secondary' => $rules[$vehicleSet['set_type'] ?? '']['units']['secondary']['label'] ?? 'Доп. единица',
        ];
        $canDelete = $roleCode === 'company_owner' || $roleCode === 'senior_logist' || (($roleCode === 'logist') && (int) ($vehicleSet['created_by_user_id'] ?? 0) === (int) ($_SESSION['user_id'] ?? 0));
        $canEdit = true;

        header('Content-Type: text/html; charset=utf-8');
        require base_path('app/View/partials/company_vehicle_set_modal_view.php');
        exit;
    } catch (\Throwable $e) {
        http_response_code(500);
        echo '<div class="notice warn">Ошибка сохранения: ' . e($e->getMessage()) . '</div>';
        exit;
    }
});

// --- Vehicle Set Modal Delete ---

$router->post('/company/vehicle-sets/{id}/modal-delete', function ($id) use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);

    header('Content-Type: application/json; charset=utf-8');

    $companyId = (int) (getSessionCompanyId() ?? 0);
    if ($companyId <= 0) {
        echo json_encode(['success' => false, 'error' => 'Компания не найдена.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company || ($company['status'] ?? '') !== 'active') {
            echo json_encode(['success' => false, 'error' => 'Компания не найдена или неактивна.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }

        $localDbConfig = $config['database'];
        $localDbConfig['database'] = $company['db_identifier'];
        $localDb = new \App\Core\Database($localDbConfig);
        $localPdo = $localDb->connection();
        applyLocalMigrations($localPdo);

        $vehicleSetStmt = $localPdo->prepare('SELECT * FROM vehicle_sets WHERE id = ?');
        $vehicleSetStmt->execute([(int) $id]);
        $vehicleSet = $vehicleSetStmt->fetch(PDO::FETCH_ASSOC) ?: null;

        if (!$vehicleSet) {
            echo json_encode(['success' => false, 'error' => 'Транспорт не найден.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }

        $roleCode = $_SESSION['role_code'] ?? '';
        if ($roleCode === 'logist' && (int) ($vehicleSet['created_by_user_id'] ?? 0) !== (int) ($_SESSION['user_id'] ?? 0)) {
            echo json_encode(['success' => false, 'error' => 'У вас нет права удалять эту запись.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }

        try {
            $localPdo->query("SELECT 1 FROM crews LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $localPdo->exec(file_get_contents(base_path('database/migrations-local/006_create_company_crews.sql')));
        }

        try {
            $localPdo->query("SELECT 1 FROM driver_vehicle_blocks LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $localPdo->exec(file_get_contents(base_path('database/migrations-local/018_create_driver_vehicle_blocks.sql')));
        }

        $crewCheck = $localPdo->prepare(
            'SELECT COUNT(*)
               FROM crews c
               JOIN driver_vehicle_blocks dvb ON dvb.id = c.driver_vehicle_block_id
              WHERE dvb.vehicle_set_id = ?'
        );
        $crewCheck->execute([(int) $id]);
        if ((int) $crewCheck->fetchColumn() > 0) {
            echo json_encode(['success' => false, 'error' => 'Транспорт участвует в экипажах. Сначала удалите экипажи.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }

        $setId = (int) $id;
        $unitIds = array_values(array_filter([
            (int) ($vehicleSet['primary_vehicle_unit_id'] ?? 0),
            (int) ($vehicleSet['secondary_vehicle_unit_id'] ?? 0),
        ]));

        $filePathsToDelete = [];
        $dirPathsToDelete = [];

        foreach ($unitIds as $unitId) {
            $docsStmt = $localPdo->prepare("SELECT relative_path FROM documents WHERE entity_type = 'vehicle_unit' AND entity_id = ?");
            $docsStmt->execute([$unitId]);
            foreach ($docsStmt->fetchAll(PDO::FETCH_ASSOC) as $doc) {
                $relative = trim((string) ($doc['relative_path'] ?? ''));
                if ($relative !== '') {
                    $absolute = storage_path($relative);
                    if (is_file($absolute)) {
                        $filePathsToDelete[] = $absolute;
                    }
                }
            }
            $dirPathsToDelete[] = storage_path('companies/' . $companyId . '/documents/vehicle_unit/' . $unitId);
        }

        $blockIdsStmt = $localPdo->prepare("SELECT id FROM driver_vehicle_blocks WHERE vehicle_set_id = ?");
        $blockIdsStmt->execute([$setId]);
        $blockIds = array_map('intval', $blockIdsStmt->fetchAll(PDO::FETCH_COLUMN));

        $localPdo->beginTransaction();
        $localPdo->prepare("DELETE FROM entity_access_grants WHERE entity_type = 'vehicle_set' AND entity_id = ?")->execute([$setId]);
        if (!empty($unitIds)) {
            $placeholders = implode(',', array_fill(0, count($unitIds), '?'));
            $localPdo->prepare("DELETE FROM entity_access_grants WHERE entity_type = 'vehicle_unit' AND entity_id IN ($placeholders)")->execute($unitIds);
            $localPdo->prepare("DELETE FROM documents WHERE entity_type = 'vehicle_unit' AND entity_id IN ($placeholders)")->execute($unitIds);
            $localPdo->prepare("DELETE FROM vehicle_units WHERE id IN ($placeholders)")->execute($unitIds);
        }
        if (!empty($blockIds)) {
            $placeholders = implode(',', array_fill(0, count($blockIds), '?'));
            $localPdo->prepare("DELETE FROM entity_access_grants WHERE entity_type = 'driver_vehicle_block' AND entity_id IN ($placeholders)")->execute($blockIds);
            $localPdo->prepare("DELETE FROM driver_vehicle_blocks WHERE id IN ($placeholders)")->execute($blockIds);
        }
        $localPdo->prepare("DELETE FROM vehicle_sets WHERE id = ?")->execute([$setId]);
        $localPdo->commit();

        foreach (array_unique($filePathsToDelete) as $filePath) {
            if (is_string($filePath) && $filePath !== '' && is_file($filePath)) {
                @unlink($filePath);
            }
        }
        foreach (array_unique($dirPathsToDelete) as $dirPath) {
            if (!is_dir($dirPath)) {
                continue;
            }
            $items = @scandir($dirPath) ?: [];
            foreach ($items as $item) {
                if ($item === '.' || $item === '..') {
                    continue;
                }
                $path = $dirPath . DIRECTORY_SEPARATOR . $item;
                if (is_file($path)) {
                    @unlink($path);
                }
            }
            @rmdir($dirPath);
        }

        echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    } catch (\Throwable $e) {
        echo json_encode(['success' => false, 'error' => 'Ошибка: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
});

$router->get('/company/vehicle-sets/{id}', function ($id) use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);
    $pageTitle = 'Транспорт';
    $pageContext = 'Транспорт › Компания';

    $companyId = (int)(getSessionCompanyId() ?? 0);
    $grants = []; $logists = [];

    if ($companyId <= 0) {
        $company = null; $vehicleSet = null; $dbError = null;
        ob_start(); require base_path('app/View/pages/company_vehicle_set_view.php');
        $content = ob_get_clean(); require base_path('app/View/layouts/main.php');
        return;
    }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]); $company = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$company) { $company = null; $vehicleSet = null; $dbError = null; goto renderViewVS; }
        $pageContext = 'Транспорт › Компания: ' . $company['name'];
        if ($company['status'] !== 'active') { $vehicleSet = null; $dbError = null; goto renderViewVS; }

        $dbIdentifier = $company['db_identifier'];
        $localDbConfig = $config['database']; $localDbConfig['database'] = $dbIdentifier;
        $localDb = new \App\Core\Database($localDbConfig); $localPdo = $localDb->connection();
        applyLocalMigrations($localPdo);

        try { $localPdo->query("SELECT 1 FROM vehicle_sets LIMIT 1")->fetch(); }
        catch (\Exception $e) { $localPdo->exec(file_get_contents(base_path('database/migrations-local/017_create_vehicle_sets.sql'))); }

        $stmt = $localPdo->prepare(
            "SELECT vs.*, vu1.plate_number AS primary_plate, vu1.brand AS primary_brand, vu1.model AS primary_model, vu1.vin AS primary_vin,
             vu2.plate_number AS secondary_plate, vu2.brand AS secondary_brand, vu2.model AS secondary_model, vu2.vin AS secondary_vin
             FROM vehicle_sets vs
             LEFT JOIN vehicle_units vu1 ON vs.primary_vehicle_unit_id = vu1.id
             LEFT JOIN vehicle_units vu2 ON vs.secondary_vehicle_unit_id = vu2.id
             WHERE vs.id = ?"
        );
        $stmt->execute([(int)$id]); $vehicleSet = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        $accessDenied = null;
        if ($vehicleSet) {
            $isLogist = ($_SESSION['role_code'] ?? '') === 'logist';
            if ($isLogist) {
                $userId = (int)$_SESSION['user_id'];
                $hasGrant = false;
                $gc = $localPdo->prepare("SELECT access_level FROM entity_access_grants WHERE entity_type = 'vehicle_set' AND entity_id = ? AND granted_to_user_id = ? AND revoked_at IS NULL LIMIT 1");
                $gc->execute([(int)$id, $userId]);
                $gr = $gc->fetch(PDO::FETCH_ASSOC);
                $hasGrant = ($gr && in_array($gr['access_level'], ['view', 'edit']));
                if ((int)$vehicleSet['created_by_user_id'] !== $userId && !$hasGrant) {
                    $accessDenied = 'У вас нет доступа к этой записи.';
                }
            }
        }

        if ($vehicleSet && !$accessDenied) { $pageTitle = 'Транспорт #' . $vehicleSet['id']; }

        if (($_SESSION['role_code'] ?? '') === 'company_owner') {
            $grantsStmt = $localPdo->prepare("SELECT g.*, u.full_name AS logist_name FROM entity_access_grants g LEFT JOIN users u ON g.granted_to_user_id = u.id WHERE g.entity_type = ? AND g.entity_id = ?");
            $grantsStmt->execute(['vehicle_set', (int)$id]);
            $grants = $grantsStmt->fetchAll(PDO::FETCH_ASSOC);
            $logists = $localPdo->query("SELECT id, full_name, login FROM users WHERE role_code IN ('logist', 'senior_logist') AND status='active' ORDER BY full_name")->fetchAll(PDO::FETCH_ASSOC);
        }

        // Load driver_vehicle_blocks for this vehicle set
        $vehicleSetBlocks = [];
        if ($vehicleSet) {
            try {
                $vsbStmt = $localPdo->prepare(
                    "SELECT dvb.id AS block_id, dvb.status AS block_status,
                     d.full_name AS driver_name, d.id AS driver_id
                     FROM driver_vehicle_blocks dvb
                     JOIN drivers d ON dvb.driver_id = d.id
                     WHERE dvb.vehicle_set_id = ? AND dvb.status != 'archived'
                     ORDER BY dvb.id DESC"
                );
                $vsbStmt->execute([(int)$id]);
                $vehicleSetBlocks = $vsbStmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (\Exception $e) {
                $vehicleSetBlocks = [];
            }
        }

        $dbError = null;
    } catch (\Exception $e) {
        $company = $company ?? null; $vehicleSet = null; $grants = []; $logists = []; $vehicleSetBlocks = [];
        $dbError = 'Не удалось загрузить: ' . $e->getMessage();
    }

    renderViewVS:
    ob_start(); require base_path('app/View/pages/company_vehicle_set_view.php');
    $content = ob_get_clean(); require base_path('app/View/layouts/main.php');
});

$router->get('/company/vehicle-sets/{id}/edit', function ($id) use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);
    $pageTitle = 'Редактировать транспорт';
    $pageContext = 'Транспорт › Компания';

    $companyId = (int)(getSessionCompanyId() ?? 0);
    $entityNotFound = false; $success = false; $vehicleUnits = [];

    if ($companyId <= 0) {
        $company = null; $vehicleSet = null; $errors = []; $old = []; $formError = null;
        ob_start(); require base_path('app/View/pages/company_vehicle_set_edit.php');
        $content = ob_get_clean(); require base_path('app/View/layouts/main.php');
        return;
    }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]); $company = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$company) { $company = null; $vehicleSet = null; $errors = []; $old = []; $formError = null; goto renderEditVS; }
        $pageContext = 'Транспорт › Компания: ' . $company['name'];
        if ($company['status'] !== 'active') { $vehicleSet = null; $errors = []; $old = []; $formError = null; goto renderEditVS; }

        $dbIdentifier = $company['db_identifier'];
        $localDbConfig = $config['database']; $localDbConfig['database'] = $dbIdentifier;
        $localDb = new \App\Core\Database($localDbConfig); $localPdo = $localDb->connection();
        applyLocalMigrations($localPdo);

        $stmt = $localPdo->prepare('SELECT * FROM vehicle_sets WHERE id = ?');
        $stmt->execute([(int)$id]); $vehicleSet = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        if (!$vehicleSet) { $entityNotFound = true; $errors = []; $old = []; $formError = null; goto renderEditVS; }

        $vehicleUnits = $localPdo->query("SELECT * FROM vehicle_units WHERE status = 'active' ORDER BY plate_number")->fetchAll(PDO::FETCH_ASSOC);
        $errors = []; $old = $vehicleSet; $formError = null;
    } catch (\Exception $e) {
        $company = $company ?? null; $vehicleSet = null; $errors = []; $old = []; $formError = 'Ошибка: ' . $e->getMessage();
    }

    renderEditVS:
    ob_start(); require base_path('app/View/pages/company_vehicle_set_edit.php');
    $content = ob_get_clean(); require base_path('app/View/layouts/main.php');
});

$router->post('/company/vehicle-sets/{id}/edit', function ($id) use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);
    $pageTitle = 'Редактировать транспорт';
    $pageContext = 'Транспорт › Компания';

    $companyId = (int)(getSessionCompanyId() ?? 0);
    $entityNotFound = false; $success = false; $vehicleUnits = [];

    if ($companyId <= 0) {
        $company = null; $vehicleSet = null; $errors = []; $old = $_POST; $formError = 'Компания не найдена';
        ob_start(); require base_path('app/View/pages/company_vehicle_set_edit.php');
        $content = ob_get_clean(); require base_path('app/View/layouts/main.php');
        return;
    }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]); $company = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$company) { $company = null; $vehicleSet = null; $errors = []; $old = $_POST; $formError = 'Компания не найдена'; goto renderEditVSPost; }
        if ($company['status'] !== 'active') { $vehicleSet = null; $errors = []; $old = $_POST; $formError = 'Редактирование недоступно'; goto renderEditVSPost; }

        $dbIdentifier = $company['db_identifier'];
        $localDbConfig = $config['database']; $localDbConfig['database'] = $dbIdentifier;
        $localDb = new \App\Core\Database($localDbConfig); $localPdo = $localDb->connection();
        applyLocalMigrations($localPdo);

        $stmt = $localPdo->prepare('SELECT * FROM vehicle_sets WHERE id = ?');
        $stmt->execute([(int)$id]); $vehicleSet = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if (!$vehicleSet) { $entityNotFound = true; $errors = []; $old = $_POST; $formError = null; goto renderEditVSPost; }

        $vehicleUnits = $localPdo->query("SELECT * FROM vehicle_units WHERE status = 'active' ORDER BY plate_number")->fetchAll(PDO::FETCH_ASSOC);
        $errors = []; $old = $_POST; $formError = null;

        $setType = trim($_POST['set_type'] ?? '');
        $primaryId = trim($_POST['primary_vehicle_unit_id'] ?? '');
        $secondaryId = trim($_POST['secondary_vehicle_unit_id'] ?? '');

        if ($primaryId === '') { $errors['primary_vehicle_unit_id'] = 'Обязательное поле'; }
        if (($setType === 'coupling' || $setType === 'road_train') && $secondaryId === '') {
            $errors['secondary_vehicle_unit_id'] = 'Обязательно для сцепки и автопоезда';
        }
        if ($setType === 'single') { $secondaryId = ''; }

        if (!empty($errors)) { goto renderEditVSPost; }

        $update = $localPdo->prepare(
            'UPDATE vehicle_sets SET set_type = :set_type, primary_vehicle_unit_id = :primary_id, secondary_vehicle_unit_id = :secondary_id, status = :status, comments = :comments, updated_by_user_id = :uid, updated_by_role = :role WHERE id = :id'
        );
        $update->execute([
            ':set_type' => $setType !== '' ? $setType : null,
            ':primary_id' => (int)$primaryId,
            ':secondary_id' => $secondaryId !== '' ? (int)$secondaryId : null,
            ':status' => $_POST['status'] ?? $vehicleSet['status'],
            ':comments' => $_POST['comments'] ?? null,
            ':uid' => (int)$_SESSION['user_id'],
            ':role' => $_SESSION['role_code'],
            ':id' => (int)$id,
        ]);

        $vehicleSet = $localPdo->prepare('SELECT * FROM vehicle_sets WHERE id = ?');
        $vehicleSet->execute([(int)$id]); $vehicleSet = $vehicleSet->fetch(PDO::FETCH_ASSOC);
        $success = true;
    } catch (\Exception $e) {
        $company = $company ?? null; $vehicleSet = $vehicleSet ?? null;
        $errors = []; $old = $_POST; $formError = 'Ошибка: ' . $e->getMessage();
    }

    renderEditVSPost:
    ob_start(); require base_path('app/View/pages/company_vehicle_set_edit.php');
    $content = ob_get_clean(); require base_path('app/View/layouts/main.php');
});

$router->post('/company/vehicle-sets/{id}/archive', function ($id) use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);
    $companyId = (int)(getSessionCompanyId() ?? 0);
    if ($companyId <= 0) { header('Location: /company/vehicle-sets'); exit; }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]); $company = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$company || $company['status'] !== 'active') { header('Location: /company/vehicle-sets'); exit; }

        $dbIdentifier = $company['db_identifier'];
        $localDbConfig = $config['database']; $localDbConfig['database'] = $dbIdentifier;
        $localDb = new \App\Core\Database($localDbConfig); $localPdo = $localDb->connection();
        applyLocalMigrations($localPdo);

        try { $localPdo->query("SELECT 1 FROM driver_vehicle_blocks LIMIT 1")->fetch(); }
        catch (\Exception $e) { $localPdo->exec(file_get_contents(base_path('database/migrations-local/018_create_driver_vehicle_blocks.sql'))); }

        $blockCheck = $localPdo->prepare('SELECT COUNT(*) FROM driver_vehicle_blocks WHERE vehicle_set_id = ? AND status = ?');
        $blockCheck->execute([(int)$id, 'active']);
        if ($blockCheck->fetchColumn() > 0) {
            $_SESSION['flash'] = 'Транспорт используется в активных связках «Водители+ТС». Сначала архивируйте связки.';
            header('Location: /company/vehicle-sets/' . $id); exit;
        }

        $update = $localPdo->prepare("UPDATE vehicle_sets SET status = 'archived' WHERE id = ?");
        $update->execute([(int)$id]);
        header('Location: /company/vehicle-sets'); exit;
    } catch (\Exception $e) {
        header('Location: /company/vehicle-sets'); exit;
    }
});

// ---- DRIVER VEHICLE BLOCKS (Водители+ТС) ----

$router->get('/company/driver-vehicle-blocks', function () use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);
    header('Location: /company/route-executors', true, 302);
    exit;

    requireRole(['company_owner', 'senior_logist', 'logist']);
    $pageTitle = 'Водители+ТС';
    $pageContext = 'Водители+ТС › Компания';

    $companyId = (int)(getSessionCompanyId() ?? 0);
    if ($companyId <= 0) {
        $company = null; $blocks = []; $dbError = null;
        ob_start(); require base_path('app/View/pages/company_driver_vehicle_blocks.php');
        $content = ob_get_clean(); require base_path('app/View/layouts/main.php');
        return;
    }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]); $company = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$company) { $company = null; $blocks = []; $dbError = null; goto renderBlocks; }
        $pageContext = 'Водители+ТС › Компания: ' . $company['name'];
        $topbarCrumbs = [
            ['label' => mb_strtoupper($company['name']), 'url' => '/company/dashboard'],
            ['label' => 'Подрядчики', 'url' => null],
            ['label' => 'Водители и транспортное средство', 'url' => null],
        ];
        if ($company['status'] !== 'active') { $blocks = []; $dbError = null; goto renderBlocks; }

        $dbIdentifier = $company['db_identifier'];
        $localDbConfig = $config['database']; $localDbConfig['database'] = $dbIdentifier;
        $localDb = new \App\Core\Database($localDbConfig); $localPdo = $localDb->connection();
        applyLocalMigrations($localPdo);

        try { $localPdo->query("SELECT 1 FROM driver_vehicle_blocks LIMIT 1")->fetch(); }
        catch (\Exception $e) { $localPdo->exec(file_get_contents(base_path('database/migrations-local/018_create_driver_vehicle_blocks.sql'))); }

        $isLogist = ($_SESSION['role_code'] ?? '') === 'logist';
        if ($isLogist) {
            $userId = (int)$_SESSION['user_id'];
            $stmt = $localPdo->prepare(
                "SELECT dvb.*, d.full_name AS driver_name, d.phone AS driver_phone, vs.set_type,
                 CONCAT(vu1.plate_number, IFNULL(CONCAT(' + ', vu2.plate_number), '')) AS plates
                 FROM driver_vehicle_blocks dvb
                 JOIN drivers d ON dvb.driver_id = d.id
                 JOIN vehicle_sets vs ON dvb.vehicle_set_id = vs.id
                 LEFT JOIN vehicle_units vu1 ON vs.primary_vehicle_unit_id = vu1.id
                 LEFT JOIN vehicle_units vu2 ON vs.secondary_vehicle_unit_id = vu2.id
                 WHERE (dvb.created_by_user_id = ? OR dvb.id IN (SELECT entity_id FROM entity_access_grants WHERE entity_type = 'driver_vehicle_block' AND granted_to_user_id = ? AND access_level = 'view'))
                 ORDER BY dvb.created_at DESC"
            );
            $stmt->execute([$userId, $userId]);
            $blocks = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $stmt = $localPdo->query(
                "SELECT dvb.*, d.full_name AS driver_name, d.phone AS driver_phone, vs.set_type,
                 CONCAT(vu1.plate_number, IFNULL(CONCAT(' + ', vu2.plate_number), '')) AS plates
                 FROM driver_vehicle_blocks dvb
                 JOIN drivers d ON dvb.driver_id = d.id
                 JOIN vehicle_sets vs ON dvb.vehicle_set_id = vs.id
                 LEFT JOIN vehicle_units vu1 ON vs.primary_vehicle_unit_id = vu1.id
                 LEFT JOIN vehicle_units vu2 ON vs.secondary_vehicle_unit_id = vu2.id
                 ORDER BY dvb.created_at DESC"
            );
            $blocks = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        $dbError = null;
    } catch (\Exception $e) {
        $company = $company ?? null; $blocks = [];
        $dbError = 'Не удалось подключиться к базе данных компании.';
    }

    renderBlocks:
    ob_start(); require base_path('app/View/pages/company_driver_vehicle_blocks.php');
    $content = ob_get_clean(); require base_path('app/View/layouts/main.php');
});

$router->get('/company/driver-vehicle-blocks/create', function () use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);
    header('Location: /company/route-executors/create', true, 302);
    exit;

    requireRole(['company_owner', 'senior_logist', 'logist']);
    $pageTitle = 'Создать связку';
    $pageContext = 'Водители+ТС › Компания';

    $companyId = (int)(getSessionCompanyId() ?? 0);
    if ($companyId <= 0) {
        $company = null; $success = false; $errors = []; $old = []; $formError = null; $createdBlock = null; $drivers = []; $vehicleSets = [];
        ob_start(); require base_path('app/View/pages/company_driver_vehicle_blocks_create.php');
        $content = ob_get_clean(); require base_path('app/View/layouts/main.php');
        return;
    }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]); $company = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$company) { $company = null; $success = false; $errors = []; $old = []; $formError = null; $createdBlock = null; $drivers = []; $vehicleSets = []; goto renderCreateBlock; }
        $pageContext = 'Водители+ТС › Компания: ' . $company['name'];

        $dbIdentifier = $company['db_identifier'];
        $localDbConfig = $config['database']; $localDbConfig['database'] = $dbIdentifier;
        $localDb = new \App\Core\Database($localDbConfig); $localPdo = $localDb->connection();
        applyLocalMigrations($localPdo);

        $isLogist = ($_SESSION['role_code'] ?? '') === 'logist';
        $userId = (int)$_SESSION['user_id'];

        if ($isLogist) {
            $dStmt = $localPdo->prepare("SELECT * FROM drivers WHERE status = 'active' AND (created_by_user_id = ? OR id IN (SELECT entity_id FROM entity_access_grants WHERE entity_type = 'driver' AND granted_to_user_id = ? AND access_level IN ('view','edit') AND revoked_at IS NULL)) ORDER BY full_name");
            $dStmt->execute([$userId, $userId]);
            $drivers = $dStmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $drivers = $localPdo->query("SELECT * FROM drivers WHERE status = 'active' ORDER BY full_name")->fetchAll(PDO::FETCH_ASSOC);
        }

        if ($isLogist) {
            $vsStmt = $localPdo->prepare("SELECT vs.*, vu1.plate_number AS primary_plate, vu2.plate_number AS secondary_plate FROM vehicle_sets vs LEFT JOIN vehicle_units vu1 ON vs.primary_vehicle_unit_id = vu1.id LEFT JOIN vehicle_units vu2 ON vs.secondary_vehicle_unit_id = vu2.id WHERE vs.status = 'active' AND (vs.created_by_user_id = ? OR vs.id IN (SELECT entity_id FROM entity_access_grants WHERE entity_type = 'vehicle_set' AND granted_to_user_id = ? AND access_level IN ('view','edit') AND revoked_at IS NULL)) ORDER BY vs.id");
            $vsStmt->execute([$userId, $userId]);
            $vehicleSets = $vsStmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $vehicleSets = $localPdo->query("SELECT vs.*, vu1.plate_number AS primary_plate, vu2.plate_number AS secondary_plate FROM vehicle_sets vs LEFT JOIN vehicle_units vu1 ON vs.primary_vehicle_unit_id = vu1.id LEFT JOIN vehicle_units vu2 ON vs.secondary_vehicle_unit_id = vu2.id WHERE vs.status = 'active' ORDER BY vs.id")->fetchAll(PDO::FETCH_ASSOC);
        }

        $success = false; $errors = []; $old = []; $formError = null; $createdBlock = null;
    } catch (\Exception $e) {
        $company = null; $success = false; $errors = []; $old = []; $formError = 'Ошибка: ' . $e->getMessage(); $createdBlock = null; $drivers = []; $vehicleSets = [];
    }

    renderCreateBlock:
    ob_start(); require base_path('app/View/pages/company_driver_vehicle_blocks_create.php');
    $content = ob_get_clean(); require base_path('app/View/layouts/main.php');
});

$router->post('/company/driver-vehicle-blocks/create', function () use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);
    $pageTitle = 'Создать связку';
    $pageContext = 'Водители+ТС › Компания';

    $companyId = (int)(getSessionCompanyId() ?? 0);
    $errors = []; $old = $_POST; $formError = null; $success = false; $createdBlock = null; $drivers = []; $vehicleSets = [];

    if ($companyId <= 0) { $company = null; $formError = 'Компания не найдена'; goto renderPostBlock; }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]); $company = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$company) { $company = null; $formError = 'Компания не найдена'; goto renderPostBlock; }
        if ($company['status'] !== 'active') { $formError = 'Создание недоступно'; goto renderPostBlock; }

        $dbIdentifier = $company['db_identifier'];
        $localDbConfig = $config['database']; $localDbConfig['database'] = $dbIdentifier;
        $localDb = new \App\Core\Database($localDbConfig); $localPdo = $localDb->connection();
        applyLocalMigrations($localPdo);

        $isLogist = ($_SESSION['role_code'] ?? '') === 'logist';
        $userId = (int)$_SESSION['user_id'];

        if ($isLogist) {
            $dStmt = $localPdo->prepare("SELECT * FROM drivers WHERE status = 'active' AND (created_by_user_id = ? OR id IN (SELECT entity_id FROM entity_access_grants WHERE entity_type = 'driver' AND granted_to_user_id = ? AND access_level IN ('view','edit') AND revoked_at IS NULL)) ORDER BY full_name");
            $dStmt->execute([$userId, $userId]);
            $drivers = $dStmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $drivers = $localPdo->query("SELECT * FROM drivers WHERE status = 'active' ORDER BY full_name")->fetchAll(PDO::FETCH_ASSOC);
        }

        if ($isLogist) {
            $vsStmt = $localPdo->prepare("SELECT vs.*, vu1.plate_number AS primary_plate, vu2.plate_number AS secondary_plate FROM vehicle_sets vs LEFT JOIN vehicle_units vu1 ON vs.primary_vehicle_unit_id = vu1.id LEFT JOIN vehicle_units vu2 ON vs.secondary_vehicle_unit_id = vu2.id WHERE vs.status = 'active' AND (vs.created_by_user_id = ? OR vs.id IN (SELECT entity_id FROM entity_access_grants WHERE entity_type = 'vehicle_set' AND granted_to_user_id = ? AND access_level IN ('view','edit') AND revoked_at IS NULL)) ORDER BY vs.id");
            $vsStmt->execute([$userId, $userId]);
            $vehicleSets = $vsStmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $vehicleSets = $localPdo->query("SELECT vs.*, vu1.plate_number AS primary_plate, vu2.plate_number AS secondary_plate FROM vehicle_sets vs LEFT JOIN vehicle_units vu1 ON vs.primary_vehicle_unit_id = vu1.id LEFT JOIN vehicle_units vu2 ON vs.secondary_vehicle_unit_id = vu2.id WHERE vs.status = 'active' ORDER BY vs.id")->fetchAll(PDO::FETCH_ASSOC);
        }

        $driverId = trim($_POST['driver_id'] ?? '');
        $vehicleSetId = trim($_POST['vehicle_set_id'] ?? '');
        $status = trim($_POST['status'] ?? 'active');
        $comments = trim($_POST['comments'] ?? '');

        if ($driverId === '') { $errors['driver_id'] = 'Обязательное поле'; }
        if ($vehicleSetId === '') { $errors['vehicle_set_id'] = 'Обязательное поле'; }

        // Backend validation: logist can only use their own drivers/vehicle_sets
        if ($driverId !== '' && $isLogist) {
            $dCheck = $localPdo->prepare("SELECT created_by_user_id FROM drivers WHERE id = ?");
            $dCheck->execute([(int)$driverId]);
            $dOwner = $dCheck->fetchColumn();
            if ($dOwner === false) {
                $errors['driver_id'] = 'Водитель не найден.';
            } elseif ((int)$dOwner !== $userId) {
                $dGrant = $localPdo->prepare("SELECT COUNT(*) FROM entity_access_grants WHERE entity_type = 'driver' AND entity_id = ? AND granted_to_user_id = ? AND access_level IN ('view','edit') AND revoked_at IS NULL");
                $dGrant->execute([(int)$driverId, $userId]);
                if ($dGrant->fetchColumn() == 0) {
                    $errors['driver_id'] = 'Водитель недоступен.';
                }
            }
        }

        if ($vehicleSetId !== '' && $isLogist) {
            $vsCheck = $localPdo->prepare("SELECT created_by_user_id FROM vehicle_sets WHERE id = ?");
            $vsCheck->execute([(int)$vehicleSetId]);
            $vsOwner = $vsCheck->fetchColumn();
            if ($vsOwner === false) {
                $errors['vehicle_set_id'] = 'Транспорт не найден.';
            } elseif ((int)$vsOwner !== $userId) {
                $vsGrant = $localPdo->prepare("SELECT COUNT(*) FROM entity_access_grants WHERE entity_type = 'vehicle_set' AND entity_id = ? AND granted_to_user_id = ? AND access_level IN ('view','edit') AND revoked_at IS NULL");
                $vsGrant->execute([(int)$vehicleSetId, $userId]);
                if ($vsGrant->fetchColumn() == 0) {
                    $errors['vehicle_set_id'] = 'Транспорт недоступен.';
                }
            }
        }

        if ($driverId !== '' && $vehicleSetId !== '') {
            $dupStmt = $localPdo->prepare('SELECT COUNT(*) FROM driver_vehicle_blocks WHERE driver_id = ? AND vehicle_set_id = ?');
            $dupStmt->execute([(int)$driverId, (int)$vehicleSetId]);
            if ($dupStmt->fetchColumn() > 0) {
                $errors['driver_id'] = 'Такая связка уже существует.';
            }
        }

        if (!empty($errors)) { goto renderPostBlock; }

        $insert = $localPdo->prepare(
            'INSERT INTO driver_vehicle_blocks (driver_id, vehicle_set_id, status, comments, created_by_user_id, created_by_role)
             VALUES (:driver_id, :vehicle_set_id, :status, :comments, :uid, :role)'
        );
        $insert->execute([
            ':driver_id' => (int)$driverId,
            ':vehicle_set_id' => (int)$vehicleSetId,
            ':status' => $status,
            ':comments' => $comments !== '' ? $comments : null,
            ':uid' => (int)$_SESSION['user_id'],
            ':role' => $_SESSION['role_code'],
        ]);

        $newId = (int)$localPdo->lastInsertId();
        $dName = $localPdo->prepare("SELECT full_name FROM drivers WHERE id = ?");
        $dName->execute([(int)$driverId]);
        $createdBlock = [
            'id' => $newId,
            'driver_name' => $dName->fetchColumn() ?: '',
            'set_type' => $vehicleSets[array_search((int)$vehicleSetId, array_column($vehicleSets, 'id'))]['set_type'] ?? '',
        ];
        $success = true;
        ob_start(); require base_path('app/View/pages/company_driver_vehicle_blocks_create.php');
        $content = ob_get_clean(); require base_path('app/View/layouts/main.php');
        return;
    } catch (\Exception $e) {
        $company = $company ?? null; $formError = 'Ошибка: ' . $e->getMessage();
    }

    renderPostBlock:
    ob_start(); require base_path('app/View/pages/company_driver_vehicle_blocks_create.php');
    $content = ob_get_clean(); require base_path('app/View/layouts/main.php');
});

$router->get('/company/driver-vehicle-blocks/{id}', function ($id) use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);
    $pageTitle = 'Водители+ТС';
    $pageContext = 'Водители+ТС › Компания';

    $companyId = (int)(getSessionCompanyId() ?? 0);
    $grants = []; $logists = []; $linkedContractor = null;

    if ($companyId <= 0) { $company = null; $block = null; $dbError = null; goto renderViewBlock; }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]); $company = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$company) { $company = null; $block = null; $dbError = null; goto renderViewBlock; }
        if ($company['status'] !== 'active') { $block = null; $dbError = null; goto renderViewBlock; }

        $dbIdentifier = $company['db_identifier'];
        $localDbConfig = $config['database']; $localDbConfig['database'] = $dbIdentifier;
        $localDb = new \App\Core\Database($localDbConfig); $localPdo = $localDb->connection();
        applyLocalMigrations($localPdo);

        $stmt = $localPdo->prepare(
            "SELECT dvb.*, d.full_name AS driver_name, d.phone AS driver_phone, vs.set_type,
             vu1.plate_number AS primary_plate, vu1.brand AS primary_brand, vu1.model AS primary_model,
             vu2.plate_number AS secondary_plate, vu2.brand AS secondary_brand, vu2.model AS secondary_model
             FROM driver_vehicle_blocks dvb
             JOIN drivers d ON dvb.driver_id = d.id
             JOIN vehicle_sets vs ON dvb.vehicle_set_id = vs.id
             LEFT JOIN vehicle_units vu1 ON vs.primary_vehicle_unit_id = vu1.id
             LEFT JOIN vehicle_units vu2 ON vs.secondary_vehicle_unit_id = vu2.id
             WHERE dvb.id = ?"
        );
        $stmt->execute([(int)$id]); $block = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        $accessDenied = null;
        if ($block) {
            $isLogist = ($_SESSION['role_code'] ?? '') === 'logist';
            if ($isLogist) {
                $userId = (int)$_SESSION['user_id'];
                $hasGrant = false;
                $gc = $localPdo->prepare("SELECT access_level FROM entity_access_grants WHERE entity_type = 'driver_vehicle_block' AND entity_id = ? AND granted_to_user_id = ? AND revoked_at IS NULL LIMIT 1");
                $gc->execute([(int)$id, $userId]);
                $gr = $gc->fetch(PDO::FETCH_ASSOC);
                $hasGrant = ($gr && in_array($gr['access_level'], ['view', 'edit']));
                if ((int)$block['created_by_user_id'] !== $userId && !$hasGrant) {
                    $accessDenied = 'У вас нет доступа к этой записи.';
                }
            }
        }

        // Загрузка привязанного перевозчика через crews
        $linkedContractor = null;
        if ($block) {
            try {
                $crewStmt = $localPdo->prepare("SELECT c.contractor_id FROM crews c WHERE c.driver_vehicle_block_id = ? AND c.status != 'archived' LIMIT 1");
                $crewStmt->execute([(int)$id]);
                $crewRow = $crewStmt->fetch(PDO::FETCH_ASSOC);
                if ($crewRow && !empty($crewRow['contractor_id'])) {
                    $cStmt = $localPdo->prepare('SELECT id, name FROM contractors WHERE id = ?');
                    $cStmt->execute([(int)$crewRow['contractor_id']]);
                    $contractor = $cStmt->fetch(PDO::FETCH_ASSOC);
                    if ($contractor) {
                        $linkedContractor = ['id' => $contractor['id'], 'name' => $contractor['name']];
                    }
                }
            } catch (\Exception $e) {
                // Игнорируем ошибку загрузки перевозчика
            }
        }

        if ($block && !$accessDenied) { $pageTitle = 'Связка #' . $block['id']; }

        if (($_SESSION['role_code'] ?? '') === 'company_owner') {
            $grantsStmt = $localPdo->prepare("SELECT g.*, u.full_name AS logist_name FROM entity_access_grants g LEFT JOIN users u ON g.granted_to_user_id = u.id WHERE g.entity_type = ? AND g.entity_id = ?");
            $grantsStmt->execute(['driver_vehicle_block', (int)$id]);
            $grants = $grantsStmt->fetchAll(PDO::FETCH_ASSOC);
            $logists = $localPdo->query("SELECT id, full_name, login FROM users WHERE role_code IN ('logist', 'senior_logist') AND status='active' ORDER BY full_name")->fetchAll(PDO::FETCH_ASSOC);
        }
        $dbError = null;
    } catch (\Exception $e) {
        $company = $company ?? null; $block = null; $grants = []; $logists = []; $linkedContractor = null;
        $dbError = 'Не удалось загрузить: ' . $e->getMessage();
    }

    renderViewBlock:
    ob_start(); require base_path('app/View/pages/company_driver_vehicle_block_view.php');
    $content = ob_get_clean(); require base_path('app/View/layouts/main.php');
});

$router->get('/company/driver-vehicle-blocks/{id}/edit', function ($id) use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);
    $pageTitle = 'Редактировать связку';
    $pageContext = 'Водители+ТС › Компания';

    $companyId = (int)(getSessionCompanyId() ?? 0);
    $entityNotFound = false; $success = false; $drivers = []; $vehicleSets = [];
    $blockDriver = null; $blockPlate = null;

    if ($companyId <= 0) { $company = null; $block = null; $errors = []; $old = []; $formError = null; goto renderEditBlock; }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]); $company = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$company) { $company = null; $block = null; $errors = []; $old = []; $formError = null; goto renderEditBlock; }
        if ($company['status'] !== 'active') { $block = null; $errors = []; $old = []; $formError = null; goto renderEditBlock; }

        $dbIdentifier = $company['db_identifier'];
        $localDbConfig = $config['database']; $localDbConfig['database'] = $dbIdentifier;
        $localDb = new \App\Core\Database($localDbConfig); $localPdo = $localDb->connection();
        applyLocalMigrations($localPdo);

        $stmt = $localPdo->prepare('SELECT * FROM driver_vehicle_blocks WHERE id = ?');
        $stmt->execute([(int)$id]); $block = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if (!$block) { $entityNotFound = true; $errors = []; $old = []; $formError = null; goto renderEditBlock; }

        // Загрузка данных водителя и транспорта для информационного блока
        try {
            $dStmt = $localPdo->prepare('SELECT full_name, phone FROM drivers WHERE id = ?');
            $dStmt->execute([(int)$block['driver_id']]);
            $blockDriver = $dStmt->fetch(PDO::FETCH_ASSOC) ?: null;

            $vsStmt = $localPdo->prepare(
                "SELECT vu1.plate_number AS primary_plate, vu2.plate_number AS secondary_plate
                 FROM vehicle_sets vs
                 LEFT JOIN vehicle_units vu1 ON vs.primary_vehicle_unit_id = vu1.id
                 LEFT JOIN vehicle_units vu2 ON vs.secondary_vehicle_unit_id = vu2.id
                 WHERE vs.id = ?"
            );
            $vsStmt->execute([(int)$block['vehicle_set_id']]);
            $vsRow = $vsStmt->fetch(PDO::FETCH_ASSOC);
            if ($vsRow) {
                $blockPlate = $vsRow['primary_plate'] ?? '';
                if (!empty($vsRow['secondary_plate'])) {
                    $blockPlate .= ' + ' . $vsRow['secondary_plate'];
                }
            }
        } catch (\Exception $e) {
            $blockDriver = null; $blockPlate = null;
        }

        $isLogist = ($_SESSION['role_code'] ?? '') === 'logist';
        $userId = (int)$_SESSION['user_id'];

        if ($isLogist) {
            $dStmt = $localPdo->prepare("SELECT * FROM drivers WHERE status = 'active' AND (created_by_user_id = ? OR id IN (SELECT entity_id FROM entity_access_grants WHERE entity_type = 'driver' AND granted_to_user_id = ? AND access_level IN ('view','edit') AND revoked_at IS NULL)) ORDER BY full_name");
            $dStmt->execute([$userId, $userId]);
            $drivers = $dStmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $drivers = $localPdo->query("SELECT * FROM drivers WHERE status = 'active' ORDER BY full_name")->fetchAll(PDO::FETCH_ASSOC);
        }

        if ($isLogist) {
            $vsStmt = $localPdo->prepare("SELECT vs.*, vu1.plate_number AS primary_plate, vu2.plate_number AS secondary_plate FROM vehicle_sets vs LEFT JOIN vehicle_units vu1 ON vs.primary_vehicle_unit_id = vu1.id LEFT JOIN vehicle_units vu2 ON vs.secondary_vehicle_unit_id = vu2.id WHERE vs.status = 'active' AND (vs.created_by_user_id = ? OR vs.id IN (SELECT entity_id FROM entity_access_grants WHERE entity_type = 'vehicle_set' AND granted_to_user_id = ? AND access_level IN ('view','edit') AND revoked_at IS NULL)) ORDER BY vs.id");
            $vsStmt->execute([$userId, $userId]);
            $vehicleSets = $vsStmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $vehicleSets = $localPdo->query("SELECT vs.*, vu1.plate_number AS primary_plate, vu2.plate_number AS secondary_plate FROM vehicle_sets vs LEFT JOIN vehicle_units vu1 ON vs.primary_vehicle_unit_id = vu1.id LEFT JOIN vehicle_units vu2 ON vs.secondary_vehicle_unit_id = vu2.id WHERE vs.status = 'active' ORDER BY vs.id")->fetchAll(PDO::FETCH_ASSOC);
        }
        $errors = []; $old = $block; $formError = null;
    } catch (\Exception $e) {
        $company = $company ?? null; $block = null; $errors = []; $old = []; $formError = 'Ошибка: ' . $e->getMessage();
        $blockDriver = null; $blockPlate = null;
    }

    renderEditBlock:
    ob_start(); require base_path('app/View/pages/company_driver_vehicle_block_edit.php');
    $content = ob_get_clean(); require base_path('app/View/layouts/main.php');
});

$router->post('/company/driver-vehicle-blocks/{id}/edit', function ($id) use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);
    $pageTitle = 'Редактировать связку';
    $pageContext = 'Водители+ТС › Компания';

    $companyId = (int)(getSessionCompanyId() ?? 0);
    $entityNotFound = false; $success = false;
    $blockDriver = null; $blockPlate = null;

    if ($companyId <= 0) { $company = null; $block = null; $errors = []; $old = $_POST; $formError = 'Компания не найдена'; goto renderEditBlockPost; }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]); $company = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$company) { $company = null; $block = null; $errors = []; $old = $_POST; $formError = 'Компания не найдена'; goto renderEditBlockPost; }
        if ($company['status'] !== 'active') { $block = null; $errors = []; $old = $_POST; $formError = 'Редактирование недоступно'; goto renderEditBlockPost; }

        $dbIdentifier = $company['db_identifier'];
        $localDbConfig = $config['database']; $localDbConfig['database'] = $dbIdentifier;
        $localDb = new \App\Core\Database($localDbConfig); $localPdo = $localDb->connection();
        applyLocalMigrations($localPdo);

        $stmt = $localPdo->prepare('SELECT * FROM driver_vehicle_blocks WHERE id = ?');
        $stmt->execute([(int)$id]); $block = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if (!$block) { $entityNotFound = true; $errors = []; $old = $_POST; $formError = null; goto renderEditBlockPost; }

        // Загрузка данных водителя и транспорта для информационного блока
        try {
            $dStmt = $localPdo->prepare('SELECT full_name, phone FROM drivers WHERE id = ?');
            $dStmt->execute([(int)$block['driver_id']]);
            $blockDriver = $dStmt->fetch(PDO::FETCH_ASSOC) ?: null;

            $vsStmt = $localPdo->prepare(
                "SELECT vu1.plate_number AS primary_plate, vu2.plate_number AS secondary_plate
                 FROM vehicle_sets vs
                 LEFT JOIN vehicle_units vu1 ON vs.primary_vehicle_unit_id = vu1.id
                 LEFT JOIN vehicle_units vu2 ON vs.secondary_vehicle_unit_id = vu2.id
                 WHERE vs.id = ?"
            );
            $vsStmt->execute([(int)$block['vehicle_set_id']]);
            $vsRow = $vsStmt->fetch(PDO::FETCH_ASSOC);
            if ($vsRow) {
                $blockPlate = $vsRow['primary_plate'] ?? '';
                if (!empty($vsRow['secondary_plate'])) {
                    $blockPlate .= ' + ' . $vsRow['secondary_plate'];
                }
            }
        } catch (\Exception $e) {
            $blockDriver = null; $blockPlate = null;
        }

        $errors = []; $old = $_POST; $formError = null;

        // Immutable: driver_id и vehicle_set_id не принимаются из формы
        // Обновляются только status и comments

        if (!empty($errors)) { goto renderEditBlockPost; }

        $update = $localPdo->prepare(
            'UPDATE driver_vehicle_blocks SET status = :status, comments = :comments, updated_by_user_id = :uid, updated_by_role = :role WHERE id = :id'
        );
        $update->execute([
            ':status' => $_POST['status'] ?? $block['status'],
            ':comments' => $_POST['comments'] ?? null,
            ':uid' => (int)$_SESSION['user_id'],
            ':role' => $_SESSION['role_code'],
            ':id' => (int)$id,
        ]);

        $block = $localPdo->prepare('SELECT * FROM driver_vehicle_blocks WHERE id = ?');
        $block->execute([(int)$id]); $block = $block->fetch(PDO::FETCH_ASSOC);
        $success = true;
    } catch (\Exception $e) {
        $company = $company ?? null; $block = $block ?? null;
        $errors = []; $old = $_POST; $formError = 'Ошибка: ' . $e->getMessage();
        $blockDriver = null; $blockPlate = null;
    }

    renderEditBlockPost:
    ob_start(); require base_path('app/View/pages/company_driver_vehicle_block_edit.php');
    $content = ob_get_clean(); require base_path('app/View/layouts/main.php');
});

$router->post('/company/driver-vehicle-blocks/{id}/archive', function ($id) use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);
    $companyId = (int)(getSessionCompanyId() ?? 0);
    if ($companyId <= 0) { header('Location: /company/driver-vehicle-blocks'); exit; }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]); $company = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$company || $company['status'] !== 'active') { header('Location: /company/driver-vehicle-blocks'); exit; }

        $dbIdentifier = $company['db_identifier'];
        $localDbConfig = $config['database']; $localDbConfig['database'] = $dbIdentifier;
        $localDb = new \App\Core\Database($localDbConfig); $localPdo = $localDb->connection();
        applyLocalMigrations($localPdo);

        try { $localPdo->query("SELECT 1 FROM driver_vehicle_blocks LIMIT 1")->fetch(); }
        catch (\Exception $e) { $localPdo->exec(file_get_contents(base_path('database/migrations-local/018_create_driver_vehicle_blocks.sql'))); }

        try { $localPdo->query("SELECT 1 FROM crews LIMIT 1")->fetch(); }
        catch (\Exception $e) { $localPdo->exec(file_get_contents(base_path('database/migrations-local/006_create_company_crews.sql'))); }

        $crewCheck = $localPdo->prepare('SELECT COUNT(*) FROM crews WHERE driver_vehicle_block_id = ? AND status = ?');
        $crewCheck->execute([(int)$id, 'active']);
        if ($crewCheck->fetchColumn() > 0) {
            $_SESSION['flash'] = 'Связка используется в активных экипажах. Сначала архивируйте экипажи.';
            header('Location: /company/driver-vehicle-blocks/' . $id); exit;
        }

        $update = $localPdo->prepare("UPDATE driver_vehicle_blocks SET status = 'archived' WHERE id = ?");
        $update->execute([(int)$id]);
        header('Location: /company/driver-vehicle-blocks'); exit;
    } catch (\Exception $e) {
        header('Location: /company/driver-vehicle-blocks'); exit;
    }
});

$router->get('/company/documents', function () use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);

    $companyId = (int)(getSessionCompanyId() ?? 0);
    $entityType = $_GET['entity_type'] ?? '';
    $entityId = (int)($_GET['entity_id'] ?? 0);
    $missingEntityContext = ($entityType === '' && $entityId <= 0);

    $whitelist = ['client' => ['label' => 'Клиент', 'labelDative' => 'клиентам', 'table' => 'clients', 'backRoute' => '/company/clients'],
                    'contractor' => ['label' => 'Перевозчик', 'labelDative' => 'перевозчикам', 'table' => 'contractors', 'backRoute' => '/company/contractors'],
                   'driver' => ['label' => 'Водитель', 'labelDative' => 'водителям', 'table' => 'drivers', 'backRoute' => '/company/drivers'],
                   'vehicle_unit' => ['label' => 'Транспортная единица', 'labelDative' => 'транспортным единицам', 'table' => 'vehicle_units', 'backRoute' => '/company/vehicles'],
                    'vehicle_set' => ['label' => 'Транспорт', 'labelDative' => 'транспорту', 'table' => 'vehicle_sets', 'backRoute' => '/company/vehicle-sets'],
                   'driver_vehicle_block' => ['label' => 'Водители+ТС', 'labelDative' => 'связкам', 'table' => 'driver_vehicle_blocks', 'backRoute' => '/company/driver-vehicle-blocks'],
                   'crew' => ['label' => 'Экипаж', 'labelDative' => 'экипажам', 'table' => 'crews', 'backRoute' => '/company/crews']];

    if (!isset($whitelist[$entityType])) {
        $entityTypeError = !$missingEntityContext;
        $company = null;
        $documents = [];
        $entityLabel = '';
        $entityLabelDative = '';
        $entityName = '';
        $backRoute = '';
        $dbError = null;
        $entityNotFound = false;

        $pageTitle = 'Документы';
        $pageContext = 'Документы › Компания';

        if ($missingEntityContext && $companyId > 0) {
            try {
                $pdo = $db->connection();
                $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
                $stmt->execute([$companyId]);
                $company = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
                if ($company) {
                    $pageContext = 'Документы › Компания: ' . $company['name'];
                }
            } catch (\Exception $e) {
                $company = null;
            }
        }

        ob_start();
        require base_path('app/View/pages/company_documents.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
        return;
    }

    $entityMeta = $whitelist[$entityType];
    $entityLabel = $entityMeta['label'];
    $entityLabelDative = $entityMeta['labelDative'];
    $tableName = $entityMeta['table'];
    $backRoute = $entityMeta['backRoute'];

    $pageTitle = 'Документы';
    $pageContext = $entityLabel . ' › Компания';
    $entityTypeError = false;
    $entityNotFound = false;
    $documents = [];
    $entityName = '';
    $dbError = null;

    if ($companyId <= 0) {
        $company = null;

        ob_start();
        require base_path('app/View/pages/company_documents.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
        return;
    }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company) {
            ob_start();
            require base_path('app/View/pages/company_documents.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $pageContext = $entityLabel . ' › Компания: ' . $company['name'];

        if ($company['status'] !== 'active') {
            ob_start();
            require base_path('app/View/pages/company_documents.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $dbIdentifier = $company['db_identifier'];
        $localDbConfig = $config['database'];
        $localDbConfig['database'] = $dbIdentifier;
        $localDb = new \App\Core\Database($localDbConfig);
        $localPdo = $localDb->connection();
                applyLocalMigrations($localPdo);

        $entityStmt = $localPdo->prepare("SELECT * FROM `{$tableName}` WHERE id = ?");
        $entityStmt->execute([$entityId]);
        $entity = $entityStmt->fetch(PDO::FETCH_ASSOC);

        if (!$entity) {
            $entityNotFound = true;

            ob_start();
            require base_path('app/View/pages/company_documents.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        switch ($entityType) {
            case 'client':
            case 'contractor':
                $entityName = $entity['name'];
                break;
            case 'driver':
                $entityName = $entity['full_name'];
                break;
            case 'vehicle_unit':
                $entityName = $entity['plate_number'];
                break;
            case 'crew':
                $entityName = 'Экипаж #' . $entity['id'];
                break;
            default:
                $entityName = '';
        }

        try {
            $localPdo->query("SELECT 1 FROM documents LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $migrationSql = file_get_contents(base_path('database/migrations-local/007_create_company_documents.sql'));
            $localPdo->exec($migrationSql);
        }

        try {
            $localPdo->query("SELECT created_by_user_id FROM documents LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $localPdo->exec("ALTER TABLE documents ADD COLUMN created_by_user_id INT UNSIGNED DEFAULT NULL, ADD COLUMN created_by_role VARCHAR(20) DEFAULT NULL");
        }

        $isLogist = ($_SESSION['role_code'] ?? '') === 'logist';
        if ($isLogist) {
            $docStmt = $localPdo->prepare(
                "SELECT * FROM documents WHERE entity_type = ? AND entity_id = ? AND deleted_at IS NULL ORDER BY created_at DESC"
            );
        } else {
            $docStmt = $localPdo->prepare(
                "SELECT * FROM documents WHERE entity_type = ? AND entity_id = ? ORDER BY created_at DESC"
            );
        }
        $docStmt->execute([$entityType, $entityId]);
        $documents = $docStmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($documents as &$doc) {
            $doc['file_size_formatted'] = formatFileSize((int)$doc['file_size']);
        }
        unset($doc);

        $dbError = null;
    } catch (\Exception $e) {
        $company = $company ?? null;
        $documents = [];
        $dbError = 'Не удалось подключиться к базе данных компании. Проверьте, что локальная БД создана.';
    }

    ob_start();
    require base_path('app/View/pages/company_documents.php');
    $content = ob_get_clean();
    require base_path('app/View/layouts/main.php');
});

$router->get('/company/documents/upload', function () use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);

    $companyId = (int)(getSessionCompanyId() ?? 0);
    $entityType = $_GET['entity_type'] ?? '';
    $entityId = (int)($_GET['entity_id'] ?? 0);
    $missingEntityContext = ($entityType === '' && $entityId <= 0);

    $whitelist = ['client' => ['label' => 'Клиент', 'labelDative' => 'клиентам', 'table' => 'clients', 'backRoute' => '/company/clients'],
                    'contractor' => ['label' => 'Перевозчик', 'labelDative' => 'перевозчикам', 'table' => 'contractors', 'backRoute' => '/company/contractors'],
                   'driver' => ['label' => 'Водитель', 'labelDative' => 'водителям', 'table' => 'drivers', 'backRoute' => '/company/drivers'],
                   'vehicle_unit' => ['label' => 'Транспортная единица', 'labelDative' => 'транспортным единицам', 'table' => 'vehicle_units', 'backRoute' => '/company/vehicles'],
                    'vehicle_set' => ['label' => 'Транспорт', 'labelDative' => 'транспорту', 'table' => 'vehicle_sets', 'backRoute' => '/company/vehicle-sets'],
                   'driver_vehicle_block' => ['label' => 'Водители+ТС', 'labelDative' => 'связкам', 'table' => 'driver_vehicle_blocks', 'backRoute' => '/company/driver-vehicle-blocks'],
                   'crew' => ['label' => 'Экипаж', 'labelDative' => 'экипажам', 'table' => 'crews', 'backRoute' => '/company/crews']];

    $replaceDocId = (int)($_GET['replace'] ?? 0);
    $replacedDoc = null;

    $pageTitle = $replaceDocId > 0 ? 'Заменить документ' : 'Загрузить документ';
    $pageContext = ($replaceDocId > 0 ? 'Замена документа › ' : 'Загрузка документа › ') . 'Компания';
    $entityTypeError = false;
    $entityNotFound = false;
    $success = false;
    $formError = null;
    $errors = [];
    $old = [];
    $createdDoc = null;
    $dbError = null;
    $docTypes = [];

    if (!isset($whitelist[$entityType])) {
        $entityTypeError = !$missingEntityContext;
        $company = null;
        $entityLabel = '';
        $entityName = '';

        if ($missingEntityContext && $companyId > 0) {
            try {
                $pdo = $db->connection();
                $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
                $stmt->execute([$companyId]);
                $company = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
                if ($company) {
                    $pageContext = 'Загрузка документа › Компания: ' . $company['name'];
                }
            } catch (\Exception $e) {
                $company = null;
            }
        }

        ob_start();
        require base_path('app/View/pages/company_documents_upload.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
        return;
    }

    $entityMeta = $whitelist[$entityType];
    $entityLabel = $entityMeta['label'];
    $tableName = $entityMeta['table'];

    if ($companyId <= 0) {
        $company = null;
        $entityName = '';

        ob_start();
        require base_path('app/View/pages/company_documents_upload.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
        return;
    }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company) {
            $entityName = '';

            ob_start();
            require base_path('app/View/pages/company_documents_upload.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $pageContext = $entityLabel . ' › Загрузка документа › Компания: ' . $company['name'];

        if ($company['status'] !== 'active') {
            $entityName = '';

            ob_start();
            require base_path('app/View/pages/company_documents_upload.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $dbIdentifier = $company['db_identifier'];
        $localDbConfig = $config['database'];
        $localDbConfig['database'] = $dbIdentifier;
        $localDb = new \App\Core\Database($localDbConfig);
        $localPdo = $localDb->connection();
                applyLocalMigrations($localPdo);

        $entityStmt = $localPdo->prepare("SELECT * FROM `{$tableName}` WHERE id = ?");
        $entityStmt->execute([$entityId]);
        $entity = $entityStmt->fetch(PDO::FETCH_ASSOC);

        if (!$entity) {
            $entityNotFound = true;
            $entityName = '';

            ob_start();
            require base_path('app/View/pages/company_documents_upload.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        switch ($entityType) {
            case 'client':
            case 'contractor':
                $entityName = $entity['name'];
                break;
            case 'driver':
                $entityName = $entity['full_name'];
                break;
            case 'vehicle_unit':
                $entityName = $entity['plate_number'];
                break;
            case 'vehicle_set':
                $entityName = 'Комплект #' . $entity['id'];
                break;
            case 'driver_vehicle_block':
                $entityName = 'Связка #' . $entity['id'];
                break;
            case 'crew':
                $entityName = 'Экипаж #' . $entity['id'];
                break;
            default:
                $entityName = '';
        }

        // Fetch document types for dropdown
        try {
            try { $localPdo->query("SELECT 1 FROM document_types LIMIT 1")->fetch(); }
            catch (\Exception $e) {
                $localPdo->exec(file_get_contents(base_path('database/migrations-local/024_create_document_types.sql')));
                $localPdo->exec(file_get_contents(base_path('database/migrations-local/025_add_document_type_id.sql')));
            }
            $docTypes = $localPdo->query("SELECT id, name, code, entity_type, category FROM document_types WHERE entity_type = '{$entityType}' OR entity_type IS NULL ORDER BY sort_order, name")->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            $docTypes = [];
        }
    } catch (\Exception $e) {
        $company = $company ?? null;
        $entityName = '';
        $dbError = 'Не удалось подключиться к базе данных компании. Проверьте, что локальная БД создана.';
    }

    if ($replaceDocId > 0 && !$entityNotFound && !$dbError && $company && $company['status'] === 'active') {
        try {
            $docStmt = $localPdo->prepare('SELECT * FROM documents WHERE id = ? AND entity_type = ? AND entity_id = ?');
            $docStmt->execute([$replaceDocId, $entityType, $entityId]);
            $replacedDoc = $docStmt->fetch(PDO::FETCH_ASSOC);
            if (!$replacedDoc) {
                $replaceDocId = 0;
            }
        } catch (\Exception $e) {
            $replaceDocId = 0;
        }
    }

    ob_start();
    require base_path('app/View/pages/company_documents_upload.php');
    $content = ob_get_clean();
    require base_path('app/View/layouts/main.php');
});

$router->post('/company/documents/upload', function () use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);

    $companyId = (int)(getSessionCompanyId() ?? 0);
    $entityType = $_GET['entity_type'] ?? '';
    $entityId = (int)($_GET['entity_id'] ?? 0);

    $whitelist = ['client' => ['label' => 'Клиент', 'labelDative' => 'клиентам', 'table' => 'clients', 'backRoute' => '/company/clients'],
                    'contractor' => ['label' => 'Перевозчик', 'labelDative' => 'перевозчикам', 'table' => 'contractors', 'backRoute' => '/company/contractors'],
                   'driver' => ['label' => 'Водитель', 'labelDative' => 'водителям', 'table' => 'drivers', 'backRoute' => '/company/drivers'],
                   'vehicle_unit' => ['label' => 'Транспортная единица', 'labelDative' => 'транспортным единицам', 'table' => 'vehicle_units', 'backRoute' => '/company/vehicles'],
                    'vehicle_set' => ['label' => 'Транспорт', 'labelDative' => 'транспорту', 'table' => 'vehicle_sets', 'backRoute' => '/company/vehicle-sets'],
                   'driver_vehicle_block' => ['label' => 'Водители+ТС', 'labelDative' => 'связкам', 'table' => 'driver_vehicle_blocks', 'backRoute' => '/company/driver-vehicle-blocks'],
                   'crew' => ['label' => 'Экипаж', 'labelDative' => 'экипажам', 'table' => 'crews', 'backRoute' => '/company/crews']];

    $pageTitle = 'Загрузить документ';
    $pageContext = 'Загрузка документа › Компания';
    $entityTypeError = false;
    $entityNotFound = false;
    $success = false;
    $formError = null;
    $errors = [];
    $old = $_POST;
    $createdDoc = null;
    $dbError = null;
    $docTypes = [];

    if (!isset($whitelist[$entityType])) {
        $entityTypeError = true;
        $company = null;
        $entityLabel = '';
        $entityName = '';

        ob_start();
        require base_path('app/View/pages/company_documents_upload.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
        return;
    }

    $entityMeta = $whitelist[$entityType];
    $entityLabel = $entityMeta['label'];
    $tableName = $entityMeta['table'];

    if ($companyId <= 0) {
        $company = null;
        $entityName = '';

        ob_start();
        require base_path('app/View/pages/company_documents_upload.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
        return;
    }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company) {
            $entityName = '';

            ob_start();
            require base_path('app/View/pages/company_documents_upload.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $pageContext = $entityLabel . ' › Загрузка документа › Компания: ' . $company['name'];

        if ($company['status'] !== 'active') {
            $entityName = '';

            ob_start();
            require base_path('app/View/pages/company_documents_upload.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $dbIdentifier = $company['db_identifier'];
        $localDbConfig = $config['database'];
        $localDbConfig['database'] = $dbIdentifier;
        $localDb = new \App\Core\Database($localDbConfig);
        $localPdo = $localDb->connection();
                applyLocalMigrations($localPdo);

        $entityStmt = $localPdo->prepare("SELECT * FROM `{$tableName}` WHERE id = ?");
        $entityStmt->execute([$entityId]);
        $entity = $entityStmt->fetch(PDO::FETCH_ASSOC);

        if (!$entity) {
            $entityNotFound = true;
            $entityName = '';

            ob_start();
            require base_path('app/View/pages/company_documents_upload.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        switch ($entityType) {
            case 'client':
            case 'contractor':
                $entityName = $entity['name'];
                break;
            case 'driver':
                $entityName = $entity['full_name'];
                break;
            case 'vehicle_unit':
                $entityName = $entity['plate_number'];
                break;
            case 'vehicle_set':
                $entityName = 'Комплект #' . $entity['id'];
                break;
            case 'driver_vehicle_block':
                $entityName = 'Связка #' . $entity['id'];
                break;
            case 'crew':
                $entityName = 'Экипаж #' . $entity['id'];
                break;
            default:
                $entityName = '';
        }

        // Fetch document types for dropdown (re-render on error)
        try {
            try { $localPdo->query("SELECT 1 FROM document_types LIMIT 1")->fetch(); }
            catch (\Exception $e) {
                $localPdo->exec(file_get_contents(base_path('database/migrations-local/024_create_document_types.sql')));
                $localPdo->exec(file_get_contents(base_path('database/migrations-local/025_add_document_type_id.sql')));
            }
            $docTypes = $localPdo->query("SELECT id, name, code, entity_type, category FROM document_types WHERE entity_type = '{$entityType}' OR entity_type IS NULL ORDER BY sort_order, name")->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            $docTypes = [];
        }

        // File handling
        $documentType = trim($_POST['document_type'] ?? '');
        // document_type is now optional (NOT required)
        $documentName = trim($_POST['document_name'] ?? '');
        $documentNumber = trim($_POST['document_number'] ?? '');
        $documentDate = trim($_POST['document_date'] ?? '');
        $documentExpire = trim($_POST['document_expire'] ?? '');
        $comments = trim($_POST['comments'] ?? '');

        if ($documentType === '') {
            // document_type is now optional - save as NULL
        }

        $fileError = $_FILES['document_file']['error'] ?? UPLOAD_ERR_NO_FILE;

        if ($fileError === UPLOAD_ERR_NO_FILE) {
            $errors['document_file'] = 'Выберите файл для загрузки';
        } elseif ($fileError === UPLOAD_ERR_INI_SIZE || $fileError === UPLOAD_ERR_FORM_SIZE) {
            $errors['document_file'] = 'Размер файла превышает допустимый лимит';
        } elseif ($fileError !== UPLOAD_ERR_OK) {
            $errors['document_file'] = 'Ошибка при загрузке файла. Попробуйте ещё раз.';
        } else {
            $originalName = $_FILES['document_file']['name'];
            $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
            $allowedExt = ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'doc', 'docx', 'xls', 'xlsx'];

            if (!in_array($ext, $allowedExt, true)) {
                $errors['document_file'] = 'Недопустимый формат файла. Разрешены: PDF, JPG, PNG, DOC, DOCX, XLS, XLSX';
            }

            if (empty($errors['document_file'])) {
                $fileSize = $_FILES['document_file']['size'];
                if ($fileSize > 20 * 1024 * 1024) {
                    $errors['document_file'] = 'Размер файла превышает 20 МБ';
                }
            }

            if (empty($errors['document_file'])) {
                if (strpos($originalName, '../') !== false || strpos($originalName, '..\\') !== false
                    || strpos($originalName, '/') !== false || strpos($originalName, '\\') !== false) {
                    $errors['document_file'] = 'Недопустимое имя файла';
                }
            }
        }

        if (!empty($errors)) {
            ob_start();
            require base_path('app/View/pages/company_documents_upload.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        try {
            $localPdo->query("SELECT 1 FROM documents LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $migrationSql = file_get_contents(base_path('database/migrations-local/007_create_company_documents.sql'));
            $localPdo->exec($migrationSql);
        }

        try {
            $localPdo->query("SELECT created_by_user_id FROM documents LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $localPdo->exec("ALTER TABLE documents ADD COLUMN created_by_user_id INT UNSIGNED DEFAULT NULL, ADD COLUMN created_by_role VARCHAR(20) DEFAULT NULL");
        }

        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $storedName = uniqid('doc_', true) . '.' . $ext;
        $relativeDir = 'companies/' . $companyId . '/documents/' . $entityType . '/' . $entityId;
        $relativePath = $relativeDir . '/' . $storedName;
        $absoluteDir = storage_path($relativeDir);

        if (!is_dir($absoluteDir)) {
            if (!mkdir($absoluteDir, 0755, true)) {
                $formError = 'Не удалось создать директорию для хранения файла.';
                ob_start();
                require base_path('app/View/pages/company_documents_upload.php');
                $content = ob_get_clean();
                require base_path('app/View/layouts/main.php');
                return;
            }
        }

        $tmpPath = $_FILES['document_file']['tmp_name'];
        $destPath = $absoluteDir . DIRECTORY_SEPARATOR . $storedName;

        if (!move_uploaded_file($tmpPath, $destPath)) {
            $formError = 'Не удалось сохранить файл. Попробуйте ещё раз.';
            ob_start();
            require base_path('app/View/pages/company_documents_upload.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $mimeType = $_FILES['document_file']['type'];
        $fileSize = $_FILES['document_file']['size'];
        $comments = trim($_POST['comments'] ?? '');
        $userId = (int)($_SESSION['user_id'] ?? 0);
        $userRole = $_SESSION['role_code'] ?? '';

        $insert = $localPdo->prepare(
            'INSERT INTO documents (entity_type, entity_id, document_type, document_name, document_number, document_date, document_expire, original_name, stored_name,
             relative_path, mime_type, file_size, status, uploaded_by_user_id, uploaded_by_role, comments, created_by_user_id, created_by_role)
             VALUES (:entity_type, :entity_id, :document_type, :document_name, :document_number, :document_date, :document_expire, :original_name, :stored_name,
             :relative_path, :mime_type, :file_size, :status, :uploaded_by_user_id, :uploaded_by_role, :comments, :created_by_user_id, :created_by_role)'
        );
        $insert->execute([
            ':entity_type'         => $entityType,
            ':entity_id'           => $entityId,
            ':document_type'       => $documentType !== '' ? $documentType : null,
            ':document_name'       => $documentName !== '' ? $documentName : null,
            ':document_number'     => $documentNumber !== '' ? $documentNumber : null,
            ':document_date'       => $documentDate !== '' ? $documentDate : null,
            ':document_expire'     => $documentExpire !== '' ? $documentExpire : null,
            ':original_name'       => $originalName,
            ':stored_name'         => $storedName,
            ':relative_path'       => $relativePath,
            ':mime_type'           => $mimeType,
            ':file_size'           => $fileSize,
            ':status'              => 'uploaded',
            ':uploaded_by_user_id' => $userId > 0 ? $userId : null,
            ':uploaded_by_role'    => $userRole !== '' ? $userRole : null,
            ':comments'            => $comments !== '' ? $comments : null,
            ':created_by_user_id'  => $userId > 0 ? $userId : null,
            ':created_by_role'     => $userRole !== '' ? $userRole : null,
        ]);

        $lastId = $localPdo->lastInsertId();
        $selectStmt = $localPdo->prepare('SELECT * FROM documents WHERE id = ?');
        $selectStmt->execute([$lastId]);
        $createdDoc = $selectStmt->fetch(PDO::FETCH_ASSOC);
        $createdDoc['file_size_formatted'] = formatFileSize((int)$createdDoc['file_size']);
        $success = true;

        $pageTitle = 'Документ загружен';
        $pageContext = $entityLabel . ' › Компания: ' . $company['name'];
    } catch (\Exception $e) {
        $company = $company ?? null;
        $entityName = $entityName ?? '';
        $formError = 'Ошибка при загрузке: ' . $e->getMessage();
    }

    ob_start();
    require base_path('app/View/pages/company_documents_upload.php');
    $content = ob_get_clean();
    require base_path('app/View/layouts/main.php');
});

$router->get('/company/documents/download', function () use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);

    $docId = (int)($_GET['id'] ?? 0);
    $companyId = (int)(getSessionCompanyId() ?? 0);

    if ($docId <= 0 || $companyId <= 0) {
        http_response_code(404);
        echo 'Document not found';
        return;
    }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company || $company['status'] !== 'active') {
            http_response_code(404);
            echo 'Company not found';
            return;
        }

        $dbIdentifier = $company['db_identifier'];
        $localDbConfig = $config['database'];
        $localDbConfig['database'] = $dbIdentifier;
        $localDb = new \App\Core\Database($localDbConfig);
        $localPdo = $localDb->connection();
                applyLocalMigrations($localPdo);

        try {
            $localPdo->query("SELECT 1 FROM documents LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $migrationSql = file_get_contents(base_path('database/migrations-local/007_create_company_documents.sql'));
            $localPdo->exec($migrationSql);
        }

        $docStmt = $localPdo->prepare('SELECT * FROM documents WHERE id = ?');
        $docStmt->execute([$docId]);
        $doc = $docStmt->fetch(PDO::FETCH_ASSOC);

        if (!$doc) {
            http_response_code(404);
            echo 'Document not found';
            return;
        }

        $storageBase = storage_path('companies/' . $companyId . '/documents/');
        $filePath = $storageBase . $doc['entity_type'] . '/' . $doc['entity_id'] . '/' . $doc['stored_name'];

        $realBase = realpath($storageBase);
        $realFile = realpath($filePath);
        if ($realFile === false || !str_starts_with($realFile, $realBase)) {
            http_response_code(404);
            echo 'File not found';
            return;
        }

        if (!file_exists($realFile)) {
            http_response_code(404);
            echo 'File not found';
            return;
        }

        $mimeType = $doc['mime_type'] ?: 'application/octet-stream';
        $originalName = $doc['original_name'] ?: 'download';

        $safeFilename = preg_replace('/[^a-zA-Z0-9._-]/u', '_', $originalName);

        header('Content-Type: ' . $mimeType);
        header('Content-Disposition: attachment; filename="' . $safeFilename . '"');
        header('Content-Length: ' . filesize($realFile));
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: no-store, must-revalidate');

        readfile($realFile);
        exit;

    } catch (\Exception $e) {
        http_response_code(500);
        echo 'Error downloading file';
    }
});

$router->get('/company/documents/view', function () use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);

    $docId = (int)($_GET['id'] ?? 0);
    $companyId = (int)(getSessionCompanyId() ?? 0);

    if ($docId <= 0 || $companyId <= 0) {
        http_response_code(404);
        echo 'Document not found';
        return;
    }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company || $company['status'] !== 'active') {
            http_response_code(404);
            echo 'Company not found';
            return;
        }

        $dbIdentifier = $company['db_identifier'];
        $localDbConfig = $config['database'];
        $localDbConfig['database'] = $dbIdentifier;
        $localDb = new \App\Core\Database($localDbConfig);
        $localPdo = $localDb->connection();
        applyLocalMigrations($localPdo);

        try {
            $localPdo->query("SELECT 1 FROM documents LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $migrationSql = file_get_contents(base_path('database/migrations-local/007_create_company_documents.sql'));
            $localPdo->exec($migrationSql);
        }

        $docStmt = $localPdo->prepare('SELECT * FROM documents WHERE id = ?');
        $docStmt->execute([$docId]);
        $doc = $docStmt->fetch(PDO::FETCH_ASSOC);

        if (!$doc) {
            http_response_code(404);
            echo 'Document not found';
            return;
        }

        $storageBase = storage_path('companies/' . $companyId . '/documents/');
        $filePath = $storageBase . $doc['entity_type'] . '/' . $doc['entity_id'] . '/' . $doc['stored_name'];

        $realBase = realpath($storageBase);
        $realFile = realpath($filePath);
        if ($realFile === false || !str_starts_with($realFile, $realBase)) {
            http_response_code(404);
            echo 'File not found';
            return;
        }

        if (!file_exists($realFile)) {
            http_response_code(404);
            echo 'File not found';
            return;
        }

        $mimeType = $doc['mime_type'] ?: 'application/octet-stream';
        $originalName = $doc['original_name'] ?: 'document';

        header('Content-Type: ' . $mimeType);
        header('Content-Disposition: inline; filename="' . $originalName . '"');
        header('Content-Length: ' . filesize($realFile));
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: no-store, must-revalidate');

        readfile($realFile);
        exit;

    } catch (\Exception $e) {
        http_response_code(500);
        echo 'Error viewing file';
    }
});

$router->post('/company/documents/delete', function () use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);

    $companyId = getSessionCompanyId();
    $docId = (int)($_GET['id'] ?? 0);
    $redirect = $_GET['redirect'] ?? '/company/dashboard';

    if ($docId <= 0 || !$companyId) {
        header('Location: ' . $redirect);
        exit;
    }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company || $company['status'] !== 'active') {
            header('Location: ' . $redirect);
            exit;
        }

        $dbIdentifier = $company['db_identifier'];
        $localDbConfig = $config['database'];
        $localDbConfig['database'] = $dbIdentifier;
        $localDb = new \App\Core\Database($localDbConfig);
        $localPdo = $localDb->connection();
                applyLocalMigrations($localPdo);

        $docStmt = $localPdo->prepare('SELECT * FROM documents WHERE id = ?');
        $docStmt->execute([$docId]);
        $doc = $docStmt->fetch(PDO::FETCH_ASSOC);

        if (!$doc) {
            header('Location: ' . $redirect);
            exit;
        }

        // Role-based access check for document deletion
        $isLogist = ($_SESSION['role_code'] ?? '') === 'logist';
        if ($isLogist) {
            $userId = (int)$_SESSION['user_id'];
            // Logist can only delete documents they uploaded
            $uploadedBy = $doc['uploaded_by_user_id'] ?? $doc['created_by_user_id'] ?? null;
            if ((int)$uploadedBy !== $userId) {
                header('Location: ' . $redirect);
                exit;
            }
        }

        $updateStmt = $localPdo->prepare('UPDATE documents SET deleted_at = NOW(), deleted_by_user_id = :uid, delete_comment = :comment, updated_at = NOW() WHERE id = :id');
        $updateStmt->execute([':uid' => (int)$_SESSION['user_id'], ':comment' => 'Удалено через интерфейс', ':id' => $docId]);

        header('Location: ' . $redirect);
        exit;
    } catch (\Exception $e) {
        header('Location: ' . $redirect);
        exit;
    }
});

$router->post('/company/documents/replace', function () use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);

    $companyId = getSessionCompanyId();
    $docId = (int)($_POST['replace_doc_id'] ?? 0);
    $redirect = $_POST['redirect'] ?? '/company/dashboard';

    if ($docId <= 0 || !$companyId) {
        header('Location: ' . $redirect);
        exit;
    }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company || $company['status'] !== 'active') {
            header('Location: ' . $redirect);
            exit;
        }

        $dbIdentifier = $company['db_identifier'];
        $localDbConfig = $config['database'];
        $localDbConfig['database'] = $dbIdentifier;
        $localDb = new \App\Core\Database($localDbConfig);
        $localPdo = $localDb->connection();
                applyLocalMigrations($localPdo);

        $docStmt = $localPdo->prepare('SELECT * FROM documents WHERE id = ?');
        $docStmt->execute([$docId]);
        $oldDoc = $docStmt->fetch(PDO::FETCH_ASSOC);

        if (!$oldDoc) {
            header('Location: ' . $redirect);
            exit;
        }

        $fileError = $_FILES['document_file']['error'] ?? UPLOAD_ERR_NO_FILE;
        if ($fileError !== UPLOAD_ERR_OK) {
            header('Location: ' . $redirect);
            exit;
        }

        $originalName = $_FILES['document_file']['name'];
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $allowedExt = ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'doc', 'docx', 'xls', 'xlsx'];
        if (!in_array($ext, $allowedExt, true)) {
            header('Location: ' . $redirect);
            exit;
        }

        $fileSize = $_FILES['document_file']['size'];
        if ($fileSize > 20 * 1024 * 1024) {
            header('Location: ' . $redirect);
            exit;
        }

        if (strpos($originalName, '../') !== false || strpos($originalName, '..\\') !== false
            || strpos($originalName, '/') !== false || strpos($originalName, '\\') !== false) {
            header('Location: ' . $redirect);
            exit;
        }

        $storageBase = storage_path('companies/' . $companyId . '/documents/');
        $entityDir = $storageBase . $oldDoc['entity_type'] . '/' . $oldDoc['entity_id'] . '/';
        if (!is_dir($entityDir)) {
            mkdir($entityDir, 0755, true);
        }

        $storedName = 'doc_' . uniqid() . '.' . $ext;
        $tmpPath = $_FILES['document_file']['tmp_name'];

        if (!move_uploaded_file($tmpPath, $entityDir . $storedName)) {
            header('Location: ' . $redirect);
            exit;
        }

        $mimeType = $_FILES['document_file']['type'] ?: mime_content_type($entityDir . $storedName);
        $documentType = trim($_POST['document_type'] ?? $oldDoc['document_type'] ?? '');

        $updateStmt = $localPdo->prepare(
            'UPDATE documents SET original_name = :oname, stored_name = :sname, mime_type = :mime,
             file_size = :fsize, document_type = :dtype, status = :status,
             deleted_at = NULL, deleted_by_user_id = NULL, delete_comment = NULL,
             uploaded_by_user_id = :uid, uploaded_by_role = :urole, updated_at = NOW()
             WHERE id = :id'
        );
        $updateStmt->execute([
            ':oname' => $originalName,
            ':sname' => $storedName,
            ':mime' => $mimeType,
            ':fsize' => $fileSize,
            ':dtype' => $documentType ?: null,
            ':status' => 'uploaded',
            ':uid' => (int)$_SESSION['user_id'],
            ':urole' => $_SESSION['role_code'] ?? null,
            ':id' => $docId,
        ]);

        header('Location: ' . $redirect);
        exit;
    } catch (\Exception $e) {
        header('Location: ' . $redirect);
        exit;
    }
});

// -- Document Types (catalog/directory) --

$router->get('/company/document-types', function () use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);
    $pageTitle = 'Типы документов';
    $pageContext = 'Типы документов › Компания';

    $companyId = (int)(getSessionCompanyId() ?? 0);
    $filterEntity = $_GET['entity_type'] ?? '';
    $deleteError = null;
    $deleteSuccess = false;
    $types = [];
    $entityTypes = [];
    $dbError = null;

    if ($companyId <= 0) {
        $company = null;
        ob_start(); require base_path('app/View/pages/company_document_types.php');
        $content = ob_get_clean(); require base_path('app/View/layouts/main.php');
        return;
    }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company) {
            ob_start(); require base_path('app/View/pages/company_document_types.php');
            $content = ob_get_clean(); require base_path('app/View/layouts/main.php');
            return;
        }

        $pageContext = 'Типы документов › Компания: ' . $company['name'];

        if ($company['status'] !== 'active') {
            ob_start(); require base_path('app/View/pages/company_document_types.php');
            $content = ob_get_clean(); require base_path('app/View/layouts/main.php');
            return;
        }

        $dbIdentifier = $company['db_identifier'];
        $localDbConfig = $config['database']; $localDbConfig['database'] = $dbIdentifier;
        $localDb = new \App\Core\Database($localDbConfig); $localPdo = $localDb->connection();
        applyLocalMigrations($localPdo);

        try {
            $localPdo->query("SELECT 1 FROM document_types LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $migrationSql = file_get_contents(base_path('database/migrations-local/024_create_document_types.sql'));
            $localPdo->exec($migrationSql);
            $migrationSql25 = file_get_contents(base_path('database/migrations-local/025_add_document_type_id.sql'));
            $localPdo->exec($migrationSql25);
        }

        if ($filterEntity !== '') {
            $typeStmt = $localPdo->prepare('SELECT * FROM document_types WHERE entity_type = ? OR entity_type IS NULL ORDER BY sort_order, name');
            $typeStmt->execute([$filterEntity]);
        } else {
            $typeStmt = $localPdo->query('SELECT * FROM document_types ORDER BY entity_type, sort_order, name');
        }
        $types = $typeStmt->fetchAll(PDO::FETCH_ASSOC);

        $etStmt = $localPdo->query("SELECT DISTINCT entity_type FROM document_types WHERE entity_type IS NOT NULL ORDER BY entity_type");
        $entityTypes = array_column($etStmt->fetchAll(PDO::FETCH_ASSOC), 'entity_type');
    } catch (\Exception $e) {
        $company = $company ?? null;
        $types = [];
        $entityTypes = [];
        $dbError = 'Не удалось загрузить типы документов: ' . $e->getMessage();
    }

    ob_start(); require base_path('app/View/pages/company_document_types.php');
    $content = ob_get_clean(); require base_path('app/View/layouts/main.php');
});

$router->get('/company/document-types/create', function () use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);
    $pageTitle = 'Создать тип документа';
    $pageContext = 'Типы документов › Компания';

    $companyId = (int)(getSessionCompanyId() ?? 0);
    $isEdit = false;
    $editId = 0;
    $existingType = null;
    $success = false;
    $errors = []; $old = []; $formError = null; $createdType = null;

    if ($companyId <= 0) {
        $company = null;
        ob_start(); require base_path('app/View/pages/company_document_types_form.php');
        $content = ob_get_clean(); require base_path('app/View/layouts/main.php');
        return;
    }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company) {
            ob_start(); require base_path('app/View/pages/company_document_types_form.php');
            $content = ob_get_clean(); require base_path('app/View/layouts/main.php');
            return;
        }

        $pageContext = 'Типы документов › Компания: ' . $company['name'];

        if ($company['status'] !== 'active') {
            ob_start(); require base_path('app/View/pages/company_document_types_form.php');
            $content = ob_get_clean(); require base_path('app/View/layouts/main.php');
            return;
        }
    } catch (\Exception $e) {
        $company = null;
        $formError = 'Ошибка: ' . $e->getMessage();
    }

    ob_start(); require base_path('app/View/pages/company_document_types_form.php');
    $content = ob_get_clean(); require base_path('app/View/layouts/main.php');
});

$router->post('/company/document-types/create', function () use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);
    $pageTitle = 'Создать тип документа';
    $pageContext = 'Типы документов › Компания';

    $companyId = (int)(getSessionCompanyId() ?? 0);
    $isEdit = false;
    $editId = 0;
    $existingType = null;
    $success = false;
    $errors = []; $old = $_POST; $formError = null; $createdType = null;

    if ($companyId <= 0) {
        $company = null;
        ob_start(); require base_path('app/View/pages/company_document_types_form.php');
        $content = ob_get_clean(); require base_path('app/View/layouts/main.php');
        return;
    }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company) { $company = null; $formError = 'Компания не найдена'; goto renderDTForm; }
        $pageContext = 'Типы документов › Компания: ' . $company['name'];

        if ($company['status'] !== 'active') { $formError = 'Создание недоступно'; goto renderDTForm; }

        $dbIdentifier = $company['db_identifier'];
        $localDbConfig = $config['database']; $localDbConfig['database'] = $dbIdentifier;
        $localDb = new \App\Core\Database($localDbConfig); $localPdo = $localDb->connection();
        applyLocalMigrations($localPdo);

        try { $localPdo->query("SELECT 1 FROM document_types LIMIT 1")->fetch(); }
        catch (\Exception $e) {
            $localPdo->exec(file_get_contents(base_path('database/migrations-local/024_create_document_types.sql')));
            $localPdo->exec(file_get_contents(base_path('database/migrations-local/025_add_document_type_id.sql')));
        }

        $name = trim($_POST['name'] ?? '');
        $code = trim($_POST['code'] ?? '');
        $entityType = trim($_POST['entity_type'] ?? '');
        $sortOrder = (int)($_POST['sort_order'] ?? 0);

        if ($name === '') { $errors['name'] = 'Обязательное поле'; }
        if (!empty($errors)) { goto renderDTForm; }

        $insert = $localPdo->prepare(
            'INSERT INTO document_types (name, code, entity_type, category, sort_order, created_by_user_id, created_by_role)
             VALUES (:name, :code, :entity_type, :category, :sort_order, :uid, :role)'
        );
        $insert->execute([
            ':name' => $name,
            ':code' => $code !== '' ? $code : null,
            ':entity_type' => $entityType !== '' ? $entityType : null,
            ':category' => 'custom',
            ':sort_order' => $sortOrder,
            ':uid' => (int)$_SESSION['user_id'],
            ':role' => $_SESSION['role_code'],
        ]);

        $newId = $localPdo->lastInsertId();
        $createdType = ['id' => $newId, 'name' => $name, 'code' => $code, 'entity_type' => $entityType, 'category' => 'custom'];
        $success = true;
    } catch (\Exception $e) {
        $company = $company ?? null;
        $formError = 'Ошибка создания: ' . $e->getMessage();
    }

    renderDTForm:
    ob_start(); require base_path('app/View/pages/company_document_types_form.php');
    $content = ob_get_clean(); require base_path('app/View/layouts/main.php');
});

$router->get('/company/document-types/{id}/edit', function ($id) use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);
    $pageTitle = 'Редактировать тип документа';
    $pageContext = 'Типы документов › Компания';

    $companyId = (int)(getSessionCompanyId() ?? 0);
    $isEdit = true;
    $editId = (int)$id;
    $existingType = null;
    $success = false;
    $errors = []; $old = []; $formError = null; $createdType = null;

    if ($companyId <= 0) {
        $company = null;
        ob_start(); require base_path('app/View/pages/company_document_types_form.php');
        $content = ob_get_clean(); require base_path('app/View/layouts/main.php');
        return;
    }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company) {
            ob_start(); require base_path('app/View/pages/company_document_types_form.php');
            $content = ob_get_clean(); require base_path('app/View/layouts/main.php');
            return;
        }

        $pageContext = 'Типы документов › Компания: ' . $company['name'];

        if ($company['status'] !== 'active') {
            ob_start(); require base_path('app/View/pages/company_document_types_form.php');
            $content = ob_get_clean(); require base_path('app/View/layouts/main.php');
            return;
        }

        $dbIdentifier = $company['db_identifier'];
        $localDbConfig = $config['database']; $localDbConfig['database'] = $dbIdentifier;
        $localDb = new \App\Core\Database($localDbConfig); $localPdo = $localDb->connection();
        applyLocalMigrations($localPdo);

        try { $localPdo->query("SELECT 1 FROM document_types LIMIT 1")->fetch(); }
        catch (\Exception $e) {
            $localPdo->exec(file_get_contents(base_path('database/migrations-local/024_create_document_types.sql')));
            $localPdo->exec(file_get_contents(base_path('database/migrations-local/025_add_document_type_id.sql')));
        }

        $typeStmt = $localPdo->prepare('SELECT * FROM document_types WHERE id = ?');
        $typeStmt->execute([$editId]);
        $existingType = $typeStmt->fetch(PDO::FETCH_ASSOC);

        if (!$existingType) {
            $formError = 'Тип документа не найден';
        }
    } catch (\Exception $e) {
        $company = null;
        $formError = 'Ошибка: ' . $e->getMessage();
    }

    ob_start(); require base_path('app/View/pages/company_document_types_form.php');
    $content = ob_get_clean(); require base_path('app/View/layouts/main.php');
});

$router->post('/company/document-types/{id}/edit', function ($id) use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);
    $pageTitle = 'Редактировать тип документа';
    $pageContext = 'Типы документов › Компания';

    $companyId = (int)(getSessionCompanyId() ?? 0);
    $isEdit = true;
    $editId = (int)$id;
    $existingType = null;
    $success = false;
    $errors = []; $old = $_POST; $formError = null; $createdType = null;

    if ($companyId <= 0) {
        $company = null;
        ob_start(); require base_path('app/View/pages/company_document_types_form.php');
        $content = ob_get_clean(); require base_path('app/View/layouts/main.php');
        return;
    }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company) { $company = null; $formError = 'Компания не найдена'; goto renderDTEditForm; }
        $pageContext = 'Типы документов › Компания: ' . $company['name'];

        if ($company['status'] !== 'active') { $formError = 'Редактирование недоступно'; goto renderDTEditForm; }

        $dbIdentifier = $company['db_identifier'];
        $localDbConfig = $config['database']; $localDbConfig['database'] = $dbIdentifier;
        $localDb = new \App\Core\Database($localDbConfig); $localPdo = $localDb->connection();
        applyLocalMigrations($localPdo);

        try { $localPdo->query("SELECT 1 FROM document_types LIMIT 1")->fetch(); }
        catch (\Exception $e) {
            $localPdo->exec(file_get_contents(base_path('database/migrations-local/024_create_document_types.sql')));
            $localPdo->exec(file_get_contents(base_path('database/migrations-local/025_add_document_type_id.sql')));
        }

        $typeStmt = $localPdo->prepare('SELECT * FROM document_types WHERE id = ?');
        $typeStmt->execute([$editId]);
        $existingType = $typeStmt->fetch(PDO::FETCH_ASSOC);

        if (!$existingType) { $formError = 'Тип документа не найден'; goto renderDTEditForm; }

        $name = trim($_POST['name'] ?? '');
        $code = trim($_POST['code'] ?? '');
        $entityType = trim($_POST['entity_type'] ?? '');
        $sortOrder = (int)($_POST['sort_order'] ?? 0);

        if ($name === '') { $errors['name'] = 'Обязательное поле'; }
        if (!empty($errors)) { goto renderDTEditForm; }

        $update = $localPdo->prepare(
            'UPDATE document_types SET name = :name, code = :code, entity_type = :entity_type, sort_order = :sort_order, updated_at = NOW()
             WHERE id = :id'
        );
        $update->execute([
            ':name' => $name,
            ':code' => $code !== '' ? $code : null,
            ':entity_type' => $entityType !== '' ? $entityType : null,
            ':sort_order' => $sortOrder,
            ':id' => $editId,
        ]);

        $createdType = ['id' => $editId, 'name' => $name, 'code' => $code, 'entity_type' => $entityType, 'category' => $existingType['category']];
        $success = true;
    } catch (\Exception $e) {
        $company = $company ?? null;
        $formError = 'Ошибка обновления: ' . $e->getMessage();
    }

    renderDTEditForm:
    ob_start(); require base_path('app/View/pages/company_document_types_form.php');
    $content = ob_get_clean(); require base_path('app/View/layouts/main.php');
});

$router->post('/company/document-types/{id}/delete', function ($id) use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);
    $pageTitle = 'Типы документов';
    $pageContext = 'Типы документов › Компания';

    $companyId = (int)(getSessionCompanyId() ?? 0);
    $editId = (int)$id;
    $deleteError = null;
    $deleteSuccess = false;
    $types = [];
    $entityTypes = [];
    $dbError = null;
    $filterEntity = '';

    if ($companyId <= 0) {
        $company = null;
        ob_start(); require base_path('app/View/pages/company_document_types.php');
        $content = ob_get_clean(); require base_path('app/View/layouts/main.php');
        return;
    }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company) {
            ob_start(); require base_path('app/View/pages/company_document_types.php');
            $content = ob_get_clean(); require base_path('app/View/layouts/main.php');
            return;
        }

        $pageContext = 'Типы документов › Компания: ' . $company['name'];

        if ($company['status'] !== 'active') {
            ob_start(); require base_path('app/View/pages/company_document_types.php');
            $content = ob_get_clean(); require base_path('app/View/layouts/main.php');
            return;
        }

        $dbIdentifier = $company['db_identifier'];
        $localDbConfig = $config['database']; $localDbConfig['database'] = $dbIdentifier;
        $localDb = new \App\Core\Database($localDbConfig); $localPdo = $localDb->connection();
        applyLocalMigrations($localPdo);

        try { $localPdo->query("SELECT 1 FROM document_types LIMIT 1")->fetch(); }
        catch (\Exception $e) {
            $localPdo->exec(file_get_contents(base_path('database/migrations-local/024_create_document_types.sql')));
            $localPdo->exec(file_get_contents(base_path('database/migrations-local/025_add_document_type_id.sql')));
        }

        $typeStmt = $localPdo->prepare('SELECT * FROM document_types WHERE id = ?');
        $typeStmt->execute([$editId]);
        $docType = $typeStmt->fetch(PDO::FETCH_ASSOC);

        if (!$docType) {
            $deleteError = 'Тип документа не найден';
        } elseif ($docType['category'] === 'predefined') {
            $deleteError = 'Нельзя удалить системный тип документа';
        } else {
            $delStmt = $localPdo->prepare('DELETE FROM document_types WHERE id = ?');
            $delStmt->execute([$editId]);
            $deleteSuccess = true;
        }

        $typeStmt = $localPdo->query('SELECT * FROM document_types ORDER BY entity_type, sort_order, name');
        $types = $typeStmt->fetchAll(PDO::FETCH_ASSOC);
        $etStmt = $localPdo->query("SELECT DISTINCT entity_type FROM document_types WHERE entity_type IS NOT NULL ORDER BY entity_type");
        $entityTypes = array_column($etStmt->fetchAll(PDO::FETCH_ASSOC), 'entity_type');
    } catch (\Exception $e) {
        $company = $company ?? null;
        $types = [];
        $entityTypes = [];
        $dbError = 'Ошибка: ' . $e->getMessage();
    }

    ob_start(); require base_path('app/View/pages/company_document_types.php');
    $content = ob_get_clean(); require base_path('app/View/layouts/main.php');
});

$router->get('/login', function () use ($config, $db) {
    if (isAuthenticated()) {
        $role = $_SESSION['role_code'] ?? '';
        if ($role === 'superadmin') {
            header('Location: /superadmin/companies');
            exit;
        }
        header('Location: /company/dashboard');
        exit;
    }

    $loginValue = '';
    $errors = [];
    $authError = null;
    $multiLogistError = null;
    $devSeedPassword = null;

    try {
        $pdo = $db->connection();
        $count = $pdo->query("SELECT COUNT(*) FROM superadmin_users")->fetchColumn();
        if ((int)$count === 0) {
            $tempPassword = generatePassword(10);
            $hash = password_hash($tempPassword, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare(
                "INSERT INTO superadmin_users (name, email, password_hash, role, is_active, created_at, updated_at)
                 VALUES (:name, :email, :hash, :role, 1, NOW(), NOW())"
            );
            $stmt->execute([
                ':name' => 'Super Admin',
                ':email' => 'admin@planex.local',
                ':hash' => $hash,
                ':role' => 'admin',
            ]);
            $devSeedPassword = $tempPassword;
        }
    } catch (\Exception $e) {
        $devSeedPassword = null;
    }

    ob_start();
    require base_path('app/View/pages/login_form.php');
    $content = ob_get_clean();

    require base_path('app/View/layouts/auth-layout.php');
});

$router->post('/login', function () use ($config, $db) {
    if (isAuthenticated()) {
        header('Location: /company/dashboard');
        exit;
    }

    $loginValue = trim($_POST['login'] ?? '');
    $password = $_POST['password'] ?? '';
    $errors = [];
    $authError = null;
    $multiLogistError = null;
    $devSeedPassword = null;

    if ($loginValue === '') {
        $errors['login'] = 'Введите логин или email';
    }

    if ($password === '') {
        $errors['password'] = 'Введите пароль';
    }

    if (!empty($errors)) {
        ob_start();
        require base_path('app/View/pages/login_form.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/auth-layout.php');
        return;
    }

    try {
        $pdo = $db->connection();

        $superStmt = $pdo->prepare(
            "SELECT * FROM superadmin_users WHERE email = :login AND is_active = 1"
        );
        $superStmt->execute([':login' => $loginValue]);
        $superUser = $superStmt->fetch(PDO::FETCH_ASSOC);

        if ($superUser && password_verify($password, $superUser['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int)$superUser['id'];
            $_SESSION['role_code'] = 'superadmin';
            $_SESSION['company_id'] = null;
            $_SESSION['user_name'] = $superUser['name'] ?? 'Super Admin';
            header('Location: /superadmin/companies');
            exit;
        }

        $ownerStmt = $pdo->prepare(
            "SELECT cu.*, c.status as company_status
             FROM company_users cu
             JOIN companies c ON cu.company_id = c.id
             WHERE cu.login = :login"
        );
        $ownerStmt->execute([':login' => $loginValue]);
        $owner = $ownerStmt->fetch(PDO::FETCH_ASSOC);

        if ($owner && password_verify($password, $owner['password_hash'])) {
            if ($owner['status'] !== 'active') {
                $authError = 'Доступ к компании временно ограничен. Обратитесь к администратору.';
            } elseif (!in_array($owner['company_status'], ['active'], true)) {
                $authError = 'Доступ к компании временно ограничен. Обратитесь к администратору.';
            } else {
                session_regenerate_id(true);
                $_SESSION['user_id'] = (int)$owner['id'];
                $_SESSION['role_code'] = 'company_owner';
                $_SESSION['company_id'] = (int)$owner['company_id'];
                $_SESSION['user_name'] = $owner['full_name'];
                header('Location: /company/dashboard');
                exit;
            }
        }

        $companiesStmt = $pdo->query("SELECT id, db_identifier FROM companies WHERE status = 'active'");
        $activeCompanies = $companiesStmt->fetchAll(PDO::FETCH_ASSOC);

        $logistCandidates = [];

        foreach ($activeCompanies as $ac) {
            try {
                $localDbConfig = $config['database'];
                $localDbConfig['database'] = $ac['db_identifier'];
                $localDb = new \App\Core\Database($localDbConfig);
                $localPdo = $localDb->connection();
                applyLocalMigrations($localPdo);

                $logistStmt = $localPdo->prepare(
                    "SELECT * FROM users WHERE login = :login AND status = 'active'"
                );
                $logistStmt->execute([':login' => $loginValue]);
                $logistUser = $logistStmt->fetch(PDO::FETCH_ASSOC);

                if ($logistUser) {
                    $logistCandidates[] = [
                        'user' => $logistUser,
                        'company_id' => (int)$ac['id'],
                        'db_identifier' => $ac['db_identifier'],
                    ];
                }
            } catch (\Exception $e) {
                continue;
            }
        }

        if (count($logistCandidates) === 1) {
            $candidate = $logistCandidates[0];
            if (password_verify($password, $candidate['user']['password_hash'])) {
                session_regenerate_id(true);
                $_SESSION['user_id'] = (int)$candidate['user']['id'];
                $_SESSION['role_code'] = $candidate['user']['role_code'] ?? 'logist';
                $_SESSION['company_id'] = $candidate['company_id'];
                $_SESSION['user_name'] = $candidate['user']['full_name'];
                header('Location: /company/dashboard');
                exit;
            }
            $authError = 'Неверный логин или пароль.';
        } elseif (count($logistCandidates) > 1) {
            $multiLogistError = 'Логин найден в нескольких компаниях, обратитесь к администратору.';
        } else {
            $authError = 'Неверный логин или пароль.';
        }

    } catch (\Exception $e) {
        $authError = 'Неверный логин или пароль.';
    }

    ob_start();
    require base_path('app/View/pages/login_form.php');
    $content = ob_get_clean();
    require base_path('app/View/layouts/auth-layout.php');
});

$router->get('/logout', function () {
    session_destroy();
    header('Location: /login', true, 302);
    exit;
});

$router->get('/company/dashboard', function () use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);

    $pageTitle = 'Обзор';
    $pageContext = 'ДАШБОРД';

    $roleCode = $_SESSION['role_code'];
    $companyId = (int)$_SESSION['company_id'];
    $userId = (int)$_SESSION['user_id'];

    $companyName = null;
    $companyError = false;
    $metrics = [];
    $logistNoAccess = false;

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT id, name, db_identifier, status FROM companies WHERE id = ?');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company || $company['status'] !== 'active') {
            $companyError = true;
        } else {
            $companyName = $company['name'];
            $dbIdentifier = $company['db_identifier'];

            $localDbConfig = $config['database'];
            $localDbConfig['database'] = $dbIdentifier;
            $localDb = new \App\Core\Database($localDbConfig);
            $localPdo = $localDb->connection();
            applyLocalMigrations($localPdo);

            if ($roleCode === 'company_owner' || $roleCode === 'senior_logist') {
                $metrics = [
                    'total_contractors' => 0, 'active_contractors' => 0,
                    'total_drivers' => 0, 'active_drivers' => 0,
                    'total_vehicles' => 0, 'active_vehicles' => 0,
                    'total_vehicle_sets' => 0, 'active_vehicle_sets' => 0,
                    'total_dvbs' => 0, 'active_dvbs' => 0,
                    'total_crews' => 0, 'active_crews' => 0, 'archived_crews' => 0,
                    'total_documents' => 0,
                ];

                $tables = ['contractors', 'drivers', 'vehicle_units', 'vehicle_sets', 'driver_vehicle_blocks', 'crews'];
                foreach ($tables as $table) {
                    try {
                        $key = ($table === 'vehicle_units') ? 'vehicles' : $table;
                        $key = ($table === 'driver_vehicle_blocks') ? 'dvbs' : $key;
                        $lStmt = $localPdo->query("SELECT COUNT(*) as total, SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active FROM `{$table}`");
                        $row = $lStmt->fetch();
                        $metrics["total_{$key}"] = (int)$row['total'];
                        $metrics["active_{$key}"] = (int)$row['active'];
                    } catch (\Exception $e) { /* table may not exist */ }
                }

                try {
                    $stmtA = $localPdo->query("SELECT COUNT(*) FROM crews WHERE status = 'archived'");
                    $metrics['archived_crews'] = (int)$stmtA->fetchColumn();
                } catch (\Exception $e) {}

                try {
                    $stmtD = $localPdo->query("SELECT COUNT(*) FROM documents WHERE deleted_at IS NULL");
                    $metrics['total_documents'] = (int)$stmtD->fetchColumn();
                } catch (\Exception $e) {}
            } else {
                // logist
                $myMetrics = [
                    'my_contractors' => 0,
                    'my_drivers' => 0,
                    'my_vehicles' => 0,
                    'my_vehicle_sets' => 0,
                    'my_dvbs' => 0,
                    'my_crews' => 0,
                    'grants_count' => 0,
                ];
                $hasData = false;

                $myTables = ['contractors', 'drivers', 'vehicle_units', 'vehicle_sets', 'driver_vehicle_blocks', 'crews'];
                foreach ($myTables as $table) {
                    try {
                        $key = ($table === 'vehicle_units') ? 'vehicles' : $table;
                        $key = ($table === 'driver_vehicle_blocks') ? 'dvbs' : $key;
                        $mStmt = $localPdo->prepare("SELECT COUNT(*) FROM `{$table}` WHERE created_by_user_id = ?");
                        $mStmt->execute([$userId]);
                        $count = (int)$mStmt->fetchColumn();
                        $myMetrics["my_{$key}"] = $count;
                        if ($count > 0) $hasData = true;
                    } catch (\Exception $e) {
                        // column created_by_user_id may not exist; silently skip
                    }
                }

                try {
                    $gStmt = $localPdo->prepare("SELECT COUNT(*) FROM entity_access_grants WHERE granted_to_user_id = ? AND revoked_at IS NULL");
                    $gStmt->execute([$userId]);
                    $myMetrics['grants_count'] = (int)$gStmt->fetchColumn();
                    if ($myMetrics['grants_count'] > 0) $hasData = true;
                } catch (\Exception $e) {}

                if ($hasData) {
                    $metrics = $myMetrics;
                } else {
                    $logistNoAccess = true;
                }
            }
        }
    } catch (\Exception $e) {
        $companyError = true;
    }

    ob_start();
    require base_path('app/View/pages/company_dashboard.php');
    $content = ob_get_clean();

    require base_path('app/View/layouts/main.php');
});

$router->post('/company/access-grants/grant', function () use ($config, $db) {
    requireRole('company_owner');

    $companyId = (int)(getSessionCompanyId() ?? 0);
    $entityType = trim($_POST['entity_type'] ?? '');
    $entityId = (int)($_POST['entity_id'] ?? 0);
    $grantedToUserId = (int)($_POST['granted_to_user_id'] ?? 0);
    $redirect = $_POST['redirect'] ?? '/company/dashboard';

    $allowedEntityTypes = ['client', 'contractor', 'driver', 'vehicle_unit', 'vehicle_set', 'driver_vehicle_block', 'crew'];

    if (!in_array($entityType, $allowedEntityTypes, true)) {
        header('Location: ' . $redirect);
        exit;
    }

    if ($entityId <= 0 || $grantedToUserId <= 0 || $companyId <= 0) {
        header('Location: ' . $redirect);
        exit;
    }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company || $company['status'] !== 'active') {
            header('Location: ' . $redirect);
            exit;
        }

        $dbIdentifier = $company['db_identifier'];
        $localDbConfig = $config['database'];
        $localDbConfig['database'] = $dbIdentifier;
        $localDb = new \App\Core\Database($localDbConfig);
        $localPdo = $localDb->connection();
                applyLocalMigrations($localPdo);

        try {
            $localPdo->query("SELECT 1 FROM entity_access_grants LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $migrationSql = file_get_contents(base_path('database/migrations-local/009_create_entity_access_grants.sql'));
            $localPdo->exec($migrationSql);
        }

        $accessLevel = trim($_POST['access_level'] ?? 'view');
        if (!in_array($accessLevel, ['view', 'edit'], true)) { $accessLevel = 'view'; }
        $comment = trim($_POST['comment'] ?? '');

        // Check for existing grant (update if exists, insert if not)
        $existingStmt = $localPdo->prepare(
            'SELECT id, access_level FROM entity_access_grants
             WHERE entity_type = ? AND entity_id = ? AND granted_to_user_id = ? AND revoked_at IS NULL'
        );
        $existingStmt->execute([$entityType, $entityId, $grantedToUserId]);
        $existing = $existingStmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            $update = $localPdo->prepare(
                'UPDATE entity_access_grants SET access_level = ?, comment = ?, updated_at = NOW() WHERE id = ?'
            );
            $update->execute([$accessLevel, $comment, $existing['id']]);
        } else {
            $insert = $localPdo->prepare(
                'INSERT INTO entity_access_grants (entity_type, entity_id, granted_to_user_id, granted_by_user_id, access_level, comment)
                 VALUES (:entity_type, :entity_id, :granted_to_user_id, :granted_by_user_id, :access_level, :comment)'
            );
            $insert->execute([
                ':entity_type'        => $entityType,
                ':entity_id'          => $entityId,
                ':granted_to_user_id' => $grantedToUserId,
                ':granted_by_user_id' => (int)$_SESSION['user_id'],
                ':access_level'       => $accessLevel,
                ':comment'            => $comment !== '' ? $comment : null,
            ]);
        }
    } catch (\Exception $e) {
        if ($e->getCode() != 23000) {
            error_log('Access grant error: ' . $e->getMessage());
        }
    }

    header('Location: ' . $redirect);
    exit;
});

// Revoke access grant
$router->post('/company/access-grants/{id}/revoke', function ($id) use ($config, $db) {
    requireRole('company_owner');

    $companyId = (int)(getSessionCompanyId() ?? 0);
    $redirect = $_GET['redirect'] ?? '/company/dashboard';

    if ($companyId <= 0) { header('Location: ' . $redirect); exit; }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company || $company['status'] !== 'active') { header('Location: ' . $redirect); exit; }

        $dbIdentifier = $company['db_identifier'];
        $localDbConfig = $config['database']; $localDbConfig['database'] = $dbIdentifier;
        $localDb = new \App\Core\Database($localDbConfig); $localPdo = $localDb->connection();
        applyLocalMigrations($localPdo);

        $update = $localPdo->prepare('UPDATE entity_access_grants SET revoked_at = NOW(), revoked_by_user_id = ? WHERE id = ?');
        $update->execute([(int)$_SESSION['user_id'], (int)$id]);
    } catch (\Exception $e) {}

    header('Location: ' . $redirect);
    exit;
});

// ============================================================
// BLOCK D5: Contractor assignment management
// ============================================================

$router->get('/company/contractor-assignments', function () use ($config, $db) {
    requireRole('company_owner');
    header('Location: /company/responsible-assignments', true, 302);
    exit;

    requireRole('company_owner');
    $pageTitle = 'Привязка перевозчиков';
    $pageContext = 'Перевозчики › Компания';

    $companyId = (int)(getSessionCompanyId() ?? 0);
    $successMessage = null;
    $formError = null;

    if ($companyId <= 0) {
        $company = null;
        $assignments = [];
        $logists = [];
        $dbError = null;
        ob_start();
        require base_path('app/View/pages/company_contractor_assignments.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
        return;
    }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company || $company['status'] !== 'active') {
            $company = $company ?? null;
            $assignments = [];
            $logists = [];
            $dbError = null;
            ob_start();
            require base_path('app/View/pages/company_contractor_assignments.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $pageContext = 'Перевозчики › Компания: ' . $company['name'];

        $dbIdentifier = $company['db_identifier'];
        $localDbConfig = $config['database'];
        $localDbConfig['database'] = $dbIdentifier;
        $localDb = new \App\Core\Database($localDbConfig);
        $localPdo = $localDb->connection();
        applyLocalMigrations($localPdo);

        try { $localPdo->query("SELECT 1 FROM contractors LIMIT 1")->fetch(); }
        catch (\Exception $e) { $localPdo->exec(file_get_contents(base_path('database/migrations-local/003_create_company_contractors.sql'))); }

        // Load all contractors with their current owner, crew/driver/vehicle counts
        $assignmentsStmt = $localPdo->prepare(
            "SELECT c.id, c.name, c.inn, c.status,
                    c.created_by_user_id AS logist_id,
                    u.full_name AS logist_name,
                    u.login AS logist_login,
                    COUNT(DISTINCT cr.id) AS crew_count,
                    COUNT(DISTINCT dvb.driver_id) AS driver_count,
                    COUNT(DISTINCT dvb.vehicle_set_id) AS vehicle_count
             FROM contractors c
             LEFT JOIN users u ON c.created_by_user_id = u.id
             LEFT JOIN crews cr ON cr.contractor_id = c.id AND cr.status != 'archived'
             LEFT JOIN driver_vehicle_blocks dvb ON cr.driver_vehicle_block_id = dvb.id
             WHERE c.status != 'archived'
             GROUP BY c.id, c.name, c.inn, c.status, c.created_by_user_id, u.full_name, u.login
             ORDER BY c.name"
        );
        $assignmentsStmt->execute();
        $assignments = $assignmentsStmt->fetchAll(PDO::FETCH_ASSOC);

        // Load active logists for dropdown
        $logists = $localPdo->query(
            "SELECT id, full_name, login FROM users
             WHERE role_code = 'logist' AND status = 'active'
             ORDER BY full_name"
        )->fetchAll(PDO::FETCH_ASSOC);

        $dbError = null;
    } catch (\Exception $e) {
        $company = $company ?? null;
        $assignments = [];
        $logists = [];
        $dbError = 'Не удалось загрузить данные: ' . $e->getMessage();
    }

    ob_start();
    require base_path('app/View/pages/company_contractor_assignments.php');
    $content = ob_get_clean();
    require base_path('app/View/layouts/main.php');
});

$router->post('/company/contractor-assignments/{id}/assign', function ($id) use ($config, $db) {
    requireRole('company_owner');

    $companyId = (int)(getSessionCompanyId() ?? 0);
    $contractorId = (int)$id;
    $newLogistId = (int)($_POST['new_logist_id'] ?? 0);
    $redirect = '/company/contractor-assignments';

    if ($companyId <= 0 || $contractorId <= 0 || $newLogistId <= 0) {
        header('Location: ' . $redirect);
        exit;
    }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company || $company['status'] !== 'active') {
            header('Location: ' . $redirect);
            exit;
        }

        $dbIdentifier = $company['db_identifier'];
        $localDbConfig = $config['database'];
        $localDbConfig['database'] = $dbIdentifier;
        $localDb = new \App\Core\Database($localDbConfig);
        $localPdo = $localDb->connection();
        applyLocalMigrations($localPdo);

        // Validate contractor exists
        $cStmt = $localPdo->prepare('SELECT * FROM contractors WHERE id = ?');
        $cStmt->execute([$contractorId]);
        $contractor = $cStmt->fetch(PDO::FETCH_ASSOC);
        if (!$contractor) {
            header('Location: ' . $redirect);
            exit;
        }

        // Validate new logist
        $lStmt = $localPdo->prepare(
            "SELECT id, full_name FROM users WHERE id = ? AND role_code = 'logist' AND status = 'active'"
        );
        $lStmt->execute([$newLogistId]);
        $newLogist = $lStmt->fetch(PDO::FETCH_ASSOC);
        if (!$newLogist) {
            header('Location: ' . $redirect);
            exit;
        }

        $oldLogistId = (int)($contractor['created_by_user_id'] ?? 0);

        // If already assigned to this logist, skip
        if ($oldLogistId === $newLogistId) {
            header('Location: ' . $redirect . '?msg=already_assigned');
            exit;
        }

        // Collect cascade entities
        // crews for this contractor -> blocks -> drivers -> vehicle_sets
        $cascadeStmt = $localPdo->prepare(
            "SELECT c.id AS crew_id, c.driver_vehicle_block_id AS block_id,
                    dvb.driver_id, dvb.vehicle_set_id
             FROM crews c
             JOIN driver_vehicle_blocks dvb ON c.driver_vehicle_block_id = dvb.id
             WHERE c.contractor_id = ? AND c.status != 'archived'"
        );
        $cascadeStmt->execute([$contractorId]);
        $cascadeRows = $cascadeStmt->fetchAll(PDO::FETCH_ASSOC);

        $crewIds = [];
        $blockIds = [];
        $driverIds = [];
        $vehicleSetIds = [];
        foreach ($cascadeRows as $row) {
            $crewIds[] = (int)$row['crew_id'];
            $blockIds[] = (int)$row['block_id'];
            $driverIds[] = (int)$row['driver_id'];
            $vehicleSetIds[] = (int)$row['vehicle_set_id'];
        }
        $uniqueBlockIds = array_unique($blockIds);
        $uniqueDriverIds = array_unique($driverIds);
        $uniqueVehicleSetIds = array_unique($vehicleSetIds);

        $role = 'logist';
        $changedByUserId = (int)$_SESSION['user_id'];
        $changedByRole = $_SESSION['role_code'] ?? 'company_owner';

        $summary = [
            'reassigned_contractors' => 1,
            'reassigned_crews' => 0,
            'reassigned_blocks' => 0,
            'reassigned_drivers' => 0,
            'reassigned_vehicle_sets' => 0,
        ];

        $localPdo->beginTransaction();
        try {
            // 1. Reassign contractor
            $upd = $localPdo->prepare(
                "UPDATE contractors SET created_by_user_id = ?, created_by_role = ?, updated_by_user_id = ?, updated_by_role = ? WHERE id = ?"
            );
            $upd->execute([$newLogistId, $role, $changedByUserId, $changedByRole, $contractorId]);

            // 2. Reassign crews
            if (!empty($crewIds)) {
                $placeholders = implode(',', array_fill(0, count($crewIds), '?'));
                $params = [$newLogistId, $role, $changedByUserId, $changedByRole];
                $params = array_merge($params, $crewIds);
                $localPdo->prepare(
                    "UPDATE crews SET created_by_user_id = ?, created_by_role = ?, updated_by_user_id = ?, updated_by_role = ? WHERE id IN ($placeholders)"
                )->execute($params);
                $summary['reassigned_crews'] = count($crewIds);
            }

            // 3. Reassign driver_vehicle_blocks (all blocks from this contractor's crews)
            if (!empty($uniqueBlockIds)) {
                $placeholders = implode(',', array_fill(0, count($uniqueBlockIds), '?'));
                $params = [$newLogistId, $role, $changedByUserId, $changedByRole];
                $params = array_merge($params, array_values($uniqueBlockIds));
                $localPdo->prepare(
                    "UPDATE driver_vehicle_blocks SET created_by_user_id = ?, created_by_role = ?, updated_by_user_id = ?, updated_by_role = ? WHERE id IN ($placeholders)"
                )->execute($params);
                $summary['reassigned_blocks'] = count($uniqueBlockIds);
            }

            // 4. Reassign ALL drivers (full context transfer)
            if (!empty($uniqueDriverIds)) {
                $placeholders = implode(',', array_fill(0, count($uniqueDriverIds), '?'));
                $params = [$newLogistId, $role, $changedByUserId, $changedByRole];
                $params = array_merge($params, array_values($uniqueDriverIds));
                $localPdo->prepare(
                    "UPDATE drivers SET created_by_user_id = ?, created_by_role = ?, updated_by_user_id = ?, updated_by_role = ? WHERE id IN ($placeholders)"
                )->execute($params);
                $summary['reassigned_drivers'] = count($uniqueDriverIds);
            }

            // 5. Reassign ALL vehicle_sets (full context transfer)
            if (!empty($uniqueVehicleSetIds)) {
                $placeholders = implode(',', array_fill(0, count($uniqueVehicleSetIds), '?'));
                $params = [$newLogistId, $role, $changedByUserId, $changedByRole];
                $params = array_merge($params, array_values($uniqueVehicleSetIds));
                $localPdo->prepare(
                    "UPDATE vehicle_sets SET created_by_user_id = ?, created_by_role = ?, updated_by_user_id = ?, updated_by_role = ? WHERE id IN ($placeholders)"
                )->execute($params);
                $summary['reassigned_vehicle_sets'] = count($uniqueVehicleSetIds);
            }

            // 6. Revoke existing contractor grants for this contractor
            $revokeGrantsStmt = $localPdo->prepare(
                "UPDATE entity_access_grants
                 SET revoked_at = NOW(), revoked_by_user_id = ?
                 WHERE entity_type = 'contractor'
                   AND entity_id = ?
                   AND revoked_at IS NULL"
            );
            $revokeGrantsStmt->execute([$changedByUserId, $contractorId]);

            // 7. Revoke cascade grants
            $grantSource = 'contractor_cascade:' . $contractorId;
            $revokeCascadeStmt = $localPdo->prepare(
                "UPDATE entity_access_grants
                 SET revoked_at = NOW(), revoked_by_user_id = ?
                 WHERE grant_source = ?
                   AND revoked_at IS NULL"
            );
            $revokeCascadeStmt->execute([$changedByUserId, $grantSource]);

            // 8. Record history
            $historyStmt = $localPdo->prepare(
                "INSERT INTO contractor_assignment_history
                    (contractor_id, old_logist_id, new_logist_id, changed_by_user_id, changed_by_role, summary_json, comment)
                 VALUES (?, ?, ?, ?, ?, ?, ?)"
            );
            $historyStmt->execute([
                $contractorId,
                $oldLogistId > 0 ? $oldLogistId : null,
                $newLogistId,
                $changedByUserId,
                $changedByRole,
                json_encode($summary, JSON_UNESCAPED_UNICODE),
                'Перепривязка перевозчика «' . ($contractor['name'] ?? '') . '» от логиста #' . ($oldLogistId ?: 'нет') . ' к логисту «' . $newLogist['full_name'] . '"',
            ]);

            $localPdo->commit();
        } catch (\Exception $e) {
            $localPdo->rollBack();
            error_log('Contractor assign error: ' . $e->getMessage());
        }
    } catch (\Exception $e) {
        error_log('Contractor assign setup error: ' . $e->getMessage());
    }

    header('Location: ' . $redirect . '?msg=assigned');
    exit;
});

// ============================================================
// BLOCK E2: Route executors — UI facade list
// ============================================================

$router->get('/company/route-executors', function () use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);
    $pageTitle = 'Исполнители рейса';
    $pageContext = 'Исполнители рейса › Компания';

    $companyId = (int)(getSessionCompanyId() ?? 0);

    if ($companyId <= 0) {
        $company = null;
        $executors = [];
        $dbError = null;

        ob_start();
        require base_path('app/View/pages/company_route_executors.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
        return;
    }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company) {
            $company = null;
            $executors = [];
            $dbError = null;

            ob_start();
            require base_path('app/View/pages/company_route_executors.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $pageContext = 'Исполнители рейса › Компания: ' . $company['name'];

        if ($company['status'] !== 'active') {
            $executors = [];
            $dbError = null;

            ob_start();
            require base_path('app/View/pages/company_route_executors.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $dbIdentifier = $company['db_identifier'];
        $localDbConfig = $config['database'];
        $localDbConfig['database'] = $dbIdentifier;
        $localDb = new \App\Core\Database($localDbConfig);
        $localPdo = $localDb->connection();
        applyLocalMigrations($localPdo);

        // Ensure crews table exists
        try {
            $localPdo->query("SELECT 1 FROM crews LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $migrationSql = file_get_contents(base_path('database/migrations-local/006_create_company_crews.sql'));
            $localPdo->exec($migrationSql);
        }

        // Ensure created_by columns exist
        try {
            $localPdo->query("SELECT created_by_user_id FROM crews LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $localPdo->exec("ALTER TABLE crews ADD COLUMN created_by_user_id INT UNSIGNED DEFAULT NULL, ADD COLUMN created_by_role VARCHAR(20) DEFAULT NULL");
        }

        // Ensure entity_access_grants table exists
        try {
            $localPdo->query("SELECT 1 FROM entity_access_grants LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $migrationSql = file_get_contents(base_path('database/migrations-local/009_create_entity_access_grants.sql'));
            $localPdo->exec($migrationSql);
        }

        $isLogist = ($_SESSION['role_code'] ?? '') === 'logist';
        $hasGrantsButAllArchived = false;

        $baseSql = "SELECT c.id AS crew_id,
                           c.status AS crew_status,
                           c.created_at AS crew_created_at,
                           c.created_by_user_id,
                           c.created_by_role,
                           ct.id AS contractor_id,
                           ct.created_by_user_id AS contractor_created_by_user_id,
                           ct.name AS contractor_name,
                           d.id AS driver_id,
                           d.created_by_user_id AS driver_created_by_user_id,
                           d.full_name AS driver_name,
                           d.phone AS driver_phone,
                           dvb.id AS driver_vehicle_block_id,
                           dvb.created_by_user_id AS dvb_created_by_user_id,
                           vs.id AS vehicle_set_id,
                           vs.created_by_user_id AS vehicle_set_created_by_user_id,
                           vs.set_type,
                           CONCAT(vu1.plate_number, IFNULL(CONCAT(' + ', vu2.plate_number), '')) AS plates,
                           vu1.plate_number AS primary_plate,
                           u.full_name AS created_by_name
                    FROM crews c
                    JOIN contractors ct ON c.contractor_id = ct.id
                    JOIN driver_vehicle_blocks dvb ON c.driver_vehicle_block_id = dvb.id
                    JOIN drivers d ON dvb.driver_id = d.id
                    JOIN vehicle_sets vs ON dvb.vehicle_set_id = vs.id
                    LEFT JOIN vehicle_units vu1 ON vs.primary_vehicle_unit_id = vu1.id
                    LEFT JOIN vehicle_units vu2 ON vs.secondary_vehicle_unit_id = vu2.id
                    LEFT JOIN users u ON c.created_by_user_id = u.id";

        if ($isLogist) {
            $userId = (int)$_SESSION['user_id'];
            $sql = $baseSql . " WHERE (
                    c.created_by_user_id = ?
                    OR c.id IN (
                        SELECT entity_id FROM entity_access_grants
                        WHERE entity_type = 'crew'
                          AND granted_to_user_id = ?
                          AND access_level IN ('view','edit')
                          AND revoked_at IS NULL
                    )
                    OR (
                        (ct.created_by_user_id = ? OR ct.id IN (
                            SELECT entity_id FROM entity_access_grants
                            WHERE entity_type = 'contractor'
                              AND granted_to_user_id = ?
                              AND access_level IN ('view','edit')
                              AND revoked_at IS NULL
                        ))
                        AND (
                            dvb.created_by_user_id = ? OR dvb.id IN (
                                SELECT entity_id FROM entity_access_grants
                                WHERE entity_type = 'driver_vehicle_block'
                                  AND granted_to_user_id = ?
                                  AND access_level IN ('view','edit')
                                  AND revoked_at IS NULL
                            )
                            OR (
                                (d.created_by_user_id = ? OR d.id IN (
                                    SELECT entity_id FROM entity_access_grants
                                    WHERE entity_type = 'driver'
                                      AND granted_to_user_id = ?
                                      AND access_level IN ('view','edit')
                                      AND revoked_at IS NULL
                                ))
                                AND
                                (vs.created_by_user_id = ? OR vs.id IN (
                                    SELECT entity_id FROM entity_access_grants
                                    WHERE entity_type = 'vehicle_set'
                                      AND granted_to_user_id = ?
                                      AND access_level IN ('view','edit')
                                      AND revoked_at IS NULL
                                ))
                            )
                        )
                    )
                )
                ORDER BY c.created_at DESC";
            $stmt = $localPdo->prepare($sql);
            $stmt->execute([$userId, $userId, $userId, $userId, $userId, $userId, $userId, $userId, $userId, $userId]);
            $executors = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($executors)) {
                $grantCountStmt = $localPdo->prepare(
                    "SELECT COUNT(*) FROM entity_access_grants WHERE entity_type = 'crew' AND granted_to_user_id = ? AND revoked_at IS NULL"
                );
                $grantCountStmt->execute([$userId]);
                $hasGrantsButAllArchived = ((int)$grantCountStmt->fetchColumn() > 0);
            }
        } else {
            $sql = $baseSql . " ORDER BY c.created_at DESC";
            $executors = $localPdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        }

        $dbError = null;
    } catch (\Exception $e) {
        $company = $company ?? null;
        $executors = [];
        $hasGrantsButAllArchived = false;
        $dbError = 'Не удалось подключиться к базе данных компании.';
    }

    ob_start();
    require base_path('app/View/pages/company_route_executors.php');
    $content = ob_get_clean();
    require base_path('app/View/layouts/main.php');
});

// ============================================================
// BLOCK E3: Route executors — CREATE
// ============================================================

$router->get('/company/route-executors/create', function () use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);
    $pageTitle = 'Создать исполнителя рейса';
    $pageContext = 'Исполнители рейса › Компания';

    $companyId = (int)(getSessionCompanyId() ?? 0);

    if ($companyId <= 0) {
        $company = null;
        $success = false; $errors = []; $old = []; $formError = null; $createdExecutor = null;
        $blockingNotices = []; $contractors = []; $drivers = []; $vehicleSets = [];
        ob_start(); require base_path('app/View/pages/company_route_executors_create.php');
        $content = ob_get_clean(); require base_path('app/View/layouts/main.php');
        return;
    }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company) {
            $company = null;
            $success = false; $errors = []; $old = []; $formError = null; $createdExecutor = null;
            $blockingNotices = []; $contractors = []; $drivers = []; $vehicleSets = [];
            ob_start(); require base_path('app/View/pages/company_route_executors_create.php');
            $content = ob_get_clean(); require base_path('app/View/layouts/main.php');
            return;
        }

        $pageContext = 'Исполнители рейса › Компания: ' . $company['name'];

        $success = false; $errors = []; $old = []; $formError = null; $createdExecutor = null;

        if ($company['status'] !== 'active') {
            $blockingNotices = []; $contractors = []; $drivers = []; $vehicleSets = [];
        } else {
            $dbIdentifier = $company['db_identifier'];
            $localDbConfig = $config['database']; $localDbConfig['database'] = $dbIdentifier;
            $localDb = new \App\Core\Database($localDbConfig); $localPdo = $localDb->connection();
            applyLocalMigrations($localPdo);

            try { $localPdo->query("SELECT 1 FROM crews LIMIT 1")->fetch(); }
            catch (\Exception $e) { $localPdo->exec(file_get_contents(base_path('database/migrations-local/006_create_company_crews.sql'))); }

            try { $localPdo->query("SELECT created_by_user_id FROM crews LIMIT 1")->fetch(); }
            catch (\Exception $e) { $localPdo->exec("ALTER TABLE crews ADD COLUMN created_by_user_id INT UNSIGNED DEFAULT NULL, ADD COLUMN created_by_role VARCHAR(20) DEFAULT NULL"); }

            try { $localPdo->query("SELECT 1 FROM entity_access_grants LIMIT 1")->fetch(); }
            catch (\Exception $e) { $localPdo->exec(file_get_contents(base_path('database/migrations-local/009_create_entity_access_grants.sql'))); }

            try { $localPdo->query("SELECT 1 FROM driver_vehicle_blocks LIMIT 1")->fetch(); }
            catch (\Exception $e) { $localPdo->exec(file_get_contents(base_path('database/migrations-local/018_create_driver_vehicle_blocks.sql'))); }

            $isLogist = ($_SESSION['role_code'] ?? '') === 'logist';
            $userId = (int)$_SESSION['user_id'];

            // Load contractors for dropdown
            if ($isLogist) {
                $cStmt = $localPdo->prepare("SELECT id, name, inn FROM contractors WHERE status = 'active' AND (created_by_user_id = ? OR id IN (SELECT entity_id FROM entity_access_grants WHERE entity_type = 'contractor' AND granted_to_user_id = ? AND access_level IN ('view','edit') AND revoked_at IS NULL)) ORDER BY name");
                $cStmt->execute([$userId, $userId]);
                $contractors = $cStmt->fetchAll(PDO::FETCH_ASSOC);
            } else {
                $contractors = $localPdo->query("SELECT id, name, inn FROM contractors WHERE status = 'active' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
            }

            // Load drivers for dropdown
            if ($isLogist) {
                $dStmt = $localPdo->prepare("SELECT id, full_name, phone FROM drivers WHERE status = 'active' AND (created_by_user_id = ? OR id IN (SELECT entity_id FROM entity_access_grants WHERE entity_type = 'driver' AND granted_to_user_id = ? AND access_level IN ('view','edit') AND revoked_at IS NULL)) ORDER BY full_name");
                $dStmt->execute([$userId, $userId]);
                $drivers = $dStmt->fetchAll(PDO::FETCH_ASSOC);
            } else {
                $drivers = $localPdo->query("SELECT id, full_name, phone FROM drivers WHERE status = 'active' ORDER BY full_name")->fetchAll(PDO::FETCH_ASSOC);
            }

            // Load vehicle sets for dropdown
            if ($isLogist) {
                $vsStmt = $localPdo->prepare("SELECT vs.id, vs.set_type, vu1.plate_number AS primary_plate, vu2.plate_number AS secondary_plate FROM vehicle_sets vs LEFT JOIN vehicle_units vu1 ON vs.primary_vehicle_unit_id = vu1.id LEFT JOIN vehicle_units vu2 ON vs.secondary_vehicle_unit_id = vu2.id WHERE vs.status = 'active' AND (vs.created_by_user_id = ? OR vs.id IN (SELECT entity_id FROM entity_access_grants WHERE entity_type = 'vehicle_set' AND granted_to_user_id = ? AND access_level IN ('view','edit') AND revoked_at IS NULL)) ORDER BY vs.set_type");
                $vsStmt->execute([$userId, $userId]);
                $vehicleSets = $vsStmt->fetchAll(PDO::FETCH_ASSOC);
            } else {
                $vehicleSets = $localPdo->query("SELECT vs.id, vs.set_type, vu1.plate_number AS primary_plate, vu2.plate_number AS secondary_plate FROM vehicle_sets vs LEFT JOIN vehicle_units vu1 ON vs.primary_vehicle_unit_id = vu1.id LEFT JOIN vehicle_units vu2 ON vs.secondary_vehicle_unit_id = vu2.id WHERE vs.status = 'active' ORDER BY vs.set_type")->fetchAll(PDO::FETCH_ASSOC);
            }

            $blockingNotices = [];
            if (empty($contractors)) {
                $blockingNotices[] = ['message' => 'Сначала создайте подрядчика.', 'link' => '/company/contractors/create', 'action' => 'Создать подрядчика'];
            }
            if (empty($drivers)) {
                $blockingNotices[] = ['message' => 'Сначала создайте водителя.', 'link' => '/company/drivers', 'action' => 'Создать водителя'];
            }
            if (empty($vehicleSets)) {
                $blockingNotices[] = ['message' => 'Сначала создайте транспортный комплект.', 'link' => '/company/vehicle-sets', 'action' => 'Создать ТС'];
            }
        }
    } catch (\Exception $e) {
        $company = null;
        $success = false; $errors = []; $old = []; $formError = 'Ошибка загрузки данных: ' . $e->getMessage();
        $createdExecutor = null; $blockingNotices = []; $contractors = []; $drivers = []; $vehicleSets = [];
    }

    ob_start();
    require base_path('app/View/pages/company_route_executors_create.php');
    $content = ob_get_clean();
    require base_path('app/View/layouts/main.php');
});

$router->post('/company/route-executors/create', function () use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);
    $pageTitle = 'Создать исполнителя рейса';
    $pageContext = 'Исполнители рейса › Компания';

    $companyId = (int)(getSessionCompanyId() ?? 0);
    $errors = [];
    $old = $_POST;
    $formError = null;
    $success = false;
    $createdExecutor = null;
    $blockingNotices = [];
    $contractors = [];
    $drivers = [];
    $vehicleSets = [];

    if ($companyId <= 0) {
        $company = null;
        $formError = 'Компания не найдена';
        ob_start(); require base_path('app/View/pages/company_route_executors_create.php');
        $content = ob_get_clean(); require base_path('app/View/layouts/main.php');
        return;
    }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company) {
            $company = null;
            $formError = 'Компания не найдена';
            ob_start(); require base_path('app/View/pages/company_route_executors_create.php');
            $content = ob_get_clean(); require base_path('app/View/layouts/main.php');
            return;
        }

        $pageContext = 'Исполнители рейса › Компания: ' . $company['name'];

        if ($company['status'] !== 'active') {
            $formError = 'Создание исполнителя рейса недоступно';
            ob_start(); require base_path('app/View/pages/company_route_executors_create.php');
            $content = ob_get_clean(); require base_path('app/View/layouts/main.php');
            return;
        }

        $dbIdentifier = $company['db_identifier'];
        $localDbConfig = $config['database'];
        $localDbConfig['database'] = $dbIdentifier;
        $localDb = new \App\Core\Database($localDbConfig);
        $localPdo = $localDb->connection();
        applyLocalMigrations($localPdo);

        try {
            $localPdo->query("SELECT 1 FROM crews LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $migrationSql = file_get_contents(base_path('database/migrations-local/006_create_company_crews.sql'));
            $localPdo->exec($migrationSql);
        }

        try {
            $localPdo->query("SELECT 1 FROM driver_vehicle_blocks LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $migrationSql = file_get_contents(base_path('database/migrations-local/018_create_driver_vehicle_blocks.sql'));
            $localPdo->exec($migrationSql);
        }

        $isLogist = ($_SESSION['role_code'] ?? '') === 'logist';
        $userId = (int)$_SESSION['user_id'];
        $userRole = $_SESSION['role_code'];

        // Reload dropdowns for re-render
        if ($isLogist) {
            $cStmt = $localPdo->prepare("SELECT id, name, inn FROM contractors WHERE status = 'active' AND (created_by_user_id = ? OR id IN (SELECT entity_id FROM entity_access_grants WHERE entity_type = 'contractor' AND granted_to_user_id = ? AND access_level IN ('view','edit') AND revoked_at IS NULL)) ORDER BY name");
            $cStmt->execute([$userId, $userId]);
            $contractors = $cStmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $contractors = $localPdo->query("SELECT id, name, inn FROM contractors WHERE status = 'active' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
        }

        if ($isLogist) {
            $dStmt = $localPdo->prepare("SELECT id, full_name, phone FROM drivers WHERE status = 'active' AND (created_by_user_id = ? OR id IN (SELECT entity_id FROM entity_access_grants WHERE entity_type = 'driver' AND granted_to_user_id = ? AND access_level IN ('view','edit') AND revoked_at IS NULL)) ORDER BY full_name");
            $dStmt->execute([$userId, $userId]);
            $drivers = $dStmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $drivers = $localPdo->query("SELECT id, full_name, phone FROM drivers WHERE status = 'active' ORDER BY full_name")->fetchAll(PDO::FETCH_ASSOC);
        }

        if ($isLogist) {
            $vsStmt = $localPdo->prepare("SELECT vs.id, vs.set_type, vu1.plate_number AS primary_plate, vu2.plate_number AS secondary_plate FROM vehicle_sets vs LEFT JOIN vehicle_units vu1 ON vs.primary_vehicle_unit_id = vu1.id LEFT JOIN vehicle_units vu2 ON vs.secondary_vehicle_unit_id = vu2.id WHERE vs.status = 'active' AND (vs.created_by_user_id = ? OR vs.id IN (SELECT entity_id FROM entity_access_grants WHERE entity_type = 'vehicle_set' AND granted_to_user_id = ? AND access_level IN ('view','edit') AND revoked_at IS NULL)) ORDER BY vs.set_type");
            $vsStmt->execute([$userId, $userId]);
            $vehicleSets = $vsStmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $vehicleSets = $localPdo->query("SELECT vs.id, vs.set_type, vu1.plate_number AS primary_plate, vu2.plate_number AS secondary_plate FROM vehicle_sets vs LEFT JOIN vehicle_units vu1 ON vs.primary_vehicle_unit_id = vu1.id LEFT JOIN vehicle_units vu2 ON vs.secondary_vehicle_unit_id = vu2.id WHERE vs.status = 'active' ORDER BY vs.set_type")->fetchAll(PDO::FETCH_ASSOC);
        }

        $blockingNotices = [];
        if (empty($contractors)) {
            $blockingNotices[] = ['message' => 'Сначала создайте подрядчика.', 'link' => '/company/contractors/create', 'action' => 'Создать подрядчика'];
        }
        if (empty($drivers)) {
            $blockingNotices[] = ['message' => 'Сначала создайте водителя.', 'link' => '/company/drivers', 'action' => 'Создать водителя'];
        }
        if (empty($vehicleSets)) {
            $blockingNotices[] = ['message' => 'Сначала создайте транспортный комплект.', 'link' => '/company/vehicle-sets', 'action' => 'Создать ТС'];
        }

        $contractorId = trim($_POST['contractor_id'] ?? '');
        $driverId = trim($_POST['driver_id'] ?? '');
        $vehicleSetId = trim($_POST['vehicle_set_id'] ?? '');
        $comments = trim($_POST['comments'] ?? '');

        // Validation
        if ($contractorId === '') {
            $errors['contractor_id'] = 'Обязательное поле';
        }
        if ($driverId === '') {
            $errors['driver_id'] = 'Обязательное поле';
        }
        if ($vehicleSetId === '') {
            $errors['vehicle_set_id'] = 'Обязательное поле';
        }

        // Backend access validation for logist
        if (empty($errors) && $isLogist) {
            // Check contractor access
            $cCheck = $localPdo->prepare("SELECT created_by_user_id FROM contractors WHERE id = ? AND status = 'active'");
            $cCheck->execute([(int)$contractorId]);
            $cOwner = $cCheck->fetchColumn();
            if ($cOwner === false) {
                $errors['contractor_id'] = 'Подрядчик не найден или неактивен.';
            } elseif ((int)$cOwner !== $userId) {
                $cGrant = $localPdo->prepare("SELECT COUNT(*) FROM entity_access_grants WHERE entity_type = 'contractor' AND entity_id = ? AND granted_to_user_id = ? AND access_level IN ('view','edit') AND revoked_at IS NULL");
                $cGrant->execute([(int)$contractorId, $userId]);
                if ($cGrant->fetchColumn() == 0) {
                    $errors['contractor_id'] = 'Подрядчик недоступен.';
                }
            }

            // Check driver access
            $dCheck = $localPdo->prepare("SELECT created_by_user_id FROM drivers WHERE id = ? AND status = 'active'");
            $dCheck->execute([(int)$driverId]);
            $dOwner = $dCheck->fetchColumn();
            if ($dOwner === false) {
                $errors['driver_id'] = 'Водитель не найден или неактивен.';
            } elseif ((int)$dOwner !== $userId) {
                $dGrant = $localPdo->prepare("SELECT COUNT(*) FROM entity_access_grants WHERE entity_type = 'driver' AND entity_id = ? AND granted_to_user_id = ? AND access_level IN ('view','edit') AND revoked_at IS NULL");
                $dGrant->execute([(int)$driverId, $userId]);
                if ($dGrant->fetchColumn() == 0) {
                    $errors['driver_id'] = 'Водитель недоступен.';
                }
            }

            // Check vehicle_set access
            $vsCheck = $localPdo->prepare("SELECT created_by_user_id FROM vehicle_sets WHERE id = ? AND status = 'active'");
            $vsCheck->execute([(int)$vehicleSetId]);
            $vsOwner = $vsCheck->fetchColumn();
            if ($vsOwner === false) {
                $errors['vehicle_set_id'] = 'Транспортный комплект не найден или неактивен.';
            } elseif ((int)$vsOwner !== $userId) {
                $vsGrant = $localPdo->prepare("SELECT COUNT(*) FROM entity_access_grants WHERE entity_type = 'vehicle_set' AND entity_id = ? AND granted_to_user_id = ? AND access_level IN ('view','edit') AND revoked_at IS NULL");
                $vsGrant->execute([(int)$vehicleSetId, $userId]);
                if ($vsGrant->fetchColumn() == 0) {
                    $errors['vehicle_set_id'] = 'Транспортный комплект недоступен.';
                }
            }
        }

        if (!empty($errors)) {
            ob_start(); require base_path('app/View/pages/company_route_executors_create.php');
            $content = ob_get_clean(); require base_path('app/View/layouts/main.php');
            return;
        }

        // --- Core logic: find or create driver_vehicle_block, then create crew ---
        $localPdo->beginTransaction();
        try {
            // The real DB schema: driver_vehicle_blocks has driver_id + vehicle_set_id.
            // crews still stores legacy driver_id + vehicle_id, where vehicle_id is the primary vehicle unit.
            $vehicleUnitStmt = $localPdo->prepare("SELECT primary_vehicle_unit_id FROM vehicle_sets WHERE id = ? AND status = 'active' LIMIT 1");
            $vehicleUnitStmt->execute([(int)$vehicleSetId]);
            $vehicleUnitId = (int)$vehicleUnitStmt->fetchColumn();

            if ($vehicleUnitId <= 0) {
                $localPdo->rollBack();
                $errors['vehicle_set_id'] = 'У выбранного транспортного комплекта не найдена основная транспортная единица.';
                ob_start(); require base_path('app/View/pages/company_route_executors_create.php');
                $content = ob_get_clean(); require base_path('app/View/layouts/main.php');
                return;
            }

            // 1. Find existing driver_vehicle_block
            $dvbStmt = $localPdo->prepare("SELECT id FROM driver_vehicle_blocks WHERE driver_id = ? AND vehicle_set_id = ? LIMIT 1");
            $dvbStmt->execute([(int)$driverId, (int)$vehicleSetId]);
            $dvbId = $dvbStmt->fetchColumn();

            if (!$dvbId) {
                // Create new driver_vehicle_block according to the current schema.
                $dvbInsert = $localPdo->prepare(
                    "INSERT INTO driver_vehicle_blocks (driver_id, vehicle_set_id, status, created_by_user_id, created_by_role)
                     VALUES (:driver_id, :vehicle_set_id, 'active', :uid, :role)"
                );
                $dvbInsert->execute([
                    ':driver_id' => (int)$driverId,
                    ':vehicle_set_id' => (int)$vehicleSetId,
                    ':uid' => $userId,
                    ':role' => $userRole,
                ]);
                $dvbId = (int)$localPdo->lastInsertId();
            }

            // 2. Check duplicate crew
            $dupStmt = $localPdo->prepare("SELECT COUNT(*) FROM crews WHERE contractor_id = ? AND driver_vehicle_block_id = ?");
            $dupStmt->execute([(int)$contractorId, $dvbId]);
            if ($dupStmt->fetchColumn() > 0) {
                $localPdo->rollBack();
                $errors['vehicle_set_id'] = 'Такой исполнитель рейса уже существует (подрядчик + водитель + ТС).';
                ob_start(); require base_path('app/View/pages/company_route_executors_create.php');
                $content = ob_get_clean(); require base_path('app/View/layouts/main.php');
                return;
            }

            // 3. Create crew. Keep legacy driver_id and vehicle_id filled for older company DBs.
            $insert = $localPdo->prepare(
                'INSERT INTO crews (contractor_id, vehicle_id, driver_id, driver_vehicle_block_id, status, comments, created_by_user_id, created_by_role)
                 VALUES (:contractor_id, :vehicle_id, :driver_id, :driver_vehicle_block_id, :status, :comments, :uid, :role)'
            );
            $insert->execute([
                ':contractor_id' => (int)$contractorId,
                ':vehicle_id' => $vehicleUnitId,
                ':driver_id' => (int)$driverId,
                ':driver_vehicle_block_id' => $dvbId,
                ':status' => 'active',
                ':comments' => $comments !== '' ? $comments : null,
                ':uid' => $userId,
                ':role' => $userRole,
            ]);

            $newId = (int)$localPdo->lastInsertId();
            $localPdo->commit();

            // Fetch created names for success display
            $ctrName = $localPdo->prepare("SELECT name FROM contractors WHERE id = ?");
            $ctrName->execute([(int)$contractorId]);
            $drvName = $localPdo->prepare("SELECT full_name FROM drivers WHERE id = ?");
            $drvName->execute([(int)$driverId]);
            $pltStmt = $localPdo->prepare("SELECT vu1.plate_number FROM vehicle_sets vs LEFT JOIN vehicle_units vu1 ON vs.primary_vehicle_unit_id = vu1.id WHERE vs.id = ?");
            $pltStmt->execute([(int)$vehicleSetId]);

            $createdExecutor = [
                'id' => $newId,
                'contractor_name' => $ctrName->fetchColumn() ?: '',
                'driver_name' => $drvName->fetchColumn() ?: '',
                'plate_number' => $pltStmt->fetchColumn() ?: '—',
            ];
            $success = true;
        } catch (\Exception $e) {
            $localPdo->rollBack();
            $formError = 'Ошибка создания исполнителя рейса: ' . $e->getMessage();
        }
    } catch (\Exception $e) {
        $company = $company ?? null;
        $formError = 'Ошибка создания исполнителя рейса: ' . $e->getMessage();
    }

    ob_start();
    require base_path('app/View/pages/company_route_executors_create.php');
    $content = ob_get_clean();
    require base_path('app/View/layouts/main.php');
});

// ============================================================
// BLOCK E3: Route executors — VIEW
// ============================================================

$router->get('/company/route-executors/{id}', function ($crewId) use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);
    $crewId = (int)$crewId;
    $pageTitle = 'Исполнитель рейса';
    $pageContext = 'Исполнители рейса › Компания';
    $entityNotFound = false;

    $companyId = (int)(getSessionCompanyId() ?? 0);

    if ($companyId <= 0) {
        $company = null;
        $crew = null;
        $dbError = null;

        ob_start();
        require base_path('app/View/pages/company_route_executor_view.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
        return;
    }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company) {
            $company = null;
            $crew = null;
            $dbError = null;

            ob_start();
            require base_path('app/View/pages/company_route_executor_view.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $pageContext = 'Исполнители рейса › Компания: ' . $company['name'];

        if ($company['status'] !== 'active') {
            $crew = null;
            $dbError = null;

            ob_start();
            require base_path('app/View/pages/company_route_executor_view.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $dbIdentifier = $company['db_identifier'];
        $localDbConfig = $config['database'];
        $localDbConfig['database'] = $dbIdentifier;
        $localDb = new \App\Core\Database($localDbConfig);
        $localPdo = $localDb->connection();
        applyLocalMigrations($localPdo);

        try {
            $localPdo->query("SELECT 1 FROM crews LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $migrationSql = file_get_contents(base_path('database/migrations-local/006_create_company_crews.sql'));
            $localPdo->exec($migrationSql);
        }

        try {
            $localPdo->query("SELECT created_by_user_id FROM crews LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $localPdo->exec("ALTER TABLE crews ADD COLUMN created_by_user_id INT UNSIGNED DEFAULT NULL, ADD COLUMN created_by_role VARCHAR(20) DEFAULT NULL");
        }

        try {
            $localPdo->query("SELECT 1 FROM entity_access_grants LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $migrationSql = file_get_contents(base_path('database/migrations-local/009_create_entity_access_grants.sql'));
            $localPdo->exec($migrationSql);
        }

        $cStmt = $localPdo->prepare(
            "SELECT c.*,
                    ct.id AS contractor_id, ct.created_by_user_id AS contractor_created_by_user_id, ct.name AS contractor_name, ct.inn AS contractor_inn,
                    d.id AS driver_id, d.created_by_user_id AS driver_created_by_user_id, d.full_name AS driver_name, d.phone AS driver_phone,
                    dvb.id AS driver_vehicle_block_id, dvb.created_by_user_id AS dvb_created_by_user_id,
                    vs.id AS vehicle_set_id, vs.created_by_user_id AS vehicle_set_created_by_user_id, vs.set_type,
                    vu1.plate_number AS primary_plate, vu1.brand AS primary_brand, vu1.model AS primary_model,
                    vu2.plate_number AS secondary_plate, vu2.brand AS secondary_brand, vu2.model AS secondary_model,
                    u.full_name AS created_by_name
             FROM crews c
             JOIN contractors ct ON c.contractor_id = ct.id
             JOIN driver_vehicle_blocks dvb ON c.driver_vehicle_block_id = dvb.id
             JOIN drivers d ON dvb.driver_id = d.id
             JOIN vehicle_sets vs ON dvb.vehicle_set_id = vs.id
             LEFT JOIN vehicle_units vu1 ON vs.primary_vehicle_unit_id = vu1.id
             LEFT JOIN vehicle_units vu2 ON vs.secondary_vehicle_unit_id = vu2.id
             LEFT JOIN users u ON c.created_by_user_id = u.id
             WHERE c.id = ?"
        );
        $cStmt->execute([$crewId]);
        $crew = $cStmt->fetch(PDO::FETCH_ASSOC);

        if (!$crew) {
            $entityNotFound = true;
        }

        // Access check for logist
        $accessDenied = null;
        if ($crew) {
            $isLogist = ($_SESSION['role_code'] ?? '') === 'logist';
            if ($isLogist) {
                $userId = (int)$_SESSION['user_id'];
                $hasGrant = false;
                $gc = $localPdo->prepare("SELECT access_level FROM entity_access_grants WHERE entity_type = 'crew' AND entity_id = ? AND granted_to_user_id = ? AND revoked_at IS NULL LIMIT 1");
                $gc->execute([$crewId, $userId]);
                $gr = $gc->fetch(PDO::FETCH_ASSOC);
                $hasGrant = ($gr && in_array($gr['access_level'], ['view', 'edit']));
                if ((int)$crew['created_by_user_id'] !== $userId && !$hasGrant) {
                    $accessDenied = 'У вас нет доступа к этой записи.';
                }
            }
        }

        $pageTitle = ($crew && !$accessDenied) ? 'Исполнитель рейса #' . $crew['id'] : 'Исполнитель рейса';

        $dbError = null;
    } catch (\Exception $e) {
        $company = $company ?? null;
        $crew = null;
        $dbError = 'Не удалось подключиться к базе данных компании.';
    }

    ob_start();
    require base_path('app/View/pages/company_route_executor_view.php');
    $content = ob_get_clean();
    require base_path('app/View/layouts/main.php');
});

// ============================================================
// BLOCK E3: Route executors — EDIT
// ============================================================

$router->get('/company/route-executors/{id}/edit', function ($crewId) use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);
    $crewId = (int)$crewId;
    $pageTitle = 'Редактировать исполнителя рейса';
    $pageContext = 'Исполнители рейса › Компания';
    $entityNotFound = false;

    $companyId = (int)(getSessionCompanyId() ?? 0);
    $success = false;
    $formError = null;
    $errors = [];

    if ($companyId <= 0) {
        $company = null;
        $crew = null; $old = []; $dbError = null;
        $contractors = []; $drivers = []; $vehicleSets = [];
        $blockingNotices = [];

        ob_start();
        require base_path('app/View/pages/company_route_executor_edit.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
        return;
    }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company) {
            $company = null;
            $crew = null; $old = []; $dbError = null;
            $contractors = []; $drivers = []; $vehicleSets = [];
            $blockingNotices = [];

            ob_start();
            require base_path('app/View/pages/company_route_executor_edit.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $pageContext = 'Исполнители рейса › Компания: ' . $company['name'];

        if ($company['status'] !== 'active') {
            $crew = null; $old = []; $dbError = null;
            $contractors = []; $drivers = []; $vehicleSets = [];
            $blockingNotices = [];

            ob_start();
            require base_path('app/View/pages/company_route_executor_edit.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $dbIdentifier = $company['db_identifier'];
        $localDbConfig = $config['database'];
        $localDbConfig['database'] = $dbIdentifier;
        $localDb = new \App\Core\Database($localDbConfig);
        $localPdo = $localDb->connection();
        applyLocalMigrations($localPdo);

        try {
            $localPdo->query("SELECT 1 FROM crews LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $migrationSql = file_get_contents(base_path('database/migrations-local/006_create_company_crews.sql'));
            $localPdo->exec($migrationSql);
        }

        // Load crew with JOIN to get driver_id and vehicle_set_id
        $cStmt = $localPdo->prepare(
            "SELECT c.*,
                    ct.created_by_user_id AS contractor_created_by_user_id,
                    d.created_by_user_id AS driver_created_by_user_id,
                    dvb.driver_id, dvb.vehicle_set_id, dvb.created_by_user_id AS dvb_created_by_user_id,
                    vs.created_by_user_id AS vehicle_set_created_by_user_id
             FROM crews c
             JOIN contractors ct ON c.contractor_id = ct.id
             JOIN driver_vehicle_blocks dvb ON c.driver_vehicle_block_id = dvb.id
             JOIN drivers d ON dvb.driver_id = d.id
             JOIN vehicle_sets vs ON dvb.vehicle_set_id = vs.id
             WHERE c.id = ?"
        );
        $cStmt->execute([$crewId]);
        $crew = $cStmt->fetch(PDO::FETCH_ASSOC);

        if (!$crew) {
            $entityNotFound = true;
            $old = [];
            $contractors = []; $drivers = []; $vehicleSets = [];
            $blockingNotices = [];
        } else {
            // Permission check
            $currentUserId = (int)($_SESSION['user_id'] ?? 0);
            $currentRole = $_SESSION['role_code'] ?? '';

            if ($currentRole !== 'company_owner' && $currentRole !== 'senior_logist') {
                if (!hasRouteExecutorAccess($localPdo, $crew, $currentUserId, 'edit')) {
                    http_response_code(403);
                    header('Content-Type: text/plain; charset=utf-8');
                    echo 'Нет доступа к редактированию исполнителя рейса.';
                    exit;
                }
            }

            $old = $crew;

            $isLogist = ($_SESSION['role_code'] ?? '') === 'logist';
            $userId = (int)$_SESSION['user_id'];

            // Load contractors
            if ($isLogist) {
                $cStmt = $localPdo->prepare("SELECT id, name, inn FROM contractors WHERE status = 'active' AND (created_by_user_id = ? OR id IN (SELECT entity_id FROM entity_access_grants WHERE entity_type = 'contractor' AND granted_to_user_id = ? AND access_level IN ('view','edit') AND revoked_at IS NULL)) ORDER BY name");
                $cStmt->execute([$userId, $userId]);
                $contractors = $cStmt->fetchAll(PDO::FETCH_ASSOC);
            } else {
                $contractors = $localPdo->query("SELECT id, name, inn FROM contractors WHERE status = 'active' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
            }

            // Load drivers (include current driver even if inactive)
            $currentDriverId = (int)($crew['driver_id'] ?? 0);
            if ($isLogist) {
                $dStmt = $localPdo->prepare(
                    "SELECT id, full_name, phone FROM drivers
                     WHERE (status = 'active'" . ($currentDriverId > 0 ? " OR id = ?" : "") . ")
                       AND (created_by_user_id = ? OR id IN (SELECT entity_id FROM entity_access_grants WHERE entity_type = 'driver' AND granted_to_user_id = ? AND access_level IN ('view','edit') AND revoked_at IS NULL))
                     ORDER BY full_name"
                );
                $params = $currentDriverId > 0 ? [$currentDriverId, $userId, $userId] : [$userId, $userId];
                $dStmt->execute($params);
                $drivers = $dStmt->fetchAll(PDO::FETCH_ASSOC);
            } else {
                $drivers = $localPdo->query(
                    "SELECT id, full_name, phone FROM drivers
                     WHERE status = 'active'" . ($currentDriverId > 0 ? " OR id = " . $currentDriverId : "") . "
                     ORDER BY full_name"
                )->fetchAll(PDO::FETCH_ASSOC);
            }

            // Load vehicle sets (include current even if inactive)
            $currentVsId = (int)($crew['vehicle_set_id'] ?? 0);
            if ($isLogist) {
                $vsStmt = $localPdo->prepare(
                    "SELECT vs.id, vs.set_type, vu1.plate_number AS primary_plate, vu2.plate_number AS secondary_plate
                     FROM vehicle_sets vs
                     LEFT JOIN vehicle_units vu1 ON vs.primary_vehicle_unit_id = vu1.id
                     LEFT JOIN vehicle_units vu2 ON vs.secondary_vehicle_unit_id = vu2.id
                     WHERE (vs.status = 'active'" . ($currentVsId > 0 ? " OR vs.id = ?" : "") . ")
                       AND (vs.created_by_user_id = ? OR vs.id IN (SELECT entity_id FROM entity_access_grants WHERE entity_type = 'vehicle_set' AND granted_to_user_id = ? AND access_level IN ('view','edit') AND revoked_at IS NULL))
                     ORDER BY vs.set_type"
                );
                $params = $currentVsId > 0 ? [$currentVsId, $userId, $userId] : [$userId, $userId];
                $vsStmt->execute($params);
                $vehicleSets = $vsStmt->fetchAll(PDO::FETCH_ASSOC);
            } else {
                $vehicleSets = $localPdo->query(
                    "SELECT vs.id, vs.set_type, vu1.plate_number AS primary_plate, vu2.plate_number AS secondary_plate
                     FROM vehicle_sets vs
                     LEFT JOIN vehicle_units vu1 ON vs.primary_vehicle_unit_id = vu1.id
                     LEFT JOIN vehicle_units vu2 ON vs.secondary_vehicle_unit_id = vu2.id
                     WHERE vs.status = 'active'" . ($currentVsId > 0 ? " OR vs.id = " . $currentVsId : "") . "
                     ORDER BY vs.set_type"
                )->fetchAll(PDO::FETCH_ASSOC);
            }

            $blockingNotices = [];
            if (empty($contractors)) {
                $blockingNotices[] = ['message' => 'Нет активных подрядчиков.', 'link' => '/company/contractors/create', 'action' => 'Создать подрядчика'];
            }
            if (empty($drivers)) {
                $blockingNotices[] = ['message' => 'Нет активных водителей.', 'link' => '/company/drivers', 'action' => 'Создать водителя'];
            }
            if (empty($vehicleSets)) {
                $blockingNotices[] = ['message' => 'Нет активных транспортных комплектов.', 'link' => '/company/vehicle-sets', 'action' => 'Создать ТС'];
            }
        }

        $dbError = null;
    } catch (\Exception $e) {
        $company = $company ?? null;
        $crew = null; $old = [];
        $dbError = 'Не удалось подключиться к базе данных компании.';
        $contractors = []; $drivers = []; $vehicleSets = [];
        $blockingNotices = [];
    }

    ob_start();
    require base_path('app/View/pages/company_route_executor_edit.php');
    $content = ob_get_clean();
    require base_path('app/View/layouts/main.php');
});

$router->post('/company/route-executors/{id}/edit', function ($crewId) use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);
    $crewId = (int)$crewId;
    $pageTitle = 'Редактировать исполнителя рейса';
    $pageContext = 'Исполнители рейса › Компания';
    $entityNotFound = false;

    $companyId = (int)(getSessionCompanyId() ?? 0);
    $errors = [];
    $old = $_POST;
    $formError = null;
    $success = false;

    if ($companyId <= 0) {
        $company = null; $crew = null; $dbError = null;
        $contractors = []; $drivers = []; $vehicleSets = [];
        $blockingNotices = [];
        $formError = 'Компания не найдена';

        ob_start();
        require base_path('app/View/pages/company_route_executor_edit.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
        return;
    }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company) {
            $company = null; $crew = null; $dbError = null;
            $contractors = []; $drivers = []; $vehicleSets = [];
            $blockingNotices = [];
            $formError = 'Компания не найдена';

            ob_start();
            require base_path('app/View/pages/company_route_executor_edit.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $pageContext = 'Исполнители рейса › Компания: ' . $company['name'];

        if ($company['status'] !== 'active') {
            $crew = null; $dbError = null;
            $contractors = []; $drivers = []; $vehicleSets = [];
            $blockingNotices = [];
            $formError = 'Редактирование исполнителя рейса недоступно';

            ob_start();
            require base_path('app/View/pages/company_route_executor_edit.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $dbIdentifier = $company['db_identifier'];
        $localDbConfig = $config['database'];
        $localDbConfig['database'] = $dbIdentifier;
        $localDb = new \App\Core\Database($localDbConfig);
        $localPdo = $localDb->connection();
        applyLocalMigrations($localPdo);

        try {
            $localPdo->query("SELECT 1 FROM crews LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $migrationSql = file_get_contents(base_path('database/migrations-local/006_create_company_crews.sql'));
            $localPdo->exec($migrationSql);
        }

        try {
            $localPdo->query("SELECT 1 FROM driver_vehicle_blocks LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $migrationSql = file_get_contents(base_path('database/migrations-local/018_create_driver_vehicle_blocks.sql'));
            $localPdo->exec($migrationSql);
        }

        $isLogist = ($_SESSION['role_code'] ?? '') === 'logist';
        $userId = (int)$_SESSION['user_id'];
        $userRole = $_SESSION['role_code'];

        // Load current crew with DV block info
        $cStmt = $localPdo->prepare(
            "SELECT c.*,
                    ct.created_by_user_id AS contractor_created_by_user_id,
                    d.created_by_user_id AS driver_created_by_user_id,
                    dvb.driver_id, dvb.vehicle_set_id, dvb.created_by_user_id AS dvb_created_by_user_id,
                    vs.created_by_user_id AS vehicle_set_created_by_user_id
             FROM crews c
             JOIN contractors ct ON c.contractor_id = ct.id
             JOIN driver_vehicle_blocks dvb ON c.driver_vehicle_block_id = dvb.id
             JOIN drivers d ON dvb.driver_id = d.id
             JOIN vehicle_sets vs ON dvb.vehicle_set_id = vs.id
             WHERE c.id = ?"
        );
        $cStmt->execute([$crewId]);
        $crew = $cStmt->fetch(PDO::FETCH_ASSOC);

        if (!$crew) {
            $entityNotFound = true;
            $dbError = null;
            $contractors = []; $drivers = []; $vehicleSets = [];
            $blockingNotices = [];

            ob_start();
            require base_path('app/View/pages/company_route_executor_edit.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        // Permission check
        $currentUserId = (int)($_SESSION['user_id'] ?? 0);
        $currentRole = $_SESSION['role_code'] ?? '';

        if ($currentRole !== 'company_owner' && $currentRole !== 'senior_logist') {
            if (!hasRouteExecutorAccess($localPdo, $crew, $currentUserId, 'edit')) {
                http_response_code(403);
                header('Content-Type: text/plain; charset=utf-8');
                echo 'Нет доступа к редактированию исполнителя рейса.';
                exit;
            }
        }

        // Reload dropdowns
        if ($isLogist) {
            $cStmt = $localPdo->prepare("SELECT id, name, inn FROM contractors WHERE status = 'active' AND (created_by_user_id = ? OR id IN (SELECT entity_id FROM entity_access_grants WHERE entity_type = 'contractor' AND granted_to_user_id = ? AND access_level IN ('view','edit') AND revoked_at IS NULL)) ORDER BY name");
            $cStmt->execute([$userId, $userId]);
            $contractors = $cStmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $contractors = $localPdo->query("SELECT id, name, inn FROM contractors WHERE status = 'active' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
        }

        $currentDriverId = (int)($crew['driver_id'] ?? 0);
        if ($isLogist) {
            $dStmt = $localPdo->prepare(
                "SELECT id, full_name, phone FROM drivers
                 WHERE (status = 'active'" . ($currentDriverId > 0 ? " OR id = ?" : "") . ")
                   AND (created_by_user_id = ? OR id IN (SELECT entity_id FROM entity_access_grants WHERE entity_type = 'driver' AND granted_to_user_id = ? AND access_level IN ('view','edit') AND revoked_at IS NULL))
                 ORDER BY full_name"
            );
            $params = $currentDriverId > 0 ? [$currentDriverId, $userId, $userId] : [$userId, $userId];
            $dStmt->execute($params);
            $drivers = $dStmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $drivers = $localPdo->query(
                "SELECT id, full_name, phone FROM drivers
                 WHERE status = 'active'" . ($currentDriverId > 0 ? " OR id = " . $currentDriverId : "") . "
                 ORDER BY full_name"
            )->fetchAll(PDO::FETCH_ASSOC);
        }

        $currentVsId = (int)($crew['vehicle_set_id'] ?? 0);
        if ($isLogist) {
            $vsStmt = $localPdo->prepare(
                "SELECT vs.id, vs.set_type, vu1.plate_number AS primary_plate, vu2.plate_number AS secondary_plate
                 FROM vehicle_sets vs
                 LEFT JOIN vehicle_units vu1 ON vs.primary_vehicle_unit_id = vu1.id
                 LEFT JOIN vehicle_units vu2 ON vs.secondary_vehicle_unit_id = vu2.id
                 WHERE (vs.status = 'active'" . ($currentVsId > 0 ? " OR vs.id = ?" : "") . ")
                   AND (vs.created_by_user_id = ? OR vs.id IN (SELECT entity_id FROM entity_access_grants WHERE entity_type = 'vehicle_set' AND granted_to_user_id = ? AND access_level IN ('view','edit') AND revoked_at IS NULL))
                 ORDER BY vs.set_type"
            );
            $params = $currentVsId > 0 ? [$currentVsId, $userId, $userId] : [$userId, $userId];
            $vsStmt->execute($params);
            $vehicleSets = $vsStmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $vehicleSets = $localPdo->query(
                "SELECT vs.id, vs.set_type, vu1.plate_number AS primary_plate, vu2.plate_number AS secondary_plate
                 FROM vehicle_sets vs
                 LEFT JOIN vehicle_units vu1 ON vs.primary_vehicle_unit_id = vu1.id
                 LEFT JOIN vehicle_units vu2 ON vs.secondary_vehicle_unit_id = vu2.id
                 WHERE vs.status = 'active'" . ($currentVsId > 0 ? " OR vs.id = " . $currentVsId : "") . "
                 ORDER BY vs.set_type"
            )->fetchAll(PDO::FETCH_ASSOC);
        }

        $blockingNotices = [];
        if (empty($contractors)) {
            $blockingNotices[] = ['message' => 'Нет активных подрядчиков.', 'link' => '/company/contractors/create', 'action' => 'Создать подрядчика'];
        }
        if (empty($drivers)) {
            $blockingNotices[] = ['message' => 'Нет активных водителей.', 'link' => '/company/drivers', 'action' => 'Создать водителя'];
        }
        if (empty($vehicleSets)) {
            $blockingNotices[] = ['message' => 'Нет активных транспортных комплектов.', 'link' => '/company/vehicle-sets', 'action' => 'Создать ТС'];
        }

        if (!empty($blockingNotices)) {
            $dbError = null;

            ob_start();
            require base_path('app/View/pages/company_route_executor_edit.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $contractorId = trim($_POST['contractor_id'] ?? '');
        $driverId = trim($_POST['driver_id'] ?? '');
        $vehicleSetId = trim($_POST['vehicle_set_id'] ?? '');

        if ($contractorId === '') {
            $errors['contractor_id'] = 'Выберите подрядчика';
        }
        if ($driverId === '') {
            $errors['driver_id'] = 'Выберите водителя';
        }
        if ($vehicleSetId === '') {
            $errors['vehicle_set_id'] = 'Выберите транспортный комплект';
        }

        // Backend access validation for logist
        if (empty($errors) && $isLogist) {
            $cCheck = $localPdo->prepare("SELECT created_by_user_id FROM contractors WHERE id = ?");
            $cCheck->execute([(int)$contractorId]);
            $cOwner = $cCheck->fetchColumn();
            if ($cOwner !== false && (int)$cOwner !== $userId) {
                $cGrant = $localPdo->prepare("SELECT COUNT(*) FROM entity_access_grants WHERE entity_type = 'contractor' AND entity_id = ? AND granted_to_user_id = ? AND access_level IN ('view','edit') AND revoked_at IS NULL");
                $cGrant->execute([(int)$contractorId, $userId]);
                if ($cGrant->fetchColumn() == 0) {
                    $errors['contractor_id'] = 'Подрядчик недоступен.';
                }
            }

            $dCheck = $localPdo->prepare("SELECT created_by_user_id FROM drivers WHERE id = ?");
            $dCheck->execute([(int)$driverId]);
            $dOwner = $dCheck->fetchColumn();
            if ($dOwner !== false && (int)$dOwner !== $userId) {
                $dGrant = $localPdo->prepare("SELECT COUNT(*) FROM entity_access_grants WHERE entity_type = 'driver' AND entity_id = ? AND granted_to_user_id = ? AND access_level IN ('view','edit') AND revoked_at IS NULL");
                $dGrant->execute([(int)$driverId, $userId]);
                if ($dGrant->fetchColumn() == 0) {
                    $errors['driver_id'] = 'Водитель недоступен.';
                }
            }

            $vsCheck = $localPdo->prepare("SELECT created_by_user_id FROM vehicle_sets WHERE id = ?");
            $vsCheck->execute([(int)$vehicleSetId]);
            $vsOwner = $vsCheck->fetchColumn();
            if ($vsOwner !== false && (int)$vsOwner !== $userId) {
                $vsGrant = $localPdo->prepare("SELECT COUNT(*) FROM entity_access_grants WHERE entity_type = 'vehicle_set' AND entity_id = ? AND granted_to_user_id = ? AND access_level IN ('view','edit') AND revoked_at IS NULL");
                $vsGrant->execute([(int)$vehicleSetId, $userId]);
                if ($vsGrant->fetchColumn() == 0) {
                    $errors['vehicle_set_id'] = 'Транспортный комплект недоступен.';
                }
            }
        }

        if (!empty($errors)) {
            ob_start();
            require base_path('app/View/pages/company_route_executor_edit.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        // --- Core logic: find or create driver_vehicle_block, then update crew ---
        $localPdo->beginTransaction();
        try {
            // 1. Find or create driver_vehicle_block for new driver+vehicle_set
            $dvbStmt = $localPdo->prepare("SELECT id FROM driver_vehicle_blocks WHERE driver_id = ? AND vehicle_set_id = ? LIMIT 1");
            $dvbStmt->execute([(int)$driverId, (int)$vehicleSetId]);
            $newDvbId = $dvbStmt->fetchColumn();

            if (!$newDvbId) {
                $dvbInsert = $localPdo->prepare(
                    "INSERT INTO driver_vehicle_blocks (driver_id, vehicle_set_id, status, created_by_user_id, created_by_role)
                     VALUES (:driver_id, :vehicle_set_id, 'active', :uid, :role)"
                );
                $dvbInsert->execute([
                    ':driver_id' => (int)$driverId,
                    ':vehicle_set_id' => (int)$vehicleSetId,
                    ':uid' => $userId,
                    ':role' => $userRole,
                ]);
                $newDvbId = (int)$localPdo->lastInsertId();
            }

            // 2. Check duplicate (excluding current crew)
            $dupStmt = $localPdo->prepare(
                "SELECT COUNT(*) FROM crews WHERE contractor_id = ? AND driver_vehicle_block_id = ? AND id != ?"
            );
            $dupStmt->execute([(int)$contractorId, $newDvbId, $crewId]);
            if ($dupStmt->fetchColumn() > 0) {
                $localPdo->rollBack();
                $formError = 'Такой исполнитель рейса уже существует в этой компании.';
                ob_start();
                require base_path('app/View/pages/company_route_executor_edit.php');
                $content = ob_get_clean();
                require base_path('app/View/layouts/main.php');
                return;
            }

            // 3. Update crew
            $status = trim($_POST['status'] ?? 'active');
            $comments = trim($_POST['comments'] ?? '');

            $update = $localPdo->prepare(
                'UPDATE crews SET contractor_id = :contractor_id, driver_vehicle_block_id = :driver_vehicle_block_id,
                 status = :status, comments = :comments,
                 updated_by_user_id = :uid, updated_by_role = :role
                 WHERE id = :id'
            );
            $update->execute([
                ':contractor_id' => (int)$contractorId,
                ':driver_vehicle_block_id' => $newDvbId,
                ':status' => $status,
                ':comments' => $comments !== '' ? $comments : null,
                ':uid' => $userId,
                ':role' => $userRole,
                ':id' => $crewId,
            ]);

            $localPdo->commit();

            // Fetch updated crew for success display
            $cStmt = $localPdo->prepare(
                "SELECT c.*,
                        ct.name AS contractor_name,
                        vu1.plate_number,
                        d.full_name AS driver_name
                 FROM crews c
                 JOIN contractors ct ON c.contractor_id = ct.id
                 JOIN driver_vehicle_blocks dvb ON c.driver_vehicle_block_id = dvb.id
                 JOIN drivers d ON dvb.driver_id = d.id
                 JOIN vehicle_sets vs ON dvb.vehicle_set_id = vs.id
                 LEFT JOIN vehicle_units vu1 ON vs.primary_vehicle_unit_id = vu1.id
                 WHERE c.id = ?"
            );
            $cStmt->execute([$crewId]);
            $crew = $cStmt->fetch(PDO::FETCH_ASSOC);
            $success = true;
            $dbError = null;
        } catch (\Exception $e) {
            $localPdo->rollBack();
            $formError = 'Ошибка обновления исполнителя рейса: ' . $e->getMessage();
        }
    } catch (\Exception $e) {
        $company = $company ?? null;
        $crew = $crew ?? null;
        $dbError = null;
        $contractors = $contractors ?? [];
        $drivers = $drivers ?? [];
        $vehicleSets = $vehicleSets ?? [];
        $blockingNotices = $blockingNotices ?? [];
        $formError = 'Ошибка обновления исполнителя рейса: ' . $e->getMessage();
    }

    ob_start();
    require base_path('app/View/pages/company_route_executor_edit.php');
    $content = ob_get_clean();
    require base_path('app/View/layouts/main.php');
});

// ============================================================
// BLOCK E3: Route executors — ARCHIVE
// ============================================================

$router->post('/company/route-executors/{id}/archive', function ($crewId) use ($config, $db) {
    requireRole(['company_owner', 'senior_logist', 'logist']);
    $crewId = (int)$crewId;

    $companyId = (int)(getSessionCompanyId() ?? 0);

    if ($companyId <= 0) {
        header('Location: /company/route-executors');
        exit;
    }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company || $company['status'] !== 'active') {
            header('Location: /company/route-executors');
            exit;
        }

        $dbIdentifier = $company['db_identifier'];
        $localDbConfig = $config['database'];
        $localDbConfig['database'] = $dbIdentifier;
        $localDb = new \App\Core\Database($localDbConfig);
        $localPdo = $localDb->connection();
        applyLocalMigrations($localPdo);

        try {
            $localPdo->query("SELECT 1 FROM crews LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $migrationSql = file_get_contents(base_path('database/migrations-local/006_create_company_crews.sql'));
            $localPdo->exec($migrationSql);
        }

        // Permission check
        $currentUserId = (int)($_SESSION['user_id'] ?? 0);
        $currentRole = $_SESSION['role_code'] ?? '';

        $crewStmt = $localPdo->prepare('SELECT * FROM crews WHERE id = ?');
        $crewStmt->execute([$crewId]);
        $crew = $crewStmt->fetch(PDO::FETCH_ASSOC);

        if (!$crew) {
            header('Location: /company/route-executors');
            exit;
        }

        if ($currentRole !== 'company_owner' && $currentRole !== 'senior_logist') {
            $isCreator = ($crew['created_by_user_id'] ?? 0) === $currentUserId;

            $grantStmt = $localPdo->prepare(
                "SELECT 1 FROM entity_access_grants
                 WHERE entity_type = 'crew' AND entity_id = ?
                 AND granted_to_user_id = ? AND access_level = 'edit'
                 AND (revoked_at IS NULL)"
            );
            $grantStmt->execute([$crewId, $currentUserId]);
            $hasEditGrant = (bool)$grantStmt->fetchColumn();

            if (!$isCreator && !$hasEditGrant) {
                http_response_code(403);
                header('Content-Type: text/plain');
                echo '403 Forbidden';
                exit;
            }
        }

        $update = $localPdo->prepare("UPDATE crews SET status = 'archived' WHERE id = ?");
        $update->execute([$crewId]);

        header('Location: /company/route-executors');
        exit;
    } catch (\Exception $e) {
        header('Location: /company/route-executors');
        exit;
    }
});

// ============================================================
// BLOCK E4: Responsible assignments — logist reassignment
// ============================================================

$router->get('/company/responsible-assignments', function () use ($config, $db) {
    requireRole('company_owner');
    $pageTitle = 'Ответственные логисты';
    $pageContext = 'Ответственные логисты › Компания';

    $companyId = (int)(getSessionCompanyId() ?? 0);
    $activeTab = $_GET['tab'] ?? 'route_executor';
    if (!in_array($activeTab, ['route_executor', 'contractor', 'driver', 'vehicle_set'], true)) {
        $activeTab = 'route_executor';
    }

    // Query params for after-redirect messages
    $successMessage = null;
    $formError = null;
    if (isset($_GET['msg'])) {
        switch ($_GET['msg']) {
            case 'assigned':
                $successMessage = 'Ответственность успешно переназначена.';
                break;
            case 'already_assigned':
                $formError = 'Выбранный логист уже является ответственным для некоторых записей.';
                break;
            case 'error':
                $formError = 'Ошибка при переназначении. Попробуйте снова.';
                break;
        }
    }

    if ($companyId <= 0) {
        $company = null;
        $items = [];
        $logists = [];
        $dbError = null;
        ob_start();
        require base_path('app/View/pages/company_responsible_assignments.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
        return;
    }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company || $company['status'] !== 'active') {
            $company = $company ?? null;
            $items = [];
            $logists = [];
            $dbError = null;
            ob_start();
            require base_path('app/View/pages/company_responsible_assignments.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $pageContext = 'Ответственные логисты › Компания: ' . $company['name'];

        $dbIdentifier = $company['db_identifier'];
        $localDbConfig = $config['database'];
        $localDbConfig['database'] = $dbIdentifier;
        $localDb = new \App\Core\Database($localDbConfig);
        $localPdo = $localDb->connection();
        applyLocalMigrations($localPdo);

        // Ensure key tables exist
        try { $localPdo->query("SELECT 1 FROM contractors LIMIT 1")->fetch(); }
        catch (\Exception $e) { $localPdo->exec(file_get_contents(base_path('database/migrations-local/003_create_company_contractors.sql'))); }
        try { $localPdo->query("SELECT 1 FROM drivers LIMIT 1")->fetch(); }
        catch (\Exception $e) { $localPdo->exec(file_get_contents(base_path('database/migrations-local/004_create_company_drivers.sql'))); }
        try { $localPdo->query("SELECT 1 FROM vehicle_sets LIMIT 1")->fetch(); }
        catch (\Exception $e) { $localPdo->exec(file_get_contents(base_path('database/migrations-local/017_create_vehicle_sets.sql'))); }
        try { $localPdo->query("SELECT 1 FROM crews LIMIT 1")->fetch(); }
        catch (\Exception $e) { $localPdo->exec(file_get_contents(base_path('database/migrations-local/006_create_company_crews.sql'))); }

        // Load active logists for dropdowns
        $logists = $localPdo->query(
            "SELECT id, full_name, login FROM users
             WHERE role_code = 'logist' AND status = 'active'
             ORDER BY full_name"
        )->fetchAll(PDO::FETCH_ASSOC);

        // Load items based on active tab
        switch ($activeTab) {
            case 'route_executor':
                $items = $localPdo->query(
                    "SELECT c.id AS crew_id,
                            ct.name AS contractor_name,
                            d.full_name AS driver_name,
                            CONCAT(vu1.plate_number, IFNULL(CONCAT(' + ', vu2.plate_number), '')) AS plates,
                            c.created_by_user_id AS logist_id,
                            u.full_name AS logist_name
                     FROM crews c
                     JOIN contractors ct ON c.contractor_id = ct.id
                     JOIN driver_vehicle_blocks dvb ON c.driver_vehicle_block_id = dvb.id
                     JOIN drivers d ON dvb.driver_id = d.id
                     JOIN vehicle_sets vs ON dvb.vehicle_set_id = vs.id
                     LEFT JOIN vehicle_units vu1 ON vs.primary_vehicle_unit_id = vu1.id
                     LEFT JOIN vehicle_units vu2 ON vs.secondary_vehicle_unit_id = vu2.id
                     LEFT JOIN users u ON c.created_by_user_id = u.id
                     WHERE c.status != 'archived'
                     ORDER BY ct.name"
                )->fetchAll(PDO::FETCH_ASSOC);
                break;

            case 'contractor':
                $items = $localPdo->query(
                    "SELECT c.id, c.name, c.inn,
                            c.created_by_user_id AS logist_id,
                            u.full_name AS logist_name,
                            COUNT(DISTINCT cr.id) AS crew_count
                     FROM contractors c
                     LEFT JOIN users u ON c.created_by_user_id = u.id
                     LEFT JOIN crews cr ON cr.contractor_id = c.id AND cr.status != 'archived'
                     WHERE c.status != 'archived'
                     GROUP BY c.id, c.name, c.inn, c.created_by_user_id, u.full_name
                     ORDER BY c.name"
                )->fetchAll(PDO::FETCH_ASSOC);
                break;

            case 'driver':
                $items = $localPdo->query(
                    "SELECT d.id, d.full_name, d.phone,
                            d.created_by_user_id AS logist_id,
                            u.full_name AS logist_name,
                            COUNT(DISTINCT cr.id) AS crew_count
                     FROM drivers d
                     LEFT JOIN users u ON d.created_by_user_id = u.id
                     LEFT JOIN driver_vehicle_blocks dvb ON dvb.driver_id = d.id
                     LEFT JOIN crews cr ON cr.driver_vehicle_block_id = dvb.id AND cr.status != 'archived'
                     WHERE d.status != 'archived'
                     GROUP BY d.id, d.full_name, d.phone, d.created_by_user_id, u.full_name
                     ORDER BY d.full_name"
                )->fetchAll(PDO::FETCH_ASSOC);
                break;

            case 'vehicle_set':
                $items = $localPdo->query(
                    "SELECT vs.id, vs.set_type,
                            vu1.plate_number,
                            vs.created_by_user_id AS logist_id,
                            u.full_name AS logist_name,
                            COUNT(DISTINCT cr.id) AS crew_count
                     FROM vehicle_sets vs
                     LEFT JOIN users u ON vs.created_by_user_id = u.id
                     LEFT JOIN vehicle_units vu1 ON vs.primary_vehicle_unit_id = vu1.id
                     LEFT JOIN driver_vehicle_blocks dvb ON dvb.vehicle_set_id = vs.id
                     LEFT JOIN crews cr ON cr.driver_vehicle_block_id = dvb.id AND cr.status != 'archived'
                     WHERE vs.status != 'archived'
                     GROUP BY vs.id, vs.set_type, vu1.plate_number, vs.created_by_user_id, u.full_name
                     ORDER BY vu1.plate_number"
                )->fetchAll(PDO::FETCH_ASSOC);
                break;
        }

        $dbError = null;
    } catch (\Exception $e) {
        $company = $company ?? null;
        $items = [];
        $logists = [];
        $dbError = 'Не удалось загрузить данные: ' . $e->getMessage();
    }

    ob_start();
    require base_path('app/View/pages/company_responsible_assignments.php');
    $content = ob_get_clean();
    require base_path('app/View/layouts/main.php');
});

$router->post('/company/responsible-assignments/reassign', function () use ($config, $db) {
    requireRole('company_owner');

    $companyId = (int)(getSessionCompanyId() ?? 0);
    $entityType = $_POST['entity_type'] ?? '';
    $entityIds = $_POST['entity_ids'] ?? [];
    $newLogistId = (int)($_POST['new_logist_id'] ?? 0);
    $cascade = ($_POST['cascade'] ?? '0') === '1';

    $redirect = '/company/responsible-assignments?tab=' . urlencode(
        in_array($entityType, ['route_executor', 'contractor', 'driver', 'vehicle_set'], true) ? $entityType : 'route_executor'
    );

    if (!is_array($entityIds)) {
        $entityIds = [$entityIds];
    }
    $entityIds = array_map('intval', $entityIds);
    $entityIds = array_filter($entityIds, function ($id) { return $id > 0; });
    $entityIds = array_unique(array_values($entityIds));

    if ($companyId <= 0 || $newLogistId <= 0 || empty($entityIds)
        || !in_array($entityType, ['route_executor', 'contractor', 'driver', 'vehicle_set'], true)) {
        header('Location: ' . $redirect . '&msg=error');
        exit;
    }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company || $company['status'] !== 'active') {
            header('Location: ' . $redirect . '&msg=error');
            exit;
        }

        $dbIdentifier = $company['db_identifier'];
        $localDbConfig = $config['database'];
        $localDbConfig['database'] = $dbIdentifier;
        $localDb = new \App\Core\Database($localDbConfig);
        $localPdo = $localDb->connection();
        applyLocalMigrations($localPdo);

        // Validate new logist exists and is active
        $lStmt = $localPdo->prepare(
            "SELECT id, full_name FROM users WHERE id = ? AND role_code = 'logist' AND status = 'active'"
        );
        $lStmt->execute([$newLogistId]);
        $newLogist = $lStmt->fetch(PDO::FETCH_ASSOC);
        if (!$newLogist) {
            header('Location: ' . $redirect . '&msg=error');
            exit;
        }

        $role = 'logist';
        $changedByUserId = (int)$_SESSION['user_id'];
        $changedByRole = $_SESSION['role_code'] ?? 'company_owner';

        $totalSkipped = 0;
        $totalReassigned = 0;
        $summary = [];

        $localPdo->beginTransaction();
        try {
            foreach ($entityIds as $entityId) {
                $oldLogistId = 0;

                // Load entity and get old_logist_id
                switch ($entityType) {
                    case 'route_executor':
                        $eStmt = $localPdo->prepare('SELECT * FROM crews WHERE id = ?');
                        $eStmt->execute([$entityId]);
                        $entity = $eStmt->fetch(PDO::FETCH_ASSOC);
                        if (!$entity) continue 2;
                        $oldLogistId = (int)($entity['created_by_user_id'] ?? 0);
                        break;

                    case 'contractor':
                        $eStmt = $localPdo->prepare('SELECT * FROM contractors WHERE id = ?');
                        $eStmt->execute([$entityId]);
                        $entity = $eStmt->fetch(PDO::FETCH_ASSOC);
                        if (!$entity) continue 2;
                        $oldLogistId = (int)($entity['created_by_user_id'] ?? 0);
                        break;

                    case 'driver':
                        $eStmt = $localPdo->prepare('SELECT * FROM drivers WHERE id = ?');
                        $eStmt->execute([$entityId]);
                        $entity = $eStmt->fetch(PDO::FETCH_ASSOC);
                        if (!$entity) continue 2;
                        $oldLogistId = (int)($entity['created_by_user_id'] ?? 0);
                        break;

                    case 'vehicle_set':
                        $eStmt = $localPdo->prepare('SELECT * FROM vehicle_sets WHERE id = ?');
                        $eStmt->execute([$entityId]);
                        $entity = $eStmt->fetch(PDO::FETCH_ASSOC);
                        if (!$entity) continue 2;
                        $oldLogistId = (int)($entity['created_by_user_id'] ?? 0);
                        break;
                }

                // Skip if already assigned to this logist
                if ($oldLogistId === $newLogistId) {
                    $totalSkipped++;
                    continue;
                }

                $totalReassigned++;

                // --- CASCADE LOGIC ---

                if ($entityType === 'route_executor') {
                    // Full cascade: crew -> block -> contractor + driver + vehicle_set

                    $crew = $entity;
                    $contractorId = (int)($crew['contractor_id'] ?? 0);
                    $blockId = (int)($crew['driver_vehicle_block_id'] ?? 0);

                    $driverId = 0;
                    $vehicleSetId = 0;
                    if ($blockId > 0) {
                        $blockStmt = $localPdo->prepare('SELECT * FROM driver_vehicle_blocks WHERE id = ?');
                        $blockStmt->execute([$blockId]);
                        $block = $blockStmt->fetch(PDO::FETCH_ASSOC);
                        if ($block) {
                            $driverId = (int)($block['driver_id'] ?? 0);
                            $vehicleSetId = (int)($block['vehicle_set_id'] ?? 0);

                            // Update block
                            $localPdo->prepare(
                                "UPDATE driver_vehicle_blocks SET created_by_user_id = ?, created_by_role = ?, updated_by_user_id = ?, updated_by_role = ? WHERE id = ?"
                            )->execute([$newLogistId, $role, $changedByUserId, $changedByRole, $blockId]);
                        }
                    }

                    // Update crew
                    $localPdo->prepare(
                        "UPDATE crews SET created_by_user_id = ?, created_by_role = ?, updated_by_user_id = ?, updated_by_role = ? WHERE id = ?"
                    )->execute([$newLogistId, $role, $changedByUserId, $changedByRole, $entityId]);

                    // Update contractor
                    if ($contractorId > 0) {
                        $localPdo->prepare(
                            "UPDATE contractors SET created_by_user_id = ?, created_by_role = ?, updated_by_user_id = ?, updated_by_role = ? WHERE id = ?"
                        )->execute([$newLogistId, $role, $changedByUserId, $changedByRole, $contractorId]);
                    }

                    // Update driver
                    if ($driverId > 0) {
                        $localPdo->prepare(
                            "UPDATE drivers SET created_by_user_id = ?, created_by_role = ?, updated_by_user_id = ?, updated_by_role = ? WHERE id = ?"
                        )->execute([$newLogistId, $role, $changedByUserId, $changedByRole, $driverId]);
                    }

                    // Update vehicle_set
                    if ($vehicleSetId > 0) {
                        $localPdo->prepare(
                            "UPDATE vehicle_sets SET created_by_user_id = ?, created_by_role = ?, updated_by_user_id = ?, updated_by_role = ? WHERE id = ?"
                        )->execute([$newLogistId, $role, $changedByUserId, $changedByRole, $vehicleSetId]);
                    }

                    // Revoke grants for crew
                    $localPdo->prepare(
                        "UPDATE entity_access_grants SET revoked_at = NOW(), revoked_by_user_id = ?
                         WHERE entity_type = 'crew' AND entity_id = ? AND revoked_at IS NULL"
                    )->execute([$changedByUserId, $entityId]);

                    // Record history
                    $localPdo->prepare(
                        "INSERT INTO responsible_assignment_history
                            (entity_type, entity_id, old_logist_id, new_logist_id, changed_by_user_id, changed_by_role, summary_json, comment)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
                    )->execute([
                        'route_executor',
                        $entityId,
                        $oldLogistId > 0 ? $oldLogistId : null,
                        $newLogistId,
                        $changedByUserId,
                        $changedByRole,
                        json_encode(['reassigned_crew' => 1, 'reassigned_contractor' => $contractorId > 0 ? 1 : 0, 'reassigned_driver' => $driverId > 0 ? 1 : 0, 'reassigned_vehicle_set' => $vehicleSetId > 0 ? 1 : 0], JSON_UNESCAPED_UNICODE),
                        'Переназначение исполнителя рейса #' . $entityId . ' от логиста #' . ($oldLogistId ?: 'нет') . ' к логисту «' . ($newLogist['full_name'] ?? '') . '»',
                    ]);

                } elseif ($entityType === 'contractor') {
                    // Update contractor
                    $localPdo->prepare(
                        "UPDATE contractors SET created_by_user_id = ?, created_by_role = ?, updated_by_user_id = ?, updated_by_role = ? WHERE id = ?"
                    )->execute([$newLogistId, $role, $changedByUserId, $changedByRole, $entityId]);

                    if ($cascade) {
                        // Cascade to crews -> blocks -> drivers -> vehicle_sets
                        $cascStmt = $localPdo->prepare(
                            "SELECT c.id AS crew_id, c.driver_vehicle_block_id AS block_id,
                                    dvb.driver_id, dvb.vehicle_set_id
                             FROM crews c
                             JOIN driver_vehicle_blocks dvb ON c.driver_vehicle_block_id = dvb.id
                             WHERE c.contractor_id = ? AND c.status != 'archived'"
                        );
                        $cascStmt->execute([$entityId]);
                        $cascRows = $cascStmt->fetchAll(PDO::FETCH_ASSOC);

                        $crewIds = [];
                        $blockIds = [];
                        $driverIds = [];
                        $vehicleSetIds = [];
                        foreach ($cascRows as $row) {
                            $crewIds[] = (int)$row['crew_id'];
                            $blockIds[] = (int)$row['block_id'];
                            $driverIds[] = (int)$row['driver_id'];
                            $vehicleSetIds[] = (int)$row['vehicle_set_id'];
                        }
                        $uniqueBlockIds = array_unique($blockIds);
                        $uniqueDriverIds = array_unique($driverIds);
                        $uniqueVehicleSetIds = array_unique($vehicleSetIds);

                        if (!empty($crewIds)) {
                            $ph = implode(',', array_fill(0, count($crewIds), '?'));
                            $params = [$newLogistId, $role, $changedByUserId, $changedByRole];
                            $params = array_merge($params, $crewIds);
                            $localPdo->prepare(
                                "UPDATE crews SET created_by_user_id = ?, created_by_role = ?, updated_by_user_id = ?, updated_by_role = ? WHERE id IN ($ph)"
                            )->execute($params);
                        }
                        if (!empty($uniqueBlockIds)) {
                            $ph = implode(',', array_fill(0, count($uniqueBlockIds), '?'));
                            $params = [$newLogistId, $role, $changedByUserId, $changedByRole];
                            $params = array_merge($params, array_values($uniqueBlockIds));
                            $localPdo->prepare(
                                "UPDATE driver_vehicle_blocks SET created_by_user_id = ?, created_by_role = ?, updated_by_user_id = ?, updated_by_role = ? WHERE id IN ($ph)"
                            )->execute($params);
                        }
                        if (!empty($uniqueDriverIds)) {
                            $ph = implode(',', array_fill(0, count($uniqueDriverIds), '?'));
                            $params = [$newLogistId, $role, $changedByUserId, $changedByRole];
                            $params = array_merge($params, array_values($uniqueDriverIds));
                            $localPdo->prepare(
                                "UPDATE drivers SET created_by_user_id = ?, created_by_role = ?, updated_by_user_id = ?, updated_by_role = ? WHERE id IN ($ph)"
                            )->execute($params);
                        }
                        if (!empty($uniqueVehicleSetIds)) {
                            $ph = implode(',', array_fill(0, count($uniqueVehicleSetIds), '?'));
                            $params = [$newLogistId, $role, $changedByUserId, $changedByRole];
                            $params = array_merge($params, array_values($uniqueVehicleSetIds));
                            $localPdo->prepare(
                                "UPDATE vehicle_sets SET created_by_user_id = ?, created_by_role = ?, updated_by_user_id = ?, updated_by_role = ? WHERE id IN ($ph)"
                            )->execute($params);
                        }
                    }

                    // Revoke grants for contractor
                    $localPdo->prepare(
                        "UPDATE entity_access_grants SET revoked_at = NOW(), revoked_by_user_id = ?
                         WHERE entity_type = 'contractor' AND entity_id = ? AND revoked_at IS NULL"
                    )->execute([$changedByUserId, $entityId]);

                    // Record history
                    $localPdo->prepare(
                        "INSERT INTO responsible_assignment_history
                            (entity_type, entity_id, old_logist_id, new_logist_id, changed_by_user_id, changed_by_role, summary_json, comment)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
                    )->execute([
                        'contractor',
                        $entityId,
                        $oldLogistId > 0 ? $oldLogistId : null,
                        $newLogistId,
                        $changedByUserId,
                        $changedByRole,
                        json_encode(['cascade' => $cascade], JSON_UNESCAPED_UNICODE),
                        'Переназначение подрядчика #' . $entityId . ' от логиста #' . ($oldLogistId ?: 'нет') . ' к логисту «' . ($newLogist['full_name'] ?? '') . '»',
                    ]);

                } elseif ($entityType === 'driver') {
                    // Update driver
                    $localPdo->prepare(
                        "UPDATE drivers SET created_by_user_id = ?, created_by_role = ?, updated_by_user_id = ?, updated_by_role = ? WHERE id = ?"
                    )->execute([$newLogistId, $role, $changedByUserId, $changedByRole, $entityId]);

                    if ($cascade) {
                        // Cascade to crews via driver_vehicle_blocks
                        $cascStmt = $localPdo->prepare(
                            "SELECT c.id AS crew_id, dvb.id AS block_id
                             FROM crews c
                             JOIN driver_vehicle_blocks dvb ON c.driver_vehicle_block_id = dvb.id
                             WHERE dvb.driver_id = ? AND c.status != 'archived'"
                        );
                        $cascStmt->execute([$entityId]);
                        $cascRows = $cascStmt->fetchAll(PDO::FETCH_ASSOC);

                        $crewIds = [];
                        $blockIds = [];
                        foreach ($cascRows as $row) {
                            $crewIds[] = (int)$row['crew_id'];
                            $blockIds[] = (int)$row['block_id'];
                        }
                        $uniqueBlockIds = array_unique($blockIds);

                        if (!empty($crewIds)) {
                            $ph = implode(',', array_fill(0, count($crewIds), '?'));
                            $params = [$newLogistId, $role, $changedByUserId, $changedByRole];
                            $params = array_merge($params, $crewIds);
                            $localPdo->prepare(
                                "UPDATE crews SET created_by_user_id = ?, created_by_role = ?, updated_by_user_id = ?, updated_by_role = ? WHERE id IN ($ph)"
                            )->execute($params);
                        }
                        if (!empty($uniqueBlockIds)) {
                            $ph = implode(',', array_fill(0, count($uniqueBlockIds), '?'));
                            $params = [$newLogistId, $role, $changedByUserId, $changedByRole];
                            $params = array_merge($params, array_values($uniqueBlockIds));
                            $localPdo->prepare(
                                "UPDATE driver_vehicle_blocks SET created_by_user_id = ?, created_by_role = ?, updated_by_user_id = ?, updated_by_role = ? WHERE id IN ($ph)"
                            )->execute($params);
                        }
                    }

                    // Revoke grants for driver
                    $localPdo->prepare(
                        "UPDATE entity_access_grants SET revoked_at = NOW(), revoked_by_user_id = ?
                         WHERE entity_type = 'driver' AND entity_id = ? AND revoked_at IS NULL"
                    )->execute([$changedByUserId, $entityId]);

                    // Record history
                    $localPdo->prepare(
                        "INSERT INTO responsible_assignment_history
                            (entity_type, entity_id, old_logist_id, new_logist_id, changed_by_user_id, changed_by_role, summary_json, comment)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
                    )->execute([
                        'driver',
                        $entityId,
                        $oldLogistId > 0 ? $oldLogistId : null,
                        $newLogistId,
                        $changedByUserId,
                        $changedByRole,
                        json_encode(['cascade' => $cascade], JSON_UNESCAPED_UNICODE),
                        'Переназначение водителя #' . $entityId . ' от логиста #' . ($oldLogistId ?: 'нет') . ' к логисту «' . ($newLogist['full_name'] ?? '') . '»',
                    ]);

                } elseif ($entityType === 'vehicle_set') {
                    // Update vehicle_set
                    $localPdo->prepare(
                        "UPDATE vehicle_sets SET created_by_user_id = ?, created_by_role = ?, updated_by_user_id = ?, updated_by_role = ? WHERE id = ?"
                    )->execute([$newLogistId, $role, $changedByUserId, $changedByRole, $entityId]);

                    if ($cascade) {
                        // Cascade to crews via driver_vehicle_blocks
                        $cascStmt = $localPdo->prepare(
                            "SELECT c.id AS crew_id, dvb.id AS block_id
                             FROM crews c
                             JOIN driver_vehicle_blocks dvb ON c.driver_vehicle_block_id = dvb.id
                             WHERE dvb.vehicle_set_id = ? AND c.status != 'archived'"
                        );
                        $cascStmt->execute([$entityId]);
                        $cascRows = $cascStmt->fetchAll(PDO::FETCH_ASSOC);

                        $crewIds = [];
                        $blockIds = [];
                        foreach ($cascRows as $row) {
                            $crewIds[] = (int)$row['crew_id'];
                            $blockIds[] = (int)$row['block_id'];
                        }
                        $uniqueBlockIds = array_unique($blockIds);

                        if (!empty($crewIds)) {
                            $ph = implode(',', array_fill(0, count($crewIds), '?'));
                            $params = [$newLogistId, $role, $changedByUserId, $changedByRole];
                            $params = array_merge($params, $crewIds);
                            $localPdo->prepare(
                                "UPDATE crews SET created_by_user_id = ?, created_by_role = ?, updated_by_user_id = ?, updated_by_role = ? WHERE id IN ($ph)"
                            )->execute($params);
                        }
                        if (!empty($uniqueBlockIds)) {
                            $ph = implode(',', array_fill(0, count($uniqueBlockIds), '?'));
                            $params = [$newLogistId, $role, $changedByUserId, $changedByRole];
                            $params = array_merge($params, array_values($uniqueBlockIds));
                            $localPdo->prepare(
                                "UPDATE driver_vehicle_blocks SET created_by_user_id = ?, created_by_role = ?, updated_by_user_id = ?, updated_by_role = ? WHERE id IN ($ph)"
                            )->execute($params);
                        }
                    }

                    // Revoke grants for vehicle_set
                    $localPdo->prepare(
                        "UPDATE entity_access_grants SET revoked_at = NOW(), revoked_by_user_id = ?
                         WHERE entity_type = 'vehicle_set' AND entity_id = ? AND revoked_at IS NULL"
                    )->execute([$changedByUserId, $entityId]);

                    // Record history
                    $localPdo->prepare(
                        "INSERT INTO responsible_assignment_history
                            (entity_type, entity_id, old_logist_id, new_logist_id, changed_by_user_id, changed_by_role, summary_json, comment)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
                    )->execute([
                        'vehicle_set',
                        $entityId,
                        $oldLogistId > 0 ? $oldLogistId : null,
                        $newLogistId,
                        $changedByUserId,
                        $changedByRole,
                        json_encode(['cascade' => $cascade], JSON_UNESCAPED_UNICODE),
                        'Переназначение ТС #' . $entityId . ' от логиста #' . ($oldLogistId ?: 'нет') . ' к логисту «' . ($newLogist['full_name'] ?? '') . '»',
                    ]);
                }
            }

            $localPdo->commit();

            // Build redirect message
            if ($totalReassigned > 0) {
                $redirect .= '&msg=assigned';
            } elseif ($totalSkipped > 0) {
                $redirect .= '&msg=already_assigned';
            } else {
                $redirect .= '&msg=error';
            }
        } catch (\Exception $e) {
            $localPdo->rollBack();
            error_log('Responsible assignment error: ' . $e->getMessage());
            $redirect .= '&msg=error';
        }
    } catch (\Exception $e) {
        error_log('Responsible assignment setup error: ' . $e->getMessage());
        $redirect .= '&msg=error';
    }

    header('Location: ' . $redirect);
    exit;
});

// ============================================================
// SUPERADMIN: Company status actions (NEW)
// ============================================================

$router->post('/superadmin/companies/{id}/activate', function ($id) use ($config, $db) {
    requireRole('superadmin');
    $pdo = $db->connection();
    $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
    $stmt->execute([(int)$id]);
    $company = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$company) {
        http_response_code(404);
        echo 'Company not found';
        return;
    }
    if ($company['status'] !== 'active') {
        $pdo->prepare('UPDATE companies SET status = ?, updated_at = NOW() WHERE id = ?')
            ->execute(['active', (int)$id]);
    }
    header('Location: /superadmin/companies?status_changed=1');
    exit;
});

$router->post('/superadmin/companies/{id}/block', function ($id) use ($config, $db) {
    requireRole('superadmin');
    $pdo = $db->connection();
    $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
    $stmt->execute([(int)$id]);
    $company = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$company) {
        http_response_code(404);
        echo 'Company not found';
        return;
    }
    if (in_array($company['status'], ['active', 'inactive'], true)) {
        $pdo->prepare('UPDATE companies SET status = ?, updated_at = NOW() WHERE id = ?')
            ->execute(['blocked', (int)$id]);
    }
    header('Location: /superadmin/companies?status_changed=1');
    exit;
});

$router->post('/superadmin/companies/{id}/archive', function ($id) use ($config, $db) {
    requireRole('superadmin');
    $pdo = $db->connection();
    $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
    $stmt->execute([(int)$id]);
    $company = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$company) {
        http_response_code(404);
        echo 'Company not found';
        return;
    }
    $pdo->prepare('UPDATE companies SET status = ?, updated_at = NOW() WHERE id = ?')
        ->execute(['archived', (int)$id]);
    header('Location: /superadmin/companies?status_changed=1');
    exit;
});

$router->post('/superadmin/companies/{id}/deactivate', function ($id) use ($config, $db) {
    requireRole('superadmin');
    $pdo = $db->connection();
    $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
    $stmt->execute([(int)$id]);
    $company = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$company) {
        header('Location: /superadmin/companies');
        exit;
    }
    if ($company['status'] === 'active') {
        $pdo->prepare('UPDATE companies SET status = ?, updated_at = NOW() WHERE id = ?')
            ->execute(['inactive', (int)$id]);
    }
    header('Location: /superadmin/companies?status_changed=1');
    exit;
});

// ============================================================
// SUPERADMIN: Company monitoring routes (NEW)
// ============================================================

$router->get('/superadmin/companies/{id}/users', function ($id) use ($config, $db) {
    requireRole('superadmin');
    $pageTitle = 'Пользователи компании';
    $pageContext = 'Реестр компаний';

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([(int)$id]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company) {
            $company = null;
            $users = [];
            $totalCount = 0;
            $dbError = null;
            $localDbError = null;

            ob_start();
            require base_path('app/View/pages/superadmin_company_users.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $pageTitle = 'Пользователи: ' . $company['name'];
        $pageContext = 'Реестр компаний';

        $ownerStmt = $pdo->prepare(
            "SELECT id, full_name, login, email, phone, role, status, created_at
             FROM company_users WHERE company_id = ? AND role = 'company_owner'"
        );
        $ownerStmt->execute([(int)$id]);
        $ownerRow = $ownerStmt->fetch(PDO::FETCH_ASSOC);

        $logists = [];
        $localDbError = null;

        if (!empty($company['db_identifier']) && $company['status'] === 'active') {
            try {
                $localDbConfig = $config['database'];
                $localDbConfig['database'] = $company['db_identifier'];
                $localDb = new \App\Core\Database($localDbConfig);
                $localPdo = $localDb->connection();
                applyLocalMigrations($localPdo);
                $logistStmt = $localPdo->prepare(
                    "SELECT id, full_name, login, email, phone, role_code, status, created_at
                     FROM users ORDER BY created_at DESC"
                );
                $logistStmt->execute();
                $logists = $logistStmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (\Exception $e) {
                $localDbError = true;
            }
        }

        $users = [];
        if ($ownerRow) {
            $users[] = [
                'type' => 'owner',
                'id' => $ownerRow['id'],
                'full_name' => $ownerRow['full_name'],
                'login' => $ownerRow['login'],
                'email' => $ownerRow['email'],
                'phone' => $ownerRow['phone'],
                'role_label' => 'Руководитель',
                'status' => $ownerRow['status'],
                'created_at' => $ownerRow['created_at'],
            ];
        }
        foreach ($logists as $l) {
            $users[] = [
                'type' => 'logist',
                'id' => $l['id'],
                'full_name' => $l['full_name'],
                'login' => $l['login'],
                'email' => $l['email'],
                'phone' => $l['phone'],
                'role_label' => 'Пользователь',
                'status' => $l['status'],
                'created_at' => $l['created_at'],
            ];
        }
        $totalCount = count($users);
        $dbError = null;
    } catch (\Exception $e) {
        $company = null;
        $users = [];
        $totalCount = 0;
        $dbError = 'Ошибка подключения к базе данных. Попробуйте позже.';
        $localDbError = null;
    }

    ob_start();
    require base_path('app/View/pages/superadmin_company_users.php');
    $content = ob_get_clean();
    require base_path('app/View/layouts/main.php');
});

$router->get('/superadmin/companies/{id}/directories', function ($id) use ($config, $db) {
    requireRole('superadmin');
    $pageTitle = 'Справочники компании';
    $pageContext = 'Реестр компаний';

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([(int)$id]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company) {
            $company = null;
            $dirs = [];
            $dbError = null;

            ob_start();
            require base_path('app/View/pages/superadmin_company_directories.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $pageTitle = 'Справочники: ' . $company['name'];
        $pageContext = 'Реестр компаний';

        $dirs = [];
        if (!empty($company['db_identifier'])) {
            try {
                $localDbConfig = $config['database'];
                $localDbConfig['database'] = $company['db_identifier'];
                $localDb = new \App\Core\Database($localDbConfig);
                $localPdo = $localDb->connection();
                applyLocalMigrations($localPdo);

                $tables = ['clients', 'contractors', 'drivers', 'vehicle_units', 'vehicle_sets', 'driver_vehicle_blocks', 'crews'];
                foreach ($tables as $table) {
                    $countStmt = $localPdo->prepare(
                        "SELECT COUNT(*) as total,
                                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
                                SUM(CASE WHEN status = 'archived' THEN 1 ELSE 0 END) as archived
                         FROM `{$table}`"
                    );
                    $countStmt->execute();
                    $dirs[$table] = $countStmt->fetch(PDO::FETCH_ASSOC);
                }
            } catch (\Exception $e) {
                $dbError = 'Локальная БД компании недоступна.';
            }
        } else {
            $dbError = 'Локальная БД компании недоступна.';
        }

        $dbError = $dbError ?? null;
    } catch (\Exception $e) {
        $company = null;
        $dirs = [];
        $dbError = 'Ошибка подключения к базе данных. Попробуйте позже.';
    }

    ob_start();
    require base_path('app/View/pages/superadmin_company_directories.php');
    $content = ob_get_clean();
    require base_path('app/View/layouts/main.php');
});

$router->get('/superadmin/companies/{id}/clients', function ($id) use ($config, $db) {
    requireRole('superadmin');

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([(int)$id]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company) {
            $company = null;
            $items = [];
            $totalCount = 0;
            $dbError = 'Компания не найдена';

            ob_start();
            require base_path('app/View/pages/superadmin_company_clients.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $pageTitle = 'Клиенты: ' . $company['name'];
        $pageContext = 'Реестр компаний';

        $items = [];
        $totalCount = 0;
        $dbError = null;

        if (!empty($company['db_identifier'])) {
            try {
                $localDbConfig = $config['database'];
                $localDbConfig['database'] = $company['db_identifier'];
                $localDb = new \App\Core\Database($localDbConfig);
                $localPdo = $localDb->connection();
                applyLocalMigrations($localPdo);

                $stmt = $localPdo->prepare("SELECT * FROM clients ORDER BY created_at DESC LIMIT 200");
                $stmt->execute();
                $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $totalCount = count($items);
            } catch (\Exception $e) {
                $dbError = 'Локальная БД компании недоступна.';
            }
        } else {
            $dbError = 'Локальная БД компании недоступна.';
        }
    } catch (\Exception $e) {
        $company = null;
        $items = [];
        $totalCount = 0;
        $dbError = 'Ошибка подключения к базе данных.';
    }

    ob_start();
    require base_path('app/View/pages/superadmin_company_clients.php');
    $content = ob_get_clean();
    require base_path('app/View/layouts/main.php');
});

$router->get('/superadmin/companies/{id}/contractors', function ($id) use ($config, $db) {
    requireRole('superadmin');

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([(int)$id]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company) {
            $company = null;
            $items = [];
            $totalCount = 0;
            $dbError = 'Компания не найдена';

            ob_start();
            require base_path('app/View/pages/superadmin_company_contractors.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $pageTitle = 'Перевозчики: ' . $company['name'];
        $pageContext = 'Реестр компаний';

        $items = [];
        $totalCount = 0;
        $dbError = null;

        if (!empty($company['db_identifier'])) {
            try {
                $localDbConfig = $config['database'];
                $localDbConfig['database'] = $company['db_identifier'];
                $localDb = new \App\Core\Database($localDbConfig);
                $localPdo = $localDb->connection();
                applyLocalMigrations($localPdo);

                $stmt = $localPdo->prepare("SELECT * FROM contractors ORDER BY created_at DESC LIMIT 200");
                $stmt->execute();
                $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $totalCount = count($items);
            } catch (\Exception $e) {
                $dbError = 'Локальная БД компании недоступна.';
            }
        } else {
            $dbError = 'Локальная БД компании недоступна.';
        }
    } catch (\Exception $e) {
        $company = null;
        $items = [];
        $totalCount = 0;
        $dbError = 'Ошибка подключения к базе данных.';
    }

    ob_start();
    require base_path('app/View/pages/superadmin_company_contractors.php');
    $content = ob_get_clean();
    require base_path('app/View/layouts/main.php');
});

$router->get('/superadmin/companies/{id}/drivers', function ($id) use ($config, $db) {
    requireRole('superadmin');

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([(int)$id]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company) {
            $company = null;
            $items = [];
            $totalCount = 0;
            $dbError = 'Компания не найдена';

            ob_start();
            require base_path('app/View/pages/superadmin_company_drivers.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $pageTitle = 'Водители: ' . $company['name'];
        $pageContext = 'Реестр компаний';

        $items = [];
        $totalCount = 0;
        $dbError = null;

        if (!empty($company['db_identifier'])) {
            try {
                $localDbConfig = $config['database'];
                $localDbConfig['database'] = $company['db_identifier'];
                $localDb = new \App\Core\Database($localDbConfig);
                $localPdo = $localDb->connection();
                applyLocalMigrations($localPdo);

                $stmt = $localPdo->prepare("SELECT * FROM drivers ORDER BY created_at DESC LIMIT 200");
                $stmt->execute();
                $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $totalCount = count($items);
            } catch (\Exception $e) {
                $dbError = 'Локальная БД компании недоступна.';
            }
        } else {
            $dbError = 'Локальная БД компании недоступна.';
        }
    } catch (\Exception $e) {
        $company = null;
        $items = [];
        $totalCount = 0;
        $dbError = 'Ошибка подключения к базе данных.';
    }

    ob_start();
    require base_path('app/View/pages/superadmin_company_drivers.php');
    $content = ob_get_clean();
    require base_path('app/View/layouts/main.php');
});

$router->get('/superadmin/companies/{id}/vehicles', function ($id) use ($config, $db) {
    requireRole('superadmin');

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([(int)$id]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company) {
            $company = null;
            $items = [];
            $totalCount = 0;
            $dbError = 'Компания не найдена';

            ob_start();
            require base_path('app/View/pages/superadmin_company_vehicles.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $pageTitle = 'Транспортные единицы: ' . $company['name'];
        $pageContext = 'Реестр компаний';

        $items = [];
        $totalCount = 0;
        $dbError = null;

        if (!empty($company['db_identifier'])) {
            try {
                $localDbConfig = $config['database'];
                $localDbConfig['database'] = $company['db_identifier'];
                $localDb = new \App\Core\Database($localDbConfig);
                $localPdo = $localDb->connection();
                applyLocalMigrations($localPdo);

                $stmt = $localPdo->prepare("SELECT * FROM vehicle_units ORDER BY created_at DESC LIMIT 200");
                $stmt->execute();
                $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $totalCount = count($items);
            } catch (\Exception $e) {
                $dbError = 'Локальная БД компании недоступна.';
            }
        } else {
            $dbError = 'Локальная БД компании недоступна.';
        }
    } catch (\Exception $e) {
        $company = null;
        $items = [];
        $totalCount = 0;
        $dbError = 'Ошибка подключения к базе данных.';
    }

    ob_start();
    require base_path('app/View/pages/superadmin_company_vehicles.php');
    $content = ob_get_clean();
    require base_path('app/View/layouts/main.php');
});

$router->get('/superadmin/companies/{id}/crews', function ($id) use ($config, $db) {
    requireRole('superadmin');

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([(int)$id]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company) {
            $company = null;
            $items = [];
            $totalCount = 0;
            $dbError = 'Компания не найдена';

            ob_start();
            require base_path('app/View/pages/superadmin_company_crews.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $pageTitle = 'Экипажи: ' . $company['name'];
        $pageContext = 'Реестр компаний';

        $items = [];
        $totalCount = 0;
        $dbError = null;

        if (!empty($company['db_identifier'])) {
            try {
                $localDbConfig = $config['database'];
                $localDbConfig['database'] = $company['db_identifier'];
                $localDb = new \App\Core\Database($localDbConfig);
                $localPdo = $localDb->connection();
                applyLocalMigrations($localPdo);

                $stmt = $localPdo->prepare(
                    "SELECT c.*,
                            ct.name AS contractor_name,
                            v.plate_number,
                            d.full_name AS driver_name
                     FROM crews c
                     LEFT JOIN contractors ct ON c.contractor_id = ct.id
                     LEFT JOIN vehicle_units v ON c.vehicle_id = v.id
                     LEFT JOIN drivers d ON c.driver_id = d.id
                     ORDER BY c.created_at DESC LIMIT 200"
                );
                $stmt->execute();
                $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $totalCount = count($items);
            } catch (\Exception $e) {
                $dbError = 'Локальная БД компании недоступна.';
            }
        } else {
            $dbError = 'Локальная БД компании недоступна.';
        }
    } catch (\Exception $e) {
        $company = null;
        $items = [];
        $totalCount = 0;
        $dbError = 'Ошибка подключения к базе данных.';
    }

    ob_start();
    require base_path('app/View/pages/superadmin_company_crews.php');
    $content = ob_get_clean();
    require base_path('app/View/layouts/main.php');
});

$router->get('/superadmin/companies/{id}/documents', function ($id) use ($config, $db) {
    requireRole('superadmin');
    $pageTitle = 'Документы компании';
    $pageContext = 'Реестр компаний';

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([(int)$id]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company) {
            $company = null;
            $documents = [];
            $totalCount = 0;
            $dbError = null;

            ob_start();
            require base_path('app/View/pages/superadmin_company_documents.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $pageTitle = 'Документы: ' . $company['name'];
        $pageContext = 'Реестр компаний';

        $documents = [];
        $totalCount = 0;
        if (!empty($company['db_identifier'])) {
            try {
                $localDbConfig = $config['database'];
                $localDbConfig['database'] = $company['db_identifier'];
                $localDb = new \App\Core\Database($localDbConfig);
                $localPdo = $localDb->connection();
                applyLocalMigrations($localPdo);
                $stmt = $localPdo->prepare(
                    "SELECT id, entity_type, entity_id, original_name, stored_name,
                            file_size, mime_type, status, created_at,
                            uploaded_by_user_id, uploaded_by_role
                     FROM documents
                     ORDER BY created_at DESC
                     LIMIT 100"
                );
                $stmt->execute();
                $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $totalCount = count($documents);
            } catch (\Exception $e) {
                $dbError = 'Локальная БД компании недоступна.';
            }
        } else {
            $dbError = 'Локальная БД компании недоступна.';
        }

        $dbError = $dbError ?? null;
    } catch (\Exception $e) {
        $company = null;
        $documents = [];
        $totalCount = 0;
        $dbError = 'Ошибка подключения к базе данных. Попробуйте позже.';
    }

    ob_start();
    require base_path('app/View/pages/superadmin_company_documents.php');
    $content = ob_get_clean();
    require base_path('app/View/layouts/main.php');
});

$router->get('/superadmin/companies/{company_id}/documents/{document_id}/download', function ($company_id, $document_id) use ($config, $db) {
    requireRole('superadmin');

    $pdo = $db->connection();
    $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
    $stmt->execute([(int)$company_id]);
    $company = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$company || empty($company['db_identifier'])) {
        http_response_code(404);
        echo 'Document not found';
        return;
    }

    try {
        $localDbConfig = $config['database'];
        $localDbConfig['database'] = $company['db_identifier'];
        $localDb = new \App\Core\Database($localDbConfig);
        $localPdo = $localDb->connection();
                applyLocalMigrations($localPdo);

        $docStmt = $localPdo->prepare("SELECT * FROM documents WHERE id = ? AND deleted_at IS NULL");
        $docStmt->execute([(int)$document_id]);
        $document = $docStmt->fetch(PDO::FETCH_ASSOC);

        if (!$document) {
            http_response_code(404);
            echo 'Document not found';
            return;
        }

        $docRelativePath = $document['relative_path'] ?? '';
        if (!empty($docRelativePath)) {
            $filePath = realpath(storage_path($docRelativePath));
        } else {
            $storageBase = storage_path('companies/' . $company_id . '/documents/');
            $filePath = realpath($storageBase . $document['stored_name']);
        }

        $companyStorageRoot = realpath(storage_path('companies/' . $company_id));
        if ($filePath === false || ($companyStorageRoot !== false && !str_starts_with($filePath, $companyStorageRoot))) {
            http_response_code(403);
            echo 'Access denied';
            return;
        }

        if (!file_exists($filePath)) {
            http_response_code(404);
            echo 'File not found';
            return;
        }

        header('Content-Type: ' . ($document['mime_type'] ?? 'application/octet-stream'));
        header('Content-Disposition: attachment; filename="' . $document['original_name'] . '"');
        header('Content-Length: ' . filesize($filePath));
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: no-store');
        readfile($filePath);
        exit;
    } catch (\Exception $e) {
        http_response_code(500);
        echo 'Download error';
    }
});

$router->get('/superadmin/companies/{id}/access-grants', function ($id) use ($config, $db) {
    requireRole('superadmin');
    $pageTitle = 'Доступы компании';
    $pageContext = 'Реестр компаний';

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([(int)$id]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company) {
            $company = null;
            $grants = [];
            $totalCount = 0;
            $dbError = null;

            ob_start();
            require base_path('app/View/pages/superadmin_company_access_grants.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $pageTitle = 'Доступы: ' . $company['name'];
        $pageContext = 'Реестр компаний';

        $grants = [];
        $totalCount = 0;
        if (!empty($company['db_identifier'])) {
            try {
                $localDbConfig = $config['database'];
                $localDbConfig['database'] = $company['db_identifier'];
                $localDb = new \App\Core\Database($localDbConfig);
                $localPdo = $localDb->connection();
                applyLocalMigrations($localPdo);
                $stmt = $localPdo->prepare(
                    "SELECT g.id, g.entity_type, g.entity_id, g.granted_to_user_id,
                            g.granted_by_user_id, g.access_level, g.created_at,
                            u_to.full_name as granted_to_name,
                            u_by.full_name as granted_by_name
                     FROM entity_access_grants g
                     LEFT JOIN users u_to ON g.granted_to_user_id = u_to.id
                     LEFT JOIN users u_by ON g.granted_by_user_id = u_by.id
                     ORDER BY g.created_at DESC
                     LIMIT 100"
                );
                $stmt->execute();
                $grants = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $totalCount = count($grants);
            } catch (\Exception $e) {
                $dbError = 'Локальная БД компании недоступна.';
            }
        } else {
            $dbError = 'Локальная БД компании недоступна.';
        }

        $dbError = $dbError ?? null;
    } catch (\Exception $e) {
        $company = null;
        $grants = [];
        $totalCount = 0;
        $dbError = 'Ошибка подключения к базе данных. Попробуйте позже.';
    }

    ob_start();
    require base_path('app/View/pages/superadmin_company_access_grants.php');
    $content = ob_get_clean();
    require base_path('app/View/layouts/main.php');
});

$router->post('/superadmin/companies/{id}/access-grants/{grant_id}/revoke', function ($id, $grant_id) use ($config, $db) {
    requireRole('superadmin');

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([(int)$id]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company || empty($company['db_identifier'])) {
            header('Location: /superadmin/companies');
            exit;
        }

        $localDbConfig = $config['database'];
        $localDbConfig['database'] = $company['db_identifier'];
        $localDb = new \App\Core\Database($localDbConfig);
        $localPdo = $localDb->connection();
                applyLocalMigrations($localPdo);

        $localPdo->prepare("DELETE FROM entity_access_grants WHERE id = ?")->execute([(int)$grant_id]);
    } catch (\Exception $e) {
    }

    header('Location: /superadmin/companies/' . $id . '/access-grants');
    exit;
});

// ============================================================
// SUPERADMIN: Create user route before dynamic {user_id}
// ============================================================

$router->get('/superadmin/companies/{id}/users/logists/create', function ($id) use ($config, $db) {
    requireRole('superadmin');
    $pageTitle = 'Создать пользователя';
    $pageContext = 'Реестр компаний';

    $pdo = $db->connection();
    $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
    $stmt->execute([(int)$id]);
    $company = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$company) {
        $company = null;
        $errors = [];
        $old = [];
        $formError = 'Компания не найдена';
        $success = false;
        $newPassword = null;

        ob_start();
        require base_path('app/View/pages/superadmin_company_logist_create.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
        return;
    }

    $errors = [];
    $old = [];
    $formError = null;
    $success = false;
    $newPassword = null;

    ob_start();
    require base_path('app/View/pages/superadmin_company_logist_create.php');
    $content = ob_get_clean();
    require base_path('app/View/layouts/main.php');
});

// ============================================================
// SUPERADMIN: User management routes (legacy URL segment: logists)
// ============================================================

$router->get('/superadmin/companies/{company_id}/users/logists/{user_id}', function ($company_id, $user_id) use ($config, $db) {
    requireRole('superadmin');
    $pageTitle = 'Пользователь';
    $pageContext = 'Реестр компаний';

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([(int)$company_id]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company) {
            $company = null;
            $logist = null;
            $companyId = (int)$company_id;
            $logistId = (int)$user_id;
            $counts = [];
            $grantsCount = 0;
            $passwordReset = false;
            $newPassword = null;
            $dbError = null;

            ob_start();
            require base_path('app/View/pages/superadmin_company_logist_view.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $companyId = (int)$company_id;
        $logistId = (int)$user_id;
        $logist = null;
        $counts = [];
        $grantsCount = 0;
        $countsIncomplete = false;
        $passwordReset = false;
        $newPassword = null;
        $dbError = null;

        if (!empty($company['db_identifier'])) {
            try {
                $localDbConfig = $config['database'];
                $localDbConfig['database'] = $company['db_identifier'];
                $localDb = new \App\Core\Database($localDbConfig);
                $localPdo = $localDb->connection();
                applyLocalMigrations($localPdo);
                $hasColumn = static function (PDO $pdo, string $table, string $column): bool {
                    try {
                        $stmt = $pdo->prepare("SHOW COLUMNS FROM `{$table}` LIKE ?");
                        $stmt->execute([$column]);
                        return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
                    } catch (\Exception $e) {
                        return false;
                    }
                };

                $logistStmt = $localPdo->prepare("SELECT * FROM users WHERE id = ?");
                $logistStmt->execute([(int)$user_id]);
                $logist = $logistStmt->fetch(PDO::FETCH_ASSOC) ?: null;

                if ($logist) {
                    $pageTitle = 'Пользователь: ' . $logist['full_name'];

                    $countTables = ['clients', 'contractors', 'drivers', 'vehicle_units', 'crews'];
                    foreach ($countTables as $table) {
                        if ($hasColumn($localPdo, $table, 'created_by_user_id')) {
                            $countStmt = $localPdo->prepare("SELECT COUNT(*) FROM `{$table}` WHERE created_by_user_id = ?");
                            $countStmt->execute([(int)$user_id]);
                            $counts[$table] = (int)$countStmt->fetchColumn();
                        } else {
                            $counts[$table] = null;
                            $countsIncomplete = true;
                        }
                    }

                    if ($hasColumn($localPdo, 'documents', 'uploaded_by_user_id')) {
                        $docStmt = $localPdo->prepare("SELECT COUNT(*) FROM documents WHERE uploaded_by_user_id = ?");
                        $docStmt->execute([(int)$user_id]);
                        $counts['documents'] = (int)$docStmt->fetchColumn();
                    } else {
                        $counts['documents'] = null;
                        $countsIncomplete = true;
                    }

                    if ($hasColumn($localPdo, 'entity_access_grants', 'granted_to_user_id')) {
                        $grantStmt = $localPdo->prepare("SELECT COUNT(*) FROM entity_access_grants WHERE granted_to_user_id = ?");
                        $grantStmt->execute([(int)$user_id]);
                        $grantsCount = (int)$grantStmt->fetchColumn();
                    } else {
                        $grantsCount = null;
                        $countsIncomplete = true;
                    }
                }
            } catch (\Exception $e) {
                $dbError = 'Ошибка подключения к локальной БД компании.';
            }
        } else {
            $dbError = 'Локальная БД компании недоступна.';
        }
    } catch (\Exception $e) {
        $company = null;
        $logist = null;
        $companyId = (int)$company_id;
        $logistId = (int)$user_id;
        $counts = [];
        $grantsCount = 0;
        $countsIncomplete = false;
        $passwordReset = false;
        $newPassword = null;
        $dbError = 'Ошибка загрузки данных: ' . $e->getMessage();
    }

    ob_start();
    require base_path('app/View/pages/superadmin_company_logist_view.php');
    $content = ob_get_clean();
    require base_path('app/View/layouts/main.php');
});

$router->get('/superadmin/companies/{company_id}/users/logists/{user_id}/edit', function ($company_id, $user_id) use ($config, $db) {
    requireRole('superadmin');
    $pageTitle = 'Редактировать пользователя';
    $pageContext = 'Реестр компаний';

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([(int)$company_id]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company) {
            $company = null;
            $companyId = (int)$company_id;
            $logist = null;
            $logistId = (int)$user_id;
            $errors = [];
            $old = [];
            $formError = 'Компания не найдена';

            ob_start();
            require base_path('app/View/pages/superadmin_company_logist_edit.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $pageTitle = 'Редактировать: ' . $company['name'];
        $companyId = (int)$company_id;
        $logistId = (int)$user_id;
        $logist = null;
        $errors = [];
        $old = [];
        $formError = null;

        if (!empty($company['db_identifier'])) {
            try {
                $localDbConfig = $config['database'];
                $localDbConfig['database'] = $company['db_identifier'];
                $localDb = new \App\Core\Database($localDbConfig);
                $localPdo = $localDb->connection();
                applyLocalMigrations($localPdo);

                $logistStmt = $localPdo->prepare("SELECT * FROM users WHERE id = ?");
                $logistStmt->execute([(int)$user_id]);
                $logist = $logistStmt->fetch(PDO::FETCH_ASSOC) ?: null;

                if ($logist) {
                    $pageTitle = 'Редактировать: ' . $logist['full_name'];
                    $old = $logist;
                }
            } catch (\Exception $e) {
                $formError = 'Локальная БД компании недоступна.';
            }
        } else {
            $formError = 'Локальная БД компании недоступна.';
        }
    } catch (\Exception $e) {
        $company = null;
        $companyId = (int)$company_id;
        $logist = null;
        $logistId = (int)$user_id;
        $errors = [];
        $old = [];
        $formError = 'Ошибка загрузки данных: ' . $e->getMessage();
    }

    ob_start();
    require base_path('app/View/pages/superadmin_company_logist_edit.php');
    $content = ob_get_clean();
    require base_path('app/View/layouts/main.php');
});

$router->post('/superadmin/companies/{company_id}/users/logists/{user_id}/edit', function ($company_id, $user_id) use ($config, $db) {
    requireRole('superadmin');
    $pageTitle = 'Редактировать пользователя';
    $pageContext = 'Реестр компаний';

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([(int)$company_id]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company) {
            $company = null;
            $companyId = (int)$company_id;
            $logist = null;
            $logistId = (int)$user_id;
            $errors = [];
            $old = $_POST;
            $formError = 'Компания не найдена';

            ob_start();
            require base_path('app/View/pages/superadmin_company_logist_edit.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $companyId = (int)$company_id;
        $logistId = (int)$user_id;

        if (empty($company['db_identifier'])) {
            $logist = null;
            $errors = [];
            $old = $_POST;
            $formError = 'Локальная БД компании недоступна.';

            ob_start();
            require base_path('app/View/pages/superadmin_company_logist_edit.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $localDbConfig = $config['database'];
        $localDbConfig['database'] = $company['db_identifier'];
        $localDb = new \App\Core\Database($localDbConfig);
        $localPdo = $localDb->connection();
                applyLocalMigrations($localPdo);

        $logistStmt = $localPdo->prepare("SELECT * FROM users WHERE id = ?");
        $logistStmt->execute([(int)$user_id]);
        $logist = $logistStmt->fetch(PDO::FETCH_ASSOC) ?: null;

        if (!$logist) {
            $errors = [];
            $old = $_POST;
            $formError = 'Пользователь не найден.';

            ob_start();
            require base_path('app/View/pages/superadmin_company_logist_edit.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $pageTitle = 'Редактировать: ' . $logist['full_name'];

        $errors = [];
        $old = $_POST;

        $fullName = trim($_POST['full_name'] ?? '');
        $login = trim($_POST['login'] ?? '');
        $roleCode = trim($_POST['role_code'] ?? $logist['role_code']);

        if ($fullName === '') {
            $errors['full_name'] = 'Обязательное поле';
        }

        if ($login === '') {
            $errors['login'] = 'Обязательное поле';
        } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $login)) {
            $errors['login'] = 'Только латинские буквы, цифры и подчёркивание';
        } else {
            $dupStmt = $localPdo->prepare("SELECT COUNT(*) FROM users WHERE login = ? AND id != ?");
            $dupStmt->execute([$login, (int)$user_id]);
            if ($dupStmt->fetchColumn() > 0) {
                $errors['login'] = 'Логин уже используется в этой компании';
            }
        }

        // FR12: Validate role
        $allowedRoles = ['logist', 'senior_logist'];
        if (!in_array($roleCode, $allowedRoles, true)) {
            $errors['role_code'] = 'Недопустимая роль';
        }

        $email = trim($_POST['email'] ?? '');
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Некорректный email';
        }

        if (!empty($errors)) {
            ob_start();
            require base_path('app/View/pages/superadmin_company_logist_edit.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $update = $localPdo->prepare(
            "UPDATE users SET
                full_name = :full_name,
                login = :login,
                email = :email,
                phone = :phone,
                role_code = :role_code,
                status = :status,
                comments = :comments,
                updated_at = NOW()
             WHERE id = :id"
        );

        $update->execute([
            ':full_name' => $fullName,
            ':login'     => $login,
            ':email'     => $email !== '' ? $email : null,
            ':phone'     => trim($_POST['phone'] ?? '') ?: null,
            ':role_code' => $roleCode,
            ':status'    => $_POST['status'] ?? $logist['status'],
            ':comments'  => trim($_POST['comments'] ?? '') ?: null,
            ':id'        => (int)$user_id,
        ]);

        header('Location: /superadmin/companies/' . $company_id . '/users/logists/' . $user_id);
        exit;
    } catch (\Exception $e) {
        $company = $company ?? null;
        $companyId = (int)$company_id;
        $logist = $logist ?? null;
        $logistId = (int)$user_id;
        $errors = [];
        $old = $_POST;
        $formError = 'Ошибка сохранения: ' . $e->getMessage();

        ob_start();
        require base_path('app/View/pages/superadmin_company_logist_edit.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
    }
});

$router->post('/superadmin/companies/{company_id}/users/logists/{user_id}/reset-password', function ($company_id, $user_id) use ($config, $db) {
    requireRole('superadmin');
    $pageTitle = 'Пользователь';
    $pageContext = 'Реестр компаний';

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([(int)$company_id]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company) {
            $company = null;
            $companyId = (int)$company_id;
            $logist = null;
            $logistId = (int)$user_id;
            $counts = [];
            $grantsCount = 0;
            $countsIncomplete = false;
            $passwordReset = false;
            $newPassword = null;
            $dbError = 'Компания не найдена';

            ob_start();
            require base_path('app/View/pages/superadmin_company_logist_view.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $companyId = (int)$company_id;
        $logistId = (int)$user_id;

        if (empty($company['db_identifier'])) {
            $logist = null;
            $counts = [];
            $grantsCount = 0;
            $countsIncomplete = false;
            $passwordReset = false;
            $newPassword = null;
            $dbError = 'Локальная БД компании недоступна.';

            ob_start();
            require base_path('app/View/pages/superadmin_company_logist_view.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $localDbConfig = $config['database'];
        $localDbConfig['database'] = $company['db_identifier'];
        $localDb = new \App\Core\Database($localDbConfig);
        $localPdo = $localDb->connection();
                applyLocalMigrations($localPdo);
        $hasColumn = static function (PDO $pdo, string $table, string $column): bool {
            try {
                $stmt = $pdo->prepare("SHOW COLUMNS FROM `{$table}` LIKE ?");
                $stmt->execute([$column]);
                return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
            } catch (\Exception $e) {
                return false;
            }
        };

                $logistStmt = $localPdo->prepare("SELECT * FROM users WHERE id = ?");
                $logistStmt->execute([(int)$user_id]);
                $logist = $logistStmt->fetch(PDO::FETCH_ASSOC) ?: null;

        if (!$logist) {
            $counts = [];
            $grantsCount = 0;
            $countsIncomplete = false;
            $passwordReset = false;
            $newPassword = null;
            $dbError = null;

            ob_start();
            require base_path('app/View/pages/superadmin_company_logist_view.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $pageTitle = 'Пользователь: ' . $logist['full_name'];

        $newPassword = generatePassword(10);
        $passwordHash = password_hash($newPassword, PASSWORD_BCRYPT);

        $update = $localPdo->prepare("UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?");
        $update->execute([$passwordHash, (int)$logist['id']]);

        $passwordReset = true;
        $dbError = null;

        $countTables = ['clients', 'contractors', 'drivers', 'vehicle_units', 'crews'];
        $counts = [];
        $countsIncomplete = false;
        foreach ($countTables as $table) {
            if ($hasColumn($localPdo, $table, 'created_by_user_id')) {
                $countStmt = $localPdo->prepare("SELECT COUNT(*) FROM `{$table}` WHERE created_by_user_id = ?");
                $countStmt->execute([(int)$user_id]);
                $counts[$table] = (int)$countStmt->fetchColumn();
            } else {
                $counts[$table] = null;
                $countsIncomplete = true;
            }
        }
        if ($hasColumn($localPdo, 'documents', 'uploaded_by_user_id')) {
            $docStmt = $localPdo->prepare("SELECT COUNT(*) FROM documents WHERE uploaded_by_user_id = ?");
            $docStmt->execute([(int)$user_id]);
            $counts['documents'] = (int)$docStmt->fetchColumn();
        } else {
            $counts['documents'] = null;
            $countsIncomplete = true;
        }
        if ($hasColumn($localPdo, 'entity_access_grants', 'granted_to_user_id')) {
            $grantStmt = $localPdo->prepare("SELECT COUNT(*) FROM entity_access_grants WHERE granted_to_user_id = ?");
            $grantStmt->execute([(int)$user_id]);
            $grantsCount = (int)$grantStmt->fetchColumn();
        } else {
            $grantsCount = null;
            $countsIncomplete = true;
        }

        ob_start();
        require base_path('app/View/pages/superadmin_company_logist_view.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
    } catch (\Exception $e) {
        $company = $company ?? null;
        $companyId = (int)$company_id;
        $logist = $logist ?? null;
        $logistId = (int)$user_id;
        $counts = [];
        $grantsCount = 0;
        $passwordReset = false;
        $newPassword = null;
        $dbError = 'Ошибка сброса пароля: ' . $e->getMessage();

        ob_start();
        require base_path('app/View/pages/superadmin_company_logist_view.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
    }
});

$router->post('/superadmin/companies/{company_id}/users/logists/{user_id}/activate', function ($company_id, $user_id) use ($config, $db) {
    requireRole('superadmin');

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([(int)$company_id]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company || empty($company['db_identifier'])) {
            header('Location: /superadmin/companies/' . $company_id . '/users');
            exit;
        }

        $localDbConfig = $config['database'];
        $localDbConfig['database'] = $company['db_identifier'];
        $localDb = new \App\Core\Database($localDbConfig);
        $localPdo = $localDb->connection();
                applyLocalMigrations($localPdo);

        $localPdo->prepare("UPDATE users SET status = 'active', updated_at = NOW() WHERE id = ?")
            ->execute([(int)$user_id]);
    } catch (\Exception $e) {
    }

    header('Location: /superadmin/companies/' . $company_id . '/users/logists/' . $user_id . '?status_changed=1');
    exit;
});

$router->post('/superadmin/companies/{company_id}/users/logists/{user_id}/block', function ($company_id, $user_id) use ($config, $db) {
    requireRole('superadmin');

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([(int)$company_id]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company || empty($company['db_identifier'])) {
            header('Location: /superadmin/companies/' . $company_id . '/users');
            exit;
        }

        $localDbConfig = $config['database'];
        $localDbConfig['database'] = $company['db_identifier'];
        $localDb = new \App\Core\Database($localDbConfig);
        $localPdo = $localDb->connection();
                applyLocalMigrations($localPdo);

        $localPdo->prepare("UPDATE users SET status = 'blocked', updated_at = NOW() WHERE id = ?")
            ->execute([(int)$user_id]);
    } catch (\Exception $e) {
    }

    header('Location: /superadmin/companies/' . $company_id . '/users/logists/' . $user_id . '?status_changed=1');
    exit;
});

$router->post('/superadmin/companies/{company_id}/users/logists/{user_id}/archive', function ($company_id, $user_id) use ($config, $db) {
    requireRole('superadmin');

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([(int)$company_id]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company || empty($company['db_identifier'])) {
            header('Location: /superadmin/companies/' . $company_id . '/users');
            exit;
        }

        $localDbConfig = $config['database'];
        $localDbConfig['database'] = $company['db_identifier'];
        $localDb = new \App\Core\Database($localDbConfig);
        $localPdo = $localDb->connection();
                applyLocalMigrations($localPdo);

        $localPdo->prepare("UPDATE users SET status = 'archived', updated_at = NOW() WHERE id = ?")
            ->execute([(int)$user_id]);
    } catch (\Exception $e) {
    }

    header('Location: /superadmin/companies/' . $company_id . '/users/logists/' . $user_id . '?status_changed=1');
    exit;
});

$router->post('/superadmin/companies/{id}/users/logists/create', function ($id) use ($config, $db) {
    requireRole('superadmin');
    $pageTitle = 'Создать пользователя';
    $pageContext = 'Реестр компаний';

    $pdo = $db->connection();
    $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
    $stmt->execute([(int)$id]);
    $company = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$company) {
        $company = null;
        $errors = [];
        $old = $_POST;
        $formError = 'Компания не найдена';
        $success = false;
        $newPassword = null;

        ob_start();
        require base_path('app/View/pages/superadmin_company_logist_create.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
        return;
    }

    $errors = [];
    $old = $_POST;
    $formError = null;
    $success = false;
    $newPassword = null;

    $fullName = trim($_POST['full_name'] ?? '');
    $login = trim($_POST['login'] ?? '');
    $roleCode = trim($_POST['role_code'] ?? 'logist');

    if ($fullName === '') {
        $errors['full_name'] = 'Обязательное поле';
    }
    if ($login === '') {
        $errors['login'] = 'Обязательное поле';
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $login)) {
        $errors['login'] = 'Только латиница, цифры и _';
    }

    // FR10: Validate role against whitelist
    $allowedRoles = ['logist', 'senior_logist'];
    if (!in_array($roleCode, $allowedRoles, true)) {
        $errors['role_code'] = 'Недопустимая роль';
    }

    if (!empty($errors)) {
        ob_start();
        require base_path('app/View/pages/superadmin_company_logist_create.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
        return;
    }

    if (empty($company['db_identifier'])) {
        $formError = 'Локальная БД компании недоступна.';
        ob_start();
        require base_path('app/View/pages/superadmin_company_logist_create.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
        return;
    }

    try {
        // Создать локальную БД, если не существует
        try {
            $tempPdo = new PDO(
                sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $config['database']['host'] ?? '127.0.0.1', $config['database']['port'] ?? '3306'),
                $config['database']['username'] ?? 'root',
                $config['database']['password'] ?? ''
            );
            $tempPdo->exec("CREATE DATABASE IF NOT EXISTS `{$company['db_identifier']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        } catch (\Exception $e) {
            // Игнорируем ошибку создания БД — основное подключение поймает проблему
        }
        // Release tempPdo to avoid any connection state interference
        $tempPdo = null;

        $localDbConfig = $config['database'];
        $localDbConfig['database'] = $company['db_identifier'];
        $localDb = new \App\Core\Database($localDbConfig);
        $localPdo = $localDb->connection();
                applyLocalMigrations($localPdo);

        $dupStmt = $localPdo->prepare("SELECT COUNT(*) FROM users WHERE login = ?");
        $dupStmt->execute([$login]);
        if ($dupStmt->fetchColumn() > 0) {
            $errors['login'] = 'Логин уже используется в этой компании';
            ob_start();
            require base_path('app/View/pages/superadmin_company_logist_create.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        if (!empty($_POST['password'])) {
            $newPassword = $_POST['password'];
        } else {
            $newPassword = generatePassword(10);
        }
        $passwordHash = password_hash($newPassword, PASSWORD_BCRYPT);

        $insert = $localPdo->prepare(
            "INSERT INTO users (full_name, login, email, phone, password_hash, role_code, status, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, 'active', NOW(), NOW())"
        );
        $insert->execute([
            $fullName,
            $login,
            $_POST['email'] ?? null,
            $_POST['phone'] ?? null,
            $passwordHash,
            $roleCode,
        ]);

        $old['role_label'] = $roleCode === 'logist' ? 'Пользователь' : $roleCode;
        $success = true;

        ob_start();
        require base_path('app/View/pages/superadmin_company_logist_create.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
    } catch (\Exception $e) {
        $formError = 'Ошибка создания пользователя: ' . $e->getMessage();
        ob_start();
        require base_path('app/View/pages/superadmin_company_logist_create.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
    }
});

// ============================================================
// SUPERADMIN: Owner status actions (NEW)
// ============================================================

$router->post('/superadmin/companies/{company_id}/users/owner/{user_id}/activate', function ($company_id, $user_id) use ($config, $db) {
    requireRole('superadmin');
    $pdo = $db->connection();
    $pdo->prepare("UPDATE company_users SET status = 'active', updated_at = NOW() WHERE id = ? AND company_id = ? AND role = 'company_owner'")
        ->execute([(int)$user_id, (int)$company_id]);
    header('Location: /superadmin/companies/' . $company_id . '/users?status_changed=1');
    exit;
});

$router->post('/superadmin/companies/{company_id}/users/owner/{user_id}/block', function ($company_id, $user_id) use ($config, $db) {
    requireRole('superadmin');
    $pdo = $db->connection();
    $pdo->prepare("UPDATE company_users SET status = 'blocked', updated_at = NOW() WHERE id = ? AND company_id = ? AND role = 'company_owner'")
        ->execute([(int)$user_id, (int)$company_id]);
    header('Location: /superadmin/companies/' . $company_id . '/users?status_changed=1');
    exit;
});

$router->post('/superadmin/companies/{company_id}/users/owner/{user_id}/archive', function ($company_id, $user_id) use ($config, $db) {
    requireRole('superadmin');
    $pdo = $db->connection();
    $pdo->prepare("UPDATE company_users SET status = 'archived', updated_at = NOW() WHERE id = ? AND company_id = ? AND role = 'company_owner'")
        ->execute([(int)$user_id, (int)$company_id]);
    header('Location: /superadmin/companies/' . $company_id . '/users?status_changed=1');
    exit;
});

$router->get('/superadmin/companies/{id}/delete', function ($id) use ($config, $db) {
    requireRole('superadmin');
    $pageTitle = 'Удаление компании';
    $pageContext = 'Реестр компаний';

    $pdo = $db->connection();
    $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
    $stmt->execute([(int)$id]);
    $company = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$company) {
        ob_start();
        echo '<div class="panel"><div class="panel-body"><div class="notice warn">Компания не найдена. <a href="/superadmin/companies">< К реестру</a></div></div></div>';
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
        return;
    }

    $preview = [];
    $preview['company_name'] = $company['name'];
    $preview['company_inn'] = $company['inn'] ?? '—';
    $preview['company_id'] = $company['id'];
    $preview['db_identifier'] = $company['db_identifier'] ?? '—';
    $preview['storage_path'] = $company['storage_path'] ?? '—';
    $preview['status'] = $company['status'];

    $ownerStmt = $pdo->prepare("SELECT * FROM company_users WHERE company_id = ? AND role = 'company_owner'");
    $ownerStmt->execute([(int)$id]);
    $preview['owner'] = $ownerStmt->fetch(PDO::FETCH_ASSOC) ?: null;

    $preview['logists_count'] = 0;
    $preview['clients_count'] = 0;
    $preview['contractors_count'] = 0;
    $preview['drivers_count'] = 0;
    $preview['vehicles_count'] = 0;
    $preview['crews_count'] = 0;
    $preview['documents_count'] = 0;
    $preview['storage_size'] = 'неизвестно';
    $localDbError = null;

    if (!empty($company['db_identifier'])) {
        try {
            $localDbConfig = $config['database'];
            $localDbConfig['database'] = $company['db_identifier'];
            $localDb = new \App\Core\Database($localDbConfig);
            $localPdo = $localDb->connection();
                applyLocalMigrations($localPdo);

            $tables = [
                'users' => 'logists_count',
                'clients' => 'clients_count',
                'contractors' => 'contractors_count',
                'drivers' => 'drivers_count',
                'vehicle_units' => 'vehicles_count',
                'crews' => 'crews_count',
                'documents' => 'documents_count'
            ];
            foreach ($tables as $table => $key) {
                $where = '';
                $preview[$key] = (int)$localPdo->query("SELECT COUNT(*) FROM `{$table}`{$where}")->fetchColumn();
            }

            $storageAbs = storage_path('companies/' . $id);
            if (is_dir($storageAbs)) {
                $size = 0;
                $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($storageAbs, RecursiveDirectoryIterator::SKIP_DOTS));
                foreach ($rii as $f) { $size += $f->getSize(); }
                $preview['storage_size'] = formatFileSize($size);
            } else {
                $preview['storage_size'] = 'папка не существует';
            }
        } catch (\Exception $e) {
            $localDbError = 'Локальная БД недоступна: ' . $e->getMessage();
        }
    }

    $dbError = null;
    $confirmError = null;
    $confirmValue = '';

    ob_start();
    require base_path('app/View/pages/superadmin_company_delete.php');
    $content = ob_get_clean();
    require base_path('app/View/layouts/main.php');
});

$router->post('/superadmin/companies/{id}/delete', function ($id) use ($config, $db) {
    requireRole('superadmin');
    $pageTitle = 'Удаление компании';
    $pageContext = 'Реестр компаний';

    $companyId = (int)$id;
    $pdo = $db->connection();

    $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
    $stmt->execute([$companyId]);
    $company = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$company) {
        header('Location: /superadmin/companies');
        exit;
    }

    $confirmPhrase = trim($_POST['confirm_phrase'] ?? '');
    $expected = 'DELETE COMPANY ' . $companyId;

    if ($confirmPhrase !== $expected) {
        $confirmError = 'Неверная контрольная фраза. Требуется: ' . $expected;

        $preview = [];
        $preview['company_name'] = $company['name'];
        $preview['company_inn'] = $company['inn'] ?? '—';
        $preview['company_id'] = $company['id'];
        $preview['db_identifier'] = $company['db_identifier'] ?? '—';
        $preview['storage_path'] = $company['storage_path'] ?? '—';
        $preview['status'] = $company['status'];

        $ownerStmt = $pdo->prepare("SELECT * FROM company_users WHERE company_id = ? AND role = 'company_owner'");
        $ownerStmt->execute([$companyId]);
        $preview['owner'] = $ownerStmt->fetch(PDO::FETCH_ASSOC) ?: null;

        $preview['logists_count'] = 0;
        $preview['clients_count'] = 0;
        $preview['contractors_count'] = 0;
        $preview['drivers_count'] = 0;
        $preview['vehicles_count'] = 0;
        $preview['crews_count'] = 0;
        $preview['documents_count'] = 0;
        $preview['storage_size'] = 'неизвестно';
        $localDbError = null;

        if (!empty($company['db_identifier'])) {
            try {
                $localDbConfig = $config['database'];
                $localDbConfig['database'] = $company['db_identifier'];
                $localDb = new \App\Core\Database($localDbConfig);
                $localPdo = $localDb->connection();
                applyLocalMigrations($localPdo);

                $tables = [
                    'users' => 'logists_count',
                    'clients' => 'clients_count',
                    'contractors' => 'contractors_count',
                    'drivers' => 'drivers_count',
                    'vehicle_units' => 'vehicles_count',
                    'crews' => 'crews_count',
                    'documents' => 'documents_count'
                ];
                foreach ($tables as $table => $key) {
                    $where = '';
                    $preview[$key] = (int)$localPdo->query("SELECT COUNT(*) FROM `{$table}`{$where}")->fetchColumn();
                }

                $storageAbs = storage_path('companies/' . $companyId);
                if (is_dir($storageAbs)) {
                    $size = 0;
                    $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($storageAbs, RecursiveDirectoryIterator::SKIP_DOTS));
                    foreach ($rii as $f) { $size += $f->getSize(); }
                    $preview['storage_size'] = formatFileSize($size);
                } else {
                    $preview['storage_size'] = 'папка не существует';
                }
            } catch (\Exception $e) {
                $localDbError = 'Локальная БД недоступна: ' . $e->getMessage();
            }
        }

        $dbError = null;
        $confirmValue = $confirmPhrase;

        ob_start();
        require base_path('app/View/pages/superadmin_company_delete.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
        return;
    }

    $dbIdentifier = $company['db_identifier'] ?? '';
    $expectedDb = 'erp_company_' . $companyId;

    if ($dbIdentifier !== $expectedDb) {
        $confirmError = 'BLOCKED: db_identifier не соответствует шаблону. Удаление невозможно.';

        $preview = [];
        $preview['company_name'] = $company['name'];
        $preview['company_inn'] = $company['inn'] ?? '—';
        $preview['company_id'] = $company['id'];
        $preview['db_identifier'] = $company['db_identifier'] ?? '—';
        $preview['storage_path'] = $company['storage_path'] ?? '—';
        $preview['status'] = $company['status'];

        $ownerStmt = $pdo->prepare("SELECT * FROM company_users WHERE company_id = ? AND role = 'company_owner'");
        $ownerStmt->execute([$companyId]);
        $preview['owner'] = $ownerStmt->fetch(PDO::FETCH_ASSOC) ?: null;

        $preview['logists_count'] = 0;
        $preview['clients_count'] = 0;
        $preview['contractors_count'] = 0;
        $preview['drivers_count'] = 0;
        $preview['vehicles_count'] = 0;
        $preview['crews_count'] = 0;
        $preview['documents_count'] = 0;
        $preview['storage_size'] = 'неизвестно';
        $localDbError = null;

        $dbError = null;
        $confirmValue = $confirmPhrase;

        ob_start();
        require base_path('app/View/pages/superadmin_company_delete.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
        return;
    }

    $timestamp = date('Ymd_His');
    $backupDir = storage_path('backups/deleted-companies/company_' . $companyId . '_' . $timestamp);
    if (!is_dir($backupDir)) { mkdir($backupDir, 0755, true); }

    $report = [
        'company_id' => $companyId,
        'company_name' => $company['name'],
        'company_inn' => $company['inn'] ?? '',
        'deleted_by' => $_SESSION['user_id'] ?? 0,
        'deleted_at' => date('Y-m-d H:i:s'),
        'db_identifier' => $dbIdentifier,
        'steps' => [],
        'backup_location' => $backupDir,
    ];

    $snapshot = ['company' => $company];
    $ownerStmt = $pdo->prepare("SELECT * FROM company_users WHERE company_id = ?");
    $ownerStmt->execute([$companyId]);
    $snapshot['company_users'] = $ownerStmt->fetchAll(PDO::FETCH_ASSOC);
    file_put_contents($backupDir . '/central_snapshot.json', json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    $report['steps'][] = 'central_snapshot_saved';

    $dumpFile = $backupDir . '/local_db_dump.sql';
    $dumpCreated = false;
    if (!empty($dbIdentifier)) {
        $dbUser = $config['database']['username'] ?? 'root';
        $dbPass = $config['database']['password'] ?? '';
        $dbHost = $config['database']['host'] ?? '127.0.0.1';
        $dbPort = $config['database']['port'] ?? '3306';

        $mysqldumpAvailable = false;
        $mysqldumpPath = trim(shell_exec('where mysqldump 2>NUL') ?? '');
        if (!empty($mysqldumpPath)) {
            $mysqldumpAvailable = true;
        }

        if ($mysqldumpAvailable) {
            $cmd = "mysqldump --host=" . escapeshellarg($dbHost) . " --port=" . escapeshellarg($dbPort) . " --user=" . escapeshellarg($dbUser) . " ";
            if ($dbPass !== '') { $cmd .= "--password=" . escapeshellarg($dbPass) . " "; }
            $cmd .= "--no-tablespaces --single-transaction --routines --triggers " . escapeshellarg($dbIdentifier);
        }

        if ($mysqldumpAvailable) {
            $output = null; $retval = 0;
            exec($cmd . ' 2>&1', $output, $retval);
            if ($retval === 0) {
                file_put_contents($dumpFile, implode("\n", $output));
                $dumpCreated = true;
                $report['steps'][] = 'local_db_dump_created';
            } else {
                $report['steps'][] = 'local_db_dump_failed: ' . implode(' ', array_slice($output, 0, 3));
            }
        } else {
            $report['steps'][] = 'local_db_dump_skipped: mysqldump unavailable';
        }
    }

    $storageAbs = storage_path('companies/' . $companyId);
    $storageBackedUp = false;
    if (is_dir($storageAbs)) {
        if (!class_exists('ZipArchive')) {
            $report['steps'][] = 'storage_backup_skipped: ZipArchive unavailable';
            // Fallback: move storage folder to backup instead of zipping
            $deletedStorageDir = $backupDir . '/deleted_storage';
            if (rename($storageAbs, $deletedStorageDir)) {
                $storageBackedUp = true;
                $report['steps'][] = 'storage_moved_to_backup (fallback, no ZipArchive)';
            } else {
                $report['steps'][] = 'storage_backup_failed: cannot move folder';
            }
        } else {
            $zipFile = $backupDir . '/storage_backup.zip';
            $zip = new ZipArchive();
            if ($zip->open($zipFile, ZipArchive::CREATE) === TRUE) {
                $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($storageAbs, RecursiveDirectoryIterator::SKIP_DOTS));
                foreach ($files as $file) {
                    $filePath = $file->getRealPath();
                    $relativePath = substr($filePath, strlen($storageAbs) + 1);
                    $zip->addFile($filePath, $relativePath);
                }
                $zip->close();
                $storageBackedUp = true;
                $report['steps'][] = 'storage_backup_created';
            } else {
                $report['steps'][] = 'storage_backup_failed: zip_open_error';
            }
        }
    } else {
        $report['steps'][] = 'storage_folder_not_found';
    }

    $backupDetails = [];
    if (!$dumpCreated) {
        $backupDetails[] = 'SQL дамп не создан (mysqldump ' . ($mysqldumpAvailable ?? false ? 'ошибка выполнения' : 'недоступен') . ')';
    }
    if (!$storageBackedUp) {
        $backupDetails[] = 'Storage backup не создан';
    }
    $backupDetails = implode('; ', $backupDetails);

    $backupOk = $dumpCreated || $storageBackedUp;
    $skipBackup = isset($_POST['skip_backup']) && $_POST['skip_backup'] === '1';

    if (!$backupOk && !$skipBackup) {
        $backupWarning = true;
        $confirmError = 'Backup не создан. Подтвердите удаление без backup.';

        $preview = [];
        $preview['company_name'] = $company['name'];
        $preview['company_inn'] = $company['inn'] ?? '—';
        $preview['company_id'] = $company['id'];
        $preview['db_identifier'] = $company['db_identifier'] ?? '—';
        $preview['storage_path'] = $company['storage_path'] ?? '—';
        $preview['status'] = $company['status'];

        $ownerStmt = $pdo->prepare("SELECT * FROM company_users WHERE company_id = ? AND role = 'company_owner'");
        $ownerStmt->execute([$companyId]);
        $preview['owner'] = $ownerStmt->fetch(PDO::FETCH_ASSOC) ?: null;

        $preview['logists_count'] = 0;
        $preview['clients_count'] = 0;
        $preview['contractors_count'] = 0;
        $preview['drivers_count'] = 0;
        $preview['vehicles_count'] = 0;
        $preview['crews_count'] = 0;
        $preview['documents_count'] = 0;
        $preview['storage_size'] = 'неизвестно';
        $localDbError = null;

        if (!empty($company['db_identifier'])) {
            try {
                $localDbConfig = $config['database'];
                $localDbConfig['database'] = $company['db_identifier'];
                $localDb = new \App\Core\Database($localDbConfig);
                $localPdo = $localDb->connection();
                applyLocalMigrations($localPdo);

                $tables = [
                    'users' => 'logists_count',
                    'clients' => 'clients_count',
                    'contractors' => 'contractors_count',
                    'drivers' => 'drivers_count',
                    'vehicle_units' => 'vehicles_count',
                    'crews' => 'crews_count',
                    'documents' => 'documents_count'
                ];
                foreach ($tables as $table => $key) {
                    $where = '';
                    $preview[$key] = (int)$localPdo->query("SELECT COUNT(*) FROM `{$table}`{$where}")->fetchColumn();
                }

                $storageAbs = storage_path('companies/' . $companyId);
                if (is_dir($storageAbs)) {
                    $size = 0;
                    $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($storageAbs, RecursiveDirectoryIterator::SKIP_DOTS));
                    foreach ($rii as $f) { $size += $f->getSize(); }
                    $preview['storage_size'] = formatFileSize($size);
                } else {
                    $preview['storage_size'] = 'папка не существует';
                }
            } catch (\Exception $e) {
                $localDbError = 'Локальная БД недоступна: ' . $e->getMessage();
            }
        }

        $dbError = null;
        $confirmValue = $confirmPhrase;

        ob_start();
        require base_path('app/View/pages/superadmin_company_delete.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
        return;
    }

    if (!empty($dbIdentifier)) {
        $dbConfig = $config['database'];
        $dbConfig['database'] = '';
        $sysDb = new \App\Core\Database($dbConfig);
        $sysPdo = $sysDb->connection();
        $sysPdo->exec("DROP DATABASE IF EXISTS `{$dbIdentifier}`");
        $report['steps'][] = 'local_db_dropped: ' . $dbIdentifier;
    }

    $pdo->prepare("DELETE FROM company_users WHERE company_id = ?")->execute([$companyId]);
    $report['steps'][] = 'central_company_users_deleted';

    $pdo->prepare("DELETE FROM companies WHERE id = ?")->execute([$companyId]);
    $report['steps'][] = 'central_company_deleted';

    if (is_dir($storageAbs)) {
        $deletedStorageDir = $backupDir . '/deleted_storage';
        rename($storageAbs, $deletedStorageDir);
        $report['steps'][] = 'storage_moved_to_backup';
    }

    file_put_contents($backupDir . '/delete_report.json', json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    header('Location: /superadmin/companies?deleted=' . $companyId);
    exit;
});

$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
