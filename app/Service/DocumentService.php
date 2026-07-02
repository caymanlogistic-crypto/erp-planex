<?php

namespace App\Service;

/**
 * DocumentService — будущий единый сервис для работы с документами.
 *
 * НАЗНАЧЕНИЕ:
 *   Убрать дублирование upload-логики (4+ копий в index.php)
 *   в один сервис с едиными правилами.
 *
 * СТАТУС: FOUNDATION — НЕ ПОДКЛЮЧЁН к рабочим routes.
 *   Сервис готов к использованию в новых модулях (driver_vehicle_blocks, crews).
 *   Существующие обработчики документов в DO_NOT_TOUCH_WORKING_CORE не меняются.
 *
 * БУДУЩЕЕ:
 *   После acceptance сервиса на новых модулях — точечно переводить
 *   существующие модули (drivers, vehicle-sets, clients, contractors)
 *   на вызовы DocumentService вместо inline-логики.
 *
 * ПРАВИЛА:
 *   - entity_type — строгий whitelist
 *   - entity_id   — INT UNSIGNED
 *   - soft delete через deleted_at (hard delete запрещён)
 *   - расширения: pdf, doc, docx, rtf, odt, xls, xlsx, csv, ods,
 *                 jpg, jpeg, png, webp, gif, bmp, tif, tiff, heic, heif, txt
 *   - максимальный размер файла: 20 MB
 *   - путь: companies/{company_id}/documents/{entity_type}/{entity_id}/
 *   - безопасное имя: uniqid + оригинальное расширение
 */

final class DocumentService
{
    // -------------------------------------------------------------------------
    // Константы
    // -------------------------------------------------------------------------

    public const MAX_FILE_SIZE_BYTES = 20 * 1024 * 1024; // 20 MB

    public const ALLOWED_EXTENSIONS = [
        'pdf', 'doc', 'docx', 'rtf', 'odt',
        'xls', 'xlsx', 'csv', 'ods',
        'jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp', 'tif', 'tiff', 'heic', 'heif',
        'txt',
    ];

    /**
     * Допустимые entity_type для документов.
     * TODO: синхронизировать при добавлении новых сущностей.
     */
    public const ALLOWED_ENTITY_TYPES = [
        'driver',
        'vehicle_unit',
        'vehicle_set',
        'driver_vehicle_block',
        'crew',
        'client',
        'contractor',
        'linear_route',
    ];

    // -------------------------------------------------------------------------
    // Валидация entity_type
    // -------------------------------------------------------------------------

    /**
     * Допустим ли entity_type для документов.
     */
    public static function isAllowedEntityType(string $entityType): bool
    {
        return in_array($entityType, self::ALLOWED_ENTITY_TYPES, true);
    }

    /**
     * Нормализация entity_type: нижний регистр, trim.
     * Возвращает null если тип недопустим.
     */
    public static function normalizeEntityType(string $entityType): ?string
    {
        $normalized = trim(mb_strtolower($entityType, 'UTF-8'));
        return self::isAllowedEntityType($normalized) ? $normalized : null;
    }

    // -------------------------------------------------------------------------
    // Расширения и размеры
    // -------------------------------------------------------------------------

    /**
     * Список допустимых расширений файлов.
     */
    public static function getAllowedExtensions(): array
    {
        return self::ALLOWED_EXTENSIONS;
    }

    /**
     * Максимальный размер одного файла в байтах.
     */
    public static function getMaxFileSizeBytes(): int
    {
        return self::MAX_FILE_SIZE_BYTES;
    }

    /**
     * Проверить расширение файла по имени.
     */
    public static function isAllowedExtension(string $filename): bool
    {
        $ext = self::extractExtension($filename);
        return $ext !== '' && in_array($ext, self::ALLOWED_EXTENSIONS, true);
    }

    /**
     * Извлечь расширение из имени файла (нижний регистр, без точки).
     */
    public static function extractExtension(string $filename): string
    {
        $parts = explode('.', basename($filename));
        return count($parts) > 1 ? strtolower(end($parts)) : '';
    }

    /**
     * Проверить, не превышает ли размер файла лимит.
     */
    public static function isFileSizeValid(int $bytes): bool
    {
        return $bytes > 0 && $bytes <= self::MAX_FILE_SIZE_BYTES;
    }

    // -------------------------------------------------------------------------
    // Безопасное имя файла
    // -------------------------------------------------------------------------

    /**
     * Сгенерировать безопасное хранимое имя файла.
     * Формат: doc_<uniqid>.<ext>
     */
    public static function makeSafeFilename(string $originalName): string
    {
        $ext = self::extractExtension($originalName);
        $safeExt = $ext !== '' ? $ext : 'dat';
        return uniqid('doc_', true) . '.' . $safeExt;
    }

    /**
     * Проверить, что имя файла не содержит path traversal.
     */
    public static function isSafeOriginalName(string $originalName): bool
    {
        $name = basename($originalName);
        if ($name === '' || $name !== $originalName) {
            return false;
        }
        if (strpos($name, '../') !== false || strpos($name, '..\\') !== false) {
            return false;
        }
        return true;
    }

    // -------------------------------------------------------------------------
    // Пути хранения
    // -------------------------------------------------------------------------

    /**
     * Построить относительный путь хранения документов для сущности.
     *
     * Возвращает: companies/{companyId}/documents/{entityType}/{entityId}
     */
    public static function buildRelativePath(int $companyId, string $entityType, int $entityId): string
    {
        return sprintf(
            'companies/%d/documents/%s/%d',
            $companyId,
            self::normalizeEntityType($entityType) ?? $entityType,
            $entityId
        );
    }

    /**
     * Построить абсолютный путь к директории хранения документов.
     * Использует storage_path() из helpers.php.
     */
    public static function buildAbsoluteDir(int $companyId, string $entityType, int $entityId): string
    {
        $relative = self::buildRelativePath($companyId, $entityType, $entityId);
        return \storage_path($relative);
    }

    /**
     * Построить полный относительный путь к файлу.
     */
    public static function buildStoredFilePath(int $companyId, string $entityType, int $entityId, string $storedName): string
    {
        return self::buildRelativePath($companyId, $entityType, $entityId) . '/' . $storedName;
    }

    // -------------------------------------------------------------------------
    // Определение типа документа для UI-бейджа
    // -------------------------------------------------------------------------

    /**
     * Определить CSS-класс и текст бейджа по имени файла и MIME-типу.
     *
     * Возвращает: ['badge_class' => 'is-pdf', 'badge_text' => 'PDF']
     *
     * Используется в JS (app.js) и может использоваться в PHP для
     * предзаполнения data-атрибутов существующих документов.
     */
    public static function detectDocumentBadge(?string $filename, ?string $mimeType = null): array
    {
        $ext = $filename !== null && $filename !== '' ? self::extractExtension($filename) : '';

        if ($ext !== '') {
            return self::badgeByExtension($ext);
        }

        // Если расширение не определено — пробуем угадать по MIME
        if ($mimeType !== null && $mimeType !== '') {
            if (strpos($mimeType, 'pdf') !== false) {
                return ['badge_class' => 'is-pdf', 'badge_text' => 'PDF'];
            }
            if (strpos($mimeType, 'image') !== false) {
                return ['badge_class' => 'is-img', 'badge_text' => 'IMG'];
            }
            if (strpos($mimeType, 'word') !== false || strpos($mimeType, 'document') !== false) {
                return ['badge_class' => 'is-doc', 'badge_text' => 'DOC'];
            }
            if (strpos($mimeType, 'excel') !== false || strpos($mimeType, 'spreadsheet') !== false) {
                return ['badge_class' => 'is-xls', 'badge_text' => 'XLS'];
            }
        }

        return ['badge_class' => 'is-other', 'badge_text' => 'FILE'];
    }

    /**
     * Определить бейдж по расширению файла.
     */
    private static function badgeByExtension(string $ext): array
    {
        return match ($ext) {
            'pdf'                                   => ['badge_class' => 'is-pdf',   'badge_text' => 'PDF'],
            'doc', 'docx', 'rtf', 'odt'            => ['badge_class' => 'is-doc',   'badge_text' => 'DOC'],
            'xls', 'xlsx', 'csv', 'ods'            => ['badge_class' => 'is-xls',   'badge_text' => 'XLS'],
            'jpg', 'jpeg', 'png', 'webp', 'gif',
            'bmp', 'tif', 'tiff', 'heic', 'heif'   => ['badge_class' => 'is-img',   'badge_text' => 'IMG'],
            default                                  => ['badge_class' => 'is-other', 'badge_text' => strtoupper($ext)],
        };
    }

    // -------------------------------------------------------------------------
    // Форматирование размера файла
    // -------------------------------------------------------------------------

    /**
     * Форматировать размер файла для UI.
     * Синхронизировано с formatFileSize() из index.php.
     */
    public static function formatFileSize(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2, '.', '') . ' МБ';
        }
        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 2, '.', '') . ' КБ';
        }
        return $bytes . ' Б';
    }

    // -------------------------------------------------------------------------
    // Предопределённые типы документов
    // -------------------------------------------------------------------------

    /**
     * Предопределённые документы водителя.
     * Используется в company_driver_create_form.php и modal_edit.php.
     *
     * TODO: после перехода на DocumentService — брать отсюда,
     *   а не дублировать в partials.
     */
    public static function driverPredefinedDocs(): array
    {
        return [
            ['name' => 'Паспорт',                  'code' => 'passport',       'ext' => 'PDF'],
            ['name' => 'Водительское удостоверение', 'code' => 'driver_license', 'ext' => 'PDF'],
            ['name' => 'СНИЛС',                     'code' => 'snils',          'ext' => 'PDF'],
        ];
    }

    /**
     * Предопределённые документы транспортного комплекта.
     *
     * TODO: синхронизировать с vehicleSetPredefinedDocumentSections() из index.php.
     */
    public static function vehicleSetPredefinedDocs(string $role): array
    {
        $role = $role === 'primary' ? 'primary' : 'secondary';
        $prefix = $role === 'primary' ? '' : 'secondary_';

        return [
            ['input_code' => $prefix . 'sts',              'doc_code' => 'sts',              'badge' => '-', 'name' => 'СТС'],
            ['input_code' => $prefix . 'diagnostic_card',   'doc_code' => 'diagnostic_card',  'badge' => '-', 'name' => 'Диагностическая карта'],
            ['input_code' => $prefix . 'photo',             'doc_code' => 'photo',             'badge' => '-', 'name' => 'Фотография'],
        ];
    }

    // -------------------------------------------------------------------------
    // Статусы документов
    // -------------------------------------------------------------------------

    /**
     * Допустимые статусы документа в БД (поле documents.status).
     *
     * TODO: статус 'uploaded' — текущий активный.
     *   Поле status является legacy (DECISIONS.md #24).
     *   Основной механизм: deleted_at IS NULL = активный документ.
     */
    public const STATUS_UPLOADED = 'uploaded';
    public const STATUS_DELETED  = 'deleted';

    // -------------------------------------------------------------------------
    // Заготовки для будущих методов (будут реализованы при интеграции)
    // -------------------------------------------------------------------------

    /**
     * TODO: upload(array $file, PDO $pdo, int $companyId, string $entityType, int $entityId, array $user): array
     *
     * Сигнатура для будущего использования.
     * Возвращает: ['ok' => bool, 'document_id' => int|null, 'error' => string|null]
     *
     * Пока не реализован — ждёт acceptance сервиса на новых модулях.
     */

    /**
     * TODO: replace(int $documentId, array $newFile, PDO $pdo, array $user): array
     *
     * Сигнатура для будущего использования.
     * Старый файл soft-delete, новый — upload.
     */

    /**
     * TODO: softDelete(int $documentId, PDO $pdo): bool
     *
     * Установить deleted_at = NOW().
     */

    /**
     * TODO: getByEntity(PDO $pdo, string $entityType, int $entityId): array
     *
     * Получить все активные документы сущности.
     */
}
