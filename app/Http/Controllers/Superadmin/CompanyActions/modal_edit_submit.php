<?php

    requireRole('superadmin');

    try {
        $pdo = $db->connection();

        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([(int) $id]);
        $company = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$company) {
            http_response_code(404);
            echo '<div class="modal-body"><div class="notice warn">Компания не найдена.</div></div>';
            return;
        }

        $errors = [];
        $old = $_POST;
        $formError = null;

        $name = trim($_POST['name'] ?? '');
        $inn  = trim($_POST['inn'] ?? '');

        $bankAccount = trim($_POST['bank_account'] ?? '');
        $bankBik     = trim($_POST['bank_bik'] ?? '');
        $bankCorr    = trim($_POST['bank_corr_account'] ?? '');

        if ($bankAccount !== '' && !preg_match('/^\d{20}$/', $bankAccount)) {
            $errors['bank_account'] = 'Расчётный счёт должен содержать ровно 20 цифр';
        }
        if ($bankBik !== '' && !preg_match('/^\d{9}$/', $bankBik)) {
            $errors['bank_bik'] = 'БИК должен содержать ровно 9 цифр';
        }
        if ($bankCorr !== '' && !preg_match('/^\d{20}$/', $bankCorr)) {
            $errors['bank_corr_account'] = 'Корр. счёт должен содержать ровно 20 цифр';
        }

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
            require base_path('app/View/partials/superadmin_company_modal_edit.php');
            echo ob_get_clean();
            return;
        }

        require base_path('app/Helpers/company_bank_guard.php');
        ensureCompanyBankColumns($pdo);

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
                comments = :comments,
                bank_account = :bank_account,
                bank_name = :bank_name,
                bank_bik = :bank_bik,
                bank_corr_account = :bank_corr_account
             WHERE id = :id'
        );

        $update->execute([
            ':name'              => $name,
            ':inn'               => $inn,
            ':kpp'               => $_POST['kpp'] ?? null,
            ':ogrn'              => $_POST['ogrn'] ?? null,
            ':legal_address'     => $_POST['legal_address'] ?? null,
            ':physical_address'  => $_POST['physical_address'] ?? null,
            ':director_position' => $_POST['director_position'] ?? null,
            ':director_full_name'=> $_POST['director_full_name'] ?? null,
            ':status'            => $_POST['status'] ?? $company['status'],
            ':comments'          => $_POST['comments'] ?? null,
            ':bank_account'      => $bankAccount !== '' ? $bankAccount : null,
            ':bank_name'         => !empty($_POST['bank_name']) ? trim($_POST['bank_name']) : null,
            ':bank_bik'          => $bankBik !== '' ? $bankBik : null,
            ':bank_corr_account' => $bankCorr !== '' ? $bankCorr : null,
            ':id'                => (int) $id,
        ]);

        require_once base_path('app/Support/legal_entity_document_upload.php');

        if (!function_exists('hasAnyUploadedFiles')) {
            function hasAnyUploadedFiles(array $fileSection): bool
            {
                $names = $fileSection['name'] ?? [];
                $errors = $fileSection['error'] ?? [];
                if (!is_array($names)) {
                    $names = [$names];
                }
                if (!is_array($errors)) {
                    $errors = [$errors];
                }
                foreach ($names as $key => $name) {
                    $err = $errors[$key] ?? UPLOAD_ERR_NO_FILE;
                    if ($err !== UPLOAD_ERR_NO_FILE && isset($name) && $name !== '') {
                        return true;
                    }
                }
                return false;
            }
        }

        $docWarning = null;
        $dbIdentifier = $company['db_identifier'] ?? '';

        if ($dbIdentifier !== '') {
            try {
                $localDbConfig = companyDatabaseConfig($config, $company);
                $localDb = new \App\Core\Database($localDbConfig);
                $localPdo = $localDb->connection();

                $userId = (int)($_SESSION['user_id'] ?? 0);
                $roleCode = (string)($_SESSION['role_code'] ?? 'superadmin');

                $deleteExistingDocs = $_POST['delete_existing_doc'] ?? [];
                foreach ($deleteExistingDocs as $docId => $val) {
                    if ($val !== '1') continue;
                    softDeleteEntityDocument($localPdo, (int)$docId, 'company', (int)$id, $userId, 'Archived via superadmin modal edit');
                }

                $existingFiles = $_FILES['existing_doc_file'] ?? [];
                if (!empty($existingFiles['name']) && is_array($existingFiles['name'])) {
                    foreach ($existingFiles['name'] as $docId => $origName) {
                        $uploadError = $existingFiles['error'][$docId] ?? UPLOAD_ERR_NO_FILE;
                        if ($uploadError !== UPLOAD_ERR_OK || trim((string) $origName) === '') continue;
                        try {
                            replaceEntityDocument(
                                $localPdo,
                                (int)$docId,
                                (int)$id,
                                'company',
                                (int)$id,
                                [
                                    'name' => (string) $origName,
                                    'tmp_name' => (string) ($existingFiles['tmp_name'][$docId] ?? ''),
                                    'type' => (string) ($existingFiles['type'][$docId] ?? ''),
                                    'size' => (int) ($existingFiles['size'][$docId] ?? 0),
                                ],
                                $userId,
                                $roleCode
                            );
                        } catch (\Exception $e) {
                            $docWarning = ($docWarning ? $docWarning . '; ' : '') . 'Не удалось заменить документ #' . $docId;
                        }
                    }
                }

                $docResult = processLegalEntityCreateDocuments(
                    $localPdo,
                    (int) $id,
                    'company',
                    (int) $id,
                    $_POST,
                    $_FILES,
                    $userId,
                    $roleCode
                );

                if (!empty($docResult['docErrors'])) {
                    $docWarning = 'Документы сохранены с ошибками: ' . implode('; ', $docResult['docErrors']);
                }
            } catch (\Exception $e) {
                $docWarning = 'Документы не сохранены: ' . $e->getMessage();
            }
        } else {
            $hasFiles = hasAnyUploadedFiles($_FILES['predef_doc'] ?? []) || hasAnyUploadedFiles($_FILES['custom_doc_file'] ?? []) || hasAnyUploadedFiles($_FILES['existing_doc_file'] ?? []);
            if ($hasFiles) {
                $docWarning = 'Документы не сохранены: рабочая база компании не настроена.';
            }
        }

        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([(int) $id]);
        $company = $stmt->fetch(\PDO::FETCH_ASSOC);

        $canEdit = true;
        $companyDocuments = [];
        $documentsWarning = null;
        $dbIdentifier = $company['db_identifier'] ?? '';
        if ($dbIdentifier !== '') {
            try {
                $localDbConfig = companyDatabaseConfig($config, $company);
                $localDb = new \App\Core\Database($localDbConfig);
                $localPdo = $localDb->connection();
                $docStmt = $localPdo->prepare(
                    'SELECT d.*, dt.name AS type_name, dt.code AS type_code
                     FROM documents d
                     LEFT JOIN document_types dt ON d.document_type_id = dt.id
                     WHERE d.entity_type = ? AND d.entity_id = ? AND d.deleted_at IS NULL
                     ORDER BY d.created_at DESC'
                );
                $docStmt->execute(['company', (int)$id]);
                $companyDocuments = $docStmt->fetchAll(\PDO::FETCH_ASSOC);
            } catch (\Exception $e) {
                $documentsWarning = 'Не удалось загрузить документы: ' . $e->getMessage();
            }
        }

        $docsByType = [];
        $docTypeCodes = ['company_card', 'inn_cert', 'ogrn_cert', 'contract'];
        foreach ($companyDocuments as $doc) {
            $code = $doc['type_code'] ?? '';
            if (in_array($code, $docTypeCodes, true)) {
                $docsByType[$code][] = $doc;
            } else {
                $docsByType['other'][] = $doc;
            }
        }

        ob_start();
        require base_path('app/View/partials/superadmin_company_modal_view.php');
        echo ob_get_clean();
    } catch (\Exception $e) {
        $formError = 'Ошибка сохранения: ' . $e->getMessage();
        $errors = [];
        $old = $_POST;

        ob_start();
        require base_path('app/View/partials/superadmin_company_modal_edit.php');
        echo ob_get_clean();
    }
