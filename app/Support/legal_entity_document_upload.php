<?php

if (!function_exists('legalEntityDocumentAllowedExtensions')) {
    function legalEntityDocumentAllowedExtensions(): array
    {
        return ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'doc', 'docx', 'xls', 'xlsx'];
    }
}

if (!function_exists('legalEntityDocumentMaxFileSize')) {
    function legalEntityDocumentMaxFileSize(): int
    {
        return 20 * 1024 * 1024;
    }
}

if (!function_exists('ensureLegalEntityDocumentTables')) {
    function ensureLegalEntityDocumentTables(PDO $pdo): void
    {
        try {
            $pdo->query('SELECT 1 FROM documents LIMIT 1')->fetch();
        } catch (\Exception $e) {
            $pdo->exec(file_get_contents(base_path('database/migrations-local/007_create_company_documents.sql')));
        }

        try {
            $pdo->query('SELECT 1 FROM document_types LIMIT 1')->fetch();
        } catch (\Exception $e) {
            $pdo->exec(file_get_contents(base_path('database/migrations-local/024_create_document_types.sql')));
            $pdo->exec(file_get_contents(base_path('database/migrations-local/025_add_document_type_id.sql')));
        }
    }
}

if (!function_exists('validateLegalEntityCustomDocumentTitles')) {
    function validateLegalEntityCustomDocumentTitles(array $post, array $files): ?string
    {
        $customNames = $files['custom_doc_file']['name'] ?? [];
        if (!is_array($customNames)) {
            return null;
        }

        foreach ($customNames as $idx => $origName) {
            $errorCode = $files['custom_doc_file']['error'][$idx] ?? UPLOAD_ERR_NO_FILE;
            if ($errorCode !== UPLOAD_ERR_OK || trim((string) $origName) === '') {
                continue;
            }

            $customTypeNew = trim((string) ($post['custom_doc_type_new'][$idx] ?? ''));
            $customTypeSelect = trim((string) ($post['custom_doc_type'][$idx] ?? ''));
            if ($customTypeNew === '' && $customTypeSelect === '') {
                return 'Введите название документа';
            }
        }

        return null;
    }
}

if (!function_exists('findLegalEntityDocumentTypeId')) {
    function findLegalEntityDocumentTypeId(PDO $pdo, string $entityType, string $documentTypeName): ?int
    {
        if ($documentTypeName === '') {
            return null;
        }

        $stmt = $pdo->prepare('SELECT id FROM document_types WHERE name = ? AND entity_type = ? LIMIT 1');
        $stmt->execute([$documentTypeName, $entityType]);
        $id = $stmt->fetchColumn();

        return $id !== false ? (int) $id : null;
    }
}

if (!function_exists('createLegalEntityCustomDocumentType')) {
    function createLegalEntityCustomDocumentType(PDO $pdo, string $entityType, string $documentTypeName, int $userId, string $roleCode): ?int
    {
        if ($documentTypeName === '') {
            return null;
        }

        $insert = $pdo->prepare(
            "INSERT IGNORE INTO document_types (name, entity_type, category, created_by_user_id, created_by_role)
             VALUES (:name, :entity_type, 'custom', :user_id, :role_code)"
        );
        $insert->execute([
            ':name' => $documentTypeName,
            ':entity_type' => $entityType,
            ':user_id' => $userId,
            ':role_code' => $roleCode,
        ]);

        $newId = (int) $pdo->lastInsertId();
        if ($newId > 0) {
            return $newId;
        }

        return findLegalEntityDocumentTypeId($pdo, $entityType, $documentTypeName);
    }
}

if (!function_exists('storeLegalEntityDocumentRecord')) {
    function storeLegalEntityDocumentRecord(
        PDO $pdo,
        int $companyId,
        string $entityType,
        int $entityId,
        string $documentTypeName,
        ?int $documentTypeId,
        array $fileInfo,
        int $userId,
        string $roleCode
    ): void {
        $relativeDir = 'companies/' . $companyId . '/documents/' . $entityType . '/' . $entityId;
        $absoluteDir = storage_path($relativeDir);
        if (!is_dir($absoluteDir)) {
            mkdir($absoluteDir, 0755, true);
        }

        $extension = strtolower(pathinfo($fileInfo['name'], PATHINFO_EXTENSION));
        $storedName = uniqid('doc_', true) . '.' . $extension;
        $absolutePath = $absoluteDir . DIRECTORY_SEPARATOR . $storedName;

        if (!move_uploaded_file($fileInfo['tmp_name'], $absolutePath)) {
            throw new RuntimeException('Не удалось сохранить файл');
        }

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
            ':entity_type' => $entityType,
            ':entity_id' => $entityId,
            ':document_type' => $documentTypeName !== '' ? $documentTypeName : null,
            ':document_type_id' => $documentTypeId,
            ':original_name' => $fileInfo['name'],
            ':stored_name' => $storedName,
            ':relative_path' => $relativeDir . '/' . $storedName,
            ':mime_type' => $fileInfo['type'],
            ':file_size' => $fileInfo['size'],
            ':status' => 'uploaded',
            ':uploaded_by_user_id' => $userId,
            ':uploaded_by_role' => $roleCode,
            ':created_by_user_id' => $userId,
            ':created_by_role' => $roleCode,
        ]);
    }
}

if (!function_exists('processLegalEntityCreateDocuments')) {
    function processLegalEntityCreateDocuments(
        PDO $pdo,
        int $companyId,
        string $entityType,
        int $entityId,
        array $post,
        array $files,
        int $userId,
        string $roleCode
    ): array {
        ensureLegalEntityDocumentTables($pdo);

        $allowedExtensions = legalEntityDocumentAllowedExtensions();
        $maxFileSize = legalEntityDocumentMaxFileSize();
        $docErrors = [];
        $uploadedDocs = [];

        $predefNames = $files['predef_doc']['name'] ?? [];
        if (is_array($predefNames)) {
            foreach ($predefNames as $code => $origName) {
                $errorCode = $files['predef_doc']['error'][$code] ?? UPLOAD_ERR_NO_FILE;
                if ($errorCode !== UPLOAD_ERR_OK || trim((string) $origName) === '') {
                    continue;
                }

                $documentLabel = (string) ($post['predef_doc_type'][$code] ?? $code);
                $extension = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
                $fileSize = (int) ($files['predef_doc']['size'][$code] ?? 0);

                if (!in_array($extension, $allowedExtensions, true)) {
                    $docErrors[] = 'Предопределённый документ «' . $documentLabel . '»: недопустимый формат';
                    continue;
                }
                if ($fileSize > $maxFileSize) {
                    $docErrors[] = 'Предопределённый документ «' . $documentLabel . '»: размер > 20 МБ';
                    continue;
                }
                if (strpos((string) $origName, '../') !== false || strpos((string) $origName, '..\\') !== false || strpos((string) $origName, '/') !== false || strpos((string) $origName, '\\') !== false) {
                    $docErrors[] = 'Предопределённый документ «' . $documentLabel . '»: недопустимое имя';
                    continue;
                }

                try {
                    $documentTypeName = trim((string) ($post['predef_doc_type'][$code] ?? ''));
                    $documentTypeId = findLegalEntityDocumentTypeId($pdo, $entityType, $documentTypeName);
                    storeLegalEntityDocumentRecord(
                        $pdo,
                        $companyId,
                        $entityType,
                        $entityId,
                        $documentTypeName,
                        $documentTypeId,
                        [
                            'name' => (string) $origName,
                            'tmp_name' => (string) ($files['predef_doc']['tmp_name'][$code] ?? ''),
                            'type' => (string) ($files['predef_doc']['type'][$code] ?? ''),
                            'size' => $fileSize,
                        ],
                        $userId,
                        $roleCode
                    );
                    $uploadedDocs[] = $documentTypeName . ' (' . $origName . ')';
                } catch (\Exception $e) {
                    $docErrors[] = 'Предопределённый документ «' . $documentLabel . '»: ошибка сохранения';
                }
            }
        }

        $customNames = $files['custom_doc_file']['name'] ?? [];
        if (is_array($customNames)) {
            foreach ($customNames as $idx => $origName) {
                $errorCode = $files['custom_doc_file']['error'][$idx] ?? UPLOAD_ERR_NO_FILE;
                if ($errorCode !== UPLOAD_ERR_OK || trim((string) $origName) === '') {
                    continue;
                }

                $extension = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
                $fileSize = (int) ($files['custom_doc_file']['size'][$idx] ?? 0);
                if (!in_array($extension, $allowedExtensions, true)) {
                    $docErrors[] = 'Произвольный документ #' . ($idx + 1) . ': недопустимый формат';
                    continue;
                }
                if ($fileSize > $maxFileSize) {
                    $docErrors[] = 'Произвольный документ #' . ($idx + 1) . ': размер > 20 МБ';
                    continue;
                }
                if (strpos((string) $origName, '../') !== false || strpos((string) $origName, '..\\') !== false || strpos((string) $origName, '/') !== false || strpos((string) $origName, '\\') !== false) {
                    $docErrors[] = 'Произвольный документ #' . ($idx + 1) . ': недопустимое имя';
                    continue;
                }

                $customTypeNew = trim((string) ($post['custom_doc_type_new'][$idx] ?? ''));
                $customTypeSelect = trim((string) ($post['custom_doc_type'][$idx] ?? ''));
                $documentTypeName = $customTypeNew !== '' ? $customTypeNew : $customTypeSelect;
                if ($documentTypeName === '') {
                    $docErrors[] = 'Произвольный документ #' . ($idx + 1) . ': введите название документа';
                    continue;
                }

                try {
                    $documentTypeId = $customTypeNew !== ''
                        ? createLegalEntityCustomDocumentType($pdo, $entityType, $customTypeNew, $userId, $roleCode)
                        : findLegalEntityDocumentTypeId($pdo, $entityType, $customTypeSelect);

                    storeLegalEntityDocumentRecord(
                        $pdo,
                        $companyId,
                        $entityType,
                        $entityId,
                        $documentTypeName,
                        $documentTypeId,
                        [
                            'name' => (string) $origName,
                            'tmp_name' => (string) ($files['custom_doc_file']['tmp_name'][$idx] ?? ''),
                            'type' => (string) ($files['custom_doc_file']['type'][$idx] ?? ''),
                            'size' => $fileSize,
                        ],
                        $userId,
                        $roleCode
                    );
                    $uploadedDocs[] = $documentTypeName . ' (' . $origName . ')';
                } catch (\Exception $e) {
                    $docErrors[] = 'Произвольный документ #' . ($idx + 1) . ': ошибка сохранения';
                }
            }
        }

        return [
            'docErrors' => $docErrors,
            'uploadedDocs' => $uploadedDocs,
        ];
    }
}
