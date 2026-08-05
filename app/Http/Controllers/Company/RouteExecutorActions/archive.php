<?php

    requireRole(['company_owner', 'senior_logist', 'logist']);
    $crewId = (int)$crewId;

    $companyId = (int)(getSessionCompanyId() ?? 0);

    if ($companyId <= 0) {
        header('Location: ' . app_url('/company/route-executors'));
        exit;
    }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company || $company['status'] !== 'active') {
            header('Location: ' . app_url('/company/route-executors'));
            exit;
        }

        $localDbConfig = companyDatabaseConfig($config, $company);
        $localDb = new \App\Core\Database($localDbConfig);
        $localPdo = $localDb->connection();
        applyLocalMigrations($localPdo);

        try {
            $localPdo->query("SELECT 1 FROM crews LIMIT 1")->fetch();
        } catch (\Exception $e) {
            $migrationSql = file_get_contents(base_path('database/migrations-local/006_create_company_crews.sql'));
            $localPdo->exec($migrationSql);
        }

        // Permission check
        $currentUserId = (int)($_SESSION['user_id'] ?? 0);
        $currentRole = $_SESSION['role_code'] ?? '';

        $crewStmt = $localPdo->prepare('SELECT * FROM crews WHERE id = ?');
        $crewStmt->execute([$crewId]);
        $crew = $crewStmt->fetch(PDO::FETCH_ASSOC);

        if (!$crew) {
            header('Location: ' . app_url('/company/route-executors'));
            exit;
        }

        if ($currentRole !== 'company_owner' && $currentRole !== 'senior_logist') {
            $isCreator = ($crew['created_by_user_id'] ?? 0) === $currentUserId;

            $grantStmt = $localPdo->prepare(
                "SELECT 1 FROM entity_access_grants
                 WHERE entity_type = 'crew' AND entity_id = ?
                 AND granted_to_user_id = ? AND access_level = 'edit'
                 AND (revoked_at IS NULL)"
            );
            $grantStmt->execute([$crewId, $currentUserId]);
            $hasEditGrant = (bool)$grantStmt->fetchColumn();

            if (!$isCreator && !$hasEditGrant) {
                http_response_code(403);
                header('Content-Type: text/plain');
                echo '403 Forbidden';
                exit;
            }
        }

        $update = $localPdo->prepare("UPDATE crews SET deleted_at = NOW(), deleted_by_user_id = ?, deleted_by_role = ? WHERE id = ?");
        $update->execute([(int)($_SESSION['user_id'] ?? 0), $_SESSION['role_code'] ?? '', $crewId]);

        $crewSnapshot = json_encode($crew, JSON_UNESCAPED_UNICODE);
        $displayName = 'Экипаж #' . $crewId;
        $uid = (int)($_SESSION['user_id'] ?? 0);
        $rl = (string)($_SESSION['role_code'] ?? '');
        $un = $_SESSION['user_name'] ?? '';
        try {
            $centralPdo = $db->connection();
            \App\Service\AuditService::recordDeletion($centralPdo, $company, 'crew', $crewId, 'crews', $displayName, $uid, $rl, $un, null, $crewSnapshot);
        } catch (\Exception $auditEx) {
            error_log('Audit record failed for crew ' . $crewId . ': ' . $auditEx->getMessage());
        }

        header('Location: ' . app_url('/company/route-executors'));
        exit;
    } catch (\Exception $e) {
        header('Location: ' . app_url('/company/route-executors'));
        exit;
    }
