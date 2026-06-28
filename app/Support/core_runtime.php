<?php

use App\Service\AccessControlService;
use App\Service\LocalMigrationService;

if (!function_exists('generatePassword')) {
    function generatePassword(int $length = 10): string
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $password = '';
        for ($i = 0; $i < $length; $i++) {
            $password .= $chars[random_int(0, strlen($chars) - 1)];
        }

        return $password;
    }
}

if (!function_exists('buildPassportNumber')) {
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
}

if (!function_exists('vehicleSetTypeRules')) {
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
}

if (!function_exists('vehicleSetPredefinedDocumentSections')) {
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
}

if (!function_exists('vehicleSetDocumentUploadMap')) {
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
}

if (!function_exists('vehicleSetUnitTypeLabelList')) {
    function vehicleSetUnitTypeLabelList(array $unitTypes): string
    {
        $labels = array_values(array_filter(array_map(static function ($type) {
            return ui_unit_type((string) $type);
        }, $unitTypes)));

        return implode(', ', $labels);
    }
}

if (!function_exists('vehicleSetVisibleUnitRoles')) {
    function vehicleSetVisibleUnitRoles(string $setType): array
    {
        $rules = vehicleSetTypeRules();
        if (!isset($rules[$setType]['units']) || !is_array($rules[$setType]['units'])) {
            return [];
        }

        return array_keys($rules[$setType]['units']);
    }
}

if (!function_exists('validateVehicleSetSelection')) {
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
}

if (!function_exists('ensureDocumentTypeRecord')) {
    function ensureDocumentTypeRecord(PDO $localPdo, string $name, string $code, string $entityType, string $category = 'predefined'): ?int
    {
        return LocalMigrationService::ensureDocumentTypeRecord($localPdo, $name, $code, $entityType, $category);
    }
}

if (!function_exists('applyLocalMigrations')) {
    function applyLocalMigrations(PDO $localPdo): void
    {
        LocalMigrationService::apply($localPdo);
    }
}

if (!function_exists('hasEntityAccess')) {
    function hasEntityAccess(PDO $pdo, string $entityType, int $entityId, int $userId, array $levels = ['view', 'edit']): bool
    {
        return AccessControlService::hasEntityAccess($pdo, $entityType, $entityId, $userId, $levels);
    }
}

if (!function_exists('hasRouteExecutorAccess')) {
    function hasRouteExecutorAccess(PDO $pdo, array $crew, int $userId, string $mode = 'view'): bool
    {
        return AccessControlService::hasRouteExecutorAccess($pdo, $crew, $userId, $mode);
    }
}

if (!function_exists('formatFileSize')) {
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
}

if (!function_exists('validateTotalUploadSize')) {
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
}

if (!function_exists('isPostTruncated')) {
    function isPostTruncated(): bool
    {
        return ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0
            && empty($_POST)
            && empty($_FILES)
            && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
    }
}
