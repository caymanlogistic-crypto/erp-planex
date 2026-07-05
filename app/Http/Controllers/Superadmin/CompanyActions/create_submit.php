<?php

    requireRole('superadmin');

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

    try {
        $pdo = $db->connection();

        require base_path('app/Helpers/company_bank_guard.php');
        ensureCompanyBankColumns($pdo);

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
            $pdo->prepare('DELETE FROM companies WHERE id = :id')
                ->execute([':id' => $companyId]);
            $formError = 'Не удалось создать рабочую базу данных компании. У текущего MySQL-пользователя нет права CREATE DATABASE. Режим общей БД с префиксами пока не включён, чтобы не смешивать данные компаний. Создайте БД erp_company_' . $companyId . ' в панели хостинга или выдайте право CREATE DATABASE, затем повторите создание.';

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
            if (!is_dir($storageDir)) {
                mkdir($storageDir, 0755, true);
            }
        } catch (\Exception $e) {
            $sysPdo->exec('DROP DATABASE IF EXISTS `' . $dbName . '`');
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
            $localDbConfig = $config['database'];
            $localDbConfig['database'] = $dbName;
            $localDb = new \App\Core\Database($localDbConfig);
            $localPdo = $localDb->connection();
            applyLocalMigrations($localPdo);
        } catch (\Exception $e) {
            $sysPdo->exec('DROP DATABASE IF EXISTS `' . $dbName . '`');
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

        $pdo->prepare('UPDATE companies SET status = :status, db_identifier = :db, storage_path = :storage, `key` = :new_key WHERE id = :id')
            ->execute([
                ':status'  => 'active',
                ':db'      => $dbName,
                ':storage' => 'storage/companies/' . $companyId . '/',
                ':new_key' => $newKey,
                ':id'      => $companyId,
            ]);

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
