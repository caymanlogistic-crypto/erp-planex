<?php

use App\Service\LinearRouteService;

requireRole(['company_owner', 'senior_logist', 'logist']);

$companyId = (int) (getSessionCompanyId() ?? 0);
if ($companyId <= 0) {
    jsonResponse(['items' => []], 200);
    return;
}

try {
    $pdo = $db->connection();
    $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
    $stmt->execute([$companyId]);
    $company = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$company || ($company['status'] ?? '') !== 'active') {
        jsonResponse(['items' => []], 200);
        return;
    }

    $localDbConfig = companyDatabaseConfig($config, $company);
    $localDb = new \App\Core\Database($localDbConfig);
    $localPdo = $localDb->connection();
    applyLocalMigrations($localPdo);

    $items = LinearRouteService::cargoTypeSuggestions($localPdo, (string) ($_GET['q'] ?? ''));
    jsonResponse(['items' => $items], 200);
} catch (\Throwable $e) {
    jsonResponse(['items' => []], 200);
}
