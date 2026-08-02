<?php

requireRole('superadmin');
$companyId = (int) $companyId;
$documentId = (int) $documentId;
$pdo = $db->connection();
$company = \App\Service\SuperadminCompanyService::loadCompany($pdo, $companyId);
if (!$company) {
    http_response_code(404);
    exit;
}

$cfg = companyDatabaseConfig($config, $company);
$localDb = new \App\Core\Database($cfg);
$localPdo = $localDb->connection();
applyLocalMigrations($localPdo);

$stmt = $localPdo->prepare('SELECT * FROM documents WHERE id = ? AND deleted_at IS NULL');
$stmt->execute([$documentId]);
$document = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$document) {
    http_response_code(404);
    exit;
}

$filePath = \App\Service\DocumentService::resolveStoredPath($companyId, (string) $document['relative_path']);
if ($filePath === null) {
    http_response_code(404);
    exit;
}

$name = \App\Service\DocumentService::safeDownloadName((string) $document['original_name']);
header('Content-Type: ' . \App\Service\DocumentService::responseMime($filePath));
header('Content-Disposition: attachment; filename="' . $name . '"');
header('Content-Length: ' . filesize($filePath));
readfile($filePath);
exit;
