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
            $localDbConfig = $config['database'];
            $localDbConfig['database'] = $company['db_identifier'];
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

    $confirmPhrase = trim($_POST['confirm_phrase'] ?? '');
    $expected = 'DELETE COMPANY ' . $companyId;

    if ($confirmPhrase !== $expected) {
        $confirmError = 'Неверная контрольная фраза. Требуется: ' . $expected;

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
                $localDbConfig = $config['database'];
                $localDbConfig['database'] = $company['db_identifier'];
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
        $confirmValue = $confirmPhrase;

        ob_start();
        require base_path('app/View/pages/superadmin_company_delete.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
        return;
    }

    $dbIdentifier = $company['db_identifier'] ?? '';
    $expectedDb = 'erp_company_' . $companyId;

    if ($dbIdentifier !== $expectedDb) {
        $confirmError = 'BLOCKED: db_identifier не соответствует шаблону. Удаление невозможно.';

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

        $dbError = null;
        $confirmValue = $confirmPhrase;

        ob_start();
        require base_path('app/View/pages/superadmin_company_delete.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
        return;
    }

    $timestamp = date('Ymd_His');
    $backupDir = storage_path('backups/deleted-companies/company_' . $companyId . '_' . $timestamp);
    if (!is_dir($backupDir)) { mkdir($backupDir, 0755, true); }

    $report = [
        'company_id' => $companyId,
        'company_name' => $company['name'],
        'company_inn' => $company['inn'] ?? '',
        'deleted_by' => $_SESSION['user_id'] ?? 0,
        'deleted_at' => date('Y-m-d H:i:s'),
        'db_identifier' => $dbIdentifier,
        'steps' => [],
        'backup_location' => $backupDir,
    ];

    $snapshot = ['company' => $company];
    $ownerStmt = $pdo->prepare("SELECT * FROM company_users WHERE company_id = ?");
    $ownerStmt->execute([$companyId]);
    $snapshot['company_users'] = $ownerStmt->fetchAll(PDO::FETCH_ASSOC);
    file_put_contents($backupDir . '/central_snapshot.json', json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    $report['steps'][] = 'central_snapshot_saved';

    $dumpFile = $backupDir . '/local_db_dump.sql';
    $dumpCreated = false;
    if (!empty($dbIdentifier)) {
        $dbUser = $config['database']['username'] ?? 'root';
        $dbPass = $config['database']['password'] ?? '';
        $dbHost = $config['database']['host'] ?? '127.0.0.1';
        $dbPort = $config['database']['port'] ?? '3306';

        $mysqldumpAvailable = false;
        $mysqldumpPath = trim(shell_exec('where mysqldump 2>NUL') ?? '');
        if (!empty($mysqldumpPath)) {
            $mysqldumpAvailable = true;
        }

        if ($mysqldumpAvailable) {
            $cmd = "mysqldump --host=" . escapeshellarg($dbHost) . " --port=" . escapeshellarg($dbPort) . " --user=" . escapeshellarg($dbUser) . " ";
            if ($dbPass !== '') { $cmd .= "--password=" . escapeshellarg($dbPass) . " "; }
            $cmd .= "--no-tablespaces --single-transaction --routines --triggers " . escapeshellarg($dbIdentifier);
        }

        if ($mysqldumpAvailable) {
            $output = null; $retval = 0;
            exec($cmd . ' 2>&1', $output, $retval);
            if ($retval === 0) {
                file_put_contents($dumpFile, implode("\n", $output));
                $dumpCreated = true;
                $report['steps'][] = 'local_db_dump_created';
            } else {
                $report['steps'][] = 'local_db_dump_failed: ' . implode(' ', array_slice($output, 0, 3));
            }
        } else {
            $report['steps'][] = 'local_db_dump_skipped: mysqldump unavailable';
        }
    }

    $storageAbs = storage_path('companies/' . $companyId);
    $storageBackedUp = false;
    if (is_dir($storageAbs)) {
        if (!class_exists('ZipArchive')) {
            $report['steps'][] = 'storage_backup_skipped: ZipArchive unavailable';
            // Fallback: move storage folder to backup instead of zipping
            $deletedStorageDir = $backupDir . '/deleted_storage';
            if (rename($storageAbs, $deletedStorageDir)) {
                $storageBackedUp = true;
                $report['steps'][] = 'storage_moved_to_backup (fallback, no ZipArchive)';
            } else {
                $report['steps'][] = 'storage_backup_failed: cannot move folder';
            }
        } else {
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
                $report['steps'][] = 'storage_backup_created';
            } else {
                $report['steps'][] = 'storage_backup_failed: zip_open_error';
            }
        }
    } else {
        $report['steps'][] = 'storage_folder_not_found';
    }

    $backupDetails = [];
    if (!$dumpCreated) {
        $backupDetails[] = 'SQL дамп не создан (mysqldump ' . ($mysqldumpAvailable ?? false ? 'ошибка выполнения' : 'недоступен') . ')';
    }
    if (!$storageBackedUp) {
        $backupDetails[] = 'Storage backup не создан';
    }
    $backupDetails = implode('; ', $backupDetails);

    $backupOk = $dumpCreated || $storageBackedUp;
    $skipBackup = isset($_POST['skip_backup']) && $_POST['skip_backup'] === '1';

    if (!$backupOk && !$skipBackup) {
        $backupWarning = true;
        $confirmError = 'Backup не создан. Подтвердите удаление без backup.';

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
                $localDbConfig = $config['database'];
                $localDbConfig['database'] = $company['db_identifier'];
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
        $confirmValue = $confirmPhrase;

        ob_start();
        require base_path('app/View/pages/superadmin_company_delete.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
        return;
    }

    if (!empty($dbIdentifier)) {
        $dbConfig = $config['database'];
        $dbConfig['database'] = '';
        $sysDb = new \App\Core\Database($dbConfig);
        $sysPdo = $sysDb->connection();
        $sysPdo->exec("DROP DATABASE IF EXISTS `{$dbIdentifier}`");
        $report['steps'][] = 'local_db_dropped: ' . $dbIdentifier;
    }

    $pdo->prepare("DELETE FROM company_users WHERE company_id = ?")->execute([$companyId]);
    $report['steps'][] = 'central_company_users_deleted';

    $pdo->prepare("DELETE FROM companies WHERE id = ?")->execute([$companyId]);
    $report['steps'][] = 'central_company_deleted';

    if (is_dir($storageAbs)) {
        $deletedStorageDir = $backupDir . '/deleted_storage';
        rename($storageAbs, $deletedStorageDir);
        $report['steps'][] = 'storage_moved_to_backup';
    }

    file_put_contents($backupDir . '/delete_report.json', json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    header('Location: /superadmin/companies?deleted=' . $companyId);
    exit;
});
