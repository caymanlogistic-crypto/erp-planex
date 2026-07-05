<?php

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
    require_once base_path('app/Support/company_database.php');
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
            $localDbConfig = companyDatabaseConfig($config, $company);
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
    require_once base_path('app/Support/company_database.php');
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

    // === THREE-GATE CONFIRMATION ===
    $confirmCheckbox = isset($_POST['confirm_checkbox']) && $_POST['confirm_checkbox'] === '1';
    $confirmName = trim($_POST['confirm_name'] ?? '');
    $confirmPhrase = trim($_POST['confirm_phrase'] ?? '');

    $errors = [];
    if (!$confirmCheckbox) {
        $errors[] = 'Необходимо подтвердить понимание необратимости действия.';
    }
    if ($confirmName !== $company['name']) {
        $errors[] = 'Название компании не совпадает.';
    }
    if ($confirmPhrase !== 'УДАЛИТЬ НАВСЕГДА') {
        $errors[] = 'Контрольная фраза не совпадает.';
    }

    if (!empty($errors)) {
        $confirmError = implode('<br>', $errors);
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
                $localDbConfig = companyDatabaseConfig($config, $company);
                $localDb = new \App\Core\Database($localDbConfig);
                $localPdo = $localDb->connection();
                applyLocalMigrations($localPdo);

                $tables = [
                    'users' => 'logists_count', 'clients' => 'clients_count',
                    'contractors' => 'contractors_count', 'drivers' => 'drivers_count',
                    'vehicle_units' => 'vehicles_count', 'crews' => 'crews_count',
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
        $confirmValue = '';

        ob_start();
        require base_path('app/View/pages/superadmin_company_delete.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
        return;
    }

    // === PREFLIGHT: DB name validation ===
    $dbIdentifier = $company['db_identifier'] ?? '';
    $isPoolDb = !empty($company['db_username']);
    $hasSeparateDb = (strpos($dbIdentifier, 'erp_company_') === 0) && !$isPoolDb;
    $hasSharedDb = !$hasSeparateDb && !empty($dbIdentifier) && !$isPoolDb;
    // Allow deletion when db_identifier matches either separate DB pattern or shared DB pattern.
    // Deletion skips DROP DATABASE for shared DB but still removes central records and storage.

    // === PREFLIGHT: storage path validation ===
    $expectedStorageSuffix = 'companies/' . $companyId;
    $storagePath = $company['storage_path'] ?? '';
    $storageAbs = storage_path($expectedStorageSuffix);
    $storageAbsReal = realpath($storageAbs);

    $projectRoot = realpath(base_path(''));
    if ($storageAbsReal === false) {
        $storageAbsReal = $storageAbs;
    }
    if ($storageAbsReal === false || $storageAbsReal === $projectRoot || strpos($storageAbsReal, $projectRoot . DIRECTORY_SEPARATOR) !== 0) {
        $confirmError = 'BLOCKED: Некорректный путь storage. Удаление невозможно.';
        ob_start(); echo '<div class="panel panel-danger"><div class="panel-body"><div class="notice danger">' . e($confirmError) . '</div><a href="/superadmin/companies/' . $companyId . '/delete" class="btn btn-ghost">← Назад</a></div></div>';
        $content = ob_get_clean(); require base_path('app/View/layouts/main.php');
        exit;
    }
    if (strpos($storageAbsReal, $projectRoot . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'companies' . DIRECTORY_SEPARATOR) !== 0) {
        $confirmError = 'BLOCKED: Путь storage не находится в storage/companies/. Удаление невозможно.';
        ob_start(); echo '<div class="panel panel-danger"><div class="panel-body"><div class="notice danger">' . e($confirmError) . '</div><a href="/superadmin/companies/' . $companyId . '/delete" class="btn btn-ghost">← Назад</a></div></div>';
        $content = ob_get_clean(); require base_path('app/View/layouts/main.php');
        exit;
    }

    $timestamp = date('Ymd_His');

    // === REPORT PATH (outside deleted folder) ===
    $reportDir = storage_path('superadmin/delete_reports');
    if (!is_dir($reportDir)) { mkdir($reportDir, 0755, true); }
    $reportFile = $reportDir . '/company_' . $companyId . '_' . $timestamp . '.json';

    // === BACKUP PATH ===
    $backupDir = storage_path('superadmin/backups/company_' . $companyId . '_' . $timestamp);
    if (!is_dir($backupDir)) { mkdir($backupDir, 0755, true); }

    $report = [
        'company_id' => $companyId,
        'company_name' => $company['name'],
        'company_inn' => $company['inn'] ?? '',
        'deleted_by_user_id' => (int)($_SESSION['user_id'] ?? 0),
        'deleted_by_email' => $_SESSION['user_login'] ?? '',
        'deleted_by_role' => 'superadmin',
        'started_at' => date('Y-m-d H:i:s'),
        'completed_at' => null,
        'status' => 'in_progress',
        'local_db' => $dbIdentifier,
        'storage_path' => $storageAbs,
        'steps' => [],
        'errors' => [],
        'backup_status' => 'unknown',
        'backup_path' => $backupDir,
    ];
    $overallSuccess = true;

    // === STEP 1: Central snapshot ===
    try {
        $snapshot = ['company' => $company];
        $ownerStmt = $pdo->prepare("SELECT * FROM company_users WHERE company_id = ?");
        $ownerStmt->execute([$companyId]);
        $snapshot['company_users'] = $ownerStmt->fetchAll(PDO::FETCH_ASSOC);
        file_put_contents($backupDir . '/central_snapshot.json', json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $report['steps'][] = ['step' => 'central_snapshot', 'status' => 'success'];
    } catch (\Exception $e) {
        $report['steps'][] = ['step' => 'central_snapshot', 'status' => 'fail', 'error' => $e->getMessage()];
        $report['errors'][] = $e->getMessage();
        $overallSuccess = false;
    }

    // === STEP 2: DB dump (if mysqldump available) ===
    $dumpCreated = false;
    if (!empty($dbIdentifier)) {
        try {
            $localDbCfg = companyDatabaseConfig($config, $company);
            $dbUser = $localDbCfg['username'] ?? 'root';
            $dbPass = $localDbCfg['password'] ?? '';
            $dbHost = $localDbCfg['host'] ?? '127.0.0.1';
            $dbPort = $localDbCfg['port'] ?? '3306';

            $mysqldumpAvailable = false;
            $mysqldumpPath = trim(shell_exec('where mysqldump 2>NUL') ?? '');
            if (!empty($mysqldumpPath)) {
                $mysqldumpAvailable = true;
            }

            if ($mysqldumpAvailable) {
                $dumpFile = $backupDir . '/local_db_dump.sql';
                $cmd = "mysqldump --host=" . escapeshellarg($dbHost) . " --port=" . escapeshellarg($dbPort) . " --user=" . escapeshellarg($dbUser) . " ";
                if ($dbPass !== '') { $cmd .= "--password=" . escapeshellarg($dbPass) . " "; }
                $cmd .= "--no-tablespaces --single-transaction --routines --triggers " . escapeshellarg($dbIdentifier);
                $cmd .= " > " . escapeshellarg($dumpFile) . " 2>&1";

                $shellOutput = null; $retval = 0;
                exec($cmd, $shellOutput, $retval);
                if ($retval === 0) {
                    $dumpCreated = true;
                    $report['steps'][] = ['step' => 'db_dump', 'status' => 'success'];
                } else {
                    $errMsg = implode(' ', array_slice($shellOutput ?? [], 0, 3));
                    if (empty($errMsg)) { $errMsg = "mysqldump failed with code $retval"; }
                    $report['steps'][] = ['step' => 'db_dump', 'status' => 'fail', 'error' => $errMsg];
                    $report['errors'][] = 'mysqldump failed: ' . $errMsg;
                }
                $report['backup_status'] = $dumpCreated ? 'created' : 'failed';
            } else {
                $report['steps'][] = ['step' => 'db_dump', 'status' => 'skipped', 'reason' => 'mysqldump unavailable'];
                $report['backup_status'] = 'unavailable';
            }
        } catch (\Exception $e) {
            $report['steps'][] = ['step' => 'db_dump', 'status' => 'fail', 'error' => $e->getMessage()];
            $report['errors'][] = 'db_dump: ' . $e->getMessage();
            $report['backup_status'] = 'error';
        }
    } else {
        $report['steps'][] = ['step' => 'db_dump', 'status' => 'skipped', 'reason' => 'no db_identifier'];
    }

    // === STEP 3: Storage backup ===
    $storageBackedUp = false;
    $storageMoved = false;
    $originalStorageExists = is_dir($storageAbs);

    if ($originalStorageExists) {
        try {
            if (class_exists('ZipArchive')) {
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
                    $report['steps'][] = ['step' => 'storage_backup_zip', 'status' => 'success'];
                } else {
                    $report['steps'][] = ['step' => 'storage_backup_zip', 'status' => 'fail', 'error' => 'zip_open_error'];
                    $report['errors'][] = 'storage_backup_zip: failed to open zip';
                }
            } else {
                $report['steps'][] = ['step' => 'storage_backup', 'status' => 'skipped', 'reason' => 'ZipArchive unavailable'];
            }
        } catch (\Exception $e) {
            $report['steps'][] = ['step' => 'storage_backup', 'status' => 'fail', 'error' => $e->getMessage()];
            $report['errors'][] = 'storage_backup: ' . $e->getMessage();
        }
    } else {
        $report['steps'][] = ['step' => 'storage_backup', 'status' => 'skipped', 'reason' => 'storage folder not found'];
    }

    // === Write partial report before destructive steps ===
    file_put_contents($reportFile, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    // === STEP 4: Drop local DB (only for separate databases) ===
    if ($hasSeparateDb) {
        try {
            $dbConfig = $config['database'];
            $dbConfig['database'] = '';
            $sysDb = new \App\Core\Database($dbConfig);
            $sysPdo = $sysDb->connection();
            $sysPdo->exec("DROP DATABASE IF EXISTS `{$dbIdentifier}`");
            $report['steps'][] = ['step' => 'drop_database', 'status' => 'success', 'db' => $dbIdentifier];
        } catch (\Exception $e) {
            $report['steps'][] = ['step' => 'drop_database', 'status' => 'fail', 'error' => $e->getMessage()];
            $report['errors'][] = 'drop_database: ' . $e->getMessage();
            $overallSuccess = false;
        }
    } else {
        $report['steps'][] = ['step' => 'drop_database', 'status' => 'skipped', 'reason' => 'shared DB mode — no separate database to drop'];
    }

    // === STEP 5: Remove central company_users ===
    try {
        $pdo->prepare("DELETE FROM company_users WHERE company_id = ?")->execute([$companyId]);
        $report['steps'][] = ['step' => 'delete_company_users', 'status' => 'success'];
    } catch (\Exception $e) {
        $report['steps'][] = ['step' => 'delete_company_users', 'status' => 'fail', 'error' => $e->getMessage()];
        $report['errors'][] = 'delete_company_users: ' . $e->getMessage();
        $overallSuccess = false;
    }

    // === STEP 6: Delete central company record ===
    try {
        $pdo->prepare("DELETE FROM companies WHERE id = ?")->execute([$companyId]);
        $report['steps'][] = ['step' => 'delete_company_central', 'status' => 'success'];
    } catch (\Exception $e) {
        $report['steps'][] = ['step' => 'delete_company_central', 'status' => 'fail', 'error' => $e->getMessage()];
        $report['errors'][] = 'delete_company_central: ' . $e->getMessage();
        $overallSuccess = false;
    }

    // === STEP 7: Delete storage/companies/{id} ===
    if ($originalStorageExists) {
        try {
            $rii = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($storageAbs, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($rii as $file) {
                if ($file->isDir()) {
                    @rmdir($file->getPathname());
                } else {
                    @unlink($file->getPathname());
                }
            }
            @rmdir($storageAbs);
            $report['steps'][] = ['step' => 'delete_storage', 'status' => 'success'];
        } catch (\Exception $e) {
            $report['steps'][] = ['step' => 'delete_storage', 'status' => 'fail', 'error' => $e->getMessage()];
            $report['errors'][] = 'delete_storage: ' . $e->getMessage();
            $overallSuccess = false;
        }
    }

    // === STEP 8: Write audit to deleted_entities ===
    try {
        $uid = (int)($_SESSION['user_id'] ?? 0);
        $rl = (string)($_SESSION['role_code'] ?? '');
        $un = $_SESSION['user_name'] ?? '';
        \App\Service\AuditService::recordDeletion(
            $pdo,
            $company,
            'company',
            $companyId,
            'companies',
            $company['name'] ?? '#' . $companyId,
            $uid,
            $rl,
            $un,
            'Hard delete by SUPERADMIN Stage 2',
            json_encode($company, JSON_UNESCAPED_UNICODE),
            0,
            $storageAbs
        );
        $report['steps'][] = ['step' => 'audit_record', 'status' => 'success'];
    } catch (\Exception $e) {
        $report['steps'][] = ['step' => 'audit_record', 'status' => 'fail', 'error' => $e->getMessage()];
    }

    // === Finalize report ===
    $report['completed_at'] = date('Y-m-d H:i:s');
    $report['status'] = $overallSuccess ? 'completed' : 'partial_failure';
    file_put_contents($reportFile, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    $redirectParam = $overallSuccess ? 'deleted=' . $companyId : 'partial=' . $companyId;
    header('Location: /superadmin/companies?' . $redirectParam);
    exit;
});
