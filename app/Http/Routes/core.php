<?php

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
