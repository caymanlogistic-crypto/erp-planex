<?php

$router->post('/superadmin/companies/{id}/activate', function ($id) use ($config, $db) {
    requireRole('superadmin');
    $pdo = $db->connection();
    $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
    $stmt->execute([(int)$id]);
    $company = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$company) {
        http_response_code(404);
        echo 'Company not found';
        return;
    }
    if ($company['status'] !== 'active') {
        $pdo->prepare('UPDATE companies SET status = ?, updated_at = NOW() WHERE id = ?')
            ->execute(['active', (int)$id]);
    }
    header('Location: /superadmin/companies?status_changed=1');
    exit;
});

$router->post('/superadmin/companies/{id}/block', function ($id) use ($config, $db) {
    requireRole('superadmin');
    $pdo = $db->connection();
    $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
    $stmt->execute([(int)$id]);
    $company = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$company) {
        http_response_code(404);
        echo 'Company not found';
        return;
    }
    if (in_array($company['status'], ['active', 'inactive'], true)) {
        $pdo->prepare('UPDATE companies SET status = ?, updated_at = NOW() WHERE id = ?')
            ->execute(['blocked', (int)$id]);
    }
    header('Location: /superadmin/companies?status_changed=1');
    exit;
});

$router->post('/superadmin/companies/{id}/archive', function ($id) use ($config, $db) {
    requireRole('superadmin');
    $pdo = $db->connection();
    $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
    $stmt->execute([(int)$id]);
    $company = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$company) {
        http_response_code(404);
        echo 'Company not found';
        return;
    }
    $pdo->prepare('UPDATE companies SET status = ?, updated_at = NOW() WHERE id = ?')
        ->execute(['archived', (int)$id]);
    header('Location: /superadmin/companies?status_changed=1');
    exit;
});

$router->post('/superadmin/companies/{id}/deactivate', function ($id) use ($config, $db) {
    requireRole('superadmin');
    $pdo = $db->connection();
    $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
    $stmt->execute([(int)$id]);
    $company = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$company) {
        header('Location: /superadmin/companies');
        exit;
    }
    if ($company['status'] === 'active') {
        $pdo->prepare('UPDATE companies SET status = ?, updated_at = NOW() WHERE id = ?')
            ->execute(['inactive', (int)$id]);
    }
    header('Location: /superadmin/companies?status_changed=1');
    exit;
});

// ============================================================
// SUPERADMIN: Company monitoring routes (NEW)
// ============================================================

$router->get('/superadmin/companies/{id}/users', function ($id) use ($config, $db) {
    requireRole('superadmin');
    $pageTitle = 'Пользователи компании';
    $pageContext = 'Реестр компаний';

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([(int)$id]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company) {
            $company = null;
            $users = [];
            $totalCount = 0;
            $dbError = null;
            $localDbError = null;

            ob_start();
            require base_path('app/View/pages/superadmin_company_users.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $pageTitle = 'Пользователи: ' . $company['name'];
        $pageContext = 'Реестр компаний';

        $ownerStmt = $pdo->prepare(
            "SELECT id, full_name, login, email, phone, role, status, created_at
             FROM company_users WHERE company_id = ? AND role = 'company_owner'"
        );
        $ownerStmt->execute([(int)$id]);
        $ownerRow = $ownerStmt->fetch(PDO::FETCH_ASSOC);

        $logists = [];
        $localDbError = null;

        if (!empty($company['db_identifier']) && $company['status'] === 'active') {
            try {
                $localDbConfig = $config['database'];
                $localDbConfig['database'] = $company['db_identifier'];
                $localDb = new \App\Core\Database($localDbConfig);
                $localPdo = $localDb->connection();
                applyLocalMigrations($localPdo);
                $logistStmt = $localPdo->prepare(
                    "SELECT id, full_name, login, email, phone, role_code, status, created_at
                     FROM users ORDER BY created_at DESC"
                );
                $logistStmt->execute();
                $logists = $logistStmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (\Exception $e) {
                $localDbError = true;
            }
        }

        $users = [];
        if ($ownerRow) {
            $users[] = [
                'type' => 'owner',
                'id' => $ownerRow['id'],
                'full_name' => $ownerRow['full_name'],
                'login' => $ownerRow['login'],
                'email' => $ownerRow['email'],
                'phone' => $ownerRow['phone'],
                'role_label' => 'Руководитель',
                'status' => $ownerRow['status'],
                'created_at' => $ownerRow['created_at'],
            ];
        }
        foreach ($logists as $l) {
            $users[] = [
                'type' => 'logist',
                'id' => $l['id'],
                'full_name' => $l['full_name'],
                'login' => $l['login'],
                'email' => $l['email'],
                'phone' => $l['phone'],
                'role_label' => 'Пользователь',
                'status' => $l['status'],
                'created_at' => $l['created_at'],
            ];
        }
        $totalCount = count($users);
        $dbError = null;
    } catch (\Exception $e) {
        $company = null;
        $users = [];
        $totalCount = 0;
        $dbError = 'Ошибка подключения к базе данных. Попробуйте позже.';
        $localDbError = null;
    }

    ob_start();
    require base_path('app/View/pages/superadmin_company_users.php');
    $content = ob_get_clean();
    require base_path('app/View/layouts/main.php');
});

$router->get('/superadmin/companies/{id}/directories', function ($id) use ($config, $db) {
    requireRole('superadmin');
    $pageTitle = 'Справочники компании';
    $pageContext = 'Реестр компаний';

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([(int)$id]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company) {
            $company = null;
            $dirs = [];
            $dbError = null;

            ob_start();
            require base_path('app/View/pages/superadmin_company_directories.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $pageTitle = 'Справочники: ' . $company['name'];
        $pageContext = 'Реестр компаний';

        $dirs = [];
        if (!empty($company['db_identifier'])) {
            try {
                $localDbConfig = $config['database'];
                $localDbConfig['database'] = $company['db_identifier'];
                $localDb = new \App\Core\Database($localDbConfig);
                $localPdo = $localDb->connection();
                applyLocalMigrations($localPdo);

                $tables = ['clients', 'contractors', 'drivers', 'vehicle_units', 'vehicle_sets', 'driver_vehicle_blocks', 'crews'];
                foreach ($tables as $table) {
                    $countStmt = $localPdo->prepare(
                        "SELECT COUNT(*) as total,
                                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
                                SUM(CASE WHEN status = 'archived' THEN 1 ELSE 0 END) as archived
                         FROM `{$table}`"
                    );
                    $countStmt->execute();
                    $dirs[$table] = $countStmt->fetch(PDO::FETCH_ASSOC);
                }
            } catch (\Exception $e) {
                $dbError = 'Локальная БД компании недоступна.';
            }
        } else {
            $dbError = 'Локальная БД компании недоступна.';
        }

        $dbError = $dbError ?? null;
    } catch (\Exception $e) {
        $company = null;
        $dirs = [];
        $dbError = 'Ошибка подключения к базе данных. Попробуйте позже.';
    }

    ob_start();
    require base_path('app/View/pages/superadmin_company_directories.php');
    $content = ob_get_clean();
    require base_path('app/View/layouts/main.php');
});

$router->get('/superadmin/companies/{id}/clients', function ($id) use ($config, $db) {
    requireRole('superadmin');

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([(int)$id]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company) {
            $company = null;
            $items = [];
            $totalCount = 0;
            $dbError = 'Компания не найдена';

            ob_start();
            require base_path('app/View/pages/superadmin_company_clients.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $pageTitle = 'Клиенты: ' . $company['name'];
        $pageContext = 'Реестр компаний';

        $items = [];
        $totalCount = 0;
        $dbError = null;

        if (!empty($company['db_identifier'])) {
            try {
                $localDbConfig = $config['database'];
                $localDbConfig['database'] = $company['db_identifier'];
                $localDb = new \App\Core\Database($localDbConfig);
                $localPdo = $localDb->connection();
                applyLocalMigrations($localPdo);

                $stmt = $localPdo->prepare("SELECT * FROM clients ORDER BY created_at DESC LIMIT 200");
                $stmt->execute();
                $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $totalCount = count($items);
            } catch (\Exception $e) {
                $dbError = 'Локальная БД компании недоступна.';
            }
        } else {
            $dbError = 'Локальная БД компании недоступна.';
        }
    } catch (\Exception $e) {
        $company = null;
        $items = [];
        $totalCount = 0;
        $dbError = 'Ошибка подключения к базе данных.';
    }

    ob_start();
    require base_path('app/View/pages/superadmin_company_clients.php');
    $content = ob_get_clean();
    require base_path('app/View/layouts/main.php');
});

$router->get('/superadmin/companies/{id}/contractors', function ($id) use ($config, $db) {
    requireRole('superadmin');

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([(int)$id]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company) {
            $company = null;
            $items = [];
            $totalCount = 0;
            $dbError = 'Компания не найдена';

            ob_start();
            require base_path('app/View/pages/superadmin_company_contractors.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $pageTitle = 'Перевозчики: ' . $company['name'];
        $pageContext = 'Реестр компаний';

        $items = [];
        $totalCount = 0;
        $dbError = null;

        if (!empty($company['db_identifier'])) {
            try {
                $localDbConfig = $config['database'];
                $localDbConfig['database'] = $company['db_identifier'];
                $localDb = new \App\Core\Database($localDbConfig);
                $localPdo = $localDb->connection();
                applyLocalMigrations($localPdo);

                $stmt = $localPdo->prepare("SELECT * FROM contractors ORDER BY created_at DESC LIMIT 200");
                $stmt->execute();
                $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $totalCount = count($items);
            } catch (\Exception $e) {
                $dbError = 'Локальная БД компании недоступна.';
            }
        } else {
            $dbError = 'Локальная БД компании недоступна.';
        }
    } catch (\Exception $e) {
        $company = null;
        $items = [];
        $totalCount = 0;
        $dbError = 'Ошибка подключения к базе данных.';
    }

    ob_start();
    require base_path('app/View/pages/superadmin_company_contractors.php');
    $content = ob_get_clean();
    require base_path('app/View/layouts/main.php');
});

$router->get('/superadmin/companies/{id}/drivers', function ($id) use ($config, $db) {
    requireRole('superadmin');

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([(int)$id]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company) {
            $company = null;
            $items = [];
            $totalCount = 0;
            $dbError = 'Компания не найдена';

            ob_start();
            require base_path('app/View/pages/superadmin_company_drivers.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $pageTitle = 'Водители: ' . $company['name'];
        $pageContext = 'Реестр компаний';

        $items = [];
        $totalCount = 0;
        $dbError = null;

        if (!empty($company['db_identifier'])) {
            try {
                $localDbConfig = $config['database'];
                $localDbConfig['database'] = $company['db_identifier'];
                $localDb = new \App\Core\Database($localDbConfig);
                $localPdo = $localDb->connection();
                applyLocalMigrations($localPdo);

                $stmt = $localPdo->prepare("SELECT * FROM drivers ORDER BY created_at DESC LIMIT 200");
                $stmt->execute();
                $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $totalCount = count($items);
            } catch (\Exception $e) {
                $dbError = 'Локальная БД компании недоступна.';
            }
        } else {
            $dbError = 'Локальная БД компании недоступна.';
        }
    } catch (\Exception $e) {
        $company = null;
        $items = [];
        $totalCount = 0;
        $dbError = 'Ошибка подключения к базе данных.';
    }

    ob_start();
    require base_path('app/View/pages/superadmin_company_drivers.php');
    $content = ob_get_clean();
    require base_path('app/View/layouts/main.php');
});

$router->get('/superadmin/companies/{id}/vehicles', function ($id) use ($config, $db) {
    requireRole('superadmin');

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([(int)$id]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company) {
            $company = null;
            $items = [];
            $totalCount = 0;
            $dbError = 'Компания не найдена';

            ob_start();
            require base_path('app/View/pages/superadmin_company_vehicles.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $pageTitle = 'Транспортные единицы: ' . $company['name'];
        $pageContext = 'Реестр компаний';

        $items = [];
        $totalCount = 0;
        $dbError = null;

        if (!empty($company['db_identifier'])) {
            try {
                $localDbConfig = $config['database'];
                $localDbConfig['database'] = $company['db_identifier'];
                $localDb = new \App\Core\Database($localDbConfig);
                $localPdo = $localDb->connection();
                applyLocalMigrations($localPdo);

                $stmt = $localPdo->prepare("SELECT * FROM vehicle_units ORDER BY created_at DESC LIMIT 200");
                $stmt->execute();
                $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $totalCount = count($items);
            } catch (\Exception $e) {
                $dbError = 'Локальная БД компании недоступна.';
            }
        } else {
            $dbError = 'Локальная БД компании недоступна.';
        }
    } catch (\Exception $e) {
        $company = null;
        $items = [];
        $totalCount = 0;
        $dbError = 'Ошибка подключения к базе данных.';
    }

    ob_start();
    require base_path('app/View/pages/superadmin_company_vehicles.php');
    $content = ob_get_clean();
    require base_path('app/View/layouts/main.php');
});

$router->get('/superadmin/companies/{id}/crews', function ($id) use ($config, $db) {
    requireRole('superadmin');

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([(int)$id]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company) {
            $company = null;
            $items = [];
            $totalCount = 0;
            $dbError = 'Компания не найдена';

            ob_start();
            require base_path('app/View/pages/superadmin_company_crews.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $pageTitle = 'Экипажи: ' . $company['name'];
        $pageContext = 'Реестр компаний';

        $items = [];
        $totalCount = 0;
        $dbError = null;

        if (!empty($company['db_identifier'])) {
            try {
                $localDbConfig = $config['database'];
                $localDbConfig['database'] = $company['db_identifier'];
                $localDb = new \App\Core\Database($localDbConfig);
                $localPdo = $localDb->connection();
                applyLocalMigrations($localPdo);

                $stmt = $localPdo->prepare(
                    "SELECT c.*,
                            ct.name AS contractor_name,
                            v.plate_number,
                            d.full_name AS driver_name
                     FROM crews c
                     LEFT JOIN contractors ct ON c.contractor_id = ct.id
                     LEFT JOIN vehicle_units v ON c.vehicle_id = v.id
                     LEFT JOIN drivers d ON c.driver_id = d.id
                     ORDER BY c.created_at DESC LIMIT 200"
                );
                $stmt->execute();
                $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $totalCount = count($items);
            } catch (\Exception $e) {
                $dbError = 'Локальная БД компании недоступна.';
            }
        } else {
            $dbError = 'Локальная БД компании недоступна.';
        }
    } catch (\Exception $e) {
        $company = null;
        $items = [];
        $totalCount = 0;
        $dbError = 'Ошибка подключения к базе данных.';
    }

    ob_start();
    require base_path('app/View/pages/superadmin_company_crews.php');
    $content = ob_get_clean();
    require base_path('app/View/layouts/main.php');
});

$router->get('/superadmin/companies/{id}/documents', function ($id) use ($config, $db) {
    requireRole('superadmin');
    $pageTitle = 'Документы компании';
    $pageContext = 'Реестр компаний';

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([(int)$id]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company) {
            $company = null;
            $documents = [];
            $totalCount = 0;
            $dbError = null;

            ob_start();
            require base_path('app/View/pages/superadmin_company_documents.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $pageTitle = 'Документы: ' . $company['name'];
        $pageContext = 'Реестр компаний';

        $documents = [];
        $totalCount = 0;
        if (!empty($company['db_identifier'])) {
            try {
                $localDbConfig = $config['database'];
                $localDbConfig['database'] = $company['db_identifier'];
                $localDb = new \App\Core\Database($localDbConfig);
                $localPdo = $localDb->connection();
                applyLocalMigrations($localPdo);
                $stmt = $localPdo->prepare(
                    "SELECT id, entity_type, entity_id, original_name, stored_name,
                            file_size, mime_type, status, created_at,
                            uploaded_by_user_id, uploaded_by_role
                     FROM documents
                     ORDER BY created_at DESC
                     LIMIT 100"
                );
                $stmt->execute();
                $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $totalCount = count($documents);
            } catch (\Exception $e) {
                $dbError = 'Локальная БД компании недоступна.';
            }
        } else {
            $dbError = 'Локальная БД компании недоступна.';
        }

        $dbError = $dbError ?? null;
    } catch (\Exception $e) {
        $company = null;
        $documents = [];
        $totalCount = 0;
        $dbError = 'Ошибка подключения к базе данных. Попробуйте позже.';
    }

    ob_start();
    require base_path('app/View/pages/superadmin_company_documents.php');
    $content = ob_get_clean();
    require base_path('app/View/layouts/main.php');
});

$router->get('/superadmin/companies/{company_id}/documents/{document_id}/download', function ($company_id, $document_id) use ($config, $db) {
    requireRole('superadmin');

    $pdo = $db->connection();
    $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
    $stmt->execute([(int)$company_id]);
    $company = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$company || empty($company['db_identifier'])) {
        http_response_code(404);
        echo 'Document not found';
        return;
    }

    try {
        $localDbConfig = $config['database'];
        $localDbConfig['database'] = $company['db_identifier'];
        $localDb = new \App\Core\Database($localDbConfig);
        $localPdo = $localDb->connection();
                applyLocalMigrations($localPdo);

        $docStmt = $localPdo->prepare("SELECT * FROM documents WHERE id = ? AND deleted_at IS NULL");
        $docStmt->execute([(int)$document_id]);
        $document = $docStmt->fetch(PDO::FETCH_ASSOC);

        if (!$document) {
            http_response_code(404);
            echo 'Document not found';
            return;
        }

        $docRelativePath = $document['relative_path'] ?? '';
        if (!empty($docRelativePath)) {
            $filePath = realpath(storage_path($docRelativePath));
        } else {
            $storageBase = storage_path('companies/' . $company_id . '/documents/');
            $filePath = realpath($storageBase . $document['stored_name']);
        }

        $companyStorageRoot = realpath(storage_path('companies/' . $company_id));
        if ($filePath === false || ($companyStorageRoot !== false && !str_starts_with($filePath, $companyStorageRoot))) {
            http_response_code(403);
            echo 'Access denied';
            return;
        }

        if (!file_exists($filePath)) {
            http_response_code(404);
            echo 'File not found';
            return;
        }

        header('Content-Type: ' . ($document['mime_type'] ?? 'application/octet-stream'));
        header('Content-Disposition: attachment; filename="' . $document['original_name'] . '"');
        header('Content-Length: ' . filesize($filePath));
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: no-store');
        readfile($filePath);
        exit;
    } catch (\Exception $e) {
        http_response_code(500);
        echo 'Download error';
    }
});

$router->get('/superadmin/companies/{id}/access-grants', function ($id) use ($config, $db) {
    requireRole('superadmin');
    $pageTitle = 'Доступы компании';
    $pageContext = 'Реестр компаний';

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([(int)$id]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company) {
            $company = null;
            $grants = [];
            $totalCount = 0;
            $dbError = null;

            ob_start();
            require base_path('app/View/pages/superadmin_company_access_grants.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $pageTitle = 'Доступы: ' . $company['name'];
        $pageContext = 'Реестр компаний';

        $grants = [];
        $totalCount = 0;
        if (!empty($company['db_identifier'])) {
            try {
                $localDbConfig = $config['database'];
                $localDbConfig['database'] = $company['db_identifier'];
                $localDb = new \App\Core\Database($localDbConfig);
                $localPdo = $localDb->connection();
                applyLocalMigrations($localPdo);
                $stmt = $localPdo->prepare(
                    "SELECT g.id, g.entity_type, g.entity_id, g.granted_to_user_id,
                            g.granted_by_user_id, g.access_level, g.created_at,
                            u_to.full_name as granted_to_name,
                            u_by.full_name as granted_by_name
                     FROM entity_access_grants g
                     LEFT JOIN users u_to ON g.granted_to_user_id = u_to.id
                     LEFT JOIN users u_by ON g.granted_by_user_id = u_by.id
                     ORDER BY g.created_at DESC
                     LIMIT 100"
                );
                $stmt->execute();
                $grants = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $totalCount = count($grants);
            } catch (\Exception $e) {
                $dbError = 'Локальная БД компании недоступна.';
            }
        } else {
            $dbError = 'Локальная БД компании недоступна.';
        }

        $dbError = $dbError ?? null;
    } catch (\Exception $e) {
        $company = null;
        $grants = [];
        $totalCount = 0;
        $dbError = 'Ошибка подключения к базе данных. Попробуйте позже.';
    }

    ob_start();
    require base_path('app/View/pages/superadmin_company_access_grants.php');
    $content = ob_get_clean();
    require base_path('app/View/layouts/main.php');
});

$router->post('/superadmin/companies/{id}/access-grants/{grant_id}/revoke', function ($id, $grant_id) use ($config, $db) {
    requireRole('superadmin');

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([(int)$id]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company || empty($company['db_identifier'])) {
            header('Location: /superadmin/companies');
            exit;
        }

        $localDbConfig = $config['database'];
        $localDbConfig['database'] = $company['db_identifier'];
        $localDb = new \App\Core\Database($localDbConfig);
        $localPdo = $localDb->connection();
                applyLocalMigrations($localPdo);

        $localPdo->prepare("DELETE FROM entity_access_grants WHERE id = ?")->execute([(int)$grant_id]);
    } catch (\Exception $e) {
    }

    header('Location: /superadmin/companies/' . $id . '/access-grants');
    exit;
});

// ============================================================
// SUPERADMIN: Create user route before dynamic {user_id}
// ============================================================

$router->get('/superadmin/companies/{id}/users/logists/create', function ($id) use ($config, $db) {
    requireRole('superadmin');
    $pageTitle = 'Создать пользователя';
    $pageContext = 'Реестр компаний';

    $pdo = $db->connection();
    $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
    $stmt->execute([(int)$id]);
    $company = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$company) {
        $company = null;
        $errors = [];
        $old = [];
        $formError = 'Компания не найдена';
        $success = false;
        $newPassword = null;

        ob_start();
        require base_path('app/View/pages/superadmin_company_logist_create.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
        return;
    }

    $errors = [];
    $old = [];
    $formError = null;
    $success = false;
    $newPassword = null;

    ob_start();
    require base_path('app/View/pages/superadmin_company_logist_create.php');
    $content = ob_get_clean();
    require base_path('app/View/layouts/main.php');
});

// ============================================================
// SUPERADMIN: User management routes (legacy URL segment: logists)
// ============================================================

$router->get('/superadmin/companies/{company_id}/users/logists/{user_id}', function ($company_id, $user_id) use ($config, $db) {
    requireRole('superadmin');
    $pageTitle = 'Пользователь';
    $pageContext = 'Реестр компаний';

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([(int)$company_id]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company) {
            $company = null;
            $logist = null;
            $companyId = (int)$company_id;
            $logistId = (int)$user_id;
            $counts = [];
            $grantsCount = 0;
            $passwordReset = false;
            $newPassword = null;
            $dbError = null;

            ob_start();
            require base_path('app/View/pages/superadmin_company_logist_view.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $companyId = (int)$company_id;
        $logistId = (int)$user_id;
        $logist = null;
        $counts = [];
        $grantsCount = 0;
        $countsIncomplete = false;
        $passwordReset = false;
        $newPassword = null;
        $dbError = null;

        if (!empty($company['db_identifier'])) {
            try {
                $localDbConfig = $config['database'];
                $localDbConfig['database'] = $company['db_identifier'];
                $localDb = new \App\Core\Database($localDbConfig);
                $localPdo = $localDb->connection();
                applyLocalMigrations($localPdo);
                $hasColumn = static function (PDO $pdo, string $table, string $column): bool {
                    try {
                        $stmt = $pdo->prepare("SHOW COLUMNS FROM `{$table}` LIKE ?");
                        $stmt->execute([$column]);
                        return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
                    } catch (\Exception $e) {
                        return false;
                    }
                };

                $logistStmt = $localPdo->prepare("SELECT * FROM users WHERE id = ?");
                $logistStmt->execute([(int)$user_id]);
                $logist = $logistStmt->fetch(PDO::FETCH_ASSOC) ?: null;

                if ($logist) {
                    $pageTitle = 'Пользователь: ' . $logist['full_name'];

                    $countTables = ['clients', 'contractors', 'drivers', 'vehicle_units', 'crews'];
                    foreach ($countTables as $table) {
                        if ($hasColumn($localPdo, $table, 'created_by_user_id')) {
                            $countStmt = $localPdo->prepare("SELECT COUNT(*) FROM `{$table}` WHERE created_by_user_id = ?");
                            $countStmt->execute([(int)$user_id]);
                            $counts[$table] = (int)$countStmt->fetchColumn();
                        } else {
                            $counts[$table] = null;
                            $countsIncomplete = true;
                        }
                    }

                    if ($hasColumn($localPdo, 'documents', 'uploaded_by_user_id')) {
                        $docStmt = $localPdo->prepare("SELECT COUNT(*) FROM documents WHERE uploaded_by_user_id = ?");
                        $docStmt->execute([(int)$user_id]);
                        $counts['documents'] = (int)$docStmt->fetchColumn();
                    } else {
                        $counts['documents'] = null;
                        $countsIncomplete = true;
                    }

                    if ($hasColumn($localPdo, 'entity_access_grants', 'granted_to_user_id')) {
                        $grantStmt = $localPdo->prepare("SELECT COUNT(*) FROM entity_access_grants WHERE granted_to_user_id = ?");
                        $grantStmt->execute([(int)$user_id]);
                        $grantsCount = (int)$grantStmt->fetchColumn();
                    } else {
                        $grantsCount = null;
                        $countsIncomplete = true;
                    }
                }
            } catch (\Exception $e) {
                $dbError = 'Ошибка подключения к локальной БД компании.';
            }
        } else {
            $dbError = 'Локальная БД компании недоступна.';
        }
    } catch (\Exception $e) {
        $company = null;
        $logist = null;
        $companyId = (int)$company_id;
        $logistId = (int)$user_id;
        $counts = [];
        $grantsCount = 0;
        $countsIncomplete = false;
        $passwordReset = false;
        $newPassword = null;
        $dbError = 'Ошибка загрузки данных: ' . $e->getMessage();
    }

    ob_start();
    require base_path('app/View/pages/superadmin_company_logist_view.php');
    $content = ob_get_clean();
    require base_path('app/View/layouts/main.php');
});

$router->get('/superadmin/companies/{company_id}/users/logists/{user_id}/edit', function ($company_id, $user_id) use ($config, $db) {
    requireRole('superadmin');
    $pageTitle = 'Редактировать пользователя';
    $pageContext = 'Реестр компаний';

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([(int)$company_id]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company) {
            $company = null;
            $companyId = (int)$company_id;
            $logist = null;
            $logistId = (int)$user_id;
            $errors = [];
            $old = [];
            $formError = 'Компания не найдена';

            ob_start();
            require base_path('app/View/pages/superadmin_company_logist_edit.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $pageTitle = 'Редактировать: ' . $company['name'];
        $companyId = (int)$company_id;
        $logistId = (int)$user_id;
        $logist = null;
        $errors = [];
        $old = [];
        $formError = null;

        if (!empty($company['db_identifier'])) {
            try {
                $localDbConfig = $config['database'];
                $localDbConfig['database'] = $company['db_identifier'];
                $localDb = new \App\Core\Database($localDbConfig);
                $localPdo = $localDb->connection();
                applyLocalMigrations($localPdo);

                $logistStmt = $localPdo->prepare("SELECT * FROM users WHERE id = ?");
                $logistStmt->execute([(int)$user_id]);
                $logist = $logistStmt->fetch(PDO::FETCH_ASSOC) ?: null;

                if ($logist) {
                    $pageTitle = 'Редактировать: ' . $logist['full_name'];
                    $old = $logist;
                }
            } catch (\Exception $e) {
                $formError = 'Локальная БД компании недоступна.';
            }
        } else {
            $formError = 'Локальная БД компании недоступна.';
        }
    } catch (\Exception $e) {
        $company = null;
        $companyId = (int)$company_id;
        $logist = null;
        $logistId = (int)$user_id;
        $errors = [];
        $old = [];
        $formError = 'Ошибка загрузки данных: ' . $e->getMessage();
    }

    ob_start();
    require base_path('app/View/pages/superadmin_company_logist_edit.php');
    $content = ob_get_clean();
    require base_path('app/View/layouts/main.php');
});

$router->post('/superadmin/companies/{company_id}/users/logists/{user_id}/edit', function ($company_id, $user_id) use ($config, $db) {
    requireRole('superadmin');
    $pageTitle = 'Редактировать пользователя';
    $pageContext = 'Реестр компаний';

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([(int)$company_id]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company) {
            $company = null;
            $companyId = (int)$company_id;
            $logist = null;
            $logistId = (int)$user_id;
            $errors = [];
            $old = $_POST;
            $formError = 'Компания не найдена';

            ob_start();
            require base_path('app/View/pages/superadmin_company_logist_edit.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $companyId = (int)$company_id;
        $logistId = (int)$user_id;

        if (empty($company['db_identifier'])) {
            $logist = null;
            $errors = [];
            $old = $_POST;
            $formError = 'Локальная БД компании недоступна.';

            ob_start();
            require base_path('app/View/pages/superadmin_company_logist_edit.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $localDbConfig = $config['database'];
        $localDbConfig['database'] = $company['db_identifier'];
        $localDb = new \App\Core\Database($localDbConfig);
        $localPdo = $localDb->connection();
                applyLocalMigrations($localPdo);

        $logistStmt = $localPdo->prepare("SELECT * FROM users WHERE id = ?");
        $logistStmt->execute([(int)$user_id]);
        $logist = $logistStmt->fetch(PDO::FETCH_ASSOC) ?: null;

        if (!$logist) {
            $errors = [];
            $old = $_POST;
            $formError = 'Пользователь не найден.';

            ob_start();
            require base_path('app/View/pages/superadmin_company_logist_edit.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $pageTitle = 'Редактировать: ' . $logist['full_name'];

        $errors = [];
        $old = $_POST;

        $fullName = trim($_POST['full_name'] ?? '');
        $login = trim($_POST['login'] ?? '');
        $roleCode = trim($_POST['role_code'] ?? $logist['role_code']);

        if ($fullName === '') {
            $errors['full_name'] = 'Обязательное поле';
        }

        if ($login === '') {
            $errors['login'] = 'Обязательное поле';
        } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $login)) {
            $errors['login'] = 'Только латинские буквы, цифры и подчёркивание';
        } else {
            $dupStmt = $localPdo->prepare("SELECT COUNT(*) FROM users WHERE login = ? AND id != ?");
            $dupStmt->execute([$login, (int)$user_id]);
            if ($dupStmt->fetchColumn() > 0) {
                $errors['login'] = 'Логин уже используется в этой компании';
            }
        }

        // FR12: Validate role
        $allowedRoles = ['logist', 'senior_logist'];
        if (!in_array($roleCode, $allowedRoles, true)) {
            $errors['role_code'] = 'Недопустимая роль';
        }

        $email = trim($_POST['email'] ?? '');
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Некорректный email';
        }

        if (!empty($errors)) {
            ob_start();
            require base_path('app/View/pages/superadmin_company_logist_edit.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $update = $localPdo->prepare(
            "UPDATE users SET
                full_name = :full_name,
                login = :login,
                email = :email,
                phone = :phone,
                role_code = :role_code,
                status = :status,
                comments = :comments,
                updated_at = NOW()
             WHERE id = :id"
        );

        $update->execute([
            ':full_name' => $fullName,
            ':login'     => $login,
            ':email'     => $email !== '' ? $email : null,
            ':phone'     => trim($_POST['phone'] ?? '') ?: null,
            ':role_code' => $roleCode,
            ':status'    => $_POST['status'] ?? $logist['status'],
            ':comments'  => trim($_POST['comments'] ?? '') ?: null,
            ':id'        => (int)$user_id,
        ]);

        header('Location: /superadmin/companies/' . $company_id . '/users/logists/' . $user_id);
        exit;
    } catch (\Exception $e) {
        $company = $company ?? null;
        $companyId = (int)$company_id;
        $logist = $logist ?? null;
        $logistId = (int)$user_id;
        $errors = [];
        $old = $_POST;
        $formError = 'Ошибка сохранения: ' . $e->getMessage();

        ob_start();
        require base_path('app/View/pages/superadmin_company_logist_edit.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
    }
});

$router->post('/superadmin/companies/{company_id}/users/logists/{user_id}/reset-password', function ($company_id, $user_id) use ($config, $db) {
    requireRole('superadmin');
    $pageTitle = 'Пользователь';
    $pageContext = 'Реестр компаний';

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([(int)$company_id]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company) {
            $company = null;
            $companyId = (int)$company_id;
            $logist = null;
            $logistId = (int)$user_id;
            $counts = [];
            $grantsCount = 0;
            $countsIncomplete = false;
            $passwordReset = false;
            $newPassword = null;
            $dbError = 'Компания не найдена';

            ob_start();
            require base_path('app/View/pages/superadmin_company_logist_view.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $companyId = (int)$company_id;
        $logistId = (int)$user_id;

        if (empty($company['db_identifier'])) {
            $logist = null;
            $counts = [];
            $grantsCount = 0;
            $countsIncomplete = false;
            $passwordReset = false;
            $newPassword = null;
            $dbError = 'Локальная БД компании недоступна.';

            ob_start();
            require base_path('app/View/pages/superadmin_company_logist_view.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $localDbConfig = $config['database'];
        $localDbConfig['database'] = $company['db_identifier'];
        $localDb = new \App\Core\Database($localDbConfig);
        $localPdo = $localDb->connection();
                applyLocalMigrations($localPdo);
        $hasColumn = static function (PDO $pdo, string $table, string $column): bool {
            try {
                $stmt = $pdo->prepare("SHOW COLUMNS FROM `{$table}` LIKE ?");
                $stmt->execute([$column]);
                return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
            } catch (\Exception $e) {
                return false;
            }
        };

                $logistStmt = $localPdo->prepare("SELECT * FROM users WHERE id = ?");
                $logistStmt->execute([(int)$user_id]);
                $logist = $logistStmt->fetch(PDO::FETCH_ASSOC) ?: null;

        if (!$logist) {
            $counts = [];
            $grantsCount = 0;
            $countsIncomplete = false;
            $passwordReset = false;
            $newPassword = null;
            $dbError = null;

            ob_start();
            require base_path('app/View/pages/superadmin_company_logist_view.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $pageTitle = 'Пользователь: ' . $logist['full_name'];

        $newPassword = generatePassword(10);
        $passwordHash = password_hash($newPassword, PASSWORD_BCRYPT);

        $update = $localPdo->prepare("UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?");
        $update->execute([$passwordHash, (int)$logist['id']]);

        $passwordReset = true;
        $dbError = null;

        $countTables = ['clients', 'contractors', 'drivers', 'vehicle_units', 'crews'];
        $counts = [];
        $countsIncomplete = false;
        foreach ($countTables as $table) {
            if ($hasColumn($localPdo, $table, 'created_by_user_id')) {
                $countStmt = $localPdo->prepare("SELECT COUNT(*) FROM `{$table}` WHERE created_by_user_id = ?");
                $countStmt->execute([(int)$user_id]);
                $counts[$table] = (int)$countStmt->fetchColumn();
            } else {
                $counts[$table] = null;
                $countsIncomplete = true;
            }
        }
        if ($hasColumn($localPdo, 'documents', 'uploaded_by_user_id')) {
            $docStmt = $localPdo->prepare("SELECT COUNT(*) FROM documents WHERE uploaded_by_user_id = ?");
            $docStmt->execute([(int)$user_id]);
            $counts['documents'] = (int)$docStmt->fetchColumn();
        } else {
            $counts['documents'] = null;
            $countsIncomplete = true;
        }
        if ($hasColumn($localPdo, 'entity_access_grants', 'granted_to_user_id')) {
            $grantStmt = $localPdo->prepare("SELECT COUNT(*) FROM entity_access_grants WHERE granted_to_user_id = ?");
            $grantStmt->execute([(int)$user_id]);
            $grantsCount = (int)$grantStmt->fetchColumn();
        } else {
            $grantsCount = null;
            $countsIncomplete = true;
        }

        ob_start();
        require base_path('app/View/pages/superadmin_company_logist_view.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
    } catch (\Exception $e) {
        $company = $company ?? null;
        $companyId = (int)$company_id;
        $logist = $logist ?? null;
        $logistId = (int)$user_id;
        $counts = [];
        $grantsCount = 0;
        $passwordReset = false;
        $newPassword = null;
        $dbError = 'Ошибка сброса пароля: ' . $e->getMessage();

        ob_start();
        require base_path('app/View/pages/superadmin_company_logist_view.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
    }
});

$router->post('/superadmin/companies/{company_id}/users/logists/{user_id}/activate', function ($company_id, $user_id) use ($config, $db) {
    requireRole('superadmin');

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([(int)$company_id]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company || empty($company['db_identifier'])) {
            header('Location: /superadmin/companies/' . $company_id . '/users');
            exit;
        }

        $localDbConfig = $config['database'];
        $localDbConfig['database'] = $company['db_identifier'];
        $localDb = new \App\Core\Database($localDbConfig);
        $localPdo = $localDb->connection();
                applyLocalMigrations($localPdo);

        $localPdo->prepare("UPDATE users SET status = 'active', updated_at = NOW() WHERE id = ?")
            ->execute([(int)$user_id]);
    } catch (\Exception $e) {
    }

    header('Location: /superadmin/companies/' . $company_id . '/users/logists/' . $user_id . '?status_changed=1');
    exit;
});

$router->post('/superadmin/companies/{company_id}/users/logists/{user_id}/block', function ($company_id, $user_id) use ($config, $db) {
    requireRole('superadmin');

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([(int)$company_id]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company || empty($company['db_identifier'])) {
            header('Location: /superadmin/companies/' . $company_id . '/users');
            exit;
        }

        $localDbConfig = $config['database'];
        $localDbConfig['database'] = $company['db_identifier'];
        $localDb = new \App\Core\Database($localDbConfig);
        $localPdo = $localDb->connection();
                applyLocalMigrations($localPdo);

        $localPdo->prepare("UPDATE users SET status = 'blocked', updated_at = NOW() WHERE id = ?")
            ->execute([(int)$user_id]);
    } catch (\Exception $e) {
    }

    header('Location: /superadmin/companies/' . $company_id . '/users/logists/' . $user_id . '?status_changed=1');
    exit;
});

$router->post('/superadmin/companies/{company_id}/users/logists/{user_id}/archive', function ($company_id, $user_id) use ($config, $db) {
    requireRole('superadmin');

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([(int)$company_id]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company || empty($company['db_identifier'])) {
            header('Location: /superadmin/companies/' . $company_id . '/users');
            exit;
        }

        $localDbConfig = $config['database'];
        $localDbConfig['database'] = $company['db_identifier'];
        $localDb = new \App\Core\Database($localDbConfig);
        $localPdo = $localDb->connection();
                applyLocalMigrations($localPdo);

        $localPdo->prepare("UPDATE users SET status = 'archived', updated_at = NOW() WHERE id = ?")
            ->execute([(int)$user_id]);
    } catch (\Exception $e) {
    }

    header('Location: /superadmin/companies/' . $company_id . '/users/logists/' . $user_id . '?status_changed=1');
    exit;
});

$router->post('/superadmin/companies/{id}/users/logists/create', function ($id) use ($config, $db) {
    requireRole('superadmin');
    $pageTitle = 'Создать пользователя';
    $pageContext = 'Реестр компаний';

    $pdo = $db->connection();
    $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
    $stmt->execute([(int)$id]);
    $company = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$company) {
        $company = null;
        $errors = [];
        $old = $_POST;
        $formError = 'Компания не найдена';
        $success = false;
        $newPassword = null;

        ob_start();
        require base_path('app/View/pages/superadmin_company_logist_create.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
        return;
    }

    $errors = [];
    $old = $_POST;
    $formError = null;
    $success = false;
    $newPassword = null;

    $fullName = trim($_POST['full_name'] ?? '');
    $login = trim($_POST['login'] ?? '');
    $roleCode = trim($_POST['role_code'] ?? 'logist');

    if ($fullName === '') {
        $errors['full_name'] = 'Обязательное поле';
    }
    if ($login === '') {
        $errors['login'] = 'Обязательное поле';
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $login)) {
        $errors['login'] = 'Только латиница, цифры и _';
    }

    // FR10: Validate role against whitelist
    $allowedRoles = ['logist', 'senior_logist'];
    if (!in_array($roleCode, $allowedRoles, true)) {
        $errors['role_code'] = 'Недопустимая роль';
    }

    if (!empty($errors)) {
        ob_start();
        require base_path('app/View/pages/superadmin_company_logist_create.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
        return;
    }

    if (empty($company['db_identifier'])) {
        $formError = 'Локальная БД компании недоступна.';
        ob_start();
        require base_path('app/View/pages/superadmin_company_logist_create.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
        return;
    }

    try {
        // Создать локальную БД, если не существует
        try {
            $tempPdo = new PDO(
                sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $config['database']['host'] ?? '127.0.0.1', $config['database']['port'] ?? '3306'),
                $config['database']['username'] ?? 'root',
                $config['database']['password'] ?? ''
            );
            $tempPdo->exec("CREATE DATABASE IF NOT EXISTS `{$company['db_identifier']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        } catch (\Exception $e) {
            // Игнорируем ошибку создания БД — основное подключение поймает проблему
        }
        // Release tempPdo to avoid any connection state interference
        $tempPdo = null;

        $localDbConfig = $config['database'];
        $localDbConfig['database'] = $company['db_identifier'];
        $localDb = new \App\Core\Database($localDbConfig);
        $localPdo = $localDb->connection();
                applyLocalMigrations($localPdo);

        $dupStmt = $localPdo->prepare("SELECT COUNT(*) FROM users WHERE login = ?");
        $dupStmt->execute([$login]);
        if ($dupStmt->fetchColumn() > 0) {
            $errors['login'] = 'Логин уже используется в этой компании';
            ob_start();
            require base_path('app/View/pages/superadmin_company_logist_create.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        if (!empty($_POST['password'])) {
            $newPassword = $_POST['password'];
        } else {
            $newPassword = generatePassword(10);
        }
        $passwordHash = password_hash($newPassword, PASSWORD_BCRYPT);

        $insert = $localPdo->prepare(
            "INSERT INTO users (full_name, login, email, phone, password_hash, role_code, status, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, 'active', NOW(), NOW())"
        );
        $insert->execute([
            $fullName,
            $login,
            $_POST['email'] ?? null,
            $_POST['phone'] ?? null,
            $passwordHash,
            $roleCode,
        ]);

        $old['role_label'] = $roleCode === 'logist' ? 'Пользователь' : $roleCode;
        $success = true;

        ob_start();
        require base_path('app/View/pages/superadmin_company_logist_create.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
    } catch (\Exception $e) {
        $formError = 'Ошибка создания пользователя: ' . $e->getMessage();
        ob_start();
        require base_path('app/View/pages/superadmin_company_logist_create.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
    }
});

// ============================================================
// SUPERADMIN: Owner status actions (NEW)
// ============================================================
