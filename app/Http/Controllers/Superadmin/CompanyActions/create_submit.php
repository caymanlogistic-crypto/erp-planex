<?php

    requireRole('superadmin');

    require_once base_path('app/Support/company_database.php');

    $isModal = ($_POST['is_modal'] ?? '') === '1';

    $pageTitle = 'Создать экспедитора';
    $pageContext = 'Реестр компаний';
    $errors = [];
    $old = $_POST;
    $formError = null;

    function renderFormPartial($errors, $old, $formError, $isModal): void
    {
        $leEntityType = 'company';
        $leFormAction = '/superadmin/companies/create';
        $leFormId = 'le-sa-company-create-form';
        $leIsModal = $isModal;
        $leOld = $old;
        $leErrors = $errors;
        $leFormError = $formError;
        $leSubmitLabel = 'Создать компанию';
        $leShowContacts = false;
        $leShowBankDetails = true;
        $leShowDocuments = true;
        $leShowInlineActions = false;
        $leInnLookupUrl = app_url('/superadmin/requisites/lookup-by-inn');
        require base_path('app/View/partials/legal_entity_create_form.php');
    }

    $name = trim($_POST['name'] ?? '');
    $inn  = trim($_POST['inn'] ?? '');

    if ($name === '') {
        $errors['name'] = 'Обязательное поле';
    }

    if ($inn === '') {
        $errors['inn'] = 'Обязательное поле';
    }

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

    if (!empty($errors)) {
        if ($isModal) {
            renderFormPartial($errors, $old, $formError, true);
            return;
        }
        ob_start();
        require base_path('app/View/pages/superadmin_companies_create.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
        return;
    }

    $companyId = 0;
    $poolEntry = null;
    $companyActivated = false;

    try {
        $pdo = $db->connection();

        require base_path('app/Helpers/company_bank_guard.php');
        ensureCompanyBankColumns($pdo);
        ensureCompanyDatabaseColumns($pdo);

        $key = 'company_' . uniqid();

        $insert = $pdo->prepare(
            'INSERT INTO companies (`key`, name, inn, kpp, ogrn, legal_address, physical_address,
                director_position, director_full_name, comments, status,
                bank_account, bank_name, bank_bik, bank_corr_account)
             VALUES (:key, :name, :inn, :kpp, :ogrn, :legal_address, :physical_address,
                :director_position, :director_full_name, :comments, :status,
                :bank_account, :bank_name, :bank_bik, :bank_corr_account)'
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
            ':bank_account'     => $bankAccount !== '' ? $bankAccount : null,
            ':bank_name'        => !empty($_POST['bank_name']) ? trim($_POST['bank_name']) : null,
            ':bank_bik'         => $bankBik !== '' ? $bankBik : null,
            ':bank_corr_account'=> $bankCorr !== '' ? $bankCorr : null,
        ]);

        $companyId = (int) $pdo->lastInsertId();
        $storageDir = storage_path('companies/' . $companyId);

        $newKey = 'company_' . $companyId;

        $poolEntry = findFreePoolDb($pdo, $companyId);
        if ($poolEntry === null) {
            $pdo->prepare('DELETE FROM companies WHERE id = :id')
                ->execute([':id' => $companyId]);
            $formError = 'Нет свободных подготовленных баз данных для новой компании. Все БД из пула заняты. Добавьте новые базы в COMPANY_DB_POOL_JSON.';

            if ($isModal) {
                renderFormPartial($errors, $old, $formError, true);
                return;
            }
            ob_start();
            require base_path('app/View/pages/superadmin_companies_create.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $poolDbConfig = $config['database'];
        $poolDbConfig['database'] = $poolEntry['database'];
        if (!empty($poolEntry['host'])) { $poolDbConfig['host'] = $poolEntry['host']; }
        if (!empty($poolEntry['port'])) { $poolDbConfig['port'] = $poolEntry['port']; }
        $poolDbConfig['username'] = $poolEntry['username'];
        $poolDbConfig['password'] = $poolEntry['password'];
        $localDb = new \App\Core\Database($poolDbConfig);
        $localPdo = $localDb->connection();

        // A released pool database may still contain the previous tenant's data.
        // Reservation is already held, so cleanup is safe and must precede migration.
        cleanCompanyPoolDatabase($localPdo);

        try {
            if (!is_dir($storageDir)) {
                mkdir($storageDir, 0755, true);
            }
        } catch (\Exception $e) {
            releasePoolDb($pdo, $poolEntry['database'], $companyId);
            $pdo->prepare('DELETE FROM companies WHERE id = :id')
                ->execute([':id' => $companyId]);

            $formError = 'Не удалось создать storage-папку: ' . $e->getMessage();

            if ($isModal) {
                renderFormPartial($errors, $old, $formError, true);
                return;
            }
            ob_start();
            require base_path('app/View/pages/superadmin_companies_create.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        try {
            applyLocalMigrations($localPdo);
        } catch (\Exception $e) {
            releasePoolDb($pdo, $poolEntry['database'], $companyId);
            $pdo->prepare('DELETE FROM companies WHERE id = :id')
                ->execute([':id' => $companyId]);
            $formError = 'Не удалось применить миграции в БД компании: ' . $e->getMessage();
            if ($isModal) {
                renderFormPartial($errors, $old, $formError, true);
                return;
            }
            ob_start();
            require base_path('app/View/pages/superadmin_companies_create.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $finalDbName = $poolEntry['database'];
        $stmt = $pdo->prepare('UPDATE companies SET status = :status, db_identifier = :db, db_host = :db_host, db_port = :db_port, db_username = :db_username, db_password = :db_password, storage_path = :storage, `key` = :new_key WHERE id = :id');
        $stmt->execute([
            ':status'      => 'active',
            ':db'          => $finalDbName,
            ':db_host'     => !empty($poolEntry['host']) ? $poolEntry['host'] : null,
            ':db_port'     => !empty($poolEntry['port']) ? $poolEntry['port'] : null,
            ':db_username' => $poolEntry['username'],
            ':db_password' => companyDbStorePassword($poolEntry['password']),
            ':storage'     => 'storage/companies/' . $companyId . '/',
            ':new_key'     => $newKey,
            ':id'          => $companyId,
        ]);
        $companyActivated = true;
        require_once base_path('app/Support/legal_entity_document_upload.php');

        $entityType = 'company';
        $docResult = processLegalEntityCreateDocuments(
            $localPdo,
            $companyId,
            $entityType,
            $companyId,
            $_POST,
            $_FILES,
            (int)($_SESSION['user_id'] ?? 0),
            (string)($_SESSION['role_code'] ?? 'superadmin')
        );

        if (!empty($docResult['docErrors'])) {
            $docWarning = 'Документы сохранены с ошибками: ' . implode('; ', $docResult['docErrors']);
            $_SESSION['company_create_doc_warning'] = $docWarning;
        }

        if ($isModal) {
            echo '<div data-le-create-success="1"></div>';
            return;
        }

        redirect_to('/superadmin/companies');
    } catch (\Exception $e) {
        if (!$companyActivated && $companyId > 0 && isset($pdo) && $pdo instanceof PDO) {
            try {
                if (is_array($poolEntry) && !empty($poolEntry['database'])) {
                    releasePoolDb($pdo, (string) $poolEntry['database'], $companyId);
                }
                $pdo->prepare("DELETE FROM companies WHERE id = ? AND status = 'provisioning'")
                    ->execute([$companyId]);
            } catch (\Throwable $cleanupError) {
                error_log('Company provisioning cleanup failed: ' . $cleanupError->getMessage());
            }
        }
        $formError = 'Не удалось создать экспедитора: ' . $e->getMessage();

        if ($isModal) {
            renderFormPartial($errors, $old, $formError, true);
            return;
        }
        ob_start();
        require base_path('app/View/pages/superadmin_companies_create.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
    }
