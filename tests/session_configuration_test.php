<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$source = file_get_contents(__DIR__ . '/../public/index.php');
if ($source === false) {
    fwrite(STDERR, "Cannot read public/index.php\n");
    exit(1);
}

$pass = 0;
$fail = 0;
$check = static function (string $name, bool $ok) use (&$pass, &$fail): void {
    if ($ok) {
        $pass++;
        echo "[PASS] {$name}\n";
        return;
    }
    $fail++;
    echo "[FAIL] {$name}\n";
};

$sessionStart = strpos($source, 'session_start();');
$envLoad = strpos($source, 'loadEnvFileNonOverwriting(');
$sessionName = strpos($source, "getenv('SESSION_COOKIE_NAME')");
$sessionNameApply = strpos($source, 'session_name($sessionName);');
$sessionPath = strpos($source, "getenv('SESSION_SAVE_PATH')");
$sessionPathApply = strpos($source, 'session_save_path($sessionSavePath);');

$check('session_start exists', $sessionStart !== false);
$check('environment is loaded before session_start', $envLoad !== false && $envLoad < $sessionStart);
$check('SESSION_COOKIE_NAME is read before session_start', $sessionName !== false && $sessionName < $sessionStart);
$check('session_name is applied before session_start', $sessionNameApply !== false && $sessionNameApply < $sessionStart);
$check('SESSION_SAVE_PATH is read before session_start', $sessionPath !== false && $sessionPath < $sessionStart);
$check('session_save_path is applied before session_start', $sessionPathApply !== false && $sessionPathApply < $sessionStart);
$check('cookie name remains optional', str_contains($source, "if (\$sessionName !== '')"));
$check('session path remains optional', str_contains($source, "if (\$sessionSavePath !== '')"));
$check('no ERPV2 cookie value is hardcoded', !str_contains($source, 'ERPV2SESSID'));
$check('no ERPV2 server path is hardcoded', !str_contains($source, 'private-storage/erpv2'));

printf("SESSION_CONFIGURATION: %d passed, %d failed\n", $pass, $fail);
exit($fail === 0 ? 0 : 1);
