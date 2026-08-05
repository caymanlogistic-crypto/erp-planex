<?php

declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(E_ALL);

try {
    $config = require getcwd() . '/bootstrap/app.php';
    require_once BASE_PATH . '/app/Support/entrypoint_dependencies.php';
    require_once BASE_PATH . '/app/Support/company_database.php';

    $centralPdo = (new \App\Core\Database($config['database']))->connection();
    $stmt = $centralPdo->prepare('SELECT * FROM companies WHERE id = ?');
    $stmt->execute([27]);
    $company = $stmt->fetch(\PDO::FETCH_ASSOC);
    if (!$company || (string) ($company['name'] ?? '') !== 'UIUX TEST EXPEDITOR') {
        throw new \RuntimeException('Test tenant unavailable.');
    }

    $localPdo = (new \App\Core\Database(companyDatabaseConfig($config, $company)))->connection();
    $routeStmt = $localPdo->prepare(
        "SELECT r.id, ct.name AS cargo_type_name, r.client_id, r.carrier_contractor_id,
                r.route_executor_id, r.deleted_at
         FROM linear_routes r
         INNER JOIN cargo_types ct ON ct.id = r.cargo_type_id
         WHERE ct.name = ?
         ORDER BY r.id DESC
         LIMIT 1"
    );
    $routeStmt->execute(['P15 DELETE TEMP рейс']);
    $route = $routeStmt->fetch(\PDO::FETCH_ASSOC);

    $result = [
        'status' => 'FAIL',
        'company_id' => 27,
        'route' => null,
        'relations' => [],
        'finance' => [],
        'audit' => null,
    ];

    if ($route) {
        $checks = [];
        foreach ([
            'client' => ['clients', (int) $route['client_id']],
            'contractor' => ['contractors', (int) $route['carrier_contractor_id']],
            'route_executor' => ['driver_vehicle_blocks', (int) $route['route_executor_id']],
        ] as $key => [$table, $id]) {
            if (!preg_match('/^[a-z_]+$/', $table)) {
                throw new \RuntimeException('Invalid relation table.');
            }
            $q = $localPdo->prepare("SELECT COUNT(*) FROM `{$table}` WHERE id = ? AND deleted_at IS NULL");
            $q->execute([$id]);
            $checks[$key] = (int) $q->fetchColumn() === 1;
        }

        $legacyStmt = $localPdo->prepare(
            "SELECT party_role, amount, payment_due_type, payment_due_days, payment_due_days_kind
             FROM linear_route_financial_terms
             WHERE linear_route_id = ? AND deleted_at IS NULL
             ORDER BY id"
        );
        $legacyStmt->execute([(int) $route['id']]);
        $legacy = $legacyStmt->fetchAll(\PDO::FETCH_ASSOC);

        $modernStmt = $localPdo->prepare(
            "SELECT party_role, amount, condition_type, payment_due_type, payment_status
             FROM linear_route_payments
             WHERE linear_route_id = ? AND deleted_at IS NULL
             ORDER BY id"
        );
        $modernStmt->execute([(int) $route['id']]);
        $modern = $modernStmt->fetchAll(\PDO::FETCH_ASSOC);

        $auditStmt = $centralPdo->prepare(
            "SELECT id, status, restored_at
             FROM deleted_entities
             WHERE company_id = ? AND entity_type = 'linear_route' AND entity_id = ?
             ORDER BY id DESC LIMIT 1"
        );
        $auditStmt->execute([27, (int) $route['id']]);
        $audit = $auditStmt->fetch(\PDO::FETCH_ASSOC) ?: null;

        $legacyDuePresent = count($legacy) >= 2;
        foreach ($legacy as $term) {
            $legacyDuePresent = $legacyDuePresent && trim((string) ($term['payment_due_type'] ?? '')) !== '';
        }
        $modernDuePresent = count($modern) >= 2;
        foreach ($modern as $payment) {
            $modernDuePresent = $modernDuePresent
                && trim((string) ($payment['condition_type'] ?? '')) !== ''
                && trim((string) ($payment['payment_due_type'] ?? '')) !== '';
        }

        $result['route'] = [
            'id' => (int) $route['id'],
            'name' => (string) $route['cargo_type_name'],
            'deleted_at_is_null' => $route['deleted_at'] === null,
        ];
        $result['relations'] = $checks;
        $result['finance'] = [
            'legacy_terms_count' => count($legacy),
            'modern_payments_count' => count($modern),
            'legacy_due_type_present' => $legacyDuePresent,
            'modern_due_type_present' => $modernDuePresent,
        ];
        $result['audit'] = $audit === null ? null : [
            'id' => (int) $audit['id'],
            'status' => (string) $audit['status'],
            'restored_at_present' => !empty($audit['restored_at']),
        ];
        $auditRestored = $audit === null || ((string) $audit['status'] === 'restored' && !empty($audit['restored_at']));
        $result['status'] = (
            $result['route']['deleted_at_is_null']
            && !in_array(false, $checks, true)
            && $legacyDuePresent
            && $modernDuePresent
            && $auditRestored
        ) ? 'PASS' : 'FAIL';
    }

    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), PHP_EOL;
    exit($result['status'] === 'PASS' ? 0 : 1);
} catch (\Throwable $error) {
    echo json_encode([
        'status' => 'FAIL',
        'error_class' => get_class($error),
        'error_message' => substr((string) $error->getMessage(), 0, 300),
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), PHP_EOL;
    exit(1);
}
