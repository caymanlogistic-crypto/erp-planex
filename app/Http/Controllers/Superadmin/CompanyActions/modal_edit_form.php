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
        $old = $company;
        $formError = null;

        $leExistingDocs = [];
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
                $leExistingDocs = $docStmt->fetchAll(\PDO::FETCH_ASSOC);
            } catch (\Exception $e) {
            }
        }

        ob_start();
        require base_path('app/View/partials/superadmin_company_modal_edit.php');
        echo ob_get_clean();
    } catch (\Exception $e) {
        http_response_code(500);
        echo '<div class="modal-body"><div class="notice warn">Ошибка загрузки: ' . e($e->getMessage()) . '</div></div>';
    }
