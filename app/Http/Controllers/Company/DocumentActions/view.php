<?php

use App\Service\LinearRouteService;

requireRole(['company_owner', 'senior_logist', 'logist']);

$companyId = (int) (getSessionCompanyId() ?? 0);
$docId = (int) ($_GET['id'] ?? 0);

if ($companyId <= 0 || $docId <= 0) {
    http_response_code(404);
    exit;
}

try {
    $pdo = $db->connection();
    $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
    $stmt->execute([$companyId]);
    $company = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$company || ($company['status'] ?? '') !== 'active') {
        http_response_code(404);
        exit;
    }

    $cfg = $config['database'];
    $cfg['database'] = $company['db_identifier'];
    $ldb = new \App\Core\Database($cfg);
    $lpdo = $ldb->connection();
    applyLocalMigrations($lpdo);

    $docStmt = $lpdo->prepare('SELECT * FROM documents WHERE id = ? AND deleted_at IS NULL');
    $docStmt->execute([$docId]);
    $doc = $docStmt->fetch(PDO::FETCH_ASSOC) ?: null;
    if ($doc === null) {
        http_response_code(404);
        exit;
    }

    if (($doc['entity_type'] ?? '') === 'linear_route') {
        $route = LinearRouteService::fetchRouteById($lpdo, (int) ($doc['entity_id'] ?? 0));
        $sessionUser = [
            'user_id' => (int) ($_SESSION['user_id'] ?? 0),
            'role_code' => (string) ($_SESSION['role_code'] ?? ''),
        ];

        if ($route === null) {
            http_response_code(404);
            exit;
        }
        if (!LinearRouteService::canViewRoute($route, $lpdo, $sessionUser)) {
            http_response_code(403);
            exit;
        }
    }

    $relPath = (string) ($doc['relative_path'] ?? '');
    if ($relPath === '' || strpos($relPath, '..') !== false) {
        http_response_code(404);
        exit;
    }

    $filePath = storage_path($relPath);
    if (!is_file($filePath)) {
        http_response_code(404);
        exit;
    }

    $mime = (string) ($doc['mime_type'] ?: 'application/octet-stream');
    $fileSize = (int) ($doc['file_size'] ?? 0);
    $originalName = (string) ($doc['original_name'] ?? 'document');

    header('Content-Type: ' . $mime);
    header(
        'Content-Disposition: '
        . ((strpos($mime, 'image/') === 0 || strpos($mime, 'pdf') !== false || strpos($mime, 'text/') === 0) ? 'inline' : 'attachment')
        . '; filename="' . addcslashes($originalName, "\"\\") . '"'
    );
    if ($fileSize > 0) {
        header('Content-Length: ' . $fileSize);
    }

    readfile($filePath);
    exit;
} catch (\Throwable $e) {
    http_response_code(500);
    exit;
}
