<?php

namespace App\Service;

use PDO;
use Throwable;

final class LinearTripDocumentUploadService
{
    public const ENTITY_TYPE = 'linear_route';

    /**
     * Validate every submitted document and move it into a tenant-scoped staging
     * directory. No permanent file or metadata is created here.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function stage(
        array $files,
        array $post,
        array $definitions,
        array $existingDocsByCode,
        int $companyId,
        int $routeId,
        string $requestToken,
        bool $requireHttpUpload = true
    ): array {
        $plans = [];

        try {
            foreach ($definitions as $inputName => $meta) {
                $file = $files[$inputName] ?? null;
                if (!is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                    continue;
                }

                $plans[] = self::stageOne(
                    $file,
                    (string) ($meta['name'] ?? 'Документ'),
                    (string) ($meta['code'] ?? ''),
                    'predefined',
                    array_values((array) ($existingDocsByCode[$inputName] ?? [])),
                    $companyId,
                    $routeId,
                    $requestToken,
                    $requireHttpUpload
                );
            }

            $customNames = array_values((array) ($post['custom_doc_type'] ?? []));
            $customFiles = $files['custom_doc_file'] ?? null;
            if (is_array($customFiles) && isset($customFiles['name']) && is_array($customFiles['name'])) {
                foreach (array_values($customFiles['name']) as $index => $unusedName) {
                    $documentName = trim((string) ($customNames[$index] ?? ''));
                    $file = [
                        'name' => $customFiles['name'][$index] ?? '',
                        'type' => $customFiles['type'][$index] ?? 'application/octet-stream',
                        'tmp_name' => $customFiles['tmp_name'][$index] ?? '',
                        'error' => $customFiles['error'][$index] ?? UPLOAD_ERR_NO_FILE,
                        'size' => $customFiles['size'][$index] ?? 0,
                    ];
                    $fileError = (int) $file['error'];

                    if ($documentName === '' && $fileError === UPLOAD_ERR_NO_FILE) {
                        continue;
                    }
                    if ($documentName === '') {
                        throw new LinearTripDocumentUploadException(
                            'Укажите название пользовательского документа.',
                            'custom_document_name_required'
                        );
                    }
                    if ($fileError === UPLOAD_ERR_NO_FILE) {
                        throw new LinearTripDocumentUploadException(
                            'Выберите файл для документа «' . $documentName . '».',
                            'custom_document_file_required'
                        );
                    }

                    $plans[] = self::stageOne(
                        $file,
                        $documentName,
                        '',
                        'custom',
                        [],
                        $companyId,
                        $routeId,
                        $requestToken,
                        $requireHttpUpload
                    );
                }
            }

            return $plans;
        } catch (Throwable $e) {
            self::cleanupStaged($plans);
            throw $e;
        }
    }

    /**
     * Assert the production schema expected by the accepted document subsystem.
     * This method never runs migrations or DDL.
     */
    public static function assertSchema(PDO $pdo): void
    {
        try {
            $pdo->query(
                'SELECT document_type_id, created_by_user_id, created_by_role, deleted_at
                   FROM documents
                  LIMIT 0'
            );
            $pdo->query(
                'SELECT id, name, code, entity_type, category
                   FROM document_types
                  LIMIT 0'
            );
        } catch (Throwable $e) {
            error_log('[P13] document schema unavailable: ' . $e->getMessage());
            throw new LinearTripDocumentUploadException(
                'Хранилище документов не подготовлено. Обратитесь к администратору.',
                'document_schema_unavailable'
            );
        }
    }

    /**
     * Move staged files to the final tenant path and insert metadata inside the
     * caller's active SQL transaction. Existing predefined metadata is retired
     * only after the new file and metadata row exist.
     *
     * @param array<int,array<string,mixed>> $plans
     * @param array<int,string> $movedFinalFiles
     */
    public static function persist(
        PDO $pdo,
        array $plans,
        int $companyId,
        int $routeId,
        int $userId,
        string $roleCode,
        array &$movedFinalFiles
    ): void {
        if ($plans === []) {
            return;
        }

        self::assertSchema($pdo);

        $relativeDir = DocumentService::buildRelativePath($companyId, self::ENTITY_TYPE, $routeId);
        $absoluteDir = DocumentService::buildAbsoluteDir($companyId, self::ENTITY_TYPE, $routeId);
        self::ensureDirectory($absoluteDir);

        foreach ($plans as $plan) {
            $stagedPath = (string) ($plan['staged_path'] ?? '');
            $storedName = (string) ($plan['stored_name'] ?? '');
            $finalPath = $absoluteDir . DIRECTORY_SEPARATOR . $storedName;
            $relativePath = DocumentService::buildStoredFilePath(
                $companyId,
                self::ENTITY_TYPE,
                $routeId,
                $storedName
            );

            if ($stagedPath === '' || !is_file($stagedPath)) {
                throw new LinearTripDocumentUploadException(
                    'Временный файл документа недоступен. Выберите файл повторно.',
                    'staged_file_missing'
                );
            }
            if (!@rename($stagedPath, $finalPath)) {
                throw new LinearTripDocumentUploadException(
                    'Не удалось записать документ в хранилище. Повторите попытку позже.',
                    'storage_move_failed'
                );
            }
            $movedFinalFiles[] = $finalPath;

            $documentTypeId = LocalMigrationService::ensureDocumentTypeRecord(
                $pdo,
                (string) $plan['document_name'],
                (string) $plan['document_code'],
                self::ENTITY_TYPE,
                (string) $plan['category']
            );

            $insert = $pdo->prepare(
                'INSERT INTO documents (
                    entity_type,
                    entity_id,
                    document_type,
                    document_type_id,
                    original_name,
                    stored_name,
                    relative_path,
                    mime_type,
                    file_size,
                    status,
                    uploaded_by_user_id,
                    uploaded_by_role,
                    created_by_user_id,
                    created_by_role
                ) VALUES (
                    :entity_type,
                    :entity_id,
                    :document_type,
                    :document_type_id,
                    :original_name,
                    :stored_name,
                    :relative_path,
                    :mime_type,
                    :file_size,
                    :status,
                    :uploaded_by_user_id,
                    :uploaded_by_role,
                    :created_by_user_id,
                    :created_by_role
                )'
            );
            $insert->execute([
                ':entity_type' => self::ENTITY_TYPE,
                ':entity_id' => $routeId,
                ':document_type' => (string) $plan['document_name'],
                ':document_type_id' => $documentTypeId,
                ':original_name' => (string) $plan['original_name'],
                ':stored_name' => $storedName,
                ':relative_path' => $relativePath,
                ':mime_type' => (string) $plan['mime_type'],
                ':file_size' => (int) $plan['file_size'],
                ':status' => 'uploaded',
                ':uploaded_by_user_id' => $userId,
                ':uploaded_by_role' => $roleCode,
                ':created_by_user_id' => $userId,
                ':created_by_role' => $roleCode,
            ]);

            $newDocumentId = (int) $pdo->lastInsertId();
            if ($newDocumentId <= 0) {
                throw new LinearTripDocumentUploadException(
                    'Не удалось записать сведения о документе.',
                    'document_metadata_failed'
                );
            }

            $replaceIds = array_values(array_unique(array_filter(array_map(
                static fn (array $row): int => (int) ($row['id'] ?? 0),
                (array) ($plan['replace_documents'] ?? [])
            ))));
            if ($replaceIds !== []) {
                $placeholders = implode(',', array_fill(0, count($replaceIds), '?'));
                $retire = $pdo->prepare(
                    "UPDATE documents
                        SET deleted_at = NOW(),
                            deleted_by_user_id = ?,
                            deleted_by_role = ?,
                            delete_comment = 'Linear route predefined document replaced'
                      WHERE id IN ({$placeholders})
                        AND entity_type = ?
                        AND entity_id = ?
                        AND deleted_at IS NULL"
                );
                $retire->execute(array_merge(
                    [$userId, $roleCode],
                    $replaceIds,
                    [self::ENTITY_TYPE, $routeId]
                ));
            }
        }
    }

    /** @param array<int,array<string,mixed>> $plans */
    public static function cleanupStaged(array $plans): void
    {
        $dirs = [];
        foreach ($plans as $plan) {
            $path = (string) ($plan['staged_path'] ?? '');
            if ($path !== '' && is_file($path)) {
                @unlink($path);
            }
            if ($path !== '') {
                $dirs[] = dirname($path);
            }
        }
        foreach (array_unique($dirs) as $dir) {
            self::removeEmptyParents($dir, 4);
        }
    }

    /** @param array<int,string> $movedFinalFiles */
    public static function cleanupFinalFiles(array $movedFinalFiles): void
    {
        foreach (array_reverse($movedFinalFiles) as $path) {
            if ($path !== '' && is_file($path)) {
                @unlink($path);
            }
        }
    }

    public static function stageOneForTest(array $file, int $companyId, int $routeId, string $requestToken): array
    {
        return self::stageOne($file, 'Тестовый документ', 'test_document', 'predefined', [], $companyId, $routeId, $requestToken, false);
    }

    private static function stageOne(
        array $file,
        string $documentName,
        string $documentCode,
        string $category,
        array $replaceDocuments,
        int $companyId,
        int $routeId,
        string $requestToken,
        bool $requireHttpUpload
    ): array {
        self::throwForUploadError((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE), $documentName);
        $validationError = DocumentService::validateUploadedFile($file, $requireHttpUpload);
        if ($validationError !== null) {
            throw self::validationException($validationError, (string) ($file['name'] ?? ''), $documentName);
        }

        $originalName = (string) $file['name'];
        $tmpName = (string) $file['tmp_name'];
        $safeToken = preg_replace('/[^a-zA-Z0-9_-]/', '', $requestToken) ?: bin2hex(random_bytes(12));
        $relativeStageDir = sprintf('companies/%d/tmp/linear_route_uploads/%d/%s', $companyId, $routeId, $safeToken);
        $absoluteStageDir = storage_path($relativeStageDir);
        self::ensureDirectory($absoluteStageDir);

        $storedName = DocumentService::makeSafeFilename($originalName);
        $stagedPath = $absoluteStageDir . DIRECTORY_SEPARATOR . $storedName;
        $moved = $requireHttpUpload ? move_uploaded_file($tmpName, $stagedPath) : @rename($tmpName, $stagedPath);
        if (!$moved) {
            throw new LinearTripDocumentUploadException('Не удалось принять документ «' . $documentName . '». Повторите выбор файла.', 'staging_move_failed');
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($stagedPath);
        if (!is_string($mime) || $mime === '') {
            @unlink($stagedPath);
            throw new LinearTripDocumentUploadException('Не удалось определить тип документа «' . $documentName . '».', 'mime_detection_failed');
        }

        return [
            'document_name' => $documentName,
            'document_code' => $documentCode,
            'category' => $category,
            'original_name' => $originalName,
            'stored_name' => $storedName,
            'staged_path' => $stagedPath,
            'mime_type' => strtolower($mime),
            'file_size' => (int) filesize($stagedPath),
            'replace_documents' => $replaceDocuments,
        ];
    }

    private static function ensureDirectory(string $directory): void
    {
        if (is_dir($directory)) {
            if (!is_writable($directory)) {
                throw new LinearTripDocumentUploadException('Каталог документов недоступен для записи.', 'storage_not_writable');
            }
            return;
        }
        if (!@mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new LinearTripDocumentUploadException('Не удалось подготовить каталог документов.', 'storage_directory_failed');
        }
        if (!is_writable($directory)) {
            throw new LinearTripDocumentUploadException('Каталог документов недоступен для записи.', 'storage_not_writable');
        }
    }

    private static function throwForUploadError(int $error, string $documentName): void
    {
        if ($error === UPLOAD_ERR_OK) return;
        $messages = [
            UPLOAD_ERR_INI_SIZE => 'Файл «' . $documentName . '» превышает допустимый размер.',
            UPLOAD_ERR_FORM_SIZE => 'Файл «' . $documentName . '» превышает допустимый размер.',
            UPLOAD_ERR_PARTIAL => 'Файл «' . $documentName . '» загружен не полностью.',
            UPLOAD_ERR_NO_FILE => 'Выберите файл для документа «' . $documentName . '».',
            UPLOAD_ERR_NO_TMP_DIR => 'На сервере недоступен временный каталог загрузки.',
            UPLOAD_ERR_CANT_WRITE => 'Сервер не смог записать загруженный файл.',
            UPLOAD_ERR_EXTENSION => 'Загрузка файла остановлена сервером.',
        ];
        throw new LinearTripDocumentUploadException($messages[$error] ?? ('Ошибка загрузки документа «' . $documentName . '».'), 'php_upload_error_' . $error);
    }

    private static function validationException(string $validationError, string $originalName, string $documentName): LinearTripDocumentUploadException
    {
        $displayName = $originalName !== '' ? $originalName : $documentName;
        $messages = [
            'no_file' => 'Выберите непустой файл для документа «' . $documentName . '».',
            'invalid_upload' => 'Не удалось проверить загрузку файла «' . $displayName . '».',
            'file_too_large' => 'Файл «' . $displayName . '» пуст или превышает лимит 20 МБ.',
            'invalid_filename' => 'Имя файла «' . $displayName . '» недопустимо.',
            'invalid_extension' => 'Расширение файла «' . $displayName . '» запрещено.',
            'invalid_mime' => 'Содержимое файла «' . $displayName . '» не соответствует его расширению.',
        ];
        return new LinearTripDocumentUploadException($messages[$validationError] ?? 'Документ не прошёл проверку.', $validationError);
    }

    private static function removeEmptyParents(string $directory, int $levels): void
    {
        $current = $directory;
        for ($i = 0; $i < $levels; $i++) {
            if (!is_dir($current)) {
                $current = dirname($current);
                continue;
            }
            $items = @scandir($current);
            if (!is_array($items) || count($items) > 2) break;
            @rmdir($current);
            $current = dirname($current);
        }
    }
}
