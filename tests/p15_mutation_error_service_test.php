<?php

declare(strict_types=1);

$source = file_get_contents(dirname(__DIR__) . '/app/Service/MutationErrorService.php');
$failed = 0;
$check = static function (bool $condition, string $label) use (&$failed): void {
    echo ($condition ? 'PASS' : 'FAIL') . ' - ' . $label . PHP_EOL;
    if (!$condition) $failed++;
};
$check(is_string($source) && str_contains($source, 'mutation_errors.log'), 'technical details use protected log');
$check(is_string($source) && str_contains($source, "'[REDACTED]'"), 'secret-like context is redacted');
$check(is_string($source) && !str_contains($source, "userMessage(string \$action, string \$errorId): string\n    {\n        return \$error->getMessage"), 'public message cannot return exception text');
$check(is_string($source) && str_contains($source, 'Код ошибки:'), 'public message contains correlation ID');
echo 'FAILED=' . $failed . PHP_EOL;
exit($failed === 0 ? 0 : 1);
