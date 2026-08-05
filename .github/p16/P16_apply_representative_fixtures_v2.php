<?php

declare(strict_types=1);

// Decode the reviewed P16 fixture source from the v1 payload, apply one
// explicit compatibility correction for the central company_users schema,
// verify the resulting source hash, and execute it from a temporary file.
$wrapperPath = __DIR__ . '/P16_apply_representative_fixtures.php';
$wrapper = file_get_contents($wrapperPath);
if (!is_string($wrapper) || !preg_match("/\\$payload = '([^']+)'/", $wrapper, $matches)) {
    throw new RuntimeException('P16 fixture payload was not found.');
}
$source = gzdecode(base64_decode($matches[1], true));
if (!is_string($source) || hash('sha256', $source) !== '08031fe8097e9bde64f005418b2a47e64900f88eb4552f1ab35b00f6c09b1f72') {
    throw new RuntimeException('P16 original fixture payload integrity failure.');
}
$before = <<<'PHP'
    $ownerStmt = $central->prepare("SELECT id FROM company_users WHERE company_id = ? AND role_code = 'company_owner' AND status = 'active' ORDER BY id LIMIT 1");
    $ownerStmt->execute([TEST_TENANT_ID]);
PHP;
$after = <<<'PHP'
    $ownerStmt = $central->prepare("SELECT id FROM company_users WHERE company_id = ? AND login = ? AND status = 'active' ORDER BY id LIMIT 1");
    $ownerStmt->execute([TEST_TENANT_ID, 'p14_owner_260804221827']);
PHP;
if (substr_count($source, $before) !== 1) {
    throw new RuntimeException('P16 central owner compatibility anchor mismatch.');
}
$source = str_replace($before, $after, $source);
if (hash('sha256', $source) !== 'd45f56a3328e3e5ecc8381b156d7fe729c6259e9128dba464b2f57e1cf4c5e1c') {
    throw new RuntimeException('P16 corrected fixture source integrity failure.');
}
$tmp = sys_get_temp_dir() . '/P16_apply_representative_fixtures_v2_' . bin2hex(random_bytes(6)) . '.php';
file_put_contents($tmp, $source, LOCK_EX);
try {
    require $tmp;
} finally {
    @unlink($tmp);
}
