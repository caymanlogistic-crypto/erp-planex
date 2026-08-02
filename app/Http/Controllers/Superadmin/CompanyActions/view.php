<?php

    requireRole('superadmin');

    require_once base_path('app/Support/company_database.php');
    $pageTitle = 'Карточка компании';
    $pageContext = 'Реестр компаний';

    try {
        $pdo = $db->connection();

        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([(int) $id]);
        $company = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$company) {
            $company = null;
            $owner = null;
            $dbError = null;

            ob_start();
            require base_path('app/View/pages/superadmin_company_view.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            return;
        }

        $pageTitle = 'Компания: ' . $company['name'];

        $ownerStmt = $pdo->prepare(
            "SELECT * FROM company_users WHERE company_id = ? AND role = 'company_owner'"
        );
        $ownerStmt->execute([(int) $id]);
        $owner = $ownerStmt->fetch(\PDO::FETCH_ASSOC) ?: null;

        $userStats = ['total' => 0, 'active' => 0, 'blocked' => 0, 'owner_count' => 0, 'logist_count' => 0];
        $ownerCountStmt = $pdo->prepare(
            "SELECT COUNT(*) as owner_total,
                    SUM(CASE WHEN status='active' THEN 1 ELSE 0 END) as owner_active,
                    SUM(CASE WHEN status='blocked' THEN 1 ELSE 0 END) as owner_blocked
             FROM company_users WHERE company_id = ?"
        );
        $ownerCountStmt->execute([(int)$id]);
        $ownerCounts = $ownerCountStmt->fetch(\PDO::FETCH_ASSOC);
        $userStats['owner_count'] = (int)($ownerCounts['owner_total'] ?? 0);
        $userStats['total'] += $userStats['owner_count'];
        $userStats['active'] += (int)($ownerCounts['owner_active'] ?? 0);
        $userStats['blocked'] += (int)($ownerCounts['owner_blocked'] ?? 0);

        $dirs = [
            'clients_total' => 0, 'clients_active' => 0, 'clients_archived' => 0,
            'contractors_total' => 0, 'contractors_active' => 0, 'contractors_archived' => 0,
            'drivers_total' => 0, 'drivers_active' => 0, 'drivers_archived' => 0,
            'vehicle_units_total' => 0, 'vehicle_units_active' => 0, 'vehicle_units_archived' => 0,
            'vehicle_sets_total' => 0, 'vehicle_sets_active' => 0, 'vehicle_sets_archived' => 0,
            'driver_vehicle_blocks_total' => 0, 'driver_vehicle_blocks_active' => 0, 'driver_vehicle_blocks_archived' => 0,
            'crews_total' => 0, 'crews_active' => 0, 'crews_archived' => 0,
        ];
        $docStats = ['total' => 0, 'active' => 0];
        $accessStats = ['total' => 0];
        $companyDocs = [];
        $localDbExists = false;
        $storageExists = false;

        if (!empty($company['db_identifier'])) {
            try {
                $localDbConfig = companyDatabaseConfig($config, $company);
                $localDb = new \App\Core\Database($localDbConfig);
                $localPdo = $localDb->connection();
                applyLocalMigrations($localPdo);
                $localDbExists = true;

                $logistCountStmt = $localPdo->prepare(
                    "SELECT COUNT(*) as logist_total,
                            SUM(CASE WHEN status='active' THEN 1 ELSE 0 END) as logist_active,
                            SUM(CASE WHEN status='blocked' THEN 1 ELSE 0 END) as logist_blocked
                     FROM users WHERE role_code IN ('logist', 'senior_logist')"
                );
                $logistCountStmt->execute();
                $logistCounts = $logistCountStmt->fetch(\PDO::FETCH_ASSOC);
                $userStats['logist_count'] = (int)($logistCounts['logist_total'] ?? 0);
                $userStats['total'] += $userStats['logist_count'];
                $userStats['active'] += (int)($logistCounts['logist_active'] ?? 0);
                $userStats['blocked'] += (int)($logistCounts['logist_blocked'] ?? 0);

                $dirTables = ['clients', 'contractors', 'drivers', 'vehicle_units', 'vehicle_sets', 'driver_vehicle_blocks', 'crews'];
                foreach ($dirTables as $table) {
                    $dirStmt = $localPdo->prepare(
                        "SELECT COUNT(*) as total,
                                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
                                SUM(CASE WHEN status = 'archived' THEN 1 ELSE 0 END) as archived
                         FROM `{$table}`"
                    );
                    $dirStmt->execute();
                    $row = $dirStmt->fetch(\PDO::FETCH_ASSOC);
                    $dirs[$table . '_total'] = (int)($row['total'] ?? 0);
                    $dirs[$table . '_active'] = (int)($row['active'] ?? 0);
                    $dirs[$table . '_archived'] = (int)($row['archived'] ?? 0);
                }

                $docCountStmt = $localPdo->prepare(
                    "SELECT COUNT(*) as total,
                            SUM(CASE WHEN deleted_at IS NULL THEN 1 ELSE 0 END) as active
                     FROM documents"
                );
                $docCountStmt->execute();
                $docRow = $docCountStmt->fetch(\PDO::FETCH_ASSOC);
                $docStats['total'] = (int)($docRow['total'] ?? 0);
                $docStats['active'] = (int)($docRow['active'] ?? 0);

                $companyDocsStmt = $localPdo->prepare(
                    'SELECT d.*, dt.name AS type_name, dt.code AS type_code
                     FROM documents d
                     LEFT JOIN document_types dt ON d.document_type_id = dt.id
                     WHERE d.entity_type = ? AND d.entity_id = ? AND d.deleted_at IS NULL
                     ORDER BY d.created_at DESC LIMIT 20'
                );
                $companyDocsStmt->execute(['company', (int)$id]);
                $companyDocs = $companyDocsStmt->fetchAll(\PDO::FETCH_ASSOC);

                $accessCountStmt = $localPdo->prepare("SELECT COUNT(*) as total FROM entity_access_grants");
                $accessCountStmt->execute();
                $accessStats['total'] = (int)$accessCountStmt->fetchColumn();
            } catch (\Exception $e) {
            }
        }

        if (!empty($company['storage_path'])) {
            $resolvedPath = base_path($company['storage_path']);
            $storageExists = is_dir($resolvedPath);
        } else {
            $fallbackPath = storage_path('companies/' . (int)$id);
            $storageExists = is_dir($fallbackPath);
        }

        $dbError = null;

        ob_start();
        require base_path('app/View/pages/superadmin_company_view.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
    } catch (\Exception $e) {
        $company = null;
        $owner = null;
        $dbError = 'Не удалось загрузить компанию: ' . $e->getMessage();

        ob_start();
        require base_path('app/View/pages/superadmin_company_view.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
    }
