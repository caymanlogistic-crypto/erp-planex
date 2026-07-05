<?php

use App\Service\DocumentService;
use App\Service\LinearRouteService;
use App\Service\LocalMigrationService;

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
    $company = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    if (!$company || ($company['status'] ?? '') !== 'active') {
        http_response_code(404);
        echo '<div class="notice warn">Компания не найдена или не активна.</div>';
        exit;
    }

    $localDbConfig = companyDatabaseConfig($config, $company);
    $localDb = new \App\Core\Database($localDbConfig);
    $localPdo = $localDb->connection();
    applyLocalMigrations($localPdo);

    $route = LinearRouteService::fetchRouteById($localPdo, (int) $id);
    if (!$route) {
        http_response_code(404);
        echo '<div class="notice warn">Рейс не найден.</div>';
        exit;
    }

    $sessionUser = [
        'user_id' => (int) ($_SESSION['user_id'] ?? 0),
        'role_code' => (string) ($_SESSION['role_code'] ?? ''),
    ];
    if (!LinearRouteService::canEditRoute($route, $localPdo, $sessionUser)) {
        http_response_code(403);
        echo '<div class="notice warn">У вас нет права редактировать эту запись.</div>';
        exit;
    }

    $old = $_POST;
    $old['id'] = (int) $id;
    $validationErrors = [];
    $formError = null;

    $routeType = trim((string) ($_POST['route_type'] ?? ''));
    $clientId = (int) ($_POST['client_id'] ?? 0);
    $carrierId = (int) ($_POST['carrier_contractor_id'] ?? 0);
    $routeExecutorId = (int) ($_POST['route_executor_id'] ?? 0);
    $cargoTypeName = LinearRouteService::normalizeCargoTypeName((string) ($_POST['cargo_type_name'] ?? ''));
    $plannedLoadingDate = LinearRouteService::normalizeDate($_POST['planned_loading_date'] ?? '');
    $plannedUnloadingDate = LinearRouteService::normalizeDate($_POST['planned_unloading_date'] ?? '');
    $actualLoadingDate = LinearRouteService::normalizeDate($_POST['actual_loading_date'] ?? '');
    $actualUnloadingDate = LinearRouteService::normalizeDate($_POST['actual_unloading_date'] ?? '');
    $comments = trim((string) ($_POST['comments'] ?? ''));

    $parsePaymentRows = static function (array $rows, string $scopeLabel, string $scopeKey, array &$errors): array {
        $result = [];

        foreach (array_values($rows) as $index => $row) {
            if (!is_array($row)) {
                continue;
            }

            $amountRaw = trim((string) ($row['amount'] ?? ''));
            $paymentType = trim((string) ($row['payment_type'] ?? ''));
            $paymentDueType = trim((string) ($row['payment_due_type'] ?? ''));
            $paymentDueDaysRaw = trim((string) ($row['payment_due_days'] ?? ''));
            $paymentDueDaysKind = trim((string) ($row['payment_due_days_kind'] ?? ''));

            $isEmpty = $amountRaw === '' && $paymentType === '' && $paymentDueType === '' && $paymentDueDaysRaw === '' && $paymentDueDaysKind === '';
            if ($isEmpty) {
                continue;
            }

            $amount = LinearRouteService::parseAmount($amountRaw);
            if ($amount === null) {
                $errors[$scopeKey . '.' . $index . '.amount'] = 'Укажите сумму целым числом для блока «' . $scopeLabel . '».';
            }
            if (!in_array($paymentType, LinearRouteService::PAYMENT_TYPES, true)) {
                $errors[$scopeKey . '.' . $index . '.payment_type'] = 'Выберите корректный тип оплаты для блока «' . $scopeLabel . '».';
            }
            if (!in_array($paymentDueType, LinearRouteService::PAYMENT_DUE_TYPES, true)) {
                $errors[$scopeKey . '.' . $index . '.payment_due_type'] = 'Выберите корректный срок оплаты для блока «' . $scopeLabel . '».';
            }

            $paymentDueDays = null;
            if (LinearRouteService::paymentDueTypeRequiresDays($paymentDueType)) {
                if ($paymentDueDaysRaw === '' || !ctype_digit($paymentDueDaysRaw) || (int) $paymentDueDaysRaw <= 0) {
                    $errors[$scopeKey . '.' . $index . '.payment_due_days'] = 'Укажите количество дней целым положительным числом для блока «' . $scopeLabel . '».';
                } else {
                    $paymentDueDays = (int) $paymentDueDaysRaw;
                }

                if (!array_key_exists($paymentDueDaysKind, LinearRouteService::PAYMENT_DUE_DAYS_KINDS)) {
                    $errors[$scopeKey . '.' . $index . '.payment_due_days_kind'] = 'Выберите тип дней для блока «' . $scopeLabel . '».';
                }
            } else {
                $paymentDueDays = null;
                $paymentDueDaysKind = null;
            }

            $result[] = [
                'amount' => $amount,
                'payment_type' => $paymentType,
                'payment_due_type' => $paymentDueType,
                'payment_due_days' => $paymentDueDays,
                'payment_due_days_kind' => $paymentDueDaysKind,
            ];
        }

        return $result;
    };

    if (!in_array($routeType, [LinearRouteService::ROUTE_TYPE_LINEAR, LinearRouteService::ROUTE_TYPE_AGENCY], true)) {
        $validationErrors['route_type'] = 'Выберите тип рейса.';
    }
    if ($clientId <= 0) {
        $validationErrors['client_id'] = 'Выберите заказчика.';
    }
    if ($carrierId <= 0) {
        $validationErrors['carrier_contractor_id'] = 'Выберите перевозчика.';
    }
    if ($routeExecutorId <= 0) {
        $validationErrors['route_executor_id'] = 'Выберите исполнителя рейса.';
    }
    if ($cargoTypeName === '') {
        $validationErrors['cargo_type_name'] = 'Укажите тип груза.';
    }
    if ($plannedLoadingDate === null) {
        $validationErrors['planned_loading_date'] = 'Укажите плановую дату загрузки.';
    }
    if ($plannedLoadingDate !== null && $plannedUnloadingDate !== null && $plannedUnloadingDate < $plannedLoadingDate) {
        $validationErrors['planned_unloading_date'] = 'Дата выгрузки не может быть раньше даты загрузки.';
    }
    if ($actualLoadingDate !== null && $actualUnloadingDate !== null && $actualUnloadingDate < $actualLoadingDate) {
        $validationErrors['actual_unloading_date'] = 'Фактическая выгрузка не может быть раньше фактической загрузки.';
    }

    $customerPayments = $parsePaymentRows((array) ($_POST['customer_payments'] ?? []), 'Заказчик', 'customer_payments', $validationErrors);
    $carrierPayments = $parsePaymentRows((array) ($_POST['carrier_payments'] ?? []), 'Перевозчик', 'carrier_payments', $validationErrors);
    if (empty($customerPayments)) {
        $validationErrors['customer_payments'] = 'Добавьте хотя бы одну оплату для заказчика.';
    }
    if (empty($carrierPayments)) {
        $validationErrors['carrier_payments'] = 'Добавьте хотя бы одну оплату для перевозчика.';
    }

    $principalRows = [];
    $principalPaymentMap = [];
    if ($routeType === LinearRouteService::ROUTE_TYPE_AGENCY) {
        foreach (array_values((array) ($_POST['principal_rows'] ?? [])) as $rowIndex => $row) {
            if (!is_array($row)) {
                continue;
            }

            $entityKey = trim((string) ($row['entity_key'] ?? ''));
            $payments = $parsePaymentRows((array) ($row['payments'] ?? []), 'Принципал', 'principal_rows.' . $rowIndex . '.payments', $validationErrors);
            if ($entityKey === '' && empty($payments)) {
                continue;
            }

            $principal = LinearRouteService::parsePrincipalEntityKey($entityKey);
            if ($principal === null) {
                $validationErrors['principal_rows.' . $rowIndex . '.entity_key'] = 'Выберите принципала.';
                continue;
            }

            $contractSide = LinearRouteService::principalContractSide(
                $principal['principal_type'],
                (int) $principal['principal_id'],
                $clientId,
                $carrierId
            );
            if ($contractSide === null) {
                $validationErrors['principal_rows.' . $rowIndex . '.entity_key'] = 'Принципалом может быть только выбранный заказчик или выбранный перевозчик.';
                continue;
            }
            if (empty($payments)) {
                $validationErrors['principal_rows.' . $rowIndex . '.payments'] = 'Добавьте хотя бы одну оплату для принципала.';
            }

            $normalizedKey = LinearRouteService::principalEntityKey($principal['principal_type'], (int) $principal['principal_id']);
            if (isset($principalPaymentMap[$normalizedKey])) {
                $validationErrors['principal_rows.' . $rowIndex . '.entity_key'] = 'Такой принципал уже добавлен.';
                continue;
            }

            $principalRows[] = $principal;
            $principalPaymentMap[$normalizedKey] = $payments;
        }

        if (empty($principalRows)) {
            $validationErrors['principal_rows'] = 'Добавьте хотя бы одного принципала.';
        }
    }

    if ($clientId > 0 && !LinearRouteService::isVisibleEntity($localPdo, 'clients', $clientId, $sessionUser, 'client')) {
        $validationErrors['client_id'] = 'Заказчик недоступен.';
    }
    if ($carrierId > 0 && !LinearRouteService::isVisibleEntity($localPdo, 'contractors', $carrierId, $sessionUser, 'contractor')) {
        $validationErrors['carrier_contractor_id'] = 'Перевозчик недоступен.';
    }

    $visibleExecutors = LinearRouteService::fetchVisibleRouteExecutors($localPdo, $sessionUser);
    $visibleExecutorIds = array_map(static fn(array $row): int => (int) $row['id'], $visibleExecutors);
    if ($routeExecutorId > 0 && !in_array($routeExecutorId, $visibleExecutorIds, true)) {
        $validationErrors['route_executor_id'] = 'Исполнитель рейса недоступен.';
    }

    $clients = LinearRouteService::fetchVisibleClients($localPdo, $sessionUser);
    $contractors = LinearRouteService::fetchVisibleContractors($localPdo, $sessionUser);
    $routeExecutors = $visibleExecutors;
    $docsByCode = LinearRouteService::fetchRouteDocuments($localPdo, (int) $id);

    if (!empty($validationErrors)) {
        header('Content-Type: text/html; charset=utf-8');
        require base_path('app/View/partials/company_linear_trip_modal_edit.php');
        exit;
    }

    $userId = (int) ($_SESSION['user_id'] ?? 0);
    $roleCode = (string) ($_SESSION['role_code'] ?? '');
    $cargoTypeId = LinearRouteService::createOrFindCargoType($localPdo, $cargoTypeName, $userId, $roleCode);

    $ensureDocumentSchema = static function (PDO $localPdo): void {
        try {
            $localPdo->query('SELECT 1 FROM documents LIMIT 1')->fetch();
        } catch (\Throwable $e) {
            $localPdo->exec(file_get_contents(base_path('database/migrations-local/007_create_company_documents.sql')));
        }
        try {
            $localPdo->query('SELECT 1 FROM document_types LIMIT 1')->fetch();
        } catch (\Throwable $e) {
            $localPdo->exec(file_get_contents(base_path('database/migrations-local/024_create_document_types.sql')));
            $localPdo->exec(file_get_contents(base_path('database/migrations-local/025_add_document_type_id.sql')));
        }
        try {
            $localPdo->query('SELECT created_by_user_id FROM documents LIMIT 1')->fetch();
        } catch (\Throwable $e) {
            $localPdo->exec("ALTER TABLE documents ADD COLUMN created_by_user_id INT UNSIGNED DEFAULT NULL, ADD COLUMN created_by_role VARCHAR(20) DEFAULT NULL");
        }
    };

    $saveUploadedDocument = static function (
        PDO $localPdo,
        int $companyId,
        int $routeId,
        string $documentName,
        string $documentCode,
        array $file,
        int $userId,
        string $roleCode,
        string $category = 'predefined'
    ): void {
        $originalName = (string) ($file['name'] ?? '');
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if (!in_array($extension, DocumentService::ALLOWED_EXTENSIONS, true)) {
            throw new \RuntimeException('Недопустимое расширение файла: ' . $documentName);
        }

        $fileSize = (int) ($file['size'] ?? 0);
        if (!DocumentService::isFileSizeValid($fileSize)) {
            throw new \RuntimeException('Размер файла превышает лимит: ' . $documentName);
        }

        $relativeDir = 'companies/' . $companyId . '/documents/linear_route/' . $routeId;
        $absoluteDir = storage_path($relativeDir);
        if (!is_dir($absoluteDir) && !mkdir($absoluteDir, 0755, true) && !is_dir($absoluteDir)) {
            throw new \RuntimeException('Не удалось создать директорию документов.');
        }

        $storedName = DocumentService::makeSafeFilename($originalName);
        $destination = $absoluteDir . DIRECTORY_SEPARATOR . $storedName;
        if (!move_uploaded_file((string) ($file['tmp_name'] ?? ''), $destination)) {
            throw new \RuntimeException('Не удалось сохранить файл: ' . $documentName);
        }

        $documentTypeId = LocalMigrationService::ensureDocumentTypeRecord($localPdo, $documentName, $documentCode, 'linear_route', $category);
        $insertDocument = $localPdo->prepare(
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
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $insertDocument->execute([
            'linear_route',
            $routeId,
            $documentName,
            $documentTypeId,
            $originalName,
            $storedName,
            $relativeDir . '/' . $storedName,
            (string) ($file['type'] ?? 'application/octet-stream'),
            $fileSize,
            'uploaded',
            $userId,
            $roleCode,
            $userId,
            $roleCode,
        ]);
    };

    $replacePredefinedDocument = static function (
        PDO $localPdo,
        array $existingDocsByCode,
        string $docKey,
        int $userId,
        string $roleCode
    ): void {
        foreach ($existingDocsByCode[$docKey] ?? [] as $document) {
            $localPdo->prepare(
                "UPDATE documents
                    SET deleted_at = NOW(),
                        deleted_by_user_id = ?,
                        deleted_by_role = ?,
                        delete_comment = 'Linear route predefined document replaced'
                  WHERE id = ?
                    AND deleted_at IS NULL"
            )->execute([$userId, $roleCode, (int) ($document['id'] ?? 0)]);
        }
    };

    $syncLegacyTerms = static function (
        PDO $localPdo,
        int $routeId,
        array $customerPayments,
        array $carrierPayments,
        array $principalPaymentMap,
        int $userId,
        string $roleCode
    ): void {
        $upsertTerm = $localPdo->prepare(
            "INSERT INTO linear_route_financial_terms (
                linear_route_id,
                party_role,
                amount,
                payment_type,
                payment_due_type,
                payment_due_days,
                payment_due_days_kind,
                created_by_user_id,
                created_by_role,
                updated_by_user_id,
                updated_by_role
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                amount = VALUES(amount),
                payment_type = VALUES(payment_type),
                payment_due_type = VALUES(payment_due_type),
                payment_due_days = VALUES(payment_due_days),
                payment_due_days_kind = VALUES(payment_due_days_kind),
                deleted_at = NULL,
                deleted_by_user_id = NULL,
                deleted_by_role = NULL,
                updated_by_user_id = VALUES(updated_by_user_id),
                updated_by_role = VALUES(updated_by_role)"
        );

        $legacyRows = [
            'customer' => $customerPayments[0] ?? null,
            'carrier' => $carrierPayments[0] ?? null,
            'principal' => null,
        ];
        foreach ($principalPaymentMap as $payments) {
            if (!empty($payments[0])) {
                $legacyRows['principal'] = $payments[0];
                break;
            }
        }

        foreach ($legacyRows as $partyRole => $term) {
            if ($term === null) {
                $localPdo->prepare(
                    "UPDATE linear_route_financial_terms
                        SET deleted_at = NOW(),
                            deleted_by_user_id = ?,
                            deleted_by_role = ?
                      WHERE linear_route_id = ?
                        AND party_role = ?
                        AND deleted_at IS NULL"
                )->execute([$userId, $roleCode, $routeId, $partyRole]);
                continue;
            }

            $upsertTerm->execute([
                $routeId,
                $partyRole,
                $term['amount'],
                $term['payment_type'],
                $term['payment_due_type'],
                $term['payment_due_days'],
                $term['payment_due_days_kind'],
                $userId,
                $roleCode,
                $userId,
                $roleCode,
            ]);
        }
    };

    $localPdo->beginTransaction();

    $updateRoute = $localPdo->prepare(
        "UPDATE linear_routes
            SET route_type = :route_type,
                client_id = :client_id,
                carrier_contractor_id = :carrier_contractor_id,
                principal_type = NULL,
                principal_id = NULL,
                agency_contract_with = NULL,
                route_executor_id = :route_executor_id,
                cargo_type_id = :cargo_type_id,
                planned_loading_date = :planned_loading_date,
                planned_unloading_date = :planned_unloading_date,
                actual_loading_date = :actual_loading_date,
                actual_unloading_date = :actual_unloading_date,
                comments = :comments,
                updated_by_user_id = :updated_by_user_id,
                updated_by_role = :updated_by_role
          WHERE id = :id
            AND deleted_at IS NULL"
    );
    $updateRoute->execute([
        ':route_type' => $routeType,
        ':client_id' => $clientId,
        ':carrier_contractor_id' => $carrierId,
        ':route_executor_id' => $routeExecutorId,
        ':cargo_type_id' => $cargoTypeId,
        ':planned_loading_date' => $plannedLoadingDate,
        ':planned_unloading_date' => $plannedUnloadingDate,
        ':actual_loading_date' => $actualLoadingDate,
        ':actual_unloading_date' => $actualUnloadingDate,
        ':comments' => $comments !== '' ? $comments : null,
        ':updated_by_user_id' => $userId,
        ':updated_by_role' => $roleCode,
        ':id' => (int) $id,
    ]);

    $storedPrincipals = [];
    if ($routeType === LinearRouteService::ROUTE_TYPE_AGENCY) {
        $storedPrincipals = LinearRouteService::storeRoutePrincipals(
            $localPdo,
            (int) $id,
            $principalRows,
            $clientId,
            $carrierId,
            $userId,
            $roleCode
        );
        LinearRouteService::syncLegacyRoutePrincipalFields(
            $localPdo,
            (int) $id,
            $storedPrincipals,
            $clientId,
            $carrierId,
            $userId,
            $roleCode
        );
    } else {
        LinearRouteService::storeRoutePrincipals(
            $localPdo,
            (int) $id,
            [],
            $clientId,
            $carrierId,
            $userId,
            $roleCode
        );
    }

    LinearRouteService::storeRoutePayments(
        $localPdo,
        (int) $id,
        $customerPayments,
        $carrierPayments,
        $principalPaymentMap,
        $storedPrincipals,
        $userId,
        $roleCode
    );
    $syncLegacyTerms($localPdo, (int) $id, $customerPayments, $carrierPayments, $principalPaymentMap, $userId, $roleCode);
    LinearRouteService::reconcileLegacyPaymentRows($localPdo, (int) $id);

    $ensureDocumentSchema($localPdo);

    $documentMap = LinearRouteService::routeDocumentDefinitions($routeType);
    foreach ($documentMap as $inputName => $meta) {
        if (empty($_FILES[$inputName]) || (int) ($_FILES[$inputName]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        if ((int) ($_FILES[$inputName]['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('Ошибка загрузки документа: ' . $meta['name']);
        }

        $replacePredefinedDocument($localPdo, $docsByCode, $inputName, $userId, $roleCode);
        $saveUploadedDocument($localPdo, $companyId, (int) $id, $meta['name'], $meta['code'], $_FILES[$inputName], $userId, $roleCode);
    }

    if ($routeType !== LinearRouteService::ROUTE_TYPE_AGENCY) {
        foreach ($docsByCode['principal_document'] ?? [] as $document) {
            $localPdo->prepare(
                "UPDATE documents
                    SET deleted_at = NOW(),
                        deleted_by_user_id = ?,
                        deleted_by_role = ?,
                        delete_comment = 'Principal document removed after route type change'
                  WHERE id = ?
                    AND deleted_at IS NULL"
            )->execute([$userId, $roleCode, (int) ($document['id'] ?? 0)]);
        }
    }

    $customDocTypes = (array) ($_POST['custom_doc_type'] ?? []);
    $customDocFiles = $_FILES['custom_doc_file'] ?? null;
    if (is_array($customDocFiles) && isset($customDocFiles['name']) && is_array($customDocFiles['name'])) {
        foreach ($customDocFiles['name'] as $index => $name) {
            $docTypeName = trim((string) ($customDocTypes[$index] ?? ''));
            $fileError = (int) ($customDocFiles['error'][$index] ?? UPLOAD_ERR_NO_FILE);
            if ($docTypeName === '' && $fileError === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            if ($docTypeName === '') {
                throw new \RuntimeException('Укажите название пользовательского документа.');
            }
            if ($fileError !== UPLOAD_ERR_OK) {
                throw new \RuntimeException('Загрузите файл для документа «' . $docTypeName . '».');
            }

            $saveUploadedDocument(
                $localPdo,
                $companyId,
                (int) $id,
                $docTypeName,
                '',
                [
                    'name' => $customDocFiles['name'][$index] ?? '',
                    'type' => $customDocFiles['type'][$index] ?? 'application/octet-stream',
                    'tmp_name' => $customDocFiles['tmp_name'][$index] ?? '',
                    'error' => $fileError,
                    'size' => $customDocFiles['size'][$index] ?? 0,
                ],
                $userId,
                $roleCode,
                'custom'
            );
        }
    }

    $localPdo->commit();

    $route = LinearRouteService::fetchRouteById($localPdo, (int) $id);
    $docsByCode = LinearRouteService::fetchRouteDocuments($localPdo, (int) $id);

    $canEdit = LinearRouteService::canEditRoute($route, $localPdo, $sessionUser);
    $canDelete = LinearRouteService::canDeleteRoute($route, $localPdo, $sessionUser);

    header('Content-Type: text/html; charset=utf-8');
    require base_path('app/View/partials/company_linear_trip_modal_view.php');
    exit;
} catch (\Throwable $e) {
    if (isset($localPdo) && $localPdo instanceof PDO && $localPdo->inTransaction()) {
        $localPdo->rollBack();
    }

    http_response_code(500);
    echo '<div class="notice warn">Не удалось сохранить рейс: ' . e($e->getMessage()) . '</div>';
    exit;
}
