<?php

    requireRole('superadmin');
    $pageTitle = 'Реестр компаний';
    $pageContext = 'Реестр компаний';

    $search = trim($_GET['search'] ?? '');
    $filterStatus = trim($_GET['status'] ?? '');

    try {
        $pdo = $db->connection();

        $sql = 'SELECT c.*,
                       cu.full_name as owner_name,
                       (SELECT COUNT(*) FROM company_users WHERE company_id = c.id) as user_count
                FROM companies c
                LEFT JOIN company_users cu ON c.id = cu.company_id AND cu.role = :owner_role';

        $conditions = [];
        $params = [':owner_role' => 'company_owner'];

        if ($search !== '') {
            $conditions[] = '(c.name LIKE :search OR c.inn LIKE :search2)';
            $params[':search'] = '%' . $search . '%';
            $params[':search2'] = '%' . $search . '%';
        }

        if ($filterStatus !== '') {
            $conditions[] = 'c.status = :status';
            $params[':status'] = $filterStatus;
        }

        if (!empty($conditions)) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }

        $sql .= ' ORDER BY c.created_at DESC';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $companies = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $dbError = null;
    } catch (\Exception $e) {
        $companies = [];
        $dbError = 'Не удалось загрузить список компаний. Попробуйте позже.';
    }

    ob_start();
    require base_path('app/View/pages/superadmin_companies.php');
    $content = ob_get_clean();

    require base_path('app/View/layouts/main.php');
